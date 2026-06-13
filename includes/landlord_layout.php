<?php
/**
 * Landlord layout wrapper — sticky sidebar + sticky tab bar.
 *
 * Usage:
 *   $pageTitle  = 'Dashboard';
 *   $activeNav  = 'dashboard';
 *   ob_start();
 *   ?> ... content ... <?php
 *   $pageContent = ob_get_clean();
 *   require __DIR__ . '/../includes/landlord_layout.php';
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/chat.php';
require_role('landlord');

$pageTitle     = $pageTitle     ?? 'Landlord';
$activeNav     = $activeNav     ?? '';
$pageTabs      = $pageTabs      ?? [];
$filterContent = $filterContent ?? '';
$pageContent   = $pageContent   ?? '';

$userId = current_user_id();
$pdo = db();
$stmt = $pdo->prepare("SELECT preferred_name, full_name FROM landlords WHERE user_id = ?");
$stmt->execute([$userId]);
$me = $stmt->fetch() ?: [];
$myName = ($me['preferred_name'] ?? '') ?: ($me['full_name'] ?? 'Landlord');

$unreadChat = chat_unread_total($userId);
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$userId]);
$unreadNotif = (int)$stmt->fetchColumn();
$totalUnread = $unreadChat + $unreadNotif;

// Pending tenancy requests count (for sidebar badge)
$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE landlord_id = ? AND status = 'pending_landlord'");
$stmt->execute([$userId]);
$pendingRequests = (int)$stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> · Landlord · RentBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/rentbridge/assets/css/style.css" rel="stylesheet">
    <link href="/rentbridge/assets/css/student.css" rel="stylesheet">
</head>
<body class="landlord-body">

<header class="user-topbar">
    <button type="button" class="topbar-toggle" id="sidebarToggle"
            data-tooltip="Hide sidebar" aria-label="Toggle sidebar">
        <i class="bi bi-list"></i>
    </button>
    <a href="/rentbridge/landlord/dashboard.php" class="topbar-brand">
        <span class="topbar-logo">R</span>
        <span class="topbar-name">RentBridge</span>
        <span style="opacity:0.5; margin: 0 4px;">·</span>
        <span style="font-family:'Manrope',sans-serif; font-size:0.85rem; font-weight:500;
                     letter-spacing:0.5px; text-transform:uppercase; opacity:0.7;">Landlord</span>
    </a>
    <div class="topbar-right">
        <span class="topbar-greeting d-none d-md-inline">
            Hello, <?= e($myName) ?>
        </span>
    </div>
</header>

<div class="user-shell">

    <aside class="user-sidebar" id="userSidebar">
        <nav class="sidebar-nav">
            <a href="/rentbridge/landlord/dashboard.php"
               class="sidebar-link <?= $activeNav === 'dashboard' ? 'active' : '' ?>">
                <i class="bi bi-house-door-fill"></i>
                <span class="sidebar-label">Browse</span>
            </a>
            <a href="/rentbridge/landlord/saved.php"
               class="sidebar-link <?= $activeNav === 'saved' ? 'active' : '' ?>">
                <i class="bi bi-bookmark-heart-fill"></i>
                <span class="sidebar-label">Saved</span>
            </a>
            <a href="/rentbridge/landlord/properties.php"
               class="sidebar-link <?= $activeNav === 'properties' ? 'active' : '' ?>">
                <i class="bi bi-buildings-fill"></i>
                <span class="sidebar-label">Property Register</span>
                <?php if ($pendingRequests > 0): ?>
                    <span class="sidebar-badge"><?= $pendingRequests ?></span>
                <?php endif; ?>
            </a>
            <a href="/rentbridge/chat.php"
               class="sidebar-link <?= $activeNav === 'chat' ? 'active' : '' ?>">
                <i class="bi bi-chat-dots-fill"></i>
                <span class="sidebar-label">Chat &amp; Notif</span>
                <?php if ($totalUnread > 0): ?>
                    <span class="sidebar-badge"><?= $totalUnread > 9 ? '9+' : $totalUnread ?></span>
                <?php endif; ?>
            </a>
            <a href="/rentbridge/landlord/profile.php"
               class="sidebar-link <?= $activeNav === 'profile_dashboard' ? 'active' : '' ?>">
                <i class="bi bi-person-circle"></i>
                <span class="sidebar-label">Profile Dashboard</span>
            </a>
            <a href="/rentbridge/about.php"
               class="sidebar-link <?= $activeNav === 'about' ? 'active' : '' ?>">
                <i class="bi bi-info-circle-fill"></i>
                <span class="sidebar-label">About RentBridge</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <a href="/rentbridge/landlord/settings.php"
               class="sidebar-link <?= $activeNav === 'settings' ? 'active' : '' ?>">
                <i class="bi bi-gear-fill"></i>
                <span class="sidebar-label">Settings</span>
            </a>
            <a href="/rentbridge/auth/logout.php" class="sidebar-link sidebar-logout">
                <i class="bi bi-box-arrow-right"></i>
                <span class="sidebar-label">Sign out</span>
            </a>
        </div>
    </aside>

    <main class="user-main">

        <?php if (!empty($pageTabs)): ?>
            <div class="user-tabbar">
                <h1 class="user-page-title-inline"><?= e($pageTitle) ?></h1>
                <?php foreach ($pageTabs as $tab): ?>
                    <a href="<?= e($tab['href']) ?>"
                       class="user-tab <?= !empty($tab['active']) ? 'active' : '' ?>">
                        <?= e($tab['label']) ?>
                        <?php if (isset($tab['count'])): ?>
                            <span class="user-tab-count"><?= (int)$tab['count'] ?></span>
                        <?php endif; ?>
                    </a>
                <?php endforeach; ?>
                <?php if (!empty($filterContent)): ?>
                    <button type="button" class="user-filter-toggle" id="filterToggle">
                        <i class="bi bi-search"></i>
                        <span>Search</span>
                    </button>
                <?php endif; ?>
            </div>
            <?php if (!empty($filterContent)): ?>
                <div class="user-filter-drawer" id="filterDrawer">
                    <?= $filterContent ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <div class="user-content">
            <?php if (empty($pageTabs)): ?>
                <div class="user-page-header-noTabs">
                    <h1 class="user-page-title-inline"><?= e($pageTitle) ?></h1>
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
(function() {
    const toggle = document.getElementById('sidebarToggle');
    const body = document.body;
    function updateTooltip() {
        toggle.setAttribute('data-tooltip',
            body.classList.contains('sidebar-collapsed') ? 'Show sidebar' : 'Hide sidebar');
    }
    if (localStorage.getItem('rb-user-sidebar') === 'collapsed') {
        body.classList.add('sidebar-collapsed');
    }
    updateTooltip();
    toggle.addEventListener('click', function() {
        body.classList.toggle('sidebar-collapsed');
        localStorage.setItem('rb-user-sidebar',
            body.classList.contains('sidebar-collapsed') ? 'collapsed' : 'expanded');
        updateTooltip();
    });
})();
(function() {
    const toggle = document.getElementById('filterToggle');
    const drawer = document.getElementById('filterDrawer');
    if (!toggle || !drawer) return;
    toggle.addEventListener('click', function() {
        drawer.classList.toggle('open');
        toggle.classList.toggle('active');
    });
})();
</script>
</body>
</html>