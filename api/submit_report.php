<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/reports.php';

require_login();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$reportedUserId = (int)($_POST['reported_user_id'] ?? 0);
$contextType    = $_POST['context_type'] ?? 'general';
$contextIdRaw   = $_POST['context_id'] ?? '';
$contextId      = ($contextIdRaw !== '' && (int)$contextIdRaw > 0) ? (int)$contextIdRaw : null;
$reason         = trim($_POST['reason'] ?? '');
$details        = trim(substr($_POST['details'] ?? '', 0, 2000));

$allowedReasons = ['harassment', 'scam', 'fake_information', 'misconduct', 'fraud', 'other'];
$allowedCtx     = ['tenancy', 'message', 'general'];

if ($reportedUserId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'No user selected.']);
    exit;
}
if (!in_array($reason, $allowedReasons, true)) {
    echo json_encode(['ok' => false, 'error' => 'Please select a valid reason.']);
    exit;
}
if (!in_array($contextType, $allowedCtx, true)) {
    $contextType = 'general';
}

// Verify the reported user actually exists
$pdo = db();
$exists = $pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ?");
$exists->execute([$reportedUserId]);
if ((int)$exists->fetchColumn() === 0) {
    echo json_encode(['ok' => false, 'error' => 'Reported user not found.']);
    exit;
}

$ok = submit_report(current_user_id(), $reportedUserId, $contextType, $contextId, $reason, $details);

echo json_encode($ok
    ? ['ok' => true]
    : ['ok' => false, 'error' => 'Report already submitted, or you cannot report yourself.']
);
