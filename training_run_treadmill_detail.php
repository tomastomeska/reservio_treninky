<?php
// training_run_treadmill_detail.php – Detail a editace běhu na páse
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
    'SELECT ts.*, a.first_name, a.last_name, ws.name as set_name, a.id as athlete_id
     FROM training_sessions ts
     JOIN athletes a ON a.id = ts.athlete_id
     JOIN workout_sets ws ON ws.id = ts.workout_set_id
     WHERE ts.id = ? AND a.coach_id = ?'
);
$stmt->execute([$sessionId, $coachId]);
$session = $stmt->fetch();

if (!$session) {
    flash('danger', 'Session nenalezena.');
    redirect(BASE_URL . '/dashboard.php');
}

// Zjisti běh na páse
$runTreadmill = getRunTreadmillSessionByTrainingSession($sessionId);

// Zpracuj formulář – update běhu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('danger', 'Neplatný bezpečnostní token.');
        redirect(BASE_URL . '/training_run_treadmill_detail.php?id=' . $sessionId);
    }
    
    if (!$runTreadmill) {
        flash('danger', 'Běh nenalezen.');
        redirect(BASE_URL . '/training_run_treadmill_detail.php?id=' . $sessionId);
    }
    
    $durationSeconds = intParam($_POST, 'duration_minutes', 0) * 60 + intParam($_POST, 'duration_seconds', 0);
    $distanceKm = (float)($_POST['distance_km'] ?? 0);
    $caloriesBurned = intParam($_POST, 'calories_burned');
    $location = trim($_POST['location'] ?? '');
    $feeling = trim($_POST['feeling'] ?? '');
    
    if ($durationSeconds <= 0 || $distanceKm <= 0) {
        flash('danger', 'Vyplňte dobu trvání a vzdálenost.');
        redirect(BASE_URL . '/training_run_treadmill_detail.php?id=' . $sessionId);
    }
    
    // Update běhu
    updateRunTreadmillSession(
        $runTreadmill['id'],
        $durationSeconds,
        $distanceKm,
        $caloriesBurned > 0 ? $caloriesBurned : null,
        !empty($location) ? $location : null,
        !empty($feeling) ? $feeling : null
    );
    
    flash('success', 'Běh byl upraven.');
    redirect(BASE_URL . '/training_run_treadmill_detail.php?id=' . $sessionId);
}

// Zpracuj formulář – ukončení běhu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('danger', 'Neplatný bezpečnostní token.');
        redirect(BASE_URL . '/training_run_treadmill_detail.php?id=' . $sessionId);
    }
    
    // Označ session jako ukončenou
    $pdo->prepare('UPDATE training_sessions SET completed_at = NOW() WHERE id = ?')
        ->execute([$sessionId]);
    
    flash('success', 'Běh byl ukončen.');
    redirect(BASE_URL . '/athlete_detail.php?id=' . $session['athlete_id']);
}

