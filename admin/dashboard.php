<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = db();
$counts = [
    'students'        => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE primary_role='student'")->fetchColumn(),
    'landlords'       => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE primary_role='landlord'")->fetchColumn(),
    'agents_active'   => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE primary_role='agent' AND status='active'")->fetchColumn(),
    'agents_pending'  => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE primary_role='agent' AND status='pending'")->fetchColumn(),
    'props_pending'   => (int)$pdo->query("SELECT COUNT(*) FROM properties WHERE status='pending_approval'")->fetchColumn(),
    'bookings_active' => (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status IN ('pending_landlord','pending_agent','agent_assigned','contract_pending','active')")->fetchColumn(),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard · RentBridge</title>
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
    <h1>System <em>overview.</em></h1>
    <p class="text-secondary mb-4">Admin dashboard</p>

    <!-- 3×2 grid: 3 USER STATS (top row) + 3 ACTION ITEMS (bottom row) -->
    <div class="row g-4">

        <!-- ROW 1: Resource counts (informational) -->
        <div class="col-md-4">
            <a href="/rentbridge/admin/users.php?role=student" class="stat-card">
                <div class="stat-card__icon" style="background: #E6ECF4; color: var(--rb-navy);">
                    <i class="bi bi-mortarboard-fill"></i>
                </div>
                <div class="stat-card__value"><?= $counts['students'] ?></div>
                <div class="stat-card__label">Students</div>
                <div class="stat-card__action">View list <i class="bi bi-arrow-right"></i></div>
            </a>
        </div>

        <div class="col-md-4">
            <a href="/rentbridge/admin/users.php?role=landlord" class="stat-card">
                <div class="stat-card__icon" style="background: #E6ECF4; color: var(--rb-navy);">
                    <i class="bi bi-house-fill"></i>
                </div>
                <div class="stat-card__value"><?= $counts['landlords'] ?></div>
                <div class="stat-card__label">Landlords</div>
                <div class="stat-card__action">View list <i class="bi bi-arrow-right"></i></div>
            </a>
        </div>

        <div class="col-md-4">
            <a href="/rentbridge/admin/agents.php?status=active" class="stat-card">
                <div class="stat-card__icon" style="background: #E4F2EA; color: var(--rb-emerald-dark);">
                    <i class="bi bi-person-badge-fill"></i>
                </div>
                <div class="stat-card__value"><?= $counts['agents_active'] ?></div>
                <div class="stat-card__label">Active agents</div>
                <div class="stat-card__action">View list <i class="bi bi-arrow-right"></i></div>
            </a>
        </div>

        <!-- ROW 2: Action items (need attention) -->
        <div class="col-md-4">
            <a href="/rentbridge/admin/agents.php?status=pending"
               class="stat-card <?= $counts['agents_pending'] > 0 ? 'stat-card--warning' : '' ?>">
                <div class="stat-card__icon" style="background: #FFF4D6; color: #B7791F;">
                    <i class="bi bi-hourglass-split"></i>
                </div>
                <div class="stat-card__value"><?= $counts['agents_pending'] ?></div>
                <div class="stat-card__label">Pending agent applications</div>
                <div class="stat-card__action">Review <i class="bi bi-arrow-right"></i></div>
            </a>
        </div>

        <div class="col-md-4">
            <a href="/rentbridge/admin/properties.php?status=pending_approval"
               class="stat-card <?= $counts['props_pending'] > 0 ? 'stat-card--warning' : '' ?>">
                <div class="stat-card__icon" style="background: #FFF4D6; color: #B7791F;">
                    <i class="bi bi-house-add-fill"></i>
                </div>
                <div class="stat-card__value"><?= $counts['props_pending'] ?></div>
                <div class="stat-card__label">Pending property listings</div>
                <div class="stat-card__action">Review <i class="bi bi-arrow-right"></i></div>
            </a>
        </div>

        <div class="col-md-4">
            <a href="/rentbridge/admin/bookings.php" class="stat-card">
                <div class="stat-card__icon" style="background: #E4F2EA; color: var(--rb-emerald-dark);">
                    <i class="bi bi-clipboard-data-fill"></i>
                </div>
                <div class="stat-card__value"><?= $counts['bookings_active'] ?></div>
                <div class="stat-card__label">Active bookings</div>
                <div class="stat-card__action">View all <i class="bi bi-arrow-right"></i></div>
            </a>
        </div>
    </div>

    <div class="text-end mt-5">
        <a href="/rentbridge/auth/logout.php" class="btn btn-outline-dark">
            <i class="bi bi-box-arrow-right me-1"></i> Sign out
        </a>
    </div>
</div>

</body>
</html>