<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$bookingId = (int)($_GET['id'] ?? 0);
if ($bookingId <= 0) {
    http_response_code(400);
    die('Invalid tenancy ID.');
}

$pdo = db();

// Fetch booking + all parties + contract
$stmt = $pdo->prepare("
    SELECT b.*,
           p.title          AS property_title,
           p.address        AS property_address,
           p.city           AS property_city,
           p.postcode       AS property_postcode,
           s.full_name      AS student_name,
           s.matric_no      AS student_matric,
           s.phone          AS student_phone,
           su.email         AS student_email,
           l.full_name      AS landlord_name,
           l.phone          AS landlord_phone,
           lu.email         AS landlord_email,
           a.full_name      AS agent_name,
           a.staff_id       AS agent_staff_id,
           a.department     AS agent_department,
           au.email         AS agent_email,
           c.id             AS contract_id,
           c.contract_code,
           c.status         AS contract_status,
           c.student_signed_at,
           c.landlord_signed_at,
           c.agent_signed_at,
           c.contract_pdf_path,
           c.created_at     AS contract_created_at,
           c.activated_at   AS contract_activated_at,
           v.id             AS verification_id,
           v.outcome        AS verification_outcome
      FROM bookings b
      JOIN properties p ON p.id = b.property_id
      JOIN users su ON su.id = b.student_id
      JOIN students s ON s.user_id = b.student_id
      JOIN users lu ON lu.id = b.landlord_id
      JOIN landlords l ON l.user_id = b.landlord_id
      LEFT JOIN users au ON au.id = b.agent_id
      LEFT JOIN agents a ON a.user_id = b.agent_id
      LEFT JOIN contracts c ON c.booking_id = b.id
      LEFT JOIN agent_verifications v ON v.booking_id = b.id
     WHERE b.id = ?
     LIMIT 1
");
$stmt->execute([$bookingId]);
$tenancy = $stmt->fetch();

if (!$tenancy) {
    http_response_code(404);
    die('Tenancy not found.');
}

// --- HANDLE ADMIN CANCEL ---
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'admin_cancel') {
        $reason = trim($_POST['cancel_reason'] ?? '');
        if ($reason === '') {
            $errors['general'] = 'Cancellation reason required.';
        } elseif (!in_array($tenancy['status'], ['active','completed','cancelled_by_student','cancelled_by_landlord','cancelled_by_admin'], true)) {
            try {
                $pdo->beginTransaction();
                $stmt = $pdo->prepare("
                    UPDATE bookings
                       SET status = 'cancelled_by_admin',
                           cancellation_reason = ?,
                           cancelled_by = ?
                     WHERE id = ?
                ");
                $stmt->execute([$reason, current_user_id(), $bookingId]);

                // Release property
                $stmt = $pdo->prepare("UPDATE properties SET status = 'available' WHERE id = ?");
                $stmt->execute([(int)$tenancy['property_id']]);

                // Notify both parties
                notify((int)$tenancy['student_id'], 'admin_cancelled',
                    'Tenancy cancelled by admin',
                    'Your tenancy #' . $bookingId . ' was cancelled. Reason: ' . $reason,
                    '/rentbridge/student/bookings.php');
                notify((int)$tenancy['landlord_id'], 'admin_cancelled',
                    'Tenancy cancelled by admin',
                    'Tenancy #' . $bookingId . ' was cancelled. Reason: ' . $reason,
                    '/rentbridge/landlord/bookings.php');

                $pdo->commit();
                set_flash('warning', 'Tenancy cancelled and parties notified.');
                header('Location: /rentbridge/admin/booking.php?id=' . $bookingId);
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors['general'] = 'Failed: ' . $e->getMessage();
            }
        }
    }
}

// --- LAYOUT ---
$pageTitle = 'Tenancy #' . $bookingId;
$activeNav = 'bookings';

