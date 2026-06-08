<?php
/**
 * Friend system — shared helpers
 *
 * Friendships are bilateral and stored once (user_a < user_b).
 * Friend requests are one-directional during pending state.
 */

require_once __DIR__ . '/auth.php';

/**
 * Are two users already friends?
 */
function are_friends(int $userA, int $userB): bool {
    if ($userA === $userB) return false;
    [$lo, $hi] = $userA < $userB ? [$userA, $userB] : [$userB, $userA];

    $stmt = db()->prepare('SELECT 1 FROM friends WHERE user_a = ? AND user_b = ? LIMIT 1');
    $stmt->execute([$lo, $hi]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Get pending request between two users (in either direction).
 * Returns the request row, or null if none pending.
 */
function pending_request_between(int $userA, int $userB): ?array {
    $stmt = db()->prepare(
        "SELECT * FROM friend_requests
          WHERE status = 'pending'
            AND ((requester_id = ? AND receiver_id = ?)
                 OR (requester_id = ? AND receiver_id = ?))
          LIMIT 1"
    );
    $stmt->execute([$userA, $userB, $userB, $userA]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Send a friend request. Returns [success, errorMessage].
 */
function send_friend_request(int $requesterId, int $receiverId, string $message = ''): array {
    if ($requesterId === $receiverId) {
        return [false, 'You cannot add yourself as a friend.'];
    }

    if (are_friends($requesterId, $receiverId)) {
        return [false, 'You are already friends.'];
    }

    if (pending_request_between($requesterId, $receiverId)) {
        return [false, 'A friend request between you two is already pending.'];
    }

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            "INSERT INTO friend_requests (requester_id, receiver_id, message, status)
             VALUES (?, ?, ?, 'pending')"
        );
        $stmt->execute([$requesterId, $receiverId, $message !== '' ? $message : null]);
        $reqId = (int)$pdo->lastInsertId();

        // Notify the receiver
        $requesterName = user_display_name($requesterId);
        notify(
            $receiverId,
            'friend_request',
            'New friend request',
            $requesterName . ' wants to connect with you on RentBridge.',
            '/rentbridge/student/friends.php'
        );

        $pdo->commit();
        return [true, ''];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return [false, 'Could not send request: ' . $e->getMessage()];
    }
}

/**
 * Accept a friend request.
 * Only the receiver can accept.
 */
function accept_friend_request(int $requestId, int $acceptingUserId): array {
    $pdo = db();
    $pdo->beginTransaction();
    try {
        // Lock and verify
        $stmt = $pdo->prepare(
            "SELECT * FROM friend_requests
              WHERE id = ? AND receiver_id = ? AND status = 'pending'
              LIMIT 1"
        );
        $stmt->execute([$requestId, $acceptingUserId]);
        $req = $stmt->fetch();

        if (!$req) {
            $pdo->rollBack();
            return [false, 'Request not found or already responded to.'];
        }

        $requesterId = (int)$req['requester_id'];
        $receiverId  = (int)$req['receiver_id'];

        // Mark request accepted
        $stmt = $pdo->prepare(
            "UPDATE friend_requests
                SET status = 'accepted',
                    responded_at = NOW()
              WHERE id = ?"
        );
        $stmt->execute([$requestId]);

        // Insert into friends (with lower ID first)
        [$lo, $hi] = $requesterId < $receiverId ? [$requesterId, $receiverId] : [$receiverId, $requesterId];
        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO friends (user_a, user_b) VALUES (?, ?)"
        );
        $stmt->execute([$lo, $hi]);

        // Notify the original requester
        $accepterName = user_display_name($acceptingUserId);
        notify(
            $requesterId,
            'friend_accepted',
            'Friend request accepted',
            $accepterName . ' accepted your friend request.',
            '/rentbridge/student/friends.php'
        );

        $pdo->commit();
        return [true, ''];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return [false, 'Could not accept request: ' . $e->getMessage()];
    }
}

/**
 * Reject a friend request.
 */
function reject_friend_request(int $requestId, int $rejectingUserId): array {
    $pdo = db();
    $stmt = $pdo->prepare(
        "UPDATE friend_requests
            SET status = 'rejected',
                responded_at = NOW()
          WHERE id = ? AND receiver_id = ? AND status = 'pending'"
    );
    $stmt->execute([$requestId, $rejectingUserId]);
    return [$stmt->rowCount() > 0, $stmt->rowCount() > 0 ? '' : 'Request not found or already responded to.'];
}

/**
 * Cancel an outgoing pending request.
 */
function cancel_friend_request(int $requestId, int $requesterId): array {
    $pdo = db();
    $stmt = $pdo->prepare(
        "UPDATE friend_requests
            SET status = 'cancelled',
                responded_at = NOW()
          WHERE id = ? AND requester_id = ? AND status = 'pending'"
    );
    $stmt->execute([$requestId, $requesterId]);
    return [$stmt->rowCount() > 0, ''];
}

/**
 * Remove a friend.
 */
function remove_friend(int $userA, int $userB): bool {
    [$lo, $hi] = $userA < $userB ? [$userA, $userB] : [$userB, $userA];
    $stmt = db()->prepare('DELETE FROM friends WHERE user_a = ? AND user_b = ?');
    $stmt->execute([$lo, $hi]);
    return $stmt->rowCount() > 0;
}

/**
 * Get all friends of a user.
 * Returns rows with: friend_id, full_name, preferred_name, email, became_friends_at
 */
function get_friends(int $userId): array {
    $stmt = db()->prepare("
        SELECT
            CASE WHEN f.user_a = ? THEN f.user_b ELSE f.user_a END AS friend_id,
            f.became_friends_at,
            u.email,
            s.full_name,
            s.preferred_name,
            s.matric_no
          FROM friends f
          JOIN users u ON u.id = (CASE WHEN f.user_a = ? THEN f.user_b ELSE f.user_a END)
          LEFT JOIN students s ON s.user_id = u.id
         WHERE f.user_a = ? OR f.user_b = ?
         ORDER BY s.preferred_name ASC
    ");
    $stmt->execute([$userId, $userId, $userId, $userId]);
    return $stmt->fetchAll();
}

/**
 * Get incoming pending requests (people who want to befriend me).
 */
function get_pending_requests_received(int $userId): array {
    $stmt = db()->prepare("
        SELECT fr.id, fr.requester_id, fr.message, fr.created_at,
               s.full_name, s.preferred_name, s.matric_no,
               u.email
          FROM friend_requests fr
          JOIN users u ON u.id = fr.requester_id
          LEFT JOIN students s ON s.user_id = fr.requester_id
         WHERE fr.receiver_id = ? AND fr.status = 'pending'
         ORDER BY fr.created_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Get outgoing pending requests (people I asked to befriend).
 */
function get_pending_requests_sent(int $userId): array {
    $stmt = db()->prepare("
        SELECT fr.id, fr.receiver_id, fr.created_at,
               s.full_name, s.preferred_name, s.matric_no,
               u.email
          FROM friend_requests fr
          JOIN users u ON u.id = fr.receiver_id
          LEFT JOIN students s ON s.user_id = fr.receiver_id
         WHERE fr.requester_id = ? AND fr.status = 'pending'
         ORDER BY fr.created_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Search for students to befriend (by name, matric, or email).
 * Excludes self, existing friends, and people with pending requests.
 */
function search_students_to_befriend(int $currentUserId, string $query): array {
    $query = trim($query);
    if (strlen($query) < 2) return [];

    $like = '%' . $query . '%';

    $stmt = db()->prepare("
        SELECT u.id AS user_id, u.email,
               s.full_name, s.preferred_name, s.matric_no
          FROM users u
          JOIN students s ON s.user_id = u.id
         WHERE u.primary_role = 'student'
           AND u.status = 'active'
           AND u.id != ?
           AND (s.full_name LIKE ?
                OR s.preferred_name LIKE ?
                OR s.matric_no LIKE ?
                OR u.email LIKE ?)
           AND u.id NOT IN (
               SELECT CASE WHEN user_a = ? THEN user_b ELSE user_a END
                 FROM friends
                WHERE user_a = ? OR user_b = ?
           )
           AND u.id NOT IN (
               SELECT receiver_id FROM friend_requests
                WHERE requester_id = ? AND status = 'pending'
               UNION
               SELECT requester_id FROM friend_requests
                WHERE receiver_id = ? AND status = 'pending'
           )
         ORDER BY s.preferred_name ASC
         LIMIT 20
    ");
    $stmt->execute([
        $currentUserId,
        $like, $like, $like, $like,
        $currentUserId, $currentUserId, $currentUserId,
        $currentUserId, $currentUserId
    ]);
    return $stmt->fetchAll();
}

/**
 * Helper: get display name for any user (for notification messages).
 * Falls back gracefully across roles.
 */
function user_display_name(int $userId): string {
    $pdo = db();

    foreach (['students', 'landlords', 'agents'] as $table) {
        $stmt = $pdo->prepare("SELECT preferred_name FROM $table WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $name = $stmt->fetchColumn();
        if ($name) return $name;
    }

    // Fallback to email prefix
    $stmt = $pdo->prepare('SELECT email FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $email = $stmt->fetchColumn();
    return $email ? explode('@', $email)[0] : 'A user';
}

/**
 * Get students who are actively looking for housing (for discovery).
 * Excludes self, current friends, and people with pending requests.
 */
function discover_housemates(int $currentUserId, array $filters = []): array {
    $where  = "u.primary_role = 'student'
               AND u.status = 'active'
               AND s.looking_for_housing = 1
               AND u.id != ?";
    $params = [$currentUserId];

    // Exclude already-friends
    $where .= " AND u.id NOT IN (
                   SELECT CASE WHEN user_a = ? THEN user_b ELSE user_a END
                     FROM friends
                    WHERE user_a = ? OR user_b = ?
               )";
    $params[] = $currentUserId;
    $params[] = $currentUserId;
    $params[] = $currentUserId;

    // Exclude users with pending requests
    $where .= " AND u.id NOT IN (
                   SELECT receiver_id FROM friend_requests
                    WHERE requester_id = ? AND status = 'pending'
                   UNION
                   SELECT requester_id FROM friend_requests
                    WHERE receiver_id = ? AND status = 'pending'
               )";
    $params[] = $currentUserId;
    $params[] = $currentUserId;

    // Optional filter: city
    if (!empty($filters['city'])) {
        $where .= " AND s.housing_pref_city LIKE ?";
        $params[] = '%' . $filters['city'] . '%';
    }

    // Optional filter: max rent
    if (!empty($filters['max_rent']) && is_numeric($filters['max_rent'])) {
        $where .= " AND (s.housing_pref_max_rent IS NULL OR s.housing_pref_max_rent <= ?)";
        $params[] = (float)$filters['max_rent'];
    }

    $sql = "SELECT u.id AS user_id, u.email,
                   s.full_name, s.preferred_name, s.matric_no, s.university,
                   s.housing_pref_city, s.housing_pref_max_rent,
                   s.housing_pref_move_in, s.housing_bio
              FROM users u
              JOIN students s ON s.user_id = u.id
             WHERE $where
             ORDER BY s.preferred_name ASC
             LIMIT 30";

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Get my own housing discovery preferences.
 */
function get_my_housing_profile(int $userId): ?array {
    $stmt = db()->prepare("
        SELECT looking_for_housing, housing_pref_city,
               housing_pref_max_rent, housing_pref_move_in, housing_bio
          FROM students WHERE user_id = ? LIMIT 1
    ");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Update my housing discovery preferences.
 */
function update_housing_profile(int $userId, array $data): void {
    $stmt = db()->prepare("
        UPDATE students
           SET looking_for_housing  = ?,
               housing_pref_city    = ?,
               housing_pref_max_rent = ?,
               housing_pref_move_in = ?,
               housing_bio          = ?
         WHERE user_id = ?
    ");
    $stmt->execute([
        !empty($data['looking_for_housing']) ? 1 : 0,
        !empty($data['housing_pref_city']) ? trim($data['housing_pref_city']) : null,
        (isset($data['housing_pref_max_rent']) && is_numeric($data['housing_pref_max_rent']))
            ? (float)$data['housing_pref_max_rent']
            : null,
        !empty($data['housing_pref_move_in']) ? $data['housing_pref_move_in'] : null,
        !empty($data['housing_bio']) ? trim($data['housing_bio']) : null,
        $userId,
    ]);
}