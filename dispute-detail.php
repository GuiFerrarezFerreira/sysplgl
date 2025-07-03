<?php
/**
 * Arbitrivm - Detalhes da Disputa
 */

require_once 'config.php';

// Verificar se está logado
if (!isLoggedIn()) {
    redirect('login.php');
    exit;
}

$disputeId = $_GET['id'] ?? null;
if (!$disputeId) {
    redirect('index.php');
    exit;
}

$user = getCurrentUser();
$db = new Database();
$auth = new Auth();

// Verificar permissão para acessar a disputa
if (!$auth->canAccessDispute($user['id'], $disputeId)) {
    $_SESSION['error'] = 'Você não tem permissão para acessar esta disputa.';
    redirect('index.php');
    exit;
}

// Buscar dados da disputa
$dispute = $db->getDisputeById($disputeId);
if (!$dispute) {
    $_SESSION['error'] = 'Disputa não encontrada.';
    redirect('index.php');
    exit;
}

// Buscar documentos
$documents = $db->getDocumentsByDispute($disputeId);

// Buscar mensagens
$messages = $db->getMessagesByDispute($disputeId);

// Buscar eventos/timeline
$events = $db->fetchAll(
    "SELECT de.*, CONCAT(u.first_name, ' ', u.last_name) as user_name
     FROM dispute_events de
     JOIN users u ON de.user_id = u.id
     WHERE de.dispute_id = ?
     ORDER BY de.created_at DESC",
    [$disputeId]
);

