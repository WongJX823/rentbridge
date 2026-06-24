<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/co_tenants.php';
require_role('student');
verify_csrf();

$tenancyId = (int)($_POST['tenancy_id'] ?? 0);
$primaryIc = trim($_POST['primary_ic'] ?? '');
$coTenants = $_POST['cotenant'] ?? [];

if ($tenancyId <= 0) {
    set_flash('danger', 'Invalid tenancy.');
    header('Location: /rentbridge/chat.php');
    exit;
}

$userId = current_user_id();
$pdo = $pdo ?? db();

// Verify user is primary tenant
$stmt = $pdo->prepare("
    SELECT 1 FROM co_tenants
     WHERE tenancy_id = ? AND student_id = ? AND is_primary = 1 LIMIT 1
");
$stmt->execute([$tenancyId, $userId]);
if (!$stmt->fetchColumn()) {
    die('You are not the primary tenant on this tenancy.');
}

// Update primary info
$res = update_primary_tenant($tenancyId, $primaryIc);
if (!$res['ok']) {
    set_flash('danger', 'Primary tenant update failed: ' . $res['error']);
    header('Location: /rentbridge/chat.php');
    exit;
}

// Remove all existing additional co-tenants (start fresh)
$pdo->prepare("UPDATE co_tenants SET status = 'removed' WHERE tenancy_id = ? AND is_primary = 0")
    ->execute([$tenancyId]);

// Add each new co-tenant
$added = 0;
$errors = [];
foreach ($coTenants as $idx => $ct) {
    $name = trim($ct['full_name'] ?? '');
    $ic   = trim($ct['ic_number'] ?? '');
    $phone = trim($ct['phone'] ?? '') ?: null;
    if ($name === '' && $ic === '') continue; // empty row

    $result = add_co_tenant($tenancyId, $name, $ic, $phone, null, $userId);
    if ($result['ok']) {
        $added++;
    } else {
        $errors[] = "Row " . ($idx + 1) . ": " . $result['error'];
    }
}

if (!empty($errors)) {
    set_flash('warning', $added . ' co-tenants added. Issues: ' . implode('; ', $errors));
} else {
    set_flash('success', $added === 0
        ? 'Primary info saved. No additional co-tenants.'
        : $added . ' co-tenant' . ($added === 1 ? '' : 's') . ' added successfully.');
}

// Notify agent
$stmt = $pdo->prepare("SELECT agent_id FROM tenancies WHERE id = ?");
$stmt->execute([$tenancyId]);
$agentId = (int)$stmt->fetchColumn();
if ($agentId > 0) {
    notify(
        $agentId,
        'cotenant_submitted',
        'Co-tenant info submitted',
        'Student submitted co-tenant details for tenancy #' . $tenancyId,
        '/rentbridge/agent/case.php?id=' . $tenancyId
    );
}

header('Location: /rentbridge/chat.php');
exit;