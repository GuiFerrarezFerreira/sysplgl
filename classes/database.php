<?php
/**
 * Arbitrivm - Classe de Banco de Dados Simples
 */

class Database {
    private $connection;
    private static $instance = null;
    
    public function __construct() {
        try {
            $this->connection = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET,
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
        } catch (PDOException $e) {
            die("Erro de conexão: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->connection;
    }
    
    // Métodos genéricos
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            logError("Database query error: " . $e->getMessage(), ['sql' => $sql, 'params' => $params]);
            throw $e;
        }
    }
    
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    public function insert($table, $data) {
        $fields = array_keys($data);
        $placeholders = array_map(function($field) { return ":$field"; }, $fields);
        
        $sql = "INSERT INTO $table (" . implode(', ', $fields) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";
        
        $this->query($sql, $data);
        return $this->connection->lastInsertId();
    }
    
    public function update($table, $data, $where, $whereParams = []) {
        $fields = array_map(function($field) { return "$field = :$field"; }, array_keys($data));
        
        $sql = "UPDATE $table SET " . implode(', ', $fields) . " WHERE $where";
        
        $params = array_merge($data, $whereParams);
        $stmt = $this->query($sql, $params);
        
        return $stmt->rowCount();
    }
    
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM $table WHERE $where";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    // Métodos específicos para usuários
    public function getUserById($id) {
        return $this->fetchOne(
            "SELECT u.*, c.company_name, c.subscription_status 
             FROM users u 
             LEFT JOIN companies c ON u.company_id = c.id 
             WHERE u.id = ?",
            [$id]
        );
    }
    
    public function getUserByEmail($email) {
        return $this->fetchOne(
            "SELECT u.*, c.company_name, c.subscription_status 
             FROM users u 
             LEFT JOIN companies c ON u.company_id = c.id 
             WHERE u.email = ?",
            [$email]
        );
    }
    
    public function checkLoginAttempts($email) {
        $result = $this->fetchOne(
            "SELECT login_attempts, last_attempt_at 
             FROM users 
             WHERE email = ?",
            [$email]
        );
        
        if (!$result) return ['attempts' => 0, 'locked' => false];
        
        $locked = false;
        if ($result['login_attempts'] >= LOGIN_ATTEMPTS_LIMIT) {
            $lastAttempt = strtotime($result['last_attempt_at']);
            $lockoutEnd = $lastAttempt + LOGIN_LOCKOUT_TIME;
            
            if (time() < $lockoutEnd) {
                $locked = true;
            } else {
                // Reset attempts após o período de bloqueio
                $this->update('users', 
                    ['login_attempts' => 0], 
                    'email = ?', 
                    [$email]
                );
                $result['login_attempts'] = 0;
            }
        }
        
        return [
            'attempts' => $result['login_attempts'],
            'locked' => $locked,
            'remaining_time' => $locked ? ($lockoutEnd - time()) : 0
        ];
    }
    
    public function incrementLoginAttempts($email) {
        $this->query(
            "UPDATE users 
             SET login_attempts = login_attempts + 1, 
                 last_attempt_at = NOW() 
             WHERE email = ?",
            [$email]
        );
    }
    
    public function resetLoginAttempts($userId) {
        $this->update('users', 
            ['login_attempts' => 0, 'last_login_at' => date('Y-m-d H:i:s')], 
            'id = ?', 
            [$userId]
        );
    }
    
    // Métodos para disputas
    public function getDisputes($filters = []) {
        $sql = "SELECT d.*, dt.name as dispute_type_name, 
                       c.company_name,
                       CONCAT(u1.first_name, ' ', u1.last_name) as claimant_name,
                       CONCAT(u2.first_name, ' ', u2.last_name) as respondent_name,
                       CONCAT(u3.first_name, ' ', u3.last_name) as arbitrator_name
                FROM disputes d
                JOIN dispute_types dt ON d.dispute_type_id = dt.id
                JOIN companies c ON d.company_id = c.id
                JOIN users u1 ON d.claimant_id = u1.id
                JOIN users u2 ON d.respondent_id = u2.id
                LEFT JOIN arbitrators a ON d.arbitrator_id = a.id
                LEFT JOIN users u3 ON a.user_id = u3.id
                WHERE 1=1";
        
        $params = [];
        
        if (isset($filters['company_id'])) {
            $sql .= " AND d.company_id = :company_id";
            $params['company_id'] = $filters['company_id'];
        }
        
        if (isset($filters['status'])) {
            $sql .= " AND d.status = :status";
            $params['status'] = $filters['status'];
        }
        
        if (isset($filters['user_id'])) {
            $sql .= " AND (d.claimant_id = :user_id OR d.respondent_id = :user_id2)";
            $params['user_id'] = $filters['user_id'];
            $params['user_id2'] = $filters['user_id'];
        }
        
        if (isset($filters['arbitrator_id'])) {
            $sql .= " AND d.arbitrator_id = :arbitrator_id";
            $params['arbitrator_id'] = $filters['arbitrator_id'];
        }
        
        $sql .= " ORDER BY d.created_at DESC";
        
        if (isset($filters['limit'])) {
            $sql .= " LIMIT " . intval($filters['limit']);
            if (isset($filters['offset'])) {
                $sql .= " OFFSET " . intval($filters['offset']);
            }
        }
        
        return $this->fetchAll($sql, $params);
    }
    
