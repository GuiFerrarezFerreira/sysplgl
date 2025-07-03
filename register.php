<?php
/**
 * Arbitrivm - Página de Registro
 */

require_once 'config.php';

// Se já estiver logado, redirecionar
if (isLoggedIn()) {
    redirect('index.php');
    exit;
}

$errors = [];
$formData = [];

// Processar registro se for POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validar CSRF
    if (!validateCSRF($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Token de segurança inválido. Por favor, recarregue a página.';
    } else {
        // Coletar dados do formulário
        $formData = [
            'first_name' => sanitizeInput($_POST['first_name'] ?? ''),
            'last_name' => sanitizeInput($_POST['last_name'] ?? ''),
            'email' => sanitizeInput($_POST['email'] ?? ''),
            'cpf' => sanitizeInput($_POST['cpf'] ?? ''),
            'phone' => sanitizeInput($_POST['phone'] ?? ''),
            'password' => $_POST['password'] ?? '',
            'password_confirm' => $_POST['password_confirm'] ?? '',
            'account_type' => $_POST['account_type'] ?? 'individual',
            'company_name' => sanitizeInput($_POST['company_name'] ?? ''),
            'cnpj' => sanitizeInput($_POST['cnpj'] ?? ''),
            'terms_accepted' => isset($_POST['terms_accepted'])
        ];
        
        // Validar campos
        if (empty($formData['first_name'])) {
            $errors[] = 'Nome é obrigatório.';
        }
        
        if (empty($formData['last_name'])) {
            $errors[] = 'Sobrenome é obrigatório.';
        }
        
        if (empty($formData['email']) || !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email válido é obrigatório.';
        }
        
        if (empty($formData['password'])) {
            $errors[] = 'Senha é obrigatória.';
        } elseif (strlen($formData['password']) < PASSWORD_MIN_LENGTH) {
            $errors[] = 'A senha deve ter pelo menos ' . PASSWORD_MIN_LENGTH . ' caracteres.';
        }
        
        if ($formData['password'] !== $formData['password_confirm']) {
            $errors[] = 'As senhas não coincidem.';
        }
        
        if (!$formData['terms_accepted']) {
            $errors[] = 'Você deve aceitar os termos de uso.';
        }
        
        // Se for conta empresarial, validar campos adicionais
        if ($formData['account_type'] === 'company') {
            if (empty($formData['company_name'])) {
                $errors[] = 'Nome da empresa é obrigatório.';
            }
            
            if (empty($formData['cnpj'])) {
                $errors[] = 'CNPJ é obrigatório.';
            }
        }
        
        // Se não houver erros, tentar registrar
        if (empty($errors)) {
            $auth = new Auth();
            $db = new Database();
            
            // Preparar dados para registro
            $registrationData = [
                'first_name' => $formData['first_name'],
                'last_name' => $formData['last_name'],
                'email' => $formData['email'],
                'cpf' => $formData['cpf'],
                'phone' => $formData['phone'],
                'password' => $formData['password'],
                'role' => 'user'
            ];
            
            // Se for empresa, criar empresa primeiro
            if ($formData['account_type'] === 'company') {
                try {
                    // Verificar se CNPJ já existe
                    $existingCompany = $db->fetchOne(
                        "SELECT id FROM companies WHERE cnpj = ?", 
                        [$formData['cnpj']]
                    );
                    
                    if ($existingCompany) {
                        $errors[] = 'Este CNPJ já está cadastrado.';
                    } else {
                        // Criar empresa
                        $companyId = $db->insert('companies', [
                            'cnpj' => $formData['cnpj'],
                            'company_name' => $formData['company_name'],
                            'email' => $formData['email'],
                            'phone' => $formData['phone'],
                            'company_type' => 'other',
                            'subscription_plan' => 'basic',
                            'subscription_status' => 'active',
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                        
                        $registrationData['company_id'] = $companyId;
                        $registrationData['role'] = 'manager';
                    }
                } catch (Exception $e) {
                    $errors[] = 'Erro ao criar empresa. Por favor, tente novamente.';
                }
            }
            
            // Registrar usuário se não houver erros
            if (empty($errors)) {
                $result = $auth->register($registrationData);
                
                if ($result['success']) {
                    redirect('login.php?registered=1');
                    exit;
                } else {
                    $errors[] = $result['message'];
                }
            }
        }
    }
}

$csrfToken = generateCSRF();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Arbitrivm</title>
    
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

        .register-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 2rem 1rem;
        }

        .register-box {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow-lg);
            width: 100%;
            max-width: 600px;
        }

        .register-logo {
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

        .register-logo h1 {
            font-size: 1.5rem;
            color: var(--primary);
            margin-top: 0.75rem;
        }

        .register-logo p {
            color: var(--gray);
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }

        .account-type-selector {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .account-type-option {
            flex: 1;
            padding: 1rem;
            border: 2px solid var(--border);
            border-radius: var(--radius);
            text-align: center;
            cursor: pointer;
            transition: var(--transition);
        }

        .account-type-option.active {
            border-color: var(--primary);
            background-color: #eff6ff;
        }

        .account-type-option h3 {
            font-size: 1rem;
            margin-bottom: 0.25rem;
        }

        .account-type-option p {
            font-size: 0.75rem;
            color: var(--gray);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        .form-group {
            margin-bottom: 1rem;
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
            padding: 0.625rem 0.875rem;
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
            align-items: start;
            gap: 0.5rem;
            margin: 1.5rem 0;
        }

        .form-checkbox input {
            margin-top: 0.25rem;
            cursor: pointer;
        }

        .form-checkbox label {
            font-size: 0.875rem;
            line-height: 1.5;
        }

        .form-checkbox a {
            color: var(--primary);
            text-decoration: none;
        }

        .form-checkbox a:hover {
            text-decoration: underline;
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

        .error-messages {
            background-color: #fee2e2;
            color: #991b1b;
            padding: 0.75rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
            font-size: 0.875rem;
        }

        .error-messages ul {
            margin: 0;
            padding-left: 1.25rem;
        }

        .form-footer {
            text-align: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
        }

        .form-footer a {
            color: var(--primary);
            text-decoration: none;
            font-size: 0.875rem;
        }

        .form-footer a:hover {
            text-decoration: underline;
        }

        .company-fields {
            display: none;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border);
        }

        .company-fields.active {
            display: block;
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
            .register-box {
                padding: 1.5rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .account-type-selector {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-box">
            <div class="register-logo">
                <div class="logo-icon">A</div>
                <h1>Criar Conta</h1>
                <p>Junte-se ao Arbitrivm</p>
            </div>

            <?php if (!empty($errors)): ?>
                <div class="error-messages">
                    <ul>
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="POST" action="register.php" id="registerForm">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                
                <!-- Tipo de Conta -->
                <div class="account-type-selector">
                    <div class="account-type-option <?php echo ($formData['account_type'] ?? 'individual') === 'individual' ? 'active' : ''; ?>" 
                         onclick="selectAccountType('individual')">
                        <h3>Pessoa Física</h3>
                        <p>Para indivíduos e profissionais</p>
                    </div>
                    <div class="account-type-option <?php echo ($formData['account_type'] ?? '') === 'company' ? 'active' : ''; ?>" 
                         onclick="selectAccountType('company')">
                        <h3>Empresa</h3>
                        <p>Para empresas e imobiliárias</p>
                    </div>
                </div>
                <input type="hidden" name="account_type" id="accountType" value="<?php echo $formData['account_type'] ?? 'individual'; ?>">

                <!-- Dados Pessoais -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="first_name">Nome *</label>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="first_name" 
                            name="first_name" 
                            value="<?php echo htmlspecialchars($formData['first_name'] ?? ''); ?>"
                            required
                        >
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="last_name">Sobrenome *</label>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="last_name" 
                            name="last_name" 
                            value="<?php echo htmlspecialchars($formData['last_name'] ?? ''); ?>"
                            required
                        >
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="email">Email *</label>
                    <input 
                        type="email" 
                        class="form-control" 
                        id="email" 
                        name="email" 
                        value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>"
                        required
                    >
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="cpf">CPF</label>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="cpf" 
                            name="cpf" 
                            value="<?php echo htmlspecialchars($formData['cpf'] ?? ''); ?>"
                            placeholder="000.000.000-00"
                            maxlength="14"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="phone">Telefone</label>
                        <input 
                            type="tel" 
                            class="form-control" 
                            id="phone" 
                            name="phone" 
                            value="<?php echo htmlspecialchars($formData['phone'] ?? ''); ?>"
                            placeholder="(00) 00000-0000"
                            maxlength="15"
                        >
                    </div>
                </div>

                <!-- Campos da Empresa -->
                <div id="companyFields" class="company-fields <?php echo ($formData['account_type'] ?? '') === 'company' ? 'active' : ''; ?>">
                    <div class="form-group">
                        <label class="form-label" for="company_name">Nome da Empresa *</label>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="company_name" 
                            name="company_name" 
                            value="<?php echo htmlspecialchars($formData['company_name'] ?? ''); ?>"
                        >
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="cnpj">CNPJ *</label>
                        <input 
                            type="text" 
                            class="form-control" 
                            id="cnpj" 
                            name="cnpj" 
                            value="<?php echo htmlspecialchars($formData['cnpj'] ?? ''); ?>"
                            placeholder="00.000.000/0000-00"
                            maxlength="18"
                        >
                    </div>
                </div>

                <!-- Senha -->
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="password">Senha *</label>
                        <input 
                            type="password" 
                            class="form-control" 
                            id="password" 
                            name="password" 
                            required
                            minlength="<?php echo PASSWORD_MIN_LENGTH; ?>"
                        >
                        <div class="password-strength">
                            <div class="password-strength-bar">
                                <div id="passwordStrengthFill" class="password-strength-fill"></div>
                            </div>
                            <span id="passwordStrengthText">Mínimo de <?php echo PASSWORD_MIN_LENGTH; ?> caracteres</span>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label" for="password_confirm">Confirmar Senha *</label>
                        <input 
                            type="password" 
                            class="form-control" 
                            id="password_confirm" 
                            name="password_confirm" 
                            required
                        >
                    </div>
                </div>

                <!-- Termos -->
                <div class="form-checkbox">
                    <input 
                        type="checkbox" 
                        id="terms_accepted" 
                        name="terms_accepted" 
                        value="1"
                        <?php echo ($formData['terms_accepted'] ?? false) ? 'checked' : ''; ?>
                        required
                    >
                    <label for="terms_accepted">
                        Li e aceito os <a href="#" target="_blank">Termos de Uso</a> e a 
                        <a href="#" target="_blank">Política de Privacidade</a> do Arbitrivm.
                    </label>
                </div>

                <button type="submit" class="btn btn-primary" id="registerBtn">
                    Criar Conta
                </button>

                <div class="form-footer">
                    <span style="color: var(--gray);">Já tem conta?</span>
                    <a href="login.php">Fazer Login</a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Seleção de tipo de conta
        function selectAccountType(type) {
            document.querySelectorAll('.account-type-option').forEach(option => {
                option.classList.remove('active');
            });
            
            event.target.closest('.account-type-option').classList.add('active');
            document.getElementById('accountType').value = type;
            
            const companyFields = document.getElementById('companyFields');
            if (type === 'company') {
                companyFields.classList.add('active');
                document.getElementById('company_name').setAttribute('required', 'required');
                document.getElementById('cnpj').setAttribute('required', 'required');
            } else {
                companyFields.classList.remove('active');
                document.getElementById('company_name').removeAttribute('required');
                document.getElementById('cnpj').removeAttribute('required');
            }
        }

        // Máscara para CPF
        document.getElementById('cpf').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 11) {
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                e.target.value = value;
            }
        });

        // Máscara para CNPJ
        document.getElementById('cnpj').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 14) {
                value = value.replace(/(\d{2})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d)/, '$1.$2');
                value = value.replace(/(\d{3})(\d)/, '$1/$2');
                value = value.replace(/(\d{4})(\d{1,2})$/, '$1-$2');
                e.target.value = value;
            }
        });

        // Máscara para telefone
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 11) {
                value = value.replace(/(\d{2})(\d)/, '($1) $2');
                value = value.replace(/(\d{5})(\d)/, '$1-$2');
                e.target.value = value;
            }
        });

        // Força da senha
        document.getElementById('password').addEventListener('input', function(e) {
            const password = e.target.value;
            const strengthFill = document.getElementById('passwordStrengthFill');
            const strengthText = document.getElementById('passwordStrengthText');
            
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

        // Handle form submission
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('registerBtn');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner"></span>Criando conta...';
        });
    </script>
</body>
</html>