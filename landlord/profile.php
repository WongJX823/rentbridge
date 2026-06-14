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
        SUM(status = 'booked') AS booked_count,
        SUM(status = 'pending_approval') AS pending_count
      FROM properties
     WHERE landlord_id = ?
");
$stmt->execute([$userId]);
$stats = $stmt->fetch() ?: ['total' => 0, 'available_count' => 0, 'rented_count' => 0, 'booked_count' => 0, 'pending_count' => 0];

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

$pageTitle = 'My Profile';
$activeNav = 'profile';

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
    <div class="bg-white border rounded-3 p-4 mb-3">
        <h6 class="text-secondary text-uppercase small mb-3">Basic info</h6>
        <table class="table table-sm mb-0">
            <tr><th class="text-secondary" style="width:200px;">Full name</th><td><?= e($landlord['full_name']) ?></td></tr>
            <?php if (!empty($landlord['preferred_name'])): ?>
                <tr><th class="text-secondary">Preferred name</th><td><?= e($landlord['preferred_name']) ?></td></tr>
            <?php endif; ?>
            <tr><th class="text-secondary">NRIC</th><td><code><?= e($landlord['ic_no']) ?></code></td></tr>
            <tr><th class="text-secondary">Email</th><td><?= e($landlord['email']) ?></td></tr>
            <tr><th class="text-secondary">Phone</th><td><?= e($landlord['phone']) ?></td></tr>
            <tr><th class="text-secondary">WhatsApp contact</th>
                <td>
                    <?php if ((int)$landlord['allow_whatsapp'] === 1): ?>
                        <span class="badge bg-success">Enabled</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Disabled</span>
                    <?php endif; ?>
                </td>
            </tr>
            <tr><th class="text-secondary">Home address</th><td><?= e($landlord['address'] ?: '—') ?></td></tr>
        </table>
    </div>

    <div class="bg-white border rounded-3 p-4 mb-3" style="background:#FAF8F3 !important;">
        <h6 class="text-secondary text-uppercase small mb-3">Account security</h6>
        <p class="small mb-2">
            Password change with email verification — coming soon.
        </p>
        <button class="btn btn-outline-secondary btn-sm" disabled>
            <i class="bi bi-key me-1"></i> Change password
        </button>
    </div>
<?php endif; ?>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/landlord_layout.php';