<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = db();

$filter = $_GET['status'] ?? 'pending_approval';
$validStatuses = ['pending_approval', 'available', 'booked', 'rented', 'hidden', 'rejected', 'all'];
if (!in_array($filter, $validStatuses, true)) $filter = 'pending_approval';

$where = '1=1';
$params = [];
if ($filter !== 'all') {
    $where .= ' AND p.status = ?';
    $params[] = $filter;
}

$stmt = $pdo->prepare("
    SELECT p.id, p.title, p.property_type, p.city, p.state,
           p.monthly_rent, p.status, p.created_at,
           l.full_name AS landlord_name,
           l.preferred_name AS landlord_nickname,
           u.email AS landlord_email,
           (SELECT image_path FROM property_images
             WHERE property_id = p.id
             ORDER BY is_primary DESC, id ASC LIMIT 1) AS image_path
      FROM properties p
      JOIN landlords l ON l.user_id = p.landlord_id
      JOIN users u ON u.id = p.landlord_id
     WHERE $where
     ORDER BY (p.status = 'pending_approval') DESC, p.created_at DESC
");
$stmt->execute($params);
$properties = $stmt->fetchAll();

// Counts for tabs
$counts = [];
foreach (['pending_approval', 'available', 'booked', 'rented', 'hidden', 'rejected'] as $s) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM properties WHERE status = ?");
    $stmt->execute([$s]);
    $counts[$s] = (int)$stmt->fetchColumn();
}
$counts['all'] = array_sum($counts);

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

function pretty_status_label(string $s): string {
    return match ($s) {
        'pending_approval' => 'Pending',
        'all'              => 'All',
        default            => ucfirst($s),
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Property listings · Admin · RentBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include '../includes/header.php'; ?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-end mb-4">
        <div>
            <h1 class="mb-1">Property listings</h1>
            <p class="text-secondary mb-0">Review, approve, or reject submitted properties.</p>
        </div>
        <a href="/rentbridge/admin/dashboard.php" class="btn btn-ghost">
            <i class="bi bi-arrow-left me-1"></i> Back to dashboard
        </a>
    </div>

    <!-- Status filter tabs -->
    <ul class="nav nav-pills mb-4 flex-wrap">
        <?php foreach (['pending_approval', 'available', 'booked', 'rented', 'hidden', 'rejected', 'all'] as $s): ?>
            <li class="nav-item">
                <a class="nav-link <?= $filter === $s ? 'active' : '' ?>"
                   href="?status=<?= $s ?>">
                    <?= e(pretty_status_label($s)) ?>
                    <span class="badge bg-light text-dark ms-1"><?= $counts[$s] ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if (empty($properties)): ?>
        <div class="text-center py-5 bg-white rounded-3 border">
            <i class="bi bi-house" style="font-size: 3rem; color: var(--rb-line);"></i>
            <h4 class="mt-3">No properties in "<?= e(pretty_status_label($filter)) ?>"</h4>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($properties as $p):
                [$badgeLabel, $badgeColor] = prop_status_badge($p['status']);
                $isPending = $p['status'] === 'pending_approval';
            ?>
                <div class="col-12">
                    <a href="/rentbridge/admin/property.php?id=<?= (int)$p['id'] ?>"
                       class="text-decoration-none text-dark d-block">
                        <div class="bg-white border rounded-3 overflow-hidden booking-row <?= $isPending ? 'booking-row--urgent' : '' ?>">
                            <div class="row g-0">
                                <div class="col-md-3" style="background:linear-gradient(135deg,#E6ECF4,#E4F2EA); min-height: 160px;">
                                    <?php if (!empty($p['image_path'])): ?>
                                        <img src="/rentbridge/<?= e($p['image_path']) ?>"
                                             style="width:100%; height:100%; object-fit:cover;" alt="">
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-9 p-4">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="mb-1"><?= e($p['title']) ?></h5>
                                        <span class="badge bg-<?= $badgeColor ?>"><?= e($badgeLabel) ?></span>
                                    </div>
                                    <div class="text-secondary small mb-3">
                                        <i class="bi bi-geo-alt"></i> <?= e($p['city']) ?>, <?= e($p['state']) ?>
                                        &nbsp;·&nbsp;
                                        <i class="bi bi-house"></i> <?= e(ucfirst(str_replace('_',' ', $p['property_type']))) ?>
                                        &nbsp;·&nbsp;
                                        <i class="bi bi-person"></i> <?= e($p['landlord_name']) ?>
                                    </div>
                                    <div class="row text-center small">
                                        <div class="col">
                                            <div class="text-secondary text-uppercase">Monthly rent</div>
                                            <strong class="text-emerald">RM <?= number_format((float)$p['monthly_rent']) ?></strong>
                                        </div>
                                        <div class="col">
                                            <div class="text-secondary text-uppercase">Submitted</div>
                                            <strong><?= e(date('d M Y', strtotime($p['created_at']))) ?></strong>
                                        </div>
                                        <div class="col">
                                            <div class="text-secondary text-uppercase">Landlord email</div>
                                            <strong><small><?= e($p['landlord_email']) ?></small></strong>
                                        </div>
                                    </div>
                                    <?php if ($isPending): ?>
                                        <div class="mt-3 small text-warning fw-semibold">
                                            <i class="bi bi-arrow-right-circle"></i> Click to review &amp; respond
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>