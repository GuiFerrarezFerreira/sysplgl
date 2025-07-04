<?php
/**
 * includes/notifications-arbitrator.php - Sistema de Notificações para Árbitros
 */

class ArbitratorNotifications {
    private $db;
    private $emailService;
    private $smsService;
    
    public function __construct($db, $emailService = null, $smsService = null) {
        $this->db = $db;
        $this->emailService = $emailService;
        $this->smsService = $smsService;
    }
    
    /**
     * Notificar novo caso designado
     */
    public function notifyNewCase($arbitratorId, $caseId) {
        $arbitrator = $this->getArbitratorInfo($arbitratorId);
        $case = $this->getCaseInfo($caseId);
        
        // Notificação no sistema
        $this->createSystemNotification($arbitrator['user_id'], [
            'type' => 'new_case_assigned',
            'title' => 'Novo Caso Designado',
            'message' => "Você foi selecionado para arbitrar o caso {$case['case_number']}",
            'link' => "/arbitrator/cases/view.php?id={$caseId}",
            'priority' => 'high'
        ]);
        
        // Email
        $emailData = [
            'subject' => "Novo Caso Designado - {$case['case_number']}",
            'template' => 'new_case_assigned',
            'data' => [
                'arbitrator_name' => $arbitrator['name'],
                'case_number' => $case['case_number'],
                'case_title' => $case['title'],
                'parties' => [
                    'claimant' => $case['claimant_name'],
                    'respondent' => $case['respondent_name']
                ],
                'dispute_amount' => formatCurrency($case['dispute_amount']),
                'deadline_accept' => date('d/m/Y H:i', strtotime('+48 hours')),
                'action_url' => BASE_URL . "/arbitrator/cases/view.php?id={$caseId}"
            ]
        ];
        
        $this->sendEmail($arbitrator['email'], $emailData);
        
        // SMS (se habilitado)
        if ($this->shouldSendSMS($arbitrator, 'new_case')) {
            $this->sendSMS($arbitrator['phone'], 
                "Arbitrivm: Novo caso {$case['case_number']} designado. Acesse o sistema para aceitar ou recusar."
            );
        }
        
        // Push notification (se app mobile)
        $this->sendPushNotification($arbitrator['user_id'], [
            'title' => 'Novo Caso Designado',
            'body' => "Caso {$case['case_number']} aguarda sua resposta",
            'data' => ['case_id' => $caseId, 'type' => 'new_case']
        ]);
    }
    
    /**
     * Notificar audiência próxima
     */
    public function notifyUpcomingHearing($hearingId) {
        $hearing = $this->getHearingInfo($hearingId);
        $arbitrator = $this->getArbitratorByCaseId($hearing['dispute_id']);
        
        $hoursUntil = (strtotime($hearing['datetime']) - time()) / 3600;
        
        // Determinar tipo de notificação baseado no tempo
        if ($hoursUntil <= 1) {
            $urgency = 'urgent';
            $title = 'Audiência em 1 hora!';
        } elseif ($hoursUntil <= 24) {
            $urgency = 'high';
            $title = 'Audiência Amanhã';
        } else {
            $urgency = 'normal';
            $title = 'Lembrete de Audiência';
        }
        
        // Notificação no sistema
        $this->createSystemNotification($arbitrator['user_id'], [
            'type' => 'hearing_reminder',
            'title' => $title,
            'message' => $this->formatHearingMessage($hearing, $hoursUntil),
            'link' => "/arbitrator/cases/hearing.php?id={$hearing['id']}",
            'priority' => $urgency
        ]);
        
        // Email
        if ($hoursUntil <= 24) {
            $this->sendHearingReminderEmail($arbitrator, $hearing);
        }
        
        // SMS para audiências urgentes
        if ($hoursUntil <= 2 && $this->shouldSendSMS($arbitrator, 'urgent_hearing')) {
            $this->sendSMS($arbitrator['phone'], 
                "URGENTE: Audiência caso {$hearing['case_number']} em {$this->formatTimeRemaining($hoursUntil)}. " .
                ($hearing['type'] === 'online' ? "Link: {$hearing['video_link']}" : "Local: {$hearing['location']}")
            );
        }
    }
    
