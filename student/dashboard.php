<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('student');

// Get student profile
$stmt = db()->prepare('SELECT * FROM students WHERE user_id = ?');
$stmt->execute([current_user_id()]);
$me = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard · Student · RentBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container py-5">
        <h1>Welcome, <em><?= e($me['full_name']) ?>.</em></h1>
        <p class="text-secondary">Student dashboard · <?= e($me['matric_no']) ?></p>

        <div class="row g-3 mt-4">
            <div class="col-md-6">
                <a href="/rentbridge/listings.php" class="d-block bg-white rounded-3 border p-4 text-decoration-none text-dark h-100">
                    <i class="bi bi-search display-6 text-emerald"></i>
                    <h5 class="mt-2 mb-1">Browse listings</h5>
                    <p class="text-secondary mb-0 small">Find your next home near campus.</p>
                </a>
            </div>
            <div class="col-md-6">
                <a href="/rentbridge/student/bookings.php" class="d-block bg-white rounded-3 border p-4 text-decoration-none text-dark h-100">
                    <i class="bi bi-calendar-check display-6 text-emerald"></i>
                    <h5 class="mt-2 mb-1">My bookings</h5>
                    <p class="text-secondary mb-0 small">View your booking history and status.</p>
                </a>
            </div>
        </div>

        <a href="../auth/logout.php" class="btn btn-outline-dark mt-4">Sign out</a>
    </div>
</body>
</html>