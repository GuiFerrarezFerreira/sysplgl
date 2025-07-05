<?php
/**
 * Functions.php - Sistema de Arbitragem Locatícia
 * Funções principais do sistema
 */

// Configurações de segurança
if (!defined('BASEPATH')) {
    define('BASEPATH', true);
}

// Incluir arquivo de configuração
require_once 'config.php';

// ========================
// FUNÇÕES DE AUTENTICAÇÃO
// ========================

/**
 * Gerar token JWT
 */
function generateJWT($user_id, $email, $tipo) {
    $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
    $payload = json_encode([
        'user_id' => $user_id,
        'email' => $email,
        'tipo' => $tipo,
        'iat' => time(),
        'exp' => time() + (86400 * 7) // 7 dias
    ]);
    
    $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    
    $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, JWT_SECRET, true);
    $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    return $base64Header . "." . $base64Payload . "." . $base64Signature;
}

/**
 * Validar token JWT
 */
function validateJWT($token) {
    $tokenParts = explode('.', $token);
    if (count($tokenParts) != 3) {
        return false;
    }
    
    $header = base64_decode($tokenParts[0]);
    $payload = base64_decode($tokenParts[1]);
    $signatureProvided = $tokenParts[2];
    
    $base64Header = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($header));
    $base64Payload = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($payload));
    
    $signature = hash_hmac('sha256', $base64Header . "." . $base64Payload, JWT_SECRET, true);
    $base64Signature = str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($signature));
    
    if ($base64Signature !== $signatureProvided) {
        return false;
    }
    
    $payloadData = json_decode($payload, true);
    
    // Verificar expiração
    if ($payloadData['exp'] < time()) {
        return false;
    }
    
    return $payloadData;
}

/**
 * Hash de senha seguro
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verificar senha
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// ========================
// FUNÇÕES DE BANCO DE DADOS
// ========================

/**
 * Conectar ao banco de dados
 */
function getDBConnection() {
    try {
        $pdo = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        return $pdo;
    } catch (PDOException $e) {
        logError("Erro de conexão DB: " . $e->getMessage());
        return false;
    }
}

/**
 * Executar query com prepared statement
 */
function executeQuery($sql, $params = []) {
    $pdo = getDBConnection();
    if (!$pdo) return false;
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        logError("Erro na query: " . $e->getMessage());
        return false;
    }
}

/**
 * Inserir registro e retornar ID
 */
function insertRecord($table, $data) {
    $columns = implode(', ', array_keys($data));
    $placeholders = ':' . implode(', :', array_keys($data));
    
    $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
    
    $pdo = getDBConnection();
    if (!$pdo) return false;
    
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($data);
        return $pdo->lastInsertId();
    } catch (PDOException $e) {
        logError("Erro ao inserir: " . $e->getMessage());
        return false;
    }
}

/**
 * Atualizar registro
 */
function updateRecord($table, $data, $where, $whereParams = []) {
    $setClause = [];
    foreach ($data as $key => $value) {
        $setClause[] = "$key = :$key";
    }
    $setClause = implode(', ', $setClause);
    
    $sql = "UPDATE $table SET $setClause WHERE $where";
    
    $params = array_merge($data, $whereParams);
    
    return executeQuery($sql, $params);
}

// ========================
// FUNÇÕES DE VALIDAÇÃO
// ========================

/**
 * Validar CPF
 */
function validateCPF($cpf) {
    $cpf = preg_replace('/[^0-9]/', '', $cpf);
    
    if (strlen($cpf) != 11) return false;
    
    if (preg_match('/(\d)\1{10}/', $cpf)) return false;
    
    // Validar dígitos verificadores
    for ($t = 9; $t < 11; $t++) {
        $d = 0;
        for ($c = 0; $c < $t; $c++) {
            $d += $cpf[$c] * (($t + 1) - $c);
        }
        $d = ((10 * $d) % 11) % 10;
        if ($cpf[$c] != $d) return false;
    }
    
    return true;
}

/**
 * Validar CNPJ
 */
