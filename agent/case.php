<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/bookings.php';
require_role('agent');

$caseId = (int)($_GET['id'] ?? $_POST['booking_id'] ?? 0);
if ($caseId <= 0) {
    http_response_code(400);
    die('Invalid case ID.');
}

$pdo = db();

// Must belong to THIS agent
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
           us.email      AS student_email,
           l.full_name   AS landlord_name,
           l.phone       AS landlord_phone,
           ul.email      AS landlord_email
      FROM bookings b
      JOIN properties p ON p.id = b.property_id
      JOIN students   s ON s.user_id = b.student_id
      JOIN users      us ON us.id = b.student_id
      JOIN landlords  l ON l.user_id = b.landlord_id
      JOIN users      ul ON ul.id = b.landlord_id
     WHERE b.id = ? AND b.agent_id = ?
     LIMIT 1
");
$stmt->execute([$caseId, current_user_id()]);
$case = $stmt->fetch();

if (!$case) {
    http_response_code(404);
    die('Case not found.');
}

$errors = [];
$reason = '';

// ---- HANDLE ACCEPT / REJECT ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $reason = trim($_POST['reason'] ?? '');

    if ($case['status'] !== 'pending_agent') {
        $errors['general'] = 'This case has already been processed.';
    } elseif (!in_array($action, ['accept', 'reject'], true)) {
        $errors['general'] = 'Invalid action.';
    } elseif ($action === 'reject' && $reason === '') {
        $errors['reason'] = 'Please give a reason so admin understands your decline.';
    } else {
        try {
            if ($action === 'accept') {
                $pdo->beginTransaction();

                // Status → agent_assigned
                $stmt = $pdo->prepare(
                    'UPDATE bookings SET status = "agent_assigned" WHERE id = ?'
                );
                $stmt->execute([$caseId]);

                // Mark property as booked (off public listings)
                $stmt = $pdo->prepare(
                    'UPDATE properties SET status = "booked" WHERE id = ?'
                );
                $stmt->execute([(int)$case['property_id']]);

                $pdo->commit();

                // Notify student + landlord
                notify(
                    (int)$case['student_id'],
                    'agent_accepted',
                    'Your UTeM agent is on the case!',
                    'Agent has accepted to witness your tenancy for "' . $case['property_title'] . '".',
                    '/rentbridge/student/bookings.php'
                );

                notify(
                    (int)$case['landlord_id'],
                    'agent_accepted',
                    'Agent confirmed for booking #' . $caseId,
                    'A UTeM staff agent has accepted to witness this tenancy.',
                    '/rentbridge/landlord/bookings.php'
                );

                set_flash('success', 'Case accepted. The tenancy will now proceed to contract signing.');
                header('Location: /rentbridge/agent/cases.php');
                exit;
            }

            // REJECT — re-trigger assignment
            $newAgentId = reassign_agent($caseId, current_user_id(), $reason);

            if ($newAgentId) {
                set_flash('info', 'Case declined. Reassigned to another agent.');
            } else {
                set_flash('warning', 'Case declined. No other available agent — admin notified.');
            }
            header('Location: /rentbridge/agent/cases.php');
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors['general'] = 'Something went wrong: ' . $e->getMessage();
        }
    }
}

function case_status_label(string $status): array {
    return match ($status) {
        'pending_agent'         => ['Awaiting your response', 'warning'],
        'agent_assigned'        => ['You accepted',           'success'],
        'contract_pending'      => ['Contract pending',       'primary'],
        'active'                => ['Active tenancy',         'success'],
        'completed'             => ['Completed',              'secondary'],
        'cancelled_by_student'  => ['Cancelled by student',   'secondary'],
        'cancelled_by_landlord' => ['Cancelled by landlord',  'secondary'],
        default                 => [ucfirst($status),         'secondary'],
    };
}
[$label, $color] = case_status_label($case['status']);

