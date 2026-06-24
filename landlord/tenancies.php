<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('landlord');

$pdo = db();
$stmt = $pdo->prepare("
    SELECT b.*,
           p.title       AS property_title,
           p.city        AS property_city,
           s.full_name   AS student_name,
           s.matric_no   AS student_matric,
           a.full_name   AS agent_name,
           a.department  AS agent_department,
           (SELECT image_path FROM property_images
             WHERE property_id = p.id
             ORDER BY is_primary DESC, id ASC LIMIT 1) AS image_path
      FROM tenancies b
      JOIN properties p ON p.id = b.property_id
      JOIN students   s ON s.user_id = b.student_id
      LEFT JOIN agents a ON a.user_id = b.agent_id
     WHERE b.landlord_id = ?
     ORDER BY (b.status = 'pending_landlord') DESC,
              b.created_at DESC
");
$stmt->execute([current_user_id()]);
$tenancies = $stmt->fetchAll();

// Reuse status helper (same as student tenancies page)
function status_label(string $status): array {
    return match ($status) {
        'pending_landlord'      => ['Needs your response', 'warning'],
        'rejected_by_landlord'  => ['You rejected',         'danger'],
        'pending_agent'         => ['Waiting for agent',    'info'],
        'agent_assigned'        => ['Agent assigned',       'primary'],
        'contract_pending'      => ['Contract pending',     'primary'],
        'active'                => ['Active tenancy',       'success'],
        'completed'             => ['Completed',            'secondary'],
        'cancelled_by_student'  => ['Cancelled by student', 'secondary'],
        'cancelled_by_landlord' => ['You cancelled',        'secondary'],
        'cancelled_by_admin'    => ['Cancelled by admin',   'danger'],
        default                 => [ucfirst($status),       'secondary'],
    };
}

// Count pending for the banner
$pendingCount = 0;
foreach ($tenancies as $b) {
    if ($b['status'] === 'pending_landlord') $pendingCount++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Tenancies · Landlord · RentBridge</title>
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
            <h1 class="mb-1">Tenancy requests</h1>
            <p class="text-secondary mb-0">
                <?= count($tenancies) ?> tenancy<?= count($tenancies) === 1 ? '' : 's' ?> total
            </p>
        </div>
        <a href="/rentbridge/landlord/dashboard.php" class="btn btn-ghost">
            <i class="bi bi-arrow-left me-1"></i> Back to dashboard
        </a>
    </div>

    <?php if ($pendingCount > 0): ?>
        <div class="alert d-flex align-items-center gap-3" style="background:#FFF4D6; border-color:#D4A017; color:#7C5E0A;">
            <i class="bi bi-bell-fill fs-4"></i>
            <div>
                <strong><?= $pendingCount ?> tenancy request<?= $pendingCount === 1 ? '' : 's' ?> waiting for your response</strong>
                <div class="small">Students are waiting to hear back. Approve or reject below.</div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (empty($tenancies)): ?>
        <div class="text-center py-5 bg-white rounded-3 border">
            <i class="bi bi-inbox" style="font-size: 3rem; color: var(--rb-line);"></i>
            <h4 class="mt-3">No tenancy requests yet</h4>
            <p class="text-secondary">Once a student books your property, you'll see it here.</p>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($tenancies as $b):
                [$label, $color] = status_label($b['status']);
                $isPending = $b['status'] === 'pending_landlord';
            ?>
                <div class="col-12">
                    <a href="/rentbridge/landlord/tenancy.php?id=<?= (int)$b['id'] ?>"
                       class="text-decoration-none text-dark d-block">
                        <div class="bg-white border rounded-3 overflow-hidden tenancy-row <?= $isPending ? 'tenancy-row--urgent' : '' ?>">
                            <div class="row g-0">
                                <div class="col-md-3" style="background:linear-gradient(135deg,#E6ECF4,#E4F2EA); min-height: 160px;">
                                    <?php if (!empty($b['image_path'])): ?>
                                        <img src="/rentbridge/<?= e($b['image_path']) ?>"
                                             style="width:100%; height:100%; object-fit:cover;" alt="">
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-9 p-4">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="mb-1"><?= e($b['property_title']) ?></h5>
                                        <span class="badge bg-<?= $color ?>"><?= e($label) ?></span>
                                    </div>
                                    <div class="text-secondary small mb-3">
                                        <i class="bi bi-person"></i>
                                        <?= e($b['student_name']) ?> · <?= e($b['student_matric']) ?>
                                        &nbsp;·&nbsp;
                                        <i class="bi bi-geo-alt"></i> <?= e($b['property_city']) ?>
                                    </div>
                                    <div class="row text-center small mb-2">
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
                                            <div class="text-secondary text-uppercase">Requested</div>
                                            <strong><?= e(date('d M, H:i', strtotime($b['created_at']))) ?></strong>
                                        </div>
                                    </div>
                                    <!-- Agent assignment status -->
                                    <?php if (in_array($b['status'], ['pending_agent','agent_assigned','agent_verifying','agent_verified','contract_pending','active'], true)): ?>
                                        <div class="small mt-3 p-2 rounded-3" style="background:var(--rb-cream); border:1px solid var(--rb-line);">
                                            <?php if (!empty($b['agent_name'])): ?>
                                                <i class="bi bi-person-badge text-emerald-dark"></i>
                                                <strong>Witness agent:</strong>
                                                <?= e($b['agent_name']) ?>
                                                <span class="text-secondary">
                                                    · <?= e($b['agent_department']) ?>
                                                </span>
                                                <?php if ($b['status'] === 'pending_agent'): ?>
                                                    <span class="badge bg-warning text-dark ms-1">🟡 awaiting confirmation</span>
                                                <?php elseif ($b['status'] === 'agent_verifying'): ?>
                                                    <span class="badge bg-info text-dark ms-1">🔍 inspecting property</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success ms-1">✓ confirmed</span>
                                                <?php endif; ?>
                                            <?php elseif ($b['status'] === 'pending_agent'): ?>
                                                <i class="bi bi-search text-secondary"></i>
                                                <span class="text-secondary">Looking for a UTeM staff agent…</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

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