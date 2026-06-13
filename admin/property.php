<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$propertyId = (int)($_GET['id'] ?? 0);
if ($propertyId <= 0) {
    http_response_code(400);
    die('Invalid property ID.');
}

$pdo = db();

// Fetch property + landlord + verification info
$stmt = $pdo->prepare("
    SELECT p.*,
           l.full_name      AS landlord_name,
           l.preferred_name AS landlord_nickname,
           l.ic_no          AS landlord_ic,
           l.phone          AS landlord_phone,
           u.email          AS landlord_email,
           u.id             AS landlord_user_id,
           va.full_name     AS verifier_name
      FROM properties p
      JOIN users u    ON u.id = p.landlord_id
      JOIN landlords l ON l.user_id = p.landlord_id
      LEFT JOIN agents va ON va.user_id = p.agent_verified_by
     WHERE p.id = ?
     LIMIT 1
");
$stmt->execute([$propertyId]);
$property = $stmt->fetch();

if (!$property) {
    http_response_code(404);
    die('Property not found.');
}

// Fetch images
$stmt = $pdo->prepare("
    SELECT id, image_path, is_primary
      FROM property_images
     WHERE property_id = ?
     ORDER BY is_primary DESC, id ASC
");
$stmt->execute([$propertyId]);
$images = $stmt->fetchAll();

// Fetch tenancies on this property
$stmt = $pdo->prepare("
    SELECT b.id, b.status, b.start_date, b.end_date, b.created_at,
           s.full_name AS student_name,
           s.matric_no,
           a.full_name AS agent_name,
           c.id        AS contract_id,
           c.contract_code,
           c.status    AS contract_status
      FROM bookings b
      JOIN students s ON s.user_id = b.student_id
      LEFT JOIN agents a ON a.user_id = b.agent_id
      LEFT JOIN contracts c ON c.booking_id = b.id
     WHERE b.property_id = ?
     ORDER BY b.created_at DESC
");
$stmt->execute([$propertyId]);
$tenancies = $stmt->fetchAll();

// --- HANDLE ADMIN ACTIONS ---
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'approve' && $property['status'] === 'pending_approval') {
            $stmt = $pdo->prepare("UPDATE properties SET status = 'available' WHERE id = ?");
            $stmt->execute([$propertyId]);

            notify(
                (int)$property['landlord_id'],
                'property_approved',
                'Property listing approved',
                'Your property "' . $property['title'] . '" is now live on RentBridge.',
                '/rentbridge/landlord/properties.php'
            );

            set_flash('success', 'Property approved and now visible to students.');
            header('Location: /rentbridge/admin/property.php?id=' . $propertyId);
            exit;
        }

        if ($action === 'reject' && $property['status'] === 'pending_approval') {
            $reason = trim($_POST['reject_reason'] ?? '');
            if ($reason === '') {
                $errors['general'] = 'Please provide a rejection reason.';
            } else {
                $stmt = $pdo->prepare("UPDATE properties SET status = 'rejected' WHERE id = ?");
                $stmt->execute([$propertyId]);

                notify(
                    (int)$property['landlord_id'],
                    'property_rejected',
                    'Property listing rejected',
                    'Your property "' . $property['title'] . '" was not approved. Reason: ' . $reason,
                    '/rentbridge/landlord/properties.php'
                );

                set_flash('warning', 'Property rejected. Landlord notified.');
                header('Location: /rentbridge/admin/property.php?id=' . $propertyId);
                exit;
            }
        }

        if ($action === 'hide' && in_array($property['status'], ['available','booked','rented'], true)) {
            $stmt = $pdo->prepare("UPDATE properties SET status = 'hidden' WHERE id = ?");
            $stmt->execute([$propertyId]);
            set_flash('info', 'Property hidden from listings.');
            header('Location: /rentbridge/admin/property.php?id=' . $propertyId);
            exit;
        }

        if ($action === 'unhide' && $property['status'] === 'hidden') {
            $stmt = $pdo->prepare("UPDATE properties SET status = 'available' WHERE id = ?");
            $stmt->execute([$propertyId]);
            set_flash('success', 'Property is visible again.');
            header('Location: /rentbridge/admin/property.php?id=' . $propertyId);
            exit;
        }
    } catch (Throwable $e) {
        $errors['general'] = 'Action failed: ' . $e->getMessage();
    }
}

