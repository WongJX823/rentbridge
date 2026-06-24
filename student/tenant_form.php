<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('student');

$pdo    = db();
$userId = current_user_id();

$formId     = (int)($_GET['form_id'] ?? 0);
$convId     = (int)($_GET['conv_id'] ?? 0);
$propertyId = (int)($_GET['property_id'] ?? 0);

if ($formId <= 0 || $convId <= 0 || $propertyId <= 0) {
    set_flash('danger', 'Invalid link.');
    header('Location: /rentbridge/student/dashboard.php');
    exit;
}

// Load the tenant_info_form message and verify this student is the recipient
$stmt = $pdo->prepare("
    SELECT m.id, m.metadata, m.conversation_id
      FROM messages m
     WHERE m.id = ? AND m.message_type = 'tenant_info_form'
");
$stmt->execute([$formId]);
$formMsg = $stmt->fetch();

if (!$formMsg) {
    set_flash('danger', 'Form not found.');
    header('Location: /rentbridge/student/dashboard.php');
    exit;
}

$meta = json_decode($formMsg['metadata'], true) ?? [];

if ((int)($meta['student_id'] ?? 0) !== $userId) {
    set_flash('danger', 'This form is not addressed to you.');
    header('Location: /rentbridge/student/dashboard.php');
    exit;
}

// Already submitted?
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM messages
     WHERE conversation_id = ?
       AND message_type = 'tenant_info_response'
       AND JSON_EXTRACT(metadata, '$.source_form_id') = ?
");
$stmt->execute([$convId, $formId]);
$alreadySubmitted = (int)$stmt->fetchColumn() > 0;

// Load property info
$stmt = $pdo->prepare("
    SELECT p.id, p.title, p.city, p.monthly_rent, p.deposit, p.landlord_id, p.assigned_agent_id,
           (SELECT image_path FROM property_images
             WHERE property_id = p.id ORDER BY is_primary DESC LIMIT 1) AS image_path
      FROM properties p
     WHERE p.id = ?
");
$stmt->execute([$propertyId]);
$prop = $stmt->fetch();

if (!$prop) {
    set_flash('danger', 'Property not found.');
    header('Location: /rentbridge/student/dashboard.php');
    exit;
}

$prefill = $meta['prefill'] ?? [];

// Handle POST submission
$errors  = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadySubmitted) {
    verify_csrf();

    $tenantName  = trim($_POST['tenant_name'] ?? '');
    $tenantIC    = trim($_POST['tenant_ic'] ?? '');
    $tenantPhone = trim($_POST['tenant_phone'] ?? '');
    $tenantEmail = trim($_POST['tenant_email'] ?? '');
    $monthlyRent = (float)($_POST['monthly_rent'] ?? 0);
    $deposit     = (float)($_POST['deposit'] ?? 0);
    $termMonths  = (int)($_POST['term_months'] ?? 12);
    $startDate   = trim($_POST['start_date'] ?? '');
    $notes       = trim($_POST['notes'] ?? '');

    if ($tenantName === '')     $errors['tenant_name'] = 'Full name is required.';
    if ($tenantIC === '')       $errors['tenant_ic']   = 'NRIC is required.';
    if ($startDate === '')      $errors['start_date']  = 'Start date is required.';
    if ($monthlyRent <= 0)      $errors['monthly_rent'] = 'Monthly rent must be greater than 0.';

    if (empty($errors['tenant_ic'])) {
        $icClean = preg_replace('/[^0-9]/', '', $tenantIC);
        if (strlen($icClean) !== 12) {
            $errors['tenant_ic'] = 'NRIC must be 12 digits (e.g. 020815-04-1234).';
        }
    }

    // Co-tenants
    $coTenants = [];
    $coNames = $_POST['cotenant_name'] ?? [];
    $coICs   = $_POST['cotenant_ic'] ?? [];
    foreach ($coNames as $i => $coName) {
        $coName = trim($coName);
        $coIC   = trim($coICs[$i] ?? '');
        if ($coName === '' && $coIC === '') continue;
        if ($coName === '' || $coIC === '') {
            $errors['cotenant_' . $i] = "Co-tenant #" . ($i + 1) . ": both name and NRIC are required.";
            continue;
        }
        $coICClean = preg_replace('/[^0-9]/', '', $coIC);
        if (strlen($coICClean) !== 12) {
            $errors['cotenant_ic_' . $i] = "Co-tenant #" . ($i + 1) . ": invalid NRIC.";
        }
        $coTenants[] = ['name' => $coName, 'ic' => $coIC];
    }

    if (empty($errors)) {
        try {
            $startDt = new DateTime($startDate);
            $endDt   = (clone $startDt)->modify('+' . $termMonths . ' months');
            $endDate = $endDt->format('Y-m-d');
        } catch (Exception) {
            $errors['start_date'] = 'Invalid start date.';
        }
    }

    if (empty($errors)) {
        $durationType = match(true) {
            $termMonths <= 5  => '1_semester',
            $termMonths <= 10 => '2_semesters',
            $termMonths === 12 => '1_year',
            default           => 'custom',
        };

        try {
            $pdo->beginTransaction();

            // Create tenancy
            $stmt = $pdo->prepare("
                INSERT INTO tenancies
                    (student_id, property_id, landlord_id, agent_id,
                     start_date, end_date, duration_type,
                     monthly_rent, deposit, status,
                     student_note, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'contract_pending', ?, NOW())
            ");
            $stmt->execute([
                $userId, $propertyId, (int)($prop['landlord_id'] ?? 0), (int)$prop['assigned_agent_id'],
                $startDate, $endDate, $durationType,
                $monthlyRent, $deposit,
                $notes !== '' ? $notes : null,
            ]);
            $tenancyId = (int)$pdo->lastInsertId();

            // Insert primary tenant
            $stmt = $pdo->prepare("
                INSERT INTO co_tenants
                    (tenancy_id, student_id, is_primary, full_name, ic_number, phone, email, sign_order, added_by, status)
                VALUES (?, ?, 1, ?, ?, ?, ?, 1, ?, 'pending')
            ");
            $stmt->execute([
                $tenancyId, $userId,
                $tenantName, $tenantIC,
                $tenantPhone ?: null, $tenantEmail ?: null,
                $userId,
            ]);

            // Insert co-tenants
            $order = 2;
            foreach ($coTenants as $co) {
                $stmt = $pdo->prepare("
                    INSERT INTO co_tenants
                        (tenancy_id, student_id, is_primary, full_name, ic_number, sign_order, added_by, status)
                    VALUES (?, NULL, 0, ?, ?, ?, ?, 'pending')
                ");
                $stmt->execute([$tenancyId, $co['name'], $co['ic'], $order, $userId]);
                $order++;
            }

            // Post response message in chat
            $tenantCount = 1 + count($coTenants);
            $responsePayload = json_encode([
                'source_form_id' => $formId,
                'tenancy_id'     => $tenancyId,
                'tenant_count'   => $tenantCount,
            ]);
            $bodyText = sprintf(
                'Tenant info submitted for "%s" — %d tenant%s · Tenancy #%d',
                $prop['title'], $tenantCount, $tenantCount > 1 ? 's' : '', $tenancyId
            );
            $stmt = $pdo->prepare("
                INSERT INTO messages
                    (conversation_id, sender_id, body, message_type, metadata, sent_at)
                VALUES (?, ?, ?, 'tenant_info_response', ?, NOW())
            ");
            $stmt->execute([$convId, $userId, $bodyText, $responsePayload]);

            // Update conversation preview
            $pdo->prepare("
                UPDATE conversations
                   SET last_message_at = NOW(),
                       last_message_preview = ?,
                       last_sender_id = ?
                 WHERE id = ?
            ")->execute([substr($bodyText, 0, 120), $userId, $convId]);

            // Notify agent
            $agentId = (int)$prop['assigned_agent_id'];
            if (function_exists('notify') && $agentId) {
                notify(
                    $agentId,
                    'tenant_info_submitted',
                    'Tenant info submitted',
                    sprintf('Student submitted tenant details for "%s". Ready to generate contract.', $prop['title']),
                    "/rentbridge/chat/conversation.php?id={$convId}"
                );
            }

            $pdo->commit();
            $success    = true;
            $tenancyIdFinal = $tenancyId;

        } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors['general'] = 'Something went wrong. Please try again.';
            error_log('[tenant_form] ' . $e->getMessage());
        }
    }
}

// Prefill from profile if not already set
if (empty($prefill['tenant_name']) || empty($prefill['tenant_email'])) {
    $stmt = $pdo->prepare("
        SELECT s.full_name, s.phone, u.email
          FROM students s JOIN users u ON u.id = s.user_id
         WHERE s.user_id = ?
    ");
    $stmt->execute([$userId]);
    $me = $stmt->fetch() ?: [];
    $prefill['tenant_name']  = $prefill['tenant_name']  ?: ($me['full_name'] ?? '');
    $prefill['tenant_phone'] = $prefill['tenant_phone'] ?: ($me['phone'] ?? '');
    $prefill['tenant_email'] = $prefill['tenant_email'] ?: ($me['email'] ?? '');
    $prefill['monthly_rent'] = $prefill['monthly_rent'] ?? $prop['monthly_rent'];
    $prefill['deposit']      = $prefill['deposit']      ?? $prop['deposit'];
}

$pageTitle = 'Tenant Info Form';
$activeNav = '';

ob_start();
?>

<p class="small mb-3">
    <a href="/rentbridge/chat/conversation.php?id=<?= $convId ?>"
       class="text-secondary text-decoration-none">
        <i class="bi bi-arrow-left"></i> Back to chat
    </a>
</p>

<?php if ($alreadySubmitted || $success): ?>

<div class="bg-white border border-success rounded-3 p-5 text-center" style="max-width:600px; margin:0 auto;">
    <i class="bi bi-check-circle-fill text-success" style="font-size:3rem;"></i>
    <h4 class="mt-3">Form submitted</h4>
    <p class="text-secondary">
        Your tenant details have been sent to the agent.
        They will prepare the contract and get back to you.
    </p>
    <a href="/rentbridge/chat/conversation.php?id=<?= $convId ?>"
       class="btn btn-primary mt-2">
        <i class="bi bi-chat-dots me-1"></i> Return to chat
    </a>
</div>

<?php else: ?>

<div class="row g-4 justify-content-center">
    <div class="col-lg-8">

        <div class="mb-4">
            <h3 class="mb-1" style="font-family:'Fraunces',serif;">Tenant Information Form</h3>
            <p class="text-secondary small mb-0">
                Fill in your details and any co-tenants. This information will appear on the legal contract.
            </p>
        </div>

        <!-- Property banner -->
        <div class="bg-white border rounded-3 p-3 mb-4 d-flex gap-3 align-items-center">
            <?php if (!empty($prop['image_path'])): ?>
            <div style="width:64px; height:64px; border-radius:8px; overflow:hidden; flex-shrink:0;">
                <img src="/rentbridge/<?= e($prop['image_path']) ?>"
                     style="width:100%; height:100%; object-fit:cover;" alt="">
            </div>
            <?php endif; ?>
            <div>
                <strong><?= e($prop['title']) ?></strong>
                <div class="small text-secondary">
                    <i class="bi bi-geo-alt"></i> <?= e($prop['city']) ?>
                    · RM <?= number_format((float)$prop['monthly_rent']) ?>/mo
                </div>
            </div>
        </div>

        <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-danger"><?= e($errors['general']) ?></div>
        <?php endif; ?>

        <form method="POST">
            <?= csrf_field() ?>

            <!-- PRIMARY TENANT -->
            <div class="bg-white border rounded-3 p-4 mb-4">
                <h5 class="mb-3">
                    <i class="bi bi-person-fill me-1 text-primary"></i>
                    Primary tenant (you)
                </h5>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            Full name <small class="text-danger">*</small>
                        </label>
                        <input type="text" name="tenant_name"
                               class="form-control <?= isset($errors['tenant_name']) ? 'is-invalid' : '' ?>"
                               value="<?= e($_POST['tenant_name'] ?? $prefill['tenant_name']) ?>"
                               placeholder="As per NRIC" required>
                        <?php if (isset($errors['tenant_name'])): ?>
                            <div class="invalid-feedback"><?= e($errors['tenant_name']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            NRIC <small class="text-danger">*</small>
                        </label>
                        <input type="text" name="tenant_ic"
                               class="form-control <?= isset($errors['tenant_ic']) ? 'is-invalid' : '' ?>"
                               value="<?= e($_POST['tenant_ic'] ?? '') ?>"
                               placeholder="020815-04-1234" required>
                        <?php if (isset($errors['tenant_ic'])): ?>
                            <div class="invalid-feedback"><?= e($errors['tenant_ic']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Phone</label>
                        <input type="text" name="tenant_phone"
                               class="form-control"
                               value="<?= e($_POST['tenant_phone'] ?? $prefill['tenant_phone']) ?>"
                               placeholder="01X-XXXXXXX">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Email</label>
                        <input type="email" name="tenant_email"
                               class="form-control"
                               value="<?= e($prefill['tenant_email']) ?>"
                               readonly style="background:#F4F4EE;">
                        <small class="text-secondary">Your registered email.</small>
                    </div>
                </div>
            </div>

            <!-- CO-TENANTS -->
            <div class="bg-white border rounded-3 p-4 mb-4">
                <h5 class="mb-1">
                    <i class="bi bi-people-fill me-1 text-secondary"></i>
                    Co-tenants
                    <small class="text-secondary fw-normal fs-6">(optional)</small>
                </h5>
                <p class="text-secondary small mb-3">
                    Others who will share this rental. All names and NRICs appear on the contract.
                </p>

                <div id="coTenantsList">
                    <?php
                    $oldCoNames = $_POST['cotenant_name'] ?? [];
                    $oldCoICs   = $_POST['cotenant_ic']   ?? [];
                    foreach ($oldCoNames as $i => $oldName):
                        if (trim($oldName) === '' && trim($oldCoICs[$i] ?? '') === '') continue;
                    ?>
                    <div class="row g-2 mb-3 align-items-start cotenant-row">
                        <div class="col-md-5">
                            <input type="text" name="cotenant_name[]"
                                   class="form-control form-control-sm <?= isset($errors['cotenant_'.$i]) ? 'is-invalid' : '' ?>"
                                   value="<?= e($oldName) ?>"
                                   placeholder="Full name (as per NRIC)">
                            <?php if (isset($errors['cotenant_'.$i])): ?>
                                <div class="invalid-feedback"><?= e($errors['cotenant_'.$i]) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-5">
                            <input type="text" name="cotenant_ic[]"
                                   class="form-control form-control-sm <?= isset($errors['cotenant_ic_'.$i]) ? 'is-invalid' : '' ?>"
                                   value="<?= e($oldCoICs[$i] ?? '') ?>"
                                   placeholder="NRIC (12 digits)">
                            <?php if (isset($errors['cotenant_ic_'.$i])): ?>
                                <div class="invalid-feedback"><?= e($errors['cotenant_ic_'.$i]) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-2">
                            <button type="button" class="btn btn-sm btn-outline-danger remove-cotenant w-100">
                                <i class="bi bi-x"></i> Remove
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <button type="button" id="addCoTenantBtn" class="btn btn-outline-secondary btn-sm">
                    <i class="bi bi-plus-circle me-1"></i> Add co-tenant
                </button>
            </div>

            <!-- TENANCY TERMS -->
            <div class="bg-white border rounded-3 p-4 mb-4">
                <h5 class="mb-3">
                    <i class="bi bi-file-earmark-text me-1 text-warning"></i>
                    Tenancy terms
                </h5>
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">
                            Monthly rent (RM) <small class="text-danger">*</small>
                        </label>
                        <input type="number" name="monthly_rent"
                               class="form-control <?= isset($errors['monthly_rent']) ? 'is-invalid' : '' ?>"
                               value="<?= e($_POST['monthly_rent'] ?? $prefill['monthly_rent']) ?>"
                               min="0" step="50" required>
                        <?php if (isset($errors['monthly_rent'])): ?>
                            <div class="invalid-feedback"><?= e($errors['monthly_rent']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Deposit (RM)</label>
                        <input type="number" name="deposit"
                               class="form-control"
                               value="<?= e($_POST['deposit'] ?? $prefill['deposit']) ?>"
                               min="0" step="50">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">
                            Term (months) <small class="text-danger">*</small>
                        </label>
                        <select name="term_months" class="form-select">
                            <?php foreach ([4 => '4 months (1 semester)', 8 => '8 months (2 semesters)', 12 => '12 months (1 year)', 24 => '24 months (2 years)'] as $val => $label): ?>
                            <option value="<?= $val ?>"
                                <?= (int)($_POST['term_months'] ?? 12) === $val ? 'selected' : '' ?>>
                                <?= $label ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">
                            Start date <small class="text-danger">*</small>
                        </label>
                        <input type="date" name="start_date"
                               class="form-control <?= isset($errors['start_date']) ? 'is-invalid' : '' ?>"
                               value="<?= e($_POST['start_date'] ?? date('Y-m-d')) ?>"
                               required>
                        <?php if (isset($errors['start_date'])): ?>
                            <div class="invalid-feedback"><?= e($errors['start_date']) ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Special terms / notes</label>
                        <textarea name="notes" rows="3" class="form-control"
                                  placeholder="Any special conditions agreed with the agent or landlord."><?= e($_POST['notes'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>

            <div class="alert alert-light border small">
                <i class="bi bi-info-circle text-secondary me-1"></i>
                By submitting, you confirm all details are accurate. The agent will generate a
                contract PDF based on this information.
            </div>

            <div class="d-flex justify-content-between align-items-center">
                <a href="/rentbridge/chat/conversation.php?id=<?= $convId ?>"
                   class="btn btn-outline-secondary">
                    Cancel
                </a>
                <button type="submit" class="btn btn-warning fw-semibold">
                    <i class="bi bi-check-circle me-1"></i> Submit tenant details
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    let count = <?= count(array_filter($oldCoNames ?? [], fn($n) => trim($n) !== '')) ?>;

    function addRow() {
        const list = document.getElementById('coTenantsList');
        const row  = document.createElement('div');
        row.className = 'row g-2 mb-3 align-items-start cotenant-row';
        row.innerHTML = `
            <div class="col-md-5">
                <input type="text" name="cotenant_name[]"
                       class="form-control form-control-sm"
                       placeholder="Full name (as per NRIC)">
            </div>
            <div class="col-md-5">
                <input type="text" name="cotenant_ic[]"
                       class="form-control form-control-sm"
                       placeholder="NRIC (12 digits)">
            </div>
            <div class="col-md-2">
                <button type="button" class="btn btn-sm btn-outline-danger remove-cotenant w-100">
                    <i class="bi bi-x"></i> Remove
                </button>
            </div>`;
        list.appendChild(row);
        row.querySelector('.remove-cotenant').addEventListener('click', () => row.remove());
    }

    document.getElementById('addCoTenantBtn').addEventListener('click', addRow);

    document.querySelectorAll('.remove-cotenant').forEach(btn => {
        btn.addEventListener('click', () => btn.closest('.cotenant-row').remove());
    });
})();
</script>

<?php endif; ?>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/student_layout.php';
