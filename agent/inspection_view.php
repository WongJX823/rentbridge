<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

$verificationId = (int)($_GET['id'] ?? 0);
if ($verificationId <= 0) {
    http_response_code(400);
    die('Invalid inspection ID.');
}

$pdo = db();

// Fetch the verification + tenancy + property + photos
$stmt = $pdo->prepare("
    SELECT v.*,
           b.id             AS tenancy_id,
           b.status         AS tenancy_status,
           b.student_id,
           b.landlord_id,
           b.agent_id,
           p.title          AS property_title,
           p.address        AS property_address,
           p.city           AS property_city,
           p.postcode       AS property_postcode,
           p.state          AS property_state,
           s.full_name      AS student_name,
           s.preferred_name AS student_nickname,
           l.full_name      AS landlord_name,
           l.preferred_name AS landlord_nickname,
           a.full_name      AS agent_name,
           a.preferred_name AS agent_nickname,
           a.department     AS agent_department,
           a.staff_id       AS agent_staff_id
      FROM agent_verifications v
      JOIN tenancies   b ON b.id = v.tenancy_id
      JOIN properties p ON p.id = b.property_id
      JOIN students   s ON s.user_id = b.student_id
      JOIN landlords  l ON l.user_id = b.landlord_id
      JOIN agents     a ON a.user_id = v.agent_id
     WHERE v.id = ?
     LIMIT 1
");
$stmt->execute([$verificationId]);
$v = $stmt->fetch();

if (!$v) {
    http_response_code(404);
    die('Inspection report not found.');
}

// Authorization: only agent (owner), student, landlord, or admin can view
$userId = current_user_id();
$role   = current_role();
$canView = ($role === 'admin')
        || ($userId === (int)$v['agent_id'])
        || ($userId === (int)$v['student_id'])
        || ($userId === (int)$v['landlord_id']);

if (!$canView) {
    http_response_code(403);
    die('You do not have permission to view this inspection.');
}

// Fetch photos
$stmt = $pdo->prepare("
    SELECT id, photo_path, caption, uploaded_at
      FROM agent_verification_photos
     WHERE verification_id = ?
     ORDER BY id ASC
");
$stmt->execute([$verificationId]);
$photos = $stmt->fetchAll();

// Compute outcome label + color
function outcome_label(string $outcome): array {
    return match ($outcome) {
        'in_progress'             => ['In progress',                 'secondary'],
        'passed'                  => ['✓ Passed — no issues',         'success'],
        'passed_with_disclosure'  => ['⚠ Passed with disclosure',     'warning'],
        'failed'                  => ['❌ Failed — major issues',     'danger'],
        default                   => [ucfirst($outcome),              'secondary'],
    };
}
[$outcomeLabel, $outcomeColor] = outcome_label($v['outcome']);

// Friendly severity label
$severityLabel = match ($v['issue_severity']) {
    'none'  => 'No issues',
    'minor' => 'Minor issues',
    'major' => 'Major issues',
    default => '—',
};

// Checklist items for rendering
$checklist = [
    'property_matches_listing'  => 'Property matches the listing photos and description',
    'property_address_correct'  => 'Property address is correct and findable',
    'facilities_match'          => 'Listed facilities are present',
    'landlord_id_matches'       => 'Landlord IC matches account info',
    'ownership_doc_sighted'     => 'Property ownership document sighted',
];

// Back link depends on role
$backLink = match ($role) {
    'agent'    => '/rentbridge/agent/cases.php',
    'student'  => '/rentbridge/student/tenancy.php?id=' . (int)$v['tenancy_id'],
    'landlord' => '/rentbridge/landlord/tenancy.php?id=' . (int)$v['tenancy_id'],
    'admin'    => '/rentbridge/admin/tenancy.php?id=' . (int)$v['tenancy_id'],
    default    => '/rentbridge/index.php',
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Inspection Report · RentBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
    <style>
        .inspect-photo {
            aspect-ratio: 4/3;
            object-fit: cover;
            border-radius: 8px;
            cursor: pointer;
            transition: transform 0.15s;
        }
        .inspect-photo:hover { transform: scale(1.03); }
        .checklist-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 0;
        }
        .checklist-tick {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
            font-weight: bold;
            flex-shrink: 0;
        }
        .check-yes { background: #d1f7d1; color: #1f7a1f; }
        .check-no  { background: #fde0e0; color: #a02020; }
    </style>
</head>
<body style="background: var(--rb-cream);">

<?php include '../includes/header.php'; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-10">

            <p class="small mb-3">
                <a href="<?= e($backLink) ?>" class="text-secondary text-decoration-none">
                    <i class="bi bi-arrow-left"></i> Back
                </a>
            </p>

            <!-- Header -->
            <div class="d-flex justify-content-between align-items-start mb-4">
                <div>
                    <h1 class="mb-1">Inspection Report</h1>
                    <p class="text-secondary mb-0">
                        Tenancy #<?= (int)$v['tenancy_id'] ?> · <?= e($v['property_title']) ?>
                    </p>
                </div>
                <span class="badge bg-<?= $outcomeColor ?> fs-6"><?= e($outcomeLabel) ?></span>
            </div>

            <div class="row g-4">

                <!-- Inspection metadata -->
                <div class="col-12">
                    <div class="bg-white border rounded-3 p-4">
                        <h6 class="text-secondary text-uppercase small mb-3">Report metadata</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <small class="text-secondary text-uppercase">Inspector</small>
                                <div class="fw-semibold"><?= e($v['agent_name']) ?></div>
                                <small class="text-secondary">
                                    <?= e($v['agent_department']) ?> · <?= e($v['agent_staff_id']) ?>
                                </small>
                            </div>
                            <div class="col-md-4">
                                <small class="text-secondary text-uppercase">Submitted</small>
                                <div class="fw-semibold">
                                    <?= $v['submitted_at']
                                        ? e(date('d M Y, H:i', strtotime($v['submitted_at'])))
                                        : '<em class="text-secondary">Not yet submitted</em>' ?>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <small class="text-secondary text-uppercase">Severity</small>
                                <div class="fw-semibold"><?= e($severityLabel) ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Property + Parties context -->
                <div class="col-md-8">
                    <div class="bg-white border rounded-3 p-4 h-100">
                        <h6 class="text-secondary text-uppercase small mb-2">Property inspected</h6>
                        <h5 class="mb-2"><?= e($v['property_title']) ?></h5>
                        <p class="text-secondary small mb-0">
                            <i class="bi bi-geo-alt"></i>
                            <?= e($v['property_address']) ?>,<br>
                            <?= e($v['property_city']) ?> <?= e($v['property_postcode']) ?>,
                            <?= e($v['property_state']) ?>
                        </p>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="bg-white border rounded-3 p-4 h-100">
                        <h6 class="text-secondary text-uppercase small mb-2">Parties</h6>
                        <div class="small">
                            <div class="mb-1">
                                <small class="text-secondary text-uppercase">Student</small><br>
                                <strong><?= e($v['student_name']) ?></strong>
                            </div>
                            <div>
                                <small class="text-secondary text-uppercase">Landlord</small><br>
                                <strong><?= e($v['landlord_name']) ?></strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Checklist results -->
                <div class="col-12">
                    <div class="bg-white border rounded-3 p-4">
                        <h6 class="text-secondary text-uppercase small mb-3">
                            <i class="bi bi-list-check"></i> Verification checklist
                        </h6>
                        <?php foreach ($checklist as $field => $label): ?>
                            <div class="checklist-item">
                                <?php if ((int)$v[$field] === 1): ?>
                                    <span class="checklist-tick check-yes">✓</span>
                                <?php else: ?>
                                    <span class="checklist-tick check-no">✗</span>
                                <?php endif; ?>
                                <span><?= e($label) ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Inspection notes -->
                <div class="col-12">
                    <div class="bg-white border rounded-3 p-4">
                        <h6 class="text-secondary text-uppercase small mb-2">
                            <i class="bi bi-card-text"></i> Inspector's notes
                        </h6>
                        <p class="mb-0" style="white-space: pre-line;">
                            <?= e($v['inspection_notes'] ?? '—') ?>
                        </p>
                    </div>
                </div>

                <!-- Issues found (if any) -->
                <?php if (!empty($v['issues_found'])): ?>
                <div class="col-12">
                    <div class="bg-white border rounded-3 p-4"
                         style="border-left: 4px solid <?= $v['issue_severity'] === 'major' ? '#DC3545' : '#D4A017' ?> !important;">
                        <h6 class="text-secondary text-uppercase small mb-2">
                            <?php if ($v['issue_severity'] === 'major'): ?>
                                <i class="bi bi-exclamation-octagon-fill text-danger"></i>
                                Major issues
                            <?php else: ?>
                                <i class="bi bi-exclamation-triangle-fill text-warning"></i>
                                Minor issues
                            <?php endif; ?>
                        </h6>
                        <p class="mb-0" style="white-space: pre-line;">
                            <?= e($v['issues_found']) ?>
                        </p>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Photos gallery -->
                <?php if (!empty($photos)): ?>
                <div class="col-12">
                    <div class="bg-white border rounded-3 p-4">
                        <h6 class="text-secondary text-uppercase small mb-3">
                            <i class="bi bi-camera"></i>
                            Inspection photos (<?= count($photos) ?>)
                        </h6>
                        <div class="row g-3">
                            <?php foreach ($photos as $i => $photo): ?>
                                <div class="col-md-4 col-sm-6">
                                    <img src="/rentbridge/<?= e($photo['photo_path']) ?>"
                                         class="w-100 inspect-photo"
                                         alt="Inspection photo <?= $i + 1 ?>"
                                         onclick="window.open(this.src, '_blank');">
                                    <?php if (!empty($photo['caption'])): ?>
                                        <small class="text-secondary d-block mt-1">
                                            <?= e($photo['caption']) ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <small class="text-secondary mt-3 d-block">
                            Click any photo to view full size.
                        </small>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Outcome summary box -->
                <div class="col-12">
                    <div class="bg-light border rounded-3 p-4">
                        <h6 class="text-secondary text-uppercase small mb-2">What this means</h6>
                        <?php if ($v['outcome'] === 'passed'): ?>
                            <p class="mb-0 text-success">
                                <i class="bi bi-check-circle-fill"></i>
                                <strong>Inspection passed.</strong>
                                The contract has been generated and signing can proceed.
                            </p>
                        <?php elseif ($v['outcome'] === 'passed_with_disclosure'): ?>
                            <p class="mb-0 text-warning">
                                <i class="bi bi-exclamation-triangle-fill"></i>
                                <strong>Minor issues disclosed.</strong>
                                The student has been notified and must decide whether to proceed with the tenancy.
                            </p>
                        <?php elseif ($v['outcome'] === 'failed'): ?>
                            <p class="mb-0 text-danger">
                                <i class="bi bi-x-octagon-fill"></i>
                                <strong>Inspection failed.</strong>
                                The tenancy has been auto-cancelled due to major issues. Admin will review the property listing.
                            </p>
                        <?php else: ?>
                            <p class="mb-0 text-secondary">
                                <i class="bi bi-clock"></i>
                                Inspection is still in progress.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>