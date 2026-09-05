<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/academic_terms.php';
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

$singleTerms   = get_upcoming_single_terms();
$academicYears = get_upcoming_academic_years();
$hasTerms      = !empty($singleTerms) || !empty($academicYears);

$errors = [];
$old = [
    'plan'          => $hasTerms ? 'single_term' : 'custom',
    'term_id'       => '',
    'session'       => '',
    'start_date'    => '',
    'end_date'      => '',
    'student_note'  => '',
];
$durationType = null; // resolved DB enum value: 1_semester | 2_semesters | custom

// ---- HANDLE FORM SUBMIT ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $old['plan']         = $_POST['plan'] ?? 'custom';
    $old['term_id']      = trim($_POST['term_id'] ?? '');
    $old['session']      = trim($_POST['session'] ?? '');
    $old['start_date']   = trim($_POST['start_date'] ?? '');
    $old['end_date']     = trim($_POST['end_date'] ?? '');
    $old['student_note'] = trim($_POST['student_note'] ?? '');

    // ---- Resolve start/end dates from the chosen plan (server-side —
    // never trust the client's dates for term-based plans) ----
    switch ($old['plan']) {
        case 'single_term':
            $term = $old['term_id'] !== '' ? get_academic_term((int)$old['term_id']) : null;
            if (!$term || $term['start_date'] < date('Y-m-d')) {
                $errors['duration_type'] = 'Please pick a valid upcoming semester.';
            } else {
                $old['start_date'] = $term['start_date'];
                $old['end_date']   = $term['end_date'];
                $durationType      = '1_semester';
            }
            break;

        case 'academic_year':
            $stmt = $pdo->prepare("
                SELECT s1.start_date AS sem1_start, s2.end_date AS sem2_end
                  FROM academic_terms s1
                  JOIN academic_terms s2 ON s2.session = s1.session AND s2.term = 'sem2'
                 WHERE s1.term = 'sem1' AND s1.session = ?
                 LIMIT 1
            ");
            $stmt->execute([$old['session']]);
            $pair = $stmt->fetch();
            if (!$pair || $pair['sem1_start'] < date('Y-m-d')) {
                $errors['duration_type'] = 'Please pick a valid upcoming academic year.';
            } else {
                $old['start_date'] = $pair['sem1_start'];
                $old['end_date']   = $pair['sem2_end'];
                $durationType      = '2_semesters';
            }
            break;

        case 'custom':
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
            if (!isset($errors['start_date'])) {
                if ($old['end_date'] === '') {
                    $errors['end_date'] = 'End date is required for a custom range.';
                } else {
                    $endTs = strtotime($old['end_date']);
                    $startTs = strtotime($old['start_date']);
                    if ($endTs === false) {
                        $errors['end_date'] = 'Invalid end date.';
                    } elseif ($endTs <= $startTs) {
                        $errors['end_date'] = 'End date must be after the start date.';
                    } elseif (($endTs - $startTs) / 86400 < 30) {
                        $errors['end_date'] = 'Minimum tenancy is 1 month.';
                    }
                }
            }
            $durationType = 'custom';
            break;

        default:
            $errors['duration_type'] = 'Invalid duration type.';
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
                $durationType,
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
                '' . BASE_PATH . '/landlord/tenancies.php?id=' . $tenancyId
            );

            set_flash('success', 'Tenancy request sent! The landlord will review and respond shortly.');
            header('Location: ' . BASE_PATH . '/student/tenancies.php');
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
                <a href="<?= BASE_PATH ?>/property.php?id=<?= (int)$propertyId ?>" class="text-secondary text-decoration-none">
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

                    <!-- Duration choice -->
                    <div class="mb-4">
                        <label class="form-label fw-semibold mb-3">How long?</label>

                        <?php if (!$hasTerms): ?>
                            <div class="alert alert-warning small">
                                <i class="bi bi-exclamation-triangle"></i>
                                No upcoming semester dates are published yet — pick custom dates below.
                            </div>
                        <?php endif; ?>

                        <?php if (isset($errors['duration_type'])): ?>
                            <div class="alert alert-danger small"><?= e($errors['duration_type']) ?></div>
                        <?php endif; ?>

                        <div class="row g-3">
                            <?php if (!empty($singleTerms)): ?>
                            <div class="col-md-6">
                                <label class="duration-card <?= $old['plan'] === 'single_term' ? 'selected' : '' ?>">
                                    <input type="radio" name="plan" value="single_term"
                                           <?= $old['plan'] === 'single_term' ? 'checked' : '' ?>>
                                    <div class="duration-card__label">1 Semester</div>
                                    <div class="duration-card__sub">Pick an upcoming semester</div>
                                </label>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($academicYears)): ?>
                            <div class="col-md-6">
                                <label class="duration-card <?= $old['plan'] === 'academic_year' ? 'selected' : '' ?>">
                                    <input type="radio" name="plan" value="academic_year"
                                           <?= $old['plan'] === 'academic_year' ? 'checked' : '' ?>>
                                    <div class="duration-card__label">2 Semesters (Full Year)</div>
                                    <div class="duration-card__sub">Continuous, incl. semester break</div>
                                </label>
                            </div>
                            <?php endif; ?>
                            <div class="col-md-6">
                                <label class="duration-card <?= $old['plan'] === 'custom' ? 'selected' : '' ?>">
                                    <input type="radio" name="plan" value="custom"
                                           <?= $old['plan'] === 'custom' ? 'checked' : '' ?>>
                                    <div class="duration-card__label">Custom range</div>
                                    <div class="duration-card__sub">Pick your own dates</div>
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Semester picker (shown only if "1 Semester" selected) -->
                    <div class="mb-4" id="term_wrap" style="display: <?= $old['plan']==='single_term' ? 'block' : 'none' ?>;">
                        <label class="form-label fw-semibold">Which semester?</label>
                        <select name="term_id" id="term_id" class="form-select">
                            <option value="">Select a semester…</option>
                            <?php foreach ($singleTerms as $t): ?>
                                <option value="<?= (int)$t['id'] ?>"
                                        data-start="<?= e($t['start_date']) ?>" data-end="<?= e($t['end_date']) ?>"
                                        <?= (string)$old['term_id'] === (string)$t['id'] ? 'selected' : '' ?>>
                                    <?= e($t['label']) ?> <?= e($t['session']) ?>
                                    (<?= e(date('d M Y', strtotime($t['start_date']))) ?> – <?= e(date('d M Y', strtotime($t['end_date']))) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Academic-year picker (shown only if "2 Semesters" selected) -->
                    <div class="mb-4" id="session_wrap" style="display: <?= $old['plan']==='academic_year' ? 'block' : 'none' ?>;">
                        <label class="form-label fw-semibold">Which academic year?</label>
                        <select name="session" id="session" class="form-select">
                            <option value="">Select an academic year…</option>
                            <?php foreach ($academicYears as $y): ?>
                                <option value="<?= e($y['session']) ?>"
                                        data-start="<?= e($y['sem1_start']) ?>" data-end="<?= e($y['sem2_end']) ?>"
                                        <?= $old['session'] === $y['session'] ? 'selected' : '' ?>>
                                    <?= e($y['session']) ?>
                                    (<?= e(date('d M Y', strtotime($y['sem1_start']))) ?> – <?= e(date('d M Y', strtotime($y['sem2_end']))) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Custom dates (shown only if "Custom range" selected) -->
                    <div id="custom_wrap" style="display: <?= $old['plan']==='custom' ? 'block' : 'none' ?>;">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Move-in date</label>
                            <input type="date" name="start_date" id="start_date" min="<?= $today ?>"
                                   class="form-control <?= isset($errors['start_date']) ? 'is-invalid' : '' ?>"
                                   value="<?= e($old['start_date']) ?>">
                            <?php if (isset($errors['start_date'])): ?>
                                <div class="invalid-feedback"><?= e($errors['start_date']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-semibold">End date</label>
                            <input type="date" name="end_date" id="end_date"
                                   class="form-control <?= isset($errors['end_date']) ? 'is-invalid' : '' ?>"
                                   value="<?= e($old['end_date']) ?>">
                            <?php if (isset($errors['end_date'])): ?>
                                <div class="invalid-feedback"><?= e($errors['end_date']) ?></div>
                            <?php endif; ?>
                        </div>
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
    const termWrap    = document.getElementById('term_wrap');
    const sessionWrap = document.getElementById('session_wrap');
    const customWrap  = document.getElementById('custom_wrap');
    const termSelect    = document.getElementById('term_id');
    const sessionSelect = document.getElementById('session');
    const startInput = document.getElementById('start_date');
    const endInput   = document.getElementById('end_date');
    const summary    = document.getElementById('summary');
    const sumStart   = document.getElementById('sum_start');
    const sumEnd     = document.getElementById('sum_end');
    const sumTotal   = document.getElementById('sum_total');
    const radios     = document.querySelectorAll('input[name="plan"]');

    function fmt(d) {
        return d.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
    }

    function monthsBetween(startDate, endDate) {
        return Math.max(1, (endDate.getFullYear() - startDate.getFullYear()) * 12
                          + (endDate.getMonth() - startDate.getMonth()));
    }

    function showSummary(startDate, endDate) {
        if (!startDate || !endDate || isNaN(startDate) || isNaN(endDate) || endDate <= startDate) {
            summary.style.display = 'none';
            return;
        }
        sumStart.textContent = fmt(startDate);
        sumEnd.textContent   = fmt(endDate);
        sumTotal.textContent = 'RM ' + Math.round(rent * monthsBetween(startDate, endDate)).toLocaleString('en-MY');
        summary.style.display = 'block';
    }

    function update() {
        const selected = document.querySelector('input[name="plan"]:checked');
        if (!selected) return;
        const plan = selected.value;

        termWrap.style.display    = plan === 'single_term'   ? 'block' : 'none';
        sessionWrap.style.display = plan === 'academic_year' ? 'block' : 'none';
        customWrap.style.display  = plan === 'custom'         ? 'block' : 'none';

        const cards = document.querySelectorAll('.duration-card');
        cards.forEach(c => c.classList.remove('selected'));
        selected.closest('.duration-card').classList.add('selected');

        if (plan === 'single_term') {
            const opt = termSelect.selectedOptions[0];
            if (!opt || !opt.dataset.start) { summary.style.display = 'none'; return; }
            showSummary(new Date(opt.dataset.start), new Date(opt.dataset.end));
        } else if (plan === 'academic_year') {
            const opt = sessionSelect.selectedOptions[0];
            if (!opt || !opt.dataset.start) { summary.style.display = 'none'; return; }
            showSummary(new Date(opt.dataset.start), new Date(opt.dataset.end));
        } else {
            if (!startInput.value || !endInput.value) { summary.style.display = 'none'; return; }
            showSummary(new Date(startInput.value), new Date(endInput.value));
        }
    }

    radios.forEach(r => r.addEventListener('change', update));
    if (termSelect)    termSelect.addEventListener('change', update);
    if (sessionSelect) sessionSelect.addEventListener('change', update);
    startInput.addEventListener('change', update);
    endInput.addEventListener('change', update);
    update();
})();
</script>
</body>
</html>