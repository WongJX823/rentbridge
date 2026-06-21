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
    'viewing_mode'  => $existing['viewing_mode']  ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // Handle "delete document" action separately
    if (($_POST['action'] ?? '') === 'delete_doc' && $isEdit) {
        $docId = (int)($_POST['doc_id'] ?? 0);
        if ($docId > 0) {
            // Verify ownership: doc must belong to a property owned by this user
            $stmt = $pdo->prepare("
                SELECT pd.id FROM property_documents pd
                  JOIN properties p ON p.id = pd.property_id
                 WHERE pd.id = ? AND p.landlord_id = ?
                 LIMIT 1
            ");
            $stmt->execute([$docId, $userId]);
            if ($stmt->fetchColumn()) {
                require_once __DIR__ . '/../includes/uploads.php';
                delete_property_document($docId);
                set_flash('info', 'Document removed.');
            }
            header('Location: /rentbridge/landlord/add_property.php?edit=' . $editId);
            exit;
        }
    }

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

    // Validate viewing_mode (no "either")
    if (!in_array($old['viewing_mode'], ['landlord_led','agent_led'], true)) {
        $errors['viewing_mode'] = 'Please select a viewing arrangement.';
    }

    // Validate at least 1 photo
    $hasExistingPhotos = false;
    if ($isEdit) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM property_images WHERE property_id = ?");
        $stmt->execute([$editId]);
        $hasExistingPhotos = (int)$stmt->fetchColumn() > 0;
    }
    $hasNewPhotos = !empty($_FILES['photos']['name'][0]);

    if (!$hasExistingPhotos && !$hasNewPhotos) {
        $errors['photos'] = 'At least 1 photo is required.';
    }

    // Validate at least 1 document (new listings only, or if no existing docs in edit)
    $hasExistingDocs = false;
    if ($isEdit) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM property_documents WHERE property_id = ?");
        $stmt->execute([$editId]);
        $hasExistingDocs = (int)$stmt->fetchColumn() > 0;
    }
    $hasNewDocs = false;
    if (!empty($_FILES['document_files']['name'])) {
        foreach ($_FILES['document_files']['error'] as $err) {
            if ($err === UPLOAD_ERR_OK) { $hasNewDocs = true; break; }
        }
    }
    if (!$hasExistingDocs && !$hasNewDocs) {
        $errors['documents'] = 'At least 1 ownership document is required to verify your property.';
    }

    // Duplicate address check (new properties only, or edit changing address)
    if (!$isEdit && empty($errors['address'])) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM properties
             WHERE landlord_id = ?
               AND address = ?
               AND status NOT IN ('rejected','deleted')
        ");
        $stmt->execute([$userId, $old['address']]);
        if ((int)$stmt->fetchColumn() > 0) {
            $errors['address'] = 'You already have an active listing at this address. Please edit the existing one instead.';
        }
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
                $count = min(count($_FILES['photos']['name']), 10); // ← cap at 10 server-side
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

            // Handle document uploads (multi-file)
            $docErrors = [];
            $docSavedCount = 0;

            if (!empty($_FILES['document_files']['name']) && is_array($_FILES['document_files']['name'])) {
                foreach ($_FILES['document_files']['name'] as $i => $name) {
                    // Skip empty slots
                    if ($_FILES['document_files']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
                    if ($_FILES['document_files']['error'][$i] !== UPLOAD_ERR_OK) {
                        $docErrors[] = "File #" . ($i + 1) . ": upload error code " . $_FILES['document_files']['error'][$i];
                        continue;
                    }

                    // Reconstruct $_FILES-style array for the helper
                    $fileForHelper = [
                        'name'     => $_FILES['document_files']['name'][$i],
                        'type'     => $_FILES['document_files']['type'][$i],
                        'tmp_name' => $_FILES['document_files']['tmp_name'][$i],
                        'error'    => $_FILES['document_files']['error'][$i],
                        'size'     => $_FILES['document_files']['size'][$i],
                    ];

                    $docType  = $_POST['document_types'][$i] ?? 'other';
                    $docNotes = trim($_POST['document_notes'][$i] ?? '');

                    $result = save_property_document(
                        $propertyId,
                        $userId,
                        $docType,
                        $fileForHelper,
                        $docNotes !== '' ? $docNotes : null
                    );

                    if ($result['ok']) {
                        $docSavedCount++;
                    } else {
                        $docErrors[] = "File '" . htmlspecialchars($name) . "': " . $result['error'];
                    }
                }
            }

            if (!empty($docErrors)) {
                set_flash('warning', $docSavedCount . ' document(s) saved. Issues: ' . implode('; ', $docErrors));
            }
            
            $pdo->commit();

            // Auto-assign agent:
            //  - always on new properties
            //  - on edits when the property has no agent assigned yet (e.g. after a rejection reset)
            require_once __DIR__ . '/../includes/agent_assignment.php';
            $needsAssignment = false;
            if (!$isEdit) {
                $needsAssignment = true;
            } else {
                // Check if property is now pending_approval but has no assigned agent
                $stmt = $pdo->prepare("SELECT assigned_agent_id, status FROM properties WHERE id = ?");
                $stmt->execute([$propertyId]);
                $latest = $stmt->fetch();
                if ($latest && $latest['status'] === 'pending_approval' && empty($latest['assigned_agent_id'])) {
                    $needsAssignment = true;
                }
            }

            if ($needsAssignment) {
                $assignResult = assign_agent_to_property((int)$propertyId);
                if ($assignResult['ok']) {
                    set_flash('success', $isEdit
                        ? 'Property updated and sent for re-review. An agent has been assigned.'
                        : 'Property submitted! An agent has been assigned to verify your listing.');
                } else {
                    set_flash('warning', ($isEdit ? 'Property updated' : 'Property submitted')
                        . ', but no agent could be assigned right now: ' . $assignResult['error']);
                }
            } else {
                set_flash('success', 'Property updated successfully.');
            }

            if (!empty($docErrors)) {
                set_flash('warning', $docSavedCount . ' document(s) saved. Issues: ' . implode('; ', $docErrors));
            }

            // Notify landlord if their rent is > RM200 below the market benchmark for same city+type
            if (!empty($old['city']) && !empty($old['property_type'])) {
                $bmStmt = $pdo->prepare("
                    SELECT ROUND(AVG(monthly_rent), 0) AS avg_rent
                      FROM properties
                     WHERE city = ? AND property_type = ?
                       AND status IN ('available','booked','rented')
                       AND id != ?
                ");
                $bmStmt->execute([$old['city'], $old['property_type'], $propertyId]);
                $benchmark = (float)$bmStmt->fetchColumn();
                $enteredRent = (float)$old['monthly_rent'];
                if ($benchmark > 0 && $enteredRent < ($benchmark - 200)) {
                    if (function_exists('notify')) {
                        notify(
                            $userId,
                            'pricing_warning',
                            'Your rent may be too low',
                            sprintf(
                                'Your listing "%s" is set at RM%s, which is more than RM200 below the market average of RM%s for %s %s properties. Consider reviewing your price.',
                                $old['title'],
                                number_format($enteredRent, 0),
                                number_format($benchmark, 0),
                                $old['city'],
                                $old['property_type']
                            ),
                            "/rentbridge/landlord/add_property.php?edit={$propertyId}"
                        );
                    }
                }
            }

            header('Location: /rentbridge/landlord/properties.php');
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
            <div class="mt-3">
                <label class="form-label fw-semibold">
                    Google Maps link
                    <small class="text-secondary fw-normal">— helps pricing accuracy</small>
                </label>
                <input type="url" name="maps_url" id="mapsUrlInput"
                    class="form-control"
                    value="<?= e($old['maps_url'] ?? '') ?>"
                    placeholder="https://maps.app.goo.gl/... or https://www.google.com/maps/@2.3138,102.3192,17z">
                <small class="text-secondary">
                    Open Google Maps, find your property, click "Share" → copy link. The pricing benchmark uses distance to UTeM.
                </small>
                <div id="mapsUrlStatus" class="small mt-1"></div>
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
                <label class="form-label fw-semibold">Furnishing</label>
                <select name="furnishing" class="form-select">
                    <option value="none"    <?= $old['furnishing'] === 'none'    ? 'selected' : '' ?>>Unfurnished</option>
                    <option value="partial" <?= $old['furnishing'] === 'partial' ? 'selected' : '' ?>>Partially furnished</option>
                    <option value="full"    <?= $old['furnishing'] === 'full'    ? 'selected' : '' ?>>Fully furnished</option>
                </select>
                <small class="text-secondary">
                    Furnishing significantly affects rental value.
                </small>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label fw-semibold">Facilities</label>
            <textarea name="facilities" rows="3" class="form-control"
                      placeholder="WiFi, aircond, washing machine, parking, etc."><?= e($old['facilities']) ?></textarea>
            <small class="text-secondary">
            List one per line or comma-separated. Common items recognized:
            <code>wifi</code>, <code>aircond</code>, <code>parking</code>, <code>washing machine</code>, 
            <code>fridge</code>, <code>kitchen</code>, <code>attached bath</code>, <code>balcony</code>, 
            <code>security</code>, <code>gym</code>, <code>pool</code>, <code>cctv</code>, <code>tv</code>.
            </small>
        </div>  

        <div>
            <label class="form-label fw-semibold">
                Inspection &amp; viewing arrangement <small class="text-danger">*</small>
            </label>
            <select name="viewing_mode"
                    class="form-select <?= isset($errors['viewing_mode']) ? 'is-invalid' : '' ?>"
                    required>
                <option value="" disabled <?= !in_array($old['viewing_mode'],['landlord_led','agent_led']) ? 'selected' : '' ?>>— Select an option —</option>
                <option value="landlord_led" <?= $old['viewing_mode']==='landlord_led'?'selected':'' ?>>I (landlord) will be present for all viewings</option>
                <option value="agent_led"    <?= $old['viewing_mode']==='agent_led'?'selected':'' ?>>Agent-led — I will hand over a key or lockbox code for agent access</option>
            </select>
            <?php if (isset($errors['viewing_mode'])): ?>
                <div class="invalid-feedback"><?= e($errors['viewing_mode']) ?></div>
            <?php endif; ?>
            <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" id="inspectionConsent" name="inspection_consent" value="1"
                       <?= !empty($_POST['inspection_consent']) ? 'checked' : '' ?> required>
                <label class="form-check-label small" for="inspectionConsent">
                    I agree to allow the assigned RentBridge agent to inspect this property and facilitate viewings on my behalf.
                    <span class="text-danger">*</span>
                </label>
            </div>
            <small class="text-secondary">Students will never view alone — agent or landlord must be present.</small>
        </div>
    </div>

    <!-- PRICING -->
    <div class="bg-white border rounded-3 p-4 mb-3">
        <h6 class="text-secondary text-uppercase small mb-3">Pricing</h6>

        <!-- BENCHMARK WIDGET (populated by JS) -->
        <div id="pricingBenchmark" class="alert d-none mb-3 d-flex gap-3 align-items-start"
            style="border: 1px solid; background: #F4FBF7;">
            <i class="bi bi-bar-chart-fill" id="benchmarkIcon"
            style="font-size: 1.5rem; color: #2E8B57; flex-shrink: 0;"></i>
            <div class="flex-grow-1">
                <strong id="benchmarkTitle"></strong>
                <span id="benchmarkConfidence" class="badge ms-2"></span>
                <div id="benchmarkBody" class="small mt-1"></div>
            </div>
        </div>

        <div id="pricingNoData" class="alert alert-light border d-none small mb-3">
            <i class="bi bi-info-circle text-secondary"></i>
            No comparable listings yet in your area — be the first!
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label fw-semibold">
                    Monthly rent (RM) <small class="text-danger">*</small>
                </label>
                <input type="number" name="monthly_rent" min="0" step="50" id="monthlyRentInput"
                    class="form-control <?= isset($errors['monthly_rent']) ? 'is-invalid' : '' ?>"
                    value="<?= e($old['monthly_rent']) ?>" required>
                <?php if (isset($errors['monthly_rent'])): ?>
                    <div class="invalid-feedback"><?= e($errors['monthly_rent']) ?></div>
                <?php endif; ?>
                <small class="text-secondary">
                    See market benchmark above to help price competitively.
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

    <?php
    // In edit mode, fetch existing photos
    $existingPhotos = [];
    if (!empty($editId)) {
        $stmt = $pdo->prepare("
            SELECT id, image_path, is_primary
            FROM property_images
            WHERE property_id = ?
            ORDER BY is_primary DESC, id ASC
        ");
        $stmt->execute([$editId]);
        $existingPhotos = $stmt->fetchAll();
    }
    ?>

    <?php if (!empty($existingPhotos)): ?>
        <div class="mb-3">
            <label class="form-label fw-semibold">Existing photos</label>
            <p class="small text-secondary mb-2">
                Click <i class="bi bi-x-circle text-danger"></i> to remove a photo.
            </p>
            <div class="row g-2" id="existingPhotosGrid">
                <?php foreach ($existingPhotos as $photo): ?>
                    <div class="col-6 col-md-4 col-lg-3" id="photoRow_<?= (int)$photo['id'] ?>">
                        <div class="position-relative existing-photo-card">
                            <img src="/rentbridge/<?= e($photo['image_path']) ?>"
                                class="w-100 rounded"
                                style="aspect-ratio:1; object-fit:cover; border:1px solid rgba(15,44,82,0.1);">
                            
                            <?php if ((int)$photo['is_primary'] === 1): ?>
                                <span class="badge bg-success position-absolute"
                                    style="top:6px; left:6px;">
                                    <i class="bi bi-star-fill"></i> Primary
                                </span>
                            <?php endif; ?>

                            <button type="button"
                                    class="btn btn-sm btn-danger delete-photo-btn position-absolute"
                                    style="top:6px; right:6px; width:28px; height:28px; padding:0;
                                        border-radius:50%; display:flex; align-items:center;
                                        justify-content:center;"
                                    data-image-id="<?= (int)$photo['id'] ?>"
                                    title="Delete this photo">
                                <i class="bi bi-x"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <hr class="my-4">
        </div>
    <?php endif; ?>

    <!-- PHOTOS -->
    <div class="bg-white border rounded-3 p-4 mb-3">
        <h6 class="text-secondary text-uppercase small mb-3">Photos</h6>
        <p class="small text-secondary mb-2">
            Property must have at least one image.
        </p>

        <?php if ($isEdit && !empty($images)): ?>
            <p class="text-secondary small mb-2">
                <i class="bi bi-info-circle"></i>
                Existing photos remain — upload more to add to the gallery.
            </p>
        <?php endif; ?>

        <!-- Hidden file input (managed by JS) -->
        <input type="file" name="photos[]" id="photoInput"
            accept="image/jpeg,image/png,image/webp" multiple
            style="display:none;">

        <!-- Visible drop zone / picker button -->
        <div id="photoDropzone"
            style="border: 2px dashed rgba(15,44,82,0.2); border-radius: 8px;
                    padding: 24px; text-align: center; cursor: pointer;
                    background: #FAF8F3; transition: all 0.15s;">
            <i class="bi bi-cloud-arrow-up" style="font-size: 2rem; color: rgba(15,44,82,0.4);"></i>
            <div class="mt-2">
                <strong>Click to add photos</strong>
                <span class="text-secondary"> or drag &amp; drop here</span>
            </div>
            <small class="text-secondary">
                JPG, PNG, or WebP · Max 5MB each · Up to <strong>10 photos</strong> per upload
            </small>
        </div>

        <!-- Selected files preview -->
        <div id="photoPreview" class="row g-2 mt-3" style="display:none;"></div>

        <div id="photoCounter" class="small text-secondary mt-2"></div>
    </div>

    <script>
    (function() {
        const MAX_FILES = 10;
        const MAX_SIZE = 5 * 1024 * 1024; // 5MB
        const ALLOWED = ['image/jpeg','image/png','image/webp'];

        const fileInput   = document.getElementById('photoInput');
        const dropzone    = document.getElementById('photoDropzone');
        const previewArea = document.getElementById('photoPreview');
        const counter     = document.getElementById('photoCounter');


        // Maintained file list (persists across multiple "add files" clicks)
        let collectedFiles = [];

        function updateInputFiles() {
            // Use DataTransfer to write the file list back to the input
            // (so it gets submitted with the form)
            const dt = new DataTransfer();
            collectedFiles.forEach(f => dt.items.add(f));
            fileInput.files = dt.files;
        }

        function updateCounter() {
            if (collectedFiles.length === 0) {
                counter.textContent = '';
                previewArea.style.display = 'none';
                return;
            }
            counter.innerHTML = `<strong>${collectedFiles.length}</strong> photo${collectedFiles.length === 1 ? '' : 's'} selected`
                + ` · Total: ${formatBytes(collectedFiles.reduce((sum, f) => sum + f.size, 0))}`;
            previewArea.style.display = 'flex';
        }

        function formatBytes(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
            return (bytes / 1024 / 1024).toFixed(1) + ' MB';
        }

        function renderPreviews() {
            previewArea.innerHTML = '';
            collectedFiles.forEach((file, idx) => {
                const col = document.createElement('div');
                col.className = 'col-md-3 col-6';

                const card = document.createElement('div');
                card.style.cssText = `
                    position: relative; border: 1px solid rgba(15,44,82,0.1);
                    border-radius: 8px; overflow: hidden; aspect-ratio: 4/3;
                    background: #F4F4EE;
                `;

                const img = document.createElement('img');
                img.style.cssText = 'width:100%; height:100%; object-fit:cover;';
                img.src = URL.createObjectURL(file);
                img.onload = () => URL.revokeObjectURL(img.src);
                card.appendChild(img);

                // Cover badge for first
                if (idx === 0) {
                    const badge = document.createElement('span');
                    badge.className = 'badge bg-success';
                    badge.style.cssText = 'position:absolute; top:6px; left:6px;';
                    badge.textContent = 'Cover';
                    card.appendChild(badge);
                }

                // Remove button
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.innerHTML = '×';
                removeBtn.style.cssText = `
                    position:absolute; top:6px; right:6px;
                    width:24px; height:24px; border-radius:50%;
                    background:rgba(0,0,0,0.6); color:white; border:none;
                    font-size:14px; line-height:1; cursor:pointer;
                `;
                removeBtn.title = 'Remove';
                removeBtn.onclick = () => {
                    collectedFiles.splice(idx, 1);
                    updateInputFiles();
                    renderPreviews();
                    updateCounter();
                };
                card.appendChild(removeBtn);

                // Filename label
                const label = document.createElement('div');
                label.style.cssText = `
                    position:absolute; bottom:0; left:0; right:0;
                    background:rgba(0,0,0,0.5); color:white;
                    font-size:0.7rem; padding:3px 6px;
                    white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
                `;
                label.textContent = file.name;
                card.appendChild(label);

                col.appendChild(card);
                previewArea.appendChild(col);
            });
        }

        function addFiles(newFileList) {
            const newFiles = Array.from(newFileList);
            const rejected = [];

            for (const file of newFiles) {
                // Validate type
                if (!ALLOWED.includes(file.type)) {
                    rejected.push(`${file.name} (wrong type)`);
                    continue;
                }
                // Validate size
                if (file.size > MAX_SIZE) {
                    rejected.push(`${file.name} (too large)`);
                    continue;
                }
                // Validate max count
                if (collectedFiles.length >= MAX_FILES) {
                    rejected.push(`${file.name} (max ${MAX_FILES} reached)`);
                    continue;
                }
                // Deduplicate by name+size
                const isDuplicate = collectedFiles.some(
                    f => f.name === file.name && f.size === file.size
                );
                if (isDuplicate) {
                    rejected.push(`${file.name} (already added)`);
                    continue;
                }
                collectedFiles.push(file);
            }

            if (rejected.length > 0) {
                alert('Some files were not added:\n\n' + rejected.join('\n'));
            }

            updateInputFiles();
            renderPreviews();
            updateCounter();
        }

        // Click anywhere on dropzone → open file picker
        dropzone.addEventListener('click', () => fileInput.click());

        // File selected via picker
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                addFiles(e.target.files);
            }
        });

        // Drag & drop
        dropzone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropzone.style.background = '#E4F2EA';
            dropzone.style.borderColor = '#2E8B57';
        });
        dropzone.addEventListener('dragleave', () => {
            dropzone.style.background = '#FAF8F3';
            dropzone.style.borderColor = 'rgba(15,44,82,0.2)';
        });
        dropzone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropzone.style.background = '#FAF8F3';
            dropzone.style.borderColor = 'rgba(15,44,82,0.2)';
            if (e.dataTransfer.files.length > 0) {
                addFiles(e.dataTransfer.files);
            }
        });
    })();

    // Delete existing property photo
    document.querySelectorAll('.delete-photo-btn').forEach(btn => {
        btn.addEventListener('click', async function() {
            if (!confirm('Delete this photo? This cannot be undone.')) return;

            const imageId = this.dataset.imageId;
            const row = document.getElementById('photoRow_' + imageId);

            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

            try {
                const formData = new FormData();
                formData.append('_csrf', '<?= csrf_token() ?>');
                formData.append('image_id', imageId);

                const resp = await fetch('/rentbridge/landlord/delete_property_image.php', {
                    method: 'POST',
                    body: formData,
                });
                const data = await resp.json();

                if (data.ok) {
                    row?.remove();
                } else {
                    alert('Failed: ' + (data.error || 'Unknown error'));
                    this.disabled = false;
                    this.innerHTML = '<i class="bi bi-x"></i>';
                }
            } catch (err) {
                alert('Network error: ' + err.message);
                this.disabled = false;
                this.innerHTML = '<i class="bi bi-x"></i>';
            }
        });
    });

    </script>

