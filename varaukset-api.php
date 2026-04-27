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
    try {
        $pdo->prepare("UPDATE varaukset SET tila=:tila WHERE id=:id")
            ->execute([':tila' => $tila, ':id' => $id]);
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
        "SELECT id, toivottu_pvm, toivottu_aika, tila, nimi, puhelin, email,
                pyora_tyyppi, palvelu, lisatiedot, luotu
         FROM varaukset $where
         ORDER BY toivottu_pvm ASC, toivottu_aika ASC"
    );
    echo json_encode(['bookings' => $stmt->fetchAll(PDO::FETCH_ASSOC)], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'db_error']);
}
