<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('agent');

$pdo = db();
$agentId = current_user_id();
$bookingId = (int)($_GET['booking_id'] ?? $_POST['booking_id'] ?? 0);

if ($bookingId <= 0) {
    die('Invalid booking ID');
}

// Verify agent owns this case
$stmt = $pdo->prepare("
    SELECT b.*, p.title AS property_title, p.id AS property_id
      FROM bookings b
      JOIN properties p ON p.id = b.property_id
     WHERE b.id = ? AND b.agent_id = ?
");
$stmt->execute([$bookingId, $agentId]);
$booking = $stmt->fetch();

if (!$booking) {
    die('Booking not found or not assigned to you.');
}

if ($booking['status'] !== 'contract_pending') {
    die('Booking is not in contract_pending state (current: ' . $booking['status'] . ')');
}

// Handle the upload
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if (empty($_FILES['signed_pdf']['name']) || $_FILES['signed_pdf']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Please select a PDF file.';
    } else {
        $tmpName = $_FILES['signed_pdf']['tmp_name'];
        $origName = $_FILES['signed_pdf']['name'];
        $size = $_FILES['signed_pdf']['size'];

        // Check MIME
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmpName);
        if ($mime !== 'application/pdf') {
            $errors[] = 'Only PDF files are accepted. Got: ' . $mime;
        }

        // Size limit 20MB
        if ($size > 20 * 1024 * 1024) {
            $errors[] = 'File too large (max 20MB).';
        }
    }

    if (empty($errors)) {
        $signedDir = __DIR__ . '/../uploads/contracts/signed';
        if (!is_dir($signedDir)) {
            mkdir($signedDir, 0755, true);
        }

        $newName = 'signed_' . $bookingId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.pdf';
        $destAbs = $signedDir . '/' . $newName;
        $destRel = 'uploads/contracts/signed/' . $newName;

        if (!move_uploaded_file($tmpName, $destAbs)) {
            $errors[] = 'Failed to save file.';
        } else {
            try {
                $pdo->beginTransaction();

                // Update booking
                $stmt = $pdo->prepare("
                    UPDATE bookings
                       SET status = 'active',
                           signed_contract_path = ?,
                           signed_uploaded_at = NOW(),
                           signed_uploaded_by = ?
                     WHERE id = ?
                ");
                $stmt->execute([$destRel, $agentId, $bookingId]);

                // Mark property as rented
                $stmt = $pdo->prepare("UPDATE properties SET status = 'rented' WHERE id = ?");
                $stmt->execute([(int)$booking['property_id']]);

                // Mark all co_tenants signed
                $stmt = $pdo->prepare("
                    UPDATE co_tenants
                       SET status = 'signed',
                           signed_at = NOW()
                     WHERE booking_id = ?
                ");
                $stmt->execute([$bookingId]);

                // Notify all parties
                if (function_exists('notify')) {
                    // Student
                    notify(
                        (int)$booking['student_id'],
                        'contract_signed',
                        'Contract activated',
                        'Signed contract for "' . $booking['property_title'] . '" has been uploaded. Tenancy is now active.',
                        '/rentbridge/student/dashboard.php'
                    );
                    // Landlord
                    notify(
                        (int)$booking['landlord_id'],
                        'contract_signed',
                        'Contract activated',
                        'Signed contract for "' . $booking['property_title'] . '" is on file. Tenancy is now active.',
                        '/rentbridge/landlord/properties.php'
                    );
                }

                $pdo->commit();
                set_flash('success', 'Signed contract uploaded. Tenancy is now active.');
                header('Location: /rentbridge/agent/dashboard.php');
                exit;
            } catch (Throwable $e) {
                $pdo->rollBack();
                @unlink($destAbs);
                $errors[] = 'Failed to update booking: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Upload Signed Contract';
$activeNav = 'cases';

ob_start();
?>

<a href="/rentbridge/agent/dashboard.php" class="small text-secondary text-decoration-none mb-3 d-inline-block">
    <i class="bi bi-arrow-left"></i> Back to dashboard
</a>

<h1 style="font-family:'Fraunces',serif;">Upload signed contract</h1>
<p class="text-secondary">
    Booking #<?= (int)$bookingId ?> · <?= e($booking['property_title']) ?>
</p>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $err): ?>
            <div><?= e($err) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="bg-white border rounded-3 p-4">

    <div class="alert alert-light border d-flex gap-3 align-items-start mb-4">
        <i class="bi bi-info-circle text-primary fs-4"></i>
        <div>
            <strong>How to complete this step:</strong>
            <ol class="small mb-0 mt-2">
                <li>Make sure all parties (landlord, tenants, and you as agent) have wet-signed the PDF.</li>
                <li>Scan the signed document, or take clear photos of each page.</li>
                <li>Combine into a single PDF if not already.</li>
                <li>Upload below. The system will mark the tenancy active.</li>
            </ol>
        </div>
    </div>

    <form method="POST" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="booking_id" value="<?= (int)$bookingId ?>">

        <div class="mb-3">
            <label class="form-label fw-semibold">
                Signed PDF <small class="text-danger">*</small>
            </label>
            <input type="file" name="signed_pdf" class="form-control" accept=".pdf" required>
            <small class="text-secondary">PDF only · max 20MB</small>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="/rentbridge/agent/dashboard.php" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-upload me-1"></i> Upload signed contract
            </button>
        </div>
    </form>
</div>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/agent_layout.php';