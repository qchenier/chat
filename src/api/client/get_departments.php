<?php
/**
 * API Endpoint: Get Departments (Client View)
 *
 * @description Este endpoint recupera la lista de departamentos públicos para el widget.
 *              Utiliza la arquitectura Core/Model para la lógica de negocio.
 * @version     2.0
 */

// Establecer cabeceras CORS para permitir la comunicación entre dominios
// Esto debe ser lo primero que el script envía.
// En producción, es más seguro y flexible leer los dominios permitidos desde un archivo de configuración.
header("Access-Control-Allow-Origin: http://cliente.test"); // Cambia esto por tu dominio de cliente real o usa '*' para desarrollo
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json");

// Manejar la solicitud pre-vuelo (preflight) OPTIONS que envían los navegadores para CORS
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit();
}

/**
 * Incluir el archivo de arranque (bootstrap) del Core de la aplicación.
 * Este archivo es responsable de registrar el autoloader de clases.
 * Usamos una ruta absoluta construida desde la ubicación de este archivo para máxima fiabilidad.
 * Desde /src/api/client/, subimos dos niveles (../..) para llegar a la raíz de /src/.
 */
try {
    $bootstrap_path = __DIR__ . '/../../Core/bootstrap.php';
    if (!file_exists($bootstrap_path)) {
        throw new Exception("Archivo de arranque no encontrado: " . $bootstrap_path);
    }
    require_once $bootstrap_path;
} catch (Throwable $e) {
    // Si el propio bootstrap falla, no podemos continuar.
    http_response_code(500);
    error_log("Error fatal al incluir bootstrap.php: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Error crítico de configuración del servidor.']);
    exit;
}


// Importar las clases necesarias con sus namespaces completos.
// El autoloader registrado en bootstrap.php se encargará de encontrar los archivos.
use SCI\Chat\Core\Config\Database;
use SCI\Chat\Core\Models\Department;

// Inicializar la respuesta por defecto
$response = ['status' => 'error', 'departments' => [], 'message' => 'No se pudieron cargar los departamentos.'];

try {
    // 1. Obtener conexión a la BD
    $conn = Database::getConnection();

    // 2. Crear instancia del Modelo
    $departmentModel = new Department($conn);
    
    // 3. Obtener los datos (solo públicos por defecto para el widget del cliente)
    $get_all_flag = isset($_GET['all']) && $_GET['all'] === 'true';
    $departments = $departmentModel->getDepartments($get_all_flag);
    
    // 4. Construir la respuesta de éxito
    $response = [
        'status' => 'success',
        'departments' => $departments,
        'message' => 'Departamentos cargados exitosamente.'
    ];
    
    // 5. Cerrar la conexión
    $conn->close();

} catch (Throwable $e) { // Capturar cualquier tipo de error (de conexión, de consulta, de clase no encontrada)
    http_response_code(500);
    $response['message'] = "Error interno del servidor.";
    error_log(
        "Error en get_departments.php: " . $e->getMessage() . 
        " en el archivo " . $e->getFile() . 
        " en la línea " . $e->getLine()
    );
}

// 6. Enviar la respuesta final como JSON
echo json_encode($response);
exit;
?>