<?php
/**
 * includes/auth.php - Sistema de Autenticação Melhorado
 * Adicionar estas funções ao arquivo existente
 */

// Verificar se o usuário está logado
function checkAuth() {
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_type'])) {
        // Salvar URL de destino para redirecionar após login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        redirect('/login.php');
        exit();
    }
    
    // Verificar se a sessão não expirou
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
        session_destroy();
        redirect('/login.php?expired=1');
        exit();
    }
    
    // Atualizar última atividade
    $_SESSION['last_activity'] = time();
}

// Verificar tipo de usuário
function checkUserType($allowedTypes) {
    checkAuth();
    
    if (!in_array($_SESSION['user_type'], $allowedTypes)) {
        // Redirecionar para dashboard apropriado
        switch ($_SESSION['user_type']) {
            case 'admin':
                redirect('/admin/');
                break;
            case 'arbitrator':
                redirect('/arbitrator/');
                break;
            case 'party':
                redirect('/party/');
                break;
            default:
                redirect('/login.php');
        }
        exit();
    }
}

// Obter ID do árbitro
function getArbitratorId($userId) {
    global $db;
    if (!isset($db)) {
        require_once dirname(__FILE__) . '/database.php';
        $db = new Database();
    }
    
    $result = $db->fetchOne("SELECT id FROM arbitrators WHERE user_id = ?", [$userId]);
    return $result['id'] ?? null;
}

