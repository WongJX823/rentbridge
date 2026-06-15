<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/saved.php';
require_login();

header('Content-Type: application/json');

verify_csrf();

$propertyId = (int)($_POST['property_id'] ?? 0);
$action     = $_POST['action'] ?? 'toggle';

if ($propertyId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Invalid property']);
    exit;
}

$userId = current_user_id();
$pdo = db();

// Verify property exists
$stmt = $pdo->prepare("SELECT 1 FROM properties WHERE id = ? LIMIT 1");
$stmt->execute([$propertyId]);
if (!$stmt->fetchColumn()) {
    echo json_encode(['ok' => false, 'error' => 'Property not found']);
    exit;
}

if ($action === 'save') {
    $result = save_property($userId, $propertyId);
} elseif ($action === 'unsave') {
    $result = unsave_property($userId, $propertyId);
} else {
    // toggle
    if (is_property_saved($userId, $propertyId)) {
        $result = unsave_property($userId, $propertyId);
    } else {
        $result = save_property($userId, $propertyId);
    }
}

echo json_encode($result);