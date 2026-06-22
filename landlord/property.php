<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('landlord');

$propertyId = (int)($_GET['id'] ?? 0);
if ($propertyId <= 0) {
    http_response_code(400);
    die('Invalid property ID.');
}

$pdo = db();
$userId = current_user_id();

// Fetch property — must belong to this landlord
$stmt = $pdo->prepare("
    SELECT p.*,
           a.full_name  AS agent_name,
           a.phone      AS agent_phone,
           a.staff_id   AS agent_staff_id,
           a.department AS agent_department,
           (SELECT full_name FROM agents av
              WHERE av.user_id = p.agent_verified_by) AS verifier_name
      FROM properties p
      LEFT JOIN agents a ON a.user_id = p.assigned_agent_id
     WHERE p.id = ?
       AND p.landlord_id = ?
     LIMIT 1
");
$stmt->execute([$propertyId, $userId]);
$property = $stmt->fetch();

if (!$property) {
    http_response_code(404);
    die('Property not found or you are not the owner.');
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
    SELECT b.id, b.status, b.start_date, b.end_date, b.monthly_rent,
           s.full_name AS student_name, s.matric_no,
           a.full_name AS agent_name,
           c.contract_code, c.id AS contract_id
      FROM bookings b
      JOIN students s ON s.user_id = b.student_id
      LEFT JOIN agents a ON a.user_id = b.agent_id
      LEFT JOIN contracts c ON c.booking_id = b.id
     WHERE b.property_id = ?
     ORDER BY b.created_at DESC
");
$stmt->execute([$propertyId]);
$tenancies = $stmt->fetchAll();

// Current inspector (active case)
$stmt = $pdo->prepare("
    SELECT a.full_name AS agent_name, a.staff_id, a.department, b.id AS booking_id, b.status
      FROM bookings b
      JOIN agents a ON a.user_id = b.agent_id
     WHERE b.property_id = ?
       AND b.status IN ('agent_assigned','agent_verifying')
     ORDER BY b.id DESC LIMIT 1
");
$stmt->execute([$propertyId]);
$currentInspector = $stmt->fetch();

// --- HANDLE ACTIONS ---
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'hide' && in_array($property['status'], ['available','booked'], true)) {
        $stmt = $pdo->prepare("UPDATE properties SET status = 'hidden' WHERE id = ?");
        $stmt->execute([$propertyId]);
        set_flash('info', 'Property hidden from listings.');
        header('Location: /rentbridge/landlord/property.php?id=' . $propertyId);
        exit;
    }
    if ($action === 'unhide' && $property['status'] === 'hidden') {
        $stmt = $pdo->prepare("UPDATE properties SET status = 'available' WHERE id = ?");
        $stmt->execute([$propertyId]);
        set_flash('success', 'Property visible again.');
        header('Location: /rentbridge/landlord/property.php?id=' . $propertyId);
        exit;
    }
    if ($action === 'delete' && in_array($property['status'], ['pending_approval','rejected','hidden'], true)) {
        // Only allow delete if not active anywhere
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM bookings
             WHERE property_id = ?
               AND status NOT IN ('cancelled_by_student','cancelled_by_landlord','cancelled_by_admin','rejected_by_landlord','completed')
        ");
        $stmt->execute([$propertyId]);
        if ((int)$stmt->fetchColumn() > 0) {
            $errors['general'] = 'Cannot delete — there are active tenancies on this property.';
        } else {
            $stmt = $pdo->prepare("DELETE FROM properties WHERE id = ? AND landlord_id = ?");
            $stmt->execute([$propertyId, $userId]);
            set_flash('warning', 'Property deleted.');
            header('Location: /rentbridge/landlord/properties.php');
            exit;
        }
    }
}

$pageTitle     = 'Property #' . $propertyId;
$activeNav     = 'properties';
$showPageTitle = false;

function landlord_prop_status_info(string $status): array {
    return match ($status) {
        'pending_approval' => ['Pending review', 'warning',
            'An agent has been assigned to verify your listing. You can still edit while pending. Once approved, students can browse this property.'],
        'available'        => ['Available', 'success',
            'Live and visible to students. They can browse and apply for tenancy.'],
        'booked'           => ['Booked', 'info',
            'A tenancy is being processed. The property is reserved but not yet rented.'],
        'rented'           => ['Rented', 'primary',
            'Currently rented. The contract is active.'],
        'hidden'           => ['Hidden', 'secondary',
            'Not visible to students. You can make it available again anytime.'],
        'rejected'         => ['Rejected', 'danger',
            'Admin did not approve this listing. You can edit and resubmit.'],
        default            => [$status, 'secondary', 'Unknown status.'],
    };
}
[$statusLabel, $statusColor, $statusDesc] = landlord_prop_status_info($property['status']);

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
<div class="d-flex justify-content-between align-items-start mb-2 flex-wrap gap-2">
    <div>
        <h2 class="mb-1"><?= e($property['title']) ?></h2>
        <p class="text-secondary mb-0 small">
            <i class="bi bi-geo-alt"></i>
            <?= e($property['address']) ?>,
            <?= e($property['city']) ?> <?= e($property['postcode']) ?>,
            <?= e($property['state']) ?>
        </p>
    </div>
    <span class="badge bg-<?= $statusColor ?> fs-6"><?= e($statusLabel) ?></span>
</div>

<!-- STATUS EXPLANATION -->
<div class="alert alert-light border d-flex gap-3 align-items-start mb-4">
    <i class="bi bi-info-circle text-secondary fs-4"></i>
    <div>
        <strong>Your property is in <?= e(strtolower($statusLabel)) ?> status.</strong><br>
        <small class="text-secondary"><?= e($statusDesc) ?></small>
    </div>
</div>

<!-- ACTION BUTTONS -->
<div class="d-flex gap-2 mb-4 flex-wrap">
    <?php if (in_array($property['status'], ['pending_approval','rejected','hidden','available'], true)): ?>
        <a href="/rentbridge/landlord/add_property.php?edit=<?= (int)$propertyId ?>"
           class="btn btn-primary">
            <i class="bi bi-pencil me-1"></i> Edit property
        </a>
    <?php endif; ?>

    <?php if (in_array($property['status'], ['available','booked'], true)): ?>
        <form method="POST" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="hide">
            <button type="submit" class="btn btn-outline-secondary"
                    onclick="return confirm('Hide this property from listings? You can unhide later.');">
                <i class="bi bi-eye-slash me-1"></i> Hide from listings
            </button>
        </form>
    <?php elseif ($property['status'] === 'hidden'): ?>
        <form method="POST" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="unhide">
            <button type="submit" class="btn btn-success">
                <i class="bi bi-eye me-1"></i> Make visible again
            </button>
        </form>
    <?php endif; ?>

    <?php if (in_array($property['status'], ['pending_approval','rejected','hidden'], true)): ?>
        <form method="POST" class="d-inline ms-auto">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <button type="submit" class="btn btn-outline-danger"
                    onclick="return confirm('Delete this property permanently? This cannot be undone.');">
                <i class="bi bi-trash me-1"></i> Delete
            </button>
        </form>
    <?php endif; ?>
</div>

<!-- INSPECTOR ALERT (if currently being inspected) -->
<?php if ($currentInspector): ?>
<div class="alert d-flex gap-3 align-items-start"
     style="background:#E6ECF4; border-color:#0F2C52; color:#0F2C52;">
    <i class="bi bi-person-badge fs-3"></i>
    <div>
        <strong>Currently with agent:</strong> <?= e($currentInspector['agent_name']) ?>
        (<code><?= e($currentInspector['staff_id']) ?></code> · <?= e($currentInspector['department']) ?>)
        <div class="small">
            Working on Tenancy #<?= (int)$currentInspector['booking_id'] ?>
            (<?= e(str_replace('_', ' ', $currentInspector['status'])) ?>)
        </div>
    </div>
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

<!-- DETAILS -->
<div class="bg-white border rounded-3 p-4 mb-4">
    <h5 class="mb-3"><i class="bi bi-house me-2"></i>Property details</h5>
    <div class="row g-3">
        <div class="col-md-3 col-6">
            <small class="text-secondary text-uppercase">Type</small>
            <div class="fw-semibold"><?= e(ucfirst(str_replace('_',' ', $property['property_type']))) ?></div>
        </div>
        <div class="col-md-3 col-6">
            <small class="text-secondary text-uppercase">Furnishing</small>
            <div class="fw-semibold"><?= e(ucfirst($property['furnishing'])) ?></div>
        </div>
        <div class="col-md-3 col-6">
            <small class="text-secondary text-uppercase">Monthly rent</small>
            <div class="fw-semibold text-emerald">RM <?= number_format((float)$property['monthly_rent']) ?></div>
        </div>
        <div class="col-md-3 col-6">
            <small class="text-secondary text-uppercase">Deposit</small>
            <div class="fw-semibold">RM <?= number_format((float)$property['deposit']) ?></div>
        </div>
    </div>

    <?php if (!empty($property['description'])): ?>
        <hr>
        <small class="text-secondary text-uppercase">Description</small>
        <p class="mb-0" style="white-space:pre-line;"><?= e($property['description']) ?></p>
    <?php endif; ?>

    <?php if (!empty($property['facilities'])): ?>
        <hr>
        <small class="text-secondary text-uppercase">Facilities</small>
        <p class="mb-0" style="white-space:pre-line;"><?= e($property['facilities']) ?></p>
    <?php endif; ?>

    <?php if (!empty($property['agent_verified_at'])): ?>
        <hr>
        <div class="d-flex gap-2 align-items-center small">
            <span class="badge bg-success">✓ Verified by agent</span>
            <span class="text-secondary">
                by <?= e($property['verifier_name'] ?? 'agent') ?>
                on <?= e(date('d M Y', strtotime($property['agent_verified_at']))) ?>
            </span>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($property['agent_name'])): ?>
    <?php
    $agentStatusBadge = match($property['agent_status'] ?? '') {
        'pending'  => '<span class="badge bg-warning text-dark">Reviewing</span>',
        'accepted' => '<span class="badge bg-success">Approved</span>',
        'rejected' => '<span class="badge bg-danger">Rejected</span>',
        'timeout'  => '<span class="badge bg-secondary">Timed out</span>',
        default    => '',
    };
    ?>
    <div class="d-flex gap-3 align-items-start p-3 mt-2 border rounded-3"
         style="background:#F4F8FF;">
        <i class="bi bi-person-badge fs-3 text-secondary"></i>
        <div class="flex-grow-1 small">
            <div class="fw-semibold mb-1">
                Assigned agent <?= $agentStatusBadge ?>
            </div>
            <div><?= e($property['agent_name']) ?>
                <?php if (!empty($property['agent_staff_id'])): ?>
                    <code class="text-secondary ms-1"><?= e($property['agent_staff_id']) ?></code>
                <?php endif; ?>
            </div>
            <?php if (!empty($property['agent_department'])): ?>
                <div class="text-secondary"><?= e($property['agent_department']) ?></div>
            <?php endif; ?>
            <?php if (!empty($property['agent_phone'])): ?>
                <div class="text-secondary"><i class="bi bi-telephone"></i> <?= e($property['agent_phone']) ?></div>
            <?php endif; ?>
        </div>
    </div>
