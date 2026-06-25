<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/contracts.php';
require_login();

$contractId = (int)($_GET['id'] ?? 0);
if ($contractId <= 0) {
    http_response_code(400);
    die('Invalid contract ID.');
}

$pdo = db();
$stmt = $pdo->prepare("
    SELECT c.*,
           p.title       AS property_title,
           p.property_type,
           p.address     AS property_address,
           p.city        AS property_city,
           p.state       AS property_state,
           p.postcode    AS property_postcode,
           p.furnishing,
           p.facilities,
           s.full_name   AS student_name,
           s.matric_no   AS student_matric,
           s.phone       AS student_phone,
           us.email      AS student_email,
           l.full_name   AS landlord_name,
           l.ic_no       AS landlord_ic,
           l.phone       AS landlord_phone,
           ul.email      AS landlord_email,
           a.full_name   AS agent_name,
           a.staff_id    AS agent_staff_id,
           a.department  AS agent_department,
           a.phone       AS agent_phone,
           ua.email      AS agent_email
      FROM contracts c
      JOIN properties p ON p.id = c.property_id
      JOIN students   s ON s.user_id = c.student_id
      JOIN users      us ON us.id = c.student_id
      JOIN landlords  l ON l.user_id = c.landlord_id
      JOIN users      ul ON ul.id = c.landlord_id
      JOIN agents     a ON a.user_id = c.agent_id
      JOIN users      ua ON ua.id = c.agent_id
     WHERE c.id = ?
     LIMIT 1
");
$stmt->execute([$contractId]);
$contract = $stmt->fetch();

if (!$contract) {
    http_response_code(404);
    die('Contract not found.');
}

// Access control: only parties + admin
if (!contract_can_view($contract, current_user_id(), current_role())) {
    http_response_code(403);
    die('You are not a party to this contract.');
}

// Calculate total months for display
$startTs = strtotime($contract['start_date']);
$endTs   = strtotime($contract['end_date']);
$months  = max(1, (int)round(($endTs - $startTs) / (30.44 * 86400)));

$nextSigner   = contract_next_signer($contract);
$canSignNow   = contract_can_sign($contract, current_user_id());

// Fetch co-tenants for this contract
$ctStmt = $pdo->prepare("SELECT * FROM co_tenants WHERE tenancy_id = ? ORDER BY sign_order ASC, id ASC");
$ctStmt->execute([(int)$contract['tenancy_id']]);
$coTenants = $ctStmt->fetchAll();

// Check if current user is a co-tenant who can still reject
$myCoTenant = null;
foreach ($coTenants as $ct) {
    if ((int)$ct['student_id'] === current_user_id() && $ct['status'] === 'pending') {
        $myCoTenant = $ct;
        break;
    }
}
$canReject = $myCoTenant !== null && $contract['status'] === 'pending_signatures';

// Status badge
$statusBadge = match ($contract['status']) {
    'pending_signatures' => ['Pending signatures', 'warning'],
    'active'             => ['Active',             'success'],
    'completed'          => ['Completed',          'secondary'],
    'terminated'         => ['Terminated',         'danger'],
    default              => [ucfirst($contract['status']), 'secondary'],
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= e($contract['contract_code']) ?> · RentBridge</title>
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

            <!-- Top action bar -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <p class="small mb-0">
                    <a href="javascript:history.back()" class="text-secondary text-decoration-none">
                        <i class="bi bi-arrow-left"></i> Back
                    </a>
                </p>
                <div class="d-flex gap-2">
                    <span class="badge bg-<?= $statusBadge[1] ?> fs-6"><?= e($statusBadge[0]) ?></span>
                    <?php if (!empty($contract['contract_pdf_path'])):
    $pdfFullPath = __DIR__ . '/../' . $contract['contract_pdf_path'];
    $cacheBust = file_exists($pdfFullPath) ? '?v=' . filemtime($pdfFullPath) : '';
?>
    <a href="/rentbridge/<?= e($contract['contract_pdf_path']) ?><?= $cacheBust ?>"
       class="btn btn-success btn-sm" target="_blank">
        <i class="bi bi-download me-1"></i> Download PDF
    </a>
<?php endif; ?>
                </div>
            </div>

            <!-- Contract header -->
            <div class="bg-white border rounded-3 p-4 p-md-5 mb-4">
                <div class="text-center mb-4">
                    <small class="text-secondary text-uppercase fw-semibold" style="letter-spacing:.15em;">
                        Bilateral Tenancy Agreement
                    </small>
                    <h1 class="mt-2 mb-1">RentBridge Contract</h1>
                    <p class="text-secondary mb-0">
                        Contract code: <code class="text-navy fw-semibold"><?= e($contract['contract_code']) ?></code>
                    </p>
                    <p class="text-secondary mb-0 small">
                        Generated <?= e(date('d M Y', strtotime($contract['created_at']))) ?>
                    </p>
                </div>

                <hr class="my-4">

                <!-- PARTIES -->
                <h5 class="mb-3">Parties to this Agreement</h5>
                <div class="row g-3 mb-4">

                    <!-- LANDLORD -->
                    <div class="col-md-4">
                        <div class="border rounded-3 p-3 h-100">
                            <small class="text-secondary text-uppercase">1. Landlord</small>
                            <h6 class="mt-1 mb-1"><?= e($contract['landlord_name']) ?></h6>
                            <div class="small text-secondary">
                                <div>IC: <?= e($contract['landlord_ic']) ?></div>
                                <div><?= e($contract['landlord_email']) ?></div>
                                <div><?= e($contract['landlord_phone']) ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- TENANTS (primary + co-tenants) -->
                    <?php foreach ($coTenants as $idx => $ct): $num = $idx + 2; ?>
                    <div class="col-md-4">
                        <div class="border rounded-3 p-3 h-100">
                            <small class="text-secondary text-uppercase">
                                <?= $num ?>. <?= (int)$ct['is_primary'] ? 'Primary Tenant' : 'Co-Tenant' ?>
                            </small>
                            <h6 class="mt-1 mb-1"><?= e($ct['full_name']) ?></h6>
                            <div class="small text-secondary">
                                <div>NRIC: <?= e($ct['ic_number']) ?></div>
                                <div><?= e($ct['email']) ?></div>
                                <div><?= e($ct['phone']) ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- PROPERTY -->
                <h5 class="mb-2">Property</h5>
                <div class="border rounded-3 p-3 mb-4">
                    <h6 class="mb-1"><?= e($contract['property_title']) ?></h6>
                    <p class="text-secondary small mb-1">
                        <?= e($contract['property_address']) ?>,
                        <?= e($contract['property_city']) ?> <?= e($contract['property_postcode']) ?>,
                        <?= e($contract['property_state']) ?>
                    </p>
                    <p class="small mb-0">
                        Type: <strong><?= e(ucfirst(str_replace('_',' ',$contract['property_type']))) ?></strong>
                        &nbsp;·&nbsp;
                        Furnishing: <strong><?= e(ucfirst($contract['furnishing'])) ?></strong>
                        <?php if (!empty($contract['facilities'])): ?>
                            <br>Facilities: <?= e($contract['facilities']) ?>
                        <?php endif; ?>
                    </p>
                </div>

                <!-- TENANCY TERMS -->
                <h5 class="mb-2">Tenancy Terms</h5>
                <div class="border rounded-3 p-3 mb-4">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <small class="text-secondary text-uppercase">Start date</small>
                            <div class="fw-semibold"><?= e(date('d M Y', $startTs)) ?></div>
                        </div>
                        <div class="col-md-3">
                            <small class="text-secondary text-uppercase">End date</small>
                            <div class="fw-semibold"><?= e(date('d M Y', $endTs)) ?></div>
                        </div>
                        <div class="col-md-3">
                            <small class="text-secondary text-uppercase">Duration</small>
                            <div class="fw-semibold"><?= $months ?> month<?= $months===1?'':'s' ?></div>
                        </div>
                        <div class="col-md-3">
                            <small class="text-secondary text-uppercase">Monthly rent</small>
                            <div class="fw-semibold text-emerald">RM <?= number_format((float)$contract['monthly_rent']) ?></div>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-md-6">
                            <small class="text-secondary text-uppercase">Security deposit</small>
                            <div class="fw-semibold">RM <?= number_format((float)$contract['deposit']) ?></div>
                        </div>
                        <div class="col-md-6">
                            <small class="text-secondary text-uppercase">Total contract value</small>
                            <div class="fw-semibold">RM <?= number_format($months * (float)$contract['monthly_rent']) ?></div>
                        </div>
                    </div>
                </div>

                <!-- STANDARD TERMS -->
                <h5 class="mb-2">Standard Terms</h5>
                <div class="border rounded-3 p-3 mb-4 small" style="white-space:pre-line;">
                    <?= e($contract['terms']) ?>
                </div>

                <!-- SIGNATURES SECTION -->
                <h5 class="mb-3">Signatures</h5>
                <div class="row g-3 mb-4">

                    <!-- Landlord -->
                    <div class="col-md-4">
                        <div class="border rounded-3 p-3 h-100 text-center">
                            <small class="text-secondary text-uppercase d-block mb-2">Landlord</small>
                            <div class="signature-slot mb-2 d-flex align-items-center justify-content-center"
                                 style="height:90px; background:#FAFAFA; border:1px dashed var(--rb-line); border-radius:6px;">
                                <?php if (!empty($contract['landlord_signature'])): ?>
                                    <img src="/rentbridge/<?= e($contract['landlord_signature']) ?>" alt="signature"
                                         style="max-height:80px; max-width:90%;">
                                <?php else: ?>
                                    <span class="text-secondary small">Not signed yet</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($contract['landlord_signed_at'])): ?>
                                <small class="text-emerald-dark fw-semibold">
                                    <i class="bi bi-check-circle-fill"></i>
                                    Signed <?= e(date('d M Y, H:i', strtotime($contract['landlord_signed_at']))) ?>
                                </small>
                            <?php else: ?>
                                <small class="text-secondary">Pending</small>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- All tenants -->
                    <?php foreach ($coTenants as $ct): ?>
                    <div class="col-md-4">
                        <div class="border rounded-3 p-3 h-100 text-center">
                            <small class="text-secondary text-uppercase d-block mb-2">
                                <?= (int)$ct['is_primary'] ? 'Primary Tenant' : 'Co-Tenant' ?>
                            </small>
                            <div class="signature-slot mb-2 d-flex align-items-center justify-content-center"
                                 style="height:90px; background:#FAFAFA; border:1px dashed var(--rb-line); border-radius:6px;">
                                <?php if (!empty($ct['signature_data'])): ?>
                                    <img src="/rentbridge/<?= e($ct['signature_data']) ?>" alt="signature"
                                         style="max-height:80px; max-width:90%;">
                                <?php else: ?>
                                    <span class="text-secondary small">Not signed yet</span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($ct['signed_at'])): ?>
                                <small class="text-emerald-dark fw-semibold">
                                    <i class="bi bi-check-circle-fill"></i>
                                    Signed <?= e(date('d M Y, H:i', strtotime($ct['signed_at']))) ?>
                                </small>
                            <?php else: ?>
                                <small class="text-secondary"><?= e($ct['full_name']) ?> · Pending</small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- ACTION PROMPT -->
                <?php if ($contract['status'] === 'pending_signatures'): ?>
                    <?php if ($canSignNow): ?>
                        <div class="alert alert-warning d-flex align-items-center gap-3">
                            <i class="bi bi-pen-fill fs-3"></i>
                            <div class="flex-grow-1">
                                <strong>It's your turn to sign.</strong>
                                <div class="small">Click below to open the signature pad.</div>
                            </div>
                            <a href="/rentbridge/contracts/sign.php?id=<?= (int)$contract['id'] ?>"
                               class="btn btn-success">
                                Sign now <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i>
                            <?php if ($nextSigner['role'] === 'all_done'): ?>
                                All signatures collected. Finalising contract.
                            <?php else: ?>
                                Waiting for <strong><?= e($nextSigner['name']) ?></strong> to sign.
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <?php if ($canReject): ?>
                        <div class="alert alert-danger d-flex align-items-center gap-3 mt-2">
                            <i class="bi bi-x-circle fs-3"></i>
                            <div class="flex-grow-1">
                                <strong>Not your tenancy?</strong>
                                <div class="small">If you were added without your consent, you can reject this contract. The agent will be notified and the tenancy will be cancelled.</div>
                            </div>
                            <button type="button" class="btn btn-danger btn-sm" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                Reject
                            </button>
                        </div>

                        <!-- Reject confirmation modal -->
                        <div class="modal fade" id="rejectModal" tabindex="-1">
                            <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header border-0">
                                        <h5 class="modal-title text-danger"><i class="bi bi-x-circle me-2"></i>Reject tenancy</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <p>You are about to reject this tenancy contract. This will:</p>
                                        <ul class="small">
                                            <li>Cancel the tenancy immediately</li>
                                            <li>Notify the assigned agent</li>
                                            <li>Notify the primary tenant</li>
                                        </ul>
                                        <p class="mb-0 text-secondary small">This action cannot be undone.</p>
                                    </div>
                                    <div class="modal-footer border-0">
                                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <form method="POST" action="/rentbridge/contracts/reject.php">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="contract_id" value="<?= (int)$contract['id'] ?>">
                                            <button type="submit" class="btn btn-danger">
                                                <i class="bi bi-x-circle me-1"></i> Confirm rejection
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php elseif ($contract['status'] === 'active'): ?>
                    <div class="alert alert-success">
                        <i class="bi bi-check-circle-fill"></i>
                        <strong>Contract is active.</strong>
                        Tenancy is now in effect. Download the signed PDF above for your records.
                    </div>
                <?php endif; ?>

            </div>

            <!-- Footer note -->
            <p class="text-center text-secondary small mb-0">
                Verify authenticity at <code>rentbridge.com/verify/<?= e($contract['contract_code']) ?></code>
            </p>
        </div>
    </div>
</div>

</body>
</html>
