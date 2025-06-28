<?php
/**
 * Arbitrivm - Endpoint para Verificar Sessão
 * api/check-session.php
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

// Verificar sessão
if (isLoggedIn()) {
    $user = getCurrentUser();
    echo json_encode([
        'success' => true,
        'data' => [
            'logged_in' => true,
            'user' => $user,
            'csrf_token' => $_SESSION['csrf_token'] ?? generateCSRF()
        ]
    ]);
} else {
    echo json_encode([
        'success' => true,
        'data' => [
            'logged_in' => false
        ]
    ]);
}