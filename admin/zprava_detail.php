<?php
// admin/zprava_detail.php – detail zprávy a přehled přečtení
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/header.php';

requireAdminLogin();
$pdo = getDB();

$id = intParam($_GET, 'id');
$msg = $pdo->prepare("SELECT * FROM admin_messages WHERE id = ?");
$msg->execute([$id]);
$message = $msg->fetch();
if (!$message) {
	flash('danger', 'Zpráva nebyla nalezena.');
	redirect(BASE_URL . '/admin/zpravy.php');
}

// Příjemci se stavem přečtení
$recipients = $pdo->prepare("
	SELECT r.*, c.name AS coach_name, c.username, c.email
	FROM admin_message_recipients r
	JOIN coaches c ON c.id = r.coach_id
	WHERE r.message_id = ?
	ORDER BY c.name
");
$recipients->execute([$id]);
$recipients = $recipients->fetchAll();

$totalCount = count($recipients);
$readCount  = count(array_filter($recipients, fn($r) => $r['read_at'] !== null));

renderAdminHeader('Detail zprávy');
?>

<div class="d-flex align-items-center mb-4 gap-3">
	<a href="<?= BASE_URL ?>/admin/zpravy.php" class="btn btn-outline-secondary btn-sm">
		<i class="fas fa-arrow-left"></i>
	</a>
	<h2 class="fw-bold mb-0"><i class="fas fa-envelope-open me-2 text-primary"></i>Detail zprávy</h2>
</div>

<div class="row g-4">
	<div class="col-lg-7">
		<div class="card shadow-sm mb-4">
			<div class="card-header d-flex justify-content-between align-items-center">
				<span class="fw-semibold fs-5"><?= h($message['subject']) ?></span>
				<span class="text-muted small"><?= date('d.m.Y H:i', strtotime($message['sent_at'])) ?></span>
			</div>
			<div class="card-body">
				<pre style="white-space:pre-wrap;font-family:inherit;margin:0"><?= h($message['body']) ?></pre>
			</div>
			<?php if ($message['attachment_name']): ?>
			<div class="card-footer">
				<i class="fas fa-paperclip me-1 text-muted"></i>
				<a href="<?= BASE_URL ?>/uploads/messages/<?= rawurlencode($message['attachment_path']) ?>"
				   target="_blank" class="text-decoration-none">
					<?= h($message['attachment_name']) ?>
				</a>
			</div>
			<?php endif; ?>
		</div>
	</div>

	<div class="col-lg-5">
		<div class="card shadow-sm">
			<div class="card-header d-flex justify-content-between">
				<span class="fw-semibold"><i class="fas fa-users me-1"></i>Příjemci</span>
				<span class="badge bg-<?= $readCount === $totalCount ? 'success' : 'warning' ?>">
					Přečteno: <?= $readCount ?>/<?= $totalCount ?>
				</span>
			</div>
			<div class="card-body p-0">
				<?php if (empty($recipients)): ?>
				<div class="p-3 text-muted">Žádní příjemci.</div>
				<?php else: ?>
				<ul class="list-group list-group-flush">
				<?php foreach ($recipients as $r): ?>
				<li class="list-group-item d-flex justify-content-between align-items-center py-2">
					<div>
						<i class="fas fa-user-tie me-1 text-muted"></i>
						<strong><?= h($r['coach_name'] ?: $r['username']) ?></strong>
						<?php if ($r['email']): ?>
						<br><small class="text-muted"><?= h($r['email']) ?></small>
						<?php endif; ?>
					</div>
					<div class="text-end">
						<?php if ($r['read_at']): ?>
						<span class="badge bg-success">
							<i class="fas fa-check me-1"></i>Přečteno
						</span>
						<br><small class="text-muted"><?= date('d.m.Y H:i', strtotime($r['read_at'])) ?></small>
						<?php else: ?>
						<span class="badge bg-secondary">Nepřečteno</span>
						<?php endif; ?>
					</div>
				</li>
				<?php endforeach; ?>
				</ul>
				<?php endif; ?>
			</div>
		</div>
	</div>
</div>

<?php renderAdminFooter(); ?>
