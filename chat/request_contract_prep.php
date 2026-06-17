<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('landlord');

header('Content-Type: application/json');

verify_csrf();

$pdo = db();
$landlordId = current_user_id();

$convId     = (int)($_POST['conversation_id'] ?? 0);
$propertyId = (int)($_POST['property_id'] ?? 0);
$studentId  = (int)($_POST['student_id'] ?? 0);

if ($convId <= 0 || $propertyId <= 0 || $studentId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Missing required parameters']);
    exit;
}

// Verify the landlord owns this property + property has approved agent
$stmt = $pdo->prepare("
    SELECT p.id, p.title, p.landlord_id, p.assigned_agent_id, p.agent_status
      FROM properties p
     WHERE p.id = ? AND p.landlord_id = ?
");
$stmt->execute([$propertyId, $landlordId]);
$prop = $stmt->fetch();

if (!$prop) {
    echo json_encode(['ok' => false, 'error' => 'Property not found or not yours']);
    exit;
}

if (empty($prop['assigned_agent_id']) || $prop['agent_status'] !== 'accepted') {
    echo json_encode([
        'ok' => false,
        'error' => 'No verified agent assigned to this property yet. Please wait for agent approval.'
    ]);
    exit;
}

$agentId = (int)$prop['assigned_agent_id'];

// Verify the source conversation is landlord ↔ student
$lowSL  = min($landlordId, $studentId);
$highSL = max($landlordId, $studentId);
$stmt = $pdo->prepare("
    SELECT id FROM conversations
     WHERE id = ? AND user_a = ? AND user_b = ?
");
$stmt->execute([$convId, $lowSL, $highSL]);
if (!$stmt->fetchColumn()) {
    echo json_encode(['ok' => false, 'error' => 'Invalid source conversation']);
    exit;
}

// Find or create the landlord ↔ agent conversation
$lowLA  = min($landlordId, $agentId);
$highLA = max($landlordId, $agentId);

$stmt = $pdo->prepare("
    SELECT id FROM conversations
     WHERE context_type = 'contract_prep'
       AND property_id = ?
       AND user_a = ?
       AND user_b = ?
     LIMIT 1
");
$stmt->execute([$propertyId, $lowLA, $highLA]);
$landlordAgentConvId = (int)$stmt->fetchColumn();

if (!$landlordAgentConvId) {
    $stmt = $pdo->prepare("
        INSERT INTO conversations 
            (user_a, user_b, property_id, context_type, created_at)
        VALUES (?, ?, ?, 'contract_prep', NOW())
    ");
    $stmt->execute([$lowLA, $highLA, $propertyId]);
    $landlordAgentConvId = (int)$pdo->lastInsertId();
}

try {
    $pdo->beginTransaction();

    // Post the structured request message in landlord ↔ agent chat
    $payload = json_encode([
        'property_id'            => $propertyId,
        'property_title'         => $prop['title'],
        'student_id'             => $studentId,
        'source_conversation_id' => $convId,
    ]);

    $bodyText = 'Contract preparation requested for property "' . $prop['title'] . '"';

    $stmt = $pdo->prepare("
        INSERT INTO messages 
            (conversation_id, sender_id, body, message_type, metadata, sent_at)
        VALUES (?, ?, ?, 'contract_prep_request', ?, NOW())
    ");
    $stmt->execute([$landlordAgentConvId, $landlordId, $bodyText, $payload]);

    // Update landlord-agent conversation preview
    $stmt = $pdo->prepare("
        UPDATE conversations
           SET last_message_at = NOW(),
               last_message_preview = ?,
               last_sender_id = ?
         WHERE id = ?
    ");
    $stmt->execute([substr($bodyText, 0, 120), $landlordId, $landlordAgentConvId]);

    // Post acknowledgment in landlord ↔ student chat
    $sysBody = 'Contract preparation has been requested. Your agent will follow up to collect the tenant details.';
    $stmt = $pdo->prepare("
        INSERT INTO messages 
            (conversation_id, sender_id, body, message_type, sent_at)
        VALUES (?, ?, ?, 'system_notice', NOW())
    ");
    $stmt->execute([$convId, $landlordId, $sysBody]);

    // Update student-landlord conversation preview
    $stmt = $pdo->prepare("
        UPDATE conversations
           SET last_message_at = NOW(),
               last_message_preview = ?,
               last_sender_id = ?
         WHERE id = ?
    ");
    $stmt->execute([substr($sysBody, 0, 120), $landlordId, $convId]);

    // Notify agent
    if (function_exists('notify')) {
        notify(
            $agentId,
            'contract_prep_request',
            'Contract preparation requested',
            'Landlord requested contract prep for "' . $prop['title'] . '"',
            "/rentbridge/chat/conversation.php?id={$landlordAgentConvId}"
        );
    }

    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'message' => 'Agent notified. They will send you a form to fill in tenant details.',
        'agent_conversation_id' => $landlordAgentConvId,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}