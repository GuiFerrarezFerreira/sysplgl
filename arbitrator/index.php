<?php
/**
 * arbitrator/index.php - Dashboard Dinâmico do Árbitro
 */

require_once '../config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Verificar se é árbitro
checkUserType(['arbitrator']);

$db = new Database();
$userId = $_SESSION['user_id'];
$arbitratorId = getArbitratorId($userId);

// Buscar informações do árbitro
$arbitratorInfo = $db->fetchOne("
    SELECT 
        a.*,
        u.name,
        u.email,
        u.phone,
        u.photo_url
    FROM arbitrators a
    INNER JOIN users u ON a.user_id = u.id
    WHERE a.id = ?
", [$arbitratorId]);

// Estatísticas gerais
$stats = [
    'active_cases' => $db->fetchOne("
        SELECT COUNT(*) as count 
        FROM arbitrator_cases ac
        INNER JOIN disputes d ON ac.dispute_id = d.id
        WHERE ac.arbitrator_id = ? 
        AND ac.status = 'accepted'
        AND d.status IN ('in_analysis', 'hearing_scheduled')
    ", [$arbitratorId])['count'],
    
    'completed_cases' => $db->fetchOne("
        SELECT COUNT(*) as count 
        FROM arbitrator_cases ac
        WHERE ac.arbitrator_id = ? 
        AND ac.status = 'completed'
    ", [$arbitratorId])['count'],
    
    'next_hearing' => $db->fetchOne("
        SELECT 
            MIN(CONCAT(h.date, ' ', h.time)) as datetime
        FROM dispute_hearings h
        INNER JOIN disputes d ON h.dispute_id = d.id
        INNER JOIN arbitrator_cases ac ON d.id = ac.dispute_id
        WHERE ac.arbitrator_id = ?
        AND h.status = 'scheduled'
        AND CONCAT(h.date, ' ', h.time) >= NOW()
    ", [$arbitratorId])['datetime'],
    
    'success_rate' => $arbitratorInfo['success_rate'] ?? 0
];

// Casos recentes
$recentCases = $db->fetchAll("
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
            AND created_at > IFNULL(
                (SELECT last_viewed_at 
                 FROM arbitrator_case_views 
                 WHERE arbitrator_id = ? AND dispute_id = d.id),
                '1970-01-01'
            )
        ) as unread_messages,
        (
            SELECT MIN(CONCAT(date, ' ', time))
            FROM dispute_hearings 
            WHERE dispute_id = d.id 
            AND status = 'scheduled'
            AND CONCAT(date, ' ', time) >= NOW()
        ) as next_hearing
    FROM disputes d
    INNER JOIN arbitrator_cases ac ON d.id = ac.dispute_id
    LEFT JOIN users u1 ON d.claimant_id = u1.id
    LEFT JOIN users u2 ON d.respondent_id = u2.id
    WHERE ac.arbitrator_id = ?
    ORDER BY 
        CASE 
            WHEN d.status = 'in_analysis' THEN 1
            WHEN d.status = 'hearing_scheduled' THEN 2
            WHEN d.status = 'pending_documents' THEN 3
            ELSE 4
        END,
        d.created_at DESC
    LIMIT 5
", [$arbitratorId, $arbitratorId]);

// Próximas audiências (7 dias)
$upcomingHearings = $db->fetchAll("
    SELECT 
        h.*,
        d.case_number,
        d.title as case_title
    FROM dispute_hearings h
    INNER JOIN disputes d ON h.dispute_id = d.id
    INNER JOIN arbitrator_cases ac ON d.id = ac.dispute_id
    WHERE ac.arbitrator_id = ?
    AND h.status = 'scheduled'
    AND h.date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY h.date, h.time
    LIMIT 5
", [$arbitratorId]);

// Notificações não lidas
$unreadNotifications = $db->fetchOne("
    SELECT COUNT(*) as count 
    FROM notifications 
    WHERE user_id = ? AND is_read = 0
", [$userId])['count'];

// Ganhos do mês
$monthlyEarnings = $db->fetchOne("
    SELECT 
        SUM(net_amount) as total,
        COUNT(*) as cases
    FROM arbitrator_fees
    WHERE arbitrator_id = ?
    AND YEAR(created_at) = YEAR(CURRENT_DATE())
    AND MONTH(created_at) = MONTH(CURRENT_DATE())
    AND status = 'paid'
", [$arbitratorId]);

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Arbitrivm</title>
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary: #f59e0b;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --gray-50: #f9fafb;
            --gray-100: #f3f4f6;
            --gray-200: #e5e7eb;
            --gray-300: #d1d5db;
            --gray-400: #9ca3af;
            --gray-500: #6b7280;
            --gray-600: #4b5563;
            --gray-700: #374151;
            --gray-800: #1f2937;
            --gray-900: #111827;
            --white: #ffffff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: var(--gray-50);
            color: var(--gray-900);
            line-height: 1.6;
        }

        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem;
        }

        .dashboard-header {
            margin-bottom: 2rem;
        }

        .welcome-message {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .subtitle {
            color: var(--gray-600);
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: var(--white);
            padding: 1.5rem;
            border-radius: 0.75rem;
            border: 1px solid var(--gray-200);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-title {
            font-size: 0.875rem;
            color: var(--gray-600);
            font-weight: 500;
        }

        .stat-icon {
            width: 40px;
            height: 40px;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
        }

        .stat-icon.primary {
            background-color: #dbeafe;
            color: var(--primary);
        }

        .stat-icon.success {
            background-color: #d1fae5;
            color: var(--success);
        }

        .stat-icon.warning {
            background-color: #fed7aa;
            color: var(--warning);
        }

        .stat-icon.info {
            background-color: #e0e7ff;
            color: var(--info);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .stat-change {
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .stat-change.positive {
            color: var(--success);
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
        }

        /* Cases Section */
        .section {
            background: var(--white);
            border-radius: 0.75rem;
            border: 1px solid var(--gray-200);
            overflow: hidden;
        }

        .section-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .section-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
        }

        /* Cases List */
        .cases-list {
            padding: 1rem;
        }

        .case-item {
            padding: 1rem;
            border: 1px solid var(--gray-200);
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            transition: all 0.2s;
            cursor: pointer;
        }

        .case-item:hover {
            border-color: var(--primary);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .case-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 0.75rem;
        }

        .case-id {
            font-size: 0.875rem;
            color: var(--gray-500);
            margin-bottom: 0.25rem;
        }

        .case-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.25rem;
        }

        .case-parties {
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        .case-status {
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .status-in_analysis {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .status-hearing_scheduled {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-pending_documents {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .status-decided {
            background-color: #d1fae5;
            color: #065f46;
        }

        .case-details {
            display: flex;
            gap: 1.5rem;
            font-size: 0.875rem;
            color: var(--gray-600);
        }

        .case-detail {
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        /* Sidebar Sections */
        .sidebar-section {
            background: var(--white);
            border-radius: 0.75rem;
            border: 1px solid var(--gray-200);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .sidebar-section h3 {
            font-size: 1rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 1rem;
        }

        .hearing-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--gray-100);
        }

        .hearing-item:last-child {
            border-bottom: none;
        }

        .hearing-date {
            font-weight: 600;
            color: var(--primary);
            font-size: 0.875rem;
        }

        .hearing-case {
            font-size: 0.875rem;
            color: var(--gray-600);
            margin-top: 0.25rem;
        }

        .hearing-type {
            display: inline-block;
            padding: 0.125rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            margin-top: 0.25rem;
        }

        .hearing-type.online {
            background: #e0e7ff;
            color: #3730a3;
        }

        .hearing-type.presencial {
            background: #d1fae5;
            color: #065f46;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: var(--gray-500);
        }

        .empty-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }

        /* Buttons */
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            font-size: 0.875rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
        }

        .btn-secondary {
            background-color: var(--gray-100);
            color: var(--gray-700);
        }

        .btn-secondary:hover {
            background-color: var(--gray-200);
        }

        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }

        .quick-action {
            padding: 1rem;
            background: var(--gray-50);
            border-radius: 0.5rem;
            text-align: center;
            text-decoration: none;
            color: var(--gray-700);
            transition: all 0.2s;
            font-size: 0.875rem;
        }

        .quick-action:hover {
            background: var(--primary);
            color: white;
        }

        /* Responsive */
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .case-details {
                flex-direction: column;
                gap: 0.5rem;
            }

            .dashboard-container {
                padding: 1rem;
            }
        }

        /* Badge para mensagens não lidas */
        .unread-badge {
            background: var(--danger);
            color: white;
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-left: 0.5rem;
        }
    </style>
</head>
<body>
    <?php include 'templates/header.php'; ?>

    <div class="dashboard-container">
        <!-- Header -->
        <div class="dashboard-header">
            <h1 class="welcome-message">Olá, <?php echo htmlspecialchars(explode(' ', $arbitratorInfo['name'])[0]); ?>!</h1>
            <p class="subtitle">
                <?php 
                $hour = date('H');
                if ($hour < 12) echo 'Bom dia';
                elseif ($hour < 18) echo 'Boa tarde';
                else echo 'Boa noite';
                ?>! Aqui está o resumo das suas atividades.
            </p>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Casos Ativos</div>
                    <div class="stat-icon primary">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 7l7-4l7 4v10l-7 4l-7-4V7z"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-value"><?php echo $stats['active_cases']; ?></div>
                <div class="stat-change positive">
                    <?php if ($stats['active_cases'] > 0): ?>
                        Em andamento
                    <?php else: ?>
                        Nenhum caso ativo
                    <?php endif; ?>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Casos Concluídos</div>
                    <div class="stat-icon success">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 10l3 3l7-7"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-value"><?php echo $stats['completed_cases']; ?></div>
                <div class="stat-change">Total histórico</div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Próxima Audiência</div>
                    <div class="stat-icon warning">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="14" height="12" rx="2"/>
                            <path d="M8 2v4m4-4v4"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-value">
                    <?php if ($stats['next_hearing']): ?>
                        <?php echo date('d/m', strtotime($stats['next_hearing'])); ?>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </div>
                <div class="stat-change">
                    <?php if ($stats['next_hearing']): ?>
                        às <?php echo date('H:i', strtotime($stats['next_hearing'])); ?>h
                    <?php else: ?>
                        Sem audiências agendadas
                    <?php endif; ?>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div class="stat-title">Taxa de Sucesso</div>
                    <div class="stat-icon info">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M10 16v-6m0-4h.01"/>
                            <circle cx="10" cy="10" r="8"/>
                        </svg>
                    </div>
                </div>
                <div class="stat-value"><?php echo number_format($stats['success_rate'], 0); ?>%</div>
                <div class="stat-change">de acordos realizados</div>
            </div>
        </div>

        <div class="content-grid">
            <!-- Cases Section -->
            <div class="section">
                <div class="section-header">
                    <h2 class="section-title">Casos Recentes</h2>
                    <a href="cases.php" class="btn btn-primary">Ver Todos</a>
                </div>

                <div class="cases-list">
                    <?php if (empty($recentCases)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">📋</div>
                            <p>Nenhum caso designado no momento.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recentCases as $case): ?>
                            <div class="case-item" onclick="window.location.href='cases.php?view=<?php echo $case['id']; ?>'">
                                <div class="case-header">
                                    <div>
                                        <div class="case-id"><?php echo htmlspecialchars($case['case_number']); ?></div>
                                        <h3 class="case-title"><?php echo htmlspecialchars($case['title']); ?></h3>
                                        <p class="case-parties">
                                            <?php echo htmlspecialchars($case['claimant_name']); ?> vs 
                                            <?php echo htmlspecialchars($case['respondent_name']); ?>
                                        </p>
                                    </div>
                                    <span class="case-status status-<?php echo $case['status']; ?>">
                                        <?php echo getStatusLabel($case['status']); ?>
                                    </span>
                                </div>
                                <div class="case-details">
                                    <div class="case-detail">
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                            <path d="M12 1a1 1 0 0 1 1 1v13h1.5a.5.5 0 0 1 0 1h-13a.5.5 0 0 1 0-1H3V2a1 1 0 0 1 1-1h8zm-2 9a1 1 0 1 0 0-2 1 1 0 0 0 0 2z"/>
                                        </svg>
                                        R$ <?php echo number_format($case['dispute_amount'], 2, ',', '.'); ?>
                                    </div>
                                    <?php if ($case['next_hearing']): ?>
                                        <div class="case-detail">
                                            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                                <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4H1z"/>
                                            </svg>
                                            <?php echo date('d/m H:i', strtotime($case['next_hearing'])); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($case['unread_messages'] > 0): ?>
                                        <div class="case-detail">
                                            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                                <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V4zm2-1a1 1 0 0 0-1 1v.217l7 4.2 7-4.2V4a1 1 0 0 0-1-1H2zm13 2.383l-4.758 2.855L15 11.114v-5.73zm-.034 6.878L9.271 8.82 8 9.583 6.728 8.82l-5.694 3.44A1 1 0 0 0 2 13h12a1 1 0 0 0 .966-.739zM1 11.114l4.758-2.876L1 5.383v5.73z"/>
                                            </svg>
                                            <span class="unread-badge"><?php echo $case['unread_messages']; ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Sidebar -->
            <div>
                <!-- Próximas Audiências -->
                <div class="sidebar-section">
                    <h3>Próximas Audiências</h3>
                    <?php if (empty($upcomingHearings)): ?>
                        <p class="empty-state" style="padding: 1rem 0;">Nenhuma audiência nos próximos 7 dias.</p>
                    <?php else: ?>
                        <?php foreach ($upcomingHearings as $hearing): ?>
                            <div class="hearing-item">
                                <div class="hearing-date">
                                    <?php echo date('d/m/Y', strtotime($hearing['date'])); ?> às <?php echo substr($hearing['time'], 0, 5); ?>
                                </div>
                                <div class="hearing-case">
                                    Caso <?php echo htmlspecialchars($hearing['case_number']); ?>
                                </div>
                                <span class="hearing-type <?php echo $hearing['type']; ?>">
                                    <?php echo ucfirst($hearing['type']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <a href="schedule.php" class="btn btn-secondary" style="width: 100%; margin-top: 1rem;">
                        Ver Agenda Completa
                    </a>
                </div>

                <!-- Ganhos do Mês -->
                <div class="sidebar-section">
                    <h3>Ganhos do Mês</h3>
                    <div style="font-size: 1.875rem; font-weight: 700; color: var(--success); margin: 0.5rem 0;">
                        R$ <?php echo number_format($monthlyEarnings['total'] ?? 0, 2, ',', '.'); ?>
                    </div>
                    <p style="font-size: 0.875rem; color: var(--gray-600);">
                        <?php echo $monthlyEarnings['cases'] ?? 0; ?> caso(s) pago(s)
                    </p>
                    <a href="earnings.php" class="btn btn-secondary" style="width: 100%; margin-top: 1rem;">
                        Ver Detalhes
                    </a>
                </div>

                <!-- Ações Rápidas -->
                <div class="sidebar-section">
                    <h3>Ações Rápidas</h3>
                    <div class="quick-actions">
                        <a href="profile.php" class="quick-action">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" style="margin-bottom: 0.5rem;">
                                <path d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z"/>
                            </svg>
                            <div>Meu Perfil</div>
                        </a>
                        <a href="documents.php" class="quick-action">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" style="margin-bottom: 0.5rem;">
                                <path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/>
                                <path fill-rule="evenodd" d="M4 5a2 2 0 012-2 1 1 0 000 2H4v10h12V5h-2a1 1 0 100-2 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5z"/>
                            </svg>
                            <div>Documentos</div>
                        </a>
                        <a href="notifications.php" class="quick-action">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" style="margin-bottom: 0.5rem;">
                                <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z"/>
                            </svg>
                            <div>Notificações <?php if ($unreadNotifications > 0): ?>(<?php echo $unreadNotifications; ?>)<?php endif; ?></div>
                        </a>
                        <a href="../logout.php" class="quick-action">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor" style="margin-bottom: 0.5rem;">
                                <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 102 0V4a1 1 0 00-1-1zm10.293 9.293a1 1 0 001.414 1.414l3-3a1 1 0 000-1.414l-3-3a1 1 0 10-1.414 1.414L14.586 9H7a1 1 0 100 2h7.586l-1.293 1.293z"/>
                            </svg>
                            <div>Sair</div>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'templates/footer.php'; ?>

    <script>
        // Auto-refresh a cada 5 minutos
        setTimeout(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>

<?php
// Função auxiliar para labels de status
function getStatusLabel($status) {
    $labels = [
        'in_analysis' => 'Em Análise',
        'hearing_scheduled' => 'Audiência Agendada',
        'pending_documents' => 'Aguardando Documentos',
        'decided' => 'Decidido',
        'settled' => 'Acordo Realizado',
        'cancelled' => 'Cancelado'
    ];
    return $labels[$status] ?? $status;
}
?>