<?php
/**
 * Arbitrivm - Endpoint de Logout Direto
 * api/logout.php
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

// Verificar se está logado
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não está logado']);
    exit;
}

// Processar logout
$auth = new Auth();
$auth->logout();

echo json_encode([
    'success' => true,
    'message' => 'Logout realizado com sucesso'
]);