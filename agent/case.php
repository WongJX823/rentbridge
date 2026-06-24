<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/tenancies.php';
require_once __DIR__ . '/../includes/agent_assignment.php';
require_role('agent');

$caseId = (int)($_GET['id'] ?? $_POST['tenancy_id'] ?? 0);
if ($caseId <= 0) {
    http_response_code(400);
    die('Invalid case ID.');
}

$pdo = db();

// Must belong to THIS agent
$stmt = $pdo->prepare("
    SELECT b.*,
           p.title       AS property_title,
           p.address     AS property_address,
           p.city        AS property_city,
           p.state       AS property_state,
           p.postcode    AS property_postcode,
           s.full_name   AS student_name,
           s.matric_no   AS student_matric,
           s.phone       AS student_phone,
           us.email      AS student_email,
           l.full_name   AS landlord_name,
           l.phone       AS landlord_phone,
           ul.email      AS landlord_email
      FROM tenancies b
      JOIN properties p ON p.id = b.property_id
      JOIN students   s ON s.user_id = b.student_id
      JOIN users      us ON us.id = b.student_id
      JOIN landlords  l ON l.user_id = b.landlord_id
      JOIN users      ul ON ul.id = b.landlord_id
     WHERE b.id = ? AND b.agent_id = ?
     LIMIT 1
");
$stmt->execute([$caseId, current_user_id()]);
$case = $stmt->fetch();

if (!$case) {
    http_response_code(404);
    die('Case not found.');
}

$errors = [];
$reason = '';

// ---- HANDLE ACCEPT / REJECT ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $reason = trim($_POST['reason'] ?? '');

    if ($case['status'] !== 'pending_agent') {
        $errors['general'] = 'This case has already been processed.';
    } elseif (!in_array($action, ['accept', 'reject'], true)) {
        $errors['general'] = 'Invalid action.';
    } elseif ($action === 'reject' && $reason === '') {
        $errors['reason'] = 'Please give a reason so admin understands your decline.';
    } else {
        try {
            if ($action === 'accept') {
                $pdo->beginTransaction();

                // Status → agent_verifying (NEW: inspection step before contract)
                $stmt = $pdo->prepare(
                    'UPDATE tenancies SET status = "agent_verifying" WHERE id = ?'
                );
                $stmt->execute([$caseId]);

                // Mark property as reserved (off public listings)
                $stmt = $pdo->prepare(
                    'UPDATE properties SET status = "reserved" WHERE id = ?'
                );
                $stmt->execute([(int)$case['property_id']]);

                // Create the inspection record with 5-day deadline
                $stmt = $pdo->prepare(
                    'INSERT INTO agent_verifications
                        (tenancy_id, agent_id, started_at, deadline_at)
                     VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 5 DAY))'
                );
                $stmt->execute([$caseId, current_user_id()]);

                $pdo->commit();

                // Notify student
                notify(
                    (int)$case['student_id'],
                    'agent_accepted',
                    'Your UTeM agent is on the case!',
                    current_user_display_name() . ' will inspect "' . $case['property_title']
                        . '" within 5 days. The contract will be issued after inspection passes.',
                    '/rentbridge/student/tenancies.php'
                );

                // Notify landlord
                notify(
                    (int)$case['landlord_id'],
                    'agent_accepted',
                    'Agent will visit your property',
                    current_user_display_name() . ' (UTeM staff) will inspect your property "'
                        . $case['property_title']
                        . '" within 5 days. Please arrange access (key handover or in-person meet).',
                    '/rentbridge/landlord/tenancies.php'
                );

                set_flash('success', 'Case accepted. Please inspect the property within 5 days.');
                header('Location: /rentbridge/agent/inspection.php?tenancy_id=' . $caseId);
                exit;
            }

            // REJECT — re-trigger assignment
            $newAgentId = reassign_agent($caseId, current_user_id(), $reason);

            if ($newAgentId) {
                set_flash('info', 'Case declined. Reassigned to another agent.');
            } else {
                set_flash('warning', 'Case declined. No other available agent — admin notified.');
            }
            header('Location: /rentbridge/agent/cases.php');
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors['general'] = 'Something went wrong: ' . $e->getMessage();
        }
    }
}

