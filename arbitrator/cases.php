<?php
/**
 * arbitrator/cases.php - Gestão de Casos do Árbitro
 */

require_once '../config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Verificar se é árbitro
checkUserType(['arbitrator']);

$db = new Database();
$arbitratorId = getArbitratorId($_SESSION['user_id']);

// Filtros
$status = $_GET['status'] ?? 'all';
$search = $_GET['search'] ?? '';
$page = (int)($_GET['page'] ?? 1);
$limit = 10;
$offset = ($page - 1) * $limit;

// Buscar casos
$query = "
    SELECT 
        d.*,
        u1.name as claimant_name,
        u2.name as respondent_name,
        GROUP_CONCAT(DISTINCT dd.file_path) as documents,
        (
            SELECT COUNT(*) 
            FROM dispute_messages 
            WHERE dispute_id = d.id 
            AND created_at > IFNULL(
                (SELECT last_viewed_at 
                 FROM arbitrator_case_views 
                 WHERE arbitrator_id = ? 
                 AND dispute_id = d.id),
                '1970-01-01'
            )
        ) as unread_messages,
        (
            SELECT MIN(date) 
            FROM dispute_hearings 
            WHERE dispute_id = d.id 
            AND date >= CURDATE()
            AND status = 'scheduled'
        ) as next_hearing_date
    FROM disputes d
    LEFT JOIN users u1 ON d.claimant_id = u1.id
    LEFT JOIN users u2 ON d.respondent_id = u2.id
    LEFT JOIN dispute_documents dd ON d.id = dd.dispute_id
    WHERE d.arbitrator_id = ?
";

$params = [$arbitratorId, $arbitratorId];

// Aplicar filtros
if ($status !== 'all') {
    $query .= " AND d.status = ?";
    $params[] = $status;
}

