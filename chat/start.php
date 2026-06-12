<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/chat.php';
require_login();

$withUserId  = (int)($_GET['with'] ?? 0);
$propertyId  = isset($_GET['property_id']) ? (int)$_GET['property_id'] : null;
$bookingId   = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : null;

if ($withUserId <= 0 || $withUserId === current_user_id()) {
    set_flash('danger', 'Invalid chat target.');
    header('Location: /rentbridge/chat.php');
    exit;
}

// Determine context type
$contextType = 'other';
if ($propertyId) $contextType = 'property_inquiry';
if ($bookingId)  $contextType = 'booking';

try {
    $conversationId = chat_get_or_create_conversation(
        current_user_id(),
        $withUserId,
        $propertyId,
        $bookingId,
        $contextType
    );
    header('Location: /rentbridge/chat/conversation.php?id=' . $conversationId);
    exit;
} catch (Throwable $e) {
    set_flash('danger', 'Could not start chat: ' . $e->getMessage());
    header('Location: /rentbridge/chat.php');
    exit;
}