// --- LAYOUT ---
$pageTitle = 'Property #' . $propertyId;
$activeNav = 'properties';

// Helper
function prop_status_badge(string $status): array {
    return match ($status) {
        'pending_approval' => ['Pending review', 'warning'],
        'available'        => ['Available',      'success'],
        'booked'           => ['Booked',         'info'],
        'rented'           => ['Rented',         'primary'],
        'hidden'           => ['Hidden',         'secondary'],
        'rejected'         => ['Rejected',       'danger'],
        default            => [$status,          'secondary'],
    };
}
function tenancy_status_label(string $status): array {
    return match ($status) {
        'pending_landlord'    => ['Pending landlord',   'warning'],
        'pending_agent'       => ['Pending agent',      'warning'],
        'agent_verifying'     => ['🔍 Inspecting',      'info'],
        'agent_verified'      => ['✓ Verified',         'success'],
        'verification_failed' => ['Inspection failed',  'danger'],
        'contract_pending'    => ['📝 Contract signing','primary'],
        'active'              => ['Active tenancy',     'success'],
        'completed'           => ['Completed',          'secondary'],
        'cancelled_by_student','cancelled_by_landlord','cancelled_by_admin'
                              => ['Cancelled',          'secondary'],
        default               => [ucfirst(str_replace('_',' ',$status)), 'secondary'],
    };
}
[$statusLabel, $statusColor] = prop_status_badge($property['status']);

ob_start();
?>

<p class="small mb-3">
    <a href="/rentbridge/admin/properties.php" class="text-secondary text-decoration-none">
        <i class="bi bi-arrow-left"></i> Back to properties
    </a>
</p>

<?php if (!empty($errors['general'])): ?>
    <div class="alert alert-danger"><?= e($errors['general']) ?></div>
<?php endif; ?>

<!-- HEADER -->
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <h2 class="mb-1"><?= e($property['title']) ?></h2>
        <p class="text-secondary mb-0">
            <i class="bi bi-geo-alt"></i> <?= e($property['address']) ?>,
            <?= e($property['city']) ?> <?= e($property['postcode']) ?>,
            <?= e($property['state']) ?>
        </p>
    </div>
    <span class="badge bg-<?= $statusColor ?> fs-6"><?= e($statusLabel) ?></span>
</div>

<!-- ACTIONS -->
<?php if ($property['status'] === 'pending_approval'): ?>
    <div class="bg-white border rounded-3 p-4 mb-4" style="border-left: 4px solid #D4A017 !important;">
        <h5 class="mb-3">Review this listing</h5>
        <p class="text-secondary small mb-3">
            Verify the listing photos, address, and landlord details look legitimate before approving.
            The agent's physical inspection will happen later at booking time.
        </p>
        <div class="d-flex gap-2 flex-wrap">
            <form method="POST" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="approve">
                <button type="submit" class="btn btn-success"
                        onclick="return confirm('Approve this listing? It will be visible to students.');">
                    <i class="bi bi-check2-circle me-1"></i> Approve listing
                </button>
            </form>
            <button type="button" class="btn btn-outline-danger" data-bs-toggle="collapse" data-bs-target="#rejectForm">
                <i class="bi bi-x-circle me-1"></i> Reject…
            </button>
        </div>
        <div class="collapse mt-3" id="rejectForm">
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reject">
                <label class="form-label small fw-semibold">Reason for rejection</label>
                <textarea name="reject_reason" rows="3" class="form-control mb-2" required
                          placeholder="e.g. Photos look fake, address doesn't exist, IC mismatch"></textarea>
                <button type="submit" class="btn btn-danger btn-sm">
                    Confirm rejection
                </button>
            </form>
        </div>
    </div>
<?php elseif (in_array($property['status'], ['available','booked','rented'], true)): ?>
    <div class="d-flex gap-2 mb-4 flex-wrap">
        <form method="POST" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="hide">
            <button type="submit" class="btn btn-outline-secondary btn-sm"
                    onclick="return confirm('Hide this property from listings? It can be unhidden later.');">
                <i class="bi bi-eye-slash me-1"></i> Hide from listings
            </button>
        </form>
    </div>
