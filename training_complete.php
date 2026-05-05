<?php
// training_complete.php – Dokončí trénink
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf($_POST['csrf_token'] ?? '')) {
    flash('danger', 'Neplatný požadavek.');
    redirect(BASE_URL . '/dashboard.php');
}

$coachId   = getCurrentCoachId();
$sessionId = intParam($_POST, 'session_id');
$location  = trim($_POST['location'] ?? '');
$notes     = trim($_POST['notes']    ?? '');
$pdo       = getDB();

// Ověření vlastnictví
$stmt = $pdo->prepare(
    'SELECT ts.id, ts.athlete_id FROM training_sessions ts
     JOIN athletes a ON ts.athlete_id = a.id
     WHERE ts.id = ? AND a.coach_id = ? AND ts.completed_at IS NULL'
);
$stmt->execute([$sessionId, $coachId]);
$session = $stmt->fetch();

if (!$session) {
    flash('danger', 'Trénink nenalezen nebo již dokončen.');
    redirect(BASE_URL . '/dashboard.php');
}

$pdo->prepare(
    'UPDATE training_sessions
     SET completed_at = NOW(), location = ?, notes = ?
     WHERE id = ?'
)->execute([$location ?: null, $notes ?: null, $sessionId]);

flash('success', 'Trénink byl úspěšně uložen! 🎉');
redirect(BASE_URL . '/training_detail.php?id=' . $sessionId);
