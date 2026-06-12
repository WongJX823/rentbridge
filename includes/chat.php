<?php
/**
 * Chat system helpers.
 * Conversations are 1-to-1, optionally linked to a property or booking.
 * Always store users in order: user_a < user_b (so we can find pairs uniquely).
 */

require_once __DIR__ . '/db.php';

/**
 * Normalize a user pair so user_a < user_b.
 */
function chat_normalize_pair(int $a, int $b): array {
    return $a < $b ? [$a, $b] : [$b, $a];
}

/**
 * Find or create a conversation between two users, optionally tied to a property/booking.
 * Returns the conversation_id.
 */
function chat_get_or_create_conversation(
    int $userId1,
    int $userId2,
    ?int $propertyId = null,
    ?int $bookingId  = null,
    string $contextType = 'property_inquiry'
): int {
    if ($userId1 === $userId2) {
        throw new InvalidArgumentException('Cannot chat with yourself.');
    }

    $pdo = db();
    [$ua, $ub] = chat_normalize_pair($userId1, $userId2);

    // Try to find existing
    $stmt = $pdo->prepare("
        SELECT id FROM conversations
         WHERE user_a = ? AND user_b = ?
           AND (property_id <=> ?)
           AND (booking_id  <=> ?)
         LIMIT 1
    ");
    $stmt->execute([$ua, $ub, $propertyId, $bookingId]);
    $row = $stmt->fetch();
    if ($row) return (int)$row['id'];

    // Create new
    $stmt = $pdo->prepare("
        INSERT INTO conversations
            (user_a, user_b, property_id, booking_id, context_type, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([$ua, $ub, $propertyId, $bookingId, $contextType]);
    return (int)$pdo->lastInsertId();
}

/**
 * Send a message in a conversation.
 * Updates the conversation's last-message metadata.
 * Returns the inserted message ID.
 */
function chat_send_message(int $conversationId, int $senderId, string $body): int {
    $body = trim($body);
    if ($body === '') {
        throw new InvalidArgumentException('Message cannot be empty.');
    }
    if (mb_strlen($body) > 2000) {
        throw new InvalidArgumentException('Message too long (max 2000 chars).');
    }

    $pdo = db();

    // Verify sender is part of this conversation + conversation isn't locked
    $stmt = $pdo->prepare("
        SELECT user_a, user_b, is_locked
          FROM conversations
         WHERE id = ?
         LIMIT 1
    ");
    $stmt->execute([$conversationId]);
    $convo = $stmt->fetch();
    if (!$convo) {
        throw new RuntimeException('Conversation not found.');
    }
    if ((int)$convo['user_a'] !== $senderId && (int)$convo['user_b'] !== $senderId) {
        throw new RuntimeException('You are not in this conversation.');
    }
    if ((int)$convo['is_locked'] === 1) {
        throw new RuntimeException('This conversation is closed.');
    }

    $pdo->beginTransaction();
    try {
        // Insert message
        $stmt = $pdo->prepare("
            INSERT INTO messages (conversation_id, sender_id, body, sent_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$conversationId, $senderId, $body]);
        $messageId = (int)$pdo->lastInsertId();

        // Update conversation's last-message metadata
        $preview = mb_substr($body, 0, 120);
        $stmt = $pdo->prepare("
            UPDATE conversations
               SET last_message_at      = NOW(),
                   last_message_preview = ?,
                   last_sender_id       = ?
             WHERE id = ?
        ");
        $stmt->execute([$preview, $senderId, $conversationId]);

        $pdo->commit();
        return $messageId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Get all conversations for a user, ordered by most recent activity.
 */
function chat_get_inbox(int $userId): array {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT c.*,
               CASE WHEN c.user_a = ? THEN c.user_b ELSE c.user_a END AS other_user_id,
               p.title         AS property_title,
               (SELECT COUNT(*) FROM messages m
                  WHERE m.conversation_id = c.id
                    AND m.sender_id != ?
                    AND m.read_at IS NULL) AS unread_count
          FROM conversations c
          LEFT JOIN properties p ON p.id = c.property_id
         WHERE c.user_a = ? OR c.user_b = ?
         ORDER BY (c.last_message_at IS NULL) ASC, c.last_message_at DESC
    ");
    $stmt->execute([$userId, $userId, $userId, $userId]);
    return $stmt->fetchAll();
}

/**
 * Get display info for a user (name + role).
 * Used to label conversations in the inbox.
 */
function chat_get_user_display(int $userId): array {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT u.id, u.primary_role,
               COALESCE(s.full_name, l.full_name, a.full_name, 'Unknown') AS name,
               COALESCE(s.preferred_name, l.preferred_name, a.preferred_name, '') AS nickname
          FROM users u
          LEFT JOIN students  s ON s.user_id = u.id
          LEFT JOIN landlords l ON l.user_id = u.id
          LEFT JOIN agents    a ON a.user_id = u.id
         WHERE u.id = ?
    ");
    $stmt->execute([$userId]);
    return $stmt->fetch() ?: ['id'=>$userId, 'name'=>'Unknown', 'nickname'=>'', 'primary_role'=>'unknown'];
}

/**
 * Get messages in a conversation, oldest first.
 * Returns messages newer than $sinceId if provided (for polling).
 */
function chat_get_messages(int $conversationId, int $sinceId = 0): array {
    $pdo = db();
    if ($sinceId > 0) {
        $stmt = $pdo->prepare("
            SELECT id, sender_id, body, sent_at, read_at
              FROM messages
             WHERE conversation_id = ? AND id > ?
             ORDER BY id ASC
        ");
        $stmt->execute([$conversationId, $sinceId]);
    } else {
        $stmt = $pdo->prepare("
            SELECT id, sender_id, body, sent_at, read_at
              FROM messages
             WHERE conversation_id = ?
             ORDER BY id ASC
             LIMIT 200
        ");
        $stmt->execute([$conversationId]);
    }
    return $stmt->fetchAll();
}

/**
 * Mark all unread messages in a conversation as read FROM the perspective of $userId.
 * (Marks messages sent by OTHER party as read.)
 */
function chat_mark_read(int $conversationId, int $userId): void {
    $pdo = db();
    $stmt = $pdo->prepare("
        UPDATE messages
           SET read_at = NOW()
         WHERE conversation_id = ?
           AND sender_id != ?
           AND read_at IS NULL
    ");
    $stmt->execute([$conversationId, $userId]);
}

/**
 * Total unread message count across all conversations for a user.
 * Used for the navbar badge.
 */
function chat_unread_total(int $userId): int {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
          FROM messages m
          JOIN conversations c ON c.id = m.conversation_id
         WHERE (c.user_a = ? OR c.user_b = ?)
           AND m.sender_id != ?
           AND m.read_at IS NULL
    ");
    $stmt->execute([$userId, $userId, $userId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Check if user is allowed to view a conversation.
 */
function chat_can_view(int $conversationId, int $userId): bool {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT 1 FROM conversations
         WHERE id = ?
           AND (user_a = ? OR user_b = ?)
    ");
    $stmt->execute([$conversationId, $userId, $userId]);
    return (bool)$stmt->fetchColumn();
}