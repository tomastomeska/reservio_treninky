<?php
// training_run_outdoor_start.php – Zahájení běhu venku (placeholder)
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php';

requireLogin();

flash('info', 'Běh venku je v přípravě. Prozatím pokračujte se standardním treningem.');
redirect(BASE_URL . '/training_session.php?id=' . intParam($_GET, 'id', 0));
?>
