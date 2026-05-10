<?php
require_once __DIR__ . '/includes/auth.php';

// ---- Read filter params from URL ----
$city     = trim($_GET['city'] ?? '');
$type     = $_GET['type'] ?? '';
$max_rent = $_GET['max_rent'] ?? '';
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 9;

// ---- Build dynamic SQL ----
$where  = ["p.status = 'available'"];
$params = [];

if ($city !== '') {
    $where[]  = "(p.city LIKE ? OR p.address LIKE ?)";
    $params[] = '%' . $city . '%';
    $params[] = '%' . $city . '%';
}

if (in_array($type, ['room', 'studio', 'whole_unit'], true)) {
    $where[]  = "p.property_type = ?";
    $params[] = $type;
}

if (is_numeric($max_rent) && (float)$max_rent > 0) {
    $where[]  = "p.monthly_rent <= ?";
    $params[] = (float)$max_rent;
}

$whereSql = implode(' AND ', $where);

// ---- Count total (for pagination) ----
$countStmt = db()->prepare("SELECT COUNT(*) FROM properties p WHERE $whereSql");
$countStmt->execute($params);
$total      = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
$page       = min($page, $totalPages);
$offset     = ($page - 1) * $perPage;

// ---- Fetch this page's listings + their primary image ----
$sql = "
    SELECT p.*,
           (SELECT image_path FROM property_images
             WHERE property_id = p.id
             ORDER BY is_primary DESC, id ASC
             LIMIT 1) AS image_path
      FROM properties p
     WHERE $whereSql
     ORDER BY p.created_at DESC
     LIMIT $perPage OFFSET $offset
";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$listings = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse listings · RentBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include 'includes/header.php'; ?>

<div class="container py-5">

    <!-- Header -->
    <div class="mb-4">
        <h1 class="mb-1">Browse listings</h1>
        <p class="text-secondary mb-0">
            <?= $total ?> propert<?= $total === 1 ? 'y' : 'ies' ?> found
        </p>
    </div>

    <!-- Filter form -->
    <form method="GET" class="bg-white border rounded-3 p-3 mb-4">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-secondary">CITY OR AREA</label>
                <input type="text" name="city" value="<?= e($city) ?>"
                       class="form-control" placeholder="Melaka, Ayer Keroh...">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-secondary">TYPE</label>
                <select name="type" class="form-select">
                    <option value="">Any</option>
                    <option value="room"       <?= $type==='room'?'selected':'' ?>>Room</option>
                    <option value="studio"     <?= $type==='studio'?'selected':'' ?>>Studio</option>
                    <option value="whole_unit" <?= $type==='whole_unit'?'selected':'' ?>>Whole unit</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-secondary">MAX RENT (RM)</label>
                <select name="max_rent" class="form-select">
                    <option value="">Any</option>
                    <option value="500"  <?= $max_rent==='500' ?'selected':'' ?>>RM 500</option>
                    <option value="800"  <?= $max_rent==='800' ?'selected':'' ?>>RM 800</option>
                    <option value="1200" <?= $max_rent==='1200'?'selected':'' ?>>RM 1,200</option>
                    <option value="2000" <?= $max_rent==='2000'?'selected':'' ?>>RM 2,000</option>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Search
                </button>
            </div>
        </div>
    </form>

    <!-- Empty state -->
    <?php if (empty($listings)): ?>
        <div class="text-center py-5">
            <i class="bi bi-house-x" style="font-size: 3rem; color: var(--rb-line);"></i>
            <h3 class="mt-3">No properties match your search</h3>
            <p class="text-secondary">Try a wider search or remove some filters.</p>
            <a href="listings.php" class="btn btn-ghost">Clear all filters</a>
        </div>
    <?php else: ?>

    <!-- Listings grid -->
    <div class="row g-4">
        <?php foreach ($listings as $p): ?>
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

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <nav class="mt-5">
            <ul class="pagination justify-content-center">
                <?php
                // Build query string preserving filters
                $qs = http_build_query(array_filter([
                    'city' => $city,
                    'type' => $type,
                    'max_rent' => $max_rent,
                ], fn($v) => $v !== ''));
                $qsAmp = $qs === '' ? '' : '&' . $qs;
                ?>

                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page-1 ?><?= $qsAmp ?>">
                        ← Previous
                    </a>
                </li>

                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="?page=<?= $i ?><?= $qsAmp ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>

                <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                    <a class="page-link" href="?page=<?= $page+1 ?><?= $qsAmp ?>">
                        Next →
                    </a>
                </li>
            </ul>
        </nav>
    <?php endif; ?>

    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>