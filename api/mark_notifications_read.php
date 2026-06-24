<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

$userId = current_user_id();
db()->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")
   ->execute([$userId]);

$redirect = $_POST['redirect'] ?? '';
if (!$redirect || !str_starts_with($redirect, '/')) {
    $redirect = '/rentbridge/index.php';
}
header('Location: ' . $redirect);
exit;
