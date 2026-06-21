<?php
// Public layout — matches role-layout sidebar behaviour
// Expects: $pageTitle, $pageContent (HTML), optionally $activeNav, $pageTabs
require_once __DIR__ . '/auth.php';

$pageTitle = $pageTitle ?? 'RentBridge';
$activeNav = $activeNav ?? '';
$pageTabs  = $pageTabs  ?? [];
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
    <link href="/rentbridge/assets/css/public_layout.css" rel="stylesheet">
</head>
<body class="student-body">

<!-- TOP BAR -->
<header class="user-topbar">
    <button type="button" class="topbar-toggle" id="sidebarToggle"
            data-tooltip="Hide sidebar" aria-label="Toggle sidebar">
        <i class="bi bi-list"></i>
    </button>
    <a href="/rentbridge/index.php" class="topbar-brand">
        <span class="topbar-logo">R</span>
        <span class="topbar-name">RentBridge</span>
    </a>
    <div class="topbar-right">
        <?php if (is_logged_in()): ?>
            <?php
            $dashboardPath = match (current_role()) {
                'student'  => '/rentbridge/student/dashboard.php',
                'landlord' => '/rentbridge/landlord/dashboard.php',
                'agent'    => '/rentbridge/agent/dashboard.php',
                'admin'    => '/rentbridge/admin/dashboard.php',
                default    => '/rentbridge/index.php',
            };
            ?>
            <a href="<?= e($dashboardPath) ?>" class="btn btn-sm btn-outline-primary me-2">
                <i class="bi bi-speedometer2 me-1"></i> Dashboard
            </a>
        <?php else: ?>
            <a href="/rentbridge/auth/login.php" class="btn btn-sm btn-primary me-2">Log in</a>
            <a href="/rentbridge/auth/register_student.php" class="btn btn-sm btn-outline-secondary">Register</a>
        <?php endif; ?>
    </div>
</header>

<div class="user-shell">

    <!-- SIDEBAR -->
    <aside class="user-sidebar" id="userSidebar">
        <nav class="sidebar-nav">
            <a href="/rentbridge/index.php"
               class="sidebar-link <?= $activeNav === 'home' ? 'active' : '' ?>">
                <i class="bi bi-house-fill"></i>
                <span class="sidebar-label">Home</span>
            </a>
            <a href="/rentbridge/listings.php"
               class="sidebar-link <?= $activeNav === 'browse' ? 'active' : '' ?>">
                <i class="bi bi-search"></i>
                <span class="sidebar-label">Browse</span>
            </a>

            <!-- Help & Info collapsible -->
            <div class="sidebar-collapsible">
                <button class="sidebar-link sidebar-collapsible-toggle" type="button">
                    <i class="bi bi-question-circle"></i>
                    <span class="sidebar-label">Help &amp; Info</span>
                    <i class="bi bi-chevron-down sidebar-chevron"></i>
                </button>
                <div class="sidebar-submenu">
                    <a href="/rentbridge/about.php"        class="sidebar-link sidebar-sublink <?= $activeNav === 'about'        ? 'active' : '' ?>">About RentBridge</a>
                    <a href="/rentbridge/how_it_works.php" class="sidebar-link sidebar-sublink <?= $activeNav === 'how_it_works' ? 'active' : '' ?>">How it works</a>
                    <a href="/rentbridge/faq.php"          class="sidebar-link sidebar-sublink <?= $activeNav === 'faq'          ? 'active' : '' ?>">FAQ</a>
                    <a href="/rentbridge/contact.php"      class="sidebar-link sidebar-sublink <?= $activeNav === 'contact'      ? 'active' : '' ?>">Feedback &amp; Contact</a>
                    <a href="/rentbridge/legal.php"        class="sidebar-link sidebar-sublink <?= $activeNav === 'legal'        ? 'active' : '' ?>">Terms &amp; Conditions</a>
                    <a href="/rentbridge/privacy.php"      class="sidebar-link sidebar-sublink <?= $activeNav === 'privacy'      ? 'active' : '' ?>">Privacy &amp; Security</a>
                    <div class="sidebar-submenu-social">
                        <a href="#" title="Twitter"   aria-label="Twitter"><i class="bi bi-twitter-x"></i></a>
                        <a href="#" title="Instagram" aria-label="Instagram"><i class="bi bi-instagram"></i></a>
                        <a href="#" title="Facebook"  aria-label="Facebook"><i class="bi bi-facebook"></i></a>
                    </div>
                </div>
            </div>
        </nav>

        <div class="sidebar-footer">
            <?php if (is_logged_in()): ?>
                <?php
                $dashboardPath = match (current_role()) {
                    'student'  => '/rentbridge/student/dashboard.php',
                    'landlord' => '/rentbridge/landlord/dashboard.php',
                    'agent'    => '/rentbridge/agent/dashboard.php',
                    'admin'    => '/rentbridge/admin/dashboard.php',
                    default    => '/rentbridge/index.php',
                };
                ?>
                <a href="<?= e($dashboardPath) ?>" class="sidebar-link">
                    <i class="bi bi-speedometer2"></i>
                    <span class="sidebar-label">Dashboard</span>
                </a>
                <a href="/rentbridge/auth/logout.php" class="sidebar-link sidebar-logout">
                    <i class="bi bi-box-arrow-right"></i>
                    <span class="sidebar-label">Sign out</span>
                </a>
            <?php else: ?>
                <a href="/rentbridge/auth/login.php" class="sidebar-link">
                    <i class="bi bi-box-arrow-in-right"></i>
                    <span class="sidebar-label">Log in</span>
                </a>
                <a href="/rentbridge/auth/register_student.php" class="sidebar-link" style="color: var(--user-accent);">
                    <i class="bi bi-person-plus"></i>
                    <span class="sidebar-label">Register</span>
                </a>
            <?php endif; ?>
        </div>
    </aside>
    <button type="button" class="sidebar-edge-btn" id="sidebarEdgeBtn" aria-label="Toggle sidebar">
        <i class="bi bi-chevron-left"></i>
        <i class="bi bi-chevron-right"></i>
    </button>

    <!-- MAIN -->
    <main class="user-main">
        <div class="user-content">
            <?php if (!empty($pageTabs)): ?>
                <div class="public-tab-bar mb-4">
                    <?php foreach ($pageTabs as $t): ?>
                        <a href="<?= e($t['href']) ?>"
                           class="public-tab <?= !empty($t['active']) ? 'active' : '' ?>">
                            <?= e($t['label']) ?>
                            <?php if (isset($t['count'])): ?>
                                <span class="badge bg-light text-dark ms-1"><?= (int)$t['count'] ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
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
    function closeAllFloating() {
        document.querySelectorAll('.sidebar-collapsible.open').forEach(el => closeFloatingSubmenu(el));
    }
    function toggle(freeze) {
        closeAllFloating();
        body.classList.toggle('sidebar-collapsed');
        if (freeze) {
            localStorage.setItem('rb-user-sidebar',
                body.classList.contains('sidebar-collapsed') ? 'collapsed' : 'expanded');
        }
        updateTooltip();
    }
    if (localStorage.getItem('rb-user-sidebar') === 'collapsed') {
        body.classList.add('sidebar-collapsed');
    }
    updateTooltip();
    freezeBtn.addEventListener('click', function() { toggle(true); });
    if (edgeBtn) edgeBtn.addEventListener('click', function() { toggle(false); });
})();

