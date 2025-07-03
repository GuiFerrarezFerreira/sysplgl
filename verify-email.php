<?php
/**
 * Arbitrivm - Verificação de Email
 */

require_once 'config.php';

// Se já estiver logado, redirecionar
if (isLoggedIn()) {
    redirect('index.php');
    exit;
}

$token = $_GET['token'] ?? '';
$success = false;
$error = '';

if ($token) {
    $auth = new Auth();
    $result = $auth->verifyEmail($token);
    
    if ($result['success']) {
        $success = true;
        $message = $result['message'];
    } else {
        $error = $result['message'];
    }
} else {
    $error = 'Token de verificação não fornecido.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificação de Email - Arbitrivm</title>
    
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
            padding: 3rem 2rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }

        .icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 1.5rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .icon.success {
            background: #d1fae5;
            color: var(--secondary);
        }

        .icon.error {
            background: #fee2e2;
            color: var(--danger);
        }

        h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        p {
            color: var(--gray);
            margin-bottom: 2rem;
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
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .btn-secondary {
            background-color: var(--white);
            color: var(--gray);
            border: 1px solid var(--border);
            margin-left: 0.5rem;
        }

        .btn-secondary:hover {
            background-color: var(--light);
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid var(--light);
            border-radius: 50%;
            border-top-color: var(--primary);
            animation: spin 0.8s linear infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="box">
            <?php if ($success): ?>
                <div class="icon success">
                    <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h1>Email Verificado!</h1>
                <p>Sua conta foi verificada com sucesso. Agora você pode fazer login no sistema.</p>
                <a href="login.php" class="btn btn-primary">Ir para Login</a>
            <?php elseif ($error): ?>
                <div class="icon error">
                    <svg width="48" height="48" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <h1>Erro na Verificação</h1>
                <p><?php echo htmlspecialchars($error); ?></p>
                <a href="register.php" class="btn btn-primary">Criar Nova Conta</a>
                <a href="login.php" class="btn btn-secondary">Ir para Login</a>
            <?php else: ?>
                <div class="loading"></div>
                <h1>Verificando...</h1>
                <p>Aguarde enquanto verificamos seu email.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$success && !$error): ?>
    <script>
        // Auto-redirecionar se não houver token
        setTimeout(function() {
            window.location.href = 'login.php';
        }, 3000);
    </script>
    <?php endif; ?>
</body>
</html>