<?php
/**
 * Arbitrivm - Endpoint de Mensagens
 * api/messages.php
 */

require_once '../config.php';

// Headers CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=UTF-8');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Verificar autenticação
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$db = new Database();
$auth = new Auth();
$user = getCurrentUser();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        handleGetMessages($db, $auth, $user);
        break;
        
    case 'POST':
        handleSendMessage($db, $auth, $user);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido']);
}

/**
 * Buscar mensagens de uma disputa
 */
function handleGetMessages($db, $auth, $user) {
    $disputeId = $_GET['dispute_id'] ?? null;
    
    if (!$disputeId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID da disputa é obrigatório']);
        return;
    }
    
    // Verificar permissão
    if (!$auth->canAccessDispute($user['id'], $disputeId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acesso negado']);
        return;
    }
    
    try {
        $messages = $db->getMessagesByDispute($disputeId);
        
        // Marcar mensagens como lidas
        markMessagesAsRead($db, $disputeId, $user['id']);
        
        echo json_encode([
            'success' => true,
            'data' => $messages
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao buscar mensagens']);
    }
}

/**
 * Enviar nova mensagem
 */
function handleSendMessage($db, $auth, $user) {
    // Verificar CSRF
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Token de segurança inválido']);
        return;
    }
    
    $disputeId = $_POST['dispute_id'] ?? null;
    $message = trim($_POST['message'] ?? '');
    
    if (!$disputeId || !$message) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'ID da disputa e mensagem são obrigatórios']);
        return;
    }
    
    // Verificar permissão
    if (!$auth->canAccessDispute($user['id'], $disputeId)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acesso negado']);
        return;
    }
    
    try {
        // Salvar mensagem
        $messageId = $db->sendMessage($disputeId, $user['id'], $message);
        
        // Buscar dados da disputa para notificações
        $dispute = $db->getDisputeById($disputeId);
        
        // Enviar notificações para as outras partes
        $recipients = [];
        
        // Adicionar partes da disputa
        if ($dispute['claimant_id'] != $user['id']) {
            $recipients[] = $dispute['claimant_id'];
        }
        if ($dispute['respondent_id'] != $user['id']) {
            $recipients[] = $dispute['respondent_id'];
        }
        
        // Adicionar árbitro se houver
        if ($dispute['arbitrator_id']) {
            $arbitrator = $db->fetchOne(
                "SELECT user_id FROM arbitrators WHERE id = ?",
                [$dispute['arbitrator_id']]
            );
            if ($arbitrator && $arbitrator['user_id'] != $user['id']) {
                $recipients[] = $arbitrator['user_id'];
            }
        }
        
        // Criar notificações
        foreach ($recipients as $recipientId) {
            $db->createNotification(
                $recipientId,
                'new_message',
                'Nova mensagem',
                "{$user['first_name']} enviou uma mensagem na disputa {$dispute['case_number']}",
                [
                    'dispute_id' => $disputeId,
                    'message_id' => $messageId,
                    'sender_name' => $user['first_name'] . ' ' . $user['last_name']
                ]
            );
        }
        
        // Log do evento
        $db->insert('dispute_events', [
            'dispute_id' => $disputeId,
            'user_id' => $user['id'],
            'event_type' => 'message_sent',
            'description' => 'Mensagem enviada',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Mensagem enviada com sucesso',
            'data' => ['id' => $messageId]
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao enviar mensagem']);
    }
}

/**
 * Marcar mensagens como lidas
 */
function markMessagesAsRead($db, $disputeId, $userId) {
    try {
        $db->query(
            "UPDATE messages m
             SET read_by = JSON_MERGE_PRESERVE(
                 COALESCE(read_by, '[]'), 
                 JSON_ARRAY(?)
             )
             WHERE dispute_id = ? 
             AND sender_id != ?
             AND NOT JSON_CONTAINS(COALESCE(read_by, '[]'), ?, '$')",
            [$userId, $disputeId, $userId, json_encode($userId)]
        );
    } catch (Exception $e) {
        // Log error but don't fail the request
        logError('Error marking messages as read: ' . $e->getMessage());
    }
}