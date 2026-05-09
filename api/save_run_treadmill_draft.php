<?php
// api/save_run_treadmill_draft.php – AJAX endpoint pro průběžné ukládání běhu na páse
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

$run = getRunTreadmillSessionByTrainingSession($sessionId);
if (!$run) {
    createRunTreadmillSession($sessionId, 0, 0);
    $run = getRunTreadmillSessionByTrainingSession($sessionId);
}

$durationSeconds = max(0, (int)($input['duration_minutes'] ?? 0) * 60 + (int)($input['duration_seconds'] ?? 0));
$distanceKm = max(0, (float)($input['distance_km'] ?? 0));
$caloriesBurned = ($input['calories_burned'] ?? '') !== '' ? (int)$input['calories_burned'] : null;
$location = trim((string)($input['location'] ?? ''));
$feeling = trim((string)($input['feeling'] ?? ''));

updateRunTreadmillSession(
    (int)$run['id'],
    $durationSeconds,
    $distanceKm,
    $caloriesBurned,
    $location !== '' ? $location : null,
    $feeling !== '' ? $feeling : null
);

echo json_encode([
    'success' => true,
    'saved_at' => date('H:i:s'),
]);