    public function getDisputeById($id) {
        return $this->fetchOne(
            "SELECT d.*, dt.name as dispute_type_name, dt.category as dispute_type_category,
                    c.company_name, c.cnpj,
                    u1.email as claimant_email, CONCAT(u1.first_name, ' ', u1.last_name) as claimant_name,
                    u2.email as respondent_email, CONCAT(u2.first_name, ' ', u2.last_name) as respondent_name,
                    a.registration_number as arbitrator_registration,
                    u3.email as arbitrator_email, CONCAT(u3.first_name, ' ', u3.last_name) as arbitrator_name
             FROM disputes d
             JOIN dispute_types dt ON d.dispute_type_id = dt.id
             JOIN companies c ON d.company_id = c.id
             JOIN users u1 ON d.claimant_id = u1.id
             JOIN users u2 ON d.respondent_id = u2.id
             LEFT JOIN arbitrators a ON d.arbitrator_id = a.id
             LEFT JOIN users u3 ON a.user_id = u3.id
             WHERE d.id = ?",
            [$id]
        );
    }
    
    public function createDispute($data) {
        // Gerar número do caso
        $lastCase = $this->fetchOne("SELECT MAX(CAST(SUBSTRING(case_number, 2) AS UNSIGNED)) as last_number FROM disputes");
        $nextNumber = ($lastCase['last_number'] ?? 0) + 1;
        $data['case_number'] = '#' . str_pad($nextNumber, 6, '0', STR_PAD_LEFT);
        
        $disputeId = $this->insert('disputes', $data);
        
        // Log do evento
        $this->insert('dispute_events', [
            'dispute_id' => $disputeId,
            'user_id' => $data['claimant_id'],
            'event_type' => 'dispute_created',
            'description' => 'Disputa criada',
            'metadata' => json_encode(['status' => $data['status']])
        ]);
        
        return $disputeId;
    }
    
    // Métodos para documentos
    public function getDocumentsByDispute($disputeId) {
        return $this->fetchAll(
            "SELECT d.*, CONCAT(u.first_name, ' ', u.last_name) as uploaded_by_name
             FROM documents d
             JOIN users u ON d.uploaded_by = u.id
             WHERE d.dispute_id = ?
             ORDER BY d.created_at DESC",
            [$disputeId]
        );
    }
    
    public function saveDocument($data) {
        return $this->insert('documents', $data);
    }
    
    // Métodos para mensagens
    public function getMessagesByDispute($disputeId, $limit = 50) {
        return $this->fetchAll(
            "SELECT m.*, CONCAT(u.first_name, ' ', u.last_name) as sender_name, u.role
             FROM messages m
             JOIN users u ON m.sender_id = u.id
             WHERE m.dispute_id = ?
             ORDER BY m.created_at DESC
             LIMIT ?",
            [$disputeId, $limit]
        );
    }
    
    public function sendMessage($disputeId, $senderId, $message) {
        return $this->insert('messages', [
            'dispute_id' => $disputeId,
            'sender_id' => $senderId,
            'message' => $message,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    // Métodos para notificações
    public function createNotification($userId, $type, $title, $message, $data = null) {
        return $this->insert('notifications', [
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data ? json_encode($data) : null,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
    
    public function getUnreadNotifications($userId) {
        return $this->fetchAll(
            "SELECT * FROM notifications 
             WHERE user_id = ? AND is_read = 0 
             ORDER BY created_at DESC",
            [$userId]
        );
    }
    
    // Métodos para árbitros
    public function getAvailableArbitrators($disputeTypeId = null) {
        $sql = "SELECT a.*, u.email, CONCAT(u.first_name, ' ', u.last_name) as name
                FROM arbitrators a
                JOIN users u ON a.user_id = u.id
                WHERE a.is_available = 1 AND a.documents_verified = 1";
        
        $params = [];
        
        if ($disputeTypeId) {
            $sql .= " AND JSON_CONTAINS(a.specializations, ?, '$')";
            $params[] = json_encode($disputeTypeId);
        }
        
        $sql .= " ORDER BY a.rating DESC, a.cases_resolved DESC";
        
        return $this->fetchAll($sql, $params);
    }
    
    // Métodos para relatórios
    public function getDashboardStats($companyId = null) {
        $params = [];
        $companyFilter = "";
        
        if ($companyId) {
            $companyFilter = " AND company_id = ?";
            $params[] = $companyId;
        }
        
        $stats = [];
        
        // Total de disputas
        $stats['total_disputes'] = $this->fetchOne(
            "SELECT COUNT(*) as count FROM disputes WHERE 1=1 $companyFilter",
            $params
        )['count'];
        
        // Disputas ativas
        $stats['active_disputes'] = $this->fetchOne(
            "SELECT COUNT(*) as count FROM disputes WHERE status = 'active' $companyFilter",
            $params
        )['count'];
        
        // Disputas resolvidas
        $stats['resolved_disputes'] = $this->fetchOne(
            "SELECT COUNT(*) as count FROM disputes WHERE status = 'resolved' $companyFilter",
            $params
        )['count'];
        
        // Tempo médio de resolução
        $avgTime = $this->fetchOne(
            "SELECT AVG(DATEDIFF(resolved_at, created_at)) as avg_days 
             FROM disputes 
             WHERE status = 'resolved' AND resolved_at IS NOT NULL $companyFilter",
            $params
        );
        $stats['avg_resolution_days'] = round($avgTime['avg_days'] ?? 0);
        
        // Taxa de resolução
        if ($stats['total_disputes'] > 0) {
            $stats['resolution_rate'] = round(($stats['resolved_disputes'] / $stats['total_disputes']) * 100, 2);
        } else {
            $stats['resolution_rate'] = 0;
        }
        
        return $stats;
    }
    
    // Log de auditoria
    public function logAction($userId, $action, $entityType = null, $entityId = null, $metadata = null) {
        return $this->insert('audit_logs', [
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'old_values' => null,
            'new_values' => $metadata ? json_encode($metadata) : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }
}