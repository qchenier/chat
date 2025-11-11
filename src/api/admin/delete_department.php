<?php
/*delete_department.php*/

// Ya no necesitamos 'db_config.php'
// require_once 'db_config.php';

// --- MODIFICACIÓN CLAVE DE RUTA ---
// Requerir el archivo de la clase Database.
// Asumiendo que este script está en C:\laragon\www\sci\src\api\admin\
// y Database.php está en C:\laragon\www\sci\src\Core\Config\Database.php
require_once __DIR__ . '/../../Core/Config/Database.php';

// Importar la clase Database del namespace
use SCI\Chat\Core\Config\Database;

header('Content-Type: application/json');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE); // Mantén estos para ver errores de desarrollo, deshabilita en prod
ini_set('display_errors', 0); // En producción, errores a logs

$response = ['status' => 'error', 'message' => 'ID de departamento no proporcionado.'];
$conn = null; // Inicializamos la conexión a null para el bloque finally

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['dept_id'])) {
    $dept_id = (int)$_POST['dept_id'];
    
    try {
        $conn = Database::getConnection(); // Obtener la conexión usando la clase Database

        // En la tabla 'conversations', la FK department_id es ON DELETE SET NULL.
        // En la tabla 'agent_departments', la FK es ON DELETE CASCADE.
        // Por lo tanto, es seguro borrar el departamento. Las asignaciones de agentes se eliminarán
        // y las conversaciones existentes apuntarán a un departamento nulo.
        
        $stmt = $conn->prepare("DELETE FROM departments WHERE id = ?");
        if (!$stmt) { // Usamos un throw aquí para capturar el error de prepare
            throw new Exception("Error al preparar la consulta de eliminación: " . $conn->error);
        }
        
        $stmt->bind_param("i", $dept_id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $response['status'] = 'success';
                $response['message'] = 'Departamento eliminado exitosamente.';
            } else {
                $response['message'] = 'No se encontró el departamento o ya fue eliminado.';
            }
        } else {
            throw new Exception('Error al eliminar el departamento: ' . $stmt->error);
        }
        $stmt->close();

    } catch (Exception $e) {
        // Capturar cualquier excepción de la conexión o de la lógica de negocio
        $response['message'] = "Error: " . $e->getMessage();
        error_log("Error en delete_department.php: " . $e->getMessage());
    } finally {
        // Asegura que la conexión a la base de datos se cierre.
        if ($conn) {
            Database::closeConnection();
        }
    }
}

echo json_encode($response);
exit;
?>