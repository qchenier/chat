<?php
/*update_department.php*/

// Ya no necesitamos 'db_config.php'
// require_once 'db_config.php';

// --- MODIFICACIÓN CLAVE DE RUTA ---
// Requerir el archivo de la clase Database.
// Asumiendo que este script está en C:\laragon\www\sci\src\api\admin\
// y Database.php está en C:\laragon\www\sci\src\Core\Config\Database.php
require_once __DIR__ . '/../../Core/Config/Database.php';

// Importar la clase Database del namespace
use SCI\Chat\Core\Config\Database;

$message = "";
$message_type = "";
$conn = null; // Inicializamos la conexión a null para el bloque finally

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (
        isset($_POST['dept_id']) && !empty((int)$_POST['dept_id']) &&
        isset($_POST['dept_name']) && !empty(trim($_POST['dept_name']))
    ) {
        $dept_id = (int)$_POST['dept_id'];
        $dept_name = trim(htmlspecialchars($_POST['dept_name']));
        $dept_description = isset($_POST['dept_description']) ? trim(htmlspecialchars($_POST['dept_description'])) : null;
        $is_public = isset($_POST['is_public']) && $_POST['is_public'] === 'on' ? 1 : 0;

        try {
            $conn = Database::getConnection(); // Obtener la conexión usando la clase Database

            // Verificar que no estemos duplicando el nombre de OTRO departamento
            $stmt_check = $conn->prepare("SELECT id FROM departments WHERE name = ? AND id != ?");
            if (!$stmt_check) {
                throw new Exception("Error preparando chequeo de nombre de departamento: " . $conn->error);
            }
            $stmt_check->bind_param("si", $dept_name, $dept_id);
            $stmt_check->execute();
            if ($stmt_check->get_result()->num_rows > 0) {
                $message = "Error: Ya existe otro departamento con ese nombre.";
                $message_type = "error";
            } else {
                $stmt_check->close(); // Close the check statement before preparing the update one

                $stmt_update = $conn->prepare("UPDATE departments SET name = ?, description = ?, is_public = ? WHERE id = ?");
                if (!$stmt_update) { // Usamos un throw aquí para capturar el error de prepare
                    throw new Exception("Error al preparar la consulta de actualización: " . $conn->error);
                }
                
                $stmt_update->bind_param("ssii", $dept_name, $dept_description, $is_public, $dept_id);
                
                if ($stmt_update->execute()) {
                    // Check affected_rows to see if any change was actually made
                    if ($stmt_update->affected_rows > 0) {
                        $message = "Departamento actualizado exitosamente.";
                        $message_type = "success";
                    } else {
                        // This can happen if no data was actually changed, which is not an error
                        $message = "Departamento actualizado. No se detectaron cambios, o el departamento no existe.";
                        $message_type = "info"; // Use info or success depending on desired UX
                    }
                } else {
                    throw new Exception("Error al actualizar el departamento: " . $stmt_update->error);
                }
                $stmt_update->close();
            }

        } catch (Exception $e) {
            // Capturar cualquier excepción de la conexión o de la lógica de negocio
            $message = "Error: " . $e->getMessage();
            $message_type = "error";
            error_log("Error en update_department.php: " . $e->getMessage());
        } finally {
            // Asegura que la conexión a la base de datos se cierre.
            if ($conn) {
                Database::closeConnection();
            }
        }
    } else {
        $message = "Error: Faltan datos para actualizar (ID o Nombre).";
        $message_type = "error";
    }
    // Redirigir al panel de administración con el mensaje
    header("Location: /public/admin/departments.php?message=" . urlencode($message) . "&type=" . $message_type);
    exit();
}

// Si la solicitud no es POST, simplemente redirigir
header("Location: /public/admin/departments.php");
exit();
?>