<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/transfers.php';
require_role('admin');

$pdo    = db();
$userId = current_user_id();

// --- HANDLE APPROVE / REJECT ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action     = $_POST['action'] ?? '';
    $transferId = (int)($_POST['transfer_id'] ?? 0);
    $adminNote  = trim($_POST['admin_note'] ?? '');

    if ($transferId > 0 && in_array($action, ['approve', 'reject'], true)) {
        $stmt = $pdo->prepare("SELECT * FROM agent_transfer_requests WHERE id = ? AND status = 'pending_admin' LIMIT 1");
        $stmt->execute([$transferId]);
        $req = $stmt->fetch();

        if ($req) {
            if ($action === 'approve') {
                $pdo->prepare("
                    UPDATE agent_transfer_requests
                       SET status = 'approved', admin_id = ?, admin_note = ?, admin_decided_at = NOW()
                     WHERE id = ?
                ")->execute([$userId, $adminNote ?: null, $transferId]);

                // Dispatch first batch
                dispatch_transfer_batch($transferId);

                // Notify requesting agent
                notify((int)$req['requesting_agent_id'], 'transfer_approved',
                    'Your transfer request has been approved',
                    'Admin approved your property transfer request. The system is now finding a replacement agent.',
                    '/rentbridge/agent/request_transfer.php'
                );

                set_flash('success', 'Transfer approved. First batch of agents notified.');
            } else {
                $pdo->prepare("
                    UPDATE agent_transfer_requests
                       SET status = 'rejected', admin_id = ?, admin_note = ?, admin_decided_at = NOW()
                     WHERE id = ?
                ")->execute([$userId, $adminNote ?: null, $transferId]);

                notify((int)$req['requesting_agent_id'], 'transfer_rejected',
                    'Your transfer request was not approved',
                    'Admin reviewed your transfer request and did not approve it.'
                        . ($adminNote ? ' Note: ' . $adminNote : ''),
                    '/rentbridge/agent/request_transfer.php'
                );

                set_flash('info', 'Transfer request rejected.');
            }
        }
    }

    header('Location: /rentbridge/admin/transfers.php');
    exit;
}

// --- LOAD REQUESTS ---
$filter = $_GET['filter'] ?? 'pending';
$validFilters = ['pending', 'active', 'all'];
if (!in_array($filter, $validFilters, true)) $filter = 'pending';

$statusGroups = [
    'pending' => ['pending_admin'],
    'active'  => ['approved', 'finding_agent'],
    'all'     => ['pending_admin', 'approved', 'rejected', 'finding_agent', 'completed'],
];

$ph = implode(',', array_fill(0, count($statusGroups[$filter]), '?'));
$stmt = $pdo->prepare("
    SELECT atr.*,
           p.title AS property_title, p.city,
           a.full_name AS requesting_agent_name,
           na.full_name AS new_agent_name,
           (SELECT COUNT(*) FROM agent_transfer_notifications atn2
             WHERE atn2.transfer_request_id = atr.id) AS total_notified,
           (SELECT COUNT(*) FROM agent_transfer_notifications atn3
             WHERE atn3.transfer_request_id = atr.id AND atn3.outcome = 'pending') AS pending_responses
      FROM agent_transfer_requests atr
      JOIN properties p ON p.id = atr.property_id
      JOIN agents a ON a.user_id = atr.requesting_agent_id
      LEFT JOIN agents na ON na.user_id = atr.new_agent_id
     WHERE atr.status IN ($ph)
     ORDER BY atr.created_at DESC
");
$stmt->execute($statusGroups[$filter]);
$requests = $stmt->fetchAll();

// Counts per filter
$counts = [];
foreach ($statusGroups as $key => $statuses) {
    $ph2 = implode(',', array_fill(0, count($statuses), '?'));
    $s = $pdo->prepare("SELECT COUNT(*) FROM agent_transfer_requests WHERE status IN ($ph2)");
    $s->execute($statuses);
    $counts[$key] = (int)$s->fetchColumn();
}

$pageTitle = 'Case Transfers';
$activeNav = 'transfers';

$pageTabs = [
    ['label' => 'Pending review',  'href' => '?filter=pending', 'active' => $filter === 'pending', 'count' => $counts['pending']],
    ['label' => 'In progress',     'href' => '?filter=active',  'active' => $filter === 'active',  'count' => $counts['active']],
    ['label' => 'All',             'href' => '?filter=all',     'active' => $filter === 'all',     'count' => $counts['all']],
];

ob_start();
?>

<?php $flash = get_flash(); if ($flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show">
        <?= e($flash['message']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (empty($requests)): ?>
<div class="text-center py-5 bg-white rounded-3 border">
    <i class="bi bi-arrow-left-right" style="font-size:3rem;color:rgba(15,44,82,0.15);"></i>
    <h4 class="mt-3">No transfer requests</h4>
    <p class="text-secondary small mb-0">No requests in this view.</p>
</div>

<?php else: ?>
<div class="bg-white border rounded-3 overflow-hidden">
    <table class="table mb-0 align-middle">
        <thead style="background:#F4F4EE;">
            <tr>
                <th class="ps-4">Property</th>
                <th>Requesting agent</th>
                <th>Reason</th>
                <th>Status</th>
                <th>Submitted</th>
                <th class="text-end pe-4"></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($requests as $r):
            [$badgeClass, $badgeLabel] = match($r['status']) {
                'pending_admin'  => ['warning text-dark', '⏳ Awaiting review'],
                'approved'       => ['info text-dark',    'Approved'],
                'finding_agent'  => ['primary',           '🔍 Finding agent (batch ' . $r['batch_number'] . ')'],
                'completed'      => ['success',           '✓ Completed'],
                'rejected'       => ['secondary',         'Rejected'],
                default          => ['secondary',          $r['status']],
            };
        ?>
        <tr>
            <td class="ps-4">
                <strong class="small"><?= e($r['property_title']) ?></strong>
                <div class="small text-secondary"><?= e($r['city']) ?></div>
            </td>
            <td class="small"><?= e($r['requesting_agent_name']) ?></td>
            <td class="small text-secondary" style="max-width:180px;">
                <?= e(mb_strimwidth($r['reason'], 0, 80, '…')) ?>
            </td>
            <td>
                <span class="badge bg-<?= $badgeClass ?>"><?= $badgeLabel ?></span>
                <?php if ($r['status'] === 'finding_agent'): ?>
                    <div class="small text-secondary mt-1">
                        <?= (int)$r['pending_responses'] ?> pending / <?= (int)$r['total_notified'] ?> notified
                    </div>
                <?php endif; ?>
                <?php if ($r['status'] === 'completed' && $r['new_agent_name']): ?>
                    <div class="small text-secondary mt-1">→ <?= e($r['new_agent_name']) ?></div>
                <?php endif; ?>
            </td>
            <td class="small text-secondary"><?= e(date('d M Y', strtotime($r['created_at']))) ?></td>
            <td class="text-end pe-4">
                <?php if ($r['status'] === 'pending_admin'): ?>
                    <button class="btn btn-sm btn-primary"
                            data-bs-toggle="modal"
                            data-bs-target="#reviewModal"
                            data-transfer-id="<?= (int)$r['id'] ?>"
                            data-property="<?= e($r['property_title']) ?>"
                            data-agent="<?= e($r['requesting_agent_name']) ?>"
                            data-reason="<?= e($r['reason']) ?>">
                        Review
                    </button>
                <?php else: ?>
                    <a href="?filter=all#req-<?= (int)$r['id'] ?>" class="btn btn-sm btn-outline-secondary">
                        Details
                    </a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header border-0">
                <h5 class="modal-title">Review transfer request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <div class="small text-secondary text-uppercase">Property</div>
                    <strong id="modalProperty"></strong>
                </div>
                <div class="mb-3">
                    <div class="small text-secondary text-uppercase">Requesting agent</div>
                    <strong id="modalAgent"></strong>
                </div>
                <div class="mb-4">
                    <div class="small text-secondary text-uppercase">Reason</div>
                    <p id="modalReason" class="mb-0"></p>
                </div>
                <div class="alert alert-info small">
                    <i class="bi bi-info-circle me-1"></i>
                    <strong>If you approve:</strong> the system will notify <?= TRANSFER_BATCH_SIZE ?> agents (FIFO by workload).
                    The first to accept takes over the property and all its active tenancies.
                </div>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="transfer_id" id="modalTransferId">
                <div class="modal-body pt-0">
                    <label class="form-label fw-semibold">Admin note <small class="text-secondary fw-normal">(optional)</small></label>
                    <textarea name="admin_note" rows="2" class="form-control"
                              placeholder="Optional note sent to the requesting agent…"></textarea>
                </div>
                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="action" value="reject" class="btn btn-outline-danger">
                        <i class="bi bi-x-circle me-1"></i> Reject
                    </button>
                    <button type="submit" name="action" value="approve" class="btn btn-success"
                            onclick="return confirm('Approve and dispatch first batch of agents?');">
                        <i class="bi bi-check-circle me-1"></i> Approve
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('reviewModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('modalTransferId').value = btn.dataset.transferId;
    document.getElementById('modalProperty').textContent = btn.dataset.property;
    document.getElementById('modalAgent').textContent   = btn.dataset.agent;
    document.getElementById('modalReason').textContent  = btn.dataset.reason;
});
</script>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/admin_layout.php';
