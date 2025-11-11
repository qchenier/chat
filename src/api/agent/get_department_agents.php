<?php
// Ya no necesitamos 'db_config.php' directamente.
// require_once 'db_config.php';

// Ajusta la ruta a tu archivo Database.php.
// Asumiendo que este script está en 'public/api/' (o similar)
// y Database.php está en 'src/Core/Config/Database.php'
// La ruta sería:
require_once __DIR__ . '/../../Core/Config/Database.php';

// Ahora que el archivo está incluido, podemos usar el namespace y la clase.
use SCI\Chat\Core\Config\Database;

// session_start(); // No es estrictamente necesario aquí si no se valida sesión para esta acción específica

header('Content-Type: application/json');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE); // Quita E_NOTICE para producción
ini_set('display_errors', 0); // Deshabilita la muestra de errores en producción

$response = ['status' => 'error', 'agents' => [], 'message' => 'ID de departamento no proporcionado o inválido.'];

$conn = null; // Inicializamos la conexión a null para el bloque finally

if (isset($_GET['department_id']) && filter_var($_GET['department_id'], FILTER_VALIDATE_INT)) {
    $department_id = (int)$_GET['department_id'];

    try {
        // Obtenemos la conexión usando la clase Database
        $conn = Database::getConnection();

        // Seleccionar agentes activos del departamento especificado
        $stmt = $conn->prepare("SELECT a.id, a.name
                                 FROM agents a
                                 JOIN agent_departments ad ON a.id = ad.agent_id
                                 WHERE ad.department_id = ? AND a.is_active = 1
                                 ORDER BY a.name ASC");
        if (!$stmt) {
            // Usa el error de la conexión si la preparación falla
            throw new Exception("Error preparando consulta de agentes por departamento: " . $conn->error);
        }
        $stmt->bind_param("i", $department_id);

        if ($stmt->execute()) {
            $result = $stmt->get_result();
            $agents = [];
            while ($row = $result->fetch_assoc()) {
                $agents[] = $row;
            }
            $response['status'] = 'success';
            $response['agents'] = $agents;
            $response['message'] = 'Agentes del departamento cargados.';
        } else {
            // Usa el error del statement si la ejecución falla
            throw new Exception("Error ejecutando consulta de agentes: " . $stmt->error);
        }
        $stmt->close();

    } catch (Exception $e) {
        // Captura cualquier excepción lanzada por Database::getConnection() o por las operaciones de la BD
        $response['message'] = $e->getMessage();
        error_log("Error en script de agentes por departamento: " . $e->getMessage());
    } finally {
        // Asegura que la conexión se cierre si se abrió
        if ($conn) {
            Database::closeConnection();
        }
    }
}

echo json_encode($response);
exit;
?>