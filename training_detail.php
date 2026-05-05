<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php';

requireLogin();

$coachId   = getCurrentCoachId();
$sessionId = intParam($_GET, 'id');
$pdo       = getDB();

// Načtení session
$stmt = $pdo->prepare(
    'SELECT ts.*, a.first_name, a.last_name, a.id AS athlete_id, a.email AS athlete_email,
            ws.name AS set_name
     FROM training_sessions ts
     JOIN athletes a ON ts.athlete_id = a.id
     JOIN workout_sets ws ON ts.workout_set_id = ws.id
     WHERE ts.id = ? AND a.coach_id = ?'
);
$stmt->execute([$sessionId, $coachId]);
$session = $stmt->fetch();

if (!$session) {
    flash('danger', 'Trénink nenalezen.');
    redirect(BASE_URL . '/dashboard.php');
}

// Načtení cviků v sadě
$exercises = getWorkoutSetExercises($session['workout_set_id']);

// Načtení sérií
$seriesByExercise = [];
$totalSeries      = 0;
foreach ($exercises as $ex) {
    $s = getSeriesForExercise($sessionId, $ex['exercise_id']);
    $seriesByExercise[$ex['exercise_id']] = $s;
    $totalSeries += count($s);
}

$athleteName = h($session['first_name'] . ' ' . $session['last_name']);

renderHeader('Detail tréninku');
?>

<div class="d-flex align-items-center mb-3 gap-3 flex-wrap page-header">
    <a href="<?= BASE_URL ?>/athlete_detail.php?id=<?= $session['athlete_id'] ?>"
       class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left me-1"></i>Zpět
    </a>
    <div>
        <h2 class="mb-0 fw-bold">
            <i class="fas fa-clipboard-list me-2 text-warning"></i>
            <?= $athleteName ?>
        </h2>
        <span class="badge bg-warning text-dark me-1"><?= h($session['set_name']) ?></span>
        <?= formatDateTime($session['completed_at'] ?? $session['started_at']) ?>
        <?php if ($session['location']): ?>
        <span class="ms-2 text-muted"><i class="fas fa-map-marker-alt me-1"></i><?= h($session['location']) ?></span>
        <?php endif; ?>
    </div>
    <div class="ms-auto d-flex gap-2 flex-wrap training-detail-actions">
        <a href="<?= BASE_URL ?>/export_csv.php?session_id=<?= $sessionId ?>"
           class="btn btn-outline-success btn-sm">
            <i class="fas fa-file-excel me-1"></i>Export CSV
        </a>
        <button onclick="window.print()" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-print me-1"></i>Tisk / PDF
        </button>
        <?php if ($session['athlete_email'] && $session['completed_at']): ?>
        <a href="<?= BASE_URL ?>/send_email.php?session_id=<?= $sessionId ?>"
           class="btn btn-outline-primary btn-sm"
           onclick="return confirm('Odeslat souhrn tréninku na <?= h($session['athlete_email']) ?>?')">
            <i class="fas fa-envelope me-1"></i>Odeslat e-mailem
        </a>
        <?php endif; ?>
        <?php if (!$session['completed_at']): ?>
        <a href="<?= BASE_URL ?>/training_session.php?id=<?= $sessionId ?>"
           class="btn btn-warning btn-sm fw-bold">
            <i class="fas fa-play me-1"></i>Pokračovat
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Souhrn -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="display-6 fw-bold text-warning"><?= count($exercises) ?></div>
            <div class="text-muted">Cviků</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card border-0 shadow-sm text-center py-3">
            <div class="display-6 fw-bold text-warning"><?= $totalSeries ?></div>
            <div class="text-muted">Sérií celkem</div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="card border-0 shadow-sm text-center py-3">
            <?php
            $totalVolume = 0;
            foreach ($seriesByExercise as $sArr) {
                foreach ($sArr as $s) {
                    $totalVolume += $s['weight'] * $s['reps'];
                }
            }
            ?>
            <div class="display-6 fw-bold text-warning"><?= number_format($totalVolume, 0, ',', '&nbsp;') ?></div>
            <div class="text-muted">Celkový objem (kg×rep)</div>
        </div>
    </div>
</div>

<!-- Cviky a série -->
<?php foreach ($exercises as $ex): ?>
<?php $series = $seriesByExercise[$ex['exercise_id']] ?? []; ?>
<div class="card border-0 shadow-sm mb-4 exercise-block" id="ex-<?= $ex['exercise_id'] ?>">
    <div class="card-header bg-dark text-white d-flex align-items-center">
        <span class="badge bg-warning text-dark me-2 fs-5"><?= $ex['exercise_order'] ?></span>
        <span class="fw-bold fs-5"><?= h($ex['exercise_name']) ?></span>
        <?php if ($series): ?>
        <?php
        $maxW  = max(array_column($series, 'weight'));
        $maxR  = max(array_column($series, 'reps'));
        ?>
        <div class="ms-auto small text-secondary">
            Max váha: <strong class="text-warning"><?= number_format($maxW, 1, ',', '') ?> kg</strong>
            &nbsp;|&nbsp; Max opak.: <strong class="text-warning"><?= $maxR ?></strong>
        </div>
        <?php endif; ?>
    </div>
    <div class="card-body p-0">
        <?php if (empty($series)): ?>
        <div class="text-center py-3 text-muted">Žádné série.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-bordered mb-0 align-middle text-center">
                <thead class="table-light">
                    <tr>
                        <th>#</th>
                        <th>Váha (kg)</th>
                        <th>Opakování</th>
                        <th>Dopomoc</th>
                        <th>Objem</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($series as $s): ?>
                    <tr>
                        <td class="fw-bold text-muted"><?= $s['series_order'] ?></td>
                        <td class="fw-bold"><?= number_format($s['weight'], 1, ',', '') ?> kg</td>
                        <td><?= $s['reps'] ?></td>
                        <td>
                            <?php if ($s['assistance_reps'] > 0): ?>
                            <span class="badge bg-warning text-dark"><?= $s['assistance_reps'] ?></span>
                            <?php else: ?>
                            <span class="text-muted">–</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-muted"><?= number_format($s['weight'] * $s['reps'], 0, ',', '') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-dark">
                    <tr>
                        <td colspan="4" class="text-end fw-semibold">Objem celkem</td>
                        <td class="fw-bold">
                            <?= number_format(array_sum(array_map(fn($s) => $s['weight'] * $s['reps'], $series)), 0, ',', '') ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>

<?php if ($session['notes']): ?>
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-dark text-white"><i class="fas fa-sticky-note me-2"></i>Poznámka</div>
    <div class="card-body"><?= h($session['notes']) ?></div>
</div>
<?php endif; ?>

<style>
@media print {
    .navbar, .btn, footer { display: none !important; }
    .card { break-inside: avoid; }
}
</style>

<?php renderFooter(); ?>
