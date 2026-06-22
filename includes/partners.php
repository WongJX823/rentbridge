<?php
/**
 * Partner / co-tenancy system helpers.
 */

require_once __DIR__ . '/auth.php';

/**
 * Compute compatibility score between viewer (logged-in student) and a post.
 * Returns integer 0-100. Higher = better match.
 *
 * Algorithm (weights):
 *   - Same preferred city                        : 40
 *   - Budget overlap (poster's rent ≤ viewer max): 25
 *   - Same university                            : 15  (always true for UTeM-only platform)
 *   - Move-in date within 14 days                : 20
 *
 * If viewer has not set housing preferences, returns 50 (neutral).
 */
function compatibility_score(array $viewer, array $post): int {
    // If viewer has no preferences set, return neutral
    if (empty($viewer['looking_for_housing'])) return 50;

    $score = 0;

    // City match (40 points)
    if (!empty($viewer['housing_pref_city']) && !empty($post['property_city'])) {
        if (strcasecmp($viewer['housing_pref_city'], $post['property_city']) === 0) {
            $score += 40;
        }
    } else {
        // No city preference set — neutral partial credit
        $score += 15;
    }

    // Budget match (25 points)
    if (!empty($viewer['housing_pref_max_rent']) && !empty($post['property_rent'])) {
        // poster's monthly rent ÷ (housemates_needed + 1) = per-person cost
        $perPerson = (float)$post['property_rent'] / max(1, ((int)$post['housemates_needed'] + 1));
        if ($perPerson <= (float)$viewer['housing_pref_max_rent']) {
            $score += 25;
        } elseif ($perPerson <= (float)$viewer['housing_pref_max_rent'] * 1.2) {
            // Within 20% over budget — half credit
            $score += 12;
        }
    } else {
        $score += 10;
    }

    // University match (15 points) — always true for UTeM-only platform
    $score += 15;

    // Move-in date proximity (20 points)
    if (!empty($viewer['housing_pref_move_in']) && !empty($post['target_move_in'])) {
        $diff = abs(strtotime($post['target_move_in']) - strtotime($viewer['housing_pref_move_in']));
        $days = $diff / 86400;
        if ($days <= 14) {
            $score += 20;
        } elseif ($days <= 30) {
            $score += 12;
        } elseif ($days <= 60) {
            $score += 5;
        }
    } else {
        $score += 10;
    }

    return min(100, max(0, $score));
}

/**
 * Convert numeric score to readable label.
 * Returns ['label' => string, 'color' => bootstrap color, 'description' => string]
 */
function compatibility_label(int $score): array {
    if ($score >= 70) {
        return [
            'label'       => 'High match',
            'color'       => 'success',
            'description' => 'Strong fit based on your preferences',
        ];
    }
    if ($score >= 40) {
        return [
            'label'       => 'Medium match',
            'color'       => 'warning',
            'description' => 'Some preferences align',
        ];
    }
    return [
        'label'       => 'Low match',
        'color'       => 'secondary',
        'description' => 'Few preferences align',
    ];
}

/**
 * Get co-tenancy post by ID with all joined info.
 * Returns null if not found.
 */