// Sidebar submenu — portal pattern (moves submenu to <body> when floating to escape stacking context)
function positionFloatingSubmenu(btn, parent) {
    const rect    = btn.getBoundingClientRect();
    let   submenu = parent._floatingSub || parent.querySelector('.sidebar-submenu');
    if (!submenu) return;
    if (!submenu.classList.contains('sidebar-submenu--floating')) {
        submenu._origParent  = submenu.parentNode;
        submenu._origNextSib = submenu.nextSibling || null;
        parent._floatingSub  = submenu;
        document.body.appendChild(submenu);
        submenu.classList.add('sidebar-submenu--floating');
    }
    const topbarH    = 56;
    const spaceAbove = rect.bottom - topbarH;
    const spaceBelow = window.innerHeight - rect.top;
    submenu.style.left = '64px';
    submenu.style.top  = submenu.style.bottom = submenu.style.maxHeight = '';
    if (spaceAbove >= spaceBelow) {
        submenu.style.top       = 'auto';
        submenu.style.bottom    = (window.innerHeight - rect.bottom) + 'px';
        submenu.style.maxHeight = spaceAbove + 'px';
    } else {
        submenu.style.bottom    = 'auto';
        submenu.style.top       = rect.top + 'px';
        submenu.style.maxHeight = spaceBelow + 'px';
    }
}
function closeFloatingSubmenu(el) {
    el.classList.remove('open');
    const sub = el._floatingSub || el.querySelector('.sidebar-submenu');
    if (!sub) return;
    if (sub.classList.contains('sidebar-submenu--floating')) {
        if (sub._origParent) sub._origParent.insertBefore(sub, sub._origNextSib);
        sub.classList.remove('sidebar-submenu--floating');
        sub.style.left = sub.style.top = sub.style.bottom = sub.style.maxHeight = '';
        el._floatingSub = null;
    } else {
        sub.style.top = sub.style.bottom = sub.style.maxHeight = '';
    }
}
document.querySelectorAll('.sidebar-collapsible-toggle').forEach(btn => {
    btn.replaceWith(btn.cloneNode(true));
});
document.querySelectorAll('.sidebar-collapsible-toggle').forEach(btn => {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        const parent  = this.closest('.sidebar-collapsible');
        if (document.body.classList.contains('sidebar-collapsed')) {
            const opening = !parent.classList.contains('open');
            if (opening) { positionFloatingSubmenu(this, parent); parent.classList.add('open'); }
            else         { closeFloatingSubmenu(parent); }
        } else {
            parent.classList.toggle('open');
        }
    });
});

// Close floating submenu when clicking outside
document.addEventListener('click', function(e) {
    if (!e.target.closest('.sidebar-collapsible') && !e.target.closest('.sidebar-submenu--floating')) {
        document.querySelectorAll('.sidebar-collapsible.open').forEach(el => closeFloatingSubmenu(el));
    }
});
</script>
</body>
</html>
