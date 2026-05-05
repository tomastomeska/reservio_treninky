<?php
// ============================================================
// Globalni konfigurace aplikace
// env.php (pokud existuje) přepisuje vychozi hodnoty
// ============================================================

// Načist lokalni/produkcni přepisy (ignorovano gitem)
$_envFile = __DIR__ . '/env.php';
if (file_exists($_envFile)) {
    require_once $_envFile;
}
unset($_envFile);

// Zakladni nastaveni aplikace
define('APP_NAME',     'TrainerApp');
define('APP_VERSION',  '1.0.0');
define('SESSION_NAME', 'trainerapp_sess');

// E-mail odesilatele
define('MAIL_FROM',      'trener@example.com');
define('MAIL_FROM_NAME', 'TrainerApp');

// BASE_URL: env.php muze nastavit vlastni hodnotu; vychozi pro lokalni dev
if (!defined('BASE_URL')) {
    define('BASE_URL', '/marcelmiler');
}

// SESSION_SECURE: true na produkci (HTTPS), false lokalne
if (!defined('SESSION_SECURE')) {
    define('SESSION_SECURE', false);
}

// Casova zona
date_default_timezone_set('Europe/Prague');