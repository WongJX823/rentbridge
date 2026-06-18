<?php
/**
 * Agent layout wrapper — sticky sidebar + sticky tab bar.
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/chat.php';
require_role('agent');

$pageTitle     = $pageTitle     ?? 'Agent';
$activeNav     = $activeNav     ?? '';
$pageTabs      = $pageTabs      ?? [];
$filterContent = $filterContent ?? '';
$pageContent   = $pageContent   ?? '';

$userId = current_user_id();
$pdo = db();
$stmt = $pdo->prepare("SELECT preferred_name, full_name, staff_id, department, current_caseload FROM agents WHERE user_id = ?");
$stmt->execute([$userId]);
$me = $stmt->fetch();
$myName = $me['preferred_name'] ?: $me['full_name'];

$unreadChat = chat_unread_total($userId);
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$userId]);
$unreadNotif = (int)$stmt->fetchColumn();
$totalUnread = $unreadChat + $unreadNotif;

// Urgent case count — for sidebar badge
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM bookings
     WHERE agent_id = ?
       AND status IN ('pending_agent','agent_verifying')
");
$stmt->execute([$userId]);
$urgentCases = (int)$stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> · Agent · RentBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/rentbridge/assets/css/style.css" rel="stylesheet">
    <link href="/rentbridge/assets/css/student.css" rel="stylesheet">
</head>
<body class="agent-body">

<header class="user-topbar">
    <button type="button" class="topbar-toggle" id="sidebarToggle"
            data-tooltip="Hide sidebar" aria-label="Toggle sidebar">
        <i class="bi bi-list"></i>
    </button>
    <a href="/rentbridge/agent/dashboard.php" class="topbar-brand">
        <span class="topbar-logo">R</span>
        <span class="topbar-name">RentBridge</span>
        <span style="opacity:0.5; margin: 0 4px;">·</span>
        <span style="font-family:'Manrope',sans-serif; font-size:0.85rem; font-weight:500;
                     letter-spacing:0.5px; text-transform:uppercase; opacity:0.7;">Agent</span>
    </a>
<div class="topbar-right">
    <div class="topbar-user-menu dropdown">
        <button class="topbar-user-btn dropdown-toggle"
                type="button"
                data-bs-toggle="dropdown"
                aria-expanded="false">
            <?php
            require_once __DIR__ . '/avatar.php';
            $_avatarPath = get_avatar_path($userId, 'agent');
            render_avatar($_avatarPath, $myName, 32);
            ?>
            <span class="topbar-user-name d-none d-md-inline">
                <?= e($myName) ?>
            </span>
            <i class="bi bi-chevron-down small ms-1 d-none d-md-inline"></i>
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow-sm">
            <li class="px-3 py-2">
                <div class="fw-semibold"><?= e($myName) ?></div>
                <small class="text-secondary">Landlord</small>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <a class="dropdown-item" href="/rentbridge/landlord/profile.php">
                    <i class="bi bi-person-circle me-2"></i> Profile
                </a>
            </li>
            <li>
                <a class="dropdown-item" href="/rentbridge/landlord/properties.php">
                    <i class="bi bi-buildings me-2"></i> My properties
                </a>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <a class="dropdown-item text-danger" href="/rentbridge/auth/logout.php">
                    <i class="bi bi-box-arrow-right me-2"></i> Sign out
                </a>
            </li>
        </ul>
    </div>
</div></header>

<div class="user-shell">

    <aside class="user-sidebar" id="userSidebar">
        <nav class="sidebar-nav">
            <a href="/rentbridge/agent/dashboard.php"
               class="sidebar-link <?= $activeNav === 'dashboard' ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2-fill"></i>
                <span class="sidebar-label">Dashboard</span>
            </a>
            <a href="/rentbridge/agent/cases.php"
               class="sidebar-link <?= $activeNav === 'cases' ? 'active' : '' ?>">
                <i class="bi bi-clipboard-data-fill"></i>
                <span class="sidebar-label">My Cases</span>
                <?php if ($urgentCases > 0): ?>
                    <span class="sidebar-badge"><?= $urgentCases ?></span>
                <?php endif; ?>
            </a>
            <a href="/rentbridge/agent/inspections.php"
               class="sidebar-link <?= $activeNav === 'inspections' ? 'active' : '' ?>">
                <i class="bi bi-clipboard-check-fill"></i>
                <span class="sidebar-label">Inspections</span>
            </a>
            <a href="/rentbridge/agent/contracts.php"
               class="sidebar-link <?= $activeNav === 'contracts' ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-text-fill"></i>
                <span class="sidebar-label">Contracts</span>
            </a>
            <a href="/rentbridge/agent/earnings.php"
               class="sidebar-link <?= $activeNav === 'earnings' ? 'active' : '' ?>">
                <i class="bi bi-cash-stack"></i>
                <span class="sidebar-label">Earnings</span>
            </a>
            <a href="/rentbridge/agent/profile.php"
            class="sidebar-link <?= $activeNav === 'profile' ? 'active' : '' ?>">
                <i class="bi bi-person-circle"></i>
                <span class="sidebar-label">Profile</span>
            </a>
            <a href="/rentbridge/chat.php"
               class="sidebar-link <?= $activeNav === 'chat' ? 'active' : '' ?>">
                <i class="bi bi-chat-dots-fill"></i>
                <span class="sidebar-label">Chat &amp; Notif</span>
                <?php if ($totalUnread > 0): ?>
                    <span class="sidebar-badge"><?= $totalUnread > 9 ? '9+' : $totalUnread ?></span>
                <?php endif; ?>
            </a>
            <a href="/rentbridge/faq.php"
            class="sidebar-link <?= $activeNav === 'faq' ? 'active' : '' ?>">
                <i class="bi bi-question-circle"></i>
                <span class="sidebar-label">FAQ</span>
            </a>
            <a href="/rentbridge/contact.php"
            class="sidebar-link <?= $activeNav === 'contact' ? 'active' : '' ?>">
                <i class="bi bi-envelope"></i>
                <span class="sidebar-label">Contact</span>
            </a>
        </nav>
        <div class="sidebar-footer">
            <a href="/rentbridge/agent/profile.php"
               class="sidebar-link <?= $activeNav === 'profile' ? 'active' : '' ?>">
                <i class="bi bi-person-fill"></i>
                <span class="sidebar-label">Profile</span>
            </a>
            <a href="/rentbridge/agent/settings.php"
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