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