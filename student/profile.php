<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('student');

$pdo = db();
$userId = current_user_id();

$errors = [];
$flashSuccess = '';

// Fetch existing data
$stmt = $pdo->prepare("
    SELECT s.*, u.email, u.created_at AS joined_at
      FROM students s
      JOIN users u ON u.id = s.user_id
     WHERE s.user_id = ?
");
$stmt->execute([$userId]);
$student = $stmt->fetch();

if (!$student) {
    die('Student profile not found.');
}

$isEditMode = isset($_GET['edit']) && $_GET['edit'] === '1';

// Handle update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $full_name      = trim($_POST['full_name'] ?? '');
    $preferred_name = trim($_POST['preferred_name'] ?? '');
    $matric_no      = trim($_POST['matric_no'] ?? '');
    $university     = trim($_POST['university'] ?? 'UTeM');
    $phone          = trim($_POST['phone'] ?? '');
    $looking        = isset($_POST['looking_for_housing']) ? 1 : 0;
    $allow_whatsapp = isset($_POST['allow_whatsapp']) ? 1 : 0;
    $pref_city      = trim($_POST['housing_pref_city'] ?? '');
    $pref_max_rent  = $_POST['housing_pref_max_rent'] ?? '';
    $pref_move_in   = trim($_POST['housing_pref_move_in'] ?? '');
    $housing_bio    = trim($_POST['housing_bio'] ?? '');

    if ($full_name === '')  $errors['full_name'] = 'Full name required';
    if ($matric_no === '')  $errors['matric_no'] = 'Matric number required';
    if ($phone === '')      $errors['phone']     = 'Phone required';
    if (strlen($housing_bio) > 255) $errors['housing_bio'] = 'Max 255 characters';

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE students
                   SET full_name = ?,
                       preferred_name = ?,
                       matric_no = ?,
                       university = ?,
                       phone = ?,
                       allow_whatsapp = ?,
                       looking_for_housing = ?,
                       housing_pref_city = ?,
                       housing_pref_max_rent = ?,
                       housing_pref_move_in = ?,
                       housing_bio = ?
                 WHERE user_id = ?
            ");
            $stmt->execute([
                $full_name, $preferred_name, $matric_no, $university, $phone,
                $allow_whatsapp,
                $looking,
                $pref_city ?: null,
                $pref_max_rent !== '' ? (float)$pref_max_rent : null,
                $pref_move_in ?: null,
                $housing_bio ?: null,
                $userId,
            ]);

            set_flash('success', 'Profile updated successfully.');
            header('Location: /rentbridge/student/profile.php');
            exit;
        } catch (Throwable $e) {
            $errors['general'] = 'Failed to save: ' . $e->getMessage();
        }
    }

    // Re-fetch for the form
    $student = array_merge($student, [
        'full_name' => $full_name,
        'preferred_name' => $preferred_name,
        'matric_no' => $matric_no,
        'university' => $university,
        'phone' => $phone,
        'allow_whatsapp' => $allow_whatsapp,
        'looking_for_housing' => $looking,
        'housing_pref_city' => $pref_city,
        'housing_pref_max_rent' => $pref_max_rent,
        'housing_pref_move_in' => $pref_move_in,
        'housing_bio' => $housing_bio,
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
            Member since <?= e(date('M Y', strtotime($student['joined_at']))) ?>
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

<?php if ($isEditMode): ?>
    <!-- EDIT MODE -->

    <?php require_once __DIR__ . '/../includes/avatar.php'; ?>

    <!-- AVATAR (separate form — must not nest inside the profile form) -->
    <div class="bg-white border rounded-3 p-4 mb-3">
        <h6 class="text-secondary text-uppercase small mb-3">Profile photo</h6>
        <div class="d-flex align-items-center gap-4">
            <?php render_avatar($student['avatar_path'] ?? null, $student['full_name'] ?? 'User', 96); ?>
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

    <form method="POST">
        <?= csrf_field() ?>

        <div class="bg-white border rounded-3 p-4 mb-3">
            <h6 class="text-secondary text-uppercase small mb-3">Basic info</h6>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Full name <small class="text-danger">*</small></label>
                    <input type="text" name="full_name"
                           class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>"
                           value="<?= e($student['full_name']) ?>" required>
                    <?php if (isset($errors['full_name'])): ?><div class="invalid-feedback"><?= e($errors['full_name']) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Preferred name <small class="text-secondary fw-normal">(nickname)</small></label>
                    <input type="text" name="preferred_name" class="form-control"
                           value="<?= e($student['preferred_name']) ?>" placeholder="e.g. Jia">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Matric number <small class="text-danger">*</small></label>
                    <input type="text" name="matric_no"
                           class="form-control <?= isset($errors['matric_no']) ? 'is-invalid' : '' ?>"
                           value="<?= e($student['matric_no']) ?>" required>
                    <?php if (isset($errors['matric_no'])): ?><div class="invalid-feedback"><?= e($errors['matric_no']) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">University</label>
                    <input type="text" name="university" class="form-control"
                           value="<?= e($student['university']) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Phone <small class="text-danger">*</small></label>
                    <input type="text" name="phone"
                           class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                           value="<?= e($student['phone']) ?>" required>
                    <?php if (isset($errors['phone'])): ?><div class="invalid-feedback"><?= e($errors['phone']) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" class="form-control" value="<?= e($student['email']) ?>" disabled>
                    <small class="text-secondary">Email cannot be changed here.</small>
                </div>
            </div>

            <div class="form-check mt-3">
                <input type="checkbox" name="allow_whatsapp" id="allowWa" class="form-check-input"
                       <?= (int)($student['allow_whatsapp'] ?? 0) === 1 ? 'checked' : '' ?>>
                <label for="allowWa" class="form-check-label fw-semibold">
                    Allow contact via WhatsApp
                </label>
                <div class="small text-secondary">
                    When checked, a WhatsApp shortcut is shown next to your phone number.
                </div>
            </div>
        </div>

        <div class="bg-white border rounded-3 p-4 mb-3">
            <h6 class="text-secondary text-uppercase small mb-3">Housing preferences</h6>

            <div class="form-check mb-3">
                <input type="checkbox" name="looking_for_housing" id="looking" class="form-check-input"
                       <?= (int)$student['looking_for_housing'] === 1 ? 'checked' : '' ?>>
                <label for="looking" class="form-check-label fw-semibold">
                    I'm currently looking for housing
                </label>
                <div class="small text-secondary">
                    When checked, you'll appear in housemate-search results.
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Preferred city</label>
                    <input type="text" name="housing_pref_city" class="form-control"
                           value="<?= e($student['housing_pref_city'] ?? '') ?>" placeholder="e.g. Ayer Keroh">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Max rent (RM)</label>
                    <input type="number" name="housing_pref_max_rent" class="form-control" min="0" step="50"
                           value="<?= e($student['housing_pref_max_rent'] ?? '') ?>" placeholder="e.g. 500">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Move-in date</label>
                    <input type="date" name="housing_pref_move_in" class="form-control"
                           value="<?= e($student['housing_pref_move_in'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label class="form-label fw-semibold">
                        About you <small class="text-secondary fw-normal">(255 chars max)</small>
                    </label>
                    <textarea name="housing_bio" rows="2" maxlength="255"
                              class="form-control <?= isset($errors['housing_bio']) ? 'is-invalid' : '' ?>"
                              placeholder="A short bio for potential housemates — your habits, study schedule, hobbies."><?= e($student['housing_bio'] ?? '') ?></textarea>
                    <?php if (isset($errors['housing_bio'])): ?><div class="invalid-feedback"><?= e($errors['housing_bio']) ?></div><?php endif; ?>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="/rentbridge/student/profile.php" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-circle me-1"></i> Save changes
            </button>
        </div>
    </form>

<?php else: ?>
    <!-- VIEW MODE -->

    <!-- Avatar -->
    <?php require_once __DIR__ . '/../includes/avatar.php'; ?>
    <div class="bg-white border rounded-3 p-4 mb-3 d-flex align-items-center gap-4 flex-wrap">
        <?php render_avatar($student['avatar_path'] ?? null, $student['full_name'] ?? 'User', 88); ?>
        <div>
            <h4 class="mb-0 fw-bold"><?= e($student['full_name']) ?></h4>
            <?php if (!empty($student['preferred_name'])): ?>
                <p class="text-secondary mb-1 small">Known as <?= e($student['preferred_name']) ?></p>
            <?php endif; ?>
            <p class="text-secondary small mb-0"><?= e($student['university']) ?> · <?= e($student['email']) ?></p>
        </div>
    </div>

    <div class="bg-white border rounded-3 p-4 mb-3">
        <h6 class="text-secondary text-uppercase small mb-3">Basic info</h6>
        <table class="table table-sm mb-0">
            <tr><th class="text-secondary" style="width:200px;">Full name</th><td><?= e($student['full_name']) ?></td></tr>
            <?php if (!empty($student['preferred_name'])): ?>
                <tr><th class="text-secondary">Preferred name</th><td><?= e($student['preferred_name']) ?></td></tr>
            <?php endif; ?>
            <tr><th class="text-secondary">Matric number</th><td><code><?= e($student['matric_no']) ?></code></td></tr>
            <tr><th class="text-secondary">University</th><td><?= e($student['university']) ?></td></tr>
            <tr><th class="text-secondary">Email</th><td><?= e($student['email']) ?></td></tr>
            <tr><th class="text-secondary">Phone</th><td>
                <?= e($student['phone']) ?>
                <?php if ((int)($student['allow_whatsapp'] ?? 0) === 1):
                    $waPhone = preg_replace('/\D/', '', $student['phone'] ?? '');
                    if ($waPhone && str_starts_with($waPhone, '0')) $waPhone = '60' . ltrim($waPhone, '0');
                    if ($waPhone): ?>
                <a href="https://wa.me/<?= e($waPhone) ?>" target="_blank" rel="noopener"
                   class="btn btn-success btn-sm rounded-pill ms-2 py-0 px-2" title="Open WhatsApp">
                    <i class="bi bi-whatsapp" style="font-size:.8rem;"></i>
                </a>
                <?php endif; endif; ?>
            </td></tr>
        </table>
    </div>

    <div class="bg-white border rounded-3 p-4 mb-3">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h6 class="text-secondary text-uppercase small mb-0">Housing preferences</h6>
            <div class="d-flex align-items-center gap-2">
                <span class="small text-secondary" id="lookingLabel">
                    <?= (int)$student['looking_for_housing'] === 1 ? 'Actively looking' : 'Not looking' ?>
                </span>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" id="lookingToggle"
                           <?= (int)$student['looking_for_housing'] === 1 ? 'checked' : '' ?>
                           style="cursor:pointer;" title="Toggle find housemates">
                </div>
            </div>
        </div>
        <table class="table table-sm mb-0">
            <tr>
                <th class="text-secondary" style="width:200px;">Preferred city</th>
                <td><?= e($student['housing_pref_city'] ?: '—') ?></td>
            </tr>
            <tr>
                <th class="text-secondary">Max rent</th>
                <td><?= !empty($student['housing_pref_max_rent']) ? 'RM ' . number_format((float)$student['housing_pref_max_rent']) : '—' ?></td>
            </tr>
            <tr>
                <th class="text-secondary">Move-in date</th>
                <td><?= !empty($student['housing_pref_move_in']) ? e(date('d M Y', strtotime($student['housing_pref_move_in']))) : '—' ?></td>
            </tr>
            <tr>
                <th class="text-secondary">About you</th>
                <td><?= !empty($student['housing_bio']) ? nl2br(e($student['housing_bio'])) : '<span class="text-secondary">No bio yet</span>' ?></td>
            </tr>
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

<script>
(function() {
    const toggle = document.getElementById('lookingToggle');
    const label  = document.getElementById('lookingLabel');
    if (!toggle) return;
    toggle.addEventListener('change', async function() {
        const checked = this.checked;
        label.textContent = checked ? 'Actively looking' : 'Not looking';
        try {
            const resp = await fetch('/rentbridge/student/toggle_looking.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({_csrf: '<?= csrf_token() ?>', looking: checked ? 1 : 0}),
            });
            const data = await resp.json();
            if (!data.ok) throw new Error(data.error);
        } catch {
            this.checked = !checked;
            label.textContent = !checked ? 'Actively looking' : 'Not looking';
        }
    });
})();
</script>
<?php endif; ?>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/student_layout.php';