<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/contracts.php';
require_once __DIR__ . '/../includes/notifications.php';
require_role('agent');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); die('POST only.'); }
verify_csrf();

$contractId = (int)($_POST['contract_id'] ?? 0);
$role       = $_POST['role'] ?? '';

if ($contractId <= 0 || !in_array($role, ['student', 'landlord'], true)) {
    set_flash('danger', 'Invalid request.');
    header('Location: /rentbridge/agent/cases.php');
    exit;
}

$pdo    = db();
$userId = current_user_id();

$stmt = $pdo->prepare("SELECT c.*, b.id AS booking_id FROM contracts c JOIN bookings b ON b.id = c.booking_id WHERE c.id = ? AND b.agent_id = ? LIMIT 1");
$stmt->execute([$contractId, $userId]);
$contract = $stmt->fetch();

if (!$contract) {
    set_flash('danger', 'Contract not found or not assigned to you.');
    header('Location: /rentbridge/agent/cases.php');
    exit;
}

$bookingId = (int)$contract['booking_id'];

// Must not already be signed
if (!empty($contract[$role . '_signed_at'])) {
    set_flash('warning', ucfirst($role) . ' has already signed.');
    header('Location: /rentbridge/agent/case.php?id=' . $bookingId);
    exit;
}

// Must be their turn (strict order: student → landlord)
$next = contract_next_signer($contract);
if ($next !== $role) {
    set_flash('warning', 'Cannot mark ' . $role . ' as signed — it is not their turn yet (next: ' . $next . ').');
    header('Location: /rentbridge/agent/case.php?id=' . $bookingId);
    exit;
}

// Mark as manually signed
$sigCol  = $role . '_signature';
$timeCol = $role . '_signed_at';
$methodCol = $role . '_sign_method';

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("UPDATE contracts SET $sigCol = 'wet_sign_manual', $timeCol = NOW(), $methodCol = 'manual' WHERE id = ?");
    $stmt->execute([$contractId]);

    // Refresh to check if all signed
    $stmt = $pdo->prepare("SELECT * FROM contracts WHERE id = ? LIMIT 1");
    $stmt->execute([$contractId]);
    $contract = $stmt->fetch();

    $allSigned = !empty($contract['student_signed_at'])
              && !empty($contract['landlord_signed_at'])
              && !empty($contract['agent_signed_at']);

    if ($allSigned) {
        $pdo->prepare("UPDATE contracts SET status = 'active', activated_at = NOW() WHERE id = ?")->execute([$contractId]);
        $pdo->prepare("UPDATE bookings  SET status = 'active' WHERE id = ?")->execute([$bookingId]);
        $pdo->prepare("UPDATE properties SET status = 'rented' WHERE id = (SELECT property_id FROM bookings WHERE id = ?)")->execute([$bookingId]);
    }

    $pdo->commit();

    if ($allSigned) {
        generate_contract_pdf($contractId);
        foreach (['student_id', 'landlord_id', 'agent_id'] as $col) {
            notify((int)$contract[$col], 'contract_active', 'Contract activated',
                'All parties have signed contract ' . $contract['contract_code'] . '. Tenancy is now active.',
                '/rentbridge/contracts/view.php?id=' . $contractId);
        }
        set_flash('success', 'Marked as manually signed. All parties have signed — contract is now active!');
    } else {
        // Notify next party it's their turn
        $nextAfter = contract_next_signer($contract);
        if ($nextAfter !== 'all_done') {
            $nextUserId = (int)$contract[$nextAfter . '_id'];
            $signMethod = $contract[$nextAfter . '_sign_method'] ?? null;
            if ($signMethod === 'manual') {
                // Next party is also manual — notify agent themselves to collect
                notify($userId, 'manual_sign_chosen', 'Collect ' . ucfirst($nextAfter) . '\'s signature',
                    'Collect the manual signature from the ' . $nextAfter . ' for contract ' . $contract['contract_code'] . '.',
                    '/rentbridge/agent/case.php?id=' . $bookingId);
            } else {
                notify($nextUserId, 'contract_your_turn', 'Your turn to e-sign the contract',
                    'Contract ' . $contract['contract_code'] . ' — previous party has signed. Click to e-sign now.',
                    '/rentbridge/contracts/sign.php?id=' . $contractId);
            }
        }
        set_flash('success', ucfirst($role) . ' marked as manually signed.');
    }

} catch (Throwable $e) {
    $pdo->rollBack();
    set_flash('danger', 'Failed: ' . $e->getMessage());
}

header('Location: /rentbridge/agent/case.php?id=' . $bookingId);
exit;
