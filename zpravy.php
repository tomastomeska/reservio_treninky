<?php
// zpravy.php – seznam zpráv pro přihlášeného trenéra
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php';

requireLogin();
$coachId = getCurrentCoachId();
$pdo     = getDB();

// Zprávy pro tohoto trenéra
$messages = $pdo->prepare("
    SELECT m.id, m.subject, m.sent_at, m.attachment_name,
           r.read_at
    FROM admin_messages m
    JOIN admin_message_recipients r ON r.message_id = m.id AND r.coach_id = ?
    ORDER BY m.sent_at DESC
");
$messages->execute([$coachId]);
$messages = $messages->fetchAll();

$unreadCount = count(array_filter($messages, fn($m) => $m['read_at'] === null));

renderHeader('Zprávy');
?>

<h3 class="fw-bold mb-4">
    <i class="fas fa-envelope me-2 text-primary"></i>Moje zprávy
    <?php if ($unreadCount > 0): ?>
    <span class="badge bg-danger ms-2"><?= $unreadCount ?> nepřečtená</span>
    <?php endif; ?>
</h3>

<?php if (empty($messages)): ?>
<div class="alert alert-info"><i class="fas fa-info-circle me-2"></i>Nemáte žádné zprávy.</div>
<?php else: ?>
<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-dark">
                <tr>
                    <th></th>
                    <th>Předmět</th>
                    <th>Datum</th>
                    <th>Příloha</th>
                    <th>Stav</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($messages as $m): ?>
            <?php $unread = $m['read_at'] === null; ?>
            <tr class="<?= $unread ? 'table-warning fw-semibold' : '' ?>">
                <td style="width:24px">
                    <?php if ($unread): ?>
                    <i class="fas fa-circle text-danger" style="font-size:.6rem" title="Nepřečteno"></i>
                    <?php else: ?>
                    <i class="fas fa-circle text-success" style="font-size:.6rem" title="Přečteno"></i>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="<?= BASE_URL ?>/zprava_detail.php?id=<?= $m['id'] ?>" class="text-decoration-none text-dark">
                        <?= h($m['subject']) ?>
                    </a>
                </td>
                <td class="text-nowrap"><?= date('d.m.Y H:i', strtotime($m['sent_at'])) ?></td>
                <td>
                    <?php if ($m['attachment_name']): ?>
                    <i class="fas fa-paperclip text-muted" title="<?= h($m['attachment_name']) ?>"></i>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($unread): ?>
                    <span class="badge bg-danger">Nepřečteno</span>
                    <?php else: ?>
                    <span class="badge bg-success">Přečteno <?= date('d.m.Y', strtotime($m['read_at'])) ?></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php renderFooter(); ?>
