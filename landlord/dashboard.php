<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('landlord');

$stmt = db()->prepare('SELECT * FROM landlords WHERE user_id = ?');
$stmt->execute([current_user_id()]);
$me = $stmt->fetch();

$stmt = db()->prepare('SELECT COUNT(*) FROM properties WHERE landlord_id = ?');
$stmt->execute([current_user_id()]);
$propCount = $stmt->fetchColumn();

$pendingStmt = db()->prepare(
    "SELECT COUNT(*) FROM bookings WHERE landlord_id = ? AND status = 'pending_landlord'"
);
$pendingStmt->execute([current_user_id()]);
$pendingBookings = (int)$pendingStmt->fetchColumn();

$propStmt = db()->prepare("SELECT COUNT(*) FROM properties WHERE landlord_id = ?");
$propStmt->execute([current_user_id()]);
$propCount = (int)$propStmt->fetchColumn();

// Pending signature — landlord signs SECOND (student must have signed first)
$pendingContractStmt = db()->prepare(
    "SELECT id, contract_code
       FROM contracts
      WHERE landlord_id = ?
        AND status = 'pending_signatures'
        AND landlord_signed_at IS NULL
        AND student_signed_at IS NOT NULL
      ORDER BY created_at DESC LIMIT 1"
);
$pendingContractStmt->execute([current_user_id()]);
$pendingContract = $pendingContractStmt->fetch();
?>

<?php if ($pendingContract): ?>
    <div class="alert d-flex align-items-center gap-3 mt-4" style="background:#FFF4D6; border-color:#D4A017; color:#7C5E0A;">
        <i class="bi bi-pen-fill fs-4"></i>
        <div class="flex-grow-1">
            <strong>Contract ready for your signature</strong>
            <div class="small">Contract code: <?= e($pendingContract['contract_code']) ?></div>
        </div>
        <a href="/rentbridge/contracts/view.php?id=<?= (int)$pendingContract['id'] ?>"
           class="btn btn-sm btn-success">
            Review &amp; sign <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>
<?php endif; ?>

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

        <?php if ($pendingBookings > 0): ?>
        <div class="alert d-flex align-items-center gap-3 mt-4" style="background:#FFF4D6; border-color:#D4A017; color:#7C5E0A;">
            <i class="bi bi-bell-fill fs-4"></i>
            <div class="flex-grow-1">
                <strong><?= $pendingBookings ?> booking request<?= $pendingBookings === 1 ? '' : 's' ?> waiting</strong>
            </div>
            <a href="/rentbridge/landlord/bookings.php" class="btn btn-sm btn-primary">
                Review now <i class="bi bi-arrow-right ms-1"></i>
            </a>
        </div>
    <?php endif; ?>

    <div class="row g-3 mt-2">
        <div class="col-md-6">
            <a href="/rentbridge/landlord/bookings.php" class="d-block bg-white rounded-3 border p-4 text-decoration-none text-dark h-100">
                <i class="bi bi-inbox display-6 text-emerald"></i>
                <h5 class="mt-2 mb-1">Booking requests</h5>
                <p class="text-secondary mb-0 small">
                    <?= $pendingBookings ?> pending · review and respond.
                </p>
            </a>
        </div>
        <div class="col-md-6">
            <a href="/rentbridge/landlord/properties.php" class="d-block bg-white rounded-3 border p-4 text-decoration-none text-dark h-100">
                <i class="bi bi-house-door display-6 text-emerald"></i>
                <h5 class="mt-2 mb-1">My properties</h5>
                <p class="text-secondary mb-0 small"><?= $propCount ?> property listed. Add more anytime.</p>
            </a>
        </div>
    </div>

    <a href="/rentbridge/auth/logout.php" class="btn btn-outline-dark mt-4">Sign out</a>
    </div>
</body>
</html>