function case_status_label(string $status): array {
    return match ($status) {
        'pending_agent'         => ['Awaiting your response', 'warning'],
        'agent_assigned'        => ['You accepted',           'success'],
        'contract_pending'      => ['Contract pending',       'primary'],
        'active'                => ['Active tenancy',         'success'],
        'completed'             => ['Completed',              'secondary'],
        'cancelled_by_student'  => ['Cancelled by student',   'secondary'],
        'cancelled_by_landlord' => ['Cancelled by landlord',  'secondary'],
        default                 => [ucfirst($status),         'secondary'],
    };
}
[$label, $color] = case_status_label($case['status']);

$agentCaseload = get_agent_caseload(current_user_id());
$startTs = strtotime($case['start_date']);
$endTs   = strtotime($case['end_date']);
$months  = max(1, (int)round(($endTs - $startTs) / (30.44 * 86400)));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Case #<?= (int)$case['id'] ?> · RentBridge</title>
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
                    <i class="bi bi-arrow-left"></i> All cases
                </a>
            </p>

            <div class="d-flex justify-content-between align-items-start mb-4">
                <div>
                    <h1 class="mb-1">Case #<?= (int)$case['id'] ?></h1>
                    <p class="text-secondary mb-0">
                        Assigned to you · <?= e(date('d M Y, H:i', strtotime($case['updated_at']))) ?>
                    </p>
                </div>
                <span class="badge bg-<?= $color ?> fs-6"><?= e($label) ?></span>
            </div>

            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-danger"><?= e($errors['general']) ?></div>
            <?php endif; ?>

            <div class="row g-4">

                <!-- Property -->
                <div class="col-12">
                    <div class="bg-white border rounded-3 p-4">
                        <h6 class="text-secondary text-uppercase small mb-3">Property</h6>
                        <h5><?= e($case['property_title']) ?></h5>
                        <p class="text-secondary small mb-0">
                            <i class="bi bi-geo-alt"></i>
                            <?= e($case['property_address']) ?>,
                            <?= e($case['property_city']) ?> <?= e($case['property_postcode']) ?>,
                            <?= e($case['property_state']) ?>
                        </p>
                    </div>
                </div>

                <!-- Student -->
                <div class="col-md-6">
                    <div class="bg-white border rounded-3 p-4 h-100">
                        <h6 class="text-secondary text-uppercase small mb-3">Student (Tenant)</h6>
                        <h5><?= e($case['student_name']) ?></h5>
                        <div class="small text-secondary">
                            <div><i class="bi bi-card-text"></i> Matric: <?= e($case['student_matric']) ?></div>
                            <div><i class="bi bi-envelope"></i> <?= e($case['student_email']) ?></div>
                            <div><i class="bi bi-telephone"></i> <?= e($case['student_phone']) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Landlord -->
                <div class="col-md-6">
                    <div class="bg-white border rounded-3 p-4 h-100">
                        <h6 class="text-secondary text-uppercase small mb-3">Landlord</h6>
                        <h5><?= e($case['landlord_name']) ?></h5>
                        <div class="small text-secondary">
                            <div><i class="bi bi-envelope"></i> <?= e($case['landlord_email']) ?></div>
                            <div><i class="bi bi-telephone"></i> <?= e($case['landlord_phone']) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Tenancy terms -->
                <div class="col-12">
                    <div class="bg-white border rounded-3 p-4">
                        <h6 class="text-secondary text-uppercase small mb-3">Tenancy terms</h6>
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="text-secondary small text-uppercase">Move in</div>
                                <strong class="fs-5"><?= e(date('d M Y', $startTs)) ?></strong>
                            </div>
                            <div class="col-md-3">
                                <div class="text-secondary small text-uppercase">Move out</div>
                                <strong class="fs-5"><?= e(date('d M Y', $endTs)) ?></strong>
                            </div>
                            <div class="col-md-3">
                                <div class="text-secondary small text-uppercase">Duration</div>
                                <strong class="fs-5"><?= $months ?> month<?= $months === 1 ? '' : 's' ?></strong>
                            </div>
                            <div class="col-md-3">
                                <div class="text-secondary small text-uppercase">Total rent</div>
                                <strong class="fs-5 text-emerald">RM <?= number_format($months * (float)$case['monthly_rent']) ?></strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Student note -->
                <?php if (!empty($case['student_note'])): ?>
                <div class="col-12">
                    <div class="bg-white border rounded-3 p-4">
                        <h6 class="text-secondary text-uppercase small mb-2">Student's note</h6>
                        <p class="mb-0" style="white-space:pre-line;"><?= e($case['student_note']) ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Landlord response (if any) -->
                <?php if (!empty($case['landlord_response'])): ?>
                <div class="col-12">
                    <div class="bg-light border rounded-3 p-4">
                        <h6 class="text-secondary text-uppercase small mb-2">Landlord's note</h6>
                        <p class="mb-0" style="white-space:pre-line;"><?= e($case['landlord_response']) ?></p>
                    </div>
                </div>
                <?php endif; ?>
                    <?php
                    require_once __DIR__ . '/../includes/co_tenants.php';
                    $coTenants = get_co_tenants((int)$case['id']);
                    $additionalCount = count(array_filter($coTenants, fn($c) => !$c['is_primary']));
                    ?>

                    <div class="bg-white border rounded-3 p-4 mb-3"
                        style="border-left: 4px solid #D4A017 !important;">
                        <h6 class="text-secondary text-uppercase small mb-3">
                            Co-tenants
                            <span class="badge bg-secondary ms-1"><?= count($coTenants) ?> total</span>
                        </h6>

                        <?php if (!empty($coTenants)): ?>
                            <table class="table table-sm mb-3">
                                <thead style="background:#F4F4EE;">
                                    <tr>
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>IC</th>
                                        <th>Phone</th>
                                        <th>Role</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($coTenants as $i => $ct): ?>
                                        <tr>
                                            <td><?= $i + 1 ?></td>
                                            <td><strong><?= e($ct['full_name']) ?></strong></td>
                                            <td><code class="small"><?= e($ct['ic_number']) ?></code></td>
                                            <td class="small"><?= e($ct['phone'] ?: '—') ?></td>
                                            <td>
                                                <?php if ((int)$ct['is_primary'] === 1): ?>
                                                    <span class="badge bg-primary">Primary (signs)</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Co-tenant</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>

    <?php if (in_array($case['status'], ['agent_verifying', 'contract_pending'], true)): ?>
    <form method="POST" action="/rentbridge/agent/send_cotenant_form.php" class="d-inline">
        <?= csrf_field() ?>
        <input type="hidden" name="tenancy_id" value="<?= (int)$case['id'] ?>">
        <button type="submit" class="btn btn-warning btn-sm">
            <i class="bi bi-send me-1"></i>
            <?= $additionalCount > 0 ? 'Re-send' : 'Send' ?> co-tenant form to student
        </button>
    </form>
    <small class="text-secondary d-block mt-2">
        Sends a form in chat for the student to fill in their IC + add additional co-tenants.
        Required before contract generation.
    </small>
    <?php endif; ?>
