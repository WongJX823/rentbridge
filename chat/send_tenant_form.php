<?php
ini_set('error_log', __DIR__ . '/../debug.log');
ini_set('log_errors', '1');
ini_set('display_errors', '0');
require_once __DIR__ . '/../includes/auth.php';
require_role('agent');

header('Content-Type: application/json');

verify_csrf();

$pdo = db();
$agentId = current_user_id();

$convId     = (int)($_POST['conversation_id'] ?? 0);
$propertyId = (int)($_POST['property_id'] ?? 0);
$studentId  = (int)($_POST['student_id'] ?? 0);

$recipientRole = $_POST['recipient_role'] ?? 'landlord';  // default to landlord for backwards-compat
if (!in_array($recipientRole, ['landlord', 'student'], true)) {
    $recipientRole = 'landlord';
}

if ($convId <= 0 || $propertyId <= 0 || $studentId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Missing parameters']);
    exit;
}

// Verify: agent IS the assigned agent for this property
$stmt = $pdo->prepare("
    SELECT p.id, p.title, p.landlord_id, p.assigned_agent_id, p.monthly_rent, p.deposit
      FROM properties p
     WHERE p.id = ? AND p.assigned_agent_id = ?
");
$stmt->execute([$propertyId, $agentId]);
$prop = $stmt->fetch();

if (!$prop) {
    echo json_encode(['ok' => false, 'error' => 'You are not the assigned agent for this property']);
    exit;
}

$landlordId = (int)$prop['landlord_id'];

// Verify conversation is landlord ↔ agent
$low  = min($landlordId, $agentId);
$high = max($landlordId, $agentId);
$stmt = $pdo->prepare("
    SELECT id FROM conversations
     WHERE id = ? AND user_a = ? AND user_b = ?
");
$stmt->execute([$convId, $low, $high]);
if (!$stmt->fetchColumn()) {
    echo json_encode(['ok' => false, 'error' => 'Invalid conversation']);
    exit;
}

// Block only if there's an UNANSWERED form for this student
$stmt = $pdo->prepare("
    SELECT m.id
      FROM messages m
     WHERE m.conversation_id = ? 
       AND m.message_type = 'tenant_info_form'
       AND JSON_EXTRACT(m.metadata, '$.student_id') = ?
       AND NOT EXISTS (
           SELECT 1 FROM messages r
            WHERE r.conversation_id = m.conversation_id
              AND r.message_type = 'tenant_info_response'
              AND JSON_EXTRACT(r.metadata, '$.source_form_id') = m.id
       )
     ORDER BY m.id DESC
     LIMIT 1
");
$stmt->execute([$convId, $studentId]);
$pendingForm = $stmt->fetchColumn();

if ($pendingForm) {
    echo json_encode([
        'ok' => false,
        'error' => 'An unanswered form (#' . $pendingForm . ') is already pending. Wait for landlord to fill it.'
    ]);
    exit;
}
try {
    $pdo->beginTransaction();

    // Pre-fetch student info for pre-fill
    $stmt = $pdo->prepare("
        SELECT s.full_name, s.preferred_name, s.matric_no, s.phone, u.email
          FROM students s
          JOIN users u ON u.id = s.user_id
         WHERE s.user_id = ?
    ");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch() ?: [];

    $payload = json_encode([
        'property_id'    => $propertyId,
        'property_title' => $prop['title'],
        'student_id'     => $studentId,
        'recipient_role' => $recipientRole,  // ← NEW
        'prefill' => [
            'tenant_name'  => $student['full_name'] ?? '',
            'tenant_phone' => $student['phone'] ?? '',
            'tenant_email' => $student['email'] ?? '',
            'matric_no'    => $student['matric_no'] ?? '',
            'monthly_rent' => $prop['monthly_rent'],
            'deposit'      => $prop['deposit'],
        ],
    ]);
    $bodyText = 'Tenant info form sent for property "' . $prop['title'] . '"';

error_log('[send_tenant_form] payload=' . var_export($payload, true));
error_log('[send_tenant_form] is_string=' . (is_string($payload) ? 'yes' : 'no'));
error_log('[send_tenant_form] strlen=' . strlen($payload));
error_log('[send_tenant_form] json_valid=' . (json_decode($payload) !== null ? 'yes' : 'no'));

try {
    $stmt = $pdo->prepare("
        INSERT INTO messages 
            (conversation_id, sender_id, body, message_type, metadata, sent_at)
        VALUES (?, ?, ?, 'tenant_info_form', ?, NOW())
    ");
    $result = $stmt->execute([$convId, $agentId, $bodyText, $payload]);
    $newId = $pdo->lastInsertId();
    error_log('[send_tenant_form] execute returned: ' . var_export($result, true));
    error_log('[send_tenant_form] inserted message id: ' . $newId);

    // Immediately read back to see what was stored
    $check = $pdo->prepare("SELECT metadata FROM messages WHERE id = ?");
    $check->execute([$newId]);
    $stored = $check->fetchColumn();
    error_log('[send_tenant_form] readback metadata: ' . var_export($stored, true));
} catch (Throwable $e) {
    error_log('[send_tenant_form] INSERT ERROR: ' . $e->getMessage());
    throw $e;
}
    // Update conversation preview
    $stmt = $pdo->prepare("
        UPDATE conversations
           SET last_message_at = NOW(),
               last_message_preview = ?,
               last_sender_id = ?
         WHERE id = ?
    ");
    $stmt->execute([substr($bodyText, 0, 120), $agentId, $convId]);

    // Notify landlord
if (function_exists('notify')) {
    $notifyUserId = ($recipientRole === 'student') ? $studentId : $landlordId;
    notify(
        $notifyUserId,
        'tenant_info_form_received',
        'Tenant info form to fill',
        'Your agent sent a form to collect tenant details for "' . $prop['title'] . '"',
        "/rentbridge/chat/conversation.php?id={$convId}"
    );
}   

    $pdo->commit();
    echo json_encode([
        'ok' => true,
        'message' => 'Form sent. They will fill in tenant details.',
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}