-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 03/07/2025 às 02:28
-- Versão do servidor: 10.4.32-MariaDB
-- Versão do PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Banco de dados: `arbitrivm_new`
--

DELIMITER $$
--
-- Procedimentos
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `update_arbitrator_stats` (IN `p_arbitrator_id` INT)   BEGIN
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
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `arbitrators`
--

CREATE TABLE `arbitrators` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `registration_number` varchar(50) NOT NULL,
  `specializations` text DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `experience_years` int(11) DEFAULT NULL,
  `hourly_rate` decimal(10,2) DEFAULT NULL,
  `cases_resolved` int(11) DEFAULT 0,
  `success_rate` decimal(5,2) DEFAULT 0.00,
  `average_resolution_days` int(11) DEFAULT 0,
  `rating` decimal(3,2) DEFAULT 0.00,
  `total_reviews` int(11) DEFAULT 0,
  `is_available` tinyint(1) DEFAULT 1,
  `documents_verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `old_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`old_values`)),
  `new_values` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`new_values`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `companies`
--

CREATE TABLE `companies` (
  `id` int(11) NOT NULL,
  `cnpj` varchar(18) NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `trade_name` varchar(255) DEFAULT NULL,
  `company_type` enum('real_estate','construction','condominium','other') NOT NULL,
  `address` text DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `state` varchar(2) DEFAULT NULL,
  `zip_code` varchar(9) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `website` varchar(255) DEFAULT NULL,
  `subscription_plan` enum('basic','professional','enterprise') DEFAULT 'basic',
  `subscription_status` enum('active','inactive','suspended') DEFAULT 'active',
  `subscription_expires_at` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `companies`
--

INSERT INTO `companies` (`id`, `cnpj`, `company_name`, `trade_name`, `company_type`, `address`, `city`, `state`, `zip_code`, `phone`, `email`, `website`, `subscription_plan`, `subscription_status`, `subscription_expires_at`, `created_at`, `updated_at`) VALUES
(1, '12.345.678/0001-90', 'Empresa Teste', NULL, 'real_estate', NULL, NULL, NULL, NULL, NULL, 'teste@empresa.com', NULL, 'basic', 'active', NULL, '2025-06-28 18:32:13', '2025-06-28 18:32:13'),
(2, '11.111.111/0001-11', 'Empresa Teste', NULL, 'real_estate', NULL, NULL, NULL, NULL, NULL, 'teste@empresa.com', NULL, 'basic', 'active', NULL, '2025-07-02 23:59:38', '2025-07-02 23:59:38');

-- --------------------------------------------------------

--
-- Estrutura para tabela `disputes`
--

CREATE TABLE `disputes` (
  `id` int(11) NOT NULL,
  `case_number` varchar(20) NOT NULL,
  `company_id` int(11) NOT NULL,
  `dispute_type_id` int(11) NOT NULL,
  `claimant_id` int(11) NOT NULL,
  `respondent_id` int(11) NOT NULL,
  `arbitrator_id` int(11) DEFAULT NULL,
  `status` enum('draft','pending_arbitrator','pending_acceptance','active','on_hold','resolved','cancelled') NOT NULL DEFAULT 'draft',
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `claim_amount` decimal(12,2) DEFAULT NULL,
  `property_address` text DEFAULT NULL,
  `contract_number` varchar(100) DEFAULT NULL,
  `priority` enum('low','normal','high','urgent') DEFAULT 'normal',
  `deadline_date` date DEFAULT NULL,
  `started_at` timestamp NULL DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolution_summary` text DEFAULT NULL,
  `decision_document_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Acionadores `disputes`
--
DELIMITER $$
CREATE TRIGGER `generate_case_number` BEFORE INSERT ON `disputes` FOR EACH ROW BEGIN
    DECLARE next_number INT;
    SELECT IFNULL(MAX(CAST(SUBSTRING(case_number, 2) AS UNSIGNED)), 0) + 1 
    INTO next_number 
    FROM disputes;
    SET NEW.case_number = CONCAT('#', LPAD(next_number, 6, '0'));
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `log_dispute_status_change` AFTER UPDATE ON `disputes` FOR EACH ROW BEGIN
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
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `dispute_events`
--

CREATE TABLE `dispute_events` (
  `id` int(11) NOT NULL,
  `dispute_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `event_type` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura stand-in para view `dispute_summary`
-- (Veja abaixo para a visão atual)
--
CREATE TABLE `dispute_summary` (
`id` int(11)
,`case_number` varchar(20)
,`title` varchar(255)
,`status` enum('draft','pending_arbitrator','pending_acceptance','active','on_hold','resolved','cancelled')
,`created_at` timestamp
,`resolved_at` timestamp
,`dispute_type` varchar(255)
,`company_name` varchar(255)
,`claimant_name` varchar(201)
,`respondent_name` varchar(201)
,`arbitrator_name` varchar(201)
,`claim_amount` decimal(12,2)
,`duration_days` int(7)
);

-- --------------------------------------------------------

--
-- Estrutura para tabela `dispute_types`
--

CREATE TABLE `dispute_types` (
  `id` int(11) NOT NULL,
  `code` varchar(50) NOT NULL,
  `name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `dispute_types`
--

INSERT INTO `dispute_types` (`id`, `code`, `name`, `description`, `category`, `is_active`, `created_at`) VALUES
(1, 'property_damage', 'Danos ao Imóvel', NULL, 'Locação', 1, '2025-06-28 16:11:43'),
(2, 'condo_infraction', 'Infração Condominial', NULL, 'Condomínio', 1, '2025-06-28 16:11:43'),
(3, 'rent_payment', 'Inadimplência de Aluguel', NULL, 'Locação', 1, '2025-06-28 16:11:43'),
(4, 'contract_breach', 'Quebra de Contrato', NULL, 'Geral', 1, '2025-06-28 16:11:43'),
(5, 'maintenance_dispute', 'Disputa sobre Manutenção', NULL, 'Manutenção', 1, '2025-06-28 16:11:43'),
(6, 'deposit_return', 'Devolução de Caução', NULL, 'Locação', 1, '2025-06-28 16:11:43');

-- --------------------------------------------------------

--
-- Estrutura para tabela `documents`
--

CREATE TABLE `documents` (
  `id` int(11) NOT NULL,
  `dispute_id` int(11) NOT NULL,
  `uploaded_by` int(11) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) NOT NULL,
  `file_type` varchar(100) DEFAULT NULL,
  `document_type` enum('contract','evidence','report','photo','video','audio','decision','other') NOT NULL,
  `description` text DEFAULT NULL,
  `is_confidential` tinyint(1) DEFAULT 0,
  `hash_verification` varchar(64) DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `document_templates`
--

CREATE TABLE `document_templates` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `type` varchar(50) NOT NULL,
  `content` text NOT NULL,
  `variables` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`variables`)),
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `dispute_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `is_system_message` tinyint(1) DEFAULT 0,
  `attachments` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`attachments`)),
  `read_by` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`read_by`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` varchar(50) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`data`)),
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `payments`
--

