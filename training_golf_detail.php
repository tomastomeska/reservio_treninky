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

$golfSession = getGolfSessionByTrainingSession($sessionId);
if (!$golfSession) {
    createGolfSession($sessionId);
    $golfSession = getGolfSessionByTrainingSession($sessionId);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('danger', 'Neplatný bezpečnostní token.');
        redirect(BASE_URL . '/training_golf_detail.php?id=' . $sessionId);
    }

    $courseName = trim($_POST['course_name'] ?? '');
    $numHoles = intParam($_POST, 'num_holes', 18);
    $gameType = (string)($_POST['game_type'] ?? 'training');
    $distanceKm = $_POST['distance_km'] !== '' ? (float)$_POST['distance_km'] : null;
    $caloriesBurned = $_POST['calories_burned'] !== '' ? (int)$_POST['calories_burned'] : null;
    $weather = trim($_POST['weather'] ?? '');
    $players = trim($_POST['players'] ?? '');
    $handicapAfter = $_POST['handicap_after'] !== '' ? (float)$_POST['handicap_after'] : null;
    $durationMinutes = $_POST['duration_minutes'] !== '' ? (int)$_POST['duration_minutes'] : null;
    $feeling = trim($_POST['feeling'] ?? '');

    $allowedGameTypes = ['training', 'tournament', 'friendly'];
    if (!in_array($gameType, $allowedGameTypes, true)) {
        $gameType = 'training';
    }

    if ($courseName === '') {
        $courseName = 'Nezadano';
    }

    if ($numHoles <= 0) {
        $numHoles = 9;
    }
    if ($numHoles > 36) {
        $numHoles = 36;
    }

    updateGolfSession(
        (int)$golfSession['id'],
        $courseName,
        $numHoles,
        $gameType,
        $distanceKm,
        $caloriesBurned,
        $weather !== '' ? $weather : null,
        $players !== '' ? $players : null,
        $handicapAfter,
        $feeling !== '' ? $feeling : null,
        $durationMinutes
    );

    $holeNumbers = $_POST['hole_number'] ?? [];
    $holePars = $_POST['hole_par'] ?? [];
    $holeScores = $_POST['hole_score'] ?? [];
    $holeNotes = $_POST['hole_notes'] ?? [];

    $holes = [];
    $rows = count($holeNumbers);
    for ($i = 0; $i < $rows; $i++) {
        $holeNumber = (int)($holeNumbers[$i] ?? 0);
        $par = (int)($holePars[$i] ?? 0);
        $scoreRaw = trim((string)($holeScores[$i] ?? ''));
        $score = $scoreRaw === '' ? null : (int)$scoreRaw;
        $notes = trim((string)($holeNotes[$i] ?? ''));

        if ($holeNumber <= 0 || $par <= 0) {
            continue;
        }

        $holes[] = [
            'hole_number' => $holeNumber,
            'par' => $par,
            'score' => $score,
            'notes' => $notes !== '' ? $notes : null,
        ];
    }

    saveGolfHoles((int)$golfSession['id'], $holes);

    flash('success', 'Golf byl uložen.');
    redirect(BASE_URL . '/training_golf_detail.php?id=' . $sessionId);
}

$golfSession = getGolfSessionByTrainingSession($sessionId);
$savedHoles = getGolfHoles((int)$golfSession['id']);
$history = getGolfHistory((int)$session['athlete_id'], 5);
$stats = calculateGolfStats((int)$session['athlete_id'], 90);

$holesByNumber = [];
foreach ($savedHoles as $hole) {
    $holesByNumber[(int)$hole['hole_number']] = $hole;
}

$numHolesForForm = (int)($golfSession['num_holes'] ?? 18);
if ($numHolesForForm <= 0) {
    $numHolesForForm = 18;
}

renderHeader('Golf - detail');
?>

