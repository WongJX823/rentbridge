<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = db();

$filter = $_GET['status'] ?? 'all_active';
$validStatuses = [
    'all_active',          // shorthand: everything not terminal
    'pending_landlord', 'pending_agent', 'agent_assigned',
    'contract_pending', 'active',
    'rejected_by_landlord', 'completed',
    'cancelled_by_student', 'cancelled_by_landlord', 'cancelled_by_admin',
    'all'
];
if (!in_array($filter, $validStatuses, true)) $filter = 'all_active';

// Optional: highlight bookings needing admin attention (pending_agent + no agent assigned)
$needsAttention = $_GET['attention'] ?? '0';

$where = '1=1';
$params = [];

if ($filter === 'all_active') {
    $where .= " AND b.status IN ('pending_landlord','pending_agent','agent_assigned','contract_pending','active')";
} elseif ($filter !== 'all') {
    $where .= ' AND b.status = ?';
    $params[] = $filter;
}

if ($needsAttention === '1') {
    $where .= " AND b.status = 'pending_agent' AND b.agent_id IS NULL";
}

$stmt = $pdo->prepare("
    SELECT b.*,
           p.title         AS property_title,
           p.city          AS property_city,
           s.full_name     AS student_name,
           s.preferred_name AS student_nickname,
           l.full_name     AS landlord_name,
           a.full_name     AS agent_name,
           (SELECT image_path FROM property_images
             WHERE property_id = p.id
             ORDER BY is_primary DESC, id ASC LIMIT 1) AS image_path
      FROM bookings b
      JOIN properties p ON p.id = b.property_id
      JOIN students   s ON s.user_id = b.student_id
      JOIN landlords  l ON l.user_id = b.landlord_id
      LEFT JOIN agents a ON a.user_id = b.agent_id
     WHERE $where
     ORDER BY
       CASE WHEN b.status = 'pending_agent' AND b.agent_id IS NULL THEN 0 ELSE 1 END,
       b.created_at DESC
");
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Count stuck bookings (needing manual agent assignment)
$stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM bookings WHERE status = 'pending_agent' AND agent_id IS NULL"
);
$stmt->execute();
$stuckCount = (int)$stmt->fetchColumn();

function booking_status_label(string $status): array {
    return match ($status) {
        'pending_landlord'      => ['Awaiting landlord',     'warning'],
        'pending_agent'         => ['Awaiting agent',        'info'],
        'agent_assigned'        => ['Agent confirmed',       'primary'],
        'contract_pending'      => ['Contract pending',      'primary'],
        'active'                => ['Active tenancy',        'success'],
        'rejected_by_landlord'  => ['Rejected by landlord',  'danger'],
        'completed'             => ['Completed',             'secondary'],
        'cancelled_by_student'  => ['Cancelled by student',  'secondary'],
        'cancelled_by_landlord' => ['Cancelled by landlord', 'secondary'],
        'cancelled_by_admin'    => ['Cancelled by admin',    'danger'],
        default                 => [ucfirst($status),        'secondary'],
    };
}