function tenancy_status_label_full(string $status): array {
    return match ($status) {
        'pending_landlord'      => ['Pending landlord',   'warning'],
        'pending_agent'         => ['Pending agent',      'warning'],
        'agent_verifying'       => ['🔍 Inspecting',      'info'],
        'agent_verified'        => ['✓ Verified',         'success'],
        'verification_failed'   => ['Inspection failed',  'danger'],
        'contract_pending'      => ['📝 Contract signing','primary'],
        'active'                => ['Active tenancy',     'success'],
        'completed'             => ['Completed',          'secondary'],
        'cancelled_by_student'  => ['Cancelled (student)','secondary'],
        'cancelled_by_landlord' => ['Cancelled (landlord)','secondary'],
        'cancelled_by_admin'    => ['Cancelled (admin)',  'danger'],
        'rejected_by_landlord'  => ['Rejected by landlord','danger'],
        default                 => [$status, 'secondary'],
    };
}
[$statusLabel, $statusColor] = tenancy_status_label_full($tenancy['status']);

// Sign progress for contract
$signed = 0;
if ($tenancy['contract_id']) {
    if (!empty($tenancy['student_signed_at']))  $signed++;
    if (!empty($tenancy['landlord_signed_at'])) $signed++;
    if (!empty($tenancy['agent_signed_at']))    $signed++;
}

ob_start();
?>

<p class="small mb-3">
    <a href="/rentbridge/admin/bookings.php" class="text-secondary text-decoration-none">
        <i class="bi bi-arrow-left"></i> Back to tenancies
    </a>
</p>

<?php if (!empty($errors['general'])): ?>
    <div class="alert alert-danger"><?= e($errors['general']) ?></div>
<?php endif; ?>

<!-- HEADER -->
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h2 class="mb-1">Tenancy #<?= (int)$bookingId ?></h2>
        <p class="text-secondary mb-0">
            Created <?= e(date('d M Y, H:i', strtotime($tenancy['created_at']))) ?>
        </p>
    </div>
    <span class="badge bg-<?= $statusColor ?> fs-6"><?= e($statusLabel) ?></span>
</div>

<!-- PROPERTY CARD -->
<div class="bg-white border rounded-3 p-4 mb-4">
    <h6 class="text-secondary text-uppercase small mb-3">Property</h6>
    <div class="d-flex justify-content-between flex-wrap gap-3">
        <div>
            <a href="/rentbridge/admin/property.php?id=<?= (int)$tenancy['property_id'] ?>"
               class="text-decoration-none text-dark">
                <strong class="fs-5"><?= e($tenancy['property_title']) ?></strong>
            </a>
            <div class="small text-secondary">
                <i class="bi bi-geo-alt"></i> <?= e($tenancy['property_address']) ?>,
                <?= e($tenancy['property_city']) ?> <?= e($tenancy['property_postcode']) ?>
            </div>
        </div>
        <div class="text-end">
            <div class="small text-secondary">Monthly rent</div>
            <strong class="text-emerald fs-5">RM <?= number_format((float)$tenancy['monthly_rent']) ?></strong>
        </div>
    </div>
</div>

