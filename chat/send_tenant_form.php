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

// Verify the conversation belongs to the correct pair for the recipient role
$otherParty = ($recipientRole === 'student') ? $studentId : $landlordId;
$low  = min($agentId, $otherParty);
$high = max($agentId, $otherParty);
$stmt = $pdo->prepare("
    SELECT id FROM conversations
     WHERE id = ? AND user_a = ? AND user_b = ?
");
$stmt->execute([$convId, $low, $high]);
if (!$stmt->fetchColumn()) {
    echo json_encode(['ok' => false, 'error' => 'Invalid conversation']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Cancel any existing pending (unanswered, not already cancelled) forms for this student
    $stmt = $pdo->prepare("
        SELECT id, metadata FROM messages
         WHERE conversation_id = ?
           AND message_type = 'tenant_info_form'
           AND JSON_EXTRACT(metadata, '$.student_id') = ?
           AND (JSON_EXTRACT(metadata, '$.cancelled') IS NULL OR JSON_EXTRACT(metadata, '$.cancelled') = false)
           AND NOT EXISTS (
               SELECT 1 FROM messages r
                WHERE r.conversation_id = messages.conversation_id
                  AND r.message_type = 'tenant_info_response'
                  AND JSON_EXTRACT(r.metadata, '$.source_form_id') = messages.id
           )
    ");
    $stmt->execute([$convId, $studentId]);
    foreach ($stmt->fetchAll() as $old) {
        $oldMeta = json_decode($old['metadata'], true) ?? [];
        $oldMeta['cancelled'] = true;
        $pdo->prepare("UPDATE messages SET metadata = ? WHERE id = ?")
            ->execute([json_encode($oldMeta), $old['id']]);
    }

    // Pre-fetch student info for pre-fill
    $stmt = $pdo->prepare("
        SELECT s.full_name, s.preferred_name, s.matric_no, s.phone, u.email
          FROM students s
          JOIN users u ON u.id = s.user_id
         WHERE s.user_id = ?
    ");
    $stmt->execute([$studentId]);
    $student = $stmt->fetch() ?: [];

    // Agent-set tenancy terms (only used when sending to student)
    $agentTerms = [];
    if ($recipientRole === 'student') {
        $monthlyRent = (float)($_POST['monthly_rent'] ?? $prop['monthly_rent']);
        $deposit     = (float)($_POST['deposit']      ?? $prop['deposit']);
        $termMonths  = (int)($_POST['term_months']    ?? 12);
        $startDate   = trim($_POST['start_date']      ?? '');
        $notes       = trim($_POST['notes']           ?? '');

        if ($monthlyRent <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Monthly rent must be greater than 0.']);
            exit;
        }
        if ($startDate === '' || !strtotime($startDate)) {
            echo json_encode(['ok' => false, 'error' => 'A valid start date is required.']);
            exit;
        }

        $startDt = new DateTime($startDate);
        $endDt   = (clone $startDt)->modify('+' . $termMonths . ' months');
        $agentTerms = [
            'monthly_rent' => $monthlyRent,
            'deposit'      => $deposit,
            'term_months'  => $termMonths,
            'start_date'   => $startDate,
            'end_date'     => $endDt->format('Y-m-d'),
            'notes'        => $notes,
        ];
    }

    $payload = json_encode([
        'property_id'    => $propertyId,
        'property_title' => $prop['title'],
        'student_id'     => $studentId,
        'recipient_role' => $recipientRole,
        'terms'          => $agentTerms,
        'prefill' => [
            'tenant_name'  => $student['full_name'] ?? '',
            'tenant_phone' => $student['phone'] ?? '',
            'tenant_email' => $student['email'] ?? '',
            'matric_no'    => $student['matric_no'] ?? '',
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