    /**
     * Notificar prazo próximo
     */
    public function notifyDeadline($caseId, $deadlineType) {
        $case = $this->getCaseInfo($caseId);
        $arbitrator = $this->getArbitratorByCaseId($caseId);
        
        $deadlines = [
            'decision' => [
                'field' => 'deadline_decision',
                'title' => 'Prazo para Sentença',
                'action' => 'emitir a decisão'
            ],
            'analysis' => [
                'field' => 'deadline_analysis',
                'title' => 'Prazo para Análise',
                'action' => 'concluir a análise'
            ],
            'response' => [
                'field' => 'deadline_response',
                'title' => 'Prazo para Manifestação',
                'action' => 'se manifestar'
            ]
        ];
        
        $deadline = $deadlines[$deadlineType];
        $deadlineDate = $case[$deadline['field']];
        $daysRemaining = (strtotime($deadlineDate) - time()) / 86400;
        
        // Determinar urgência
        $urgency = $daysRemaining <= 2 ? 'urgent' : ($daysRemaining <= 7 ? 'high' : 'normal');
        
        // Notificação no sistema
        $this->createSystemNotification($arbitrator['user_id'], [
            'type' => 'deadline_reminder',
            'title' => "{$deadline['title']} - {$case['case_number']}",
            'message' => "Restam " . ceil($daysRemaining) . " dias para {$deadline['action']}",
            'link' => "/arbitrator/cases/view.php?id={$caseId}",
            'priority' => $urgency
        ]);
        
        // Email para prazos críticos
        if ($daysRemaining <= 3) {
            $this->sendDeadlineEmail($arbitrator, $case, $deadline, $daysRemaining);
        }
    }
    
    /**
     * Notificar nova mensagem no caso
     */
    public function notifyNewMessage($messageId) {
        $message = $this->getMessageInfo($messageId);
        $arbitrator = $this->getArbitratorByCaseId($message['dispute_id']);
        
        // Não notificar se o árbitro enviou a mensagem
        if ($message['sender_id'] == $arbitrator['user_id']) {
            return;
        }
        
        // Notificação no sistema
        $this->createSystemNotification($arbitrator['user_id'], [
            'type' => 'new_message',
            'title' => "Nova mensagem - Caso {$message['case_number']}",
            'message' => "{$message['sender_name']}: " . substr($message['message'], 0, 100) . "...",
            'link' => "/arbitrator/cases/messages.php?id={$message['dispute_id']}#msg-{$messageId}",
            'priority' => 'normal'
        ]);
        
        // Email digest (agrupa mensagens)
        $this->queueMessageDigest($arbitrator['user_id'], $message);
    }
    
    /**
     * Notificar documento adicionado
     */
    public function notifyNewDocument($documentId) {
        $document = $this->getDocumentInfo($documentId);
        $arbitrator = $this->getArbitratorByCaseId($document['dispute_id']);
        
        // Notificação no sistema
        $this->createSystemNotification($arbitrator['user_id'], [
            'type' => 'new_document',
            'title' => "Novo documento - Caso {$document['case_number']}",
            'message' => "'{$document['title']}' foi adicionado por {$document['uploader_name']}",
            'link' => "/arbitrator/cases/documents.php?id={$document['dispute_id']}#doc-{$documentId}",
            'priority' => 'normal',
            'data' => [
                'document_type' => $document['file_type'],
                'document_size' => $document['file_size']
            ]
        ]);
    }
    
