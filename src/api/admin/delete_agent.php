<?php
/*delete_agent.php*/

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

$response = ['status' => 'error', 'message' => 'ID de agente no proporcionado.'];
$conn = null; // Inicializamos la conexión a null para el bloque finally

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['agent_id'])) {
    $agent_id = (int)$_POST['agent_id'];
    
    try {
        $conn = Database::getConnection(); // Obtener la conexión
        $conn->begin_transaction(); // Iniciar transacción

        try {
            // (Opcional) Borrar el archivo de la foto de perfil del servidor
            // Necesitamos la ruta completa del archivo para unlink()
            $stmt_pic = $conn->prepare("SELECT profile_picture_path FROM agents WHERE id = ?");
            if (!$stmt_pic) throw new Exception("Error preparando consulta de foto de agente: " . $conn->error);
            
            $stmt_pic->bind_param("i", $agent_id);
            $stmt_pic->execute();
            $result_pic = $stmt_pic->get_result();
            
            if ($row_pic = $result_pic->fetch_assoc()) {
                if (!empty($row_pic['profile_picture_path'])) {
                    // Construir la ruta completa del archivo en el sistema de archivos
                    // Asumimos que la ruta en BD es 'agent_photos/filename.jpg' y el directorio base es public/
                    $photo_file_path = __DIR__ . '/../../../public/' . $row_pic['profile_picture_path'];
                    
                    if (file_exists($photo_file_path)) {
                        if (!unlink($photo_file_path)) {
                            // No es un error fatal para el borrado del agente, pero lo logueamos
                            error_log("Advertencia: No se pudo eliminar el archivo de foto: " . $photo_file_path);
                        }
                    } else {
                        error_log("Advertencia: Archivo de foto no encontrado en disco: " . $photo_file_path);
                    }
                }
            }
            $stmt_pic->close();
            
            // Eliminar el agente. Las FKs configuradas con CASCADE se encargarán de borrar
            // las entradas relacionadas en agent_departments, etc.
            $stmt_delete = $conn->prepare("DELETE FROM agents WHERE id = ?");
            if (!$stmt_delete) throw new Exception("Error preparando borrado de agente: " . $conn->error);

            $stmt_delete->bind_param("i", $agent_id);
            if ($stmt_delete->execute()) {
                if ($stmt_delete->affected_rows > 0) {
                    $conn->commit(); // Confirmar la transacción si el agente fue borrado
                    $response['status'] = 'success';
                    $response['message'] = 'Agente eliminado exitosamente.';
                } else {
                    // Si affected_rows es 0, significa que el agente no existía
                    throw new Exception("No se encontró el agente o ya fue eliminado.");
                }
            } else {
                throw new Exception("Error al ejecutar el borrado del agente: " . $stmt_delete->error);
            }
            $stmt_delete->close();

        } catch (Exception $e) {
            if ($conn && $conn->in_transaction) { // Solo hacer rollback si una transacción está activa
                $conn->rollback();
            }
            throw $e; // Re-lanzar para que el catch externo lo maneje
        }

    } catch (Exception $e) {
        // Capturar cualquier excepción de la conexión o de la lógica de negocio
        $response['message'] = "Error: " . $e->getMessage();
        error_log("Error en delete_agent.php: " . $e->getMessage());
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