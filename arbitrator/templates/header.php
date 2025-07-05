<?php
/**
 * arbitrator/templates/header.php - Header para páginas do árbitro
 */

// Buscar informações do árbitro
$db = new Database();
$userId = $_SESSION['user_id'] ?? 0;
$userInfo = $db->fetchOne("SELECT name, email, photo_url FROM users WHERE id = ?", [$userId]);

// Contar notificações não lidas
$unreadNotifications = $db->fetchOne(
    "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0",
    [$userId]
)['count'];

// Página atual para destacar no menu
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Estilos do Header */
        .app-header {
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .header-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 64px;
        }
        
        .header-left {
            display: flex;
            align-items: center;
            gap: 2rem;
        }
        
        .logo-link {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            color: #2563eb;
            font-weight: 700;
            font-size: 1.25rem;
        }
        
        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #2563eb, #1d4ed8);
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .nav-menu {
            display: flex;
            gap: 1.5rem;
        }
        
        .nav-link {
            color: #6b7280;
            text-decoration: none;
            font-weight: 500;
            padding: 0.5rem 0;
            position: relative;
            transition: color 0.2s;
        }
        
        .nav-link:hover {
            color: #2563eb;
        }
        
        .nav-link.active {
            color: #2563eb;
        }
        
        .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: -1rem;
            left: 0;
            right: 0;
            height: 3px;
            background: #2563eb;
        }
        
        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .notification-button {
            position: relative;
            padding: 0.5rem;
            border-radius: 0.5rem;
            background: transparent;
            border: none;
            cursor: pointer;
            color: #6b7280;
            transition: all 0.2s;
        }
        
        .notification-button:hover {
            background: #f3f4f6;
            color: #374151;
        }
        
        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            background: #ef4444;
            color: white;
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.125rem 0.375rem;
            border-radius: 9999px;
            min-width: 1.25rem;
            text-align: center;
        }
        
        .user-menu {
            position: relative;
        }
        
        .user-menu-button {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            background: transparent;
            border: none;
            cursor: pointer;
            transition: background 0.2s;
        }
        
        .user-menu-button:hover {
            background: #f3f4f6;
        }
        
        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #e5e7eb;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6b7280;
            font-weight: 600;
            overflow: hidden;
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-name {
            font-weight: 500;
            color: #374151;
        }
        
        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            margin-top: 0.5rem;
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
            min-width: 200px;
            display: none;
            z-index: 200;
        }
        
        .dropdown-menu.show {
            display: block;
        }
        
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: #374151;
            text-decoration: none;
            transition: background 0.2s;
        }
        
        .dropdown-item:hover {
            background: #f3f4f6;
        }
        
        .dropdown-divider {
            height: 1px;
            background: #e5e7eb;
            margin: 0.5rem 0;
        }
        
        /* Mobile Menu */
        .mobile-menu-button {
            display: none;
            padding: 0.5rem;
            background: transparent;
            border: none;
            cursor: pointer;
            color: #6b7280;
        }
        
        @media (max-width: 768px) {
            .nav-menu {
                display: none;
            }
            
            .mobile-menu-button {
                display: block;
            }
            
            .header-container {
                padding: 0 1rem;
            }
        }
    </style>
</head>
<body>
    <header class="app-header">
        <div class="header-container">
            <div class="header-left">
                <button class="mobile-menu-button" onclick="toggleMobileMenu()">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                
                <a href="/arbitrator/" class="logo-link">
                    <div class="logo-icon">A</div>
                    <span>Arbitrivm</span>
                </a>
                
                <nav class="nav-menu">
                    <a href="index.php" class="nav-link <?php echo $currentPage === 'index' ? 'active' : ''; ?>">Dashboard</a>
                    <a href="cases.php" class="nav-link <?php echo $currentPage === 'cases' ? 'active' : ''; ?>">Casos</a>
                    <a href="schedule.php" class="nav-link <?php echo $currentPage === 'schedule' ? 'active' : ''; ?>">Agenda</a>
                    <a href="earnings.php" class="nav-link <?php echo $currentPage === 'earnings' ? 'active' : ''; ?>">Honorários</a>
                    <a href="documents.php" class="nav-link <?php echo $currentPage === 'documents' ? 'active' : ''; ?>">Documentos</a>
                </nav>
            </div>
            
            <div class="header-right">
                <a href="notifications.php" class="notification-button">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path d="M10 13a7 7 0 007-7H3a7 7 0 007 7z"/>
                        <path d="M10 16v2"/>
                    </svg>
                    <?php if ($unreadNotifications > 0): ?>
                        <span class="notification-badge"><?php echo $unreadNotifications; ?></span>
                    <?php endif; ?>
                </a>
                
                <div class="user-menu">
                    <button class="user-menu-button" onclick="toggleUserMenu()">
                        <div class="user-avatar">
                            <?php if ($userInfo['photo_url']): ?>
                                <img src="<?php echo htmlspecialchars($userInfo['photo_url']); ?>" alt="Avatar">
                            <?php else: ?>
                                <?php echo strtoupper(substr($userInfo['name'], 0, 1)); ?>
                            <?php endif; ?>
                        </div>
                        <span class="user-name"><?php echo htmlspecialchars($userInfo['name']); ?></span>
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                            <path d="M7.247 11.14L2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z"/>
                        </svg>
                    </button>
                    
                    <div class="dropdown-menu" id="userDropdown">
                        <a href="profile.php" class="dropdown-item">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6zm2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0zm4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4zm-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10c-2.29 0-3.516.68-4.168 1.332-.678.678-.83 1.418-.832 1.664h10z"/>
                            </svg>
                            Meu Perfil
                        </a>
                        <a href="notifications.php" class="dropdown-item">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                <path d="M8 16a2 2 0 0 0 2-2H6a2 2 0 0 0 2 2zm.995-14.901a1 1 0 1 0-1.99 0A5.002 5.002 0 0 0 3 6c0 1.098-.5 6-2 7h14c-1.5-1-2-5.902-2-7 0-2.42-1.72-4.44-4.005-4.901z"/>
                            </svg>
                            Notificações
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="../logout.php" class="dropdown-item">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 12.5a.5.5 0 0 1-.5.5h-8a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5h8a.5.5 0 0 1 .5.5v2a.5.5 0 0 0 1 0v-2A1.5 1.5 0 0 0 9.5 2h-8A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h8a1.5 1.5 0 0 0 1.5-1.5v-2a.5.5 0 0 0-1 0v2z"/>
                                <path fill-rule="evenodd" d="M15.854 8.354a.5.5 0 0 0 0-.708l-3-3a.5.5 0 0 0-.708.708L14.293 7.5H5.5a.5.5 0 0 0 0 1h8.793l-2.147 2.146a.5.5 0 0 0 .708.708l3-3z"/>
                            </svg>
                            Sair
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </header>
    
    <script>
        function toggleUserMenu() {
            const dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('show');
            
            // Fechar ao clicar fora
            document.addEventListener('click', function closeDropdown(e) {
                if (!e.target.closest('.user-menu')) {
                    dropdown.classList.remove('show');
                    document.removeEventListener('click', closeDropdown);
                }
            });
        }
        
        function toggleMobileMenu() {
            // Implementar menu mobile
        }
    </script>
</body>
</html>