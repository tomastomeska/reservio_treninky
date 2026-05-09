<?php
// ============================================================
// Konfigurace databaze
// Upravte podle vaseho nastaveni WAMP/MySQL
// ============================================================

// Bezpecnostni fallback: nacti env.php i kdyz nektery vstupni skript nenasel config.php
$_envFile = __DIR__ . '/env.php';
if (file_exists($_envFile)) {
    require_once $_envFile;
}
unset($_envFile);

if (!defined('DB_HOST'))    define('DB_HOST',    'localhost');
if (!defined('DB_NAME'))    define('DB_NAME',    'marcelmiler');
if (!defined('DB_USER'))    define('DB_USER',    'root');
if (!defined('DB_PASS'))    define('DB_PASS',    '');
if (!defined('DB_CHARSET')) define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET
        );
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            ensureSchemaUpgrades($pdo);
        } catch (PDOException $e) {
            error_log('DB connection failed: ' . $e->getMessage());
            die('<!DOCTYPE html><html lang="cs"><head><meta charset="UTF-8">
                <title>Chyba DB</title>
                <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
                </head><body class="bg-light"><div class="container mt-5">
                <div class="alert alert-danger">
                    <h4>Nelze se pripojit k databazi</h4>
                    <p>Zkontrolujte nastaveni v <code>config/env.php</code> dle <code>config/env.example.php</code> a ujistete se, ze databazovy server je dostupny.</p>
                </div></div></body></html>');
        }
    }
    return $pdo;
}

