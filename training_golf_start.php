<?php
// training_golf_start.php – Zahájení golfu (placeholder)
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php';

requireLogin();

flash('info', 'Golf je v přípravě. Prozatím pokračujte se standardním treningem.');
redirect(BASE_URL . '/training_session.php?id=' . intParam($_GET, 'id', 0));
?>
