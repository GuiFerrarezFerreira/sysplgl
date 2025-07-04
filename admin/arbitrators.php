<?php
/**
 * Arbitrivm - Administração de Árbitros
 * admin/arbitrators.php
 */

require_once '../config.php';

// Verificar se está logado e é admin
if (!isLoggedIn() || getCurrentUser()['role'] !== 'admin') {
    $_SESSION['error'] = 'Acesso negado.';
    redirect('../login.php');
    exit;
}

$user = getCurrentUser();
$db = new Database();

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRF($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';
    $arbitratorId = intval($_POST['arbitrator_id'] ?? 0);
    
    switch ($action) {
        case 'approve':
            approveArbitrator($db, $arbitratorId);
            break;
            
        case 'reject':
            rejectArbitrator($db, $arbitratorId, $_POST['reason'] ?? '');
            break;
            
        case 'suspend':
            suspendArbitrator($db, $arbitratorId, $_POST['reason'] ?? '');
            break;
            
        case 'activate':
            activateArbitrator($db, $arbitratorId);
            break;
            
        case 'verify_document':
            verifyDocument($db, intval($_POST['document_id'] ?? 0));
            break;
    }
}

// Filtros
$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';

// Buscar árbitros
$sql = "SELECT a.*, u.*, 
        a.id as arbitrator_id,
        u.id as user_id,
        CONCAT(u.first_name, ' ', u.last_name) as full_name,
        (SELECT COUNT(*) FROM disputes WHERE arbitrator_id = a.id) as total_cases,
        (SELECT COUNT(*) FROM disputes WHERE arbitrator_id = a.id AND status = 'active') as active_cases,
        (SELECT COUNT(*) FROM arbitrator_documents WHERE arbitrator_id = a.id AND verified = 0) as pending_docs
        FROM arbitrators a
        JOIN users u ON a.user_id = u.id
        WHERE 1=1";

$params = [];

// Aplicar filtros
if ($filter === 'pending') {
    $sql .= " AND a.documents_verified = 0";
} elseif ($filter === 'active') {
    $sql .= " AND a.documents_verified = 1 AND a.is_available = 1";
} elseif ($filter === 'suspended') {
    $sql .= " AND u.is_active = 0";
}

// Busca
if ($search) {
    $sql .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR a.registration_number LIKE ?)";
    $searchParam = "%$search%";
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
}

$sql .= " ORDER BY a.created_at DESC";

$arbitrators = $db->fetchAll($sql, $params);

// Funções de ação
function approveArbitrator($db, $arbitratorId) {
    try {
        // Atualizar status
        $db->update('arbitrators', 
            ['documents_verified' => 1, 'verified_at' => date('Y-m-d H:i:s')],
            'id = ?',
            [$arbitratorId]
        );
        
        // Buscar dados do árbitro
        $arbitrator = $db->fetchOne(
            "SELECT u.email, u.first_name FROM arbitrators a 
             JOIN users u ON a.user_id = u.id 
             WHERE a.id = ?",
            [$arbitratorId]
        );
        
        // Enviar email
        $subject = "Cadastro Aprovado - Arbitrivm";
        $message = "
            <h2>Parabéns, {$arbitrator['first_name']}!</h2>
            <p>Seu cadastro como árbitro foi aprovado.</p>
            <p>Você já pode começar a receber casos para arbitragem.</p>
            <p><a href='" . BASE_URL . "/login.php'>Acessar plataforma</a></p>
        ";
        
        Utils::sendEmail($arbitrator['email'], $subject, $message);
        
        $_SESSION['success'] = 'Árbitro aprovado com sucesso!';
    } catch (Exception $e) {
        $_SESSION['error'] = 'Erro ao aprovar árbitro.';
        logError('Erro ao aprovar árbitro: ' . $e->getMessage());
    }
    
    redirect('arbitrators.php');
}

