<?php
/**
 * varaukset-api.php – JSON API for admin.html bookings tab
 *
 * GET  ?token=HASH&filter=tulevat|menneet|kaikki  → {"bookings": [...]}
 * POST token + id + tila                           → {"ok": true}
 */

require_once __DIR__ . '/config.php';

/* ─── Configuration ─────────────────────────────────────── */
define('TOKEN_HASH', 'a1d182d125869e9e5df6cff0f27f9d194e61793c90183ae0b5b86a0bc87ea4fc');
define('BOOKINGS_FILE', __DIR__ . '/varaukset.json');

/* ─── Headers ───────────────────────────────────────────── */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

/* ─── Token check ───────────────────────────────────────── */
$token = $_GET['token'] ?? $_POST['token'] ?? '';
if (!hash_equals(TOKEN_HASH, $token)) {
    http_response_code(401);
    echo json_encode(['error' => 'unauthorized']);
    exit;
}

function readBookings(): array {
    if (!file_exists(BOOKINGS_FILE)) return [];
    return json_decode(file_get_contents(BOOKINGS_FILE), true) ?: [];
}

function writeBookings(array $bookings): void {
    file_put_contents(BOOKINGS_FILE, json_encode(array_values($bookings), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

function lahetaPeruutus(array $b): void {
    $fiMonths = ['tammikuuta','helmikuuta','maaliskuuta','huhtikuuta','toukokuuta','kesäkuuta',
                 'heinäkuuta','elokuuta','syyskuuta','lokakuuta','marraskuuta','joulukuuta'];
    $d   = (int) date('j', strtotime($b['toivottu_pvm']));
    $m   = (int) date('n', strtotime($b['toivottu_pvm'])) - 1;
    $y   = date('Y', strtotime($b['toivottu_pvm']));
    $dateFi = $d . '. ' . $fiMonths[$m] . ' ' . $y;

    $nimi  = htmlspecialchars($b['nimi'], ENT_QUOTES);
    $aika  = htmlspecialchars($b['toivottu_aika'], ENT_QUOTES);

    $html = <<<HTML
<!DOCTYPE html><html lang="fi"><head><meta charset="UTF-8"></head><body
  style="font-family:Arial,Helvetica,sans-serif;background:#f5f5f5;margin:0;padding:0">
<table width="100%" cellpadding="0" cellspacing="0">
  <tr><td align="center" style="padding:32px 16px">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:10px;overflow:hidden;max-width:600px">
      <tr><td style="background:#1E3A5F;padding:28px 32px">
        <p style="color:#F5C518;font-size:13px;font-weight:700;letter-spacing:2px;text-transform:uppercase;margin:0">Pielisen Pyörähuolto</p>
        <h1 style="color:#fff;font-size:22px;margin:8px 0 0">Varaus peruttu</h1>
      </td></tr>
      <tr><td style="padding:28px 32px">
        <p style="color:#1a1a1a;font-size:16px;margin:0 0 16px">Hei <strong>$nimi</strong>,</p>
        <p style="color:#555;font-size:15px;line-height:1.6;margin:0 0 16px">
          Varauksesi <strong>$dateFi klo $aika</strong> on valitettavasti peruttu.
        </p>
        <p style="color:#555;font-size:15px;line-height:1.6;margin:0 0 16px">
          Olemme pian yhteydessä sinuun sopivan uuden ajan löytämiseksi.
          Voit myös soittaa meille suoraan: <strong>013 456 7890</strong>.
        </p>
        <p style="color:#555;font-size:14px">Pahoittelemme aiheutunutta haittaa.</p>
      </td></tr>
      <tr><td style="background:#f5f7fa;padding:18px 32px;border-top:1px solid #e2e6ea">
        <p style="color:#999;font-size:12px;margin:0">Pielisen Pyörähuolto · Kauppakatu 14, 80100 Joensuu · 013 456 7890</p>
      </td></tr>
    </table>
  </td></tr>
</table>
</body></html>
HTML;

    $payload = json_encode([
        'sender'      => ['name' => SENDER_NAME, 'email' => SENDER_EMAIL],
        'to'          => [['email' => $b['email'], 'name' => $b['nimi']]],
        'subject'     => 'Varaus peruttu – Pielisen Pyörähuolto',
        'htmlContent' => $html,
    ]);

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'api-key: ' . BREVO_API_KEY],
        CURLOPT_TIMEOUT        => 10,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

/* ─── POST: update status ───────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id      = (int)($_POST['id'] ?? 0);
    $tila    = $_POST['tila'] ?? '';
    $allowed = ['vahvistettu', 'valmis', 'peruttu', 'uusi'];
    if (!$id || !in_array($tila, $allowed, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid']);
        exit;
    }
    $bookings = readBookings();
    $target   = null;
    foreach ($bookings as &$b) {
        if ((int)$b['id'] === $id) {
            $target = $b;
            $b['tila'] = $tila;
            break;
        }
    }
    writeBookings($bookings);

    // Send cancellation email if just cancelled
    if ($tila === 'peruttu' && $target && !empty($target['email'])) {
        lahetaPeruutus($target);
    }

    echo json_encode(['ok' => true]);
    exit;
}

/* ─── GET: fetch bookings ───────────────────────────────── */
$filter   = $_GET['filter'] ?? 'tulevat';
$today    = date('Y-m-d');
$all      = readBookings();

$bookings = array_values(array_filter($all, function($b) use ($filter, $today) {
    return match($filter) {
        'menneet' => $b['toivottu_pvm'] < $today,
        'kaikki'  => true,
        default   => $b['toivottu_pvm'] >= $today && $b['tila'] !== 'peruttu',
    };
}));

usort($bookings, fn($a, $b) => strcmp($a['toivottu_pvm'].$a['toivottu_aika'], $b['toivottu_pvm'].$b['toivottu_aika']));

echo json_encode(['bookings' => $bookings], JSON_UNESCAPED_UNICODE);
