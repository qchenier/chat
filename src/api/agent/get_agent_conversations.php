<?php
/**
 * SCI-CHAT-SYSTEM
 *
 * @package       SCI\Chat\Endpoints
 * @author        [Tu Nombre/Empresa]
 * @copyright     Copyright (c) 2024, [Tu Nombre/Empresa]
 * @license       [Tu Licencia, ej. MIT]
 * @version       1.5.0
 *
 * @file          get_agent_conversations.php
 * @description Este endpoint recupera la lista de conversaciones relevantes para un agente específico.
 * La lógica está diseñada para presentar una cola de trabajo unificada y priorizada:
 * 1. Muestra los chats pendientes de asignación en los departamentos del agente.
 * 2. Muestra los chats que ya están activos y asignados a este agente.
 * El resultado está paginado para manejar grandes volúmenes de conversaciones.
 *
 * @api
 * @method        GET
 * @param         int agent_id - (Requerido) El ID del agente que realiza la solicitud.
 * @param         int page - (Opcional) El número de página para la paginación. Por defecto es 1.
 * @param         int items_per_page - (Opcional) El número de conversaciones a devolver por página. Por defecto es 25.
 *
 * @return        JSON Un objeto JSON que contiene:
 * - status: ('success'|'error')
 * - conversations: Un array de objetos de conversación.
 * - pagination: Un objeto con detalles de la paginación (currentPage, totalPages, etc.).
 * - message: Un mensaje descriptivo del resultado de la operación.
 */

// --- MODIFICACIÓN CLAVE DE RUTA ---
// Requerir el archivo de la clase Database.
// Asumiendo que este script está en C:\laragon\www\sci\public\agent\ (o public\api\)
// y Database.php está en C:\laragon\www\sci\src\Core\Config\Database.php
require_once __DIR__ . '/../../Core/Config/Database.php';

// Importar la clase Database del namespace
use SCI\Chat\Core\Config\Database;

// Establecer la cabecera de la respuesta a JSON para una correcta interpretación por parte del cliente.
header('Content-Type: application/json');

// Configuración de reporte de errores para un entorno de producción.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', 0);

// Inicialización del objeto de respuesta por defecto.
$response = [
    'status' => 'error',
    'conversations' => [],
    'message' => 'No se pudieron cargar las conversaciones.',
    'pagination' => null
];

// Variable para la conexión a la base de datos, inicializada a null
$conn = null;

// --- Validación de Parámetros de Entrada ---

// Se requiere el ID del agente para identificar qué conversaciones son relevantes.
$current_agent_id = isset($_GET['agent_id']) ? (int)$_GET['agent_id'] : null;

// Parámetros de paginación con valores por defecto y validación básica.
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$items_per_page = isset($_GET['items_per_page']) ? max(5, (int)$_GET['items_per_page']) : 25;
$offset = ($page - 1) * $items_per_page;

// Si no se proporciona un ID de agente, la operación no puede continuar.
if (!$current_agent_id) {
    $response['message'] = 'No se identificó al agente. La operación fue abortada.';
    echo json_encode($response);
    exit;
}

// --- Lógica Principal ---

