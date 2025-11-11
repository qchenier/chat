<?php
session_start(); // Necesitamos la sesión para saber quién transfiere

// Ya no necesitamos 'db_config.php'
// require_once 'db_config.php';

// --- MODIFICACIÓN CLAVE DE RUTA ---
// Requerir el archivo de la clase Database.
// Asumiendo que este script está en C:\laragon\www\sci\public\agent\ (o public\api\)
// y Database.php está en C:\laragon\www\sci\src\Core\Config\Database.php
require_once __DIR__ . '/../../Core/Config/Database.php';

// Importar la clase Database del namespace
use SCI\Chat\Core\Config\Database;

header('Content-Type: application/json');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE); // Quita E_NOTICE para producción
ini_set('display_errors', 0); // En producción, errores a logs

$response = ['status' => 'error', 'message' => 'Datos incompletos o inválidos para la transferencia.'];

// Verificar que el agente que transfiere esté logueado
if (!isset($_SESSION['agent_id']) || empty($_SESSION['agent_id'])) {
    $response['message'] = 'Acción no permitida. Agente no autenticado.';
    echo json_encode($response);
    exit;
}
$transferring_agent_id = (int)$_SESSION['agent_id'];
$transferring_agent_name = isset($_SESSION['agent_name']) ? $_SESSION['agent_name'] : 'Agente';

$conn = null; // Inicializamos la conexión a null para el bloque finally

if (isset($_POST['conversation_id'], $_POST['target_department_id'])) {
    $conversation_id = (int)$_POST['conversation_id'];
    $target_department_id = (int)$_POST['target_department_id'];
    $target_agent_id = isset($_POST['target_agent_id']) && !empty($_POST['target_agent_id']) ? (int)$_POST['target_agent_id'] : null;
    $transfer_note = isset($_POST['transfer_note']) ? trim(htmlspecialchars($_POST['transfer_note'])) : null;

    if ($conversation_id <= 0 || $target_department_id <= 0) {
        $response['message'] = 'ID de conversación o departamento destino inválido.';
        echo json_encode($response);
        exit;
    }

    try {
        $conn = Database::getConnection(); // Obtener la conexión usando la clase Database

        $conn->begin_transaction(); // Iniciar transacción

        try {
            // Obtener nombre del departamento destino para el mensaje del sistema
            $dept_name_target = "Desconocido";
            $stmt_dept = $conn->prepare("SELECT name FROM departments WHERE id = ?");
            if($stmt_dept){
                $stmt_dept->bind_param("i", $target_department_id);
                $stmt_dept->execute();
                $result_dept = $stmt_dept->get_result();
                if($row_dept = $result_dept->fetch_assoc()){
                    $dept_name_target = $row_dept['name'];
                }
                $stmt_dept->close();
            } else {
                 error_log("Error preparando consulta de nombre de departamento: " . $conn->error);
            }

            // Actualizar la conversación
            $new_status = 'pending_agent'; // Por defecto, queda pendiente en el nuevo departamento
            $new_agent_id_sql = NULL;      // Por defecto, ningún agente específico

            if ($target_agent_id !== null && $target_agent_id > 0) {
                // TODO: Verificar si el target_agent_id pertenece al target_department_id (importante)
                // Esto es una validación de negocio que debería ocurrir antes o aquí.
                $new_status = 'active'; // O 'transferred_pending_acceptance' si quieres que el agente acepte
                $new_agent_id_sql = $target_agent_id;
            }

            $stmt_update_convo = $conn->prepare("UPDATE conversations
                                                 SET department_id = ?, agent_id = ?, status = ?, updated_at = NOW(), last_message_at = NOW()
                                                 WHERE id = ?");
            if (!$stmt_update_convo) {
                throw new Exception("Error preparando actualización de conversación: " . $conn->error);
            }

            // Usamos una variable auxiliar para el bind_param si $new_agent_id_sql es null
            $bind_agent_id = $new_agent_id_sql;
            $stmt_update_convo->bind_param("iisi", $target_department_id, $bind_agent_id, $new_status, $conversation_id);

            if (!$stmt_update_convo->execute()) {
                throw new Exception("Error al actualizar la conversación: " . $stmt_update_convo->error);
            }
            $stmt_update_convo->close();

            // Añadir un mensaje de sistema al chat sobre la transferencia
            $system_message_text = "Chat transferido al departamento '{$dept_name_target}' por {$transferring_agent_name}.";
            if ($target_agent_id !== null && $target_agent_id > 0) {
                // Obtener nombre del agente destino para el mensaje
                $agent_name_target = "Agente"; // Fallback
                $stmt_agent_name = $conn->prepare("SELECT name FROM agents WHERE id = ?");
                if($stmt_agent_name){
                    $stmt_agent_name->bind_param("i", $target_agent_id);
                    $stmt_agent_name->execute();
                    $result_agent_name = $stmt_agent_name->get_result();
                    if($row_agent_name = $result_agent_name->fetch_assoc()){
                        $agent_name_target = $row_agent_name['name'];
                    }
                    $stmt_agent_name->close();
                } else {
                    error_log("Error preparando consulta de nombre de agente: " . $conn->error);
                }
                $system_message_text = "Chat transferido a {$agent_name_target} (Dpto: {$dept_name_target}) por {$transferring_agent_name}.";
            }
            if (!empty($transfer_note)) {
                $system_message_text .= " Nota: " . $transfer_note;
            }

            $timestamp_unix_system = time();
            $stmt_system_msg = $conn->prepare("INSERT INTO messages (conversation_id, sender_type, sender_name, message_text, timestamp_unix)
                                               VALUES (?, 'system', 'Sistema', ?, ?)");
            if (!$stmt_system_msg) {
                throw new Exception("Error preparando mensaje de sistema: " . $conn->error);
            }

            $stmt_system_msg->bind_param("isi", $conversation_id, $system_message_text, $timestamp_unix_system);
            if (!$stmt_system_msg->execute()) {
                // No hacer rollback por esto, es un mensaje informativo, pero loguear el error.
                error_log("Error al insertar mensaje de sistema para transferencia (convo_id: {$conversation_id}): " . $stmt_system_msg->error);
            }
            $stmt_system_msg->close();

            // (Opcional) Insertar en tabla chat_transfers
            // ...

            $conn->commit(); // Confirmar la transacción
            $response = ['status' => 'success', 'message' => 'Chat transferido exitosamente.'];

        } catch (Exception $e) {
            // Si algo falla dentro del try anidado, se hace rollback y se propaga la excepción.
            if ($conn->in_transaction) { // Verificar si hay una transacción activa antes de hacer rollback
                $conn->rollback();
            }
            throw $e; // Re-lanzar la excepción para que el catch externo la maneje
        }

    } catch (Exception $e) {
        // Capturar cualquier excepción de la conexión o de la lógica de negocio
        $response['message'] = "Error durante la transferencia: " . $e->getMessage();
        error_log("Error en transfer_chat.php: " . $e->getMessage());
    } finally {
        // Asegura que la conexión a la base de datos se cierre.
        if ($conn) {
            Database::closeConnection();
        }
    }
}

echo json_encode($response);
exit;