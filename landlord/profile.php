<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('landlord');

$pdo = db();
$userId = current_user_id();

$errors = [];

$stmt = $pdo->prepare("
    SELECT l.*, u.email, u.created_at AS joined_at
      FROM landlords l
      JOIN users u ON u.id = l.user_id
     WHERE l.user_id = ?
");
$stmt->execute([$userId]);
$landlord = $stmt->fetch();

if (!$landlord) {
    die('Landlord profile not found.');
}

// Property stats
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total,
        SUM(status = 'available') AS available_count,
        SUM(status = 'rented') AS rented_count,
        SUM(status = 'reserved') AS reserved_count,
        SUM(status = 'pending_approval') AS pending_count
      FROM properties
     WHERE landlord_id = ?
");
$stmt->execute([$userId]);
$stats = $stmt->fetch() ?: ['total' => 0, 'available_count' => 0, 'rented_count' => 0, 'reserved_count' => 0, 'pending_count' => 0];

$isEditMode = isset($_GET['edit']) && $_GET['edit'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $full_name      = trim($_POST['full_name'] ?? '');
    $preferred_name = trim($_POST['preferred_name'] ?? '');
    $ic_no          = trim($_POST['ic_no'] ?? '');
    $phone          = trim($_POST['phone'] ?? '');
    $allow_whatsapp = isset($_POST['allow_whatsapp']) ? 1 : 0;
    $address        = trim($_POST['address'] ?? '');

    if ($full_name === '') $errors['full_name'] = 'Full name required';
    if ($phone === '')     $errors['phone']     = 'Phone required';
    if ($ic_no !== '') {
        $icClean = preg_replace('/[^0-9]/', '', $ic_no);
        if (strlen($icClean) !== 12) {
            $errors['ic_no'] = 'IC must be 12 digits (e.g. 030823-02-0465)';
        }
    } else {
        $errors['ic_no'] = 'IC required';
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE landlords
                   SET full_name = ?,
                       preferred_name = ?,
                       ic_no = ?,
                       phone = ?,
                       allow_whatsapp = ?,
                       address = ?
                 WHERE user_id = ?
            ");
            $stmt->execute([
                $full_name, $preferred_name, $ic_no, $phone,
                $allow_whatsapp, $address ?: null, $userId,
            ]);

            set_flash('success', 'Profile updated successfully.');
            header('Location: /rentbridge/landlord/profile.php');
            exit;
        } catch (Throwable $e) {
            $errors['general'] = 'Failed to save: ' . $e->getMessage();
        }
    }

    $landlord = array_merge($landlord, [
        'full_name' => $full_name,
        'preferred_name' => $preferred_name,
        'ic_no' => $ic_no,
        'phone' => $phone,
        'allow_whatsapp' => $allow_whatsapp,
        'address' => $address,
    ]);
    $isEditMode = true;
}

$pageTitle     = 'My Profile';
$activeNav     = 'profile';
$showPageTitle = false;

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-1" style="font-family:'Fraunces',serif;">My Profile</h1>
        <p class="text-secondary mb-0">
            Member since <?= e(date('M Y', strtotime($landlord['joined_at']))) ?>
            <?php if ((int)$landlord['verified'] === 1): ?>
                · <span class="badge bg-success"><i class="bi bi-patch-check-fill"></i> Verified</span>
            <?php else: ?>
                · <span class="badge bg-warning text-dark">Pending verification</span>
            <?php endif; ?>
        </p>
    </div>
    <?php if (!$isEditMode): ?>
        <a href="?edit=1" class="btn btn-primary">
            <i class="bi bi-pencil-square me-1"></i> Edit profile
        </a>
    <?php endif; ?>
</div>

<?php if (!empty($errors['general'])): ?>
    <div class="alert alert-danger"><?= e($errors['general']) ?></div>
<?php endif; ?>

