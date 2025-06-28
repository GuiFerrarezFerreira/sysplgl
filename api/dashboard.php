<?php
/**
 * Arbitrivm - Endpoint do Dashboard
 * api/dashboard.php
 */

require_once '../config.php';

// Headers CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, OPTIONS');
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
$user = getCurrentUser();

// Obter estatísticas baseadas no tipo de usuário
$companyId = null;
if ($user['role'] === 'user' || $user['role'] === 'manager') {
    $companyId = $user['company_id'];
}

$stats = $db->getDashboardStats($companyId);

echo json_encode([
    'success' => true,
    'data' => $stats
]);