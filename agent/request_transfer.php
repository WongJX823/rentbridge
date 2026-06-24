<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/transfers.php';
require_role('agent');

$pdo    = db();
$userId = current_user_id();

// Properties this agent is currently responsible for
$stmt = $pdo->prepare("
    SELECT p.id, p.title, p.city, p.status,
           COUNT(b.id) AS active_tenancies
      FROM properties p
      LEFT JOIN tenancies b ON b.property_id = p.id
             AND b.agent_id = ?
             AND b.status IN ('pending_agent','agent_assigned','agent_verifying','agent_verified','contract_pending','active')
     WHERE p.assigned_agent_id = ?
     GROUP BY p.id
     ORDER BY p.title ASC
");
$stmt->execute([$userId, $userId]);
$myProperties = $stmt->fetchAll();

// Pending transfer requests from this agent
$stmt = $pdo->prepare("
    SELECT atr.*, p.title AS property_title
      FROM agent_transfer_requests atr
      JOIN properties p ON p.id = atr.property_id
     WHERE atr.requesting_agent_id = ?
     ORDER BY atr.created_at DESC
");
$stmt->execute([$userId]);
$myRequests = $stmt->fetchAll();

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $propertyId = (int)($_POST['property_id'] ?? 0);
    $reason     = trim($_POST['reason'] ?? '');

    if ($propertyId <= 0) {
        $errors['property_id'] = 'Please select a property.';
    } else {
        // Verify this agent owns this property
        $chk = $pdo->prepare("SELECT id FROM properties WHERE id = ? AND assigned_agent_id = ? LIMIT 1");
        $chk->execute([$propertyId, $userId]);
        if (!$chk->fetch()) {
            $errors['property_id'] = 'Invalid property selection.';
        }
    }

    if ($reason === '') {
        $errors['reason'] = 'Please explain why you need to transfer this property.';
    }

    // Check no open request already exists for this property
    if (empty($errors)) {
        $chk = $pdo->prepare("
            SELECT id FROM agent_transfer_requests
             WHERE property_id = ? AND requesting_agent_id = ?
               AND status IN ('pending_admin','approved','finding_agent')
             LIMIT 1
        ");
        $chk->execute([$propertyId, $userId]);
        if ($chk->fetch()) {
            $errors['property_id'] = 'You already have an open transfer request for this property.';
        }
    }

    if (empty($errors)) {
        $pdo->prepare("
            INSERT INTO agent_transfer_requests (property_id, requesting_agent_id, reason)
            VALUES (?, ?, ?)
        ")->execute([$propertyId, $userId, $reason]);

        $requestId = (int)$pdo->lastInsertId();

        // Notify admins
        $stmt = $pdo->query("SELECT id FROM users WHERE primary_role = 'admin'");
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $adminId) {
            notify((int)$adminId, 'transfer_request',
                'Agent requested property case transfer',
                current_user_display_name() . ' has requested to transfer a property case. Review and approve/reject.',
                '/rentbridge/admin/transfers.php?id=' . $requestId
            );
        }

        set_flash('success', 'Transfer request submitted. Admin will review shortly.');
        header('Location: /rentbridge/agent/request_transfer.php');
        exit;
    }
}

$pageTitle = 'Request Case Transfer';
$activeNav = 'cases';

ob_start();
?>

<p class="text-secondary mb-4">
    Request to hand off one of your assigned properties to another agent.
    An admin must approve the request before the system finds a replacement.
</p>

<?php if (!empty($myRequests)): ?>
<div class="bg-white border rounded-3 overflow-hidden mb-4">
    <div class="px-4 pt-4 pb-2">
        <h5 class="mb-1">Your transfer requests</h5>
    </div>
    <table class="table mb-0 align-middle">
        <thead style="background:#F4F4EE;">
            <tr>
                <th class="ps-4">Property</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Submitted</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($myRequests as $r):
            [$badgeClass, $badgeLabel] = match($r['status']) {
                'pending_admin'  => ['warning text-dark', '⏳ Awaiting admin'],
                'approved'       => ['info text-dark',    '✓ Approved'],
                'finding_agent'  => ['primary',           '🔍 Finding agent'],
                'completed'      => ['success',           '✓ Completed'],
                'rejected'       => ['danger',            '✗ Rejected'],
                default          => ['secondary',          $r['status']],
            };
        ?>
            <tr>
                <td class="ps-4 small fw-semibold"><?= e($r['property_title']) ?></td>
                <td class="small text-secondary" style="max-width:200px;">
                    <?= e(mb_strimwidth($r['reason'], 0, 80, '…')) ?>
                </td>
                <td><span class="badge bg-<?= $badgeClass ?>"><?= $badgeLabel ?></span></td>
                <td class="small text-secondary"><?= e(date('d M Y', strtotime($r['created_at']))) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if (empty($myProperties)): ?>
    <div class="text-center py-5 bg-white rounded-3 border">
        <i class="bi bi-house-x" style="font-size:3rem;color:rgba(15,44,82,0.15);"></i>
        <h5 class="mt-3">No properties assigned to you</h5>
        <p class="text-secondary small mb-0">You have no properties to transfer.</p>
    </div>
<?php else: ?>
<div class="bg-white border rounded-3 p-4">
    <h5 class="mb-1">Submit a transfer request</h5>
    <p class="small text-secondary mb-4">
        Once submitted, admin reviews your request. If approved, the system will notify agents
        in batches of <?= TRANSFER_BATCH_SIZE ?> (FIFO by workload) until one accepts.
    </p>

    <form method="POST">
        <?= csrf_field() ?>

        <div class="mb-3">
            <label class="form-label fw-semibold">Property <span class="text-danger">*</span></label>
            <select name="property_id" class="form-select <?= isset($errors['property_id']) ? 'is-invalid' : '' ?>" required>
                <option value="">— select property —</option>
                <?php foreach ($myProperties as $p): ?>
                    <option value="<?= (int)$p['id'] ?>"
                        <?= ((int)($_POST['property_id'] ?? 0) === (int)$p['id']) ? 'selected' : '' ?>>
                        <?= e($p['title']) ?> (<?= e($p['city']) ?>)
                        <?php if ($p['active_tenancies'] > 0): ?>
                            — <?= (int)$p['active_tenancies'] ?> active tenancy(s)
                        <?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <?php if (isset($errors['property_id'])): ?>
                <div class="invalid-feedback"><?= e($errors['property_id']) ?></div>
            <?php endif; ?>
        </div>

        <div class="mb-4">
            <label class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
            <textarea name="reason" rows="4"
                      class="form-control <?= isset($errors['reason']) ? 'is-invalid' : '' ?>"
                      placeholder="e.g. Schedule conflict, long-term leave, conflict of interest with landlord…"
                      required><?= e($_POST['reason'] ?? '') ?></textarea>
            <?php if (isset($errors['reason'])): ?>
                <div class="invalid-feedback"><?= e($errors['reason']) ?></div>
            <?php endif; ?>
        </div>

        <div class="alert alert-warning small">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <strong>Note:</strong> Once transferred, you will lose access to this property and all
            its tenancies. This cannot be undone without admin intervention.
        </div>

        <button type="submit" class="btn btn-primary"
                onclick="return confirm('Submit transfer request to admin?');">
            <i class="bi bi-arrow-left-right me-1"></i> Submit transfer request
        </button>
    </form>
</div>
<?php endif; ?>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/agent_layout.php';
