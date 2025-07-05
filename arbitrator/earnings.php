<?php
/**
 * arbitrator/earnings.php - Honorários do Árbitro
 */

require_once '../config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

checkUserType(['arbitrator']);

$db = new Database();
$userId = $_SESSION['user_id'];
$arbitratorId = getArbitratorId($userId);

// Filtros
$period = $_GET['period'] ?? 'month';
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');
$status = $_GET['status'] ?? 'all';

// Construir condições de data
$dateConditions = "YEAR(af.created_at) = ?";
$dateParams = [$year];

if ($period === 'month') {
    $dateConditions .= " AND MONTH(af.created_at) = ?";
    $dateParams[] = $month;
} elseif ($period === 'quarter') {
    $quarter = ceil($month / 3);
    $quarterStart = ($quarter - 1) * 3 + 1;
    $quarterEnd = $quarter * 3;
    $dateConditions .= " AND MONTH(af.created_at) BETWEEN ? AND ?";
    $dateParams = [$year, $quarterStart, $quarterEnd];
}

// Buscar resumo financeiro
$summary = $db->fetchOne("
    SELECT 
        COUNT(*) as total_cases,
        SUM(af.total_amount) as gross_total,
        SUM(af.platform_fee) as platform_fees,
        SUM(af.net_amount) as net_total,
        SUM(CASE WHEN af.status = 'paid' THEN af.net_amount ELSE 0 END) as paid_total,
        SUM(CASE WHEN af.status = 'pending' THEN af.net_amount ELSE 0 END) as pending_total,
        SUM(CASE WHEN af.status = 'approved' THEN af.net_amount ELSE 0 END) as approved_total
    FROM arbitrator_fees af
    WHERE af.arbitrator_id = ? AND $dateConditions
", array_merge([$arbitratorId], $dateParams));

// Buscar detalhes dos pagamentos
$query = "
    SELECT 
        af.*,
        d.case_number,
        d.title as case_title,
        d.dispute_amount,
        d.decided_at,
        u1.name as claimant_name,
        u2.name as respondent_name
    FROM arbitrator_fees af
    INNER JOIN disputes d ON af.dispute_id = d.id
    LEFT JOIN users u1 ON d.claimant_id = u1.id
    LEFT JOIN users u2 ON d.respondent_id = u2.id
    WHERE af.arbitrator_id = ? AND $dateConditions
";

$params = array_merge([$arbitratorId], $dateParams);

if ($status !== 'all') {
    $query .= " AND af.status = ?";
    $params[] = $status;
}

$query .= " ORDER BY af.created_at DESC";

$fees = $db->fetchAll($query, $params);

// Buscar evolução mensal (últimos 12 meses)
$evolution = $db->fetchAll("
    SELECT 
        DATE_FORMAT(af.created_at, '%Y-%m') as period,
        COUNT(*) as cases,
        SUM(af.net_amount) as earnings,
        SUM(CASE WHEN af.status = 'paid' THEN af.net_amount ELSE 0 END) as paid_earnings
    FROM arbitrator_fees af
    WHERE af.arbitrator_id = ?
    AND af.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
    GROUP BY DATE_FORMAT(af.created_at, '%Y-%m')
    ORDER BY period
", [$arbitratorId]);

// Estatísticas de performance
$performanceStats = $db->fetchOne("
    SELECT 
        AVG(af.hours_worked) as avg_hours_per_case,
        COUNT(DISTINCT af.dispute_id) as total_completed_cases,
        SUM(af.hours_worked) as total_hours_worked,
        AVG(af.net_amount) as avg_earning_per_case
    FROM arbitrator_fees af
    WHERE af.arbitrator_id = ?
    AND af.status = 'paid'
    AND YEAR(af.created_at) = ?
", [$arbitratorId, $year]);

// Buscar dados bancários do árbitro
$bankInfo = $db->fetchOne("
    SELECT bank_name, bank_agency, bank_account, account_type, pix_key 
    FROM arbitrator_bank_info 
    WHERE arbitrator_id = ?
", [$arbitratorId]);

// Processar solicitação de pagamento
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'request_payment') {
    $feeIds = $_POST['fee_ids'] ?? [];
    
    if (!empty($feeIds)) {
        try {
            $db->beginTransaction();
            
            // Verificar se todos os fees estão aprovados
            $placeholders = str_repeat('?,', count($feeIds) - 1) . '?';
            $approvedFees = $db->fetchAll(
                "SELECT * FROM arbitrator_fees 
                 WHERE id IN ($placeholders) 
                 AND arbitrator_id = ? 
                 AND status = 'approved'",
                array_merge($feeIds, [$arbitratorId])
            );
            
            if (count($approvedFees) !== count($feeIds)) {
                throw new Exception('Alguns honorários selecionados não estão aprovados.');
            }
            
            // Criar solicitação de pagamento
            $totalAmount = array_sum(array_column($approvedFees, 'net_amount'));
            
            $paymentRequestId = $db->insert('payment_requests', [
                'arbitrator_id' => $arbitratorId,
                'amount' => $totalAmount,
                'fee_ids' => json_encode($feeIds),
                'bank_info' => json_encode($bankInfo),
                'status' => 'pending',
                'requested_at' => date('Y-m-d H:i:s')
            ]);
            
            // Atualizar status dos fees
            $db->query(
                "UPDATE arbitrator_fees 
                 SET payment_request_id = ? 
                 WHERE id IN ($placeholders)",
                array_merge([$paymentRequestId], $feeIds)
            );
            
            $db->commit();
            $_SESSION['success'] = 'Solicitação de pagamento enviada com sucesso!';
            
        } catch (Exception $e) {
            $db->rollback();
            $_SESSION['error'] = 'Erro ao solicitar pagamento: ' . $e->getMessage();
        }
        
        redirect('earnings.php');
    }
}

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Honorários - Arbitrivm</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .earnings-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .earnings-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .filter-section {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-bottom: 2rem;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .summary-card {
            background: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }
        
        .summary-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--primary);
        }
        
        .summary-card.success::before {
            background: var(--success);
        }
        
        .summary-card.warning::before {
            background: var(--warning);
        }
        
        .summary-card.info::before {
            background: var(--info);
        }
        
        .summary-label {
            font-size: 0.875rem;
            color: #6b7280;
            margin-bottom: 0.5rem;
        }
        
        .summary-value {
            font-size: 1.875rem;
            font-weight: 700;
            color: #111827;
        }
        
        .summary-detail {
            font-size: 0.75rem;
            color: #9ca3af;
            margin-top: 0.5rem;
        }
        
        .content-sections {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 2rem;
        }
        
        .fees-section {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .fees-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .fees-table th {
            background: #f9fafb;
            padding: 0.75rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
            font-size: 0.875rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .fees-table td {
            padding: 1rem 0.75rem;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .fees-table tr:hover {
            background: #f9fafb;
        }
        
        .case-info {
            font-weight: 500;
            color: #111827;
            margin-bottom: 0.25rem;
        }
        
        .case-parties {
            font-size: 0.75rem;
            color: #6b7280;
        }
        
        .fee-amount {
            text-align: right;
            font-weight: 600;
        }
        
        .fee-status {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        
        .status-approved {
            background: #dbeafe;
            color: #1e40af;
        }
        
        .status-paid {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-disputed {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .chart-section {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .chart-container {
            height: 300px;
            position: relative;
        }
        
        .bank-info-section {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-top: 1rem;
        }
        
        .bank-info-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f3f4f6;
        }
        
        .bank-info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 500;
            color: #6b7280;
        }
        
        .info-value {
            color: #111827;
        }
        
        .action-buttons {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            display: flex;
            gap: 1rem;
        }
        
        .floating-btn {
            padding: 1rem 1.5rem;
            border-radius: 9999px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .checkbox-cell {
            text-align: center;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #6b7280;
        }
        
        .empty-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
            opacity: 0.3;
        }
        
        @media (max-width: 1024px) {
            .content-sections {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .summary-cards {
                grid-template-columns: 1fr;
            }
            
            .fees-table {
                font-size: 0.875rem;
            }
            
            .fees-table th,
            .fees-table td {
                padding: 0.5rem;
            }
            
            .action-buttons {
                position: static;
                margin-top: 2rem;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <?php include 'templates/header.php'; ?>
    
    <div class="earnings-container">
        <div class="earnings-header">
            <h1>Meus Honorários</h1>
            <button class="btn btn-primary" onclick="exportReport()">
                <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                    <path d="M8 10a.5.5 0 0 0 .5-.5V3.707l2.146 2.147a.5.5 0 0 0 .708-.708l-3-3a.5.5 0 0 0-.708 0l-3 3a.5.5 0 1 0 .708.708L7.5 3.707V9.5a.5.5 0 0 0 .5.5z"/>
                    <path d="M2.5 11a.5.5 0 0 0-.5.5v2a1 1 0 0 0 1 1h10a1 1 0 0 0 1-1v-2a.5.5 0 0 0-1 0v2H3v-2a.5.5 0 0 0-.5-.5z"/>
                </svg>
                Exportar Relatório
            </button>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <!-- Filtros -->
        <div class="filter-section">
            <div class="filter-group">
                <label>Período:</label>
                <select id="periodFilter" class="form-control" onchange="updateFilters()">
                    <option value="month" <?php echo $period === 'month' ? 'selected' : ''; ?>>Mensal</option>
                    <option value="quarter" <?php echo $period === 'quarter' ? 'selected' : ''; ?>>Trimestral</option>
                    <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>Anual</option>
                </select>
            </div>
            
            <div class="filter-group">
                <label>Ano:</label>
                <select id="yearFilter" class="form-control" onchange="updateFilters()">
                    <?php for ($y = date('Y'); $y >= date('Y') - 5; $y--): ?>
                        <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <?php if ($period === 'month'): ?>
            <div class="filter-group">
                <label>Mês:</label>
                <select id="monthFilter" class="form-control" onchange="updateFilters()">
                    <?php
                    $months = [
                        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março', 4 => 'Abril',
                        5 => 'Maio', 6 => 'Junho', 7 => 'Julho', 8 => 'Agosto',
                        9 => 'Setembro', 10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro'
                    ];
                    foreach ($months as $m => $name): ?>
                        <option value="<?php echo $m; ?>" <?php echo $month == $m ? 'selected' : ''; ?>>
                            <?php echo $name; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="filter-group">
                <label>Status:</label>
                <select id="statusFilter" class="form-control" onchange="updateFilters()">
                    <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>Todos</option>
                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pendente</option>
                    <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Aprovado</option>
                    <option value="paid" <?php echo $status === 'paid' ? 'selected' : ''; ?>>Pago</option>
                </select>
            </div>
        </div>
        
        <!-- Cards de Resumo -->
        <div class="summary-cards">
            <div class="summary-card">
                <div class="summary-label">Total Bruto</div>
                <div class="summary-value">R$ <?php echo number_format($summary['gross_total'] ?? 0, 2, ',', '.'); ?></div>
                <div class="summary-detail"><?php echo $summary['total_cases'] ?? 0; ?> casos no período</div>
            </div>
            
            <div class="summary-card warning">
                <div class="summary-label">Taxa Administrativa (20%)</div>
                <div class="summary-value">R$ <?php echo number_format($summary['platform_fees'] ?? 0, 2, ',', '.'); ?></div>
                <div class="summary-detail">Retido pela plataforma</div>
            </div>
            
            <div class="summary-card info">
                <div class="summary-label">Total Líquido</div>
                <div class="summary-value">R$ <?php echo number_format($summary['net_total'] ?? 0, 2, ',', '.'); ?></div>
                <div class="summary-detail">Valor a receber</div>
            </div>
            
            <div class="summary-card success">
                <div class="summary-label">Total Pago</div>
                <div class="summary-value">R$ <?php echo number_format($summary['paid_total'] ?? 0, 2, ',', '.'); ?></div>
                <div class="summary-detail">Já creditado</div>
            </div>
        </div>
        
        <div class="content-sections">
            <!-- Lista de Honorários -->
            <div class="fees-section">
                <div class="section-header">
                    <h2>Detalhamento de Honorários</h2>
                    <?php if ($summary['approved_total'] > 0): ?>
                        <button class="btn btn-primary" onclick="requestPayment()">
                            Solicitar Pagamento
                        </button>
                    <?php endif; ?>
                </div>
                
                <?php if (empty($fees)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">💰</div>
                        <p>Nenhum honorário registrado no período selecionado.</p>
                    </div>
                <?php else: ?>
                    <form id="paymentForm" method="POST">
                        <input type="hidden" name="action" value="request_payment">
                        
                        <table class="fees-table">
                            <thead>
                                <tr>
                                    <th width="30">
                                        <input type="checkbox" id="selectAll" onchange="toggleAllCheckboxes()">
                                    </th>
                                    <th>Caso</th>
                                    <th>Tipo</th>
                                    <th>Horas</th>
                                    <th>Valor Bruto</th>
                                    <th>Taxa</th>
                                    <th>Valor Líquido</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fees as $fee): ?>
                                    <tr>
                                        <td class="checkbox-cell">
                                            <?php if ($fee['status'] === 'approved'): ?>
                                                <input type="checkbox" name="fee_ids[]" value="<?php echo $fee['id']; ?>" class="fee-checkbox">
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="case-info">
                                                Caso <?php echo htmlspecialchars($fee['case_number']); ?>
                                            </div>
                                            <div class="case-parties">
                                                <?php echo htmlspecialchars($fee['claimant_name']); ?> vs 
                                                <?php echo htmlspecialchars($fee['respondent_name']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php 
                                            $feeTypes = [
                                                'hourly' => 'Por Hora',
                                                'fixed' => 'Fixo',
                                                'percentage' => 'Percentual'
                                            ];
                                            echo $feeTypes[$fee['fee_type']] ?? $fee['fee_type'];
                                            ?>
                                        </td>
                                        <td><?php echo number_format($fee['hours_worked'] ?? 0, 1); ?>h</td>
                                        <td class="fee-amount">R$ <?php echo number_format($fee['total_amount'], 2, ',', '.'); ?></td>
                                        <td class="fee-amount">R$ <?php echo number_format($fee['platform_fee'], 2, ',', '.'); ?></td>
                                        <td class="fee-amount"><strong>R$ <?php echo number_format($fee['net_amount'], 2, ',', '.'); ?></strong></td>
                                        <td>
                                            <span class="fee-status status-<?php echo $fee['status']; ?>">
                                                <?php 
                                                $statusLabels = [
                                                    'pending' => 'Pendente',
                                                    'approved' => 'Aprovado',
                                                    'paid' => 'Pago',
                                                    'disputed' => 'Contestado'
                                                ];
                                                echo $statusLabels[$fee['status']] ?? $fee['status'];
                                                ?>
                                            </span>
                                            <?php if ($fee['paid_at']): ?>
                                                <br>
                                                <small class="text-muted">
                                                    <?php echo date('d/m/Y', strtotime($fee['paid_at'])); ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </form>
                <?php endif; ?>
            </div>
            
            <!-- Gráfico e Informações Bancárias -->
            <div>
                <!-- Gráfico de Evolução -->
                <div class="chart-section">
                    <h3>Evolução dos Ganhos</h3>
                    <div class="chart-container">
                        <canvas id="earningsChart"></canvas>
                    </div>
                </div>
                
                <!-- Informações Bancárias -->
                <div class="bank-info-section">
                    <h3>Dados Bancários</h3>
                    <?php if ($bankInfo): ?>
                        <div class="bank-info-item">
                            <span class="info-label">Banco:</span>
                            <span class="info-value"><?php echo htmlspecialchars($bankInfo['bank_name'] ?? 'Não informado'); ?></span>
                        </div>
                        <div class="bank-info-item">
                            <span class="info-label">Agência:</span>
                            <span class="info-value"><?php echo htmlspecialchars($bankInfo['bank_agency'] ?? 'Não informado'); ?></span>
                        </div>
                        <div class="bank-info-item">
                            <span class="info-label">Conta:</span>
                            <span class="info-value"><?php echo htmlspecialchars($bankInfo['bank_account'] ?? 'Não informado'); ?></span>
                        </div>
                        <div class="bank-info-item">
                            <span class="info-label">Tipo:</span>
                            <span class="info-value">
                                <?php 
                                $accountTypes = ['checking' => 'Corrente', 'savings' => 'Poupança'];
                                echo $accountTypes[$bankInfo['account_type']] ?? 'Não informado';
                                ?>
                            </span>
                        </div>
                        <?php if ($bankInfo['pix_key']): ?>
                        <div class="bank-info-item">
                            <span class="info-label">Chave PIX:</span>
                            <span class="info-value"><?php echo htmlspecialchars($bankInfo['pix_key']); ?></span>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-muted">Dados bancários não cadastrados.</p>
                    <?php endif; ?>
                    <button class="btn btn-secondary btn-block mt-3" onclick="editBankInfo()">
                        Atualizar Dados Bancários
                    </button>
                </div>
                
                <!-- Estatísticas de Performance -->
                <div class="bank-info-section">
                    <h3>Performance</h3>
                    <div class="bank-info-item">
                        <span class="info-label">Média de horas/caso:</span>
                        <span class="info-value"><?php echo number_format($performanceStats['avg_hours_per_case'] ?? 0, 1); ?>h</span>
                    </div>
                    <div class="bank-info-item">
                        <span class="info-label">Total de horas:</span>
                        <span class="info-value"><?php echo number_format($performanceStats['total_hours_worked'] ?? 0, 1); ?>h</span>
                    </div>
                    <div class="bank-info-item">
                        <span class="info-label">Ganho médio/caso:</span>
                        <span class="info-value">R$ <?php echo number_format($performanceStats['avg_earning_per_case'] ?? 0, 2, ',', '.'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php include 'templates/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Dados para o gráfico
        const evolutionData = <?php echo json_encode($evolution); ?>;
        
        // Configurar gráfico
        const ctx = document.getElementById('earningsChart').getContext('2d');
        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: evolutionData.map(e => {
                    const [year, month] = e.period.split('-');
                    const monthNames = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
                    return monthNames[parseInt(month) - 1] + '/' + year.substr(2);
                }),
                datasets: [{
                    label: 'Total',
                    data: evolutionData.map(e => parseFloat(e.earnings)),
                    borderColor: '#2563eb',
                    backgroundColor: 'rgba(37, 99, 235, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Pago',
                    data: evolutionData.map(e => parseFloat(e.paid_earnings)),
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'R$ ' + value.toLocaleString('pt-BR');
                            }
                        }
                    }
                }
            }
        });
        
        function updateFilters() {
            const period = document.getElementById('periodFilter').value;
            const year = document.getElementById('yearFilter').value;
            const month = document.getElementById('monthFilter')?.value || 1;
            const status = document.getElementById('statusFilter').value;
            
            window.location.href = `earnings.php?period=${period}&year=${year}&month=${month}&status=${status}`;
        }
        
        function toggleAllCheckboxes() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.fee-checkbox');
            
            checkboxes.forEach(cb => {
                cb.checked = selectAll.checked;
            });
        }
        
        function requestPayment() {
            const checkboxes = document.querySelectorAll('.fee-checkbox:checked');
            
            if (checkboxes.length === 0) {
                alert('Selecione pelo menos um honorário aprovado para solicitar pagamento.');
                return;
            }
            
            if (confirm(`Solicitar pagamento de ${checkboxes.length} honorário(s)?`)) {
                document.getElementById('paymentForm').submit();
            }
        }
        
        function exportReport() {
            const params = new URLSearchParams(window.location.search);
            window.open(`export-earnings.php?${params.toString()}`, '_blank');
        }
        
        function editBankInfo() {
            window.location.href = 'profile.php#bank-info';
        }
    </script>
</body>
</html>