<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = db();

// --- TOTAL COUNTS (no filter) ---
$counts = [
    'students'    => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE primary_role = 'student'")->fetchColumn(),
    'landlords'   => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE primary_role = 'landlord'")->fetchColumn(),
    'agents'      => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE primary_role = 'agent'")->fetchColumn(),
    'properties'  => (int)$pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn(),
    'bookings'    => (int)$pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn(),
    'contracts'   => (int)$pdo->query("SELECT COUNT(*) FROM contracts WHERE status = 'active'")->fetchColumn(),
];

// --- ATTENTION ITEMS (require admin action) ---
$attention = [
    'pending_agents'      => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE primary_role = 'agent' AND status = 'pending'")->fetchColumn(),
    'pending_properties'  => (int)$pdo->query("SELECT COUNT(*) FROM properties WHERE status = 'pending_approval'")->fetchColumn(),
    'stuck_bookings'      => (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending_agent' AND agent_id IS NULL")->fetchColumn(),
];
$totalAttention = $attention['pending_agents'] + $attention['pending_properties'] + $attention['stuck_bookings'];

// --- LAYOUT ---
$pageTitle = 'Dashboard';
$activeNav = 'dashboard';

ob_start();
?>

<p class="text-secondary mb-4">Overall platform statistics.</p>

<?php if ($totalAttention > 0): ?>
    <div class="alert d-flex align-items-center gap-3 mb-4"
         style="background:#FFF4D6; border-color:#D4A017; color:#7C5E0A;">
        <i class="bi bi-exclamation-triangle-fill fs-4"></i>
        <div class="flex-grow-1">
            <strong><?= $totalAttention ?> item<?= $totalAttention === 1 ? '' : 's' ?> need your attention</strong>
            <div class="small">
                <?php if ($attention['pending_agents'] > 0): ?>
                    <?= $attention['pending_agents'] ?> agent<?= $attention['pending_agents'] === 1 ? '' : 's' ?> awaiting approval ·
                <?php endif; ?>
                <?php if ($attention['pending_properties'] > 0): ?>
                    <?= $attention['pending_properties'] ?> propert<?= $attention['pending_properties'] === 1 ? 'y' : 'ies' ?> pending review ·
                <?php endif; ?>
                <?php if ($attention['stuck_bookings'] > 0): ?>
                    <?= $attention['stuck_bookings'] ?> booking<?= $attention['stuck_bookings'] === 1 ? '' : 's' ?> stuck without agent
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Overall stats grid -->
<div class="admin-stat-grid">

    <a href="/rentbridge/admin/students.php" class="admin-stat-card">
        <div class="admin-stat-icon">
            <i class="bi bi-mortarboard-fill"></i>
        </div>
        <div class="admin-stat-value"><?= $counts['students'] ?></div>
        <div class="admin-stat-label">Total students</div>
        <div class="admin-stat-action">View all <i class="bi bi-arrow-right"></i></div>
    </a>

    <a href="/rentbridge/admin/landlords.php" class="admin-stat-card">
        <div class="admin-stat-icon" style="background: #E6ECF4; color: #0F2C52;">
            <i class="bi bi-house-heart-fill"></i>
        </div>
        <div class="admin-stat-value"><?= $counts['landlords'] ?></div>
        <div class="admin-stat-label">Total landlords</div>
        <div class="admin-stat-action">View all <i class="bi bi-arrow-right"></i></div>
    </a>

    <a href="/rentbridge/admin/agents.php" class="admin-stat-card">
        <div class="admin-stat-icon" style="background: #FFF4D6; color: #7C5E0A;">
            <i class="bi bi-person-badge-fill"></i>
        </div>
        <div class="admin-stat-value"><?= $counts['agents'] ?></div>
        <div class="admin-stat-label">Total agents</div>
        <div class="admin-stat-action">View all <i class="bi bi-arrow-right"></i></div>
    </a>

    <a href="/rentbridge/admin/properties.php" class="admin-stat-card">
        <div class="admin-stat-icon" style="background: #E4F2EA; color: #2E8B57;">
            <i class="bi bi-house-door-fill"></i>
        </div>
        <div class="admin-stat-value"><?= $counts['properties'] ?></div>
        <div class="admin-stat-label">Total properties</div>
        <div class="admin-stat-action">View all <i class="bi bi-arrow-right"></i></div>
    </a>

    <a href="/rentbridge/admin/bookings.php" class="admin-stat-card">
        <div class="admin-stat-icon" style="background: #F4F4EE; color: #6c5e3a;">
            <i class="bi bi-clipboard-data-fill"></i>
        </div>
        <div class="admin-stat-value"><?= $counts['bookings'] ?></div>
        <div class="admin-stat-label">Total bookings</div>
        <div class="admin-stat-action">View all <i class="bi bi-arrow-right"></i></div>
    </a>

    <a href="/rentbridge/admin/bookings.php?tab=active" class="admin-stat-card">
        <div class="admin-stat-icon" style="background: #E4F2EA; color: #1e6b3f;">
            <i class="bi bi-check-circle-fill"></i>
        </div>
        <div class="admin-stat-value"><?= $counts['contracts'] ?></div>
        <div class="admin-stat-label">Active tenancies</div>
        <div class="admin-stat-action">View all <i class="bi bi-arrow-right"></i></div>
    </a>

</div>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/admin_layout.php';