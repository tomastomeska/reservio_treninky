<?php
// includes/header.php
// Volá se: renderHeader('Název stránky');
function renderHeader(string $title = '', bool $withCharts = false): void {
    $coach   = getCurrentCoach();
    $flash   = getFlash();
    $appName = APP_NAME;
    $fullTitle = $title ? "$title – $appName" : $appName;
    ?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($fullTitle) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <?php if ($withCharts): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <?php endif; ?>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm sticky-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-bold text-warning" href="<?= BASE_URL ?>/dashboard.php">
            <i class="fas fa-dumbbell me-2"></i><?= h($appName) ?>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/dashboard.php">
                        <i class="fas fa-users me-1"></i>Sportovci
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/exercises.php">
                        <i class="fas fa-list me-1"></i>Cviky
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="<?= BASE_URL ?>/sady.php">
                        <i class="fas fa-layer-group me-1"></i>Sady
                    </a>
                </li>
            </ul>
            <?php if ($coach): ?>
            <div class="navbar-nav">
                <a class="nav-link text-secondary" href="<?= BASE_URL ?>/profile.php">
                    <i class="fas fa-user-tie me-1"></i><?= h($coach['name'] ?: $coach['username']) ?>
                </a>
                <a class="nav-link text-danger" href="<?= BASE_URL ?>/logout.php">
                    <i class="fas fa-sign-out-alt me-1"></i>Odhlásit
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
</nav>

<div class="container-fluid px-3 px-md-4 py-3 py-md-4">
<?php if ($flash): ?>
<div class="alert alert-<?= h($flash['type']) ?> alert-dismissible fade show" role="alert">
    <?= h($flash['message']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>
    <?php
}

function renderFooter(): void {
    $appName = APP_NAME;
    ?>
</div><!-- /container-fluid -->

<footer class="footer mt-auto py-3 bg-dark text-center text-secondary">
    <small><?= h($appName) ?> &copy; <?= date('Y') ?></small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= BASE_URL ?>/assets/js/app.js"></script>
</body>
</html>
<?php
}