renderHeader('Běh na páse – Detail');
?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-primary text-white fw-bold">
                <i class="fas fa-running me-2"></i>Běh na páse
            </div>
            <div class="card-body">
                <div class="mb-3 p-3 bg-light rounded">
                    <strong><?= h($session['first_name']) ?> <?= h($session['last_name']) ?></strong>
                    <br>
                    <small class="text-muted">Sada: <?= h($session['set_name']) ?></small>
                </div>

                <?php if ($runTreadmill): ?>
                <form method="post" novalidate>
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="save">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Doba trvání (minuty)</label>
                                <input type="number" name="duration_minutes" class="form-control"
                                       value="<?= intval($runTreadmill['duration_seconds'] / 60) ?>"
                                       min="0" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Sekundy</label>
                                <input type="number" name="duration_seconds" class="form-control"
                                       value="<?= $runTreadmill['duration_seconds'] % 60 ?>"
                                       min="0" max="59">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Vzdálenost (km)</label>
                        <input type="number" name="distance_km" class="form-control"
                               value="<?= h($runTreadmill['distance_km']) ?>"
                               step="0.1" min="0" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Spálené kalorie <span class="text-muted fw-normal">(nepovinné)</span></label>
                        <input type="number" name="calories_burned" class="form-control"
                               value="<?= h($runTreadmill['calories_burned'] ?? '') ?>"
                               min="0">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Místo <span class="text-muted fw-normal">(nepovinné)</span></label>
                        <input type="text" name="location" class="form-control"
                               value="<?= h($runTreadmill['location'] ?? '') ?>"
                               placeholder="např. fitness centrum, domácí trénink">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Pocit <span class="text-muted fw-normal">(nepovinné)</span></label>
                        <textarea name="feeling" class="form-control" rows="3"
                                  placeholder="Jak se cítil během běhu?..."><?= h($runTreadmill['feeling'] ?? '') ?></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary fw-bold">
                            <i class="fas fa-save me-1"></i>Uložit
                        </button>
                        <a href="<?= BASE_URL ?>/athlete_detail.php?id=<?= $session['athlete_id'] ?>" class="btn btn-secondary">
                            Zpět
                        </a>
                    </div>
                </form>

                <hr>

                <h5>Statistika běhu</h5>
                <div class="row text-center">
                    <div class="col-md-3">
                        <strong><?= number_format($runTreadmill['distance_km'], 2, ',', ' ') ?> km</strong>
                        <br><small class="text-muted">Vzdálenost</small>
                    </div>
                    <div class="col-md-3">
                        <strong><?= gmdate('H:i:s', $runTreadmill['duration_seconds']) ?></strong>
                        <br><small class="text-muted">Čas</small>
                    </div>
                    <div class="col-md-3">
                        <strong><?= $runTreadmill['distance_km'] > 0 ? number_format($runTreadmill['duration_seconds'] / $runTreadmill['distance_km'], 0, ',', ' ') . ' s/km' : '–' ?></strong>
                        <br><small class="text-muted">Průměrné tempo</small>
                    </div>
                    <div class="col-md-3">
                        <strong><?= h($runTreadmill['calories_burned'] ?? '–') ?> kcal</strong>
                        <br><small class="text-muted">Kalorie</small>
                    </div>
                </div>

                <hr>

                <!-- Tlačítko pro ukončení běhu -->
                <form method="post" class="mt-3">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="complete">
                    <button type="submit" class="btn btn-success fw-bold"
                            onclick="return confirm('Chcete ukončit tento běh?')">
                        <i class="fas fa-stop me-1"></i>Ukončit běh
                    </button>
                </form>

                <?php else: ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Běh na páse nebyl zahájen. <a href="<?= BASE_URL ?>/training_run_treadmill_start.php?id=<?= $sessionId ?>">Zahájit nyní</a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Historie běhů na páse -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-dark text-white fw-bold">
                <i class="fas fa-history me-2"></i>Poslední běhy na páse
            </div>
            <div class="card-body p-0">
                <?php
                $history = getRunTreadmillHistory($session['athlete_id'], 5);
                if (empty($history)):
                ?>
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                    Žádné běhy v historii.
                </div>
                <?php else: ?>
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Datum</th>
                            <th>Čas</th>
                            <th>Vzdálenost</th>
                            <th>Kalorie</th>
                            <th>Tempo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $run): ?>
                        <tr>
                            <td><?= formatDate($run['completed_at'] ?? $run['ts_started_at']) ?></td>
                            <td><?= gmdate('H:i:s', $run['duration_seconds']) ?></td>
                            <td><?= number_format($run['distance_km'], 2, ',', ' ') ?> km</td>
                            <td><?= h($run['calories_burned'] ?? '–') ?></td>
                            <td><?= $run['distance_km'] > 0 ? number_format($run['duration_seconds'] / $run['distance_km'], 0, ',', ' ') . ' s/km' : '–' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Celkové statistiky -->
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header bg-info text-white fw-bold">
                <i class="fas fa-chart-bar me-2"></i>Celkové statistiky (poslední 30 dní)
            </div>
            <div class="card-body">
                <?php
                $stats = calculateRunTreadmillStats($session['athlete_id'], 30);
                ?>
                <div class="row text-center">
                    <div class="col-md-3">
                        <strong><?= $stats['total_runs'] ?></strong>
                        <br><small class="text-muted">Počet běhů</small>
                    </div>
                    <div class="col-md-3">
                        <strong><?= number_format($stats['total_km'], 1, ',', ' ') ?> km</strong>
                        <br><small class="text-muted">Celkem</small>
                    </div>
                    <div class="col-md-3">
                        <strong><?= number_format($stats['avg_km'], 1, ',', ' ') ?> km</strong>
                        <br><small class="text-muted">Průměr</small>
                    </div>
                    <div class="col-md-3">
                        <strong><?= $stats['total_calories'] ?> kcal</strong>
                        <br><small class="text-muted">Celkem spáleno</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php renderFooter(); ?>