try {
    // Establecer conexión con la base de datos usando la clase Database.
    $conn = Database::getConnection(); // Aquí se llama al método estático

    // --- Paso 1: Obtener los Departamentos del Agente ---
    // Se necesita saber a qué departamentos pertenece el agente para mostrarle los chats pendientes.
    $stmt_agent_depts = $conn->prepare("SELECT department_id FROM agent_departments WHERE agent_id = ?");
    if (!$stmt_agent_depts) {
        throw new Exception("Error al preparar la consulta de departamentos del agente: " . $conn->error);
    }

    $stmt_agent_depts->bind_param("i", $current_agent_id);
    $stmt_agent_depts->execute();
    $result_agent_depts = $stmt_agent_depts->get_result();

    $agent_department_ids = [];
    while ($row_dept = $result_agent_depts->fetch_assoc()) {
        $agent_department_ids[] = (int)$row_dept['department_id'];
    }
    $stmt_agent_depts->close();

    // --- Paso 2: Construir la Cláusula WHERE Dinámicamente ---
    // Esta cláusula define qué conversaciones son visibles para el agente.
    $where_clauses_array = [];
    $bind_params_values_where = [];
    $bind_param_types_where = "";

    // Crear placeholders (?) para la cláusula IN de SQL de forma segura.
    $department_ids_placeholder = "(-1)"; // Usar un valor no existente si el agente no tiene departamentos.
    if (!empty($agent_department_ids)) {
        $department_ids_placeholder = implode(',', array_fill(0, count($agent_department_ids), '?'));
        $bind_param_types_where .= str_repeat('i', count($agent_department_ids));
        $bind_params_values_where = array_merge($bind_params_values_where, $agent_department_ids);
    }

    // La condición principal:
    // (El chat está en un departamento del agente Y está pendiente) O (El chat ya está asignado a este agente)
    $base_condition_sql = "((c.department_id IN (" . $department_ids_placeholder . ") AND c.status = 'pending_agent') OR (c.agent_id = ? AND c.status IN ('active', 'open', 'on_hold')))";
    $where_clauses_array[] = $base_condition_sql;
    $bind_param_types_where .= "i";
    $bind_params_values_where[] = $current_agent_id;

    $sql_where_string = implode(" AND ", $where_clauses_array);

    // --- Paso 3: Contar el Total de Conversaciones para la Paginación ---
    // Se ejecuta una consulta de conteo primero para saber el total de páginas.
    $sql_count = "SELECT COUNT(DISTINCT c.id) as total_conversations
                    FROM conversations c
                    WHERE {$sql_where_string}";

    $stmt_count = $conn->prepare($sql_count);
    if (!$stmt_count) throw new Exception("Error al preparar la consulta de conteo: " . $conn->error);

    if (!empty($bind_param_types_where)) {
        // Usa call_user_func_array o el operador de propagación (...) para bind_param
        $stmt_count->bind_param($bind_param_types_where, ...$bind_params_values_where);
    }
    $stmt_count->execute();
    $result_count = $stmt_count->get_result();
    $total_conversations_row = $result_count->fetch_assoc();
    $total_conversations = $total_conversations_row ? (int)$total_conversations_row['total_conversations'] : 0;
    $stmt_count->close();

    // Calcular el total de páginas y corregir la página actual si es necesario.
    $total_pages = ($items_per_page > 0 && $total_conversations > 0) ? ceil($total_conversations / $items_per_page) : 1;
    if ($page > $total_pages && $total_conversations > 0) $page = $total_pages;
    $offset = ($page - 1) * $items_per_page;

    // --- Paso 4: Obtener los Datos de las Conversaciones para la Página Actual ---
    // Se define el orden de aparición: primero los pendientes, luego los más recientes.
    $order_by_clause = "ORDER BY
                            CASE c.status
                                WHEN 'pending_agent' THEN 1
                                ELSE 2
                            END,
                            c.last_message_at DESC, c.created_at DESC";

    $sql_data = "SELECT
                        c.id, c.user_email, c.user_name, c.status, c.department_id,
                        d.name as department_name,
                        c.agent_id, ag.name as agent_name_assigned,
                        c.last_message_at,
                        (SELECT m.message_text FROM messages m WHERE m.conversation_id = c.id ORDER BY m.timestamp_unix DESC LIMIT 1) as last_message_preview,
                        (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.sender_type = 'user' AND m.timestamp_unix > COALESCE((SELECT MAX(m_agent.timestamp_unix) FROM messages m_agent WHERE m_agent.conversation_id = c.id AND m_agent.sender_type = 'agent'), 0)) as unread_user_messages
                    FROM conversations c
                    LEFT JOIN departments d ON c.department_id = d.id
                    LEFT JOIN agents ag ON c.agent_id = ag.id
                    WHERE {$sql_where_string}
                    {$order_by_clause}
                    LIMIT ? OFFSET ?";

    $stmt_data = $conn->prepare($sql_data);
    if (!$stmt_data) throw new Exception("Error al preparar la consulta de datos: " . $conn->error);

    // Añadir los parámetros de LIMIT y OFFSET al final de los parámetros del WHERE.
    $final_bind_param_types = $bind_param_types_where . "ii";
    $final_bind_params_values = array_merge($bind_params_values_where, [$items_per_page, $offset]);

    // Usa call_user_func_array o el operador de propagación (...) para bind_param
    $stmt_data->bind_param($final_bind_param_types, ...$final_bind_params_values);

    if ($stmt_data->execute()) {
        $result_data = $stmt_data->get_result();
        $conversations = [];
        while ($row = $result_data->fetch_assoc()) {
            $conversations[] = $row;
        }
        $response['status'] = 'success';
        $response['conversations'] = $conversations;
        $response['message'] = 'Conversaciones cargadas exitosamente.';
        $response['pagination'] = [
            'currentPage' => $page,
            'itemsPerPage' => $items_per_page,
            'totalItems' => $total_conversations,
            'totalPages' => $total_pages
        ];
    } else {
        throw new Exception("Error al ejecutar la consulta de datos: " . $stmt_data->error);
    }
    $stmt_data->close();

} catch (Exception $e) {
    // Capturar cualquier excepción para un manejo de errores centralizado.
    $response['message'] = "Error interno del servidor: " . $e->getMessage();
    error_log("Error en get_agent_conversations.php: " . $e->getMessage());
} finally {
    // Cerrar la conexión a la base de datos de forma segura.
    if ($conn) {
        Database::closeConnection();
    }
}

// Enviar la respuesta final en formato JSON.
echo json_encode($response);
exit;
?>