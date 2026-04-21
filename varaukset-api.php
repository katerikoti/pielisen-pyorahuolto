<?php
/**
 * varaukset-api.php – JSON API for admin.html bookings tab
 *
 * GET  ?token=HASH&filter=tulevat|menneet|kaikki  → {"bookings": [...]}
 * POST token + id + tila                           → {"ok": true}
 *
 * Token = the SHA-256 hash of the admin password (same value as PW_HASH in admin.html).
 * This means only someone who logged into admin.html can call this endpoint.
 *
 * Same DB credentials as varaus.php – change the VAIHDA placeholders below.
 */

/* ─── Configuration ─────────────────────────────────────── */
// Token = PW_HASH from admin.html (SHA-256 of admin password):
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

/* ─── POST: update status ───────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id      = (int)($_POST['id'] ?? 0);
    $tila    = $_POST['tila'] ?? '';
    $allowed = ['uusi', 'vahvistettu', 'valmis', 'peruttu'];
    if (!$id || !in_array($tila, $allowed, true)) {
        http_response_code(400);
        echo json_encode(['error' => 'invalid']);
        exit;
    }
    $bookings = readBookings();
    foreach ($bookings as &$b) {
        if ((int)$b['id'] === $id) { $b['tila'] = $tila; break; }
    }
    writeBookings($bookings);
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
