<?php
/**
 * arbitrator/schedule.php - Agenda do Árbitro
 */

require_once '../config.php';
require_once '../includes/auth.php';
require_once '../includes/database.php';
require_once '../includes/functions.php';

checkUserType(['arbitrator']);

$db = new Database();
$userId = $_SESSION['user_id'];
$arbitratorId = getArbitratorId($userId);

// Processar ações
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'update_availability':
            try {
                $db->beginTransaction();
                
                // Limpar disponibilidade anterior
                $db->delete('arbitrator_availability', 'arbitrator_id = ?', [$arbitratorId]);
                
                // Inserir nova disponibilidade
                $days = $_POST['days'] ?? [];
                foreach ($days as $day => $times) {
                    if (isset($times['enabled']) && $times['enabled'] == '1') {
                        $db->insert('arbitrator_availability', [
                            'arbitrator_id' => $arbitratorId,
                            'day_of_week' => $day,
                            'start_time' => $times['start'],
                            'end_time' => $times['end'],
                            'is_available' => 1
                        ]);
                    }
                }
                
                // Atualizar horas semanais
                $weeklyHours = $_POST['weekly_hours'] ?? 20;
                $db->update('arbitrators', 
                    ['availability_hours' => $weeklyHours],
                    'id = ?',
                    [$arbitratorId]
                );
                
                $db->commit();
                $_SESSION['success'] = 'Disponibilidade atualizada com sucesso!';
                
            } catch (Exception $e) {
                $db->rollback();
                $_SESSION['error'] = 'Erro ao atualizar disponibilidade.';
                logError('Erro ao atualizar disponibilidade: ' . $e->getMessage());
            }
            redirect('schedule.php');
            break;
            
        case 'reschedule_hearing':
            $hearingId = $_POST['hearing_id'] ?? 0;
            $newDate = $_POST['new_date'] ?? '';
            $newTime = $_POST['new_time'] ?? '';
            $reason = $_POST['reason'] ?? '';
            
            try {
                // Verificar se o árbitro tem acesso
                $hearing = $db->fetchOne(
                    "SELECT h.*, d.case_number 
                     FROM dispute_hearings h
                     INNER JOIN disputes d ON h.dispute_id = d.id
                     INNER JOIN arbitrator_cases ac ON d.id = ac.dispute_id
                     WHERE h.id = ? AND ac.arbitrator_id = ?",
                    [$hearingId, $arbitratorId]
                );
                
                if (!$hearing) {
                    throw new Exception('Audiência não encontrada');
                }
                
                // Atualizar audiência
                $db->update('dispute_hearings', [
                    'date' => $newDate,
                    'time' => $newTime,
                    'rescheduled_reason' => $reason,
                    'rescheduled_by' => $userId,
                    'rescheduled_at' => date('Y-m-d H:i:s')
                ], 'id = ?', [$hearingId]);
                
                // Notificar partes
                createNotification($hearing['dispute_id'], 'hearing_rescheduled', [
                    'old_date' => $hearing['date'],
                    'old_time' => $hearing['time'],
                    'new_date' => $newDate,
                    'new_time' => $newTime,
                    'reason' => $reason
                ]);
                
                $_SESSION['success'] = 'Audiência reagendada com sucesso!';
                
            } catch (Exception $e) {
                $_SESSION['error'] = 'Erro ao reagendar audiência: ' . $e->getMessage();
            }
            redirect('schedule.php');
            break;
            
        case 'cancel_hearing':
            $hearingId = $_POST['hearing_id'] ?? 0;
            $reason = $_POST['cancel_reason'] ?? '';
            
            try {
                // Verificar acesso e cancelar
                $hearing = $db->fetchOne(
                    "SELECT h.*, d.case_number 
                     FROM dispute_hearings h
                     INNER JOIN disputes d ON h.dispute_id = d.id
                     INNER JOIN arbitrator_cases ac ON d.id = ac.dispute_id
                     WHERE h.id = ? AND ac.arbitrator_id = ?",
                    [$hearingId, $arbitratorId]
                );
                
                if (!$hearing) {
                    throw new Exception('Audiência não encontrada');
                }
                
                $db->update('dispute_hearings', [
                    'status' => 'cancelled',
                    'cancelled_reason' => $reason,
                    'cancelled_by' => $userId,
                    'cancelled_at' => date('Y-m-d H:i:s')
                ], 'id = ?', [$hearingId]);
                
                // Notificar partes
                createNotification($hearing['dispute_id'], 'hearing_cancelled', [
                    'date' => $hearing['date'],
                    'time' => $hearing['time'],
                    'reason' => $reason
                ]);
                
                $_SESSION['success'] = 'Audiência cancelada.';
                
            } catch (Exception $e) {
                $_SESSION['error'] = 'Erro ao cancelar audiência.';
            }
            redirect('schedule.php');
            break;
    }
}

