    <?php
/**
 * SCI-CHAT-SYSTEM - Endpoint de Streaming de Mensajes (SSE)
 *
 * @file        stream_messages.php
 * @description Mantiene una conexión HTTP abierta y envía eventos en tiempo real
 *              (SSE) con nuevos mensajes para una conversación específica.
 */

// --- Arranque y Configuración ---
// La ruta es relativa a /src/streams/
//require_once __DIR__ . '/../api/common/cors_config.php';
//require_once __DIR__ . '/../Core/bootstrap.php';
|
// --- Arranque y Configuración ---

// CORRECCIÓN 1: cors_config.php está en la misma carpeta.
require_once __DIR__ . '/cors_config.php'; 

// CORRECCIÓN 2: bootstrap.php está dos carpetas más arriba, dentro de Core.
require_once __DIR__ . '/../../Core/bootstrap.php';

// ... el resto del código ...

// Importar las clases que se usarán
use SCI\Chat\Core\Config\Database;
use SCI\Chat\Core\Models\Conversation;
use SCI\Chat\Core\Models\Message;

// --- Cabeceras Específicas de SSE ---
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
set_time_limit(0);

// --- Validación de Parámetros ---
$user_email = isset($_GET['chat_id']) ? trim(htmlspecialchars($_GET['chat_id'])) : null;
if (!$user_email || !filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
    error_log("SSE Stream: Conexión denegada - chat_id (email) inválido o no proporcionado.");
    http_response_code(400);
    exit;
}

// Determinar el timestamp inicial para la consulta, combinando el enviado por el navegador y por nuestro JS.
$browser_last_event_id = isset($_SERVER["HTTP_LAST_EVENT_ID"]) && is_numeric($_SERVER["HTTP_LAST_EVENT_ID"]) ? (int)$_SERVER["HTTP_LAST_EVENT_ID"] : 0;
$js_initial_timestamp = isset($_GET['initial_ts']) && is_numeric($_GET['initial_ts']) ? (int)$_GET['initial_ts'] : 0;
$current_last_timestamp = max($browser_last_event_id, $js_initial_timestamp);

error_log("SSE Stream: Conexión iniciada para {$user_email}. Usando current_last_timestamp: {$current_last_timestamp}");

// --- Lógica Principal ---
$conn = null; // Definir fuera para acceso en `finally`
try {
    $conn = Database::getConnection();
    
    $conversationModel = new Conversation($conn);
    $messageModel = new Message($conn);

    // Obtener el ID de la conversación
    $conversation = $conversationModel->findByEmail($user_email);
    if (!$conversation) {
        throw new Exception("Conversación no encontrada para el email: " . $user_email);
    }
    $conversation_id = $conversation['id'];

    // Bucle principal para enviar eventos
    while (true) {
        if (connection_aborted()) {
            error_log("SSE Stream: Cliente desconectado para convo_id " . $conversation_id);
            break;
        }

        // Usar el Modelo para obtener nuevos mensajes
        $new_messages_from_db = $messageModel->getNewMessagesForConversation($conversation_id, $current_last_timestamp);
        
        if (!empty($new_messages_from_db)) {
            $messages_to_send = [];
            foreach ($new_messages_from_db as $msg_row) {
                // Formatear mensaje para el cliente JS
                $messages_to_send[] = [
                    // El id del mensaje de la BD no es crucial para el cliente, pero el timestamp sí lo es.
                    'sender_type' => $msg_row['sender_type'],
                    'sender_name' => $msg_row['sender_name'],
                    'message' => $msg_row['message_text'],
                    'timestamp' => (int)$msg_row['timestamp_unix']
                ];
            }
            
            // Enviar cada mensaje como un evento separado
            foreach ($messages_to_send as $msg) {
                echo "id: " . $msg['timestamp'] . "\n";
                echo "event: new_message\n";
                echo "data: " . json_encode($msg) . "\n\n";
                
                // Actualizar el timestamp con el del último mensaje que estamos a punto de enviar
                $current_last_timestamp = $msg['timestamp'];
            }
            
            // Forzar el envío de la salida al navegador
            if (ob_get_level() > 0) ob_flush();
            flush();
            
            error_log("SSE Stream: Enviados " . count($messages_to_send) . " mensajes para convo_id " . $conversation_id . ". Nuevo timestamp: " . $current_last_timestamp);
        } else {
            // Enviar un comentario de heartbeat para mantener la conexión activa
            echo ":heartbeat\n\n";
            if (ob_get_level() > 0) ob_flush();
            flush();
        }

        // Pausar la ejecución antes de la siguiente consulta
        sleep(2); 
    }

} catch (Throwable $e) {
    error_log("Error fatal en stream_messages.php para {$user_email}: " . $e->getMessage());
} finally {
    if (isset($conn) && $conn->ping()) {
        $conn->close();
    }
    error_log("SSE stream: Script terminado para " . $user_email);
}
?>