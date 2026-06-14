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
elseif ($type === 'partner_inquiry') {
    // Student → Student about a co-tenancy post
    if ($role !== 'student') {
        http_response_code(403);
        die('Only students can reply to partner posts.');
    }

    $posterId = (int)($_GET['with'] ?? 0);
    $postId   = (int)($_GET['post_id'] ?? 0);

    if ($posterId <= 0 || $postId <= 0) {
        die('Invalid partner inquiry.');
    }
    if ($posterId === $userId) {
        die('You cannot message your own post.');
    }

    // Validate: the post exists, is open, and belongs to the target user
    $stmt = db()->prepare("
        SELECT ctp.id, ctp.property_id, ctp.message, ctp.housemates_needed,
               p.title AS property_title
          FROM co_tenancy_posts ctp
          JOIN properties p ON p.id = ctp.property_id
         WHERE ctp.id = ?
           AND ctp.poster_id = ?
           AND ctp.status = 'open'
         LIMIT 1
    ");
    $stmt->execute([$postId, $posterId]);
    $post = $stmt->fetch();

    if (!$post) {
        die('This partner post is no longer open or does not exist.');
    }

    // Verify viewer is a student
    $stmt = db()->prepare("SELECT 1 FROM students WHERE user_id = ?");
    $stmt->execute([$userId]);
    if (!$stmt->fetchColumn()) {
        http_response_code(403);
        die('Only students can use this feature.');
    }

    // Create or find conversation
    // Reuse 'property_inquiry' type since the underlying chat schema accepts it,
    // OR use a dedicated 'partner_inquiry' if your schema enum includes it.
    // Safer: use 'property_inquiry' with the property_id from the post.
    $convoId = find_or_create_conversation(
        $userId,
        $posterId,
        'partner_inquiry',
        (int)$post['property_id'],
        null
    );

    // Auto-paste a friendly opener if this is a NEW conversation
    // Check if there are any existing messages — if not, seed one
    $stmt = db()->prepare("SELECT COUNT(*) FROM messages WHERE conversation_id = ?");
    $stmt->execute([$convoId]);
    $msgCount = (int)$stmt->fetchColumn();

    if ($msgCount === 0) {
        $propertyUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/rentbridge/property.php?id=' . (int)$post['property_id'];
        $opener = "Hi! I'm interested in joining your co-tenancy for \""
                . $post['property_title'] . "\".\n\n"
                . "Property: " . $propertyUrl;

        $stmt = db()->prepare("
            INSERT INTO messages (conversation_id, sender_id, body, sent_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$convoId, $userId, $opener]);
    }

    header('Location: /rentbridge/chat.php?id=' . $convoId);
    exit;
}
else {
    http_response_code(400);
    die('Invalid conversation type.');
}