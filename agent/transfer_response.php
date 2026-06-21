<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/transfers.php';
require_role('agent');

$pdo    = db();
$userId = current_user_id();

$transferId = (int)($_GET['id'] ?? $_POST['transfer_id'] ?? 0);

if ($transferId <= 0) {
    set_flash('danger', 'Invalid transfer request.');
    header('Location: /rentbridge/agent/cases.php');
    exit;
}

// Load transfer + verify this agent was notified
$stmt = $pdo->prepare("
    SELECT atr.*, p.title AS property_title, p.city, p.monthly_rent,
           u.full_name AS requesting_agent_name,
           atn.outcome AS my_outcome, atn.id AS notif_id
      FROM agent_transfer_requests atr
      JOIN properties p ON p.id = atr.property_id
      JOIN users u ON u.id = atr.requesting_agent_id
      JOIN agent_transfer_notifications atn
        ON atn.transfer_request_id = atr.id AND atn.agent_id = ?
     WHERE atr.id = ?
     LIMIT 1
");
$stmt->execute([$userId, $transferId]);
$transfer = $stmt->fetch();

if (!$transfer) {
    set_flash('danger', 'Transfer offer not found or not addressed to you.');
    header('Location: /rentbridge/agent/cases.php');
    exit;
}

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($transfer['my_outcome'] !== 'pending') {
        set_flash('info', 'You have already responded to this transfer.');
        header('Location: /rentbridge/agent/cases.php');
        exit;
    }

    if ($transfer['status'] !== 'finding_agent') {
        set_flash('info', 'This transfer is no longer active.');
        header('Location: /rentbridge/agent/cases.php');
        exit;
    }

    if ($action === 'accept') {
        $ok = complete_transfer($transferId, $userId);
        if ($ok) {
            set_flash('success', 'You have accepted the property case transfer.');
        } else {
            set_flash('danger', 'Something went wrong. Please try again or contact admin.');
        }
        header('Location: /rentbridge/agent/cases.php?tab=properties');
        exit;
    }

    if ($action === 'decline') {
        decline_transfer_notification($transferId, $userId);
        set_flash('info', 'You declined the transfer. The system will offer it to the next agent.');
        header('Location: /rentbridge/agent/cases.php');
        exit;
    }
}

// Fetch active bookings for context
$stmt = $pdo->prepare("
    SELECT b.id, b.status, s.full_name AS student_name
      FROM bookings b
      JOIN students s ON s.user_id = b.student_id
     WHERE b.property_id = ? AND b.agent_id = ?
       AND b.status IN ('pending_agent','agent_assigned','agent_verifying','agent_verified','contract_pending','active')
");
$stmt->execute([$transfer['property_id'], $transfer['requesting_agent_id']]);
$bookings = $stmt->fetchAll();

$pageTitle = 'Transfer Offer';
$activeNav = 'cases';

ob_start();
?>

<p class="text-secondary mb-4">
    You have been offered to take over the following property case.
</p>

<div class="row g-4">

    <div class="col-12">
        <div class="bg-white border rounded-3 p-4" style="border-left:4px solid #C9923F !important;">
            <h6 class="text-secondary text-uppercase small mb-2">Property</h6>
            <h4 class="mb-1"><?= e($transfer['property_title']) ?></h4>
            <div class="text-secondary small"><?= e($transfer['city']) ?> · RM <?= number_format((float)$transfer['monthly_rent']) ?>/mo</div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="bg-white border rounded-3 p-4 h-100">
            <h6 class="text-secondary text-uppercase small mb-2">Transferring agent</h6>
            <strong><?= e($transfer['requesting_agent_name']) ?></strong>
            <p class="small text-secondary mt-2 mb-0">
                <strong>Reason for transfer:</strong><br>
                <?= e($transfer['reason']) ?>
            </p>
        </div>
    </div>

    <?php if (!empty($bookings)): ?>
    <div class="col-md-6">
        <div class="bg-white border rounded-3 p-4 h-100">
            <h6 class="text-secondary text-uppercase small mb-2">
                Active bookings you will inherit (<?= count($bookings) ?>)
            </h6>
            <ul class="list-unstyled mb-0 small">
                <?php foreach ($bookings as $b): ?>
                    <li class="mb-1">
                        <code>#<?= (int)$b['id'] ?></code>
                        <?= e($b['student_name']) ?>
                        <span class="text-secondary">— <?= e($b['status']) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($transfer['my_outcome'] !== 'pending'): ?>
    <div class="col-12">
        <div class="alert alert-secondary">
            You already responded: <strong><?= e($transfer['my_outcome']) ?></strong>.
            <a href="/rentbridge/agent/cases.php" class="btn btn-sm btn-outline-secondary ms-2">Back to cases</a>
        </div>
    </div>

    <?php elseif ($transfer['status'] !== 'finding_agent'): ?>
    <div class="col-12">
        <div class="alert alert-info">
            This transfer is no longer open (status: <?= e($transfer['status']) ?>).
            <a href="/rentbridge/agent/cases.php" class="btn btn-sm btn-outline-secondary ms-2">Back to cases</a>
        </div>
    </div>

    <?php else: ?>
    <div class="col-12">
        <div class="bg-white border rounded-3 p-4">
            <h6 class="text-secondary text-uppercase small mb-3">Your response</h6>
            <div class="alert alert-info small border-0" style="background:var(--rb-cream);">
                <i class="bi bi-info-circle"></i>
                Accepting means you take full responsibility for this property and all its bookings
                from this point on. The first agent to accept in this batch wins the case.
            </div>
            <form method="POST" class="d-flex gap-2">
                <?= csrf_field() ?>
                <input type="hidden" name="transfer_id" value="<?= (int)$transferId ?>">
                <button type="submit" name="action" value="accept" class="btn btn-success"
                        onclick="return confirm('Accept this property case transfer?');">
                    <i class="bi bi-check-circle me-1"></i> Accept transfer
                </button>
                <button type="submit" name="action" value="decline" class="btn btn-outline-secondary"
                        onclick="return confirm('Decline this transfer offer?');">
                    <i class="bi bi-x-circle me-1"></i> Decline
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/agent_layout.php';
