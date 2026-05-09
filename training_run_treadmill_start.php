<?php
// training_run_treadmill_start.php – Zahájení běhu na páse
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php';

requireLogin();

$coachId = getCurrentCoachId();
$pdo = getDB();

// Zjisti session
$sessionId = intParam($_GET, 'id', 0);
if ($sessionId === 0) {
    flash('danger', 'Session nenalezena.');
    redirect(BASE_URL . '/dashboard.php');
}

$stmt = $pdo->prepare(
    'SELECT ts.*, a.first_name, a.last_name, ws.name as set_name
     FROM training_sessions ts
     JOIN athletes a ON a.id = ts.athlete_id
     JOIN workout_sets ws ON ws.id = ts.workout_set_id
     WHERE ts.id = ? AND a.coach_id = ? AND ts.completed_at IS NULL'
);
$stmt->execute([$sessionId, $coachId]);
$session = $stmt->fetch();

if (!$session) {
    flash('danger', 'Session nenalezena nebo již ukončena.');
    redirect(BASE_URL . '/dashboard.php');
}

// Zpracuj formulář – zahájení běhu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'start') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('danger', 'Neplatný bezpečnostní token.');
        redirect(BASE_URL . '/training_run_treadmill_start.php?id=' . $sessionId);
    }
    
    $durationSeconds = intParam($_POST, 'duration_minutes', 0) * 60 + intParam($_POST, 'duration_seconds', 0);
    $distanceKm = (float)($_POST['distance_km'] ?? 0);
    
    if ($durationSeconds <= 0 || $distanceKm <= 0) {
        flash('danger', 'Vyplňte dobu trvání a vzdálenost.');
        redirect(BASE_URL . '/training_run_treadmill_start.php?id=' . $sessionId);
    }
    
    // Vytvoř běh na páse
    $runId = createRunTreadmillSession($sessionId, $durationSeconds, $distanceKm);
    
    // Ihned přesměruj na detail, kde lze editovat zbylé údaje
    flash('success', 'Běh na páse zahájen. Můžete jej nyní editovat.');
    redirect(BASE_URL . '/training_run_treadmill_detail.php?id=' . $sessionId);
}

renderHeader('Zahájit běh na páse');
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-primary text-white fw-bold">
                <i class="fas fa-running me-2"></i>Zahájit běh na páse
            </div>
            <div class="card-body">
                <div class="mb-3 p-3 bg-light rounded">
                    <strong><?= h($session['first_name']) ?> <?= h($session['last_name']) ?></strong>
                    <br>
                    <small class="text-muted">Sada: <?= h($session['set_name']) ?></small>
                </div>

                <form method="post" novalidate>
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="start">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Doba trvání (minuty)</label>
                                <input type="number" name="duration_minutes" class="form-control"
                                       placeholder="0" min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Sekundy</label>
                                <input type="number" name="duration_seconds" class="form-control"
                                       placeholder="0" min="0" max="59">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Vzdálenost (km)</label>
                        <input type="number" name="distance_km" class="form-control"
                               placeholder="0.0" step="0.1" min="0" required>
                    </div>

                    <div class="alert alert-info small">
                        <i class="fas fa-info-circle me-1"></i>
                        Můžete přidat další údaje (kalorií, lokaci, pocit) po ukončení běhu.
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary fw-bold">
                            <i class="fas fa-play me-1"></i>Zahájit běh
                        </button>
                        <a href="<?= BASE_URL ?>/training_session.php?id=<?= $sessionId ?>" class="btn btn-secondary">
                            Zpět
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php renderFooter(); ?>
