<?php
/**
 * Arbitrivm - Recuperação de Senha
 */

require_once 'config.php';

// Se já estiver logado, redirecionar
if (isLoggedIn()) {
    redirect('index.php');
    exit;
}

$message = '';
$error = '';
$success = false;

// Processar solicitação
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    
    if (validateCSRF($_POST['csrf_token'] ?? '')) {
        $auth = new Auth();
        $result = $auth->forgotPassword($email);
        
        if ($result['success']) {
            $success = true;
            $message = $result['message'];
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
    <title>Recuperar Senha - Arbitrivm</title>
    
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
            padding: 0.75rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
            font-size: 0.875rem;
            text-align: center;
        }

        .info-message {
            background-color: #dbeafe;
            color: #1e40af;
            padding: 0.75rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
            font-size: 0.875rem;
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
                <h1>Recuperar Senha</h1>
            </div>

            <?php if ($success): ?>
                <div class="success-icon">
                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="success-message"><?php echo htmlspecialchars($message); ?></div>
                <div class="form-footer">
                    <a href="login.php">Voltar ao Login</a>
                </div>
            <?php else: ?>
                <?php if ($error): ?>
                    <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>

                <div class="info-message">
                    Digite seu email cadastrado e enviaremos instruções para redefinir sua senha.
                </div>

                <form method="POST" action="forgot-password.php" id="forgotForm">
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
                            placeholder="seu@email.com"
                        >
                    </div>

                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        Enviar Email de Recuperação
                    </button>

                    <div class="form-footer">
                        <a href="login.php">Voltar ao Login</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script>
        document.getElementById('forgotForm')?.addEventListener('submit', function(e) {
            const btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span>Enviando...';
        });
    </script>
</body>
</html>