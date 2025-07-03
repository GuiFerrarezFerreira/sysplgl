<?php
/**
 * Arbitrivm - Lista de Árbitros
 */

require_once 'config.php';

// Verificar se está logado
if (!isLoggedIn()) {
    redirect('login.php');
    exit;
}

$user = getCurrentUser();
$db = new Database();

// Buscar árbitros
$arbitrators = $db->fetchAll(
    "SELECT a.*, u.email, CONCAT(u.first_name, ' ', u.last_name) as name,
            (SELECT COUNT(*) FROM disputes WHERE arbitrator_id = a.id AND status = 'active') as active_cases
     FROM arbitrators a
     JOIN users u ON a.user_id = u.id
     WHERE a.documents_verified = 1
     ORDER BY a.rating DESC, a.cases_resolved DESC"
);

$csrfToken = generateCSRF();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Árbitros - Arbitrivm</title>
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* Reutilizar CSS base */
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
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 1.875rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: var(--gray);
            font-size: 0.875rem;
        }

        .filters {
            background: var(--white);
            padding: 1.5rem;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 2rem;
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: end;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
            font-size: 0.875rem;
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

        .form-select {
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.5rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            padding-right: 2.5rem;
        }

        .arbitrators-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
        }

        .arbitrator-card {
            background: var(--white);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            overflow: hidden;
            transition: var(--transition);
        }

        .arbitrator-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .arbitrator-header {
            padding: 1.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .arbitrator-name {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .arbitrator-registration {
            font-size: 0.875rem;
            opacity: 0.9;
        }

        .arbitrator-body {
            padding: 1.5rem;
        }

        .arbitrator-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .stat {
            text-align: center;
            padding: 0.75rem;
            background: var(--light);
            border-radius: var(--radius);
        }

        .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary);
        }

        .stat-label {
            font-size: 0.75rem;
            color: var(--gray);
            text-transform: uppercase;
        }

        .rating {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .stars {
            display: flex;
            gap: 0.125rem;
        }

        .star {
            width: 16px;
            height: 16px;
            fill: #fbbf24;
        }

        .star.empty {
            fill: #e5e7eb;
        }

        .rating-text {
            font-size: 0.875rem;
            color: var(--gray);
        }

        .specializations {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .specialization-tag {
            background: var(--light);
            color: var(--dark);
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
        }

        .arbitrator-bio {
            font-size: 0.875rem;
            color: var(--gray);
            line-height: 1.5;
            margin-bottom: 1rem;
        }

        .arbitrator-footer {
            padding: 1rem 1.5rem;
            background: var(--light);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .availability {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.875rem;
        }

        .availability-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--secondary);
        }

        .availability-dot.busy {
            background: var(--warning);
        }

        .availability-dot.unavailable {
            background: var(--danger);
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

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--gray);
        }

        .empty-state-icon {
            width: 64px;
            height: 64px;
            margin: 0 auto 1rem;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .container {
                padding: 1rem;
            }

            .filters {
                flex-direction: column;
            }

            .filter-group {
                width: 100%;
            }

            .arbitrators-grid {
                grid-template-columns: 1fr;
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
            <span>Árbitros</span>
        </nav>

        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Árbitros</h1>
            <p class="page-subtitle">Encontre árbitros especializados para suas disputas</p>
        </div>

        <!-- Filters -->
        <div class="filters">
            <div class="filter-group">
                <label class="filter-label">Especialização</label>
                <select class="form-control form-select" id="filterSpecialization">
                    <option value="">Todas as especializações</option>
                    <option value="locacao">Locação</option>
                    <option value="compra-venda">Compra e Venda</option>
                    <option value="condominio">Condomínio</option>
                    <option value="construcao">Construção</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Disponibilidade</label>
                <select class="form-control form-select" id="filterAvailability">
                    <option value="">Todos</option>
                    <option value="available">Disponível</option>
                    <option value="busy">Ocupado</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label class="filter-label">Ordenar por</label>
                <select class="form-control form-select" id="sortBy">
                    <option value="rating">Melhor avaliação</option>
                    <option value="cases">Mais casos resolvidos</option>
                    <option value="time">Tempo de resolução</option>
                    <option value="price">Menor custo</option>
                </select>
            </div>
            
            <button class="btn btn-primary" onclick="applyFilters()">Filtrar</button>
        </div>

        <!-- Arbitrators Grid -->
        <?php if (empty($arbitrators)): ?>
        <div class="empty-state">
            <svg class="empty-state-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
            </svg>
            <h3>Nenhum árbitro encontrado</h3>
            <p>Tente ajustar os filtros para encontrar árbitros disponíveis.</p>
        </div>
        <?php else: ?>
        <div class="arbitrators-grid">
            <?php foreach ($arbitrators as $arbitrator): ?>
            <div class="arbitrator-card">
                <div class="arbitrator-header">
                    <div class="arbitrator-name"><?php echo htmlspecialchars($arbitrator['name']); ?></div>
                    <div class="arbitrator-registration">Registro: <?php echo htmlspecialchars($arbitrator['registration_number']); ?></div>
                </div>
                
                <div class="arbitrator-body">
                    <div class="arbitrator-stats">
                        <div class="stat">
                            <div class="stat-value"><?php echo $arbitrator['cases_resolved']; ?></div>
                            <div class="stat-label">Casos Resolvidos</div>
                        </div>
                        <div class="stat">
                            <div class="stat-value"><?php echo $arbitrator['average_resolution_days'] ?? 0; ?>d</div>
                            <div class="stat-label">Tempo Médio</div>
                        </div>
                    </div>
                    
                    <div class="rating">
                        <div class="stars">
                            <?php
                            $rating = floatval($arbitrator['rating']);
                            for ($i = 1; $i <= 5; $i++) {
                                $filled = $i <= $rating ? '' : 'empty';
                                echo '<svg class="star ' . $filled . '" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                      </svg>';
                            }
                            ?>
                        </div>
                        <span class="rating-text">
                            <?php echo number_format($rating, 1); ?> 
                            (<?php echo $arbitrator['total_reviews']; ?> avaliações)
                        </span>
                    </div>
                    
                    <?php if ($arbitrator['specializations']): ?>
                    <div class="specializations">
                        <?php
                        $specs = json_decode($arbitrator['specializations'], true) ?? [];
                        foreach ($specs as $spec): 
                        ?>
                        <span class="specialization-tag"><?php echo htmlspecialchars($spec); ?></span>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($arbitrator['bio']): ?>
                    <p class="arbitrator-bio">
                        <?php echo htmlspecialchars(substr($arbitrator['bio'], 0, 150)); ?>...
                    </p>
                    <?php endif; ?>
                </div>
                
                <div class="arbitrator-footer">
                    <div class="availability">
                        <?php
                        $activeCases = intval($arbitrator['active_cases']);
                        if (!$arbitrator['is_available']) {
                            echo '<span class="availability-dot unavailable"></span>Indisponível';
                        } elseif ($activeCases > 5) {
                            echo '<span class="availability-dot busy"></span>Ocupado';
                        } else {
                            echo '<span class="availability-dot"></span>Disponível';
                        }
                        ?>
                    </div>
                    
                    <a href="arbitrator-profile.php?id=<?php echo $arbitrator['id']; ?>" class="btn btn-primary">
                        Ver Perfil
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <script>
        function applyFilters() {
            // Implementar lógica de filtros
            const specialization = document.getElementById('filterSpecialization').value;
            const availability = document.getElementById('filterAvailability').value;
            const sortBy = document.getElementById('sortBy').value;
            
            // Por enquanto, apenas recarregar
            location.reload();
        }
    </script>
</body>
</html>