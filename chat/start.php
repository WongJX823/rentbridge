<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/chat.php';
require_login();

$userId = current_user_id();
$role   = current_role();
$type   = $_GET['type'] ?? '';

if ($type === 'property_inquiry') {
    // Student → Landlord about a specific property
    if ($role !== 'student') {
        http_response_code(403);
        die('Only students can start property inquiries.');
    }

    $propertyId = (int)($_GET['property_id'] ?? 0);
    if ($propertyId <= 0) die('Invalid property.');

    $stmt = db()->prepare("SELECT landlord_id, status FROM properties WHERE id = ? LIMIT 1");
    $stmt->execute([$propertyId]);
    $prop = $stmt->fetch();
    if (!$prop) die('Property not found.');
    if ($prop['status'] !== 'available') {
        die('You can only ask about properties that are currently available.');
    }

    $convoId = find_or_create_conversation(
        $userId,
        (int)$prop['landlord_id'],
        'property_inquiry',
        $propertyId,
        null
    );

    header('Location: /rentbridge/chat.php?id=' . $convoId);
    exit;
}

elseif ($type === 'friend') {
    // Student → Friend (must already be friends)
    require_once __DIR__ . '/../includes/friends.php';
    $friendId = (int)($_GET['friend_id'] ?? 0);
    if ($friendId <= 0) die('Invalid friend.');

    if (!are_friends($userId, $friendId)) {
        die('You can only message users on your friend list.');
    }

    $convoId = find_or_create_conversation($userId, $friendId, 'friend', null, null);
    header('Location: /rentbridge/chat.php?id=' . $convoId);
    exit;
}

elseif ($type === 'agent_case') {
    // Student → Agent (must be assigned to a booking)
    $bookingId = (int)($_GET['booking_id'] ?? 0);
    if ($bookingId <= 0) die('Invalid booking.');

    $stmt = db()->prepare("
        SELECT student_id, agent_id FROM bookings
         WHERE id = ? AND agent_id IS NOT NULL LIMIT 1
    ");
    $stmt->execute([$bookingId]);
    $b = $stmt->fetch();
    if (!$b) die('Booking not found or no agent assigned.');

    $isStudent = $userId === (int)$b['student_id'];
    $isAgent   = $userId === (int)$b['agent_id'];

    if (!$isStudent && !$isAgent) {
        http_response_code(403);
        die('Not authorized.');
    }

    $otherId = $isStudent ? (int)$b['agent_id'] : (int)$b['student_id'];
    $convoId = find_or_create_conversation($userId, $otherId, 'agent_case', null, $bookingId);
    header('Location: /rentbridge/chat.php?id=' . $convoId);
    exit;
}

else {
    http_response_code(400);
    die('Invalid conversation type.');
}