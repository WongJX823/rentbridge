<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('landlord');

$bookingId = (int)($_GET['id'] ?? $_POST['booking_id'] ?? 0);
if ($bookingId <= 0) {
    http_response_code(400);
    die('Invalid booking ID.');
}

$pdo = db();

// Fetch the booking — must belong to THIS landlord
$stmt = $pdo->prepare("
    SELECT b.*,
           p.title       AS property_title,
           p.address     AS property_address,
           p.city        AS property_city,
           p.state       AS property_state,
           p.postcode    AS property_postcode,
           s.full_name   AS student_name,
           s.matric_no   AS student_matric,
           s.phone       AS student_phone,
           a.full_name   AS agent_name,
           a.department  AS agent_department,
           a.staff_id    AS agent_staff_id,
           u.email       AS student_email
      FROM bookings b
      JOIN properties p ON p.id = b.property_id
      JOIN students   s ON s.user_id = b.student_id
      JOIN users      u ON u.id = b.student_id
       LEFT JOIN agents a ON a.user_id = b.agent_id
     WHERE b.id = ? AND b.landlord_id = ?
     LIMIT 1
");
$stmt->execute([$bookingId, current_user_id()]);
$booking = $stmt->fetch();

if (!$booking) {
    http_response_code(404);
    die('Booking not found.');
}

$errors = [];
$reason = '';

// ---- HANDLE APPROVE / REJECT ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';
    $reason = trim($_POST['reason'] ?? '');

    // Only allow action if status is 'pending_landlord'
    if ($booking['status'] !== 'pending_landlord') {
        $errors['general'] = 'This booking has already been processed.';
    } elseif (!in_array($action, ['approve', 'reject'], true)) {
        $errors['general'] = 'Invalid action.';
    } elseif ($action === 'reject' && $reason === '') {
        $errors['reason'] = 'Please give a reason for rejecting.';
    } else {
        try {
            $pdo->beginTransaction();

            if ($action === 'approve') {
                $stmt = $pdo->prepare(
                    'UPDATE bookings
                        SET status            = "pending_agent",
                            landlord_response = ?
                      WHERE id = ?'
                );
                $stmt->execute([$reason !== '' ? $reason : null, $bookingId]);

                notify(
                    (int)$booking['student_id'],
                    'booking_approved',
                    'Your booking was approved!',
                    'Landlord approved your request for "' . $booking['property_title'] . '". A UTeM agent will be assigned shortly.',
                    '/rentbridge/student/bookings.php'
                );

                $pdo->commit();

                // ▶ Trigger agent auto-assignment (FYP centerpiece)
                require_once __DIR__ . '/../includes/bookings.php';
                $assignedAgentId = auto_assign_agent($bookingId);

                if ($assignedAgentId) {
                    set_flash('success', 'Booking approved! Agent auto-assigned. Student notified.');
                } else {
                    set_flash('info', 'Booking approved, but no available agent right now. Admin has been notified to assign manually.');
                }
                // TODO Module 8.3: trigger auto_assign_agent($bookingId)
                // For now, we'll do that in the next sub-module.

            } else {
                // REJECT
                $stmt = $pdo->prepare(
                    'UPDATE bookings
                        SET status            = "rejected_by_landlord",
                            landlord_response = ?
                      WHERE id = ?'
                );
                $stmt->execute([$reason, $bookingId]);

                notify(
                    (int)$booking['student_id'],
                    'booking_rejected',
                    'Your booking was not approved',
                    'Landlord could not approve your request for "' . $booking['property_title'] . '". Reason: ' . $reason,
                    '/rentbridge/student/bookings.php'
                );

                $pdo->commit();
                set_flash('info', 'Booking rejected. The student has been notified.');
            }

            header('Location: /rentbridge/landlord/bookings.php');
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors['general'] = 'Something went wrong: ' . $e->getMessage();
        }
    }
}

// Status label
function status_label(string $status): array {
    return match ($status) {
        'pending_landlord'      => ['Awaiting your response', 'warning'],
        'rejected_by_landlord'  => ['You rejected',           'danger'],
        'pending_agent'         => ['Waiting for agent',      'info'],
        'agent_assigned'        => ['Agent assigned',         'primary'],
        'contract_pending'      => ['Contract pending',       'primary'],
        'active'                => ['Active tenancy',         'success'],
        'completed'             => ['Completed',              'secondary'],
        'cancelled_by_student'  => ['Cancelled by student',   'secondary'],
        'cancelled_by_landlord' => ['You cancelled',          'secondary'],
        'cancelled_by_admin'    => ['Cancelled by admin',     'danger'],
        default                 => [ucfirst($status),         'secondary'],
    };
}
[$label, $color] = status_label($booking['status']);

// Calculate total months for display
$startTs = strtotime($booking['start_date']);
$endTs   = strtotime($booking['end_date']);
$months  = max(1, (int)round(($endTs - $startTs) / (30.44 * 86400)));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Booking #<?= (int)$booking['id'] ?> · RentBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body style="background: var(--rb-cream);">