<?php elseif ($property['status'] === 'hidden'): ?>
    <div class="d-flex gap-2 mb-4">
        <form method="POST" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="unhide">
            <button type="submit" class="btn btn-success btn-sm">
                <i class="bi bi-eye me-1"></i> Make visible again
            </button>
        </form>
    </div>
<?php endif; ?>

<!-- PHOTOS -->
<?php if (!empty($images)): ?>
<div class="bg-white border rounded-3 p-4 mb-4">
    <h5 class="mb-3"><i class="bi bi-camera me-2"></i>Listing photos (<?= count($images) ?>)</h5>
    <div class="row g-2">
        <?php foreach ($images as $i => $img): ?>
            <div class="col-md-4 col-6">
                <img src="/rentbridge/<?= e($img['image_path']) ?>"
                     class="w-100 rounded-3"
                     style="aspect-ratio: 4/3; object-fit: cover; cursor:pointer;"
                     data-bs-toggle="modal" data-bs-target="#img<?= (int)$img['id'] ?>"
                     alt="">
            </div>
            <div class="modal fade" id="img<?= (int)$img['id'] ?>" tabindex="-1">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h6 class="modal-title">Photo <?= $i+1 ?> of <?= count($images) ?></h6>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body p-0">
                            <img src="/rentbridge/<?= e($img['image_path']) ?>" class="w-100" alt="">
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- DOCUMENTS -->
<?php
require_once __DIR__ . '/../includes/uploads.php';
$documents = get_property_documents($propertyId);
?>
<?php if (!empty($documents)): ?>
<div class="bg-white border rounded-3 p-4 mb-4">
    <h5 class="mb-3">
        <i class="bi bi-file-earmark-lock me-2"></i>
        Ownership documents
        <span class="badge bg-secondary ms-1 fs-6">Private</span>
    </h5>
    <p class="text-secondary small mb-3">
        Only visible to you, admin, and your assigned agent.
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
                        <?php if (!empty($d['notes'])): ?>
                            <div class="small text-secondary fst-italic">"<?= e($d['notes']) ?>"</div>
                        <?php endif; ?>
                    </div>
                    <i class="bi bi-box-arrow-up-right text-secondary"></i>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- PROPERTY DETAILS + LANDLORD (side-by-side) -->
