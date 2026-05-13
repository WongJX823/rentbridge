<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bookings.php';
require_role('admin');

$bookingId = (int)($_GET['id'] ?? $_POST['booking_id'] ?? 0);
if ($bookingId <= 0) {
    http_response_code(400);
    die('Invalid booking ID.');
}

$pdo = db();

// Fetch everything we need
$stmt = $pdo->prepare("
    SELECT b.*,
           p.title          AS property_title,
           p.address        AS property_address,
           p.city           AS property_city,
           p.state          AS property_state,
           p.postcode       AS property_postcode,
           p.status         AS property_status,
           s.full_name      AS student_name,
           s.preferred_name AS student_nickname,
           s.matric_no      AS student_matric,
           s.phone          AS student_phone,
           us.email         AS student_email,
           l.full_name      AS landlord_name,
           l.preferred_name AS landlord_nickname,
           l.phone          AS landlord_phone,
           ul.email         AS landlord_email,
           a.full_name      AS agent_name,
           a.staff_id       AS agent_staff_id,
           a.department     AS agent_department,
           ua.email         AS agent_email,
           cb.email         AS cancelled_by_email,
           ct.id              AS contract_id,
           ct.contract_code   AS contract_code,
           ct.status          AS contract_status,
           ct.contract_pdf_path AS contract_pdf,
           ct.student_signed_at,
           ct.landlord_signed_at,
           ct.agent_signed_at
      FROM bookings b
      JOIN properties p ON p.id = b.property_id
      JOIN students   s ON s.user_id = b.student_id
      JOIN users      us ON us.id = b.student_id
      JOIN landlords  l ON l.user_id = b.landlord_id
      JOIN users      ul ON ul.id = b.landlord_id
      LEFT JOIN agents a ON a.user_id = b.agent_id
      LEFT JOIN users  ua ON ua.id = b.agent_id
      LEFT JOIN users  cb ON cb.id = b.cancelled_by
      LEFT JOIN contracts ct ON ct.booking_id = b.id
     WHERE b.id = ?
     LIMIT 1
");
$stmt->execute([$bookingId]);
$booking = $stmt->fetch();

if (!$booking) {
    http_response_code(404);
    die('Booking not found.');
}

$errors = [];
$reason = '';

// ---- HANDLE ADMIN ACTIONS ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $reason = trim($_POST['reason'] ?? '');

    if ($action === 'assign_agent') {
        $newAgentId = (int)($_POST['agent_id'] ?? 0);

        if ($newAgentId <= 0) {
            $errors['general'] = 'Please pick an agent.';
        } elseif ($booking['status'] !== 'pending_agent') {
            $errors['general'] = 'This booking is not awaiting agent assignment.';
        } else {
            try {
                $pdo->beginTransaction();

                // Update booking
                $stmt = $pdo->prepare('UPDATE bookings SET agent_id = ? WHERE id = ?');
                $stmt->execute([$newAgentId, $bookingId]);

                // Increment agent caseload
                $stmt = $pdo->prepare(
                    'UPDATE agents SET current_caseload = current_caseload + 1 WHERE user_id = ?'
                );
                $stmt->execute([$newAgentId]);

                $pdo->commit();

                // Notify the agent
                notify(
                    $newAgentId,
                    'agent_assignment',
                    'Manually assigned by admin',
                    'You have been assigned by an administrator to booking #' . $bookingId . '.',
                    '/rentbridge/agent/cases.php'
                );

                set_flash('success', 'Agent assigned successfully.');
                header('Location: /rentbridge/admin/booking.php?id=' . $bookingId);
                exit;

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors['general'] = 'Error: ' . $e->getMessage();
            }
        }
    }

    elseif ($action === 'cancel_admin') {
        if ($reason === '') {
            $errors['reason'] = 'Cancellation reason is required (for audit trail).';
        } elseif (in_array($booking['status'], ['completed', 'cancelled_by_student', 'cancelled_by_landlord', 'cancelled_by_admin', 'rejected_by_landlord'], true)) {
            $errors['general'] = 'This booking cannot be cancelled in its current state.';
        } else {
            try {
                $pdo->beginTransaction();

                // Cancel booking
                $stmt = $pdo->prepare(
                    'UPDATE bookings
                        SET status              = "cancelled_by_admin",
                            cancellation_reason = ?,
                            cancelled_by        = ?
                      WHERE id = ?'
                );
                $stmt->execute([$reason, current_user_id(), $bookingId]);

                // If there was an assigned agent, decrement their caseload
                if (!empty($booking['agent_id'])) {
                    $stmt = $pdo->prepare(
                        'UPDATE agents SET current_caseload = GREATEST(0, current_caseload - 1) WHERE user_id = ?'
                    );
                    $stmt->execute([(int)$booking['agent_id']]);
                }

                // If property was 'booked', set it back to 'available'
                if ($booking['property_status'] === 'booked') {
                    $stmt = $pdo->prepare(
                        'UPDATE properties SET status = "available" WHERE id = ?'
                    );
                    $stmt->execute([(int)$booking['property_id']]);
                }

                $pdo->commit();

                // Notify all parties
                $msg = 'Booking #' . $bookingId . ' for "' . $booking['property_title'] . '" was cancelled by an administrator. Reason: ' . $reason;
                foreach (['student_id', 'landlord_id', 'agent_id'] as $col) {
                    if (!empty($booking[$col])) {
                        notify(
                            (int)$booking[$col],
                            'booking_cancelled_admin',
                            'Booking cancelled by admin',
                            $msg,
                            '/rentbridge/index.php'
                        );
                    }
                }

                set_flash('success', 'Booking cancelled. All parties notified.');
                header('Location: /rentbridge/admin/bookings.php?status=cancelled_by_admin');
                exit;

            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $errors['general'] = 'Error: ' . $e->getMessage();
            }
        }
    }

    elseif ($action === 'retry_assign') {
        if ($booking['status'] !== 'pending_agent') {
            $errors['general'] = 'Auto-assignment only works on bookings awaiting an agent.';
        } else {
            $result = auto_assign_agent($bookingId);
            if ($result) {
                set_flash('success', 'Auto-assignment succeeded! Agent has been notified.');
            } else {
                set_flash('warning', 'Still no eligible agent. Try manual assignment below.');
            }
            header('Location: /rentbridge/admin/booking.php?id=' . $bookingId);
            exit;
        }
    }
}

