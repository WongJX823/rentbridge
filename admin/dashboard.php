<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = db();
$counts = [
    'students'        => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE primary_role='student'")->fetchColumn(),
    'landlords'       => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE primary_role='landlord'")->fetchColumn(),
    'agents_active'   => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE primary_role='agent' AND status='active'")->fetchColumn(),
    'agents_pending'  => (int)$pdo->query("SELECT COUNT(*) FROM users WHERE primary_role='agent' AND status='pending'")->fetchColumn(),
    'props_pending'   => (int)$pdo->query("SELECT COUNT(*) FROM properties WHERE status='pending_approval'")->fetchColumn(),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard · RentBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container py-5">
        <h1>System <em>overview.</em></h1>
        <p class="text-secondary">Admin dashboard</p>

        <div class="row g-4 mt-3">
            <div class="col-md-4 col-6">
                <div class="bg-white rounded-3 border p-4">
                    <div class="display-6 fw-bold text-navy"><?= $counts['students'] ?></div>
                    <div class="text-secondary">Students</div>
                </div>
            </div>
            <div class="col-md-4 col-6">
                <div class="bg-white rounded-3 border p-4">
                    <div class="display-6 fw-bold text-navy"><?= $counts['landlords'] ?></div>
                    <div class="text-secondary">Landlords</div>
                </div>
            </div>
            <div class="col-md-4 col-6">
                <div class="bg-white rounded-3 border p-4">
                    <div class="display-6 fw-bold text-navy"><?= $counts['agents_active'] ?></div>
                    <div class="text-secondary">Active agents</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="bg-white rounded-3 border p-4" style="border-left:4px solid #D4A017 !important;">
                    <div class="display-6 fw-bold"><?= $counts['agents_pending'] ?></div>
                    <div class="text-secondary">⏳ Pending agent applications</div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="bg-white rounded-3 border p-4" style="border-left:4px solid #D4A017 !important;">
                    <div class="display-6 fw-bold"><?= $counts['props_pending'] ?></div>
                    <div class="text-secondary">⏳ Pending property listings</div>
                </div>
            </div>
        </div>

        <a href="../auth/logout.php" class="btn btn-outline-dark mt-4">Sign out</a>
    </div>
</body>
</html>