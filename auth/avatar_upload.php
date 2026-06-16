<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/avatar.php';
require_login();

verify_csrf();

$userId = current_user_id();
$role = current_role();

if (!in_array($role, ['student', 'landlord', 'agent'], true)) {
    set_flash('danger', 'Avatar upload not supported for your role.');
    header('Location: /rentbridge/');
    exit;
}

if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] === UPLOAD_ERR_NO_FILE) {
    set_flash('warning', 'No file selected.');
    header('Location: /rentbridge/' . $role . '/profile.php');
    exit;
}

$result = save_avatar($_FILES['avatar'], $userId, $role);

if ($result['ok']) {
    set_flash('success', 'Profile photo updated.');
} else {
    set_flash('danger', 'Upload failed: ' . $result['error']);
}

header('Location: /rentbridge/' . $role . '/profile.php');
exit;