if ($search) {
    $query .= " AND (d.case_number LIKE ? OR d.title LIKE ? OR u1.name LIKE ? OR u2.name LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

$query .= " GROUP BY d.id ORDER BY d.created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

$cases = $db->fetchAll($query, $params);

// Contar total
$countQuery = "SELECT COUNT(*) as total FROM disputes WHERE arbitrator_id = ?";
$countParams = [$arbitratorId];

if ($status !== 'all') {
    $countQuery .= " AND status = ?";
    $countParams[] = $status;
}

$totalCases = $db->fetchOne($countQuery, $countParams)['total'];
$totalPages = ceil($totalCases / $limit);

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $disputeId = (int)($_POST['dispute_id'] ?? 0);
    
    // Verificar se o árbitro tem acesso ao caso
    $hasAccess = $db->fetchOne(
        "SELECT id FROM disputes WHERE id = ? AND arbitrator_id = ?",
        [$disputeId, $arbitratorId]
    );
    
    if (!$hasAccess) {
        $_SESSION['error'] = 'Acesso negado a este caso.';
        redirect($_SERVER['PHP_SELF']);
    }
    
    switch ($action) {
        case 'update_status':
            $newStatus = $_POST['status'] ?? '';
            $notes = $_POST['notes'] ?? '';
            
            try {
                $db->beginTransaction();
                
                // Atualizar status
                $db->update('disputes', 
                    ['status' => $newStatus, 'updated_at' => date('Y-m-d H:i:s')],
                    'id = ?',
                    [$disputeId]
                );
                
                // Adicionar ao histórico
                $db->insert('dispute_history', [
                    'dispute_id' => $disputeId,
                    'action' => 'status_change',
                    'description' => "Status alterado de {$hasAccess['status']} para $newStatus",
                    'notes' => $notes,
                    'created_by' => $_SESSION['user_id'],
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                // Notificar partes
                notifyParties($disputeId, 'status_change', [
                    'old_status' => $hasAccess['status'],
                    'new_status' => $newStatus,
                    'notes' => $notes
                ]);
                
                $db->commit();
                $_SESSION['success'] = 'Status atualizado com sucesso.';
                
            } catch (Exception $e) {
                $db->rollback();
                $_SESSION['error'] = 'Erro ao atualizar status.';
                logError('Erro ao atualizar status do caso: ' . $e->getMessage());
            }
            break;
            
        case 'schedule_hearing':
            $date = $_POST['hearing_date'] ?? '';
            $time = $_POST['hearing_time'] ?? '';
            $type = $_POST['hearing_type'] ?? 'presencial';
            $location = $_POST['location'] ?? '';
            $link = $_POST['video_link'] ?? '';
            
            try {
                $hearingData = [
                    'dispute_id' => $disputeId,
                    'date' => $date,
                    'time' => $time,
                    'type' => $type,
                    'location' => $type === 'presencial' ? $location : null,
                    'video_link' => $type === 'online' ? $link : null,
                    'status' => 'scheduled',
                    'created_by' => $_SESSION['user_id'],
                    'created_at' => date('Y-m-d H:i:s')
                ];
                
                $db->insert('dispute_hearings', $hearingData);
                
                // Notificar partes
                notifyParties($disputeId, 'hearing_scheduled', $hearingData);
                
                $_SESSION['success'] = 'Audiência agendada com sucesso.';
                
            } catch (Exception $e) {
                $_SESSION['error'] = 'Erro ao agendar audiência.';
                logError('Erro ao agendar audiência: ' . $e->getMessage());
            }
            break;
            
        case 'upload_document':
            if (isset($_FILES['document'])) {
                $uploadResult = uploadFile($_FILES['document'], 'dispute_documents/' . $disputeId);
                
                if ($uploadResult['success']) {
                    try {
                        $db->insert('dispute_documents', [
                            'dispute_id' => $disputeId,
                            'title' => $_POST['document_title'] ?? $_FILES['document']['name'],
                            'file_path' => $uploadResult['path'],
                            'file_type' => $uploadResult['type'],
                            'file_size' => $uploadResult['size'],
                            'uploaded_by' => $_SESSION['user_id'],
                            'created_at' => date('Y-m-d H:i:s')
                        ]);
                        
                        $_SESSION['success'] = 'Documento enviado com sucesso.';
                        
                    } catch (Exception $e) {
                        $_SESSION['error'] = 'Erro ao registrar documento.';
                        logError('Erro ao registrar documento: ' . $e->getMessage());
                    }
                } else {
                    $_SESSION['error'] = $uploadResult['message'];
                }
            }
            break;
            
        case 'send_message':
            $message = $_POST['message'] ?? '';
            $toParty = $_POST['to_party'] ?? 'both'; // both, claimant, respondent
            
            if ($message) {
                try {
                    $db->insert('dispute_messages', [
                        'dispute_id' => $disputeId,
                        'sender_id' => $_SESSION['user_id'],
                        'message' => $message,
                        'to_party' => $toParty,
                        'created_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    // Notificar destinatários
                    notifyMessage($disputeId, $message, $toParty);
                    
                    $_SESSION['success'] = 'Mensagem enviada com sucesso.';
                    
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Erro ao enviar mensagem.';
                    logError('Erro ao enviar mensagem: ' . $e->getMessage());
                }
            }
            break;
            
        case 'issue_decision':
            $decision = $_POST['decision'] ?? '';
            $reasoning = $_POST['reasoning'] ?? '';
            
            if ($decision && $reasoning) {
                try {
                    $db->beginTransaction();
                    
                    // Salvar decisão
                    $db->insert('dispute_decisions', [
                        'dispute_id' => $disputeId,
                        'arbitrator_id' => $arbitratorId,
                        'decision' => $decision,
                        'reasoning' => $reasoning,
                        'issued_at' => date('Y-m-d H:i:s')
                    ]);
                    
                    // Atualizar status do caso
                    $db->update('disputes',
                        ['status' => 'decided', 'decided_at' => date('Y-m-d H:i:s')],
                        'id = ?',
                        [$disputeId]
                    );
                    
                    // Gerar PDF da decisão
                    $pdfPath = generateDecisionPDF($disputeId);
                    
                    // Notificar partes
                    notifyParties($disputeId, 'decision_issued', [
                        'decision' => $decision,
                        'pdf_path' => $pdfPath
                    ]);
                    
                    $db->commit();
                    $_SESSION['success'] = 'Decisão emitida com sucesso.';
                    
                } catch (Exception $e) {
                    $db->rollback();
                    $_SESSION['error'] = 'Erro ao emitir decisão.';
                    logError('Erro ao emitir decisão: ' . $e->getMessage());
                }
            }
            break;
    }
    
    redirect($_SERVER['PHP_SELF']);
}

// Marcar caso como visualizado
if (isset($_GET['view']) && is_numeric($_GET['view'])) {
    $db->query(
        "INSERT INTO arbitrator_case_views (arbitrator_id, dispute_id, last_viewed_at) 
         VALUES (?, ?, NOW()) 
         ON DUPLICATE KEY UPDATE last_viewed_at = NOW()",
        [$arbitratorId, $_GET['view']]
    );
}

// Estatísticas
$stats = [
    'total' => $db->fetchOne(
        "SELECT COUNT(*) as count FROM disputes WHERE arbitrator_id = ?",
        [$arbitratorId]
    )['count'],
    'active' => $db->fetchOne(
        "SELECT COUNT(*) as count FROM disputes WHERE arbitrator_id = ? AND status IN ('in_analysis', 'hearing_scheduled')",
        [$arbitratorId]
    )['count'],
    'pending' => $db->fetchOne(
        "SELECT COUNT(*) as count FROM disputes WHERE arbitrator_id = ? AND status = 'pending_documents'",
        [$arbitratorId]
    )['count'],
    'completed' => $db->fetchOne(
        "SELECT COUNT(*) as count FROM disputes WHERE arbitrator_id = ? AND status IN ('decided', 'settled')",
        [$arbitratorId]
    )['count']
];

// Funções auxiliares específicas desta página
function notifyParties($disputeId, $type, $data = []) {
    global $db;
    
    $dispute = $db->fetchOne(
        "SELECT * FROM disputes WHERE id = ?",
        [$disputeId]
    );
    
    $parties = [
        $db->fetchOne("SELECT * FROM users WHERE id = ?", [$dispute['claimant_id']]),
        $db->fetchOne("SELECT * FROM users WHERE id = ?", [$dispute['respondent_id']])
    ];
    
    foreach ($parties as $party) {
        // Criar notificação no sistema
        $db->insert('notifications', [
            'user_id' => $party['id'],
            'type' => $type,
            'title' => getNotificationTitle($type),
            'message' => getNotificationMessage($type, $data),
            'link' => '/disputes/view.php?id=' . $disputeId,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Enviar email
        $emailBody = getEmailTemplate($type, array_merge($data, [
            'user_name' => $party['name'],
            'case_number' => $dispute['case_number'],
            'case_title' => $dispute['title']
        ]));
        
        sendEmail($party['email'], getNotificationTitle($type), $emailBody);
    }
}

function getNotificationTitle($type) {
    $titles = [
        'status_change' => 'Atualização no Status do Caso',
        'hearing_scheduled' => 'Audiência Agendada',
        'decision_issued' => 'Decisão Emitida',
        'document_uploaded' => 'Novo Documento Disponível',
        'message_sent' => 'Nova Mensagem no Caso'
    ];
    
    return $titles[$type] ?? 'Atualização no Caso';
}

function getNotificationMessage($type, $data) {
    switch ($type) {
        case 'status_change':
            return "O status do caso foi alterado para: " . getStatusLabel($data['new_status']);
        case 'hearing_scheduled':
            return "Uma audiência foi agendada para " . formatDate($data['date']) . " às " . $data['time'];
        case 'decision_issued':
            return "A decisão arbitral foi emitida. Acesse o caso para visualizar.";
        default:
            return "Há uma nova atualização no seu caso.";
    }
}

function generateDecisionPDF($disputeId) {
    // Implementar geração de PDF da decisão
    // Por enquanto, retornar caminho fictício
    return '/documents/decisions/decision_' . $disputeId . '.pdf';
}

// Include do template HTML
include 'templates/cases.php';
?>