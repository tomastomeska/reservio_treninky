<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/functions.php';

// Přesměrovat přihlášeného na dashboard
if (isLoggedIn()) {
    redirect(BASE_URL . '/dashboard.php');
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        $error = 'Neplatný bezpečnostní token. Zkuste to znovu.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Vyplňte uživatelské jméno i heslo.';
        } else {
            $pdo  = getDB();
            $stmt = $pdo->prepare('SELECT id, password, name, is_active FROM coaches WHERE username = ?');
            $stmt->execute([$username]);
            $coach = $stmt->fetch();

            if ($coach && password_verify($password, $coach['password'])) {
                if (!$coach['is_active']) {
                    $error = 'Váš účet byl zablokován. Kontaktujte správce.';
                } else {
                    session_regenerate_id(true);
                    $_SESSION['coach_id']   = $coach['id'];
                    $_SESSION['coach_name'] = $coach['name'] ?: $username;
                    // Aktualizace posledního přihlášení
                    $pdo->prepare('UPDATE coaches SET last_login = NOW() WHERE id = ?')->execute([$coach['id']]);
                    redirect(BASE_URL . '/dashboard.php');
                }
            } else {
                $error = 'Nesprávné přihlašovací údaje.';
            }
        }
    }
}

$logoFile = null;
$logoDir = __DIR__ . '/uploads/logo';
if (is_dir($logoDir)) {
    $allowedExt = ['png', 'jpg', 'jpeg', 'webp', 'svg'];
    foreach (scandir($logoDir) ?: [] as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, $allowedExt, true)) {
            $logoFile = $file;
            break;
        }
    }
}
$logoUrl = $logoFile ? (BASE_URL . '/uploads/logo/' . rawurlencode($logoFile)) : null;
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Přihlášení – <?= APP_NAME ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <style>
        :root {
            --brand-dark: #0e1f45;
            --brand-darker: #08142e;
            --brand-gold: #f3b300;
            --panel-bg: rgba(255, 255, 255, 0.97);
            --text-main: #1b2433;
            --text-soft: #6b7485;
        }

        html,
        body {
            min-height: 100%;
        }

        body {
            margin: 0;
            font-family: "Segoe UI", "Trebuchet MS", sans-serif;
            background:
                radial-gradient(1100px 700px at -10% 110%, rgba(243, 179, 0, 0.28), transparent 52%),
                radial-gradient(850px 540px at 120% -5%, rgba(88, 130, 255, 0.28), transparent 55%),
                linear-gradient(140deg, var(--brand-darker), #10275b 55%, var(--brand-dark));
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 28px 16px;
        }

        .login-wrap {
            width: 100%;
            max-width: 500px;
        }

        .brand {
            text-align: center;
            margin-bottom: 20px;
        }

        .brand-logo {
            max-width: 220px;
            width: 100%;
            height: auto;
            display: inline-block;
            cursor: pointer;
            user-select: none;
            filter: drop-shadow(0 8px 24px rgba(0, 0, 0, 0.35));
        }

        .brand-fallback {
            color: #ffffff;
            font-size: 2rem;
            font-weight: 800;
            letter-spacing: 0.5px;
            margin: 0;
            cursor: pointer;
            user-select: none;
            text-shadow: 0 6px 22px rgba(0, 0, 0, 0.35);
        }

        .brand-subtitle {
            color: #d6deef;
            margin: 10px 0 0;
            font-size: 1.1rem;
        }

        .login-card {
            background: var(--panel-bg);
            border: 0;
            border-radius: 18px;
            box-shadow: 0 18px 50px rgba(7, 18, 44, 0.45);
            overflow: hidden;
        }

        .login-card .card-body {
            padding: 30px 24px;
        }

        .login-title {
            text-align: center;
            font-size: 2rem;
            margin: 0 0 4px;
            font-weight: 800;
            color: #0f234f;
        }

        .login-sub {
            text-align: center;
            margin: 0 0 22px;
            color: var(--text-soft);
        }

        .form-label {
            font-weight: 700;
            color: #263552;
            margin-bottom: 7px;
        }

        .form-control {
            border-radius: 12px;
            border: 2px solid #d8dfeb;
            min-height: 52px;
            font-size: 1rem;
        }

        .form-control:focus {
            border-color: var(--brand-gold);
            box-shadow: 0 0 0 0.25rem rgba(243, 179, 0, 0.2);
        }

        .btn-login {
            min-height: 54px;
            border: 0;
            border-radius: 12px;
            font-weight: 800;
            font-size: 1.25rem;
            color: #10275b;
            background: linear-gradient(180deg, #ffca2f 0%, #f3b300 100%);
            box-shadow: 0 8px 18px rgba(243, 179, 0, 0.35);
        }

        .btn-login:hover {
            color: #0a1a3d;
            transform: translateY(-1px);
            background: linear-gradient(180deg, #ffd34f 0%, #f7bc1f 100%);
        }

        .footer-meta {
            text-align: center;
            margin-top: 18px;
            color: #d0d9ee;
            font-size: 0.95rem;
        }

        .footer-meta a {
            color: #ffd55b;
            text-decoration: none;
            font-weight: 700;
        }

        .footer-meta a:hover {
            text-decoration: underline;
        }

        @media (max-width: 575px) {
            .login-card .card-body {
                padding: 24px 18px;
            }

            .login-title {
                font-size: 1.7rem;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrap">
        <div class="brand">
            <?php if ($logoUrl): ?>
                <img src="<?= h($logoUrl) ?>"
                     alt="<?= h(APP_NAME) ?>"
                     id="brandLogo"
                     class="brand-logo"
                     title="Dvojklik pro administraci">
            <?php else: ?>
                <h1 id="brandLogo" class="brand-fallback" title="Dvojklik pro administraci"><?= h(APP_NAME) ?></h1>
            <?php endif; ?>
            <p class="brand-subtitle">Aplikace pro trenéry</p>
        </div>

        <div class="card login-card">
            <div class="card-body">
                <h2 class="login-title">Přihlásit se</h2>
                <p class="login-sub">Vítejte zpět v tréninkovém rozhraní</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger py-2 mb-3"><?= h($error) ?></div>
                <?php endif; ?>

                <form method="post" novalidate>
                    <?= csrfField() ?>
                    <div class="mb-3">
                        <label class="form-label" for="username">Uživatelské jméno</label>
                        <input id="username" type="text" name="username" class="form-control"
                               value="<?= h($_POST['username'] ?? '') ?>"
                               autofocus autocomplete="username" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="password">Heslo</label>
                        <input id="password" type="password" name="password" class="form-control"
                               autocomplete="current-password" required>
                    </div>

                    <button type="submit" class="btn btn-login w-100">Přihlásit se</button>
                </form>
            </div>
        </div>

        <div class="footer-meta">
            <div class="mb-1">verze <?= h(getAppSetting('app_version', defined('APP_VERSION') ? APP_VERSION : '—')) ?></div>
            <div>
                Vytvořil <strong>Tomáš Tomeška</strong>
                &nbsp;·&nbsp;
                <a href="mailto:tomas.tomeska@seznam.cz">tomas.tomeska@seznam.cz</a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.getElementById('brandLogo').addEventListener('dblclick', function () {
        window.location.href = '<?= BASE_URL ?>/login_admin.php';
    });
    </script>
</body>
</html>
