<?php
require_once __DIR__ . '/auth.php';

/**
 * Check if a user has saved a property.
 */
function is_property_saved(int $userId, int $propertyId): bool {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT 1 FROM saved_properties
         WHERE user_id = ? AND property_id = ? LIMIT 1
    ");
    $stmt->execute([$userId, $propertyId]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Get all saved property IDs for a user (efficient batch check).
 * Returns: [propertyId => true, ...]
 */
function get_saved_property_ids(int $userId, array $propertyIds = []): array {
    if (empty($propertyIds)) {
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT property_id FROM saved_properties WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        return array_flip(array_column($stmt->fetchAll(), 'property_id'));
    }

    $placeholders = implode(',', array_fill(0, count($propertyIds), '?'));
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT property_id FROM saved_properties
         WHERE user_id = ? AND property_id IN ($placeholders)
    ");
    $stmt->execute(array_merge([$userId], $propertyIds));
    return array_flip(array_column($stmt->fetchAll(), 'property_id'));
}

/**
 * Save a property for a user. Returns ['ok' => bool, 'saved' => bool].
 */
function save_property(int $userId, int $propertyId): array {
    $pdo = db();
    try {
        $stmt = $pdo->prepare("
            INSERT IGNORE INTO saved_properties (user_id, property_id)
            VALUES (?, ?)
        ");
        $stmt->execute([$userId, $propertyId]);
        return ['ok' => true, 'saved' => true];
    } catch (Throwable $e) {
        return ['ok' => false, 'saved' => false];
    }
}

/**
 * Unsave a property.
 */
function unsave_property(int $userId, int $propertyId): array {
    $pdo = db();
    try {
        $stmt = $pdo->prepare("
            DELETE FROM saved_properties WHERE user_id = ? AND property_id = ?
        ");
        $stmt->execute([$userId, $propertyId]);
        return ['ok' => true, 'saved' => false];
    } catch (Throwable $e) {
        return ['ok' => false, 'saved' => true];
    }
}

/**
 * List saved properties with full details for display.
 */
function list_saved_properties(int $userId): array {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT p.id, p.title, p.city, p.state, p.monthly_rent, p.property_type,
               p.furnishing, p.status,
               sp.saved_at,
               (SELECT image_path FROM property_images
                 WHERE property_id = p.id
                 ORDER BY is_primary DESC, id LIMIT 1) AS image_path
          FROM saved_properties sp
          JOIN properties p ON p.id = sp.property_id
         WHERE sp.user_id = ?
         ORDER BY sp.saved_at DESC
    ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}