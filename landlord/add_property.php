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

            // Handle document upload (single file at a time)
            if (!empty($_FILES['document_file']['name']) && $_FILES['document_file']['error'] === UPLOAD_ERR_OK) {
                $docType = $_POST['document_type'] ?? 'other';
                $docNotes = trim($_POST['document_notes'] ?? '');

                $result = save_property_document(
                    $propertyId,
                    $userId,
                    $docType,
                    $_FILES['document_file'],
                    $docNotes !== '' ? $docNotes : null
                );

                if (!$result['ok']) {
                    // Don't fail the whole transaction — just warn
                    set_flash('warning', 'Property saved, but document upload failed: ' . $result['error']);
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
    </script>

    <!-- DOCUMENTS -->
<div class="bg-white border rounded-3 p-4 mb-3">
    <h6 class="text-secondary text-uppercase small mb-3">
        Ownership documents
        <span class="badge bg-secondary ms-1">Private</span>
    </h6>

    <div class="alert alert-light border d-flex gap-2 small mb-3">
        <i class="bi bi-shield-lock text-secondary"></i>
        <div>
            <strong>Only you, admin, and your assigned agent can see these.</strong>
            Students never see your documents. Used to verify you're the legitimate owner.
        </div>
    </div>

    <?php
    // Show existing documents if edit mode
    $existingDocs = $isEdit ? get_property_documents($editId) : [];
    if (!empty($existingDocs)):
    ?>
        <div class="mb-3">
            <div class="small text-secondary text-uppercase mb-2">Uploaded</div>
            <?php foreach ($existingDocs as $d):
                $typeLabel = match($d['document_type']) {
                    'ownership_proof' => 'Ownership proof',
                    'utility_bill'    => 'Utility bill',
                    default           => 'Other',
                };
                $icon = strpos($d['mime_type'], 'pdf') !== false ? 'bi-file-pdf' : 'bi-file-image';
            ?>
                <div class="d-flex gap-2 align-items-center p-2 border rounded-3 mb-2">
                    <i class="bi <?= $icon ?> fs-4 text-secondary"></i>
                    <div class="flex-grow-1">
                        <a href="/rentbridge/<?= e($d['file_path']) ?>" target="_blank"
                           class="text-decoration-none text-dark">
                            <strong class="small"><?= e($d['original_name']) ?></strong>
                        </a>
                        <div class="small text-secondary">
                            <?= e($typeLabel) ?>
                            · <?= number_format((float)$d['file_size'] / 1024, 0) ?> KB
                            · <?= e(date('d M Y', strtotime($d['uploaded_at']))) ?>
                        </div>
                    </div>
                    <form method="POST" class="m-0">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete_doc">
                        <input type="hidden" name="doc_id" value="<?= (int)$d['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                onclick="return confirm('Delete this document?');"
                                title="Delete">
                            <i class="bi bi-trash"></i>
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Upload form for new doc -->
        <div class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small fw-semibold">Document type</label>
                <select name="document_type" class="form-select">
                    <option value="ownership_proof">Ownership proof (geran, SPA)</option>
                    <option value="utility_bill">Utility bill (proof of address)</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label small fw-semibold">File</label>
                <input type="file" name="document_file" class="form-control"
                    accept="application/pdf,image/jpeg,image/png,image/webp">
                <small class="text-secondary">PDF, JPG, PNG · max 5MB</small>
            </div>
            <div class="col-md-2">
                <small class="text-secondary d-block mb-2">&nbsp;</small>
                <small class="text-secondary">
                    Upload happens<br>with form submit
                </small>
            </div>
        </div>

        <div class="mt-2">
            <label class="form-label small fw-semibold">Notes (optional)</label>
            <input type="text" name="document_notes" class="form-control"
                maxlength="200" placeholder="e.g. Geran issued 2018, lot 234">
        </div>
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