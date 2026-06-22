<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

header('Content-Type: application/json');
verify_csrf();

$pdo = db();
$userId = current_user_id();
$role   = current_role();

if ($role !== 'agent') {
    echo json_encode(['ok' => false, 'error' => 'Only agents can send inspection schedule requests.']);
    exit;
}

$convId     = (int)($_POST['conversation_id'] ?? 0);
$propertyId = (int)($_POST['property_id'] ?? 0);
$slots      = trim($_POST['slots'] ?? '');
$note       = trim($_POST['note'] ?? '');

if ($convId <= 0 || $propertyId <= 0 || $slots === '') {
    echo json_encode(['ok' => false, 'error' => 'Missing required fields.']);
    exit;
}

// Verify agent is assigned to this property
$stmt = $pdo->prepare("
    SELECT p.id, p.title, p.landlord_id, p.agent_status, p.assigned_agent_id
      FROM properties p
     WHERE p.id = ? AND p.assigned_agent_id = ? AND p.agent_status = 'inspecting'
");
$stmt->execute([$propertyId, $userId]);
$prop = $stmt->fetch();

if (!$prop) {
    echo json_encode(['ok' => false, 'error' => 'Property not found or you are not the assigned agent in inspection phase.']);
    exit;
}

// Verify the conversation belongs to this agent↔landlord pair
$landlordId = (int)$prop['landlord_id'];
$lo = min($userId, $landlordId);
$hi = max($userId, $landlordId);
$stmt = $pdo->prepare("
    SELECT id FROM conversations
     WHERE id = ? AND user_a = ? AND user_b = ? AND (property_id <=> ?)
");
$stmt->execute([$convId, $lo, $hi, $propertyId]);
if (!$stmt->fetchColumn()) {
    echo json_encode(['ok' => false, 'error' => 'Invalid conversation.']);
    exit;
}

// Block if there's already an unanswered schedule request
$stmt = $pdo->prepare("
    SELECT m.id FROM messages m
     WHERE m.conversation_id = ?
       AND m.message_type = 'inspection_schedule_request'
       AND NOT EXISTS (
           SELECT 1 FROM messages r
            WHERE r.conversation_id = m.conversation_id
              AND r.message_type IN ('inspection_schedule_response', 'inspection_schedule_reschedule')
              AND r.sent_at > m.sent_at
       )
     ORDER BY m.id DESC LIMIT 1
");
$stmt->execute([$convId]);
if ($stmt->fetchColumn()) {
    echo json_encode(['ok' => false, 'error' => 'An unanswered schedule request is already pending. Wait for the landlord to respond.']);
    exit;
}

$payload = json_encode([
    'property_id'    => $propertyId,
    'property_title' => $prop['title'],
    'landlord_id'    => $landlordId,
    'slots'          => $slots,
    'note'           => $note,
]);

$bodyText = 'Inspection schedule request for "' . $prop['title'] . '"';

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO messages (conversation_id, sender_id, body, message_type, metadata, sent_at)
        VALUES (?, ?, ?, 'inspection_schedule_request', ?, NOW())
    ");
    $stmt->execute([$convId, $userId, $bodyText, $payload]);

    $pdo->prepare("UPDATE conversations SET last_message_at = NOW(), last_message_preview = ?, last_sender_id = ? WHERE id = ?")
        ->execute([substr($bodyText, 0, 120), $userId, $convId]);

    // Notify landlord
    if (function_exists('notify')) {
        notify(
            $landlordId,
            'inspection_schedule_request',
            'Agent proposed inspection times',
            "The agent proposed inspection time(s) for your property \"{$prop['title']}\". Please confirm a slot.",
            "/rentbridge/chat/conversation.php?id={$convId}"
        );
    }

    $pdo->commit();
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
