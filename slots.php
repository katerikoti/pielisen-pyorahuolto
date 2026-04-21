<?php
/**
 * slots.php - Pielisen Pyörähuolto booked-slots endpoint
 *
 * GET ?date=YYYY-MM-DD
 * Returns JSON: {"booked": ["09:00","13:00",...]}
 *
 * Storage: varaukset.json (no database required)
 */

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

$requested = new DateTime($date);
$today     = new DateTime('today');
if ($requested < $today) {
    jsonOut(['booked' => []]);
}

/* ─── Read from JSON file ────────────────────────────────── */
$file = __DIR__ . '/varaukset.json';
$all  = file_exists($file) ? (json_decode(file_get_contents($file), true) ?: []) : [];

$booked = array_values(array_map(
    fn($b) => $b['toivottu_aika'],
    array_filter($all, fn($b) => $b['toivottu_pvm'] === $date && $b['tila'] !== 'peruttu')
));

jsonOut(['booked' => $booked]);
