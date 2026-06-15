<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/verification.php';
require_login();

header('Content-Type: application/json');

verify_csrf();

$userId = current_user_id();
$code = trim($_POST['code'] ?? '');
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

// Basic validation
if ($code === '' || $newPassword === '' || $confirmPassword === '') {
    echo json_encode(['ok' => false, 'error' => 'All fields are required.']);
    exit;
}

if ($newPassword !== $confirmPassword) {
    echo json_encode(['ok' => false, 'error' => 'Passwords do not match.']);
    exit;
}

if (strlen($newPassword) < 8) {
    echo json_encode(['ok' => false, 'error' => 'Password must be at least 8 characters.']);
    exit;
}

if (!preg_match('/[A-Za-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
    echo json_encode(['ok' => false, 'error' => 'Password must contain letters and numbers.']);
    exit;
}

// Verify code
$verifyResult = verify_code($userId, 'password_change', $code);
if (!$verifyResult['ok']) {
    echo json_encode(['ok' => false, 'error' => $verifyResult['error']]);
    exit;
}

// Update password
$pdo = db();
try {
    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
    $stmt->execute([$hash, $userId]);

    echo json_encode([
        'ok' => true,
        'message' => 'Password changed successfully.',
    ]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'Failed to update password: ' . $e->getMessage()]);
}