<?php
/**
 * arbitrator/notifications.php - Notificações do Árbitro
 */

require_once '../config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

checkUserType(['arbitrator']);

$db = new Database();
$userId = $_SESSION['user_id'];

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'mark_read':
            $notificationId = $_POST['notification_id'] ?? 0;
            $db->update('notifications', 
                ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')],
                'id = ? AND user_id = ?',
                [$notificationId, $userId]
            );
            echo json_encode(['success' => true]);
            exit;
            
        case 'mark_all_read':
            $db->update('notifications',
                ['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')],
                'user_id = ? AND is_read = 0',
                [$userId]
            );
            $_SESSION['success'] = 'Todas as notificações foram marcadas como lidas.';
            redirect('notifications.php');
            break;
            
        case 'delete_notification':
            $notificationId = $_POST['notification_id'] ?? 0;
            $db->delete('notifications', 'id = ? AND user_id = ?', [$notificationId, $userId]);
            $_SESSION['success'] = 'Notificação removida.';
            redirect('notifications.php');
            break;
            
        case 'update_preferences':
            $preferences = [
                'email' => [
                    'new_case' => isset($_POST['email_new_case']),
                    'hearing_reminder' => isset($_POST['email_hearing_reminder']),
                    'deadline_reminder' => isset($_POST['email_deadline_reminder']),
                    'new_message' => isset($_POST['email_new_message']),
                    'new_document' => isset($_POST['email_new_document']),
                    'payment' => isset($_POST['email_payment']),
                    'review' => isset($_POST['email_review'])
                ],
                'sms' => [
                    'new_case' => isset($_POST['sms_new_case']),
                    'urgent_hearing' => isset($_POST['sms_urgent_hearing']),
                    'payments' => isset($_POST['sms_payments'])
                ],
                'push' => [
                    'enabled' => isset($_POST['push_enabled'])
                ]
            ];
            
            $db->update('users',
                ['notification_preferences' => json_encode($preferences)],
                'id = ?',
                [$userId]
            );
            
            $_SESSION['success'] = 'Preferências de notificação atualizadas.';
            redirect('notifications.php');
            break;
    }
}

// Filtros
$filter = $_GET['filter'] ?? 'all';
$type = $_GET['type'] ?? 'all';
$page = (int)($_GET['page'] ?? 1);
$limit = 20;
$offset = ($page - 1) * $limit;

// Construir query
$query = "SELECT * FROM notifications WHERE user_id = ?";
$params = [$userId];

if ($filter === 'unread') {
    $query .= " AND is_read = 0";
} elseif ($filter === 'read') {
    $query .= " AND is_read = 1";
}

if ($type !== 'all') {
    $query .= " AND type = ?";
    $params[] = $type;
}

$query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

// Buscar notificações
$notifications = $db->fetchAll($query, $params);

// Contar total
$countQuery = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ?";
$countParams = [$userId];

if ($filter === 'unread') {
    $countQuery .= " AND is_read = 0";
} elseif ($filter === 'read') {
    $countQuery .= " AND is_read = 1";
}

if ($type !== 'all') {
    $countQuery .= " AND type = ?";
    $countParams[] = $type;
}

$totalNotifications = $db->fetchOne($countQuery, $countParams)['total'];
$totalPages = ceil($totalNotifications / $limit);

// Estatísticas
$stats = [
    'total' => $db->fetchOne("SELECT COUNT(*) as count FROM notifications WHERE user_id = ?", [$userId])['count'],
    'unread' => $db->fetchOne("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0", [$userId])['count'],
    'today' => $db->fetchOne("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND DATE(created_at) = CURDATE()", [$userId])['count'],
    'this_week' => $db->fetchOne("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)", [$userId])['count']
];

// Buscar preferências atuais
$user = $db->fetchOne("SELECT notification_preferences FROM users WHERE id = ?", [$userId]);
$preferences = json_decode($user['notification_preferences'] ?? '{}', true);

