<?php
/*create_department.php*/

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
    if (isset($_POST['dept_name']) && !empty(trim($_POST['dept_name']))) {
        $dept_name = trim(htmlspecialchars($_POST['dept_name']));
        $dept_description = isset($_POST['dept_description']) ? trim(htmlspecialchars($_POST['dept_description'])) : null;
        $is_public = isset($_POST['is_public']) && $_POST['is_public'] === 'on' ? 1 : 0;

        try {
            $conn = Database::getConnection(); // Obtener la conexión usando la clase Database

            // Iniciar transacción para asegurar atomicity (opcional pero buena práctica para operaciones de escritura)
            $conn->begin_transaction();

            try {
                // Verificar si el departamento ya existe
                $stmt_check = $conn->prepare("SELECT id FROM departments WHERE name = ?");
                if (!$stmt_check) {
                    throw new Exception("Error preparando chequeo de departamento: " . $conn->error);
                }
                $stmt_check->bind_param("s", $dept_name);
                $stmt_check->execute();
                if ($stmt_check->get_result()->num_rows > 0) {
                    throw new Exception("Error: Ya existe un departamento con ese nombre.");
                }
                $stmt_check->close();

                // Insertar nuevo departamento
                $stmt_insert = $conn->prepare("INSERT INTO departments (name, description, is_public) VALUES (?, ?, ?)");
                if (!$stmt_insert) {
                    throw new Exception("Error preparando inserción de departamento: " . $conn->error);
                }
                $stmt_insert->bind_param("ssi", $dept_name, $dept_description, $is_public);
                
                if (!$stmt_insert->execute()) {
                    throw new Exception("Error al crear el departamento: " . $stmt_insert->error);
                }
                $stmt_insert->close();

                $conn->commit(); // Confirmar la transacción
                $message = "Departamento '" . $dept_name . "' creado exitosamente.";
                $message_type = "success";

            } catch (Exception $e) {
                if ($conn && $conn->in_transaction) { // Solo hacer rollback si una transacción está activa
                    $conn->rollback();
                }
                throw $e; // Re-lanzar para que el catch externo lo maneje
            }

        } catch (Exception $e) {
            // Capturar cualquier excepción de la conexión o de la lógica de negocio
            $message = "Error de base de datos: " . $e->getMessage();
            $message_type = "error";
            error_log("Error en create_department.php: " . $e->getMessage());
        } finally {
            // Asegura que la conexión a la base de datos se cierre.
            if ($conn) {
                Database::closeConnection();
            }
        }
    } else {
        $message = "Error: El nombre del departamento es obligatorio.";
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