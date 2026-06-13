<?php
/**
 * Chat system — shared helpers
 *
 * Conversations are 1-to-1 between two users, with optional context
 * (a property inquiry, a booking, etc.). Users are stored canonically
 * with user_a < user_b.
 */

require_once __DIR__ . '/auth.php';

/**
 * Find or create a conversation between two users with given context.
 * Returns the conversation ID.
 */
function find_or_create_conversation(
    int $userA,
    int $userB,
    string $contextType = 'other',
    ?int $propertyId = null,
    ?int $bookingId = null
): int {
    if ($userA === $userB) {
        throw new RuntimeException('Cannot create conversation with yourself.');
    }

    [$lo, $hi] = $userA < $userB ? [$userA, $userB] : [$userB, $userA];
    $pdo = db();

    $stmt = $pdo->prepare("
        SELECT id FROM conversations
         WHERE user_a = ? AND user_b = ?
           AND (property_id <=> ?) AND (booking_id <=> ?)
         LIMIT 1
    ");
    $stmt->execute([$lo, $hi, $propertyId, $bookingId]);
    $existing = $stmt->fetchColumn();
    if ($existing) return (int)$existing;

    $stmt = $pdo->prepare("
        INSERT INTO conversations (user_a, user_b, property_id, booking_id, context_type)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([$lo, $hi, $propertyId, $bookingId, $contextType]);
    return (int)$pdo->lastInsertId();
}

/**
 * Send a message in a conversation.
 */
function send_message(int $conversationId, int $senderId, string $body): array {
    $body = trim($body);
    if ($body === '') {
        return [false, 'Message cannot be empty.', null];
    }
    if (mb_strlen($body) > 2000) {
        return [false, 'Message too long (max 2000 characters).', null];
    }

    $pdo = db();

    $stmt = $pdo->prepare("
        SELECT user_a, user_b, is_locked FROM conversations WHERE id = ? LIMIT 1
    ");
    $stmt->execute([$conversationId]);
    $convo = $stmt->fetch();
    if (!$convo) {
        return [false, 'Conversation not found.', null];
    }
    if ($senderId !== (int)$convo['user_a'] && $senderId !== (int)$convo['user_b']) {
        return [false, 'You are not a participant in this conversation.', null];
    }
    if (!empty($convo['is_locked'])) {
        return [false, 'This conversation is closed.', null];
    }

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("
            INSERT INTO messages (conversation_id, sender_id, body)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$conversationId, $senderId, $body]);
        $messageId = (int)$pdo->lastInsertId();

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
        return [true, '', $messageId];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return [false, 'Could not send message: ' . $e->getMessage(), null];
    }
}

/**
 * Get a single conversation if the user is allowed to see it.
 */
function get_conversation_for_user(int $conversationId, int $userId, string $role): ?array {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT c.*,
               ua.email AS user_a_email,
               ub.email AS user_b_email,
               ua.primary_role AS user_a_role,
               ub.primary_role AS user_b_role,
               p.title AS property_title
          FROM conversations c
          JOIN users ua ON ua.id = c.user_a
          JOIN users ub ON ub.id = c.user_b
          LEFT JOIN properties p ON p.id = c.property_id
         WHERE c.id = ?
         LIMIT 1
    ");
    $stmt->execute([$conversationId]);
    $convo = $stmt->fetch();
    if (!$convo) return null;

    if ($role !== 'admin' &&
        $userId !== (int)$convo['user_a'] &&
        $userId !== (int)$convo['user_b']) {
        return null;
    }
    return $convo;
}

/**
 * Get messages of a conversation.
 */
function get_messages(int $conversationId, ?int $afterId = null, int $limit = 100): array {
    $pdo = db();
    if ($afterId !== null) {
        $stmt = $pdo->prepare("
            SELECT id, sender_id, body, sent_at, read_at
              FROM messages
             WHERE conversation_id = ? AND id > ?
             ORDER BY sent_at ASC, id ASC
             LIMIT ?
        ");
        $stmt->bindValue(1, $conversationId, PDO::PARAM_INT);
        $stmt->bindValue(2, $afterId, PDO::PARAM_INT);
        $stmt->bindValue(3, $limit, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare("
            SELECT id, sender_id, body, sent_at, read_at
              FROM messages
             WHERE conversation_id = ?
             ORDER BY sent_at ASC, id ASC
             LIMIT ?
        ");
        $stmt->bindValue(1, $conversationId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
    }
    return $stmt->fetchAll();
}

/**
 * Mark unread messages in a conversation as read.
 */
function mark_messages_read(int $conversationId, int $userId): void {
    $stmt = db()->prepare("
        UPDATE messages
           SET read_at = NOW()
         WHERE conversation_id = ?
           AND sender_id != ?
           AND read_at IS NULL
    ");
    $stmt->execute([$conversationId, $userId]);
}

/**
 * Get the inbox for a user.
 */
function get_user_inbox(int $userId): array {
    $stmt = db()->prepare("
        SELECT c.id, c.context_type, c.property_id, c.booking_id, c.is_locked,
               c.last_message_at, c.last_message_preview, c.last_sender_id,
               CASE WHEN c.user_a = ? THEN c.user_b ELSE c.user_a END AS other_user_id,
               p.title AS property_title,
               (SELECT COUNT(*) FROM messages m
                 WHERE m.conversation_id = c.id
                   AND m.sender_id != ?
                   AND m.read_at IS NULL) AS unread_count
          FROM conversations c
          LEFT JOIN properties p ON p.id = c.property_id
         WHERE c.user_a = ? OR c.user_b = ?
         ORDER BY c.last_message_at DESC, c.created_at DESC
    ");
    $stmt->execute([$userId, $userId, $userId, $userId]);
    return $stmt->fetchAll();
}

/**
 * Unread message count across all conversations.
 * Both names exist for backward compatibility.
 */
function unread_message_count(int $userId): int {
    $stmt = db()->prepare("
        SELECT COUNT(*) FROM messages m
          JOIN conversations c ON c.id = m.conversation_id
         WHERE (c.user_a = ? OR c.user_b = ?)
           AND m.sender_id != ?
           AND m.read_at IS NULL
    ");
    $stmt->execute([$userId, $userId, $userId]);
    return (int)$stmt->fetchColumn();
}

/** Alias so old code calling chat_unread_total() still works. */
function chat_unread_total(int $userId): int {
    return unread_message_count($userId);
}

/**
 * Get display name + role for a user (used in inbox).
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

/** Alias for legacy code that calls user_display_name(). */
function user_display_name(int $userId): string {
    $info = chat_get_user_display($userId);
    return $info['nickname'] ?: $info['name'];
}