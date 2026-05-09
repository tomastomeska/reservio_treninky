-- ============================================================
-- TrainerApp v1.1.01 – Vytvoření globálních cviků pro speciální sporty
-- ============================================================

-- Důležité: bez UNIQUE klíče ON DUPLICATE KEY nefunguje jako ochrana proti duplicitám.
-- Proto používáme INSERT ... SELECT ... WHERE NOT EXISTS podle sport_type.

INSERT INTO `exercises` (`coach_id`, `name`, `is_global`, `sport_type`, `created_at`)
SELECT NULL, CONVERT(0x42C49B68206E612070C3A17365 USING utf8mb4), 1, 'run_treadmill', NOW()
FROM DUAL
WHERE NOT EXISTS (
		SELECT 1
		FROM `exercises`
		WHERE `coach_id` IS NULL
			AND `is_global` = 1
			AND `sport_type` = 'run_treadmill'
);

INSERT INTO `exercises` (`coach_id`, `name`, `is_global`, `sport_type`, `created_at`)
SELECT NULL, CONVERT(0x476F6C66 USING utf8mb4), 1, 'golf', NOW()
FROM DUAL
WHERE NOT EXISTS (
		SELECT 1
		FROM `exercises`
		WHERE `coach_id` IS NULL
			AND `is_global` = 1
			AND `sport_type` = 'golf'
);

INSERT INTO `exercises` (`coach_id`, `name`, `is_global`, `sport_type`, `created_at`)
SELECT NULL, CONVERT(0x42C49B682076656E6B75 USING utf8mb4), 1, 'run_outdoor', NOW()
FROM DUAL
WHERE NOT EXISTS (
		SELECT 1
		FROM `exercises`
		WHERE `coach_id` IS NULL
			AND `is_global` = 1
			AND `sport_type` = 'run_outdoor'
);
