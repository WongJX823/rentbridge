<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('agent');

$bookingId = (int)($_POST['booking_id'] ?? 0);
if ($bookingId <= 0) {
    die('Invalid booking.');
}

verify_csrf();

$pdo = db();
$userId = current_user_id();

// Verify agent is on this case
$stmt = $pdo->prepare("
    SELECT b.id, b.student_id, b.landlord_id, b.agent_id, b.property_id,
           c.id AS contract_id, c.contract_code, c.generated_pdf_path, c.doc_hash, c.status AS contract_status
      FROM bookings b
      LEFT JOIN contracts c ON c.booking_id = b.id
     WHERE b.id = ? AND b.agent_id = ? LIMIT 1
");
$stmt->execute([$bookingId, $userId]);
$booking = $stmt->fetch();

if (!$booking) {
    set_flash('danger', 'Booking not found or you are not the assigned agent.');
    header('Location: /rentbridge/agent/cases.php');
    exit;
}

if (empty($booking['contract_id']) || empty($booking['generated_pdf_path'])) {
    set_flash('danger', 'Generate the contract PDF first before uploading the signed copy.');
    header('Location: /rentbridge/agent/case.php?id=' . $bookingId);
    exit;
}

// Validate upload
if (!isset($_FILES['signed_pdf']) || $_FILES['signed_pdf']['error'] !== UPLOAD_ERR_OK) {
    set_flash('danger', 'Upload failed. Please try again.');
    header('Location: /rentbridge/agent/case.php?id=' . $bookingId);
    exit;
}

$file = $_FILES['signed_pdf'];

// Size limit: 10MB
if ($file['size'] > 10 * 1024 * 1024) {
    set_flash('danger', 'File too large (max 10MB).');
    header('Location: /rentbridge/agent/case.php?id=' . $bookingId);
    exit;
}

// Type validation
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
$allowedMimes = ['application/pdf' => 'pdf'];
if (!isset($allowedMimes[$mime])) {
    set_flash('danger', 'Only PDF files accepted (got: ' . $mime . ').');
    header('Location: /rentbridge/agent/case.php?id=' . $bookingId);
    exit;
}

// Save file
$newName = $booking['contract_code'] . '_signed_' . time() . '.pdf';
$destDir = __DIR__ . '/../uploads/signed_contracts';
$destPath = $destDir . '/' . $newName;

if (!is_dir($destDir)) {
    mkdir($destDir, 0755, true);
}

if (!move_uploaded_file($file['tmp_name'], $destPath)) {
    set_flash('danger', 'Failed to save uploaded file.');
    header('Location: /rentbridge/agent/case.php?id=' . $bookingId);
    exit;
}

// Compute hash of uploaded file
$uploadedHash = hash_file('sha256', $destPath);
$originalHash = $booking['doc_hash'];
$hashMatches = ($uploadedHash === $originalHash);

// === BASIC TAMPERING CHECK ===
// Note: hashes WILL differ because the signed PDF has handwritten signatures
// scanned in. The hash comparison here is informational only.
// A proper integrity check would require OCR + text comparison, deferred.

// Save uploaded file path + mark contract active
$relPath = 'uploads/signed_contracts/' . $newName;

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare("
        UPDATE contracts
           SET signed_pdf_path = ?,
               signed_uploaded_at = NOW(),
               signed_uploaded_by = ?,
               upload_method = 'external_upload',
               status = 'active',
               activated_at = NOW()
         WHERE id = ?
    ");
    $stmt->execute([$relPath, $userId, $booking['contract_id']]);

    $stmt = $pdo->prepare("UPDATE bookings SET status = 'active' WHERE id = ?");
    $stmt->execute([$bookingId]);

    // Update property to rented
    $stmt = $pdo->prepare("UPDATE properties SET status = 'rented' WHERE id = ?");
    $stmt->execute([(int)$booking['property_id']]);

    // Notify all parties
    notify(
        (int)$booking['student_id'],
        'contract_active',
        '✓ Contract signed and active',
        'Contract ' . $booking['contract_code'] . ' is now active. You may move in on the agreed date.',
        '/rentbridge/student/booking.php?id=' . $bookingId
    );
    notify(
        (int)$booking['landlord_id'],
        'contract_active',
        '✓ Contract signed and active',
        'Contract ' . $booking['contract_code'] . ' is now active.',
        '/rentbridge/landlord/booking.php?id=' . $bookingId
    );

    $pdo->commit();

    set_flash('success', 'Signed contract uploaded. Tenancy is now active.');
} catch (Throwable $e) {
    $pdo->rollBack();
    @unlink($destPath); // remove file if DB update failed
    set_flash('danger', 'Failed to save: ' . $e->getMessage());
}

header('Location: /rentbridge/agent/case.php?id=' . $bookingId);
exit;