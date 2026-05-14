-- ============================================================
-- TrainerApp - Databázové schéma
-- ============================================================

CREATE DATABASE IF NOT EXISTS `marcelmiler`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE `marcelmiler`;

-- Trenéři (přihlášení uživatelé)
CREATE TABLE IF NOT EXISTS `coaches` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `username`   VARCHAR(100) NOT NULL UNIQUE,
    `password`   VARCHAR(255) NOT NULL,
    `name`       VARCHAR(200),
    `email`      VARCHAR(255),
    `last_login` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sportovci
CREATE TABLE IF NOT EXISTS `athletes` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `coach_id`   INT NOT NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `last_name`  VARCHAR(100) NOT NULL,
    `birth_date` DATE,
    `email`      VARCHAR(255),
    `notes`      TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`coach_id`) REFERENCES `coaches`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cviky (název cviku)
CREATE TABLE IF NOT EXISTS `exercises` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `coach_id`   INT NULL,
    `name`       VARCHAR(200) NOT NULL,
    `photo`      VARCHAR(255) NULL,
    `is_global`  TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`coach_id`) REFERENCES `coaches`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Sady (tréninkové plány – šablony)
CREATE TABLE IF NOT EXISTS `workout_sets` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `coach_id`   INT NOT NULL,
    `name`       VARCHAR(200) NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`coach_id`) REFERENCES `coaches`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Cviky v sadách
CREATE TABLE IF NOT EXISTS `workout_set_exercises` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `workout_set_id`  INT NOT NULL,
    `exercise_id`     INT NOT NULL,
    `exercise_order`  INT NOT NULL DEFAULT 1,
    FOREIGN KEY (`workout_set_id`) REFERENCES `workout_sets`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`exercise_id`)    REFERENCES `exercises`(`id`)    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tréninkové session (konkrétní tréninky)
CREATE TABLE IF NOT EXISTS `training_sessions` (
    `id`                  INT AUTO_INCREMENT PRIMARY KEY,
    `athlete_id`          INT NOT NULL,
    `workout_set_id`      INT NOT NULL,
    `location`            VARCHAR(300),
    `notes`               TEXT,
    `training_photo`      VARCHAR(255),
    `started_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `completed_at`        TIMESTAMP NULL DEFAULT NULL,
    `deleted_by_coach_at` DATETIME NULL DEFAULT NULL,
    `deleted_by_coach_id` INT NULL DEFAULT NULL,
    `paired_session_id`   INT NULL DEFAULT NULL,
    FOREIGN KEY (`athlete_id`)        REFERENCES `athletes`(`id`)        ON DELETE CASCADE,
    FOREIGN KEY (`workout_set_id`)    REFERENCES `workout_sets`(`id`)    ON DELETE RESTRICT,
    FOREIGN KEY (`paired_session_id`) REFERENCES `paired_sessions`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Galerie fotek k tréninku (více fotek)
CREATE TABLE IF NOT EXISTS `training_session_photos` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `session_id` INT NOT NULL,
    `filename`   VARCHAR(255) NOT NULL,
    `sort_order` INT NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_training_session_photos_session` (`session_id`),
    FOREIGN KEY (`session_id`) REFERENCES `training_sessions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Katalog sportovišť a míst tréninku
CREATE TABLE IF NOT EXISTS `training_venues` (
    `id`                  INT AUTO_INCREMENT PRIMARY KEY,
    `name`                VARCHAR(255) NOT NULL,
    `address`             VARCHAR(255) NULL,
    `note`                TEXT NULL,
    `is_active`           TINYINT(1) NOT NULL DEFAULT 1,
    `created_by_coach_id` INT NULL,
    `created_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_training_venue_name` (`name`),
    KEY `idx_training_venues_active_name` (`is_active`, `name`),
    FOREIGN KEY (`created_by_coach_id`) REFERENCES `coaches`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Snapshot cviků v konkrétní session (historie nezávislá na změnách v sadě)
CREATE TABLE IF NOT EXISTS `training_session_exercises` (
    `id`             INT AUTO_INCREMENT PRIMARY KEY,
    `session_id`     INT NOT NULL,
    `exercise_id`    INT NOT NULL,
    `exercise_order` INT NOT NULL,
    `exercise_name`  VARCHAR(200) NOT NULL,
    UNIQUE KEY `uniq_session_exercise` (`session_id`, `exercise_id`),
    KEY `idx_session_order` (`session_id`, `exercise_order`),
    FOREIGN KEY (`session_id`) REFERENCES `training_sessions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`exercise_id`) REFERENCES `exercises`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Série v tréninku (konkrétní data: váha, opakování, dopomoc)
CREATE TABLE IF NOT EXISTS `session_series` (
    `id`              INT AUTO_INCREMENT PRIMARY KEY,
    `session_id`      INT NOT NULL,
    `exercise_id`     INT NOT NULL,
    `series_order`    INT NOT NULL,
    `weight`          DECIMAL(7,2) DEFAULT 0,
    `reps`            INT DEFAULT 0,
    `assistance_reps` INT DEFAULT 0,
    `created_at`      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`session_id`)  REFERENCES `training_sessions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`exercise_id`) REFERENCES `exercises`(`id`)         ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Superadministrátoři (spravují trenéry)
CREATE TABLE IF NOT EXISTS `superadmins` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `username`   VARCHAR(100) NOT NULL UNIQUE,
    `password`   VARCHAR(255) NOT NULL,
    `name`       VARCHAR(200),
    `email`      VARCHAR(255),
    `last_login` DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Skupiny párových tréninků (2+ sportovci ve stejném čase)
CREATE TABLE IF NOT EXISTS `paired_sessions` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `coach_id`   INT NOT NULL,
    `started_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`coach_id`) REFERENCES `coaches`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Výzvy k zadání tělesné hmotnosti sportovcem přes bezpečný odkaz
CREATE TABLE IF NOT EXISTS `athlete_weight_invites` (
    `id`         INT AUTO_INCREMENT PRIMARY KEY,
    `athlete_id` INT NOT NULL,
    `coach_id`   INT NOT NULL,
    `email`      VARCHAR(255) NOT NULL,
    `token_hash` CHAR(64) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used_at`    DATETIME NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_weight_invite_token_hash` (`token_hash`),
    KEY `idx_weight_invites_athlete` (`athlete_id`),
    KEY `idx_weight_invites_expires` (`expires_at`),
    FOREIGN KEY (`athlete_id`) REFERENCES `athletes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`coach_id`) REFERENCES `coaches`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Historie tělesné hmotnosti sportovce
CREATE TABLE IF NOT EXISTS `athlete_weight_logs` (
    `id`                  INT AUTO_INCREMENT PRIMARY KEY,
    `athlete_id`          INT NOT NULL,
    `measured_at`         DATE NOT NULL,
    `weight_kg`           DECIMAL(5,2) NOT NULL,
    `source`              ENUM('coach','athlete_link') NOT NULL DEFAULT 'coach',
    `invite_id`           INT NULL,
    `created_by_coach_id` INT NULL,
    `created_at`          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_weight_logs_athlete_date` (`athlete_id`, `measured_at`),
    KEY `idx_weight_logs_invite` (`invite_id`),
    FOREIGN KEY (`athlete_id`) REFERENCES `athletes`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`invite_id`) REFERENCES `athlete_weight_invites`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`created_by_coach_id`) REFERENCES `coaches`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
