<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/agent_assignment.php';
require_role('agent');

$pdo = db();
$agentId = current_user_id();
$propertyId = (int)($_GET['id'] ?? 0);

if ($propertyId <= 0) {
    die('Property not specified.');
}

// Handle POST (accept/reject)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'accept') {
        $result = agent_accept_property($propertyId, $agentId);
        set_flash($result['ok'] ? 'success' : 'danger',
                  $result['ok'] ? 'Property approved and is now live.'
                                : ('Failed: ' . $result['error']));
        header('Location: /rentbridge/agent/property_review.php?id=' . $propertyId);
        exit;
    } elseif ($action === 'reject') {
        $reason = trim($_POST['reason'] ?? '');
        if ($reason === '') {
            set_flash('warning', 'Please provide a reason for rejection.');
        } else {
            $result = agent_reject_property($propertyId, $agentId, $reason);
            if ($result['ok']) {
                set_flash('info', $result['no_agents_left']
                    ? 'Rejected. No more agents available — escalated to admin.'
                    : 'Rejected. Reassigning to next agent.');
            } else {
                set_flash('danger', 'Failed: ' . ($result['error'] ?? 'Unknown error'));
            }
            header('Location: /rentbridge/agent/dashboard.php');
            exit;
        }
    }
}

// Fetch property + landlord
$stmt = $pdo->prepare("
    SELECT p.*,
           l.full_name AS landlord_name,
           l.ic_no AS landlord_ic,
           l.phone AS landlord_phone,
           l.verified AS landlord_verified,
           u.email AS landlord_email
      FROM properties p
      JOIN landlords l ON l.user_id = p.landlord_id
      JOIN users u ON u.id = p.landlord_id
     WHERE p.id = ?
");
$stmt->execute([$propertyId]);
$prop = $stmt->fetch();

if (!$prop) {
    die('Property not found.');
}

// Authorization: only the currently assigned agent (or already accepted) can view
if ((int)$prop['assigned_agent_id'] !== $agentId && $prop['agent_status'] !== 'accepted') {
    die('You are not assigned to this property.');
}

// Photos
$stmt = $pdo->prepare("SELECT image_path FROM property_images WHERE property_id = ? ORDER BY is_primary DESC, id ASC");
$stmt->execute([$propertyId]);
$photos = $stmt->fetchAll();

// Documents
$stmt = $pdo->prepare("SELECT * FROM property_documents WHERE property_id = ? ORDER BY id ASC");
$stmt->execute([$propertyId]);
$docs = $stmt->fetchAll();

$pageTitle = 'Property Review';
$activeNav = 'review';

ob_start();
?>

<a href="/rentbridge/agent/dashboard.php" class="small text-secondary text-decoration-none mb-3 d-inline-block">
    <i class="bi bi-arrow-left"></i> Back to dashboard
</a>

<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h1 style="font-family:'Fraunces',serif;"><?= e($prop['title']) ?></h1>
        <p class="text-secondary mb-0">
            <i class="bi bi-geo-alt"></i>
            <?= e($prop['address']) ?>, <?= e($prop['city']) ?>
        </p>
    </div>
    <?php
    $statusBadge = match($prop['agent_status']) {
        'pending'  => 'bg-warning text-dark',
        'accepted' => 'bg-success',
        'rejected' => 'bg-danger',
        'timeout'  => 'bg-secondary',
        default    => 'bg-light text-dark',
    };
    ?>
    <span class="badge <?= $statusBadge ?> fs-6"><?= e($prop['agent_status'] ?? 'unknown') ?></span>
</div>

<div class="row g-4">
    <!-- LEFT: details -->
    <div class="col-lg-8">

        <!-- Photos -->
        <?php if (!empty($photos)): ?>
            <div class="bg-white border rounded-3 p-4 mb-3">
                <h6 class="text-secondary text-uppercase small mb-3">Photos</h6>
                <div class="row g-2">
                    <?php foreach ($photos as $img): ?>
                        <div class="col-md-4 col-6">
                            <img src="/rentbridge/<?= e($img['image_path']) ?>"
                                 class="w-100 rounded" style="aspect-ratio:1; object-fit:cover;">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Property details -->
        <div class="bg-white border rounded-3 p-4 mb-3">
            <h6 class="text-secondary text-uppercase small mb-3">Property details</h6>
            <table class="table table-sm mb-0">
                <tr><th style="width:180px;">Type</th><td><?= e($prop['property_type']) ?></td></tr>
                <tr><th>Furnishing</th><td><?= e($prop['furnishing']) ?></td></tr>
                <tr><th>Monthly rent</th><td>RM <?= number_format((float)$prop['monthly_rent']) ?></td></tr>
                <tr><th>Deposit</th><td>RM <?= number_format((float)$prop['deposit']) ?></td></tr>
                <tr><th>Address</th><td><?= e($prop['address']) ?>, <?= e($prop['city']) ?> <?= e($prop['postcode']) ?>, <?= e($prop['state']) ?></td></tr>
                <?php if (!empty($prop['maps_url'])): ?>
                <tr><th>Maps</th><td><a href="<?= e($prop['maps_url']) ?>" target="_blank">Open on Google Maps <i class="bi bi-box-arrow-up-right"></i></a></td></tr>
                <?php endif; ?>
                <tr><th>Facilities</th><td><?= e($prop['facilities'] ?? '—') ?></td></tr>
                <?php if (!empty($prop['description'])): ?>
                <tr><th>Description</th><td><?= nl2br(e($prop['description'])) ?></td></tr>
                <?php endif; ?>
            </table>
        </div>

        <!-- Documents -->
        <div class="bg-white border rounded-3 p-4 mb-3">
            <h6 class="text-secondary text-uppercase small mb-3">Ownership documents</h6>
            <?php if (empty($docs)): ?>
                <p class="text-secondary mb-0">No documents uploaded.</p>
            <?php else: ?>
                <ul class="list-unstyled mb-0">
                    <?php foreach ($docs as $doc): ?>
                        <li class="mb-2">
                            <a href="/rentbridge/<?= e($doc['file_path']) ?>" target="_blank">
                                <i class="bi bi-file-earmark-text"></i>
                                <?= e($doc['document_type'] ?? 'Document') ?>
                            </a>
                            <?php if (!empty($doc['notes'])): ?>
                                <small class="text-secondary"> — <?= e($doc['notes']) ?></small>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- RIGHT: actions -->
    <div class="col-lg-4">
        <div class="bg-white border rounded-3 p-4 sticky-top" style="top:80px;">

            <h6 class="text-secondary text-uppercase small mb-3">Landlord</h6>
            <p class="mb-1"><strong><?= e($prop['landlord_name']) ?></strong>
                <?php if ((int)$prop['landlord_verified'] === 1): ?>
                    <i class="bi bi-patch-check-fill text-success"></i>
                <?php endif; ?>
            </p>
            <p class="small text-secondary mb-1"><code><?= e($prop['landlord_ic']) ?></code></p>
            <p class="small text-secondary mb-1"><i class="bi bi-telephone"></i> <?= e($prop['landlord_phone']) ?></p>
            <p class="small text-secondary mb-3"><i class="bi bi-envelope"></i> <?= e($prop['landlord_email']) ?></p>

            <hr>

            <?php if ($prop['agent_status'] === 'pending'): ?>
                <h6 class="text-secondary text-uppercase small mb-3">Decision</h6>
                <form method="POST" class="mb-2">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="accept">
                    <button type="submit" class="btn btn-success w-100"
                            onclick="return confirm('Approve this property as verified?');">
                        <i class="bi bi-check-circle me-1"></i> Accept & Approve
                    </button>
                </form>

                <button type="button" class="btn btn-outline-danger w-100"
                        data-bs-toggle="modal" data-bs-target="#rejectModal">
                    <i class="bi bi-x-circle me-1"></i> Reject
                </button>

                <p class="text-secondary small text-center mt-3 mb-0">
                    <i class="bi bi-info-circle"></i>
                    Rejecting will reassign this property to the next available agent.
                </p>
            <?php elseif ($prop['agent_status'] === 'accepted'): ?>
                <div class="alert alert-success small mb-0">
                    <i class="bi bi-check-circle-fill"></i>
                    You approved this property. It's now live.
                </div>
            <?php else: ?>
                <p class="text-secondary small">This assignment is no longer active.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Reject modal -->
<div class="modal fade" id="rejectModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form method="POST" class="modal-content">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="reject">
            <div class="modal-header">
                <h5 class="modal-title">Reject this property</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-secondary small">
                    Provide a clear reason. The next agent will see this. Use this if the
                    documents look fake, the photos don't match the address, you're unable
                    to inspect in your area, etc.
                </p>
                <textarea name="reason" class="form-control" rows="4" required
                          placeholder="e.g. Property is outside my coverage area in Melaka Tengah."></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-danger">Submit rejection</button>
            </div>
        </form>
    </div>
</div>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/agent_layout.php';