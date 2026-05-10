<?php
require_once __DIR__ . '/includes/auth.php';

// Fetch up to 6 newest available properties for the featured section
try {
    $stmt = db()->query("
        SELECT p.*,
               (SELECT image_path FROM property_images
                 WHERE property_id = p.id
                 ORDER BY is_primary DESC, id ASC
                 LIMIT 1) AS image_path
          FROM properties p
         WHERE p.status = 'available'
         ORDER BY p.created_at DESC
         LIMIT 6
    ");
    $featured = $stmt->fetchAll();
} catch (Throwable $e) {
    $featured = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RentBridge · Find Student Housing You Can Trust</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include 'includes/header.php'; ?>

<!-- Hero -->
<section class="bg-navy text-white py-5">
    <div class="container py-4">
        <div class="row">
            <div class="col-lg-8">
                <h1 class="text-white display-4 fw-semibold mb-3">
                    Find your home, <em class="text-success">without the guesswork.</em>
                </h1>
                <p class="lead text-white-50 mb-4">
                    Browse verified student rentals near campus. Every booking witnessed by a UTeM staff agent.
                </p>
                <a href="listings.php" class="btn btn-success btn-lg">
                    <i class="bi bi-search me-1"></i> Browse listings
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Featured listings -->
<section class="container py-5">
    <div class="d-flex justify-content-between align-items-end mb-4">
        <div>
            <small class="text-secondary fw-semibold text-uppercase">Featured</small>
            <h2 class="mb-0">Recently listed near campus</h2>
        </div>
        <a href="listings.php" class="btn btn-ghost">
            See all <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>

    <?php if (empty($featured)): ?>
        <div class="text-center py-5 text-secondary">
            <i class="bi bi-house" style="font-size: 2rem;"></i>
            <p class="mt-2 mb-0">No listings yet. Check back soon!</p>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($featured as $p): ?>
                <div class="col-md-6 col-lg-4">
                    <a href="property.php?id=<?= (int)$p['id'] ?>" class="rb-card text-decoration-none">
                        <div class="rb-card__media">
                            <span class="rb-card__badge">
                                <?= e(ucfirst(str_replace('_', ' ', $p['property_type']))) ?>
                            </span>
                            <?php if (!empty($p['image_path'])): ?>
                                <img src="<?= e($p['image_path']) ?>" alt="<?= e($p['title']) ?>">
                            <?php endif; ?>
                        </div>
                        <div class="rb-card__body">
                            <h3 class="rb-card__title"><?= e($p['title']) ?></h3>
                            <div class="rb-card__meta">
                                <i class="bi bi-geo-alt"></i> <?= e($p['city'] . ', ' . $p['state']) ?>
                            </div>
                            <div class="rb-card__price">
                                <strong>RM <?= number_format((float)$p['monthly_rent']) ?></strong>
                                <span>per month</span>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>