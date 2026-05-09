-- ============================================================
-- TrainerApp v1.1.01 – Vytvoření globálních cviků pro speciální sporty
-- ============================================================

-- Vložit globální cviky pro speciální sporty (superadmin = coach_id NULL, is_global = 1)
INSERT INTO `exercises` (`coach_id`, `name`, `is_global`, `sport_type`, `created_at`) VALUES
(NULL, 'Běh na páse', 1, 'run_treadmill', NOW()),
(NULL, 'Golf', 1, 'golf', NOW()),
(NULL, 'Běh venku', 1, 'run_outdoor', NOW())
ON DUPLICATE KEY UPDATE `created_at` = NOW();
