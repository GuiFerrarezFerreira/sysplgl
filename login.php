<?php
session_start();
require_once 'config.php';
require_once 'includes/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$db = new Database();
$error = '';

// Se já estiver logado, redirecionar
if (isset($_SESSION['user_id'])) {
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
    }
}

// Processar login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);
    
    if (empty($email) || empty($password)) {
        $error = 'Por favor, preencha todos os campos.';
    } else {
        $result = authenticateUser($email, $password);
        
        if ($result['success']) {
            // Configurar cookie "lembrar-me" se solicitado
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true);
                
                // Salvar token no banco
                $db->insert('remember_tokens', [
                    'user_id' => $_SESSION['user_id'],
                    'token' => hash('sha256', $token),
                    'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
                    'created_at' => date('Y-m-d H:i:s')
                ]);
            }
            
            // Redirecionar para URL salva ou dashboard apropriado
            $redirectUrl = $_SESSION['redirect_after_login'] ?? null;
            unset($_SESSION['redirect_after_login']);
            
            if ($redirectUrl) {
                redirect($redirectUrl);
            } else {
                switch ($result['user_type']) {
                    case 'admin':
                        redirect('/admin/');
                        break;
                    case 'arbitrator':
                        redirect('/arbitrator/');
                        break;
                    case 'party':
                        redirect('/party/');
                        break;
                }
            }
        } else {
            $error = $result['message'];
        }
    }
}

// Verificar cookie "lembrar-me"
if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_token'])) {
    $token = $_COOKIE['remember_token'];
    $hashedToken = hash('sha256', $token);
    
    $rememberData = $db->fetchOne("
        SELECT rt.*, u.* 
        FROM remember_tokens rt
        INNER JOIN users u ON rt.user_id = u.id
        WHERE rt.token = ? 
        AND rt.expires_at > NOW()
        AND u.is_active = 1
    ", [$hashedToken]);
    
    if ($rememberData) {
        createUserSession($rememberData);
        
        // Redirecionar baseado no tipo de usuário
        switch ($rememberData['user_type']) {
            case 'admin':
                redirect('/admin/');
                break;
            case 'arbitrator':
                redirect('/arbitrator/');
                break;
            case 'party':
                redirect('/party/');
                break;
        }
    }
}

// Mensagens de status
$statusMessage = '';
if (isset($_GET['registered'])) {
    $statusMessage = '<div class="alert alert-success">Cadastro realizado com sucesso! Faça login para continuar.</div>';
} elseif (isset($_GET['expired'])) {
    $statusMessage = '<div class="alert alert-warning">Sua sessão expirou. Por favor, faça login novamente.</div>';
} elseif (isset($_GET['logout'])) {
    $statusMessage = '<div class="alert alert-info">Você saiu do sistema com sucesso.</div>';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Arbitrivm</title>
    <style>
        /* Estilos do login.php existente com melhorias */
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

        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 1rem;
        }

        .login-box {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            padding: 2rem;
        }

        .login-logo {
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

        .login-logo h1 {
            margin-top: 1rem;
            color: #1f2937;
            font-size: 1.875rem;
        }

        .login-logo p {
            color: #6b7280;
            margin-top: 0.5rem;
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

        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .remember-me input {
            width: 1rem;
            height: 1rem;
        }

        .forgot-link {
            color: #2563eb;
            text-decoration: none;
            font-size: 0.875rem;
        }

        .forgot-link:hover {
            text-decoration: underline;
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
        }

        .btn-primary:hover {
            background: #1d4ed8;
        }

        .btn-primary:disabled {
            background: #9ca3af;
            cursor: not-allowed;
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

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .divider {
            text-align: center;
            margin: 1.5rem 0;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e5e7eb;
        }

        .divider span {
            background: white;
            padding: 0 1rem;
            position: relative;
            color: #6b7280;
            font-size: 0.875rem;
        }

        .register-link {
            text-align: center;
            margin-top: 1.5rem;
        }

        .register-link a {
            color: #2563eb;
            text-decoration: none;
            font-weight: 500;
        }

        .register-link a:hover {
            text-decoration: underline;
        }

        .loading {
            display: inline-block;
            width: 1rem;
            height: 1rem;
            border: 2px solid #f3f4f6;
            border-radius: 50%;
            border-top-color: #2563eb;
            animation: spin 0.8s linear infinite;
            margin-right: 0.5rem;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
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

            <?php echo $statusMessage; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" id="loginForm">
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
                    <div class="password-wrapper">
                        <input 
                            type="password" 
                            class="form-control" 
                            id="password" 
                            name="password" 
                            required
                        >
                        <button type="button" class="toggle-password" onclick="togglePassword()">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="remember-forgot">
                    <label class="remember-me">
                        <input type="checkbox" name="remember" id="remember">
                        <span>Lembrar de mim</span>
                    </label>
                    <a href="forgot-password.php" class="forgot-link">Esqueceu a senha?</a>
                </div>

                <button type="submit" class="btn btn-primary" id="submitBtn">
                    Entrar
                </button>
            </form>

            <div class="divider">
                <span>ou</span>
            </div>

            <div class="register-link">
                <p>Não tem uma conta?</p>
                <a href="register.php">Cadastre-se como Parte</a> | 
                <a href="register-arbitrator.php">Seja um Árbitro</a>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const type = passwordInput.type === 'password' ? 'text' : 'password';
            passwordInput.type = type;
        }

        // Adicionar indicador de carregamento ao enviar
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="loading"></span>Entrando...';
        });
    </script>
</body>
</html>