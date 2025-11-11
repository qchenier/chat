<?php
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
ini_set('display_errors', 0); // Deshabilita la muestra de errores en producción

$response = ['status' => 'error', 'message' => 'Datos incompletos o inválidos.'];

$conn = null; // Inicializamos la conexión a null para el bloque finally

if (isset($_POST['conversation_id'], $_POST['agent_id'])) {
    $conversation_id = (int)$_POST['conversation_id'];
    $agent_id = (int)$_POST['agent_id'];

    if ($conversation_id > 0 && $agent_id > 0) {
        try {
            // Obtener la conexión usando la clase Database
            $conn = Database::getConnection();

            // Iniciar transacción para asegurar que solo un agente lo tome
            $conn->begin_transaction();
            try {
                // Verificar que el chat esté 'pending_agent' y no asignado aún
                $stmt_check = $conn->prepare("SELECT agent_id, status FROM conversations WHERE id = ? FOR UPDATE");
                // FOR UPDATE bloquea la fila para evitar que dos agentes la tomen al mismo tiempo
                if (!$stmt_check) {
                    throw new Exception("Error preparando chequeo de conversación: " . $conn->error);
                }

                $stmt_check->bind_param("i", $conversation_id);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();

                if ($row_check = $result_check->fetch_assoc()) {
                    if ($row_check['status'] === 'pending_agent' && $row_check['agent_id'] === null) {
                        // El chat está disponible para ser tomado
                        $stmt_assign = $conn->prepare("UPDATE conversations SET agent_id = ?, status = 'active', updated_at = NOW() WHERE id = ? AND agent_id IS NULL AND status = 'pending_agent'");
                        // Doble chequeo en el WHERE para mayor seguridad contra race conditions
                        if (!$stmt_assign) {
                            throw new Exception("Error preparando asignación: " . $conn->error);
                        }

                        $stmt_assign->bind_param("ii", $agent_id, $conversation_id);
                        if ($stmt_assign->execute()) {
                            if ($stmt_assign->affected_rows > 0) {
                                $conn->commit(); // Confirmar los cambios si todo fue bien
                                $response = ['status' => 'success', 'message' => 'Chat asignado correctamente.'];
                            } else {
                                // Alguien más lo tomó justo en este instante, o el estado ya cambió
                                $conn->rollback(); // Revertir la transacción
                                $response['message'] = 'Este chat ya fue tomado por otro agente o su estado cambió.';
                            }
                        } else {
                            throw new Exception("Error al asignar el chat: " . $stmt_assign->error);
                        }
                        $stmt_assign->close();
                    } else {
                        // El chat ya está asignado o no está en estado pendiente
                        // No se necesita rollback si solo fue un SELECT y no hubo UPDATE fallido aquí.
                        // Sin embargo, para asegurar, podemos hacer un rollback si la transacción fue iniciada y no hay commit.
                        // La lógica de asignación ya maneja el commit/rollback basado en affected_rows.
                        
                        $response['message'] = 'Este chat ya no está disponible para ser tomado (asignado o estado incorrecto).';
                        
                        if ($row_check['agent_id'] === $agent_id) { // Si ya lo tiene este mismo agente
                             $response = ['status' => 'success', 'message' => 'Ya tienes este chat.']; // No es un error
                             // Si ya lo tiene, no hubo cambio, así que no se necesita rollback.
                             // Idealmente, aquí no se hace commit si no hubo operación de escritura.
                             // El `FOR UPDATE` ya liberaría el bloqueo al finalizar la transacción implícitamente o explícitamente.
                        } else {
                            // Si el chat estaba en otro estado o asignado a otro, y no a este agente, hacemos rollback
                            // Solo si hay una transacción abierta y no se ha hecho commit
                            // Esto previene que el FOR UPDATE mantenga el bloqueo si no se va a hacer commit.
                             $conn->rollback(); 
                        }
                    }
                } else {
                    throw new Exception("Conversación no encontrada.");
                }
                $stmt_check->close();

            } catch (Exception $e) {
                // Si algo falla dentro del try interno, se hace rollback y se propaga la excepción.
                if ($conn->in_transaction) { // Verificar si hay una transacción activa
                    $conn->rollback();
                }
                throw $e; // Re-lanzar la excepción para que el catch externo la maneje
            }

        } catch (Exception $e) {
            // Capturar cualquier excepción de la conexión o de la lógica de negocio
            $response['message'] = $e->getMessage();
            error_log("Error en assign_agent_to_chat.php: " . $e->getMessage());
        } finally {
            // Asegura que la conexión a la base de datos se cierre.
            if ($conn) {
                Database::closeConnection();
            }
        }
    } else {
        $response['message'] = 'ID de conversación o agente inválido.';
    }
}

echo json_encode($response);
exit;