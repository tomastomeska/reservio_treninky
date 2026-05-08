<?php
// admin/zprava_nova.php – odeslat novou zprávu trenérům
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/header.php';

requireAdminLogin();
$pdo = getDB();

$coaches = $pdo->query("SELECT id, name, username, email FROM coaches WHERE is_active = 1 ORDER BY name")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
		flash('danger', 'Neplatný bezpečnostní token.');
		redirect(BASE_URL . '/admin/zpravy.php');
	}

	$subject    = trim($_POST['subject'] ?? '');
	$body       = trim($_POST['body'] ?? '');
	$recipients = $_POST['recipients'] ?? [];    // 'all' nebo pole ID

	if ($subject === '') $errors[] = 'Předmět nesmí být prázdný.';
	if ($body === '')    $errors[] = 'Text zprávy nesmí být prázdný.';

	// Připrav seznam trenérů
	if (in_array('all', (array)$recipients, true)) {
		$coachIds = array_column($coaches, 'id');
	} else {
		$coachIds = array_map('intval', array_filter((array)$recipients));
	}
	if (empty($coachIds)) $errors[] = 'Vyberte alespoň jednoho příjemce.';

	// Upload přílohy
	$attachPath = null;
	$attachName = null;
	if (!empty($_FILES['attachment']['name'])) {
		$origName = $_FILES['attachment']['name'];
		$tmpPath  = $_FILES['attachment']['tmp_name'];
		$size     = $_FILES['attachment']['size'];
		$err      = $_FILES['attachment']['error'];

		if ($err !== UPLOAD_ERR_OK) {
			$errors[] = 'Chyba při nahrávání přílohy (kód ' . $err . ').';
		} elseif ($size > 50 * 1024 * 1024) {
			$errors[] = 'Příloha nesmí být větší než 50 MB.';
		} else {
			$ext     = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
			$allowed = ['pdf','doc','docx','xls','xlsx','csv','jpg','jpeg','png','gif','webp',
			            'zip','rar','7z','mp4','mov','avi','mkv','txt','ppt','pptx'];
			if (!in_array($ext, $allowed, true)) {
				$errors[] = 'Typ souboru .' . h($ext) . ' není povolen.';
			} else {
				$uploadDir = dirname(__DIR__) . '/uploads/messages/';
				if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
				$newName    = time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
				$attachName = $origName;
				if (!move_uploaded_file($tmpPath, $uploadDir . $newName)) {
					$errors[] = 'Přílohu se nepodařilo uložit.';
				} else {
					$attachPath = $newName;
				}
			}
		}
	}

	if (empty($errors)) {
		$pdo->beginTransaction();
		try {
			$stmtMsg = $pdo->prepare("
				INSERT INTO admin_messages (subject, body, attachment_path, attachment_name, sent_at)
				VALUES (?, ?, ?, ?, NOW())
			");
			$stmtMsg->execute([$subject, $body, $attachPath, $attachName]);
			$messageId = (int)$pdo->lastInsertId();

			$stmtRec = $pdo->prepare("
				INSERT IGNORE INTO admin_message_recipients (message_id, coach_id) VALUES (?, ?)
			");
			foreach ($coachIds as $cid) {
				$stmtRec->execute([$messageId, $cid]);
			}
			$pdo->commit();
			flash('success', 'Zpráva byla odeslána ' . count($coachIds) . ' trenérům.');
			redirect(BASE_URL . '/admin/zprava_detail.php?id=' . $messageId);
		} catch (Throwable $e) {
			$pdo->rollBack();
			error_log('zprava_nova error: ' . $e->getMessage());
			$errors[] = 'Databázová chyba, zkuste to znovu.';
		}
	}
}

renderAdminHeader('Nová zpráva');
?>

<div class="d-flex align-items-center mb-4 gap-3">
	<a href="<?= BASE_URL ?>/admin/zpravy.php" class="btn btn-outline-secondary btn-sm">
		<i class="fas fa-arrow-left"></i>
	</a>
	<h2 class="fw-bold mb-0"><i class="fas fa-pen me-2 text-primary"></i>Nová zpráva</h2>
</div>

<?php if ($errors): ?>
<div class="alert alert-danger">
	<ul class="mb-0"><?php foreach ($errors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="post" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?= csrfToken() ?>">

<div class="row g-4">
	<div class="col-lg-8">
		<div class="card shadow-sm">
			<div class="card-body">
				<div class="mb-3">
					<label class="form-label fw-semibold">Předmět *</label>
					<input type="text" name="subject" class="form-control"
					       value="<?= h($_POST['subject'] ?? '') ?>" required maxlength="255">
				</div>
				<div class="mb-3">
					<label class="form-label fw-semibold">Text zprávy *</label>
					<textarea name="body" class="form-control" rows="10" required
					          style="resize:vertical"><?= h($_POST['body'] ?? '') ?></textarea>
				</div>
				<div class="mb-3">
					<label class="form-label fw-semibold">Příloha <small class="text-muted">(max 50 MB – PDF, Word, Excel, obrázky, ZIP, video…)</small></label>
					<input type="file" name="attachment" class="form-control"
					       accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.jpg,.jpeg,.png,.gif,.webp,.zip,.rar,.7z,.mp4,.mov,.avi,.mkv,.txt,.ppt,.pptx">
				</div>
			</div>
		</div>
	</div>

	<div class="col-lg-4">
		<div class="card shadow-sm">
			<div class="card-header fw-semibold">
				<i class="fas fa-users me-1"></i>Příjemci
			</div>
			<div class="card-body">
				<div class="form-check mb-2">
					<input class="form-check-input" type="checkbox" name="recipients[]"
					       value="all" id="chk_all"
					       <?= in_array('all', (array)($_POST['recipients'] ?? []), true) ? 'checked' : '' ?>>
					<label class="form-check-label fw-semibold text-primary" for="chk_all">
						Všichni aktivní trenéři (<?= count($coaches) ?>)
					</label>
				</div>
				<hr class="my-2">
				<div style="max-height:350px;overflow-y:auto">
				<?php foreach ($coaches as $c): ?>
				<div class="form-check">
					<input class="form-check-input coach-chk" type="checkbox" name="recipients[]"
					       value="<?= $c['id'] ?>" id="chk_<?= $c['id'] ?>"
					       <?= in_array((string)$c['id'], (array)($_POST['recipients'] ?? []), true) ? 'checked' : '' ?>>
					<label class="form-check-label" for="chk_<?= $c['id'] ?>">
						<?= h($c['name'] ?: $c['username']) ?>
						<?php if ($c['email']): ?>
						<small class="text-muted">(<?= h($c['email']) ?>)</small>
						<?php endif; ?>
					</label>
				</div>
				<?php endforeach; ?>
				</div>
			</div>
		</div>

		<div class="d-grid mt-3">
			<button type="submit" class="btn btn-primary btn-lg">
				<i class="fas fa-paper-plane me-2"></i>Odeslat zprávu
			</button>
		</div>
	</div>
</div>
</form>

<script>
// "Všichni" checkbox ovládá ostatní
document.getElementById('chk_all').addEventListener('change', function() {
	document.querySelectorAll('.coach-chk').forEach(cb => {
		cb.checked = this.checked;
		cb.disabled = this.checked;
	});
});
// Pokud je všichni zaškrtnut při načtení, odblokuj ostatní
(function() {
	const all = document.getElementById('chk_all');
	if (all.checked) {
		document.querySelectorAll('.coach-chk').forEach(cb => cb.disabled = true);
	}
})();
</script>

<?php renderAdminFooter(); ?>
