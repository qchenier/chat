<?php
namespace SCI\Chat\Core\Models;

use mysqli;

class Message {
    private $conn;

    public function __construct(mysqli $db) {
        $this->conn = $db;
    }


    /**
     * Obtiene mensajes de una conversación más nuevos que un timestamp dado.
     * @param int $conversationId
     * @param int $lastTimestampUnix
     * @return array Un array de mensajes.
     * @throws Exception Si la consulta falla.
     */
    public function getNewMessagesForConversation(int $conversationId, int $lastTimestampUnix): array {
        $stmt = $this->conn->prepare(
            "SELECT sender_type, sender_name, message_text, timestamp_unix 
             FROM messages 
             WHERE conversation_id = ? AND timestamp_unix > ?
             ORDER BY timestamp_unix ASC"
        );
        if (!$stmt) {
            throw new Exception("Error al preparar consulta getNewMessages: " . $this->conn->error);
        }

        $stmt->bind_param("ii", $conversationId, $lastTimestampUnix);
        if (!$stmt->execute()) {
            throw new Exception("Error al ejecutar consulta getNewMessages: " . $stmt->error);
        }

        $result = $stmt->get_result();
        $messages = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        
        return $messages;
    }


    /**
     * Crea un nuevo mensaje en la base de datos.
     * @param int $conversationId
     * @param string $senderType
     * @param string $senderName
     * @param string $messageText
     * @return int|false El ID del mensaje insertado o false si falla.
     */
    public function create(int $conversationId, string $senderType, string $senderName, string $messageText) {
        $timestamp_unix = time();
        
        $stmt = $this->conn->prepare(
            "INSERT INTO messages (conversation_id, sender_type, sender_name, message_text, timestamp_unix) VALUES (?, ?, ?, ?, ?)"
        );
        if (!$stmt) {
            error_log("Error al preparar inserción de mensaje: " . $this->conn->error);
            return false;
        }

        $stmt->bind_param("isssi", $conversationId, $senderType, $senderName, $messageText, $timestamp_unix);

        if ($stmt->execute()) {
            $lastId = $stmt->insert_id;
            $stmt->close();
            return $lastId;
        } else {
            error_log("Error al ejecutar inserción de mensaje: " . $stmt->error);
            $stmt->close();
            return false;
        }
    }
}
?>