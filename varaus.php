<?php
/**
 * varaus.php - Pielisen Pyörähuolto booking handler
 *
 * Receives POST from ajanvaraus.html wizard (step 3).
 * Validates input, stores booking in MySQL, sends email confirmation
 * via Brevo (formerly Sendinblue) Transactional Email API.
 *
 * Required configuration (change the placeholder values below):
 *   DB_HOST, DB_NAME, DB_USER, DB_PASS  — MySQL connection details
 *   BREVO_API_KEY                        — from app.brevo.com > Settings > API keys
 *   SENDER_EMAIL                         — verified sender address in Brevo
 *   ADMIN_EMAIL                          — where admin notification is sent
 *
 * DB table (run once):
 *   CREATE TABLE varaukset (
 *     id            INT AUTO_INCREMENT PRIMARY KEY,
 *     toivottu_pvm  DATE         NOT NULL,
 *     toivottu_aika VARCHAR(10)  NOT NULL,
 *     tila          VARCHAR(20)  NOT NULL DEFAULT 'uusi',
 *     nimi          VARCHAR(100) NOT NULL,
 *     puhelin       VARCHAR(30)  NOT NULL,
 *     email         VARCHAR(120) NOT NULL,
 *     pyora_tyyppi  VARCHAR(50)  NOT NULL,
 *     palvelu       VARCHAR(50)  NOT NULL,
 *     lisatiedot    TEXT,
 *     luotu         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
 *   );
 */

/* ─── Configuration ─────────────────────────────────────── */
require_once __DIR__ . '/config.php';

/* ─── Helpers ───────────────────────────────────────────── */
function redirect(string $status): never {
    header('Location: ajanvaraus.html?status=' . $status);
    exit;
}

function sanitize(string $value, int $maxLen = 200): string {
    return mb_substr(trim(strip_tags($value)), 0, $maxLen);
}

function lahetaSahkoposti(string $toEmail, string $toName, string $subject, string $htmlBody): bool {
    $payload = json_encode([
        'sender'     => ['name' => SENDER_NAME, 'email' => SENDER_EMAIL],
        'to'         => [['email' => $toEmail, 'name' => $toName]],
        'subject'    => $subject,
        'htmlContent'=> $htmlBody,
    ]);

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'api-key: ' . BREVO_API_KEY,
        ],
        CURLOPT_TIMEOUT        => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($httpCode >= 200 && $httpCode < 300);
}

/* ─── Only accept POST ──────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('error');
}

/* ─── Honeypot check ────────────────────────────────────── */
if (!empty($_POST['website'])) {
    redirect('ok'); // Silent rejection
}

/* ─── Validate & sanitize ───────────────────────────────── */
$nimi        = sanitize($_POST['nimi']        ?? '', 100);
$puhelin     = sanitize($_POST['puhelin']     ?? '', 30);
$email       = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$pvm         = sanitize($_POST['toivottu_pvm'] ?? '', 10);
$aika        = sanitize($_POST['toivottu_aika'] ?? '', 10);
$pyora_tyyppi = sanitize($_POST['pyora_tyyppi'] ?? '', 50);
$palvelu     = sanitize($_POST['palvelu']      ?? '', 50);
$lisatiedot  = sanitize($_POST['lisatiedot']   ?? '', 1000);

// Required field check
if (!$nimi || !$puhelin || !$email || !$pvm || !$aika || !$pyora_tyyppi || !$palvelu) {
    redirect('error');
}

// Email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirect('error');
}

// Date format YYYY-MM-DD
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $pvm)) {
    redirect('error');
}

// Time format HH:MM
if (!preg_match('/^\d{2}:\d{2}$/', $aika)) {
    redirect('error');
}

// Date must not be in the past
$bookingDate = new DateTime($pvm);
$today       = new DateTime('today');
if ($bookingDate < $today) {
    redirect('error');
}

// Allowed time slots
$dow = (int) $bookingDate->format('w'); // 0=Sun, 6=Sat
$allowedSat     = ['10:00','11:00','12:00','13:00'];
$allowedWeekday = ['09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00'];
$allowedSlots   = $dow === 0 ? [] : ($dow === 6 ? $allowedSat : $allowedWeekday);