$csrfToken = generateCSRF();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($dispute['case_number']); ?> - Arbitrivm</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* Reutilizar CSS base do index.php */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --primary-light: #3b82f6;
            --secondary: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
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
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
            color: var(--gray);
            margin-bottom: 2rem;
        }

        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .page-header {
            background: var(--white);
            padding: 2rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .case-number {
            color: var(--primary);
            font-weight: 600;
        }

        .page-subtitle {
            color: var(--gray);
            font-size: 0.875rem;
        }

        .status-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-top: 1rem;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: var(--gray);
            margin-bottom: 0.25rem;
        }

        .info-value {
            font-weight: 600;
            color: var(--dark);
        }

        .tabs {
            display: flex;
            gap: 0.5rem;
            border-bottom: 1px solid var(--border);
            margin-bottom: 2rem;
        }

        .tab {
            padding: 1rem 1.5rem;
            border-radius: var(--radius) var(--radius) 0 0;
            background: transparent;
            border: none;
            cursor: pointer;
            color: var(--gray);
            font-weight: 500;
            transition: var(--transition);
            position: relative;
        }

        .tab:hover {
            color: var(--primary);
        }

        .tab.active {
            color: var(--primary);
            background: var(--white);
        }

        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--primary);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .card {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--dark);
        }

        .card-body {
            padding: 1.5rem;
        }

        .description-box {
            background: var(--light);
            padding: 1rem;
            border-radius: var(--radius);
            margin-bottom: 1rem;
        }

        .parties-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .party-card {
            border: 1px solid var(--border);
            padding: 1.5rem;
            border-radius: var(--radius);
        }

        .party-role {
            font-size: 0.75rem;
            text-transform: uppercase;
            color: var(--gray);
            margin-bottom: 0.5rem;
        }

        .party-name {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .party-info {
            font-size: 0.875rem;
            color: var(--gray);
            line-height: 1.5;
        }

        .document-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .document-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1rem;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            transition: var(--transition);
        }

        .document-item:hover {
            background: var(--light);
        }

        .document-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .document-icon {
            width: 40px;
            height: 40px;
            background: var(--light);
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray);
        }

        .document-name {
            font-weight: 500;
            color: var(--dark);
        }

        .document-meta {
            font-size: 0.75rem;
            color: var(--gray);
        }

        .chat-container {
            display: flex;
            flex-direction: column;
            height: 600px;
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 1.5rem;
            background: var(--light);
            border-radius: var(--radius);
        }

        .message {
            margin-bottom: 1.5rem;
        }

        .message-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .message-author {
            font-weight: 600;
            font-size: 0.875rem;
        }

        .message-time {
            font-size: 0.75rem;
            color: var(--gray);
        }

        .message-content {
            background: var(--white);
            padding: 1rem;
            border-radius: var(--radius);
            font-size: 0.875rem;
        }

        .message.sent .message-content {
            background: var(--primary);
            color: white;
            margin-left: auto;
            max-width: 70%;
        }

        .chat-input {
            padding: 1.5rem;
            border-top: 1px solid var(--border);
            display: flex;
            gap: 1rem;
        }

        .timeline {
            position: relative;
            padding-left: 2rem;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 0.5rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background-color: var(--border);
        }

        .timeline-item {
            position: relative;
            margin-bottom: 2rem;
        }

        .timeline-marker {
            position: absolute;
            left: -1.5rem;
            top: 0.25rem;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            background-color: var(--white);
            border: 2px solid var(--primary);
        }

        .timeline-content {
            background-color: var(--white);
            padding: 1rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
        }

        .timeline-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .timeline-title {
            font-weight: 600;
            font-size: 0.875rem;
        }

        .timeline-time {
            font-size: 0.75rem;
            color: var(--gray);
        }

        .timeline-description {
            font-size: 0.875rem;
            color: var(--gray);
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 500;
            border-radius: var(--radius);
            border: none;
            cursor: pointer;
            transition: var(--transition);
            text-decoration: none;
            gap: 0.5rem;
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
        }

        .btn-secondary:hover {
            background-color: var(--light);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            font-size: 0.75rem;
            font-weight: 500;
            border-radius: 9999px;
        }

        .badge-primary {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .badge-success {
            background-color: #d1fae5;
            color: #065f46;
        }

        .badge-warning {
            background-color: #fef3c7;
            color: #92400e;
        }

        .badge-danger {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .form-control {
            width: 100%;
            padding: 0.5rem 0.75rem;
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

        textarea.form-control {
            resize: vertical;
            min-height: 80px;
        }

        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray);
        }

        .empty-state-icon {
            width: 48px;
            height: 48px;
            margin: 0 auto 1rem;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .info-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .parties-grid {
                grid-template-columns: 1fr;
            }

            .tabs {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Breadcrumb -->
        <nav class="breadcrumb">
            <a href="index.php">Dashboard</a>
            <span>/</span>
            <a href="index.php#disputes">Disputas</a>
            <span>/</span>
            <span><?php echo htmlspecialchars($dispute['case_number']); ?></span>
        </nav>

        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <span class="case-number"><?php echo htmlspecialchars($dispute['case_number']); ?></span>
                - <?php echo htmlspecialchars($dispute['title']); ?>
            </h1>
            <p class="page-subtitle"><?php echo htmlspecialchars($dispute['dispute_type_name']); ?></p>
            
            <div class="status-header">
                <?php
                $statusMap = [
                    'draft' => ['class' => 'badge-gray', 'text' => 'Rascunho'],
                    'pending_arbitrator' => ['class' => 'badge-warning', 'text' => 'Aguardando Árbitro'],
                    'pending_acceptance' => ['class' => 'badge-warning', 'text' => 'Aguardando Aceitação'],
                    'active' => ['class' => 'badge-primary', 'text' => 'Em Andamento'],
                    'on_hold' => ['class' => 'badge-warning', 'text' => 'Em Espera'],
                    'resolved' => ['class' => 'badge-success', 'text' => 'Resolvida'],
                    'cancelled' => ['class' => 'badge-danger', 'text' => 'Cancelada']
                ];
                $statusInfo = $statusMap[$dispute['status']] ?? ['class' => 'badge-gray', 'text' => $dispute['status']];
                ?>
                <span class="badge <?php echo $statusInfo['class']; ?>">
                    <?php echo $statusInfo['text']; ?>
                </span>
                
                <?php if ($dispute['priority'] === 'urgent'): ?>
                <span class="badge badge-danger">Urgente</span>
                <?php elseif ($dispute['priority'] === 'high'): ?>
                <span class="badge badge-warning">Alta Prioridade</span>
                <?php endif; ?>
            </div>

            <div class="info-grid">
                <div class="info-item">
                    <span class="info-label">Valor da Causa</span>
                    <span class="info-value">
                        <?php echo $dispute['claim_amount'] ? formatMoney($dispute['claim_amount']) : 'Não informado'; ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Data de Criação</span>
                    <span class="info-value"><?php echo formatDate($dispute['created_at'], 'd/m/Y H:i'); ?></span>
                </div>
                <div class="info-item">
                    <span class="info-label">Prazo</span>
                    <span class="info-value">
                        <?php echo $dispute['deadline_date'] ? formatDate($dispute['deadline_date']) : 'Não definido'; ?>
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">Árbitro</span>
                    <span class="info-value">
                        <?php echo $dispute['arbitrator_name'] ?? 'Aguardando designação'; ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" onclick="showTab('overview')">Visão Geral</button>
            <button class="tab" onclick="showTab('documents')">Documentos (<?php echo count($documents); ?>)</button>
            <button class="tab" onclick="showTab('messages')">Mensagens (<?php echo count($messages); ?>)</button>
            <button class="tab" onclick="showTab('timeline')">Histórico</button>
        </div>

        <!-- Tab Contents -->
        <div id="overview-tab" class="tab-content active">
            <!-- Descrição -->
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Descrição da Disputa</h2>
                </div>
                <div class="card-body">
                    <div class="description-box">
                        <?php echo nl2br(htmlspecialchars($dispute['description'])); ?>
                    </div>
                    
                    <?php if ($dispute['property_address']): ?>
                    <div class="info-item">
                        <span class="info-label">Endereço do Imóvel</span>
                        <span class="info-value"><?php echo htmlspecialchars($dispute['property_address']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($dispute['contract_number']): ?>
                    <div class="info-item" style="margin-top: 1rem;">
                        <span class="info-label">Número do Contrato</span>
                        <span class="info-value"><?php echo htmlspecialchars($dispute['contract_number']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Partes Envolvidas -->
            <div class="card" style="margin-top: 2rem;">
                <div class="card-header">
                    <h2 class="card-title">Partes Envolvidas</h2>
                </div>
                <div class="card-body">
                    <div class="parties-grid">
                        <div class="party-card">
                            <div class="party-role">Requerente</div>
                            <div class="party-name"><?php echo htmlspecialchars($dispute['claimant_name']); ?></div>
                            <div class="party-info">
                                <?php echo htmlspecialchars($dispute['claimant_email']); ?>
                            </div>
                        </div>
                        
                        <div class="party-card">
                            <div class="party-role">Requerido</div>
                            <div class="party-name"><?php echo htmlspecialchars($dispute['respondent_name']); ?></div>
                            <div class="party-info">
                                <?php echo htmlspecialchars($dispute['respondent_email']); ?>
                            </div>
                        </div>
                        
                        <?php if ($dispute['arbitrator_name']): ?>
                        <div class="party-card">
                            <div class="party-role">Árbitro</div>
                            <div class="party-name"><?php echo htmlspecialchars($dispute['arbitrator_name']); ?></div>
                            <div class="party-info">
                                <?php echo htmlspecialchars($dispute['arbitrator_email']); ?><br>
                                Registro: <?php echo htmlspecialchars($dispute['arbitrator_registration']); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Ações -->
            <?php if ($dispute['status'] === 'active'): ?>
            <div class="action-buttons">
                <button class="btn btn-primary" onclick="openModal('addDocument')">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                    </svg>
                    Adicionar Documento
                </button>
                
                <?php if ($user['role'] === 'arbitrator' && $dispute['arbitrator_email'] === $user['email']): ?>
                <button class="btn btn-secondary" onclick="openModal('schedule')">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    Agendar Audiência
                </button>
                
                <button class="btn btn-secondary" onclick="openModal('decision')">
                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Emitir Decisão
                </button>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <div id="documents-tab" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Documentos</h2>
                    <button class="btn btn-primary" onclick="openModal('addDocument')">
                        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        Adicionar
                    </button>
                </div>
                <div class="card-body">
                    <?php if (empty($documents)): ?>
                    <div class="empty-state">
                        <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <p>Nenhum documento foi adicionado ainda.</p>
                    </div>
                    <?php else: ?>
                    <div class="document-list">
                        <?php foreach ($documents as $doc): ?>
                        <div class="document-item">
                            <div class="document-info">
                                <div class="document-icon">
                                    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                </div>
                                <div>
                                    <div class="document-name"><?php echo htmlspecialchars($doc['file_name']); ?></div>
                                    <div class="document-meta">
                                        <?php echo Utils::formatBytes($doc['file_size']); ?> • 
                                        Enviado por <?php echo htmlspecialchars($doc['uploaded_by_name']); ?> • 
                                        <?php echo Utils::timeAgo($doc['created_at']); ?>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <a href="api/documents.php?action=download&id=<?php echo $doc['id']; ?>" 
                                   class="btn btn-secondary" 
                                   target="_blank">
                                    Download
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div id="messages-tab" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Mensagens</h2>
                </div>
                <div class="card-body">
                    <div class="chat-container">
                        <div class="chat-messages" id="chatMessages">
                            <?php if (empty($messages)): ?>
                            <div class="empty-state">
                                <p>Nenhuma mensagem ainda. Inicie a conversa!</p>
                            </div>
                            <?php else: ?>
                                <?php foreach (array_reverse($messages) as $msg): ?>
                                <div class="message <?php echo $msg['sender_id'] == $user['id'] ? 'sent' : 'received'; ?>">
                                    <div class="message-header">
                                        <span class="message-author"><?php echo htmlspecialchars($msg['sender_name']); ?></span>
                                        <span class="message-time"><?php echo Utils::timeAgo($msg['created_at']); ?></span>
                                    </div>
                                    <div class="message-content">
                                        <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <form class="chat-input" onsubmit="sendMessage(event)">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <textarea 
                                class="form-control" 
                                name="message" 
                                placeholder="Digite sua mensagem..." 
                                required
                                onkeypress="if(event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); sendMessage(event); }"
                            ></textarea>
                            <button type="submit" class="btn btn-primary">Enviar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <div id="timeline-tab" class="tab-content">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Histórico de Eventos</h2>
                </div>
                <div class="card-body">
                    <div class="timeline">
                        <?php foreach ($events as $event): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker"></div>
                            <div class="timeline-content">
                                <div class="timeline-header">
                                    <span class="timeline-title"><?php echo htmlspecialchars($event['description']); ?></span>
                                    <span class="timeline-time"><?php echo formatDate($event['created_at'], 'd/m/Y H:i'); ?></span>
                                </div>
                                <p class="timeline-description">
                                    Por <?php echo htmlspecialchars($event['user_name']); ?>
                                    <?php if ($event['metadata']): ?>
                                        <?php
                                        $metadata = json_decode($event['metadata'], true);
                                        if ($metadata && isset($metadata['old_status']) && isset($metadata['new_status'])) {
                                            echo " • De " . $statusMap[$metadata['old_status']]['text'] . 
                                                 " para " . $statusMap[$metadata['new_status']]['text'];
                                        }
                                        ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Tab functionality
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Remove active class from all tab buttons
            document.querySelectorAll('.tab').forEach(button => {
                button.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.add('active');
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }

        // Send message
        async function sendMessage(event) {
            event.preventDefault();
            
            const form = event.target;
            const formData = new FormData(form);
            formData.append('dispute_id', '<?php echo $disputeId; ?>');
            
            try {
                const response = await fetch('api/messages.php', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success) {
                    location.reload(); // Recarregar para mostrar nova mensagem
                } else {
                    alert('Erro ao enviar mensagem: ' + data.message);
                }
            } catch (error) {
                alert('Erro ao enviar mensagem');
            }
        }

        // Auto-scroll chat to bottom
        const chatMessages = document.getElementById('chatMessages');
        if (chatMessages) {
            chatMessages.scrollTop = chatMessages.scrollHeight;
        }

        // Modal functions (placeholder)
        function openModal(type) {
            alert('Modal ' + type + ' será implementado');
        }
    </script>
</body>
</html>