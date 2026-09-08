<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/contracts.php';
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

$stmt = $pdo->prepare("SELECT id, generated_pdf_path FROM contracts WHERE tenancy_id = ? LIMIT 1");
$stmt->execute([$tenancyId]);
$contract = $stmt->fetch() ?: [];

$pendingManualSigners = !empty($contract['id']) ? contract_pending_manual_signers((int)$contract['id']) : [];

/** Runs the exact same activation steps regardless of how the final signed PDF was produced. */
function finalize_signed_contract(PDO $pdo, array $tenancy, int $tenancyId, int $agentId, string $destRel): void {
    $pdo->beginTransaction();

    $pdo->prepare("
        UPDATE tenancies
           SET status = 'active',
               signed_contract_path = ?,
               signed_uploaded_at = NOW(),
               signed_uploaded_by = ?
         WHERE id = ?
    ")->execute([$destRel, $agentId, $tenancyId]);

    // COALESCE landlord_signed_at: if the landlord already e-signed digitally
    // (mixed signing — only some other party chose manual), keep that
    // original timestamp instead of overwriting it with the upload time.
    $pdo->prepare("
        UPDATE contracts
           SET status = 'active',
               signed_pdf_path = ?,
               signed_uploaded_at = NOW(),
               signed_uploaded_by = ?,
               activated_at = NOW(),
               landlord_signed_at = COALESCE(landlord_signed_at, NOW())
         WHERE tenancy_id = ? AND status = 'pending_signatures'
    ")->execute([$destRel, $agentId, $tenancyId]);

    $contractIdRow = $pdo->prepare("SELECT id FROM contracts WHERE tenancy_id = ? LIMIT 1");
    $contractIdRow->execute([$tenancyId]);
    $contractId = (int)$contractIdRow->fetchColumn();
    if ($contractId > 0) {
        ensure_agent_commission_for_contract($contractId, 'earned');
    }

    $pdo->prepare("UPDATE properties SET status = 'rented' WHERE id = ?")
        ->execute([(int)$tenancy['property_id']]);

    $pdo->prepare("
        UPDATE co_tenants SET status = 'signed', signed_at = NOW() WHERE tenancy_id = ?
    ")->execute([$tenancyId]);

    if (function_exists('notify')) {
        notify(
            (int)$tenancy['student_id'], 'contract_signed', 'Contract activated',
            'Signed contract for "' . $tenancy['property_title'] . '" has been uploaded. Your tenancy is now active.',
            '' . BASE_PATH . '/student/tenancy.php?id=' . $tenancyId
        );
        notify(
            (int)$tenancy['landlord_id'], 'contract_signed', 'Contract activated',
            'Signed contract for "' . $tenancy['property_title'] . '" is on file. The tenancy is now active.',
            '' . BASE_PATH . '/landlord/tenancy.php?id=' . $tenancyId
        );
    }

    $pdo->commit();
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'upload_whole') {
        if (empty($_FILES['signed_pdf']['name']) || $_FILES['signed_pdf']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Please select a PDF file.';
        } else {
            $tmpName = $_FILES['signed_pdf']['tmp_name'];
            $size = $_FILES['signed_pdf']['size'];
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($tmpName);
            if ($mime !== 'application/pdf') {
                $errors[] = 'Only PDF files are accepted. Got: ' . $mime;
            }
            if ($size > 20 * 1024 * 1024) {
                $errors[] = 'File too large (max 20MB).';
            }

            if (empty($errors)) {
                $newName = 'signed_' . $tenancyId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.pdf';
                $destRel = 'uploads/contracts/signed/' . $newName;
                if (!rb_storage_put_file($tmpName, $destRel)) {
                    $errors[] = 'Failed to save file.';
                } else {
                    try {
                        finalize_signed_contract($pdo, $tenancy, $tenancyId, $agentId, $destRel);
                        set_flash('success', 'Signed contract uploaded. Tenancy is now active.');
                        header('Location: ' . BASE_PATH . '/agent/dashboard.php');
                        exit;
                    } catch (Throwable $e) {
                        if ($pdo->inTransaction()) $pdo->rollBack();
                        $errors[] = 'Failed to update tenancy: ' . $e->getMessage();
                    }
                }
            }
        }
    }
}

$showManualOnly = !empty($_GET['manual']) || empty($contract['generated_pdf_path']) || empty($pendingManualSigners);

$pageTitle     = 'Upload Signed Contract';
$activeNav     = 'cases';
$showPageTitle = false;

ob_start();
?>

<a href="<?= BASE_PATH ?>/agent/dashboard.php" class="small text-secondary text-decoration-none mb-3 d-inline-block">
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

<div id="signatureSuccessBox" class="alert alert-success d-none"></div>

<?php if (!$showManualOnly): ?>
<div class="bg-white border rounded-3 p-4 mb-3">
    <h5 class="mb-1"><i class="bi bi-crop me-1 text-primary"></i> Extract a physical signature from a scan</h5>
    <div class="alert alert-light border d-flex gap-3 align-items-start my-3">
        <i class="bi bi-info-circle text-primary fs-4"></i>
        <div>
            <strong>How this works:</strong>
            <ol class="small mb-0 mt-2">
                <li>Print the contract (already shows everyone's e-signature embedded) and get the physical signer to sign their blank line.</li>
                <li>Scan just that page and upload it below.</li>
                <li>Draw a box around the signature. That crop is saved as their signature — nobody else's signature is touched, and the final contract is generated fresh from the complete data.</li>
            </ol>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <label class="form-label fw-semibold">Scanned page (PDF) <small class="text-danger">*</small></label>
            <input type="file" id="scanFile" class="form-control" accept=".pdf">
            <small class="text-secondary">PDF only · max 20MB</small>
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Page number</label>
            <input type="number" id="scanPageNum" class="form-control" min="1" value="1">
        </div>
        <div class="col-md-3">
            <label class="form-label fw-semibold">Who signed this?</label>
            <select id="signerTarget" class="form-select">
                <?php foreach ($pendingManualSigners as $s): ?>
                    <option value="<?= e($s['target']) ?>"><?= e($s['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div id="cropArea" class="d-none">
        <p class="small text-secondary mb-2">
            Click and drag on the page below to draw a box around the signature, then click "Use this crop."
        </p>
        <div style="overflow:auto; max-height:60vh; border:1px solid #ddd; border-radius:6px; background:#F4F4EE;">
            <canvas id="pdfCanvas" style="cursor:crosshair; display:block;"></canvas>
        </div>
        <div class="d-flex gap-2 mt-3">
            <button type="button" id="useCropBtn" class="btn btn-primary" disabled>
                <i class="bi bi-check2-square me-1"></i> Use this crop
            </button>
            <span id="cropStatus" class="text-secondary small align-self-center"></span>
        </div>
    </div>

    <div id="cropError" class="alert alert-danger small d-none mt-3"></div>
</div>

<p class="text-center small text-secondary mb-3">— or —</p>
<?php endif; ?>

<div class="bg-white border rounded-3 p-4">
    <h5 class="mb-1"><i class="bi bi-upload me-1 text-secondary"></i> Upload the complete merged file yourself</h5>
    <div class="alert alert-light border d-flex gap-3 align-items-start my-3">
        <i class="bi bi-info-circle text-secondary fs-4"></i>
        <div>
            <strong>How to complete this step:</strong>
            <ol class="small mb-0 mt-2">
                <li>Make sure all parties (landlord, tenants, and you as agent) have wet-signed the PDF.</li>
                <li>Scan the signed document, or take clear photos of each page.</li>
                <li>Combine into a single PDF if not already.</li>
                <li>Upload below. The system will mark the tenancy active immediately — no review step.</li>
            </ol>
        </div>
    </div>

    <form method="POST" enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="tenancy_id" value="<?= (int)$tenancyId ?>">
        <input type="hidden" name="action" value="upload_whole">

        <div class="mb-3">
            <label class="form-label fw-semibold">
                Signed PDF <small class="text-danger">*</small>
            </label>
            <input type="file" name="signed_pdf" class="form-control" accept=".pdf" required>
            <small class="text-secondary">PDF only · max 20MB</small>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="<?= BASE_PATH ?>/agent/dashboard.php" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-upload me-1"></i> Upload signed contract
            </button>
        </div>
    </form>
</div>

<?php if (!$showManualOnly): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<script>
(function () {
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

    const fileInput   = document.getElementById('scanFile');
    const pageNumInput = document.getElementById('scanPageNum');
    const cropArea    = document.getElementById('cropArea');
    const canvas      = document.getElementById('pdfCanvas');
    const ctx         = canvas.getContext('2d');
    const useCropBtn  = document.getElementById('useCropBtn');
    const cropStatus  = document.getElementById('cropStatus');
    const cropErrorEl = document.getElementById('cropError');
    const targetSelect = document.getElementById('signerTarget');
    const successBox  = document.getElementById('signatureSuccessBox');

    let basePixels = null; // cached ImageData of the rendered page, for redraw during drag
    let rect = null;       // {x,y,w,h} in canvas pixel space
    let dragStart = null;

    function showError(msg) {
        cropErrorEl.textContent = msg;
        cropErrorEl.classList.remove('d-none');
    }
    function clearError() {
        cropErrorEl.classList.add('d-none');
    }

    async function renderPage() {
        clearError();
        cropArea.classList.add('d-none');
        useCropBtn.disabled = true;
        rect = null;

        const file = fileInput.files[0];
        if (!file) return;
        const pageNum = parseInt(pageNumInput.value, 10) || 1;

        try {
            const buf = await file.arrayBuffer();
            const pdf = await pdfjsLib.getDocument({ data: buf }).promise;
            if (pageNum > pdf.numPages) {
                showError('That PDF only has ' + pdf.numPages + ' page(s).');
                return;
            }
            const page = await pdf.getPage(pageNum);
            const viewport = page.getViewport({ scale: 1.5 });
            canvas.width  = viewport.width;
            canvas.height = viewport.height;
            await page.render({ canvasContext: ctx, viewport: viewport }).promise;
            basePixels = ctx.getImageData(0, 0, canvas.width, canvas.height);
            cropArea.classList.remove('d-none');
            cropStatus.textContent = 'Draw a box around the signature.';
        } catch (err) {
            showError('Could not read that PDF: ' + err.message);
        }
    }

    fileInput.addEventListener('change', renderPage);
    pageNumInput.addEventListener('change', renderPage);

    function canvasPos(e) {
        const r = canvas.getBoundingClientRect();
        return { x: Math.round(e.clientX - r.left), y: Math.round(e.clientY - r.top) };
    }

    canvas.addEventListener('mousedown', function (e) {
        dragStart = canvasPos(e);
    });
    canvas.addEventListener('mousemove', function (e) {
        if (!dragStart || !basePixels) return;
        const pos = canvasPos(e);
        const x = Math.min(dragStart.x, pos.x), y = Math.min(dragStart.y, pos.y);
        const w = Math.abs(pos.x - dragStart.x), h = Math.abs(pos.y - dragStart.y);
        ctx.putImageData(basePixels, 0, 0);
        ctx.strokeStyle = '#2E8B57';
        ctx.lineWidth = 2;
        ctx.strokeRect(x, y, w, h);
        ctx.fillStyle = 'rgba(46,139,87,0.15)';
        ctx.fillRect(x, y, w, h);
        rect = { x, y, w, h };
    });
    canvas.addEventListener('mouseup', function () {
        dragStart = null;
        useCropBtn.disabled = !(rect && rect.w > 5 && rect.h > 5);
        if (useCropBtn.disabled) {
            cropStatus.textContent = 'Box too small — try again.';
        } else {
            cropStatus.textContent = 'Box ready (' + rect.w + '×' + rect.h + 'px).';
        }
    });

    useCropBtn.addEventListener('click', async function () {
        if (!rect) return;
        clearError();
        const target = targetSelect.value;
        if (!target) {
            showError('No pending physical signer selected.');
            return;
        }

        // The selection box drawn during mousemove is a green tinted overlay
        // painted directly onto this canvas for visual feedback — crop from
        // the clean rendered page (basePixels), not the decorated canvas, or
        // the tint gets baked into the saved signature image.
        ctx.putImageData(basePixels, 0, 0);

        const cropCanvas = document.createElement('canvas');
        cropCanvas.width = rect.w;
        cropCanvas.height = rect.h;
        cropCanvas.getContext('2d').drawImage(canvas, rect.x, rect.y, rect.w, rect.h, 0, 0, rect.w, rect.h);
        const dataUrl = cropCanvas.toDataURL('image/png');

        useCropBtn.disabled = true;
        useCropBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Saving...';

        try {
            const formData = new FormData();
            formData.set('_csrf', '<?= csrf_token() ?>');
            formData.set('tenancy_id', '<?= (int)$tenancyId ?>');
            formData.set('target', target);
            formData.set('signature_data', dataUrl);

            const resp = await fetch('<?= BASE_PATH ?>/agent/apply_physical_signature.php', {
                method: 'POST', body: formData
            });
            const data = await resp.json();
            if (data.ok) {
                if (data.all_signed) {
                    successBox.textContent = 'All signatures collected — the tenancy is now active! The final contract PDF has been generated.';
                    successBox.classList.remove('d-none');
                    document.querySelectorAll('#scanFile, #scanPageNum, #signerTarget, #useCropBtn').forEach(el => el.disabled = true);
                    document.getElementById('cropArea').classList.add('d-none');
                } else {
                    successBox.textContent = 'Signature saved for that signer. Reloading for the next one...';
                    successBox.classList.remove('d-none');
                    setTimeout(() => location.reload(), 1200);
                }
            } else {
                showError(data.error || 'Failed to save signature.');
                useCropBtn.disabled = false;
                useCropBtn.innerHTML = '<i class="bi bi-check2-square me-1"></i> Use this crop';
            }
        } catch (err) {
            showError('Network error: ' + err.message);
            useCropBtn.disabled = false;
            useCropBtn.innerHTML = '<i class="bi bi-check2-square me-1"></i> Use this crop';
        }
    });
})();
</script>
<?php endif; ?>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/agent_layout.php';
