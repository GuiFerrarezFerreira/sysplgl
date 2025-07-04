<?php
/**
 * api/arbitrators.php - API REST para Árbitros
 */

require_once '../config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Headers CORS e JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Verificar autenticação via token JWT ou sessão
$authToken = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if ($authToken) {
    $userId = validateJWT(str_replace('Bearer ', '', $authToken));
    if (!$userId) {
        jsonResponse(['error' => 'Token inválido'], 401);
    }
} else {
    checkAuth();
    $userId = $_SESSION['user_id'] ?? null;
}

$db = new Database();
$method = $_SERVER['REQUEST_METHOD'];
$path = explode('/', trim($_SERVER['PATH_INFO'] ?? '', '/'));

// Roteamento básico
$resource = $path[0] ?? '';
$id = $path[1] ?? null;
$action = $path[2] ?? null;

try {
    switch ($method) {
        case 'GET':
            handleGet($resource, $id, $action);
            break;
        case 'POST':
            handlePost($resource, $action);
            break;
        case 'PUT':
            handlePut($resource, $id);
            break;
        case 'DELETE':
            handleDelete($resource, $id);
            break;
        default:
            jsonResponse(['error' => 'Método não permitido'], 405);
    }
} catch (Exception $e) {
    logError('API Error: ' . $e->getMessage());
    jsonResponse(['error' => 'Erro interno do servidor'], 500);
}

/**
 * Handlers para GET
 */
function handleGet($resource, $id, $action) {
    global $db, $userId;
    
    switch ($resource) {
        case 'profile':
            // GET /api/arbitrators/profile - Perfil do árbitro atual
            $arbitrator = $db->fetchOne(
                "SELECT a.*, u.name, u.email, u.phone, u.photo_url,
                        COUNT(DISTINCT ac.id) as total_cases,
                        AVG(ar.rating) as avg_rating
                 FROM arbitrators a
                 INNER JOIN users u ON a.user_id = u.id
                 LEFT JOIN arbitrator_cases ac ON a.id = ac.arbitrator_id
                 LEFT JOIN arbitrator_reviews ar ON a.id = ar.arbitrator_id
                 WHERE a.user_id = ?
                 GROUP BY a.id",
                [$userId]
            );
            
            if (!$arbitrator) {
                jsonResponse(['error' => 'Perfil de árbitro não encontrado'], 404);
            }
            
            // Decodificar campos JSON
            $arbitrator['specializations'] = json_decode($arbitrator['specializations'] ?? '[]');
            $arbitrator['certifications'] = json_decode($arbitrator['certifications'] ?? '[]');
            $arbitrator['languages'] = json_decode($arbitrator['languages'] ?? '[]');
            
            jsonResponse($arbitrator);
            break;
            
        case 'cases':
            if ($id) {
                // GET /api/arbitrators/cases/{id} - Detalhes de um caso
                getCaseDetails($id);
            } else {
                // GET /api/arbitrators/cases - Lista de casos do árbitro
                getArbitratorCases();
            }
            break;
            
        case 'schedule':
            // GET /api/arbitrators/schedule - Agenda do árbitro
            getArbitratorSchedule();
            break;
            
        case 'earnings':
            // GET /api/arbitrators/earnings - Relatório de ganhos
            getEarningsReport();
            break;
            
        case 'documents':
            // GET /api/arbitrators/documents - Documentos do árbitro
            getArbitratorDocuments();
            break;
            
        case 'notifications':
            // GET /api/arbitrators/notifications - Notificações
            getNotifications();
            break;
            
        case 'statistics':
            // GET /api/arbitrators/statistics - Estatísticas
            getArbitratorStatistics();
            break;
            
        default:
            jsonResponse(['error' => 'Recurso não encontrado'], 404);
    }
}

/**
 * Handlers para POST
 */