$startTs = strtotime($case['start_date']);
$endTs   = strtotime($case['end_date']);
$months  = max(1, (int)round(($endTs - $startTs) / (30.44 * 86400)));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Case #<?= (int)$case['id'] ?> · RentBridge</title>
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
                <a href="/rentbridge/agent/cases.php" class="text-secondary text-decoration-none">
                    <i class="bi bi-arrow-left"></i> All cases
                </a>
            </p>

            <div class="d-flex justify-content-between align-items-start mb-4">
                <div>
                    <h1 class="mb-1">Case #<?= (int)$case['id'] ?></h1>
                    <p class="text-secondary mb-0">
                        Assigned to you · <?= e(date('d M Y, H:i', strtotime($case['updated_at']))) ?>
                    </p>
                </div>
                <span class="badge bg-<?= $color ?> fs-6"><?= e($label) ?></span>
            </div>

            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-danger"><?= e($errors['general']) ?></div>
            <?php endif; ?>

            <div class="row g-4">

                <!-- Property -->
                <div class="col-12">
                    <div class="bg-white border rounded-3 p-4">
                        <h6 class="text-secondary text-uppercase small mb-3">Property</h6>
                        <h5><?= e($case['property_title']) ?></h5>
                        <p class="text-secondary small mb-0">
                            <i class="bi bi-geo-alt"></i>
                            <?= e($case['property_address']) ?>,
                            <?= e($case['property_city']) ?> <?= e($case['property_postcode']) ?>,
                            <?= e($case['property_state']) ?>
                        </p>
                    </div>
                </div>

                <!-- Student -->
                <div class="col-md-6">
                    <div class="bg-white border rounded-3 p-4 h-100">
                        <h6 class="text-secondary text-uppercase small mb-3">Student (Tenant)</h6>
                        <h5><?= e($case['student_name']) ?></h5>
                        <div class="small text-secondary">
                            <div><i class="bi bi-card-text"></i> Matric: <?= e($case['student_matric']) ?></div>
                            <div><i class="bi bi-envelope"></i> <?= e($case['student_email']) ?></div>
                            <div><i class="bi bi-telephone"></i> <?= e($case['student_phone']) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Landlord -->
                <div class="col-md-6">
                    <div class="bg-white border rounded-3 p-4 h-100">
                        <h6 class="text-secondary text-uppercase small mb-3">Landlord</h6>
                        <h5><?= e($case['landlord_name']) ?></h5>
                        <div class="small text-secondary">
                            <div><i class="bi bi-envelope"></i> <?= e($case['landlord_email']) ?></div>
                            <div><i class="bi bi-telephone"></i> <?= e($case['landlord_phone']) ?></div>
                        </div>
                    </div>
                </div>

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
                                <strong class="fs-5 text-emerald">RM <?= number_format($months * (float)$case['monthly_rent']) ?></strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Student note -->
                <?php if (!empty($case['student_note'])): ?>
                <div class="col-12">
                    <div class="bg-white border rounded-3 p-4">
                        <h6 class="text-secondary text-uppercase small mb-2">Student's note</h6>
                        <p class="mb-0" style="white-space:pre-line;"><?= e($case['student_note']) ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Landlord response (if any) -->
                <?php if (!empty($case['landlord_response'])): ?>
                <div class="col-12">
                    <div class="bg-light border rounded-3 p-4">
                        <h6 class="text-secondary text-uppercase small mb-2">Landlord's note</h6>
                        <p class="mb-0" style="white-space:pre-line;"><?= e($case['landlord_response']) ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Action panel — only if pending_agent -->
                <?php if ($case['status'] === 'pending_agent'): ?>
                <div class="col-12">
                    <div class="bg-white border rounded-3 p-4">
                        <h6 class="text-secondary text-uppercase small mb-3">Your response</h6>

                        <div class="alert alert-info border-0 small" style="background:var(--rb-cream);">
                            <i class="bi bi-info-circle"></i>
                            <strong>Reminder:</strong> Accepting means you'll witness this tenancy contract and be the case handler. If you accept then need to back out later, contact admin.
                        </div>

                        <form method="POST">
                            <?= csrf_field() ?>
                            <input type="hidden" name="booking_id" value="<?= (int)$case['id'] ?>">

                            <div class="mb-3">
                                <label class="form-label">Optional message <small class="text-secondary fw-normal">— required if rejecting</small></label>
                                <textarea name="reason" rows="3"
                                          class="form-control <?= isset($errors['reason']) ? 'is-invalid' : '' ?>"
                                          placeholder="e.g. Reason for declining"><?= e($reason) ?></textarea>
                                <?php if (isset($errors['reason'])): ?>
                                    <div class="invalid-feedback"><?= e($errors['reason']) ?></div>
                                <?php endif; ?>
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" name="action" value="accept" class="btn btn-success">
                                    <i class="bi bi-check-circle me-1"></i> Accept case
                                </button>
                                <button type="submit" name="action" value="reject" class="btn btn-outline-danger"
                                        onclick="return confirm('Decline this case? The system will reassign to another agent.');">
                                    <i class="bi bi-x-circle me-1"></i> Decline
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