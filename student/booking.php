<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('student');

$bookingId = (int)($_GET['id'] ?? 0);
if ($bookingId <= 0) {
    http_response_code(400);
    die('Invalid booking ID.');
}

$pdo = db();

// Fetch booking + everyone (student must own this booking)
$stmt = $pdo->prepare("
    SELECT b.*,
           p.title          AS property_title,
           p.address        AS property_address,
           p.city           AS property_city,
           p.state          AS property_state,
           p.postcode       AS property_postcode,
           l.full_name      AS landlord_name,
           l.preferred_name AS landlord_nickname,
           l.phone          AS landlord_phone,
           ul.email         AS landlord_email,
           a.full_name      AS agent_name,
           a.preferred_name AS agent_nickname,
           a.staff_id       AS agent_staff_id,
           a.department     AS agent_department,
           a.phone          AS agent_phone,
           ua.email         AS agent_email,
           ct.id                 AS contract_id,
           ct.contract_code      AS contract_code,
           ct.status             AS contract_status,
           ct.contract_pdf_path  AS contract_pdf,
           ct.student_signed_at  AS c_student_signed,
           ct.landlord_signed_at AS c_landlord_signed,
           ct.agent_signed_at    AS c_agent_signed,
           ct.activated_at       AS c_activated_at,
           (SELECT image_path FROM property_images
             WHERE property_id = p.id
             ORDER BY is_primary DESC, id ASC LIMIT 1) AS image_path
      FROM bookings b
      JOIN properties p ON p.id = b.property_id
      JOIN landlords  l ON l.user_id = b.landlord_id
      JOIN users      ul ON ul.id = b.landlord_id
      LEFT JOIN agents a ON a.user_id = b.agent_id
      LEFT JOIN users  ua ON ua.id = b.agent_id
      LEFT JOIN contracts ct ON ct.booking_id = b.id
     WHERE b.id = ? AND b.student_id = ?
     LIMIT 1
");
$stmt->execute([$bookingId, current_user_id()]);
$booking = $stmt->fetch();

if (!$booking) {
    http_response_code(404);
    die('Booking not found.');
}

function status_label(string $status): array {
    return match ($status) {
        'pending_landlord'      => ['Waiting for landlord', 'warning'],
        'rejected_by_landlord'  => ['Rejected by landlord', 'danger'],
        'pending_agent'         => ['Waiting for agent',    'info'],
        'agent_assigned'        => ['Agent confirmed',      'primary'],
        'contract_pending'      => ['Contract pending',     'primary'],
        'active'                => ['Active tenancy',       'success'],
        'completed'             => ['Completed',            'secondary'],
        'cancelled_by_student'  => ['Cancelled by you',     'secondary'],
        'cancelled_by_landlord' => ['Cancelled by landlord','danger'],
        'cancelled_by_admin'    => ['Cancelled by admin',   'danger'],
        default                 => [ucfirst($status),       'secondary'],
    };
}
[$label, $color] = status_label($booking['status']);

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
        <div class="col-lg-10">

            <p class="small mb-3">
                <a href="/rentbridge/student/bookings.php" class="text-secondary text-decoration-none">
                    <i class="bi bi-arrow-left"></i> All my bookings
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

            <div class="row g-4">

                <!-- Property -->
                <div class="col-12">
                    <div class="bg-white border rounded-3 overflow-hidden">
                        <div class="row g-0">
                            <div class="col-md-4" style="background:linear-gradient(135deg,#E6ECF4,#E4F2EA); min-height: 200px;">
                                <?php if (!empty($booking['image_path'])): ?>
                                    <img src="/rentbridge/<?= e($booking['image_path']) ?>"
                                         style="width:100%; height:100%; object-fit:cover;" alt="">
                                <?php endif; ?>
                            </div>
                            <div class="col-md-8 p-4">
                                <h6 class="text-secondary text-uppercase small mb-2">Property</h6>
                                <h4 class="mb-2"><?= e($booking['property_title']) ?></h4>
                                <p class="text-secondary small mb-0">
                                    <i class="bi bi-geo-alt"></i>
                                    <?= e($booking['property_address']) ?>,
                                    <?= e($booking['property_city']) ?> <?= e($booking['property_postcode']) ?>,
                                    <?= e($booking['property_state']) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Landlord + Agent -->
                <div class="col-md-6">
                    <div class="bg-white border rounded-3 p-4 h-100">
                        <h6 class="text-secondary text-uppercase small mb-3">Landlord</h6>
                        <h5 class="mb-1"><?= e($booking['landlord_name']) ?></h5>
                        <small class="text-secondary d-block mb-3">@<?= e($booking['landlord_nickname']) ?></small>
                        <?php if (in_array($booking['status'], ['agent_assigned','contract_pending','active','completed'])): ?>
                            <div class="small text-secondary">
                                <div><i class="bi bi-envelope"></i> <?= e($booking['landlord_email']) ?></div>
                                <div><i class="bi bi-telephone"></i> <?= e($booking['landlord_phone']) ?></div>
                            </div>
                        <?php else: ?>
                            <small class="text-secondary"><i class="bi bi-lock"></i> Contact details unlocked after agent assignment.</small>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="bg-white border rounded-3 p-4 h-100" style="background: var(--rb-cream);">
                        <h6 class="text-secondary text-uppercase small mb-3">Witness Agent</h6>
                        <?php if (!empty($booking['agent_name'])): ?>
                            <h5 class="mb-1"><?= e($booking['agent_name']) ?></h5>
                            <small class="text-secondary d-block mb-3">
                                <?= e($booking['agent_department']) ?> · UTeM
                                <?php if ($booking['status'] === 'pending_agent'): ?>
                                    <span class="badge bg-warning text-dark ms-1">🟡 awaiting confirmation</span>
                                <?php else: ?>
                                    <span class="badge bg-success ms-1">✓ confirmed</span>
                                <?php endif; ?>
                            </small>
                            <?php if (in_array($booking['status'], ['agent_assigned','contract_pending','active','completed'])): ?>
                                <div class="small text-secondary">
                                    <div><i class="bi bi-envelope"></i> <?= e($booking['agent_email']) ?></div>
                                    <div><i class="bi bi-telephone"></i> <?= e($booking['agent_phone']) ?></div>
                                </div>
                            <?php endif; ?>
                        <?php elseif ($booking['status'] === 'pending_agent'): ?>
                            <div class="small text-secondary">
                                <i class="bi bi-search"></i> Looking for a UTeM staff agent…
                            </div>
                        <?php else: ?>
                            <div class="small text-secondary">Not yet assigned</div>
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
                                <div class="fw-semibold fs-5"><?= e(date('d M Y', $startTs)) ?></div>
                            </div>
                            <div class="col-md-3">
                                <small class="text-secondary text-uppercase">Move out</small>
                                <div class="fw-semibold fs-5"><?= e(date('d M Y', $endTs)) ?></div>
                            </div>
                            <div class="col-md-3">
                                <small class="text-secondary text-uppercase">Duration</small>
                                <div class="fw-semibold fs-5"><?= $months ?> month<?= $months===1?'':'s' ?></div>
                            </div>
                            <div class="col-md-3">
                                <small class="text-secondary text-uppercase">Total rent</small>
                                <div class="fw-semibold fs-5 text-emerald">RM <?= number_format($months * (float)$booking['monthly_rent']) ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Your note -->
                <?php if (!empty($booking['student_note'])): ?>
                <div class="col-12">
                    <div class="bg-white border rounded-3 p-4">
                        <h6 class="text-secondary text-uppercase small mb-2">Your note to landlord</h6>
                        <p class="mb-0" style="white-space:pre-line;"><?= e($booking['student_note']) ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Landlord response (if any) -->
                <?php if (!empty($booking['landlord_response'])): ?>
                <div class="col-12">
                    <div class="bg-light border rounded-3 p-4">
                        <h6 class="text-secondary text-uppercase small mb-2">Landlord's note</h6>
                        <p class="mb-0" style="white-space:pre-line;"><?= e($booking['landlord_response']) ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Contract section (if exists) -->
                <?php if (!empty($booking['contract_id'])):
                    $contractStatus = $booking['contract_status'];
                    $sigCount = ((int)!empty($booking['c_student_signed']))
                              + ((int)!empty($booking['c_landlord_signed']))
                              + ((int)!empty($booking['c_agent_signed']));
                    $statusColor = match ($contractStatus) {
                        'pending_signatures' => 'warning',
                        'active'             => 'success',
                        'completed'          => 'secondary',
                        'terminated'         => 'danger',
                        default              => 'secondary',
                    };
                    $pdfFullPath = !empty($booking['contract_pdf']) ? __DIR__ . '/../' . $booking['contract_pdf'] : null;
                    $cacheBust = ($pdfFullPath && file_exists($pdfFullPath)) ? '?v=' . filemtime($pdfFullPath) : '';
                    $myTurn = !empty($booking['contract_id'])
                              && $contractStatus === 'pending_signatures'
                              && empty($booking['c_student_signed']);
                ?>
                <div class="col-12">
                    <div class="bg-white border rounded-3 p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h6 class="text-secondary text-uppercase small mb-1">Contract</h6>
                                <h5 class="mb-0"><code><?= e($booking['contract_code']) ?></code></h5>
                            </div>
                            <span class="badge bg-<?= $statusColor ?> fs-6">
                                <?= e(ucfirst(str_replace('_',' ', $contractStatus))) ?>
                            </span>
                        </div>

                        <!-- Your-turn alert -->
                        <?php if ($myTurn): ?>
                            <div class="alert alert-warning d-flex align-items-center gap-3 mb-3">
                                <i class="bi bi-pen-fill fs-4"></i>
                                <div class="flex-grow-1">
                                    <strong>Your signature is needed</strong>
                                    <div class="small">You sign first, then your landlord, then the witness agent.</div>
                                </div>
                                <a href="/rentbridge/contracts/sign.php?id=<?= (int)$booking['contract_id'] ?>"
                                   class="btn btn-success">
                                    Sign now <i class="bi bi-arrow-right ms-1"></i>
                                </a>
                            </div>
                        <?php endif; ?>

                        <!-- Sign progress -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <small class="text-secondary text-uppercase">You</small>
                                <div class="<?= !empty($booking['c_student_signed']) ? 'text-success' : 'text-secondary' ?>">
                                    <?= !empty($booking['c_student_signed'])
                                        ? '✓ Signed ' . e(date('d M Y, H:i', strtotime($booking['c_student_signed'])))
                                        : '○ Not signed yet' ?>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <small class="text-secondary text-uppercase">Landlord</small>
                                <div class="<?= !empty($booking['c_landlord_signed']) ? 'text-success' : 'text-secondary' ?>">
                                    <?= !empty($booking['c_landlord_signed'])
                                        ? '✓ Signed ' . e(date('d M Y, H:i', strtotime($booking['c_landlord_signed'])))
                                        : '○ Not signed yet' ?>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <small class="text-secondary text-uppercase">Witness Agent</small>
                                <div class="<?= !empty($booking['c_agent_signed']) ? 'text-success' : 'text-secondary' ?>">
                                    <?= !empty($booking['c_agent_signed'])
                                        ? '✓ Signed ' . e(date('d M Y, H:i', strtotime($booking['c_agent_signed'])))
                                        : '○ Not signed yet' ?>
                                </div>
                            </div>
                        </div>

                        <?php if (!empty($booking['c_activated_at'])): ?>
                            <div class="small text-secondary mb-3">
                                <i class="bi bi-check-circle-fill text-success"></i>
                                Contract activated <?= e(date('d M Y, H:i', strtotime($booking['c_activated_at']))) ?>
                            </div>
                        <?php endif; ?>

                        <div class="d-flex gap-2 flex-wrap">
                            <?php if (!empty($booking['contract_pdf']) && $pdfFullPath && file_exists($pdfFullPath)): ?>
                                <a href="/rentbridge/<?= e($booking['contract_pdf']) ?><?= $cacheBust ?>"
                                   target="_blank" class="btn btn-success">
                                    <i class="bi bi-download me-1"></i> Download PDF
                                </a>
                            <?php endif; ?>
                            <a href="/rentbridge/contracts/view.php?id=<?= (int)$booking['contract_id'] ?>"
                               class="btn btn-outline-dark">
                                <i class="bi bi-file-earmark-text me-1"></i> View full contract
                            </a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Cancellation info (if cancelled) -->
                <?php if (!empty($booking['cancellation_reason'])): ?>
                <div class="col-12">
                    <div class="bg-white border rounded-3 p-4" style="border-left:4px solid #DC3545 !important;">
                        <h6 class="text-secondary text-uppercase small mb-2">Cancellation</h6>
                        <p class="mb-0" style="white-space: pre-line;"><?= e($booking['cancellation_reason']) ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- REPORT ISSUE -->
                <div class="col-12">
                    <div class="text-center pt-2 pb-4">
                        <button type="button"
                                class="btn btn-link btn-sm text-secondary text-decoration-none p-0"
                                data-bs-toggle="modal" data-bs-target="#reportModal">
                            <i class="bi bi-flag me-1"></i> Report an issue with this booking
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/../includes/reports.php';
$reportSubjects = [];
if (!empty($booking['landlord_id']))
    $reportSubjects[] = ['id' => (int)$booking['landlord_id'], 'name' => $booking['landlord_name'], 'role' => 'landlord'];
if (!empty($booking['agent_id']))
    $reportSubjects[] = ['id' => (int)$booking['agent_id'], 'name' => $booking['agent_name'], 'role' => 'agent'];

if (!empty($reportSubjects)):
    render_report_modal($reportSubjects, 'booking', (int)$booking['id']);
endif;
?>

</body>
</html>