    /**
     * Notificar pagamento de honorários
     */
    public function notifyPayment($paymentId) {
        $payment = $this->getPaymentInfo($paymentId);
        $arbitrator = $this->getArbitratorInfo($payment['arbitrator_id']);
        
        // Notificação no sistema
        $this->createSystemNotification($arbitrator['user_id'], [
            'type' => 'payment_received',
            'title' => 'Pagamento Recebido',
            'message' => "Honorários do caso {$payment['case_number']} foram creditados: " . 
                       formatCurrency($payment['net_amount']),
            'link' => "/arbitrator/earnings/view.php?id={$paymentId}",
            'priority' => 'high'
        ]);
        
        // Email com comprovante
        $this->sendPaymentEmail($arbitrator, $payment);
        
        // SMS para pagamentos
        if ($this->shouldSendSMS($arbitrator, 'payments')) {
            $this->sendSMS($arbitrator['phone'], 
                "Arbitrivm: Pagamento de " . formatCurrency($payment['net_amount']) . 
                " creditado em sua conta. Caso {$payment['case_number']}"
            );
        }
    }
    
    /**
     * Notificar avaliação recebida
     */
    public function notifyReview($reviewId) {
        $review = $this->getReviewInfo($reviewId);
        $arbitrator = $this->getArbitratorInfo($review['arbitrator_id']);
        
        // Notificação no sistema (sem mostrar quem avaliou se anônimo)
        $this->createSystemNotification($arbitrator['user_id'], [
            'type' => 'new_review',
            'title' => 'Nova Avaliação Recebida',
            'message' => "Você recebeu uma avaliação {$review['rating']}/5 estrelas no caso {$review['case_number']}",
            'link' => "/arbitrator/reviews/view.php?id={$reviewId}",
            'priority' => 'normal'
        ]);
        
        // Email apenas se avaliação positiva ou com feedback construtivo
        if ($review['rating'] >= 4 || strlen($review['review_text']) > 50) {
            $this->sendReviewEmail($arbitrator, $review);
        }
    }
    
    /**
     * Criar notificação no sistema
     */
    private function createSystemNotification($userId, $data) {
        $this->db->insert('notifications', [
            'user_id' => $userId,
            'type' => $data['type'],
            'title' => $data['title'],
            'message' => $data['message'],
            'link' => $data['link'] ?? null,
            'priority' => $data['priority'] ?? 'normal',
            'data' => isset($data['data']) ? json_encode($data['data']) : null,
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Atualizar contador de notificações não lidas
        $this->updateUnreadCount($userId);
        
        // Enviar via WebSocket se conectado
        $this->sendRealtimeNotification($userId, $data);
    }
    
    /**
     * Enviar email usando template
     */
    private function sendEmail($to, $emailData) {
        if (!$this->emailService) {
            // Fallback para função mail() básica
            $subject = $emailData['subject'];
            $body = $this->renderEmailTemplate($emailData['template'], $emailData['data']);
            return Utils::sendEmail($to, $subject, $body);
        }
        
        return $this->emailService->send($to, $emailData);
    }
    
    /**
     * Renderizar template de email
     */
    private function renderEmailTemplate($template, $data) {
        $templates = [
            'new_case_assigned' => "
                <h2>Novo Caso Designado</h2>
                <p>Olá {arbitrator_name},</p>
                <p>Você foi selecionado para arbitrar o seguinte caso:</p>
                <div style='background: #f5f5f5; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                    <h3>Caso {case_number}</h3>
                    <p><strong>Título:</strong> {case_title}</p>
                    <p><strong>Requerente:</strong> {parties[claimant]}</p>
                    <p><strong>Requerido:</strong> {parties[respondent]}</p>
                    <p><strong>Valor da Disputa:</strong> {dispute_amount}</p>
                </div>
                <p><strong>Prazo para aceitar:</strong> {deadline_accept}</p>
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='{action_url}' style='background: #2563eb; color: white; padding: 12px 30px; text-decoration: none; border-radius: 6px;'>
                        Visualizar Caso
                    </a>
                </div>
            ",
            
            'hearing_reminder' => "
                <h2>Lembrete de Audiência</h2>
                <p>Olá {arbitrator_name},</p>
                <p>Este é um lembrete sobre a audiência agendada:</p>
                <div style='background: #fff3cd; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #ffc107;'>
                    <h3>Caso {case_number}</h3>
                    <p><strong>Data:</strong> {hearing_date}</p>
                    <p><strong>Horário:</strong> {hearing_time}</p>
                    <p><strong>Tipo:</strong> {hearing_type}</p>
                    {hearing_location}
                    {hearing_link}
                </div>
                <p><strong>Partes:</strong></p>
                <ul>
                    <li>Requerente: {claimant_name}</li>
                    <li>Requerido: {respondent_name}</li>
                </ul>
            ",
            
            'payment_received' => "
                <h2>Pagamento de Honorários</h2>
                <p>Olá {arbitrator_name},</p>
                <p>Informamos que seus honorários foram processados:</p>
                <div style='background: #d1fae5; padding: 20px; border-radius: 8px; margin: 20px 0; border-left: 4px solid #10b981;'>
                    <h3>Detalhes do Pagamento</h3>
                    <p><strong>Caso:</strong> {case_number}</p>
                    <p><strong>Valor Bruto:</strong> {gross_amount}</p>
                    <p><strong>Taxa Administrativa (20%):</strong> {platform_fee}</p>
                    <p><strong>Valor Líquido:</strong> {net_amount}</p>
                    <p><strong>Data do Pagamento:</strong> {payment_date}</p>
                </div>
                <p>O comprovante está disponível em sua área de honorários.</p>
            "
        ];
        
        // Substituir variáveis no template
        $html = $templates[$template] ?? '';
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $subKey => $subValue) {
                    $html = str_replace("{{$key}[{$subKey}]}", $subValue, $html);
                }
            } else {
                $html = str_replace("{{$key}}", $value, $html);
            }
        }
        
