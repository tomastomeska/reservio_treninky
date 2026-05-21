-- Hotfix: doplneni historickych sportovist do katalogu training_venues
-- Bezpecne pro existujici DB (INSERT IGNORE + DISTINCT).

SET NAMES utf8mb4;

INSERT IGNORE INTO `training_venues` (`name`, `is_active`)
SELECT DISTINCT TRIM(`location`) AS `name`, 1
FROM `training_sessions`
WHERE `location` IS NOT NULL
  AND TRIM(`location`) <> '';

INSERT IGNORE INTO `training_venues` (`name`, `is_active`)
SELECT DISTINCT TRIM(`location`) AS `name`, 1
FROM `run_treadmill_sessions`
WHERE `location` IS NOT NULL
  AND TRIM(`location`) <> '';
