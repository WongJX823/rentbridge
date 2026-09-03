<?php
/**
 * Auth-gated image for one party's e-signature on a contract. Signature
 * images could be reused to help forge a document, so they get the same
 * gate as the contract PDF itself — never served as plain static files
 * under uploads/signatures/ (blocked there by .htaccess).
 *
 * Usage: signature.php?contract_id=<id>&field=landlord|agent|student
 *        signature.php?contract_id=<id>&co_tenant_id=<id>
 */
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/contracts.php';
require_login();

$contractId = (int)($_GET['contract_id'] ?? 0);
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

$coTenantId = (int)($_GET['co_tenant_id'] ?? 0);
if ($coTenantId > 0) {
    $stmt = $pdo->prepare('SELECT signature_data FROM co_tenants WHERE id = ? AND tenancy_id = ? LIMIT 1');
    $stmt->execute([$coTenantId, (int)$contract['tenancy_id']]);
    $relPath = $stmt->fetchColumn();
} else {
    $field = $_GET['field'] ?? '';
    $column = match ($field) {
        'landlord' => 'landlord_signature',
        'agent'    => 'agent_signature',
        'student'  => 'student_signature',
        default    => null,
    };
    if ($column === null) {
        http_response_code(400);
        die('Invalid field.');
    }
    $relPath = $contract[$column] ?? null;
}

if (!$relPath) {
    http_response_code(404);
    die('Signature not found.');
}

$absolutePath = __DIR__ . '/../' . $relPath;
if (!is_file($absolutePath)) {
    http_response_code(404);
    die('File missing on disk.');
}

header('Content-Type: image/png');
header('Content-Length: ' . filesize($absolutePath));
header('X-Content-Type-Options: nosniff');
readfile($absolutePath);