function validateCNPJ($cnpj) {
    $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
    
    if (strlen($cnpj) != 14) return false;
    
    if (preg_match('/(\d)\1{13}/', $cnpj)) return false;
    
    // Validar dígitos verificadores
    $tamanho = strlen($cnpj) - 2;
    $numeros = substr($cnpj, 0, $tamanho);
    $digitos = substr($cnpj, $tamanho);
    
    $soma = 0;
    $pos = $tamanho - 7;
    for ($i = $tamanho; $i >= 1; $i--) {
        $soma += $numeros[$tamanho - $i] * $pos--;
        if ($pos < 2) $pos = 9;
    }
    
    $resultado = $soma % 11 < 2 ? 0 : 11 - $soma % 11;
    if ($resultado != $digitos[0]) return false;
    
    $tamanho = $tamanho + 1;
    $numeros = substr($cnpj, 0, $tamanho);
    $soma = 0;
    $pos = $tamanho - 7;
    for ($i = $tamanho; $i >= 1; $i--) {
        $soma += $numeros[$tamanho - $i] * $pos--;
        if ($pos < 2) $pos = 9;
    }
    
    $resultado = $soma % 11 < 2 ? 0 : 11 - $soma % 11;
    if ($resultado != $digitos[1]) return false;
    
    return true;
}

/**
 * Validar email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Validar telefone
 */
function validatePhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return strlen($phone) >= 10 && strlen($phone) <= 11;
}

/**
 * Sanitizar input
 */
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    return $input;
}

// ========================
// FUNÇÕES DE ARQUIVO
// ========================

/**
 * Upload de arquivo
 */
function uploadFile($file, $tipo = 'documento', $caso_id = null) {
    $allowedTypes = [
        'documento' => ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'],
        'sentenca' => ['pdf', 'doc', 'docx'],
        'prova' => ['pdf', 'jpg', 'jpeg', 'png', 'mp4', 'mp3']
    ];
    
    if (!isset($allowedTypes[$tipo])) {
        return ['success' => false, 'error' => 'Tipo de arquivo inválido'];
    }
    
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($extension, $allowedTypes[$tipo])) {
        return ['success' => false, 'error' => 'Extensão não permitida'];
    }
    
    // Verificar tamanho (max 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        return ['success' => false, 'error' => 'Arquivo muito grande (máx: 10MB)'];
    }
    
    // Gerar nome único
    $fileName = uniqid() . '_' . time() . '.' . $extension;
    $uploadDir = UPLOAD_PATH . '/' . $tipo . '/';
    
    if ($caso_id) {
        $uploadDir .= $caso_id . '/';
    }
    
    // Criar diretório se não existir
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $uploadPath = $uploadDir . $fileName;
    
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        return [
            'success' => true,
            'filename' => $fileName,
            'path' => $uploadPath,
            'size' => $file['size'],
            'type' => $file['type']
        ];
    }
    
    return ['success' => false, 'error' => 'Erro ao fazer upload'];
}

/**
 * Deletar arquivo
 */
function deleteFile($filePath) {
    if (file_exists($filePath)) {
        return unlink($filePath);
    }
    return false;
}

// ========================
// FUNÇÕES DE EMAIL
// ========================

/**
 * Enviar email
 */
