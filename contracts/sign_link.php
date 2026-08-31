<?php
/**
 * Link-based contract signing for co-tenants WITHOUT an account.
 * Authenticated by a secure per-co-tenant token (no login required).
 * The token holder signs on a signature pad; the signature is embedded in the
 * same contract as the account holders' e-signatures.
 */
require_once __DIR__ . '/../includes/auth.php';        // db(), e(), csrf_field(), verify_csrf() — no login needed
require_once __DIR__ . '/../includes/contracts.php';

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');

function sign_link_page(string $title, string $bodyHtml): void {
    ?><!DOCTYPE html><html lang="en"><head><meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> · RentBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    </head><body style="background:#F5F4EF;"><div class="container py-5"><div class="row justify-content-center">
    <div class="col-lg-8"><?= $bodyHtml ?></div></div></div></body></html><?php
    exit;
}

if ($token === '') {
    sign_link_page('Invalid link', '<div class="alert alert-danger">This signing link is invalid.</div>');
}

$pdo  = db();
$stmt = $pdo->prepare("
    SELECT ct.id, ct.full_name, ct.ic_number, ct.status AS ct_status, ct.tenancy_id,
           c.id AS contract_id, c.contract_code, c.status AS contract_status,
           p.title AS property_title
      FROM co_tenants ct
      JOIN contracts  c ON c.tenancy_id = ct.tenancy_id
      JOIN properties p ON p.id = c.property_id
     WHERE ct.sign_token = ? LIMIT 1
");
$stmt->execute([$token]);
$info = $stmt->fetch();

if (!$info) {
    sign_link_page('Invalid link', '<div class="alert alert-danger"><i class="bi bi-x-circle me-1"></i> This signing link is invalid or has expired.</div>');
}

$done = '';
if ($info['ct_status'] === 'signed') {
    $done = '<div class="alert alert-success"><i class="bi bi-check-circle me-1"></i> You have already signed this contract. Thank you.</div>';
} elseif ($info['contract_status'] !== 'pending_signatures') {
    $done = '<div class="alert alert-info"><i class="bi bi-info-circle me-1"></i> This contract is no longer accepting signatures.</div>';
}
if ($done !== '') {
    sign_link_page('Contract signing', $done);
}

// Is it this signer's turn?
$contract = $pdo->prepare('SELECT * FROM contracts WHERE id = ? LIMIT 1');
$contract->execute([(int)$info['contract_id']]);
$contract = $contract->fetch();
$next = contract_next_signer($contract);
$isTurn = ($next['role'] === 'tenant' && (int)$next['co_tenant_id'] === (int)$info['id']);

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $res = apply_signature_by_token($token, $_POST['signature_data'] ?? '');
    if ($res['success']) {
        sign_link_page('Signed',
            '<div class="alert alert-success"><i class="bi bi-check-circle me-1"></i> Thank you, ' . e($info['full_name'])
            . '. Your signature has been recorded'
            . ($res['all_signed'] ? ' and the contract is now active.' : '.') . '</div>'
            . '<p class="text-secondary small">You may close this page.</p>');
    }
    $errors['general'] = $res['message'];
    if (!empty($res['wait'])) $isTurn = false;
}

ob_start();
?>
<h4 class="mb-1">Sign your tenancy contract</h4>
<p class="text-secondary">Contract <code><?= e($info['contract_code']) ?></code> · <?= e($info['property_title']) ?></p>

<div class="bg-white border rounded-3 p-4 p-md-5">
    <p class="mb-3"><strong>Signer:</strong> <?= e($info['full_name']) ?>
        &nbsp;·&nbsp; <span class="text-secondary">NRIC <?= e($info['ic_number']) ?></span></p>

    <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-<?= $isTurn ? 'danger' : 'warning' ?>"><i class="bi bi-exclamation-circle me-1"></i> <?= e($errors['general']) ?></div>
    <?php endif; ?>

    <?php if (!$isTurn): ?>
        <div class="alert alert-info mb-0">
            <i class="bi bi-hourglass-split me-1"></i>
            It is not your turn to sign yet. Please wait until the earlier signer(s) have signed, then reopen this link.
        </div>
    <?php else: ?>
        <div class="alert border-0" style="background:#F5F4EF;">
            <i class="bi bi-info-circle me-1"></i>
            By signing below you confirm you have read and agree to the tenancy terms. Your signature, timestamp, and IP address are recorded.
        </div>
        <form method="POST" id="sign-form" novalidate>
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= e($token) ?>">
            <input type="hidden" name="signature_data" id="signature_data" value="">
            <label class="form-label fw-semibold mb-2">Your signature</label>
            <div style="border:1px dashed #B9C2CC; border-radius:8px; background:#fff;">
                <canvas id="signature-pad" width="700" height="220" style="width:100%; touch-action:none;"></canvas>
            </div>
            <small class="text-secondary d-block mt-2">Draw inside the box using your mouse or finger.</small>
            <div class="d-flex gap-2 mt-4">
                <button type="button" id="btn-clear" class="btn btn-outline-secondary"><i class="bi bi-eraser"></i> Clear</button>
                <button type="submit" class="btn btn-success flex-grow-1"><i class="bi bi-pen me-1"></i> Submit signature</button>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php if ($isTurn): ?>
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script>
(function () {
    const canvas = document.getElementById('signature-pad');
    const dataInput = document.getElementById('signature_data');
    const form = document.getElementById('sign-form');
    function resize() {
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        canvas.width = canvas.offsetWidth * ratio;
        canvas.height = canvas.offsetHeight * ratio;
        canvas.getContext('2d').scale(ratio, ratio);
        pad.clear();
    }
    const pad = new SignaturePad(canvas, { backgroundColor: 'rgba(255,255,255,0)', penColor: '#0F2C52', minWidth: 1.2, maxWidth: 2.8 });
    window.addEventListener('resize', resize); resize();
    document.getElementById('btn-clear').addEventListener('click', () => pad.clear());
    form.addEventListener('submit', function (e) {
        if (pad.isEmpty()) { e.preventDefault(); alert('Please draw your signature before submitting.'); return; }
        dataInput.value = pad.toDataURL('image/png');
    });
})();
</script>
<?php endif; ?>
<?php
$body = ob_get_clean();
sign_link_page('Sign contract', $body);
