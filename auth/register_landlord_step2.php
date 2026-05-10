<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/uploads.php';

// Must have completed Step 1 first
if (empty($_SESSION['landlord_signup'])) {
    header('Location: register_landlord.php');
    exit;
}

$step1 = $_SESSION['landlord_signup'];
$errors = [];
$old = [
    'title'         => '',
    'property_type' => '',
    'address'       => '',
    'city'          => '',
    'monthly_rent'  => '',
	'postcode'      => '',
	'state'         => 'Melaka', 
    'deposit'       => '',
    'furnishing'    => 'partial',
    'description'   => '',
    'facilities'    => '',
];

// Malaysia states + federal territories (alphabetical)
const MY_STATES = [
    'Johor',
    'Kedah',
    'Kelantan',
    'Kuala Lumpur',
    'Labuan',
    'Melaka',
    'Negeri Sembilan',
    'Pahang',
    'Penang',
    'Perak',
    'Perlis',
    'Putrajaya',
    'Sabah',
    'Sarawak',
    'Selangor',
    'Terengganu',
];

// ---- HANDLE STEP 2 SUBMIT ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    foreach (array_keys($old) as $field) {
        $old[$field] = trim($_POST[$field] ?? '');
    }

    // Validate property fields
    if ($old['title'] === '')        $errors['title']        = 'Property title is required.';
    if ($old['property_type'] === '')$errors['property_type']= 'Pick a property type.';
    if ($old['address'] === '')      $errors['address']      = 'Address is required.';
	if ($old['city'] === '')         $errors['city']         = 'City is required.';
	if ($old['postcode'] === '') {
    $errors['postcode'] = 'Postcode is required.';
	} elseif (!preg_match('/^\d{5}$/', $old['postcode'])) {
		$errors['postcode'] = 'Postcode must be 5 digits.';
	}
	if (!in_array($old['state'], MY_STATES, true)) {
    $errors['state'] = 'Please select a valid state.';
	}
	if (!is_numeric($old['monthly_rent']) || (float)$old['monthly_rent'] <= 0)
        $errors['monthly_rent'] = 'Enter a valid monthly rent amount.';
    if ($old['deposit'] !== '' && (!is_numeric($old['deposit']) || (float)$old['deposit'] < 0))
        $errors['deposit'] = 'Deposit must be 0 or more.';

    // Validate photos
    $photoCount = 0;
    $validPhotos = [];

    if (!empty($_FILES['photos']['name'][0])) {
        $totalFiles = count($_FILES['photos']['name']);

        if ($totalFiles > 3) {
            $errors['photos'] = 'You can upload up to 3 photos.';
        } else {
            // Loop and validate each
            for ($i = 0; $i < $totalFiles; $i++) {
                $file = [
                    'name'     => $_FILES['photos']['name'][$i],
                    'type'     => $_FILES['photos']['type'][$i],
                    'tmp_name' => $_FILES['photos']['tmp_name'][$i],
                    'error'    => $_FILES['photos']['error'][$i],
                    'size'     => $_FILES['photos']['size'][$i],
                ];
                $err = validate_image_upload($file);
                if ($err) {
                    $errors['photos'] = "Photo " . ($i + 1) . ": " . $err;
                    break;
                }
                $validPhotos[] = $file;
                $photoCount++;
            }
        }
    }

    if ($photoCount < 1 && !isset($errors['photos'])) {
        $errors['photos'] = 'Please upload at least 1 property photo.';
    }

    // ---- ALL VALID — save everything in ONE transaction ----
    if (empty($errors)) {
        try {
            $pdo = db();
            $pdo->beginTransaction();

            // 1. Insert into users
            $stmt = $pdo->prepare(
                'INSERT INTO users (email, password_hash, primary_role, status)
                 VALUES (?, ?, "landlord", "active")'
            );
            $stmt->execute([$step1['email'], $step1['password_hash']]);
            $userId = (int)$pdo->lastInsertId();

            // 2. Insert into landlords
            $stmt = $pdo->prepare(
                'INSERT INTO landlords (user_id, full_name, ic_no, phone)
                 VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$userId, $step1['full_name'], $step1['ic_no'], $step1['phone']]);

            // 3. Insert into properties
           $stmt = $pdo->prepare(
    'INSERT INTO properties
        (landlord_id, title, property_type, address, city, postcode, state,
         monthly_rent, deposit, description, facilities, furnishing)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);
$stmt->execute([
    $userId,
    $old['title'],
    $old['property_type'],
    $old['address'],
    $old['city'],
    $old['postcode'],   
	$old['state'],  
    (float)$old['monthly_rent'],
    $old['deposit'] === '' ? 0 : (float)$old['deposit'],
    $old['description'],
    $old['facilities'],
    $old['furnishing'],
]);
            $propertyId = (int)$pdo->lastInsertId();

            // 4. Move uploaded files + insert into property_images
            $stmt = $pdo->prepare(
                'INSERT INTO property_images (property_id, image_path, is_primary)
                 VALUES (?, ?, ?)'
            );
            foreach ($validPhotos as $i => $file) {
                $savedPath = save_property_image($file);
                $stmt->execute([$propertyId, $savedPath, $i === 0 ? 1 : 0]);
            }

            $pdo->commit();

            // Clear signup session
            unset($_SESSION['landlord_signup']);

            set_flash('success', 'Welcome! Your landlord account and first property are live.');
            header('Location: login.php');
            exit;

        } catch (Throwable $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $errors['general'] = 'Something went wrong: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign up · Landlord (Step 2) · RentBridge</title>
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
        <div class="col-md-9 col-lg-7">

            <div class="text-center mb-4">
                <a href="../index.php" class="text-decoration-none d-inline-flex align-items-center gap-2 mb-3" style="color:var(--rb-navy); font-family:'Fraunces',serif; font-size:1.6rem; font-weight:600;">
                    <span style="width:30px;height:30px;background:var(--rb-emerald);border-radius:8px;display:inline-flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:1rem;transform:rotate(-6deg);">R</span>
                    RentBridge
                </a>
            </div>

            <div class="bg-white rounded-4 p-4 p-md-5 border">

                <p class="small mb-2">
                    <a href="register_landlord.php" class="text-secondary text-decoration-none">
                        <i class="bi bi-arrow-left"></i> Back to step 1
                    </a>
                </p>

                <!-- Step indicator -->
                <div class="d-flex align-items-center gap-2 mb-3 small text-secondary fw-semibold">
                    <span class="badge bg-success">✓</span>
                    <span class="text-success">About you</span>
                    <span class="text-secondary mx-1">›</span>
                    <span class="badge bg-primary">2</span>
                    <span>Your first property</span>
                </div>

                <h1 class="mb-1">Add your first listing</h1>
                <p class="text-secondary mb-4">Step 2 of 2 — tell us about the property you'll be renting out.</p>

                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-danger"><?= e($errors['general']) ?></div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data" novalidate>
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Property title</label>
                        <input type="text" name="title" class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>"
                               placeholder="Cozy room near UTeM main gate" value="<?= e($old['title']) ?>" required>
                        <?php if (isset($errors['title'])): ?><div class="invalid-feedback"><?= e($errors['title']) ?></div><?php endif; ?>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Property type</label>
                            <select name="property_type" class="form-select <?= isset($errors['property_type']) ? 'is-invalid' : '' ?>" required>
                                <option value="">Choose...</option>
                                <option value="room"       <?= $old['property_type']==='room'?'selected':'' ?>>Room</option>
                                <option value="studio"     <?= $old['property_type']==='studio'?'selected':'' ?>>Studio</option>
                                <option value="whole_unit" <?= $old['property_type']==='whole_unit'?'selected':'' ?>>Whole unit</option>
                            </select>
                            <?php if (isset($errors['property_type'])): ?><div class="invalid-feedback"><?= e($errors['property_type']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Furnishing</label>
                            <select name="furnishing" class="form-select">
                                <option value="furnished"   <?= $old['furnishing']==='furnished'?'selected':'' ?>>Furnished</option>
                                <option value="partial"     <?= $old['furnishing']==='partial'?'selected':'' ?>>Partial</option>
                                <option value="unfurnished" <?= $old['furnishing']==='unfurnished'?'selected':'' ?>>Unfurnished</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Address</label>
                        <textarea name="address" rows="2" class="form-control <?= isset($errors['address']) ? 'is-invalid' : '' ?>" required><?= e($old['address']) ?></textarea>
                        <?php if (isset($errors['address'])): ?><div class="invalid-feedback"><?= e($errors['address']) ?></div><?php endif; ?>
                    </div>

<div class="row g-3 mb-3">
    <div class="col-md-5">
        <label class="form-label fw-semibold">City</label>
        <input type="text" name="city"
               class="form-control <?= isset($errors['city']) ? 'is-invalid' : '' ?>"
               placeholder="Ayer Keroh" value="<?= e($old['city']) ?>" required>
        <?php if (isset($errors['city'])): ?>
            <div class="invalid-feedback"><?= e($errors['city']) ?></div>
        <?php endif; ?>
    </div>

    <div class="col-md-3">
        <label class="form-label fw-semibold">Postcode</label>
        <input type="text" name="postcode"
               class="form-control <?= isset($errors['postcode']) ? 'is-invalid' : '' ?>"
               placeholder="75450" maxlength="5" inputmode="numeric"
               value="<?= e($old['postcode']) ?>" required>
        <?php if (isset($errors['postcode'])): ?>
            <div class="invalid-feedback"><?= e($errors['postcode']) ?></div>
        <?php endif; ?>
    </div>

    <div class="col-md-4">
        <label class="form-label fw-semibold">State</label>
        <select name="state"
                class="form-select <?= isset($errors['state']) ? 'is-invalid' : '' ?>" required>
            <?php foreach (MY_STATES as $st): ?>
                <option value="<?= e($st) ?>" <?= $old['state'] === $st ? 'selected' : '' ?>>
                    <?= e($st) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($errors['state'])): ?>
            <div class="invalid-feedback"><?= e($errors['state']) ?></div>
        <?php endif; ?>
    </div>
</div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Monthly rent (RM)</label>
                            <input type="number" step="0.01" min="0" name="monthly_rent"
                                   class="form-control <?= isset($errors['monthly_rent']) ? 'is-invalid' : '' ?>"
                                   placeholder="600" value="<?= e($old['monthly_rent']) ?>" required>
                            <?php if (isset($errors['monthly_rent'])): ?><div class="invalid-feedback"><?= e($errors['monthly_rent']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Deposit (RM) <small class="text-secondary fw-normal">— optional</small></label>
                            <input type="number" step="0.01" min="0" name="deposit"
                                   class="form-control <?= isset($errors['deposit']) ? 'is-invalid' : '' ?>"
                                   placeholder="600" value="<?= e($old['deposit']) ?>">
                            <?php if (isset($errors['deposit'])): ?><div class="invalid-feedback"><?= e($errors['deposit']) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description <small class="text-secondary fw-normal">— optional</small></label>
                        <textarea name="description" rows="3" class="form-control" placeholder="Quiet area, walking distance to UTeM..."><?= e($old['description']) ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Facilities <small class="text-secondary fw-normal">— optional, comma-separated</small></label>
                        <input type="text" name="facilities" class="form-control"
                               placeholder="WiFi, parking, washing machine" value="<?= e($old['facilities']) ?>">
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">Property photos <span class="text-danger">*</span></label>
                        <input type="file" name="photos[]" multiple accept="image/jpeg,image/png,image/webp"
                               class="form-control <?= isset($errors['photos']) ? 'is-invalid' : '' ?>" required>
                        <small class="text-secondary">1 to 3 images · JPG, PNG, or WEBP · max 5 MB each</small>
                        <?php if (isset($errors['photos'])): ?><div class="invalid-feedback d-block"><?= e($errors['photos']) ?></div><?php endif; ?>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        Create account &amp; list property <i class="bi bi-check-circle ms-1"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

</body>
</html>