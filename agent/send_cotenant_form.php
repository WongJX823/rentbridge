<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/chat.php';
require_role('agent');

$tenancyId = (int)($_POST['tenancy_id'] ?? 0);
if ($tenancyId <= 0) {
    die('Invalid tenancy.');
}

verify_csrf();

$pdo = db();
$userId = current_user_id();

// Verify agent is assigned to this tenancy
$stmt = $pdo->prepare("
    SELECT b.id, b.student_id, b.agent_id, b.property_id,
           p.title AS property_title
      FROM tenancies b
      JOIN properties p ON p.id = b.property_id
     WHERE b.id = ? AND b.agent_id = ? LIMIT 1
");
$stmt->execute([$tenancyId, $userId]);
$tenancy = $stmt->fetch();

if (!$tenancy) {
    die('Tenancy not found or you are not the assigned agent.');
}

// Find or create conversation between agent and student
$convoId = find_or_create_conversation(
    $userId,
    (int)$tenancy['student_id'],
    'agent_case',
    null,
    $tenancyId
);

// Send the special message
$metadata = json_encode([
    'tenancy_id'     => $tenancyId,
    'property_title' => $tenancy['property_title'],
]);

$stmt = $pdo->prepare("
    INSERT INTO messages (conversation_id, sender_id, body, message_type, metadata)
    VALUES (?, ?, ?, 'co_tenant_form', ?)
");
$stmt->execute([
    $convoId,
    $userId,
    "📋 Co-tenant details requested\nPlease fill in the names and IC numbers of everyone who will rent this property with you.",
    $metadata,
]);

// Notify student
notify(
    (int)$tenancy['student_id'],
    'cotenant_form_request',
    'Agent requested co-tenant details',
    'Please open the chat to fill in co-tenant info for "' . $tenancy['property_title'] . '".',
    '/rentbridge/chat.php?id=' . $convoId
);

set_flash('success', 'Co-tenant form sent to student via chat.');
header('Location: /rentbridge/agent/case.php?id=' . $tenancyId);
exit;