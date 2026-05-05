<?php
// admin/coach_add.php – přidání nového trenéra
require_once __DIR__ . '/../includes/admin_auth.php';
require_once __DIR__ . '/header.php';

requireAdminLogin();

$pdo   = getDB();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Neplatný bezpečnostní token.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $name     = trim($_POST['name']     ?? '');
        $email    = trim($_POST['email']    ?? '');
        $password = $_POST['password'] ?? '';
        $password2 = $_POST['password2'] ?? '';
        $isActive  = isset($_POST['is_active']) ? 1 : 0;

        if ($username === '') {
            $error = 'Zadejte uživatelské jméno.';
        } elseif (!preg_match('/^[a-z0-9_.\-]{3,50}$/i', $username)) {
            $error = 'Uživatelské jméno smí obsahovat jen písmena, číslice, tečku, pomlčku a podtržítko (3–50 znaků).';
        } elseif (strlen($password) < 6) {
            $error = 'Heslo musí mít alespoň 6 znaků.';
        } elseif ($password !== $password2) {
            $error = 'Hesla se neshodují.';
        } elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Neplatná e-mailová adresa.';
        } else {
            // Unikátnost uživatelského jména
            $stmt = $pdo->prepare('SELECT id FROM coaches WHERE username = ?');
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $error = 'Toto uživatelské jméno je již obsazeno.';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare(
                    'INSERT INTO coaches (username, password, name, email, is_active) VALUES (?, ?, ?, ?, ?)'
                )->execute([$username, $hash, $name ?: null, $email ?: null, $isActive]);
                flash('success', 'Trenér ' . $username . ' byl úspěšně přidán.');
                redirect(BASE_URL . '/admin/coaches.php');
            }
        }
    }
}

renderAdminHeader('Přidat trenéra');
?>

<div class="d-flex align-items-center mb-4 gap-3">
    <a href="<?= BASE_URL ?>/admin/coaches.php" class="btn btn-outline-secondary btn-sm">
        <i class="fas fa-arrow-left"></i>
    </a>
    <h4 class="fw-bold mb-0">
        <i class="fas fa-user-plus me-2" style="color:#a78bfa"></i>Přidat trenéra
    </h4>
</div>

<?php if ($error): ?>
<div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<div class="card border-0 shadow-sm" style="max-width:600px">
    <div class="card-body p-4">
        <form method="post" novalidate>
            <?= csrfField() ?>
            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label class="form-label fw-semibold">Jméno trenéra</label>
                    <input type="text" name="name" class="form-control"
                           value="<?= h($_POST['name'] ?? '') ?>"
                           placeholder="Jan Novák">
                </div>
                <div class="col-sm-6">
                    <label class="form-label fw-semibold">
                        Uživatelské jméno <span class="text-danger">*</span>
                    </label>
                    <input type="text" name="username" class="form-control"
                           value="<?= h($_POST['username'] ?? '') ?>"
                           required autofocus autocomplete="off">
                    <div class="form-text">3–50 znaků: písmena, číslice, . - _</div>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">E-mail</label>
                <input type="email" name="email" class="form-control"
                       value="<?= h($_POST['email'] ?? '') ?>"
                       placeholder="trener@example.com">
            </div>
            <div class="row g-3 mb-3">
                <div class="col-sm-6">
                    <label class="form-label fw-semibold">
                        Heslo <span class="text-danger">*</span>
                        <small class="text-muted fw-normal">(min. 6 znaků)</small>
                    </label>
                    <input type="password" name="password" class="form-control"
                           required autocomplete="new-password">
                </div>
                <div class="col-sm-6">
                    <label class="form-label fw-semibold">
                        Heslo znovu <span class="text-danger">*</span>
                    </label>
                    <input type="password" name="password2" class="form-control"
                           required autocomplete="new-password">
                </div>
            </div>
            <div class="mb-4">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="is_active"
                           id="isActive" value="1"
                           <?= (!isset($_POST['is_active']) || $_POST['is_active']) ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="isActive">
                        Trenér je aktivní (může se přihlásit)
                    </label>
                </div>
            </div>
            <div class="d-flex gap-2">
                <button type="submit" class="btn fw-bold px-4"
                        style="background:#7c3aed;color:#fff;border:none">
                    <i class="fas fa-save me-1"></i>Vytvořit trenéra
                </button>
                <a href="<?= BASE_URL ?>/admin/coaches.php" class="btn btn-outline-secondary">Zrušit</a>
            </div>
        </form>
    </div>
</div>

<?php renderAdminFooter(); ?>
