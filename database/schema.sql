-- Tabela de empresas (B2B)
CREATE TABLE companies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    cnpj VARCHAR(18) UNIQUE NOT NULL,
    company_name VARCHAR(255) NOT NULL,
    trade_name VARCHAR(255),
    company_type ENUM('real_estate', 'construction', 'condominium', 'other') NOT NULL,
    address TEXT,
    city VARCHAR(100),
    state VARCHAR(2),
    zip_code VARCHAR(9),
    phone VARCHAR(20),
    email VARCHAR(255) NOT NULL,
    website VARCHAR(255),
    subscription_plan ENUM('basic', 'professional', 'enterprise') DEFAULT 'basic',
    subscription_status ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
    subscription_expires_at DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_cnpj (cnpj),
    INDEX idx_company_type (company_type),
    INDEX idx_subscription_status (subscription_status)
);

-- Tabela de usuários
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    cpf VARCHAR(14) UNIQUE,
    phone VARCHAR(20),
    role ENUM('admin', 'manager', 'user', 'arbitrator', 'party') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    is_verified BOOLEAN DEFAULT FALSE,
    verification_token VARCHAR(255),
    two_factor_enabled BOOLEAN DEFAULT FALSE,
    two_factor_secret VARCHAR(255),
    last_login_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_company (company_id)
);

-- Tabela de árbitros
CREATE TABLE arbitrators (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT UNIQUE NOT NULL,
    registration_number VARCHAR(50) UNIQUE NOT NULL,
    specializations TEXT,
    bio TEXT,
    experience_years INT,
    hourly_rate DECIMAL(10,2),
    cases_resolved INT DEFAULT 0,
    success_rate DECIMAL(5,2) DEFAULT 0,
    average_resolution_days INT DEFAULT 0,
    rating DECIMAL(3,2) DEFAULT 0,
    total_reviews INT DEFAULT 0,
    is_available BOOLEAN DEFAULT TRUE,
    documents_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_available (is_available),
    INDEX idx_rating (rating),
    INDEX idx_specializations (specializations)
);

-- Tabela de tipos de disputa
CREATE TABLE dispute_types (
    id INT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Inserir tipos de disputa padrão
INSERT INTO dispute_types (code, name, category) VALUES
('property_damage', 'Danos ao Imóvel', 'Locação'),
('condo_infraction', 'Infração Condominial', 'Condomínio'),
('rent_payment', 'Inadimplência de Aluguel', 'Locação'),
('contract_breach', 'Quebra de Contrato', 'Geral'),
('maintenance_dispute', 'Disputa sobre Manutenção', 'Manutenção'),
('deposit_return', 'Devolução de Caução', 'Locação');

-- Tabela de disputas
CREATE TABLE disputes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    case_number VARCHAR(20) UNIQUE NOT NULL,
    company_id INT NOT NULL,
    dispute_type_id INT NOT NULL,
    claimant_id INT NOT NULL,
    respondent_id INT NOT NULL,
    arbitrator_id INT,
    status ENUM('draft', 'pending_arbitrator', 'pending_acceptance', 'active', 'on_hold', 'resolved', 'cancelled') NOT NULL DEFAULT 'draft',
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    claim_amount DECIMAL(12,2),
    property_address TEXT,
    contract_number VARCHAR(100),
    priority ENUM('low', 'normal', 'high', 'urgent') DEFAULT 'normal',
    deadline_date DATE,
    started_at TIMESTAMP NULL,
    resolved_at TIMESTAMP NULL,
    resolution_summary TEXT,
    decision_document_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (dispute_type_id) REFERENCES dispute_types(id),
    FOREIGN KEY (claimant_id) REFERENCES users(id),
    FOREIGN KEY (respondent_id) REFERENCES users(id),
    FOREIGN KEY (arbitrator_id) REFERENCES arbitrators(id),
    INDEX idx_case_number (case_number),
    INDEX idx_status (status),
    INDEX idx_company (company_id),
    INDEX idx_arbitrator (arbitrator_id),
    INDEX idx_dates (created_at, resolved_at)
);

-- Tabela de documentos
CREATE TABLE documents (
    id INT PRIMARY KEY AUTO_INCREMENT,
    dispute_id INT NOT NULL,
    uploaded_by INT NOT NULL,
    file_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size INT NOT NULL,
    file_type VARCHAR(100),
    document_type ENUM('contract', 'evidence', 'report', 'photo', 'video', 'audio', 'decision', 'other') NOT NULL,
    description TEXT,
    is_confidential BOOLEAN DEFAULT FALSE,
    hash_verification VARCHAR(64),
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dispute_id) REFERENCES disputes(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id),
    INDEX idx_dispute (dispute_id),
    INDEX idx_type (document_type),
    INDEX idx_uploaded_by (uploaded_by)
);

-- Tabela de mensagens do chat
CREATE TABLE messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    dispute_id INT NOT NULL,
    sender_id INT NOT NULL,
    message TEXT NOT NULL,
    is_system_message BOOLEAN DEFAULT FALSE,
    attachments JSON,
    read_by JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dispute_id) REFERENCES disputes(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id),
    INDEX idx_dispute (dispute_id),
    INDEX idx_sender (sender_id),
    INDEX idx_created (created_at)
);

