<?php
/**
 * Arbitrivm - Dashboard Principal
 * Requer autenticação
 */

require_once 'config.php';

// Verificar se está logado
if (!isLoggedIn()) {
    redirect('login.php');
    exit;
}

$user = getCurrentUser();
$csrfToken = generateCSRF();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Arbitrivm - Dashboard</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    
</head>
<body>
    <!-- Main App -->
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <div class="logo-icon">A</div>
                    <span>Arbitrivm</span>
                </div>
            </div>
            
            <nav class="sidebar-nav">
                <a href="#" class="nav-item active" onclick="navigateTo('dashboard')">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                    </svg>
                    Dashboard
                </a>
                <a href="#" class="nav-item" onclick="navigateTo('disputes')">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                    </svg>
                    Disputas
                </a>
                <a href="#" class="nav-item" onclick="navigateTo('arbitrators')">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                    </svg>
                    Árbitros
                </a>
                <a href="#" class="nav-item" onclick="navigateTo('documents')">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Documentos
                </a>
                <a href="#" class="nav-item" onclick="navigateTo('messages')">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                    Mensagens
                </a>
                <a href="#" class="nav-item" onclick="navigateTo('reports')">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                    </svg>
                    Relatórios
                </a>
                <a href="#" class="nav-item" onclick="navigateTo('settings')">
                    <svg class="nav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    Configurações
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="header">
                <div class="header-content">
                    <button class="mobile-menu-btn" onclick="toggleSidebar()">☰</button>
                    
                    <div class="search-bar">
                        <svg class="search-icon" width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        <input type="text" class="search-input" placeholder="Buscar disputas, documentos, árbitros...">
                    </div>

                    <div class="header-actions">
                        <button class="notification-btn" onclick="loadNotifications()">
                            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                            </svg>
                            <span class="notification-badge hidden" id="notificationCount">0</span>
                        </button>

                        <button class="btn btn-primary" onclick="openModal('newDispute')">
                            <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            Nova Disputa
                        </button>

                        <div class="user-menu" onclick="toggleUserDropdown()">
                            <div class="user-avatar"><?php echo strtoupper(substr($user['first_name'], 0, 1) . substr($user['last_name'], 0, 1)); ?></div>
                            <div>
                                <div class="font-bold"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                                <div class="text-muted" style="font-size: 0.75rem;"><?php echo htmlspecialchars($user['company_name'] ?? 'Usuário Individual'); ?></div>
                            </div>
                            
                            <div class="user-dropdown" id="userDropdown">
                                <a class="dropdown-item" href="#" onclick="navigateTo('settings')">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: inline-block; margin-right: 0.5rem;">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                    Meu Perfil
                                </a>
                                <a class="dropdown-item" href="#" onclick="navigateTo('settings')">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: inline-block; margin-right: 0.5rem;">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    Configurações
                                </a>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item" href="logout.php">
                                    <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display: inline-block; margin-right: 0.5rem;">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                    </svg>
                                    Sair
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Page Content -->
            <div class="page-content" id="pageContent">
                <!-- Dashboard Content -->
                <div id="dashboard-page" class="page-section">
                    <div class="page-header">
                        <h1 class="page-title">Dashboard</h1>
                        <p class="page-subtitle">Visão geral das suas disputas e atividades</p>
                    </div>

                    <!-- Stats -->
                    <div class="stats-grid" id="dashboardStats">
                        <!-- Stats will be loaded dynamically -->
                    </div>

                    <!-- Recent Disputes -->
                    <div class="card mt-4">
                        <div class="card-header flex justify-between items-center">
                            <h2 class="card-title">Disputas Recentes</h2>
                            <button class="btn btn-secondary btn-sm" onclick="navigateTo('disputes')">Ver Todas</button>
                        </div>
                        <div class="card-body">
                            <div class="table-container">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Tipo</th>
                                            <th>Partes</th>
                                            <th>Status</th>
                                            <th>Data</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody id="recentDisputesTable">
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">Carregando...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <!-- Timeline -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h2 class="card-title">Atividade Recente</h2>
                        </div>
                        <div class="card-body">
                            <div class="timeline" id="activityTimeline">
                                <!-- Timeline items will be loaded dynamically -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Other Pages (Hidden by default) -->
                <div id="disputes-page" class="page-section hidden">
                    <div class="page-header">
                        <h1 class="page-title">Disputas</h1>
                        <p class="page-subtitle">Gerencie todas as suas disputas de arbitragem</p>
                    </div>
                    
                    <div class="card">
                        <div class="card-body">
                            <div class="table-container">
                                <table>
                                    <thead>
                                        <tr>
                                            <th>Caso</th>
                                            <th>Tipo</th>
                                            <th>Título</th>
                                            <th>Status</th>
                                            <th>Árbitro</th>
                                            <th>Criado em</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody id="disputesTable">
                                        <tr>
                                            <td colspan="7" class="text-center text-muted">Carregando disputas...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="arbitrators-page" class="page-section hidden">
                    <div class="page-header">
                        <h1 class="page-title">Árbitros</h1>
                        <p class="page-subtitle">Encontre árbitros especializados para suas disputas</p>
                    </div>
                    <!-- Arbitrators content will go here -->
                </div>

                <div id="documents-page" class="page-section hidden">
                    <div class="page-header">
                        <h1 class="page-title">Documentos</h1>
                        <p class="page-subtitle">Central de documentos e arquivos</p>
                    </div>
                    <!-- Documents content will go here -->
                </div>

                <div id="messages-page" class="page-section hidden">
                    <div class="page-header">
                        <h1 class="page-title">Mensagens</h1>
                        <p class="page-subtitle">Comunicação com partes e árbitros</p>
                    </div>
                    <!-- Messages content will go here -->
                </div>

                <div id="reports-page" class="page-section hidden">
                    <div class="page-header">
                        <h1 class="page-title">Relatórios</h1>
                        <p class="page-subtitle">Análises e insights das suas disputas</p>
                    </div>
                    <!-- Reports content will go here -->
                </div>

                <div id="settings-page" class="page-section hidden">
                    <div class="page-header">
                        <h1 class="page-title">Configurações</h1>
                        <p class="page-subtitle">Gerencie suas preferências e conta</p>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h2 class="card-title">Informações Pessoais</h2>
                        </div>
                        <div class="card-body">
                            <form id="profileForm">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <div class="form-group">
                                    <label class="form-label">Nome</label>
                                    <input type="text" class="form-control" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Sobrenome</label>
                                    <input type="text" class="form-control" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>">
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Telefone</label>
                                    <input type="tel" class="form-control" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                                </div>
                                <button type="submit" class="btn btn-primary">Salvar Alterações</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- New Dispute Modal -->
    <div class="modal" id="newDisputeModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Nova Disputa</h2>
                <button class="modal-close" onclick="closeModal('newDispute')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="newDisputeForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    
                    <div class="form-group">
                        <label class="form-label">Tipo de Disputa</label>
                        <select class="form-control form-select" name="dispute_type_id" required>
                            <option value="">Selecione o tipo...</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Título da Disputa</label>
                        <input type="text" class="form-control" name="title" required placeholder="Descreva brevemente o conflito">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Email do Requerido</label>
                        <input type="email" class="form-control" name="respondent_email" required placeholder="email@exemplo.com">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Valor da Causa (opcional)</label>
                        <input type="number" class="form-control" name="claim_amount" step="0.01" placeholder="0.00">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Endereço do Imóvel (opcional)</label>
                        <input type="text" class="form-control" name="property_address" placeholder="Rua, número, cidade...">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Descrição Detalhada</label>
                        <textarea class="form-control" name="description" rows="4" required placeholder="Descreva os detalhes do conflito..."></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Documentos</label>
                        <div class="file-upload" onclick="document.getElementById('disputeFiles').click()">
                            <svg class="file-upload-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                            </svg>
                            <p class="file-upload-text">Clique para fazer upload ou arraste arquivos aqui</p>
                            <p class="text-muted" style="font-size: 0.75rem;">PDF, DOC, DOCX, JPG, PNG (máx. 50MB)</p>
                        </div>
                        <input type="file" id="disputeFiles" multiple style="display: none;" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png">
                        <div class="file-list" id="fileList"></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('newDispute')">Cancelar</button>
                <button class="btn btn-primary" onclick="submitNewDispute()">Criar Disputa</button>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Loading Overlay -->
    <div class="loading-overlay hidden" id="loadingOverlay">
        <div class="spinner" style="width: 50px; height: 50px; border-width: 4px;"></div>
    </div>

    <!-- Scripts -->
    <script>
        // Configuration
        const API_BASE_URL = '<?php echo API_URL; ?>';
        const CSRF_TOKEN = '<?php echo $csrfToken; ?>';
        
        // State management
        let currentUser = <?php echo json_encode($user); ?>;
        let currentPage = 'dashboard';
        let notifications = [];
        let selectedFiles = [];

        // API Service
        async function apiRequest(endpoint, options = {}) {
            try {
                const url = `${API_BASE_URL}${endpoint}`;
                
                const headers = {
                    ...options.headers
                };
                
                // Add CSRF token to non-GET requests
                if (options.method && options.method !== 'GET') {
                    headers['X-CSRF-Token'] = CSRF_TOKEN;
                }
                
                // Add Content-Type if not FormData
                if (!options.body || !(options.body instanceof FormData)) {
                    headers['Content-Type'] = 'application/json';
                }

                const response = await fetch(url, {
                    ...options,
                    credentials: 'include',
                    headers: headers
                });

                const data = await response.json();

                if (!response.ok) {
                    throw new Error(data.message || `API Error: ${response.status}`);
                }

                return data;
            } catch (error) {
                console.error('API Error:', error);
                throw error;
            }
        }

        // Initialize dashboard
        async function init() {
            loadDashboard();
            loadNotifications();
            
            // Auto-refresh notifications
            setInterval(loadNotifications, 30000);
        }

        // Navigation
        function navigateTo(page) {
            // Update active nav item
            document.querySelectorAll('.nav-item').forEach(item => {
                item.classList.remove('active');
            });
            event.target.closest('.nav-item').classList.add('active');

            // Hide all pages
            document.querySelectorAll('.page-section').forEach(section => {
                section.classList.add('hidden');
            });

            // Show selected page
            const pageElement = document.getElementById(`${page}-page`);
            if (pageElement) {
                pageElement.classList.remove('hidden');
            }

            currentPage = page;

            // Load page-specific data
            switch (page) {
                case 'dashboard':
                    loadDashboard();
                    break;
                case 'disputes':
                    loadDisputes();
                    break;
                case 'arbitrators':
                    loadArbitrators();
                    break;
                case 'documents':
                    loadDocuments();
                    break;
                case 'messages':
                    loadMessages();
                    break;
                case 'reports':
                    loadReports();
                    break;
            }

            // Close sidebar on mobile
            if (window.innerWidth <= 768) {
                toggleSidebar();
            }
        }

        // Dashboard functions
        async function loadDashboard() {
            try {
                const statsResponse = await apiRequest('/dashboard.php');
                if (statsResponse.success) {
                    renderDashboardStats(statsResponse.data);
                }

                const disputesResponse = await apiRequest('/disputes.php?limit=5');
                if (disputesResponse.success) {
                    renderRecentDisputes(disputesResponse.data.disputes);
                }

                loadActivityTimeline();
            } catch (error) {
                showToast('Erro ao carregar dashboard', 'error');
            }
        }

        function renderDashboardStats(stats) {
            const statsHtml = `
                <div class="stat-card">
                    <div class="stat-icon primary">
                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                    </div>
                    <div class="stat-value">${stats.active_disputes || 0}</div>
                    <div class="stat-label">Disputas Ativas</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon success">
                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="stat-value">${stats.resolved_disputes || 0}</div>
                    <div class="stat-label">Disputas Resolvidas</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon warning">
                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="stat-value">${stats.avg_resolution_days || 0} dias</div>
                    <div class="stat-label">Tempo Médio de Resolução</div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon danger">
                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="stat-value">${stats.resolution_rate || 0}%</div>
                    <div class="stat-label">Taxa de Resolução</div>
                </div>
            `;
            
            document.getElementById('dashboardStats').innerHTML = statsHtml;
        }

        function renderRecentDisputes(disputes) {
            if (!disputes || disputes.length === 0) {
                document.getElementById('recentDisputesTable').innerHTML = `
                    <tr>
                        <td colspan="6" class="text-center text-muted">Nenhuma disputa encontrada</td>
                    </tr>
                `;
                return;
            }

            const html = disputes.map(dispute => `
                <tr>
                    <td>${dispute.case_number}</td>
                    <td>${dispute.dispute_type_name}</td>
                    <td>${dispute.claimant_name} vs. ${dispute.respondent_name}</td>
                    <td>${getStatusBadge(dispute.status)}</td>
                    <td>${formatDate(dispute.created_at)}</td>
                    <td>
                        <button class="btn btn-secondary btn-sm" onclick="viewDispute('${dispute.id}')">Ver Detalhes</button>
                    </td>
                </tr>
            `).join('');

            document.getElementById('recentDisputesTable').innerHTML = html;
        }

        function loadActivityTimeline() {
            // Simulated timeline data
            const timelineHtml = `
                <div class="timeline-item">
                    <div class="timeline-marker"></div>
                    <div class="timeline-content">
                        <p class="font-bold">Sistema inicializado</p>
                        <p class="text-muted" style="font-size: 0.875rem;">Bem-vindo ao Arbitrivm</p>
                    </div>
                </div>
            `;
            
            document.getElementById('activityTimeline').innerHTML = timelineHtml;
        }

        // Disputes functions
        async function loadDisputes() {
            try {
                showLoading();
                const response = await apiRequest('/disputes.php');
                
                if (response.success) {
                    renderDisputesTable(response.data.disputes);
                }
            } catch (error) {
                showToast('Erro ao carregar disputas', 'error');
            } finally {
                hideLoading();
            }
        }

        function renderDisputesTable(disputes) {
            if (!disputes || disputes.length === 0) {
                document.getElementById('disputesTable').innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center text-muted">Nenhuma disputa encontrada</td>
                    </tr>
                `;
                return;
            }

            const html = disputes.map(dispute => `
                <tr>
                    <td>${dispute.case_number}</td>
                    <td>${dispute.dispute_type_name}</td>
                    <td>${dispute.title}</td>
                    <td>${getStatusBadge(dispute.status)}</td>
                    <td>${dispute.arbitrator_name || '-'}</td>
                    <td>${formatDate(dispute.created_at)}</td>
                    <td>
                        <button class="btn btn-primary btn-sm" onclick="viewDispute('${dispute.id}')">Detalhes</button>
                    </td>
                </tr>
            `).join('');

            document.getElementById('disputesTable').innerHTML = html;
        }

        // Modal functions
        async function openModal(modalType) {
            const modal = document.getElementById(`${modalType}Modal`);
            if (modal) {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
                
                if (modalType === 'newDispute') {
                    await loadDisputeTypes();
                }
            }
        }

        function closeModal(modalType) {
            const modal = document.getElementById(`${modalType}Modal`);
            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = '';
                
                if (modalType === 'newDispute') {
                    document.getElementById('newDisputeForm').reset();
                    selectedFiles = [];
                    document.getElementById('fileList').innerHTML = '';
                }
            }
        }

        async function loadDisputeTypes() {
            try {
                const response = await apiRequest('/dispute-types.php');
                
                if (response.success) {
                    const select = document.querySelector('select[name="dispute_type_id"]');
                    select.innerHTML = '<option value="">Selecione o tipo...</option>' + 
                        response.data.map(type => `<option value="${type.id}">${type.name}</option>`).join('');
                }
            } catch (error) {
                console.error('Erro ao carregar tipos de disputa:', error);
            }
        }

        async function submitNewDispute() {
            const form = document.getElementById('newDisputeForm');
            const formData = new FormData(form);
            const data = Object.fromEntries(formData);
            
            try {
                showLoading();
                
                const response = await apiRequest('/disputes.php', {
                    method: 'POST',
                    body: JSON.stringify(data)
                });
                
                if (response.success) {
                    const disputeId = response.data.id;
                    
                    // Upload files if any
                    if (selectedFiles.length > 0) {
                        for (const file of selectedFiles) {
                            const uploadData = new FormData();
                            uploadData.append('document', file);
                            uploadData.append('dispute_id', disputeId);
                            
                            await apiRequest('/documents.php', {
                                method: 'POST',
                                body: uploadData
                            });
                        }
                    }
                    
                    showToast('Disputa criada com sucesso!', 'success');
                    closeModal('newDispute');
                    
                    if (currentPage === 'disputes') {
                        loadDisputes();
                    } else if (currentPage === 'dashboard') {
                        loadDashboard();
                    }
                }
            } catch (error) {
                showToast(error.message || 'Erro ao criar disputa', 'error');
            } finally {
                hideLoading();
            }
        }

        // File handling
        document.getElementById('disputeFiles').addEventListener('change', (e) => {
            const files = Array.from(e.target.files);
            selectedFiles = [...selectedFiles, ...files];
            renderFileList();
        });

        function renderFileList() {
            const fileListHtml = selectedFiles.map((file, index) => `
                <div class="file-item">
                    <div class="flex items-center gap-1">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <div>
                            <div style="font-size: 0.875rem;">${file.name}</div>
                            <div class="text-muted" style="font-size: 0.75rem;">${(file.size / 1024 / 1024).toFixed(2)} MB</div>
                        </div>
                    </div>
                    <button class="btn btn-sm" onclick="removeFile(${index})" style="background: #fee2e2; color: #991b1b;">Remover</button>
                </div>
            `).join('');
            
            document.getElementById('fileList').innerHTML = fileListHtml;
        }

        function removeFile(index) {
            selectedFiles.splice(index, 1);
            renderFileList();
        }

        // Notifications
        async function loadNotifications() {
            try {
                const response = await apiRequest('/notifications.php');
                
                if (response.success) {
                    notifications = response.data;
                    updateNotificationBadge();
                }
            } catch (error) {
                console.error('Erro ao carregar notificações:', error);
            }
        }

        function updateNotificationBadge() {
            const unreadCount = notifications.filter(n => !n.is_read).length;
            const badge = document.getElementById('notificationCount');
            
            if (unreadCount > 0) {
                badge.textContent = unreadCount;
                badge.classList.remove('hidden');
            } else {
                badge.classList.add('hidden');
            }
        }

        // User dropdown
        function toggleUserDropdown() {
            const dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('active');
        }

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.user-menu')) {
                document.getElementById('userDropdown').classList.remove('active');
            }
        });

        // Sidebar toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('active');
        }

        // Helper functions
        function getStatusBadge(status) {
            const statusMap = {
                'draft': { class: 'badge-gray', text: 'Rascunho' },
                'pending_arbitrator': { class: 'badge-warning', text: 'Aguardando Árbitro' },
                'pending_acceptance': { class: 'badge-warning', text: 'Aguardando Aceitação' },
                'active': { class: 'badge-primary', text: 'Em Andamento' },
                'on_hold': { class: 'badge-warning', text: 'Em Espera' },
                'resolved': { class: 'badge-success', text: 'Resolvida' },
                'cancelled': { class: 'badge-danger', text: 'Cancelada' }
            };
            
            const statusInfo = statusMap[status] || { class: 'badge-gray', text: status };
            return `<span class="badge ${statusInfo.class}">${statusInfo.text}</span>`;
        }

        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('pt-BR');
        }

        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast ${type}`;
            
            const icon = type === 'success' ? '✓' : type === 'error' ? '✕' : 'ℹ';
            
            toast.innerHTML = `
                <span style="font-size: 1.25rem;">${icon}</span>
                <span>${message}</span>
                <button onclick="this.parentElement.remove()" style="background: none; border: none; cursor: pointer; font-size: 1.25rem; color: var(--gray);">×</button>
            `;
            
            document.getElementById('toastContainer').appendChild(toast);
            
            setTimeout(() => {
                toast.remove();
            }, 5000);
        }

        function showLoading() {
            document.getElementById('loadingOverlay').classList.remove('hidden');
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').classList.add('hidden');
        }

        // Other page loaders
        async function loadArbitrators() {
            showToast('Carregando árbitros...', 'info');
        }

        async function loadDocuments() {
            showToast('Carregando documentos...', 'info');
        }

        async function loadMessages() {
            showToast('Carregando mensagens...', 'info');
        }

        async function loadReports() {
            showToast('Carregando relatórios...', 'info');
        }

        function viewDispute(disputeId) {
            showToast(`Visualizando disputa ${disputeId}`, 'info');
        }

        // Close modals on outside click
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    modal.classList.remove('active');
                    document.body.style.overflow = '';
                }
            });
        });

        // Handle window resize
        window.addEventListener('resize', () => {
            if (window.innerWidth > 768) {
                document.getElementById('sidebar').classList.remove('active');
            }
        });

        // Initialize app on load
        document.addEventListener('DOMContentLoaded', init);
    </script>
</body>
</html>