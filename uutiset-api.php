<?php
/**
 * uutiset-api.php – News API for Pielisen Pyörähuolto
 *
 * Storage: uutiset.json (no database required)
 *
 * GET  (no token)     → published posts only  (for index.html)
 * GET  ?token=HASH    → all posts             (for admin.html)
 * POST action=add     + token → add post
 * POST action=toggle  + token + id → toggle published
 * POST action=delete  + token + id → delete post
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

define('NEWS_TOKEN_HASH', 'a1d182d125869e9e5df6cff0f27f9d194e61793c90183ae0b5b86a0bc87ea4fc');
define('NEWS_FILE', __DIR__ . '/uutiset.json');

function isAdmin(): bool {
    $token = trim($_GET['token'] ?? $_POST['token'] ?? '');
    return $token !== '' && hash_equals(NEWS_TOKEN_HASH, strtolower($token));
}

function readPosts(): array {
    if (!file_exists(NEWS_FILE)) return [];
    $data = json_decode(file_get_contents(NEWS_FILE), true);
    return is_array($data) ? $data : [];
}

function writePosts(array $posts): void {
    file_put_contents(NEWS_FILE, json_encode(array_values($posts), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/* ── GET ── */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $all = readPosts();
    if (isAdmin()) {
        $posts = $all;
    } else {
        $posts = array_values(array_filter($all, fn($p) => !empty($p['published'])));
    }
    usort($posts, fn($a, $b) => strcmp($b['date'], $a['date']));
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
        $posts = readPosts();
        $maxId = array_reduce($posts, fn($carry, $p) => max($carry, (int)($p['id'] ?? 0)), 0);
        $posts[] = ['id' => $maxId + 1, 'title' => $title, 'body' => $body, 'date' => $date, 'published' => 1];
        writePosts($posts);
        echo json_encode(['ok' => true, 'id' => $maxId + 1]);
        exit;
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['error' => 'invalid_id']); exit; }
        $posts = readPosts();
        $pub = 0;
        foreach ($posts as &$p) {
            if ((int)$p['id'] === $id) {
                $p['published'] = $p['published'] ? 0 : 1;
                $pub = $p['published'];
                break;
            }
        }
        writePosts($posts);
        echo json_encode(['ok' => true, 'published' => $pub]);
        exit;
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['error' => 'invalid_id']); exit; }
        $posts = array_filter(readPosts(), fn($p) => (int)$p['id'] !== $id);
        writePosts($posts);
        echo json_encode(['ok' => true]);
        exit;
    }

    echo json_encode(['error' => 'unknown_action']);
}
