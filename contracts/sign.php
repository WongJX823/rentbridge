<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/contracts.php';

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/contracts.php';
require_login();

$contractId = (int)($_GET['id'] ?? $_POST['contract_id'] ?? 0);
if ($contractId <= 0) {
    http_response_code(400);
    die('Invalid contract ID.');
}

$pdo = db();
$stmt = $pdo->prepare('SELECT * FROM contracts WHERE id = ? LIMIT 1');
$stmt->execute([$contractId]);
$contract = $stmt->fetch();

if (!$contract) {
    http_response_code(404);
    die('Contract not found.');
}

// Must be a party
if (!contract_can_view($contract, current_user_id(), current_role())) {
    http_response_code(403);
    die('You are not a party to this contract.');
}

// Must be their turn
if (!contract_can_sign($contract, current_user_id())) {
    set_flash('info', 'It is not your turn to sign right now.');
    header('Location: /rentbridge/contracts/view.php?id=' . $contractId);
    exit;
}

// Determine which role for the page label
if      (current_user_id() === (int)$contract['student_id'])  { $role = 'student';  $roleLabel = 'Tenant'; }
elseif  (current_user_id() === (int)$contract['landlord_id']) { $role = 'landlord'; $roleLabel = 'Landlord'; }
else                                                           { $role = 'agent';    $roleLabel = 'Witness Agent'; }

$errors = [];

// ---- HANDLE SUBMISSION ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $dataUrl = $_POST['signature_data'] ?? '';
    if ($dataUrl === '' || $dataUrl === 'data:,') {
        $errors['general'] = 'Please draw your signature before submitting.';
    } else {
        $result = apply_signature($contractId, current_user_id(), $dataUrl);
        if ($result['success']) {
            set_flash('success',
                $result['all_signed']
                    ? 'All signatures collected — contract is now active! 🎉'
                    : 'Your signature has been recorded.'
            );
            header('Location: /rentbridge/contracts/view.php?id=' . $contractId);
            exit;
        } else {
            $errors['general'] = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign contract · RentBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body style="background: var(--rb-cream);">

<?php include '../includes/header.php'; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <p class="small mb-3">
                <a href="/rentbridge/contracts/view.php?id=<?= (int)$contractId ?>"
                   class="text-secondary text-decoration-none">
                    <i class="bi bi-arrow-left"></i> Back to contract
                </a>
            </p>

            <h1 class="mb-1">Sign as <em><?= e($roleLabel) ?></em></h1>
            <p class="text-secondary mb-4">
                Contract <code><?= e($contract['contract_code']) ?></code> ·
                Draw your signature below, then click Submit.
            </p>

            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-circle"></i> <?= e($errors['general']) ?>
                </div>
            <?php endif; ?>

            <div class="bg-white border rounded-3 p-4 p-md-5">

                <div class="alert alert-info border-0" style="background:var(--rb-cream);">
                    <i class="bi bi-info-circle"></i>
                    By signing below, you confirm you have read and agree to the terms in this contract.
                    Your signature, the timestamp, and your IP address will be recorded for audit purposes.
                </div>

                <form method="POST" id="sign-form" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="contract_id" value="<?= (int)$contractId ?>">
                    <input type="hidden" name="signature_data" id="signature_data" value="">

                    <label class="form-label fw-semibold mb-2">Your signature</label>
                    <div class="signature-pad-wrapper">
                        <canvas id="signature-pad"
                                width="700" height="220"
                                style="touch-action: none;">
                        </canvas>
                    </div>
                    <small class="text-secondary d-block mt-2">
                        Draw inside the box using your mouse or finger.
                    </small>

                    <div class="d-flex gap-2 mt-4">
                        <button type="button" id="btn-clear" class="btn btn-ghost">
                            <i class="bi bi-eraser"></i> Clear
                        </button>
                        <button type="submit" id="btn-submit" class="btn btn-success flex-grow-1">
                            <i class="bi bi-pen me-1"></i> Submit signature
                        </button>
                    </div>
                </form>
            </div>

            <p class="text-center text-secondary small mt-4 mb-0">
                <i class="bi bi-shield-check"></i>
                Your signature is stored as a PNG image with timestamp and IP. This is the legally-attestable record.
            </p>
        </div>
    </div>
</div>

<!-- signature_pad library -->
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script>
(function () {
    const canvas    = document.getElementById('signature-pad');
    const dataInput = document.getElementById('signature_data');
    const form      = document.getElementById('sign-form');
    const clearBtn  = document.getElementById('btn-clear');

    // Make canvas resolution match its display size, for crisper lines
    function resizeCanvas() {
        const ratio  = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width  = canvas.offsetWidth  * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext('2d').scale(ratio, ratio);
        pad.clear();   // resize wipes the canvas
    }

    const pad = new SignaturePad(canvas, {
        backgroundColor: 'rgba(255, 255, 255, 0)',
        penColor: '#0F2C52',
        minWidth: 1.2,
        maxWidth: 2.8,
    });

    window.addEventListener('resize', resizeCanvas);
    resizeCanvas();

    clearBtn.addEventListener('click', function () { pad.clear(); });

    form.addEventListener('submit', function (e) {
        if (pad.isEmpty()) {
            e.preventDefault();
            alert('Please draw your signature before submitting.');
            return;
        }
        // Trim whitespace around the signature for a cleaner image
        dataInput.value = pad.toDataURL('image/png');
    });
})();
</script>
</body>
</html>