if (!in_array($aika, $allowedSlots, true)) {
    redirect('error');
}

/* ─── DB: check for duplicate & insert ─────────────────── */
try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );

    // Check if slot is already taken
    $check = $pdo->prepare(
        "SELECT id FROM varaukset WHERE toivottu_pvm = :pvm AND toivottu_aika = :aika AND tila != 'peruttu'"
    );
    $check->execute([':pvm' => $pvm, ':aika' => $aika]);
    if ($check->fetch()) {
        redirect('error'); // Slot taken — race condition guard
    }

    // Insert booking
    $cancelToken = bin2hex(random_bytes(32));
    $stmt = $pdo->prepare(
        "INSERT INTO varaukset
            (toivottu_pvm, toivottu_aika, tila, nimi, puhelin, email, pyora_tyyppi, palvelu, lisatiedot, cancel_token)
         VALUES
            (:pvm, :aika, 'uusi', :nimi, :puhelin, :email, :pyora_tyyppi, :palvelu, :lisatiedot, :cancel_token)"
    );
    $stmt->execute([
        ':pvm'          => $pvm,
        ':aika'         => $aika,
        ':nimi'         => $nimi,
        ':puhelin'      => $puhelin,
        ':email'        => $email,
        ':pyora_tyyppi' => $pyora_tyyppi,
        ':palvelu'      => $palvelu,
        ':lisatiedot'   => $lisatiedot,
        ':cancel_token' => $cancelToken,
    ]);

} catch (PDOException $e) {
    error_log('Pielisen Pyörähuolto varaus DB error: ' . $e->getMessage());
    redirect('error');
}

/* ─── Format date for emails ────────────────────────────── */
$fiMonths = ['tammikuuta','helmikuuta','maaliskuuta','huhtikuuta','toukokuuta','kesäkuuta',
             'heinäkuuta','elokuuta','syyskuuta','lokakuuta','marraskuuta','joulukuuta'];
$d = (int) $bookingDate->format('j');
$m = (int) $bookingDate->format('n') - 1;
$y = $bookingDate->format('Y');
$dateFi = $d . '. ' . $fiMonths[$m] . ' ' . $y;

$palveluLabels = [
    'perushuolto'  => 'Perushuolto',
    'tayshuolto'   => 'Täyshuolto',
    'sahko_huolto' => 'Sähköpyörän huolto',
    'rengaskorjaus'=> 'Rengaskorjaus',
    'muu'          => 'Muu / kerron lisää',
];
$pyoraLabels = [
    'tavallinen' => 'Tavallinen polkupyörä',
    'sahkopyora' => 'Sähköpyörä',
    'lapsi'      => 'Lasten pyörä',
    'muu'        => 'Muu',
];
$palveluNimi = $palveluLabels[$palvelu] ?? $palvelu;
$pyoraNimi   = $pyoraLabels[$pyora_tyyppi] ?? $pyora_tyyppi;

/* ─── Customer confirmation email ──────────────────────── */
$cancelUrl = rtrim(SITE_URL, '/') . '/peruuta.php?token=' . $cancelToken;
$customerHtml = <<<HTML
<!DOCTYPE html><html lang="fi"><head><meta charset="UTF-8"></head><body
  style="font-family:Arial,Helvetica,sans-serif;background:#f5f5f5;margin:0;padding:0">
