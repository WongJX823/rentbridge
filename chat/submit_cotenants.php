<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/co_tenants.php';
require_role('student');
verify_csrf();

$bookingId = (int)($_POST['booking_id'] ?? 0);
$primaryIc = trim($_POST['primary_ic'] ?? '');
$coTenants = $_POST['cotenant'] ?? [];

if ($bookingId <= 0) {
    set_flash('danger', 'Invalid booking.');
    header('Location: /rentbridge/chat.php');
    exit;
}

$userId = current_user_id();
$pdo = $pdo ?? db();

// Verify user is primary tenant
$stmt = $pdo->prepare("
    SELECT 1 FROM co_tenants
     WHERE booking_id = ? AND student_id = ? AND is_primary = 1 LIMIT 1
");
$stmt->execute([$bookingId, $userId]);
if (!$stmt->fetchColumn()) {
    die('You are not the primary tenant on this booking.');
}

// Update primary info
$res = update_primary_tenant($bookingId, $primaryIc);
if (!$res['ok']) {
    set_flash('danger', 'Primary tenant update failed: ' . $res['error']);
    header('Location: /rentbridge/chat.php');
    exit;
}

// Remove all existing additional co-tenants (start fresh)
$pdo->prepare("UPDATE co_tenants SET status = 'removed' WHERE booking_id = ? AND is_primary = 0")
    ->execute([$bookingId]);

// Add each new co-tenant
$added = 0;
$errors = [];
foreach ($coTenants as $idx => $ct) {
    $name = trim($ct['full_name'] ?? '');
    $ic   = trim($ct['ic_number'] ?? '');
    $phone = trim($ct['phone'] ?? '') ?: null;
    if ($name === '' && $ic === '') continue; // empty row

    $result = add_co_tenant($bookingId, $name, $ic, $phone, null, $userId);
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
$stmt = $pdo->prepare("SELECT agent_id FROM bookings WHERE id = ?");
$stmt->execute([$bookingId]);
$agentId = (int)$stmt->fetchColumn();
if ($agentId > 0) {
    notify(
        $agentId,
        'cotenant_submitted',
        'Co-tenant info submitted',
        'Student submitted co-tenant details for booking #' . $bookingId,
        '/rentbridge/agent/case.php?id=' . $bookingId
    );
}

header('Location: /rentbridge/chat.php');
exit;