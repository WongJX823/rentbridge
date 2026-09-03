<?php
/**
 * Auth-gated download for a contract's PDF (generated, signed, or the
 * merged "best available" copy). Contracts contain full legal/personal
 * detail — never served as plain static files under uploads/contracts/,
 * uploads/contracts/signed/, or uploads/generated_contracts/ (all blocked
 * there by .htaccess).
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/contracts.php';
require_login();

$contractId = (int)($_GET['id'] ?? 0);
if ($contractId <= 0) {
    http_response_code(400);
    die('Invalid contract ID.');
}

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM contracts WHERE id = ? LIMIT 1');
$stmt->execute([$contractId]);
$contract = $stmt->fetch();

if (!$contract) {
    http_response_code(404);
    die('Contract not found.');
}

if (!contract_can_view($contract, current_user_id(), current_role())) {
    http_response_code(403);
    die('You are not a party to this contract.');
}

// Prefer the merged/signed copy, then the agent-generated one, then whatever
// the original single-file column holds — same priority every caller used.
$relPath = $contract['contract_pdf_path']
    ?: $contract['signed_pdf_path']
    ?: $contract['generated_pdf_path']
    ?? null;

if (!$relPath) {
    http_response_code(404);
    die('No PDF available for this contract yet.');
}

$absolutePath = __DIR__ . '/../' . $relPath;
if (!is_file($absolutePath)) {
    http_response_code(404);
    die('File missing on disk.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . e($contract['contract_code']) . '.pdf"');
header('Content-Length: ' . filesize($absolutePath));
header('X-Content-Type-Options: nosniff');
readfile($absolutePath);
