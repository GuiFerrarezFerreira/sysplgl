<?php
/**
 * Arbitrivm - Endpoint de Disputas Direto
 * api/disputes.php
 */

require_once '../config.php';

// Headers CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=UTF-8');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Verificar autenticação
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit;
}

$db = new Database();
$user = getCurrentUser();

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        handleGetDisputes($db, $user);
        break;
        
    case 'POST':
        handleCreateDispute($db, $user);
        break;
        
    case 'PUT':
        handleUpdateDispute($db, $user);
        break;
        
    case 'DELETE':
        handleDeleteDispute($db, $user);
        break;
        
    default:
        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Método não permitido']);
}

function handleGetDisputes($db, $user) {
    $filters = [];
    
    // Aplicar filtros baseados no role do usuário
    if ($user['role'] === 'user' || $user['role'] === 'manager') {
        $filters['company_id'] = $user['company_id'];
    } elseif ($user['role'] === 'arbitrator') {
        $arbitrator = $db->fetchOne("SELECT id FROM arbitrators WHERE user_id = ?", [$user['id']]);
        if ($arbitrator) {
            $filters['arbitrator_id'] = $arbitrator['id'];
        }
    } elseif ($user['role'] === 'party') {
        $filters['user_id'] = $user['id'];
    }
    
    // Filtros adicionais dos parâmetros
    if (isset($_GET['status'])) {
        $filters['status'] = $_GET['status'];
    }
    
    // Paginação
    $page = intval($_GET['page'] ?? 1);
    $limit = intval($_GET['limit'] ?? ITEMS_PER_PAGE);
    $offset = ($page - 1) * $limit;
    
    $filters['limit'] = $limit;
    $filters['offset'] = $offset;
    
    // Buscar disputas
    $disputes = $db->getDisputes($filters);
    
    // Contar total para paginação
    $totalSql = "SELECT COUNT(*) as total FROM disputes WHERE 1=1";
    $totalParams = [];
    
    if (isset($filters['company_id'])) {
        $totalSql .= " AND company_id = ?";
        $totalParams[] = $filters['company_id'];
    }
    
    $total = $db->fetchOne($totalSql, $totalParams)['total'];
    
    echo json_encode([
        'success' => true,
        'data' => [
            'disputes' => $disputes,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit)
            ]
        ]
    ]);
}

function handleCreateDispute($db, $user) {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validar dados obrigatórios
    $required = ['dispute_type_id', 'title', 'description', 'respondent_email'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Campo '$field' é obrigatório"]);
            return;
        }
    }
    
    // Verificar se o respondente existe ou criar conta temporária
    $respondent = $db->getUserByEmail($data['respondent_email']);
    if (!$respondent) {
        // Criar usuário temporário
        $tempPassword = generateToken(8);
        $respondentData = [
            'email' => $data['respondent_email'],
            'password_hash' => password_hash($tempPassword, PASSWORD_BCRYPT),
            'first_name' => 'Pendente',
            'last_name' => 'Cadastro',
            'role' => 'party',
            'is_active' => 1,
            'is_verified' => 0,
            'verification_token' => generateToken(),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $respondentId = $db->insert('users', $respondentData);
    } else {
        $respondentId = $respondent['id'];
    }
    
    // Preparar dados da disputa
    $disputeData = [
        'company_id' => $user['company_id'],
        'dispute_type_id' => $data['dispute_type_id'],
        'claimant_id' => $user['id'],
        'respondent_id' => $respondentId,
        'status' => 'pending_arbitrator',
        'title' => sanitizeInput($data['title']),
        'description' => sanitizeInput($data['description']),
        'claim_amount' => $data['claim_amount'] ?? null,
        'property_address' => sanitizeInput($data['property_address'] ?? ''),
        'contract_number' => sanitizeInput($data['contract_number'] ?? ''),
        'priority' => $data['priority'] ?? 'normal',
        'deadline_date' => date('Y-m-d', strtotime('+30 days')),
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    try {
        $disputeId = $db->createDispute($disputeData);
        
        // Notificar respondente
        $db->createNotification(
            $respondentId,
            'new_dispute',
            'Nova Disputa',
            "Você foi incluído em uma nova disputa: {$disputeData['title']}",
            ['dispute_id' => $disputeId]
        );
        
        echo json_encode([
            'success' => true,
            'message' => 'Disputa criada com sucesso',
            'data' => ['id' => $disputeId]
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Erro ao criar disputa']);
    }
}

function handleUpdateDispute($db, $user) {
    // Implementar update
    http_response_code(501);
    echo json_encode(['success' => false, 'message' => 'Não implementado']);
}

function handleDeleteDispute($db, $user) {
    // Implementar delete
    http_response_code(501);
    echo json_encode(['success' => false, 'message' => 'Não implementado']);
}