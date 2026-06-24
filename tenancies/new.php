<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('student');  // Only students can book

$propertyId = (int)($_GET['property_id'] ?? $_POST['property_id'] ?? 0);
if ($propertyId <= 0) {
    http_response_code(400);
    die('Invalid property.');
}

$pdo = db();

// Fetch the property (must be available)
$stmt = $pdo->prepare("
    SELECT p.*, l.full_name AS landlord_name
      FROM properties p
      JOIN landlords l ON l.user_id = p.landlord_id
     WHERE p.id = ? AND p.status = 'available'
     LIMIT 1
");
$stmt->execute([$propertyId]);
$prop = $stmt->fetch();

if (!$prop) {
    http_response_code(404);
    die('Property not found or no longer available.');
}

$errors = [];
$old = [
    'start_date'    => '',
    'duration_type' => 'semester_4',
    'end_date'      => '',
    'student_note'  => '',
];

// ---- HANDLE FORM SUBMIT ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $old['start_date']    = trim($_POST['start_date'] ?? '');
    $old['duration_type'] = $_POST['duration_type'] ?? 'semester_4';
    $old['end_date']      = trim($_POST['end_date'] ?? '');
    $old['student_note']  = trim($_POST['student_note'] ?? '');

    // ---- Validate start date ----
    if ($old['start_date'] === '') {
        $errors['start_date'] = 'Move-in date is required.';
    } else {
        $startTs = strtotime($old['start_date']);
        if ($startTs === false) {
            $errors['start_date'] = 'Invalid date format.';
        } elseif ($startTs < strtotime('today')) {
            $errors['start_date'] = 'Move-in date cannot be in the past.';
        }
    }

    // ---- Calculate / validate end date (server-side, never trust client) ----
    if (!isset($errors['start_date'])) {
        $startTs = strtotime($old['start_date']);
        $endTs   = null;

        switch ($old['duration_type']) {
            case 'semester_4':
                $endTs = strtotime('+98 days', $startTs); // 14 weeks
                break;
            case 'academic_8':
                $endTs = strtotime('+8 months', $startTs);
                break;
            case 'full_year_12':
                $endTs = strtotime('+12 months', $startTs);
                break;
            case 'custom':
                if ($old['end_date'] === '') {
                    $errors['end_date'] = 'End date is required for custom duration.';
                } else {
                    $endTs = strtotime($old['end_date']);
                    if ($endTs === false) {
                        $errors['end_date'] = 'Invalid end date.';
                    } elseif ($endTs <= $startTs) {
                        $errors['end_date'] = 'End date must be after the start date.';
                    } else {
                        $diffDays = ($endTs - $startTs) / 86400;
                        if ($diffDays < 28) {
                            $errors['end_date'] = 'Minimum tenancy is 1 month (28 days).';
                        }
                    }
                }
                break;
            default:
                $errors['duration_type'] = 'Invalid duration type.';
        }

        if ($endTs && !isset($errors['end_date'])) {
            $old['end_date'] = date('Y-m-d', $endTs);
        }
    }

    // ---- Check for overlapping tenancies on this property ----
    if (empty($errors)) {
        $stmt = $pdo->prepare("
            SELECT id FROM tenancies
             WHERE property_id = ?
               AND status IN ('pending_landlord','pending_agent','agent_assigned',
                              'agent_verifying','agent_verified','contract_pending','active')
               AND start_date <= ?
               AND end_date   >= ?
        ");
        $stmt->execute([$propertyId, $old['end_date'], $old['start_date']]);
        if ($stmt->fetch()) {
            $errors['general'] = 'This property already has a tenancy that overlaps with your dates. Please pick different dates.';
        }
    }

    // ---- Prevent student tenancy their own property (edge case) ----
    if (empty($errors) && $prop['landlord_id'] == current_user_id()) {
        $errors['general'] = 'You cannot book your own property.';
    }

    // ---- All good — save the tenancy ----
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare(
                'INSERT INTO tenancies
                    (student_id, property_id, landlord_id, start_date, end_date,
                     duration_type, monthly_rent, deposit, student_note, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, "pending_landlord")'
            );
            $stmt->execute([
                current_user_id(),
                $propertyId,
                $prop['landlord_id'],
                $old['start_date'],
                $old['end_date'],
                $old['duration_type'],
                $prop['monthly_rent'],
                $prop['deposit'],
                $old['student_note'],
            ]);
            $tenancyId = (int)$pdo->lastInsertId();

            $pdo->commit();

            // Notify the landlord (dashboard banner)
            notify(
                (int)$prop['landlord_id'],
                'tenancy_request',
                'New tenancy request',
                'A student has requested to book "' . $prop['title'] . '".',
                '/rentbridge/landlord/tenancies.php?id=' . $tenancyId
            );

            set_flash('success', 'Tenancy request sent! The landlord will review and respond shortly.');
            header('Location: /rentbridge/student/tenancies.php');
            exit;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors['general'] = 'Something went wrong: ' . $e->getMessage();
        }
    }
}

