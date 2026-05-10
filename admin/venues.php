<?php
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/header.php';

requireAdminLogin();

$pdo = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        die('Neplatný CSRF token.');
    }

    $action = (string)($_POST['action'] ?? '');

    if ($action === 'add') {
        $name = normalizeTrainingVenueName($_POST['name'] ?? '');
        $address = trim((string)($_POST['address'] ?? ''));
        $note = trim((string)($_POST['note'] ?? ''));

        if ($name === '') {
            flash('danger', 'Název sportoviště je povinný.');
            redirect(BASE_URL . '/admin/venues.php');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO training_venues (name, address, note, is_active)
             VALUES (?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE
                address = VALUES(address),
                note = VALUES(note),
                is_active = 1'
        );
        $stmt->execute([
            $name,
            $address !== '' ? $address : null,
            $note !== '' ? $note : null,
        ]);

        flash('success', 'Sportoviště bylo uloženo.');
        redirect(BASE_URL . '/admin/venues.php');
    }

    if ($action === 'update') {
        $venueId = (int)($_POST['venue_id'] ?? 0);
        $name = normalizeTrainingVenueName($_POST['name'] ?? '');
        $address = trim((string)($_POST['address'] ?? ''));
        $note = trim((string)($_POST['note'] ?? ''));
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($venueId <= 0 || $name === '') {
            flash('danger', 'Sportoviště se nepodařilo uložit.');
            redirect(BASE_URL . '/admin/venues.php');
        }

        $currentStmt = $pdo->prepare('SELECT name FROM training_venues WHERE id = ?');
        $currentStmt->execute([$venueId]);
        $currentVenue = $currentStmt->fetch();
        if (!$currentVenue) {
            flash('danger', 'Sportoviště nebylo nalezeno.');
            redirect(BASE_URL . '/admin/venues.php');
        }

        $oldName = (string)$currentVenue['name'];

        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            'UPDATE training_venues
             SET name = ?, address = ?, note = ?, is_active = ?, updated_at = NOW()
             WHERE id = ?'
        );

        try {
            $stmt->execute([
                $name,
                $address !== '' ? $address : null,
                $note !== '' ? $note : null,
                $isActive,
                $venueId,
            ]);

            if ($oldName !== $name) {
                $pdo->prepare('UPDATE training_sessions SET location = ? WHERE location = ?')
                    ->execute([$name, $oldName]);
                $pdo->prepare('UPDATE run_treadmill_sessions SET location = ? WHERE location = ?')
                    ->execute([$name, $oldName]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            flash('danger', 'Sportoviště se nepodařilo uložit. Zkontrolujte, zda už stejný název neexistuje.');
            redirect(BASE_URL . '/admin/venues.php');
        }

        flash('success', 'Sportoviště bylo upraveno.');
        redirect(BASE_URL . '/admin/venues.php');
    }
}

$venues = $pdo->query(
    'SELECT tv.*, c.name AS coach_name, c.username AS coach_username,
            (SELECT COUNT(*) FROM training_sessions ts
             WHERE ts.location COLLATE utf8mb4_unicode_ci = tv.name COLLATE utf8mb4_unicode_ci) AS usage_count
     FROM training_venues tv
     LEFT JOIN coaches c ON c.id = tv.created_by_coach_id
     ORDER BY tv.is_active DESC, tv.name ASC'
)->fetchAll();

renderAdminHeader('Sportoviště');
?>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-3 mb-4">
    <div>
        <h2 class="mb-1 fw-bold"><i class="fas fa-map-location-dot me-2" style="color:#a78bfa"></i>Sportoviště a místa</h2>
        <div class="text-muted">Katalog míst pro všechny tréninkové formuláře kromě golfu.</div>
    </div>
    <div class="badge text-bg-dark px-3 py-2"><?= count($venues) ?> míst</div>
</div>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-dark text-white fw-semibold">Přidat sportoviště</div>
    <div class="card-body">
        <form method="post" class="row g-3">
            <?= csrfField() ?>
            <input type="hidden" name="action" value="add">
            <div class="col-md-4">
                <label class="form-label fw-semibold">Název</label>
                <input type="text" name="name" class="form-control" maxlength="255" required placeholder="např. Posilovna Royal Brno">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Adresa</label>
                <input type="text" name="address" class="form-control" maxlength="255" placeholder="např. U Stadionu 12, Brno">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">Poznámka</label>
                <input type="text" name="note" class="form-control" maxlength="500" placeholder="Parkování vzadu, vstup z boku...">
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100 fw-bold">
                    <i class="fas fa-plus me-1"></i>Přidat
                </button>
            </div>
        </form>
    </div>
</div>

<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold">Seznam sportovišť</div>
    <div class="card-body p-0">
        <?php if (empty($venues)): ?>
        <div class="text-center py-5 text-muted">Zatím tu není žádné sportoviště.</div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Název</th>
                        <th>Adresa</th>
                        <th>Poznámka</th>
                        <th>Přidal</th>
                        <th>Použití</th>
                        <th>Aktivní</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($venues as $venue): ?>
                    <tr>
                        <td colspan="6" class="p-0 border-0">
                            <form method="post" class="row g-0 align-items-center border-top px-3 py-3">
                                <?= csrfField() ?>
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="venue_id" value="<?= (int)$venue['id'] ?>">

                                <div class="col-md-3 pe-md-2 mb-2 mb-md-0">
                                    <input type="text" name="name" class="form-control" maxlength="255" required value="<?= h((string)$venue['name']) ?>">
                                </div>
                                <div class="col-md-3 pe-md-2 mb-2 mb-md-0">
                                    <input type="text" name="address" class="form-control" maxlength="255" value="<?= h((string)($venue['address'] ?? '')) ?>" placeholder="Adresa...">
                                </div>
                                <div class="col-md-2 pe-md-2 mb-2 mb-md-0">
                                    <input type="text" name="note" class="form-control" maxlength="500" value="<?= h((string)($venue['note'] ?? $venue['admin_note'] ?? '')) ?>" placeholder="Poznámka...">
                                </div>
                                <div class="col-md-2 pe-md-2 mb-2 mb-md-0 text-muted small">
                                    <?php if (!empty($venue['coach_name']) || !empty($venue['coach_username'])): ?>
                                    <?= h((string)($venue['coach_name'] ?: $venue['coach_username'])) ?>
                                    <?php else: ?>
                                    Admin nebo import
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-1 pe-md-1 mb-2 mb-md-0 text-muted small">
                                    <?= (int)$venue['usage_count'] ?>x
                                </div>
                                <div class="col-md-1 pe-md-1 mb-2 mb-md-0 text-center">
                                    <div class="form-check d-inline-flex align-items-center justify-content-center m-0">
                                        <input class="form-check-input" type="checkbox" name="is_active" value="1" <?= (int)$venue['is_active'] === 1 ? 'checked' : '' ?>>
                                    </div>
                                </div>
                                <div class="col-md-1 text-md-end">
                                    <button type="submit" class="btn btn-outline-primary fw-semibold">
                                        <i class="fas fa-save me-1"></i>Uložit
                                    </button>
                                </div>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php renderAdminFooter(); ?>