</div>  
<!-- CONTRACT GENERATION -->
<?php
$stmt = $pdo->prepare("SELECT * FROM contracts WHERE tenancy_id = ? LIMIT 1");
$stmt->execute([(int)$case['id']]);
$contract = $stmt->fetch();

$canGenerate = !empty($coTenants);
$primaryReady = false;
foreach ($coTenants as $ct) {
    if ((int)$ct['is_primary'] === 1 && $ct['ic_number'] !== 'PENDING' && !empty($ct['ic_number'])) {
        $primaryReady = true;
        break;
    }
}
?>

<?php if (in_array($case['status'], ['agent_verifying','agent_verified','contract_pending','active'], true)): ?>
<div class="bg-white border rounded-3 p-4 mb-3"
     style="border-left: 4px solid #2E8B57 !important;">
    <h6 class="text-secondary text-uppercase small mb-3">
        Contract
        <?php if ($contract && !empty($contract['generated_pdf_path'])): ?>
            <span class="badge bg-success ms-1">Generated</span>
        <?php endif; ?>
    </h6>

    <?php if (!$primaryReady): ?>
        <div class="alert alert-warning small mb-0">
            <i class="bi bi-exclamation-triangle"></i>
            <strong>Cannot generate contract yet.</strong> The primary tenant has not
            submitted their IC number. Send the co-tenant form first.
        </div>
    <?php elseif (empty($contract) || empty($contract['generated_pdf_path'])): ?>
        <p class="small text-secondary mb-3">
            Generate the contract PDF. You'll download it and send to all parties
            (landlord + tenants) for handwritten signing via WhatsApp/email.
        </p>
        <a href="/rentbridge/agent/generate_contract.php?tenancy_id=<?= (int)$case['id'] ?>"
           class="btn btn-success">
            <i class="bi bi-file-earmark-pdf me-1"></i> Generate contract PDF
        </a>
    <?php else: ?>
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <small class="text-secondary">Contract code</small>
                <div><code><?= e($contract['contract_code']) ?></code></div>
            </div>
            <div class="col-md-4">
                <small class="text-secondary">Generated</small>
                <div><?= e(date('d M Y, H:i', strtotime($contract['generated_at']))) ?></div>
            </div>
            <div class="col-md-4">
                <small class="text-secondary">Status</small>
                <div>
                    <?php if ($contract['status'] === 'active'): ?>
                        <span class="badge bg-success">Active (signed)</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark">Awaiting signed upload</span>
                    <?php endif; ?>
                    
                </div>
            </div>
        </div>

        <div class="d-flex gap-2 flex-wrap">
            <a href="/rentbridge/agent/generate_contract.php?tenancy_id=<?= (int)$case['id'] ?>"
               target="_blank" class="btn btn-primary">
                <i class="bi bi-download me-1"></i> Download PDF
            </a>
        </div>
        <small class="text-secondary d-block mt-2">
            Send via WhatsApp/email to landlord + all tenants for signing.
            Upload signed copy back (coming next turn).
        </small>
    <?php endif; ?>
    <?php if (!empty($contract['generated_pdf_path']) && empty($contract['signed_pdf_path']) && $contract['status'] !== 'active'): ?>
    <!-- Upload signed copy section -->
    <hr class="my-3">
    <h6 class="text-secondary text-uppercase small mb-3">Upload signed copy</h6>
    <p class="small text-secondary mb-3">
        After all parties have signed the printed contract, scan or photograph
        the signed pages, combine into a single PDF, and upload here.
    </p>

    <form method="POST" action="/rentbridge/agent/upload_signed_contract.php"
          enctype="multipart/form-data">
        <?= csrf_field() ?>
        <input type="hidden" name="tenancy_id" value="<?= (int)$case['id'] ?>">

        <div class="mb-3">
            <input type="file" name="signed_pdf" class="form-control"
                   accept="application/pdf" required>
            <small class="text-secondary">PDF only, max 10MB</small>
        </div>

        <button type="submit" class="btn btn-success"
                onclick="return confirm('Upload signed contract? This will mark the tenancy as active and the property as rented.');">
            <i class="bi bi-upload me-1"></i> Upload signed contract
        </button>
    </form>
