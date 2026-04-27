<?php
/**
 * uutiset-api.php – News API for Pielisen Pyörähuolto
 *
 * GET  (no token)     → published posts only  (for index.html)
 * GET  ?token=HASH    → all posts             (for admin.html)
 * POST action=add     + token → add post
 * POST action=toggle  + token + id → toggle published
 * POST action=delete  + token + id → delete post
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

define('NEWS_TOKEN_HASH', 'a1d182d125869e9e5df6cff0f27f9d194e61793c90183ae0b5b86a0bc87ea4fc');

function isAdmin(): bool {
    $token = trim($_GET['token'] ?? $_POST['token'] ?? '');
    return $token !== '' && hash_equals(NEWS_TOKEN_HASH, strtolower($token));
}

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'db_connect']);
    exit;
}

/* ── GET ── */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (isAdmin()) {
        $stmt = $pdo->query('SELECT * FROM uutiset ORDER BY date DESC, luotu DESC');
    } else {
        $stmt = $pdo->query('SELECT id, title, body, date FROM uutiset WHERE published=1 ORDER BY date DESC, luotu DESC');
    }
    $posts = array_map(function ($row) {
        if (isset($row['published'])) $row['published'] = (int)$row['published'];
        return $row;
    }, $stmt->fetchAll());
    echo json_encode(['posts' => $posts]);
    exit;
}

/* ── POST ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isAdmin()) {
        http_response_code(403);
        echo json_encode(['error' => 'unauthorized']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        $body  = trim($_POST['body']  ?? '');
        $date  = trim($_POST['date']  ?? '');
        if (!$title || !$body || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            echo json_encode(['error' => 'invalid_input']);
            exit;
        }
        $stmt = $pdo->prepare('INSERT INTO uutiset (title, body, date, published) VALUES (?, ?, ?, 1)');
        $stmt->execute([$title, $body, $date]);
        echo json_encode(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
        exit;
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['error' => 'invalid_id']); exit; }
        $pdo->prepare('UPDATE uutiset SET published = 1 - published WHERE id = ?')->execute([$id]);
        $pub = (int)$pdo->prepare('SELECT published FROM uutiset WHERE id = ?')->execute([$id]);
        $row = $pdo->prepare('SELECT published FROM uutiset WHERE id = ?');
        $row->execute([$id]);
        echo json_encode(['ok' => true, 'published' => (int)$row->fetchColumn()]);
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['error' => 'invalid_id']); exit; }
        $pdo->prepare('DELETE FROM uutiset WHERE id = ?')->execute([$id]);
        echo json_encode(['ok' => true]);
        exit;
    }

    echo json_encode(['error' => 'unknown_action']);
}
