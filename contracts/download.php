<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/notifications.php';
require_login();

$contractId = (int)($_GET['id'] ?? 0);
if ($contractId <= 0) { http_response_code(400); die('Invalid contract.'); }

$pdo = db();
$userId = current_user_id();

$stmt = $pdo->prepare("SELECT * FROM contracts WHERE id = ? LIMIT 1");
$stmt->execute([$contractId]);
$contract = $stmt->fetch();

if (!$contract) { http_response_code(404); die('Contract not found.'); }

// Only the primary student or landlord can use this
if ($userId === (int)$contract['student_id']) {
    $role = 'student';
} elseif ($userId === (int)$contract['landlord_id']) {
    $role = 'landlord';
} else {
    http_response_code(403); die('Access denied.');
}

// Must not have already signed
if (!empty($contract[$role . '_signed_at'])) {
    set_flash('info', 'You have already signed this contract.');
    header('Location: /rentbridge/contracts/view.php?id=' . $contractId);
    exit;
}

// Must have a generated PDF to download
$pdfRel = $contract['generated_pdf_path'] ?? '';
$pdfAbs = __DIR__ . '/../' . $pdfRel;
if (empty($pdfRel) || !file_exists($pdfAbs)) {
    set_flash('warning', 'Contract PDF is not ready yet. Please contact your agent.');
    header('Location: /rentbridge/' . ($role === 'student' ? 'student' : 'landlord') . '/booking.php?id=' . $contract['booking_id']);
    exit;
}

// Record manual sign method (idempotent — only set if not already set)
if (empty($contract[$role . '_sign_method'])) {
    $stmt = $pdo->prepare("UPDATE contracts SET {$role}_sign_method = 'manual' WHERE id = ?");
    $stmt->execute([$contractId]);

    // Notify agent: this party chose manual
    $partyLabel = $role === 'student' ? 'Tenant' : 'Landlord';
    $stmt2 = $pdo->prepare("SELECT booking_id FROM contracts WHERE id = ? LIMIT 1");
    $stmt2->execute([$contractId]);
    $bookingId = (int)$stmt2->fetchColumn();

    notify(
        (int)$contract['agent_id'],
        'manual_sign_chosen',
        $partyLabel . ' will sign manually',
        $partyLabel . ' has downloaded contract ' . $contract['contract_code'] . ' to sign manually. Once you receive their physical signature, mark them as signed in your case.',
        '/rentbridge/agent/case.php?id=' . $contract['booking_id']
    );
}

// Stream PDF download
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $contract['contract_code'] . '.pdf"');
header('Content-Length: ' . filesize($pdfAbs));
header('Cache-Control: private');
readfile($pdfAbs);
exit;
