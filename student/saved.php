<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('student');

$pageTitle = 'Saved properties';
$activeNav = 'saved';

ob_start();
?>

<div class="text-center py-5 bg-white rounded-3 border">
    <i class="bi bi-bookmark-heart" style="font-size: 3rem; color: rgba(15,44,82,0.15);"></i>
    <h4 class="mt-3">No saved properties yet</h4>
    <p class="text-secondary small">
        Browse properties and click the bookmark icon to save them here.<br>
        This feature is coming in the next update.
    </p>
    <a href="/rentbridge/listings.php" class="btn btn-primary mt-2">
        <i class="bi bi-search me-1"></i> Browse properties
    </a>
</div>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/student_layout.php';