// Buscar disponibilidade atual
$availability = $db->fetchAll(
    "SELECT * FROM arbitrator_availability WHERE arbitrator_id = ? ORDER BY day_of_week",
    [$arbitratorId]
);

// Converter para array indexado por dia
$availabilityByDay = [];
foreach ($availability as $slot) {
    $availabilityByDay[$slot['day_of_week']] = $slot;
}

// Buscar audiências do mês
$currentMonth = $_GET['month'] ?? date('m');
$currentYear = $_GET['year'] ?? date('Y');
$startDate = "$currentYear-$currentMonth-01";
$endDate = date('Y-m-t', strtotime($startDate));

$hearings = $db->fetchAll("
    SELECT 
        h.*,
        d.case_number,
        d.title as case_title,
        u1.name as claimant_name,
        u2.name as respondent_name
    FROM dispute_hearings h
    INNER JOIN disputes d ON h.dispute_id = d.id
    INNER JOIN arbitrator_cases ac ON d.id = ac.dispute_id
    LEFT JOIN users u1 ON d.claimant_id = u1.id
    LEFT JOIN users u2 ON d.respondent_id = u2.id
    WHERE ac.arbitrator_id = ?
    AND h.date BETWEEN ? AND ?
    AND h.status IN ('scheduled', 'confirmed')
    ORDER BY h.date, h.time
", [$arbitratorId, $startDate, $endDate]);

// Organizar audiências por data
$hearingsByDate = [];
foreach ($hearings as $hearing) {
    $hearingsByDate[$hearing['date']][] = $hearing;
}

// Buscar prazos importantes
$deadlines = $db->fetchAll("
    SELECT 
        d.id,
        d.case_number,
        d.title,
        d.deadline_decision,
        d.deadline_analysis,
        'decision' as deadline_type
    FROM disputes d
    INNER JOIN arbitrator_cases ac ON d.id = ac.dispute_id
    WHERE ac.arbitrator_id = ?
    AND ac.status = 'accepted'
    AND d.status IN ('in_analysis', 'hearing_scheduled')
    AND (
        (d.deadline_decision BETWEEN ? AND ?)
        OR (d.deadline_analysis BETWEEN ? AND ?)
    )
", [$arbitratorId, $startDate, $endDate, $startDate, $endDate]);

// Estatísticas do mês
$monthStats = $db->fetchOne("
    SELECT 
        COUNT(DISTINCT h.id) as total_hearings,
        COUNT(DISTINCT CASE WHEN h.type = 'online' THEN h.id END) as online_hearings,
        COUNT(DISTINCT CASE WHEN h.type = 'presencial' THEN h.id END) as presencial_hearings,
        COUNT(DISTINCT d.id) as active_cases
    FROM dispute_hearings h
    INNER JOIN disputes d ON h.dispute_id = d.id
    INNER JOIN arbitrator_cases ac ON d.id = ac.dispute_id
    WHERE ac.arbitrator_id = ?
    AND h.date BETWEEN ? AND ?
    AND h.status IN ('scheduled', 'confirmed')
", [$arbitratorId, $startDate, $endDate]);

// Dias da semana
$daysOfWeek = [
    0 => 'Domingo',
    1 => 'Segunda-feira',
    2 => 'Terça-feira',
    3 => 'Quarta-feira',
    4 => 'Quinta-feira',
    5 => 'Sexta-feira',
    6 => 'Sábado'
];

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agenda - Arbitrivm</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .schedule-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .schedule-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }
        
        .month-navigation {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .month-navigation button {
            background: none;
            border: 1px solid #e5e7eb;
            padding: 0.5rem;
            border-radius: 0.375rem;
            cursor: pointer;
        }
        
        .month-navigation button:hover {
            background: #f3f4f6;
        }
        
        .stats-row {
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
            color: #2563eb;
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
        
        .calendar-section {
            background: white;
            border-radius: 0.75rem;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .availability-section {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            height: fit-content;
        }
        
        .calendar {
            width: 100%;
            border-collapse: collapse;
        }
        
        .calendar th {
            background: #f9fafb;
            padding: 0.75rem;
            text-align: center;
            font-weight: 600;
            color: #374151;
            border: 1px solid #e5e7eb;
        }
        
        .calendar td {
            border: 1px solid #e5e7eb;
            padding: 0.5rem;
            vertical-align: top;
            height: 100px;
            position: relative;
        }
        
        .calendar-day {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        
        .calendar-day.other-month {
            color: #d1d5db;
        }
        
        .calendar-day.today {
            background: #2563eb;
            color: white;
            padding: 0.125rem 0.375rem;
            border-radius: 0.25rem;
            display: inline-block;
        }
        
        .calendar-events {
            font-size: 0.75rem;
        }
        
        .calendar-event {
            background: #dbeafe;
            color: #1e40af;
            padding: 0.125rem 0.25rem;
            border-radius: 0.25rem;
            margin-bottom: 0.125rem;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            cursor: pointer;
        }
        
        .calendar-event.deadline {
            background: #fef3c7;
            color: #92400e;
        }
        
        .calendar-event:hover {
            opacity: 0.8;
        }
        
        .availability-day {
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .availability-day:last-child {
            border-bottom: none;
        }
        
        .availability-toggle {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .time-inputs {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }
        
        .time-inputs input {
            width: 100px;
        }
        
        .hearing-details {
            background: white;
            border-radius: 0.75rem;
            padding: 2rem;
            margin-top: 2rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .hearing-list {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .hearing-item {
            padding: 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            transition: all 0.2s;
        }
        
        .hearing-item:hover {
            border-color: #2563eb;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .hearing-time {
            font-weight: 600;
            color: #2563eb;
            margin-bottom: 0.25rem;
        }
        
        .hearing-case {
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        
        .hearing-parties {
            font-size: 0.875rem;
            color: #6b7280;
        }
        
        .hearing-type {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }
        
        .hearing-type.online {
            background: #e0e7ff;
            color: #3730a3;
        }
        
        .hearing-type.presencial {
            background: #d1fae5;
            color: #065f46;
        }
        
        .hearing-actions {
            display: flex;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        
        .modal-content {
            background: white;
            padding: 2rem;
            border-radius: 0.75rem;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .close-modal {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #6b7280;
        }
        
        @media (max-width: 768px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .calendar td {
                height: 80px;
                font-size: 0.875rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'templates/header.php'; ?>
    
    <div class="schedule-container">
        <div class="schedule-header">
            <h1>Minha Agenda</h1>
            <div class="month-navigation">
                <button onclick="changeMonth(-1)">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z"/>
                    </svg>
                </button>
                <h2><?php echo strftime('%B de %Y', strtotime($startDate)); ?></h2>
                <button onclick="changeMonth(1)">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z"/>
                    </svg>
                </button>
            </div>
        </div>
        
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
        <?php endif; ?>
        
        <!-- Estatísticas do Mês -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-value"><?php echo $monthStats['total_hearings']; ?></div>
                <div class="stat-label">Audiências no Mês</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $monthStats['online_hearings']; ?></div>
                <div class="stat-label">Audiências Online</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $monthStats['presencial_hearings']; ?></div>
                <div class="stat-label">Audiências Presenciais</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $monthStats['active_cases']; ?></div>
                <div class="stat-label">Casos Ativos</div>
            </div>
        </div>
        
        <div class="content-grid">
            <!-- Calendário -->
            <div class="calendar-section">
                <h3>Calendário</h3>
                <?php
                // Gerar calendário
                $firstDay = date('w', strtotime($startDate));
                $daysInMonth = date('t', strtotime($startDate));
                $today = date('Y-m-d');
                
                echo '<table class="calendar">';
                echo '<thead><tr>';
                foreach ($daysOfWeek as $day) {
                    echo '<th>' . substr($day, 0, 3) . '</th>';
                }
                echo '</tr></thead>';
                echo '<tbody><tr>';
                
                // Dias do mês anterior
                for ($i = 0; $i < $firstDay; $i++) {
                    echo '<td><div class="calendar-day other-month">&nbsp;</div></td>';
                }
                
                // Dias do mês atual
                for ($day = 1; $day <= $daysInMonth; $day++) {
                    if (($day + $firstDay - 1) % 7 == 0 && $day > 1) {
                        echo '</tr><tr>';
                    }
                    
                    $currentDate = sprintf('%s-%02d-%02d', $currentYear . '-' . $currentMonth, $day);
                    $isToday = $currentDate == $today;
                    
                    echo '<td>';
                    echo '<div class="calendar-day' . ($isToday ? ' today' : '') . '">' . $day . '</div>';
                    echo '<div class="calendar-events">';
                    
                    // Audiências do dia
                    if (isset($hearingsByDate[$currentDate])) {
                        foreach ($hearingsByDate[$currentDate] as $hearing) {
                            echo '<div class="calendar-event" onclick="showHearingDetails(\'' . $hearing['id'] . '\')">';
                            echo substr($hearing['time'], 0, 5) . ' - ' . $hearing['case_number'];
                            echo '</div>';
                        }
                    }
                    
                    // Prazos do dia
                    foreach ($deadlines as $deadline) {
                        if ($deadline['deadline_decision'] == $currentDate || $deadline['deadline_analysis'] == $currentDate) {
                            echo '<div class="calendar-event deadline">';
                            echo 'Prazo: ' . $deadline['case_number'];
                            echo '</div>';
                        }
                    }
                    
                    echo '</div>';
                    echo '</td>';
                }
                
                // Completar última semana
                $remainingDays = 7 - (($daysInMonth + $firstDay) % 7);
                if ($remainingDays < 7) {
                    for ($i = 0; $i < $remainingDays; $i++) {
                        echo '<td><div class="calendar-day other-month">&nbsp;</div></td>';
                    }
                }
                
                echo '</tr></tbody>';
                echo '</table>';
                ?>
            </div>
            
            <!-- Disponibilidade -->
            <div class="availability-section">
                <h3>Disponibilidade Semanal</h3>
                
                <form action="schedule.php" method="POST" id="availabilityForm">
                    <input type="hidden" name="action" value="update_availability">
                    
                    <?php foreach ($daysOfWeek as $dayNum => $dayName): ?>
                        <?php $slot = $availabilityByDay[$dayNum] ?? null; ?>
                        <div class="availability-day">
                            <div class="availability-toggle">
                                <label>
                                    <input type="checkbox" 
                                           name="days[<?php echo $dayNum; ?>][enabled]" 
                                           value="1"
                                           <?php echo $slot ? 'checked' : ''; ?>
                                           onchange="toggleDayAvailability(<?php echo $dayNum; ?>)">
                                    <?php echo $dayName; ?>
                                </label>
                            </div>
                            <div class="time-inputs" id="times-<?php echo $dayNum; ?>" style="<?php echo !$slot ? 'display:none;' : ''; ?>">
                                <input type="time" 
                                       name="days[<?php echo $dayNum; ?>][start]" 
                                       value="<?php echo $slot['start_time'] ?? '09:00'; ?>"
                                       class="form-control">
                                <span>às</span>
                                <input type="time" 
                                       name="days[<?php echo $dayNum; ?>][end]" 
                                       value="<?php echo $slot['end_time'] ?? '18:00'; ?>"
                                       class="form-control">
                            </div>
                        </div>
                    <?php endforeach; ?>
                    
                    <div class="form-group">
                        <label>Horas Disponíveis por Semana</label>
                        <input type="number" 
                               name="weekly_hours" 
                               class="form-control" 
                               value="<?php echo $profile['availability_hours'] ?? 20; ?>"
                               min="1"
                               max="168">
                    </div>
                    
                    <button type="submit" class="btn btn-primary btn-block">Salvar Disponibilidade</button>
                </form>
            </div>
        </div>
        
        <!-- Lista de Audiências do Dia -->
        <div class="hearing-details" id="dailyHearings" style="display: none;">
            <h3>Audiências do Dia <span id="selectedDate"></span></h3>
            <div class="hearing-list" id="hearingsList"></div>
        </div>
    </div>
    
    <!-- Modal de Reagendamento -->
    <div id="rescheduleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Reagendar Audiência</h3>
                <button type="button" class="close-modal" onclick="hideModal('rescheduleModal')">&times;</button>
            </div>
            
            <form action="schedule.php" method="POST">
                <input type="hidden" name="action" value="reschedule_hearing">
                <input type="hidden" name="hearing_id" id="rescheduleHearingId">
                
                <div class="form-group">
                    <label>Nova Data *</label>
                    <input type="date" name="new_date" class="form-control" required min="<?php echo date('Y-m-d'); ?>">
                </div>
                
                <div class="form-group">
                    <label>Novo Horário *</label>
                    <input type="time" name="new_time" class="form-control" required>
                </div>
                
                <div class="form-group">
                    <label>Motivo do Reagendamento *</label>
                    <textarea name="reason" class="form-control" rows="3" required></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Reagendar</button>
                    <button type="button" class="btn btn-secondary" onclick="hideModal('rescheduleModal')">Cancelar</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal de Cancelamento -->
    <div id="cancelModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Cancelar Audiência</h3>
                <button type="button" class="close-modal" onclick="hideModal('cancelModal')">&times;</button>
            </div>
            
            <form action="schedule.php" method="POST">
                <input type="hidden" name="action" value="cancel_hearing">
                <input type="hidden" name="hearing_id" id="cancelHearingId">
                
                <div class="alert alert-warning">
                    <strong>Atenção:</strong> O cancelamento de audiências deve ser feito apenas em casos excepcionais.
                </div>
                
                <div class="form-group">
                    <label>Motivo do Cancelamento *</label>
                    <textarea name="cancel_reason" class="form-control" rows="4" required></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-danger">Confirmar Cancelamento</button>
                    <button type="button" class="btn btn-secondary" onclick="hideModal('cancelModal')">Voltar</button>
                </div>
            </form>
        </div>
    </div>
    
    <?php include 'templates/footer.php'; ?>
    
    <script>
        // Dados das audiências para JavaScript
        const hearingsData = <?php echo json_encode($hearings); ?>;
        
        function changeMonth(direction) {
            const currentMonth = <?php echo $currentMonth; ?>;
            const currentYear = <?php echo $currentYear; ?>;
            
            let newMonth = parseInt(currentMonth) + direction;
            let newYear = currentYear;
            
            if (newMonth > 12) {
                newMonth = 1;
                newYear++;
            } else if (newMonth < 1) {
                newMonth = 12;
                newYear--;
            }
            
            window.location.href = `schedule.php?month=${newMonth}&year=${newYear}`;
        }
        
        function toggleDayAvailability(day) {
            const timesDiv = document.getElementById(`times-${day}`);
            const checkbox = document.querySelector(`input[name="days[${day}][enabled]"]`);
            
            if (checkbox.checked) {
                timesDiv.style.display = 'flex';
            } else {
                timesDiv.style.display = 'none';
            }
        }
        
        function showHearingDetails(date) {
            const hearingsForDate = hearingsData.filter(h => h.date === date);
            const selectedDateEl = document.getElementById('selectedDate');
            const hearingsList = document.getElementById('hearingsList');
            const dailyHearings = document.getElementById('dailyHearings');
            
            selectedDateEl.textContent = formatDate(date);
            hearingsList.innerHTML = '';
            
            if (hearingsForDate.length === 0) {
                hearingsList.innerHTML = '<p class="text-muted">Nenhuma audiência agendada para este dia.</p>';
            } else {
                hearingsForDate.forEach(hearing => {
                    const hearingItem = createHearingItem(hearing);
                    hearingsList.appendChild(hearingItem);
                });
            }
            
            dailyHearings.style.display = 'block';
            dailyHearings.scrollIntoView({ behavior: 'smooth' });
        }
        
        function createHearingItem(hearing) {
            const div = document.createElement('div');
            div.className = 'hearing-item';
            
            const typeClass = hearing.type === 'online' ? 'online' : 'presencial';
            const typeText = hearing.type === 'online' ? 'Online' : 'Presencial';
            
            div.innerHTML = `
                <div class="hearing-time">${hearing.time.substr(0, 5)}</div>
                <div class="hearing-case">Caso ${hearing.case_number}: ${hearing.case_title}</div>
                <div class="hearing-parties">${hearing.claimant_name} vs ${hearing.respondent_name}</div>
                <span class="hearing-type ${typeClass}">${typeText}</span>
                ${hearing.type === 'online' && hearing.video_link ? 
                    `<div style="margin-top: 0.5rem;">
                        <a href="${hearing.video_link}" target="_blank" class="text-primary">
                            <small>Link da reunião</small>
                        </a>
                    </div>` : ''}
                ${hearing.type === 'presencial' && hearing.location ? 
                    `<div style="margin-top: 0.5rem;">
                        <small class="text-muted">Local: ${hearing.location}</small>
                    </div>` : ''}
                <div class="hearing-actions">
                    <button class="btn btn-sm btn-secondary" onclick="openReschedule('${hearing.id}')">
                        Reagendar
                    </button>
                    <button class="btn btn-sm btn-danger" onclick="openCancel('${hearing.id}')">
                        Cancelar
                    </button>
                    <a href="cases/view.php?id=${hearing.dispute_id}" class="btn btn-sm btn-primary">
                        Ver Caso
                    </a>
                </div>
            `;
            
            return div;
        }
        
        function formatDate(dateStr) {
            const date = new Date(dateStr + 'T00:00:00');
            const options = { day: 'numeric', month: 'long', year: 'numeric' };
            return date.toLocaleDateString('pt-BR', options);
        }
        
        function openReschedule(hearingId) {
            document.getElementById('rescheduleHearingId').value = hearingId;
            showModal('rescheduleModal');
        }
        
        function openCancel(hearingId) {
            document.getElementById('cancelHearingId').value = hearingId;
            showModal('cancelModal');
        }
        
        function showModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }
        
        function hideModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        // Fechar modal ao clicar fora
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        
        // Mostrar audiências de hoje ao carregar
        window.onload = function() {
            const today = new Date().toISOString().split('T')[0];
            const todayHearings = hearingsData.filter(h => h.date === today);
            if (todayHearings.length > 0) {
                showHearingDetails(today);
            }
        }
    </script>
</body>
</html>