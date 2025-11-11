<?php
/**
 * SCI-CHAT-SYSTEM - Modelo de Agente
 *
 * @package     SCI\Chat\Core\Models
 * @version     1.0.0
 *
 * @file        Agent.php
 * @description Representa un agente y encapsula la lógica de acceso a datos para la tabla 'agents'.
 */

namespace SCI\Chat\Core\Models;

use mysqli;
use Exception;

class Agent {
    private $conn;

    public function __construct(mysqli $db) {
        $this->conn = $db;
    }

    /**
     * Obtiene los IDs de los departamentos para un agente específico.
     *
     * @param int $agentId El ID del agente.
     * @return array Un array de IDs de departamento.
     * @throws Exception Si la consulta falla.
     */
    public function getDepartmentIds(int $agentId): array {
        $stmt = $this->conn->prepare("SELECT department_id FROM agent_departments WHERE agent_id = ?");
        if (!$stmt) {
            throw new Exception("Error al preparar la consulta de departamentos del agente: " . $this->conn->error);
        }
        $stmt->bind_param("i", $agentId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $departmentIds = [];
        while ($row = $result->fetch_assoc()) {
            $departmentIds[] = (int)$row['department_id'];
        }
        $stmt->close();
        return $departmentIds;
    }
}
?>