        return $html;
    }
    
    /**
     * Verificar preferências de notificação
     */
    private function shouldSendSMS($arbitrator, $type) {
        $preferences = json_decode($arbitrator['notification_preferences'] ?? '{}', true);
        return $preferences['sms'][$type] ?? false;
    }
    
    /**
     * Enviar notificação em tempo real via WebSocket
     */
    private function sendRealtimeNotification($userId, $data) {
        // Implementar integração com servidor WebSocket
        // Por exemplo, usando Ratchet ou similar
        try {
            $client = new \WebSocket\Client("ws://localhost:8080");
            $client->send(json_encode([
                'type' => 'notification',
                'user_id' => $userId,
                'data' => $data
            ]));
            $client->close();
        } catch (\Exception $e) {
            logError("Erro ao enviar notificação realtime: " . $e->getMessage());
        }
    }
    
    /**
     * Agendar digest de mensagens
     */
    private function queueMessageDigest($userId, $message) {
        // Verificar se já existe digest pendente
        $existing = $this->db->fetchOne(
            "SELECT * FROM notification_digests 
             WHERE user_id = ? AND type = 'messages' AND sent = 0",
            [$userId]
        );
        
        if ($existing) {
            // Adicionar à digest existente
            $data = json_decode($existing['data'], true);
            $data['messages'][] = [
                'case_id' => $message['dispute_id'],
                'case_number' => $message['case_number'],
                'sender' => $message['sender_name'],
                'preview' => substr($message['message'], 0, 100)
            ];
            
            $this->db->update('notification_digests', 
                ['data' => json_encode($data), 'updated_at' => date('Y-m-d H:i:s')],
                'id = ?',
                [$existing['id']]
            );
        } else {
            // Criar nova digest
            $this->db->insert('notification_digests', [
                'user_id' => $userId,
                'type' => 'messages',
                'data' => json_encode([
                    'messages' => [[
                        'case_id' => $message['dispute_id'],
                        'case_number' => $message['case_number'],
                        'sender' => $message['sender_name'],
                        'preview' => substr($message['message'], 0, 100)
                    ]]
                ]),
                'scheduled_for' => date('Y-m-d H:i:s', strtotime('+1 hour')),
                'created_at' => date('Y-m-d H:i:s')
            ]);
        }
    }
    
    /**
     * Métodos auxiliares para buscar informações
     */
    private function getArbitratorInfo($arbitratorId) {
        return $this->db->fetchOne(
            "SELECT a.*, u.name, u.email, u.phone, u.notification_preferences 
             FROM arbitrators a 
             INNER JOIN users u ON a.user_id = u.id 
             WHERE a.id = ?",
            [$arbitratorId]
        );
    }
    
    private function getCaseInfo($caseId) {
        return $this->db->fetchOne(
            "SELECT d.*, u1.name as claimant_name, u2.name as respondent_name 
             FROM disputes d
             LEFT JOIN users u1 ON d.claimant_id = u1.id
             LEFT JOIN users u2 ON d.respondent_id = u2.id
             WHERE d.id = ?",
            [$caseId]
        );
    }
    
    private function getHearingInfo($hearingId) {
        return $this->db->fetchOne(
            "SELECT h.*, d.case_number, d.title as case_title,
                    CONCAT(h.date, ' ', h.time) as datetime
             FROM dispute_hearings h
             INNER JOIN disputes d ON h.dispute_id = d.id
             WHERE h.id = ?",
            [$hearingId]
        );
    }
    
    private function formatTimeRemaining($hours) {
        if ($hours < 1) {
            return round($hours * 60) . " minutos";
        } elseif ($hours < 24) {
            return round($hours) . " hora(s)";
        } else {
            return round($hours / 24) . " dia(s)";
        }
    }
}

