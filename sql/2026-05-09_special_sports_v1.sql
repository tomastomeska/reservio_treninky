-- ============================================================
-- TrainerApp v1.1.01 – Speciální sporty (Golf, Běh venku, Běh na páse)
-- Migration file: 2026-05-09_special_sports_v1.sql
-- ============================================================

-- 1. Přidat sloupec sport_type do exercises
ALTER TABLE `exercises` ADD COLUMN `sport_type` ENUM('standard', 'golf', 'run_outdoor', 'run_treadmill') NOT NULL DEFAULT 'standard' AFTER `is_global`;

-- 2. Přidat sloupec sport_type do training_sessions (odvozeno z prvního cviku v sadě)
ALTER TABLE `training_sessions` ADD COLUMN `sport_type` ENUM('standard', 'golf', 'run_outdoor', 'run_treadmill') NOT NULL DEFAULT 'standard' AFTER `paired_session_id`;

-- 3. Přidat sport_type i do snapshotu cviků v session
ALTER TABLE `training_session_exercises` ADD COLUMN `sport_type` ENUM('standard', 'golf', 'run_outdoor', 'run_treadmill') NOT NULL DEFAULT 'standard' AFTER `exercise_name`;

-- ============================================================
-- Golf tabulky
-- ============================================================

CREATE TABLE IF NOT EXISTS `golf_sessions` (
    `id`                 INT AUTO_INCREMENT PRIMARY KEY,
    `session_id`         INT NOT NULL UNIQUE,
    `course_id`          INT NULL,
    `tee_id`             INT NULL,
    `tee_name`           VARCHAR(80) NULL,
    `course_name`        VARCHAR(255) NOT NULL,
    `num_holes`          INT NOT NULL DEFAULT 18,
    `game_type`          ENUM('training', 'tournament', 'friendly') NOT NULL DEFAULT 'training',
    `distance_km`        DECIMAL(6,2) NULL,
    `calories_burned`    INT NULL,
    `weather`            VARCHAR(100) NULL,
    `players`            TEXT NULL COMMENT 'JSON or comma-separated names',
    `handicap_before`    DECIMAL(5,1) NULL,
    `count_for_handicap` TINYINT(1) NOT NULL DEFAULT 1,
    `course_rating`      DECIMAL(4,1) NULL,
    `slope_rating`       SMALLINT NULL,
    `score_total`        INT NULL,
    `score_differential` DECIMAL(5,1) NULL,
    `handicap_after`     DECIMAL(5,1) NULL,
    `feeling`            TEXT NULL,
    `duration_minutes`   INT NULL,
    `started_at`         DATETIME NULL,
    `ended_at`           DATETIME NULL,
    `created_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`session_id`) REFERENCES `training_sessions`(`id`) ON DELETE CASCADE,
    KEY `idx_golf_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `golf_courses` (
    `id`                 INT AUTO_INCREMENT PRIMARY KEY,
    `name`               VARCHAR(255) NOT NULL,
    `location`           VARCHAR(255) NULL,
    `is_active`          TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_golf_course_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `golf_course_tees` (
    `id`                 INT AUTO_INCREMENT PRIMARY KEY,
    `course_id`          INT NOT NULL,
    `tee_name`           VARCHAR(80) NOT NULL,
    `gender`             ENUM('men','women','unisex') NOT NULL DEFAULT 'unisex',
    `par`                INT NOT NULL DEFAULT 72,
    `course_rating`      DECIMAL(4,1) NOT NULL,
    `slope_rating`       SMALLINT NOT NULL,
    `length_m`           INT NULL,
    `is_active`          TINYINT(1) NOT NULL DEFAULT 1,
    `created_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_course_tee_gender` (`course_id`, `tee_name`, `gender`),
    KEY `idx_tee_course` (`course_id`),
    FOREIGN KEY (`course_id`) REFERENCES `golf_courses`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `golf_holes` (
    `id`                 INT AUTO_INCREMENT PRIMARY KEY,
    `golf_session_id`    INT NOT NULL,
    `hole_number`        INT NOT NULL,
    `par`                INT NOT NULL,
    `score`              INT NULL,
    `notes`              TEXT NULL,
    `created_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_golf_hole` (`golf_session_id`, `hole_number`),
    FOREIGN KEY (`golf_session_id`) REFERENCES `golf_sessions`(`id`) ON DELETE CASCADE,
    KEY `idx_golf_holes_session` (`golf_session_id`, `hole_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Běh venku tabulky
-- ============================================================

CREATE TABLE IF NOT EXISTS `run_outdoor_sessions` (
    `id`                 INT AUTO_INCREMENT PRIMARY KEY,
    `session_id`         INT NOT NULL UNIQUE,
    `duration_seconds`   INT NOT NULL,
    `distance_km`        DECIMAL(6,2) NOT NULL,
    `run_type`           ENUM('free', 'intervals', 'tempo', 'race', 'recovery') NOT NULL DEFAULT 'free',
    `surface`            ENUM('asphalt', 'trail', 'mixed') NOT NULL DEFAULT 'asphalt',
    `avg_pace`           VARCHAR(10) NULL COMMENT 'mm:ss per km',
    `max_speed`          DECIMAL(5,2) NULL COMMENT 'km/h',
    `calories_burned`    INT NULL,
    `step_count`         INT NULL,
    `rpe`                INT NULL COMMENT 'Rate of Perceived Exertion 1-10',
    `tempo_variability`  DECIMAL(3,1) NULL COMMENT 'Percentage',
    `feeling`            TEXT NULL,
    `started_at`         DATETIME NULL,
    `ended_at`           DATETIME NULL,
    `created_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`session_id`) REFERENCES `training_sessions`(`id`) ON DELETE CASCADE,
    KEY `idx_run_outdoor_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `run_outdoor_splits` (
    `id`                 INT AUTO_INCREMENT PRIMARY KEY,
    `run_session_id`     INT NOT NULL,
    `km_marker`          DECIMAL(4,2) NOT NULL,
    `split_time`         VARCHAR(10) NOT NULL COMMENT 'mm:ss',
    `pace`               VARCHAR(10) NULL COMMENT 'mm:ss per km',
    `max_speed_at_km`    DECIMAL(5,2) NULL COMMENT 'km/h',
    `created_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uniq_run_split` (`run_session_id`, `km_marker`),
    FOREIGN KEY (`run_session_id`) REFERENCES `run_outdoor_sessions`(`id`) ON DELETE CASCADE,
    KEY `idx_run_splits` (`run_session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Běh na páse tabulka
-- ============================================================

CREATE TABLE IF NOT EXISTS `run_treadmill_sessions` (
    `id`                 INT AUTO_INCREMENT PRIMARY KEY,
    `session_id`         INT NOT NULL UNIQUE,
    `duration_seconds`   INT NOT NULL,
    `distance_km`        DECIMAL(6,2) NOT NULL,
    `calories_burned`    INT NULL,
    `location`           VARCHAR(255) NULL,
    `feeling`            TEXT NULL,
    `started_at`         DATETIME NULL,
    `ended_at`           DATETIME NULL,
    `created_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at`         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`session_id`) REFERENCES `training_sessions`(`id`) ON DELETE CASCADE,
    KEY `idx_run_treadmill_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Trigger: automaticky naplnit sport_type v training_sessions
-- (při vložení session vezmeme sport_type z prvního cviku v sadě)
-- ============================================================

DELIMITER //

CREATE TRIGGER `trg_training_sessions_set_sport_type`
BEFORE INSERT ON `training_sessions`
FOR EACH ROW
BEGIN
    DECLARE v_sport_type VARCHAR(50);
    SELECT e.`sport_type` INTO v_sport_type
    FROM `workout_set_exercises` wse
    JOIN `exercises` e ON e.`id` = wse.`exercise_id`
    WHERE wse.`workout_set_id` = NEW.`workout_set_id`
    ORDER BY wse.`exercise_order` ASC
    LIMIT 1;
    
    IF v_sport_type IS NOT NULL THEN
        SET NEW.`sport_type` = v_sport_type;
    END IF;
END //

DELIMITER ;
