<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('agent');

$pageTitle = 'Settings';
$activeNav = 'settings';
ob_start();
?>
<div class="text-center py-5 bg-white rounded-3 border">
    <i class="bi bi-gear" style="font-size:3rem;color:rgba(15,44,82,0.15);"></i>
    <h4 class="mt-3">Settings — coming soon</h4>
    <p class="text-secondary small">
        Dark mode, language preference, notification settings.<br>
        Coming after all core modules are complete.
    </p>
</div>
<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/agent_layout.php';