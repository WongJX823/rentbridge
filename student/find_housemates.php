<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('student');

$pdo = db();
$userId = current_user_id();
$propertyId = (int)($_GET['property_id'] ?? 0);

if ($propertyId <= 0) {
    set_flash('danger', 'Invalid property.');
    header('Location: /rentbridge/listings.php');
    exit;
}

// Fetch property
$stmt = $pdo->prepare("
    SELECT p.*,
           (SELECT image_path FROM property_images
             WHERE property_id = p.id ORDER BY is_primary DESC LIMIT 1) AS image_path
      FROM properties p
     WHERE p.id = ? AND p.status = 'available'
");
$stmt->execute([$propertyId]);
$property = $stmt->fetch();

if (!$property) {
    set_flash('danger', 'Property not available.');
    header('Location: /rentbridge/listings.php');
    exit;
}

// Check existing post
$stmt = $pdo->prepare("
    SELECT id FROM co_tenancy_posts
     WHERE poster_id = ? AND property_id = ? AND status = 'open'
");
$stmt->execute([$userId, $propertyId]);
if ($stmt->fetchColumn()) {
    set_flash('info', 'You already have an open post for this property.');
    header('Location: /rentbridge/student/partners.php');
    exit;
}

$errors = [];
$old = ['message' => '', 'housemates_needed' => '1', 'semesters_needed' => '1'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $old['message']           = trim($_POST['message'] ?? '');
    $old['housemates_needed'] = (int)($_POST['housemates_needed'] ?? 1);
    $old['semesters_needed']  = (int)($_POST['semesters_needed'] ?? 1);

    if ($old['message'] === '') {
        $errors['message'] = 'Tell others why they should join you.';
    }
    if ($old['housemates_needed'] < 1 || $old['housemates_needed'] > 5) {
        $errors['housemates_needed'] = 'Must be between 1 and 5.';
    }
    if ($old['semesters_needed'] < 1 || $old['semesters_needed'] > 6) {
        $errors['semesters_needed'] = 'Must be between 1 and 6 semesters.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare("
            INSERT INTO co_tenancy_posts (poster_id, property_id, message, housemates_needed, semesters_needed)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$userId, $propertyId, $old['message'], $old['housemates_needed'], $old['semesters_needed']]);

        // Auto-enable looking_for_housing for them
        $pdo->prepare("UPDATE students SET looking_for_housing = 1 WHERE user_id = ?")->execute([$userId]);

        set_flash('success', 'Post created! Other students can now message you about this property.');
        header('Location: /rentbridge/student/partners.php');
        exit;
    }
}

$perPersonEst = (float)$property['monthly_rent'] / (int)($old['housemates_needed'] + 1);

$pageTitle = 'Find Housemates';
$activeNav = 'partners';

ob_start();
?>

<p class="small mb-3">
    <a href="/rentbridge/properties/<?= (int)$propertyId ?>" class="text-secondary text-decoration-none">
        <i class="bi bi-arrow-left"></i> Back to property
    </a>
</p>

<div class="row g-4">
    <!-- LEFT: form -->
    <div class="col-lg-7">
        <h3 class="mb-1">Find housemates for this property</h3>
        <p class="text-secondary mb-4">
            This post will appear to other students looking for housing in
            <strong><?= e($property['city']) ?></strong>.
        </p>

        <form method="POST" class="bg-white border rounded-3 p-4">
            <?= csrf_field() ?>

            <div class="mb-3">
                <label class="form-label fw-semibold">
                    How many more housemates do you need? <small class="text-danger">*</small>
                </label>
                <select name="housemates_needed" class="form-select">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <option value="<?= $i ?>" <?= (int)$old['housemates_needed']===$i?'selected':'' ?>>
                            <?= $i ?> more <?= $i === 1 ? 'housemate' : 'housemates' ?>
                            (so <?= $i + 1 ?> people total)
                        </option>
                    <?php endfor; ?>
                </select>
                <small class="text-secondary">
                    Per-person estimate: <strong>RM <?= number_format($perPersonEst) ?> / month</strong>
                </small>
            </div>

            <div class="mb-3">
                <label class="form-label fw-semibold">
                    How many semesters do you plan to rent? <small class="text-danger">*</small>
                </label>
                <select name="semesters_needed"
                        class="form-select <?= isset($errors['semesters_needed']) ? 'is-invalid' : '' ?>">
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                        <option value="<?= $i ?>" <?= (int)$old['semesters_needed']===$i?'selected':'' ?>>
                            <?= $i ?> semester<?= $i > 1 ? 's' : '' ?>
                        </option>
                    <?php endfor; ?>
                </select>
                <?php if (isset($errors['semesters_needed'])): ?>
                    <div class="invalid-feedback"><?= e($errors['semesters_needed']) ?></div>
                <?php endif; ?>
                <small class="text-secondary">1 semester ≈ 6 months (UTeM academic calendar).</small>
            </div>

            <div class="mb-4">
                <label class="form-label fw-semibold">
                    Your message to potential housemates <small class="text-danger">*</small>
                </label>
                <textarea name="message" rows="5" required
                          class="form-control <?= isset($errors['message']) ? 'is-invalid' : '' ?>"
                          placeholder="e.g. Found this property near campus, looking for 2 quiet housemates. I'm Year 3 CS student, non-smoker, sleep early. Prefer female-only for the unit. Move-in mid-August."
                          maxlength="500"><?= e($old['message']) ?></textarea>
                <?php if (isset($errors['message'])): ?>
                    <div class="invalid-feedback"><?= e($errors['message']) ?></div>
                <?php endif; ?>
                <small class="text-secondary">
                    Mention your year, lifestyle, gender preferences, move-in date.
                    Max 500 characters.
                </small>
            </div>

            <div class="alert alert-light border small">
                <i class="bi bi-info-circle text-secondary me-1"></i>
                Posting will automatically enable "Looking for housing" in your profile so
                other students can match with you.
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="/rentbridge/properties/<?= (int)$propertyId ?>" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-megaphone me-1"></i> Post to Find Housemates
                </button>
            </div>
        </form>
    </div>

    <!-- RIGHT: property preview -->
    <div class="col-lg-5">
        <div class="bg-white border rounded-3 overflow-hidden sticky-top" style="top: 80px;">
            <div style="aspect-ratio: 16/10; background: linear-gradient(135deg,#E6ECF4,#E4F2EA);">
                <?php if (!empty($property['image_path'])): ?>
                    <img src="/rentbridge/<?= e($property['image_path']) ?>"
                         style="width:100%; height:100%; object-fit:cover;" alt="">
                <?php endif; ?>
            </div>
            <div class="p-3">
                <h5 class="mb-1"><?= e($property['title']) ?></h5>
                <div class="small text-secondary mb-2">
                    <i class="bi bi-geo-alt"></i> <?= e($property['city']) ?>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-secondary">Monthly rent</small>
                        <div><strong class="text-emerald">RM <?= number_format((float)$property['monthly_rent']) ?></strong></div>
                    </div>
                    <span class="badge bg-light text-dark">
                        <?= e(ucfirst(str_replace('_',' ', $property['property_type']))) ?>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/student_layout.php';