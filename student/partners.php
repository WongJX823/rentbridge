<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('student');

$pageTitle = 'Partners';
$activeNav = 'partners';

ob_start();
?>

<div class="text-center py-5 bg-white rounded-3 border">
    <i class="bi bi-people" style="font-size: 3rem; color: rgba(15,44,82,0.15);"></i>
    <h4 class="mt-3">No partners yet</h4>
    <p class="text-secondary small">
        Once you've signed a contract or saved someone from Find Stranger, they'll appear here.<br>
        This feature is coming in the next update.
    </p>
</div>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/student_layout.php';