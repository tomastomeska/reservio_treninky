-- ============================================================
-- TrainerApp v1.1.01 – Oprava globálních speciálních cviků
-- - sloučení duplicit podle sport_type
-- - zachování referencí v sadách/snapshotech/sériích
-- - oprava názvů s diakritikou
-- ============================================================

START TRANSACTION;

CREATE TEMPORARY TABLE tmp_special_keep AS
SELECT sport_type, MIN(id) AS keep_id
FROM exercises
WHERE coach_id IS NULL
  AND is_global = 1
  AND sport_type IN ('golf', 'run_outdoor', 'run_treadmill')
GROUP BY sport_type;

-- Oprava názvů (hex literály zabrání problémům s kódováním při importu)
UPDATE exercises
SET name = CONVERT(0x476F6C66 USING utf8mb4)
WHERE coach_id IS NULL AND is_global = 1 AND sport_type = 'golf';

UPDATE exercises
SET name = CONVERT(0x42C49B682076656E6B75 USING utf8mb4)
WHERE coach_id IS NULL AND is_global = 1 AND sport_type = 'run_outdoor';

UPDATE exercises
SET name = CONVERT(0x42C49B68206E612070C3A17365 USING utf8mb4)
WHERE coach_id IS NULL AND is_global = 1 AND sport_type = 'run_treadmill';

-- Vyhnout se kolizi uniq_session_exercise před přemapováním
DELETE tse_drop
FROM training_session_exercises tse_drop
JOIN exercises e_drop ON e_drop.id = tse_drop.exercise_id
JOIN tmp_special_keep k ON k.sport_type = e_drop.sport_type
JOIN training_session_exercises tse_keep
  ON tse_keep.session_id = tse_drop.session_id
 AND tse_keep.exercise_id = k.keep_id
WHERE e_drop.id <> k.keep_id
  AND e_drop.coach_id IS NULL
  AND e_drop.is_global = 1;

-- Přemapování referencí na canonical ID
UPDATE workout_set_exercises wse
JOIN exercises e_drop ON e_drop.id = wse.exercise_id
JOIN tmp_special_keep k ON k.sport_type = e_drop.sport_type
SET wse.exercise_id = k.keep_id
WHERE e_drop.id <> k.keep_id
  AND e_drop.coach_id IS NULL
  AND e_drop.is_global = 1;

UPDATE training_session_exercises tse
JOIN exercises e_drop ON e_drop.id = tse.exercise_id
JOIN tmp_special_keep k ON k.sport_type = e_drop.sport_type
SET tse.exercise_id = k.keep_id
WHERE e_drop.id <> k.keep_id
  AND e_drop.coach_id IS NULL
  AND e_drop.is_global = 1;

UPDATE session_series ss
JOIN exercises e_drop ON e_drop.id = ss.exercise_id
JOIN tmp_special_keep k ON k.sport_type = e_drop.sport_type
SET ss.exercise_id = k.keep_id
WHERE e_drop.id <> k.keep_id
  AND e_drop.coach_id IS NULL
  AND e_drop.is_global = 1;

-- Smazání přebytečných duplicit
DELETE e
FROM exercises e
JOIN tmp_special_keep k ON k.sport_type = e.sport_type
WHERE e.id <> k.keep_id
  AND e.coach_id IS NULL
  AND e.is_global = 1;

DROP TEMPORARY TABLE tmp_special_keep;

COMMIT;
