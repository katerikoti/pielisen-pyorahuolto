<?php
/**
 * slots.php - Pielisen Pyörähuolto booked-slots endpoint
 *
 * GET ?date=YYYY-MM-DD
 * Returns JSON: {"booked": ["09:00","13:00",...]}
 *
 * Called by ajanvaraus.html wizard (step 2) via fetch().
 * Uses the same DB credentials as varaus.php.
 */

/* ─── Configuration ─────────────────────────────────────── */
require_once __DIR__ . '/config.php';

/* ─── Response helper ───────────────────────────────────── */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function jsonOut(array $data): never {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/* ─── Validate date parameter ───────────────────────────── */
$date = $_GET['date'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    jsonOut(['booked' => []]);
}

// Reject past dates silently
$requested = new DateTime($date);
$today     = new DateTime('today');
if ($requested < $today) {
    jsonOut(['booked' => []]);
}

/* ─── Query DB ──────────────────────────────────────────── */
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

    $stmt = $pdo->prepare(
        "SELECT toivottu_aika FROM varaukset
          WHERE toivottu_pvm = :date AND tila != 'peruttu'"
    );
    $stmt->execute([':date' => $date]);
    $booked = array_column($stmt->fetchAll(), 'toivottu_aika');

} catch (PDOException $e) {
    error_log('Pielisen Pyörähuolto slots.php DB error: ' . $e->getMessage());
    jsonOut(['booked' => []]); // Fail gracefully — all slots appear free
}

jsonOut(['booked' => $booked]);
