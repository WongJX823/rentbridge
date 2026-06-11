<?php
/**
 * Admin layout wrapper.
 *
 * Usage in each admin page:
 *   $pageTitle  = 'Properties';
 *   $activeNav  = 'properties';     // sidebar item highlight
 *   $pageTabs   = [                  // optional sub-tabs
 *       ['label' => 'All',     'href' => '?tab=all',     'active' => $tab === 'all'],
 *       ['label' => 'Booked',  'href' => '?tab=booked',  'active' => $tab === 'booked'],
 *   ];
 *   ob_start();
 *   ?>
 *   ... content HTML ...
 *   <?php
 *   $pageContent = ob_get_clean();
 *   require __DIR__ . '/../includes/admin_layout.php';
 */

require_once __DIR__ . '/auth.php';
require_role('admin');

$pageTitle  = $pageTitle  ?? 'Admin';
$activeNav  = $activeNav  ?? '';
$pageTabs   = $pageTabs   ?? [];
$pageContent= $pageContent?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> · Admin · RentBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/rentbridge/assets/css/style.css" rel="stylesheet">
    <link href="/rentbridge/assets/css/admin.css" rel="stylesheet">
</head>
<body class="admin-body">

<!-- TOP BAR -->
<header class="admin-topbar">
    <button type="button" class="topbar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
        <i class="bi bi-list"></i>
    </button>
    <a href="/rentbridge/admin/dashboard.php" class="topbar-brand">
        <span class="topbar-logo">R</span>
        <span class="topbar-name">RentBridge</span>
        <span class="topbar-divider">·</span>
        <span class="topbar-tag">Admin Panel</span>
    </a>
    <div class="topbar-right">
        <span id="topbarClock" class="topbar-clock">
            <i class="bi bi-clock"></i> <span id="clockText">—</span>
        </span>
    </div>
</header>

<div class="admin-shell">

    <!-- SIDEBAR -->
    <aside class="admin-sidebar" id="adminSidebar">
        <nav class="sidebar-nav">
            <a href="/rentbridge/admin/dashboard.php"
               class="sidebar-link <?= $activeNav === 'dashboard' ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2-fill"></i>
                <span class="sidebar-label">Dashboard</span>
            </a>
            <a href="/rentbridge/admin/users.php"
               class="sidebar-link <?= $activeNav === 'users' ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i>
                <span class="sidebar-label">Users</span>
            </a>
            <a href="/rentbridge/admin/agents.php"
               class="sidebar-link <?= $activeNav === 'agents' ? 'active' : '' ?>">
                <i class="bi bi-person-badge-fill"></i>
                <span class="sidebar-label">Agents</span>
            </a>
            <a href="/rentbridge/admin/properties.php"
               class="sidebar-link <?= $activeNav === 'properties' ? 'active' : '' ?>">
                <i class="bi bi-house-door-fill"></i>
                <span class="sidebar-label">Properties</span>
            </a>
            <a href="/rentbridge/admin/bookings.php"
               class="sidebar-link <?= $activeNav === 'bookings' ? 'active' : '' ?>">
                <i class="bi bi-clipboard-data-fill"></i>
                <span class="sidebar-label">Bookings</span>
            </a>
            <a href="/rentbridge/admin/reports.php"
               class="sidebar-link <?= $activeNav === 'reports' ? 'active' : '' ?>">
                <i class="bi bi-bar-chart-fill"></i>
                <span class="sidebar-label">Statistics</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <a href="/rentbridge/auth/logout.php" class="sidebar-link sidebar-logout">
                <i class="bi bi-box-arrow-right"></i>
                <span class="sidebar-label">Sign out</span>
            </a>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="admin-main">

        <!-- Page header (title + tabs) -->
        <div class="admin-page-header">
            <h1 class="admin-page-title"><?= e($pageTitle) ?></h1>

            <?php if (!empty($pageTabs)): ?>
                <nav class="admin-tabs">
                    <?php foreach ($pageTabs as $tab): ?>
                        <a href="<?= e($tab['href']) ?>"
                           class="admin-tab <?= !empty($tab['active']) ? 'active' : '' ?>">
                            <?= e($tab['label']) ?>
                            <?php if (isset($tab['count'])): ?>
                                <span class="admin-tab-count"><?= (int)$tab['count'] ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            <?php endif; ?>
        </div>

        <!-- Page-specific flash message -->
        <?php $flash = get_flash(); if ($flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show">
                <?= e($flash['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Content from page -->
        <?= $pageContent ?>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Sidebar collapse toggle
(function() {
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('adminSidebar');
    const body = document.body;

    // Restore previous state
    if (localStorage.getItem('rb-admin-sidebar') === 'collapsed') {
        body.classList.add('sidebar-collapsed');
    }

    toggle.addEventListener('click', function() {
        body.classList.toggle('sidebar-collapsed');
        localStorage.setItem('rb-admin-sidebar',
            body.classList.contains('sidebar-collapsed') ? 'collapsed' : 'expanded');
    });
})();

// Live clock
(function() {
    const el = document.getElementById('clockText');
    if (!el) return;
    function tick() {
        const now = new Date();
        const opts = { weekday:'short', hour:'2-digit', minute:'2-digit', hour12: true };
        el.textContent = now.toLocaleString('en-MY', opts);
    }
    tick();
    setInterval(tick, 30000);  // update every 30 seconds
})();
</script>
</body>
</html>