function sendEmail($to, $subject, $body, $attachments = []) {
    $headers = [
        'MIME-Version' => '1.0',
        'Content-type' => 'text/html; charset=UTF-8',
        'From' => MAIL_FROM_NAME . ' <' . MAIL_FROM . '>',
        'Reply-To' => MAIL_FROM,
        'X-Mailer' => 'PHP/' . phpversion()
    ];
    
    $headerString = '';
    foreach ($headers as $key => $value) {
        $headerString .= $key . ': ' . $value . "\r\n";
    }
    
    // Template de email
    $template = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background-color: #1a5490; color: white; padding: 20px; text-align: center; }
            .content { padding: 20px; background-color: #f4f4f4; }
            .footer { text-align: center; padding: 10px; font-size: 12px; color: #666; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>Sistema de Arbitragem</h1>
            </div>
            <div class="content">
                ' . $body . '
            </div>
            <div class="footer">
                <p>Este é um email automático. Por favor, não responda.</p>
                <p>&copy; ' . date('Y') . ' Sistema de Arbitragem. Todos os direitos reservados.</p>
            </div>
        </div>
    </body>
    </html>';
    
    return mail($to, $subject, $template, $headerString);
}

/**
 * Enviar notificação
 */
function sendNotification($user_id, $tipo, $mensagem, $caso_id = null) {
    $data = [
        'usuario_id' => $user_id,
        'tipo' => $tipo,
        'mensagem' => $mensagem,
        'caso_id' => $caso_id,
        'lida' => 0,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    $notificationId = insertRecord('notificacoes', $data);
    
    // Buscar email do usuário
    $stmt = executeQuery("SELECT email, nome FROM usuarios WHERE id = ?", [$user_id]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Enviar email também
        $subject = "Nova notificação - Sistema de Arbitragem";
        $body = "
            <h2>Olá, {$user['nome']}</h2>
            <p>Você tem uma nova notificação:</p>
            <div style='background-color: #fff; padding: 15px; border-left: 4px solid #1a5490;'>
                <p><strong>{$mensagem}</strong></p>
            </div>
            <p>Acesse o sistema para mais detalhes.</p>
        ";
        
        sendEmail($user['email'], $subject, $body);
    }
    
    return $notificationId;
}

// ========================
// FUNÇÕES DE SEGURANÇA
// ========================

/**
 * Gerar token CSRF
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validar token CSRF
 */
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Verificar permissão
 */
function checkPermission($user_id, $permissao) {
    $stmt = executeQuery(
        "SELECT p.nome FROM usuarios u 
         JOIN perfis p ON u.perfil_id = p.id 
         WHERE u.id = ?",
        [$user_id]
    );
    
    $perfil = $stmt->fetch();
    
    $permissoes = [
        'admin' => ['*'],
        'arbitro' => ['gerenciar_casos', 'emitir_sentenca', 'agendar_audiencia'],
        'parte' => ['visualizar_caso', 'enviar_documento', 'participar_audiencia'],
        'advogado' => ['visualizar_caso', 'enviar_documento', 'participar_audiencia', 'representar_parte']
    ];
    
    if (!$perfil) return false;
    
    $perfilNome = $perfil['nome'];
    
    if (isset($permissoes[$perfilNome])) {
        return in_array('*', $permissoes[$perfilNome]) || in_array($permissao, $permissoes[$perfilNome]);
    }
    
    return false;
}

/**
 * Log de atividade
 */
function logActivity($user_id, $acao, $detalhes = '', $ip = null) {
    $data = [
        'usuario_id' => $user_id,
        'acao' => $acao,
        'detalhes' => $detalhes,
        'ip' => $ip ?: $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT'],
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    return insertRecord('log_atividades', $data);
}

/**
 * Log de erro
 */
function logError($mensagem, $nivel = 'ERROR') {
    $logFile = LOG_PATH . '/error_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[$timestamp] [$nivel] $mensagem" . PHP_EOL;
    
    error_log($logMessage, 3, $logFile);
}

// ========================
// FUNÇÕES AUXILIARES
// ========================

/**
 * Formatar CPF/CNPJ
 */
function formatDocument($documento) {
    $documento = preg_replace('/[^0-9]/', '', $documento);
    
    if (strlen($documento) == 11) {
        // CPF
        return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $documento);
    } elseif (strlen($documento) == 14) {
        // CNPJ
        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $documento);
    }
    
    return $documento;
}

/**
 * Formatar telefone
 */
function formatPhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    if (strlen($phone) == 10) {
        return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $phone);
    } elseif (strlen($phone) == 11) {
        return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $phone);
    }
    
    return $phone;
}

/**
 * Formatar data
 */
function formatDate($date, $format = 'd/m/Y') {
    if (!$date) return '';
    
    $dateTime = new DateTime($date);
    return $dateTime->format($format);
}

/**
 * Formatar valor monetário
 */
function formatMoney($value) {
    return 'R$ ' . number_format($value, 2, ',', '.');
}

/**
 * Gerar código único
 */
function generateUniqueCode($prefix = 'ARB') {
    return $prefix . date('Y') . str_pad(mt_rand(1, 999999), 6, '0', STR_PAD_LEFT);
}

/**
 * Calcular prazo
 */
