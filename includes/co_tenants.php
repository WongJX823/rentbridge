<?php
require_once __DIR__ . '/auth.php';

/**
 * Get all co-tenants for a tenancy, ordered: primary first, then by sign_order.
 */
function get_co_tenants(int $tenancyId): array {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT *
          FROM co_tenants
         WHERE tenancy_id = ?
           AND status != 'removed'
         ORDER BY is_primary DESC, sign_order ASC, id ASC
    ");
    $stmt->execute([$tenancyId]);
    return $stmt->fetchAll();
}

/**
 * Count co-tenants on a tenancy.
 */
function count_co_tenants(int $tenancyId): int {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM co_tenants
         WHERE tenancy_id = ? AND status != 'removed'
    ");
    $stmt->execute([$tenancyId]);
    return (int)$stmt->fetchColumn();
}

/**
 * Add an additional co-tenant (not the primary).
 * Returns ['ok' => bool, 'id' => int|null, 'error' => string|null]
 */
function add_co_tenant(
    int $tenancyId,
    string $fullName,
    string $icNumber,
    ?string $phone,
    ?string $email,
    int $addedBy
): array {
    $fullName = trim($fullName);
    $icNumber = trim($icNumber);

    if ($fullName === '')         return ['ok' => false, 'id' => null, 'error' => 'Full name required'];
    if (strlen($fullName) > 150)  return ['ok' => false, 'id' => null, 'error' => 'Name too long'];
    if ($icNumber === '')         return ['ok' => false, 'id' => null, 'error' => 'IC number required'];

    // Basic IC format validation (Malaysian: 12 digits, optional dashes)
    $icClean = preg_replace('/[^0-9]/', '', $icNumber);
    if (strlen($icClean) !== 12) {
        return ['ok' => false, 'id' => null, 'error' => 'IC must be 12 digits (e.g. 030303-03-0303)'];
    }

    $pdo = db();

    // Find next sign_order
    $stmt = $pdo->prepare("
        SELECT COALESCE(MAX(sign_order), 0) + 1
          FROM co_tenants WHERE tenancy_id = ?
    ");
    $stmt->execute([$tenancyId]);
    $nextOrder = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        INSERT INTO co_tenants
            (tenancy_id, student_id, is_primary, full_name, ic_number, phone, email, sign_order, added_by)
        VALUES (?, NULL, 0, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $tenancyId,
        $fullName,
        $icNumber,
        $phone ?: null,
        $email ?: null,
        $nextOrder,
        $addedBy,
    ]);

    return ['ok' => true, 'id' => (int)$pdo->lastInsertId(), 'error' => null];
}

/**
 * Update primary tenant info (the leader student fills in their own NRIC at the form step).
 */
function update_primary_tenant(int $tenancyId, string $icNumber): array {
    $icClean = preg_replace('/[^0-9]/', '', $icNumber);
    if (strlen($icClean) !== 12) {
        return ['ok' => false, 'error' => 'IC must be 12 digits'];
    }

    $pdo = db();
    $stmt = $pdo->prepare("
        UPDATE co_tenants
           SET ic_number = ?
         WHERE tenancy_id = ? AND is_primary = 1
    ");
    $stmt->execute([$icNumber, $tenancyId]);
    return ['ok' => true, 'error' => null];
}

/**
 * Remove a co-tenant (soft delete).
 */
function remove_co_tenant(int $coTenantId): bool {
    $pdo = db();
    $stmt = $pdo->prepare("
        UPDATE co_tenants SET status = 'removed' WHERE id = ? AND is_primary = 0
    ");
    return $stmt->execute([$coTenantId]);
}