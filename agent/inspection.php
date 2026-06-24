<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/uploads.php';
require_role('agent');

$tenancyId = (int)($_GET['tenancy_id'] ?? $_POST['tenancy_id'] ?? 0);
if ($tenancyId <= 0) {
    http_response_code(400);
    die('Invalid tenancy ID.');
}

$pdo = db();

// Fetch tenancy + verification record
$stmt = $pdo->prepare("
    SELECT b.*,
           p.title          AS property_title,
           p.address        AS property_address,
           p.city           AS property_city,
           p.postcode       AS property_postcode,
           p.state          AS property_state,
           p.monthly_rent   AS property_rent,
           s.full_name      AS student_name,
           l.full_name      AS landlord_name,
           l.ic_no          AS landlord_ic,
           l.phone          AS landlord_phone,
           v.id             AS verification_id,
           v.outcome        AS v_outcome,
           v.started_at     AS v_started,
           v.deadline_at    AS v_deadline,
           v.submitted_at   AS v_submitted
      FROM tenancies b
      JOIN properties p ON p.id = b.property_id
      JOIN students   s ON s.user_id = b.student_id
      JOIN landlords  l ON l.user_id = b.landlord_id
      LEFT JOIN agent_verifications v ON v.tenancy_id = b.id
     WHERE b.id = ?
       AND b.agent_id = ?
     LIMIT 1
");
$stmt->execute([$tenancyId, current_user_id()]);
$tenancy = $stmt->fetch();

if (!$tenancy) {
    http_response_code(404);
    die('Tenancy not found or you are not assigned as the agent.');
}

if ($tenancy['status'] !== 'agent_verifying') {
    set_flash('warning', 'This tenancy is not in inspection phase. Current status: ' . $tenancy['status']);
    header('Location: /rentbridge/agent/cases.php');
    exit;
}

if (!$tenancy['verification_id']) {
    die('Verification record missing. Please contact admin.');
}

if ($tenancy['v_outcome'] !== 'in_progress') {
    set_flash('info', 'Inspection already submitted.');
    header('Location: /rentbridge/agent/inspection_view.php?id=' . $tenancy['verification_id']);
    exit;
}

$errors = [];
$old = [
    'property_matches_listing' => '',
    'property_address_correct' => '',
    'facilities_match'         => '',
    'landlord_id_matches'      => '',
    'ownership_doc_sighted'    => '',
    'inspection_notes'         => '',
    'issues_found'             => '',
    'issue_severity'           => 'none',
];

// ---- HANDLE FORM SUBMISSION ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    // ---- ABORT / CANNOT INSPECT ----
    if (($_POST['action'] ?? '') === 'abort') {
        $abortReason = trim($_POST['abort_reason'] ?? '');
        if ($abortReason === '') {
            $errors['abort'] = 'Please provide a reason for aborting the inspection.';
        } else {
            try {
                $pdo->beginTransaction();

                $pdo->prepare("
                    UPDATE agent_verifications
                       SET outcome = 'aborted',
                           inspection_notes = ?,
                           submitted_at = NOW()
                     WHERE id = ?
                ")->execute([$abortReason, $tenancy['verification_id']]);

                $pdo->prepare("
                    UPDATE tenancies
                       SET status = 'inspection_aborted',
                           cancellation_reason = ?
                     WHERE id = ?
                ")->execute(['Inspection aborted by agent: ' . $abortReason, $tenancyId]);

                // Release property back to available
                $pdo->prepare("UPDATE properties SET status = 'available' WHERE id = ?")
                    ->execute([(int)$tenancy['property_id']]);

                $pdo->commit();

                notify(
                    (int)$tenancy['student_id'],
                    'inspection_aborted',
                    'Inspection could not be completed',
                    'The agent was unable to inspect "' . $tenancy['property_title']
                        . '". Reason: ' . $abortReason . '. Your tenancy has been cancelled — you may rebook.',
                    '/rentbridge/student/tenancies.php'
                );
                notify(
                    (int)$tenancy['landlord_id'],
                    'inspection_aborted',
                    'Agent could not complete inspection',
                    'The assigned agent was unable to inspect your property "'
                        . $tenancy['property_title'] . '". Reason: ' . $abortReason
                        . '. Please contact admin if this is incorrect.',
                    '/rentbridge/landlord/tenancies.php'
                );

                set_flash('info', 'Inspection aborted. Tenancy has been cancelled and parties notified.');
                header('Location: /rentbridge/agent/cases.php');
                exit;

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors['abort'] = 'Something went wrong: ' . $e->getMessage();
            }
        }
    }

    // Collect form input
    foreach (['property_matches_listing','property_address_correct','facilities_match',
              'landlord_id_matches','ownership_doc_sighted'] as $f) {
        $old[$f] = ($_POST[$f] ?? '') === '1' ? '1' : '0';
    }
    $old['inspection_notes'] = trim($_POST['inspection_notes'] ?? '');
    $old['issues_found']     = trim($_POST['issues_found'] ?? '');
    $old['issue_severity']   = $_POST['issue_severity'] ?? 'none';

    if (!in_array($old['issue_severity'], ['none','minor','major'], true)) {
        $old['issue_severity'] = 'none';
    }

    // Validate
    if ($old['inspection_notes'] === '') {
        $errors['inspection_notes'] = 'Please describe your inspection findings.';
    }

    if ($old['issue_severity'] !== 'none' && $old['issues_found'] === '') {
        $errors['issues_found'] = 'Please describe the issues you found.';
    }

    // Validate photos — minimum 5 required
    $validPhotos = [];
    if (!empty($_FILES['photos']['name'][0])) {
        $totalFiles = count($_FILES['photos']['name']);
        for ($i = 0; $i < $totalFiles; $i++) {
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
                $errors['photos'] = "Photo " . ($i + 1) . ": " . $err;
                break;
            }
            $validPhotos[] = $file;
        }
    }

    if (count($validPhotos) < 5 && !isset($errors['photos'])) {
        $errors['photos'] = 'Please upload at least 5 photos as evidence of your inspection.';
    }

    // Determine outcome based on severity
    $outcome = match ($old['issue_severity']) {
        'none'  => 'passed',
        'minor' => 'passed_with_disclosure',  // student must acknowledge
        'major' => 'failed',                  // tenancy auto-cancels
    };

    // ---- ALL VALID — save the inspection ----
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            // 1. Update verification record
            $stmt = $pdo->prepare("
                UPDATE agent_verifications
                   SET property_matches_listing = ?,
                       property_address_correct = ?,
                       facilities_match         = ?,
                       landlord_id_matches      = ?,
                       ownership_doc_sighted    = ?,
                       inspection_notes         = ?,
                       issues_found             = ?,
                       issue_severity           = ?,
                       outcome                  = ?,
                       submitted_at             = NOW()
                 WHERE id = ?
            ");
            $stmt->execute([
                (int)$old['property_matches_listing'],
                (int)$old['property_address_correct'],
                (int)$old['facilities_match'],
                (int)$old['landlord_id_matches'],
                (int)$old['ownership_doc_sighted'],
                $old['inspection_notes'],
                $old['issues_found'] !== '' ? $old['issues_found'] : null,
                $old['issue_severity'],
                $outcome,
                $tenancy['verification_id']
            ]);

            // 2. Save photos
            $photoStmt = $pdo->prepare("
                INSERT INTO agent_verification_photos (verification_id, photo_path)
                VALUES (?, ?)
            ");
            foreach ($validPhotos as $file) {
                $savedPath = save_inspection_photo($file);
                $photoStmt->execute([$tenancy['verification_id'], $savedPath]);
            }

            // 3. Update tenancy status based on outcome
            if ($outcome === 'passed') {
                // Clean pass → ready for contract
                $stmt = $pdo->prepare("UPDATE tenancies SET status = 'agent_verified' WHERE id = ?");
                $stmt->execute([$tenancyId]);

                // Mark property as agent-verified
                $stmt = $pdo->prepare("
                    UPDATE properties
                       SET agent_verified_at = NOW(),
                           agent_verified_by = ?
                     WHERE id = ?
                ");
                $stmt->execute([current_user_id(), (int)$tenancy['property_id']]);

                // Notify student
                notify(
                    (int)$tenancy['student_id'],
                    'inspection_passed',
                    'Property inspection passed! ✓',
                    'Your tenancy #' . $tenancyId . ' for "' . $tenancy['property_title']
                        . '" passed inspection. The contract is being prepared for signing.',
                    '/rentbridge/student/tenancy.php?id=' . $tenancyId
                );

                // Notify landlord
                notify(
                    (int)$tenancy['landlord_id'],
                    'inspection_passed',
                    'Property inspection passed ✓',
                    'The agent has verified your property "' . $tenancy['property_title']
                        . '" for tenancy #' . $tenancyId . '. Contract is being prepared.',
                    '/rentbridge/landlord/tenancy.php?id=' . $tenancyId
                );

                // Auto-create the contract NOW (deferred from accept step)
                require_once __DIR__ . '/../includes/contracts.php';
                $contractId = create_contract_from_tenancy($tenancyId);

                // Update tenancy to contract_pending
                if ($contractId) {
                    $stmt = $pdo->prepare("UPDATE tenancies SET status = 'contract_pending' WHERE id = ?");
                    $stmt->execute([$tenancyId]);
                }
            }
            elseif ($outcome === 'passed_with_disclosure') {
                // Student must explicitly accept the disclosed issues
                // For now: keep status 'agent_verifying' until student decides
                // We'll build the student decision page next
                notify(
                    (int)$tenancy['student_id'],
                    'inspection_issues',
                    '⚠ Minor issues found — your decision needed',
                    'The agent inspection found minor issues with "' . $tenancy['property_title']
                        . '". Please review and decide whether to proceed.',
                    '/rentbridge/student/inspection_decision.php?tenancy_id=' . $tenancyId
                );
            }
            elseif ($outcome === 'failed') {
                // Major issues → auto-cancel tenancy
                $stmt = $pdo->prepare("
                    UPDATE tenancies
                       SET status = 'verification_failed',
                           cancellation_reason = ?
                     WHERE id = ?
                ");
                $stmt->execute([
                    'Failed agent inspection: ' . $old['issues_found'],
                    $tenancyId
                ]);

                // Release property back to available
                $stmt = $pdo->prepare("UPDATE properties SET status = 'available' WHERE id = ?");
                $stmt->execute([(int)$tenancy['property_id']]);

                // Notify all parties
                notify(
                    (int)$tenancy['student_id'],
                    'inspection_failed',
                    '❌ Inspection failed — tenancy cancelled',
                    'The agent found major issues with "' . $tenancy['property_title']
                        . '" during inspection. Your tenancy has been cancelled.',
                    '/rentbridge/student/tenancies.php'
                );
                notify(
                    (int)$tenancy['landlord_id'],
                    'inspection_failed',
                    'Major issues found during inspection',
                    'The agent inspection found major issues with "' . $tenancy['property_title']
                        . '". The tenancy has been cancelled. Please address the issues and contact admin.',
                    '/rentbridge/landlord/properties.php'
                );

                // Alert admin
                $adminStmt = $pdo->prepare("SELECT id FROM users WHERE primary_role = 'admin'");
                $adminStmt->execute();
                foreach ($adminStmt->fetchAll() as $admin) {
                    notify(
                        (int)$admin['id'],
                        'inspection_failed',
                        '⚠ Property failed agent inspection',
                        'Tenancy #' . $tenancyId . ' — property "' . $tenancy['property_title']
                            . '" failed major-issue inspection. Review recommended.',
                        '/rentbridge/admin/property.php?id=' . $tenancy['property_id']
                    );
                }
            }

            $pdo->commit();

            set_flash('success', 'Inspection submitted successfully.');
            header('Location: /rentbridge/agent/inspection_view.php?id=' . $tenancy['verification_id']);
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors['general'] = 'Something went wrong: ' . $e->getMessage();
        }
    }
}

// Compute deadline status for the warning banner
$deadlineTs = strtotime($tenancy['v_deadline']);
$now        = time();
$hoursLeft  = max(0, round(($deadlineTs - $now) / 3600));
$overdue    = $now > $deadlineTs;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Property Inspection · Agent · RentBridge</title>
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
        <div class="col-lg-9">

            <p class="small mb-3">
                <a href="/rentbridge/agent/cases.php" class="text-secondary text-decoration-none">
                    <i class="bi bi-arrow-left"></i> All my cases
                </a>
            </p>

            <h1 class="mb-1">Property Inspection</h1>
            <p class="text-secondary mb-4">Tenancy #<?= (int)$tenancyId ?> · <?= e($tenancy['property_title']) ?></p>

            <?php if ($overdue): ?>
                <div class="alert alert-danger d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill fs-4"></i>
                    <div>
                        <strong>Overdue!</strong> Inspection deadline was
                        <?= e(date('d M Y, H:i', $deadlineTs)) ?>.
                        Please complete and submit immediately.
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-info d-flex align-items-center gap-2">
                    <i class="bi bi-clock fs-4"></i>
                    <div>
                        Deadline: <strong><?= e(date('d M Y, H:i', $deadlineTs)) ?></strong>
                        — about <?= $hoursLeft ?> hours left.
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-danger"><?= e($errors['general']) ?></div>
            <?php endif; ?>

            <!-- Context: who/what -->
            <div class="bg-white border rounded-3 p-4 mb-4">
                <h6 class="text-secondary text-uppercase small mb-3">Inspection context</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <small class="text-secondary text-uppercase">Property</small>
                        <div class="fw-semibold"><?= e($tenancy['property_title']) ?></div>
                        <div class="small text-secondary">
                            <?= e($tenancy['property_address']) ?>,<br>
                            <?= e($tenancy['property_city']) ?> <?= e($tenancy['property_postcode']) ?>,
                            <?= e($tenancy['property_state']) ?>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <small class="text-secondary text-uppercase">Student</small>
                        <div class="fw-semibold"><?= e($tenancy['student_name']) ?></div>
                    </div>
                    <div class="col-md-3">
                        <small class="text-secondary text-uppercase">Landlord</small>
                        <div class="fw-semibold"><?= e($tenancy['landlord_name']) ?></div>
                        <div class="small text-secondary">
                            IC: <?= e($tenancy['landlord_ic']) ?><br>
                            <?= e($tenancy['landlord_phone']) ?>
                        </div>
                    </div>
                </div>
            </div>

            <?php
            require_once __DIR__ . '/../includes/uploads.php';
            $documents = get_property_documents((int)$tenancy['property_id']);
            ?>
            <?php if (!empty($documents)): ?>
            <div class="bg-white border rounded-3 p-4 mb-4"
                style="border-left: 4px solid #0F2C52 !important;">
                <h5 class="mb-3">
                    <i class="bi bi-file-earmark-lock me-2"></i>
                    Landlord's documents to verify
                </h5>
                <p class="text-secondary small mb-3">
                    Cross-check these documents against the landlord's IC during your inspection.
                </p>
                <div class="row g-2">
                    <?php foreach ($documents as $d):
                        $typeLabel = match($d['document_type']) {
                            'ownership_proof' => 'Ownership proof',
                            'utility_bill'    => 'Utility bill',
                            default           => 'Other',
                        };
                        $icon = strpos($d['mime_type'], 'pdf') !== false ? 'bi-file-pdf' : 'bi-file-image';
                    ?>
                        <div class="col-md-6">
                            <a href="/rentbridge/<?= e($d['file_path']) ?>" target="_blank"
                            class="d-flex gap-2 align-items-center p-3 border rounded-3 text-decoration-none text-dark"
                            style="transition: background 0.1s;"
                            onmouseover="this.style.background='#FAF8F3'"
                            onmouseout="this.style.background='white'">
                                <i class="bi <?= $icon ?> fs-3 text-secondary"></i>
                                <div class="flex-grow-1">
                                    <strong class="small"><?= e($d['original_name']) ?></strong>
                                    <div class="small text-secondary">
                                        <?= e($typeLabel) ?>
                                        · <?= number_format((float)$d['file_size'] / 1024, 0) ?> KB
                                    </div>
                                </div>
                                <i class="bi bi-box-arrow-up-right text-secondary"></i>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Inspection form -->
            <form method="POST" enctype="multipart/form-data" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="tenancy_id" value="<?= (int)$tenancyId ?>">

                <!-- Property verification checklist -->
                <div class="bg-white border rounded-3 p-4 mb-4">
                    <h5 class="mb-3"><i class="bi bi-house-check me-2"></i>Property verification</h5>
                    <p class="text-secondary small mb-3">Tick each item you have physically verified.</p>

                    <?php
                    $checklist = [
                        'property_matches_listing'  => 'Property matches the listing photos and description',
                        'property_address_correct'  => 'Property address is correct and findable',
                        'facilities_match'          => 'Listed facilities (WiFi, parking, etc.) are present',
                    ];
                    foreach ($checklist as $field => $label):
                    ?>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox"
                                   name="<?= e($field) ?>" id="<?= e($field) ?>" value="1"
                                   <?= ($old[$field] === '1') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="<?= e($field) ?>">
                                <?= e($label) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Identity verification -->
                <div class="bg-white border rounded-3 p-4 mb-4">
                    <h5 class="mb-3"><i class="bi bi-person-vcard me-2"></i>Identity verification</h5>
                    <p class="text-secondary small mb-3">Verify the landlord's identity and ownership of the property.</p>

                    <?php
                    $idChecklist = [
                        'landlord_id_matches'    => 'Landlord IC matches account info (' . $tenancy['landlord_ic'] . ')',
                        'ownership_doc_sighted'  => 'Property ownership document sighted (title / SPA / utility bill)',
                    ];
                    foreach ($idChecklist as $field => $label):
                    ?>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox"
                                   name="<?= e($field) ?>" id="<?= e($field) ?>" value="1"
                                   <?= ($old[$field] === '1') ? 'checked' : '' ?>>
                            <label class="form-check-label" for="<?= e($field) ?>">
                                <?= e($label) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Photos -->
                <div class="bg-white border rounded-3 p-4 mb-4">
                    <h5 class="mb-3"><i class="bi bi-camera me-2"></i>Inspection photos</h5>
                    <p class="text-secondary small mb-3">
                        Upload <strong>at least 5 photos</strong> as evidence of your visit.
                        Recommended: entrance, bedroom, bathroom, kitchen, living area.
                    </p>

                    <input type="file" name="photos[]" accept="image/*" multiple
                           class="form-control <?= isset($errors['photos']) ? 'is-invalid' : '' ?>" required>
                    <small class="text-secondary">Hold Ctrl/Cmd to select multiple files. JPG/PNG/WebP only.</small>
                    <?php if (isset($errors['photos'])): ?>
                        <div class="invalid-feedback d-block"><?= e($errors['photos']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Inspection notes -->
                <div class="bg-white border rounded-3 p-4 mb-4">
                    <h5 class="mb-3"><i class="bi bi-card-text me-2"></i>Inspection notes</h5>

                    <label class="form-label fw-semibold">General notes <small class="text-secondary fw-normal">— required</small></label>
                    <textarea name="inspection_notes" rows="4"
                              class="form-control <?= isset($errors['inspection_notes']) ? 'is-invalid' : '' ?>"
                              placeholder="Describe the property's condition, your visit, anything noteworthy..."
                              required><?= e($old['inspection_notes']) ?></textarea>
                    <?php if (isset($errors['inspection_notes'])): ?>
                        <div class="invalid-feedback"><?= e($errors['inspection_notes']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Issues found -->
                <div class="bg-white border rounded-3 p-4 mb-4">
                    <h5 class="mb-3"><i class="bi bi-exclamation-triangle me-2"></i>Issues found</h5>

                    <label class="form-label fw-semibold">Severity</label>
                    <select name="issue_severity" id="severity" class="form-select mb-3">
                        <option value="none"  <?= $old['issue_severity']==='none' ?'selected':'' ?>>
                            ✓ No issues — property matches listing fully
                        </option>
                        <option value="minor" <?= $old['issue_severity']==='minor'?'selected':'' ?>>
                            ⚠ Minor issues — student can decide whether to proceed
                        </option>
                        <option value="major" <?= $old['issue_severity']==='major'?'selected':'' ?>>
                            ❌ Major issues — tenancy should be cancelled
                        </option>
                    </select>

                    <label class="form-label fw-semibold">
                        Describe issues <small class="text-secondary fw-normal">— required if severity is minor or major</small>
                    </label>
                    <textarea name="issues_found" rows="4"
                              class="form-control <?= isset($errors['issues_found']) ? 'is-invalid' : '' ?>"
                              placeholder="e.g. Photos show air-cond but room has no air-cond. Carpet has visible stains. Door lock loose."><?= e($old['issues_found']) ?></textarea>
                    <?php if (isset($errors['issues_found'])): ?>
                        <div class="invalid-feedback d-block"><?= e($errors['issues_found']) ?></div>
                    <?php endif; ?>
                </div>

                <!-- Outcome explanation -->
                <div class="bg-light border rounded-3 p-3 mb-4">
                    <small class="text-secondary">
                        <strong>What happens next?</strong><br>
                        • <strong>No issues</strong>: contract auto-generated, all parties notified to sign<br>
                        • <strong>Minor issues</strong>: student reviews the report, decides to proceed or cancel<br>
                        • <strong>Major issues</strong>: tenancy cancelled, admin notified, property may be removed from listings
                    </small>
                </div>

                <div class="d-flex gap-2 justify-content-end">
                    <button type="button" class="btn btn-outline-danger me-auto"
                            data-bs-toggle="modal" data-bs-target="#abortModal">
                        <i class="bi bi-x-octagon me-1"></i> Cannot inspect
                    </button>
                    <a href="/rentbridge/agent/cases.php" class="btn btn-ghost">Cancel</a>
                    <button type="submit" class="btn btn-primary"
                            onclick="return confirm('Submit inspection report? You cannot change it after submission.');">
                        <i class="bi bi-check2-circle me-1"></i> Submit inspection
                    </button>
                </div>
            </form>

            <!-- Abort modal -->
            <div class="modal fade" id="abortModal" tabindex="-1" aria-labelledby="abortModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header border-0">
                            <h5 class="modal-title text-danger" id="abortModalLabel">
                                <i class="bi bi-x-octagon me-2"></i>Cannot complete inspection
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <form method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="tenancy_id" value="<?= (int)$tenancyId ?>">
                            <input type="hidden" name="action" value="abort">
                            <div class="modal-body">
                                <?php if (!empty($errors['abort'])): ?>
                                    <div class="alert alert-danger small"><?= e($errors['abort']) ?></div>
                                <?php endif; ?>
                                <p class="small text-secondary">
                                    Use this only if you genuinely cannot carry out the inspection —
                                    e.g. landlord unresponsive, property inaccessible, wrong address.
                                    The tenancy will be cancelled and both student and landlord notified.
                                </p>
                                <label class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
                                <textarea name="abort_reason" rows="3" class="form-control" required
                                          placeholder="e.g. Landlord did not show up. Attempted contact 3 times with no response."></textarea>
                            </div>
                            <div class="modal-footer border-0">
                                <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Go back</button>
                                <button type="submit" class="btn btn-danger"
                                        onclick="return confirm('This will cancel the tenancy and notify both parties. Are you sure?');">
                                    <i class="bi bi-x-octagon me-1"></i> Abort inspection
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- REPORT ISSUE -->
<div class="container pb-4">
    <div class="text-center">
        <button type="button"
                class="btn btn-link btn-sm text-secondary text-decoration-none p-0"
                data-bs-toggle="modal" data-bs-target="#reportModal">
            <i class="bi bi-flag me-1"></i> Report an issue with this inspection
        </button>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/reports.php';
$reportSubjects = [
    ['id' => (int)$tenancy['student_id'],  'name' => $tenancy['student_name'],  'role' => 'student'],
    ['id' => (int)$tenancy['landlord_id'], 'name' => $tenancy['landlord_name'], 'role' => 'landlord'],
];
render_report_modal($reportSubjects, 'tenancy', (int)$tenancy['id']);
?>

</body>
</html>