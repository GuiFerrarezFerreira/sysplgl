<?php
/**
 * forgot-password.php - Recuperação de Senha
 */

session_start();
require_once 'config.php';
require_once 'includes/database.php';
require_once 'includes/functions.php';

$db = new Database();
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    
    if (empty($email)) {
        $message = 'Por favor, informe seu email.';
        $messageType = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Por favor, informe um email válido.';
        $messageType = 'error';
    } else {
        // Buscar usuário
        $user = $db->fetchOne("SELECT id, name, email FROM users WHERE email = ?", [$email]);
        
        if ($user) {
            // Gerar token
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Salvar token no banco
            $db->insert('password_resets', [
                'user_id' => $user['id'],
                'token' => $token,
                'expires_at' => $expiry
            ]);
            
            // Enviar email
            $resetLink = BASE_URL . "/reset-password.php?token=" . $token;
            $emailBody = "
                <h2>Recuperação de Senha - Arbitrivm</h2>
                <p>Olá {$user['name']},</p>
                <p>Recebemos uma solicitação para redefinir sua senha.</p>
                <p>Para criar uma nova senha, clique no link abaixo:</p>
                <p><a href='{$resetLink}' style='background: #2563eb; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block;'>Redefinir Senha</a></p>
                <p>Ou copie e cole este link no seu navegador:<br>{$resetLink}</p>
                <p>Este link expira em 1 hora.</p>
                <p>Se você não solicitou a redefinição de senha, ignore este email.</p>
                <p>Atenciosamente,<br>Equipe Arbitrivm</p>
            ";
            
            if (sendEmail($user['email'], 'Recuperação de Senha - Arbitrivm', $emailBody)) {
                $message = 'Instruções para redefinir sua senha foram enviadas para seu email.';
                $messageType = 'success';
            } else {
                $message = 'Erro ao enviar email. Tente novamente mais tarde.';
                $messageType = 'error';
            }
        } else {
            // Por segurança, mostrar a mesma mensagem mesmo se o email não existir
            $message = 'Instruções para redefinir sua senha foram enviadas para seu email.';
            $messageType = 'success';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Senha - Arbitrivm</title>
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

        .help-text {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 1.5rem;
            line-height: 1.5;
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
                <p class="subtitle">Recuperação de Senha</p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($messageType !== 'success'): ?>
                <p class="help-text">
                    Digite seu email cadastrado e enviaremos instruções para redefinir sua senha.
                </p>

                <form method="POST">
                    <div class="form-group">
                        <label class="form-label" for="email">Email</label>
                        <input 
                            type="email" 
                            class="form-control" 
                            id="email" 
                            name="email" 
                            required 
                            autofocus
                            placeholder="seu@email.com"
                        >
                    </div>

                    <button type="submit" class="btn btn-primary">
                        Enviar Instruções
                    </button>

                    <a href="login.php">
                        <button type="button" class="btn btn-secondary">
                            Voltar ao Login
                        </button>
                    </a>
                </form>
            <?php else: ?>
                <a href="login.php">
                    <button type="button" class="btn btn-primary">
                        Voltar ao Login
                    </button>
                </a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>