<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/chat.php';
require_login();

$userId = current_user_id();
$role   = current_role();
$type   = $_GET['type'] ?? '';

if ($type === 'property_inquiry') {
    // Student → Agent about a specific property
    if ($role !== 'student') {
        http_response_code(403);
        die('Only students can start property inquiries.');
    }

    $propertyId = (int)($_GET['property_id'] ?? 0);
    if ($propertyId <= 0) die('Invalid property.');

    $stmt = db()->prepare("
        SELECT landlord_id, status, assigned_agent_id, agent_status, viewing_mode, title
          FROM properties WHERE id = ? LIMIT 1
    ");
    $stmt->execute([$propertyId]);
    $prop = $stmt->fetch();
    if (!$prop) die('Property not found.');
    if ($prop['status'] !== 'available') {
        die('You can only ask about properties that are currently available.');
    }
    if (empty($prop['assigned_agent_id']) || $prop['agent_status'] !== 'accepted') {
        die('No agent is currently assigned to this property. Please try again later.');
    }

    $agentUserId = (int)$prop['assigned_agent_id'];

    $convoId = find_or_create_conversation(
        $userId,
        $agentUserId,
        'property_inquiry',
        $propertyId,
        null
    );

    // Seed a one-time system notice so the agent knows the viewing mode
    $viewingMode = $prop['viewing_mode'] ?? 'agent_led';
    $notice = match ($viewingMode) {
        'landlord_led' => "📋 Viewing mode: Landlord-led. The landlord will be present during all viewings — coordinate with them before scheduling.",
        'either'       => "📋 Viewing mode: Either. Both you and the landlord can facilitate viewings — confirm with the landlord who will handle each visit.",
        default        => "📋 Viewing mode: Agent-led. You are responsible for arranging access and conducting the viewing independently.",
    };

    $pdo = db();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM messages WHERE conversation_id = ?");
    $stmt->execute([$convoId]);
    if ((int)$stmt->fetchColumn() === 0) {
        $pdo->prepare("
            INSERT INTO messages (conversation_id, sender_id, body, message_type, sent_at)
            VALUES (?, ?, ?, 'system_notice', NOW())
        ")->execute([$convoId, $agentUserId, $notice]);
        $pdo->prepare("
            UPDATE conversations SET last_message_at = NOW(), last_message_preview = ?, last_sender_id = ? WHERE id = ?
        ")->execute([substr($notice, 0, 120), $agentUserId, $convoId]);
    }

    header('Location: /rentbridge/chat/conversation.php?id=' . $convoId);
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
    header('Location: /rentbridge/chat/conversation.php?id=' . $convoId);
    exit;
}

elseif ($type === 'agent_case') {
    // Student → Agent (must be assigned to a tenancy)
    $tenancyId = (int)($_GET['tenancy_id'] ?? 0);
    if ($tenancyId <= 0) die('Invalid tenancy.');

    $stmt = db()->prepare("
        SELECT student_id, agent_id FROM tenancies
         WHERE id = ? AND agent_id IS NOT NULL LIMIT 1
    ");
    $stmt->execute([$tenancyId]);
    $b = $stmt->fetch();
    if (!$b) die('Tenancy not found or no agent assigned.');

    $isStudent = $userId === (int)$b['student_id'];
    $isAgent   = $userId === (int)$b['agent_id'];

    if (!$isStudent && !$isAgent) {
        http_response_code(403);
        die('Not authorized.');
    }

    $otherId = $isStudent ? (int)$b['agent_id'] : (int)$b['student_id'];
    $convoId = find_or_create_conversation($userId, $otherId, 'agent_case', null, $tenancyId);
    header('Location: /rentbridge/chat/conversation.php?id=' . $convoId);
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
        $opener = "Hi! I'm interested in joining your co-tenancy for \""
                . $post['property_title'] . "\".";

        $stmt = db()->prepare("
            INSERT INTO messages (conversation_id, sender_id, body, sent_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$convoId, $userId, $opener]);
    }

    header('Location: /rentbridge/chat/conversation.php?id=' . $convoId);
    exit;
}
else {
    http_response_code(400);
    die('Invalid conversation type.');
}