<!-- PROPERTY STATS (always visible) -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="bg-white border rounded-3 p-3 text-center">
            <div class="text-secondary small text-uppercase">Total properties</div>
            <strong class="fs-3 text-emerald"><?= (int)$stats['total'] ?></strong>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="bg-white border rounded-3 p-3 text-center">
            <div class="text-secondary small text-uppercase">Available</div>
            <strong class="fs-3"><?= (int)$stats['available_count'] ?></strong>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="bg-white border rounded-3 p-3 text-center">
            <div class="text-secondary small text-uppercase">Rented</div>
            <strong class="fs-3"><?= (int)$stats['rented_count'] ?></strong>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="bg-white border rounded-3 p-3 text-center">
            <div class="text-secondary small text-uppercase">Pending review</div>
            <strong class="fs-3"><?= (int)$stats['pending_count'] ?></strong>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/avatar.php'; ?>

<?php if ($isEditMode): ?>
    <!-- EDIT MODE -->
    <form method="POST">
        <?= csrf_field() ?>

        <!-- AVATAR -->
        <div class="bg-white border rounded-3 p-4 mb-3">
            <h6 class="text-secondary text-uppercase small mb-3">Profile photo</h6>
            <div class="d-flex align-items-center gap-4">
                <?php render_avatar($landlord['avatar_path'] ?? null, $landlord['full_name'], 96); ?>
                <div class="flex-grow-1">
                    <form method="POST" action="/rentbridge/auth/avatar_upload.php"
                        enctype="multipart/form-data" class="d-flex gap-2 align-items-center">
                        <?= csrf_field() ?>
                        <input type="file" name="avatar" class="form-control form-control-sm"
                            accept="image/jpeg,image/png,image/webp" required
                            style="max-width: 320px;">
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-upload me-1"></i> Upload
                        </button>
                    </form>
                    <small class="text-secondary d-block mt-2">
                        JPG, PNG, or WebP · max 5MB · square images look best
                    </small>
                </div>
            </div>
        </div>

        <div class="bg-white border rounded-3 p-4 mb-3">
            <h6 class="text-secondary text-uppercase small mb-3">Basic info</h6>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Full name <small class="text-danger">*</small></label>
                    <input type="text" name="full_name"
                           class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>"
                           value="<?= e($landlord['full_name']) ?>" required>
                    <?php if (isset($errors['full_name'])): ?><div class="invalid-feedback"><?= e($errors['full_name']) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Preferred name <small class="text-secondary fw-normal">(nickname)</small></label>
                    <input type="text" name="preferred_name" class="form-control"
                           value="<?= e($landlord['preferred_name']) ?>" placeholder="e.g. Mr. Tan">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">NRIC (IC number) <small class="text-danger">*</small></label>
                    <input type="text" name="ic_no"
                           class="form-control <?= isset($errors['ic_no']) ? 'is-invalid' : '' ?>"
                           value="<?= e($landlord['ic_no']) ?>" placeholder="030823-02-0465" required>
                    <?php if (isset($errors['ic_no'])): ?><div class="invalid-feedback"><?= e($errors['ic_no']) ?></div><?php endif; ?>
                    <small class="text-secondary">Used on tenancy contracts.</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Phone <small class="text-danger">*</small></label>
                    <input type="text" name="phone"
                           class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                           value="<?= e($landlord['phone']) ?>" required>
                    <?php if (isset($errors['phone'])): ?><div class="invalid-feedback"><?= e($errors['phone']) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" class="form-control" value="<?= e($landlord['email']) ?>" disabled>
                    <small class="text-secondary">Email cannot be changed here.</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Home address <small class="text-secondary fw-normal">(optional)</small></label>
                    <input type="text" name="address" class="form-control"
                           value="<?= e($landlord['address'] ?? '') ?>"
                           placeholder="Your mailing address">
                </div>
            </div>

            <div class="form-check mt-3">
                <input type="checkbox" name="allow_whatsapp" id="whatsapp" class="form-check-input"
                       <?= (int)$landlord['allow_whatsapp'] === 1 ? 'checked' : '' ?>>
                <label for="whatsapp" class="form-check-label fw-semibold">
                    Allow students to contact me via WhatsApp
                </label>
                <div class="small text-secondary">
                    When checked, your phone number is shown as a "WhatsApp me" link on your property pages.
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="/rentbridge/landlord/profile.php" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-circle me-1"></i> Save changes
            </button>
        </div>
    </form>

