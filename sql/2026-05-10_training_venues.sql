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
    CONSTRAINT `fk_training_venue_coach`
        FOREIGN KEY (`created_by_coach_id`) REFERENCES `coaches`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;