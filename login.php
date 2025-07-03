<?php
/**
 * Arbitrivm - Página de Login
 */

require_once 'config.php';

// Se já estiver logado, redirecionar para dashboard
if (isLoggedIn()) {
    redirect('index.php');
    exit;
}

// Processar login se for POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (validateCSRF($_POST['csrf_token'] ?? '')) {
        $auth = new Auth();
        $result = $auth->login($email, $password);
        
        if ($result['success']) {
            redirect('index.php');
            exit;
        } else {
            $error = $result['message'];
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
    <title>Login - Arbitrivm</title>
    
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

        .login-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 1rem;
        }

        .login-box {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 400px;
        }

        .login-logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 2rem;
            font-weight: bold;
        }

        .login-logo h1 {
            font-size: 2rem;
            color: var(--primary);
            margin-top: 1rem;
        }

        .login-logo p {
            color: var(--gray);
            font-size: 0.875rem;
            margin-top: 0.5rem;
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

        .form-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }

        .form-checkbox input {
            cursor: pointer;
        }

        .form-checkbox span {
            font-size: 0.875rem;
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
            padding: 0.75rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
            font-size: 0.875rem;
            text-align: center;
        }

        .form-footer {
            text-align: center;
            margin-top: 2rem;
        }

        .form-footer a {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.875rem;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        .divider {
            margin: 1.5rem 0;
            text-align: center;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: var(--border);
        }

        .divider span {
            background: var(--white);
            padding: 0 1rem;
            position: relative;
            color: var(--gray);
            font-size: 0.875rem;
        }

        .demo-info {
            background-color: #f0f9ff;
            border: 1px solid #3b82f6;
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1.5rem;
            font-size: 0.875rem;
        }

        .demo-info strong {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--primary);
        }

        .demo-credentials {
            background-color: var(--white);
            padding: 0.5rem;
            border-radius: 0.25rem;
            margin-top: 0.5rem;
            font-family: monospace;
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

        @media (max-width: 640px) {
            .login-box {
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="login-logo">
                <div class="logo-icon">A</div>
                <h1>Arbitrivm</h1>
                <p>Sistema de Arbitragem Imobiliária</p>
            </div>

            <!-- Demo Info (remover em produção) -->
            <?php if (ENVIRONMENT === 'development'): ?>
            <div class="demo-info">
                <strong>🔒 Credenciais de Demonstração:</strong>
                <div class="demo-credentials">
                    Email: teste@teste.com<br>
                    Senha: teste
                </div>
            </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <?php if (isset($_GET['registered'])): ?>
                <div class="success-message">Cadastro realizado com sucesso! Faça login para continuar.</div>
            <?php endif; ?>

            <?php if (isset($_GET['logout'])): ?>
                <div class="success-message">Logout realizado com sucesso.</div>
            <?php endif; ?>

            <form method="POST" action="login.php" id="loginForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                
                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <input 
                        type="email" 
                        class="form-control" 
                        id="email" 
                        name="email" 
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                        required 
                        autofocus
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Senha</label>
                    <input 
                        type="password" 
                        class="form-control" 
                        id="password" 
                        name="password" 
                        required
                    >
                </div>

                <div class="form-group">
                    <label class="form-checkbox">
                        <input type="checkbox" name="remember_me" value="1">
                        <span>Lembrar de mim</span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary" id="loginBtn">
                    Entrar
                </button>

                <div class="form-footer">
                    <a href="forgot-password.php">Esqueceu sua senha?</a>
                </div>

                <div class="divider">
                    <span>ou</span>
                </div>

                <div class="form-footer">
                    <span style="color: var(--gray);">Não tem conta?</span>
                    <a href="register.php">Registre-se</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Handle form submission
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('loginBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span>Entrando...';
        });

        // Auto-fill demo credentials for development
        <?php if (ENVIRONMENT === 'development'): ?>
        document.addEventListener('DOMContentLoaded', function() {
            const demoInfo = document.querySelector('.demo-info');
            if (demoInfo) {
                demoInfo.style.cursor = 'pointer';
                demoInfo.addEventListener('click', function() {
                    document.getElementById('email').value = 'teste@teste.com';
                    document.getElementById('password').value = 'teste';
                });
            }
        });
        <?php endif; ?>
    </script>
</body>
</html>