function rejectArbitrator($db, $arbitratorId, $reason) {
    try {
        // Buscar dados antes de rejeitar
        $arbitrator = $db->fetchOne(
            "SELECT u.email, u.first_name, u.id as user_id FROM arbitrators a 
             JOIN users u ON a.user_id = u.id 
             WHERE a.id = ?",
            [$arbitratorId]
        );
        
        // Deletar registro de árbitro
        $db->delete('arbitrators', 'id = ?', [$arbitratorId]);
        
        // Atualizar role do usuário
        $db->update('users', ['role' => 'user'], 'id = ?', [$arbitrator['user_id']]);
        
        // Enviar email
        $subject = "Cadastro de Árbitro - Arbitrivm";
        $message = "
            <h2>Olá, {$arbitrator['first_name']}</h2>
            <p>Infelizmente seu cadastro como árbitro não foi aprovado.</p>
            <p><strong>Motivo:</strong> $reason</p>
            <p>Você pode tentar novamente após resolver as pendências apontadas.</p>
        ";
        
        Utils::sendEmail($arbitrator['email'], $subject, $message);
        
        $_SESSION['success'] = 'Árbitro rejeitado.';
    } catch (Exception $e) {
        $_SESSION['error'] = 'Erro ao rejeitar árbitro.';
        logError('Erro ao rejeitar árbitro: ' . $e->getMessage());
    }
    
    redirect('arbitrators.php');
}

function suspendArbitrator($db, $arbitratorId, $reason) {
    try {
        // Buscar user_id
        $arbitrator = $db->fetchOne("SELECT user_id FROM arbitrators WHERE id = ?", [$arbitratorId]);
        
        // Desativar usuário
        $db->update('users', ['is_active' => 0], 'id = ?', [$arbitrator['user_id']]);
        
        // Desativar árbitro
        $db->update('arbitrators', ['is_available' => 0], 'id = ?', [$arbitratorId]);
        
        // Log
        $db->insert('arbitrator_suspensions', [
            'arbitrator_id' => $arbitratorId,
            'reason' => $reason,
            'suspended_by' => getCurrentUserId(),
            'suspended_at' => date('Y-m-d H:i:s')
        ]);
        
        $_SESSION['success'] = 'Árbitro suspenso.';
    } catch (Exception $e) {
        $_SESSION['error'] = 'Erro ao suspender árbitro.';
    }
    
    redirect('arbitrators.php');
}

function activateArbitrator($db, $arbitratorId) {
    try {
        // Buscar user_id
        $arbitrator = $db->fetchOne("SELECT user_id FROM arbitrators WHERE id = ?", [$arbitratorId]);
        
        // Ativar usuário
        $db->update('users', ['is_active' => 1], 'id = ?', [$arbitrator['user_id']]);
        
        // Ativar árbitro
        $db->update('arbitrators', ['is_available' => 1], 'id = ?', [$arbitratorId]);
        
        $_SESSION['success'] = 'Árbitro ativado.';
    } catch (Exception $e) {
        $_SESSION['error'] = 'Erro ao ativar árbitro.';
    }
    
    redirect('arbitrators.php');
}

function verifyDocument($db, $documentId) {
    try {
        $db->update('arbitrator_documents', 
            ['verified' => 1, 'verified_at' => date('Y-m-d H:i:s'), 'verified_by' => getCurrentUserId()],
            'id = ?',
            [$documentId]
        );
        
        $_SESSION['success'] = 'Documento verificado.';
    } catch (Exception $e) {
        $_SESSION['error'] = 'Erro ao verificar documento.';
    }
}

