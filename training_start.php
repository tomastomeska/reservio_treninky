<?php
// training_start.php – Zahájí novou tréninkovou session
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf($_POST['csrf_token'] ?? '')) {
    flash('danger', 'Neplatný požadavek.');
    redirect(BASE_URL . '/dashboard.php');
}

$coachId      = getCurrentCoachId();
$athleteId    = intParam($_POST, 'athlete_id');
$workoutSetId = intParam($_POST, 'workout_set_id');
$pdo          = getDB();

// Ověření sportovce
$stmt = $pdo->prepare('SELECT id FROM athletes WHERE id = ? AND coach_id = ?');
$stmt->execute([$athleteId, $coachId]);
if (!$stmt->fetch()) {
    flash('danger', 'Sportovec nenalezen.');
    redirect(BASE_URL . '/dashboard.php');
}

// Ověření sady
$stmt = $pdo->prepare('SELECT id FROM workout_sets WHERE id = ? AND coach_id = ?');
$stmt->execute([$workoutSetId, $coachId]);
if (!$stmt->fetch()) {
    flash('danger', 'Sada nenalezena.');
    redirect(BASE_URL . '/athlete_detail.php?id=' . $athleteId);
}

// Zkontroluj, zda neexistuje nedokončená session pro tohoto sportovce
$stmt = $pdo->prepare(
    'SELECT id FROM training_sessions
     WHERE athlete_id = ? AND completed_at IS NULL
     LIMIT 1'
);
$stmt->execute([$athleteId]);
$existing = $stmt->fetch();
if ($existing) {
    // Pokračuj v existující session
    redirect(BASE_URL . '/training_session.php?id=' . $existing['id']);
}

// Vytvoř novou session
$stmt = $pdo->prepare(
    'INSERT INTO training_sessions (athlete_id, workout_set_id) VALUES (?, ?)'
);
$stmt->execute([$athleteId, $workoutSetId]);
$sessionId = (int)$pdo->lastInsertId();

redirect(BASE_URL . '/training_session.php?id=' . $sessionId);
