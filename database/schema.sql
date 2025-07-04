-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Tempo de geração: 04/07/2025 às 19:33
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
  `education_level` varchar(50) DEFAULT NULL,
  `course_name` varchar(255) DEFAULT NULL,
  `institution` varchar(255) DEFAULT NULL,
  `graduation_year` int(4) DEFAULT NULL,
  `professional_registration` varchar(255) DEFAULT NULL,
  `previous_cases` int(11) DEFAULT 0,
  `weekly_availability` varchar(50) DEFAULT NULL,
  `communication_preferences` text DEFAULT NULL,
  `bank_account` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`bank_account`)),
  `tax_info` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tax_info`)),
  `experience_years` int(11) DEFAULT NULL,
  `hourly_rate` decimal(10,2) DEFAULT NULL,
  `cases_resolved` int(11) DEFAULT 0,
  `success_rate` decimal(5,2) DEFAULT 0.00,
  `average_resolution_days` int(11) DEFAULT 0,
  `rating` decimal(3,2) DEFAULT 0.00,
  `total_reviews` int(11) DEFAULT 0,
  `is_available` tinyint(1) DEFAULT 1,
  `documents_verified` tinyint(1) DEFAULT 0,
  `verified_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `arbitrator_availability`
--

CREATE TABLE `arbitrator_availability` (
  `id` int(11) NOT NULL,
  `arbitrator_id` int(11) NOT NULL,
  `day_of_week` tinyint(1) NOT NULL COMMENT '0=Domingo, 6=Sábado',
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `is_available` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `arbitrator_blocks`
--

CREATE TABLE `arbitrator_blocks` (
  `id` int(11) NOT NULL,
  `arbitrator_id` int(11) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `arbitrator_cases`
--

CREATE TABLE `arbitrator_cases` (
  `id` int(11) NOT NULL,
  `arbitrator_id` int(11) NOT NULL,
  `dispute_id` int(11) NOT NULL,
  `assigned_at` datetime DEFAULT current_timestamp(),
  `accepted_at` datetime DEFAULT NULL,
  `status` enum('pending','accepted','rejected','completed','removed') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `fee_amount` decimal(10,2) DEFAULT NULL,
  `fee_paid` tinyint(1) DEFAULT 0,
  `fee_paid_at` datetime DEFAULT NULL,
  `performance_rating` int(11) DEFAULT NULL,
  `performance_review` text DEFAULT NULL,
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Acionadores `arbitrator_cases`
--
DELIMITER $$
CREATE TRIGGER `update_case_count_after_assignment` AFTER UPDATE ON `arbitrator_cases` FOR EACH ROW BEGIN
    IF NEW.status = 'accepted' AND OLD.status != 'accepted' THEN
        UPDATE arbitrators 
        SET total_cases = total_cases + 1
        WHERE id = NEW.arbitrator_id;
    ELSEIF NEW.status = 'completed' AND OLD.status != 'completed' THEN
        UPDATE arbitrators 
        SET completed_cases = completed_cases + 1,
            success_rate = (completed_cases + 1) * 100.0 / total_cases
        WHERE id = NEW.arbitrator_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `arbitrator_case_views`
--

CREATE TABLE `arbitrator_case_views` (
  `arbitrator_id` int(11) NOT NULL,
  `dispute_id` int(11) NOT NULL,
  `last_viewed_at` datetime DEFAULT current_timestamp(),
  `view_count` int(11) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `arbitrator_communications`
--

CREATE TABLE `arbitrator_communications` (
  `id` int(11) NOT NULL,
  `arbitrator_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('notification','update','reminder','alert') DEFAULT 'notification',
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `read_at` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `arbitrator_documents`
--

CREATE TABLE `arbitrator_documents` (
  `id` int(11) NOT NULL,
  `arbitrator_id` int(11) NOT NULL,
  `document_type` varchar(100) NOT NULL,
  `file_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_size` int(11) DEFAULT NULL,
  `verified` tinyint(1) DEFAULT 0,
  `verified_at` timestamp NULL DEFAULT NULL,
  `verified_by` int(11) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `arbitrator_fees`
--

CREATE TABLE `arbitrator_fees` (
  `id` int(11) NOT NULL,
  `arbitrator_id` int(11) NOT NULL,
  `dispute_id` int(11) NOT NULL,
  `fee_type` enum('hourly','fixed','percentage') NOT NULL,
  `base_amount` decimal(10,2) NOT NULL,
  `hours_worked` decimal(5,2) DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `platform_fee` decimal(10,2) DEFAULT NULL,
  `net_amount` decimal(10,2) DEFAULT NULL,
  `status` enum('pending','approved','paid','disputed') DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `arbitrator_impediments`
--

CREATE TABLE `arbitrator_impediments` (
  `id` int(11) NOT NULL,
  `arbitrator_id` int(11) NOT NULL,
  `dispute_id` int(11) NOT NULL,
  `type` enum('impediment','suspicion') NOT NULL,
  `reason` text NOT NULL,
  `declared_by` enum('arbitrator','party','system') NOT NULL,
  `declaring_party_id` int(11) DEFAULT NULL,
  `status` enum('pending','accepted','rejected') DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `reviewed_at` datetime DEFAULT NULL,
  `review_notes` text DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `arbitrator_invitations`
--

CREATE TABLE `arbitrator_invitations` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `invited_by` int(11) NOT NULL,
  `specializations` text DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` enum('pending','accepted','expired') DEFAULT 'pending',
  `accepted_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `arbitrator_performance`
--

CREATE TABLE `arbitrator_performance` (
  `id` int(11) NOT NULL,
  `arbitrator_id` int(11) NOT NULL,
  `month` int(2) NOT NULL,
  `year` int(4) NOT NULL,
  `cases_assigned` int(11) DEFAULT 0,
  `cases_completed` int(11) DEFAULT 0,
  `cases_cancelled` int(11) DEFAULT 0,
  `average_resolution_days` decimal(5,2) DEFAULT NULL,
  `average_rating` decimal(3,2) DEFAULT NULL,
  `total_reviews` int(11) DEFAULT 0,
  `on_time_completion_rate` decimal(5,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `arbitrator_preferences`
--

CREATE TABLE `arbitrator_preferences` (
  `id` int(11) NOT NULL,
  `arbitrator_id` int(11) NOT NULL,
  `preference_type` enum('dispute_type','value_range','location','complexity') NOT NULL,
  `preference_value` varchar(255) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `arbitrator_qualifications`
--

CREATE TABLE `arbitrator_qualifications` (
  `id` int(11) NOT NULL,
  `arbitrator_id` int(11) NOT NULL,
  `type` enum('education','certification','course','experience') NOT NULL,
  `title` varchar(255) NOT NULL,
  `institution` varchar(255) DEFAULT NULL,
  `date_obtained` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `description` text DEFAULT NULL,
  `document_id` int(11) DEFAULT NULL,
  `verified` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `arbitrator_rate_history`
--

CREATE TABLE `arbitrator_rate_history` (
  `id` int(11) NOT NULL,
  `arbitrator_id` int(11) NOT NULL,
  `old_rate` decimal(10,2) DEFAULT NULL,
  `new_rate` decimal(10,2) NOT NULL,
  `effective_date` date NOT NULL,
  `reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `arbitrator_reviews`
--

CREATE TABLE `arbitrator_reviews` (
  `id` int(11) NOT NULL,
  `arbitrator_id` int(11) NOT NULL,
  `dispute_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` >= 1 and `rating` <= 5),
  `punctuality` int(11) DEFAULT NULL CHECK (`punctuality` >= 1 and `punctuality` <= 5),
  `professionalism` int(11) DEFAULT NULL CHECK (`professionalism` >= 1 and `professionalism` <= 5),
  `communication` int(11) DEFAULT NULL CHECK (`communication` >= 1 and `communication` <= 5),
  `impartiality` int(11) DEFAULT NULL CHECK (`impartiality` >= 1 and `impartiality` <= 5),
  `review_text` text DEFAULT NULL,
  `is_anonymous` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Acionadores `arbitrator_reviews`
--
DELIMITER $$
CREATE TRIGGER `update_arbitrator_stats_after_review` AFTER INSERT ON `arbitrator_reviews` FOR EACH ROW BEGIN
    UPDATE arbitrators a
    SET 
        a.rating = (
            SELECT AVG(rating) 
            FROM arbitrator_reviews 
            WHERE arbitrator_id = NEW.arbitrator_id
        )
    WHERE a.id = NEW.arbitrator_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Estrutura para tabela `arbitrator_suspensions`
--

CREATE TABLE `arbitrator_suspensions` (
  `id` int(11) NOT NULL,
  `arbitrator_id` int(11) NOT NULL,
  `reason` text NOT NULL,
  `suspended_by` int(11) NOT NULL,
  `suspended_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `lifted_by` int(11) DEFAULT NULL,
  `lifted_at` timestamp NULL DEFAULT NULL,
  `lift_reason` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Estrutura para tabela `arbitrator_trainings`
--

CREATE TABLE `arbitrator_trainings` (
  `id` int(11) NOT NULL,
  `arbitrator_id` int(11) NOT NULL,
  `training_name` varchar(255) NOT NULL,
  `institution` varchar(255) DEFAULT NULL,
  `completion_date` date DEFAULT NULL,
  `certificate_path` varchar(500) DEFAULT NULL,
  `credits` int(11) DEFAULT 0,
  `verified` tinyint(1) DEFAULT 0,
  `verified_by` int(11) DEFAULT NULL,
  `verified_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

--
-- Despejando dados para a tabela `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(1, 1, 'logout', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-07-03 00:47:28'),
(2, 1, 'logout', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-07-03 00:48:17'),
(3, 1, 'logout', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-07-03 00:51:35'),
(4, 1, 'login', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-07-03 00:51:41'),
(5, 1, 'logout', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-07-03 01:03:39'),
(6, 1, 'login', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-07-03 01:03:41'),
(7, 1, 'login', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36', '2025-07-04 12:32:40'),
(8, 1, 'logout', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (X11; Linux aarch64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 CrKey/1.54.250320', '2025-07-04 12:38:35'),
(9, 1, 'login', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (X11; Linux aarch64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 CrKey/1.54.250320', '2025-07-04 12:38:44'),
(10, 1, 'login', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36', '2025-07-04 12:44:09'),
(11, 1, 'logout', 'users', 1, NULL, NULL, '::1', 'Mozilla/5.0 (X11; Linux aarch64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/137.0.0.0 Safari/537.36 CrKey/1.54.250320', '2025-07-04 12:49:31');

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
-- Despejando dados para a tabela `disputes`
--

INSERT INTO `disputes` (`id`, `case_number`, `company_id`, `dispute_type_id`, `claimant_id`, `respondent_id`, `arbitrator_id`, `status`, `title`, `description`, `claim_amount`, `property_address`, `contract_number`, `priority`, `deadline_date`, `started_at`, `resolved_at`, `resolution_summary`, `decision_document_id`, `created_at`, `updated_at`) VALUES
(1, '#000001', 1, 1, 1, 1, NULL, 'pending_arbitrator', 'Teste', 'AAAAAAAAAAAAA', 0.00, '', '', 'normal', '2025-08-03', NULL, NULL, NULL, NULL, '2025-07-04 12:48:10', '2025-07-04 12:48:10');

--
-- Acionadores `disputes`
--
DELIMITER $$
CREATE TRIGGER `after_dispute_complete_update_arbitrator_stats` AFTER UPDATE ON `disputes` FOR EACH ROW BEGIN
    IF NEW.status = 'resolved' AND OLD.status != 'resolved' AND NEW.arbitrator_id IS NOT NULL THEN
        -- Atualizar estatísticas do árbitro
        CALL update_arbitrator_stats(NEW.arbitrator_id);
        
        -- Atualizar performance mensal
        INSERT INTO arbitrator_performance (
            arbitrator_id, 
            month, 
            year, 
            cases_completed
        ) VALUES (
            NEW.arbitrator_id,
            MONTH(NOW()),
            YEAR(NOW()),
            1
        ) ON DUPLICATE KEY UPDATE
            cases_completed = cases_completed + 1;
    END IF;
END
$$
DELIMITER ;
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

--
-- Despejando dados para a tabela `dispute_events`
--

INSERT INTO `dispute_events` (`id`, `dispute_id`, `user_id`, `event_type`, `description`, `metadata`, `created_at`) VALUES
(1, 1, 1, 'dispute_created', 'Disputa criada', '{\"status\":\"pending_arbitrator\"}', '2025-07-04 12:48:10');

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
(6, 'deposit_return', 'Devolução de Caução', NULL, 'Locação', 1, '2025-06-28 16:11:43'),
(7, 'rental_residential', 'Locação Residencial', 'Disputas relacionadas a contratos de locação residencial', 'Locação', 1, '2025-07-04 13:05:59'),
(8, 'rental_commercial', 'Locação Comercial', 'Disputas relacionadas a contratos de locação comercial', 'Locação', 1, '2025-07-04 13:05:59'),
(9, 'property_sale', 'Compra e Venda', 'Disputas relacionadas a contratos de compra e venda de imóveis', 'Transação', 1, '2025-07-04 13:05:59'),
(10, 'condo_rules', 'Regulamento Condominial', 'Disputas sobre regras e regulamentos de condomínio', 'Condomínio', 1, '2025-07-04 13:05:59'),
(11, 'condo_fees', 'Taxas Condominiais', 'Disputas sobre pagamento de taxas e contribuições', 'Condomínio', 1, '2025-07-04 13:05:59'),
(12, 'construction_defects', 'Vícios de Construção', 'Disputas sobre defeitos e vícios em construções', 'Construção', 1, '2025-07-04 13:05:59'),
(13, 'construction_delay', 'Atraso na Entrega', 'Disputas sobre atrasos em obras e entregas', 'Construção', 1, '2025-07-04 13:05:59'),
(14, 'property_boundaries', 'Limites de Propriedade', 'Disputas sobre divisas e limites entre propriedades', 'Propriedade', 1, '2025-07-04 13:05:59'),
(15, 'rural_lease', 'Arrendamento Rural', 'Disputas sobre contratos de arrendamento rural', 'Rural', 1, '2025-07-04 13:05:59'),
(16, 'property_inheritance', 'Herança Imobiliária', 'Disputas sobre partilha de bens imóveis', 'Sucessão', 1, '2025-07-04 13:05:59');

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

--
-- Despejando dados para a tabela `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `data`, `is_read`, `read_at`, `created_at`) VALUES
(1, 1, 'new_dispute', 'Nova Disputa', 'Você foi incluído em uma nova disputa: Teste', '{\"dispute_id\":\"1\"}', 0, NULL, '2025-07-04 12:48:10');

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

--
-- Acionadores `reviews`
--
DELIMITER $$
CREATE TRIGGER `after_review_insert_update_arbitrator_rating` AFTER INSERT ON `reviews` FOR EACH ROW BEGIN
    -- Atualizar rating do árbitro
    UPDATE arbitrators a
    SET 
        rating = (
            SELECT AVG(rating)
            FROM reviews
            WHERE arbitrator_id = NEW.arbitrator_id
        ),
        total_reviews = (
            SELECT COUNT(*)
            FROM reviews
            WHERE arbitrator_id = NEW.arbitrator_id
        )
    WHERE id = NEW.arbitrator_id;
END
$$
DELIMITER ;

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
(1, 1, 'teste@teste.com', 'teste', 'Teste', 'Usuário', NULL, NULL, 'manager', 1, 1, NULL, 0, NULL, 0, '2025-07-03 00:43:23', NULL, NULL, '2025-07-04 12:44:09', '2025-06-28 18:32:13', '2025-07-04 12:44:09');

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
  ADD KEY `idx_specializations` (`specializations`(768)),
  ADD KEY `idx_arbitrator_availability` (`is_available`,`documents_verified`),
  ADD KEY `idx_arbitrator_rating` (`rating`,`cases_resolved`),
  ADD KEY `idx_arbitrator_specializations` (`specializations`(255)),
  ADD KEY `idx_arbitrator_search` (`is_available`,`verified_at`,`rating`);

--
-- Índices de tabela `arbitrator_availability`
--
ALTER TABLE `arbitrator_availability`
  ADD PRIMARY KEY (`id`),
  ADD KEY `arbitrator_id` (`arbitrator_id`);

--
-- Índices de tabela `arbitrator_blocks`
--
ALTER TABLE `arbitrator_blocks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `arbitrator_id` (`arbitrator_id`),
  ADD KEY `dates` (`start_date`,`end_date`);

--
-- Índices de tabela `arbitrator_cases`
--
ALTER TABLE `arbitrator_cases`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_assignment` (`arbitrator_id`,`dispute_id`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_dispute` (`dispute_id`);

--
-- Índices de tabela `arbitrator_case_views`
--
ALTER TABLE `arbitrator_case_views`
  ADD PRIMARY KEY (`arbitrator_id`,`dispute_id`),
  ADD KEY `dispute_id` (`dispute_id`);

--
-- Índices de tabela `arbitrator_communications`
--
ALTER TABLE `arbitrator_communications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_arbitrator_unread` (`arbitrator_id`,`read_at`),
  ADD KEY `idx_type_priority` (`type`,`priority`);

--
-- Índices de tabela `arbitrator_documents`
--
ALTER TABLE `arbitrator_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `arbitrator_id` (`arbitrator_id`),
  ADD KEY `verified_by` (`verified_by`);

--
-- Índices de tabela `arbitrator_fees`
--
ALTER TABLE `arbitrator_fees`
  ADD PRIMARY KEY (`id`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `idx_arbitrator_status` (`arbitrator_id`,`status`),
  ADD KEY `idx_dispute` (`dispute_id`),
  ADD KEY `idx_fee_payment` (`arbitrator_id`,`status`,`paid_at`);

--
-- Índices de tabela `arbitrator_impediments`
--
ALTER TABLE `arbitrator_impediments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `dispute_id` (`dispute_id`),
  ADD KEY `declaring_party_id` (`declaring_party_id`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `idx_arbitrator_dispute` (`arbitrator_id`,`dispute_id`),
  ADD KEY `idx_status` (`status`);

--
-- Índices de tabela `arbitrator_invitations`
--
ALTER TABLE `arbitrator_invitations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `email` (`email`),
  ADD KEY `invited_by` (`invited_by`),
  ADD KEY `status` (`status`);

--
-- Índices de tabela `arbitrator_performance`
--
ALTER TABLE `arbitrator_performance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `arbitrator_month_year` (`arbitrator_id`,`month`,`year`),
  ADD KEY `arbitrator_id` (`arbitrator_id`);

--
-- Índices de tabela `arbitrator_preferences`
--
ALTER TABLE `arbitrator_preferences`
  ADD PRIMARY KEY (`id`),
  ADD KEY `arbitrator_id` (`arbitrator_id`),
  ADD KEY `preference_type` (`preference_type`);

--
-- Índices de tabela `arbitrator_qualifications`
--
ALTER TABLE `arbitrator_qualifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `arbitrator_id` (`arbitrator_id`),
  ADD KEY `document_id` (`document_id`);

--
-- Índices de tabela `arbitrator_rate_history`
--
ALTER TABLE `arbitrator_rate_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `arbitrator_id` (`arbitrator_id`),
  ADD KEY `effective_date` (`effective_date`);

--
-- Índices de tabela `arbitrator_reviews`
--
ALTER TABLE `arbitrator_reviews`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_review` (`dispute_id`,`reviewer_id`),
  ADD KEY `reviewer_id` (`reviewer_id`),
  ADD KEY `idx_arbitrator_rating` (`arbitrator_id`,`rating`),
  ADD KEY `idx_review_search` (`arbitrator_id`,`created_at`);

--
-- Índices de tabela `arbitrator_suspensions`
--
ALTER TABLE `arbitrator_suspensions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `arbitrator_id` (`arbitrator_id`),
  ADD KEY `suspended_by` (`suspended_by`),
  ADD KEY `lifted_by` (`lifted_by`);

--
-- Índices de tabela `arbitrator_trainings`
--
ALTER TABLE `arbitrator_trainings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `verified_by` (`verified_by`),
  ADD KEY `idx_arbitrator` (`arbitrator_id`),
  ADD KEY `idx_verified` (`verified`);

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
-- AUTO_INCREMENT de tabela `arbitrator_availability`
--
ALTER TABLE `arbitrator_availability`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `arbitrator_blocks`
--
ALTER TABLE `arbitrator_blocks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `arbitrator_cases`
--
ALTER TABLE `arbitrator_cases`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `arbitrator_communications`
--
ALTER TABLE `arbitrator_communications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `arbitrator_documents`
--
ALTER TABLE `arbitrator_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `arbitrator_fees`
--
ALTER TABLE `arbitrator_fees`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `arbitrator_impediments`
--
ALTER TABLE `arbitrator_impediments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `arbitrator_invitations`
--
ALTER TABLE `arbitrator_invitations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `arbitrator_performance`
--
ALTER TABLE `arbitrator_performance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `arbitrator_preferences`
--
ALTER TABLE `arbitrator_preferences`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `arbitrator_qualifications`
--
ALTER TABLE `arbitrator_qualifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `arbitrator_rate_history`
--
ALTER TABLE `arbitrator_rate_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `arbitrator_reviews`
--
ALTER TABLE `arbitrator_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `arbitrator_suspensions`
--
ALTER TABLE `arbitrator_suspensions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `arbitrator_trainings`
--
ALTER TABLE `arbitrator_trainings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT de tabela `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT de tabela `companies`
--
ALTER TABLE `companies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT de tabela `disputes`
--
ALTER TABLE `disputes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `dispute_events`
--
ALTER TABLE `dispute_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT de tabela `dispute_types`
--
ALTER TABLE `dispute_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
-- Restrições para tabelas `arbitrator_availability`
--
ALTER TABLE `arbitrator_availability`
  ADD CONSTRAINT `arbitrator_availability_ibfk_1` FOREIGN KEY (`arbitrator_id`) REFERENCES `arbitrators` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `arbitrator_blocks`
--
ALTER TABLE `arbitrator_blocks`
  ADD CONSTRAINT `arbitrator_blocks_ibfk_1` FOREIGN KEY (`arbitrator_id`) REFERENCES `arbitrators` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `arbitrator_cases`
--
ALTER TABLE `arbitrator_cases`
  ADD CONSTRAINT `arbitrator_cases_ibfk_1` FOREIGN KEY (`arbitrator_id`) REFERENCES `arbitrators` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `arbitrator_cases_ibfk_2` FOREIGN KEY (`dispute_id`) REFERENCES `disputes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `arbitrator_cases_ibfk_3` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `arbitrator_case_views`
--
ALTER TABLE `arbitrator_case_views`
  ADD CONSTRAINT `arbitrator_case_views_ibfk_1` FOREIGN KEY (`arbitrator_id`) REFERENCES `arbitrators` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `arbitrator_case_views_ibfk_2` FOREIGN KEY (`dispute_id`) REFERENCES `disputes` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `arbitrator_communications`
--
ALTER TABLE `arbitrator_communications`
  ADD CONSTRAINT `arbitrator_communications_ibfk_1` FOREIGN KEY (`arbitrator_id`) REFERENCES `arbitrators` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `arbitrator_communications_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `arbitrator_documents`
--
ALTER TABLE `arbitrator_documents`
  ADD CONSTRAINT `arbitrator_documents_ibfk_1` FOREIGN KEY (`arbitrator_id`) REFERENCES `arbitrators` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `arbitrator_documents_ibfk_2` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`);

--
-- Restrições para tabelas `arbitrator_fees`
--
ALTER TABLE `arbitrator_fees`
  ADD CONSTRAINT `arbitrator_fees_ibfk_1` FOREIGN KEY (`arbitrator_id`) REFERENCES `arbitrators` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `arbitrator_fees_ibfk_2` FOREIGN KEY (`dispute_id`) REFERENCES `disputes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `arbitrator_fees_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `arbitrator_impediments`
--
ALTER TABLE `arbitrator_impediments`
  ADD CONSTRAINT `arbitrator_impediments_ibfk_1` FOREIGN KEY (`arbitrator_id`) REFERENCES `arbitrators` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `arbitrator_impediments_ibfk_2` FOREIGN KEY (`dispute_id`) REFERENCES `disputes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `arbitrator_impediments_ibfk_3` FOREIGN KEY (`declaring_party_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `arbitrator_impediments_ibfk_4` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Restrições para tabelas `arbitrator_invitations`
--
ALTER TABLE `arbitrator_invitations`
  ADD CONSTRAINT `arbitrator_invitations_ibfk_1` FOREIGN KEY (`invited_by`) REFERENCES `users` (`id`);

--
-- Restrições para tabelas `arbitrator_performance`
--
ALTER TABLE `arbitrator_performance`
  ADD CONSTRAINT `arbitrator_performance_ibfk_1` FOREIGN KEY (`arbitrator_id`) REFERENCES `arbitrators` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `arbitrator_preferences`
--
ALTER TABLE `arbitrator_preferences`
  ADD CONSTRAINT `arbitrator_preferences_ibfk_1` FOREIGN KEY (`arbitrator_id`) REFERENCES `arbitrators` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `arbitrator_qualifications`
--
ALTER TABLE `arbitrator_qualifications`
  ADD CONSTRAINT `arbitrator_qualifications_ibfk_1` FOREIGN KEY (`arbitrator_id`) REFERENCES `arbitrators` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `arbitrator_qualifications_ibfk_2` FOREIGN KEY (`document_id`) REFERENCES `arbitrator_documents` (`id`);

--
-- Restrições para tabelas `arbitrator_rate_history`
--
ALTER TABLE `arbitrator_rate_history`
  ADD CONSTRAINT `arbitrator_rate_history_ibfk_1` FOREIGN KEY (`arbitrator_id`) REFERENCES `arbitrators` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `arbitrator_reviews`
--
ALTER TABLE `arbitrator_reviews`
  ADD CONSTRAINT `arbitrator_reviews_ibfk_1` FOREIGN KEY (`arbitrator_id`) REFERENCES `arbitrators` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `arbitrator_reviews_ibfk_2` FOREIGN KEY (`dispute_id`) REFERENCES `disputes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `arbitrator_reviews_ibfk_3` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Restrições para tabelas `arbitrator_suspensions`
--
ALTER TABLE `arbitrator_suspensions`
  ADD CONSTRAINT `arbitrator_suspensions_ibfk_1` FOREIGN KEY (`arbitrator_id`) REFERENCES `arbitrators` (`id`),
  ADD CONSTRAINT `arbitrator_suspensions_ibfk_2` FOREIGN KEY (`suspended_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `arbitrator_suspensions_ibfk_3` FOREIGN KEY (`lifted_by`) REFERENCES `users` (`id`);

--
-- Restrições para tabelas `arbitrator_trainings`
--
ALTER TABLE `arbitrator_trainings`
  ADD CONSTRAINT `arbitrator_trainings_ibfk_1` FOREIGN KEY (`arbitrator_id`) REFERENCES `arbitrators` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `arbitrator_trainings_ibfk_2` FOREIGN KEY (`verified_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

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