<?php elseif ($property['status'] === 'pending_approval'): ?>
    <div class="alert alert-warning small py-2 mt-2">
        <i class="bi bi-hourglass-split"></i> Assigning an agent — this usually takes a moment.
    </div>
<?php elseif ($property['status'] === 'needs_admin'): ?>
    <div class="alert alert-danger small py-2 mt-2">
        <i class="bi bi-exclamation-triangle"></i>
        No agents available — admin reviewing manually.
    </div>
<?php endif; ?>

<!-- TENANCIES -->
<?php if (!empty($tenancies)): ?>
<div class="bg-white border rounded-3 p-4 mb-4">
    <h5 class="mb-3"><i class="bi bi-clipboard-data me-2"></i>Tenancies on this property (<?= count($tenancies) ?>)</h5>
    <table class="table mb-0 align-middle">
        <thead style="background:#F4F4EE;">
            <tr>
                <th>ID</th>
                <th>Student</th>
                <th>Period</th>
                <th>Status</th>
                <th class="text-end"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tenancies as $t):
                $sLabel = match($t['status']) {
                    'pending_landlord' => ['Awaiting your response','warning'],
                    'pending_agent'    => ['Pending agent','warning'],
                    'agent_verifying'  => ['🔍 Inspecting','info'],
                    'agent_verified'   => ['✓ Verified','success'],
                    'contract_pending' => ['📝 Contract','primary'],
                    'active'           => ['Active','success'],
                    'completed'        => ['Completed','secondary'],
                    default            => [ucfirst(str_replace('_',' ', $t['status'])), 'secondary'],
                };
            ?>
                <tr>
                    <td><code class="text-secondary">#<?= (int)$t['id'] ?></code></td>
                    <td class="small">
                        <?= e($t['student_name']) ?>
                        <div class="text-secondary"><code><?= e($t['matric_no']) ?></code></div>
                    </td>
                    <td class="small">
                        <?= e(date('d M Y', strtotime($t['start_date']))) ?> →
                        <?= e(date('d M Y', strtotime($t['end_date']))) ?>
                    </td>
                    <td><span class="badge bg-<?= $sLabel[1] ?>"><?= e($sLabel[0]) ?></span></td>
                    <td class="text-end">
                        <a href="/rentbridge/landlord/booking.php?id=<?= (int)$t['id'] ?>"
                           class="btn btn-sm btn-outline-dark">
                            View <i class="bi bi-arrow-right"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/landlord_layout.php';