<?php
/*get_departments.php*/

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

// Nuevo parámetro para decidir si obtener todos o solo los públicos
$get_all = isset($_GET['all']) && $_GET['all'] === 'true';

$response = ['status' => 'error', 'departments' => [], 'message' => 'No se pudieron cargar los departamentos.'];
$conn = null; // Inicializamos la conexión a null para el bloque finally

try {
    // Obtener la conexión usando la clase Database
    $conn = Database::getConnection();

    // Construir la consulta dinámicamente con prepared statements
    $sql = "SELECT id, name, description, is_public, created_at FROM departments";
    $bind_types = "";
    $bind_params = [];

    if (!$get_all) {
        $sql .= " WHERE is_public = ?"; // Usar placeholder
        $bind_types .= "i"; // 'i' para entero (boolean se trata como 0 o 1)
        $bind_params[] = 1; // 1 para true (público)
    }
    $sql .= " ORDER BY name ASC";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Error preparando consulta de departamentos: " . $conn->error);
    }

    // Si hay parámetros para bindear, hacerlo
    if (!empty($bind_params)) {
        $stmt->bind_param($bind_types, ...$bind_params);
    }

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        $departments = [];
        while ($row = $result->fetch_assoc()) {
            $row['is_public'] = (bool)$row['is_public']; // Convertir 0/1 a true/false
            $departments[] = $row;
        }
        $response['status'] = 'success';
        $response['departments'] = $departments;
        $response['message'] = 'Departamentos cargados.';
    } else {
        throw new Exception("Error al ejecutar la consulta: " . $stmt->error);
    }
    $stmt->close();

} catch (Exception $e) {
    // Capturar cualquier excepción de la conexión o de la lógica de negocio
    $response['message'] = "Error interno del servidor: " . $e->getMessage();
    error_log("Error en get_departments.php: " . $e->getMessage());
} finally {
    // Asegura que la conexión a la base de datos se cierre.
    if ($conn) {
        Database::closeConnection();
    }
}

echo json_encode($response);
exit;
?>