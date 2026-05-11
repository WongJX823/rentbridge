<?php
require_once __DIR__ . '/../includes/auth.php';

$errors  = [];
$success = false;
$old = [
    'email'      => '',
    'full_name'  => '',
    'staff_id'   => '',
    'department' => '',
    'phone'      => '',
];

// ---- HANDLE FORM SUBMIT ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $old['email']      = trim($_POST['email'] ?? '');
    $old['full_name']  = trim($_POST['full_name'] ?? '');
    $old['staff_id']   = strtoupper(trim($_POST['staff_id'] ?? ''));
    $old['department'] = trim($_POST['department'] ?? '');
    $old['phone']      = trim($_POST['phone'] ?? '');
    $password          = $_POST['password'] ?? '';
    $confirm           = $_POST['password_confirm'] ?? '';

    // Validate
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL))
        $errors['email'] = 'Enter a valid email address.';

    if ($old['full_name']  === '') $errors['full_name']  = 'Full name is required.';
    if ($old['staff_id']   === '') $errors['staff_id']   = 'Staff ID is required.';
    if ($old['department'] === '') $errors['department'] = 'Department is required.';
    if ($old['phone']      === '') $errors['phone']      = 'Phone number is required.';

    $pwError = validate_password($password);
    if ($pwError !== null)
        $errors['password'] = $pwError;

    if ($password !== $confirm)
        $errors['password_confirm'] = 'Passwords do not match.';

    // DB uniqueness check
    if (empty($errors)) {
        try {
            $pdo = db();

            $stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = ?');
            $stmt->execute([$old['email']]);
            if ($stmt->fetch()) $errors['email'] = 'This email is already registered.';

            $stmt = $pdo->prepare('SELECT 1 FROM agents WHERE staff_id = ?');
            $stmt->execute([$old['staff_id']]);
            if ($stmt->fetch()) $errors['staff_id'] = 'This staff ID is already registered.';

            // All valid — save
            if (empty($errors)) {
                $pdo->beginTransaction();

                // Note: status='pending' — admin must approve before they can log in
                $stmt = $pdo->prepare(
                    'INSERT INTO users (email, password_hash, primary_role, status)
                     VALUES (?, ?, "agent", "pending")'
                );
                $stmt->execute([
                    $old['email'],
                    password_hash($password, PASSWORD_BCRYPT)
                ]);
                $userId = (int)$pdo->lastInsertId();

                $stmt = $pdo->prepare(
                    'INSERT INTO agents (user_id, full_name, staff_id, department, phone)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $userId,
                    $old['full_name'],
                    $old['staff_id'],
                    $old['department'],
                    $old['phone']
                ]);

                $pdo->commit();

                $success = true;
                $old = ['email'=>'','full_name'=>'','staff_id'=>'','department'=>'','phone'=>''];
            }
        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $errors['general'] = 'Something went wrong: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign up · Agent · RentBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body style="background: var(--rb-cream);">

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">

            <div class="text-center mb-4">
                <a href="../index.php" class="text-decoration-none d-inline-flex align-items-center gap-2 mb-3" style="color:var(--rb-navy); font-family:'Fraunces',serif; font-size:1.6rem; font-weight:600;">
                    <span style="width:30px;height:30px;background:var(--rb-emerald);border-radius:8px;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:1rem;transform:rotate(-6deg);">R</span>
                    RentBridge
                </a>
            </div>

            <div class="bg-white rounded-4 p-4 p-md-5 border">

                <p class="small mb-2">
                    <a href="register.php" class="text-secondary text-decoration-none">
                        <i class="bi bi-arrow-left"></i> Pick a different role
                    </a>
                </p>

                <h1 class="mb-1">Agent application</h1>
                <p class="text-secondary mb-4">For UTeM staff only. Applications are reviewed by the admin.</p>

                <?php if ($success): ?>
                    <!-- ============ SUCCESS STATE ============ -->
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle-fill"></i>
                        <strong>Application submitted.</strong>
                        <p class="mb-0 mt-2">
                            Your account is <strong>pending admin approval</strong>.
                            You'll be notified by email once it's activated. Until then, you cannot log in.
                        </p>
                    </div>
                    <a href="../index.php" class="btn btn-primary w-100 mt-3">
                        <i class="bi bi-house ms-1"></i> Back to home
                    </a>

                <?php else: ?>
                    <!-- ============ FORM ============ -->

                    <?php if (!empty($errors['general'])): ?>
                        <div class="alert alert-danger"><?= e($errors['general']) ?></div>
                    <?php endif; ?>

                    <div class="alert alert-info border-0" style="background: var(--rb-cream);">
                        <i class="bi bi-info-circle"></i>
                        Agent accounts require admin approval before sign-in.
                    </div>

                    <form method="POST" novalidate>
                        <?= csrf_field() ?>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Full name</label>
                            <input type="text" name="full_name"
                                   class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>"
                                   value="<?= e($old['full_name']) ?>" required>
                            <?php if (isset($errors['full_name'])): ?>
                                <div class="invalid-feedback"><?= e($errors['full_name']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">Staff ID</label>
                                <input type="text" name="staff_id"
                                       class="form-control <?= isset($errors['staff_id']) ? 'is-invalid' : '' ?>"
                                       placeholder="UTM12345"
                                       value="<?= e($old['staff_id']) ?>" required>
                                <?php if (isset($errors['staff_id'])): ?>
                                    <div class="invalid-feedback"><?= e($errors['staff_id']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">Phone</label>
                                <input type="tel" name="phone"
                                       class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                                       placeholder="0123456789"
                                       value="<?= e($old['phone']) ?>" required>
                                <?php if (isset($errors['phone'])): ?>
                                    <div class="invalid-feedback"><?= e($errors['phone']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Department / Faculty</label>
                            <input type="text" name="department"
                                   class="form-control <?= isset($errors['department']) ? 'is-invalid' : '' ?>"
                                   placeholder="FTMK, HEP, etc."
                                   value="<?= e($old['department']) ?>" required>
                            <?php if (isset($errors['department'])): ?>
                                <div class="invalid-feedback"><?= e($errors['department']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">UTeM staff email</label>
                            <input type="email" name="email"
                                   class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                                   placeholder="name@utem.edu.my"
                                   value="<?= e($old['email']) ?>" required>
                            <?php if (isset($errors['email'])): ?>
                                <div class="invalid-feedback"><?= e($errors['email']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">Password</label>
                                <input type="password" name="password" id="pw-input"
                                    class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                                    placeholder="Min 8 chars, 3 of: aA1@" required>
                                <div id="pw-meter" class="rb-pw-meter mt-1" aria-hidden="true">
                                    <div class="rb-pw-meter__bar"></div>
                                </div>
                                <small id="pw-hint" class="rb-pw-hint">Use 8+ characters with letters, numbers and symbols.</small>
                                <?php if (isset($errors['password'])): ?>
                                    <div class="invalid-feedback d-block"><?= e($errors['password']) ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="col-sm-6">
                                <label class="form-label fw-semibold">Confirm password</label>
                                <input type="password" name="password_confirm"
                                       class="form-control <?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>"
                                       required>
                                <?php if (isset($errors['password_confirm'])): ?>
                                    <div class="invalid-feedback"><?= e($errors['password_confirm']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-success w-100">
                            Submit application <i class="bi bi-arrow-right ms-1"></i>
                        </button>
                    </form>

                    <p class="text-center mt-4 mb-0 text-secondary">
                        Already have an account?
                        <a href="login.php" class="fw-semibold">Log in</a>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const input = document.getElementById('pw-input');
    const bar   = document.querySelector('#pw-meter .rb-pw-meter__bar');
    const hint  = document.getElementById('pw-hint');
    if (!input || !bar || !hint) return;

    // List mirrors PHP's COMMON_PASSWORDS (top offenders)
    const common = new Set([
        'password','password1','password123','password!','p@ssw0rd',
        '12345678','123456789','1234567890','qwerty123','qwertyuiop',
        'abc12345','iloveyou','admin123','admin@123','letmein123',
        'welcome1','welcome123','welcome2024','welcome2025','welcome2026'
    ]);

    function score(pw) {
        let s = 0;
        if (pw.length >= 8)  s += 15;
        if (pw.length >= 12) s += 15;
        if (pw.length >= 16) s += 10;
        if (/[a-z]/.test(pw))         s += 10;
        if (/[A-Z]/.test(pw))         s += 10;
        if (/[0-9]/.test(pw))         s += 10;
        if (/[^a-zA-Z0-9]/.test(pw))  s += 10;
        if (!/(.)\1{2,}/.test(pw))    s += 10;
        if (!common.has(pw.toLowerCase())) s += 10;
        return Math.min(s, 100);
    }

    function classes(pw) {
        let n = 0;
        if (/[a-z]/.test(pw)) n++;
        if (/[A-Z]/.test(pw)) n++;
        if (/[0-9]/.test(pw)) n++;
        if (/[^a-zA-Z0-9]/.test(pw)) n++;
        return n;
    }

    function update() {
        const pw = input.value;
        bar.classList.remove('weak','fair','good','strong');
        hint.classList.remove('weak','fair','good','strong');

        if (pw === '') {
            hint.textContent = 'Use 8+ characters with letters, numbers and symbols.';
            return;
        }

        if (common.has(pw.toLowerCase())) {
            bar.classList.add('weak');
            hint.classList.add('weak');
            hint.textContent = '⚠ This password is too common. Please pick another.';
            return;
        }

        if (pw.length < 8) {
            bar.classList.add('weak');
            hint.classList.add('weak');
            hint.textContent = `${pw.length}/8 characters \u2014 keep going.`;
            return;
        }

        if (classes(pw) < 3) {
            bar.classList.add('weak');
            hint.classList.add('weak');
            hint.textContent = '⚠ Add another character type (UPPERCASE, number, or symbol).';
            return;
        }

        const s = score(pw);
        if      (s < 50)  { bar.classList.add('weak');   hint.classList.add('weak');   hint.textContent = 'Weak'; }
        else if (s < 70)  { bar.classList.add('fair');   hint.classList.add('fair');   hint.textContent = 'Fair'; }
        else if (s < 85)  { bar.classList.add('good');   hint.classList.add('good');   hint.textContent = 'Good ✓'; }
        else              { bar.classList.add('strong'); hint.classList.add('strong'); hint.textContent = 'Strong ✓'; }
    }

    input.addEventListener('input', update);
})();
</script>
</body>
</html>