<?php elseif (!empty($contract['signed_pdf_path'])): ?>
    <!-- Signed copy already uploaded -->
    <hr class="my-3">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h6 class="text-secondary text-uppercase small mb-2">Signed contract</h6>
            <p class="small mb-0">
                <span class="badge bg-success">✓ Active</span>
                Uploaded <?= e(date('d M Y, H:i', strtotime($contract['signed_uploaded_at']))) ?>
            </p>
        </div>
        <a href="/rentbridge/<?= e($contract['signed_pdf_path']) ?>"
           target="_blank" class="btn btn-outline-dark btn-sm">
            <i class="bi bi-file-earmark-pdf me-1"></i> Download signed copy
        </a>
    </div>
<?php endif; ?>

<?php
// E-sign mixed path: all turns done but one or more parties chose manual signing
$mixedSigningPending = $contract
    && $contract['status'] !== 'active'
    && !empty($contract['student_signed_at'])
    && !empty($contract['landlord_signed_at'])
    && !empty($contract['agent_signed_at'])
    && ($contract['student_sign_method'] === 'manual' || $contract['landlord_sign_method'] === 'manual')
    && empty($contract['generated_pdf_path']);
if ($mixedSigningPending): ?>
<hr class="my-3">
<div class="alert alert-warning d-flex gap-3 align-items-start mb-3">
    <i class="bi bi-file-earmark-person fs-4 mt-1"></i>
    <div>
        <strong>Physical signature collection required</strong>
        <div class="small mt-1">
            <?php
            $manualParties = [];
            if ($contract['student_sign_method'] === 'manual') $manualParties[] = 'Tenant';
            if ($contract['landlord_sign_method'] === 'manual') $manualParties[] = 'Landlord';
            echo implode(' and ', $manualParties);
            ?> chose to sign a physical copy.
            Print the contract, collect their handwritten signatures, then upload the merged PDF below.
        </div>
    </div>
