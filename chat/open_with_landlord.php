<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/chat.php';
require_role('agent');

$propertyId = (int)($_GET['property_id'] ?? 0);
if ($propertyId <= 0) {
    set_flash('danger', 'Invalid property.');
    header('Location: /rentbridge/chat.php');
    exit;
}

$pdo = db();
$stmt = $pdo->prepare("SELECT id, landlord_id, title FROM properties WHERE id = ? LIMIT 1");
$stmt->execute([$propertyId]);
$prop = $stmt->fetch();

if (!$prop || empty($prop['landlord_id'])) {
    set_flash('danger', 'Property or landlord not found.');
    header('Location: /rentbridge/chat.php');
    exit;
}

$landlordId = (int)$prop['landlord_id'];
$agentId    = current_user_id();

if ($landlordId === $agentId) {
    set_flash('danger', 'You cannot chat with yourself.');
    header('Location: /rentbridge/chat.php');
    exit;
}

$convId = find_or_create_conversation($agentId, $landlordId, 'agent_case', $propertyId);

header('Location: /rentbridge/chat/conversation.php?id=' . $convId);
exit;