// Get list of eligible agents for manual assignment (excluding landlord + already-rejected)
$eligibleAgents = [];
if ($booking['status'] === 'pending_agent' && empty($booking['agent_id'])) {
    $rejected = [];
    if (!empty($booking['rejected_agents'])) {
        $decoded = json_decode($booking['rejected_agents'], true);
        if (is_array($decoded)) $rejected = array_map('intval', $decoded);
    }
    $rejected[] = (int)$booking['landlord_id'];

    $placeholders = implode(',', array_fill(0, count($rejected), '?'));

    $stmt = $pdo->prepare("
        SELECT a.user_id, a.full_name, a.staff_id, a.department,
               a.current_caseload, a.max_caseload, a.availability
          FROM agents a
          JOIN users u ON u.id = a.user_id
         WHERE u.status = 'active'
           AND a.user_id NOT IN ($placeholders)
         ORDER BY a.current_caseload ASC, a.full_name ASC
    ");
    $stmt->execute($rejected);
    $eligibleAgents = $stmt->fetchAll();
}

function booking_status_label(string $status): array {
    return match ($status) {
        'pending_landlord'      => ['Awaiting landlord',     'warning'],
        'pending_agent'         => ['Awaiting agent',        'info'],
        'agent_assigned'        => ['Agent confirmed',       'primary'],
        'contract_pending'      => ['Contract pending',      'primary'],
        'active'                => ['Active tenancy',        'success'],
        'rejected_by_landlord'  => ['Rejected by landlord',  'danger'],
        'completed'             => ['Completed',             'secondary'],
        'cancelled_by_student'  => ['Cancelled by student',  'secondary'],
        'cancelled_by_landlord' => ['Cancelled by landlord', 'secondary'],
        'cancelled_by_admin'    => ['Cancelled by admin',    'danger'],
        default                 => [ucfirst($status),        'secondary'],
    };
}
[$label, $color] = booking_status_label($booking['status']);

$startTs = strtotime($booking['start_date']);
$endTs   = strtotime($booking['end_date']);
$months  = max(1, (int)round(($endTs - $startTs) / (30.44 * 86400)));
$isStuck = $booking['status'] === 'pending_agent' && empty($booking['agent_id']);
$isCancellable = !in_array($booking['status'], [
    'completed', 'cancelled_by_student', 'cancelled_by_landlord',
    'cancelled_by_admin', 'rejected_by_landlord'
], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Booking #<?= (int)$booking['id'] ?> · Admin · RentBridge</title>
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
        <div class="col-lg-10">

            <p class="small mb-3">
                <a href="/rentbridge/admin/bookings.php" class="text-secondary text-decoration-none">
                    <i class="bi bi-arrow-left"></i> All bookings
                </a>
            </p>

            <div class="d-flex justify-content-between align-items-start mb-4">
                <div>
                    <h1 class="mb-1">Booking #<?= (int)$booking['id'] ?></h1>
                    <p class="text-secondary mb-0">
                        Created <?= e(date('d M Y, H:i', strtotime($booking['created_at']))) ?>
                    </p>
                </div>
                <span class="badge bg-<?= $color ?> fs-6"><?= e($label) ?></span>
            </div>

            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-danger"><?= e($errors['general']) ?></div>
            <?php endif; ?>

            <?php $flash = get_flash(); if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
            <?php endif; ?>

            <div class="row g-4">

                <!-- Property -->
                <div class="col-12">
                    <div class="bg-white border rounded-3 p-4">
                        <h6 class="text-secondary text-uppercase small mb-3">Property</h6>
                        <h5><?= e($booking['property_title']) ?></h5>
                        <p class="text-secondary small mb-0">
                            <i class="bi bi-geo-alt"></i>
                            <?= e($booking['property_address']) ?>,
                            <?= e($booking['property_city']) ?> <?= e($booking['property_postcode']) ?>,
                            <?= e($booking['property_state']) ?>
                            &nbsp;·&nbsp;
                            Status: <span class="badge bg-light text-dark border"><?= e($booking['property_status']) ?></span>
                        </p>
                    </div>
                </div>

                <!-- 3 Parties -->
                <div class="col-md-4">
                    <div class="bg-white border rounded-3 p-4 h-100">
                        <h6 class="text-secondary text-uppercase small mb-3">Student</h6>
                        <h5 class="mb-1"><?= e($booking['student_name']) ?></h5>
                        <small class="text-secondary d-block mb-3">@<?= e($booking['student_nickname']) ?></small>
                        <div class="small text-secondary">
                            <div><i class="bi bi-card-text"></i> <?= e($booking['student_matric']) ?></div>
                            <div><i class="bi bi-envelope"></i> <?= e($booking['student_email']) ?></div>
                            <div><i class="bi bi-telephone"></i> <?= e($booking['student_phone']) ?></div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="bg-white border rounded-3 p-4 h-100">
                        <h6 class="text-secondary text-uppercase small mb-3">Landlord</h6>
                        <h5 class="mb-1"><?= e($booking['landlord_name']) ?></h5>
                        <small class="text-secondary d-block mb-3">@<?= e($booking['landlord_nickname']) ?></small>
                        <div class="small text-secondary">
                            <div><i class="bi bi-envelope"></i> <?= e($booking['landlord_email']) ?></div>
                            <div><i class="bi bi-telephone"></i> <?= e($booking['landlord_phone']) ?></div>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="bg-white border rounded-3 p-4 h-100">
                        <h6 class="text-secondary text-uppercase small mb-3">Witness Agent</h6>
                        <?php if (!empty($booking['agent_name'])): ?>
                            <h5 class="mb-1"><?= e($booking['agent_name']) ?></h5>
                            <small class="text-secondary d-block mb-3"><?= e($booking['agent_department']) ?> · <?= e($booking['agent_staff_id']) ?></small>
                            <div class="small text-secondary">
                                <div><i class="bi bi-envelope"></i> <?= e($booking['agent_email']) ?></div>
                            </div>
                        <?php else: ?>
                            <div class="text-secondary small">
                                <i class="bi bi-search"></i>
                                <?php if ($isStuck): ?>
                                    <strong class="text-warning">No agent assigned</strong> — manual assignment needed
                                <?php else: ?>
                                    Not yet assigned
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tenancy terms -->
                <div class="col-12">
                    <div class="bg-white border rounded-3 p-4">
                        <h6 class="text-secondary text-uppercase small mb-3">Tenancy terms</h6>
                        <div class="row text-center">
                            <div class="col-md-3">
                                <small class="text-secondary text-uppercase">Move in</small>
                                <div class="fw-semibold"><?= e(date('d M Y', $startTs)) ?></div>
                            </div>
                            <div class="col-md-3">
                                <small class="text-secondary text-uppercase">Move out</small>
                                <div class="fw-semibold"><?= e(date('d M Y', $endTs)) ?></div>
                            </div>
                            <div class="col-md-3">
                                <small class="text-secondary text-uppercase">Duration</small>
                                <div class="fw-semibold"><?= $months ?> month<?= $months === 1 ? '' : 's' ?></div>
                            </div>
                            <div class="col-md-3">
                                <small class="text-secondary text-uppercase">Total rent</small>
                                <div class="fw-semibold text-emerald">RM <?= number_format($months * (float)$booking['monthly_rent']) ?></div>
                            </div>
                        </div>
                    </div>      
                </div>

                <!-- Contract (if exists) -->
                <?php if (!empty($booking['contract_id'])): ?>
                <div class="col-12">
                    <div class="bg-white border rounded-3 p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h6 class="text-secondary text-uppercase small mb-0">Contract</h6>
                            <span class="badge bg-<?= match ($booking['contract_status']) {
                                'pending_signatures' => 'warning',
                                'active'             => 'success',
                                'completed'          => 'secondary',
                                'terminated'         => 'danger',
                                default              => 'secondary',
                            } ?>">
                                <?= e(ucfirst(str_replace('_',' ', $booking['contract_status']))) ?>
                            </span>
                        </div>

                        <div class="row g-3 align-items-center">
                            <div class="col-md-3">
                                <small class="text-secondary text-uppercase">Code</small>
                                <div class="fw-semibold"><code><?= e($booking['contract_code']) ?></code></div>
                            </div>
                            <div class="col-md-6">
                                <small class="text-secondary text-uppercase">Signatures</small>
                                <div class="small">
                                    <span class="me-2">
                                        <?= !empty($booking['student_signed_at']) ? '✓' : '✗' ?> Student
                                    </span>
                                    <span class="me-2">
                                        <?= !empty($booking['landlord_signed_at']) ? '✓' : '✗' ?> Landlord
                                    </span>
                                    <span>
                                        <?= !empty($booking['agent_signed_at']) ? '✓' : '✗' ?> Agent
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-3 text-md-end">
                                <div class="d-flex gap-2 justify-content-md-end flex-wrap">
                                    <?php if (!empty($booking['contract_pdf'])):
                                        $pdfFullPath = __DIR__ . '/../' . $booking['contract_pdf'];
                                        $cacheBust = file_exists($pdfFullPath) ? '?v=' . filemtime($pdfFullPath) : '';
                                    ?>
                                        <a href="/rentbridge/<?= e($booking['contract_pdf']) ?><?= $cacheBust ?>"
                                        target="_blank" class="btn btn-sm btn-success">
                                            <i class="bi bi-download me-1"></i> PDF
                                        </a>
                                    <?php endif; ?>
                                    <a href="/rentbridge/contracts/view.php?id=<?= (int)$booking['contract_id'] ?>"
                                    class="btn btn-sm btn-outline-dark">
                                        <i class="bi bi-file-earmark-text me-1"></i> Open
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Cancellation info (if cancelled) -->
                <?php if (!empty($booking['cancellation_reason'])): ?>
                <div class="col-12">
                    <div class="bg-white border rounded-3 p-4" style="border-left:4px solid #DC3545 !important;">
                        <h6 class="text-secondary text-uppercase small mb-2">Cancellation record</h6>
                        <div class="small text-secondary mb-2">
                            Cancelled by: <?= e($booking['cancelled_by_email'] ?? 'unknown') ?>
                        </div>
                        <p class="mb-0" style="white-space: pre-line;"><?= e($booking['cancellation_reason']) ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Rejected agents history (audit trail) -->
                <?php if (!empty($booking['rejected_agents'])):
                    $rejected = json_decode($booking['rejected_agents'], true) ?? [];
                    if (!empty($rejected)):
                ?>
                <div class="col-12">
                    <div class="bg-light border rounded-3 p-4">
                        <h6 class="text-secondary text-uppercase small mb-2">
                            <i class="bi bi-clock-history"></i> Agent rejection history (admin-only)
                        </h6>
                        <small class="text-secondary">
                            <?= count($rejected) ?> agent<?= count($rejected) === 1 ? '' : 's' ?> previously declined this case
                            (IDs: <?= e(implode(', ', $rejected)) ?>). They are excluded from auto-assignment.
                        </small>
                    </div>
                </div>
                <?php endif; endif; ?>

                <!-- ADMIN ACTIONS PANEL -->
                <?php if ($isStuck): ?>
                <!-- Stuck booking: show retry + manual assign -->
                <div class="col-12">
                    <div class="bg-white border rounded-3 p-4" style="border-left:4px solid #D4A017 !important;">
                        <h6 class="text-secondary text-uppercase small mb-3">⚠ Manual agent assignment</h6>
                        <p class="text-secondary small">
                            Auto-assignment couldn't find an eligible agent. Try retrying the algorithm, or pick an agent manually below.
                        </p>

                        <!-- Retry auto-assign -->
                        <form method="POST" class="mb-3">
                            <?= csrf_field() ?>
                            <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
                            <button type="submit" name="action" value="retry_assign" class="btn btn-outline-primary">
                                <i class="bi bi-arrow-clockwise me-1"></i> Retry auto-assignment
                            </button>
                        </form>

                        <hr>

                        <!-- Manual assign -->
                        <form method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">

                            <label class="form-label small fw-semibold">Or pick an agent to manually assign:</label>

                            <?php if (empty($eligibleAgents)): ?>
                                <div class="alert alert-danger small mb-2">
                                    <i class="bi bi-exclamation-circle"></i>
                                    No eligible agents at all — every active agent has either been rejected, is the landlord, or no agents exist.
                                </div>
                            <?php else: ?>
                                <select name="agent_id" class="form-select mb-3" required>
                                    <option value="">— Choose an agent —</option>
                                    <?php foreach ($eligibleAgents as $a):
                                        $isFull = (int)$a['current_caseload'] >= (int)$a['max_caseload'];
                                        $isUnavailable = $a['availability'] !== 'available';
                                    ?>
                                        <option value="<?= (int)$a['user_id'] ?>"
                                                <?= ($isFull || $isUnavailable) ? 'disabled' : '' ?>>
                                            <?= e($a['full_name']) ?>
                                            (<?= e($a['department']) ?>)
                                            · Caseload: <?= (int)$a['current_caseload'] ?>/<?= (int)$a['max_caseload'] ?>
                                            <?php if ($isFull): ?>· FULL<?php endif; ?>
                                            <?php if ($isUnavailable): ?>· <?= e($a['availability']) ?><?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" name="action" value="assign_agent" class="btn btn-success">
                                    <i class="bi bi-person-plus me-1"></i> Manually assign
                                </button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Admin cancel (always available for non-terminal states) -->
                <?php if ($isCancellable): ?>
                <div class="col-12">
                    <div class="bg-white border rounded-3 p-4" style="border-left:4px solid #DC3545 !important;">
                        <h6 class="text-secondary text-uppercase small mb-3">Admin override: cancel booking</h6>
                        <p class="text-secondary small">
                            Use this only for dispute resolution, misconduct, or system errors. All parties will be notified with your reason.
                        </p>

                        <form method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">

                            <div class="mb-3">
                                <label class="form-label small">Cancellation reason <small class="text-secondary fw-normal">— required for audit trail</small></label>
                                <textarea name="reason" rows="3"
                                          class="form-control <?= isset($errors['reason']) ? 'is-invalid' : '' ?>"
                                          placeholder="e.g. Property reported as misrepresented, mutual agreement, suspected fraud..."><?= e($reason) ?></textarea>
                                <?php if (isset($errors['reason'])): ?>
                                    <div class="invalid-feedback"><?= e($errors['reason']) ?></div>
                                <?php endif; ?>
                            </div>

                            <button type="submit" name="action" value="cancel_admin" class="btn btn-outline-danger"
                                    onclick="return confirm('Cancel this booking on behalf of admin? All parties will be notified. This cannot be undone.');">
                                <i class="bi bi-x-octagon me-1"></i> Cancel booking
                            </button>
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