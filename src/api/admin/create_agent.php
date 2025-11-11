<?php
/*create_agent.php*/

// Ya no necesitamos 'db_config.php'
// require_once 'db_config.php';

// --- MODIFICACIÓN CLAVE DE RUTA ---
// Requerir el archivo de la clase Database.
// Asumiendo que este script está en C:\laragon\www\sci\src\api\admin\
// y Database.php está en C:\laragon\www\sci\src\Core\Config\Database.php
require_once __DIR__ . '/../../Core/Config/Database.php';

// Importar la clase Database del namespace
use SCI\Chat\Core\Config\Database;

// Función de ayuda para subir archivos (podríamos moverla a un archivo de utilidades)
function handleFileUpload($file, $agentId) {
    // Definimos la ruta base del proyecto para el directorio de subidas.
    // Asumiendo que las fotos deben ir en C:\laragon\www\sci\public\agent_photos\
    $baseUploadDir = __DIR__ . '/../../../public/agent_photos/'; // Go up from src/api/admin/ to sci/, then into public/

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

        // Generar un nombre de archivo único
        $newFileName = 'agent_' . $agentId . '_' . time() . '.' . $fileExtension;
        $uploadPathFull = $baseUploadDir . $newFileName;
        
        // La ruta que se guardará en la base de datos (relativa a la raíz web, por ejemplo)
        // Si el directorio de fotos está en public/, la ruta guardada sería: agent_photos/nombre_archivo.jpg
        $dbPath = 'agent_photos/' . $newFileName;
        
        if (move_uploaded_file($file['tmp_name'], $uploadPathFull)) {
            return $dbPath; // Devolver la ruta relativa a la web para guardar en BD
        } else {
            throw new Exception("Error al mover el archivo subido.");
        }
    }
    return null; // No hay archivo o hubo un error no fatal
}

$message = "";
$message_type = "";
$conn = null; // Inicializamos la conexión a null para el bloque finally

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (
        isset($_POST['agent_name'], $_POST['agent_email'], $_POST['agent_password']) &&
        !empty(trim($_POST['agent_name'])) && !empty(trim($_POST['agent_email'])) && !empty(trim($_POST['agent_password']))
    ) {
        $agent_name = trim(htmlspecialchars($_POST['agent_name']));
        $agent_email = trim(htmlspecialchars($_POST['agent_email']));
        $agent_password_raw = trim($_POST['agent_password']);
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $agent_departments_ids = isset($_POST['agent_departments']) && is_array($_POST['agent_departments']) ? $_POST['agent_departments'] : [];

        if (!filter_var($agent_email, FILTER_VALIDATE_EMAIL)) {
            $message = "Error: Formato de email inválido.";
            $message_type = "error";
        } else {
            try {
                $conn = Database::getConnection(); // Obtener la conexión
                $conn->begin_transaction(); // Iniciar transacción

                try {
                    // Verificar si el email ya existe
                    $stmt_check = $conn->prepare("SELECT id FROM agents WHERE email = ?");
                    if (!$stmt_check) throw new Exception("Error preparando chequeo de email: " . $conn->error);
                    $stmt_check->bind_param("s", $agent_email);
                    $stmt_check->execute();
                    if ($stmt_check->get_result()->num_rows > 0) {
                        throw new Exception("Error: Ya existe un agente con ese email.");
                    }
                    $stmt_check->close();

                    // Insertar nuevo agente
                    $password_hash = password_hash($agent_password_raw, PASSWORD_DEFAULT);
                    $stmt_agent = $conn->prepare("INSERT INTO agents (name, email, password_hash, is_active) VALUES (?, ?, ?, ?)");
                    if (!$stmt_agent) throw new Exception("Error preparando inserción de agente: " . $conn->error);
                    
                    $stmt_agent->bind_param("sssi", $agent_name, $agent_email, $password_hash, $is_active);
                    if (!$stmt_agent->execute()) throw new Exception("Error creando agente: " . $stmt_agent->error);
                    
                    $new_agent_id = $stmt_agent->insert_id;
                    $stmt_agent->close();
                    
                    // Manejar la subida de foto DESPUÉS de tener el new_agent_id
                    $photoPath = handleFileUpload($_FILES['agent_photo'] ?? null, $new_agent_id);
                    if ($photoPath) {
                        $stmt_photo = $conn->prepare("UPDATE agents SET profile_picture_path = ? WHERE id = ?");
                        if(!$stmt_photo) throw new Exception("Error preparando actualización de foto: " . $conn->error);
                        $stmt_photo->bind_param("si", $photoPath, $new_agent_id);
                        if (!$stmt_photo->execute()) {
                             // Si falla la actualización de la foto, loggear pero no abortar la creación del agente
                             error_log("Error al actualizar la ruta de la foto para el agente ID {$new_agent_id}: " . $stmt_photo->error);
                        }
                        $stmt_photo->close();
                    }

                    // Asignar departamentos al agente
                    if (!empty($agent_departments_ids)) {
                        $stmt_dept = $conn->prepare("INSERT INTO agent_departments (agent_id, department_id) VALUES (?, ?)");
                        if (!$stmt_dept) throw new Exception("Error preparando asignación de depto: " . $conn->error);
                        foreach ($agent_departments_ids as $dept_id) {
                            $dept_id_int = (int)$dept_id;
                            $stmt_dept->bind_param("ii", $new_agent_id, $dept_id_int);
                            if (!$stmt_dept->execute()) {
                                // Si falla una asignación de departamento, loggear pero no necesariamente abortar todo
                                error_log("Error al asignar el departamento {$dept_id_int} al agente {$new_agent_id}: " . $stmt_dept->error);
                            }
                        }
                        $stmt_dept->close();
                    }

                    $conn->commit(); // Confirmar la transacción
                    $message = "Agente '" . $agent_name . "' creado exitosamente.";
                    $message_type = "success";

                } catch (Exception $e) {
                    if ($conn && $conn->in_transaction) { // Solo hacer rollback si una transacción está activa
                        $conn->rollback();
                    }
                    $message = $e->getMessage();
                    $message_type = "error";
                    error_log("Error en create_agent.php (Transaction): " . $e->getMessage());
                }

            } catch (Exception $e) {
                // Esto captura errores de Database::getConnection() o excepciones re-lanzadas desde el try interno
                $message = "Error de base de datos: " . $e->getMessage();
                $message_type = "error";
                error_log("Error en create_agent.php (Connection or Outer): " . $e->getMessage());
            } finally {
                // Asegura que la conexión se cierre si se abrió
                if ($conn) {
                    Database::closeConnection();
                }
            }
        }
    } else {
        $message = "Error: Nombre, email y contraseña son obligatorios.";
        $message_type = "error";
    }
    // Redirigir al panel de administración con el mensaje
    header("Location: /public/admin/agents.php?message=" . urlencode($message) . "&type=" . $message_type);
    exit();
}

// Si la solicitud no es POST, simplemente redirigir
header("Location: /public/admin/agents.php");
exit();
?>