function get_co_tenancy_post(int $postId): ?array {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT ctp.*,
               s.full_name      AS poster_name,
               s.preferred_name AS poster_nickname,
               s.matric_no      AS poster_matric,
               s.housing_bio    AS poster_bio,
               p.title          AS property_title,
               p.city           AS property_city,
               p.address        AS property_address,
               p.monthly_rent   AS property_rent,
               p.property_type  AS property_type,
               p.furnishing     AS property_furnishing,
               (SELECT image_path FROM property_images
                 WHERE property_id = p.id
                 ORDER BY is_primary DESC, id LIMIT 1) AS property_image
          FROM co_tenancy_posts ctp
          JOIN students s ON s.user_id = ctp.poster_id
          JOIN properties p ON p.id = ctp.property_id
         WHERE ctp.id = ?
         LIMIT 1
    ");
    $stmt->execute([$postId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Get all open posts with viewer context.
 * Returns array of posts sorted by score desc (or recency if no viewer prefs).
 */
function list_co_tenancy_posts(int $viewerId, array $filters = []): array {
    $pdo = db();

    // Load viewer's preferences for scoring
    $stmt = $pdo->prepare("
        SELECT looking_for_housing, housing_pref_city,
               housing_pref_max_rent, housing_pref_move_in
          FROM students WHERE user_id = ?
    ");
    $stmt->execute([$viewerId]);
    $viewer = $stmt->fetch() ?: [];

    // Base query
    $where = "ctp.status = 'open' AND ctp.poster_id != ?";
    $params = [$viewerId];

    if (!empty($filters['city'])) {
        $where .= " AND p.city = ?";
        $params[] = $filters['city'];
    }
    if (!empty($filters['max_rent'])) {
        $where .= " AND p.monthly_rent <= ?";
        $params[] = (float)$filters['max_rent'];
    }

    $stmt = $pdo->prepare("
        SELECT ctp.*,
               ctp.created_at AS post_created_at,
               s.full_name      AS poster_name,
               s.preferred_name AS poster_nickname,
               s.matric_no      AS poster_matric,
               s.housing_bio    AS poster_bio,
               s.housing_pref_move_in AS target_move_in,
               p.title          AS property_title,
               p.city           AS property_city,
               p.monthly_rent   AS property_rent,
               p.property_type  AS property_type,
               (SELECT image_path FROM property_images
                 WHERE property_id = p.id
                 ORDER BY is_primary DESC, id LIMIT 1) AS property_image
          FROM co_tenancy_posts ctp
          JOIN students s   ON s.user_id   = ctp.poster_id
          JOIN properties p ON p.id        = ctp.property_id
         WHERE $where
         ORDER BY ctp.created_at DESC
    ");
    $stmt->execute($params);
    $posts = $stmt->fetchAll();

    // Compute compatibility scores
    foreach ($posts as &$post) {
        $post['compatibility_score'] = compatibility_score($viewer, $post);
        $post['compatibility'] = compatibility_label($post['compatibility_score']);
    }
    unset($post);

    // Sort by score descending (if viewer has prefs)
    if (!empty($viewer['looking_for_housing'])) {
        usort($posts, fn($a, $b) => $b['compatibility_score'] - $a['compatibility_score']);
    }

    return $posts;
}

/**
 * Get my open posts (for the poster to manage).
 */
function get_my_co_tenancy_posts(int $userId): array {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT ctp.*,
               p.title       AS property_title,
               p.city        AS property_city,
               p.monthly_rent AS property_rent,
               (SELECT COUNT(*) FROM co_tenancy_applications cta
                 WHERE cta.post_id = ctp.id AND cta.status = 'pending') AS pending_count,
               (SELECT COUNT(*) FROM co_tenancy_applications cta
                 WHERE cta.post_id = ctp.id AND cta.status = 'accepted') AS accepted_count
          FROM co_tenancy_posts ctp
          JOIN properties p ON p.id = ctp.property_id
         WHERE ctp.poster_id = ?
         ORDER BY ctp.created_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

/**
 * Get all applications for a post (poster view).
 */
function get_post_applications(int $postId, int $posterId): array {
    $pdo = db();
    // Verify ownership
    $stmt = $pdo->prepare("SELECT id FROM co_tenancy_posts WHERE id = ? AND poster_id = ?");
    $stmt->execute([$postId, $posterId]);
    if (!$stmt->fetchColumn()) return [];

    $stmt = $pdo->prepare("
        SELECT cta.*,
               s.full_name      AS applicant_name,
               s.preferred_name AS applicant_nick,
               s.matric_no,
               s.housing_bio,
               s.housing_pref_city,
               s.housing_pref_max_rent
          FROM co_tenancy_applications cta
          JOIN students s ON s.user_id = cta.applicant_id
         WHERE cta.post_id = ?
         ORDER BY
           FIELD(cta.status, 'pending','accepted','rejected'),
           cta.created_at ASC
    ");
    $stmt->execute([$postId]);
    return $stmt->fetchAll();
}

/**
 * Get my application for a given post.
 */
function get_my_application(int $postId, int $applicantId): ?array {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT * FROM co_tenancy_applications
         WHERE post_id = ? AND applicant_id = ?
    ");
    $stmt->execute([$postId, $applicantId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Submit a housemate application. Returns [ok, error].
 */
function apply_to_co_tenancy_post(int $postId, int $applicantId, string $message): array {
    $pdo = db();

    // Verify post is open and not own post
    $stmt = $pdo->prepare("SELECT poster_id, housemates_needed, status FROM co_tenancy_posts WHERE id = ?");
    $stmt->execute([$postId]);
    $post = $stmt->fetch();

    if (!$post || $post['status'] !== 'open') {
        return [false, 'This post is no longer open.'];
    }
    if ((int)$post['poster_id'] === $applicantId) {
        return [false, 'You cannot apply to your own post.'];
    }

    // Check for duplicate
    $stmt = $pdo->prepare("SELECT id FROM co_tenancy_applications WHERE post_id = ? AND applicant_id = ?");
    $stmt->execute([$postId, $applicantId]);
    if ($stmt->fetchColumn()) {
        return [false, 'You have already applied to this post.'];
    }

    // Check capacity — don't allow more applications than needed * 3 (buffer)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM co_tenancy_applications WHERE post_id = ? AND status != 'rejected'");
    $stmt->execute([$postId]);
    if ((int)$stmt->fetchColumn() >= (int)$post['housemates_needed'] * 3) {
        return [false, 'This post has enough applicants already.'];
    }

    $stmt = $pdo->prepare("
        INSERT INTO co_tenancy_applications (post_id, applicant_id, message)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$postId, $applicantId, $message]);

    // Notify poster
    $stmt = $pdo->prepare("
        SELECT s.full_name, s.preferred_name FROM students s
         WHERE s.user_id = ?
    ");
    $stmt->execute([$applicantId]);
    $applicant = $stmt->fetch();
    $name = $applicant['preferred_name'] ?: $applicant['full_name'];

    if (function_exists('notify')) {
        $stmt = $pdo->prepare("SELECT poster_id FROM co_tenancy_posts WHERE id = ?");
        $stmt->execute([$postId]);
        $posterId = (int)$stmt->fetchColumn();
        notify(
            $posterId,
            'housemate_application',
            'New housemate application',
            "{$name} applied to join your housemate group.",
            "/rentbridge/student/manage_post.php?id={$postId}"
        );
    }

    return [true, null];
}

/**
 * Accept or reject an application. On accept, checks if post is now full → creates group chat.
 * Returns [ok, error, ?group_conversation_id]
 */
function respond_to_application(int $applicationId, int $posterId, string $decision): array {
    $pdo = db();

    // Load application + post in one query
    $stmt = $pdo->prepare("
        SELECT cta.*, ctp.poster_id, ctp.housemates_needed, ctp.property_id, ctp.status AS post_status,
               ctp.group_conversation_id
          FROM co_tenancy_applications cta
          JOIN co_tenancy_posts ctp ON ctp.id = cta.post_id
         WHERE cta.id = ? AND ctp.poster_id = ?
         LIMIT 1
    ");
    $stmt->execute([$applicationId, $posterId]);
    $app = $stmt->fetch();

    if (!$app) return [false, 'Application not found.', null];
    if ($app['post_status'] !== 'open') return [false, 'Post is no longer open.', null];
    if (!in_array($decision, ['accepted', 'rejected'], true)) return [false, 'Invalid decision.', null];

    $pdo->beginTransaction();
    try {
        // Update application status
        $pdo->prepare("
            UPDATE co_tenancy_applications
               SET status = ?, responded_at = NOW()
             WHERE id = ?
        ")->execute([$decision, $applicationId]);

        // Notify applicant
        $applicantId = (int)$app['applicant_id'];
        $postId = (int)$app['post_id'];

        if (function_exists('notify')) {
            if ($decision === 'accepted') {
                notify(
                    $applicantId,
                    'housemate_accepted',
                    'You were accepted!',
                    'Your housemate application was accepted. Check the group chat.',
                    "/rentbridge/student/housemate_post.php?id={$postId}"
                );
            } else {
                notify(
                    $applicantId,
                    'housemate_rejected',
                    'Application not accepted',
                    'Your housemate application was not accepted this time.',
                    "/rentbridge/student/partners.php"
                );
            }
        }

        $groupConvId = null;

        if ($decision === 'accepted') {
            // Count accepted applications
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM co_tenancy_applications
                 WHERE post_id = ? AND status = 'accepted'
            ");
            $stmt->execute([$postId]);
            $acceptedCount = (int)$stmt->fetchColumn();

            if ($acceptedCount >= (int)$app['housemates_needed']) {
                // Post is full — create group chat if not done yet
                if (empty($app['group_conversation_id'])) {
                    // Create a group conversation (user_b = NULL for group convos)
                    $pdo->prepare("
                        INSERT INTO conversations (user_a, user_b, context_type, property_id, created_at)
                        VALUES (?, NULL, 'housemate_group', ?, NOW())
                    ")->execute([$posterId, (int)$app['property_id']]);
                    $groupConvId = (int)$pdo->lastInsertId();

                    // Save on post
                    $pdo->prepare("
                        UPDATE co_tenancy_posts
                           SET group_conversation_id = ?, status = 'filled'
                         WHERE id = ?
                    ")->execute([$groupConvId, $postId]);

                    // Add poster as participant
                    $pdo->prepare("
                        INSERT IGNORE INTO conversation_participants (conversation_id, user_id)
                        VALUES (?, ?)
                    ")->execute([$groupConvId, $posterId]);

                    // Add all accepted applicants as participants
                    $stmt = $pdo->prepare("
                        SELECT applicant_id FROM co_tenancy_applications
                         WHERE post_id = ? AND status = 'accepted'
                    ");
                    $stmt->execute([$postId]);
                    $acceptedIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

                    $ins = $pdo->prepare("
                        INSERT IGNORE INTO conversation_participants (conversation_id, user_id)
                        VALUES (?, ?)
                    ");
                    foreach ($acceptedIds as $uid) {
                        $ins->execute([$groupConvId, (int)$uid]);

                        // Welcome message + notify each accepted member
                        if (function_exists('notify') && (int)$uid !== $posterId) {
                            notify(
                                (int)$uid,
                                'housemate_group_ready',
                                'Housemate group is ready!',
                                'Your group is full. A group chat has been created.',
                                "/rentbridge/chat/conversation.php?id={$groupConvId}"
                            );
                        }
                    }

                    // Welcome system message
                    $pdo->prepare("
                        INSERT INTO messages (conversation_id, sender_id, body, message_type, sent_at)
                        VALUES (?, ?, 'The housemate group is now full. Welcome everyone!', 'system_notice', NOW())
                    ")->execute([$groupConvId, $posterId]);

                    $pdo->prepare("
                        UPDATE conversations
                           SET last_message_at = NOW(),
                               last_message_preview = 'The housemate group is now full.',
                               last_sender_id = ?
                         WHERE id = ?
                    ")->execute([$posterId, $groupConvId]);

                } else {
                    $groupConvId = (int)$app['group_conversation_id'];
                    // Add the newly accepted applicant if post was already filled earlier
                    $pdo->prepare("
                        INSERT IGNORE INTO conversation_participants (conversation_id, user_id)
                        VALUES (?, ?)
                    ")->execute([$groupConvId, $applicantId]);
                }
            }
        }

        $pdo->commit();
        return [true, null, $groupConvId];

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return [false, $e->getMessage(), null];
    }
}

/**
 * Cancel a post (poster closes it manually).
 */
function cancel_co_tenancy_post(int $postId, int $posterId): bool {
    $pdo = db();
    $stmt = $pdo->prepare("
        UPDATE co_tenancy_posts SET status = 'cancelled'
         WHERE id = ? AND poster_id = ? AND status = 'open'
    ");
    $stmt->execute([$postId, $posterId]);
    return $stmt->rowCount() > 0;
}

/**
 * Check if a user is a participant in a group conversation.
 */
function is_group_participant(int $conversationId, int $userId): bool {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT 1 FROM conversation_participants
         WHERE conversation_id = ? AND user_id = ?
    ");
    $stmt->execute([$conversationId, $userId]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Get all participants of a group conversation.
 */
function get_group_participants(int $conversationId): array {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT cp.user_id, s.full_name, s.preferred_name, s.matric_no
          FROM conversation_participants cp
          JOIN students s ON s.user_id = cp.user_id
         WHERE cp.conversation_id = ?
         ORDER BY cp.joined_at ASC
    ");
    $stmt->execute([$conversationId]);
    return $stmt->fetchAll();
}