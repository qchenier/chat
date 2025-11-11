<?php
/*update_agent.php*/

// Ya no necesitamos 'db_config.php'
// require_once 'db_config.php';

// --- MODIFICACIÓN CLAVE DE RUTA ---
// Requerir el archivo de la clase Database.
// Asumiendo que este script está en C:\laragon\www\sci\src\api\admin\
// y Database.php está en C:\laragon\www\sci\src\Core\Config\Database.php
require_once __DIR__ . '/../../Core/Config/Database.php';

// Importar la clase Database del namespace
use SCI\Chat\Core\Config\Database;

// Función de ayuda para subir archivos
function handleFileUpload($file, $agentId, $conn) {
    // Definimos la ruta base del proyecto para el directorio de subidas.
    // Asumiendo que las fotos deben ir en C:\laragon\www\sci\public\agent_photos\
    $baseUploadDir = __DIR__ . '/../../../public/agent/images/agent_photos/'; // Go up from src/api/admin/ to sci/, then into public/

    if (isset($file) && $file['error'] === UPLOAD_ERR_OK) {
        if (!is_dir($baseUploadDir)) {
            if (!mkdir($baseUploadDir, 0775, true)) { // Usar 0775 para permisos
                throw new Exception("No se pudo crear el directorio de fotos: " . $baseUploadDir);
            }
        }

        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($fileExtension, $allowedExtensions)) {
            throw new Exception("Tipo de archivo no permitido. Solo se aceptan JPG, PNG, GIF.");
        }

        // Obtener la ruta de la foto antigua para borrarla
        $oldPhotoPath = null;
        $stmt_old_pic = $conn->prepare("SELECT profile_picture_path FROM agents WHERE id = ?");
        if ($stmt_old_pic) {
            $stmt_old_pic->bind_param("i", $agentId);
            $stmt_old_pic->execute();
            $result_old_pic = $stmt_old_pic->get_result();
            if ($row_old_pic = $result_old_pic->fetch_assoc()) {
                $oldPhotoPath = $row_old_pic['profile_picture_path'];
            }
            $stmt_old_pic->close();
        }

        // Generar un nombre de archivo único
        $newFileName = 'agent_' . $agentId . '_' . time() . '.' . $fileExtension;
        $uploadPathFull = $baseUploadDir . $newFileName;
        $dbPath = 'agent_photos/' . $newFileName; // Ruta relativa para la BD

        if (move_uploaded_file($file['tmp_name'], $uploadPathFull)) {
            // Borrar la foto antigua si existe y no es la por defecto o vacía
            if ($oldPhotoPath && !empty($oldPhotoPath) && strpos($oldPhotoPath, 'default.png') === false) {
                $fullOldPath = __DIR__ . '/../../../public/' . $oldPhotoPath;
                if (file_exists($fullOldPath)) {
                    if (!unlink($fullOldPath)) {
                        error_log("Advertencia: No se pudo eliminar la foto antigua: " . $fullOldPath);
                    }
                }
            }
            return $dbPath; // Devolver la nueva ruta para guardar en BD
        } else {
            throw new Exception("Error al mover el archivo subido.");
        }
    }
    return null; // No hay archivo o hubo un error no fatal (ej. no se envió un archivo)
}


