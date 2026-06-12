<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/chat.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    echo json_encode(['ok' => false, 'error' => 'Not logged in']);
    exit;
}

$conversationId = (int)($_GET['conversation_id'] ?? 0);
$sinceId = (int)($_GET['since'] ?? 0);

if (!chat_can_view($conversationId, current_user_id())) {
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

// Mark messages as read while polling (user is viewing the convo)
chat_mark_read($conversationId, current_user_id());

$messages = chat_get_messages($conversationId, $sinceId);
echo json_encode(['ok' => true, 'messages' => $messages]);