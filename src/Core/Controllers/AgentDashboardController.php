<?php
/**
 * SCI-CHAT-SYSTEM - Controlador del Dashboard del Agente
 *
 * @package     SCI\Chat\Core\Controllers
 * @version     1.0.0
 *
 * @file        AgentDashboardController.php
 * @description Orquesta las acciones relacionadas con el dashboard del agente.
 *              Actúa como intermediario entre los endpoints de la API y los Modelos de datos.
 */

namespace SCI\Chat\Core\Controllers;

// En un proyecto real, usarías un autoloader de Composer. Por ahora, hacemos require manual.
require_once __DIR__ . '/../Models/Conversation.php';
require_once __DIR__ . '/../Models/Agent.php';

use SCI\Chat\Core\Models\Conversation as ConversationModel;
use SCI\Chat\Core\Models\Agent as AgentModel;
use mysqli;
use Exception;

class AgentDashboardController {
    private $db;

    public function __construct(mysqli $db) {
        $this->db = $db;
    }

    /**
     * Obtiene la lista de conversaciones para el dashboard de un agente.
     *
     * @param int $agentId
     * @param int $page
     * @param int $itemsPerPage
     * @return array La respuesta formateada en JSON.
     */
    public function listConversations(int $agentId, int $page, int $itemsPerPage): array {
        try {
            $agentModel = new AgentModel($this->db);
            $departmentIds = $agentModel->getDepartmentIds($agentId);
            
            if (empty($departmentIds)) {
                return ['status' => 'success', 'conversations' => [], 'pagination' => ['totalPages' => 0, 'totalItems' => 0], 'message' => 'Agente no asignado a departamentos.'];
            }

            $conversationModel = new ConversationModel($this->db);
            $result = $conversationModel->getAgentConversations($agentId, $departmentIds, $page, $itemsPerPage);
            
            return [
                'status' => 'success',
                'conversations' => $result['data'],
                'pagination' => [
                    'currentPage' => $page,
                    'itemsPerPage' => $itemsPerPage,
                    'totalItems' => $result['total'],
                    'totalPages' => ($itemsPerPage > 0) ? ceil($result['total'] / $items_per_page) : 1
                ],
                'message' => 'Conversaciones cargadas.'
            ];
        } catch (Exception $e) {
            error_log("Controller Error en listConversations: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Error interno del servidor al obtener conversaciones.'];
        }
    }

    /**
     * Asigna un chat a un agente.
     *
     * @param int $conversationId
     * @param int $agentId
     * @return array La respuesta formateada en JSON.
     */
    public function assignChat(int $conversationId, int $agentId): array {
        try {
            $conversationModel = new ConversationModel($this->db);
            $success = $conversationModel->assignToAgent($conversationId, $agentId);

            if ($success) {
                return ['status' => 'success', 'message' => 'Chat asignado correctamente.'];
            } else {
                return ['status' => 'error', 'message' => 'El chat ya no está disponible o ya fue tomado.'];
            }
        } catch (Exception $e) {
            error_log("Controller Error en assignChat: " . $e->getMessage());
            return ['status' => 'error', 'message' => 'Error interno del servidor al asignar el chat.'];
        }
    }
}
?>