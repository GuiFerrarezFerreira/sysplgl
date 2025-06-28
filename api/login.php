<?php
/**
 * Arbitrivm - Endpoint de Login Direto
 * api/login.php
 */

require_once '../config.php';

// Headers CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=UTF-8');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Apenas POST é permitido
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método não permitido']);
    exit;
}

// Obter dados da requisição
$input = json_decode(file_get_contents('php://input'), true);

// Validar dados
if (empty($input['email']) || empty($input['password'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Email e senha são obrigatórios']);
    exit;
}

// Processar login
$auth = new Auth();
$result = $auth->login($input['email'], $input['password']);

if ($result['success']) {
    // Regenerar ID da sessão por segurança
    session_regenerate_id(true);
    
    // Retornar sucesso
    echo json_encode([
        'success' => true,
        'message' => $result['message'],
        'data' => [
            'user' => $result['user'],
            'session_id' => session_id()
        ]
    ]);
} else {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => $result['message']
    ]);
}