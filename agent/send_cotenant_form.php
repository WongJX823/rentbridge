<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/chat.php';
require_role('agent');

$bookingId = (int)($_POST['booking_id'] ?? 0);
if ($bookingId <= 0) {
    die('Invalid booking.');
}

verify_csrf();

$pdo = db();
$userId = current_user_id();

// Verify agent is assigned to this booking
$stmt = $pdo->prepare("
    SELECT b.id, b.student_id, b.agent_id, b.property_id,
           p.title AS property_title
      FROM bookings b
      JOIN properties p ON p.id = b.property_id
     WHERE b.id = ? AND b.agent_id = ? LIMIT 1
");
$stmt->execute([$bookingId, $userId]);
$booking = $stmt->fetch();

if (!$booking) {
    die('Booking not found or you are not the assigned agent.');
}

// Find or create conversation between agent and student
$convoId = find_or_create_conversation(
    $userId,
    (int)$booking['student_id'],
    'agent_case',
    null,
    $bookingId
);

// Send the special message
$metadata = json_encode([
    'booking_id'     => $bookingId,
    'property_title' => $booking['property_title'],
]);

$stmt = $pdo->prepare("
    INSERT INTO messages (conversation_id, sender_id, body, message_type, metadata)
    VALUES (?, ?, ?, 'co_tenant_form', ?)
");
$stmt->execute([
    $convoId,
    $userId,
    "📋 Co-tenant details requested\nPlease fill in the names and IC numbers of everyone who will rent this property with you.",
    $metadata,
]);

// Notify student
notify(
    (int)$booking['student_id'],
    'cotenant_form_request',
    'Co-tenant details needed',
    'Fill in the co-tenant info for "' . $booking['property_title'] . '" — open the chat to complete the form.',
    '/rentbridge/chat/conversation.php?id=' . $convoId
);

set_flash('success', 'Co-tenant form sent to student via chat.');
header('Location: /rentbridge/agent/case.php?id=' . $bookingId);
exit;