<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php';

requireLogin();

$coachId = getCurrentCoachId();
$pdo = getDB();
$sessionId = intParam($_GET, 'id', 0);

if ($sessionId <= 0) {
    flash('danger', 'Session nenalezena.');
    redirect(BASE_URL . '/dashboard.php');
}

$stmt = $pdo->prepare(
    'SELECT ts.*, a.first_name, a.last_name, a.id AS athlete_id, ws.name AS set_name
     FROM training_sessions ts
     JOIN athletes a ON a.id = ts.athlete_id
     JOIN workout_sets ws ON ws.id = ts.workout_set_id
     WHERE ts.id = ? AND a.coach_id = ? AND ts.deleted_by_coach_at IS NULL'
);
$stmt->execute([$sessionId, $coachId]);
$session = $stmt->fetch();

if (!$session) {
    flash('danger', 'Session nenalezena.');
    redirect(BASE_URL . '/dashboard.php');
}

$runOutdoor = getRunOutdoorSessionByTrainingSession($sessionId);
if (!$runOutdoor) {
    createRunOutdoorSession($sessionId);
    $runOutdoor = getRunOutdoorSessionByTrainingSession($sessionId);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('danger', 'Neplatný bezpečnostní token.');
        redirect(BASE_URL . '/training_run_outdoor_detail.php?id=' . $sessionId);
    }

    $durationSeconds = intParam($_POST, 'duration_minutes', 0) * 60 + intParam($_POST, 'duration_seconds', 0);
    $distanceKm = (float)($_POST['distance_km'] ?? 0);
    $runType = (string)($_POST['run_type'] ?? 'free');
    $surface = (string)($_POST['surface'] ?? 'asphalt');

    $allowedRunTypes = ['free', 'intervals', 'tempo', 'race', 'recovery'];
    $allowedSurfaces = ['asphalt', 'trail', 'mixed'];
    if (!in_array($runType, $allowedRunTypes, true)) {
        $runType = 'free';
    }
    if (!in_array($surface, $allowedSurfaces, true)) {
        $surface = 'asphalt';
    }

    $maxSpeed = $_POST['max_speed'] !== '' ? (float)$_POST['max_speed'] : null;
    $caloriesBurned = $_POST['calories_burned'] !== '' ? (int)$_POST['calories_burned'] : null;
    $stepCount = $_POST['step_count'] !== '' ? (int)$_POST['step_count'] : null;
    $rpe = $_POST['rpe'] !== '' ? (int)$_POST['rpe'] : null;
    $tempoVariability = $_POST['tempo_variability'] !== '' ? (float)$_POST['tempo_variability'] : null;
    $feeling = trim($_POST['feeling'] ?? '');

    if ($durationSeconds < 0 || $distanceKm < 0) {
        flash('danger', 'Čas a vzdálenost nesmí být záporné.');
        redirect(BASE_URL . '/training_run_outdoor_detail.php?id=' . $sessionId);
    }

    updateRunOutdoorSession(
        (int)$runOutdoor['id'],
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

    $splitKm = $_POST['split_km'] ?? [];
    $splitTime = $_POST['split_time'] ?? [];
    $splitPace = $_POST['split_pace'] ?? [];
    $splitMaxSpeed = $_POST['split_max_speed'] ?? [];

    $splits = [];
    $rows = max(count($splitKm), count($splitTime));
    for ($i = 0; $i < $rows; $i++) {
        $km = isset($splitKm[$i]) && $splitKm[$i] !== '' ? (float)$splitKm[$i] : 0;
        $time = trim((string)($splitTime[$i] ?? ''));
        $pace = trim((string)($splitPace[$i] ?? ''));
        $maxAtKm = isset($splitMaxSpeed[$i]) && $splitMaxSpeed[$i] !== '' ? (float)$splitMaxSpeed[$i] : null;

        if ($km <= 0 || $time === '') {
            continue;
        }

        if (!preg_match('/^\d{1,2}:\d{2}$/', $time)) {
            continue;
        }

        if ($pace !== '' && !preg_match('/^\d{1,2}:\d{2}$/', $pace)) {
            $pace = null;
        }

        $splits[] = [
            'km_marker' => $km,
            'split_time' => $time,
            'pace' => $pace ?: null,
            'max_speed_at_km' => $maxAtKm,
        ];
    }

    saveRunOutdoorSplits((int)$runOutdoor['id'], $splits);

    flash('success', 'Běh venku byl uložen.');
    redirect(BASE_URL . '/training_run_outdoor_detail.php?id=' . $sessionId);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'complete') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('danger', 'Neplatný bezpečnostní token.');
        redirect(BASE_URL . '/training_run_outdoor_detail.php?id=' . $sessionId);
    }

    $pdo->prepare('UPDATE training_sessions SET completed_at = NOW() WHERE id = ?')
        ->execute([$sessionId]);

    flash('success', 'Běh venku byl ukončen.');
    redirect(BASE_URL . '/athlete_detail.php?id=' . $session['athlete_id']);
}

