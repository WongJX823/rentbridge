<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('agent');

$pdo = db();
$userId = current_user_id();

$errors = [];

$stmt = $pdo->prepare("
    SELECT a.*, u.email, u.created_at AS joined_at
      FROM agents a
      JOIN users u ON u.id = a.user_id
     WHERE a.user_id = ?
");
$stmt->execute([$userId]);
$agent = $stmt->fetch();

if (!$agent) {
    die('Agent profile not found.');
}

// Case stats
$stmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_cases,
        SUM(status = 'active')           AS active_cases,
        SUM(status = 'completed')        AS completed_cases,
        SUM(status = 'pending_agent')    AS pending_cases
      FROM bookings
     WHERE agent_id = ?
");
$stmt->execute([$userId]);
$stats = $stmt->fetch() ?: ['total_cases' => 0, 'active_cases' => 0, 'completed_cases' => 0, 'pending_cases' => 0];

$isEditMode = isset($_GET['edit']) && $_GET['edit'] === '1';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $full_name = trim($_POST['full_name'] ?? '');
    $phone     = trim($_POST['phone'] ?? '');

    if ($full_name === '') $errors['full_name'] = 'Full name required';
    if ($phone === '')     $errors['phone'] = 'Phone required';

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("
                UPDATE agents
                   SET full_name = ?,
                       phone = ?
                 WHERE user_id = ?
            ");
            $stmt->execute([$full_name, $phone, $userId]);

            set_flash('success', 'Profile updated successfully.');
            header('Location: /rentbridge/agent/profile.php');
            exit;
        } catch (Throwable $e) {
            $errors['general'] = 'Failed to save: ' . $e->getMessage();
        }
    }

    $agent = array_merge($agent, [
        'full_name' => $full_name,
        'phone' => $phone,
    ]);
    $isEditMode = true;
}

$pageTitle = 'My Profile';
$activeNav = 'profile';

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-1" style="font-family:'Fraunces',serif;">My Profile</h1>
        <p class="text-secondary mb-0">
            Member since <?= e(date('M Y', strtotime($agent['joined_at']))) ?>
            · <span class="badge bg-info text-dark"><i class="bi bi-mortarboard-fill"></i> UTeM Agent</span>
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

<!-- CASE STATS -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="bg-white border rounded-3 p-3 text-center">
            <div class="text-secondary small text-uppercase">Total cases</div>
            <strong class="fs-3 text-emerald"><?= (int)$stats['total_cases'] ?></strong>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="bg-white border rounded-3 p-3 text-center">
            <div class="text-secondary small text-uppercase">Active</div>
            <strong class="fs-3"><?= (int)$stats['active_cases'] ?></strong>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="bg-white border rounded-3 p-3 text-center">
            <div class="text-secondary small text-uppercase">Completed</div>
            <strong class="fs-3"><?= (int)$stats['completed_cases'] ?></strong>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="bg-white border rounded-3 p-3 text-center">
            <div class="text-secondary small text-uppercase">Pending</div>
            <strong class="fs-3"><?= (int)$stats['pending_cases'] ?></strong>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/avatar.php'; ?>

<!-- AVATAR -->
<div class="bg-white border rounded-3 p-4 mb-3">
    <h6 class="text-secondary text-uppercase small mb-3">Profile photo</h6>
    <div class="d-flex align-items-center gap-4">
        <?php render_avatar($agent['avatar_path'] ?? null, $agent['full_name'], 96); ?>
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

<?php if ($isEditMode): ?>
    <!-- EDIT MODE -->
    <form method="POST">
        <?= csrf_field() ?>

        <div class="bg-white border rounded-3 p-4 mb-3">
            <h6 class="text-secondary text-uppercase small mb-3">Basic info</h6>
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Full name <small class="text-danger">*</small></label>
                    <input type="text" name="full_name"
                           class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>"
                           value="<?= e($agent['full_name']) ?>" required>
                    <?php if (isset($errors['full_name'])): ?><div class="invalid-feedback"><?= e($errors['full_name']) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Phone <small class="text-danger">*</small></label>
                    <input type="text" name="phone"
                           class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                           value="<?= e($agent['phone']) ?>" required>
                    <?php if (isset($errors['phone'])): ?><div class="invalid-feedback"><?= e($errors['phone']) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Staff ID</label>
                    <input type="text" class="form-control" value="<?= e($agent['staff_id'] ?? '—') ?>" disabled>
                    <small class="text-secondary">Staff ID is set by admin.</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" class="form-control" value="<?= e($agent['email']) ?>" disabled>
                    <small class="text-secondary">Email cannot be changed here.</small>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="/rentbridge/agent/profile.php" class="btn btn-outline-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check-circle me-1"></i> Save changes
            </button>
        </div>
    </form>

<?php else: ?>
    <!-- VIEW MODE -->
    <div class="bg-white border rounded-3 p-4 mb-3">
        <h6 class="text-secondary text-uppercase small mb-3">Basic info</h6>
        <table class="table table-sm mb-0">
            <tr><th class="text-secondary" style="width:200px;">Full name</th><td><?= e($agent['full_name']) ?></td></tr>
            <tr><th class="text-secondary">Staff ID</th><td><code><?= e($agent['staff_id'] ?? '—') ?></code></td></tr>
            <tr><th class="text-secondary">Email</th><td><?= e($agent['email']) ?></td></tr>
            <tr><th class="text-secondary">Phone</th><td>
                <?= e($agent['phone']) ?>
                <?php
                $waPhone = preg_replace('/\D/', '', $agent['phone'] ?? '');
                if ($waPhone && str_starts_with($waPhone, '0')) $waPhone = '60' . ltrim($waPhone, '0');
                if ($waPhone): ?>
                <a href="https://wa.me/<?= e($waPhone) ?>" target="_blank" rel="noopener"
                   class="btn btn-success btn-sm rounded-pill ms-2 py-0 px-2" title="Open WhatsApp">
                    <i class="bi bi-whatsapp" style="font-size:.8rem;"></i>
                </a>
                <?php endif; ?>
            </td></tr>
        </table>
    </div>

    <div class="bg-white border rounded-3 p-4 mb-3">
        <h6 class="text-secondary text-uppercase small mb-3">Account security</h6>
        <p class="small text-secondary mb-3">
            For security, password changes require email verification.
        </p>
        <button class="btn btn-outline-primary btn-sm"
                data-bs-toggle="modal" data-bs-target="#passwordChangeModal">
            <i class="bi bi-key me-1"></i> Change password
        </button>
    </div>

    <?php
    // Copy the same password change modal + script from student/profile.php
    // (omitted here for brevity — paste it from student/profile.php)
    ?>
<?php endif; ?>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/agent_layout.php';