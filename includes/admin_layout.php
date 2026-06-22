<?php
/**
 * Admin layout wrapper — sticky sidebar + sticky tab bar + filter drawer.
 *
 * Usage:
 *   $pageTitle  = 'Agents';
 *   $activeNav  = 'agents';
 *   $pageTabs   = [
 *       ['label' => 'All',      'href' => '?tab=all',      'active' => $tab==='all',      'count' => 12],
 *       ['label' => 'Assigned', 'href' => '?tab=assigned', 'active' => $tab==='assigned', 'count' => 7],
 *       ['label' => 'Pending',  'href' => '?tab=pending',  'active' => $tab==='pending',  'count' => 5],
 *   ];
 *   $filterContent = '<input ... />';   // optional: HTML for filter drawer
 *
 *   ob_start();
 *   ?> ... your page content ...  <?php
 *   $pageContent = ob_get_clean();
 *   require __DIR__ . '/../includes/admin_layout.php';
 */

require_once __DIR__ . '/auth.php';
require_role('admin');

$pageTitle     = $pageTitle     ?? 'Admin';
$activeNav     = $activeNav     ?? '';
$pageTabs      = $pageTabs      ?? [];
$filterContent = $filterContent ?? '';
$pageContent   = $pageContent   ?? '';
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
    <button type="button"
            class="topbar-toggle"
            id="sidebarToggle"
            data-tooltip="Hide sidebar"
            aria-label="Toggle sidebar">
        <i class="bi bi-list"></i>
    </button>
    <a href="/rentbridge/admin/dashboard.php" class="topbar-brand">
        <span class="topbar-logo">R</span>
        <span class="topbar-name">RentBridge</span>
        <span class="topbar-divider">·</span>
        <span class="topbar-tag">Admin Panel</span>
    </a>
    <div class="topbar-right">
        <span class="topbar-clock">
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
            <a href="/rentbridge/admin/students.php"
            class="sidebar-link <?= $activeNav === 'students' ? 'active' : '' ?>">
                <i class="bi bi-mortarboard-fill"></i>
                <span class="sidebar-label">Students</span>
            </a>
            <a href="/rentbridge/admin/landlords.php"
            class="sidebar-link <?= $activeNav === 'landlords' ? 'active' : '' ?>">
                <i class="bi bi-house-heart-fill"></i>
                <span class="sidebar-label">Landlords</span>
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
                <span class="sidebar-label">Tenancies</span>
            </a>
            
            <a href="/rentbridge/admin/reports.php"
               class="sidebar-link <?= $activeNav === 'flagreports' ? 'active' : '' ?>">
                <i class="bi bi-flag-fill"></i>
                <span class="sidebar-label">Flag Reports</span>
                <?php
                $flagCount = (int)($pdo ?? db())->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'")->fetchColumn();
                if ($flagCount > 0): ?>
                    <span class="sidebar-badge"><?= $flagCount > 9 ? '9+' : $flagCount ?></span>
                <?php endif; ?>
            </a>

            <a href="/rentbridge/admin/statistics/summary.php"
               class="sidebar-link <?= $activeNav === 'reports' ? 'active' : '' ?>">
                <i class="bi bi-bar-chart-fill"></i>
                <span class="sidebar-label">Statistics</span>
            </a>
            <a href="/rentbridge/admin/transfers.php"
               class="sidebar-link <?= $activeNav === 'transfers' ? 'active' : '' ?>">
                <i class="bi bi-arrow-left-right"></i>
                <span class="sidebar-label">Transfers</span>
                <?php
                $xferCount = (int)($pdo ?? db())->query("SELECT COUNT(*) FROM agent_transfer_requests WHERE status = 'pending_admin'")->fetchColumn();
                if ($xferCount > 0): ?>
                    <span class="sidebar-badge"><?= $xferCount > 9 ? '9+' : $xferCount ?></span>
                <?php endif; ?>
            </a>
            <a href="/rentbridge/admin/messages.php"
            class="sidebar-link <?= $activeNav === 'messages' ? 'active' : '' ?>">
                <i class="bi bi-envelope-fill"></i>
                <span class="sidebar-label">Messages</span>
            </a>

            <!-- Help & Info -->
            <div class="sidebar-collapsible">
                <button class="sidebar-link sidebar-collapsible-toggle" type="button">
                    <i class="bi bi-question-circle"></i>
                    <span class="sidebar-label">Help &amp; Info</span>
                    <i class="bi bi-chevron-down sidebar-chevron"></i>
                </button>
                <div class="sidebar-submenu" style="display:none;">
                    <a href="/rentbridge/about.php"        class="sidebar-link sidebar-sublink <?= $activeNav === 'about'        ? 'active' : '' ?>">About RentBridge</a>
                    <a href="/rentbridge/how_it_works.php" class="sidebar-link sidebar-sublink <?= $activeNav === 'how_it_works' ? 'active' : '' ?>">How it works</a>
                    <a href="/rentbridge/faq.php"          class="sidebar-link sidebar-sublink <?= $activeNav === 'faq'          ? 'active' : '' ?>">FAQ</a>
                    <a href="/rentbridge/contact.php"      class="sidebar-link sidebar-sublink <?= $activeNav === 'contact'      ? 'active' : '' ?>">Feedback &amp; Contact</a>
                    <a href="/rentbridge/legal.php"        class="sidebar-link sidebar-sublink <?= $activeNav === 'legal'        ? 'active' : '' ?>">Terms &amp; Conditions</a>
                    <a href="/rentbridge/privacy.php"      class="sidebar-link sidebar-sublink <?= $activeNav === 'privacy'      ? 'active' : '' ?>">Privacy &amp; Security</a>
                </div>
            </div>
        </nav>
        <div class="sidebar-footer">
            <a href="/rentbridge/auth/logout.php" class="sidebar-link sidebar-logout">
                <i class="bi bi-box-arrow-right"></i>
                <span class="sidebar-label">Sign out</span>
            </a>
        </div>
    </aside>
    <button type="button" class="sidebar-edge-btn" id="sidebarEdgeBtn" aria-label="Toggle sidebar">
        <i class="bi bi-chevron-left"></i>
        <i class="bi bi-chevron-right"></i>
    </button>

    <!-- MAIN AREA -->
    <main class="admin-main">

        <!-- Tab bar at top of content area -->
        <?php if (!empty($pageTabs)): ?>
            <div class="admin-tabbar">
                <h1 class="admin-page-title-inline"><?= e($pageTitle) ?></h1>
                <?php foreach ($pageTabs as $tab): ?>
                    <a href="<?= e($tab['href']) ?>"
                       class="admin-tab <?= !empty($tab['active']) ? 'active' : '' ?>">
                        <?= e($tab['label']) ?>
                        <?php if (isset($tab['count'])): ?>
                            <span class="admin-tab-count"><?= (int)$tab['count'] ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>

                <?php if (!empty($filterContent)): ?>
                    <button type="button" class="admin-filter-toggle" id="filterToggle">
                        <i class="bi bi-search"></i>
                        <span>Search</span>
                    </button>
                <?php endif; ?>
            </div>

            <?php if (!empty($filterContent)): ?>
                <div class="admin-filter-drawer" id="filterDrawer">
                    <?= $filterContent ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <!-- Page content -->
        <div class="admin-content">

            <?php if (empty($pageTabs)): ?>
                <!-- For pages without tabs (e.g. Dashboard) -->
                <div class="admin-page-header-noTabs">
                    <h1 class="admin-page-title-inline"><?= e($pageTitle) ?></h1>
                </div>
            <?php endif; ?>

            <?php $flash = get_flash(); if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show">
                    <?= e($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?= $pageContent ?>
        </div>

    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Sidebar controls
(function() {
    const freezeBtn = document.getElementById('sidebarToggle');
    const edgeBtn   = document.getElementById('sidebarEdgeBtn');
    const body = document.body;
    function updateTooltip() {
        freezeBtn.setAttribute('data-tooltip',
            body.classList.contains('sidebar-collapsed') ? 'Show sidebar' : 'Hide sidebar');
    }
    function toggle(freeze) {
        body.classList.toggle('sidebar-collapsed');
        if (freeze) {
            localStorage.setItem('rb-admin-sidebar',
                body.classList.contains('sidebar-collapsed') ? 'collapsed' : 'expanded');
        }
        updateTooltip();
    }
    if (localStorage.getItem('rb-admin-sidebar') === 'collapsed') {
        body.classList.add('sidebar-collapsed');
    }
    updateTooltip();
    freezeBtn.addEventListener('click', function() { toggle(true); });
    if (edgeBtn) edgeBtn.addEventListener('click', function() { toggle(false); });
})();

// Sidebar collapsible (Help & Info)
(function() {
    document.querySelectorAll('.sidebar-collapsible-toggle').forEach(function(btn) {
        var submenu = btn.nextElementSibling;
        if (!submenu) return;
        // Restore saved state
        if (localStorage.getItem('rb-admin-help-open') === '1') {
            submenu.style.display = 'block';
            btn.classList.add('open');
        }
        btn.addEventListener('click', function() {
            var isOpen = submenu.style.display === 'block';
            submenu.style.display = isOpen ? 'none' : 'block';
            btn.classList.toggle('open', !isOpen);
            localStorage.setItem('rb-admin-help-open', isOpen ? '0' : '1');
        });
    });
})();

// Filter drawer toggle
(function() {
    const toggle = document.getElementById('filterToggle');
    const drawer = document.getElementById('filterDrawer');
    if (!toggle || !drawer) return;
    toggle.addEventListener('click', function() {
        drawer.classList.toggle('open');
        toggle.classList.toggle('active');
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
    setInterval(tick, 30000);
})();
</script>
</body>
</html>