<?php else: ?>
    <!-- VIEW MODE -->
    <?php require_once __DIR__ . '/../includes/avatar.php'; ?>
    <div class="bg-white border rounded-3 p-4 mb-3 d-flex align-items-center gap-4 flex-wrap">
        <?php render_avatar($landlord['avatar_path'] ?? null, $landlord['full_name'] ?? 'User', 88); ?>
        <div>
            <h4 class="mb-0 fw-bold"><?= e($landlord['full_name']) ?></h4>
            <?php if (!empty($landlord['preferred_name'])): ?>
                <p class="text-secondary mb-1 small">Known as <?= e($landlord['preferred_name']) ?></p>
            <?php endif; ?>
            <p class="text-secondary small mb-0"><?= e($landlord['email']) ?></p>
        </div>
    </div>

    <div class="bg-white border rounded-3 p-4 mb-3">
        <h6 class="text-secondary text-uppercase small mb-3">Basic info</h6>
        <table class="table table-sm mb-0">
            <tr><th class="text-secondary" style="width:200px;">Full name</th><td><?= e($landlord['full_name']) ?></td></tr>
            <?php if (!empty($landlord['preferred_name'])): ?>
                <tr><th class="text-secondary">Preferred name</th><td><?= e($landlord['preferred_name']) ?></td></tr>
            <?php endif; ?>
            <tr><th class="text-secondary">NRIC</th><td><code><?= e($landlord['ic_no']) ?></code></td></tr>
            <tr><th class="text-secondary">Email</th><td><?= e($landlord['email']) ?></td></tr>
            <tr><th class="text-secondary">Phone</th><td>
                <?= e($landlord['phone']) ?>
                <?php if ((int)($landlord['allow_whatsapp'] ?? 0) === 1):
                    $waPhone = preg_replace('/\D/', '', $landlord['phone'] ?? '');
                    if ($waPhone && str_starts_with($waPhone, '0')) $waPhone = '60' . ltrim($waPhone, '0');
                    if ($waPhone): ?>
                <a href="https://wa.me/<?= e($waPhone) ?>" target="_blank" rel="noopener"
                   class="btn btn-success btn-sm rounded-pill ms-2 py-0 px-2" title="Open WhatsApp">
                    <i class="bi bi-whatsapp" style="font-size:.8rem;"></i>
                </a>
                <?php endif; endif; ?>
            </td></tr>
            <tr><th class="text-secondary">Home address</th><td><?= e($landlord['address'] ?: '—') ?></td></tr>
        </table>
    </div>

    <div class="bg-white border rounded-3 p-4 mb-3">
    <h6 class="text-secondary text-uppercase small mb-3">Account security</h6>
    <p class="small text-secondary mb-3">
        For security, password changes require email verification.
        A 6-digit code will be sent to your registered email.
    </p>
    <button class="btn btn-outline-primary btn-sm"
            data-bs-toggle="modal" data-bs-target="#passwordChangeModal">
        <i class="bi bi-key me-1"></i> Change password
    </button>
</div>