$runOutdoor = getRunOutdoorSessionByTrainingSession($sessionId);
$splits = getRunOutdoorSplits((int)$runOutdoor['id']);
$history = getRunOutdoorHistory((int)$session['athlete_id'], 5);
$stats = calculateRunOutdoorStats((int)$session['athlete_id'], 30);

renderHeader('Běh venku - detail');
?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-success text-white fw-bold">
                <i class="fas fa-person-hiking me-2"></i>Běh venku
            </div>
            <div class="card-body">
                <div class="mb-3 p-3 bg-light rounded">
                    <strong><?= h($session['first_name']) ?> <?= h($session['last_name']) ?></strong><br>
                    <small class="text-muted">Sada: <?= h($session['set_name']) ?></small>
                </div>

                <form method="post" novalidate id="run-outdoor-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="save">

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Doba (min)</label>
                                <input type="number" class="form-control" name="duration_minutes"
                                       min="0" value="<?= intdiv((int)$runOutdoor['duration_seconds'], 60) ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Sekundy</label>
                                <input type="number" class="form-control" name="duration_seconds"
                                       min="0" max="59" value="<?= ((int)$runOutdoor['duration_seconds']) % 60 ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Vzdálenost (km)</label>
                                <input type="number" class="form-control" name="distance_km" step="0.01" min="0"
                                       value="<?= h((string)$runOutdoor['distance_km']) ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Typ běhu</label>
                                <select class="form-select" name="run_type">
                                    <option value="free" <?= $runOutdoor['run_type'] === 'free' ? 'selected' : '' ?>>Volný</option>
                                    <option value="intervals" <?= $runOutdoor['run_type'] === 'intervals' ? 'selected' : '' ?>>Intervaly</option>
                                    <option value="tempo" <?= $runOutdoor['run_type'] === 'tempo' ? 'selected' : '' ?>>Tempo</option>
                                    <option value="race" <?= $runOutdoor['run_type'] === 'race' ? 'selected' : '' ?>>Závod</option>
                                    <option value="recovery" <?= $runOutdoor['run_type'] === 'recovery' ? 'selected' : '' ?>>Regenerační</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Povrch</label>
                                <select class="form-select" name="surface">
                                    <option value="asphalt" <?= $runOutdoor['surface'] === 'asphalt' ? 'selected' : '' ?>>Asfalt</option>
                                    <option value="trail" <?= $runOutdoor['surface'] === 'trail' ? 'selected' : '' ?>>Terén</option>
                                    <option value="mixed" <?= $runOutdoor['surface'] === 'mixed' ? 'selected' : '' ?>>Mix</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Max rychlost (km/h)</label>
                                <input type="number" class="form-control" name="max_speed" step="0.1" min="0"
                                       value="<?= h((string)($runOutdoor['max_speed'] ?? '')) ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Kalorie</label>
                                <input type="number" class="form-control" name="calories_burned" min="0"
                                       value="<?= h((string)($runOutdoor['calories_burned'] ?? '')) ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Kroky</label>
                                <input type="number" class="form-control" name="step_count" min="0"
                                       value="<?= h((string)($runOutdoor['step_count'] ?? '')) ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">RPE (1-10)</label>
                                <input type="number" class="form-control" name="rpe" min="1" max="10"
                                       value="<?= h((string)($runOutdoor['rpe'] ?? '')) ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Variabilita tempa (%)</label>
                                <input type="number" class="form-control" name="tempo_variability" min="0" step="0.1"
                                       value="<?= h((string)($runOutdoor['tempo_variability'] ?? '')) ?>">
                            </div>
                        </div>
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Pocit</label>
                                <input type="text" class="form-control" name="feeling"
                                       value="<?= h((string)($runOutdoor['feeling'] ?? '')) ?>"
                                       placeholder="Jak se běželo?">
                            </div>
                        </div>
                    </div>

                    <hr>
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <h5 class="mb-0">Splity</h5>
                        <button type="button" class="btn btn-outline-secondary btn-sm" onclick="addSplitRow()">
                            <i class="fas fa-plus me-1"></i>Přidat split
                        </button>
                    </div>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered align-middle" id="splits-table">
                            <thead class="table-light">
                                <tr>
                                    <th>Km</th>
                                    <th>Čas splitu (mm:ss)</th>
                                    <th>Tempo (mm:ss)</th>
                                    <th>Max km/h</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($splits)): ?>
                                <tr>
                                    <td><input type="number" step="0.01" min="0" name="split_km[]" class="form-control form-control-sm"></td>
                                    <td><input type="text" name="split_time[]" class="form-control form-control-sm" placeholder="05:15"></td>
                                    <td><input type="text" name="split_pace[]" class="form-control form-control-sm" placeholder="05:15"></td>
                                    <td><input type="number" step="0.1" min="0" name="split_max_speed[]" class="form-control form-control-sm"></td>
                                    <td><button type="button" class="btn btn-outline-danger btn-sm" onclick="removeSplitRow(this)"><i class="fas fa-times"></i></button></td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($splits as $split): ?>
                                <tr>
                                    <td><input type="number" step="0.01" min="0" name="split_km[]" class="form-control form-control-sm" value="<?= h((string)$split['km_marker']) ?>"></td>
                                    <td><input type="text" name="split_time[]" class="form-control form-control-sm" value="<?= h($split['split_time']) ?>"></td>
                                    <td><input type="text" name="split_pace[]" class="form-control form-control-sm" value="<?= h((string)($split['pace'] ?? '')) ?>"></td>
                                    <td><input type="number" step="0.1" min="0" name="split_max_speed[]" class="form-control form-control-sm" value="<?= h((string)($split['max_speed_at_km'] ?? '')) ?>"></td>
                                    <td><button type="button" class="btn btn-outline-danger btn-sm" onclick="removeSplitRow(this)"><i class="fas fa-times"></i></button></td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex gap-2 align-items-center">
                        <button type="submit" class="btn btn-success fw-bold">
                            <i class="fas fa-save me-1"></i>Uložit běh venku
                        </button>
                        <a href="<?= BASE_URL ?>/training_session.php?id=<?= $sessionId ?>" class="btn btn-secondary">Zpět na trénink</a>
                        <small id="run-outdoor-autosave-status" class="text-muted ms-2">Automatické ukládání zapnuto</small>
                    </div>
                </form>

                <hr>

                <form method="post" class="mt-3">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="complete">
                    <button type="submit" class="btn btn-primary fw-bold"
                            onclick="return confirm('Chcete ukončit tento běh venku?')">
                        <i class="fas fa-flag-checkered me-1"></i>Ukončit trénink
                    </button>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-dark text-white fw-bold">
                <i class="fas fa-history me-2"></i>Poslední běhy venku
            </div>
            <div class="card-body p-0">
                <?php if (empty($history)): ?>
                <div class="text-center py-4 text-muted">Žádná historie.</div>
                <?php else: ?>
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Datum</th>
                            <th>Vzdálenost</th>
                            <th>Čas</th>
                            <th>Tempo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $run): ?>
                        <tr>
                            <td><?= formatDate($run['completed_at'] ?? $run['ts_started_at']) ?></td>
                            <td><?= number_format((float)$run['distance_km'], 2, ',', ' ') ?> km</td>
                            <td><?= gmdate('H:i:s', (int)$run['duration_seconds']) ?></td>
                            <td><?= h((string)($run['avg_pace'] ?? '–')) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-info text-white fw-bold">
                <i class="fas fa-chart-bar me-2"></i>Statistiky (30 dní)
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-3">
                        <strong><?= (int)$stats['total_runs'] ?></strong><br>
                        <small class="text-muted">Běhů</small>
                    </div>
                    <div class="col-md-3">
                        <strong><?= number_format((float)$stats['total_km'], 2, ',', ' ') ?> km</strong><br>
                        <small class="text-muted">Celkem</small>
                    </div>
                    <div class="col-md-3">
                        <strong><?= gmdate('H:i:s', (int)$stats['total_seconds']) ?></strong><br>
                        <small class="text-muted">Celkový čas</small>
                    </div>
                    <div class="col-md-3">
                        <strong><?= (int)$stats['total_calories'] ?> kcal</strong><br>
                        <small class="text-muted">Kalorie</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function addSplitRow() {
    const tbody = document.querySelector('#splits-table tbody');
    const tr = document.createElement('tr');
    tr.innerHTML =
        '<td><input type="number" step="0.01" min="0" name="split_km[]" class="form-control form-control-sm"></td>' +
        '<td><input type="text" name="split_time[]" class="form-control form-control-sm" placeholder="05:15"></td>' +
        '<td><input type="text" name="split_pace[]" class="form-control form-control-sm" placeholder="05:15"></td>' +
        '<td><input type="number" step="0.1" min="0" name="split_max_speed[]" class="form-control form-control-sm"></td>' +
        '<td><button type="button" class="btn btn-outline-danger btn-sm" onclick="removeSplitRow(this)"><i class="fas fa-times"></i></button></td>';
    tbody.appendChild(tr);

    if (window.__runOutdoorAutosave && typeof window.__runOutdoorAutosave.scheduleSave === 'function') {
        window.__runOutdoorAutosave.scheduleSave();
    }
}

