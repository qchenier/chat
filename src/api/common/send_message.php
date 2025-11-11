<?php
/**
 * API Endpoint: Send Message
 *
 * @description Recibe un nuevo mensaje, lo guarda en la BD y actualiza la conversación.
 * @version     2.1
 */

// --- 1. ARRANQUE Y CONFIGURACIÓN ---
require_once __DIR__ . '/cors_config.php';
require_once __DIR__ . '/../../Core/bootstrap.php'; 

use SCI\Chat\Core\Config\Database;

// --- 2. INICIALIZACIÓN DE RESPUESTA ---
$response = ['status' => 'error', 'message' => 'Datos incompletos o inválidos.'];

// --- 3. LÓGICA DEL ENDPOINT ---
if (
    isset($_POST['chat_id'], $_POST['sender_name'], $_POST['sender_type'], $_POST['message']) &&
    !empty(trim($_POST['chat_id'])) && !empty(trim($_POST['message']))
) {
    $user_email_for_chat_id = trim(htmlspecialchars($_POST['chat_id']));
    $sender_name = htmlspecialchars($_POST['sender_name']);
    $sender_type = htmlspecialchars($_POST['sender_type']);
    $message_text = htmlspecialchars($_POST['message']);
    $timestamp_unix = time();

    try {
        $conn = Database::getConnection();
        $conn->begin_transaction();

        // 1. Obtener el ID y estado de la conversación
        $stmt_get_convo = $conn->prepare("SELECT id, status FROM conversations WHERE user_email = ?");
        if (!$stmt_get_convo) throw new Exception("Error al preparar la consulta de conversación: " . $conn->error);
        
        $stmt_get_convo->bind_param("s", $user_email_for_chat_id);
        $stmt_get_convo->execute();
        $result_convo = $stmt_get_convo->get_result();
        
        if ($row_convo = $result_convo->fetch_assoc()) {
            $conversation_id = $row_convo['id'];
            $current_convo_status = $row_convo['status'];
        } else {
            throw new Exception("Conversación no encontrada para el email: " . $user_email_for_chat_id);
        }
        $stmt_get_convo->close();

        // 2. Insertar el mensaje
        $stmt_insert_msg = $conn->prepare("INSERT INTO messages (conversation_id, sender_type, sender_name, message_text, timestamp_unix) VALUES (?, ?, ?, ?, ?)");
        if (!$stmt_insert_msg) throw new Exception("Error al preparar la consulta para insertar mensaje: " . $conn->error);
        
        $stmt_insert_msg->bind_param("isssi", $conversation_id, $sender_type, $sender_name, $message_text, $timestamp_unix);
        if (!$stmt_insert_msg->execute()) throw new Exception("Error al guardar el mensaje: " . $stmt_insert_msg->error);
        $stmt_insert_msg->close();

        // 3. Actualizar la conversación
        $new_status = $current_convo_status;
        //if ($sender_type === 'user' && ($current_convo_status === 'pending_agent' || $current_convo_status === 'closed')) {//
        
        if ($sender_type === 'user' && $current_convo_status === 'pending_agent') {
            $new_status = 'active';
        } elseif ($sender_type === 'agent') {
            $new_status = 'active';
        }

        // if ($sender_type === 'user') {
        // if ($current_convo_status === 'pending_agent' || $current_convo_status === 'closed') {
        //         $new_status = 'active';
        //     }
        // } elseif ($sender_type === 'agent') {
        //     if ($current_convo_status === 'closed') { // Si el agente responde a un chat cerrado, lo activa
        //         $new_status = 'active';
        //     }
        //     // Si ya está activo o en espera, no es necesario cambiarlo.
        // }
        
        $stmt_update_convo = $conn->prepare("UPDATE conversations SET status = ?, last_message_at = FROM_UNIXTIME(?) WHERE id = ?");
        if (!$stmt_update_convo) throw new Exception("Error al preparar actualización de conversación: " . $conn->error);
        
        $stmt_update_convo->bind_param("sii", $new_status, $timestamp_unix, $conversation_id); 
        if (!$stmt_update_convo->execute()) throw new Exception("Error al actualizar la conversación: " . $stmt_update_convo->error);
        $stmt_update_convo->close();
        
        $conn->commit();
        $response = ['status' => 'success', 'message' => 'Mensaje guardado.'];

    } catch (Throwable $e) {
        if (isset($conn) && $conn->ping()) $conn->rollback();
        http_response_code(500);
        $response['message'] = "Error interno del servidor al enviar el mensaje.";
        error_log("Error en send_message.php: " . $e->getMessage() . " en " . $e->getFile() . " en la línea " . $e->getLine());
    } finally {
        if (isset($conn) && $conn->ping()) $conn->close();
    }
}

// --- 4. ENVIAR RESPUESTA FINAL ---
    echo json_encode($response);
    exit;
?>