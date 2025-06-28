<?php
/**
 * Arbitrivm - Endpoint de Tipos de Disputa
 * api/dispute-types.php
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

// Buscar tipos de disputa ativos
$types = $db->fetchAll("SELECT id, name, category FROM dispute_types WHERE is_active = 1 ORDER BY name");

echo json_encode([
    'success' => true,
    'data' => $types
]);