<?php
/**
 * reset-password.php - Redefinir Senha
 */

session_start();
require_once 'config.php';
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$db = new Database();
$message = '';
$messageType = '';
$validToken = false;
$token = $_GET['token'] ?? '';

// Validar token
if ($token) {
    $reset = $db->fetchOne("
        SELECT pr.*, u.email 
        FROM password_resets pr
        INNER JOIN users u ON pr.user_id = u.id
        WHERE pr.token = ? 
        AND pr.expires_at > NOW() 
        AND pr.used = 0
    ", [$token]);
    
    if ($reset) {
        $validToken = true;
    } else {
        $message = 'Link inválido ou expirado. Solicite um novo link de recuperação.';
        $messageType = 'error';
    }
}

// Processar nova senha
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    if (empty($password) || empty($confirmPassword)) {
        $message = 'Por favor, preencha todos os campos.';
        $messageType = 'error';
    } elseif ($password !== $confirmPassword) {
        $message = 'As senhas não coincidem.';
        $messageType = 'error';
    } elseif (!isStrongPassword($password)) {
        $message = 'A senha deve ter pelo menos 8 caracteres e conter letras maiúsculas, minúsculas e números.';
        $messageType = 'error';
    } else {
        try {
            $db->beginTransaction();
            
            // Atualizar senha
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $db->update('users', 
                ['password' => $hashedPassword],
                'id = ?',
                [$reset['user_id']]
            );
            
            // Marcar token como usado
            $db->update('password_resets',
                ['used' => 1, 'used_at' => date('Y-m-d H:i:s')],
                'id = ?',
                [$reset['id']]
            );
            
            // Registrar no log de segurança
            $db->insert('security_logs', [
                'user_id' => $reset['user_id'],
                'event_type' => 'password_reset',
                'event_description' => 'Senha redefinida via link de recuperação',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
            ]);
            
            // Invalidar outras sessões do usuário
            $db->delete('user_sessions', 'user_id = ?', [$reset['user_id']]);
            
            $db->commit();
            
            $message = 'Senha redefinida com sucesso! Você já pode fazer login.';
            $messageType = 'success';
            $validToken = false; // Prevenir reutilização
            
        } catch (Exception $e) {
            $db->rollback();
            $message = 'Erro ao redefinir senha. Tente novamente.';
            $messageType = 'error';
            logError('Erro ao redefinir senha: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinir Senha - Arbitrivm</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .container {
            width: 100%;
            max-width: 400px;
            padding: 1rem;
        }

        .box {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            padding: 2rem;
        }

        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: bold;
        }

        h1 {
            margin-top: 1rem;
            color: #1f2937;
            font-size: 1.875rem;
        }

        .subtitle {
            color: #6b7280;
            margin-top: 0.5rem;
            margin-bottom: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            color: #374151;
            font-weight: 500;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid #d1d5db;
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: all 0.2s;
        }

        .form-control:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .password-wrapper {
            position: relative;
        }

        .toggle-password {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #6b7280;
            cursor: pointer;
        }

        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.75rem;
        }

        .strength-weak {
            color: #ef4444;
        }

        .strength-medium {
            color: #f59e0b;
        }

        .strength-strong {
            color: #10b981;
        }

        .btn {
            width: 100%;
            padding: 0.75rem;
            border: none;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #2563eb;
            color: white;
            margin-bottom: 1rem;
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-primary:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
        }

        .alert {
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .requirements {
            background: #f9fafb;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }

        .requirements h3 {
            font-size: 0.875rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #374151;
        }

        .requirement {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.25rem;
            color: #6b7280;
        }

        .requirement.met {
            color: #10b981;
        }

        .requirement-icon {
            width: 16px;
            height: 16px;
        }

        a {
            color: #2563eb;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="box">
            <div class="logo">
                <div class="logo-icon">A</div>
                <h1>Arbitrivm</h1>
                <p class="subtitle">Criar Nova Senha</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($validToken): ?>
                <form method="POST" id="resetForm">
                    <div class="requirements">
                        <h3>Requisitos da senha:</h3>
                        <div class="requirement" id="req-length">
                            <span class="requirement-icon">○</span>
                            Mínimo 8 caracteres
                        </div>
                        <div class="requirement" id="req-uppercase">
                            <span class="requirement-icon">○</span>
                            Uma letra maiúscula
                        </div>
                        <div class="requirement" id="req-lowercase">
                            <span class="requirement-icon">○</span>
                            Uma letra minúscula
                        </div>
                        <div class="requirement" id="req-number">
                            <span class="requirement-icon">○</span>
                            Um número
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password">Nova Senha</label>
                        <div class="password-wrapper">
                            <input 
                                type="password" 
                                class="form-control" 
                                id="password" 
                                name="password" 
                                required
                                onkeyup="checkPasswordStrength()"
                            >
                            <button type="button" class="toggle-password" onclick="togglePassword('password')">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                                    <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z"/>
                                </svg>
                            </button>
                        </div>
                        <div class="password-strength" id="passwordStrength"></div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirmar Nova Senha</label>
                        <div class="password-wrapper">
                            <input 
                                type="password" 
                                class="form-control" 
                                id="confirm_password" 
                                name="confirm_password" 
                                required
                                onkeyup="checkPasswordMatch()"
                            >
                            <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                                    <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z"/>
                                </svg>
                            </button>
                        </div>
                        <div class="password-strength" id="passwordMatch"></div>
                    </div>

                    <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                        Redefinir Senha
                    </button>

                    <a href="login.php">
                        <button type="button" class="btn btn-secondary">
                            Cancelar
                        </button>
                    </a>
                </form>
            <?php else: ?>
                <a href="forgot-password.php">
                    <button type="button" class="btn btn-primary">
                        Solicitar Novo Link
                    </button>
                </a>
                <a href="login.php">
                    <button type="button" class="btn btn-secondary">
                        Voltar ao Login
                    </button>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            field.type = field.type === 'password' ? 'text' : 'password';
        }

        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthDiv = document.getElementById('passwordStrength');
            const submitBtn = document.getElementById('submitBtn');
            
            // Verificar requisitos
            const requirements = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /[0-9]/.test(password)
            };
            
            // Atualizar indicadores
            Object.keys(requirements).forEach(req => {
                const element = document.getElementById(`req-${req}`);
                if (requirements[req]) {
                    element.classList.add('met');
                    element.querySelector('.requirement-icon').textContent = '✓';
                } else {
                    element.classList.remove('met');
                    element.querySelector('.requirement-icon').textContent = '○';
                }
            });
            
            // Calcular força
            const metRequirements = Object.values(requirements).filter(v => v).length;
            
            if (password.length === 0) {
                strengthDiv.textContent = '';
            } else if (metRequirements < 2) {
                strengthDiv.textContent = 'Senha fraca';
                strengthDiv.className = 'password-strength strength-weak';
            } else if (metRequirements < 4) {
                strengthDiv.textContent = 'Senha média';
                strengthDiv.className = 'password-strength strength-medium';
            } else {
                strengthDiv.textContent = 'Senha forte';
                strengthDiv.className = 'password-strength strength-strong';
            }
            
            // Verificar se pode habilitar botão
            checkFormValidity();
        }

        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchDiv = document.getElementById('passwordMatch');
            
            if (confirmPassword.length === 0) {
                matchDiv.textContent = '';
            } else if (password === confirmPassword) {
                matchDiv.textContent = 'Senhas coincidem';
                matchDiv.className = 'password-strength strength-strong';
            } else {
                matchDiv.textContent = 'Senhas não coincidem';
                matchDiv.className = 'password-strength strength-weak';
            }
            
            checkFormValidity();
        }

        function checkFormValidity() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const submitBtn = document.getElementById('submitBtn');
            
            const isValid = password.length >= 8 && 
                           /[A-Z]/.test(password) && 
                           /[a-z]/.test(password) && 
                           /[0-9]/.test(password) && 
                           password === confirmPassword;
            
            submitBtn.disabled = !isValid;
        }
    </script>
</body>
</html>