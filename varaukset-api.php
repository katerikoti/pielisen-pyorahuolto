<?php
/**
 * varaukset-api.php – JSON API for admin.html bookings tab
 *
 * GET  ?token=HASH&filter=tulevat|menneet|kaikki       → {"bookings": [...]}
 * POST token + action=update + id + tila [+ notify=1]  → {"ok": true}
 * POST token + action=add + fields...                  → {"ok": true, "id": N}
 *
 * Token = the SHA-256 hash of the admin password (same value as PW_HASH in admin.html).
 * This means only someone who logged into admin.html can call this endpoint.
 *
 * Same DB credentials as varaus.php – change the VAIHDA placeholders below.
 */

/* ─── Configuration ─────────────────────────────────────── */
require_once __DIR__ . '/config.php';
// Token = PW_HASH from admin.html (SHA-256 of admin password):
define('TOKEN_HASH', 'a1d182d125869e9e5df6cff0f27f9d194e61793c90183ae0b5b86a0bc87ea4fc');

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

/* ─── DB connect ────────────────────────────────────────── */
try {
    $pdo = new PDO(
        'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'db_connect']);
    exit;
}

/* ─── Helpers ───────────────────────────────────────────── */
function sendCancelEmail(array $booking): void {
    if (!defined('BREVO_API_KEY') || !BREVO_API_KEY) return;
    $fi_months = ['','tammikuuta','helmikuuta','maaliskuuta','huhtikuuta','toukokuuta','kesäkuuta',
                  'heinäkuuta','elokuuta','syyskuuta','lokakuuta','marraskuuta','joulukuuta'];
    $dt   = new DateTime($booking['toivottu_pvm']);
    $day  = (int)$dt->format('j');
    $mon  = $fi_months[(int)$dt->format('n')];
    $year = $dt->format('Y');
    $date_fi = "{$day}. {$mon} {$year}";
    $time_fi = $booking['toivottu_aika'];
    $name    = htmlspecialchars($booking['nimi'], ENT_QUOTES, 'UTF-8');

    $html = "
<p>Hyvä {$name},</p>
<p>Varauksesi <strong>{$date_fi} klo {$time_fi}</strong> Pielisen Pyörähuoltoon on peruttu.</p>
<p>Jos peruutus on tapahtunut virheellisesti tai haluat varata uuden ajan, ota yhteyttä:<br>
Puh. <a href='tel:0134567890'>013 456 7890</a> tai <a href='mailto:info@pielisenpyorahuolto.fi'>info@pielisenpyorahuolto.fi</a></p>
<p>Pahoittelemme mahdollista vaivaa.</p>
<p>– Pielisen Pyörähuolto</p>
";

    $payload = json_encode([
        'sender'      => ['name' => SENDER_NAME, 'email' => SENDER_EMAIL],
        'to'          => [['email' => $booking['email'], 'name' => $booking['nimi']]],
        'subject'     => "Varauksesi {$date_fi} klo {$time_fi} on peruttu",
        'htmlContent' => $html,
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
        CURLOPT_TIMEOUT => 10,
    ]);
    curl_exec($ch);
    curl_close($ch);
}

/* ─── POST: add booking or update status ────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update';

    /* ── action=add: insert new admin booking or block ── */
    if ($action === 'add') {
        $tyyppi    = $_POST['tyyppi'] ?? 'admin';
        $allowed_t = ['admin', 'esto'];
        if (!in_array($tyyppi, $allowed_t, true)) {
            http_response_code(400);
            echo json_encode(['error' => 'invalid_tyyppi']);
            exit;
        }

        $pvm        = trim($_POST['toivottu_pvm']  ?? '');
        $aika       = trim($_POST['toivottu_aika'] ?? '');
        $aika_loppu = trim($_POST['aika_loppuu']   ?? '');
        $koko       = !empty($_POST['koko_paiva']);

        // Basic date validation
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $pvm)) {
            http_response_code(400); echo json_encode(['error' => 'invalid_date']); exit;
        }

        $SLOTS_WEEKDAY = ['09:00','10:00','11:00','12:00','13:00','14:00','15:00','16:00'];
        $SLOTS_SAT     = ['10:00','11:00','12:00','13:00'];
        $dow = (int)(new DateTime($pvm))->format('w'); // 0=Sun, 6=Sat, 0=closed
        $allSlots = ($dow === 6) ? $SLOTS_SAT : $SLOTS_WEEKDAY;

        if ($koko) {
            // Insert one row per slot for block-whole-day
            $slots_to_insert = $allSlots;
        } elseif ($aika_loppu) {
            // Range block: include all slots from $aika up to and including $aika_loppu
            if (!preg_match('/^\d{2}:\d{2}$/', $aika) || !preg_match('/^\d{2}:\d{2}$/', $aika_loppu)) {
                http_response_code(400); echo json_encode(['error' => 'invalid_time']); exit;
            }
            $slots_to_insert = array_filter($allSlots, fn($s) => $s >= $aika && $s <= $aika_loppu);
            $slots_to_insert = array_values($slots_to_insert);
            if (empty($slots_to_insert)) {
                http_response_code(400); echo json_encode(['error' => 'invalid_range']); exit;
            }
        } else {
            if (!preg_match('/^\d{2}:\d{2}$/', $aika)) {
                http_response_code(400); echo json_encode(['error' => 'invalid_time']); exit;
            }
            $slots_to_insert = [$aika];
        }

        // For admin type, collect customer fields
        $nimi         = mb_substr(trim(strip_tags($_POST['nimi']         ?? '')), 0, 100);
        $puhelin      = mb_substr(trim(strip_tags($_POST['puhelin']      ?? '')), 0, 30);
        $email        = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $pyora_tyyppi = mb_substr(trim(strip_tags($_POST['pyora_tyyppi'] ?? '')), 0, 50);
        $palvelu      = mb_substr(trim(strip_tags($_POST['palvelu']      ?? '')), 0, 50);
        $lisatiedot   = mb_substr(trim(strip_tags($_POST['lisatiedot']   ?? '')), 0, 1000);

        if ($tyyppi === 'admin') {
            if (!$nimi || !$email) {
                http_response_code(400); echo json_encode(['error' => 'missing_fields']); exit;
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(400); echo json_encode(['error' => 'invalid_email']); exit;
            }
        }

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO varaukset (toivottu_pvm, toivottu_aika, tila, tyyppi, nimi, puhelin, email, pyora_tyyppi, palvelu, lisatiedot)
                 VALUES (:pvm, :aika, 'vahvistettu', :tyyppi, :nimi, :puhelin, :email, :pyora, :palvelu, :lisatiedot)"
            );
            $last_id = null;
            foreach ($slots_to_insert as $slot) {
                $stmt->execute([
                    ':pvm'        => $pvm,
                    ':aika'       => $slot,
                    ':tyyppi'     => $tyyppi,
                    ':nimi'       => $nimi,
                    ':puhelin'    => $puhelin,
                    ':email'      => $email,
                    ':pyora'      => $pyora_tyyppi,
                    ':palvelu'    => $palvelu,
                    ':lisatiedot' => $lisatiedot,
                ]);
                $last_id = (int)$pdo->lastInsertId();
            }
            echo json_encode(['ok' => true, 'id' => $last_id]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'db_error']);
        }
        exit;
    }

    /* ── action=update (default): change booking status ── */
    $id      = (int)($_POST['id'] ?? 0);
    $tila    = $_POST['tila'] ?? '';
    $notify  = !empty($_POST['notify']);
    $allowed = ['uusi', 'vahvistettu', 'valmis', 'peruttu'];
    if (!$id || !in_array($tila, $allowed, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid']);
        exit;
    }
    try {
        // Fetch booking before updating (needed for email)
        $booking = null;
        if ($tila === 'peruttu' && $notify) {
            $s = $pdo->prepare("SELECT nimi, email, toivottu_pvm, toivottu_aika, tyyppi FROM varaukset WHERE id=:id");
            $s->execute([':id' => $id]);
            $booking = $s->fetch(PDO::FETCH_ASSOC);
        }
        $pdo->prepare("UPDATE varaukset SET tila=:tila WHERE id=:id")
            ->execute([':tila' => $tila, ':id' => $id]);
        if ($booking && $booking['tyyppi'] === 'asiakas' && $booking['email']) {
            sendCancelEmail($booking);
        }
        echo json_encode(['ok' => true]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['error' => 'db_error']);
    }
    exit;
}

/* ─── GET: fetch bookings ───────────────────────────────── */
$filter = $_GET['filter'] ?? 'tulevat';
$where  = match($filter) {
    'menneet' => "WHERE toivottu_pvm < CURDATE()",
    'kaikki'  => "",
    default   => "WHERE toivottu_pvm >= CURDATE() AND tila != 'peruttu'",
};

try {
    $stmt = $pdo->query(
        "SELECT id, toivottu_pvm, toivottu_aika, tila, tyyppi, nimi, puhelin, email,
                pyora_tyyppi, palvelu, lisatiedot, luotu
         FROM varaukset $where
         ORDER BY toivottu_pvm ASC, toivottu_aika ASC"
    );
    echo json_encode(['bookings' => $stmt->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'db_error']);
}
