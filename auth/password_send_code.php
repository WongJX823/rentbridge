<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/verification.php';
require_once __DIR__ . '/../includes/mailer.php';
require_login();

header('Content-Type: application/json');

verify_csrf();

$userId = current_user_id();
$pdo = db();

// Rate limit: don't send more than 1 code per 60 seconds
$stmt = $pdo->prepare("
    SELECT created_at FROM verification_codes
     WHERE user_id = ? AND purpose = 'password_change'
     ORDER BY id DESC LIMIT 1
");
$stmt->execute([$userId]);
$lastCode = $stmt->fetch();

if ($lastCode && (time() - strtotime($lastCode['created_at'])) < 60) {
    $waitSeconds = 60 - (time() - strtotime($lastCode['created_at']));
    echo json_encode([
        'ok' => false,
        'error' => "Please wait {$waitSeconds}s before requesting another code.",
    ]);
    exit;
}

// Fetch user email + name
$stmt = $pdo->prepare("
    SELECT u.email, u.primary_role,
           COALESCE(s.full_name, l.full_name, a.full_name, u.email) AS display_name
      FROM users u
      LEFT JOIN students s  ON s.user_id  = u.id
      LEFT JOIN landlords l ON l.user_id  = u.id
      LEFT JOIN agents a    ON a.user_id  = u.id
     WHERE u.id = ?
");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    echo json_encode(['ok' => false, 'error' => 'User not found.']);
    exit;
}

// Generate code
$code = create_verification_code($userId, 'password_change', 10);

// Send email
$result = send_verification_code_email(
    $user['email'],
    $user['display_name'],
    $code,
    'password change'
);

if (!$result['ok']) {
    error_log('[password_send_code] Mail failed: ' . $result['error']);
    echo json_encode([
        'ok' => false,
        'error' => 'Failed to send email. Please try again or contact support.',
    ]);
    exit;
}

echo json_encode([
    'ok' => true,
    'message' => 'Verification code sent to ' . preg_replace('/(.{2}).*(@.*)/', '$1***$2', $user['email']),
]);