<!-- 3 PARTIES (side by side) -->
<div class="row g-3 mb-4">
    <!-- Student -->
    <div class="col-md-4">
        <div class="bg-white border rounded-3 p-3 h-100">
            <small class="text-secondary text-uppercase">Student</small>
            <div class="fw-semibold mt-1"><?= e($tenancy['student_name']) ?></div>
            <div class="small text-secondary"><code><?= e($tenancy['student_matric']) ?></code></div>
            <div class="small text-secondary mt-2">
                <div><i class="bi bi-envelope"></i> <?= e($tenancy['student_email']) ?></div>
                <div><i class="bi bi-telephone"></i> <?= e($tenancy['student_phone']) ?></div>
            </div>
            <a href="/rentbridge/admin/user.php?id=<?= (int)$tenancy['student_id'] ?>"
               class="btn btn-sm btn-outline-dark w-100 mt-2">View profile</a>
        </div>
    </div>

    <!-- Landlord -->
    <div class="col-md-4">
        <div class="bg-white border rounded-3 p-3 h-100">
            <small class="text-secondary text-uppercase">Landlord</small>
            <div class="fw-semibold mt-1"><?= e($tenancy['landlord_name']) ?></div>
            <div class="small text-secondary mt-2">
                <div><i class="bi bi-envelope"></i> <?= e($tenancy['landlord_email']) ?></div>
                <div><i class="bi bi-telephone"></i> <?= e($tenancy['landlord_phone']) ?></div>
            </div>
            <a href="/rentbridge/admin/user.php?id=<?= (int)$tenancy['landlord_id'] ?>"
               class="btn btn-sm btn-outline-dark w-100 mt-2">View profile</a>
        </div>
    </div>

    <!-- Agent -->
    <div class="col-md-4">
        <div class="bg-white border rounded-3 p-3 h-100">
            <small class="text-secondary text-uppercase">Agent</small>
            <?php if (!empty($tenancy['agent_name'])): ?>
                <div class="fw-semibold mt-1"><?= e($tenancy['agent_name']) ?></div>
                <div class="small text-secondary"><code><?= e($tenancy['agent_staff_id']) ?></code> · <?= e($tenancy['agent_department']) ?></div>
                <div class="small text-secondary mt-2">
                    <div><i class="bi bi-envelope"></i> <?= e($tenancy['agent_email']) ?></div>
                </div>
                <a href="/rentbridge/admin/user.php?id=<?= (int)$tenancy['agent_id'] ?>"
                   class="btn btn-sm btn-outline-dark w-100 mt-2">View profile</a>
            <?php else: ?>
                <div class="text-secondary small mt-2">No agent assigned yet</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- TENANCY DETAILS -->
<div class="bg-white border rounded-3 p-4 mb-4">
    <h5 class="mb-3"><i class="bi bi-calendar-range me-2"></i>Tenancy details</h5>
    <div class="row g-3">
        <div class="col-md-3 col-6">
            <small class="text-secondary text-uppercase">Start date</small>
            <div class="fw-semibold"><?= e(date('d M Y', strtotime($tenancy['start_date']))) ?></div>
        </div>
        <div class="col-md-3 col-6">
            <small class="text-secondary text-uppercase">End date</small>
            <div class="fw-semibold"><?= e(date('d M Y', strtotime($tenancy['end_date']))) ?></div>
        </div>
        <div class="col-md-3 col-6">
            <small class="text-secondary text-uppercase">Monthly rent</small>
            <div class="fw-semibold">RM <?= number_format((float)$tenancy['monthly_rent']) ?></div>
        </div>
        <div class="col-md-3 col-6">
            <small class="text-secondary text-uppercase">Deposit</small>
            <div class="fw-semibold">RM <?= number_format((float)$tenancy['deposit']) ?></div>
        </div>
    </div>

    <?php if (!empty($tenancy['student_note'])): ?>
        <hr>
        <small class="text-secondary text-uppercase">Student's note</small>
        <p style="white-space:pre-line;" class="mb-0 small"><?= e($tenancy['student_note']) ?></p>
    <?php endif; ?>

    <?php if (!empty($tenancy['cancellation_reason'])): ?>
        <hr>
        <small class="text-secondary text-uppercase">Cancellation reason</small>
        <p style="white-space:pre-line;" class="mb-0 small text-danger"><?= e($tenancy['cancellation_reason']) ?></p>
    <?php endif; ?>
</div>

<!-- INSPECTION REPORT -->
<?php if ($tenancy['verification_id']): ?>
<div class="bg-white border rounded-3 p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-clipboard-check me-2"></i>Inspection report</h5>
        <a href="/rentbridge/agent/inspection_view.php?id=<?= (int)$tenancy['verification_id'] ?>"
           class="btn btn-sm btn-outline-dark">
            View full report <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>
    <p class="text-secondary small mt-2 mb-0">
        Outcome: <strong><?= e(ucfirst(str_replace('_', ' ', $tenancy['verification_outcome']))) ?></strong>
    </p>
</div>
<?php endif; ?>

