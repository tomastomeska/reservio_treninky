<?php
// training_reopen.php – Znovu otevře již dokončený trénink
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf($_POST['csrf_token'] ?? '')) {
    flash('danger', 'Neplatný požadavek.');
    redirect(BASE_URL . '/dashboard.php');
}

$coachId   = getCurrentCoachId();
$sessionId = intParam($_POST, 'session_id');
$pdo       = getDB();

$stmt = $pdo->prepare(
    'SELECT ts.id FROM training_sessions ts
     JOIN athletes a ON ts.athlete_id = a.id
    WHERE ts.id = ? AND a.coach_id = ?
      AND ts.completed_at IS NOT NULL
      AND ts.deleted_by_coach_at IS NULL'
);
$stmt->execute([$sessionId, $coachId]);
$session = $stmt->fetch();

if (!$session) {
    flash('danger', 'Trénink nenalezen nebo už je otevřený.');
    redirect(BASE_URL . '/dashboard.php');
}

$pdo->prepare('UPDATE training_sessions SET completed_at = NULL WHERE id = ?')
    ->execute([$sessionId]);

flash('success', 'Trénink byl znovu otevřen. Můžete upravit série a znovu ho ukončit.');
redirect(BASE_URL . '/training_session.php?id=' . $sessionId);
