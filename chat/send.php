<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/chat.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// Manual CSRF check (uses _csrf field, matches your csrf_field() helper)
if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['_csrf'] ?? '')) {
    echo json_encode(['ok' => false, 'error' => 'CSRF token mismatch. Please refresh.']);
    exit;
}

try {
    $conversationId = (int)($_POST['conversation_id'] ?? 0);
    $body = $_POST['body'] ?? '';

    [$ok, $err, $messageId] = send_message($conversationId, current_user_id(), $body);

    if (!$ok) {
        echo json_encode(['ok' => false, 'error' => $err]);
        exit;
    }

    $pdo = db();
    $stmt = $pdo->prepare("SELECT id, sender_id, body, sent_at FROM messages WHERE id = ?");
    $stmt->execute([$messageId]);
    $msg = $stmt->fetch();

    echo json_encode(['ok' => true, 'message' => $msg]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}