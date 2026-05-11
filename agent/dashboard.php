<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('agent');

$stmt = db()->prepare('SELECT * FROM agents WHERE user_id = ?');
$stmt->execute([current_user_id()]);
$me = $stmt->fetch();

$pendingStmt = db()->prepare(
    "SELECT COUNT(*) FROM bookings WHERE agent_id = ? AND status = 'pending_agent'"
);
$pendingStmt->execute([current_user_id()]);
$pendingCases = (int)$pendingStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard · Agent · RentBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include '../includes/header.php'; ?>

    <div class="container py-5">
        <h1>Welcome, <em><?= e($me['full_name']) ?>.</em></h1>
        <p class="text-secondary">Agent dashboard · Staff ID <?= e($me['staff_id']) ?></p>

        <p>Caseload: <?= (int)$me['current_caseload'] ?> / <?= (int)$me['max_caseload'] ?></p>

        <?php if ($pendingCases > 0): ?>
    <div class="alert d-flex align-items-center gap-3 mt-4" style="background:#FFF4D6; border-color:#D4A017; color:#7C5E0A;">
        <i class="bi bi-bell-fill fs-4"></i>
        <div class="flex-grow-1">
            <strong><?= $pendingCases ?> new case<?= $pendingCases === 1 ? '' : 's' ?> waiting for your acceptance</strong>
        </div>
        <a href="/rentbridge/agent/cases.php" class="btn btn-sm btn-primary">
            Review now <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>
<?php endif; ?>

<div class="row g-3 mt-2">
    <div class="col-md-6">
        <a href="/rentbridge/agent/cases.php" class="d-block bg-white rounded-3 border p-4 text-decoration-none text-dark h-100">
            <i class="bi bi-clipboard-check display-6 text-emerald"></i>
            <h5 class="mt-2 mb-1">My cases</h5>
            <p class="text-secondary mb-0 small">
                <?= $pendingCases ?> pending · <?= (int)$me['current_caseload'] ?>/<?= (int)$me['max_caseload'] ?> caseload.
            </p>
        </a>
    </div>
    <div class="col-md-6">
        <div class="bg-white rounded-3 border p-4 h-100">
            <i class="bi bi-toggle-on display-6 text-emerald"></i>
            <h5 class="mt-2 mb-1">Availability</h5>
            <p class="text-secondary mb-0 small">
                Status: <strong class="text-emerald"><?= e(ucfirst(str_replace('_', ' ', $me['availability']))) ?></strong>
            </p>
        </div>
    </div>
</div>

<a href="/rentbridge/auth/logout.php" class="btn btn-outline-dark mt-4">Sign out</a>

    </div>
</body>
</html>