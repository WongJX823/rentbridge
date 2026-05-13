<?php
require_once __DIR__ . '/../includes/auth.php';

// Already logged in? bounce to the right dashboard
if (is_logged_in()) {
    header('Location: ' . dashboard_url_for(current_role()));
    exit;
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $error = 'Please enter your email and password.';
    } else {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            // Same generic message for "no such email" + "wrong password"
            $error = 'Invalid email or password.';
        } elseif ($user['status'] === 'suspended') {
            $error = 'This account has been suspended. Please contact support.';
        } elseif ($user['status'] === 'pending') {
            $error = 'Your account is pending admin approval. Please check back later.';
        } elseif ($user['status'] === 'rejected') {
            $error = 'Your application was not approved. Please contact UTeM HEP for clarification.';
        } else {
            // ✅ All good — log in
            login_user($user);
            set_flash('success', 'Welcome back!');
            header('Location: ' . dashboard_url_for($user['primary_role']));
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log in · RentBridge</title>
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
        <div class="col-md-7 col-lg-5">

            <div class="text-center mb-4">
                <a href="../index.php" class="text-decoration-none d-inline-flex align-items-center gap-2 mb-3"
                   style="color:var(--rb-navy); font-family:'Fraunces',serif; font-size:1.6rem; font-weight:600;">
                    <span style="width:30px;height:30px;background:var(--rb-emerald);border-radius:8px;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:1rem;transform:rotate(-6deg);">R</span>
                    RentBridge
                </a>
            </div>

            <div class="bg-white rounded-4 p-4 p-md-5 border">

                <h1 class="mb-1">Welcome back</h1>
                <p class="text-secondary mb-4">Log in to continue.</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle"></i>
                        <?= e($error) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Email</label>
                        <input type="email" name="email" class="form-control"
                               value="<?= e($email) ?>" required autofocus
                               placeholder="you@student.utem.edu.my">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <label class="d-flex align-items-center gap-2 text-secondary small">
                            <input type="checkbox" name="remember" value="1">
                            Remember me
                        </label>
                        <a href="#" class="small">Forgot password?</a>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        Log in <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                </form>

                <hr class="my-4">

                <p class="text-center mb-0 text-secondary">
                    New to RentBridge?
                    <a href="register.php" class="fw-semibold">Create an account</a>
                </p>
            </div>

            <p class="text-center mt-4 small">
                <a href="../index.php" class="text-secondary text-decoration-none">
                    <i class="bi bi-arrow-left"></i> Back to home
                </a>
            </p>
        </div>
    </div>
</div>

</body>
</html>