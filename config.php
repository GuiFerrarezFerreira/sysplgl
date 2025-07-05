<?php
/**
 * Arbitrivm - Configuração Principal (Sem Composer)
 * Sistema de Arbitragem para Disputas Imobiliárias
 */

// Headers de segurança (DEVE VIR ANTES DE QUALQUER OUTPUT)
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

// Iniciar sessão apenas se não estiver iniciada
if (session_status() === PHP_SESSION_NONE) {
    // Configurar parâmetros da sessão ANTES de iniciar
    ini_set('session.gc_maxlifetime', 7200);
    session_set_cookie_params([
        'lifetime' => 7200,
        'path' => '/',
        'domain' => '',
        'secure' => false, // Mudar para true em produção com HTTPS
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}

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
define('BASE_URL', 'http://localhost/sysplgl'); // Ajustado para incluir o caminho do projeto
define('API_URL', BASE_URL . '/api');

// Configurações de segurança
define('PASSWORD_MIN_LENGTH', 8);
define('LOGIN_ATTEMPTS_LIMIT', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutos

// Configurações de paginação
define('ITEMS_PER_PAGE', 20);

// Diretórios
define('ROOT_PATH', dirname(__FILE__));
define('INCLUDES_PATH', ROOT_PATH . '/includes/');
define('API_PATH_DIR', ROOT_PATH . '/api/');
define('LOGS_PATH', ROOT_PATH . '/logs/');

// Criar diretórios necessários
$directories = [
    UPLOAD_PATH, 
    LOGS_PATH, 
    UPLOAD_PATH . 'documents/', 
    UPLOAD_PATH . 'temp/',
    UPLOAD_PATH . 'thumbnails/'
];

foreach ($directories as $dir) {
    if (!file_exists($dir)) {
        if (!@mkdir($dir, 0777, true)) {
            error_log("Falha ao criar diretório: $dir");
        }
    }
}

// Incluir classes essenciais (com nomes corretos em minúsculas)
$requiredClasses = ['database', 'auth', 'utils', 'filehandler', 'response'];
foreach ($requiredClasses as $class) {
    $file = CLASSES_PATH . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    } else {
        error_log("Arquivo de classe não encontrado: $file");
    }
}

// Função de autoload simples
function loadClass($className) {
    $className = strtolower($className); // Converter para minúsculas
    $file = CLASSES_PATH . $className . '.php';
    if (file_exists($file)) {
        require_once $file;
        return true;
    }
    return false;
}

// Registrar autoloader
spl_autoload_register('loadClass');

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
    
    // Cache do usuário na sessão
    if (isset($_SESSION['user_data']) && 
        isset($_SESSION['user_data_timestamp']) && 
        (time() - $_SESSION['user_data_timestamp'] < 300)) { // Cache de 5 minutos
        return $_SESSION['user_data'];
    }
    
    try {
        $db = new Database();
        $user = $db->getUserById($_SESSION['user_id']);
        
        if ($user) {
            $_SESSION['user_data'] = $user;
            $_SESSION['user_data_timestamp'] = time();
        }
        
        return $user;
    } catch (Exception $e) {
        logError('Erro ao buscar usuário: ' . $e->getMessage());
        return null;
    }
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
    if (!headers_sent()) {
        header("Location: $url");
        exit;
    } else {
        echo "<script>window.location.href='$url';</script>";
        exit;
    }
}

function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function generateToken($length = 32) {
    // Usar diferentes métodos dependendo da disponibilidade
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes($length / 2));
    } elseif (function_exists('openssl_random_pseudo_bytes')) {
        return bin2hex(openssl_random_pseudo_bytes($length / 2));
    } else {
        // Fallback menos seguro (apenas para desenvolvimento)
        $token = '';
        $chars = '0123456789abcdef';
        for ($i = 0; $i < $length; $i++) {
            $token .= $chars[mt_rand(0, 15)];
        }
        return $token;
    }
}

function logError($message, $context = []) {
    try {
        $logFile = LOGS_PATH . date('Y-m-d') . '.log';
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = !empty($context) ? json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        $logMessage = "[$timestamp] $message $contextStr" . PHP_EOL;
        
        // Criar diretório de logs se não existir
        if (!file_exists(LOGS_PATH)) {
            mkdir(LOGS_PATH, 0777, true);
        }
        
        error_log($logMessage, 3, $logFile);
    } catch (Exception $e) {
        // Se falhar ao escrever no arquivo, usar error_log padrão
        error_log("Log Error: $message");
    }
}

function formatDate($date, $format = 'd/m/Y') {
    if (empty($date)) return '';
    
    $timestamp = is_numeric($date) ? $date : strtotime($date);
    if ($timestamp === false) return '';
    
    return date($format, $timestamp);
}

function formatMoney($value) {
    if (!is_numeric($value)) return 'R$ 0,00';
    return 'R$ ' . number_format((float)$value, 2, ',', '.');
}

// Verificar CSRF token
function validateCSRF($token) {
    if (empty($token) || !isset($_SESSION['csrf_token'])) {
        return false;
    }
    
    // Comparação segura contra timing attacks
    return hash_equals($_SESSION['csrf_token'], $token);
}

function generateCSRF() {
    $_SESSION['csrf_token'] = generateToken();
    return $_SESSION['csrf_token'];
}

// Função para limpar sessões antigas (chamar periodicamente)
function cleanOldSessions() {
    if (isset($_SESSION['last_activity']) && 
        (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
        session_unset();
        session_destroy();
        return false;
    }
    $_SESSION['last_activity'] = time();
    return true;
}

// Verificar se as extensões necessárias estão instaladas
function checkRequirements() {
    $required = [
        'pdo' => 'PDO',
        'pdo_mysql' => 'PDO MySQL',
        'session' => 'Session',
        'json' => 'JSON',
        'fileinfo' => 'FileInfo'
    ];
    
    $missing = [];
    foreach ($required as $ext => $name) {
        if (!extension_loaded($ext)) {
            $missing[] = $name;
        }
    }
    
    if (!empty($missing)) {
        die('Extensões PHP necessárias não encontradas: ' . implode(', ', $missing));
    }
}

// Verificar requisitos na inicialização
if (ENVIRONMENT === 'development') {
    checkRequirements();
}

// Limpar sessões antigas
if (isLoggedIn()) {
    cleanOldSessions();
}