<div class="row justify-content-center">
    <div class="col-lg-11">
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-success text-white fw-bold">
                <i class="fas fa-golf-ball me-2"></i>Golf
            </div>
            <div class="card-body">
                <div class="mb-3 p-3 bg-light rounded">
                    <strong><?= h($session['first_name']) ?> <?= h($session['last_name']) ?></strong><br>
                    <small class="text-muted">Sada: <?= h($session['set_name']) ?></small>
                </div>

                <form method="post" novalidate id="golf-form">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="save">

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Hřiště</label>
                                <input type="text" class="form-control" name="course_name"
                                       value="<?= h((string)$golfSession['course_name']) ?>" placeholder="např. Albatross">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Počet jamek</label>
                                <input type="number" class="form-control" name="num_holes" id="num_holes"
                                       min="1" max="36" value="<?= $numHolesForForm ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Typ hry</label>
                                <select class="form-select" name="game_type">
                                    <option value="training" <?= $golfSession['game_type'] === 'training' ? 'selected' : '' ?>>Trénink</option>
                                    <option value="friendly" <?= $golfSession['game_type'] === 'friendly' ? 'selected' : '' ?>>Přátelské</option>
                                    <option value="tournament" <?= $golfSession['game_type'] === 'tournament' ? 'selected' : '' ?>>Turnaj</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Km</label>
                                <input type="number" class="form-control" step="0.1" min="0" name="distance_km"
                                       value="<?= h((string)($golfSession['distance_km'] ?? '')) ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Kalorie</label>
                                <input type="number" class="form-control" min="0" name="calories_burned"
                                       value="<?= h((string)($golfSession['calories_burned'] ?? '')) ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Doba (min)</label>
                                <input type="number" class="form-control" min="0" name="duration_minutes"
                                       value="<?= h((string)($golfSession['duration_minutes'] ?? '')) ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">HCP po hře</label>
                                <input type="number" class="form-control" step="0.1" name="handicap_after"
                                       value="<?= h((string)($golfSession['handicap_after'] ?? '')) ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold">Počasí</label>
                                <input type="text" class="form-control" name="weather"
                                       value="<?= h((string)($golfSession['weather'] ?? '')) ?>" placeholder="slunečno, vítr...">
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Spoluhráči</label>
                        <input type="text" class="form-control" name="players"
                               value="<?= h((string)($golfSession['players'] ?? '')) ?>" placeholder="Jména oddělená čárkou">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Pocit / poznámka</label>
                        <textarea class="form-control" name="feeling" rows="2" placeholder="Shrnutí kola..."><?= h((string)($golfSession['feeling'] ?? '')) ?></textarea>
                    </div>

                    <hr>
                    <h5>Jamky</h5>
                    <div class="table-responsive mb-3">
                        <table class="table table-sm table-bordered align-middle" id="holes-table">
                            <thead class="table-light">
                                <tr>
                                    <th>Jamka</th>
                                    <th>Par</th>
                                    <th>Skóre</th>
                                    <th>Poznámka</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php for ($i = 1; $i <= $numHolesForForm; $i++): ?>
                                <?php $hole = $holesByNumber[$i] ?? null; ?>
                                <tr>
                                    <td>
                                        <?= $i ?>
                                        <input type="hidden" name="hole_number[]" value="<?= $i ?>">
                                    </td>
                                    <td><input type="number" class="form-control form-control-sm" name="hole_par[]" min="1" max="10" value="<?= h((string)($hole['par'] ?? 4)) ?>"></td>
                                    <td><input type="number" class="form-control form-control-sm" name="hole_score[]" min="1" max="20" value="<?= h((string)($hole['score'] ?? '')) ?>"></td>
                                    <td><input type="text" class="form-control form-control-sm" name="hole_notes[]" value="<?= h((string)($hole['notes'] ?? '')) ?>"></td>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex gap-2 align-items-center">
                        <button type="submit" class="btn btn-success fw-bold">
                            <i class="fas fa-save me-1"></i>Uložit golf
                        </button>
                        <a href="<?= BASE_URL ?>/training_session.php?id=<?= $sessionId ?>" class="btn btn-secondary">Zpět na trénink</a>
                        <small id="golf-autosave-status" class="text-muted ms-2">Automatické ukládání zapnuto</small>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-dark text-white fw-bold">
                <i class="fas fa-history me-2"></i>Poslední kola
            </div>
            <div class="card-body p-0">
                <?php if (empty($history)): ?>
                <div class="text-center py-4 text-muted">Žádná historie.</div>
                <?php else: ?>
                <table class="table table-sm table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Datum</th>
                            <th>Hřiště</th>
                            <th>Skóre</th>
                            <th>Par</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $round): ?>
                        <tr>
                            <td><?= formatDate($round['completed_at'] ?? $round['ts_started_at']) ?></td>
                            <td><?= h((string)$round['course_name']) ?></td>
                            <td><?= (int)$round['total_score'] ?></td>
                            <td><?= (int)$round['total_par'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-info text-white fw-bold">
                <i class="fas fa-chart-line me-2"></i>Statistiky (90 dní)
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-2">
                        <strong><?= (int)$stats['total_rounds'] ?></strong><br>
                        <small class="text-muted">Kol</small>
                    </div>
                    <div class="col-md-2">
                        <strong><?= $stats['avg_handicap'] !== null ? number_format((float)$stats['avg_handicap'], 1, ',', ' ') : '–' ?></strong><br>
                        <small class="text-muted">Prům. HCP</small>
                    </div>
                    <div class="col-md-2">
                        <strong><?= number_format((float)$stats['total_km'], 1, ',', ' ') ?> km</strong><br>
                        <small class="text-muted">Uchozeno</small>
                    </div>
                    <div class="col-md-2">
                        <strong><?= (int)$stats['total_calories'] ?> kcal</strong><br>
                        <small class="text-muted">Kalorie</small>
                    </div>
                    <div class="col-md-2">
                        <strong><?= (int)$stats['total_score'] ?></strong><br>
                        <small class="text-muted">Skóre</small>
                    </div>
                    <div class="col-md-2">
                        <strong><?= (int)$stats['total_par'] ?></strong><br>
                        <small class="text-muted">Par</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const golfForm = document.getElementById('golf-form');
const autosaveStatus = document.getElementById('golf-autosave-status');
const apiUrl = '<?= BASE_URL ?>/api/save_golf_draft.php';
const sessionId = <?= (int)$sessionId ?>;

let saveTimer = null;
let saveInProgress = false;
let pendingSave = false;
let lastSavedHash = '';

function setStatus(text, cls) {
    autosaveStatus.classList.remove('text-muted', 'text-success', 'text-danger');
    autosaveStatus.classList.add(cls);
    autosaveStatus.textContent = text;
}

function collectGolfPayload() {
    const payload = {
        session_id: sessionId,
        course_name: (golfForm.querySelector('[name="course_name"]')?.value || '').trim(),
        num_holes: parseInt(golfForm.querySelector('[name="num_holes"]')?.value || '18', 10) || 18,
        game_type: golfForm.querySelector('[name="game_type"]')?.value || 'training',
        distance_km: golfForm.querySelector('[name="distance_km"]')?.value ?? '',
        calories_burned: golfForm.querySelector('[name="calories_burned"]')?.value ?? '',
        weather: (golfForm.querySelector('[name="weather"]')?.value || '').trim(),
        players: (golfForm.querySelector('[name="players"]')?.value || '').trim(),
        handicap_after: golfForm.querySelector('[name="handicap_after"]')?.value ?? '',
        duration_minutes: golfForm.querySelector('[name="duration_minutes"]')?.value ?? '',
        feeling: (golfForm.querySelector('[name="feeling"]')?.value || '').trim(),
        holes: []
    };

    const holeNumbers = golfForm.querySelectorAll('[name="hole_number[]"]');
    const holePars = golfForm.querySelectorAll('[name="hole_par[]"]');
    const holeScores = golfForm.querySelectorAll('[name="hole_score[]"]');
    const holeNotes = golfForm.querySelectorAll('[name="hole_notes[]"]');

    for (let i = 0; i < holeNumbers.length; i++) {
        payload.holes.push({
            hole_number: holeNumbers[i]?.value || '',
            par: holePars[i]?.value || '',
            score: holeScores[i]?.value || '',
            notes: (holeNotes[i]?.value || '').trim()
        });
    }

    return payload;
}

async function saveGolfDraft(immediate = false) {
    if (!immediate && saveInProgress) {
        pendingSave = true;
        return;
    }

    const payload = collectGolfPayload();
    const hash = JSON.stringify(payload);
    if (!immediate && hash === lastSavedHash) {
        return;
    }

    if (saveInProgress) {
        pendingSave = true;
        return;
    }

    saveInProgress = true;
    setStatus('Ukládám...', 'text-muted');

    try {
        const resp = await fetch(apiUrl, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const data = await resp.json();
        if (!data.success) {
            throw new Error(data.error || 'Uložení se nezdařilo');
        }
        lastSavedHash = hash;
        setStatus('Uloženo ' + (data.saved_at || ''), 'text-success');
    } catch (err) {
        setStatus('Neuloženo - zkontrolujte připojení', 'text-danger');
    } finally {
        saveInProgress = false;
        if (pendingSave) {
            pendingSave = false;
            saveGolfDraft(false);
        }
    }
}

function scheduleGolfAutosave() {
    setStatus('Neuložené změny', 'text-muted');
    if (saveTimer) {
        clearTimeout(saveTimer);
    }
    saveTimer = setTimeout(function() {
        saveGolfDraft(false);
    }, 700);
}

document.getElementById('num_holes').addEventListener('change', function() {
    const target = parseInt(this.value || '18', 10);
    if (target <= 0 || target > 36) {
        return;
    }

    const tbody = document.querySelector('#holes-table tbody');
    const existingRows = tbody.querySelectorAll('tr');

    if (existingRows.length === target) {
        return;
    }

    const oldData = {};
    existingRows.forEach(function(row) {
        const hole = parseInt(row.querySelector('input[name="hole_number[]"]').value, 10);
        oldData[hole] = {
            par: row.querySelector('input[name="hole_par[]"]').value,
            score: row.querySelector('input[name="hole_score[]"]').value,
            notes: row.querySelector('input[name="hole_notes[]"]').value
        };
    });

    tbody.innerHTML = '';
    for (let i = 1; i <= target; i++) {
        const data = oldData[i] || {par: '4', score: '', notes: ''};
        const tr = document.createElement('tr');
        tr.innerHTML =
            '<td>' + i + '<input type="hidden" name="hole_number[]" value="' + i + '"></td>' +
            '<td><input type="number" class="form-control form-control-sm" name="hole_par[]" min="1" max="10" value="' + data.par + '"></td>' +
            '<td><input type="number" class="form-control form-control-sm" name="hole_score[]" min="1" max="20" value="' + data.score + '"></td>' +
            '<td><input type="text" class="form-control form-control-sm" name="hole_notes[]" value="' + data.notes.replace(/"/g, '&quot;') + '"></td>';
        tbody.appendChild(tr);
    }

    scheduleGolfAutosave();
});

golfForm.addEventListener('input', function(e) {
    if (!e.target || e.target.type === 'hidden') {
        return;
    }
    scheduleGolfAutosave();
});

golfForm.addEventListener('change', function(e) {
    if (!e.target || e.target.type === 'hidden') {
        return;
    }
    scheduleGolfAutosave();
});

golfForm.addEventListener('submit', function() {
    setStatus('Ukládám...', 'text-muted');
});

window.addEventListener('beforeunload', function() {
    if (saveTimer) {
        clearTimeout(saveTimer);
    }
    saveGolfDraft(true);
});
</script>

<?php renderFooter(); ?>