// Verificar se usuário é árbitro verificado
function isVerifiedArbitrator($userId) {
    global $db;
    if (!isset($db)) {
        require_once dirname(__FILE__) . '/database.php';
        $db = new Database();
    }
    
    $result = $db->fetchOne("
        SELECT a.is_verified 
        FROM arbitrators a 
        WHERE a.user_id = ? AND a.is_verified = 1
    ", [$userId]);
    
    return !empty($result);
}

// Sistema de Login Aprimorado
function authenticateUser($email, $password) {
    global $db;
    if (!isset($db)) {
        require_once dirname(__FILE__) . '/database.php';
        $db = new Database();
    }
    
    // Buscar usuário
    $user = $db->fetchOne("
        SELECT id, email, password, user_type, is_active, login_attempts, last_login_attempt
        FROM users 
        WHERE email = ?
    ", [$email]);
    
    if (!$user) {
        logLoginAttempt($email, false, 'Usuário não encontrado');
        return ['success' => false, 'message' => 'Email ou senha inválidos'];
    }
    
    // Verificar se conta está bloqueada
    if ($user['login_attempts'] >= LOGIN_ATTEMPTS_LIMIT) {
        $lockoutTime = strtotime($user['last_login_attempt']) + LOGIN_LOCKOUT_TIME;
        if (time() < $lockoutTime) {
            $minutesLeft = ceil(($lockoutTime - time()) / 60);
            return [
                'success' => false, 
                'message' => "Conta bloqueada. Tente novamente em $minutesLeft minutos."
            ];
        } else {
            // Resetar tentativas após período de bloqueio
            $db->update('users', ['login_attempts' => 0], 'id = ?', [$user['id']]);
        }
    }
    
    // Verificar senha
    if (!password_verify($password, $user['password'])) {
        // Incrementar tentativas de login
        $db->query("
            UPDATE users 
            SET login_attempts = login_attempts + 1, 
                last_login_attempt = NOW() 
            WHERE id = ?
        ", [$user['id']]);
        
        logLoginAttempt($email, false, 'Senha incorreta');
        
        $attemptsLeft = LOGIN_ATTEMPTS_LIMIT - $user['login_attempts'] - 1;
        if ($attemptsLeft > 0) {
            return [
                'success' => false, 
                'message' => "Email ou senha inválidos. $attemptsLeft tentativas restantes."
            ];
        } else {
            return [
                'success' => false, 
                'message' => 'Conta bloqueada devido a múltiplas tentativas falhas.'
            ];
        }
    }
    
    // Verificar se conta está ativa
    if (!$user['is_active']) {
        logLoginAttempt($email, false, 'Conta inativa');
        return ['success' => false, 'message' => 'Sua conta está inativa. Entre em contato com o suporte.'];
    }
    
    // Para árbitros, verificar se está verificado
    if ($user['user_type'] === 'arbitrator') {
        $arbitrator = $db->fetchOne("
            SELECT is_verified 
            FROM arbitrators 
            WHERE user_id = ?
        ", [$user['id']]);
        
        if (!$arbitrator || !$arbitrator['is_verified']) {
            logLoginAttempt($email, false, 'Árbitro não verificado');
            return [
                'success' => false, 
                'message' => 'Seu cadastro como árbitro ainda está em análise.'
            ];
        }
    }
    
    // Login bem-sucedido
    createUserSession($user);
    
    // Resetar tentativas e atualizar último login
    $db->update('users', [
        'login_attempts' => 0,
        'last_login' => date('Y-m-d H:i:s'),
        'last_ip' => $_SERVER['REMOTE_ADDR'] ?? null
    ], 'id = ?', [$user['id']]);
    
    logLoginAttempt($email, true, 'Login bem-sucedido');
    
    return ['success' => true, 'user_type' => $user['user_type']];
}

// Criar sessão do usuário
function createUserSession($user) {
    // Regenerar ID da sessão para segurança
    session_regenerate_id(true);
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_type'] = $user['user_type'];
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? null;
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? null;
    
    // Gerar token de sessão único
    $_SESSION['session_token'] = bin2hex(random_bytes(32));
}

// Logout seguro
function logoutUser() {
    // Limpar todas as variáveis de sessão
    $_SESSION = array();
    
    // Destruir o cookie de sessão
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destruir a sessão
    session_destroy();
}

// Log de tentativas de login
function logLoginAttempt($email, $success, $reason = null) {
    global $db;
    if (!isset($db)) {
        require_once dirname(__FILE__) . '/database.php';
        $db = new Database();
    }
    
    try {
        $db->insert('login_attempts', [
            'email' => $email,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'success' => $success ? 1 : 0,
            'reason' => $reason,
            'attempted_at' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        logError('Erro ao registrar tentativa de login: ' . $e->getMessage());
    }
}

// Verificar força da senha
function isStrongPassword($password) {
    if (strlen($password) < PASSWORD_MIN_LENGTH) {
        return false;
    }
    
    // Deve conter pelo menos: 1 maiúscula, 1 minúscula, 1 número
    if (!preg_match('/[A-Z]/', $password) || 
        !preg_match('/[a-z]/', $password) || 
        !preg_match('/[0-9]/', $password)) {
        return false;
    }
    
    return true;
}

// Gerar token para reset de senha
function generatePasswordResetToken($userId) {
    global $db;
    
    $token = bin2hex(random_bytes(32));
    $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    $db->insert('password_resets', [
        'user_id' => $userId,
        'token' => $token,
        'expires_at' => $expiry,
        'created_at' => date('Y-m-d H:i:s')
    ]);
    
    return $token;
}

// Validar token de reset
function validatePasswordResetToken($token) {
    global $db;
    
    $reset = $db->fetchOne("
        SELECT * FROM password_resets 
        WHERE token = ? 
        AND expires_at > NOW() 
        AND used = 0
    ", [$token]);
    
    return $reset;
}

// Middleware para verificar sessão em requisições AJAX
function checkAjaxAuth() {
    if (!isset($_SESSION['user_id'])) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['error' => 'Não autorizado']);
        exit();
    }
}

// Verificar se sessão é válida (previne session hijacking)
function isValidSession() {
    // Verificar IP
    if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $_SERVER['REMOTE_ADDR']) {
        return false;
    }
    
    // Verificar User Agent
    if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
        return false;
    }
    
    return true;
}


?>