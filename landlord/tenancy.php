<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('landlord');

$tenancyId = (int)($_GET['id'] ?? 0);
if ($tenancyId <= 0) {
    http_response_code(400);
    die('Invalid tenancy ID.');
}

$pdo = db();
$userId = current_user_id();

// Fetch tenancy — must belong to this landlord
$stmt = $pdo->prepare("
    SELECT b.*,
           p.title          AS property_title,
           p.address        AS property_address,
           p.city           AS property_city,
           p.postcode       AS property_postcode,
           p.monthly_rent   AS property_rent,
           s.full_name      AS student_name,
           s.preferred_name AS student_nickname,
           s.matric_no      AS student_matric,
           s.phone          AS student_phone,
           su.email         AS student_email,
           a.full_name      AS agent_name,
           a.staff_id       AS agent_staff_id,
           a.department     AS agent_department,
           a.phone          AS agent_phone,
           a.allow_whatsapp AS agent_allow_whatsapp,
           au.email         AS agent_email,
           c.id                  AS contract_id,
           c.contract_code,
           c.status              AS contract_status,
           c.student_signed_at,
           c.landlord_signed_at,
           c.agent_signed_at,
           c.contract_pdf_path,
           c.generated_pdf_path,
           c.signed_pdf_path,
           c.created_at          AS contract_created_at,
           v.id             AS verification_id,
           v.outcome        AS verification_outcome,
           v.issue_severity AS verification_severity
      FROM tenancies b
      JOIN properties p ON p.id = b.property_id
      JOIN users su ON su.id = b.student_id
      JOIN students s ON s.user_id = b.student_id
      LEFT JOIN users au ON au.id = b.agent_id
      LEFT JOIN agents a ON a.user_id = b.agent_id
      LEFT JOIN contracts c ON c.tenancy_id = b.id
      LEFT JOIN agent_verifications v ON v.tenancy_id = b.id
     WHERE b.id = ?
       AND b.landlord_id = ?
     LIMIT 1
");
$stmt->execute([$tenancyId, $userId]);
$tenancy = $stmt->fetch();

if (!$tenancy) {
    http_response_code(404);
    die('Tenancy not found or you are not the landlord on this tenancy.');
}

// --- HANDLE ACTIONS ---
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'approve' && $tenancy['status'] === 'pending_landlord') {
            $response = trim($_POST['landlord_response'] ?? '');

            $pdo->beginTransaction();
            $stmt = $pdo->prepare("
                UPDATE tenancies
                   SET status = 'pending_agent',
                       landlord_response = ?
                 WHERE id = ?
            ");
            $stmt->execute([$response ?: null, $tenancyId]);

            // Auto-assign agent (uses existing helper)
            require_once __DIR__ . '/../includes/tenancies.php';
            if (function_exists('auto_assign_agent')) {
                auto_assign_agent($tenancyId);
            }

            // Notify student
            notify(
                (int)$tenancy['student_id'],
                'tenancy_approved',
                'Landlord approved your tenancy application',
                'Your tenancy #' . $tenancyId . ' for "' . $tenancy['property_title']
                    . '" was approved. An agent will inspect the property next.',
                '/rentbridge/student/tenancy.php?id=' . $tenancyId
            );

            $pdo->commit();
            set_flash('success', 'Tenancy approved. Agent will be assigned for inspection.');
            header('Location: /rentbridge/landlord/tenancy.php?id=' . $tenancyId);
            exit;
        }

        if ($action === 'reject' && $tenancy['status'] === 'pending_landlord') {
            $reason = trim($_POST['reject_reason'] ?? '');
            if ($reason === '') {
                $errors['general'] = 'Please provide a reason for rejection.';
            } else {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("
                    UPDATE tenancies
                       SET status = 'rejected_by_landlord',
                           landlord_response = ?,
                           cancellation_reason = ?,
                           cancelled_by = ?
                     WHERE id = ?
                ");
                $stmt->execute([$reason, $reason, $userId, $tenancyId]);

                notify(
                    (int)$tenancy['student_id'],
                    'tenancy_rejected',
                    'Tenancy application not approved',
                    'Your tenancy #' . $tenancyId . ' for "' . $tenancy['property_title']
                        . '" was not approved. Reason: ' . $reason,
                    '/rentbridge/student/tenancy.php?id=' . $tenancyId
                );

                $pdo->commit();
                set_flash('warning', 'Tenancy rejected. Student has been notified.');
                header('Location: /rentbridge/landlord/tenancy.php?id=' . $tenancyId);
                exit;
            }
        }

        if ($action === 'cancel' && in_array($tenancy['status'], ['pending_agent','agent_assigned','agent_verifying','agent_verified','contract_pending'], true)) {
            $reason = trim($_POST['cancel_reason'] ?? '');
            if ($reason === '') {
                $errors['general'] = 'Cancellation reason is required.';
            } else {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("
                    UPDATE tenancies
                       SET status = 'cancelled_by_landlord',
                           cancellation_reason = ?,
                           cancelled_by = ?
                     WHERE id = ?
                ");
                $stmt->execute([$reason, $userId, $tenancyId]);

                // Release property
                $stmt = $pdo->prepare("UPDATE properties SET status = 'available' WHERE id = ?");
                $stmt->execute([(int)$tenancy['property_id']]);

                // Notify student + agent (if assigned)
                notify(
                    (int)$tenancy['student_id'],
                    'tenancy_cancelled',
                    'Tenancy cancelled by landlord',
                    'Tenancy #' . $tenancyId . ' cancelled. Reason: ' . $reason,
                    '/rentbridge/student/tenancies.php'
                );
                if (!empty($tenancy['agent_id'])) {
                    notify(
                        (int)$tenancy['agent_id'],
                        'tenancy_cancelled',
                        'Tenancy cancelled by landlord',
                        'Tenancy #' . $tenancyId . ' was cancelled. Case closed.',
                        '/rentbridge/agent/cases.php'
                    );
                }

                $pdo->commit();
                set_flash('warning', 'Tenancy cancelled. All parties notified.');
                header('Location: /rentbridge/landlord/tenancy.php?id=' . $tenancyId);
                exit;
            }
        }
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $errors['general'] = 'Action failed: ' . $e->getMessage();
    }
}

// Sign progress
$signed = 0;
if ($tenancy['contract_id']) {
    if (!empty($tenancy['student_signed_at']))  $signed++;
    if (!empty($tenancy['landlord_signed_at'])) $signed++;
    if (!empty($tenancy['agent_signed_at']))    $signed++;
}

$pageTitle = 'Tenancy #' . $tenancyId;
$activeNav = 'properties';

function landlord_tenancy_status(string $status): array {
    return match ($status) {
        'pending_landlord'      => ['Awaiting your response',   'warning'],
        'rejected_by_landlord'  => ['You rejected',             'danger'],
        'pending_agent'         => ['Awaiting agent assignment','warning'],
        'agent_assigned'        => ['Agent assigned',           'info'],
        'agent_verifying'       => ['🔍 Agent inspecting',      'info'],
        'agent_verified'        => ['✓ Inspection passed',      'success'],
        'verification_failed'   => ['Inspection failed',        'danger'],
        'contract_pending'      => ['📝 Contract signing',      'primary'],
        'active'                => ['Active tenancy',           'success'],
        'completed'             => ['Completed',                'secondary'],
        'cancelled_by_student'  => ['Cancelled (student)',      'secondary'],
        'cancelled_by_landlord' => ['Cancelled (you)',          'secondary'],
        'cancelled_by_admin'    => ['Cancelled (admin)',        'danger'],
        default                 => [$status, 'secondary'],
    };
}
[$statusLabel, $statusColor] = landlord_tenancy_status($tenancy['status']);

ob_start();
?>

<p class="small mb-3">
    <a href="/rentbridge/landlord/properties.php" class="text-secondary text-decoration-none">
        <i class="bi bi-arrow-left"></i> Back to properties
    </a>
</p>

<?php if (!empty($errors['general'])): ?>
    <div class="alert alert-danger"><?= e($errors['general']) ?></div>
<?php endif; ?>

<!-- HEADER -->
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h2 class="mb-1">Tenancy #<?= (int)$tenancyId ?></h2>
        <p class="text-secondary mb-0">
            Submitted <?= e(date('d M Y, H:i', strtotime($tenancy['created_at']))) ?>
        </p>
    </div>
    <span class="badge bg-<?= $statusColor ?> fs-6"><?= e($statusLabel) ?></span>
</div>

<!-- DECISION BOX (for pending_landlord) -->
<?php if ($tenancy['status'] === 'pending_landlord'): ?>
    <div class="bg-white border rounded-3 p-4 mb-4"
         style="border-left: 4px solid #D4A017 !important;">
        <h5 class="mb-3">
            <i class="bi bi-exclamation-circle text-warning"></i>
            Your decision is needed
        </h5>
        <p class="text-secondary mb-3">
            <?= e($tenancy['student_name']) ?> wants to rent
            <strong><?= e($tenancy['property_title']) ?></strong>
            from <?= e(date('d M Y', strtotime($tenancy['start_date']))) ?>
            to <?= e(date('d M Y', strtotime($tenancy['end_date']))) ?>.
        </p>

        <?php if (!empty($tenancy['student_note'])): ?>
            <div class="bg-light rounded-3 p-3 mb-3 small">
                <strong>Student's note:</strong><br>
                <?= nl2br(e($tenancy['student_note'])) ?>
            </div>
        <?php endif; ?>

        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-success"
                    data-bs-toggle="collapse" data-bs-target="#approveForm">
                <i class="bi bi-check2-circle me-1"></i> Approve application
            </button>
            <button type="button" class="btn btn-outline-danger"
                    data-bs-toggle="collapse" data-bs-target="#rejectForm">
                <i class="bi bi-x-circle me-1"></i> Reject...
            </button>
        </div>

        <!-- Approve form -->
        <div class="collapse mt-3" id="approveForm">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="approve">
                <label class="form-label small fw-semibold">
                    Optional message to student
                </label>
                <textarea name="landlord_response" rows="2" class="form-control mb-2"
                          placeholder="Welcome! Looking forward to having you as a tenant..."></textarea>
                <button type="submit" class="btn btn-success btn-sm"
                        onclick="return confirm('Approve this tenancy? An agent will be assigned to inspect the property.');">
                    Confirm approval
                </button>
            </form>
        </div>

        <!-- Reject form -->
        <div class="collapse mt-3" id="rejectForm">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reject">
                <label class="form-label small fw-semibold">
                    Reason for rejection <small class="text-danger">*</small>
                </label>
                <textarea name="reject_reason" rows="3" class="form-control mb-2" required
                          placeholder="e.g. Property no longer available; prefer longer tenancy term; etc."></textarea>
                <button type="submit" class="btn btn-danger btn-sm">
                    Confirm rejection
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- PROPERTY CARD -->
<div class="bg-white border rounded-3 p-4 mb-3">
    <h6 class="text-secondary text-uppercase small mb-3">Property</h6>
    <div class="d-flex justify-content-between flex-wrap gap-3">
        <div>
            <a href="/rentbridge/landlord/property.php?id=<?= (int)$tenancy['property_id'] ?>"
               class="text-decoration-none text-dark">
                <strong class="fs-5"><?= e($tenancy['property_title']) ?></strong>
            </a>
            <div class="small text-secondary">
                <i class="bi bi-geo-alt"></i>
                <?= e($tenancy['property_address']) ?>,
                <?= e($tenancy['property_city']) ?> <?= e($tenancy['property_postcode']) ?>
            </div>
        </div>
        <div class="text-end">
            <div class="small text-secondary">Monthly rent</div>
            <strong class="text-emerald fs-5">
                RM <?= number_format((float)$tenancy['monthly_rent']) ?>
            </strong>
        </div>
    </div>
</div>

<!-- STUDENT CARD -->
<div class="bg-white border rounded-3 p-4 mb-3">
    <h6 class="text-secondary text-uppercase small mb-3">Student</h6>
    <div class="d-flex justify-content-between flex-wrap align-items-start gap-3">
        <div>
            <strong><?= e($tenancy['student_name']) ?></strong>
            <?php if (!empty($tenancy['student_nickname'])): ?>
                <span class="text-secondary">"<?= e($tenancy['student_nickname']) ?>"</span>
            <?php endif; ?>
            <div class="small text-secondary">
                <code><?= e($tenancy['student_matric']) ?></code>
            </div>
            <div class="small text-secondary mt-2">
                <i class="bi bi-envelope"></i> <?= e($tenancy['student_email']) ?>
            </div>
            <div class="small text-secondary">
                <i class="bi bi-telephone"></i> <?= e($tenancy['student_phone']) ?>
            </div>
        </div>
        <a href="/rentbridge/chat/start.php?with=<?= (int)$tenancy['student_id'] ?>&tenancy_id=<?= (int)$tenancyId ?>"
           class="btn btn-outline-primary btn-sm">
            <i class="bi bi-chat-dots me-1"></i> Open chat
        </a>
    </div>
</div>

<!-- AGENT CARD (if assigned) -->
<?php if (!empty($tenancy['agent_id'])): ?>
<div class="bg-white border rounded-3 p-4 mb-3">
    <h6 class="text-secondary text-uppercase small mb-3">Agent</h6>
    <div class="d-flex justify-content-between flex-wrap align-items-start gap-3">
        <div>
            <strong><?= e($tenancy['agent_name']) ?></strong>
            <div class="small text-secondary">
                <code><?= e($tenancy['agent_staff_id']) ?></code> · <?= e($tenancy['agent_department']) ?>
            </div>
            <div class="small text-secondary mt-2">
                <i class="bi bi-envelope"></i> <?= e($tenancy['agent_email']) ?>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?php if ((int)($tenancy['agent_allow_whatsapp'] ?? 0) === 1): ?>
                <?php
                $waMsg = 'Hi, regarding tenancy #' . $tenancyId . ' (' . $tenancy['property_title'] . ')';
                ?>
                <a href="<?= e(whatsapp_link($tenancy['agent_phone'], $waMsg)) ?>"
                   target="_blank" class="btn btn-sm"
                   style="background:#25D366; color:white;">
                    <i class="bi bi-whatsapp"></i> WhatsApp
                </a>
            <?php endif; ?>
            <a href="/rentbridge/chat/start.php?with=<?= (int)$tenancy['agent_id'] ?>&tenancy_id=<?= (int)$tenancyId ?>"
               class="btn btn-outline-primary btn-sm">
                <i class="bi bi-chat-dots me-1"></i> Open chat
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- TENANCY DETAILS -->
<div class="bg-white border rounded-3 p-4 mb-3">
    <h6 class="text-secondary text-uppercase small mb-3">Tenancy details</h6>
    <div class="row g-3">
        <div class="col-md-3 col-6">
            <small class="text-secondary text-uppercase">Start</small>
            <div class="fw-semibold"><?= e(date('d M Y', strtotime($tenancy['start_date']))) ?></div>
        </div>
        <div class="col-md-3 col-6">
            <small class="text-secondary text-uppercase">End</small>
            <div class="fw-semibold"><?= e(date('d M Y', strtotime($tenancy['end_date']))) ?></div>
        </div>
        <div class="col-md-3 col-6">
            <small class="text-secondary text-uppercase">Rent</small>
            <div class="fw-semibold">RM <?= number_format((float)$tenancy['monthly_rent']) ?></div>
        </div>
        <div class="col-md-3 col-6">
            <small class="text-secondary text-uppercase">Deposit</small>
            <div class="fw-semibold">RM <?= number_format((float)$tenancy['deposit']) ?></div>
        </div>
    </div>

    <?php if (!empty($tenancy['landlord_response'])): ?>
        <hr>
        <small class="text-secondary text-uppercase">Your response</small>
        <p class="small mb-0" style="white-space:pre-line;"><?= e($tenancy['landlord_response']) ?></p>
    <?php endif; ?>

    <?php if (!empty($tenancy['cancellation_reason'])): ?>
        <hr>
        <small class="text-secondary text-uppercase">Cancellation reason</small>
        <p class="small text-danger mb-0" style="white-space:pre-line;"><?= e($tenancy['cancellation_reason']) ?></p>
    <?php endif; ?>
</div>

<!-- INSPECTION REPORT (if exists) -->
<?php if (!empty($tenancy['verification_id'])): ?>
<div class="bg-white border rounded-3 p-4 mb-3">
    <div class="d-flex justify-content-between align-items-center mb-2">
        <h6 class="text-secondary text-uppercase small mb-0">Inspection report</h6>
        <a href="/rentbridge/agent/inspection_view.php?id=<?= (int)$tenancy['verification_id'] ?>"
           class="btn btn-sm btn-outline-dark">
            View full report <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>
    <p class="small mb-0 text-secondary">
        Outcome: <strong><?= e(ucfirst(str_replace('_',' ', $tenancy['verification_outcome'] ?? 'in progress'))) ?></strong>
        <?php if (!empty($tenancy['verification_severity']) && $tenancy['verification_severity'] !== 'none'): ?>
            · Issue severity: <strong><?= e(ucfirst($tenancy['verification_severity'])) ?></strong>
        <?php endif; ?>
    </p>
</div>
<?php endif; ?>

<!-- CONTRACT CARD (if exists) -->
<?php if (!empty($tenancy['contract_id'])): ?>
<div class="bg-white border rounded-3 p-4 mb-3">
    <h6 class="text-secondary text-uppercase small mb-3">Contract</h6>

    <div class="row g-3 align-items-center mb-3">
        <div class="col-md-4">
            <small class="text-secondary">Contract code</small>
            <div class="fw-semibold"><code><?= e($tenancy['contract_code']) ?></code></div>
        </div>
        <div class="col-md-4">
            <small class="text-secondary">Status</small>
            <div>
                <?php if ($tenancy['contract_status'] === 'active'): ?>
                    <span class="badge bg-success">Active</span>
                <?php elseif ($tenancy['contract_status'] === 'pending_signatures'): ?>
                    <span class="badge bg-warning text-dark">Awaiting signatures</span>
                <?php elseif ($tenancy['contract_status'] === 'completed'): ?>
                    <span class="badge bg-secondary">Completed</span>
                <?php else: ?>
                    <span class="badge bg-danger"><?= e($tenancy['contract_status']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-4">
            <small class="text-secondary">Sign progress</small>
            <div class="fw-semibold">
                <?= $signed ?>/3
                <?php if ($signed === 3): ?> ✓<?php endif; ?>
            </div>
        </div>
    </div>

    <?php
        $bestLandlordPdf = $tenancy['contract_pdf_path'] ?? $tenancy['signed_pdf_path'] ?? $tenancy['generated_pdf_path'] ?? null;
        $landlordPdfFull = $bestLandlordPdf ? __DIR__ . '/../' . $bestLandlordPdf : null;
    ?>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/rentbridge/contracts/view.php?id=<?= (int)$tenancy['contract_id'] ?>"
           class="btn btn-sm btn-primary">
            View contract <i class="bi bi-arrow-right ms-1"></i>
        </a>
        <?php if ($bestLandlordPdf && $landlordPdfFull && file_exists($landlordPdfFull)): ?>
            <a href="/rentbridge/<?= e($bestLandlordPdf) ?>" target="_blank"
               class="btn btn-sm btn-outline-success">
                <i class="bi bi-download me-1"></i> Download PDF
            </a>
        <?php endif; ?>
        <?php if (empty($tenancy['landlord_signed_at']) && $tenancy['contract_status'] === 'pending_signatures'): ?>
            <a href="/rentbridge/contracts/sign.php?id=<?= (int)$tenancy['contract_id'] ?>"
               class="btn btn-sm btn-success">
                <i class="bi bi-pen-fill me-1"></i> Sign your part
            </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- CANCEL ACTION (for in-progress tenancies) -->
<?php if (in_array($tenancy['status'], ['pending_agent','agent_assigned','agent_verifying','agent_verified','contract_pending'], true)): ?>
<div class="bg-white border rounded-3 p-4 mb-4"
     style="border-left: 4px solid #DC3545 !important;">
    <h6 class="text-secondary text-uppercase small mb-3">Cancel tenancy</h6>
    <p class="text-secondary small mb-3">
        Cancelling will release the property and notify the student
        <?php if (!empty($tenancy['agent_id'])): ?>and the agent<?php endif; ?>.
        Use only if absolutely necessary.
    </p>
    <button type="button" class="btn btn-outline-danger btn-sm"
            data-bs-toggle="collapse" data-bs-target="#cancelForm">
        <i class="bi bi-x-circle me-1"></i> Cancel this tenancy...
    </button>
    <div class="collapse mt-3" id="cancelForm">
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="cancel">
            <label class="form-label small fw-semibold">
                Reason <small class="text-danger">*</small>
            </label>
            <textarea name="cancel_reason" rows="3" class="form-control mb-2" required
                      placeholder="Explain why you're cancelling"></textarea>
            <button type="submit" class="btn btn-danger btn-sm"
                    onclick="return confirm('Cancel this tenancy? This cannot be undone.');">
                Confirm cancellation
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- REPORT ISSUE -->
<div class="text-center pt-2 pb-4">
    <button type="button"
            class="btn btn-link btn-sm text-secondary text-decoration-none p-0"
            data-bs-toggle="modal" data-bs-target="#reportModal">
        <i class="bi bi-flag me-1"></i> Report an issue with this tenancy
    </button>
</div>

<?php
$pageContent = ob_get_clean();

require_once __DIR__ . '/../includes/reports.php';
$reportSubjects = [];
if (!empty($tenancy['student_id']))
    $reportSubjects[] = ['id' => (int)$tenancy['student_id'], 'name' => $tenancy['student_name'], 'role' => 'student'];
if (!empty($tenancy['agent_id']))
    $reportSubjects[] = ['id' => (int)$tenancy['agent_id'], 'name' => $tenancy['agent_name'], 'role' => 'agent'];

if (!empty($reportSubjects)):
    // Append modal to pageContent so it's inside the layout
    ob_start();
    render_report_modal($reportSubjects, 'tenancy', (int)$tenancy['id']);
    $pageContent .= ob_get_clean();
endif;

require __DIR__ . '/../includes/landlord_layout.php';