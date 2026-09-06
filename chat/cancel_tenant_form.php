<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('agent');

header('Content-Type: application/json');
verify_csrf();

$pdo     = db();
$agentId = current_user_id();
$formId  = (int)($_POST['form_id'] ?? 0);
$convId  = (int)($_POST['conversation_id'] ?? 0);

if ($formId <= 0 || $convId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Missing parameters']);
    exit;
}

$stmt = $pdo->prepare("
    SELECT id, metadata FROM messages
     WHERE id = ? AND conversation_id = ? AND message_type = 'tenant_info_form' AND sender_id = ?
");
$stmt->execute([$formId, $convId, $agentId]);
$row = $stmt->fetch();

if (!$row) {
    echo json_encode(['ok' => false, 'error' => 'Form not found']);
    exit;
}

$meta = json_decode($row['metadata'], true) ?? [];
if (empty($meta['cancelled'])) {
    $meta['cancelled'] = true;
    $pdo->prepare("UPDATE messages SET metadata = ? WHERE id = ?")
        ->execute([json_encode($meta), $formId]);

    $body = 'Agent cancelled the tenant info form request.';
    $pdo->prepare("
        INSERT INTO messages (conversation_id, sender_id, body, message_type, sent_at)
        VALUES (?, ?, ?, 'system_notice', NOW())
    ")->execute([$convId, $agentId, $body]);
    $pdo->prepare("
        UPDATE conversations SET last_message_at = NOW(), last_message_preview = ?, last_sender_id = ?
         WHERE id = ?
    ")->execute([substr($body, 0, 120), $agentId, $convId]);
}

echo json_encode(['ok' => true]);
