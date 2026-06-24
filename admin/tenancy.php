<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$tenancyId = (int)($_GET['id'] ?? 0);
if ($tenancyId <= 0) {
    http_response_code(400);
    die('Invalid tenancy ID.');
}

$pdo = db();

// Fetch tenancy + all parties + contract
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
           ua.full_name     AS uploaded_by_name
      FROM tenancies b
      JOIN properties p ON p.id = b.property_id
      JOIN users su ON su.id = b.student_id
      JOIN students s ON s.user_id = b.student_id
      JOIN users lu ON lu.id = b.landlord_id
      JOIN landlords l ON l.user_id = b.landlord_id
      LEFT JOIN users au ON au.id = b.agent_id
      LEFT JOIN agents a ON a.user_id = b.agent_id
      LEFT JOIN agents ua ON ua.user_id = b.signed_uploaded_by
     WHERE b.id = ?
     LIMIT 1
");
$stmt->execute([$tenancyId]);
$tenancy = $stmt->fetch();

if (!$tenancy) {
    http_response_code(404);
    die('Tenancy not found.');
}

// Fetch ALL tenants (primary + co_tenants)
$stmt = $pdo->prepare("
    SELECT id, is_primary, full_name, ic_number, phone, email, sign_order, status, signed_at
      FROM co_tenants
     WHERE tenancy_id = ?
       AND status != 'removed'
     ORDER BY sign_order ASC
");
$stmt->execute([$tenancyId]);
$tenants = $stmt->fetchAll();

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
                    UPDATE tenancies
                       SET status = 'cancelled_by_admin',
                           cancellation_reason = ?,
                           cancelled_by = ?
                     WHERE id = ?
                ");
                $stmt->execute([$reason, current_user_id(), $tenancyId]);

                // Release property
                $stmt = $pdo->prepare("UPDATE properties SET status = 'available' WHERE id = ?");
                $stmt->execute([(int)$tenancy['property_id']]);

                // Notify both parties
                notify((int)$tenancy['student_id'], 'admin_cancelled',
                    'Tenancy cancelled by admin',
                    'Your tenancy #' . $tenancyId . ' was cancelled. Reason: ' . $reason,
                    '/rentbridge/student/tenancies.php');
                notify((int)$tenancy['landlord_id'], 'admin_cancelled',
                    'Tenancy cancelled by admin',
                    'Tenancy #' . $tenancyId . ' was cancelled. Reason: ' . $reason,
                    '/rentbridge/landlord/tenancies.php');

                $pdo->commit();
                set_flash('warning', 'Tenancy cancelled and parties notified.');
                header('Location: /rentbridge/admin/tenancy.php?id=' . $tenancyId);
                exit;
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors['general'] = 'Failed: ' . $e->getMessage();
            }
        }
    }
}

// --- LAYOUT ---
$pageTitle = 'Tenancy #' . $tenancyId;
$activeNav = 'tenancies';

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

ob_start();
?>

<p class="small mb-3">
    <a href="/rentbridge/admin/tenancies.php" class="text-secondary text-decoration-none">
        <i class="bi bi-arrow-left"></i> Back to tenancies
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

<!-- ALL TENANTS CARD -->
<?php if (!empty($tenants)): ?>
<div class="bg-white border rounded-3 p-4 mb-4">
    <h5 class="mb-3">
        <i class="bi bi-people-fill me-2"></i>
        Tenants (<?= count($tenants) ?>)
    </h5>
    <table class="table table-sm mb-0">
        <thead style="background:#F4F4EE;">
            <tr>
                <th>#</th>
                <th>Name</th>
                <th>IC</th>
                <th>Phone</th>
                <th>Email</th>
                <th>Role</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tenants as $t): ?>
                <tr>
                    <td><?= (int)$t['sign_order'] ?></td>
                    <td>
                        <strong><?= e($t['full_name']) ?></strong>
                    </td>
                    <td><code class="small"><?= e($t['ic_number']) ?></code></td>
                    <td class="small"><?= e($t['phone'] ?: '—') ?></td>
                    <td class="small"><?= e($t['email'] ?: '—') ?></td>
                    <td>
                        <?php if ((int)$t['is_primary'] === 1): ?>
                            <span class="badge bg-primary">Primary</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">Co-tenant</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- CONTRACT CARD (print-upload model) -->
<div class="bg-white border rounded-3 p-4 mb-4">
    <h5 class="mb-3"><i class="bi bi-file-earmark-text me-2"></i>Contract</h5>

    <?php if (empty($tenancy['signed_contract_path']) && $tenancy['status'] !== 'contract_pending'): ?>
        <p class="text-secondary mb-0">
            Contract not yet generated.
            <small>(Will be available once tenant info is submitted.)</small>
        </p>

    <?php elseif (empty($tenancy['signed_contract_path']) && $tenancy['status'] === 'contract_pending'): ?>
        <div class="alert alert-warning mb-0">
            <i class="bi bi-hourglass-split me-1"></i>
            <strong>Awaiting signatures.</strong>
            Contract has been generated. Parties need to wet-sign offline,
            then the agent uploads the signed PDF.
        </div>

    <?php else: ?>
        <!-- Signed contract on file -->
        <div class="row g-3">
            <div class="col-md-4">
                <small class="text-secondary text-uppercase">Status</small>
                <div><span class="badge bg-success">✓ Signed &amp; Active</span></div>
            </div>
            <div class="col-md-4">
                <small class="text-secondary text-uppercase">Signed uploaded</small>
                <div class="fw-semibold">
                    <?= e(date('d M Y, H:i', strtotime($tenancy['signed_uploaded_at']))) ?>
                </div>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="/rentbridge/<?= e($tenancy['signed_contract_path']) ?>"
                   target="_blank" class="btn btn-sm btn-outline-dark">
                    <i class="bi bi-file-pdf me-1"></i> View signed PDF
                </a>
            </div>
        </div>

        <hr>

        <div class="row g-3 small">
            <div class="col-md-6">
                <span class="text-secondary">Uploaded by agent:</span><br>
                <strong><?= e($tenancy['uploaded_by_name'] ?? '—') ?></strong>
                <code class="text-secondary">(Agent ID: <?= (int)$tenancy['signed_uploaded_by'] ?>)</code>
            </div>
            <div class="col-md-6">
                <span class="text-secondary">All tenants signed:</span><br>
                <?php
                $totalT = count($tenants);
                $signedT = 0;
                foreach ($tenants as $t) {
                    if ($t['status'] === 'signed') $signedT++;
                }
                ?>
                <strong><?= $signedT ?>/<?= $totalT ?></strong>
                <?php if ($signedT === $totalT && $totalT > 0): ?>
                    <i class="bi bi-check-circle-fill text-success"></i>
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