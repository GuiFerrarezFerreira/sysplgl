<?php
/**
 * Arbitrivm - Redefinir Senha
 */

require_once 'config.php';

// Se já estiver logado, redirecionar
if (isLoggedIn()) {
    redirect('index.php');
    exit;
}

$token = $_GET['token'] ?? '';
$error = '';
$success = false;

// Verificar se token existe
if (!$token) {
    $_SESSION['error'] = 'Token de redefinição inválido.';
    redirect('forgot-password.php');
    exit;
}

// Processar nova senha
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['password'] ?? '';
    $confirmPassword = $_POST['password_confirm'] ?? '';
    
    if (validateCSRF($_POST['csrf_token'] ?? '')) {
        if ($newPassword !== $confirmPassword) {
            $error = 'As senhas não coincidem.';
        } else {
            $auth = new Auth();
            $result = $auth->resetPassword($token, $newPassword);
            
            if ($result['success']) {
                $success = true;
            } else {
                $error = $result['message'];
            }
        }
    } else {
        $error = 'Token de segurança inválido. Por favor, recarregue a página.';
    }
}

$csrfToken = generateCSRF();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinir Senha - Arbitrivm</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --dark: #1f2937;
            --gray: #6b7280;
            --light: #f3f4f6;
            --white: #ffffff;
            --border: #e5e7eb;
            --shadow: 0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
            --radius: 0.5rem;
            --transition: all 0.3s ease;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #f9fafb;
            color: var(--dark);
            line-height: 1.6;
        }

        .container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 1rem;
        }

        .box {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 400px;
        }

        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo-icon {
            width: 60px;
            height: 60px;
            margin: 0 auto;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: bold;
        }

        .logo h1 {
            font-size: 1.5rem;
            color: var(--primary);
            margin-top: 0.75rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
            font-size: 0.875rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            font-size: 0.875rem;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem 1.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: var(--radius);
            border: none;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            width: 100%;
        }

        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            background-color: var(--primary-dark);
        }

        .error-message {
            background-color: #fee2e2;
            color: #991b1b;
            padding: 0.75rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
            font-size: 0.875rem;
            text-align: center;
        }

        .success-message {
            background-color: #d1fae5;
            color: #065f46;
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
            font-size: 0.875rem;
            text-align: center;
        }

        .form-footer {
            text-align: center;
            margin-top: 1.5rem;
        }

        .form-footer a {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.875rem;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.75rem;
        }

        .password-strength-bar {
            height: 4px;
            background-color: var(--light);
            border-radius: 2px;
            margin-bottom: 0.25rem;
            overflow: hidden;
        }

        .password-strength-fill {
            height: 100%;
            transition: width 0.3s ease;
        }

        .strength-weak { background-color: var(--danger); width: 33%; }
        .strength-medium { background-color: var(--warning); width: 66%; }
        .strength-strong { background-color: var(--secondary); width: 100%; }

        .password-requirements {
            background-color: var(--light);
            padding: 0.75rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
            font-size: 0.75rem;
        }

        .requirement {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.25rem;
        }

        .requirement:last-child {
            margin-bottom: 0;
        }

        .requirement-icon {
            width: 16px;
            height: 16px;
            color: var(--gray);
        }

        .requirement.met .requirement-icon {
            color: var(--secondary);
        }

        .success-icon {
            width: 48px;
            height: 48px;
            margin: 0 auto 1rem;
            background: #d1fae5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--secondary);
        }

        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid var(--white);
            border-radius: 50%;
            border-top-color: transparent;
            animation: spin 0.8s linear infinite;
            margin-right: 0.5rem;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="box">
            <div class="logo">
                <div class="logo-icon">A</div>
                <h1>Nova Senha</h1>
            </div>

            <?php if ($success): ?>
                <div class="success-icon">
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="success-message">
                    Senha redefinida com sucesso!<br>
                    Você já pode fazer login com sua nova senha.
                </div>
                <a href="login.php" class="btn btn-primary">Ir para Login</a>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <form method="POST" action="reset-password.php?token=<?php echo urlencode($token); ?>" id="resetForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <div class="form-group">
                        <label class="form-label" for="password">Nova Senha</label>
                        <input 
                            type="password" 
                            class="form-control" 
                            id="password" 
                            name="password" 
                            required
                            minlength="<?php echo PASSWORD_MIN_LENGTH; ?>"
                            autofocus
                        >
                        <div class="password-strength">
                            <div class="password-strength-bar">
                                <div id="passwordStrengthFill" class="password-strength-fill"></div>
                            </div>
                            <span id="passwordStrengthText">Digite sua senha</span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password_confirm">Confirmar Nova Senha</label>
                        <input 
                            type="password" 
                            class="form-control" 
                            id="password_confirm" 
                            name="password_confirm" 
                            required
                        >
                    </div>

                    <div class="password-requirements">
                        <div class="requirement" id="req-length">
                            <svg class="requirement-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Mínimo de <?php echo PASSWORD_MIN_LENGTH; ?> caracteres
                        </div>
                        <div class="requirement" id="req-uppercase">
                            <svg class="requirement-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Uma letra maiúscula
                        </div>
                        <div class="requirement" id="req-lowercase">
                            <svg class="requirement-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Uma letra minúscula
                        </div>
                        <div class="requirement" id="req-number">
                            <svg class="requirement-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Um número
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        Redefinir Senha
                    </button>

                    <div class="form-footer">
                        <a href="login.php">Voltar ao Login</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Validação de força da senha
        document.getElementById('password')?.addEventListener('input', function(e) {
            const password = e.target.value;
            const strengthFill = document.getElementById('passwordStrengthFill');
            const strengthText = document.getElementById('passwordStrengthText');
            
            // Verificar requisitos
            const requirements = {
                'req-length': password.length >= <?php echo PASSWORD_MIN_LENGTH; ?>,
                'req-uppercase': /[A-Z]/.test(password),
                'req-lowercase': /[a-z]/.test(password),
                'req-number': /[0-9]/.test(password)
            };
            
            // Atualizar indicadores de requisitos
            let metCount = 0;
            for (const [id, met] of Object.entries(requirements)) {
                const element = document.getElementById(id);
                if (met) {
                    element.classList.add('met');
                    metCount++;
                } else {
                    element.classList.remove('met');
                }
            }
            
            // Calcular força
            let strength = 0;
            if (password.length >= <?php echo PASSWORD_MIN_LENGTH; ?>) strength++;
            if (password.length >= 12) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/[0-9]/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            strengthFill.className = 'password-strength-fill';
            
            if (strength <= 2) {
                strengthFill.classList.add('strength-weak');
                strengthText.textContent = 'Senha fraca';
                strengthText.style.color = 'var(--danger)';
            } else if (strength <= 3) {
                strengthFill.classList.add('strength-medium');
                strengthText.textContent = 'Senha média';
                strengthText.style.color = 'var(--warning)';
            } else {
                strengthFill.classList.add('strength-strong');
                strengthText.textContent = 'Senha forte';
                strengthText.style.color = 'var(--secondary)';
            }
        });

        // Validar confirmação de senha
        document.getElementById('resetForm')?.addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('password_confirm').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('As senhas não coincidem!');
                return;
            }
            
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span>Redefinindo...';
        });
    </script>
</body>
</html>