$csrfToken = generateCSRF();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administração de Árbitros - Arbitrivm</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
    <?php include 'includes/admin-header.php'; ?>
    
    <div class="admin-container">
        <?php include 'includes/admin-sidebar.php'; ?>
        
        <main class="admin-content">
            <div class="page-header">
                <h1>Administração de Árbitros</h1>
                <div class="header-actions">
                    <a href="invite-arbitrator.php" class="btn btn-primary">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Convidar Árbitro
                    </a>
                </div>
            </div>
            
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-error">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Filtros -->
            <div class="filters-bar">
                <div class="filter-tabs">
                    <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                        Todos (<?php echo count($arbitrators); ?>)
                    </a>
                    <a href="?filter=pending" class="filter-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                        Pendentes
                    </a>
                    <a href="?filter=active" class="filter-tab <?php echo $filter === 'active' ? 'active' : ''; ?>">
                        Ativos
                    </a>
                    <a href="?filter=suspended" class="filter-tab <?php echo $filter === 'suspended' ? 'active' : ''; ?>">
                        Suspensos
                    </a>
                </div>
                
                <form class="search-form" method="GET">
                    <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                    <input type="search" name="search" placeholder="Buscar por nome, email ou registro..." 
                           value="<?php echo htmlspecialchars($search); ?>" class="search-input">
                    <button type="submit" class="btn btn-secondary">Buscar</button>
                </form>
            </div>
            
            <!-- Lista de Árbitros -->
            <div class="data-table">
                <table>
                    <thead>
                        <tr>
                            <th>Árbitro</th>
                            <th>Registro</th>
                            <th>Especialização</th>
                            <th>Casos</th>
                            <th>Avaliação</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($arbitrators as $arb): ?>
                        <tr>
                            <td>
                                <div class="user-info">
                                    <strong><?php echo htmlspecialchars($arb['full_name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($arb['email']); ?></small>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($arb['registration_number']); ?></td>
                            <td>
                                <?php
                                $specs = json_decode($arb['specializations'], true) ?? [];
                                echo htmlspecialchars(implode(', ', array_slice($specs, 0, 2)));
                                if (count($specs) > 2) echo '...';
                                ?>
                            </td>
                            <td>
                                <?php echo $arb['total_cases']; ?> total<br>
                                <small><?php echo $arb['active_cases']; ?> ativos</small>
                            </td>
                            <td>
                                <?php if ($arb['rating'] > 0): ?>
                                    ⭐ <?php echo number_format($arb['rating'], 1); ?>
                                    <small>(<?php echo $arb['total_reviews']; ?>)</small>
                                <?php else: ?>
                                    <small>Sem avaliações</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!$arb['documents_verified']): ?>
                                    <span class="badge badge-warning">Pendente</span>
                                    <?php if ($arb['pending_docs'] > 0): ?>
                                        <br><small><?php echo $arb['pending_docs']; ?> docs</small>
                                    <?php endif; ?>
                                <?php elseif (!$arb['is_active']): ?>
                                    <span class="badge badge-danger">Suspenso</span>
                                <?php elseif (!$arb['is_available']): ?>
                                    <span class="badge badge-gray">Indisponível</span>
                                <?php else: ?>
                                    <span class="badge badge-success">Ativo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <a href="arbitrator-detail.php?id=<?php echo $arb['arbitrator_id']; ?>" 
                                       class="btn btn-sm" title="Ver detalhes">
                                        👁️
                                    </a>
                                    
                                    <?php if (!$arb['documents_verified']): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="action" value="approve">
                                            <input type="hidden" name="arbitrator_id" value="<?php echo $arb['arbitrator_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-success" title="Aprovar">
                                                ✓
                                            </button>
                                        </form>
                                        <button class="btn btn-sm btn-danger" 
                                                onclick="rejectArbitrator(<?php echo $arb['arbitrator_id']; ?>)"
                                                title="Rejeitar">
                                            ✗
                                        </button>
                                    <?php elseif ($arb['is_active']): ?>
                                        <button class="btn btn-sm btn-warning" 
                                                onclick="suspendArbitrator(<?php echo $arb['arbitrator_id']; ?>)"
                                                title="Suspender">
                                            ⏸️
                                        </button>
                                    <?php else: ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                            <input type="hidden" name="action" value="activate">
                                            <input type="hidden" name="arbitrator_id" value="<?php echo $arb['arbitrator_id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-success" title="Ativar">
                                                ▶️
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>
    
    <!-- Modal de Rejeição -->
    <div class="modal" id="rejectModal" style="display: none;">
        <div class="modal-content">
            <h3>Rejeitar Árbitro</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="arbitrator_id" id="rejectArbitratorId">
                
                <div class="form-group">
                    <label>Motivo da rejeição:</label>
                    <textarea name="reason" class="form-control" rows="4" required></textarea>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('reject')">Cancelar</button>
                    <button type="submit" class="btn btn-danger">Rejeitar</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal de Suspensão -->
    <div class="modal" id="suspendModal" style="display: none;">
        <div class="modal-content">
            <h3>Suspender Árbitro</h3>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <input type="hidden" name="action" value="suspend">
                <input type="hidden" name="arbitrator_id" id="suspendArbitratorId">
                
                <div class="form-group">
                    <label>Motivo da suspensão:</label>
                    <textarea name="reason" class="form-control" rows="4" required></textarea>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('suspend')">Cancelar</button>
                    <button type="submit" class="btn btn-warning">Suspender</button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function rejectArbitrator(id) {
            document.getElementById('rejectArbitratorId').value = id;
            document.getElementById('rejectModal').style.display = 'block';
        }
        
        function suspendArbitrator(id) {
            document.getElementById('suspendArbitratorId').value = id;
            document.getElementById('suspendModal').style.display = 'block';
        }
        
        function closeModal(type) {
            document.getElementById(type + 'Modal').style.display = 'none';
        }
    </script>
</body>
</html>