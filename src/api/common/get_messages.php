<?php
/**
 * API Endpoint: Get Messages
 *
 * @description Obtiene los mensajes nuevos de una conversación específica
 *              basado en el email del usuario y el timestamp del último mensaje recibido.
 */

// 1. Incluir configuración CORS y el bootstrap del Core
require_once __DIR__ . '/../common/cors_config.php';
require_once __DIR__ . '/../../Core/bootstrap.php'; 

// 2. Importar las clases necesarias
use SCI\Chat\Core\Config\Database;
use SCI\Chat\Core\Models\Conversation;
use SCI\Chat\Core\Models\Message;

// 3. Inicializar la respuesta
$response = ['status' => 'error', 'messages' => [], 'message' => 'Chat ID (email) no proporcionado o inválido.'];

// 4. Procesar la solicitud
if (isset($_GET['chat_id']) && !empty(trim($_GET['chat_id']))) {
    
    $user_email = trim(htmlspecialchars($_GET['chat_id']));
    $last_timestamp_unix = isset($_GET['last_timestamp']) ? (int)$_GET['last_timestamp'] : 0;

    if (!filter_var($user_email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Formato de Chat ID (email) inválido.';
    } else {
        $conn = null;
        try {
            $conn = Database::getConnection();
            
            $conversationModel = new Conversation($conn);
            $messageModel = new Message($conn);

            // 1. Encontrar la conversación por el email del usuario
            $conversation = $conversationModel->findByEmail($user_email);

            if (!$conversation) {
                // No es un error fatal, simplemente no hay mensajes que devolver.
                $response = [
                    'status' => 'success',
                    'messages' => [],
                    'message' => 'Conversación no encontrada.'
                ];
            } else {
                $conversation_id = $conversation['id'];

                // 2. Obtener los mensajes nuevos usando el modelo
                $new_messages_from_db = $messageModel->getNewMessagesForConversation($conversation_id, $last_timestamp_unix);
                
                // 3. Formatear la respuesta para el frontend
                $formatted_messages = [];
                foreach ($new_messages_from_db as $msg_row) {
                    $formatted_messages[] = [
                        'sender_type' => $msg_row['sender_type'],
                        'sender_name' => $msg_row['sender_name'],
                        'message' => $msg_row['message_text'],
                        'timestamp' => (int)$msg_row['timestamp_unix']
                    ];
                }

                $response['status'] = 'success';
                $response['messages'] = $formatted_messages;
                $response['message'] = count($formatted_messages) > 0 ? 'Nuevos mensajes encontrados.' : 'No hay mensajes nuevos.';
            }

        } catch (Throwable $e) {
            http_response_code(500);
            $response['message'] = "Error interno del servidor al obtener mensajes.";
            error_log("Error en get_messages.php: " . $e->getMessage());
        } finally {
            if ($conn) $conn->close();
        }
    }
}

// 5. Enviar la respuesta final
    echo json_encode($response);
    exit;
?>