function calculateDeadline($startDate, $days, $skipWeekends = true) {
    $date = new DateTime($startDate);
    $daysAdded = 0;
    
    while ($daysAdded < $days) {
        $date->modify('+1 day');
        
        if ($skipWeekends && in_array($date->format('N'), [6, 7])) {
            continue;
        }
        
        $daysAdded++;
    }
    
    return $date->format('Y-m-d');
}

/**
 * Obter status do caso
 */
function getCaseStatus($status_id) {
    $statuses = [
        1 => ['nome' => 'Novo', 'cor' => 'primary'],
        2 => ['nome' => 'Em Análise', 'cor' => 'info'],
        3 => ['nome' => 'Aguardando Documentos', 'cor' => 'warning'],
        4 => ['nome' => 'Em Audiência', 'cor' => 'info'],
        5 => ['nome' => 'Aguardando Sentença', 'cor' => 'warning'],
        6 => ['nome' => 'Concluído', 'cor' => 'success'],
        7 => ['nome' => 'Arquivado', 'cor' => 'secondary'],
        8 => ['nome' => 'Cancelado', 'cor' => 'danger']
    ];
    
    return $statuses[$status_id] ?? ['nome' => 'Desconhecido', 'cor' => 'secondary'];
}

/**
 * Verificar sessão ativa
 */
function checkSession() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    if (!isset($_SESSION['user_id'])) {
        header('Location: /login.php');
        exit;
    }
    
    // Renovar sessão a cada 30 minutos
    if (!isset($_SESSION['last_activity']) || (time() - $_SESSION['last_activity'] > 1800)) {
        session_regenerate_id(true);
        $_SESSION['last_activity'] = time();
    }
}

/**
 * API Response Helper
 */
function apiResponse($success, $data = null, $message = '', $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    
    $response = [
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('c')
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Paginar resultados
 */
function paginate($query, $params = [], $page = 1, $perPage = 20) {
    $offset = ($page - 1) * $perPage;
    
    // Contar total de registros
    $countQuery = "SELECT COUNT(*) as total FROM ($query) as count_table";
    $stmt = executeQuery($countQuery, $params);
    $total = $stmt->fetch()['total'];
    
    // Buscar registros paginados
    $paginatedQuery = $query . " LIMIT $perPage OFFSET $offset";
    $stmt = executeQuery($paginatedQuery, $params);
    $data = $stmt->fetchAll();
    
    return [
        'data' => $data,
        'total' => $total,
        'page' => $page,
        'perPage' => $perPage,
        'totalPages' => ceil($total / $perPage)
    ];
}

/**
 * Exportar para PDF
 */
function exportToPDF($html, $filename = 'document.pdf') {
    // Esta função requer a biblioteca TCPDF ou similar
    // Implementação básica - adaptar conforme biblioteca escolhida
    require_once 'vendor/tcpdf/tcpdf.php';
    
    $pdf = new TCPDF();
    $pdf->AddPage();
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output($filename, 'D');
}

/**
 * Validar dados do formulário
 */
function validateForm($data, $rules) {
    $errors = [];
    
    foreach ($rules as $field => $rule) {
        $value = $data[$field] ?? '';
        
        // Required
        if (strpos($rule, 'required') !== false && empty($value)) {
            $errors[$field] = "O campo é obrigatório";
            continue;
        }
        
        // Email
        if (strpos($rule, 'email') !== false && !validateEmail($value)) {
            $errors[$field] = "Email inválido";
        }
        
        // CPF
        if (strpos($rule, 'cpf') !== false && !validateCPF($value)) {
            $errors[$field] = "CPF inválido";
        }
        
        // CNPJ
        if (strpos($rule, 'cnpj') !== false && !validateCNPJ($value)) {
            $errors[$field] = "CNPJ inválido";
        }
        
        // Min length
        if (preg_match('/min:(\d+)/', $rule, $matches)) {
            if (strlen($value) < $matches[1]) {
                $errors[$field] = "Mínimo de {$matches[1]} caracteres";
            }
        }
        
        // Max length
        if (preg_match('/max:(\d+)/', $rule, $matches)) {
            if (strlen($value) > $matches[1]) {
                $errors[$field] = "Máximo de {$matches[1]} caracteres";
            }
        }
    }
    
    return $errors;
}