/**
 * Cron job para processar notificações agendadas
 */
function processScheduledNotifications() {
    $db = new Database();
    $notifier = new ArbitratorNotifications($db);
    
    // Processar lembretes de audiência
    $upcomingHearings = $db->fetchAll(
        "SELECT h.* FROM dispute_hearings h
         WHERE h.status = 'scheduled'
         AND h.notification_sent = 0
         AND CONCAT(h.date, ' ', h.time) BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)"
    );
    
    foreach ($upcomingHearings as $hearing) {
        $notifier->notifyUpcomingHearing($hearing['id']);
        $db->update('dispute_hearings', 
            ['notification_sent' => 1],
            'id = ?',
            [$hearing['id']]
        );
    }
    
    // Processar lembretes de prazo
    $upcomingDeadlines = $db->fetchAll(
        "SELECT d.*, ac.arbitrator_id 
         FROM disputes d
         INNER JOIN arbitrator_cases ac ON d.id = ac.dispute_id
         WHERE ac.status = 'accepted'
         AND d.status IN ('in_analysis', 'hearing_scheduled')
         AND (
            (d.deadline_decision BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY))
            OR (d.deadline_analysis BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 3 DAY))
         )"
    );
    
    foreach ($upcomingDeadlines as $case) {
        if ($case['deadline_decision'] && strtotime($case['deadline_decision']) - time() < 259200) {
            $notifier->notifyDeadline($case['id'], 'decision');
        }
        if ($case['deadline_analysis'] && strtotime($case['deadline_analysis']) - time() < 259200) {
            $notifier->notifyDeadline($case['id'], 'analysis');
        }
    }
    
    // Processar digests
    $digests = $db->fetchAll(
        "SELECT * FROM notification_digests 
         WHERE sent = 0 AND scheduled_for <= NOW()"
    );
    
    foreach ($digests as $digest) {
        processDigest($digest);
        $db->update('notification_digests',
            ['sent' => 1, 'sent_at' => date('Y-m-d H:i:s')],
            'id = ?',
            [$digest['id']]
        );
    }
}