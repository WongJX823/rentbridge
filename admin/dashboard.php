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
    'tenancies'    => (int)$pdo->query("SELECT COUNT(*) FROM tenancies")->fetchColumn(),
    'contracts'   => (int)$pdo->query("SELECT COUNT(*) FROM contracts WHERE status = 'active'")->fetchColumn(),
];

// --- ATTENTION ITEMS (require admin action) ---
$attention = [
    'pending_agents'      => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE primary_role = 'agent' AND status = 'pending'")->fetchColumn(),
    'pending_properties'  => (int)$pdo->query("SELECT COUNT(*) FROM properties WHERE status = 'pending_approval'")->fetchColumn(),
    'needs_admin_props'   => (int)$pdo->query("SELECT COUNT(*) FROM properties WHERE status = 'needs_admin'")->fetchColumn(),
    'aborted_inspections' => (int)$pdo->query("SELECT COUNT(*) FROM tenancies WHERE status = 'inspection_aborted'")->fetchColumn(),
    'pending_transfers'   => (int)$pdo->query("SELECT COUNT(*) FROM agent_transfer_requests WHERE status = 'pending_admin'")->fetchColumn(),
    'pending_reports'     => (int)$pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'")->fetchColumn(),
];
$totalAttention = array_sum($attention);

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
            <div class="small d-flex flex-wrap gap-2 mt-1">
                <?php if ($attention['pending_agents'] > 0): ?>
                    <a href="/rentbridge/admin/agents.php?tab=pending" class="badge bg-warning text-dark text-decoration-none">
                        <i class="bi bi-person-badge me-1"></i>
                        <?= $attention['pending_agents'] ?> agent<?= $attention['pending_agents'] === 1 ? '' : 's' ?> awaiting approval
                    </a>
                <?php endif; ?>
                <?php if ($attention['pending_properties'] > 0): ?>
                    <a href="/rentbridge/admin/properties.php?status=pending_approval" class="badge bg-warning text-dark text-decoration-none">
                        <i class="bi bi-house me-1"></i>
                        <?= $attention['pending_properties'] ?> propert<?= $attention['pending_properties'] === 1 ? 'y' : 'ies' ?> pending review
                    </a>
                <?php endif; ?>
                <?php if ($attention['needs_admin_props'] > 0): ?>
                    <a href="/rentbridge/admin/properties.php?status=needs_admin" class="badge bg-danger text-white text-decoration-none">
                        <i class="bi bi-exclamation-circle me-1"></i>
                        <?= $attention['needs_admin_props'] ?> propert<?= $attention['needs_admin_props'] === 1 ? 'y' : 'ies' ?> — no agents available
                    </a>
                <?php endif; ?>
                <?php if ($attention['aborted_inspections'] > 0): ?>
                    <a href="/rentbridge/admin/tenancies.php?status=inspection_aborted" class="badge bg-danger text-white text-decoration-none">
                        <i class="bi bi-x-octagon me-1"></i>
                        <?= $attention['aborted_inspections'] ?> inspection<?= $attention['aborted_inspections'] === 1 ? '' : 's' ?> aborted — needs resolution
                    </a>
                <?php endif; ?>
                <?php if ($attention['pending_transfers'] > 0): ?>
                    <a href="/rentbridge/admin/transfers.php?filter=pending_admin" class="badge bg-warning text-dark text-decoration-none">
                        <i class="bi bi-arrow-left-right me-1"></i>
                        <?= $attention['pending_transfers'] ?> case transfer<?= $attention['pending_transfers'] === 1 ? '' : 's' ?> awaiting review
                    </a>
                <?php endif; ?>
                <?php if ($attention['pending_reports'] > 0): ?>
                    <a href="/rentbridge/admin/reports.php?filter_status=pending" class="badge bg-danger text-white text-decoration-none">
                        <i class="bi bi-flag-fill me-1"></i>
                        <?= $attention['pending_reports'] ?> flag report<?= $attention['pending_reports'] === 1 ? '' : 's' ?> unreviewed
                    </a>
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

    <a href="/rentbridge/admin/tenancies.php" class="admin-stat-card">
        <div class="admin-stat-icon" style="background: #F4F4EE; color: #6c5e3a;">
            <i class="bi bi-clipboard-data-fill"></i>
        </div>
        <div class="admin-stat-value"><?= $counts['tenancies'] ?></div>
        <div class="admin-stat-label">Total tenancies</div>
        <div class="admin-stat-action">View all <i class="bi bi-arrow-right"></i></div>
    </a>

    <a href="/rentbridge/admin/tenancies.php?tab=active" class="admin-stat-card">
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