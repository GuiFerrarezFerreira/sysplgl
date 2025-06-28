<?php
/**
 * Arbitrivm - Configuração Principal (Sem Composer)
 * Sistema de Arbitragem para Disputas Imobiliárias
 */

// Iniciar sessão
session_start();

// Configurações do ambiente
define('ENVIRONMENT', 'development');

// Configurações de erro
if (ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Timezone
date_default_timezone_set('America/Sao_Paulo');

// Configurações do banco de dados
define('DB_HOST', 'localhost');
define('DB_NAME', 'arbitrivm_new');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Configurações de sessão
define('SESSION_LIFETIME', 7200); // 2 horas
define('SESSION_NAME', 'arbitrivm_session');
define('SESSION_SECURE', false); // Mudar para true em produção com HTTPS
define('SESSION_HTTPONLY', true);

// Configurações de upload
define('UPLOAD_PATH', dirname(__FILE__) . '/uploads/');
define('MAX_FILE_SIZE', 52428800); // 50MB
define('ALLOWED_FILE_TYPES', [
    'pdf', 'doc', 'docx', 'xls', 'xlsx', 
    'png', 'jpg', 'jpeg', 'gif', 
    'mp4', 'avi', 'mov', 'mp3', 'wav'
]);

// Configurações de email
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', '');
define('SMTP_PASS', '');
define('SMTP_FROM_EMAIL', 'noreply@arbitrivm.com.br');
define('SMTP_FROM_NAME', 'Arbitrivm');

// URLs do sistema
define('BASE_URL', 'http://localhost');
define('API_PATH', '/api');

// Configurações de segurança
define('PASSWORD_MIN_LENGTH', 8);
define('LOGIN_ATTEMPTS_LIMIT', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutos

// Configurações de paginação
define('ITEMS_PER_PAGE', 20);

// Diretórios
define('ROOT_PATH', dirname(__FILE__));
define('INCLUDES_PATH', ROOT_PATH . '/includes/');
define('CLASSES_PATH', ROOT_PATH . '/classes/');
define('API_PATH_DIR', ROOT_PATH . '/api/');
define('LOGS_PATH', ROOT_PATH . '/logs/');

// Criar diretórios necessários
$directories = [UPLOAD_PATH, LOGS_PATH, UPLOAD_PATH . 'documents/', UPLOAD_PATH . 'temp/'];
foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        mkdir($dir, 0777, true);
    }
}

// Incluir classes essenciais
require_once CLASSES_PATH . 'Database.php';
require_once CLASSES_PATH . 'Auth.php';
require_once CLASSES_PATH . 'Utils.php';
require_once CLASSES_PATH . 'FileHandler.php';
require_once CLASSES_PATH . 'Response.php';

// Configurar sessão
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
session_set_cookie_params([
    'lifetime' => SESSION_LIFETIME,
    'path' => '/',
    'domain' => '',
    'secure' => SESSION_SECURE,
    'httponly' => SESSION_HTTPONLY,
    'samesite' => 'Lax'
]);

// Função de autoload simples
function loadClass($className) {
    $file = CLASSES_PATH . $className . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
}

// Funções auxiliares globais
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function getCurrentUserId() {
    return $_SESSION['user_id'] ?? null;
}

function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    if (isset($_SESSION['user_data'])) {
        return $_SESSION['user_data'];
    }
    
    $db = new Database();
    $user = $db->getUserById($_SESSION['user_id']);
    $_SESSION['user_data'] = $user;
    
    return $user;
}

function hasPermission($permission) {
    $user = getCurrentUser();
    if (!$user) return false;
    
    $permissions = [
        'admin' => ['*'],
        'manager' => ['disputes.*', 'documents.*', 'reports.*', 'users.view'],
        'user' => ['disputes.own', 'documents.own'],
        'arbitrator' => ['disputes.assigned', 'documents.dispute'],
        'party' => ['disputes.involved', 'documents.involved']
    ];
    
    $userPermissions = $permissions[$user['role']] ?? [];
    
    if (in_array('*', $userPermissions)) {
        return true;
    }
    
    foreach ($userPermissions as $userPermission) {
        if ($userPermission === $permission || 
            (strpos($userPermission, '*') !== false && 
             strpos($permission, str_replace('*', '', $userPermission)) === 0)) {
            return true;
        }
    }
    
    return false;
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

function logError($message, $context = []) {
    $logFile = LOGS_PATH . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = !empty($context) ? json_encode($context) : '';
    $logMessage = "[$timestamp] $message $contextStr" . PHP_EOL;
    
    error_log($logMessage, 3, $logFile);
}

function formatDate($date, $format = 'd/m/Y') {
    if (empty($date)) return '';
    return date($format, strtotime($date));
}

function formatMoney($value) {
    return 'R$ ' . number_format($value, 2, ',', '.');
}

// Verificar CSRF token
function validateCSRF($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

function generateCSRF() {
    $_SESSION['csrf_token'] = generateToken();
    return $_SESSION['csrf_token'];
}

// Headers de segurança
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');