<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = db();

// Fetch summary counts
$counts = [];

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE primary_role = 'student' AND status = 'active'");
$counts['students'] = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE primary_role = 'landlord' AND status = 'active'");
$counts['landlords'] = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE primary_role = 'agent' AND status = 'active'");
$counts['agents_active'] = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE primary_role = 'agent' AND status = 'pending'");
$counts['agents_pending'] = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM properties WHERE status = 'pending_approval'");
$counts['properties_pending'] = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM properties WHERE status = 'available'");
$counts['properties_available'] = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status IN ('pending_landlord','pending_agent','agent_verifying','agent_verified','contract_pending','active')");
$counts['bookings_active'] = (int)$stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending_agent' AND agent_id IS NULL");
$counts['bookings_stuck'] = (int)$stmt->fetchColumn();

// Setup admin layout
$pageTitle = 'Dashboard';
$activeNav = 'dashboard';

ob_start();
?>

<!-- Welcome -->
<div class="mb-4">
    <p class="text-secondary mb-0">Welcome back. Here's what's happening across RentBridge today.</p>
</div>

<!-- Stuck bookings alert -->
<?php if ($counts['bookings_stuck'] > 0): ?>
    <div class="alert d-flex align-items-center gap-3 mb-4" style="background:#FFF4D6; border-color:#D4A017; color:#7C5E0A;">
        <i class="bi bi-exclamation-triangle-fill fs-4"></i>
        <div class="flex-grow-1">
            <strong><?= $counts['bookings_stuck'] ?> booking<?= $counts['bookings_stuck'] === 1 ? '' : 's' ?> need manual agent assignment</strong>
            <div class="small">Auto-assignment couldn't find an eligible agent.</div>
        </div>
        <a href="/rentbridge/admin/bookings.php?attention=1" class="btn btn-sm btn-warning">
            Review now <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>
<?php endif; ?>

<!-- Stat cards grid -->
<div class="admin-stat-grid">

    <a href="/rentbridge/admin/users.php?role=student" class="admin-stat-card">
        <div class="admin-stat-icon">
            <i class="bi bi-mortarboard-fill"></i>
        </div>
        <div class="admin-stat-value"><?= $counts['students'] ?></div>
        <div class="admin-stat-label">Active students</div>
        <div class="admin-stat-action">View all <i class="bi bi-arrow-right"></i></div>
    </a>

    <a href="/rentbridge/admin/users.php?role=landlord" class="admin-stat-card">
        <div class="admin-stat-icon" style="background: #E6ECF4; color: #0F2C52;">
            <i class="bi bi-house-heart-fill"></i>
        </div>
        <div class="admin-stat-value"><?= $counts['landlords'] ?></div>
        <div class="admin-stat-label">Active landlords</div>
        <div class="admin-stat-action">View all <i class="bi bi-arrow-right"></i></div>
    </a>

    <a href="/rentbridge/admin/agents.php?status=active" class="admin-stat-card">
        <div class="admin-stat-icon" style="background: #FFF4D6; color: #7C5E0A;">
            <i class="bi bi-person-badge-fill"></i>
        </div>
        <div class="admin-stat-value"><?= $counts['agents_active'] ?></div>
        <div class="admin-stat-label">Active agents</div>
        <div class="admin-stat-action">View all <i class="bi bi-arrow-right"></i></div>
    </a>

    <a href="/rentbridge/admin/agents.php?status=pending" class="admin-stat-card">
        <div class="admin-stat-icon" style="background: #FFF4D6; color: #D4A017;">
            <i class="bi bi-hourglass-split"></i>
        </div>
        <div class="admin-stat-value"><?= $counts['agents_pending'] ?></div>
        <div class="admin-stat-label">Pending agent approvals</div>
        <div class="admin-stat-action">Review <i class="bi bi-arrow-right"></i></div>
    </a>

    <a href="/rentbridge/admin/properties.php?status=pending_approval" class="admin-stat-card">
        <div class="admin-stat-icon" style="background: #FFF4D6; color: #D4A017;">
            <i class="bi bi-house-add-fill"></i>
        </div>
        <div class="admin-stat-value"><?= $counts['properties_pending'] ?></div>
        <div class="admin-stat-label">Pending property listings</div>
        <div class="admin-stat-action">Review <i class="bi bi-arrow-right"></i></div>
    </a>

    <a href="/rentbridge/admin/bookings.php" class="admin-stat-card">
        <div class="admin-stat-icon" style="background: #E4F2EA; color: #2E8B57;">
            <i class="bi bi-clipboard-check-fill"></i>
        </div>
        <div class="admin-stat-value"><?= $counts['bookings_active'] ?></div>
        <div class="admin-stat-label">Active bookings</div>
        <div class="admin-stat-action">View all <i class="bi bi-arrow-right"></i></div>
    </a>

</div>

<!-- Quick actions placeholder (future: recent activity feed) -->
<div class="bg-white border rounded-3 p-4">
    <h5 class="mb-3"><i class="bi bi-lightning-charge-fill text-warning"></i> Quick actions</h5>
    <p class="text-secondary small mb-3">Common admin tasks:</p>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/rentbridge/admin/agents.php?status=pending" class="btn btn-sm btn-outline-dark">
            <i class="bi bi-person-check me-1"></i> Approve agents
        </a>
        <a href="/rentbridge/admin/properties.php?status=pending_approval" class="btn btn-sm btn-outline-dark">
            <i class="bi bi-house-check me-1"></i> Review properties
        </a>
        <a href="/rentbridge/admin/bookings.php?attention=1" class="btn btn-sm btn-outline-dark">
            <i class="bi bi-exclamation-triangle me-1"></i> Stuck bookings
        </a>
        <a href="/rentbridge/admin/reports.php" class="btn btn-sm btn-outline-dark">
            <i class="bi bi-bar-chart me-1"></i> View reports
        </a>
    </div>
</div>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/admin_layout.php';