// Tipos de notificação
$notificationTypes = [
    'new_case_assigned' => ['icon' => '📋', 'color' => 'primary'],
    'hearing_reminder' => ['icon' => '📅', 'color' => 'warning'],
    'deadline_reminder' => ['icon' => '⏰', 'color' => 'danger'],
    'new_message' => ['icon' => '💬', 'color' => 'info'],
    'new_document' => ['icon' => '📄', 'color' => 'secondary'],
    'payment_received' => ['icon' => '💰', 'color' => 'success'],
    'new_review' => ['icon' => '⭐', 'color' => 'warning'],
    'status_change' => ['icon' => '🔄', 'color' => 'primary'],
    'decision_issued' => ['icon' => '⚖️', 'color' => 'success']
];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notificações - Arbitrivm</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .notifications-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .notifications-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .header-actions {
            display: flex;
            gap: 1rem;
        }
        
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 350px;
            gap: 2rem;
        }
        
        .notifications-section {
            background: white;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .section-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e5e7eb;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .filter-tabs {
            display: flex;
            gap: 1rem;
            padding: 0 1.5rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .filter-tab {
            padding: 1rem 0;
            color: #6b7280;
            text-decoration: none;
            font-weight: 500;
            position: relative;
            transition: color 0.2s;
        }
        
        .filter-tab:hover {
            color: #2563eb;
        }
        
        .filter-tab.active {
            color: #2563eb;
        }
        
        .filter-tab.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 2px;
            background: #2563eb;
        }
        
        .notifications-list {
            max-height: 600px;
            overflow-y: auto;
        }
        
        .notification-item {
            display: flex;
            padding: 1.5rem;
            border-bottom: 1px solid #f3f4f6;
            transition: background 0.2s;
            cursor: pointer;
        }
        
        .notification-item:hover {
            background: #f9fafb;
        }
        
        .notification-item.unread {
            background: #f0f9ff;
        }
        
        .notification-icon {
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 0.5rem;
            margin-right: 1rem;
            font-size: 1.5rem;
        }
        
        .notification-icon.primary {
            background: #dbeafe;
        }
        
        .notification-icon.success {
            background: #d1fae5;
        }
        
        .notification-icon.warning {
            background: #fed7aa;
        }
        
        .notification-icon.danger {
            background: #fee2e2;
        }
        
        .notification-icon.info {
            background: #e0e7ff;
        }
        
        .notification-icon.secondary {
            background: #f3f4f6;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-title {
            font-weight: 600;
            color: #111827;
            margin-bottom: 0.25rem;
        }
        
        .notification-message {
            color: #6b7280;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }
        
        .notification-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.75rem;
            color: #9ca3af;
        }
        
        .notification-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .notification-dot {
            width: 8px;
            height: 8px;
            background: #2563eb;
            border-radius: 50%;
        }
        
        .preferences-section {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            height: fit-content;
        }
        
        .preference-group {
            margin-bottom: 1.5rem;
        }
        
        .preference-group:last-child {
            margin-bottom: 0;
        }
        
        .preference-group h4 {
            margin-bottom: 0.75rem;
            color: #374151;
        }
        
        .preference-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0.5rem 0;
        }
        
        .preference-label {
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .toggle-switch {
            position: relative;
            width: 44px;
            height: 24px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: #e5e7eb;
            transition: 0.3s;
            border-radius: 24px;
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 16px;
            width: 16px;
            left: 4px;
            bottom: 4px;
            background: white;
            transition: 0.3s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background: #2563eb;
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(20px);
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #9ca3af;
        }
        
        .empty-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            padding: 1.5rem;
            border-top: 1px solid #e5e7eb;
        }
        
        .pagination a {
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            text-decoration: none;
            color: #6b7280;
            transition: all 0.2s;
        }
        
        .pagination a:hover {
            background: #f3f4f6;
            color: #111827;
        }
        
        .pagination a.active {
            background: #2563eb;
            color: white;
        }
        
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .preferences-section {
                order: -1;
            }
        }
        
        @media (max-width: 768px) {
            .notifications-header {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
            
            .header-actions {
                flex-direction: column;
            }
            
            .filter-tabs {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
        }
    </style>
</head>
<body>
    <?php include 'templates/header.php'; ?>
    
    <div class="notifications-container">
        <div class="notifications-header">
            <h1>Notificações</h1>
            <div class="header-actions">
                <?php if ($stats['unread'] > 0): ?>
                    <form action="notifications.php" method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="mark_all_read">
                        <button type="submit" class="btn btn-secondary">
                            Marcar todas como lidas
                        </button>
                    </form>
                <?php endif; ?>
                <button class="btn btn-primary" onclick="showPreferences()">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                        <path d="M8 4.754a3.246 3.246 0 1 0 0 6.492 3.246 3.246 0 0 0 0-6.492zM5.754 8a2.246 2.246 0 1 1 4.492 0 2.246 2.246 0 0 1-4.492 0z"/>
                        <path d="M9.796 1.343c-.527-1.79-3.065-1.79-3.592 0l-.094.319a.873.873 0 0 1-1.255.52l-.292-.16c-1.64-.892-3.433.902-2.54 2.541l.159.292a.873.873 0 0 1-.52 1.255l-.319.094c-1.79.527-1.79 3.065 0 3.592l.319.094a.873.873 0 0 1 .52 1.255l-.16.292c-.892 1.64.901 3.434 2.541 2.54l.292-.159a.873.873 0 0 1 1.255.52l.094.319c.527 1.79 3.065 1.79 3.592 0l.094-.319a.873.873 0 0 1 1.255-.52l.292.16c1.64.893 3.434-.902 2.54-2.541l-.159-.292a.873.873 0 0 1 .52-1.255l.319-.094c1.79-.527 1.79-3.065 0-3.592l-.319-.094a.873.873 0 0 1-.52-1.255l.16-.292c.893-1.64-.902-3.433-2.541-2.54l-.292.159a.873.873 0 0 1-1.255-.52l-.094-.319zm-2.633.283c.246-.835 1.428-.835 1.674 0l.094.319a1.873 1.873 0 0 0 2.693 1.115l.291-.16c.764-.415 1.6.42 1.184 1.185l-.159.292a1.873 1.873 0 0 0 1.116 2.692l.318.094c.835.246.835 1.428 0 1.674l-.319.094a1.873 1.873 0 0 0-1.115 2.693l.16.291c.415.764-.42 1.6-1.185 1.184l-.291-.159a1.873 1.873 0 0 0-2.693 1.116l-.094.318c-.246.835-1.428.835-1.674 0l-.094-.319a1.873 1.873 0 0 0-2.692-1.115l-.292.16c-.764.415-1.6-.42-1.184-1.185l.159-.291A1.873 1.873 0 0 0 1.945 8.93l-.319-.094c-.835-.246-.835-1.428 0-1.674l.319-.094A1.873 1.873 0 0 0 3.06 4.377l-.16-.292c-.415-.764.42-1.6 1.185-1.184l.292.159a1.873 1.873 0 0 0 2.692-1.115l.094-.319z"/>
                    </svg>
                    Preferências
                </button>
            </div>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <!-- Estatísticas -->
        <div class="stats-cards">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total']; ?></div>
                <div class="stat-label">Total</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #2563eb;"><?php echo $stats['unread']; ?></div>
                <div class="stat-label">Não Lidas</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['today']; ?></div>
                <div class="stat-label">Hoje</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['this_week']; ?></div>
                <div class="stat-label">Esta Semana</div>
            </div>
        </div>
        
        <div class="content-grid">
            <!-- Lista de Notificações -->
            <div class="notifications-section">
                <div class="section-header">
                    <h2>Notificações</h2>
                    <select class="form-control" style="width: auto;" onchange="filterByType(this.value)">
                        <option value="all" <?php echo $type === 'all' ? 'selected' : ''; ?>>Todos os tipos</option>
                        <option value="new_case_assigned" <?php echo $type === 'new_case_assigned' ? 'selected' : ''; ?>>Novos casos</option>
                        <option value="hearing_reminder" <?php echo $type === 'hearing_reminder' ? 'selected' : ''; ?>>Audiências</option>
                        <option value="deadline_reminder" <?php echo $type === 'deadline_reminder' ? 'selected' : ''; ?>>Prazos</option>
                        <option value="new_message" <?php echo $type === 'new_message' ? 'selected' : ''; ?>>Mensagens</option>
                        <option value="payment_received" <?php echo $type === 'payment_received' ? 'selected' : ''; ?>>Pagamentos</option>
                    </select>
                </div>
                
                <div class="filter-tabs">
                    <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                        Todas
                    </a>
                    <a href="?filter=unread" class="filter-tab <?php echo $filter === 'unread' ? 'active' : ''; ?>">
                        Não lidas
                    </a>
                    <a href="?filter=read" class="filter-tab <?php echo $filter === 'read' ? 'active' : ''; ?>">
                        Lidas
                    </a>
                </div>
                
                <div class="notifications-list">
                    <?php if (empty($notifications)): ?>
                        <div class="empty-state">
                            <div class="empty-icon">🔔</div>
                            <p>Nenhuma notificação encontrada.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($notifications as $notification): ?>
                            <?php 
                            $typeInfo = $notificationTypes[$notification['type']] ?? ['icon' => '📌', 'color' => 'secondary'];
                            ?>
                            <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>" 
                                 onclick="handleNotificationClick(<?php echo $notification['id']; ?>, '<?php echo htmlspecialchars($notification['link'] ?? ''); ?>')">
                                <div class="notification-icon <?php echo $typeInfo['color']; ?>">
                                    <?php echo $typeInfo['icon']; ?>
                                </div>
                                <div class="notification-content">
                                    <div class="notification-title">
                                        <?php echo htmlspecialchars($notification['title']); ?>
                                    </div>
                                    <div class="notification-message">
                                        <?php echo htmlspecialchars($notification['message']); ?>
                                    </div>
                                    <div class="notification-meta">
                                        <span><?php echo timeAgo($notification['created_at']); ?></span>
                                        <?php if ($notification['priority'] === 'high'): ?>
                                            <span style="color: #ef4444;">Alta prioridade</span>
                                        <?php elseif ($notification['priority'] === 'urgent'): ?>
                                            <span style="color: #dc2626; font-weight: 600;">Urgente!</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="notification-actions">
                                    <?php if (!$notification['is_read']): ?>
                                        <div class="notification-dot"></div>
                                    <?php endif; ?>
                                    <form action="notifications.php" method="POST" style="display: inline;" onclick="event.stopPropagation();">
                                        <input type="hidden" name="action" value="delete_notification">
                                        <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                        <button type="submit" class="btn btn-sm" style="background: none; border: none; color: #9ca3af;" title="Remover">
                                            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                                <path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/>
                                                <path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V4h-.5a1 1 0 0 1-1-1V2a1 1 0 0 1 1-1H6a1 1 0 0 1 1-1h2a1 1 0 0 1 1 1h3.5a1 1 0 0 1 1 1v1zM4.118 4 4 4.059V13a1 1 0 0 0 1 1h6a1 1 0 0 0 1-1V4.059L11.882 4H4.118zM2.5 3V2h11v1h-11z"/>
                                            </svg>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>&filter=<?php echo $filter; ?>&type=<?php echo $type; ?>">
                                Anterior
                            </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <a href="?page=<?php echo $i; ?>&filter=<?php echo $filter; ?>&type=<?php echo $type; ?>" 
                               class="<?php echo $i === $page ? 'active' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?>&filter=<?php echo $filter; ?>&type=<?php echo $type; ?>">
                                Próxima
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Preferências -->
            <div class="preferences-section" id="preferencesPanel" style="display: none;">
                <h3>Preferências de Notificação</h3>
                
                <form action="notifications.php" method="POST">
                    <input type="hidden" name="action" value="update_preferences">
                    
                    <div class="preference-group">
                        <h4>Notificações por Email</h4>
                        <div class="preference-item">
                            <label class="preference-label">Novos casos designados</label>
                            <label class="toggle-switch">
                                <input type="checkbox" name="email_new_case" <?php echo ($preferences['email']['new_case'] ?? true) ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="preference-item">
                            <label class="preference-label">Lembretes de audiência</label>
                            <label class="toggle-switch">
                                <input type="checkbox" name="email_hearing_reminder" <?php echo ($preferences['email']['hearing_reminder'] ?? true) ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="preference-item">
                            <label class="preference-label">Lembretes de prazo</label>
                            <label class="toggle-switch">
                                <input type="checkbox" name="email_deadline_reminder" <?php echo ($preferences['email']['deadline_reminder'] ?? true) ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="preference-item">
                            <label class="preference-label">Novas mensagens</label>
                            <label class="toggle-switch">
                                <input type="checkbox" name="email_new_message" <?php echo ($preferences['email']['new_message'] ?? false) ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="preference-item">
                            <label class="preference-label">Novos documentos</label>
                            <label class="toggle-switch">
                                <input type="checkbox" name="email_new_document" <?php echo ($preferences['email']['new_document'] ?? false) ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="preference-item">
                            <label class="preference-label">Pagamentos</label>
                            <label class="toggle-switch">
                                <input type="checkbox" name="email_payment" <?php echo ($preferences['email']['payment'] ?? true) ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="preference-item">
                            <label class="preference-label">Avaliações recebidas</label>
                            <label class="toggle-switch">
                                <input type="checkbox" name="email_review" <?php echo ($preferences['email']['review'] ?? true) ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="preference-group">
                        <h4>Notificações por SMS</h4>
                        <div class="preference-item">
                            <label class="preference-label">Novos casos urgentes</label>
                            <label class="toggle-switch">
                                <input type="checkbox" name="sms_new_case" <?php echo ($preferences['sms']['new_case'] ?? false) ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="preference-item">
                            <label class="preference-label">Audiências urgentes</label>
                            <label class="toggle-switch">
                                <input type="checkbox" name="sms_urgent_hearing" <?php echo ($preferences['sms']['urgent_hearing'] ?? true) ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <div class="preference-item">
                            <label class="preference-label">Confirmação de pagamentos</label>
                            <label class="toggle-switch">
                                <input type="checkbox" name="sms_payments" <?php echo ($preferences['sms']['payments'] ?? false) ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="preference-group">
                        <h4>Notificações Push</h4>
                        <div class="preference-item">
                            <label class="preference-label">Ativar notificações push</label>
                            <label class="toggle-switch">
                                <input type="checkbox" name="push_enabled" <?php echo ($preferences['push']['enabled'] ?? true) ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">Salvar Preferências</button>
                </form>
            </div>
        </div>
    </div>
    
    <?php include 'templates/footer.php'; ?>
    
    <script>
        function handleNotificationClick(notificationId, link) {
            // Marcar como lida via AJAX
            fetch('notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=mark_read&notification_id=${notificationId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && link) {
                    window.location.href = link;
                }
            });
            
            // Remover classe unread imediatamente
            event.currentTarget.classList.remove('unread');
        }
        
        function filterByType(type) {
            const urlParams = new URLSearchParams(window.location.search);
            urlParams.set('type', type);
            urlParams.set('page', '1');
            window.location.href = `notifications.php?${urlParams.toString()}`;
        }
        
        function showPreferences() {
            const panel = document.getElementById('preferencesPanel');
            panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
        }
        
        // Auto-refresh para novas notificações
        setInterval(function() {
            fetch('api/notifications/count.php')
                .then(response => response.json())
                .then(data => {
                    if (data.unread > 0) {
                        // Atualizar contador no header se existir
                        const badge = document.querySelector('.notification-badge');
                        if (badge) {
                            badge.textContent = data.unread;
                            badge.style.display = 'block';
                        }
                    }
                });
        }, 30000); // A cada 30 segundos
    </script>
</body>
</html>

<?php
// Função auxiliar para tempo relativo
function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->y > 0) {
        return $diff->y . ' ano' . ($diff->y > 1 ? 's' : '') . ' atrás';
    } elseif ($diff->m > 0) {
        return $diff->m . ' mês' . ($diff->m > 1 ? 'es' : '') . ' atrás';
    } elseif ($diff->d > 0) {
        if ($diff->d == 1) return 'ontem';
        return $diff->d . ' dias atrás';
    } elseif ($diff->h > 0) {
        return $diff->h . ' hora' . ($diff->h > 1 ? 's' : '') . ' atrás';
    } elseif ($diff->i > 0) {
        return $diff->i . ' minuto' . ($diff->i > 1 ? 's' : '') . ' atrás';
    } else {
        return 'agora mesmo';
    }
}
?>