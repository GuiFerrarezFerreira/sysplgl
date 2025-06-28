<?php
/**
 * Arbitrivm - Endpoint de Disputas
 */

function handleDisputesRequest($method, $id, $action, $params) {
    $db = new Database();
    $auth = new Auth();
    $response = new Response();
    $fileHandler = new FileHandler();
    
    $userId = getCurrentUserId();
    $user = getCurrentUser();
    
    switch ($method) {
        case 'GET':
            if ($id) {
                // GET /disputes/{id} - Obter disputa específica
                getDispute($id, $userId, $db, $auth, $response);
            } else {
                // GET /disputes - Listar disputas
                listDisputes($params, $user, $db, $response);
            }
            break;
            
        case 'POST':
            if ($id && $action) {
                // POST /disputes/{id}/{action} - Ações na disputa
                handleDisputeAction($id, $action, $params, $userId, $db, $response);
            } else {
                // POST /disputes - Criar nova disputa
                createDispute($params, $user, $db, $response, $fileHandler);
            }
            break;
            
        case 'PUT':
            if ($id) {
                // PUT /disputes/{id} - Atualizar disputa
                updateDispute($id, $params, $userId, $db, $auth, $response);
            } else {
                $response->error('ID da disputa é obrigatório', 400);
            }
            break;
            
        case 'DELETE':
            if ($id) {
                // DELETE /disputes/{id} - Cancelar disputa
                cancelDispute($id, $userId, $db, $auth, $response);
            } else {
                $response->error('ID da disputa é obrigatório', 400);
            }
            break;
            
        default:
            $response->error('Método não permitido', 405);
    }
}

