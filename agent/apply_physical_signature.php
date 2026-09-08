<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/contracts.php';
require_role('agent');

header('Content-Type: application/json');
verify_csrf();

$agentId    = current_user_id();
$tenancyId  = (int)($_POST['tenancy_id'] ?? 0);
$target     = trim($_POST['target'] ?? '');
$signature  = trim($_POST['signature_data'] ?? '');

if ($tenancyId <= 0 || $target === '' || $signature === '') {
    echo json_encode(['ok' => false, 'error' => 'Missing parameters.']);
    exit;
}

$pdo = db();
$stmt = $pdo->prepare("SELECT id, agent_id FROM contracts WHERE tenancy_id = ? LIMIT 1");
$stmt->execute([$tenancyId]);
$contract = $stmt->fetch();

if (!$contract) {
    echo json_encode(['ok' => false, 'error' => 'Contract not found for this tenancy.']);
    exit;
}
if ((int)$contract['agent_id'] !== $agentId) {
    echo json_encode(['ok' => false, 'error' => 'You are not the assigned agent for this tenancy.']);
    exit;
}

$result = apply_manual_signature((int)$contract['id'], $agentId, $target, $signature);
echo json_encode([
    'ok'         => $result['success'],
    'error'      => $result['success'] ? null : $result['message'],
    'all_signed' => $result['all_signed'] ?? false,
    'message'    => $result['message'],
]);
