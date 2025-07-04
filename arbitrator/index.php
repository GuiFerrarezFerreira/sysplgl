<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard do Árbitro - Arbitrivm</title>
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
            --black: #000000;
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

        /* Layout */
        .app-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 250px;
            background: var(--white);
            border-right: 1px solid var(--gray-200);
            display: flex;
            flex-direction: column;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--primary);
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        .sidebar-nav {
            flex: 1;
            padding: 1rem 0;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1.5rem;
            color: var(--gray-700);
            text-decoration: none;
            transition: all 0.2s;
            position: relative;
        }

        .nav-item:hover {
            background-color: var(--gray-50);
            color: var(--primary);
        }

        .nav-item.active {
            background-color: var(--primary);
            color: white;
        }

        .nav-item.active::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
            background-color: var(--primary-dark);
        }

        /* Main Content */
        .main-content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        /* Header */
        .header {
            background: var(--white);
            padding: 1rem 2rem;
            border-bottom: 1px solid var(--gray-200);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-900);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .notification-btn {
            position: relative;
            padding: 0.5rem;
            border-radius: 0.5rem;
            background: transparent;
            border: none;
            cursor: pointer;
            color: var(--gray-600);
        }

        .notification-btn:hover {
            background-color: var(--gray-100);
        }

        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            width: 8px;
            height: 8px;
            background-color: var(--danger);
            border-radius: 50%;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: background-color 0.2s;
        }

        .user-menu:hover {
            background-color: var(--gray-100);
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        /* Content Area */
        .content {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
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

        .stat-change.negative {
            color: var(--danger);
        }

        /* Cases Section */
        .section {
            background: var(--white);
            border-radius: 0.75rem;
            border: 1px solid var(--gray-200);
            margin-bottom: 2rem;
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

        /* Tabs */
        .tabs {
            display: flex;
            gap: 2rem;
            padding: 0 1.5rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .tab {
            padding: 1rem 0;
            color: var(--gray-600);
            text-decoration: none;
            font-weight: 500;
            position: relative;
            transition: color 0.2s;
        }

        .tab:hover {
            color: var(--primary);
        }

        .tab.active {
            color: var(--primary);
        }

        .tab.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 2px;
            background-color: var(--primary);
        }

        /* Cases List */
        .cases-list {
            padding: 1.5rem;
        }

        .case-item {
            padding: 1.5rem;
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
            margin-bottom: 1rem;
        }

        .case-id {
            font-size: 0.875rem;
            color: var(--gray-500);
            margin-bottom: 0.25rem;
        }

        .case-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
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

        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-in-progress {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .status-completed {
            background-color: #d1fae5;
            color: #065f46;
        }

        .case-details {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }

        .case-detail {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .detail-label {
            font-size: 0.75rem;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .detail-value {
            font-size: 0.875rem;
            color: var(--gray-900);
            font-weight: 500;
        }

        /* Buttons */
        .btn {
            padding: 0.625rem 1.25rem;
            border-radius: 0.5rem;
            font-weight: 500;
            font-size: 0.875rem;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
            display: inline-flex;
            align-items: center;
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
            background-color: var(--gray-100);
            color: var(--gray-700);
        }

        .btn-secondary:hover {
            background-color: var(--gray-200);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem;
        }

        .empty-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 1rem;
            background-color: var(--gray-100);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-400);
            font-size: 1.5rem;
        }

        .empty-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-900);
            margin-bottom: 0.5rem;
        }

        .empty-text {
            color: var(--gray-600);
            margin-bottom: 1.5rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: -250px;
                top: 0;
                bottom: 0;
                z-index: 1000;
                transition: left 0.3s;
            }

            .sidebar.open {
                left: 0;
            }

            .main-content {
                margin-left: 0;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            .case-details {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
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
                <a href="#" class="nav-item active">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="3" width="7" height="7" rx="1"/>
                        <rect x="3" y="12" width="7" height="5" rx="1"/>
                        <rect x="12" y="3" width="5" height="14" rx="1"/>
                    </svg>
                    Dashboard
                </a>
                <a href="#" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M3 7l7-4l7 4v10l-7 4l-7-4V7z"/>
                        <path d="M10 3v7m0 0l7 4m-7-4l-7 4"/>
                    </svg>
                    Meus Casos
                </a>
                <a href="#" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="14" height="12" rx="2"/>
                        <path d="M8 2v4m4-4v4"/>
                    </svg>
                    Agenda
                </a>
                <a href="#" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 2a4 4 0 100 8 4 4 0 000-8zM3 18v-2a4 4 0 014-4h4a4 4 0 014 4v2"/>
                    </svg>
                    Perfil
                </a>
                <a href="#" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                    </svg>
                    Honorários
                </a>
                <a href="#" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 12h2m-2-4h2m-6 8h10a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                    </svg>
                    Documentos
                </a>
                <a href="#" class="nav-item">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="10" cy="10" r="3"/>
                        <path d="M10 1v6m0 6v6m9-9h-6m-6 0H1"/>
                    </svg>
                    Configurações
                </a>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Header -->
            <header class="header">
                <h1 class="header-title">Dashboard do Árbitro</h1>
                <div class="header-actions">
                    <button class="notification-btn">
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path d="M10 13a7 7 0 007-7H3a7 7 0 007 7z"/>
                            <path d="M10 16v2"/>
                        </svg>
                        <span class="notification-badge"></span>
                    </button>
                    <div class="user-menu">
                        <div class="user-avatar">JA</div>
                        <div>
                            <div style="font-weight: 600;">João Árbitro</div>
                            <div style="font-size: 0.75rem; color: var(--gray-500);">Árbitro Sênior</div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Content -->
            <div class="content">
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
                        <div class="stat-value">8</div>
                        <div class="stat-change positive">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M8 12V4m0 0L4 8m4-4l4 4"/>
                            </svg>
                            +2 este mês
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
                        <div class="stat-value">45</div>
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
                        <div class="stat-value">15/01</div>
                        <div class="stat-change">às 14:00h</div>
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
                        <div class="stat-value">94%</div>
                        <div class="stat-change">de acordos realizados</div>
                    </div>
                </div>

                <!-- Cases Section -->
                <div class="section">
                    <div class="section-header">
                        <h2 class="section-title">Casos Recentes</h2>
                        <button class="btn btn-primary">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M8 4v8m-4-4h8"/>
                            </svg>
                            Ver Todos
                        </button>
                    </div>

                    <!-- Tabs -->
                    <div class="tabs">
                        <a href="#" class="tab active">Em Andamento</a>
                        <a href="#" class="tab">Aguardando</a>
                        <a href="#" class="tab">Concluídos</a>
                    </div>

                    <!-- Cases List -->
                    <div class="cases-list">
                        <div class="case-item">
                            <div class="case-header">
                                <div>
                                    <div class="case-id">ARB-2025-001</div>
                                    <h3 class="case-title">Disputa sobre Vícios Construtivos</h3>
                                    <p class="case-parties">Silva Construções vs. João Pedro Santos</p>
                                </div>
                                <span class="case-status status-in-progress">Em Análise</span>
                            </div>
                            <div class="case-details">
                                <div class="case-detail">
                                    <span class="detail-label">Valor da Causa</span>
                                    <span class="detail-value">R$ 150.000,00</span>
                                </div>
                                <div class="case-detail">
                                    <span class="detail-label">Próxima Audiência</span>
                                    <span class="detail-value">15/01/2025 às 14:00</span>
                                </div>
                                <div class="case-detail">
                                    <span class="detail-label">Prazo para Sentença</span>
                                    <span class="detail-value">30/01/2025</span>
                                </div>
                            </div>
                        </div>

                        <div class="case-item">
                            <div class="case-header">
                                <div>
                                    <div class="case-id">ARB-2025-002</div>
                                    <h3 class="case-title">Rescisão Contratual - Locação Comercial</h3>
                                    <p class="case-parties">Shopping Center XYZ vs. Loja ABC</p>
                                </div>
                                <span class="case-status status-pending">Aguardando Documentos</span>
                            </div>
                            <div class="case-details">
                                <div class="case-detail">
                                    <span class="detail-label">Valor da Causa</span>
                                    <span class="detail-value">R$ 280.000,00</span>
                                </div>
                                <div class="case-detail">
                                    <span class="detail-label">Documentos Pendentes</span>
                                    <span class="detail-value">3 arquivos</span>
                                </div>
                                <div class="case-detail">
                                    <span class="detail-label">Prazo</span>
                                    <span class="detail-value">20/01/2025</span>
                                </div>
                            </div>
                        </div>

                        <div class="case-item">
                            <div class="case-header">
                                <div>
                                    <div class="case-id">ARB-2024-087</div>
                                    <h3 class="case-title">Cobrança de Aluguéis Atrasados</h3>
                                    <p class="case-parties">Imobiliária Central vs. Maria Silva</p>
                                </div>
                                <span class="case-status status-completed">Concluído</span>
                            </div>
                            <div class="case-details">
                                <div class="case-detail">
                                    <span class="detail-label">Valor Acordado</span>
                                    <span class="detail-value">R$ 45.000,00</span>
                                </div>
                                <div class="case-detail">
                                    <span class="detail-label">Data de Conclusão</span>
                                    <span class="detail-value">28/12/2024</span>
                                </div>
                                <div class="case-detail">
                                    <span class="detail-label">Resultado</span>
                                    <span class="detail-value">Acordo Homologado</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Funcionalidade básica do menu
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
                this.classList.add('active');
            });
        });

        // Funcionalidade das tabs
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function(e) {
                e.preventDefault();
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
            });
        });

        // Toggle sidebar no mobile
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
        }
    </script>
</body>
</html>