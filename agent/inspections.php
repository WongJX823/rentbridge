<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('agent');

$pageTitle = 'Inspections';
$activeNav = 'inspections';
ob_start();
?>
<div class="text-center py-5 bg-white rounded-3 border">
    <i class="bi bi-clipboard-check" style="font-size:3rem;color:rgba(15,44,82,0.15);"></i>
    <h4 class="mt-3">Inspections list coming soon</h4>
    <p class="text-secondary small">
        For now, find inspections in "My Cases" filtered by status.
    </p>
    <a href="/rentbridge/agent/cases.php?tab=verifying" class="btn btn-primary mt-2">
        Go to My Cases <i class="bi bi-arrow-right ms-1"></i>
    </a>
</div>
<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/agent_layout.php';