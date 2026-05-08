<?php
// zprava_detail.php – zobrazení a potvrzení přečtení zprávy
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php';

requireLogin();
$coachId = getCurrentCoachId();
$pdo     = getDB();

$id = intParam($_GET, 'id');

// Načti zprávu – jen pokud je trenér příjemcem
$stmt = $pdo->prepare("
    SELECT m.*, r.read_at, r.id AS recipient_id
    FROM admin_messages m
    JOIN admin_message_recipients r ON r.message_id = m.id AND r.coach_id = ?
    WHERE m.id = ?
");
$stmt->execute([$coachId, $id]);
$message = $stmt->fetch();

if (!$message) {
    flash('danger', 'Zpráva nebyla nalezena.');
    redirect(BASE_URL . '/zpravy.php');
}

// Zpracování potvrzení přečtení
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm_read') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        flash('danger', 'Neplatný bezpečnostní token.');
        redirect(BASE_URL . '/zpravy.php');
    }
    if ($message['read_at'] === null) {
        $pdo->prepare("
            UPDATE admin_message_recipients SET read_at = NOW()
            WHERE message_id = ? AND coach_id = ?
        ")->execute([$id, $coachId]);
    }
    redirect(BASE_URL . '/zpravy.php');
}

renderHeader('Zpráva: ' . $message['subject']);
?>

<div class="d-flex align-items-center mb-4 gap-3">
    <a href="<?= BASE_URL ?>/zpravy.php" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left"></i>
    </a>
    <h3 class="fw-bold mb-0"><i class="fas fa-envelope-open me-2 text-primary"></i>Zpráva</h3>
</div>

<div class="row justify-content-center">
<div class="col-lg-8">

<?php if ($message['read_at'] === null): ?>
<div class="alert alert-warning d-flex align-items-center gap-2 mb-4">
    <i class="fas fa-exclamation-triangle fs-5"></i>
    <span><strong>Nepřečtená zpráva.</strong> Po přečtení prosím potvrďte přečtení tlačítkem níže.</span>
</div>
<?php endif; ?>

<div class="card shadow-sm mb-4">
    <div class="card-header d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <div class="fw-bold fs-5"><?= h($message['subject']) ?></div>
            <div class="text-muted small">
                <i class="fas fa-calendar me-1"></i><?= date('d.m.Y H:i', strtotime($message['sent_at'])) ?>
                &nbsp;&nbsp;
                <i class="fas fa-user-shield me-1"></i>Administrátor TrainerApp
            </div>
        </div>
        <?php if ($message['read_at']): ?>
        <span class="badge bg-success align-self-center">
            <i class="fas fa-check me-1"></i>Přečteno <?= date('d.m.Y H:i', strtotime($message['read_at'])) ?>
        </span>
        <?php else: ?>
        <span class="badge bg-danger align-self-center">Nepřečteno</span>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <div style="white-space:pre-wrap;font-size:1rem;line-height:1.7"><?= h($message['body']) ?></div>
    </div>
    <?php if ($message['attachment_name']): ?>
    <div class="card-footer">
        <i class="fas fa-paperclip me-1 text-muted"></i>
        <strong>Příloha:</strong>
        <a href="<?= BASE_URL ?>/uploads/messages/<?= rawurlencode($message['attachment_path']) ?>"
           target="_blank" class="ms-1">
            <?= h($message['attachment_name']) ?>
        </a>
    </div>
    <?php endif; ?>
</div>

<?php if ($message['read_at'] === null): ?>
<div class="card border-danger shadow-sm">
    <div class="card-body text-center py-4">
        <p class="mb-3 fw-semibold">
            <i class="fas fa-hand-point-down me-2 text-danger"></i>
            Po přečtení zprávy prosím potvrďte, že jste ji přečetli.
        </p>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">
            <input type="hidden" name="action" value="confirm_read">
            <button type="submit" class="btn btn-danger btn-lg px-5">
                <i class="fas fa-check-circle me-2"></i>Potvrzuji přečtení
            </button>
        </form>
    </div>
</div>
<?php else: ?>
<div class="text-center">
    <a href="<?= BASE_URL ?>/zpravy.php" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i>Zpět na zprávy
    </a>
</div>
<?php endif; ?>

</div>
</div>

<?php renderFooter(); ?>
