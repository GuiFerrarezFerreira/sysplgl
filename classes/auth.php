<?php
/**
 * Arbitrivm - Classe de Autenticação Simples (Sessões PHP)
 */

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Login do usuário
     */
    public function login($email, $password) {
        // Verificar tentativas de login
        $attempts = $this->db->checkLoginAttempts($email);
        
        if ($attempts['locked']) {
            $minutes = ceil($attempts['remaining_time'] / 60);
            return [
                'success' => false,
                'message' => "Conta bloqueada. Tente novamente em $minutes minutos."
            ];
        }
        
        // Buscar usuário
        $user = $this->db->getUserByEmail($email);
        
        if (!$user) {
            $this->db->incrementLoginAttempts($email);
            return [
                'success' => false,
                'message' => 'Email ou senha incorretos.'
            ];
        }
        
        // Verificar senha
        //if (!password_verify($password, $user['password_hash'])) {
        if ($password != $user['password_hash']) {            
            $this->db->incrementLoginAttempts($email);
            return [
                'success' => false,
                'message' => 'Email ou senha incorretos.'
            ];
        }
        
        // Verificar se usuário está ativo
        if (!$user['is_active']) {
            return [
                'success' => false,
                'message' => 'Sua conta está desativada.'
            ];
        }
        
        // Verificar se email foi verificado
        if (!$user['is_verified']) {
            return [
                'success' => false,
                'message' => 'Por favor, verifique seu email antes de fazer login.'
            ];
        }
        
        // Verificar status da empresa (para usuários B2B)
        if ($user['company_id'] && $user['subscription_status'] !== 'active') {
            return [
                'success' => false,
                'message' => 'A assinatura da empresa não está ativa.'
            ];
        }
        
        // Login bem-sucedido
        $this->createSession($user);
        $this->db->resetLoginAttempts($user['id']);
        $this->db->logAction($user['id'], 'login', 'users', $user['id']);
        
        return [
            'success' => true,
            'message' => 'Login realizado com sucesso.',
            'user' => $this->sanitizeUserData($user)
        ];
    }
    
    /**
     * Logout do usuário
     */
    public function logout() {
        $userId = getCurrentUserId();
        
        if ($userId) {
            $this->db->logAction($userId, 'logout', 'users', $userId);
        }
        
        // Destruir sessão
        $_SESSION = [];
        session_destroy();
        
        // Remover cookie de sessão
        if (isset($_COOKIE[session_name()])) {
            setcookie(session_name(), '', time() - 3600, '/');
        }
        
        return true;
    }
    
    /**
     * Registrar novo usuário
     */
    public function register($data) {
        // Validar dados
        $validation = $this->validateRegistrationData($data);
        if (!$validation['success']) {
            return $validation;
        }
        
        // Verificar se email já existe
        if ($this->db->getUserByEmail($data['email'])) {
            return [
                'success' => false,
                'message' => 'Este email já está cadastrado.'
            ];
        }
        
        // Verificar CPF único se fornecido
        if (!empty($data['cpf'])) {
            $existingCpf = $this->db->fetchOne("SELECT id FROM users WHERE cpf = ?", [$data['cpf']]);
            if ($existingCpf) {
                return [
                    'success' => false,
                    'message' => 'Este CPF já está cadastrado.'
                ];
            }
        }
        
        // Preparar dados
        $userData = [
            'email' => $data['email'],
            'password_hash' => password_hash($data['password'], PASSWORD_BCRYPT),
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'cpf' => $data['cpf'] ?? null,
            'phone' => $data['phone'] ?? null,
            'role' => $data['role'] ?? 'user',
            'company_id' => $data['company_id'] ?? null,
            'is_active' => 1,
            'is_verified' => 0,
            'verification_token' => generateToken(),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        try {
            $userId = $this->db->insert('users', $userData);
            
            // Se for árbitro, criar registro adicional
            if ($userData['role'] === 'arbitrator' && isset($data['arbitrator_data'])) {
                $arbitratorData = $data['arbitrator_data'];
                $arbitratorData['user_id'] = $userId;
                $arbitratorData['created_at'] = date('Y-m-d H:i:s');
                $this->db->insert('arbitrators', $arbitratorData);
            }
            
            // Enviar email de verificação
            $this->sendVerificationEmail($userData['email'], $userData['verification_token']);
            
            return [
                'success' => true,
                'message' => 'Cadastro realizado com sucesso. Verifique seu email para ativar sua conta.',
                'user_id' => $userId
            ];
            
        } catch (Exception $e) {
            logError('Registration error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao realizar cadastro. Tente novamente.'
            ];
        }
    }
    
    /**
     * Verificar email
     */
    public function verifyEmail($token) {
        $user = $this->db->fetchOne(
            "SELECT id, email FROM users WHERE verification_token = ? AND is_verified = 0",
            [$token]
        );
        
        if (!$user) {
            return [
                'success' => false,
                'message' => 'Token de verificação inválido ou expirado.'
            ];
        }
        
        $this->db->update('users', 
            ['is_verified' => 1, 'verification_token' => null],
            'id = ?',
            [$user['id']]
        );
        
        return [
            'success' => true,
            'message' => 'Email verificado com sucesso. Você já pode fazer login.'
        ];
    }
    
    /**
     * Solicitar redefinição de senha
     */
    public function forgotPassword($email) {
        $user = $this->db->getUserByEmail($email);
        
        if (!$user) {
            // Não revelar se o email existe
            return [
                'success' => true,
                'message' => 'Se o email existir em nossa base, você receberá instruções para redefinir a senha.'
            ];
        }
        
        // Gerar token de reset
        $resetToken = generateToken();
        $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $this->db->update('users',
            [
                'reset_token' => $resetToken,
                'reset_token_expires' => $expiresAt
            ],
            'id = ?',
            [$user['id']]
        );
        
        // Enviar email
        $this->sendPasswordResetEmail($user['email'], $resetToken);
        
        return [
            'success' => true,
            'message' => 'Se o email existir em nossa base, você receberá instruções para redefinir a senha.'
        ];
    }
    
    /**
     * Redefinir senha
     */
    public function resetPassword($token, $newPassword) {
        $user = $this->db->fetchOne(
            "SELECT id FROM users 
             WHERE reset_token = ? 
             AND reset_token_expires > NOW()",
            [$token]
        );
        
        if (!$user) {
            return [
                'success' => false,
                'message' => 'Token inválido ou expirado.'
            ];
        }
        
        // Validar nova senha
        if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
            return [
                'success' => false,
                'message' => 'A senha deve ter pelo menos ' . PASSWORD_MIN_LENGTH . ' caracteres.'
            ];
        }
        
        // Atualizar senha
        $this->db->update('users',
            [
                'password_hash' => password_hash($newPassword, PASSWORD_BCRYPT),
                'reset_token' => null,
                'reset_token_expires' => null
            ],
            'id = ?',
            [$user['id']]
        );
        
        return [
            'success' => true,
            'message' => 'Senha redefinida com sucesso.'
        ];
    }
    
    /**
     * Alterar senha (usuário logado)
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        $user = $this->db->getUserById($userId);
        
        if (!$user) {
            return [
                'success' => false,
                'message' => 'Usuário não encontrado.'
            ];
        }
        
        // Verificar senha atual
        //if (!password_verify($currentPassword, $user['password_hash'])) {
        if ($currentPassword != $user['password_hash']) {            
            return [
                'success' => false,
                'message' => 'Senha atual incorreta.'
            ];
        }
        
        // Validar nova senha
        if (strlen($newPassword) < PASSWORD_MIN_LENGTH) {
            return [
                'success' => false,
                'message' => 'A nova senha deve ter pelo menos ' . PASSWORD_MIN_LENGTH . ' caracteres.'
            ];
        }
        
        // Atualizar senha
        $this->db->update('users',
            ['password_hash' => password_hash($newPassword, PASSWORD_BCRYPT)],
            'id = ?',
            [$userId]
        );
        
        return [
            'success' => true,
            'message' => 'Senha alterada com sucesso.'
        ];
    }
    
    /**
     * Verificar se usuário tem permissão para acessar disputa
     */
    public function canAccessDispute($userId, $disputeId) {
        $user = $this->db->getUserById($userId);
        $dispute = $this->db->getDisputeById($disputeId);
        
        if (!$user || !$dispute) {
            return false;
        }
        
        // Admin tem acesso total
        if ($user['role'] === 'admin') {
            return true;
        }
        
        // Verificar se é da mesma empresa
        if ($user['company_id'] && $user['company_id'] == $dispute['company_id']) {
            return true;
        }
        
        // Verificar se é uma das partes
        if ($userId == $dispute['claimant_id'] || $userId == $dispute['respondent_id']) {
            return true;
        }
        
        // Verificar se é o árbitro
        if ($user['role'] === 'arbitrator') {
            $arbitrator = $this->db->fetchOne(
                "SELECT id FROM arbitrators WHERE user_id = ?",
                [$userId]
            );
            
            if ($arbitrator && $arbitrator['id'] == $dispute['arbitrator_id']) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Funções privadas auxiliares
     */
    
    private function createSession($user) {
        session_regenerate_id(true);
        
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['company_id'] = $user['company_id'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        $_SESSION['user_data'] = $this->sanitizeUserData($user);
    }
    
    private function sanitizeUserData($user) {
        unset($user['password_hash']);
        unset($user['reset_token']);
        unset($user['reset_token_expires']);
        unset($user['verification_token']);
        unset($user['two_factor_secret']);
        
        return $user;
    }
    
    private function validateRegistrationData($data) {
        $errors = [];
        
        // Email
        if (empty($data['email'])) {
            $errors[] = 'Email é obrigatório.';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email inválido.';
        }
        
        // Senha
        if (empty($data['password'])) {
            $errors[] = 'Senha é obrigatória.';
        } elseif (strlen($data['password']) < PASSWORD_MIN_LENGTH) {
            $errors[] = 'A senha deve ter pelo menos ' . PASSWORD_MIN_LENGTH . ' caracteres.';
        }
        
        // Nome
        if (empty($data['first_name'])) {
            $errors[] = 'Nome é obrigatório.';
        }
        
        if (empty($data['last_name'])) {
            $errors[] = 'Sobrenome é obrigatório.';
        }
        
        // CPF (se fornecido)
        if (!empty($data['cpf']) && !$this->validateCPF($data['cpf'])) {
            $errors[] = 'CPF inválido.';
        }
        
        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => implode(' ', $errors)
            ];
        }
        
        return ['success' => true];
    }
    
    private function validateCPF($cpf) {
        // Remove caracteres não numéricos
        $cpf = preg_replace('/[^0-9]/', '', $cpf);
        
        if (strlen($cpf) != 11) {
            return false;
        }
        
        // Verifica sequências repetidas
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }
        
        // Validação do dígito verificador
        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf[$c] * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf[$c] != $d) {
                return false;
            }
        }
        
        return true;
    }
    
    private function sendVerificationEmail($email, $token) {
        $verificationUrl = BASE_URL . "/verify-email.php?token=$token";
        
        $subject = "Verificação de Email - Arbitrivm";
        $message = "
            <h2>Bem-vindo ao Arbitrivm!</h2>
            <p>Para ativar sua conta, clique no link abaixo:</p>
            <p><a href='$verificationUrl'>Verificar Email</a></p>
            <p>Ou copie e cole este link no seu navegador:</p>
            <p>$verificationUrl</p>
            <p>Este link expira em 24 horas.</p>
        ";
        
        // Aqui você implementaria o envio real do email
        // Por enquanto, apenas log
        logError("Email de verificação seria enviado para: $email com token: $token");
    }
    
    private function sendPasswordResetEmail($email, $token) {
        $resetUrl = BASE_URL . "/reset-password.php?token=$token";
        
        $subject = "Redefinição de Senha - Arbitrivm";
        $message = "
            <h2>Redefinição de Senha</h2>
            <p>Você solicitou a redefinição de sua senha.</p>
            <p><a href='$resetUrl'>Redefinir Senha</a></p>
            <p>Ou copie e cole este link no seu navegador:</p>
            <p>$resetUrl</p>
            <p>Este link expira em 1 hora.</p>
            <p>Se você não solicitou esta redefinição, ignore este email.</p>
        ";
        
        // Implementar envio real do email
        logError("Email de reset seria enviado para: $email com token: $token");
    }
}