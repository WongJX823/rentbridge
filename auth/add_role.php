<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$userId   = current_user_id();
$myRoles  = get_user_roles($userId);
// Agent accounts require UTeM-staff verification (see register_agent.php),
// which has no equivalent approval step for an already-active account —
// so agent isn't self-service here. Admin is never self-service.
$addable  = array_values(array_diff(['student', 'landlord'], $myRoles));

$role   = $_GET['role'] ?? $_POST['role'] ?? '';
$errors = [];
$old    = ['full_name' => '', 'ic_no' => '', 'phone' => '', 'matric_no' => ''];

if (!in_array($role, $addable, true)) {
    $role = '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $role !== '') {
    verify_csrf();

    $old['full_name'] = trim($_POST['full_name'] ?? '');
    $old['ic_no']     = trim($_POST['ic_no'] ?? '');
    $old['phone']     = trim($_POST['phone'] ?? '');
    $old['matric_no'] = trim($_POST['matric_no'] ?? '');

    if ($old['full_name'] === '') $errors['full_name'] = 'Full name required.';
    if ($old['phone'] === '')     $errors['phone']     = 'Phone number required.';

    $icClean = preg_replace('/[^0-9]/', '', $old['ic_no']);
    if (strlen($icClean) !== 12) $errors['ic_no'] = 'IC must be 12 digits (e.g. 030303-03-0303).';

    if ($role === 'student' && $old['matric_no'] === '') {
        $errors['matric_no'] = 'Matric number required.';
    }

    if (empty($errors)) {
        $pdo = db();
        try {
            if ($role === 'student') {
                $stmt = $pdo->prepare('SELECT 1 FROM students WHERE matric_no = ?');
                $stmt->execute([$old['matric_no']]);
                if ($stmt->fetch()) {
                    $errors['matric_no'] = 'This matric number is already registered.';
                }
            }

            if (empty($errors)) {
                $pdo->beginTransaction();

                if ($role === 'student') {
                    $pdo->prepare('
                        INSERT INTO students (user_id, full_name, matric_no, ic_no, university, phone)
                        VALUES (?, ?, ?, ?, "UTeM", ?)
                    ')->execute([$userId, $old['full_name'], $old['matric_no'], $old['ic_no'], $old['phone']]);
                } else { // landlord
                    $pdo->prepare('
                        INSERT INTO landlords (user_id, full_name, ic_no, phone)
                        VALUES (?, ?, ?, ?)
                    ')->execute([$userId, $old['full_name'], $old['ic_no'], $old['phone']]);
                }

                grant_user_role($userId, $role, false);
                $pdo->commit();

                switch_active_role($role);
                set_flash('success', "You're now registered as a $role too!");
                header('Location: ' . dashboard_url_for($role));
                exit;
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors['general'] = 'Something went wrong: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Add a role';
ob_start();
?>
<div class="row justify-content-center">
    <div class="col-lg-6">
        <h3 class="mb-1">Add another role</h3>
        <p class="text-secondary mb-4">
            Hold more than one role on RentBridge — e.g. a student who also lists a
            property as a landlord. Your existing account and login stay the same.
        </p>

        <?php if (!empty($errors['general'])): ?>
            <div class="alert alert-danger"><?= e($errors['general']) ?></div>
        <?php endif; ?>

        <?php if ($role === ''): ?>
            <div class="list-group mb-3">
                <?php foreach ($addable as $r): ?>
                    <a href="?role=<?= e($r) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-<?= $r === 'student' ? 'mortarboard' : 'building' ?> me-2"></i>
                            Become a <?= e(ucfirst($r)) ?>
                        </span>
                        <i class="bi bi-chevron-right"></i>
                    </a>
                <?php endforeach; ?>
            </div>
            <?php if (!in_array('agent', $myRoles, true)): ?>
                <p class="text-secondary small">
                    Want to become an agent? Agent accounts need UTeM-staff verification —
                    <a href="<?= BASE_PATH ?>/contact.php">contact admin</a> to be added.
                </p>
            <?php endif; ?>
            <?php if (empty($addable) && in_array('agent', $myRoles, true)): ?>
                <p class="text-secondary">You already hold every role available for self-service.</p>
            <?php endif; ?>
        <?php else: ?>
            <form method="POST" class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <p class="small mb-1">
                        <a href="add_role.php" class="text-secondary text-decoration-none">
                            <i class="bi bi-arrow-left"></i> Pick a different role
                        </a>
                    </p>
                    <h5 class="mb-3">Become a <?= e(ucfirst($role)) ?></h5>
                    <?= csrf_field() ?>
                    <input type="hidden" name="role" value="<?= e($role) ?>">

                    <div class="mb-3">
                        <label class="form-label">Full name</label>
                        <input type="text" name="full_name" class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>"
                               value="<?= e($old['full_name']) ?>" required>
                        <?php if (isset($errors['full_name'])): ?><div class="invalid-feedback"><?= e($errors['full_name']) ?></div><?php endif; ?>
                    </div>

                    <?php if ($role === 'student'): ?>
                    <div class="mb-3">
                        <label class="form-label">Matric number</label>
                        <input type="text" name="matric_no" class="form-control <?= isset($errors['matric_no']) ? 'is-invalid' : '' ?>"
                               value="<?= e($old['matric_no']) ?>" required>
                        <?php if (isset($errors['matric_no'])): ?><div class="invalid-feedback"><?= e($errors['matric_no']) ?></div><?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <div class="mb-3">
                        <label class="form-label">IC number</label>
                        <input type="text" name="ic_no" class="form-control <?= isset($errors['ic_no']) ? 'is-invalid' : '' ?>"
                               placeholder="030303-03-0303" value="<?= e($old['ic_no']) ?>" required>
                        <?php if (isset($errors['ic_no'])): ?><div class="invalid-feedback"><?= e($errors['ic_no']) ?></div><?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Phone</label>
                        <input type="text" name="phone" class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>"
                               value="<?= e($old['phone']) ?>" required>
                        <?php if (isset($errors['phone'])): ?><div class="invalid-feedback"><?= e($errors['phone']) ?></div><?php endif; ?>
                    </div>

                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle me-1"></i> Add <?= e(ucfirst($role)) ?> role
                    </button>
                </div>
            </form>
        <?php endif; ?>
    </div>
</div>
<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/public_layout.php';
