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
                       looking_for_housing = ?,
                       housing_pref_city = ?,
                       housing_pref_max_rent = ?,
                       housing_pref_move_in = ?,
                       housing_bio = ?
                 WHERE user_id = ?
            ");
            $stmt->execute([
                $full_name, $preferred_name, $matric_no, $university, $phone,
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
        'looking_for_housing' => $looking,
        'housing_pref_city' => $pref_city,
        'housing_pref_max_rent' => $pref_max_rent,
        'housing_pref_move_in' => $pref_move_in,
        'housing_bio' => $housing_bio,
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
            <tr><th class="text-secondary">Phone</th><td><?= e($student['phone']) ?></td></tr>
        </table>
    </div>

    <div class="bg-white border rounded-3 p-4 mb-3">
        <h6 class="text-secondary text-uppercase small mb-3">
            Housing preferences
            <?php if ((int)$student['looking_for_housing'] === 1): ?>
                <span class="badge bg-success ms-1">Actively looking</span>
            <?php else: ?>
                <span class="badge bg-secondary ms-1">Not actively looking</span>
            <?php endif; ?>
        </h6>
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
require __DIR__ . '/../includes/student_layout.php';