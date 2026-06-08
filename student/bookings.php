<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('student');

$pdo = db();

// Handle cancel action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'cancel') {
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $cancellable = ['pending_landlord', 'pending_agent', 'agent_assigned'];

    $stmt = $pdo->prepare(
        'SELECT id, status, agent_id FROM bookings WHERE id = ? AND student_id = ? LIMIT 1'
    );
    $stmt->execute([$bookingId, current_user_id()]);
    $row = $stmt->fetch();

    if ($row && in_array($row['status'], $cancellable, true)) {
        try {
            $pdo->beginTransaction();

            $pdo->prepare("UPDATE bookings SET status = 'cancelled_by_student' WHERE id = ?")
                ->execute([$bookingId]);

            if ($row['agent_id']) {
                $pdo->prepare(
                    'UPDATE agents SET current_caseload = GREATEST(0, current_caseload - 1) WHERE user_id = ?'
                )->execute([$row['agent_id']]);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
        }
    }

    header('Location: /rentbridge/student/bookings.php');
    exit;
}
$stmt = $pdo->prepare("
    SELECT b.*,
           p.title         AS property_title,
           p.city          AS property_city,
           l.full_name     AS landlord_name,
           a.full_name     AS agent_name,
           a.department    AS agent_department,
           (SELECT image_path FROM property_images
             WHERE property_id = p.id
             ORDER BY is_primary DESC, id ASC LIMIT 1) AS image_path
      FROM bookings b
      JOIN properties p ON p.id = b.property_id
      JOIN landlords  l ON l.user_id = b.landlord_id
      LEFT JOIN agents a ON a.user_id = b.agent_id
     WHERE b.student_id = ?
     ORDER BY b.created_at DESC
");
$stmt->execute([current_user_id()]);
$bookings = $stmt->fetchAll();

// Helper: human-readable status
function status_label(string $status): array {
    return match ($status) {
        'pending_landlord'      => ['Waiting for landlord', 'warning'],
        'rejected_by_landlord'  => ['Rejected by landlord', 'danger'],
        'pending_agent'         => ['Waiting for agent',    'info'],
        'agent_assigned'        => ['Agent assigned',       'primary'],
        'contract_pending'      => ['Contract pending',     'primary'],
        'active'                => ['Active tenancy',       'success'],
        'completed'             => ['Completed',            'secondary'],
        'cancelled_by_student'  => ['Cancelled by you',     'secondary'],
        'cancelled_by_landlord' => ['Cancelled by landlord','danger'],
        'cancelled_by_admin'    => ['Cancelled by admin',   'danger'],
        default                 => [ucfirst($status),       'secondary'],
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My bookings · RentBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include '../includes/header.php'; ?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-end mb-4">
        <div>
            <h1 class="mb-1">My bookings</h1>
            <p class="text-secondary mb-0"><?= count($bookings) ?> booking<?= count($bookings) === 1 ? '' : 's' ?></p>
        </div>
        <a href="/rentbridge/listings.php" class="btn btn-ghost">
            <i class="bi bi-search me-1"></i> Browse more
        </a>
    </div>

    <?php if (empty($bookings)): ?>
        <div class="text-center py-5 bg-white rounded-3 border">
            <i class="bi bi-calendar-x" style="font-size: 3rem; color: var(--rb-line);"></i>
            <h4 class="mt-3">No bookings yet</h4>
            <p class="text-secondary">Find a place that feels right.</p>
            <a href="/rentbridge/listings.php" class="btn btn-primary">Browse listings</a>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($bookings as $b):
                [$label, $color] = status_label($b['status']);
            ?>
                <div class="col-12">
                    <a href="/rentbridge/student/booking.php?id=<?= (int)$b['id'] ?>"
                        class="text-decoration-none text-dark d-block">
                            <div class="bg-white border rounded-3 overflow-hidden booking-row">
                                <div class="row g-0">
                            <div class="col-md-3" style="background:linear-gradient(135deg,#E6ECF4,#E4F2EA); min-height: 160px;">
                                <?php if (!empty($b['image_path'])): ?>
                                    <img src="/rentbridge/<?= e($b['image_path']) ?>"
                                         style="width:100%; height:100%; object-fit:cover;"
                                         alt="">
                                <?php endif; ?>
                            </div>
                            <div class="col-md-9 p-4">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h5 class="mb-1"><?= e($b['property_title']) ?></h5>
                                    <span class="badge bg-<?= $color ?>"><?= e($label) ?></span>
                                </div>
                                <div class="text-secondary small mb-3">
                                    <i class="bi bi-geo-alt"></i> <?= e($b['property_city']) ?>
                                    &nbsp;·&nbsp;
                                    <i class="bi bi-person"></i> Landlord: <?= e($b['landlord_name']) ?>
                                </div>
                                <div class="row text-center small mb-2">
                                    <div class="col">
                                        <div class="text-secondary text-uppercase">Move in</div>
                                        <strong><?= e(date('d M Y', strtotime($b['start_date']))) ?></strong>
                                    </div>
                                    <div class="col">
                                        <div class="text-secondary text-uppercase">Move out</div>
                                        <strong><?= e(date('d M Y', strtotime($b['end_date']))) ?></strong>
                                    </div>
                                    <div class="col">
                                        <div class="text-secondary text-uppercase">Monthly</div>
                                        <strong class="text-emerald">RM <?= number_format((float)$b['monthly_rent']) ?></strong>
                                    </div>
                                </div>
                                <!-- Agent assignment status -->
                                <?php if ($b['status'] === 'pending_agent' || $b['status'] === 'agent_assigned'
                                        || $b['status'] === 'contract_pending' || $b['status'] === 'active'): ?>
                                    <div class="small mt-3 p-2 rounded-3" style="background:var(--rb-cream); border:1px solid var(--rb-line);">
                                        <?php if (!empty($b['agent_name'])): ?>
                                            <i class="bi bi-person-badge text-emerald-dark"></i>
                                            <strong>Witness agent:</strong>
                                            <?= e($b['agent_name']) ?>
                                            <span class="text-secondary">
                                                · <?= e($b['agent_department']) ?>
                                            </span>
                                            <?php if ($b['status'] === 'pending_agent'): ?>
                                                <span class="badge bg-warning text-dark ms-1">🟡 awaiting confirmation</span>
                                            <?php else: ?>
                                                <span class="badge bg-success ms-1">✓ confirmed</span>
                                            <?php endif; ?>
                                        <?php elseif ($b['status'] === 'pending_agent'): ?>
                                            <i class="bi bi-search text-secondary"></i>
                                            <span class="text-secondary">Looking for a UTeM staff agent…</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($b['student_note'])): ?>
                                    <p class="small text-secondary mt-2 mb-0">
                                        <strong>Your note:</strong> <?= e($b['student_note']) ?>
                                    </p>
                                <?php endif; ?>
                                <?php if (in_array($b['status'], ['pending_landlord', 'pending_agent', 'agent_assigned'], true)): ?>
                                    <form method="post" class="mt-3"
                                          onsubmit="return confirm('Cancel this booking request?')">
                                        <input type="hidden" name="action" value="cancel">
                                        <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">
                                            <i class="bi bi-x-circle me-1"></i>Cancel booking
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>