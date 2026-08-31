<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');
require_once __DIR__ . '/../includes/openai_calendar.php';

$pdo = db();

$extractError   = '';
$extractedTerms = [];
$rawOutput      = '';

/* ------------------------------------------------------------------ */
/*  POST: extract from an uploaded PDF                                 */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'extract') {
    verify_csrf();

    $f = $_FILES['calendar_pdf'] ?? null;
    if (!$f || $f['error'] !== UPLOAD_ERR_OK) {
        $extractError = 'Please choose a PDF file to upload.';
    } elseif ($f['size'] > 15 * 1024 * 1024) {
        $extractError = 'The PDF is larger than 15 MB.';
    } else {
        // Validate it really is a PDF.
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($f['tmp_name']);
        $isPdf = ($mime === 'application/pdf')
              || (strtolower(pathinfo($f['name'], PATHINFO_EXTENSION)) === 'pdf');
        if (!$isPdf) {
            $extractError = 'That file is not a PDF.';
        } else {
            $tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'rbcal_' . bin2hex(random_bytes(4)) . '.pdf';
            if (!move_uploaded_file($f['tmp_name'], $tmp)) {
                $extractError = 'Could not store the uploaded file.';
            } else {
                $res = openai_extract_calendar($tmp);
                @unlink($tmp);
                if (!$res['ok']) {
                    $extractError = $res['error'];
                    $rawOutput    = (string)($res['raw'] ?? '');
                } else {
                    $extractedTerms = $res['terms'];
                    $rawOutput      = (string)($res['raw'] ?? '');
                    if (!$extractedTerms) $extractError = 'The model returned no terms. Check the raw output below.';
                }
            }
        }
    }
}

