<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/uploads.php';
require_role('landlord');

$pdo = db();
$userId = current_user_id();

// EDIT mode?
$editId = (int)($_GET['edit'] ?? 0);
$isEdit = $editId > 0;
$existing = null;

if ($isEdit) {
    $stmt = $pdo->prepare("
        SELECT * FROM properties
         WHERE id = ?
           AND landlord_id = ?
         LIMIT 1
    ");
    $stmt->execute([$editId, $userId]);
    $existing = $stmt->fetch();

    if (!$existing) {
        http_response_code(404);
        die('Property not found or you are not the owner.');
    }

    // Don't allow edit on already-rented properties
    if (in_array($existing['status'], ['rented','booked'], true)) {
        set_flash('warning', 'Cannot edit a property that is currently rented or booked.');
        header('Location: /rentbridge/landlord/property.php?id=' . $editId);
        exit;
    }
}

$errors = [];
$old = [
    'title'         => $existing['title']         ?? '',
    'property_type' => $existing['property_type'] ?? 'room',
    'address'       => $existing['address']       ?? '',
    'city'          => $existing['city']          ?? '',
    'postcode'      => $existing['postcode']      ?? '',
    'state'         => $existing['state']         ?? 'Melaka',
    'monthly_rent'  => $existing['monthly_rent']  ?? '',
    'deposit'       => $existing['deposit']       ?? '',
    'description'   => $existing['description']   ?? '',
    'facilities'    => $existing['facilities']    ?? '',
    'furnishing'    => $existing['furnishing']    ?? 'partial',
    'viewing_mode'  => $existing['viewing_mode']  ?? 'either',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    foreach (array_keys($old) as $f) {
        $old[$f] = trim($_POST[$f] ?? '');
    }

    // Validate
    if ($old['title'] === '')        $errors['title'] = 'Title is required.';
    if ($old['address'] === '')      $errors['address'] = 'Address is required.';
    if ($old['city'] === '')         $errors['city'] = 'City is required.';
    if ($old['postcode'] === '')     $errors['postcode'] = 'Postcode is required.';
    if (!is_numeric($old['monthly_rent']) || (float)$old['monthly_rent'] <= 0) {
        $errors['monthly_rent'] = 'Enter a valid monthly rent.';
    }
    if ($old['deposit'] !== '' && !is_numeric($old['deposit'])) {
        $errors['deposit'] = 'Deposit must be a number.';
    }
    if (!in_array($old['property_type'], ['room','studio','whole_unit'], true)) {
        $errors['property_type'] = 'Invalid property type.';
    }
    if (!in_array($old['furnishing'], ['none','partial','full'], true)) {
        $errors['furnishing'] = 'Invalid furnishing option.';
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            if ($isEdit) {
                // UPDATE existing
                // Reset to pending_approval if currently rejected/hidden — admin reviews again
                $newStatus = $existing['status'];
                if ($existing['status'] === 'rejected') {
                    $newStatus = 'pending_approval';
                }

                $stmt = $pdo->prepare("
                    UPDATE properties
                       SET title = ?,
                           property_type = ?,
                           address = ?,
                           city = ?,
                           postcode = ?,
                           state = ?,
                           monthly_rent = ?,
                           deposit = ?,
                           description = ?,
                           facilities = ?,
                           furnishing = ?,
                           viewing_mode = ?,
                           status = ?
                     WHERE id = ?
                       AND landlord_id = ?
                ");
                $stmt->execute([
                    $old['title'],
                    $old['property_type'],
                    $old['address'],
                    $old['city'],
                    $old['postcode'],
                    $old['state'],
                    (float)$old['monthly_rent'],
                    (float)($old['deposit'] !== '' ? $old['deposit'] : 0),
                    $old['description'] !== '' ? $old['description'] : null,
                    $old['facilities']  !== '' ? $old['facilities']  : null,
                    $old['furnishing'],
                    $old['viewing_mode'],
                    $newStatus,
                    $editId,
                    $userId
                ]);

                $propertyId = $editId;
            } else {
                // CREATE new
                $stmt = $pdo->prepare("
                    INSERT INTO properties
                        (landlord_id, title, property_type, address, city, postcode, state,
                         monthly_rent, deposit, description, facilities, furnishing,
                         viewing_mode, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending_approval')
                ");
                $stmt->execute([
                    $userId,
                    $old['title'],
                    $old['property_type'],
                    $old['address'],
                    $old['city'],
                    $old['postcode'],
                    $old['state'],
                    (float)$old['monthly_rent'],
                    (float)($old['deposit'] !== '' ? $old['deposit'] : 0),
                    $old['description'] !== '' ? $old['description'] : null,
                    $old['facilities']  !== '' ? $old['facilities']  : null,
                    $old['furnishing'],
                    $old['viewing_mode']
                ]);
                $propertyId = (int)$pdo->lastInsertId();
            }

            // Handle photo uploads (if any)
            if (!empty($_FILES['photos']['name'][0])) {
                $count = count($_FILES['photos']['name']);
                for ($i = 0; $i < $count; $i++) {
                    if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) continue;

                    $tmpName = $_FILES['photos']['tmp_name'][$i];
                    $origName = $_FILES['photos']['name'][$i];
                    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

                    if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) continue;
                    if (filesize($tmpName) > 5 * 1024 * 1024) continue; // 5MB limit

                    // Save under uploads/properties/N_TIMESTAMP_RAND.ext
                    $newName = $propertyId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                    $dest = __DIR__ . '/../uploads/properties/' . $newName;
                    if (!is_dir(__DIR__ . '/../uploads/properties')) {
                        mkdir(__DIR__ . '/../uploads/properties', 0755, true);
                    }
                    if (move_uploaded_file($tmpName, $dest)) {
                        $isPrimary = $i === 0 && !$isEdit ? 1 : 0;
                        $stmt = $pdo->prepare("
                            INSERT INTO property_images (property_id, image_path, is_primary)
                            VALUES (?, ?, ?)
                        ");
                        $stmt->execute([$propertyId, 'uploads/properties/' . $newName, $isPrimary]);
                    }
                }
            }

            $pdo->commit();
            set_flash('success', $isEdit ? 'Property updated.' : 'Property submitted for review.');
            header('Location: /rentbridge/landlord/property.php?id=' . $propertyId);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors['general'] = 'Failed: ' . $e->getMessage();
        }
    }
}

$pageTitle = $isEdit ? 'Edit Property' : 'List New Property';
$activeNav = 'properties';

ob_start();
?>

<p class="small mb-3">
    <a href="<?= $isEdit ? '/rentbridge/landlord/property.php?id=' . $editId : '/rentbridge/landlord/properties.php' ?>"
       class="text-secondary text-decoration-none">
        <i class="bi bi-arrow-left"></i> Back
    </a>
</p>

<?php if (!empty($errors['general'])): ?>
    <div class="alert alert-danger"><?= e($errors['general']) ?></div>
<?php endif; ?>

<!-- WARNING BANNER -->
<div class="alert d-flex gap-3 align-items-start mb-4"
     style="background:#FFF4D6; border-color:#D4A017; color:#7C5E0A;">
    <i class="bi bi-exclamation-triangle-fill fs-4"></i>
    <div>
        <strong>Important: your listing will be inspected.</strong><br>
        <small>
            Once submitted, an agent will be auto-assigned to physically inspect this property.
            Make sure your details are accurate — wrong info may cause your listing to be rejected.
            <?php if ($isEdit && $existing['status'] === 'pending_approval'): ?>
                You can still edit this listing while it's pending. Once approved or being inspected, changes are restricted.
            <?php endif; ?>
        </small>
    </div>
</div>

<form method="POST" enctype="multipart/form-data" novalidate>
    <?= csrf_field() ?>

    <!-- BASIC INFO -->
    <div class="bg-white border rounded-3 p-4 mb-3">
        <h6 class="text-secondary text-uppercase small mb-3">Basic info</h6>

        <div class="mb-3">
            <label class="form-label fw-semibold">
                Listing title <small class="text-danger">*</small>
            </label>
            <input type="text" name="title"
                   class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>"
                   value="<?= e($old['title']) ?>"
                   placeholder="e.g. Cozy Single Room Near UTeM Main Gate" required>
            <?php if (isset($errors['title'])): ?>
                <div class="invalid-feedback"><?= e($errors['title']) ?></div>
            <?php endif; ?>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">
                    Type <small class="text-danger">*</small>
                </label>
                <select name="property_type" class="form-select" required>
                    <option value="room"       <?= $old['property_type']==='room'?'selected':'' ?>>Room (single)</option>
                    <option value="studio"     <?= $old['property_type']==='studio'?'selected':'' ?>>Studio apartment</option>
                    <option value="whole_unit" <?= $old['property_type']==='whole_unit'?'selected':'' ?>>Whole unit (for sharing)</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">
                    Furnishing <small class="text-danger">*</small>
                </label>
                <select name="furnishing" class="form-select" required>
                    <option value="none"    <?= $old['furnishing']==='none'?'selected':'' ?>>Unfurnished</option>
                    <option value="partial" <?= $old['furnishing']==='partial'?'selected':'' ?>>Partially furnished</option>
                    <option value="full"    <?= $old['furnishing']==='full'?'selected':'' ?>>Fully furnished</option>
                </select>
            </div>
        </div>
    </div>

    <!-- ADDRESS -->
    <div class="bg-white border rounded-3 p-4 mb-3">
        <h6 class="text-secondary text-uppercase small mb-3">Address</h6>

        <div class="mb-3">
            <label class="form-label fw-semibold">
                Full street address <small class="text-danger">*</small>
            </label>
            <textarea name="address" rows="2"
                      class="form-control <?= isset($errors['address']) ? 'is-invalid' : '' ?>"
                      placeholder="No 23, Jalan Sutera 5, Taman Sutera" required><?= e($old['address']) ?></textarea>
            <?php if (isset($errors['address'])): ?>
                <div class="invalid-feedback"><?= e($errors['address']) ?></div>
            <?php endif; ?>
        </div>

        <div class="row g-3">
            <div class="col-md-5">
                <label class="form-label fw-semibold">
                    City / area <small class="text-danger">*</small>
                </label>
                <input type="text" name="city"
                       class="form-control <?= isset($errors['city']) ? 'is-invalid' : '' ?>"
                       value="<?= e($old['city']) ?>"
                       placeholder="Ayer Keroh" required>
                <?php if (isset($errors['city'])): ?>
                    <div class="invalid-feedback"><?= e($errors['city']) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold">
                    Postcode <small class="text-danger">*</small>
                </label>
                <input type="text" name="postcode"
                       class="form-control <?= isset($errors['postcode']) ? 'is-invalid' : '' ?>"
                       value="<?= e($old['postcode']) ?>"
                       placeholder="75450" required>
                <?php if (isset($errors['postcode'])): ?>
                    <div class="invalid-feedback"><?= e($errors['postcode']) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">State</label>
                <input type="text" name="state" class="form-control"
                       value="<?= e($old['state']) ?>" placeholder="Melaka">
            </div>
        </div>
    </div>

    <!-- PRICING -->
    <div class="bg-white border rounded-3 p-4 mb-3">
        <h6 class="text-secondary text-uppercase small mb-3">Pricing</h6>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">
                    Monthly rent (RM) <small class="text-danger">*</small>
                </label>
                <input type="number" name="monthly_rent" min="0" step="50"
                       class="form-control <?= isset($errors['monthly_rent']) ? 'is-invalid' : '' ?>"
                       value="<?= e($old['monthly_rent']) ?>" required>
                <?php if (isset($errors['monthly_rent'])): ?>
                    <div class="invalid-feedback"><?= e($errors['monthly_rent']) ?></div>
                <?php endif; ?>
                <small class="text-secondary">
                    Pricing helper coming soon — for now, check nearby properties on the listings page.
                </small>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Deposit (RM)</label>
                <input type="number" name="deposit" min="0" step="50"
                       class="form-control <?= isset($errors['deposit']) ? 'is-invalid' : '' ?>"
                       value="<?= e($old['deposit']) ?>">
                <?php if (isset($errors['deposit'])): ?>
                    <div class="invalid-feedback"><?= e($errors['deposit']) ?></div>
                <?php endif; ?>
                <small class="text-secondary">Usually 1-2 months of rent.</small>
            </div>
        </div>
    </div>

    <!-- DESCRIPTION + FACILITIES -->
    <div class="bg-white border rounded-3 p-4 mb-3">
        <h6 class="text-secondary text-uppercase small mb-3">Details</h6>

        <div class="mb-3">
            <label class="form-label fw-semibold">Description</label>
            <textarea name="description" rows="4" class="form-control"
                      placeholder="Describe the property — what's special about it, the neighborhood, etc."><?= e($old['description']) ?></textarea>
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold">Facilities</label>
            <textarea name="facilities" rows="3" class="form-control"
                      placeholder="WiFi, aircond, washing machine, parking, etc."><?= e($old['facilities']) ?></textarea>
        </div>

        <div>
            <label class="form-label fw-semibold">Viewing arrangement</label>
            <select name="viewing_mode" class="form-select">
                <option value="either"        <?= $old['viewing_mode']==='either'?'selected':'' ?>>Either landlord or agent shows the property</option>
                <option value="landlord_led"  <?= $old['viewing_mode']==='landlord_led'?'selected':'' ?>>I (landlord) will show it myself</option>
                <option value="agent_led"     <?= $old['viewing_mode']==='agent_led'?'selected':'' ?>>Only agent shows it (I'm not always around)</option>
            </select>
            <small class="text-secondary">Students will never view alone — agent or landlord must be present.</small>
        </div>
    </div>

    <!-- PHOTOS -->
    <div class="bg-white border rounded-3 p-4 mb-3">
        <h6 class="text-secondary text-uppercase small mb-3">Photos</h6>

        <?php if ($isEdit && !empty($images)): ?>
            <p class="text-secondary small mb-2">
                Existing photos remain — upload more to add to the gallery.
            </p>
        <?php endif; ?>

        <input type="file" name="photos[]" class="form-control"
               accept="image/jpeg,image/png,image/webp" multiple>
        <small class="text-secondary">
            JPG, PNG, or WebP. Max 5MB each. Upload multiple — first one becomes the cover photo.
        </small>
    </div>

    <!-- ACTIONS -->
    <div class="d-flex justify-content-end gap-2">
        <a href="<?= $isEdit ? '/rentbridge/landlord/property.php?id=' . $editId : '/rentbridge/landlord/properties.php' ?>"
           class="btn btn-outline-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check2 me-1"></i>
            <?= $isEdit ? 'Save changes' : 'Submit listing' ?>
        </button>
    </div>
</form>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/landlord_layout.php';