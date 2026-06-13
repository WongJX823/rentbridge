<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

$pageTitle = 'Statistics — Users';
$activeNav = 'statistics';

$pageTabs = [
    ['label' => 'Summary',     'href' => '/rentbridge/admin/statistics/summary.php',    'active' => false],
    ['label' => 'Users',       'href' => '/rentbridge/admin/statistics/users.php',     'active' => true],
    ['label' => 'Properties',  'href' => '/rentbridge/admin/statistics/properties.php','active' => false],
    ['label' => 'Tenancies',   'href' => '/rentbridge/admin/statistics/tenancies.php', 'active' => false],
    ['label' => 'Financial',   'href' => '/rentbridge/admin/statistics/financial.php', 'active' => false],
];

ob_start();
?>

<div class="text-center py-5 bg-white rounded-3 border">
    <i class="bi bi-people" style="font-size: 3rem; color: rgba(15,44,82,0.15);"></i>
    <h4 class="mt-3">Users statistics coming soon</h4>
    <p class="text-secondary small">
        Detailed user analytics: registration trends, role breakdown, activity patterns.<br>
        Will be built in the next phase.
    </p>
    <a href="/rentbridge/admin/statistics/summary.php" class="btn btn-outline-secondary mt-2">
        <i class="bi bi-arrow-left me-1"></i> Back to summary
    </a>
</div>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../../includes/admin_layout.php';