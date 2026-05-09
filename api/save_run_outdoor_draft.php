<?php
// api/save_run_outdoor_draft.php – AJAX endpoint pro průběžné ukládání běhu venku
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json; charset=utf-8');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Nepřihlášen']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Neplatná metoda']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    echo json_encode(['success' => false, 'error' => 'Neplatná data']);
    exit;
}

$coachId = getCurrentCoachId();
$sessionId = (int)($input['session_id'] ?? 0);
if ($sessionId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Neplatné session_id']);
    exit;
}

$pdo = getDB();
$stmt = $pdo->prepare(
    'SELECT ts.id
     FROM training_sessions ts
     JOIN athletes a ON a.id = ts.athlete_id
     WHERE ts.id = ?
       AND a.coach_id = ?
       AND ts.deleted_by_coach_at IS NULL
       AND ts.completed_at IS NULL'
);
$stmt->execute([$sessionId, $coachId]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Trénink nenalezen nebo je již dokončen']);
    exit;
}

$run = getRunOutdoorSessionByTrainingSession($sessionId);
if (!$run) {
    createRunOutdoorSession($sessionId);
    $run = getRunOutdoorSessionByTrainingSession($sessionId);
}

$durationSeconds = max(0, (int)($input['duration_minutes'] ?? 0) * 60 + (int)($input['duration_seconds'] ?? 0));
$distanceKm = max(0, (float)($input['distance_km'] ?? 0));
$runType = (string)($input['run_type'] ?? 'free');
$surface = (string)($input['surface'] ?? 'asphalt');
$allowedRunTypes = ['free', 'intervals', 'tempo', 'race', 'recovery'];
$allowedSurfaces = ['asphalt', 'trail', 'mixed'];
if (!in_array($runType, $allowedRunTypes, true)) {
    $runType = 'free';
}
if (!in_array($surface, $allowedSurfaces, true)) {
    $surface = 'asphalt';
}

$maxSpeed = ($input['max_speed'] ?? '') !== '' ? (float)$input['max_speed'] : null;
$caloriesBurned = ($input['calories_burned'] ?? '') !== '' ? (int)$input['calories_burned'] : null;
$stepCount = ($input['step_count'] ?? '') !== '' ? (int)$input['step_count'] : null;
$rpe = ($input['rpe'] ?? '') !== '' ? (int)$input['rpe'] : null;
$tempoVariability = ($input['tempo_variability'] ?? '') !== '' ? (float)$input['tempo_variability'] : null;
$feeling = trim((string)($input['feeling'] ?? ''));

updateRunOutdoorSession(
    (int)$run['id'],
    $durationSeconds,
    $distanceKm,
    $runType,
    $surface,
    $maxSpeed,
    $caloriesBurned,
    $stepCount,
    $rpe,
    $tempoVariability,
    $feeling !== '' ? $feeling : null
);

$splitsInput = $input['splits'] ?? [];
$splits = [];
if (is_array($splitsInput)) {
    foreach ($splitsInput as $split) {
        if (!is_array($split)) {
            continue;
        }

        $km = isset($split['km_marker']) && $split['km_marker'] !== '' ? (float)$split['km_marker'] : 0;
        $time = trim((string)($split['split_time'] ?? ''));
        $pace = trim((string)($split['pace'] ?? ''));
        $maxAtKm = isset($split['max_speed_at_km']) && $split['max_speed_at_km'] !== '' ? (float)$split['max_speed_at_km'] : null;

        if ($km <= 0 || $time === '') {
            continue;
        }

        if (!preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            continue;
        }

        if ($pace !== '' && !preg_match('/^\d{1,2}:\d{2}$/', $pace)) {
            $pace = '';
        }

        $splits[] = [
            'km_marker' => $km,
            'split_time' => $time,
            'pace' => $pace !== '' ? $pace : null,
            'max_speed_at_km' => $maxAtKm,
        ];
    }
}

saveRunOutdoorSplits((int)$run['id'], $splits);

echo json_encode([
    'success' => true,
    'saved_at' => date('H:i:s'),
]);