<!-- CONTRACT CARD (simpler version per spec) -->
<div class="bg-white border rounded-3 p-4 mb-4">
    <h5 class="mb-3"><i class="bi bi-file-earmark-text me-2"></i>Contract</h5>

    <?php if (empty($tenancy['contract_id'])): ?>
        <p class="text-secondary mb-0">
            Contract not yet generated.
            <small>(Will be created automatically when inspection passes.)</small>
        </p>
    <?php else: ?>
        <div class="row g-3 align-items-center">
            <div class="col-md-3">
                <small class="text-secondary text-uppercase">Contract code</small>
                <div class="fw-semibold"><code><?= e($tenancy['contract_code']) ?></code></div>
            </div>
            <div class="col-md-3">
                <small class="text-secondary text-uppercase">Status</small>
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
            <div class="col-md-3">
                <small class="text-secondary text-uppercase">Sign progress</small>
                <div class="fw-semibold">
                    <?= $signed ?>/3 signatures
                    <?php if ($signed === 3): ?> ✓<?php endif; ?>
                </div>
            </div>
            <div class="col-md-3 text-md-end">
                <?php if (!empty($tenancy['contract_pdf_path'])): ?>
                    <a href="/rentbridge/<?= e($tenancy['contract_pdf_path']) ?>?v=<?= filemtime(__DIR__ . '/../' . $tenancy['contract_pdf_path']) ?>"
                       target="_blank" class="btn btn-sm btn-outline-dark">
                        <i class="bi bi-file-pdf me-1"></i> Download PDF
                    </a>
                <?php endif; ?>
                <a href="/rentbridge/contracts/view.php?id=<?= (int)$tenancy['contract_id'] ?>"
                   class="btn btn-sm btn-outline-dark">
                    View contract <i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>
        </div>

        <hr>

        <div class="row g-2 small">
            <div class="col-md-4">
                <span class="text-secondary">Student:</span>
                <?php if (!empty($tenancy['student_signed_at'])): ?>
                    <span class="text-success">✓ <?= e(date('d M, H:i', strtotime($tenancy['student_signed_at']))) ?></span>
                <?php else: ?>
                    <span class="text-secondary">— not signed</span>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <span class="text-secondary">Landlord:</span>
                <?php if (!empty($tenancy['landlord_signed_at'])): ?>
                    <span class="text-success">✓ <?= e(date('d M, H:i', strtotime($tenancy['landlord_signed_at']))) ?></span>
                <?php else: ?>
                    <span class="text-secondary">— not signed</span>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <span class="text-secondary">Agent:</span>
                <?php if (!empty($tenancy['agent_signed_at'])): ?>
                    <span class="text-success">✓ <?= e(date('d M, H:i', strtotime($tenancy['agent_signed_at']))) ?></span>
                <?php else: ?>
                    <span class="text-secondary">— not signed</span>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- ADMIN ACTIONS -->
<?php if (!in_array($tenancy['status'], ['completed','cancelled_by_student','cancelled_by_landlord','cancelled_by_admin'], true)): ?>
<div class="bg-white border rounded-3 p-4 mb-4" style="border-left: 4px solid #DC3545 !important;">
    <h5 class="mb-3"><i class="bi bi-shield-exclamation me-2"></i>Admin actions</h5>
    <p class="text-secondary small mb-3">Cancellation by admin will release the property and notify all parties. Use sparingly.</p>

    <button type="button" class="btn btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#cancelForm">
        <i class="bi bi-x-circle me-1"></i> Cancel this tenancy…
    </button>

    <div class="collapse mt-3" id="cancelForm">
        <form method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="admin_cancel">
            <label class="form-label small fw-semibold">Reason (will be sent to both parties)</label>
            <textarea name="cancel_reason" rows="3" class="form-control mb-2" required
                      placeholder="Explain why this tenancy is being cancelled"></textarea>
            <button type="submit" class="btn btn-danger btn-sm"
                    onclick="return confirm('Cancel this tenancy? This cannot be undone.');">
                Confirm cancellation
            </button>
        </form>
    </div>
</div>
<?php endif; ?>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/admin_layout.php';