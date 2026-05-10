<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('landlord');

$stmt = db()->prepare('SELECT * FROM landlords WHERE user_id = ?');
$stmt->execute([current_user_id()]);
$me = $stmt->fetch();

$stmt = db()->prepare('SELECT COUNT(*) FROM properties WHERE landlord_id = ?');
$stmt->execute([current_user_id()]);
$propCount = $stmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard · Landlord · RentBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container py-5">
        <h1>Welcome, <em><?= e($me['full_name']) ?>.</em></h1>
        <p class="text-secondary">Landlord dashboard · <?= (int)$propCount ?> property listed</p>

        <div class="alert alert-info mt-4">
            <i class="bi bi-info-circle"></i>
            This is your dashboard. Property management coming in the next module.
        </div>

        <a href="../auth/logout.php" class="btn btn-outline-dark">Sign out</a>
    </div>
</body>
</html>