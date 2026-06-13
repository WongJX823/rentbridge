<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('student');

$pdo = db();
$userId = current_user_id();

// Load current profile
$stmt = $pdo->prepare("
    SELECT u.email, u.created_at,
           s.full_name, s.preferred_name, s.matric_no, s.university, s.phone,
           s.looking_for_housing,
           s.housing_pref_city, s.housing_pref_max_rent,
           s.housing_pref_move_in, s.housing_bio
      FROM users u
      JOIN students s ON s.user_id = u.id
     WHERE u.id = ?
");
$stmt->execute([$userId]);
$me = $stmt->fetch();

if (!$me) {
    die('Profile not found.');
}

$errors = [];
$old = [
    'preferred_name'        => $me['preferred_name'],
    'phone'                 => $me['phone'],
    'looking_for_housing'   => (int)$me['looking_for_housing'],
    'housing_pref_city'     => $me['housing_pref_city'] ?? '',
    'housing_pref_max_rent' => $me['housing_pref_max_rent'] ?? '',
    'housing_pref_move_in'  => $me['housing_pref_move_in'] ?? '',
    'housing_bio'           => $me['housing_bio'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $old['preferred_name']        = trim($_POST['preferred_name'] ?? '');
    $old['phone']                 = trim($_POST['phone'] ?? '');
    $old['looking_for_housing']   = isset($_POST['looking_for_housing']) ? 1 : 0;
    $old['housing_pref_city']     = trim($_POST['housing_pref_city'] ?? '');
    $old['housing_pref_max_rent'] = trim($_POST['housing_pref_max_rent'] ?? '');
    $old['housing_pref_move_in']  = trim($_POST['housing_pref_move_in'] ?? '');
    $old['housing_bio']           = trim($_POST['housing_bio'] ?? '');

    // Validate
    if ($old['phone'] === '') {
        $errors['phone'] = 'Phone number is required.';
    }
    if ($old['housing_pref_max_rent'] !== '' && !is_numeric($old['housing_pref_max_rent'])) {
        $errors['housing_pref_max_rent'] = 'Must be a number.';
    }
    if ($old['housing_pref_move_in'] !== '' && !strtotime($old['housing_pref_move_in'])) {
        $errors['housing_pref_move_in'] = 'Invalid date.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            UPDATE students
               SET preferred_name = ?,
                   phone = ?,
                   looking_for_housing = ?,
                   housing_pref_city = ?,
                   housing_pref_max_rent = ?,
                   housing_pref_move_in = ?,
                   housing_bio = ?
             WHERE user_id = ?
        ");
        $stmt->execute([
            $old['preferred_name'],
            $old['phone'],
            $old['looking_for_housing'],
            $old['housing_pref_city']     !== '' ? $old['housing_pref_city'] : null,
            $old['housing_pref_max_rent'] !== '' ? (float)$old['housing_pref_max_rent'] : null,
            $old['housing_pref_move_in']  !== '' ? $old['housing_pref_move_in'] : null,
            $old['housing_bio']           !== '' ? $old['housing_bio'] : null,
            $userId
        ]);

        set_flash('success', 'Profile updated successfully.');
        header('Location: /rentbridge/student/profile.php');
        exit;
    }
}

$pageTitle = 'My Profile';
$activeNav = 'profile';

ob_start();
?>

<div class="row g-4">

    <div class="col-lg-8">
        <form method="POST" novalidate>
            <?= csrf_field() ?>

            <!-- IDENTITY (read-only) -->
            <div class="bg-white border rounded-3 p-4 mb-3">
                <h6 class="text-secondary text-uppercase small mb-3">Account info</h6>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Full name</label>
                    <input type="text" class="form-control" value="<?= e($me['full_name']) ?>" disabled>
                    <small class="text-secondary">Cannot be changed. Contact admin if needed.</small>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Matric number</label>
                        <input type="text" class="form-control" value="<?= e($me['matric_no']) ?>" disabled>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">University</label>
                        <input type="text" class="form-control" value="<?= e($me['university']) ?>" disabled>
                    </div>
                </div>

                <div class="mt-3">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" class="form-control" value="<?= e($me['email']) ?>" disabled>
                </div>
            </div>

            <!-- EDITABLE INFO -->
            <div class="bg-white border rounded-3 p-4 mb-3">
                <h6 class="text-secondary text-uppercase small mb-3">Personal</h6>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Nickname</label>
                    <input type="text" name="preferred_name"
                           class="form-control"
                           value="<?= e($old['preferred_name']) ?>"
                           placeholder="What people call you">
                    <small class="text-secondary">Used across RentBridge instead of your full name.</small>
                </div>

                <div class="mb-1">
                    <label class="form-label fw-semibold">
                        Phone <small class="text-danger">*</small>
                    </label>
                    <input type="text" name="phone"
                           class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                           value="<?= e($old['phone']) ?>"
                           placeholder="012-3456789" required>
                    <?php if (isset($errors['phone'])): ?>
                        <div class="invalid-feedback"><?= e($errors['phone']) ?></div>
                    <?php endif; ?>
                    <small class="text-secondary">
                        Visible only to landlords and agents you contact via RentBridge.
                    </small>
                </div>
            </div>

            <!-- HOUSING PREFERENCES -->
            <div class="bg-white border rounded-3 p-4 mb-3">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <h6 class="text-secondary text-uppercase small mb-0">Housing preferences</h6>
                </div>

                <div class="form-check form-switch mb-3 p-3 rounded-3"
                     style="background:#F4F4EE; padding-left: 3rem !important;">
                    <input class="form-check-input" type="checkbox" role="switch"
                           name="looking_for_housing" id="looking_for_housing" value="1"
                           <?= $old['looking_for_housing'] ? 'checked' : '' ?>>
                    <label class="form-check-label fw-semibold" for="looking_for_housing">
                        I'm looking for housing
                    </label>
                    <div class="small text-secondary">
                        Enable to appear in "Find stranger" discovery and let others know
                        you're searching for housemates.
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Preferred area</label>
                        <input type="text" name="housing_pref_city"
                               class="form-control"
                               value="<?= e($old['housing_pref_city']) ?>"
                               placeholder="e.g. Ayer Keroh, Durian Tunggal">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Max monthly budget (RM)</label>
                        <input type="number" name="housing_pref_max_rent"
                               class="form-control <?= isset($errors['housing_pref_max_rent']) ? 'is-invalid' : '' ?>"
                               value="<?= e($old['housing_pref_max_rent']) ?>"
                               placeholder="500"
                               min="0" step="50">
                        <?php if (isset($errors['housing_pref_max_rent'])): ?>
                            <div class="invalid-feedback"><?= e($errors['housing_pref_max_rent']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Earliest move-in date</label>
                        <input type="date" name="housing_pref_move_in"
                               class="form-control <?= isset($errors['housing_pref_move_in']) ? 'is-invalid' : '' ?>"
                               value="<?= e($old['housing_pref_move_in']) ?>">
                        <?php if (isset($errors['housing_pref_move_in'])): ?>
                            <div class="invalid-feedback"><?= e($errors['housing_pref_move_in']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="mt-3">
                    <label class="form-label fw-semibold">About me (optional)</label>
                    <textarea name="housing_bio" rows="3" class="form-control"
                              placeholder="Tell potential housemates a bit about yourself — what you study, your habits, what you're looking for..."
                              maxlength="500"><?= e($old['housing_bio']) ?></textarea>
                    <small class="text-secondary">Max 500 characters.</small>
                </div>
            </div>

            <!-- SAVE -->
            <div class="d-flex justify-content-end gap-2">
                <a href="/rentbridge/student/dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check2 me-1"></i> Save changes
                </button>
            </div>
        </form>
    </div>

    <!-- RIGHT SIDEBAR — account summary -->
    <div class="col-lg-4">
        <div class="bg-white border rounded-3 p-4 mb-3">
            <h6 class="text-secondary text-uppercase small mb-3">Account summary</h6>

            <div class="d-flex align-items-center gap-3 mb-3">
                <div style="width: 56px; height: 56px; background: #E4F2EA; color: #2E8B57;
                            border-radius: 50%; display:flex; align-items:center;
                            justify-content:center; font-size: 1.5rem;">
                    <i class="bi bi-mortarboard-fill"></i>
                </div>
                <div>
                    <div class="fw-semibold"><?= e($me['preferred_name'] ?: $me['full_name']) ?></div>
                    <small class="text-secondary">Student · <?= e($me['university']) ?></small>
                </div>
            </div>

            <div class="small text-secondary mb-1">
                <i class="bi bi-calendar3"></i> Joined
                <?= e(date('M Y', strtotime($me['created_at']))) ?>
            </div>
            <div class="small text-secondary">
                <i class="bi bi-envelope"></i> <?= e($me['email']) ?>
            </div>
        </div>

        <!-- Quick links -->
        <div class="bg-white border rounded-3 p-3">
            <h6 class="text-secondary text-uppercase small mb-3 px-1">Account</h6>
            <a href="/rentbridge/auth/logout.php"
               class="d-flex align-items-center gap-2 p-2 text-decoration-none text-danger rounded-3"
               style="transition: background 0.15s;"
               onmouseover="this.style.background='#FFE8E8'"
               onmouseout="this.style.background='transparent'">
                <i class="bi bi-box-arrow-right"></i>
                <span>Sign out</span>
            </a>
        </div>

        <!-- Tip card -->
        <div class="bg-light border rounded-3 p-3 mt-3 small">
            <i class="bi bi-lightbulb text-warning"></i>
            <strong>Tip:</strong>
            Enabling "looking for housing" makes your profile discoverable.
            Others looking for housemates can see your preferences and contact you.
        </div>
    </div>

</div>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/student_layout.php';