function ensureSchemaUpgrades(PDO $pdo): void {
    // Kompatibilita: starsi instalace mely u sportovce pouze sloupec "age".
    $stmt = $pdo->query("SHOW COLUMNS FROM athletes LIKE 'birth_date'");
    if (!$stmt->fetch()) {
        $pdo->exec('ALTER TABLE athletes ADD COLUMN birth_date DATE NULL AFTER last_name');
    }

    // Foto sloupec pro cviky
    $stmt2 = $pdo->query("SHOW COLUMNS FROM exercises LIKE 'photo'");
    if (!$stmt2->fetch()) {
        $pdo->exec('ALTER TABLE exercises ADD COLUMN photo VARCHAR(255) NULL');
    }

    // Globalni cviky mohou mit coach_id = NULL
    $stmtCoachId = $pdo->query("SHOW COLUMNS FROM exercises LIKE 'coach_id'");
    $coachIdColumn = $stmtCoachId->fetch();
    if ($coachIdColumn && strtoupper((string)($coachIdColumn['Null'] ?? 'NO')) !== 'YES') {
        $pdo->exec('ALTER TABLE exercises MODIFY COLUMN coach_id INT NULL');
    }

    // Foto sloupec pro sportovce
    $stmt3 = $pdo->query("SHOW COLUMNS FROM athletes LIKE 'photo'");
    if (!$stmt3->fetch()) {
        $pdo->exec('ALTER TABLE athletes ADD COLUMN photo VARCHAR(255) NULL');
    }

    // Foto sloupec pro dokonceny trenink
    $stmtTsPhoto = $pdo->query("SHOW COLUMNS FROM training_sessions LIKE 'training_photo'");
    if (!$stmtTsPhoto->fetch()) {
        $pdo->exec('ALTER TABLE training_sessions ADD COLUMN training_photo VARCHAR(255) NULL AFTER notes');
    }

    // Soft-delete tréninku trenérem (pro admin obnovu)
    $stmtTsDeleted = $pdo->query("SHOW COLUMNS FROM training_sessions LIKE 'deleted_by_coach_at'");
    if (!$stmtTsDeleted->fetch()) {
        $pdo->exec('ALTER TABLE training_sessions ADD COLUMN deleted_by_coach_at DATETIME NULL AFTER completed_at');
    }

    // ID trenéra, který trénink smazal (audit)
    $stmtTsDeletedBy = $pdo->query("SHOW COLUMNS FROM training_sessions LIKE 'deleted_by_coach_id'");
    if (!$stmtTsDeletedBy->fetch()) {
        $pdo->exec('ALTER TABLE training_sessions ADD COLUMN deleted_by_coach_id INT NULL AFTER deleted_by_coach_at');
    }

    // Snapshot cviků v konkrétní session (historie nezávislá na editaci sady)
    $pdo->exec(" 
        CREATE TABLE IF NOT EXISTS `training_session_exercises` (
            `id`             INT AUTO_INCREMENT PRIMARY KEY,
            `session_id`     INT NOT NULL,
            `exercise_id`    INT NOT NULL,
            `exercise_order` INT NOT NULL,
            `exercise_name`  VARCHAR(200) NOT NULL,
            UNIQUE KEY `uniq_session_exercise` (`session_id`, `exercise_id`),
            KEY `idx_session_order` (`session_id`, `exercise_order`),
            CONSTRAINT `fk_tse_session`
                FOREIGN KEY (`session_id`) REFERENCES `training_sessions`(`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_tse_exercise`
                FOREIGN KEY (`exercise_id`) REFERENCES `exercises`(`id`) ON DELETE RESTRICT
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Tel. kontakt pro sportovce
    $stmtPhone = $pdo->query("SHOW COLUMNS FROM athletes LIKE 'phone_contact'");
    if (!$stmtPhone->fetch()) {
        $pdo->exec('ALTER TABLE athletes ADD COLUMN phone_contact VARCHAR(20) NULL AFTER birth_date');
    }

    // Poslední přihlášení trenéra
    $stmtLogin = $pdo->query("SHOW COLUMNS FROM coaches LIKE 'last_login'");
    if (!$stmtLogin->fetch()) {
        $pdo->exec('ALTER TABLE coaches ADD COLUMN last_login DATETIME NULL');
    }

    // Tabulka superadminu
    $pdo->exec(" 
        CREATE TABLE IF NOT EXISTS `superadmins` (
            `id`         INT AUTO_INCREMENT PRIMARY KEY,
            `username`   VARCHAR(100) NOT NULL UNIQUE,
            `password`   VARCHAR(255) NOT NULL,
            `name`       VARCHAR(200),
            `email`      VARCHAR(255),
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // last_login pro superadminy (starsi instalace sloupec nemaji)
    $stmtSALogin = $pdo->query("SHOW COLUMNS FROM superadmins LIKE 'last_login'");
    if (!$stmtSALogin->fetch()) {
        $pdo->exec('ALTER TABLE superadmins ADD COLUMN last_login DATETIME NULL');
    }

    // Aktivni stav trenera
    $stmtAct = $pdo->query("SHOW COLUMNS FROM coaches LIKE 'is_active'");
    if (!$stmtAct->fetch()) {
        $pdo->exec('ALTER TABLE coaches ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1');
    }

    // Globalni cviky
    $stmtGlob = $pdo->query("SHOW COLUMNS FROM exercises LIKE 'is_global'");
    if (!$stmtGlob->fetch()) {
        $pdo->exec('ALTER TABLE exercises ADD COLUMN is_global TINYINT(1) NOT NULL DEFAULT 0');
    }

    // Hlaska po prihlaseni (admin edituje zpravy pro trenery)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `login_message` (
            `id`         INT AUTO_INCREMENT PRIMARY KEY,
            `message`    TEXT NOT NULL,
            `version`    INT NOT NULL DEFAULT 1,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Sledovani trvaleho skryti hlasky konkretnim trenerem
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `coach_message_seen` (
            `coach_id`        INT NOT NULL,
            `message_version` INT NOT NULL,
            PRIMARY KEY (`coach_id`, `message_version`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Nastaveni aplikace (klic-hodnota)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `app_settings` (
            `key`        VARCHAR(100) NOT NULL PRIMARY KEY,
            `value`      TEXT NOT NULL,
            `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    // Výchozí verze z konstanty – pouze pokud záznam ještě neexistuje
    $pdo->exec("
        INSERT IGNORE INTO `app_settings` (`key`, `value`)
        VALUES ('app_version', '" . APP_VERSION . "')
    ");

    // Poslední přihlášení superadmina
    $stmtAdminLogin = $pdo->query("SHOW COLUMNS FROM superadmins LIKE 'last_login'");
    if (!$stmtAdminLogin->fetch()) {
        $pdo->exec('ALTER TABLE superadmins ADD COLUMN last_login DATETIME NULL');
    }

    // Párový trénink: tabulka skupin a cizí klíč v training_sessions
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `paired_sessions` (
            `id`         INT AUTO_INCREMENT PRIMARY KEY,
            `coach_id`   INT NOT NULL,
            `started_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (`coach_id`) REFERENCES `coaches`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    $stmtPaired = $pdo->query("SHOW COLUMNS FROM `training_sessions` LIKE 'paired_session_id'");
    if (!$stmtPaired->fetch()) {
        $pdo->exec('ALTER TABLE `training_sessions` ADD COLUMN `paired_session_id` INT NULL DEFAULT NULL');
    }

    // Narozeninové notifikace – log odeslaných emailů (zabraňuje duplicitám)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `birthday_notifications` (
            `id`                INT AUTO_INCREMENT PRIMARY KEY,
            `athlete_id`        INT NOT NULL,
            `notification_type` ENUM('warning','birthday') NOT NULL,
            `year`              YEAR NOT NULL,
            `sent_at`           DATETIME NOT NULL,
            UNIQUE KEY `uq_athlete_year_type` (`athlete_id`, `year`, `notification_type`),
            FOREIGN KEY (`athlete_id`) REFERENCES `athletes`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Zprávy od admina trenérům
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `admin_messages` (
            `id`              INT AUTO_INCREMENT PRIMARY KEY,
            `subject`         VARCHAR(255) NOT NULL,
            `body`            TEXT NOT NULL,
            `attachment_path` VARCHAR(500) NULL,
            `attachment_name` VARCHAR(255) NULL,
            `sent_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Příjemci zpráv (trenéři) + stav přečtení + složka
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `admin_message_recipients` (
            `id`         INT AUTO_INCREMENT PRIMARY KEY,
            `message_id` INT NOT NULL,
            `coach_id`   INT NOT NULL,
            `read_at`    DATETIME NULL,
            `status`     ENUM('inbox','archived','deleted') NOT NULL DEFAULT 'inbox',
            UNIQUE KEY `uq_msg_coach` (`message_id`, `coach_id`),
            FOREIGN KEY (`message_id`) REFERENCES `admin_messages`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`coach_id`)   REFERENCES `coaches`(`id`)        ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Přidat status sloupec pokud tabulka existuje bez něj (starší instalace)
    $stmtMsgStatus = $pdo->query("SHOW COLUMNS FROM admin_message_recipients LIKE 'status'");
    if (!$stmtMsgStatus->fetch()) {
        $pdo->exec("ALTER TABLE admin_message_recipients ADD COLUMN `status` ENUM('inbox','archived','deleted') NOT NULL DEFAULT 'inbox'");
    }

    // Akční tlačítka zpráv (volitelná tlačítka/podpisy přidaná adminem do zprávy)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `message_actions` (
            `id`          INT AUTO_INCREMENT PRIMARY KEY,
            `message_id`  INT NOT NULL,
            `label`       VARCHAR(100) NOT NULL,
            `action_type` ENUM('button','signature') NOT NULL DEFAULT 'button',
            `sort_order`  INT NOT NULL DEFAULT 0,
            FOREIGN KEY (`message_id`) REFERENCES `admin_messages`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");

    // Logy stisku akčních tlačítek trenéry
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `message_action_logs` (
            `id`             INT AUTO_INCREMENT PRIMARY KEY,
            `action_id`      INT NOT NULL,
            `coach_id`       INT NOT NULL,
            `pressed_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `ip_address`     VARCHAR(45),
            `user_agent`     TEXT,
            `signature_data` MEDIUMTEXT NULL,
            UNIQUE KEY `uq_action_coach` (`action_id`, `coach_id`),
            FOREIGN KEY (`action_id`) REFERENCES `message_actions`(`id`) ON DELETE CASCADE,
            FOREIGN KEY (`coach_id`)  REFERENCES `coaches`(`id`)          ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
}

// ============================================================
// Speciální sporty – Běh na páse
// ============================================================

/**
 * Vytvoří záznam běhu na páse po startu tréninku
 */
function createRunTreadmillSession(int $sessionId, int $durationSeconds, float $distanceKm): int {
    $pdo = getDB();
    $stmt = $pdo->prepare('
        INSERT INTO `run_treadmill_sessions`
            (`session_id`, `duration_seconds`, `distance_km`, `started_at`, `created_at`)
        VALUES (?, ?, ?, NOW(), NOW())
    ');
    $stmt->execute([$sessionId, $durationSeconds, $distanceKm]);
    return (int)$pdo->lastInsertId();
}

/**
 * Aktualizuje běh na páse (po ukončení, v detailu)
 */
function updateRunTreadmillSession(int $runSessionId, int $durationSeconds, float $distanceKm, ?int $caloriesBurned = null, ?string $location = null, ?string $feeling = null): bool {
    $pdo = getDB();
    $stmt = $pdo->prepare('
        UPDATE `run_treadmill_sessions`
        SET `duration_seconds` = ?,
            `distance_km` = ?,
            `calories_burned` = ?,
            `location` = ?,
            `feeling` = ?,
            `ended_at` = NOW(),
            `updated_at` = NOW()
        WHERE `id` = ?
    ');
    $stmt->execute([$durationSeconds, $distanceKm, $caloriesBurned, $location, $feeling, $runSessionId]);
    return $stmt->rowCount() > 0;
}

/**
 * Načte běh na páse z session_id
 */
function getRunTreadmillSessionByTrainingSession(int $sessionId): ?array {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT * FROM `run_treadmill_sessions` WHERE `session_id` = ?');
    $stmt->execute([$sessionId]);
    return $stmt->fetch() ?: null;
}

/**
 * Načte běh na páse z ID běhu
 */
function getRunTreadmillSession(int $runSessionId): ?array {
    $pdo = getDB();
    $stmt = $pdo->prepare('SELECT * FROM `run_treadmill_sessions` WHERE `id` = ?');
    $stmt->execute([$runSessionId]);
    return $stmt->fetch() ?: null;
}

/**
 * Vrátí poslední běhy na páse pro sportovce
 */
function getRunTreadmillHistory(int $athleteId, int $limit = 10): array {
    $pdo = getDB();
    $stmt = $pdo->prepare('
        SELECT rts.*, ts.completed_at, ts.started_at as ts_started_at
        FROM `run_treadmill_sessions` rts
        JOIN `training_sessions` ts ON ts.id = rts.session_id
        WHERE ts.athlete_id = ? AND ts.completed_at IS NOT NULL
        ORDER BY ts.completed_at DESC
        LIMIT ?
    ');
    $stmt->execute([$athleteId, $limit]);
    return $stmt->fetchAll();
}

/**
 * Vypočítá statistiky běhu na páse (průměr, totál)
 */
function calculateRunTreadmillStats(int $athleteId, int $daysBack = 30): array {
    $pdo = getDB();
    $stmt = $pdo->prepare('
        SELECT 
            COUNT(*) as total_runs,
            SUM(rts.distance_km) as total_km,
            AVG(rts.distance_km) as avg_km,
            SUM(rts.calories_burned) as total_calories,
            SUM(rts.duration_seconds) as total_seconds
        FROM `run_treadmill_sessions` rts
        JOIN `training_sessions` ts ON ts.id = rts.session_id
        WHERE ts.athlete_id = ? 
            AND ts.completed_at IS NOT NULL
            AND ts.completed_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    ');
    $stmt->execute([$athleteId, $daysBack]);
    $row = $stmt->fetch();
    
    return [
        'total_runs'      => (int)($row['total_runs'] ?? 0),
        'total_km'        => (float)($row['total_km'] ?? 0),
        'avg_km'          => (float)($row['avg_km'] ?? 0),
        'total_calories'  => (int)($row['total_calories'] ?? 0),
        'total_seconds'   => (int)($row['total_seconds'] ?? 0),
        'avg_pace_seconds' => $row['total_seconds'] > 0 && $row['total_km'] > 0 
            ? (int)($row['total_seconds'] / $row['total_km']) 
            : 0,
    ];
}