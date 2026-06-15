<?php
require_once __DIR__ . '/auth.php';

/**
 * Generate and store a verification code for a user.
 * Returns the 6-digit code (string).
 */
function create_verification_code(int $userId, string $purpose, int $validMinutes = 10): string {
    $pdo = db();
    $code = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

    // Invalidate any unused codes for this user+purpose (prevent stockpiling)
    $stmt = $pdo->prepare("
        UPDATE verification_codes
           SET used_at = NOW()
         WHERE user_id = ? AND purpose = ? AND used_at IS NULL
    ");
    $stmt->execute([$userId, $purpose]);

    // Insert new code
    $stmt = $pdo->prepare("
        INSERT INTO verification_codes (user_id, purpose, code, expires_at, ip_address)
        VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL ? MINUTE), ?)
    ");
    $stmt->execute([$userId, $purpose, $code, $validMinutes, $_SERVER['REMOTE_ADDR'] ?? null]);

    return $code;
}

/**
 * Verify a code submitted by user. Returns ['ok' => bool, 'error' => string|null].
 * If ok, marks the code as used.
 */
function verify_code(int $userId, string $purpose, string $submittedCode): array {
    $pdo = db();
    $submittedCode = preg_replace('/\s+/', '', $submittedCode);

    if (!preg_match('/^\d{6}$/', $submittedCode)) {
        return ['ok' => false, 'error' => 'Code must be 6 digits'];
    }

    // Find latest valid code
    $stmt = $pdo->prepare("
        SELECT id, code, expires_at, attempts
          FROM verification_codes
         WHERE user_id = ? AND purpose = ? AND used_at IS NULL
         ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$userId, $purpose]);
    $row = $stmt->fetch();

    if (!$row) {
        return ['ok' => false, 'error' => 'No verification code requested. Please request a new one.'];
    }

    if (strtotime($row['expires_at']) < time()) {
        return ['ok' => false, 'error' => 'Code expired. Please request a new one.'];
    }

    if ((int)$row['attempts'] >= 5) {
        return ['ok' => false, 'error' => 'Too many attempts. Please request a new code.'];
    }

    // Increment attempts
    $stmt = $pdo->prepare("UPDATE verification_codes SET attempts = attempts + 1 WHERE id = ?");
    $stmt->execute([$row['id']]);

    if (!hash_equals($row['code'], $submittedCode)) {
        return ['ok' => false, 'error' => 'Incorrect code. Please try again.'];
    }

    // Mark as used
    $stmt = $pdo->prepare("UPDATE verification_codes SET used_at = NOW() WHERE id = ?");
    $stmt->execute([$row['id']]);

    return ['ok' => true, 'error' => null];
}