-- Tabela de notificações
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    data JSON,
    is_read BOOLEAN DEFAULT FALSE,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_read (is_read),
    INDEX idx_created (created_at)
);

-- Tabela de eventos/timeline da disputa
CREATE TABLE dispute_events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    dispute_id INT NOT NULL,
    user_id INT NOT NULL,
    event_type VARCHAR(50) NOT NULL,
    description TEXT NOT NULL,
    metadata JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dispute_id) REFERENCES disputes(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_dispute (dispute_id),
    INDEX idx_created (created_at)
);

-- Tabela de avaliações
CREATE TABLE reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    dispute_id INT NOT NULL,
    reviewer_id INT NOT NULL,
    arbitrator_id INT NOT NULL,
    rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (dispute_id) REFERENCES disputes(id),
    FOREIGN KEY (reviewer_id) REFERENCES users(id),
    FOREIGN KEY (arbitrator_id) REFERENCES arbitrators(id),
    UNIQUE KEY unique_review (dispute_id, reviewer_id),
    INDEX idx_arbitrator (arbitrator_id)
);

-- Tabela de pagamentos
CREATE TABLE payments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    company_id INT NOT NULL,
    dispute_id INT,
    amount DECIMAL(10,2) NOT NULL,
    payment_type ENUM('subscription', 'arbitration_fee', 'additional_service') NOT NULL,
    payment_method ENUM('credit_card', 'debit_card', 'bank_transfer', 'pix', 'boleto') NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    gateway_transaction_id VARCHAR(255),
    gateway_response JSON,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (company_id) REFERENCES companies(id),
    FOREIGN KEY (dispute_id) REFERENCES disputes(id),
    INDEX idx_company (company_id),
    INDEX idx_status (status),
    INDEX idx_type (payment_type)
);

-- Tabela de logs de auditoria
CREATE TABLE audit_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_created (created_at)
);

-- Tabela de sessões (para JWT refresh tokens)
CREATE TABLE user_sessions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    refresh_token VARCHAR(500) UNIQUE NOT NULL,
    device_info JSON,
    ip_address VARCHAR(45),
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_token (refresh_token),
    INDEX idx_expires (expires_at)
);

-- Tabela de templates de documentos
CREATE TABLE document_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL,
    content TEXT NOT NULL,
    variables JSON,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Views úteis
CREATE VIEW dispute_summary AS
SELECT 
    d.id,
    d.case_number,
    d.title,
    d.status,
    d.created_at,
    d.resolved_at,
    dt.name as dispute_type,
    c.company_name,
    CONCAT(u1.first_name, ' ', u1.last_name) as claimant_name,
    CONCAT(u2.first_name, ' ', u2.last_name) as respondent_name,
    CONCAT(u3.first_name, ' ', u3.last_name) as arbitrator_name,
    d.claim_amount,
    DATEDIFF(IFNULL(d.resolved_at, NOW()), d.created_at) as duration_days
FROM disputes d
JOIN dispute_types dt ON d.dispute_type_id = dt.id
JOIN companies c ON d.company_id = c.id
JOIN users u1 ON d.claimant_id = u1.id
JOIN users u2 ON d.respondent_id = u2.id
LEFT JOIN arbitrators a ON d.arbitrator_id = a.id
LEFT JOIN users u3 ON a.user_id = u3.id;

-- Stored Procedures
DELIMITER //

CREATE PROCEDURE update_arbitrator_stats(IN p_arbitrator_id INT)
BEGIN
    UPDATE arbitrators a
    SET 
        cases_resolved = (
            SELECT COUNT(*) 
            FROM disputes 
            WHERE arbitrator_id = p_arbitrator_id 
            AND status = 'resolved'
        ),
        average_resolution_days = (
            SELECT AVG(DATEDIFF(resolved_at, started_at))
            FROM disputes
            WHERE arbitrator_id = p_arbitrator_id
            AND status = 'resolved'
            AND resolved_at IS NOT NULL
            AND started_at IS NOT NULL
        ),
        rating = (
            SELECT AVG(rating)
            FROM reviews
            WHERE arbitrator_id = p_arbitrator_id
        ),
        total_reviews = (
            SELECT COUNT(*)
            FROM reviews
            WHERE arbitrator_id = p_arbitrator_id
        )
    WHERE id = p_arbitrator_id;
END//

DELIMITER ;

-- Triggers
DELIMITER //

CREATE TRIGGER generate_case_number 
BEFORE INSERT ON disputes
FOR EACH ROW
BEGIN
    DECLARE next_number INT;
    SELECT IFNULL(MAX(CAST(SUBSTRING(case_number, 2) AS UNSIGNED)), 0) + 1 
    INTO next_number 
    FROM disputes;
    SET NEW.case_number = CONCAT('#', LPAD(next_number, 6, '0'));
END//

CREATE TRIGGER log_dispute_status_change
AFTER UPDATE ON disputes
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO dispute_events (dispute_id, user_id, event_type, description, metadata)
        VALUES (
            NEW.id,
            NEW.updated_at,
            'status_change',
            CONCAT('Status alterado de ', OLD.status, ' para ', NEW.status),
            JSON_OBJECT('old_status', OLD.status, 'new_status', NEW.status)
        );
    END IF;
END//

DELIMITER ;