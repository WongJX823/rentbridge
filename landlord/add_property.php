<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/uploads.php';
require_role('landlord');

$errors = [];
$old = [
    'title'         => '',
    'property_type' => 'room',
    'address'       => '',
    'city'          => '',
    'postcode'      => '',
    'state'         => 'Melaka',
    'monthly_rent'  => '',
    'deposit'       => '',
    'description'   => '',
    'facilities'    => '',
    'furnishing'    => 'partial',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $old['title']         = trim($_POST['title'] ?? '');
    $old['property_type'] = $_POST['property_type'] ?? 'room';
    $old['address']       = trim($_POST['address'] ?? '');
    $old['city']          = trim($_POST['city'] ?? '');
    $old['postcode']      = trim($_POST['postcode'] ?? '');
    $old['state']         = trim($_POST['state'] ?? '');
    $old['monthly_rent']  = trim($_POST['monthly_rent'] ?? '');
    $old['deposit']       = trim($_POST['deposit'] ?? '');
    $old['description']   = trim($_POST['description'] ?? '');
    $old['facilities']    = trim($_POST['facilities'] ?? '');
    $old['furnishing']    = $_POST['furnishing'] ?? 'partial';

    // Validate
    if ($old['title']    === '') $errors['title']    = 'Listing title is required.';
    if ($old['address']  === '') $errors['address']  = 'Address is required.';
    if ($old['city']     === '') $errors['city']     = 'City is required.';
    if ($old['state']    === '') $errors['state']    = 'State is required.';

    if (!preg_match('/^\d{5}$/', $old['postcode']))
        $errors['postcode'] = 'Postcode must be exactly 5 digits.';

    if (!is_numeric($old['monthly_rent']) || (float)$old['monthly_rent'] <= 0)
        $errors['monthly_rent'] = 'Enter a valid monthly rent.';

    if ($old['deposit'] !== '' && (!is_numeric($old['deposit']) || (float)$old['deposit'] < 0))
        $errors['deposit'] = 'Deposit must be a valid number.';

    if (!in_array($old['property_type'], ['room', 'studio', 'whole_unit'], true))
        $errors['property_type'] = 'Invalid property type.';

    if (!in_array($old['furnishing'], ['none', 'partial', 'full'], true))
        $errors['furnishing'] = 'Invalid furnishing option.';

    // Validate photos
    $photos = [];
    if (!empty($_FILES['photos']) && is_array($_FILES['photos']['name'])) {
        $count = count($_FILES['photos']['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($_FILES['photos']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;

            $file = [
                'name'     => $_FILES['photos']['name'][$i],
                'type'     => $_FILES['photos']['type'][$i],
                'tmp_name' => $_FILES['photos']['tmp_name'][$i],
                'error'    => $_FILES['photos']['error'][$i],
                'size'     => $_FILES['photos']['size'][$i],
            ];

            $err = validate_image_upload($file);
            if ($err) {
                $errors['photos'] = $err;
                break;
            }
            $photos[] = $file;
        }
    }

    if (empty($errors) && count($photos) === 0)
        $errors['photos'] = 'Please upload at least one photo of your property.';

    if (empty($errors) && count($photos) > 3)
        $errors['photos'] = 'Maximum 3 photos allowed.';

    // Save
    if (empty($errors)) {
        try {
            $pdo = db();
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                "INSERT INTO properties
                    (landlord_id, title, property_type, address, city, postcode, state,
                     monthly_rent, deposit, description, facilities, furnishing, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_approval')"
            );
            $stmt->execute([
                current_user_id(),
                $old['title'],
                $old['property_type'],
                $old['address'],
                $old['city'],
                $old['postcode'],
                $old['state'],
                (float)$old['monthly_rent'],
                $old['deposit'] !== '' ? (float)$old['deposit'] : 0,
                $old['description'],
                $old['facilities'],
                $old['furnishing'],
            ]);
            $propertyId = (int)$pdo->lastInsertId();

            // Save photos
            foreach ($photos as $i => $photo) {
                $path = save_property_image($photo);
                $stmt = $pdo->prepare(
                    'INSERT INTO property_images (property_id, image_path, is_primary)
                     VALUES (?, ?, ?)'
                );
                $stmt->execute([$propertyId, $path, $i === 0 ? 1 : 0]);
            }

            $pdo->commit();

            set_flash('success', 'Property submitted! It will be live once admin approves it.');
            header('Location: /rentbridge/landlord/properties.php');
            exit;

        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $errors['general'] = 'Something went wrong: ' . $e->getMessage();
        }
    }
}

// Malaysian states for the dropdown
$states = [
    'Johor', 'Kedah', 'Kelantan', 'Melaka', 'Negeri Sembilan', 'Pahang',
    'Perak', 'Perlis', 'Pulau Pinang', 'Sabah', 'Sarawak', 'Selangor',
    'Terengganu', 'WP Kuala Lumpur', 'WP Labuan', 'WP Putrajaya'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add property · Landlord · RentBridge</title>
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
        <div class="col-lg-8">

            <p class="small mb-3">
                <a href="/rentbridge/landlord/properties.php" class="text-secondary text-decoration-none">
                    <i class="bi bi-arrow-left"></i> Back to my properties
                </a>
            </p>

            <h1 class="mb-1">Add a new property</h1>
            <p class="text-secondary mb-4">Once submitted, admin will review before it goes live.</p>

            <div class="bg-white rounded-3 p-4 p-md-5 border">

                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-danger"><?= e($errors['general']) ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" novalidate>
                    <?= csrf_field() ?>

                    <!-- Title -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Listing title</label>
                        <input type="text" name="title"
                               class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>"
                               value="<?= e($old['title']) ?>"
                               placeholder="e.g. Cozy single room near UTeM Main Gate" required>
                        <?php if (isset($errors['title'])): ?>
                            <div class="invalid-feedback"><?= e($errors['title']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Property type + Furnishing -->
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Property type</label>
                            <select name="property_type" class="form-select" required>
                                <option value="room"       <?= $old['property_type']==='room'?'selected':'' ?>>Room (single occupancy)</option>
                                <option value="studio"     <?= $old['property_type']==='studio'?'selected':'' ?>>Studio</option>
                                <option value="whole_unit" <?= $old['property_type']==='whole_unit'?'selected':'' ?>>Whole unit / house</option>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Furnishing</label>
                            <select name="furnishing" class="form-select" required>
                                <option value="none"    <?= $old['furnishing']==='none'?'selected':'' ?>>None (unfurnished)</option>
                                <option value="partial" <?= $old['furnishing']==='partial'?'selected':'' ?>>Partial</option>
                                <option value="full"    <?= $old['furnishing']==='full'?'selected':'' ?>>Fully furnished</option>
                            </select>
                        </div>
                    </div>

                    <!-- Address -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Address</label>
                        <textarea name="address" rows="2"
                                  class="form-control <?= isset($errors['address']) ? 'is-invalid' : '' ?>"
                                  placeholder="Block/unit, road, area" required><?= e($old['address']) ?></textarea>
                        <?php if (isset($errors['address'])): ?>
                            <div class="invalid-feedback"><?= e($errors['address']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- City + Postcode + State -->
                    <div class="row g-3 mb-3">
                        <div class="col-sm-5">
                            <label class="form-label fw-semibold">City</label>
                            <input type="text" name="city"
                                   class="form-control <?= isset($errors['city']) ? 'is-invalid' : '' ?>"
                                   placeholder="Ayer Keroh"
                                   value="<?= e($old['city']) ?>" required>
                            <?php if (isset($errors['city'])): ?>
                                <div class="invalid-feedback"><?= e($errors['city']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-sm-3">
                            <label class="form-label fw-semibold">Postcode</label>
                            <input type="text" name="postcode" maxlength="5"
                                   class="form-control <?= isset($errors['postcode']) ? 'is-invalid' : '' ?>"
                                   placeholder="75450"
                                   value="<?= e($old['postcode']) ?>" required>
                            <?php if (isset($errors['postcode'])): ?>
                                <div class="invalid-feedback"><?= e($errors['postcode']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-sm-4">
                            <label class="form-label fw-semibold">State</label>
                            <select name="state" class="form-select" required>
                                <?php foreach ($states as $st): ?>
                                    <option value="<?= e($st) ?>" <?= $old['state']===$st?'selected':'' ?>>
                                        <?= e($st) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Rent + Deposit -->
                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Monthly rent (RM)</label>
                            <input type="number" name="monthly_rent" min="1" step="50"
                                   class="form-control <?= isset($errors['monthly_rent']) ? 'is-invalid' : '' ?>"
                                   placeholder="500"
                                   value="<?= e($old['monthly_rent']) ?>" required>
                            <?php if (isset($errors['monthly_rent'])): ?>
                                <div class="invalid-feedback"><?= e($errors['monthly_rent']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Security deposit (RM)</label>
                            <input type="number" name="deposit" min="0" step="50"
                                   class="form-control <?= isset($errors['deposit']) ? 'is-invalid' : '' ?>"
                                   placeholder="500"
                                   value="<?= e($old['deposit']) ?>">
                            <small class="text-secondary">Typically 1 month's rent.</small>
                            <?php if (isset($errors['deposit'])): ?>
                                <div class="invalid-feedback"><?= e($errors['deposit']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Description -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description <small class="text-secondary fw-normal">— optional</small></label>
                        <textarea name="description" rows="4" class="form-control"
                                  placeholder="What makes this place special? Distance to UTeM, amenities, vibe..."><?= e($old['description']) ?></textarea>
                    </div>

                    <!-- Facilities -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Facilities <small class="text-secondary fw-normal">— comma-separated</small></label>
                        <input type="text" name="facilities" class="form-control"
                               placeholder="WiFi, parking, washing machine, air-cond"
                               value="<?= e($old['facilities']) ?>">
                    </div>

                    <!-- Photos -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Photos <small class="text-secondary fw-normal">— 1 to 3 images, JPG/PNG/WEBP</small></label>
                        <input type="file" name="photos[]" accept="image/*" multiple
                               class="form-control <?= isset($errors['photos']) ? 'is-invalid' : '' ?>" required>
                        <small class="text-secondary">First photo will be the main listing image.</small>
                        <?php if (isset($errors['photos'])): ?>
                            <div class="invalid-feedback"><?= e($errors['photos']) ?></div>
                        <?php endif; ?>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-send me-1"></i> Submit for review
                    </button>

                    <p class="text-center text-secondary small mt-3 mb-0">
                        Your listing will be reviewed by admin before going live (usually within 1-2 days).
                    </p>
                </form>
            </div>
        </div>
    </div>
</div>

</body>
</html>