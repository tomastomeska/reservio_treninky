<?php
// ============================================================
// Pomocné funkce
// ============================================================

if (!function_exists('h')) {
    function h(?string $str): string {
        return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('formatDate')) {
    function formatDate(?string $dt): string {
        return $dt ? date('d.m.Y', strtotime($dt)) : '–';
    }
}

if (!function_exists('formatDateTime')) {
    function formatDateTime(?string $dt): string {
        return $dt ? date('d.m.Y H:i', strtotime($dt)) : '–';
    }
}

function calculateAge(?string $birthDate): ?int {
    if (!$birthDate) {
        return null;
    }

    try {
        $dob = new DateTime($birthDate);
        $now = new DateTime();
        if ($dob > $now) {
            return null;
        }
        return (int)$now->diff($dob)->y;
    } catch (Exception $e) {
        return null;
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url): void {
        header('Location: ' . $url);
        exit;
    }
}

// Vrátí poslední dokončenou session sportovce
function getLastSession(int $athleteId): ?array {
    $pdo  = getDB();
    $stmt = $pdo->prepare(
        'SELECT ts.*, ws.name AS set_name
         FROM training_sessions ts
         JOIN workout_sets ws ON ts.workout_set_id = ws.id
         WHERE ts.athlete_id = ? AND ts.completed_at IS NOT NULL
         ORDER BY ts.completed_at DESC
         LIMIT 1'
    );
    $stmt->execute([$athleteId]);
    return $stmt->fetch() ?: null;
}

// Vrátí počet dokončených sezení sportovce
function getSessionCount(int $athleteId): int {
    $pdo  = getDB();
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM training_sessions
         WHERE athlete_id = ? AND completed_at IS NOT NULL'
    );
    $stmt->execute([$athleteId]);
    return (int)$stmt->fetchColumn();
}

// Vrátí série pro dané sezení a cvik
function getSeriesForExercise(int $sessionId, int $exerciseId): array {
    $pdo  = getDB();
    $stmt = $pdo->prepare(
        'SELECT * FROM session_series
         WHERE session_id = ? AND exercise_id = ?
         ORDER BY series_order ASC'
    );
    $stmt->execute([$sessionId, $exerciseId]);
    return $stmt->fetchAll();
}

// Vrátí celý obsah sady (cviky seřazené)
function getWorkoutSetExercises(int $setId): array {
    $pdo  = getDB();
    $stmt = $pdo->prepare(
        'SELECT wse.*, e.name AS exercise_name
         FROM workout_set_exercises wse
         JOIN exercises e ON wse.exercise_id = e.id
         WHERE wse.workout_set_id = ?
         ORDER BY wse.exercise_order ASC'
    );
    $stmt->execute([$setId]);
    return $stmt->fetchAll();
}

// Bezpečný int z $_GET / $_POST
if (!function_exists('intParam')) {
    function intParam(array $source, string $key, int $default = 0): int {
        return isset($source[$key]) ? (int)$source[$key] : $default;
    }
}

// ============================================================
// Upload fotografií
// ============================================================

/**
 * Nahraje soubor z $_FILES[$inputName] do uploads/$subDir/.
 * Vrátí název souboru nebo null při chybě / žádný soubor.
 */
function saveUploadedPhoto(string $inputName, string $subDir): ?string {
    if (empty($_FILES[$inputName]) || $_FILES[$inputName]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    $file = $_FILES[$inputName];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return null;
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];
    if (!array_key_exists($mime, $allowed)) {
        return null;
    }
    $dir = dirname(__DIR__) . '/uploads/' . $subDir . '/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
        return null;
    }
    return $filename;
}

/** Smaže soubor fotografie z disku. */
function deleteUploadedPhoto(?string $filename, string $subDir): void {
    if (!$filename) {
        return;
    }
    $path = dirname(__DIR__) . '/uploads/' . $subDir . '/' . $filename;
    if (is_file($path)) {
        unlink($path);
    }
}

/** Vrátí URL fotografie nebo prázdný řetězec. */
function photoUrl(?string $filename, string $subDir): string {
    if (!$filename) {
        return '';
    }
    return BASE_URL . '/uploads/' . $subDir . '/' . rawurlencode($filename);
}

/**
 * Odešle uvítací e-mail trenérovi s přihlašovacími údaji přes SMTP (PHPMailer).
 * Vrátí true při úspěchu, false při chybě.
 */
