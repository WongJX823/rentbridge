<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$propId = (int)($_GET['id'] ?? $_POST['property_id'] ?? 0);
if ($propId <= 0) {
    http_response_code(400);
    die('Invalid property ID.');
}

$pdo = db();

// Fetch the property + landlord + all photos
$stmt = $pdo->prepare("
    SELECT p.*,
           l.full_name      AS landlord_name,
           l.preferred_name AS landlord_nickname,
           l.ic_no          AS landlord_ic,
           l.phone          AS landlord_phone,
           u.email          AS landlord_email,
           u.status         AS landlord_status
      FROM properties p
      JOIN landlords l ON l.user_id = p.landlord_id
      JOIN users u ON u.id = p.landlord_id
     WHERE p.id = ?
     LIMIT 1
");
$stmt->execute([$propId]);
$prop = $stmt->fetch();

if (!$prop) {
    http_response_code(404);
    die('Property not found.');
}

// All photos
$stmt = $pdo->prepare("
    SELECT image_path FROM property_images
     WHERE property_id = ?
     ORDER BY is_primary DESC, id ASC
");
$stmt->execute([$propId]);
$photos = $stmt->fetchAll();

$errors = [];
$reason = '';

// ---- HANDLE ACTION ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $reason = trim($_POST['reason'] ?? '');

    $allowed = ['approve', 'reject', 'hide', 'unhide'];
    if (!in_array($action, $allowed, true)) {
        $errors['general'] = 'Invalid action.';
    } elseif ($action === 'reject' && $reason === '') {
        $errors['reason'] = 'Please give a reason for rejecting this property.';
    } else {
        $newStatus = match ($action) {
            'approve' => 'available',
            'reject'  => 'rejected',
            'hide'    => 'hidden',
            'unhide'  => 'available',
        };

        try {
            $stmt = $pdo->prepare('UPDATE properties SET status = ? WHERE id = ?');
            $stmt->execute([$newStatus, $propId]);

            // Notify landlord
            $msg = match ($action) {
                'approve' => 'Your property "' . $prop['title'] . '" has been approved and is now live in search results!',
                'reject'  => 'Your property "' . $prop['title'] . '" was not approved. Reason: ' . $reason,
                'hide'    => 'Your property "' . $prop['title'] . '" has been hidden by an administrator.',
                'unhide'  => 'Your property "' . $prop['title'] . '" is visible again in search results.',
            };
            notify(
                (int)$prop['landlord_id'],
                'property_status_change',
                'Property status updated',
                $msg,
                '/rentbridge/property.php?id=' . $propId
            );

            set_flash('success', 'Property ' . $action . 'd successfully.');
            header('Location: /rentbridge/admin/properties.php?status=' . $newStatus);
            exit;

        } catch (Throwable $e) {
            $errors['general'] = 'Error: ' . $e->getMessage();
        }
    }
}

function prop_status_badge(string $status): array {
    return match ($status) {
        'pending_approval' => ['Pending review', 'warning'],
        'available'        => ['Available',       'success'],
        'booked'           => ['Booked',          'info'],
        'rented'           => ['Rented out',      'primary'],
        'hidden'           => ['Hidden',          'secondary'],
        'rejected'         => ['Rejected',        'danger'],
        default            => [ucfirst($status),  'secondary'],
    };
}
[$badgeLabel, $badgeColor] = prop_status_badge($prop['status']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Review property · Admin · RentBridge</title>
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
        <div class="col-lg-10">

            <p class="small mb-3">
                <a href="/rentbridge/admin/properties.php" class="text-secondary text-decoration-none">
                    <i class="bi bi-arrow-left"></i> All properties
                </a>
            </p>

            <div class="d-flex justify-content-between align-items-start mb-4">
                <div>
                    <h1 class="mb-1"><?= e($prop['title']) ?></h1>
                    <p class="text-secondary mb-0">
                        Submitted <?= e(date('d M Y, H:i', strtotime($prop['created_at']))) ?>
                    </p>
                </div>
                <span class="badge bg-<?= $badgeColor ?> fs-6"><?= e($badgeLabel) ?></span>
            </div>

            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-danger"><?= e($errors['general']) ?></div>
            <?php endif; ?>

            <!-- Photo gallery -->
            <?php if (!empty($photos)): ?>
                <div class="row g-2 mb-4">
                    <?php foreach ($photos as $i => $img): ?>
                        <div class="col-md-<?= $i === 0 ? '6' : '3' ?>">
                            <img src="/rentbridge/<?= e($img['image_path']) ?>"
                                 class="w-100 rounded-3"
                                 style="aspect-ratio: <?= $i === 0 ? '4/3' : '1/1' ?>; object-fit: cover;"
                                 alt="">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert alert-warning mb-4">
                    <i class="bi bi-exclamation-triangle"></i>
                    No photos uploaded for this property — landlord should add photos before this can be listed.
                </div>
            <?php endif; ?>

            <div class="row g-4">

                <!-- Property details -->
                <div class="col-md-8">
                    <div class="bg-white border rounded-3 p-4 h-100">
                        <h6 class="text-secondary text-uppercase small mb-3">Property details</h6>

                        <div class="row g-3 mb-3">
                            <div class="col-sm-6">
                                <small class="text-secondary text-uppercase">Type</small>
                                <div class="fw-semibold"><?= e(ucfirst(str_replace('_',' ', $prop['property_type']))) ?></div>
                            </div>
                            <div class="col-sm-6">
                                <small class="text-secondary text-uppercase">Furnishing</small>
                                <div class="fw-semibold"><?= e(ucfirst($prop['furnishing'])) ?></div>
                            </div>
                            <div class="col-sm-6">
                                <small class="text-secondary text-uppercase">Monthly rent</small>
                                <div class="fw-semibold text-emerald fs-5">RM <?= number_format((float)$prop['monthly_rent']) ?></div>
                            </div>
                            <div class="col-sm-6">
                                <small class="text-secondary text-uppercase">Deposit</small>
                                <div class="fw-semibold">RM <?= number_format((float)$prop['deposit']) ?></div>
                            </div>
                        </div>

                        <hr>

                        <small class="text-secondary text-uppercase">Address</small>
                        <p class="mb-3">
                            <?= e($prop['address']) ?>,<br>
                            <?= e($prop['city']) ?> <?= e($prop['postcode']) ?>,
                            <?= e($prop['state']) ?>
                        </p>

                        <?php if (!empty($prop['description'])): ?>
                            <small class="text-secondary text-uppercase">Description</small>
                            <p style="white-space: pre-line;"><?= e($prop['description']) ?></p>
                        <?php endif; ?>

                        <?php if (!empty($prop['facilities'])): ?>
                            <small class="text-secondary text-uppercase">Facilities</small>
                            <p class="mb-0">
                                <?php foreach (explode(',', $prop['facilities']) as $f): $f = trim($f); if ($f === '') continue; ?>
                                    <span class="badge bg-light text-dark border me-1 mb-1 px-3 py-2"><?= e($f) ?></span>
                                <?php endforeach; ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Landlord info -->
                <div class="col-md-4">
                    <div class="bg-white border rounded-3 p-4 h-100">
                        <h6 class="text-secondary text-uppercase small mb-3">Listed by</h6>
                        <h5 class="mb-1"><?= e($prop['landlord_name']) ?></h5>
                        <small class="text-secondary d-block mb-3">@<?= e($prop['landlord_nickname']) ?></small>

                        <div class="small text-secondary">
                            <div class="mb-1"><i class="bi bi-card-text"></i> IC: <?= e($prop['landlord_ic']) ?></div>
                            <div class="mb-1"><i class="bi bi-envelope"></i> <?= e($prop['landlord_email']) ?></div>
                            <div class="mb-1"><i class="bi bi-telephone"></i> <?= e($prop['landlord_phone']) ?></div>
                            <div>
                                <i class="bi bi-shield-check"></i>
                                Account: <span class="badge bg-light text-dark border"><?= e($prop['landlord_status']) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action panel -->
                <div class="col-12">
                    <div class="bg-white border rounded-3 p-4">
                        <h6 class="text-secondary text-uppercase small mb-3">Admin actions</h6>

                        <form method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="property_id" value="<?= (int)$prop['id'] ?>">

                            <?php if ($prop['status'] === 'pending_approval'): ?>
                                <p class="text-secondary small mb-3">
                                    <i class="bi bi-info-circle"></i>
                                    Approve to publish this listing in public search results. Reject to deny it (landlord will be notified with the reason).
                                </p>

                                <div class="mb-3">
                                    <label class="form-label small">Reason <small class="text-secondary fw-normal">— required if rejecting</small></label>
                                    <textarea name="reason" rows="3"
                                              class="form-control <?= isset($errors['reason']) ? 'is-invalid' : '' ?>"
                                              placeholder="e.g. Photos are unclear, missing required information..."><?= e($reason) ?></textarea>
                                    <?php if (isset($errors['reason'])): ?>
                                        <div class="invalid-feedback"><?= e($errors['reason']) ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="d-flex gap-2">
                                    <button type="submit" name="action" value="approve" class="btn btn-success"
                                            onclick="return confirm('Approve this listing? It will go live in search results.');">
                                        <i class="bi bi-check-circle me-1"></i> Approve listing
                                    </button>
                                    <button type="submit" name="action" value="reject" class="btn btn-outline-danger"
                                            onclick="return confirm('Reject this listing? Landlord will be notified.');">
                                        <i class="bi bi-x-circle me-1"></i> Reject
                                    </button>
                                </div>

                            <?php elseif ($prop['status'] === 'available'): ?>
                                <p class="text-secondary small">
                                    This property is live. You can hide it from public search if there are issues.
                                </p>
                                <div class="mb-3">
                                    <label class="form-label small">Reason for hiding <small class="text-secondary fw-normal">— optional</small></label>
                                    <textarea name="reason" rows="2"
                                              class="form-control"
                                              placeholder="e.g. Reports of issues..."><?= e($reason) ?></textarea>
                                </div>
                                <button type="submit" name="action" value="hide" class="btn btn-outline-warning"
                                        onclick="return confirm('Hide this property from public listings?');">
                                    <i class="bi bi-eye-slash me-1"></i> Hide from listings
                                </button>

                            <?php elseif ($prop['status'] === 'hidden'): ?>
                                <p class="text-secondary small">
                                    This property is currently hidden. Unhide to put it back in search results.
                                </p>
                                <button type="submit" name="action" value="unhide" class="btn btn-success"
                                        onclick="return confirm('Make this property visible again?');">
                                    <i class="bi bi-eye me-1"></i> Unhide
                                </button>

                            <?php elseif ($prop['status'] === 'rejected'): ?>
                                <p class="text-secondary small">
                                    This property was rejected. To reconsider, the landlord must edit and resubmit it.
                                </p>

                            <?php else: ?>
                                <p class="text-secondary small mb-0">
                                    <i class="bi bi-info-circle"></i>
                                    No admin actions available for properties in "<?= e($prop['status']) ?>" status.
                                </p>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

</body>
</html>