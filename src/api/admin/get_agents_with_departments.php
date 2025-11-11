<?php
/*get_agents_with_departments.php*/

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
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 0); // En producción, errores a logs

$response = ['status' => 'error', 'agents' => [], 'message' => 'No se pudieron cargar los agentes.'];
$conn = null; // Inicializamos la conexión a null para el bloque finally

try {
    // Obtener la conexión usando la clase Database
    $conn = Database::getConnection();

    // Consulta para obtener agentes y los nombres/IDs de sus departamentos concatenados
    // Esta consulta no necesita parámetros, así que no usaremos prepared statements con bind_param
    // La usamos directamente con query() pero dentro del bloque try-catch.
    $sql = "SELECT
                a.id,
                a.name,
                a.email,
                a.is_active,
                a.profile_picture_path,
                a.created_at,
                GROUP_CONCAT(d.name SEPARATOR ', ') as department_names,
                GROUP_CONCAT(d.id) as department_ids
            FROM agents a
            LEFT JOIN agent_departments ad ON a.id = ad.agent_id
            LEFT JOIN departments d ON ad.department_id = d.id
            GROUP BY a.id
            ORDER BY a.name ASC";

    $result = $conn->query($sql);

    if ($result) {
        $agents = [];
        while ($row = $result->fetch_assoc()) {
            // Convertir 0/1 a true/false para is_active
            $row['is_active'] = (bool)$row['is_active'];
            // Convertir la cadena de IDs de departamento a un array de enteros
            if ($row['department_ids']) {
                $row['department_ids'] = array_map('intval', explode(',', $row['department_ids']));
            } else {
                $row['department_ids'] = [];
            }
            $agents[] = $row;
        }
        $response['status'] = 'success';
        $response['agents'] = $agents;
        $response['message'] = 'Agentes cargados.';
    } else {
        // Capturar errores de la consulta si la ejecución falla
        throw new Exception('Error al ejecutar la consulta de agentes: ' . $conn->error);
    }

} catch (Exception $e) {
    // Capturar cualquier excepción de la conexión o de la lógica de negocio
    $response['message'] = "Error interno del servidor: " . $e->getMessage();
    error_log("Error en get_agents_with_departments.php: " . $e->getMessage());
} finally {
    // Asegura que la conexión a la base de datos se cierre.
    if ($conn) {
        Database::closeConnection();
    }
}

echo json_encode($response);
exit;
?>