function listDisputes($params, $user, $db, $response) {
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
    if (isset($params['status'])) {
        $filters['status'] = $params['status'];
    }
    
    // Paginação
    $page = intval($params['page'] ?? 1);
    $limit = intval($params['limit'] ?? ITEMS_PER_PAGE);
    $offset = ($page - 1) * $limit;
    
    $filters['limit'] = $limit;
    $filters['offset'] = $offset;
    
    // Buscar disputas
    $disputes = $db->getDisputes($filters);
    
    // Contar total
    $totalSql = "SELECT COUNT(*) as total FROM disputes WHERE 1=1";
    $totalParams = [];
    
    if (isset($filters['company_id'])) {
        $totalSql .= " AND company_id = ?";
        $totalParams[] = $filters['company_id'];
    }
    
    if (isset($filters['status'])) {
        $totalSql .= " AND status = ?";
        $totalParams[] = $filters['status'];
    }
    
    if (isset($filters['user_id'])) {
        $totalSql .= " AND (claimant_id = ? OR respondent_id = ?)";
        $totalParams[] = $filters['user_id'];
        $totalParams[] = $filters['user_id'];
    }
    
    if (isset($filters['arbitrator_id'])) {
        $totalSql .= " AND arbitrator_id = ?";
        $totalParams[] = $filters['arbitrator_id'];
    }
    
    $total = $db->fetchOne($totalSql, $totalParams)['total'];
    
    $response->success([
        'disputes' => $disputes,
        'pagination' => [
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

function getDispute($id, $userId, $db, $auth, $response) {
    // Verificar permissão
    if (!$auth->canAccessDispute($userId, $id)) {
        $response->error('Acesso negado', 403);
    }
    
    // Buscar disputa
    $dispute = $db->getDisputeById($id);
    
    if (!$dispute) {
        $response->error('Disputa não encontrada', 404);
    }
    
    // Adicionar dados extras
    $dispute['documents'] = $db->getDocumentsByDispute($id);
    $dispute['messages_count'] = $db->fetchOne(
        "SELECT COUNT(*) as count FROM messages WHERE dispute_id = ?",
        [$id]
    )['count'];
    
    $dispute['events'] = $db->fetchAll(
        "SELECT de.*, CONCAT(u.first_name, ' ', u.last_name) as user_name
         FROM dispute_events de
         JOIN users u ON de.user_id = u.id
         WHERE de.dispute_id = ?
         ORDER BY de.created_at DESC
         LIMIT 10",
        [$id]
    );
    
    $response->success($dispute);
}

function createDispute($params, $user, $db, $response, $fileHandler) {
    // Validar dados
    $required = ['dispute_type_id', 'title', 'description', 'respondent_email'];
    foreach ($required as $field) {
        if (empty($params[$field])) {
            $response->error("Campo '$field' é obrigatório", 400);
        }
    }
    
    // Verificar se o respondente existe ou criar conta temporária
    $respondent = $db->getUserByEmail($params['respondent_email']);
    if (!$respondent) {
        // Criar usuário temporário
        $tempPassword = generateToken(8);
        $respondentData = [
            'email' => $params['respondent_email'],
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
        
        // TODO: Enviar email de convite para o respondente
    } else {
        $respondentId = $respondent['id'];
    }
    
    // Preparar dados da disputa
    $disputeData = [
        'company_id' => $user['company_id'],
        'dispute_type_id' => $params['dispute_type_id'],
        'claimant_id' => $user['id'],
        'respondent_id' => $respondentId,
        'status' => 'pending_arbitrator',
        'title' => sanitizeInput($params['title']),
        'description' => sanitizeInput($params['description']),
        'claim_amount' => $params['claim_amount'] ?? null,
        'property_address' => sanitizeInput($params['property_address'] ?? ''),
        'contract_number' => sanitizeInput($params['contract_number'] ?? ''),
        'priority' => $params['priority'] ?? 'normal',
        'deadline_date' => date('Y-m-d', strtotime('+30 days')),
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    try {
        // Criar disputa
        $disputeId = $db->createDispute($disputeData);
        
        // Upload de documentos se houver
        if (!empty($_FILES['documents'])) {
            $uploadedFiles = $fileHandler->uploadMultiple($_FILES['documents'], $disputeId, $user['id']);
        }
        
        // Notificar respondente
        $db->createNotification(
            $respondentId,
            'new_dispute',
            'Nova Disputa',
            "Você foi incluído em uma nova disputa: {$disputeData['title']}",
            ['dispute_id' => $disputeId]
        );
        
        // Notificar árbitros disponíveis
        notifyAvailableArbitrators($disputeId, $params['dispute_type_id'], $db);
        
        $response->success([
            'id' => $disputeId,
            'message' => 'Disputa criada com sucesso'
        ], 201);
        
    } catch (Exception $e) {
        $response->error('Erro ao criar disputa: ' . $e->getMessage(), 500);
    }
}

function updateDispute($id, $params, $userId, $db, $auth, $response) {
    // Verificar permissão
    if (!$auth->canAccessDispute($userId, $id)) {
        $response->error('Acesso negado', 403);
    }
    
    $dispute = $db->getDisputeById($id);
    if (!$dispute) {
        $response->error('Disputa não encontrada', 404);
    }
    
    // Verificar campos permitidos baseado no status
    $allowedFields = getAllowedFieldsForStatus($dispute['status']);
    $updateData = [];
    
    foreach ($allowedFields as $field) {
        if (isset($params[$field])) {
            $updateData[$field] = sanitizeInput($params[$field]);
        }
    }
    
    if (empty($updateData)) {
        $response->error('Nenhum campo válido para atualizar', 400);
    }
    
    // Atualizar
    $db->update('disputes', $updateData, 'id = ?', [$id]);
    
    // Log do evento
    $db->insert('dispute_events', [
        'dispute_id' => $id,
        'user_id' => $userId,
        'event_type' => 'dispute_updated',
        'description' => 'Disputa atualizada',
        'metadata' => json_encode(['updated_fields' => array_keys($updateData)])
    ]);
    
    $response->success(['message' => 'Disputa atualizada com sucesso']);
}

function handleDisputeAction($id, $action, $params, $userId, $db, $response) {
    $user = getCurrentUser();
    
    switch ($action) {
        case 'assign-arbitrator':
            assignArbitrator($id, $params, $user, $db, $response);
            break;
            
        case 'accept':
            acceptDispute($id, $user, $db, $response);
            break;
            
        case 'reject':
            rejectDispute($id, $user, $db, $response);
            break;
            
        case 'resolve':
            resolveDispute($id, $params, $user, $db, $response);
            break;
            
        case 'add-document':
            addDocument($id, $user, $db, $response);
            break;
            
        case 'send-message':
            sendMessage($id, $params, $user, $db, $response);
            break;
            
        default:
            $response->error('Ação inválida', 400);
    }
}

function assignArbitrator($disputeId, $params, $user, $db, $response) {
    // Verificar permissão (apenas admin ou manager da empresa)
    if (!in_array($user['role'], ['admin', 'manager'])) {
        $response->error('Sem permissão para atribuir árbitro', 403);
    }
    
    if (empty($params['arbitrator_id'])) {
        $response->error('ID do árbitro é obrigatório', 400);
    }
    
    $dispute = $db->getDisputeById($disputeId);
    if (!$dispute) {
        $response->error('Disputa não encontrada', 404);
    }
    
    if ($dispute['status'] !== 'pending_arbitrator') {
        $response->error('Disputa já possui árbitro atribuído', 400);
    }
    
    // Verificar se árbitro existe e está disponível
    $arbitrator = $db->fetchOne(
        "SELECT a.*, u.email FROM arbitrators a 
         JOIN users u ON a.user_id = u.id 
         WHERE a.id = ? AND a.is_available = 1",
        [$params['arbitrator_id']]
    );
    
    if (!$arbitrator) {
        $response->error('Árbitro não disponível', 400);
    }
    
    // Atualizar disputa
    $db->update('disputes', [
        'arbitrator_id' => $params['arbitrator_id'],
        'status' => 'pending_acceptance'
    ], 'id = ?', [$disputeId]);
    
    // Notificar árbitro
    $db->createNotification(
        $arbitrator['user_id'],
        'arbitrator_assigned',
        'Nova Disputa Atribuída',
        'Você foi selecionado para arbitrar uma disputa',
        ['dispute_id' => $disputeId]
    );
    
    // Log do evento
    $db->insert('dispute_events', [
        'dispute_id' => $disputeId,
        'user_id' => $user['id'],
        'event_type' => 'arbitrator_assigned',
        'description' => 'Árbitro atribuído à disputa',
        'metadata' => json_encode(['arbitrator_id' => $params['arbitrator_id']])
    ]);
    
    $response->success(['message' => 'Árbitro atribuído com sucesso']);
}

function acceptDispute($disputeId, $user, $db, $response) {
    // Verificar se é árbitro
    if ($user['role'] !== 'arbitrator') {
        $response->error('Apenas árbitros podem aceitar disputas', 403);
    }
    
    $arbitrator = $db->fetchOne("SELECT id FROM arbitrators WHERE user_id = ?", [$user['id']]);
    if (!$arbitrator) {
        $response->error('Árbitro não encontrado', 404);
    }
    
    $dispute = $db->getDisputeById($disputeId);
    if (!$dispute) {
        $response->error('Disputa não encontrada', 404);
    }
    
    if ($dispute['arbitrator_id'] != $arbitrator['id']) {
        $response->error('Você não é o árbitro designado para esta disputa', 403);
    }
    
    if ($dispute['status'] !== 'pending_acceptance') {
        $response->error('Esta disputa não está aguardando aceitação', 400);
    }
    
    // Atualizar status
    $db->update('disputes', [
        'status' => 'active',
        'started_at' => date('Y-m-d H:i:s')
    ], 'id = ?', [$disputeId]);
    
    // Notificar partes
    $db->createNotification(
        $dispute['claimant_id'],
        'dispute_accepted',
        'Disputa Aceita',
        'O árbitro aceitou a disputa e o processo foi iniciado',
        ['dispute_id' => $disputeId]
    );
    
    $db->createNotification(
        $dispute['respondent_id'],
        'dispute_accepted',
        'Disputa Aceita',
        'O árbitro aceitou a disputa e o processo foi iniciado',
        ['dispute_id' => $disputeId]
    );
    
    // Log do evento
    $db->insert('dispute_events', [
        'dispute_id' => $disputeId,
        'user_id' => $user['id'],
        'event_type' => 'dispute_accepted',
        'description' => 'Árbitro aceitou a disputa',
        'metadata' => json_encode(['arbitrator_id' => $arbitrator['id']])
    ]);
    
    $response->success(['message' => 'Disputa aceita com sucesso']);
}

function rejectDispute($disputeId, $user, $db, $response) {
    // Similar ao accept, mas muda status para pending_arbitrator novamente
    if ($user['role'] !== 'arbitrator') {
        $response->error('Apenas árbitros podem rejeitar disputas', 403);
    }
    
    $arbitrator = $db->fetchOne("SELECT id FROM arbitrators WHERE user_id = ?", [$user['id']]);
    $dispute = $db->getDisputeById($disputeId);
    
    if (!$dispute || $dispute['arbitrator_id'] != $arbitrator['id']) {
        $response->error('Acesso negado', 403);
    }
    
    // Voltar status e remover árbitro
    $db->update('disputes', [
        'status' => 'pending_arbitrator',
        'arbitrator_id' => null
    ], 'id = ?', [$disputeId]);
    
    // Log do evento
    $db->insert('dispute_events', [
        'dispute_id' => $disputeId,
        'user_id' => $user['id'],
        'event_type' => 'dispute_rejected',
        'description' => 'Árbitro rejeitou a disputa',
        'metadata' => null
    ]);
    
    $response->success(['message' => 'Disputa rejeitada']);
}

function resolveDispute($disputeId, $params, $user, $db, $response) {
    if ($user['role'] !== 'arbitrator') {
        $response->error('Apenas árbitros podem resolver disputas', 403);
    }
    
    if (empty($params['resolution_summary'])) {
        $response->error('Resumo da decisão é obrigatório', 400);
    }
    
    $arbitrator = $db->fetchOne("SELECT id FROM arbitrators WHERE user_id = ?", [$user['id']]);
    $dispute = $db->getDisputeById($disputeId);
    
    if (!$dispute || $dispute['arbitrator_id'] != $arbitrator['id']) {
        $response->error('Acesso negado', 403);
    }
    
    if ($dispute['status'] !== 'active') {
        $response->error('Apenas disputas ativas podem ser resolvidas', 400);
    }
    
    // Atualizar disputa
    $db->update('disputes', [
        'status' => 'resolved',
        'resolved_at' => date('Y-m-d H:i:s'),
        'resolution_summary' => sanitizeInput($params['resolution_summary']),
        'decision_document_id' => $params['decision_document_id'] ?? null
    ], 'id = ?', [$disputeId]);
    
    // Atualizar estatísticas do árbitro
    updateArbitratorStats($arbitrator['id'], $db);
    
    // Notificar partes
    $db->createNotification(
        $dispute['claimant_id'],
        'dispute_resolved',
        'Disputa Resolvida',
        'A disputa foi resolvida pelo árbitro',
        ['dispute_id' => $disputeId]
    );
    
    $db->createNotification(
        $dispute['respondent_id'],
        'dispute_resolved',
        'Disputa Resolvida',
        'A disputa foi resolvida pelo árbitro',
        ['dispute_id' => $disputeId]
    );
    
    // Log do evento
    $db->insert('dispute_events', [
        'dispute_id' => $disputeId,
        'user_id' => $user['id'],
        'event_type' => 'dispute_resolved',
        'description' => 'Disputa resolvida',
        'metadata' => json_encode(['resolution_summary' => $params['resolution_summary']])
    ]);
    
    $response->success(['message' => 'Disputa resolvida com sucesso']);
}

function cancelDispute($disputeId, $userId, $db, $auth, $response) {
    $dispute = $db->getDisputeById($disputeId);
    
    if (!$dispute) {
        $response->error('Disputa não encontrada', 404);
    }
    
    // Apenas o reclamante ou admin pode cancelar
    $user = getCurrentUser();
    if ($dispute['claimant_id'] != $userId && $user['role'] !== 'admin') {
        $response->error('Sem permissão para cancelar esta disputa', 403);
    }
    
    // Não pode cancelar se já estiver resolvida
    if (in_array($dispute['status'], ['resolved', 'cancelled'])) {
        $response->error('Esta disputa não pode ser cancelada', 400);
    }
    
    // Cancelar
    $db->update('disputes', [
        'status' => 'cancelled'
    ], 'id = ?', [$disputeId]);
    
    // Log do evento
    $db->insert('dispute_events', [
        'dispute_id' => $disputeId,
        'user_id' => $userId,
        'event_type' => 'dispute_cancelled',
        'description' => 'Disputa cancelada',
        'metadata' => null
    ]);
    
    $response->success(['message' => 'Disputa cancelada com sucesso']);
}

function addDocument($disputeId, $user, $db, $response) {
    if (empty($_FILES['document'])) {
        $response->error('Nenhum arquivo enviado', 400);
    }
    
    $auth = new Auth();
    if (!$auth->canAccessDispute($user['id'], $disputeId)) {
        $response->error('Acesso negado', 403);
    }
    
    $fileHandler = new FileHandler();
    try {
        $result = $fileHandler->uploadFile(
            $_FILES['document'],
            $disputeId,
            $user['id'],
            $_POST['document_type'] ?? 'other',
            $_POST['description'] ?? ''
        );
        
        $response->success($result, 201);
    } catch (Exception $e) {
        $response->error($e->getMessage(), 400);
    }
}

function sendMessage($disputeId, $params, $user, $db, $response) {
    if (empty($params['message'])) {
        $response->error('Mensagem não pode estar vazia', 400);
    }
    
    $auth = new Auth();
    if (!$auth->canAccessDispute($user['id'], $disputeId)) {
        $response->error('Acesso negado', 403);
    }
    
    $messageId = $db->sendMessage($disputeId, $user['id'], sanitizeInput($params['message']));
    
    // Notificar outros participantes
    $dispute = $db->getDisputeById($disputeId);
    $participants = [$dispute['claimant_id'], $dispute['respondent_id']];
    
    if ($dispute['arbitrator_id']) {
        $arbitrator = $db->fetchOne(
            "SELECT user_id FROM arbitrators WHERE id = ?",
            [$dispute['arbitrator_id']]
        );
        if ($arbitrator) {
            $participants[] = $arbitrator['user_id'];
        }
    }
    
    // Remover o remetente da lista
    $participants = array_diff($participants, [$user['id']]);
    
    foreach ($participants as $participantId) {
        $db->createNotification(
            $participantId,
            'new_message',
            'Nova Mensagem',
            "{$user['first_name']} enviou uma mensagem na disputa",
            ['dispute_id' => $disputeId, 'message_id' => $messageId]
        );
    }
    
    $response->success([
        'id' => $messageId,
        'message' => 'Mensagem enviada com sucesso'
    ], 201);
}

// Funções auxiliares
function getAllowedFieldsForStatus($status) {
    $fields = [
        'draft' => ['title', 'description', 'claim_amount', 'priority'],
        'pending_arbitrator' => ['priority', 'deadline_date'],
        'pending_acceptance' => [],
        'active' => ['priority'],
        'resolved' => [],
        'cancelled' => []
    ];
    
    return $fields[$status] ?? [];
}

function notifyAvailableArbitrators($disputeId, $disputeTypeId, $db) {
    $arbitrators = $db->fetchAll(
        "SELECT a.user_id 
         FROM arbitrators a
         WHERE a.is_available = 1
         AND JSON_CONTAINS(a.specializations, ?, ')
         ORDER BY a.rating DESC
         LIMIT 10",
        [json_encode($disputeTypeId)]
    );
    
    foreach ($arbitrators as $arbitrator) {
        $db->createNotification(
            $arbitrator['user_id'],
            'new_dispute_available',
            'Nova Disputa Disponível',
            'Uma nova disputa está disponível para arbitragem',
            ['dispute_id' => $disputeId]
        );
    }
}

function updateArbitratorStats($arbitratorId, $db) {
    // Casos resolvidos
    $resolved = $db->fetchOne(
        "SELECT COUNT(*) as count FROM disputes 
         WHERE arbitrator_id = ? AND status = 'resolved'",
        [$arbitratorId]
    )['count'];
    
    // Tempo médio
    $avgTime = $db->fetchOne(
        "SELECT AVG(DATEDIFF(resolved_at, started_at)) as avg_days
         FROM disputes
         WHERE arbitrator_id = ? AND status = 'resolved'
         AND resolved_at IS NOT NULL AND started_at IS NOT NULL",
        [$arbitratorId]
    )['avg_days'];
    
    // Rating médio
    $avgRating = $db->fetchOne(
        "SELECT AVG(rating) as avg_rating, COUNT(*) as total
         FROM reviews
         WHERE arbitrator_id = ?",
        [$arbitratorId]
    );
    
    $db->update('arbitrators', [
        'cases_resolved' => $resolved,
        'average_resolution_days' => round($avgTime ?? 0),
        'rating' => round($avgRating['avg_rating'] ?? 0, 2),
        'total_reviews' => $avgRating['total'] ?? 0
    ], 'id = ?', [$arbitratorId]);
}