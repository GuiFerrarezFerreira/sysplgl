<?php
/**
 * Arbitrivm - Endpoint de Notificações
 * api/notifications.php
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
$userId = getCurrentUserId();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        // Buscar notificações não lidas
        $notifications = $db->getUnreadNotifications($userId);
        
        echo json_encode([
            'success' => true,
            'data' => $notifications
        ]);
        break;
        
    case 'POST':
        // Marcar notificação como lida
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (isset($data['notification_id'])) {
            $db->update('notifications', 
                ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')],
                'id = ? AND user_id = ?',
                [$data['notification_id'], $userId]
            );
            
            echo json_encode([
                'success' => true,
                'message' => 'Notificação marcada como lida'
            ]);
        } else {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID da notificação é obrigatório']);
        }
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido']);
}