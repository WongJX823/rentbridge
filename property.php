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
            <img src="/rentbridge/<?= e($photos[0]['image_path']) ?>"
                 class="w-100 rounded-3"
                 style="aspect-ratio: 4/3; object-fit: cover;"
                 alt="<?= e($prop['title']) ?>">
        </div>
        <?php if (count($photos) > 1): ?>
        <div class="col-lg-4">
            <div class="row g-2">
                <?php foreach (array_slice($photos, 1, 3) as $img): ?>
                    <div class="col-12 col-md-6 col-lg-12">
                        <img src="/rentbridge/<?= e($img['image_path']) ?>"
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
    <?php
// === Check if user arrived via a co-tenancy post ===
$fromPostId = (int)($_GET['from_post'] ?? 0);
$fromPost = null;
if ($fromPostId > 0) {
    $stmt = db()->prepare("
        SELECT ctp.*,
               s.full_name AS poster_name,
               s.preferred_name AS poster_nickname,
               s.matric_no AS poster_matric,
               s.housing_bio AS poster_bio
          FROM co_tenancy_posts ctp
          JOIN students s ON s.user_id = ctp.poster_id
         WHERE ctp.id = ? AND ctp.property_id = ? AND ctp.status = 'open'
    ");
    $stmt->execute([$fromPostId, (int)$prop['id']]);
    $fromPost = $stmt->fetch();
}

// === Also fetch ALL active posts for this property (anyone, not just the one we came from) ===
$stmt = db()->prepare("
    SELECT ctp.id, ctp.message, ctp.housemates_needed, ctp.created_at,
           s.full_name      AS poster_name,
           s.preferred_name AS poster_nickname,
           s.matric_no      AS poster_matric
      FROM co_tenancy_posts ctp
      JOIN students s ON s.user_id = ctp.poster_id
     WHERE ctp.property_id = ? AND ctp.status = 'open'
     ORDER BY ctp.created_at DESC
");
$stmt->execute([(int)$prop['id']]);
$allPosts = $stmt->fetchAll();
?>

<?php if ($fromPost): ?>
<!-- BANNER: arrived from a specific co-tenancy post -->
<div class="alert d-flex gap-3 align-items-start mb-4"
     style="background:#E4F2EA; border-color:#2E8B57; color:#0F2C52;">
    <div style="width:40px; height:40px; border-radius:50%;
                background:white; color:#0F2C52;
                display:flex; align-items:center; justify-content:center;
                font-weight:600; flex-shrink:0;">
        <?= strtoupper(substr($fromPost['poster_nickname'] ?: $fromPost['poster_name'], 0, 1)) ?>
    </div>
    <div class="flex-grow-1">
        <strong><?= e($fromPost['poster_nickname'] ?: $fromPost['poster_name']) ?></strong>
        <span class="text-secondary small">
            (<code><?= e($fromPost['poster_matric']) ?></code>)
        </span>
        is looking for <strong><?= (int)$fromPost['housemates_needed'] ?> more housemate<?= $fromPost['housemates_needed']==1?'':'s' ?></strong>
        for this property.
        <?php if (!empty($fromPost['message'])): ?>
            <div class="small mt-2"
                 style="background:white; border-radius:6px; padding:8px 12px;">
                "<?= e($fromPost['message']) ?>"
            </div>
        <?php endif; ?>
    </div>
    <a href="/rentbridge/chat/start.php?type=partner_inquiry&with=<?= (int)$fromPost['poster_id'] ?>&post_id=<?= (int)$fromPost['id'] ?>"
        class="btn btn-success btn-sm" style="flex-shrink:0;">
        <i class="bi bi-chat-dots me-1"></i> Message
    </a>
</div>
<?php elseif (!empty($allPosts) && is_logged_in() && current_role() === 'student'): ?>
    
<!-- BANNER: there are co-tenancy posts even though we didn't arrive from one -->
<div class="alert alert-light border d-flex gap-3 align-items-start mb-4">
    <i class="bi bi-people-fill text-emerald fs-3"></i>
    <div class="flex-grow-1">
        <strong><?= count($allPosts) ?>
        student<?= count($allPosts)===1?'':'s' ?> looking for housemates on this property.</strong>
        <div class="small text-secondary">
            <?php foreach (array_slice($allPosts, 0, 3) as $i => $p): ?>
                <?= e($p['poster_nickname'] ?: $p['poster_name']) ?> needs <?= (int)$p['housemates_needed'] ?><?= $i < min(count($allPosts), 3) - 1 ? ', ' : '' ?>
            <?php endforeach; ?>
            <?php if (count($allPosts) > 3): ?>
                + <?= count($allPosts) - 3 ?> more
            <?php endif; ?>
        </div>
    </div>
    <a href="/rentbridge/student/partners.php?city=<?= e($prop['city']) ?>"
       class="btn btn-sm btn-outline-dark" style="flex-shrink:0;">
        View posts <i class="bi bi-arrow-right ms-1"></i>
    </a>
</div>
<?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <!-- Title + location -->
            <span class="badge bg-light text-secondary border mb-2">
                <?= e(ucfirst(str_replace('_', ' ', $prop['property_type']))) ?>
            </span>
            <h1 class="mb-2"><?= e($prop['title']) ?></h1>
            <p class="text-secondary">
                <i class="bi bi-geo-alt"></i>
                <?= e($prop['address']) ?>,
                <?= e($prop['city']) ?> <?= e($prop['postcode']) ?>,
                <?= e($prop['state']) ?>
            </p>

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
                    <button type="button" class="btn btn-success w-100 mb-2"
                            data-bs-toggle="modal" data-bs-target="#loginPromptModal">
                        <i class="bi bi-calendar-check me-1"></i> Request to book
                    </button>
                    <button type="button" class="btn btn-outline-dark w-100"
                            data-bs-toggle="modal" data-bs-target="#loginPromptModal">
                        <i class="bi bi-chat-left-text me-1"></i> Message landlord
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if (!is_logged_in()): ?>
<!-- Login prompt modal — placed at root level to avoid z-index issues -->
<div class="modal fade" id="loginPromptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center px-4 pb-4 pt-0">
                <i class="bi bi-lock" style="font-size:3rem; color: rgba(15,44,82,0.3);"></i>
                <h5 class="mt-3 mb-2">Log in to continue</h5>
                <p class="text-secondary small mb-4">
                    Create a free account to message landlords, save properties, and book tenancies.
                </p>
                <div class="d-grid gap-2">
                    <a href="/rentbridge/auth/login.php" class="btn btn-primary">
                        <i class="bi bi-person-plus me-1"></i> Log in now
                    </a>
                    <a href="/rentbridge/auth/register.php" class="btn btn-outline-dark">
                        I dont have an account
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>