<!-- PASSWORD CHANGE MODAL -->
<div class="modal fade" id="passwordChangeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-shield-lock text-emerald me-2"></i>
                    Change password
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Step 1: Request code -->
                <div id="pwdStep1">
                    <p class="small">
                        Click below to send a 6-digit verification code to your email.
                    </p>
                    <button id="pwdSendCodeBtn" class="btn btn-primary w-100">
                        <i class="bi bi-envelope-arrow-up me-1"></i> Send verification code
                    </button>
                    <div id="pwdSendStatus" class="small mt-2"></div>
                </div>

                <!-- Step 2: Enter code + new password -->
                <div id="pwdStep2" class="d-none">
                    <div class="alert alert-light border small mb-3" id="pwdCodeSentMsg">
                        ✓ Code sent. Check your email.
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Verification code</label>
                        <input type="text" id="pwdCodeInput" class="form-control text-center"
                               style="letter-spacing:6px; font-size:1.3rem; font-family:monospace;"
                               maxlength="6" placeholder="123456" inputmode="numeric">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">New password</label>
                        <input type="password" id="pwdNewInput" class="form-control"
                               minlength="8" placeholder="At least 8 chars + numbers">
                        <small class="text-secondary">8+ characters, must include letters and numbers.</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Confirm new password</label>
                        <input type="password" id="pwdConfirmInput" class="form-control"
                               minlength="8">
                    </div>

                    <div id="pwdError" class="alert alert-danger small d-none"></div>

                    <div class="d-flex gap-2">
                        <button id="pwdSubmitBtn" class="btn btn-primary flex-grow-1">
                            <i class="bi bi-check-circle me-1"></i> Change password
                        </button>
                        <button id="pwdResendBtn" class="btn btn-outline-secondary btn-sm" title="Resend code">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 3: Success -->
                <div id="pwdStep3" class="d-none text-center py-3">
                    <i class="bi bi-check-circle-fill text-success" style="font-size: 3rem;"></i>
                    <h5 class="mt-3">Password changed successfully</h5>
                    <p class="text-secondary small">
                        Your password has been updated. Use the new password next time you log in.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const csrfToken = '<?= csrf_token() ?>';
    const step1 = document.getElementById('pwdStep1');
    const step2 = document.getElementById('pwdStep2');
    const step3 = document.getElementById('pwdStep3');
    const sendBtn = document.getElementById('pwdSendCodeBtn');
    const sendStatus = document.getElementById('pwdSendStatus');
    const codeSentMsg = document.getElementById('pwdCodeSentMsg');
    const codeInput = document.getElementById('pwdCodeInput');
    const newInput = document.getElementById('pwdNewInput');
    const confirmInput = document.getElementById('pwdConfirmInput');
    const submitBtn = document.getElementById('pwdSubmitBtn');
    const resendBtn = document.getElementById('pwdResendBtn');
    const errorBox = document.getElementById('pwdError');

    async function sendCode() {
        sendBtn.disabled = true;
        resendBtn && (resendBtn.disabled = true);
        sendStatus.textContent = 'Sending...';

        try {
            const resp = await fetch('/rentbridge/auth/password_send_code.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({_csrf: csrfToken}),
            });
            const data = await resp.json();

            if (data.ok) {
                codeSentMsg.innerHTML = '✓ ' + data.message;
                step1.classList.add('d-none');
                step2.classList.remove('d-none');
                codeInput.focus();
            } else {
                sendStatus.innerHTML = '<span class="text-danger">' + data.error + '</span>';
            }
        } catch (err) {
            sendStatus.innerHTML = '<span class="text-danger">Network error: ' + err.message + '</span>';
        } finally {
            sendBtn.disabled = false;
            resendBtn && (resendBtn.disabled = false);
        }
    }

    async function submitChange() {
        errorBox.classList.add('d-none');

        const code = codeInput.value.trim();
        const newPwd = newInput.value;
        const confirmPwd = confirmInput.value;

        if (!code || !newPwd || !confirmPwd) {
            showError('Please fill in all fields.');
            return;
        }
        if (newPwd !== confirmPwd) {
            showError('Passwords do not match.');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="bi bi-hourglass me-1"></i> Verifying...';

        try {
            const resp = await fetch('/rentbridge/auth/password_change.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    _csrf: csrfToken,
                    code: code,
                    new_password: newPwd,
                    confirm_password: confirmPwd,
                }),
            });
            const data = await resp.json();

            if (data.ok) {
                step2.classList.add('d-none');
                step3.classList.remove('d-none');
            } else {
                showError(data.error);
            }
        } catch (err) {
            showError('Network error: ' + err.message);
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Change password';
        }
    }

    function showError(msg) {
        errorBox.textContent = msg;
        errorBox.classList.remove('d-none');
    }

    sendBtn.addEventListener('click', sendCode);
    submitBtn.addEventListener('click', submitChange);
    resendBtn.addEventListener('click', sendCode);

    // Reset modal on close
    document.getElementById('passwordChangeModal').addEventListener('hidden.bs.modal', function() {
        step1.classList.remove('d-none');
        step2.classList.add('d-none');
        step3.classList.add('d-none');
        sendStatus.textContent = '';
        codeInput.value = '';
        newInput.value = '';
        confirmInput.value = '';
        errorBox.classList.add('d-none');
    });
})();
</script>
<?php endif; ?>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/landlord_layout.php';