$message = "";
$message_type = "";
$conn = null; // Inicializamos la conexión a null para el bloque finally

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['agent_id'], $_POST['agent_name'], $_POST['agent_email'])) {
        $agent_id = (int)$_POST['agent_id'];
        $agent_name = trim(htmlspecialchars($_POST['agent_name']));
        $agent_email = trim(htmlspecialchars($_POST['agent_email']));
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $agent_departments_ids = isset($_POST['agent_departments']) && is_array($_POST['agent_departments']) ? $_POST['agent_departments'] : [];

        try {
            $conn = Database::getConnection(); // Obtener la conexión
            $conn->begin_transaction(); // Iniciar transacción

            try {
                // Verificar si el nuevo email ya existe en otro agente
                $stmt_check_email = $conn->prepare("SELECT id FROM agents WHERE email = ? AND id != ?");
                if (!$stmt_check_email) throw new Exception("Error preparando chequeo de email: " . $conn->error);
                $stmt_check_email->bind_param("si", $agent_email, $agent_id);
                $stmt_check_email->execute();
                if ($stmt_check_email->get_result()->num_rows > 0) {
                    throw new Exception("Error: Ya existe otro agente con ese email.");
                }
                $stmt_check_email->close();


                // Construir la consulta de actualización dinámicamente
                $sql_parts = [];
                $types = "";
                $params = [];

                $sql_parts[] = "name = ?"; $types .= "s"; $params[] = $agent_name;
                $sql_parts[] = "email = ?"; $types .= "s"; $params[] = $agent_email;
                $sql_parts[] = "is_active = ?"; $types .= "i"; $params[] = $is_active;

                // Actualizar contraseña solo si se proporcionó una nueva
                if (!empty(trim($_POST['agent_password']))) {
                    $password_hash = password_hash(trim($_POST['agent_password']), PASSWORD_DEFAULT);
                    $sql_parts[] = "password_hash = ?"; $types .= "s"; $params[] = $password_hash;
                }

                // Subir foto de perfil solo si se proporcionó una nueva
                $newPhotoPath = handleFileUpload($_FILES['agent_photo'] ?? null, $agent_id, $conn); // Pasamos $conn
                if ($newPhotoPath) {
                    $sql_parts[] = "profile_picture_path = ?"; $types .= "s"; $params[] = $newPhotoPath;
                }

                $sql = "UPDATE agents SET " . implode(', ', $sql_parts) . " WHERE id = ?";
                $types .= "i"; // Tipo para el agent_id al final
                $params[] = $agent_id; // Valor para el agent_id al final

                $stmt_update = $conn->prepare($sql);
                if (!$stmt_update) throw new Exception("Error preparando actualización de agente: " . $conn->error);
                
                // Usar el operador splat (...) para pasar los parámetros dinámicamente
                $stmt_update->bind_param($types, ...$params);
                if (!$stmt_update->execute()) throw new Exception("Error actualizando agente: " . $stmt_update->error);
                $stmt_update->close();

                // Actualizar los departamentos: borrar los antiguos e insertar los nuevos
                $stmt_delete_depts = $conn->prepare("DELETE FROM agent_departments WHERE agent_id = ?");
                if (!$stmt_delete_depts) throw new Exception("Error preparando borrado de departamentos: " . $conn->error);
                $stmt_delete_depts->bind_param("i", $agent_id);
                if (!$stmt_delete_depts->execute()) { // Added check for execute
                    throw new Exception("Error al borrar departamentos anteriores: " . $stmt_delete_depts->error);
                }
                $stmt_delete_depts->close();

                if (!empty($agent_departments_ids)) {
                    $stmt_dept = $conn->prepare("INSERT INTO agent_departments (agent_id, department_id) VALUES (?, ?)");
                    if (!$stmt_dept) throw new Exception("Error preparando asignación de departamento: " . $conn->error);
                    foreach ($agent_departments_ids as $dept_id) {
                        $dept_id_int = (int)$dept_id;
                        $stmt_dept->bind_param("ii", $agent_id, $dept_id_int);
                        if (!$stmt_dept->execute()) { // Added check for execute
                             error_log("Error al asignar el departamento {$dept_id_int} al agente {$agent_id}: " . $stmt_dept->error);
                             // Consider if this should be a critical error or just logged
                        }
                    }
                    $stmt_dept->close();
                }

                $conn->commit(); // Confirmar la transacción
                $message = "Agente actualizado exitosamente.";
                $message_type = "success";

            } catch (Exception $e) {
                if ($conn && $conn->in_transaction) { // Solo hacer rollback si una transacción está activa
                    $conn->rollback();
                }
                $message = $e->getMessage();
                $message_type = "error";
                error_log("Error en update_agent.php (Transaction): " . $e->getMessage());
            }

        } catch (Exception $e) {
            // Esto captura errores de Database::getConnection() o excepciones re-lanzadas desde el try interno
            $message = "Error de base de datos: " . $e->getMessage();
            $message_type = "error";
            error_log("Error en update_agent.php (Connection or Outer): " . $e->getMessage());
        } finally {
            // Asegura que la conexión se cierre si se abrió
            if ($conn) {
                Database::closeConnection();
            }
        }
    } else {
        $message = "Datos incompletos para actualizar.";
        $message_type = "error";
    }
header("Location: /public/admin/agents.php?message=" . urlencode($message) . "&type=" . $message_type);
exit();
}
header("Location: /public/admin/agents.php");
exit();
?>