function sendCoachWelcomeEmail(string $toEmail, string $username, string $password, string $loginUrl): bool {
    $phpmailerSrc = dirname(__DIR__) . '/vendor/phpmailer/phpmailer/src';
    if (!file_exists($phpmailerSrc . '/PHPMailer.php')) {
        error_log('sendCoachWelcomeEmail: PHPMailer not found at ' . $phpmailerSrc);
        return false;
    }

    require_once $phpmailerSrc . '/Exception.php';
    require_once $phpmailerSrc . '/PHPMailer.php';
    require_once $phpmailerSrc . '/SMTP.php';

    $safeUsername = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');
    $safePassword = htmlspecialchars($password, ENT_QUOTES, 'UTF-8');
    $safeLoginUrl = htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8');

    $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="cs">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f4f4f7;font-family:Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f7;padding:32px 0;">
    <tr><td align="center">
      <table width="100%" style="max-width:560px;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 12px rgba(0,0,0,.08);">

        <!-- Hlavicka -->
        <tr>
          <td style="background:linear-gradient(135deg,#7c3aed,#a78bfa);padding:36px 40px;text-align:center;">
            <div style="font-size:32px;margin-bottom:8px;">&#x1F4AA;</div>
            <h1 style="margin:0;color:#ffffff;font-size:22px;font-weight:700;letter-spacing:.5px;">TrainerApp</h1>
            <p style="margin:6px 0 0;color:#e9d5ff;font-size:13px;">V&#225;&#353; tr&#233;ninkov&#253; syst&#233;m</p>
          </td>
        </tr>

        <!-- Obsah -->
        <tr>
          <td style="padding:36px 40px;">
            <p style="margin:0 0 16px;color:#374151;font-size:15px;">Dobr&#253; den,</p>
            <p style="margin:0 0 24px;color:#374151;font-size:15px;">
              byl V&#225;m vytvo&#345;en &#250;&#269;et <strong>tren&#233;ra</strong> v aplikaci <strong>TrainerApp</strong>.
              N&#237;&#382;e najdete sv&#233; p&#345;ihla&#353;ovac&#237; &#250;daje.
            </p>

            <!-- Prihlasovaci udaje -->
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;margin-bottom:24px;">
              <tr>
                <td style="padding:20px 24px;">
                  <table width="100%" cellpadding="0" cellspacing="0">
                    <tr>
                      <td style="padding:7px 0;color:#6b7280;font-size:13px;width:170px;">P&#345;ihla&#353;ovac&#237; str&#225;nka</td>
                      <td style="padding:7px 0;">
                        <a href="{LOGIN_URL_RAW}" style="color:#7c3aed;font-weight:600;font-size:13px;text-decoration:none;">{LOGIN_URL_SAFE}</a>
                      </td>
                    </tr>
                    <tr>
                      <td style="padding:7px 0;color:#6b7280;font-size:13px;border-top:1px solid #e5e7eb;">U&#382;ivatelsk&#233; jm&#233;no</td>
                      <td style="padding:7px 0;font-weight:700;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;">{USERNAME}</td>
                    </tr>
                    <tr>
                      <td style="padding:7px 0;color:#6b7280;font-size:13px;border-top:1px solid #e5e7eb;">Heslo</td>
                      <td style="padding:7px 0;font-weight:700;font-size:14px;color:#111827;border-top:1px solid #e5e7eb;font-family:monospace;">{PASSWORD}</td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>

            <!-- CTA tlacitko -->
            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom:28px;">
              <tr>
                <td align="center">
                  <a href="{LOGIN_URL_RAW}"
                     style="display:inline-block;background:linear-gradient(135deg,#7c3aed,#a78bfa);color:#ffffff;text-decoration:none;font-size:15px;font-weight:700;padding:14px 36px;border-radius:8px;">
                    P&#345;ihl&#225;sit se do TrainerApp
                  </a>
                </td>
              </tr>
            </table>

            <!-- Upozorneni -->
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#fffbeb;border-left:4px solid #f59e0b;border-radius:4px;margin-bottom:28px;">
              <tr>
                <td style="padding:12px 16px;color:#92400e;font-size:13px;">
                  &#9888;&#65039; <strong>Doporu&#269;en&#237;:</strong> Po prvn&#237;m p&#345;ihl&#225;&#353;en&#237; si heslo ihned zm&#283;&#328;te v nastaven&#237; profilu.
                </td>
              </tr>
            </table>

            <p style="margin:0;color:#6b7280;font-size:13px;">S pozdravem,<br><strong style="color:#374151;">Administrace TrainerApp</strong></p>
          </td>
        </tr>

        <!-- Paticka -->
        <tr>
          <td style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:20px 40px;text-align:center;">
            <p style="margin:0 0 6px;color:#9ca3af;font-size:12px;">
              Aplikaci vytvo&#345;il a spravuje <strong style="color:#6b7280;">Tom&#225;&#353; Tome&#353;ka</strong>
            </p>
            <p style="margin:0;color:#9ca3af;font-size:12px;">
              Dotazy a podpora:
              <a href="mailto:admin@reservio.online" style="color:#7c3aed;text-decoration:none;">admin@reservio.online</a>
            </p>
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

    $htmlBody = str_replace(
        ['{LOGIN_URL_RAW}', '{LOGIN_URL_SAFE}', '{USERNAME}', '{PASSWORD}'],
        [$loginUrl, $safeLoginUrl, $safeUsername, $safePassword],
        $htmlBody
    );

    $altBody =
        "Dobrý den,\n\n" .
        "byl Vám vytvořen účet trenéra v aplikaci TrainerApp.\n\n" .
        "Přihlašovací stránka: " . $loginUrl . "\n" .
        "Uživatelské jméno: " . $username . "\n" .
        "Heslo: " . $password . "\n\n" .
        "Doporučení: po prvním přihlášení si heslo ihned změňte v profilu.\n\n" .
        "S pozdravem\n" .
        "Administrace TrainerApp\n\n" .
        "---\n" .
        "Aplikaci vytvořil a spravuje Tomáš Tomeška\n" .
        "Podpora: admin@reservio.online\n";

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($toEmail);

        $mail->isHTML(true);
        $mail->Subject = 'Přihlašovací údaje do TrainerApp';
        $mail->Body    = $htmlBody;
        $mail->AltBody = $altBody;

        $mail->send();
        return true;
    } catch (\Exception $e) {
        error_log('sendCoachWelcomeEmail error: ' . $mail->ErrorInfo);
        return false;
    }
}
