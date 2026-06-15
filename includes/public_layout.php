<?php
// Public layout — collapsible sidebar (default collapsed) for guest/public pages
// Expects: $pageTitle, $pageContent (HTML), optionally $activeNav, $pageTabs
require_once __DIR__ . '/auth.php';

$pageTitle = $pageTitle ?? 'RentBridge';
$activeNav = $activeNav ?? '';
$pageTabs  = $pageTabs ?? null;

// If user is somehow logged in viewing a public page, redirect them gently
// (optional — comment out if you want guests AND users to see public pages with same layout)
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
    <link href="/rentbridge/assets/css/public_layout.css" rel="stylesheet">
</head>
<body>

<div class="app-shell" id="appShell">

    <!-- SIDEBAR -->
    <aside class="public-sidebar" id="publicSidebar">

        <!-- Logo + toggle -->
        <div class="public-sidebar-header">
            <a href="/rentbridge/index.php" class="public-logo">
                <span class="public-logo-icon">R</span>
                <span class="public-logo-text">RentBridge</span>
            </a>
            <button type="button" class="public-sidebar-toggle" id="sidebarToggle"
                    title="Toggle sidebar">
                <i class="bi bi-list"></i>
            </button>
        </div>

        <!-- Nav items -->
        <nav class="public-sidebar-nav">
            <a href="/rentbridge/index.php"
               class="public-nav-link <?= $activeNav === 'home' ? 'active' : '' ?>">
                <i class="bi bi-house-fill"></i>
                <span class="public-nav-label">Home</span>
            </a>
            <a href="/rentbridge/listings.php"
               class="public-nav-link <?= $activeNav === 'browse' ? 'active' : '' ?>">
                <i class="bi bi-search"></i>
                <span class="public-nav-label">Browse</span>
            </a>
            <a href="/rentbridge/about.php"
               class="public-nav-link <?= $activeNav === 'about' ? 'active' : '' ?>">
                <i class="bi bi-info-circle"></i>
                <span class="public-nav-label">About</span>
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

            <div class="public-nav-divider"></div>

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
                <a href="<?= e($dashboardPath) ?>" class="public-nav-link public-nav-cta">
                    <i class="bi bi-speedometer2"></i>
                    <span class="public-nav-label">Dashboard</span>
                </a>
                <a href="/rentbridge/auth/logout.php" class="public-nav-link">
                    <i class="bi bi-box-arrow-right"></i>
                    <span class="public-nav-label">Sign out</span>
                </a>
            <?php else: ?>
                <a href="/rentbridge/auth/login.php" class="public-nav-link">
                    <i class="bi bi-box-arrow-in-right"></i>
                    <span class="public-nav-label">Log in</span>
                </a>
                <a href="/rentbridge/auth/register_student.php" class="public-nav-link public-nav-cta">
                    <i class="bi bi-person-plus"></i>
                    <span class="public-nav-label">Register</span>
                </a>
            <?php endif; ?>
        </nav>
    </aside>

    <!-- MAIN -->
    <main class="app-main" id="appMain">

        <!-- Mobile top bar -->
        <div class="public-mobile-bar d-md-none">
            <button type="button" class="btn btn-sm" id="mobileSidebarToggle">
                <i class="bi bi-list fs-4"></i>
            </button>
            <a href="/rentbridge/index.php" class="public-logo">
                <span class="public-logo-icon">R</span>
                <span class="public-logo-text">RentBridge</span>
            </a>
            <span></span>
        </div>

        <div class="public-main-inner">
            <?php if ($pageTabs): ?>
                <div class="public-tab-bar mb-3">
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
(function() {
    const shell  = document.getElementById('appShell');
    const sidebar = document.getElementById('publicSidebar');
    const toggle  = document.getElementById('sidebarToggle');
    const mobileToggle = document.getElementById('mobileSidebarToggle');

    // Apply saved state on load (default: collapsed)
    const saved = localStorage.getItem('publicSidebarExpanded');
    if (saved === 'true') {
        shell.classList.add('sidebar-expanded');
    }

    function toggleSidebar() {
        shell.classList.toggle('sidebar-expanded');
        localStorage.setItem('publicSidebarExpanded',
            shell.classList.contains('sidebar-expanded'));
    }

    function toggleMobile() {
        shell.classList.toggle('sidebar-mobile-open');
    }

    toggle?.addEventListener('click', toggleSidebar);
    mobileToggle?.addEventListener('click', toggleMobile);

    // Close mobile sidebar when clicking outside
    document.addEventListener('click', (e) => {
        if (!shell.classList.contains('sidebar-mobile-open')) return;
        if (sidebar.contains(e.target)) return;
        if (mobileToggle?.contains(e.target)) return;
        shell.classList.remove('sidebar-mobile-open');
    });
})();
</script>

</body>
</html>