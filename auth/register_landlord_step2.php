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

const MELAKA_CITIES = [
    'Ayer Keroh', 'Bukit Beruang', 'Durian Tunggal', 'Melaka Tengah',
    'Cheng', 'Batu Berendam', 'Bertam', 'Alor Gajah', 'Krubong', 'Masjid Tanah',
];

$old = [
    'title'         => '',
    'property_type' => 'room',
    'address'       => '',
    'city'          => '',
    'postcode'      => '',
    'state'         => 'Melaka',
    'monthly_rent'  => '',
    'deposit'       => '',
    'furnishing'    => 'partial',
    'description'   => '',
    'facilities'    => '',
    'viewing_mode'  => '',
];

// ---- HANDLE STEP 2 SUBMIT ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    foreach (array_keys($old) as $field) {
        $old[$field] = trim($_POST[$field] ?? '');
    }
    $old['state'] = 'Melaka';

    // Validate property fields
    if ($old['title'] === '')        $errors['title']   = 'Property title is required.';
    if ($old['address'] === '')      $errors['address'] = 'Address is required.';
    if (!in_array($old['city'], MELAKA_CITIES, true)) $errors['city'] = 'Please select a valid area.';
    if ($old['postcode'] === '') {
        $errors['postcode'] = 'Postcode is required.';
    } elseif (!preg_match('/^\d{5}$/', $old['postcode'])) {
        $errors['postcode'] = 'Postcode must be 5 digits.';
    }
    if (!in_array($old['property_type'], ['room','studio','whole_unit'], true)) {
        $errors['property_type'] = 'Pick a property type.';
    }
    if (!in_array($old['furnishing'], ['none','partial','full'], true)) {
        $errors['furnishing'] = 'Invalid furnishing option.';
    }
    if (!is_numeric($old['monthly_rent']) || (float)$old['monthly_rent'] <= 0) {
        $errors['monthly_rent'] = 'Enter a valid monthly rent amount.';
    }
    if ($old['deposit'] !== '' && (!is_numeric($old['deposit']) || (float)$old['deposit'] < 0)) {
        $errors['deposit'] = 'Deposit must be 0 or more.';
    }
    if (!in_array($old['viewing_mode'], ['landlord_led','agent_led','either'], true)) {
        $errors['viewing_mode'] = 'Please select a viewing arrangement.';
    }
    if (empty($_POST['inspection_consent'])) {
        $errors['inspection_consent'] = 'You must agree to allow inspection.';
    }

    // Validate photos (at least 1, up to 10)
    $validPhotos = [];
    if (!empty($_FILES['photos']['name'][0])) {
        $totalFiles = count($_FILES['photos']['name']);
        if ($totalFiles > 10) {
            $errors['photos'] = 'You can upload up to 10 photos.';
        } else {
            for ($i = 0; $i < $totalFiles; $i++) {
                if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK) continue;
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
            }
        }
    }
    if (empty($validPhotos) && !isset($errors['photos'])) {
        $errors['photos'] = 'Please upload at least 1 property photo.';
    }

    // Validate documents (at least 1)
    $hasNewDocs = false;
    if (!empty($_FILES['document_files']['error'])) {
        foreach ($_FILES['document_files']['error'] as $err) {
            if ($err === UPLOAD_ERR_OK) { $hasNewDocs = true; break; }
        }
    }
    if (!$hasNewDocs) {
        $errors['documents'] = 'At least 1 ownership document is required to verify your property.';
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
                'INSERT INTO landlords (user_id, full_name, preferred_name, ic_no, phone)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $userId,
                $step1['full_name'],
                $step1['preferred_name'],
                $step1['ic_no'],
                $step1['phone'],
            ]);

            // 3. Insert into properties
            $stmt = $pdo->prepare(
                'INSERT INTO properties
                    (landlord_id, title, property_type, address, city, postcode, state,
                     monthly_rent, deposit, description, facilities, furnishing,
                     viewing_mode, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending_approval")'
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
                $old['deposit'] !== '' ? (float)$old['deposit'] : 0,
                $old['description'] !== '' ? $old['description'] : null,
                $old['facilities']  !== '' ? $old['facilities']  : null,
                $old['furnishing'],
                $old['viewing_mode'],
            ]);
            $propertyId = (int)$pdo->lastInsertId();

            // 4. Save photos
            $stmt = $pdo->prepare(
                'INSERT INTO property_images (property_id, image_path, is_primary)
                 VALUES (?, ?, ?)'
            );
            foreach ($validPhotos as $i => $file) {
                $savedPath = save_property_image($file);
                $stmt->execute([$propertyId, $savedPath, $i === 0 ? 1 : 0]);
            }

            // 5. Save documents
            if (!empty($_FILES['document_files']['name']) && is_array($_FILES['document_files']['name'])) {
                foreach ($_FILES['document_files']['name'] as $i => $name) {
                    if ($_FILES['document_files']['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
                    if ($_FILES['document_files']['error'][$i] !== UPLOAD_ERR_OK) continue;
                    $fileForHelper = [
                        'name'     => $_FILES['document_files']['name'][$i],
                        'type'     => $_FILES['document_files']['type'][$i],
                        'tmp_name' => $_FILES['document_files']['tmp_name'][$i],
                        'error'    => $_FILES['document_files']['error'][$i],
                        'size'     => $_FILES['document_files']['size'][$i],
                    ];
                    $docType  = $_POST['document_types'][$i] ?? 'other';
                    $docNotes = trim($_POST['document_notes'][$i] ?? '');
                    save_property_document(
                        $propertyId, $userId, $docType, $fileForHelper,
                        $docNotes !== '' ? $docNotes : null
                    );
                }
            }

            $pdo->commit();

            // Auto-assign agent
            require_once __DIR__ . '/../includes/agent_assignment.php';
            assign_agent_to_property($propertyId);

            // Clear signup session
            unset($_SESSION['landlord_signup']);

            set_flash('success', 'Welcome! Your account is live. Your property listing has been submitted for review.');
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

            <!-- WARNING BANNER -->
            <div class="alert d-flex gap-3 align-items-start mb-4"
                 style="background:#FFF4D6; border-color:#D4A017; color:#7C5E0A;">
                <i class="bi bi-exclamation-triangle-fill fs-4"></i>
                <div>
                    <strong>Important: your listing will be inspected.</strong><br>
                    <small>
                        Once submitted, an agent will be auto-assigned to physically inspect this property.
                        Make sure your details are accurate — wrong info may cause your listing to be rejected.
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
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                City / area <small class="text-danger">*</small>
                            </label>
                            <select name="city"
                                    class="form-select <?= isset($errors['city']) ? 'is-invalid' : '' ?>"
                                    required>
                                <option value="">— Select area —</option>
                                <?php foreach (MELAKA_CITIES as $c): ?>
                                    <option value="<?= e($c) ?>" <?= $old['city'] === $c ? 'selected' : '' ?>>
                                        <?= e($c) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
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
                                   placeholder="75450" maxlength="5" inputmode="numeric" required>
                            <?php if (isset($errors['postcode'])): ?>
                                <div class="invalid-feedback"><?= e($errors['postcode']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">State</label>
                            <input type="text" name="state" class="form-control"
                                   value="Melaka" readonly>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">
                                Google Maps link
                                <small class="text-secondary fw-normal">— helps pricing accuracy</small>
                            </label>
                            <input type="url" name="maps_url" id="mapsUrlInput"
                                   class="form-control"
                                   placeholder="https://maps.app.goo.gl/... or https://www.google.com/maps/@2.3138,102.3192,17z">
                            <small class="text-secondary">
                                Open Google Maps, find your property, click "Share" → copy link. The pricing benchmark uses distance to UTeM.
                            </small>
                            <div id="mapsUrlStatus" class="small mt-1"></div>
                        </div>
                    </div>
                </div>

                <!-- DETAILS -->
                <div class="bg-white border rounded-3 p-4 mb-3">
                    <h6 class="text-secondary text-uppercase small mb-3">Details</h6>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" rows="4" class="form-control"
                                  placeholder="Describe the property — what's special about it, the neighborhood, etc."><?= e($old['description']) ?></textarea>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                Type <small class="text-danger">*</small>
                            </label>
                            <select name="property_type"
                                    class="form-select <?= isset($errors['property_type']) ? 'is-invalid' : '' ?>"
                                    required>
                                <option value="room"       <?= $old['property_type']==='room'?'selected':'' ?>>Room (single)</option>
                                <option value="studio"     <?= $old['property_type']==='studio'?'selected':'' ?>>Studio apartment</option>
                                <option value="whole_unit" <?= $old['property_type']==='whole_unit'?'selected':'' ?>>Whole unit (for sharing)</option>
                            </select>
                            <?php if (isset($errors['property_type'])): ?>
                                <div class="invalid-feedback"><?= e($errors['property_type']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Furnishing</label>
                            <select name="furnishing" class="form-select">
                                <option value="none"    <?= $old['furnishing']==='none'?'selected':'' ?>>Unfurnished</option>
                                <option value="partial" <?= $old['furnishing']==='partial'?'selected':'' ?>>Partially furnished</option>
                                <option value="full"    <?= $old['furnishing']==='full'?'selected':'' ?>>Fully furnished</option>
                            </select>
                            <small class="text-secondary">Furnishing significantly affects rental value.</small>
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
                            <option value="" disabled <?= !in_array($old['viewing_mode'], ['landlord_led','agent_led','either']) ? 'selected' : '' ?>>— Select an option —</option>
                            <option value="landlord_led" <?= $old['viewing_mode']==='landlord_led'?'selected':'' ?>>I (landlord) will be present for all viewings</option>
                            <option value="agent_led"    <?= $old['viewing_mode']==='agent_led'?'selected':'' ?>>Agent-led — I will hand over a key or lockbox code for agent access</option>
                            <option value="either"       <?= $old['viewing_mode']==='either'?'selected':'' ?>>Either — agent or I can facilitate viewings</option>
                        </select>
                        <?php if (isset($errors['viewing_mode'])): ?>
                            <div class="invalid-feedback"><?= e($errors['viewing_mode']) ?></div>
                        <?php endif; ?>
                        <div class="form-check mt-2">
                            <input class="form-check-input <?= isset($errors['inspection_consent']) ? 'is-invalid' : '' ?>"
                                   type="checkbox" id="inspectionConsent" name="inspection_consent" value="1"
                                   <?= !empty($_POST['inspection_consent']) ? 'checked' : '' ?> required>
                            <label class="form-check-label small" for="inspectionConsent">
                                I agree to allow the assigned RentBridge agent to inspect this property and facilitate viewings on my behalf.
                                <span class="text-danger">*</span>
                            </label>
                            <?php if (isset($errors['inspection_consent'])): ?>
                                <div class="invalid-feedback"><?= e($errors['inspection_consent']) ?></div>
                            <?php endif; ?>
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
                            <small class="text-secondary">See market benchmark above to help price competitively.</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Deposit (RM)</label>
                            <input type="number" name="deposit" min="0" step="50"
                                   class="form-control <?= isset($errors['deposit']) ? 'is-invalid' : '' ?>"
                                   value="<?= e($old['deposit']) ?>">
                            <?php if (isset($errors['deposit'])): ?>
                                <div class="invalid-feedback"><?= e($errors['deposit']) ?></div>
                            <?php endif; ?>
                            <small class="text-secondary">Usually 1–2 months of rent.</small>
                        </div>
                    </div>
                </div>

                <!-- PHOTOS -->
                <div class="bg-white border rounded-3 p-4 mb-3">
                    <h6 class="text-secondary text-uppercase small mb-3">Photos</h6>
                    <p class="small text-secondary mb-2">
                        Property must have at least one image.
                    </p>

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

                    <?php if (isset($errors['photos'])): ?>
                        <div class="text-danger small mt-2"><?= e($errors['photos']) ?></div>
                    <?php endif; ?>
                </div>

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
                            <div class="col-md-3">
                                <label class="form-label small fw-semibold">Document type</label>
                                <select name="document_types[]" class="form-select form-select-sm">
                                    <option value="">— Select type —</option>
                                    <option value="ownership_proof">Ownership Proof</option>
                                    <option value="utility_bill">Utility bill</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6">
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

                <button type="submit" class="btn btn-primary w-100 mb-5">
                    Create account &amp; submit listing <i class="bi bi-check-circle ms-1"></i>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    const MAX_FILES = 10;
    const MAX_SIZE = 5 * 1024 * 1024;
    const ALLOWED = ['image/jpeg','image/png','image/webp'];

    const fileInput   = document.getElementById('photoInput');
    const dropzone    = document.getElementById('photoDropzone');
    const previewArea = document.getElementById('photoPreview');
    const counter     = document.getElementById('photoCounter');

    let collectedFiles = [];

    function updateInputFiles() {
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

            if (idx === 0) {
                const badge = document.createElement('span');
                badge.className = 'badge bg-success';
                badge.style.cssText = 'position:absolute; top:6px; left:6px;';
                badge.textContent = 'Cover';
                card.appendChild(badge);
            }

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
            if (!ALLOWED.includes(file.type)) {
                rejected.push(`${file.name} (wrong type)`);
                continue;
            }
            if (file.size > MAX_SIZE) {
                rejected.push(`${file.name} (too large)`);
                continue;
            }
            if (collectedFiles.length >= MAX_FILES) {
                rejected.push(`${file.name} (max ${MAX_FILES} reached)`);
                continue;
            }
            const isDuplicate = collectedFiles.some(f => f.name === file.name && f.size === file.size);
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

    dropzone.addEventListener('click', () => fileInput.click());

    fileInput.addEventListener('change', (e) => {
        if (e.target.files.length > 0) addFiles(e.target.files);
    });

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
        if (e.dataTransfer.files.length > 0) addFiles(e.dataTransfer.files);
    });
})();

// Pricing benchmark widget
let benchmarkSuggested = null;
(function() {
    const benchmarkBox = document.getElementById('pricingBenchmark');
    const noDataBox    = document.getElementById('pricingNoData');
    const titleEl      = document.getElementById('benchmarkTitle');
    const confidenceEl = document.getElementById('benchmarkConfidence');
    const bodyEl       = document.getElementById('benchmarkBody');
    const iconEl       = document.getElementById('benchmarkIcon');

    const citySelect      = document.querySelector('select[name="city"]');
    const typeInput       = document.querySelector('select[name="property_type"]');
    const furnInput       = document.querySelector('select[name="furnishing"]');
    const rentInput       = document.getElementById('monthlyRentInput');
    const facilitiesInput = document.querySelector('textarea[name="facilities"]');
    const mapsUrlInput    = document.getElementById('mapsUrlInput');
    const mapsStatusEl    = document.getElementById('mapsUrlStatus');

    let lastQuery = '';
    let timer = null;

    async function fetchBenchmark() {
        const city = (citySelect?.value || '').trim();
        const type = typeInput?.value || '';
        const furn = furnInput?.value || '';
        const facilities = (facilitiesInput?.value || '').trim();
        const mapsUrl = (mapsUrlInput?.value || '').trim();
        const query = `${city}|${type}|${furn}|${facilities}|${mapsUrl}`;

        if (query === lastQuery) return;
        lastQuery = query;

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
            benchmarkSuggested = data.suggested > 0 ? data.suggested : null;

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

    citySelect?.addEventListener('change', debouncedFetch);
    typeInput?.addEventListener('change', debouncedFetch);
    furnInput?.addEventListener('change', debouncedFetch);
    rentInput?.addEventListener('input', debouncedFetch);
    facilitiesInput?.addEventListener('input', debouncedFetch);
    mapsUrlInput?.addEventListener('input', debouncedFetch);

    setTimeout(fetchBenchmark, 200);
})();

document.querySelector('form').addEventListener('submit', function(e) {
    const rent = parseFloat(document.getElementById('monthlyRentInput').value || 0);
    if (benchmarkSuggested !== null && rent > 0 && rent < benchmarkSuggested - 150) {
        const diff = Math.round(benchmarkSuggested - rent);
        const ok = confirm(
            `Your monthly rent of RM${Math.round(rent).toLocaleString('en-MY')} is RM${diff} below the suggested market price of RM${Math.round(benchmarkSuggested).toLocaleString('en-MY')}.\n\nAre you sure you want to list at this price?`
        );
        if (!ok) e.preventDefault();
    }
});
</script>

</body>
</html>
