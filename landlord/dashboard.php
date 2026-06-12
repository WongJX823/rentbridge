<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('landlord');

$pdo = db();
$userId = current_user_id();

// Load current data
$stmt = $pdo->prepare("
    SELECT u.email, l.full_name, l.preferred_name, l.phone, l.allow_whatsapp, l.address
      FROM users u
      JOIN landlords l ON l.user_id = u.id
     WHERE u.id = ?
");
$stmt->execute([$userId]);
$me = $stmt->fetch();

if (!$me) {
    die('Profile not found.');
}

$errors = [];
$old = [
    'preferred_name' => $me['preferred_name'],
    'phone'          => $me['phone'],
    'allow_whatsapp' => (int)($me['allow_whatsapp'] ?? 0),   // ← NEW
    'address'        => $me['address'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $old['preferred_name'] = trim($_POST['preferred_name'] ?? '');
    $old['phone']          = trim($_POST['phone'] ?? '');
    $old['allow_whatsapp'] = isset($_POST['allow_whatsapp']) ? 1 : 0;
    $old['address']        = trim($_POST['address'] ?? '');

    if ($old['phone'] === '') {
        $errors['phone'] = 'Phone is required.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            UPDATE landlords
            SET preferred_name = ?,
                phone = ?,
                allow_whatsapp = ?,
                address = ?
            WHERE user_id = ?
        ");
        $stmt->execute([
            $old['preferred_name'],
            $old['phone'],
            $old['allow_whatsapp'],
            $old['address'] !== '' ? $old['address'] : null,
            $userId
        ]);

        set_flash('success', 'Profile updated.');
        header('Location: profile.php');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Profile · Landlord · RentBridge</title>
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
        <div class="col-lg-7">

            <h1 class="mb-1">My profile</h1>
            <p class="text-secondary mb-4">Manage how students and agents contact you.</p>

            <?php $flash = get_flash(); if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
            <?php endif; ?>

            <form method="POST" class="bg-white border rounded-3 p-4">
                <?= csrf_field() ?>

                <h6 class="text-secondary text-uppercase small mb-3">Account</h6>
                <div class="mb-3">
                    <label class="form-label">Full name</label>
                    <input type="text" class="form-control" value="<?= e($me['full_name']) ?>" disabled>
                    <small class="text-secondary">Cannot be changed. Contact admin if needed.</small>
                </div>
                <div class="mb-3">
                    <label class="form-label">Email</label>
                    <input type="email" class="form-control" value="<?= e($me['email']) ?>" disabled>
                </div>
                <div class="mb-4">
                    <label class="form-label">Nickname</label>
                    <input type="text" name="preferred_name" class="form-control"
                           value="<?= e($old['preferred_name']) ?>"
                           placeholder="What people call you">
                </div>

                <h6 class="text-secondary text-uppercase small mb-3">Contact</h6>
                <div class="mb-3">
                    <label class="form-label">Phone number <small class="text-danger">*</small></label>
                    <input type="text" name="phone"
                           class="form-control <?= isset($errors['phone'])?'is-invalid':'' ?>"
                           value="<?= e($old['phone']) ?>"
                           placeholder="012-3456789" required>
                    <?php if (isset($errors['phone'])): ?>
                        <div class="invalid-feedback"><?= e($errors['phone']) ?></div>
                    <?php endif; ?>
                    <small class="text-secondary">Used by admin and agents to contact you. Not shown publicly unless you add WhatsApp below.</small>
                </div>

                <div class="mb-4">
    <div class="form-check border rounded-3 p-3"
         style="background:#F4F4EE; border-color: rgba(15,44,82,0.1) !important;">
        <input class="form-check-input" type="checkbox"
               name="allow_whatsapp" id="allow_whatsapp" value="1"
               <?= $old['allow_whatsapp'] ? 'checked' : '' ?>>
        <label class="form-check-label fw-semibold" for="allow_whatsapp">
            <i class="bi bi-whatsapp text-success me-1"></i>
            Allow contact via WhatsApp
        </label>
        <div class="small text-secondary mt-2">
            If enabled, your phone number (<strong><?= e($old['phone']) ?></strong>) will be visible
            to students viewing your property, and a green WhatsApp button will appear next to it.
            <br><br>
            If disabled, students must use RentBridge's internal chat — your phone stays private.
        </div>
    </div>
</div>
                <h6 class="text-secondary text-uppercase small mb-3">Address</h6>
                <div class="mb-4">
                    <label class="form-label">Your home address <span class="badge bg-secondary">Admin-only</span></label>
                    <textarea name="address" rows="3" class="form-control"><?= e($old['address']) ?></textarea>
                    <small class="text-secondary">Used by admin for verification. Never shown publicly.</small>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <a href="/rentbridge/landlord/dashboard.php" class="btn btn-ghost">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2 me-1"></i> Save changes
                    </button>
                </div>
            </form>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>