// Calculate preset end dates for the JS preview
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book this property · RentBridge</title>
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
        <div class="col-lg-8">

            <p class="small mb-3">
                <a href="/rentbridge/property.php?id=<?= (int)$propertyId ?>" class="text-secondary text-decoration-none">
                    <i class="bi bi-arrow-left"></i> Back to property
                </a>
            </p>

            <h1 class="mb-1">Request to book</h1>
            <p class="text-secondary mb-4">Pick your move-in date and how long you'd like to stay.</p>

            <div class="bg-white border rounded-3 p-4 p-md-5">

                <!-- Property summary -->
                <div class="d-flex gap-3 mb-4 pb-3 border-bottom">
                    <div class="flex-shrink-0">
                        <i class="bi bi-house-door display-4 text-secondary"></i>
                    </div>
                    <div>
                        <h5 class="mb-1"><?= e($prop['title']) ?></h5>
                        <div class="text-secondary small">
                            <i class="bi bi-geo-alt"></i> <?= e($prop['city']) ?>, <?= e($prop['state']) ?>
                        </div>
                        <div class="mt-2">
                            <span class="text-emerald fw-semibold">
                                RM <?= number_format((float)$prop['monthly_rent']) ?>
                            </span>
                            <span class="text-secondary small">/ month</span>
                        </div>
                    </div>
                </div>

                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-circle"></i> <?= e($errors['general']) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="property_id" value="<?= (int)$propertyId ?>">

                    <!-- Move-in date -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Move-in date</label>
                        <input type="date" name="start_date" id="start_date" min="<?= $today ?>"
                               class="form-control <?= isset($errors['start_date']) ? 'is-invalid' : '' ?>"
                               value="<?= e($old['start_date']) ?>" required>
                        <?php if (isset($errors['start_date'])): ?>
                            <div class="invalid-feedback"><?= e($errors['start_date']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Duration choice -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold mb-3">How long?</label>

                        <div class="row g-3">
                            <?php
                            $options = [
                                'semester_4'   => ['1 Semester',     '14 weeks',  'days',   98],
                                'academic_8'   => ['Academic Year',  '8 months',  'months',  8],
                                'full_year_12' => ['Full Year',      '12 months', 'months', 12],
                                'custom'       => ['Custom range',   'You pick',  null,    null],
                            ];
                            foreach ($options as $key => [$label, $sub, $unit, $value]):
                            ?>
                            <div class="col-md-6">
                                <label class="duration-card <?= $old['duration_type'] === $key ? 'selected' : '' ?>">
                                    <input type="radio" name="duration_type" value="<?= $key ?>"
                                           <?= $old['duration_type'] === $key ? 'checked' : '' ?>
                                           <?= $unit === 'months' ? 'data-months="'.$value.'"' : '' ?>
                                           <?= $unit === 'days'   ? 'data-days="'.$value.'"'   : '' ?>>
                                    <div class="duration-card__label"><?= e($label) ?></div>
                                    <div class="duration-card__sub"><?= e($sub) ?></div>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Custom end date (shown only if "Custom range" selected) -->
                    <div class="mb-4" id="custom_end_wrap" style="display: <?= $old['duration_type']==='custom' ? 'block' : 'none' ?>;">
                        <label class="form-label fw-semibold">End date</label>
                        <input type="date" name="end_date" id="end_date"
                               class="form-control <?= isset($errors['end_date']) ? 'is-invalid' : '' ?>"
                               value="<?= e($old['end_date']) ?>">
                        <?php if (isset($errors['end_date'])): ?>
                            <div class="invalid-feedback"><?= e($errors['end_date']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Tenancy summary -->
                    <div class="alert alert-light border" id="summary" style="display:none;">
                        <div class="row text-center">
                            <div class="col-sm-4">
                                <small class="text-secondary text-uppercase d-block">Move in</small>
                                <strong id="sum_start">—</strong>
                            </div>
                            <div class="col-sm-4">
                                <small class="text-secondary text-uppercase d-block">Move out</small>
                                <strong id="sum_end">—</strong>
                            </div>
                            <div class="col-sm-4">
                                <small class="text-secondary text-uppercase d-block">Total rent</small>
                                <strong class="text-emerald" id="sum_total">RM —</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Optional note -->
                    <div class="mb-4 mt-4">
                        <label class="form-label fw-semibold">Note to landlord <small class="text-secondary fw-normal">— optional</small></label>
                        <textarea name="student_note" class="form-control" rows="3"
                                  placeholder="Anything you'd like the landlord to know..."><?= e($old['student_note']) ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg w-100">
                        <i class="bi bi-send me-1"></i> Send tenancy request
                    </button>

                    <p class="text-center text-secondary small mt-3 mb-0">
                        Your request will be reviewed by the landlord, then witnessed by a UTeM staff agent.
                    </p>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const rent       = <?= (float)$prop['monthly_rent'] ?>;
    const startInput = document.getElementById('start_date');
    const endInput   = document.getElementById('end_date');
    const customWrap = document.getElementById('custom_end_wrap');
    const summary    = document.getElementById('summary');
    const sumStart   = document.getElementById('sum_start');
    const sumEnd     = document.getElementById('sum_end');
    const sumTotal   = document.getElementById('sum_total');
    const radios     = document.querySelectorAll('input[name="duration_type"]');

    function fmt(d) {
        return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    }

    function addMonths(d, n) {
        const r = new Date(d);
        r.setMonth(r.getMonth() + n);
        return r;
    }
    function addDays(d, n) {
        const r = new Date(d);
        r.setDate(r.getDate() + n);
        return r;
    }

    function update() {
        const startVal = startInput.value;
        const selected = document.querySelector('input[name="duration_type"]:checked');
        if (!selected) return;

        const months   = selected.dataset.months ? parseInt(selected.dataset.months) : null;
        const days     = selected.dataset.days   ? parseInt(selected.dataset.days)   : null;
        const isCustom = selected.value === 'custom';

        customWrap.style.display = isCustom ? 'block' : 'none';

        if (!startVal) { summary.style.display = 'none'; return; }

        const startDate = new Date(startVal);
        let endDate;
        let totalCost;

        if (isCustom) {
            if (!endInput.value) { summary.style.display = 'none'; return; }
            endDate = new Date(endInput.value);
            if (endDate <= startDate) { summary.style.display = 'none'; return; }
            const diffMonths = (endDate.getFullYear() - startDate.getFullYear()) * 12
                             + (endDate.getMonth() - startDate.getMonth());
            totalCost = rent * Math.max(1, diffMonths);
        } else if (days !== null) {
            endDate   = addDays(startDate, days);
            totalCost = rent * (days / 30.44); // prorate by actual days
        } else {
            endDate   = addMonths(startDate, months);
            totalCost = rent * months;
        }

        const cards = document.querySelectorAll('.duration-card');
        cards.forEach(c => c.classList.remove('selected'));
        selected.closest('.duration-card').classList.add('selected');

        sumStart.textContent = fmt(startDate);
        sumEnd.textContent   = fmt(endDate);
        sumTotal.textContent = 'RM ' + Math.round(totalCost).toLocaleString('en-MY');
        summary.style.display = 'block';
    }

    startInput.addEventListener('change', update);
    endInput.addEventListener('change', update);
    radios.forEach(r => r.addEventListener('change', update));
    update();
})();
</script>
</body>
</html>