<div class="row g-3 mb-4">
    <div class="col-md-7">
        <div class="bg-white border rounded-3 p-4 h-100">
            <h5 class="mb-3"><i class="bi bi-house me-2"></i>Property details</h5>
            <div class="row g-3">
                <div class="col-6">
                    <small class="text-secondary text-uppercase">Type</small>
                    <div class="fw-semibold"><?= e(ucfirst(str_replace('_',' ', $property['property_type']))) ?></div>
                </div>
                <div class="col-6">
                    <small class="text-secondary text-uppercase">Furnishing</small>
                    <div class="fw-semibold"><?= e(ucfirst($property['furnishing'])) ?></div>
                </div>
                <div class="col-6">
                    <small class="text-secondary text-uppercase">Monthly rent</small>
                    <div class="fw-semibold text-emerald">
                        RM <?= number_format((float)$property['monthly_rent']) ?>
                    </div>
                </div>
                <div class="col-6">
                    <small class="text-secondary text-uppercase">Deposit</small>
                    <div class="fw-semibold">
                        RM <?= number_format((float)$property['deposit']) ?>
                    </div>
                </div>
                <div class="col-6">
                    <small class="text-secondary text-uppercase">Viewing mode</small>
                    <div class="fw-semibold">
                        <?= e(ucfirst(str_replace('_',' ', $property['viewing_mode']))) ?>
                    </div>
                </div>
                <div class="col-6">
                    <small class="text-secondary text-uppercase">Listed on</small>
                    <div class="fw-semibold"><?= e(date('d M Y', strtotime($property['created_at']))) ?></div>
                </div>
            </div>

            <?php if (!empty($property['description'])): ?>
                <hr>
                <small class="text-secondary text-uppercase">Description</small>
                <p style="white-space:pre-line;" class="mb-0"><?= e($property['description']) ?></p>
            <?php endif; ?>

            <?php if (!empty($property['facilities'])): ?>
                <hr>
                <small class="text-secondary text-uppercase">Facilities</small>
                <p style="white-space:pre-line;" class="mb-0"><?= e($property['facilities']) ?></p>
            <?php endif; ?>

            <?php if (!empty($property['agent_verified_at'])): ?>
                <hr>
                <div class="d-flex align-items-center gap-2 small">
                    <span class="badge bg-success">✓ Agent-verified</span>
                    <span class="text-secondary">
                        by <?= e($property['verifier_name'] ?? 'agent') ?>
                        on <?= e(date('d M Y', strtotime($property['agent_verified_at']))) ?>
                    </span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="col-md-5">
        <div class="bg-white border rounded-3 p-4 h-100">
            <h5 class="mb-3"><i class="bi bi-person me-2"></i>Landlord</h5>
            <div class="mb-2">
                <strong><?= e($property['landlord_name']) ?></strong>
                <?php if (!empty($property['landlord_nickname'])): ?>
                    <span class="text-secondary">"<?= e($property['landlord_nickname']) ?>"</span>
                <?php endif; ?>
            </div>
            <div class="small text-secondary mb-1">
                <i class="bi bi-credit-card-2-front"></i> IC: <code><?= e($property['landlord_ic']) ?></code>
            </div>
            <div class="small text-secondary mb-1">
                <i class="bi bi-telephone"></i> <?= e($property['landlord_phone']) ?>
            </div>
            <div class="small text-secondary mb-3">
                <i class="bi bi-envelope"></i> <?= e($property['landlord_email']) ?>
            </div>
            <a href="/rentbridge/admin/user.php?id=<?= (int)$property['landlord_user_id'] ?>"
               class="btn btn-sm btn-outline-dark w-100">
                View landlord profile <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
    </div>
</div>

<!-- TENANCIES on this property -->
<div class="bg-white border rounded-3 p-4 mb-4">
    <h5 class="mb-3">
        <i class="bi bi-clipboard-data me-2"></i>
        Tenancies on this property <small class="text-secondary fw-normal">(<?= count($tenancies) ?>)</small>
    </h5>

    <?php if (empty($tenancies)): ?>
        <p class="text-secondary mb-0">No tenancies on this property yet.</p>
    <?php else: ?>
        <table class="table mb-0 align-middle">
            <thead style="background:#F4F4EE;">
                <tr>
                    <th>Tenancy</th>
                    <th>Student</th>
                    <th>Agent</th>
                    <th>Contract</th>
                    <th>Status</th>
                    <th class="text-end"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tenancies as $t):
                    [$tLabel, $tColor] = tenancy_status_label($t['status']);
                ?>
                    <tr>
                        <td>
                            <strong>#<?= (int)$t['id'] ?></strong>
                            <div class="small text-secondary">
                                <?= e(date('d M Y', strtotime($t['created_at']))) ?>
                            </div>
                        </td>
                        <td class="small">
                            <?= e($t['student_name']) ?>
                            <div class="text-secondary"><code><?= e($t['matric_no']) ?></code></div>
                        </td>
                        <td class="small">
                            <?= !empty($t['agent_name']) ? e($t['agent_name']) : '<span class="text-secondary">—</span>' ?>
                        </td>
                        <td class="small">
                            <?php if (!empty($t['contract_code'])): ?>
                                <a href="/rentbridge/contracts/view.php?id=<?= (int)$t['contract_id'] ?>"
                                   class="text-decoration-none">
                                    <code><?= e($t['contract_code']) ?></code>
                                </a>
                            <?php else: ?>
                                <span class="text-secondary">—</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-<?= $tColor ?>"><?= e($tLabel) ?></span></td>
                        <td class="text-end">
                            <a href="/rentbridge/admin/booking.php?id=<?= (int)$t['id'] ?>"
                               class="btn btn-sm btn-outline-dark">
                                View <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/admin_layout.php';