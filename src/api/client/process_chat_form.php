<?php
/**
 * API Endpoint: Process Chat Form (Iniciar Conversación)
 *
 * @description Recibe los datos iniciales del usuario desde el widget,
 *              crea o actualiza una conversación en la base de datos y
 *              devuelve una respuesta de éxito o error.
 * @version     2.0
 */

// --- Arranque y Configuración ---

// 1. Incluir la configuración CORS PRIMERO para que las cabeceras se envíen siempre.
// Esta es la corrección crucial que faltaba.
require_once __DIR__ . '/../common/cors_config.php';

// 2. Incluir el bootstrap que registra el autoloader y carga el Core.
require_once __DIR__ . '/../../Core/bootstrap.php'; 

// 3. Importar las clases que se usarán.
use SCI\Chat\Core\Config\Database;
// En el futuro, la lógica de la BD se moverá a un modelo Conversation.
// use SCI\Chat\Core\Models\Conversation;

// --- Inicialización de Respuesta ---
$response = ['status' => 'error', 'message' => 'Método no permitido o datos incompletos.'];

// --- Lógica del Endpoint ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validar y sanitizar los datos de entrada
    $nombre = isset($_POST['nombre']) ? trim(htmlspecialchars($_POST['nombre'])) : '';
    $apellidos = isset($_POST['apellidos']) ? trim(htmlspecialchars($_POST['apellidos'])) : '';
    $correo = isset($_POST['correo']) ? trim(htmlspecialchars($_POST['correo'])) : '';
    $department_id = isset($_POST['department_id']) ? (int)$_POST['department_id'] : null;

    if (empty($nombre) || empty($apellidos) || empty($correo) || empty($department_id)) {
        $response['message'] = 'Todos los campos (excepto empresa) son obligatorios.';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'El formato del correo electrónico no es válido.';
    } else {
        try {
            $conn = Database::getConnection();
            $fullName = $nombre . ' ' . $apellidos;
            $initial_status = 'pending_agent';

            $stmt = $conn->prepare(
                "INSERT INTO conversations (user_email, user_name, department_id, status, last_message_at) 
                 VALUES (?, ?, ?, ?, NOW())
                 ON DUPLICATE KEY UPDATE 
                     user_name = VALUES(user_name), 
                     department_id = VALUES(department_id), 
                     status = VALUES(status), 
                     updated_at = NOW(), 
                     last_message_at = NOW()"
            );
            
            if (!$stmt) throw new Exception("Error al preparar la consulta: " . $conn->error);
            
            $stmt->bind_param("ssis", $correo, $fullName, $department_id, $initial_status);
            
            if (!$stmt->execute()) throw new Exception("Error al registrar la conversación: " . $stmt->error);
            
            $conversation_id = $stmt->insert_id;
            if ($conversation_id == 0 && $stmt->affected_rows > 0) {
                $result_id_stmt = $conn->prepare("SELECT id FROM conversations WHERE user_email = ?");
                if ($result_id_stmt) {
                    $result_id_stmt->bind_param("s", $correo);
                    $result_id_stmt->execute();
                    $result_id_obj = $result_id_stmt->get_result();
                    if($row_id = $result_id_obj->fetch_assoc()){
                        $conversation_id = $row_id['id'];
                    }
                    $result_id_stmt->close();
                }
            }
            $stmt->close();
            
            $response = [
                'status' => 'success',
                'message' => 'Información recibida. Iniciando chat...',
                'conversation_id' => $conversation_id
            ]; 
            $conn->close();

        } catch (Throwable $e) {
            http_response_code(500);
            $response['message'] = "Error interno del servidor al procesar la solicitud.";
            error_log("Error en process_chat_form.php: " . $e->getMessage());
        }
    }
}

// --- Enviar Respuesta ---
// La cabecera Content-Type: application/json ya se establece en cors_config.php
echo json_encode($response);
exit;
?>