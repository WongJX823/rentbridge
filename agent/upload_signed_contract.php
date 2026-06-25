<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('agent');

$pdo = db();
$agentId = current_user_id();
$tenancyId = (int)($_GET['tenancy_id'] ?? $_POST['tenancy_id'] ?? 0);

if ($tenancyId <= 0) {
    die('Invalid tenancy ID');
}

// Verify agent owns this case
$stmt = $pdo->prepare("
    SELECT b.*, p.title AS property_title, p.id AS property_id, p.monthly_rent
      FROM tenancies b
      JOIN properties p ON p.id = b.property_id
     WHERE b.id = ? AND b.agent_id = ?
");
$stmt->execute([$tenancyId, $agentId]);
$tenancy = $stmt->fetch();

if (!$tenancy) {
    die('Tenancy not found or not assigned to you.');
}

if ($tenancy['status'] !== 'contract_pending') {
    die('Tenancy is not in contract_pending state (current: ' . $tenancy['status'] . ')');
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

        $newName = 'signed_' . $tenancyId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.pdf';
        $destAbs = $signedDir . '/' . $newName;
        $destRel = 'uploads/contracts/signed/' . $newName;

        if (!move_uploaded_file($tmpName, $destAbs)) {
            $errors[] = 'Failed to save file.';
        } else {
            try {
                $pdo->beginTransaction();

                // Update tenancy
                $stmt = $pdo->prepare("
                    UPDATE tenancies
                       SET status = 'active',
                           signed_contract_path = ?,
                           signed_uploaded_at = NOW(),
                           signed_uploaded_by = ?
                     WHERE id = ?
                ");
                $stmt->execute([$destRel, $agentId, $tenancyId]);

                // Activate the contract record too (covers the e-sign mixed-signing path)
                $pdo->prepare("
                    UPDATE contracts
                       SET status = 'active',
                           signed_pdf_path = ?,
                           signed_uploaded_at = NOW(),
                           signed_uploaded_by = ?,
                           activated_at = NOW()
                     WHERE tenancy_id = ? AND status = 'pending_signatures'
                ")->execute([$destRel, $agentId, $tenancyId]);

                // Create commission record (1 month base rent, 70% UTeM / 30% agent)
                $contractIdRow = $pdo->prepare("SELECT id FROM contracts WHERE tenancy_id = ? LIMIT 1");
                $contractIdRow->execute([$tenancyId]);
                $contractId = (int)$contractIdRow->fetchColumn();

                if ($contractId > 0) {
                    $dupCheck = $pdo->prepare("SELECT id FROM agent_commissions WHERE contract_id = ? LIMIT 1");
                    $dupCheck->execute([$contractId]);
                    if (!$dupCheck->fetchColumn()) {
                        $baseRent      = (float)$tenancy['monthly_rent'];
                        $commissionAmt = $baseRent;
                        $sstAmt        = round($commissionAmt * 0.06, 2);
                        $totalPayable  = round($commissionAmt + $sstAmt, 2);
                        $pdo->prepare("
                            INSERT INTO agent_commissions
                                (contract_id, agent_id, base_rent, commission_pct, commission_amt,
                                 sst_pct, sst_amt, total_payable, status, earned_at)
                            VALUES (?, ?, ?, 100.00, ?, 6.00, ?, ?, 'earned', NOW())
                        ")->execute([$contractId, $agentId, $baseRent, $commissionAmt, $sstAmt, $totalPayable]);
                    }
                }

                // Mark property as rented
                $stmt = $pdo->prepare("UPDATE properties SET status = 'rented' WHERE id = ?");
                $stmt->execute([(int)$tenancy['property_id']]);

                // Mark all co_tenants signed
                $stmt = $pdo->prepare("
                    UPDATE co_tenants
                       SET status = 'signed',
                           signed_at = NOW()
                     WHERE tenancy_id = ?
                ");
                $stmt->execute([$tenancyId]);

                // Notify all parties
                if (function_exists('notify')) {
                    notify(
                        (int)$tenancy['student_id'],
                        'contract_signed',
                        'Contract activated',
                        'Signed contract for "' . $tenancy['property_title'] . '" has been uploaded. Your tenancy is now active.',
                        '/rentbridge/student/tenancy.php?id=' . $tenancyId
                    );
                    notify(
                        (int)$tenancy['landlord_id'],
                        'contract_signed',
                        'Contract activated',
                        'Signed contract for "' . $tenancy['property_title'] . '" is on file. The tenancy is now active.',
                        '/rentbridge/landlord/tenancy.php?id=' . $tenancyId
                    );
                }

                $pdo->commit();
                set_flash('success', 'Signed contract uploaded. Tenancy is now active.');
                header('Location: /rentbridge/agent/dashboard.php');
                exit;
            } catch (Throwable $e) {
                $pdo->rollBack();
                @unlink($destAbs);
                $errors[] = 'Failed to update tenancy: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle     = 'Upload Signed Contract';
$activeNav     = 'cases';
$showPageTitle = false;

ob_start();
?>

<a href="/rentbridge/agent/dashboard.php" class="small text-secondary text-decoration-none mb-3 d-inline-block">
    <i class="bi bi-arrow-left"></i> Back to dashboard
</a>

<h1 style="font-family:'Fraunces',serif;">Upload signed contract</h1>
<p class="text-secondary">
    Tenancy #<?= (int)$tenancyId ?> · <?= e($tenancy['property_title']) ?>
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
        <input type="hidden" name="tenancy_id" value="<?= (int)$tenancyId ?>">

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