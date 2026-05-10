<?php
require_once __DIR__ . '/../includes/auth.php';

$errors = [];
$old = [
    'email'     => $_SESSION['landlord_signup']['email']     ?? '',
    'full_name' => $_SESSION['landlord_signup']['full_name'] ?? '',
    'ic_no'     => $_SESSION['landlord_signup']['ic_no']     ?? '',
    'phone'     => $_SESSION['landlord_signup']['phone']     ?? '',
];

// ---- HANDLE STEP 1 SUBMIT ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $old['email']     = trim($_POST['email'] ?? '');
    $old['full_name'] = trim($_POST['full_name'] ?? '');
    $old['ic_no']     = trim($_POST['ic_no'] ?? '');
    $old['phone']     = trim($_POST['phone'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm          = $_POST['password_confirm'] ?? '';

    // Validate
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL))
        $errors['email'] = 'Enter a valid email address.';

    if ($old['full_name'] === '')
        $errors['full_name'] = 'Full name is required.';

    if ($old['ic_no'] === '')
        $errors['ic_no'] = 'IC number is required.';

    if ($old['phone'] === '')
        $errors['phone'] = 'Phone number is required.';

    if (strlen($password) < 6)
        $errors['password'] = 'Password must be at least 6 characters.';

    if ($password !== $confirm)
        $errors['password_confirm'] = 'Passwords do not match.';

    // DB uniqueness check
    if (empty($errors)) {
        $pdo = db();

        $stmt = $pdo->prepare('SELECT 1 FROM users WHERE email = ?');
        $stmt->execute([$old['email']]);
        if ($stmt->fetch()) $errors['email'] = 'This email is already registered.';

        $stmt = $pdo->prepare('SELECT 1 FROM landlords WHERE ic_no = ?');
        $stmt->execute([$old['ic_no']]);
        if ($stmt->fetch()) $errors['ic_no'] = 'This IC number is already registered.';
    }

    // All good? Save Step 1 data to session and continue to Step 2
    if (empty($errors)) {
        $_SESSION['landlord_signup'] = [
            'email'         => $old['email'],
            'full_name'     => $old['full_name'],
            'ic_no'         => $old['ic_no'],
            'phone'         => $old['phone'],
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
        ];
        header('Location: register_landlord_step2.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign up · Landlord (Step 1) · RentBridge</title>
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

                <!-- Step indicator -->
                <div class="d-flex align-items-center gap-2 mb-3 small text-secondary fw-semibold">
                    <span class="badge bg-primary">1</span>
                    <span>About you</span>
                    <span class="text-secondary mx-1">›</span>
                    <span class="badge bg-light text-secondary border">2</span>
                    <span>Your first property</span>
                </div>

                <h1 class="mb-1">Landlord sign-up</h1>
                <p class="text-secondary mb-4">Step 1 of 2 — let's start with your account info.</p>

                <form method="POST" novalidate>
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Full name</label>
                        <input type="text" name="full_name" class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>"
                               value="<?= e($old['full_name']) ?>" required>
                        <?php if (isset($errors['full_name'])): ?><div class="invalid-feedback"><?= e($errors['full_name']) ?></div><?php endif; ?>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">IC number</label>
                            <input type="text" name="ic_no" class="form-control <?= isset($errors['ic_no']) ? 'is-invalid' : '' ?>"
                                   placeholder="900101-14-5678" value="<?= e($old['ic_no']) ?>" required>
                            <?php if (isset($errors['ic_no'])): ?><div class="invalid-feedback"><?= e($errors['ic_no']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Phone</label>
                            <input type="tel" name="phone" class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                                   placeholder="0123456789" value="<?= e($old['phone']) ?>" required>
                            <?php if (isset($errors['phone'])): ?><div class="invalid-feedback"><?= e($errors['phone']) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email address</label>
                        <input type="email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                               value="<?= e($old['email']) ?>" required>
                        <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= e($errors['email']) ?></div><?php endif; ?>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Password</label>
                            <input type="password" name="password" class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>"
                                   placeholder="At least 6 characters" required>
                            <?php if (isset($errors['password'])): ?><div class="invalid-feedback"><?= e($errors['password']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Confirm password</label>
                            <input type="password" name="password_confirm" class="form-control <?= isset($errors['password_confirm']) ? 'is-invalid' : '' ?>" required>
                            <?php if (isset($errors['password_confirm'])): ?><div class="invalid-feedback"><?= e($errors['password_confirm']) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        Continue to property info <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                </form>

                <p class="text-center mt-4 mb-0 text-secondary">
                    Already have an account?
                    <a href="login.php" class="fw-semibold">Log in</a>
                </p>
            </div>
        </div>
    </div>
</div>

</body>
</html>