<?php
// Check if logged-in student already has an open post for this property
$stmt = db()->prepare("
    SELECT id FROM co_tenancy_posts
     WHERE poster_id = ? AND property_id = ? AND status = 'open'
     LIMIT 1
");
$stmt->execute([current_user_id(), (int)$property['id']]);
$myExistingPost = $stmt->fetchColumn();
?>

<?php if (!$myExistingPost): ?>
    <a href="/rentbridge/student/find_housemates.php?property_id=<?= (int)$property['id'] ?>"
       class="btn btn-outline-primary w-100 mt-2">
        <i class="bi bi-people-fill me-1"></i> Share with housemates
    </a>
<?php else: ?>
    <div class="alert alert-success small mb-0 mt-2">
        <i class="bi bi-check-circle"></i>
        You have an open co-tenancy post for this property.
        <a href="/rentbridge/student/partners.php">View posts</a>
    </div>
<?php endif; ?>