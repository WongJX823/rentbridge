<?php
/**
 * Student layout wrapper — sticky sidebar + sticky tab bar.
 *
 * Usage in student pages:
 *   $pageTitle  = 'Browse properties';
 *   $activeNav  = 'browse';
 *   ob_start();
 *   ?> ... content ... <?php
 *   $pageContent = ob_get_clean();
 *   require __DIR__ . '/../includes/student_layout.php';
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/chat.php';

require_role('student');
$currentRole = 'student';

$pageTitle     = $pageTitle     ?? 'Student';
$activeNav     = $activeNav     ?? '';
$pageTabs      = $pageTabs      ?? [];
$filterContent = $filterContent ?? '';
$pageContent   = $pageContent   ?? '';

// Current user info for sidebar
$userId = current_user_id();
$pdo = db();
$stmt = $pdo->prepare("SELECT preferred_name, full_name FROM students WHERE user_id = ?");
$stmt->execute([$userId]);
$me = $stmt->fetch() ?: [];
$myName = ($me['preferred_name'] ?? '') ?: ($me['full_name'] ?? 'User');

// Unread chat count
$unreadChat = chat_unread_total($userId);

// Unread notifications
$stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$userId]);
$unreadNotif = (int)$stmt->fetchColumn();

$totalUnread = $unreadChat + $unreadNotif;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> · RentBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/rentbridge/assets/css/style.css" rel="stylesheet">
    <link href="/rentbridge/assets/css/student.css" rel="stylesheet">
</head>
<body class="student-body">

<!-- TOP BAR -->
<header class="user-topbar">
    <button type="button"
            class="topbar-toggle"
            id="sidebarToggle"
            data-tooltip="Hide sidebar"
            aria-label="Toggle sidebar">
        <i class="bi bi-list"></i>
    </button>
    <a href="/rentbridge/student/dashboard.php" class="topbar-brand">        <span class="topbar-name">RentBridge</span></a>
<div class="topbar-right">
    <div class="topbar-user-menu dropdown">
        <button class="topbar-user-btn dropdown-toggle"
                type="button"
                data-bs-toggle="dropdown"
                aria-expanded="false">
            <?php
            require_once __DIR__ . '/avatar.php';
            $_avatarPath = get_avatar_path($userId, 'student');
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
                <small class="text-secondary">Student</small>
            </li>
            <li><hr class="dropdown-divider"></li>
            <li>
                <a class="dropdown-item" href="/rentbridge/student/profile.php">
                    <i class="bi bi-person-circle me-2"></i> Profile
                </a>
            </li>
            <li>
                <a class="dropdown-item" href="/rentbridge/saved.php">
                    <i class="bi bi-bookmark-heart me-2"></i> Saved properties
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

    <!-- SIDEBAR -->
    <aside class="user-sidebar" id="userSidebar">
        <nav class="sidebar-nav">
            <a href="/rentbridge/student/dashboard.php"
            class="sidebar-link <?= $activeNav === 'dashboard' ? 'active' : '' ?>">
                <i class="bi bi-house-door-fill"></i>
                <span class="sidebar-label">Dashboard</span>
            </a>
            <a href="/rentbridge/listings.php"
            class="sidebar-link <?= $activeNav === 'browse' ? 'active' : '' ?>">
                <i class="bi bi-search"></i>
                <span class="sidebar-label">Browse</span>
            </a>
            <a href="/rentbridge/saved.php"            class="sidebar-link <?= $activeNav === 'saved' ? 'active' : '' ?>">
                <i class="bi bi-bookmark-heart-fill"></i>
                <span class="sidebar-label">Saved</span>
            </a>
            <a href="/rentbridge/student/partners.php"
            class="sidebar-link <?= $activeNav === 'partners' ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i>
                <span class="sidebar-label">Find Partners</span>
            </a>
            <a href="/rentbridge/chat.php"
            class="sidebar-link <?= $activeNav === 'chat' ? 'active' : '' ?>">
                <i class="bi bi-chat-dots-fill"></i>
                <span class="sidebar-label">Chat &amp; Notif</span>
                <?php if ($totalUnread > 0): ?>
                    <span class="sidebar-badge"><?= $totalUnread > 9 ? '9+' : $totalUnread ?></span>
                <?php endif; ?>
            </a>
            <a href="/rentbridge/student/profile.php"
            class="sidebar-link <?= $activeNav === 'profile' ? 'active' : '' ?>">
                <i class="bi bi-person-circle"></i>
                <span class="sidebar-label">Profile</span>
            </a>            

            <!-- Help & Info -->
            <div class="sidebar-collapsible">     
                <button class="sidebar-link sidebar-collapsible-toggle"
                        data-collapse-target="helpMenu" type="button">
                    <i class="bi bi-question-circle"></i>
                    <span class="sidebar-label">Help &amp; Info</span>
                    <i class="bi bi-chevron-down sidebar-chevron"></i>
                </button>
                <div class="sidebar-submenu" id="helpMenu">
                    <a href="/rentbridge/about.php"   class="sidebar-link">About RentBridge</a>
                    <a href="/rentbridge/faq.php"     class="sidebar-link">FAQ</a>
                    <a href="/rentbridge/contact.php" class="sidebar-link">Feedback &amp; Contact</a>
                    <a href="/rentbridge/legal.php"   class="sidebar-link">Privacy &amp; Terms</a>
                    <div class="sidebar-submenu-social">
                        <a href="#" title="Twitter"   aria-label="Twitter"><i class="bi bi-twitter-x"></i></a>
                        <a href="#" title="Instagram" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                        <a href="#" title="Facebook"  aria-label="Facebook"><i class="bi bi-facebook"></i></a>
                    </div>
                </div>
            </div>
        </nav>        
        <div class="sidebar-footer">
            <a href="/rentbridge/student/profile.php"
               class="sidebar-link <?= $activeNav === 'profile' ? 'active' : '' ?>">
                <i class="bi bi-gear-fill"></i>
                <span class="sidebar-label">Settings</span>
            </a>
            <a href="/rentbridge/auth/logout.php" class="sidebar-link sidebar-logout">
                <i class="bi bi-box-arrow-right"></i>
                <span class="sidebar-label">Sign out</span>
            </a>
        </div>
    </aside>

    <!-- MAIN AREA -->
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
// Sidebar freeze toggle
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

// Filter drawer
(function() {
    const toggle = document.getElementById('filterToggle');
    const drawer = document.getElementById('filterDrawer');
    if (!toggle || !drawer) return;
    toggle.addEventListener('click', function() {
        drawer.classList.toggle('open');
        toggle.classList.toggle('active');
    });
})();

// Sidebar submenu — independent of sidebar collapsed/expanded state
document.querySelectorAll('.sidebar-collapsible-toggle').forEach(btn => {
    btn.replaceWith(btn.cloneNode(true));
});
document.querySelectorAll('.sidebar-collapsible-toggle').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const parent = this.closest('.sidebar-collapsible');
        const opening = !parent.classList.contains('open');
        // Set top for fixed floating panel before opening
        if (opening && document.body.classList.contains('sidebar-collapsed')) {
            const rect = this.getBoundingClientRect();
            parent.querySelector('.sidebar-submenu').style.top = rect.top + 'px';
        }
        parent.classList.toggle('open');
    });
});

// Close floating submenu when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.sidebar-collapsible')) {
        document.querySelectorAll('.sidebar-collapsible.open').forEach(el => {
            el.classList.remove('open');
        });
    }
});
</script>
</body>
</html>