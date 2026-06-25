<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('student');

$pdo    = db();
$userId = current_user_id();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method not allowed.');
}

verify_csrf();

$contractId = (int)($_POST['contract_id'] ?? 0);
if ($contractId <= 0) {
    set_flash('danger', 'Invalid contract.');
    header('Location: /rentbridge/student/dashboard.php');
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM contracts WHERE id = ? LIMIT 1');
$stmt->execute([$contractId]);
$contract = $stmt->fetch();

if (!$contract || $contract['status'] !== 'pending_signatures') {
    set_flash('danger', 'This contract is no longer open for rejection.');
    header('Location: /rentbridge/student/dashboard.php');
    exit;
}

// Verify this user is an unsigned co-tenant (non-primary) on this tenancy
$stmt = $pdo->prepare("
    SELECT id, full_name FROM co_tenants
     WHERE tenancy_id = ? AND student_id = ? AND is_primary = 0 AND status = 'pending'
     LIMIT 1
");
$stmt->execute([(int)$contract['tenancy_id'], $userId]);
$myRow = $stmt->fetch();

if (!$myRow) {
    set_flash('danger', 'You are not able to reject this contract.');
    header('Location: /rentbridge/contracts/view.php?id=' . $contractId);
    exit;
}

$coTenantName = $myRow['full_name'];

try {
    $pdo->beginTransaction();

    // Mark this co-tenant as rejected
    $pdo->prepare("
        UPDATE co_tenants SET status = 'removed', notes = ? WHERE id = ?
    ")->execute(['Rejected by co-tenant on ' . date('Y-m-d H:i:s'), $myRow['id']]);

    // Terminate the contract
    $pdo->prepare("
        UPDATE contracts SET status = 'terminated' WHERE id = ?
    ")->execute([$contractId]);

    // Cancel the tenancy
    $pdo->prepare("
        UPDATE tenancies
           SET status = 'cancelled_by_student',
               cancellation_reason = ?,
               cancelled_by = ?
         WHERE id = ?
    ")->execute([
        'Co-tenant ' . $coTenantName . ' rejected the tenancy.',
        $userId,
        (int)$contract['tenancy_id'],
    ]);

    $pdo->commit();

    // Notify agent
    if (function_exists('notify') && !empty($contract['agent_id'])) {
        notify(
            (int)$contract['agent_id'],
            'contract_rejected',
            'Co-tenant rejected contract',
            $coTenantName . ' has rejected contract ' . $contract['contract_code'] . '. The tenancy has been cancelled.',
            '/rentbridge/agent/dashboard.php'
        );
    }

    // Notify primary tenant
    if (function_exists('notify') && !empty($contract['student_id'])) {
        notify(
            (int)$contract['student_id'],
            'contract_rejected',
            'Tenancy cancelled by co-tenant',
            $coTenantName . ' has rejected contract ' . $contract['contract_code'] . '. Please contact your agent to resolve this.',
            '/rentbridge/student/dashboard.php'
        );
    }

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    set_flash('danger', 'Something went wrong. Please try again.');
    header('Location: /rentbridge/contracts/view.php?id=' . $contractId);
    exit;
}

set_flash('info', 'You have rejected the tenancy contract. The agent has been notified.');
header('Location: /rentbridge/student/dashboard.php');
exit;