<table width="100%" cellpadding="0" cellspacing="0">
  <tr><td align="center" style="padding:32px 16px">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:10px;overflow:hidden;max-width:600px">
      <!-- Header -->
      <tr><td style="background:#1E3A5F;padding:28px 32px">
        <p style="color:#F5C518;font-size:13px;font-weight:700;letter-spacing:2px;text-transform:uppercase;margin:0">Pielisen Pyörähuolto</p>
        <h1 style="color:#fff;font-size:22px;margin:8px 0 0">Varausvahvistus</h1>
      </td></tr>
      <!-- Body -->
      <tr><td style="padding:28px 32px">
        <p style="color:#1a1a1a;font-size:16px;margin:0 0 20px">Hei <strong>$nimi</strong>,</p>
        <p style="color:#555;font-size:15px;line-height:1.6;margin:0 0 24px">
          Varauksesi on vastaanotettu. Tässä yhteenveto:
        </p>
        <table width="100%" cellpadding="10" cellspacing="0"
               style="background:#f0f4fa;border-radius:8px;border:1px solid #dce8f8">
          <tr><td style="color:#555;font-size:14px;width:40%">Päivä</td>
              <td style="color:#1a1a1a;font-size:14px;font-weight:700">$dateFi</td></tr>
          <tr style="background:#fff"><td style="color:#555;font-size:14px">Aika</td>
              <td style="color:#1a1a1a;font-size:14px;font-weight:700">$aika</td></tr>
          <tr><td style="color:#555;font-size:14px">Palvelu</td>
              <td style="color:#1a1a1a;font-size:14px;font-weight:700">$palveluNimi</td></tr>
          <tr style="background:#fff"><td style="color:#555;font-size:14px">Pyörä</td>
              <td style="color:#1a1a1a;font-size:14px;font-weight:700">$pyoraNimi</td></tr>
          <tr><td style="color:#555;font-size:14px">Osoite</td>
              <td style="color:#1a1a1a;font-size:14px;font-weight:700">Kauppakatu 14, 80100 Joensuu</td></tr>
        </table>
        <p style="color:#555;font-size:14px;line-height:1.6;margin:24px 0 0">
          Tuo pyörä korjaamolle sovittuna päivänä. Muutoksista soita meille: <strong>013 456 7890</strong>.
        </p>
        <p style="margin:16px 0 0">
          <a href="$cancelUrl" style="color:#c0392b;font-size:13px">Peruuta varaus</a>
        </p>
      </td></tr>
      <!-- Footer -->
      <tr><td style="background:#f5f7fa;padding:18px 32px;border-top:1px solid #e2e6ea">
        <p style="color:#999;font-size:12px;margin:0">
          Pielisen Pyörähuolto · Kauppakatu 14, 80100 Joensuu · 013 456 7890
        </p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body></html>
HTML;

lahetaSahkoposti($email, $nimi, 'Varausvahvistus - Pielisen Pyörähuolto', $customerHtml);

/* ─── Admin notification email ──────────────────────────── */
$lisatiedotRow = $lisatiedot
    ? "<tr><td style='color:#555;font-size:13px'>Lisätiedot</td><td style='color:#111;font-size:13px'>$lisatiedot</td></tr>"
    : '';

$adminHtml = <<<HTML
<!DOCTYPE html><html lang="fi"><head><meta charset="UTF-8"></head><body
  style="font-family:Arial,sans-serif;background:#f5f5f5;padding:20px">
<h2 style="color:#1E3A5F">Uusi huoltovaraus</h2>
<table cellpadding="8" cellspacing="0"
       style="background:#fff;border-radius:8px;border:1px solid #ddd;font-size:13px">
  <tr><td style="color:#555;width:120px">Päivä</td>  <td><strong>$dateFi</strong></td></tr>
  <tr style="background:#f9f9f9"><td style="color:#555">Aika</td><td><strong>$aika</strong></td></tr>
  <tr><td style="color:#555">Nimi</td>               <td>$nimi</td></tr>
  <tr style="background:#f9f9f9"><td style="color:#555">Puhelin</td><td>$puhelin</td></tr>
  <tr><td style="color:#555">Sähköposti</td>         <td>$email</td></tr>
  <tr style="background:#f9f9f9"><td style="color:#555">Pyörä</td><td>$pyoraNimi</td></tr>
  <tr><td style="color:#555">Palvelu</td>            <td>$palveluNimi</td></tr>
  $lisatiedotRow
</table>
</body></html>
HTML;

lahetaSahkoposti(ADMIN_EMAIL, SENDER_NAME, "Uusi varaus: $nimi - $dateFi klo $aika", $adminHtml);

/* ─── Done ──────────────────────────────────────────────── */
redirect('ok');