/* ------------------------------------------------------------------ */
/*  POST: save the confirmed terms                                    */
/* ------------------------------------------------------------------ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    verify_csrf();

    $sessions = $_POST['session']    ?? [];
    $terms    = $_POST['term']       ?? [];
    $labels   = $_POST['label']      ?? [];
    $starts   = $_POST['start_date'] ?? [];
    $ends     = $_POST['end_date']   ?? [];

    $valid = ['sem1', 'sem2', 'short'];
    $saved = 0; $errors = [];

    $stmt = $pdo->prepare("
        INSERT INTO academic_terms (session, term, label, start_date, end_date)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE label = VALUES(label),
                                start_date = VALUES(start_date),
                                end_date = VALUES(end_date)
    ");

    foreach ($sessions as $i => $session) {
        $session = trim($session);
        $term    = trim($terms[$i]  ?? '');
        $label   = trim($labels[$i] ?? '');
        $start   = trim($starts[$i] ?? '');
        $end     = trim($ends[$i]   ?? '');
        if ($session === '' && $start === '' && $end === '') continue; // blank row

        $sd = DateTime::createFromFormat('Y-m-d', $start);
        $ed = DateTime::createFromFormat('Y-m-d', $end);
        if ($session === '')                       { $errors[] = "Row " . ($i + 1) . ": session is required."; continue; }
        if (!in_array($term, $valid, true))        { $errors[] = "Row " . ($i + 1) . ": invalid term."; continue; }
        if (!$sd || $sd->format('Y-m-d') !== $start) { $errors[] = "Row " . ($i + 1) . ": invalid start date."; continue; }
        if (!$ed || $ed->format('Y-m-d') !== $end)   { $errors[] = "Row " . ($i + 1) . ": invalid end date."; continue; }
        if ($sd > $ed)                             { $errors[] = "Row " . ($i + 1) . ": start is after end."; continue; }

        $stmt->execute([$session, $term, ($label !== '' ? $label : $term), $start, $end]);
        $saved++;
    }

    if ($saved > 0) set_flash('success', "Saved $saved academic term(s).");
    if ($errors)    set_flash('warning', implode(' ', $errors));
    header('Location: /rentbridge/admin/academic_calendar.php');
    exit;
}

/* Current saved terms */
$savedTerms = $pdo->query("
    SELECT * FROM academic_terms ORDER BY session DESC, start_date ASC
")->fetchAll();

$keyMissing = (OPENAI_API_KEY === '');

/* ------------------------------------------------------------------ */
ob_start();
?>
<div class="mb-4">
    <h4 class="mb-1">Academic Calendar</h4>
    <p class="text-secondary mb-0">These UTeM semester dates drive tenancy durations. Import the official
        calendar, review the AI-extracted dates, then save.</p>
</div>

<?php if ($keyMissing): ?>
    <div class="alert alert-warning">
        <i class="bi bi-exclamation-triangle me-1"></i>
        OpenAI API key is not configured. Add it to <code>config/openai.php</code> (or set the
        <code>OPENAI_API_KEY</code> environment variable) before extracting.
    </div>
<?php endif; ?>

<!-- STEP 1: get the PDF from UTeM -->
<div class="bg-white border rounded-3 p-4 mb-3">
    <h6 class="mb-2">1. Get the official calendar</h6>
    <a href="https://www.utem.edu.my/en/academic-calendar.html" target="_blank" rel="noopener"
       class="btn btn-outline-primary">
        <i class="bi bi-box-arrow-up-right me-1"></i> Open the UTeM academic calendar website
    </a>
    <div class="small text-secondary mt-2">
        Download the latest Diploma &amp; Bachelor calendar PDF, then upload it below.
    </div>
</div>

<!-- STEP 2: upload + extract -->
<div class="bg-white border rounded-3 p-4 mb-3">
    <h6 class="mb-2">2. Upload &amp; extract</h6>
    <form method="post" enctype="multipart/form-data" class="row g-2 align-items-center">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="extract">
        <div class="col-auto flex-grow-1">
            <input type="file" name="calendar_pdf" accept="application/pdf,.pdf"
                   class="form-control" required>
        </div>
        <div class="col-auto">
            <button type="submit" class="btn btn-primary" <?= $keyMissing ? 'disabled' : '' ?>>
                <i class="bi bi-magic me-1"></i> Extract dates with AI
            </button>
        </div>
    </form>
    <div class="small text-secondary mt-2">The PDF is sent to GPT-4o once; nothing is saved until you confirm below.</div>
</div>

<?php if ($extractError): ?>
    <div class="alert alert-danger"><i class="bi bi-x-circle me-1"></i> <?= e($extractError) ?></div>
<?php endif; ?>

<?php if ($extractedTerms): ?>
<!-- STEP 3: review + save -->
<div class="bg-white border rounded-3 p-4 mb-3" style="border-left:4px solid #2E8B57 !important;">
    <h6 class="mb-1">3. Review &amp; save</h6>
    <p class="small text-secondary">Check every date against the PDF before saving — these become legally-binding tenancy periods.</p>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save">
        <div class="table-responsive">
            <table class="table align-middle">
                <thead><tr>
                    <th>Session</th><th>Term</th><th>Label</th><th>Start date</th><th>End date</th>
                </tr></thead>
                <tbody>
                <?php foreach ($extractedTerms as $t): ?>
                    <tr>
                        <td><input type="text" name="session[]" value="<?= e($t['session']) ?>" class="form-control form-control-sm" placeholder="2025/2026"></td>
                        <td>
                            <select name="term[]" class="form-select form-select-sm">
                                <?php foreach (['sem1'=>'Semester 1','sem2'=>'Semester 2','short'=>'Short semester'] as $v=>$lbl): ?>
                                    <option value="<?= $v ?>" <?= $t['term']===$v?'selected':'' ?>><?= $lbl ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="text" name="label[]" value="<?= e($t['label']) ?>" class="form-control form-control-sm"></td>
                        <td><input type="date" name="start_date[]" value="<?= e($t['start_date']) ?>" class="form-control form-control-sm"></td>
                        <td><input type="date" name="end_date[]" value="<?= e($t['end_date']) ?>" class="form-control form-control-sm"></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button type="submit" class="btn btn-success"><i class="bi bi-check2-circle me-1"></i> Save to calendar</button>
    </form>
</div>
<?php endif; ?>

<?php if ($rawOutput !== ''): ?>
    <details class="mb-3">
        <summary class="small text-secondary">Raw AI output (for debugging)</summary>
        <pre class="small bg-light border rounded p-2 mt-1" style="white-space:pre-wrap;"><?= e($rawOutput) ?></pre>
    </details>
<?php endif; ?>

<!-- Saved terms -->
<div class="bg-white border rounded-3 p-4">
    <h6 class="mb-3">Saved academic terms</h6>
    <?php if (!$savedTerms): ?>
        <p class="text-secondary mb-0">None yet.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-sm mb-0">
                <thead><tr><th>Session</th><th>Term</th><th>Label</th><th>Start</th><th>End</th></tr></thead>
                <tbody>
                <?php foreach ($savedTerms as $t): ?>
                    <tr>
                        <td><?= e($t['session']) ?></td>
                        <td><span class="badge bg-secondary"><?= e($t['term']) ?></span></td>
                        <td><?= e($t['label']) ?></td>
                        <td><?= e($t['start_date']) ?></td>
                        <td><?= e($t['end_date']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
<?php
$pageContent = ob_get_clean();
$pageTitle   = 'Academic Calendar';
$activeNav   = 'academic_calendar';
require __DIR__ . '/../includes/admin_layout.php';
