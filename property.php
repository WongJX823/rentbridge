<?php
require_once __DIR__ . '/includes/auth.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    die('Property not found.');
}

// Fetch property + landlord info
$stmt = db()->prepare("
    SELECT p.*,
           l.full_name AS landlord_name,
           l.phone     AS landlord_phone,
           l.verified  AS landlord_verified
      FROM properties p
      JOIN landlords l ON l.user_id = p.landlord_id
     WHERE p.id = ?
       AND p.status = 'available'
     LIMIT 1
");
$stmt->execute([$id]);
$prop = $stmt->fetch();

if (!$prop) {
    http_response_code(404);
    die('Property not found or no longer available.');
}

// Fetch all photos
$stmt = db()->prepare("
    SELECT image_path FROM property_images
     WHERE property_id = ?
     ORDER BY is_primary DESC, id ASC
");
$stmt->execute([$id]);
$photos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($prop['title']) ?> · RentBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include 'includes/header.php'; ?>

<div class="container py-4">

    <!-- Back link -->
    <p class="mb-3">
        <a href="listings.php" class="text-secondary text-decoration-none">
            <i class="bi bi-arrow-left"></i> Back to listings
        </a>
    </p>

    <!-- Photo gallery -->
    <?php if (!empty($photos)): ?>
        <div class="row g-2 mb-4">
            <div class="col-lg-8">
                <img src="<?= e($photos[0]['image_path']) ?>"
                     class="w-100 rounded-3"
                     style="aspect-ratio: 4/3; object-fit: cover;"
                     alt="<?= e($prop['title']) ?>">
            </div>
            <?php if (count($photos) > 1): ?>
            <div class="col-lg-4">
                <div class="row g-2">
                    <?php foreach (array_slice($photos, 1, 3) as $img): ?>
                        <div class="col-12 col-md-6 col-lg-12">
                            <img src="<?= e($img['image_path']) ?>"
                                 class="w-100 rounded-3"
                                 style="aspect-ratio: 4/3; object-fit: cover;"
                                 alt="">
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <!-- Title + location -->
            <span class="badge bg-light text-secondary border mb-2">
                <?= e(ucfirst(str_replace('_', ' ', $prop['property_type']))) ?>
            </span>
            <h1 class="mb-2"><?= e($prop['title']) ?></h1>
            <p class="text-secondary mb-1">
                <i class="bi bi-geo-alt"></i>
                <?= e($prop['address']) ?>,
                <?= e($prop['city']) ?> <?= e($prop['postcode']) ?>,
                <?= e($prop['state']) ?>
            </p>
            <?php
                $mapQuery = urlencode(
                    $prop['address'] . ', ' . $prop['city'] . ' ' . $prop['postcode'] . ', ' . $prop['state']
                );
            ?>
            <a href="https://www.google.com/maps/search/?api=1&query=<?= $mapQuery ?>"
               target="_blank" rel="noopener"
               class="small text-decoration-none">
                <i class="bi bi-map"></i> View on Google Maps
            </a>

            <hr class="my-4">

            <!-- Property details -->
            <div class="row g-3 mb-4">
                <div class="col-sm-4">
                    <small class="text-secondary fw-semibold text-uppercase">Monthly rent</small>
                    <div class="fs-4 fw-semibold text-emerald">
                        RM <?= number_format((float)$prop['monthly_rent']) ?>
                    </div>
                </div>
                <div class="col-sm-4">
                    <small class="text-secondary fw-semibold text-uppercase">Deposit</small>
                    <div class="fs-4 fw-semibold">
                        RM <?= number_format((float)$prop['deposit']) ?>
                    </div>
                </div>
                <div class="col-sm-4">
                    <small class="text-secondary fw-semibold text-uppercase">Furnishing</small>
                    <div class="fs-4 fw-semibold">
                        <?= e(ucfirst($prop['furnishing'])) ?>
                    </div>
                </div>
            </div>

            <!-- Description -->
            <?php if (!empty($prop['description'])): ?>
                <h5 class="mt-4">About this place</h5>
                <p style="white-space: pre-line;"><?= e($prop['description']) ?></p>
            <?php endif; ?>

            <!-- Facilities -->
            <?php if (!empty($prop['facilities'])): ?>
                <h5 class="mt-4">Facilities</h5>
                <p>
                    <?php foreach (explode(',', $prop['facilities']) as $f):
                        $f = trim($f);
                        if ($f === '') continue;
                    ?>
                        <span class="badge bg-light text-dark border me-1 mb-1 px-3 py-2">
                            <?= e($f) ?>
                        </span>
                    <?php endforeach; ?>
                </p>
            <?php endif; ?>
        </div>

        <!-- Sidebar: landlord + actions -->
        <div class="col-lg-4">
            <div class="bg-white border rounded-3 p-4 sticky-top" style="top: 20px;">
                <h6 class="text-secondary text-uppercase small mb-3">Listed by</h6>

                <div class="d-flex align-items-center gap-3 mb-3">
                    <div class="rounded-circle bg-light d-flex align-items-center justify-content-center"
                         style="width:48px; height:48px; font-size:1.2rem;">
                        <i class="bi bi-person-fill text-secondary"></i>
                    </div>
                    <div>
                        <div class="fw-semibold">
                            <?= e($prop['landlord_name']) ?>
                            <?php if ((int)$prop['landlord_verified'] === 1): ?>
                                <i class="bi bi-patch-check-fill text-success" title="Verified landlord"></i>
                            <?php endif; ?>
                        </div>
                        <small class="text-secondary">Landlord</small>
                    </div>
                </div>

                <hr>

                <?php if (is_logged_in() && current_role() === 'student'): ?>
                    <a href="/rentbridge/bookings/new.php?property_id=<?= (int)$prop['id'] ?>" class="btn btn-success w-100 mb-2">
                        <i class="bi bi-calendar-check me-1"></i> Request to book
                    </a>
                    <a href="#" class="btn btn-ghost w-100">
                        <i class="bi bi-chat-left-text me-1"></i> Message landlord
                    </a>
                <?php elseif (!is_logged_in()): ?>
                    <a href="auth/login.php" class="btn btn-success w-100 mb-2">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Log in to book
                    </a>
                    <small class="text-secondary d-block text-center">
                        <a href="auth/register_student.php">Don't have an account?</a>
                    </small>
                <?php else: ?>
                    <div class="alert alert-info mb-0">
                        <i class="bi bi-info-circle"></i>
                        Only students can book properties.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>