function pretty_filter_label(string $s): string {
    return match ($s) {
        'all_active'            => 'In progress',
        'all'                   => 'All',
        'pending_landlord'      => 'Awaiting landlord',
        'pending_agent'         => 'Awaiting agent',
        'agent_assigned'        => 'Agent confirmed',
        'contract_pending'      => 'Contract pending',
        'active'                => 'Active',
        'completed'             => 'Completed',
        'rejected_by_landlord'  => 'Rejected',
        'cancelled_by_student'  => 'Cancelled (student)',
        'cancelled_by_landlord' => 'Cancelled (landlord)',
        'cancelled_by_admin'    => 'Cancelled (admin)',
        default                 => ucfirst($s),
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Bookings · Admin · RentBridge</title>
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
            <h1 class="mb-1">All bookings</h1>
            <p class="text-secondary mb-0"><?= count($bookings) ?> booking<?= count($bookings) === 1 ? '' : 's' ?> shown</p>
        </div>
        <a href="/rentbridge/admin/dashboard.php" class="btn btn-ghost">
            <i class="bi bi-arrow-left me-1"></i> Back to dashboard
        </a>
    </div>

    <!-- Alert if there are stuck bookings -->
    <?php if ($stuckCount > 0 && $needsAttention !== '1'): ?>
        <div class="alert d-flex align-items-center gap-3 mb-4" style="background:#FFF4D6; border-color:#D4A017; color:#7C5E0A;">
            <i class="bi bi-exclamation-triangle-fill fs-4"></i>
            <div class="flex-grow-1">
                <strong><?= $stuckCount ?> booking<?= $stuckCount === 1 ? '' : 's' ?> need manual agent assignment</strong>
                <div class="small">Auto-assignment failed — likely no eligible agents are available.</div>
            </div>
            <a href="?attention=1" class="btn btn-sm btn-warning">
                Show only these <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
    <?php endif; ?>

    <!-- Status filter tabs -->
    <ul class="nav nav-pills mb-4 flex-wrap">
        <?php foreach (['all_active', 'pending_landlord', 'pending_agent', 'active', 'completed', 'all'] as $s): ?>
            <li class="nav-item">
                <a class="nav-link <?= ($filter === $s && $needsAttention !== '1') ? 'active' : '' ?>"
                   href="?status=<?= $s ?>">
                    <?= e(pretty_filter_label($s)) ?>
                </a>
            </li>
        <?php endforeach; ?>
        <li class="nav-item">
            <a class="nav-link text-warning <?= $needsAttention === '1' ? 'active' : '' ?>"
               href="?attention=1">
                <i class="bi bi-exclamation-triangle"></i> Needs attention
                <?php if ($stuckCount > 0): ?>
                    <span class="badge bg-warning text-dark ms-1"><?= $stuckCount ?></span>
                <?php endif; ?>
            </a>
        </li>
    </ul>

    <?php if (empty($bookings)): ?>
        <div class="text-center py-5 bg-white rounded-3 border">
            <i class="bi bi-clipboard" style="font-size: 3rem; color: var(--rb-line);"></i>
            <h4 class="mt-3">No bookings match this filter</h4>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($bookings as $b):
                [$label, $color] = booking_status_label($b['status']);
                $isStuck = $b['status'] === 'pending_agent' && empty($b['agent_id']);
            ?>
                <div class="col-12">
                    <a href="/rentbridge/admin/booking.php?id=<?= (int)$b['id'] ?>"
                       class="text-decoration-none text-dark d-block">
                        <div class="bg-white border rounded-3 overflow-hidden booking-row <?= $isStuck ? 'booking-row--urgent' : '' ?>">
                            <div class="row g-0">
                                <div class="col-md-3" style="background:linear-gradient(135deg,#E6ECF4,#E4F2EA); min-height: 160px;">
                                    <?php if (!empty($b['image_path'])): ?>
                                        <img src="/rentbridge/<?= e($b['image_path']) ?>"
                                             style="width:100%; height:100%; object-fit:cover;" alt="">
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-9 p-4">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h5 class="mb-1">
                                                <?= e($b['property_title']) ?>
                                                <small class="text-secondary fw-normal">· Booking #<?= (int)$b['id'] ?></small>
                                            </h5>
                                            <div class="text-secondary small">
                                                <i class="bi bi-person"></i> <?= e($b['student_name']) ?>
                                                &nbsp;·&nbsp;
                                                <i class="bi bi-house"></i> <?= e($b['landlord_name']) ?>
                                                <?php if (!empty($b['agent_name'])): ?>
                                                    &nbsp;·&nbsp;
                                                    <i class="bi bi-person-badge"></i> <?= e($b['agent_name']) ?>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <span class="badge bg-<?= $color ?>"><?= e($label) ?></span>
                                    </div>
                                    <div class="row text-center small mt-3">
                                        <div class="col">
                                            <div class="text-secondary text-uppercase">Move in</div>
                                            <strong><?= e(date('d M Y', strtotime($b['start_date']))) ?></strong>
                                        </div>
                                        <div class="col">
                                            <div class="text-secondary text-uppercase">Move out</div>
                                            <strong><?= e(date('d M Y', strtotime($b['end_date']))) ?></strong>
                                        </div>
                                        <div class="col">
                                            <div class="text-secondary text-uppercase">Monthly</div>
                                            <strong class="text-emerald">RM <?= number_format((float)$b['monthly_rent']) ?></strong>
                                        </div>
                                        <div class="col">
                                            <div class="text-secondary text-uppercase">Created</div>
                                            <strong><?= e(date('d M, H:i', strtotime($b['created_at']))) ?></strong>
                                        </div>
                                    </div>
                                    <?php if ($isStuck): ?>
                                        <div class="mt-3 small text-warning fw-semibold">
                                            <i class="bi bi-exclamation-triangle"></i>
                                            Needs manual agent assignment — click to review
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