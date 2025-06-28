<?php
/**
 * Arbitrivm - API REST Simples
 * Ponto de entrada para todas as requisições da API
 */

require_once '../config.php';

// Headers CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=UTF-8');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Obter método e caminho da requisição
$method = $_SERVER['REQUEST_METHOD'];
$request_uri = $_SERVER['REQUEST_URI'];
$script_name = $_SERVER['SCRIPT_NAME'];
$path = str_replace(dirname($script_name), '', $request_uri);
$path = explode('?', $path)[0];
$path = trim($path, '/');

// Dividir o caminho em segmentos
$segments = explode('/', $path);
$resource = $segments[0] ?? '';
$id = $segments[1] ?? null;
$action = $segments[2] ?? null;

// Obter dados da requisição
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$params = array_merge($_GET, $input);

// Instanciar classes necessárias
$db = new Database();
$auth = new Auth();
$response = new Response();

// Rotas públicas (sem autenticação)
$publicRoutes = [
    'POST' => ['auth/login', 'auth/register', 'auth/verify-email', 'auth/forgot-password', 'auth/reset-password']
];

// Verificar se é rota pública
$isPublicRoute = false;
$currentRoute = $method . ' ' . $resource . ($action ? '/' . $action : '');

foreach ($publicRoutes[$method] ?? [] as $publicRoute) {
    if (strpos($currentRoute, $publicRoute) === 0) {
        $isPublicRoute = true;
        break;
    }
}

// Verificar autenticação para rotas protegidas
if (!$isPublicRoute) {
    if (!isLoggedIn()) {
        $response->error('Não autorizado. Faça login primeiro.', 401);
    }
    
    // Verificar se a sessão não expirou
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
        session_destroy();
        $response->error('Sessão expirada. Faça login novamente.', 401);
    }
    
    $_SESSION['last_activity'] = time();
}

// Roteamento principal
try {
    switch ($resource) {
        // Autenticação
        case 'auth':
            require_once 'endpoints/auth.php';
            handleAuthRequest($method, $action, $params);
            break;
            
        // Usuários
        case 'users':
            require_once 'endpoints/users.php';
            handleUsersRequest($method, $id, $action, $params);
            break;
            
        // Empresas
        case 'companies':
            require_once 'endpoints/companies.php';
            handleCompaniesRequest($method, $id, $action, $params);
            break;
            
        // Disputas
        case 'disputes':
            require_once 'endpoints/disputes.php';
            handleDisputesRequest($method, $id, $action, $params);
            break;
            
        // Documentos
        case 'documents':
            require_once 'endpoints/documents.php';
            handleDocumentsRequest($method, $id, $action, $params);
            break;
            
        // Mensagens
        case 'messages':
            require_once 'endpoints/messages.php';
            handleMessagesRequest($method, $id, $action, $params);
            break;
            
        // Árbitros
        case 'arbitrators':
            require_once 'endpoints/arbitrators.php';
            handleArbitratorsRequest($method, $id, $action, $params);
            break;
            
        // Notificações
        case 'notifications':
            require_once 'endpoints/notifications.php';
            handleNotificationsRequest($method, $id, $action, $params);
            break;
            
        // Relatórios
        case 'reports':
            require_once 'endpoints/reports.php';
            handleReportsRequest($method, $id, $action, $params);
            break;
            
        // Dashboard
        case 'dashboard':
            require_once 'endpoints/dashboard.php';
            handleDashboardRequest($method, $params);
            break;
            
        // Health check
        case 'health':
            $response->success([
                'status' => 'ok',
                'timestamp' => date('c'),
                'version' => '1.0.0'
            ]);
            break;
            
        default:
            $response->error('Endpoint não encontrado', 404);
    }
    
} catch (Exception $e) {
    logError('API Error: ' . $e->getMessage(), [
        'method' => $method,
        'resource' => $resource,
        'user_id' => getCurrentUserId()
    ]);
    
    $response->error('Erro interno do servidor', 500);
}