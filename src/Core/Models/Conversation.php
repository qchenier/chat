<?php
namespace SCI\Chat\Core\Models;

use mysqli;

class Conversation {
    private $conn;

    public function __construct(mysqli $db) {
        $this->conn = $db;
    }

    /**
     * Encuentra una conversación por el email del usuario.
     * @param string $email
     * @return array|null Los datos de la conversación o null si no se encuentra.
     */
    public function findByEmail(string $email): ?array {
        $stmt = $this->conn->prepare("SELECT id, status FROM conversations WHERE user_email = ?");
        if (!$stmt) {
            error_log("Error preparando findByEmail: " . $this->conn->error);
            return null;
        }
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        $conversation = $result->fetch_assoc();
        $stmt->close();
        return $conversation;
    }

    /**
     * Actualiza el estado y el timestamp del último mensaje de una conversación.
     * @param int $conversationId
     * @param string $newStatus
     * @return bool True si tuvo éxito, false si no.
     */
    public function updateStatusAndLastMessageTime(int $conversationId, string $newStatus): bool {
        $timestamp_unix = time();
        $stmt = $this->conn->prepare("UPDATE conversations SET status = ?, last_message_at = FROM_UNIXTIME(?) WHERE id = ?");
        if (!$stmt) {
            error_log("Error preparando updateStatusAndLastMessageTime: " . $this->conn->error);
            return false;
        }
        $stmt->bind_param("sii", $newStatus, $timestamp_unix, $conversationId);
        
        $success = $stmt->execute();
        if (!$success) {
            error_log("Error ejecutando updateStatusAndLastMessageTime: " . $stmt->error);
        }
        $stmt->close();
        return $success;
    }

    // ... aquí irían otros métodos que ya tengas o que añadas en el futuro
}
?>