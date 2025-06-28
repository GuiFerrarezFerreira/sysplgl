<?php
/**
 * Arbitrivm - Endpoint de Autenticação
 */

function handleAuthRequest($method, $action, $params) {
    $auth = new Auth();
    $response = new Response();
    
    if ($method !== 'POST') {
        $response->error('Método não permitido', 405);
    }
    
    switch ($action) {
        case 'login':
            handleLogin($params, $auth, $response);
            break;
            
        case 'register':
            handleRegister($params, $auth, $response);
            break;
            
        case 'logout':
            handleLogout($auth, $response);
            break;
            
        case 'verify-email':
            handleVerifyEmail($params, $auth, $response);
            break;
            
        case 'forgot-password':
            handleForgotPassword($params, $auth, $response);
            break;
            
        case 'reset-password':
            handleResetPassword($params, $auth, $response);
            break;
            
        case 'check-session':
            handleCheckSession($response);
            break;
            
        default:
            $response->error('Ação inválida', 400);
    }
}

function handleLogin($params, $auth, $response) {
    // Validar campos obrigatórios
    if (empty($params['email']) || empty($params['password'])) {
        $response->error('Email e senha são obrigatórios', 400);
    }
    
    // Validar CSRF token se estiver presente
    if (isset($params['csrf_token']) && !validateCSRF($params['csrf_token'])) {
        $response->error('Token de segurança inválido', 403);
    }
    
    // Tentar fazer login
    $result = $auth->login($params['email'], $params['password']);
    
    if ($result['success']) {
        // Regenerar ID da sessão por segurança
        session_regenerate_id(true);
        
        // Gerar novo CSRF token
        $csrfToken = generateCSRF();
        
        $response->success([
            'user' => $result['user'],
            'csrf_token' => $csrfToken,
            'session_id' => session_id()
        ], $result['message']);
    } else {
        $response->error($result['message'], 401);
    }
}

function handleRegister($params, $auth, $response) {
    // Validar campos obrigatórios
    $required = ['email', 'password', 'first_name', 'last_name'];
    foreach ($required as $field) {
        if (empty($params[$field])) {
            $response->error("Campo '$field' é obrigatório", 400);
        }
    }
    
    // Validar confirmação de senha
    if (isset($params['password_confirm']) && $params['password'] !== $params['password_confirm']) {
        $response->error('As senhas não coincidem', 400);
    }
    
    // Preparar dados
    $registrationData = [
        'email' => sanitizeInput($params['email']),
        'password' => $params['password'],
        'first_name' => sanitizeInput($params['first_name']),
        'last_name' => sanitizeInput($params['last_name']),
        'cpf' => isset($params['cpf']) ? preg_replace('/[^0-9]/', '', $params['cpf']) : null,
        'phone' => isset($params['phone']) ? preg_replace('/[^0-9]/', '', $params['phone']) : null,
        'role' => $params['role'] ?? 'user',
        'company_id' => $params['company_id'] ?? null
    ];
    
    // Se for árbitro, adicionar dados extras
    if ($registrationData['role'] === 'arbitrator' && isset($params['arbitrator_data'])) {
        $registrationData['arbitrator_data'] = [
            'registration_number' => sanitizeInput($params['arbitrator_data']['registration_number']),
            'specializations' => json_encode($params['arbitrator_data']['specializations'] ?? []),
            'bio' => sanitizeInput($params['arbitrator_data']['bio'] ?? ''),
            'experience_years' => intval($params['arbitrator_data']['experience_years'] ?? 0),
            'hourly_rate' => floatval($params['arbitrator_data']['hourly_rate'] ?? 0)
        ];
    }
    
    // Registrar
    $result = $auth->register($registrationData);
    
    if ($result['success']) {
        $response->success([
            'user_id' => $result['user_id']
        ], $result['message'], 201);
    } else {
        $response->error($result['message'], 400);
    }
}

function handleLogout($auth, $response) {
    if (!isLoggedIn()) {
        $response->error('Não está logado', 401);
    }
    
    $auth->logout();
    $response->success(null, 'Logout realizado com sucesso');
}

function handleVerifyEmail($params, $auth, $response) {
    if (empty($params['token'])) {
        $response->error('Token é obrigatório', 400);
    }
    
    $result = $auth->verifyEmail($params['token']);
    
    if ($result['success']) {
        $response->success(null, $result['message']);
    } else {
        $response->error($result['message'], 400);
    }
}

function handleForgotPassword($params, $auth, $response) {
    if (empty($params['email'])) {
        $response->error('Email é obrigatório', 400);
    }
    
    $result = $auth->forgotPassword($params['email']);
    
    // Sempre retornar sucesso por segurança (não revelar se email existe)
    $response->success(null, $result['message']);
}

function handleResetPassword($params, $auth, $response) {
    if (empty($params['token']) || empty($params['password'])) {
        $response->error('Token e nova senha são obrigatórios', 400);
    }
    
    $result = $auth->resetPassword($params['token'], $params['password']);
    
    if ($result['success']) {
        $response->success(null, $result['message']);
    } else {
        $response->error($result['message'], 400);
    }
}

function handleCheckSession($response) {
    if (isLoggedIn()) {
        $user = getCurrentUser();
        $response->success([
            'logged_in' => true,
            'user' => $user,
            'csrf_token' => $_SESSION['csrf_token'] ?? generateCSRF()
        ]);
    } else {
        $response->success([
            'logged_in' => false
        ]);
    }
}