<!-- OWNERSHIP DOCUMENTS -->
<div class="bg-white border rounded-3 p-4 mb-3">
    <h6 class="text-secondary text-uppercase small mb-3">
        Ownership Documents
        <span class="badge bg-secondary ms-1" style="font-size: 0.7rem; text-transform: none;">private</span>
    </h6>
     <p class="small text-secondary mb-3">
        Upload up to 3 documents (geran, IC copy, utility bill, etc).
        PDF, JPG, or PNG · max 5MB each.
    </p>

    <div class="alert alert-light border small mb-3" style="background:#FAF8F3;">
        <i class="bi bi-shield-lock text-secondary"></i>
        <strong>Only you, admin, and your assigned agent can see these.</strong>
        Students never see your documents. Used to verify you're the legitimate owner.
    </div>
    <?php if (isset($errors['documents'])): ?>
        <div class="alert alert-danger small py-2"><?= e($errors['documents']) ?></div>
    <?php endif; ?>

     <?php for ($i = 0; $i < 3; $i++): ?>
        <div class="row g-2 mb-3 align-items-end pb-3 border-bottom">
            <div class="col-md-4">
                <label class="form-label small fw-semibold">Document type</label>
                <select name="document_types[]" class="form-select form-select-sm">
                    <option value="">— Select type —</option>
                    <option value="ownership_proof">Ownership Proof</option>
                    <option value="utility_bill">Utility bill</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label small fw-semibold">File</label>
                <input type="file" name="document_files[]"
                       class="form-control form-control-sm"
                       accept=".pdf,.jpg,.jpeg,.png">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold">Notes (optional)</label>
                <input type="text" name="document_notes[]"
                       class="form-control form-control-sm"
                       placeholder="e.g. Page 1 of 3">
            </div>
        </div>
    <?php endfor; ?>
    </div>


    <?php
    // Show existing documents if editing
    if ($isEdit) {
        require_once __DIR__ . '/../includes/uploads.php';
        $existingDocs = function_exists('get_property_documents')
            ? get_property_documents($editId)
            : [];
        if (!empty($existingDocs)):
    ?>
        <div class="mt-3">
            <h6 class="small text-secondary mb-2">Existing documents</h6>
            <table class="table table-sm border">
                <thead style="background:#F4F4EE;">
                    <tr>
                        <th>Type</th>
                        <th>File</th>
                        <th>Notes</th>
                        <th>Uploaded</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($existingDocs as $doc): ?>
                        <tr>
                            <td><small><?= e(ucwords(str_replace('_', ' ', $doc['document_type']))) ?></small></td>
                            <td><small><a href="/rentbridge/<?= e($doc['file_path']) ?>" target="_blank">View</a></small></td>
                            <td><small><?= e($doc['notes'] ?: '—') ?></small></td>
                            <td><small><?= e(date('d M Y', strtotime($doc['uploaded_at']))) ?></small></td>
                            <td><small>
                                    <button type="button"
                                        class="btn btn-sm btn-outline-danger delete-doc-btn"
                                        data-doc-id="<?= (int)$doc['id'] ?>">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php
        endif;
    }
    ?>