function removeSplitRow(btn) {
    const row = btn.closest('tr');
    const tbody = document.querySelector('#splits-table tbody');
    if (tbody.querySelectorAll('tr').length > 1) {
        row.remove();
        if (window.__runOutdoorAutosave && typeof window.__runOutdoorAutosave.scheduleSave === 'function') {
            window.__runOutdoorAutosave.scheduleSave();
        }
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('run-outdoor-form');
    const statusEl = document.getElementById('run-outdoor-autosave-status');
    if (!form || !window.createSportAutosave) {
        return;
    }

    const autosave = window.createSportAutosave({
        form: form,
        statusEl: statusEl,
        endpoint: '<?= BASE_URL ?>/api/save_run_outdoor_draft.php',
        debounceMs: 700,
        buildPayload: function() {
            const splitKm = form.querySelectorAll('[name="split_km[]"]');
            const splitTime = form.querySelectorAll('[name="split_time[]"]');
            const splitPace = form.querySelectorAll('[name="split_pace[]"]');
            const splitMaxSpeed = form.querySelectorAll('[name="split_max_speed[]"]');
            const splits = [];

            for (let i = 0; i < splitKm.length; i++) {
                splits.push({
                    km_marker: splitKm[i]?.value || '',
                    split_time: splitTime[i]?.value || '',
                    pace: splitPace[i]?.value || '',
                    max_speed_at_km: splitMaxSpeed[i]?.value || ''
                });
            }

            return {
                session_id: <?= (int)$sessionId ?>,
                duration_minutes: form.querySelector('[name="duration_minutes"]').value || '0',
                duration_seconds: form.querySelector('[name="duration_seconds"]').value || '0',
                distance_km: form.querySelector('[name="distance_km"]').value || '',
                run_type: form.querySelector('[name="run_type"]').value || 'free',
                surface: form.querySelector('[name="surface"]').value || 'asphalt',
                max_speed: form.querySelector('[name="max_speed"]').value || '',
                calories_burned: form.querySelector('[name="calories_burned"]').value || '',
                step_count: form.querySelector('[name="step_count"]').value || '',
                rpe: form.querySelector('[name="rpe"]').value || '',
                tempo_variability: form.querySelector('[name="tempo_variability"]').value || '',
                feeling: form.querySelector('[name="feeling"]').value || '',
                splits: splits
            };
        }
    });

    window.__runOutdoorAutosave = autosave;

    form.addEventListener('submit', function() {
        if (autosave && typeof autosave.saveNow === 'function') {
            autosave.saveNow();
        }
    });
});
</script>

<?php renderFooter(); ?>
