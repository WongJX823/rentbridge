<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('agent');

$pageTitle = 'Contracts';
$activeNav = 'contracts';
ob_start();
?>
<div class="text-center py-5 bg-white rounded-3 border">
    <i class="bi bi-file-earmark-text" style="font-size:3rem;color:rgba(15,44,82,0.15);"></i>
    <h4 class="mt-3">Contracts page coming soon</h4>
    <p class="text-secondary small">
        For now, find contracts via My Cases.
    </p>
</div>
<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/agent_layout.php';