<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/co_tenants.php';
require_once __DIR__ . '/../includes/contracts.php';
require_role('agent');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_PATH . '/agent/cases.php');
    exit;
}

verify_csrf();

$tenancyId = (int)($_POST['tenancy_id'] ?? 0);
if ($tenancyId <= 0) {
    set_flash('danger', 'Invalid tenancy.');
    header('Location: ' . BASE_PATH . '/agent/cases.php');
    exit;
}

$pdo = db();
$stmt = $pdo->prepare("SELECT id, status, student_id, landlord_id FROM tenancies WHERE id = ? AND agent_id = ? LIMIT 1");
$stmt->execute([$tenancyId, current_user_id()]);
$tenancy = $stmt->fetch();

if (!$tenancy) {
    http_response_code(404);
    die('Tenancy not found or you are not the assigned agent.');
}

// Co-tenants can only be added while the contract isn't finalized yet.
if (!in_array($tenancy['status'], ['agent_verifying', 'agent_verified', 'contract_pending'], true)) {
    set_flash('danger', 'Co-tenants can no longer be added — this tenancy is already active or closed.');
    header('Location: ' . BASE_PATH . '/agent/case.php?id=' . $tenancyId);
    exit;
}

$fullName = trim($_POST['full_name'] ?? '');
$icNumber = trim($_POST['ic_number'] ?? '');
$phone    = trim($_POST['phone'] ?? '');
$email    = trim($_POST['email'] ?? '');

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    set_flash('danger', 'Invalid email address.');
    header('Location: ' . BASE_PATH . '/agent/case.php?id=' . $tenancyId);
    exit;
}

$result = add_co_tenant($tenancyId, $fullName, $icNumber, $phone ?: null, $email ?: null, current_user_id());

if (!$result['ok']) {
    set_flash('danger', $result['error']);
    header('Location: ' . BASE_PATH . '/agent/case.php?id=' . $tenancyId);
    exit;
}

// Backfill a signing token now, in case a contract already exists and the
// agent wants to send the sign-link email right away from the contract page.
ensure_cotenant_sign_tokens($tenancyId);

notify(
    (int)$tenancy['student_id'],
    'cotenant_added',
    'A co-tenant was added',
    current_user_display_name() . ' added ' . $fullName . ' as a co-tenant on your tenancy.',
    '' . BASE_PATH . '/student/tenancy.php?id=' . $tenancyId
);
notify(
    (int)$tenancy['landlord_id'],
    'cotenant_added',
    'A co-tenant was added',
    current_user_display_name() . ' added ' . $fullName . ' as a co-tenant on tenancy #' . $tenancyId . '.',
    '' . BASE_PATH . '/landlord/tenancy.php?id=' . $tenancyId
);

set_flash('success', $fullName . ' was added as a co-tenant.');
header('Location: ' . BASE_PATH . '/agent/case.php?id=' . $tenancyId);
exit;