function handlePost($resource, $action) {
    global $db, $userId;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    switch ($resource) {
        case 'cases':
            if ($action === 'accept') {
                // POST /api/arbitrators/cases/accept - Aceitar caso
                acceptCase($data);
            } elseif ($action === 'reject') {
                // POST /api/arbitrators/cases/reject - Rejeitar caso
                rejectCase($data);
            } elseif ($action === 'message') {
                // POST /api/arbitrators/cases/message - Enviar mensagem
                sendCaseMessage($data);
            } elseif ($action === 'hearing') {
                // POST /api/arbitrators/cases/hearing - Agendar audiência
                scheduleHearing($data);
            } elseif ($action === 'decision') {
                // POST /api/arbitrators/cases/decision - Emitir decisão
                issueDecision($data);
            }
            break;
            
        case 'availability':
            // POST /api/arbitrators/availability - Atualizar disponibilidade
            updateAvailability($data);
            break;
            
        case 'documents':
            // POST /api/arbitrators/documents - Upload de documento
            uploadDocument();
            break;
            
        case 'impediments':
            // POST /api/arbitrators/impediments - Declarar impedimento
            declareImpediment($data);
            break;
            
        default:
            jsonResponse(['error' => 'Ação não permitida'], 405);
    }
}

/**
 * Handlers para PUT
 */
function handlePut($resource, $id) {
    global $db, $userId;
    
    $data = json_decode(file_get_contents('php://input'), true);
    
    switch ($resource) {
        case 'profile':
            // PUT /api/arbitrators/profile - Atualizar perfil
            updateArbitratorProfile($data);
            break;
            
        case 'cases':
            // PUT /api/arbitrators/cases/{id} - Atualizar caso
            updateCase($id, $data);
            break;
            
        case 'settings':
            // PUT /api/arbitrators/settings - Atualizar configurações
            updateSettings($data);
            break;
            
        default:
            jsonResponse(['error' => 'Recurso não encontrado'], 404);
    }
}

/**
 * Handlers para DELETE
 */
function handleDelete($resource, $id) {
    switch ($resource) {
        case 'documents':
            // DELETE /api/arbitrators/documents/{id}
            deleteDocument($id);
            break;
            
        case 'availability':
            // DELETE /api/arbitrators/availability/{id}
            deleteAvailability($id);
            break;
            
        default:
            jsonResponse(['error' => 'Ação não permitida'], 405);
    }
}

/**
 * Funções específicas da API
 */
function getArbitratorCases() {
    global $db, $userId;
    
    $arbitratorId = getArbitratorId($userId);
    $status = $_GET['status'] ?? 'all';
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 10);
    $offset = ($page - 1) * $limit;
    
    $query = "
        SELECT 
            d.*,
            u1.name as claimant_name,
            u2.name as respondent_name,
            ac.status as assignment_status,
            ac.fee_amount,
            (
                SELECT COUNT(*) 
                FROM dispute_messages 
                WHERE dispute_id = d.id 
                AND created_at > IFNULL(acv.last_viewed_at, '1970-01-01')
            ) as unread_messages,
            (
                SELECT MIN(date) 
                FROM dispute_hearings 
                WHERE dispute_id = d.id 
                AND date >= CURDATE()
                AND status = 'scheduled'
            ) as next_hearing
        FROM disputes d
        INNER JOIN arbitrator_cases ac ON d.id = ac.dispute_id
        LEFT JOIN users u1 ON d.claimant_id = u1.id
        LEFT JOIN users u2 ON d.respondent_id = u2.id
        LEFT JOIN arbitrator_case_views acv ON d.id = acv.dispute_id AND acv.arbitrator_id = ?
        WHERE ac.arbitrator_id = ?
    ";
    
    $params = [$arbitratorId, $arbitratorId];
    
    if ($status !== 'all') {
        $query .= " AND d.status = ?";
        $params[] = $status;
    }
    
    $query .= " ORDER BY d.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $cases = $db->fetchAll($query, $params);
    
    // Contar total
    $totalQuery = "
        SELECT COUNT(*) as total 
        FROM arbitrator_cases ac 
        WHERE ac.arbitrator_id = ?
    ";
    $totalParams = [$arbitratorId];
    
    if ($status !== 'all') {
        $totalQuery .= " AND EXISTS (SELECT 1 FROM disputes d WHERE d.id = ac.dispute_id AND d.status = ?)";
        $totalParams[] = $status;
    }
    
    $total = $db->fetchOne($totalQuery, $totalParams)['total'];
    
    jsonResponse([
        'cases' => $cases,
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'pages' => ceil($total / $limit)
        ]
    ]);
}