<?php include '../includes/header.php'; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">

            <p class="small mb-3">
                <a href="/rentbridge/landlord/bookings.php" class="text-secondary text-decoration-none">
                    <i class="bi bi-arrow-left"></i> All bookings
                </a>
            </p>

            <div class="d-flex justify-content-between align-items-start mb-4">
                <div>
                    <h1 class="mb-1">Booking #<?= (int)$booking['id'] ?></h1>
                    <p class="text-secondary mb-0">
                        Requested <?= e(date('d M Y, H:i', strtotime($booking['created_at']))) ?>
                    </p>
                </div>
                <span class="badge bg-<?= $color ?> fs-6"><?= e($label) ?></span>
            </div>

            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-circle"></i> <?= e($errors['general']) ?>
                </div>
            <?php endif; ?>

            <div class="row g-4">

                <!-- Property summary -->
                <div class="col-md-6">
                    <div class="bg-white border rounded-3 p-4 h-100">
                        <h6 class="text-secondary text-uppercase small mb-3">Property</h6>
                        <h5><?= e($booking['property_title']) ?></h5>
                        <p class="text-secondary small mb-0">
                            <i class="bi bi-geo-alt"></i>
                            <?= e($booking['property_address']) ?>,
                            <?= e($booking['property_city']) ?> <?= e($booking['property_postcode']) ?>,
                            <?= e($booking['property_state']) ?>
                        </p>
                    </div>
                </div>

                <!-- Student info -->
                <div class="col-md-6">
                    <div class="bg-white border rounded-3 p-4 h-100">
                        <h6 class="text-secondary text-uppercase small mb-3">Student</h6>
                        <h5><?= e($booking['student_name']) ?></h5>
                        <div class="small text-secondary">
                            <div><i class="bi bi-card-text"></i> Matric: <?= e($booking['student_matric']) ?></div>
                            <div><i class="bi bi-envelope"></i> <?= e($booking['student_email']) ?></div>
                            <div><i class="bi bi-telephone"></i> <?= e($booking['student_phone']) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Witness Agent card -->
<?php if (!empty($booking['agent_name'])): ?>
<div class="col-12">
    <div class="bg-white border rounded-3 p-4">
        <h6 class="text-secondary text-uppercase small mb-3">Witness Agent</h6>
        <div class="d-flex gap-3 align-items-center">
            <div class="rounded-circle bg-light d-flex align-items-center justify-content-center"
                 style="width:48px; height:48px;">
                <i class="bi bi-person-badge text-secondary fs-4"></i>
            </div>
            <div class="flex-grow-1">
                <h5 class="mb-0"><?= e($booking['agent_name']) ?></h5>
                <small class="text-secondary">
                    UTeM Staff ID: <?= e($booking['agent_staff_id']) ?>
                    · <?= e($booking['agent_department']) ?>
                </small>
            </div>
            <?php if ($booking['status'] === 'pending_agent'): ?>
                <span class="badge bg-warning text-dark">🟡 awaiting confirmation</span>
            <?php elseif (in_array($booking['status'], ['agent_assigned','contract_pending','active'])): ?>
                <span class="badge bg-success">✓ confirmed</span>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php elseif ($booking['status'] === 'pending_agent'): ?>
<div class="col-12">
    <div class="bg-white border rounded-3 p-4 text-center text-secondary">
        <i class="bi bi-search fs-3"></i>
        <p class="mb-0 mt-2">Looking for a UTeM staff agent…</p>
    </div>
</div>
<?php endif; ?>

                <!-- Tenancy terms -->
                <div class="col-12">
                    <div class="bg-white border rounded-3 p-4">
                        <h6 class="text-secondary text-uppercase small mb-3">Tenancy terms</h6>
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="text-secondary small text-uppercase">Move in</div>
                                <strong class="fs-5"><?= e(date('d M Y', $startTs)) ?></strong>
                            </div>
                            <div class="col-md-3">
                                <div class="text-secondary small text-uppercase">Move out</div>
                                <strong class="fs-5"><?= e(date('d M Y', $endTs)) ?></strong>
                            </div>
                            <div class="col-md-3">
                                <div class="text-secondary small text-uppercase">Duration</div>
                                <strong class="fs-5"><?= $months ?> month<?= $months === 1 ? '' : 's' ?></strong>
                            </div>
                            <div class="col-md-3">
                                <div class="text-secondary small text-uppercase">Total rent</div>
                                <strong class="fs-5 text-emerald">RM <?= number_format($months * (float)$booking['monthly_rent']) ?></strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Student's note -->
                <?php if (!empty($booking['student_note'])): ?>
                <div class="col-12">
                    <div class="bg-white border rounded-3 p-4">
                        <h6 class="text-secondary text-uppercase small mb-2">Note from student</h6>
                        <p class="mb-0" style="white-space: pre-line;"><?= e($booking['student_note']) ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Previous response (if already actioned) -->
                <?php if (!empty($booking['landlord_response'])): ?>
                <div class="col-12">
                    <div class="bg-light border rounded-3 p-4">
                        <h6 class="text-secondary text-uppercase small mb-2">Your response</h6>
                        <p class="mb-0" style="white-space: pre-line;"><?= e($booking['landlord_response']) ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Action panel — only if pending_landlord -->
                <?php if ($booking['status'] === 'pending_landlord'): ?>
                <div class="col-12">
                    <div class="bg-white border rounded-3 p-4">
                        <h6 class="text-secondary text-uppercase small mb-3">Your response</h6>

                        <form method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">

                            <div class="mb-3">
                                <label class="form-label">Optional message <small class="text-secondary fw-normal">— shown to the student</small></label>
                                <textarea name="reason" rows="3"
                                          class="form-control <?= isset($errors['reason']) ? 'is-invalid' : '' ?>"
                                          placeholder="(Required if you reject)"><?= e($reason) ?></textarea>
                                <?php if (isset($errors['reason'])): ?>
                                    <div class="invalid-feedback"><?= e($errors['reason']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" name="action" value="approve" class="btn btn-success">
                                    <i class="bi bi-check-circle me-1"></i> Approve booking
                                </button>
                                <button type="submit" name="action" value="reject" class="btn btn-outline-danger"
                                        onclick="return confirm('Reject this booking? The student will be notified.');">
                                    <i class="bi bi-x-circle me-1"></i> Reject
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

</body>
</html>