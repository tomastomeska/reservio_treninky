<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php';

requireLogin();

$coachId = getCurrentCoachId();
$pdo     = getDB();

// Načtení sportovců s doplňkovými info
$stmt = $pdo->prepare(
    'SELECT a.*,
            (SELECT COUNT(*) FROM training_sessions ts
                         WHERE ts.athlete_id = a.id
                             AND ts.completed_at IS NOT NULL
                             AND ts.deleted_by_coach_at IS NULL) AS session_count,
            (SELECT ts2.started_at FROM training_sessions ts2
                         WHERE ts2.athlete_id = a.id
                             AND ts2.completed_at IS NOT NULL
                             AND ts2.deleted_by_coach_at IS NULL
             ORDER BY ts2.completed_at DESC LIMIT 1) AS last_session_date,
            (SELECT ws.name FROM training_sessions ts3
             JOIN workout_sets ws ON ts3.workout_set_id = ws.id
                         WHERE ts3.athlete_id = a.id
                             AND ts3.completed_at IS NOT NULL
                             AND ts3.deleted_by_coach_at IS NULL
             ORDER BY ts3.completed_at DESC LIMIT 1) AS last_set_name
     FROM athletes a
     WHERE a.coach_id = ?
     ORDER BY a.last_name, a.first_name'
);
$stmt->execute([$coachId]);
$athletes = $stmt->fetchAll();

renderHeader('Dashboard');
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <h2 class="mb-0"><i class="fas fa-users me-2 text-warning"></i>Moji sportovci</h2>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (count($athletes) >= 2): ?>
        <a href="<?= BASE_URL ?>/training_paired_start.php" class="btn btn-outline-warning btn-sm fw-bold">
            <i class="fas fa-people-group me-1"></i>Párový trénink
        </a>
        <?php endif; ?>
        <a href="<?= BASE_URL ?>/athlete_add.php" class="btn btn-warning btn-sm fw-bold">
            <i class="fas fa-plus me-1"></i>Přidat sportovce
        </a>
    </div>
</div>

<?php if (empty($athletes)): ?>
<div class="card border-0 shadow-sm">
    <div class="card-body text-center py-5">
        <div class="display-3 text-muted mb-3">🏃</div>
        <h4 class="text-muted">Zatím nemáte žádné sportovce</h4>
        <p class="text-muted">Přidejte prvního sportovce a začněte trénovat!</p>
        <a href="<?= BASE_URL ?>/athlete_add.php" class="btn btn-warning fw-bold">
            <i class="fas fa-plus me-1"></i>Přidat sportovce
        </a>
    </div>
</div>
<?php else: ?>
<div class="row g-3">
    <?php foreach ($athletes as $a): ?>
    <div class="col-md-6 col-xl-4">
        <div class="card athlete-card border-0 shadow-sm h-100">
            <div class="card-body">
                <?php if ($a['photo']): ?>
                <div class="text-center mb-3">
                    <img src="<?= h(photoUrl($a['photo'], 'athletes')) ?>" alt="Fotografie"
                         class="rounded-circle"
                         style="width:100px;height:100px;object-fit:cover;border:3px solid #ffc107;">
                </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h5 class="card-title mb-0 fw-bold">
                            <?= h($a['first_name'] . ' ' . $a['last_name']) ?>
                        </h5>
                        <?php $age = calculateAge($a['birth_date'] ?? null); ?>
                        <small class="text-muted"><?= $age !== null ? $age . ' let' : '' ?></small>
                    </div>
                    <span class="badge bg-warning text-dark rounded-pill fs-6">
                        <?= $a['session_count'] ?>×
                    </span>
                </div>

                <?php if ($a['email']): ?>
                <p class="text-muted small mb-2">
                    <i class="fas fa-envelope me-1"></i><?= h($a['email']) ?>
                </p>
                <?php endif; ?>

                <?php if ($a['phone_contact']): ?>
                <p class="text-muted small mb-2">
                    <i class="fas fa-phone me-1"></i><?= h($a['phone_contact']) ?>
                </p>
                <?php endif; ?>

                <div class="mb-3">
                    <?php if ($a['last_session_date']): ?>
                    <span class="badge bg-light text-dark border me-1">
                        <i class="fas fa-clock me-1"></i>Poslední trénink: <?= formatDate($a['last_session_date']) ?>
                    </span>
                    <?php if ($a['last_set_name']): ?>
                    <span class="badge bg-secondary">
                        <i class="fas fa-layer-group me-1"></i><?= h($a['last_set_name']) ?>
                    </span>
                    <?php endif; ?>
                    <?php else: ?>
                    <span class="badge bg-light text-muted border">Žádný trénink</span>
                    <?php endif; ?>
                </div>

                <div class="d-flex gap-2">
                    <a href="<?= BASE_URL ?>/athlete_detail.php?id=<?= $a['id'] ?>"
                       class="btn btn-dark btn-sm flex-fill">
                        <i class="fas fa-user me-1"></i>Detail
                    </a>
                    <a href="<?= BASE_URL ?>/training_new.php?athlete_id=<?= $a['id'] ?>"
                       class="btn btn-warning btn-sm flex-fill">
                        <i class="fas fa-play me-1"></i>Trénink
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php renderFooter(); ?>