function getCaseDetails($caseId) {
    global $db, $userId;
    
    $arbitratorId = getArbitratorId($userId);
    
    // Verificar acesso
    $hasAccess = $db->fetchOne(
        "SELECT 1 FROM arbitrator_cases WHERE arbitrator_id = ? AND dispute_id = ?",
        [$arbitratorId, $caseId]
    );
    
    if (!$hasAccess) {
        jsonResponse(['error' => 'Acesso negado'], 403);
    }
    
    // Buscar detalhes completos
    $case = $db->fetchOne("
        SELECT 
            d.*,
            u1.name as claimant_name,
            u1.email as claimant_email,
            u1.phone as claimant_phone,
            u2.name as respondent_name,
            u2.email as respondent_email,
            u2.phone as respondent_phone,
            ac.fee_amount,
            ac.status as assignment_status
        FROM disputes d
        INNER JOIN arbitrator_cases ac ON d.id = ac.dispute_id
        LEFT JOIN users u1 ON d.claimant_id = u1.id
        LEFT JOIN users u2 ON d.respondent_id = u2.id
        WHERE d.id = ? AND ac.arbitrator_id = ?
    ", [$caseId, $arbitratorId]);
    
    // Documentos
    $case['documents'] = $db->fetchAll(
        "SELECT * FROM dispute_documents WHERE dispute_id = ? ORDER BY created_at DESC",
        [$caseId]
    );
    
    // Audiências
    $case['hearings'] = $db->fetchAll(
        "SELECT * FROM dispute_hearings WHERE dispute_id = ? ORDER BY date ASC",
        [$caseId]
    );
    
    // Mensagens recentes
    $case['recent_messages'] = $db->fetchAll(
        "SELECT m.*, u.name as sender_name 
         FROM dispute_messages m
         LEFT JOIN users u ON m.sender_id = u.id
         WHERE m.dispute_id = ?
         ORDER BY m.created_at DESC
         LIMIT 10",
        [$caseId]
    );
    
    // Marcar como visualizado
    $db->query(
        "INSERT INTO arbitrator_case_views (arbitrator_id, dispute_id, last_viewed_at) 
         VALUES (?, ?, NOW()) 
         ON DUPLICATE KEY UPDATE last_viewed_at = NOW(), view_count = view_count + 1",
        [$arbitratorId, $caseId]
    );
    
    jsonResponse($case);
}

function acceptCase($data) {
    global $db, $userId;
    
    $arbitratorId = getArbitratorId($userId);
    $caseId = $data['case_id'] ?? 0;
    $feeAmount = $data['fee_amount'] ?? null;
    
    try {
        $db->beginTransaction();
        
        // Verificar se o caso está pendente
        $case = $db->fetchOne(
            "SELECT * FROM arbitrator_cases WHERE arbitrator_id = ? AND dispute_id = ? AND status = 'pending'",
            [$arbitratorId, $caseId]
        );
        
        if (!$case) {
            throw new Exception('Caso não encontrado ou já processado');
        }
        
        // Aceitar caso
        $db->update('arbitrator_cases', [
            'status' => 'accepted',
            'accepted_at' => date('Y-m-d H:i:s'),
            'fee_amount' => $feeAmount
        ], 'id = ?', [$case['id']]);
        
        // Atualizar status da disputa
        $db->update('disputes', [
            'status' => 'in_analysis'
        ], 'id = ?', [$caseId]);
        
        // Notificar partes
        createNotification($caseId, 'arbitrator_accepted', [
            'arbitrator_name' => getUserName($userId)
        ]);
        
        $db->commit();
        jsonResponse(['success' => true, 'message' => 'Caso aceito com sucesso']);
        
    } catch (Exception $e) {
        $db->rollback();
        jsonResponse(['error' => $e->getMessage()], 400);
    }
}

function rejectCase($data) {
    global $db, $userId;
    
    $arbitratorId = getArbitratorId($userId);
    $caseId = $data['case_id'] ?? 0;
    $reason = $data['reason'] ?? '';
    
    try {
        $db->beginTransaction();
        
        // Verificar se o caso está pendente
        $case = $db->fetchOne(
            "SELECT * FROM arbitrator_cases WHERE arbitrator_id = ? AND dispute_id = ? AND status = 'pending'",
            [$arbitratorId, $caseId]
        );
        
        if (!$case) {
            throw new Exception('Caso não encontrado ou já processado');
        }
        
        // Rejeitar caso
        $db->update('arbitrator_cases', [
            'status' => 'rejected',
            'rejection_reason' => $reason
        ], 'id = ?', [$case['id']]);
        
        // Buscar novo árbitro ou voltar para seleção
        $db->update('disputes', [
            'status' => 'arbitrator_selection',
            'arbitrator_id' => null
        ], 'id = ?', [$caseId]);
        
        // Notificar administração
        createAdminNotification('arbitrator_rejected_case', [
            'case_id' => $caseId,
            'arbitrator_name' => getUserName($userId),
            'reason' => $reason
        ]);
        
        $db->commit();
        jsonResponse(['success' => true, 'message' => 'Caso rejeitado']);
        
    } catch (Exception $e) {
        $db->rollback();
        jsonResponse(['error' => $e->getMessage()], 400);
    }
}

function scheduleHearing($data) {
    global $db, $userId;
    
    $arbitratorId = getArbitratorId($userId);
    $caseId = $data['case_id'] ?? 0;
    
    // Verificar acesso
    if (!hasArbitratorAccess($arbitratorId, $caseId)) {
        jsonResponse(['error' => 'Acesso negado'], 403);
    }
    
    try {
        $hearingData = [
            'dispute_id' => $caseId,
            'date' => $data['date'],
            'time' => $data['time'],
            'type' => $data['type'] ?? 'presencial',
            'location' => $data['location'] ?? null,
            'video_link' => $data['video_link'] ?? null,
            'agenda' => $data['agenda'] ?? null,
            'status' => 'scheduled',
            'created_by' => $userId,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $db->insert('dispute_hearings', $hearingData);
        
        // Notificar partes
        createNotification($caseId, 'hearing_scheduled', $hearingData);
        
        jsonResponse([
            'success' => true,
            'message' => 'Audiência agendada com sucesso',
            'hearing_id' => $db->lastInsertId()
        ]);
        
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 400);
    }
}

function issueDecision($data) {
    global $db, $userId;
    
    $arbitratorId = getArbitratorId($userId);
    $caseId = $data['case_id'] ?? 0;
    
    // Verificar acesso
    if (!hasArbitratorAccess($arbitratorId, $caseId)) {
        jsonResponse(['error' => 'Acesso negado'], 403);
    }
    
    try {
        $db->beginTransaction();
        
        // Salvar decisão
        $decisionData = [
            'dispute_id' => $caseId,
            'arbitrator_id' => $arbitratorId,
            'decision_type' => $data['decision_type'] ?? 'final',
            'decision' => $data['decision'],
            'reasoning' => $data['reasoning'],
            'orders' => json_encode($data['orders'] ?? []),
            'issued_at' => date('Y-m-d H:i:s')
        ];
        
        $db->insert('dispute_decisions', $decisionData);
        $decisionId = $db->lastInsertId();
        
        // Atualizar status do caso
        $db->update('disputes', [
            'status' => 'decided',
            'decided_at' => date('Y-m-d H:i:s')
        ], 'id = ?', [$caseId]);
        
        // Atualizar status do árbitro no caso
        $db->update('arbitrator_cases', [
            'status' => 'completed'
        ], 'arbitrator_id = ? AND dispute_id = ?', [$arbitratorId, $caseId]);
        
        // Gerar PDF da decisão
        $pdfPath = generateDecisionPDF($decisionId);
        
        // Notificar partes
        createNotification($caseId, 'decision_issued', [
            'decision_id' => $decisionId,
            'pdf_path' => $pdfPath
        ]);
        
        $db->commit();
        jsonResponse([
            'success' => true,
            'message' => 'Decisão emitida com sucesso',
            'decision_id' => $decisionId,
            'pdf_url' => $pdfPath
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        jsonResponse(['error' => $e->getMessage()], 400);
    }
}

function getArbitratorSchedule() {
    global $db, $userId;
    
    $arbitratorId = getArbitratorId($userId);
    $startDate = $_GET['start'] ?? date('Y-m-d');
    $endDate = $_GET['end'] ?? date('Y-m-d', strtotime('+30 days'));
    
    // Audiências agendadas
    $hearings = $db->fetchAll("
        SELECT 
            h.*,
            d.case_number,
            d.title as case_title,
            u1.name as claimant_name,
            u2.name as respondent_name
        FROM dispute_hearings h
        INNER JOIN disputes d ON h.dispute_id = d.id
        INNER JOIN arbitrator_cases ac ON d.id = ac.dispute_id
        LEFT JOIN users u1 ON d.claimant_id = u1.id
        LEFT JOIN users u2 ON d.respondent_id = u2.id
        WHERE ac.arbitrator_id = ?
        AND h.date BETWEEN ? AND ?
        AND h.status = 'scheduled'
        ORDER BY h.date, h.time
    ", [$arbitratorId, $startDate, $endDate]);
    
    // Prazos importantes
    $deadlines = $db->fetchAll("
        SELECT 
            d.id,
            d.case_number,
            d.title,
            d.deadline_decision as deadline,
            'decision' as deadline_type
        FROM disputes d
        INNER JOIN arbitrator_cases ac ON d.id = ac.dispute_id
        WHERE ac.arbitrator_id = ?
        AND ac.status = 'accepted'
        AND d.status IN ('in_analysis', 'hearing_scheduled')
        AND d.deadline_decision BETWEEN ? AND ?
    ", [$arbitratorId, $startDate, $endDate]);
    
    jsonResponse([
        'hearings' => $hearings,
        'deadlines' => $deadlines
    ]);
}

function getEarningsReport() {
    global $db, $userId;
    
    $arbitratorId = getArbitratorId($userId);
    $period = $_GET['period'] ?? 'month'; // month, quarter, year
    $year = $_GET['year'] ?? date('Y');
    $month = $_GET['month'] ?? date('m');
    
    // Resumo de ganhos
    $summary = $db->fetchOne("
        SELECT 
            COUNT(*) as total_cases,
            SUM(total_amount) as gross_total,
            SUM(platform_fee) as platform_fees,
            SUM(net_amount) as net_total,
            SUM(CASE WHEN status = 'paid' THEN net_amount ELSE 0 END) as paid_total,
            SUM(CASE WHEN status = 'pending' THEN net_amount ELSE 0 END) as pending_total
        FROM arbitrator_fees
        WHERE arbitrator_id = ?
        AND YEAR(created_at) = ?
        " . ($period === 'month' ? "AND MONTH(created_at) = ?" : ""),
        $period === 'month' ? [$arbitratorId, $year, $month] : [$arbitratorId, $year]
    );
    
    // Detalhamento por caso
    $details = $db->fetchAll("
        SELECT 
            af.*,
            d.case_number,
            d.title as case_title,
            d.dispute_amount
        FROM arbitrator_fees af
        INNER JOIN disputes d ON af.dispute_id = d.id
        WHERE af.arbitrator_id = ?
        AND YEAR(af.created_at) = ?
        " . ($period === 'month' ? "AND MONTH(af.created_at) = ?" : "") . "
        ORDER BY af.created_at DESC
    ", $period === 'month' ? [$arbitratorId, $year, $month] : [$arbitratorId, $year]);
    
    // Gráfico de evolução
    $evolution = $db->fetchAll("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as period,
            COUNT(*) as cases,
            SUM(net_amount) as earnings
        FROM arbitrator_fees
        WHERE arbitrator_id = ?
        AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY period
    ", [$arbitratorId]);
    
    jsonResponse([
        'summary' => $summary,
        'details' => $details,
        'evolution' => $evolution
    ]);
}

function updateAvailability($data) {
    global $db, $userId;
    
    $arbitratorId = getArbitratorId($userId);
    
    try {
        $db->beginTransaction();
        
        // Limpar disponibilidade anterior
        $db->delete('arbitrator_availability', 'arbitrator_id = ?', [$arbitratorId]);
        
        // Inserir nova disponibilidade
        foreach ($data['schedule'] ?? [] as $schedule) {
            $db->insert('arbitrator_availability', [
                'arbitrator_id' => $arbitratorId,
                'day_of_week' => $schedule['day'],
                'start_time' => $schedule['start'],
                'end_time' => $schedule['end'],
                'is_available' => $schedule['available'] ?? true
            ]);
        }
        
        // Atualizar horas disponíveis
        if (isset($data['weekly_hours'])) {
            $db->update('arbitrators', 
                ['availability_hours' => $data['weekly_hours']],
                'id = ?',
                [$arbitratorId]
            );
        }
        
        $db->commit();
        jsonResponse(['success' => true, 'message' => 'Disponibilidade atualizada']);
        
    } catch (Exception $e) {
        $db->rollback();
        jsonResponse(['error' => $e->getMessage()], 400);
    }
}

function declareImpediment($data) {
    global $db, $userId;
    
    $arbitratorId = getArbitratorId($userId);
    $caseId = $data['case_id'] ?? 0;
    $type = $data['type'] ?? 'impediment';
    $reason = $data['reason'] ?? '';
    
    try {
        // Verificar se já existe declaração
        $existing = $db->fetchOne(
            "SELECT id FROM arbitrator_impediments 
             WHERE arbitrator_id = ? AND dispute_id = ? AND status = 'pending'",
            [$arbitratorId, $caseId]
        );
        
        if ($existing) {
            throw new Exception('Já existe uma declaração pendente para este caso');
        }
        
        // Inserir declaração
        $db->insert('arbitrator_impediments', [
            'arbitrator_id' => $arbitratorId,
            'dispute_id' => $caseId,
            'type' => $type,
            'reason' => $reason,
            'declared_by' => 'arbitrator',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Notificar administração
        createAdminNotification('impediment_declared', [
            'arbitrator_id' => $arbitratorId,
            'case_id' => $caseId,
            'type' => $type
        ]);
        
        jsonResponse(['success' => true, 'message' => 'Declaração registrada']);
        
    } catch (Exception $e) {
        jsonResponse(['error' => $e->getMessage()], 400);
    }
}

// Funções auxiliares
function getArbitratorId($userId) {
    global $db;
    $result = $db->fetchOne("SELECT id FROM arbitrators WHERE user_id = ?", [$userId]);
    return $result['id'] ?? null;
}

function hasArbitratorAccess($arbitratorId, $caseId) {
    global $db;
    return $db->fetchOne(
        "SELECT 1 FROM arbitrator_cases 
         WHERE arbitrator_id = ? AND dispute_id = ? AND status = 'accepted'",
        [$arbitratorId, $caseId]
    ) !== null;
}

function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    echo json_encode($data);
    exit;
}