CREATE TABLE `payments` (
  `id` int(11) NOT NULL,
  `company_id` int(11) NOT NULL,
  `dispute_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_type` enum('subscription','arbitration_fee','additional_service') NOT NULL,
  `payment_method` enum('credit_card','debit_card','bank_transfer','pix','boleto') NOT NULL,
  `status` enum('pending','processing','completed','failed','refunded') NOT NULL DEFAULT 'pending',
  `gateway_transaction_id` varchar(255) DEFAULT NULL,
  `gateway_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`gateway_response`)),
  `paid_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `dispute_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `arbitrator_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `company_id` int(11) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `cpf` varchar(14) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','manager','user','arbitrator','party') NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `is_verified` tinyint(1) DEFAULT 0,
  `verification_token` varchar(255) DEFAULT NULL,
  `two_factor_enabled` tinyint(1) DEFAULT 0,
  `two_factor_secret` varchar(255) DEFAULT NULL,
  `login_attempts` int(11) DEFAULT 0,
  `last_attempt_at` timestamp NULL DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expires` timestamp NULL DEFAULT NULL,
  `last_login_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Despejando dados para a tabela `users`
--

INSERT INTO `users` (`id`, `company_id`, `email`, `password_hash`, `first_name`, `last_name`, `cpf`, `phone`, `role`, `is_active`, `is_verified`, `verification_token`, `two_factor_enabled`, `two_factor_secret`, `login_attempts`, `last_attempt_at`, `reset_token`, `reset_token_expires`, `last_login_at`, `created_at`, `updated_at`) VALUES
(1, 1, 'teste@teste.com', 'teste', 'Teste', 'Usuário', NULL, NULL, 'manager', 1, 1, NULL, 0, NULL, 2, '2025-07-03 00:24:07', NULL, NULL, NULL, '2025-06-28 18:32:13', '2025-07-03 00:24:07');

-- --------------------------------------------------------

--
-- Estrutura para tabela `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `refresh_token` varchar(500) NOT NULL,
  `device_info` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`device_info`)),
  `ip_address` varchar(45) DEFAULT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para view `dispute_summary`
--
DROP TABLE IF EXISTS `dispute_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `dispute_summary`  AS SELECT `d`.`id` AS `id`, `d`.`case_number` AS `case_number`, `d`.`title` AS `title`, `d`.`status` AS `status`, `d`.`created_at` AS `created_at`, `d`.`resolved_at` AS `resolved_at`, `dt`.`name` AS `dispute_type`, `c`.`company_name` AS `company_name`, concat(`u1`.`first_name`,' ',`u1`.`last_name`) AS `claimant_name`, concat(`u2`.`first_name`,' ',`u2`.`last_name`) AS `respondent_name`, concat(`u3`.`first_name`,' ',`u3`.`last_name`) AS `arbitrator_name`, `d`.`claim_amount` AS `claim_amount`, to_days(ifnull(`d`.`resolved_at`,current_timestamp())) - to_days(`d`.`created_at`) AS `duration_days` FROM ((((((`disputes` `d` join `dispute_types` `dt` on(`d`.`dispute_type_id` = `dt`.`id`)) join `companies` `c` on(`d`.`company_id` = `c`.`id`)) join `users` `u1` on(`d`.`claimant_id` = `u1`.`id`)) join `users` `u2` on(`d`.`respondent_id` = `u2`.`id`)) left join `arbitrators` `a` on(`d`.`arbitrator_id` = `a`.`id`)) left join `users` `u3` on(`a`.`user_id` = `u3`.`id`)) ;

--
-- Índices para tabelas despejadas
--

--
-- Índices de tabela `arbitrators`
--
ALTER TABLE `arbitrators`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `registration_number` (`registration_number`),
  ADD KEY `idx_available` (`is_available`),
  ADD KEY `idx_rating` (`rating`),
  ADD KEY `idx_specializations` (`specializations`(768));

--
-- Índices de tabela `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Índices de tabela `companies`
--
ALTER TABLE `companies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `cnpj` (`cnpj`),
  ADD KEY `idx_cnpj` (`cnpj`),
  ADD KEY `idx_company_type` (`company_type`),
  ADD KEY `idx_subscription_status` (`subscription_status`);

--
-- Índices de tabela `disputes`
--
ALTER TABLE `disputes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `case_number` (`case_number`),
  ADD KEY `dispute_type_id` (`dispute_type_id`),
  ADD KEY `claimant_id` (`claimant_id`),
  ADD KEY `respondent_id` (`respondent_id`),
  ADD KEY `idx_case_number` (`case_number`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_company` (`company_id`),
  ADD KEY `idx_arbitrator` (`arbitrator_id`),
  ADD KEY `idx_dates` (`created_at`,`resolved_at`);

--
-- Índices de tabela `dispute_events`
--
ALTER TABLE `dispute_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_dispute` (`dispute_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Índices de tabela `dispute_types`
--
ALTER TABLE `dispute_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`);

--
-- Índices de tabela `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_dispute` (`dispute_id`),
  ADD KEY `idx_type` (`document_type`),
  ADD KEY `idx_uploaded_by` (`uploaded_by`);

--
-- Índices de tabela `document_templates`
--
ALTER TABLE `document_templates`
  ADD PRIMARY KEY (`id`);

--
-- Índices de tabela `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_dispute` (`dispute_id`),
  ADD KEY `idx_sender` (`sender_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Índices de tabela `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_read` (`is_read`),
  ADD KEY `idx_created` (`created_at`);

--
-- Índices de tabela `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `dispute_id` (`dispute_id`),
  ADD KEY `idx_company` (`company_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_type` (`payment_type`);

--
-- Índices de tabela `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_review` (`dispute_id`,`reviewer_id`),
  ADD KEY `reviewer_id` (`reviewer_id`),
  ADD KEY `idx_arbitrator` (`arbitrator_id`);

--
-- Índices de tabela `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `cpf` (`cpf`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_company` (`company_id`),
  ADD KEY `idx_login_attempts` (`email`,`login_attempts`),
  ADD KEY `idx_reset_token` (`reset_token`);

--
-- Índices de tabela `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `refresh_token` (`refresh_token`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_token` (`refresh_token`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- AUTO_INCREMENT para tabelas despejadas
--

--
-- AUTO_INCREMENT de tabela `arbitrators`
--
ALTER TABLE `arbitrators`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `companies`
--
ALTER TABLE `companies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `disputes`
--
ALTER TABLE `disputes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `dispute_events`
--
ALTER TABLE `dispute_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `dispute_types`
--
ALTER TABLE `dispute_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT de tabela `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `document_templates`
--
ALTER TABLE `document_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `payments`
--
ALTER TABLE `payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Restrições para tabelas despejadas
--

--
-- Restrições para tabelas `arbitrators`
--
ALTER TABLE `arbitrators`
  ADD CONSTRAINT `arbitrators_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `audit_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `disputes`
--
ALTER TABLE `disputes`
  ADD CONSTRAINT `disputes_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  ADD CONSTRAINT `disputes_ibfk_2` FOREIGN KEY (`dispute_type_id`) REFERENCES `dispute_types` (`id`),
  ADD CONSTRAINT `disputes_ibfk_3` FOREIGN KEY (`claimant_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `disputes_ibfk_4` FOREIGN KEY (`respondent_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `disputes_ibfk_5` FOREIGN KEY (`arbitrator_id`) REFERENCES `arbitrators` (`id`);

--
-- Restrições para tabelas `dispute_events`
--
ALTER TABLE `dispute_events`
  ADD CONSTRAINT `dispute_events_ibfk_1` FOREIGN KEY (`dispute_id`) REFERENCES `disputes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `dispute_events_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Restrições para tabelas `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`dispute_id`) REFERENCES `disputes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `documents_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Restrições para tabelas `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`dispute_id`) REFERENCES `disputes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`);

--
-- Restrições para tabelas `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`),
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`dispute_id`) REFERENCES `disputes` (`id`);

--
-- Restrições para tabelas `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`dispute_id`) REFERENCES `disputes` (`id`),
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `reviews_ibfk_3` FOREIGN KEY (`arbitrator_id`) REFERENCES `arbitrators` (`id`);

--
-- Restrições para tabelas `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