</div>
<h6 class="text-secondary text-uppercase small mb-2">Upload merged signed contract</h6>
<form method="POST" action="/rentbridge/agent/upload_signed_contract.php" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <input type="hidden" name="tenancy_id" value="<?= (int)$case['id'] ?>">
    <div class="mb-3">
        <input type="file" name="signed_pdf" class="form-control" accept="application/pdf" required>
        <small class="text-secondary">PDF only, max 20MB</small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <button type="submit" class="btn btn-success"
                onclick="return confirm('Upload merged signed contract? This will activate the tenancy.');">
            <i class="bi bi-upload me-1"></i> Upload &amp; activate tenancy
        </button>
        <?php if (!empty($contract['contract_pdf_path'])): ?>
        <a href="/rentbridge/<?= e($contract['contract_pdf_path']) ?>" target="_blank"
           class="btn btn-outline-secondary">
            <i class="bi bi-download me-1"></i> Download digital draft
        </a>
        <?php endif; ?>
    </div>
</form>
<?php endif; ?>
</div>
<?php endif; ?>


                <!-- Action panel — only if pending_agent -->
                <?php if ($case['status'] === 'pending_agent'): ?>
                <div class="col-12">
                    <div class="bg-white border rounded-3 p-4">
                        <h6 class="text-secondary text-uppercase small mb-3">Your response</h6>

                        <div class="alert alert-info border-0 small" style="background:var(--rb-cream);">
                            <i class="bi bi-info-circle"></i>
                            <strong>Reminder:</strong> Accepting means you'll witness this tenancy contract and be the case handler. If you accept then need to back out later, contact admin.
                        </div>

                        <?php if ($agentCaseload >= AGENT_CASELOAD_WARN): ?>
                        <div class="alert alert-warning small mb-3 py-2">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i>
                            <strong>High caseload warning</strong> — you currently have <?= $agentCaseload ?> active properties/cases (recommended max: <?= AGENT_CASELOAD_WARN ?>).
                            By accepting, you confirm you can fulfil all responsibilities on time.
                            <strong>Inability to complete assigned duties may result in a report being filed against you.</strong>
                        </div>
                        <?php endif; ?>

                        <form method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="tenancy_id" value="<?= (int)$case['id'] ?>">

                            <div class="mb-3">
                                <label class="form-label">Optional message <small class="text-secondary fw-normal">— required if rejecting</small></label>
                                <textarea name="reason" rows="3"
                                          class="form-control <?= isset($errors['reason']) ? 'is-invalid' : '' ?>"
                                          placeholder="e.g. Reason for declining"><?= e($reason) ?></textarea>
                                <?php if (isset($errors['reason'])): ?>
                                    <div class="invalid-feedback"><?= e($errors['reason']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" name="action" value="accept" class="btn btn-success"
                                    <?php if ($agentCaseload >= AGENT_CASELOAD_WARN): ?>
                                    onclick="return confirm('Your caseload is high. You are responsible for completing all accepted cases — failure may result in a report. Accept anyway?')"
                                    <?php endif; ?>>
                                    <i class="bi bi-check-circle me-1"></i> Accept case
                                </button>
                                <button type="submit" name="action" value="reject" class="btn btn-outline-danger"
                                        onclick="return confirm('Decline this case? The system will reassign to another agent.');">
                                    <i class="bi bi-x-circle me-1"></i> Decline
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<!-- REPORT ISSUE -->
<div class="container pb-5">
    <div class="row justify-content-center">
        <div class="col-lg-9 text-center">
            <button type="button"
                    class="btn btn-link btn-sm text-secondary text-decoration-none p-0"
                    data-bs-toggle="modal" data-bs-target="#reportModal">
                <i class="bi bi-flag me-1"></i> Report an issue with this case
            </button>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/reports.php';
$reportSubjects = [
    ['id' => (int)$case['student_id'],  'name' => $case['student_name'],  'role' => 'student'],
    ['id' => (int)$case['landlord_id'], 'name' => $case['landlord_name'], 'role' => 'landlord'],
];
render_report_modal($reportSubjects, 'tenancy', (int)$case['id']);
?>

</body>
</html>