</div>

<!-- SUBMIT -->
<div class="d-flex justify-content-end gap-2 mb-5">
    <a href="<?= $isEdit ? '/rentbridge/landlord/property.php?id=' . $editId : '/rentbridge/landlord/properties.php' ?>"
       class="btn btn-outline-secondary">
        Cancel
    </a>
    <button type="submit" class="btn btn-primary px-4">
        <i class="bi bi-check-circle me-1"></i>
        <?= $isEdit ? 'Save changes' : 'Upload property' ?>
    </button>
</div>

</form>
<script>
(function() {
    const benchmarkBox = document.getElementById('pricingBenchmark');
    const noDataBox    = document.getElementById('pricingNoData');
    const titleEl      = document.getElementById('benchmarkTitle');
    const confidenceEl = document.getElementById('benchmarkConfidence');
    const bodyEl       = document.getElementById('benchmarkBody');
    const iconEl       = document.getElementById('benchmarkIcon');

    const cityInput  = document.querySelector('input[name="city"]');
    const typeInput  = document.querySelector('select[name="property_type"]');
    const furnInput  = document.querySelector('select[name="furnishing"]');
    const rentInput  = document.getElementById('monthlyRentInput');
    const facilitiesInput = document.querySelector('textarea[name="facilities"]');
    const mapsUrlInput    = document.getElementById('mapsUrlInput');
    const mapsStatusEl    = document.getElementById('mapsUrlStatus');

    let lastQuery = '';
    let timer = null;

async function fetchBenchmark() {
        const city = (cityInput?.value || '').trim();
        const type = typeInput?.value || '';
        const furn = furnInput?.value || '';
        const facilities = (facilitiesInput?.value || '').trim();
        const mapsUrl = (mapsUrlInput?.value || '').trim();
        const query = `${city}|${type}|${furn}|${facilities}|${mapsUrl}`;
        
        if (query === lastQuery) return;
        lastQuery = query;

        // Don't bother fetching if NOTHING is filled in
        const anyInput = city || facilities || mapsUrl;
        if (!anyInput) {
            benchmarkBox.classList.add('d-none');
            noDataBox.classList.add('d-none');
            if (mapsStatusEl) mapsStatusEl.innerHTML = '';
            return;
        }

        try {
            const params = new URLSearchParams({
                city, type, furnishing: furn, facilities, maps_url: mapsUrl,
            });
            const resp = await fetch('/rentbridge/landlord/pricing_check.php?' + params);
            const data = await resp.json();
            console.log('[pricing] response:', data);

            // Show maps URL status
            if (mapsStatusEl) {
                if (mapsUrl && data.coords_extracted) {
                    mapsStatusEl.innerHTML = `<i class="bi bi-check-circle text-success"></i> Coordinates extracted · ${data.distance_km} km from UTeM`;
                    mapsStatusEl.className = 'small text-success mt-1';
                } else if (mapsUrl && !data.coords_extracted) {
                    mapsStatusEl.innerHTML = `<i class="bi bi-exclamation-circle text-warning"></i> Couldn't extract coordinates from this link`;
                    mapsStatusEl.className = 'small text-warning mt-1';
                } else {
                    mapsStatusEl.innerHTML = '';
                }
            }

            const hasAnyValue = data.has_data || data.partial_preview;

            if (!hasAnyValue) {
                benchmarkBox.classList.add('d-none');
                noDataBox.classList.remove('d-none');
                return;
            }

            noDataBox.classList.add('d-none');
            benchmarkBox.classList.remove('d-none');    

            const tierLabel = data.match_tier === 'exact'
                ? 'exact match'
                : data.match_tier === 'same type'
                    ? 'same city + same type'
                    : 'same city';

            if (data.has_data) {
                titleEl.textContent = `Suggested: RM ${formatRM(data.suggested)}`;
            } else {
                titleEl.textContent = `Partial estimate: +RM ${formatRM(data.suggested)} from features`;
            }

            const confColor = data.confidence === 'high' ? 'success'
                            : data.confidence === 'medium' ? 'warning'
                            : 'secondary';
            confidenceEl.className = `badge bg-${confColor}`;
            confidenceEl.textContent = `${data.confidence} confidence`;

            // Build breakdown
            let breakdown = '';
            if (data.has_data) {
                breakdown += `
                    <strong>Base market:</strong> RM ${formatRM(data.base_market)}
                    <small class="text-secondary">(median of ${data.count} ${tierLabel} listings)</small>`;
            } else {
                breakdown += `
                    <em class="text-secondary">No city filled yet — base market price not available.
                    Showing premium estimates from filled fields:</em>`;
            }

            if (data.distance_km !== null) {
                const distSign = data.distance_premium >= 0 ? '+' : '−';
                breakdown += `<br>
                    <strong>Distance (${data.distance_km} km from UTeM):</strong>
                    ${distSign}RM ${formatRM(Math.abs(data.distance_premium))}`;
            }

            if (data.amenity_premium > 0) {
                breakdown += `<br>
                    <strong>Amenities (+RM ${formatRM(data.amenity_premium)}):</strong>
                    <small class="text-secondary">${data.amenities_matched.join(', ')}</small>`;
            }

            if (data.furnishing_premium !== 0) {
                const sign = data.furnishing_premium >= 0 ? '+' : '−';
                breakdown += `<br>
                    <strong>Furnishing (${furn}):</strong>
                    ${sign}RM ${formatRM(Math.abs(data.furnishing_premium))}`;
            }

            breakdown += `<br><br>
                <small class="text-secondary">
                    Sample range: RM ${formatRM(data.min)} – RM ${formatRM(data.max)}
                </small>`;

            bodyEl.innerHTML = breakdown;

            // Color tinting based on user's current rent vs benchmark
            const currentRent = parseFloat(rentInput?.value || 0);
            if (currentRent > 0) {
                if (currentRent > data.max * 1.15) {
                    iconEl.style.color = '#dc3545';
                    benchmarkBox.style.borderColor = '#dc3545';
                    benchmarkBox.style.background = '#FFF5F5';
                    bodyEl.innerHTML += `<div class="mt-2 text-danger small">⚠️ Your rent is significantly above market range — may receive fewer applications.</div>`;
                } else if (currentRent < data.min * 0.85) {
                    iconEl.style.color = '#D4A017';
                    benchmarkBox.style.borderColor = '#D4A017';
                    benchmarkBox.style.background = '#FFF4D6';
                    bodyEl.innerHTML += `<div class="mt-2 text-warning small">ℹ️ Your rent is below market — you may be undercharging.</div>`;
                } else {
                    iconEl.style.color = '#2E8B57';
                    benchmarkBox.style.borderColor = '#2E8B57';
                    benchmarkBox.style.background = '#F4FBF7';
                    bodyEl.innerHTML += `<div class="mt-2 text-success small">✓ Your price is within market range.</div>`;
                }
            }
        } catch (err) {
            console.warn('Benchmark fetch failed:', err);
        }
    }

    function formatRM(n) {
        return Number(n).toLocaleString('en-MY', { maximumFractionDigits: 0 });
    }

    function debouncedFetch() {
        clearTimeout(timer);
        timer = setTimeout(fetchBenchmark, 400);
    }

    cityInput?.addEventListener('input', debouncedFetch);
    typeInput?.addEventListener('change', debouncedFetch);
    furnInput?.addEventListener('change', debouncedFetch);
    rentInput?.addEventListener('input', debouncedFetch);
    facilitiesInput?.addEventListener('input', debouncedFetch);
    mapsUrlInput?.addEventListener('input', debouncedFetch);

// Force initial fetch always (avoids stale state)
    setTimeout(fetchBenchmark, 200);

    // Sanity log
    console.log('[pricing] inputs:', {
        city: !!cityInput,
        type: !!typeInput,
        furn: !!furnInput,
        rent: !!rentInput,
        facilities: !!facilitiesInput,
        maps: !!mapsUrlInput,
    });})();

    document.querySelectorAll('.delete-doc-btn').forEach(btn => {
    btn.addEventListener('click', function() {
        const form = document.createElement('form');
        form.method = 'POST';

        form.innerHTML = `
            <input type="hidden" name="_csrf"
                   value="<?= csrf_token() ?>">
            <input type="hidden" name="action"
                   value="delete_doc">
            <input type="hidden" name="doc_id"
                   value="${this.dataset.docId}">
        `;

        document.body.appendChild(form);
        form.submit();
    });
});

document.querySelector('form').addEventListener('submit', function(e) {
    // Count existing photos that aren't being deleted + newly selected files
    const existingPhotos = document.querySelectorAll('#existingPhotosGrid > div').length;
    const fileInput = document.querySelector('input[name="photos[]"]');
    const newPhotos = fileInput ? fileInput.files.length : 0;
    
    const total = existingPhotos + newPhotos;
    
    if (total < 1) {
        e.preventDefault();
        alert('Please upload at least 1 photo of the property.');
        fileInput?.scrollIntoView({behavior: 'smooth', block: 'center'});
        fileInput?.focus();
    }
});
</script>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/landlord_layout.php';