-- ============================================================
-- Add sport_type column to training_session_exercises
-- ============================================================

ALTER TABLE `training_session_exercises` ADD COLUMN `sport_type` ENUM('standard', 'golf', 'run_outdoor', 'run_treadmill') NOT NULL DEFAULT 'standard' AFTER `exercise_name`;
