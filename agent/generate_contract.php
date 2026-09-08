<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/co_tenants.php';
require_once __DIR__ . '/../includes/contracts.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_role('agent');

$tenancyId = (int)($_GET['tenancy_id'] ?? 0);
if ($tenancyId <= 0) {
    die('Invalid tenancy.');
}

$pdo = db();
$userId = current_user_id();

// === Fetch tenancy + parties ===
$stmt = $pdo->prepare("
    SELECT b.*,
           p.title         AS property_title,
           p.address       AS property_address,
           p.city          AS property_city,
           p.state         AS property_state,
           p.postcode      AS property_postcode,
           p.property_type AS property_type,
           p.furnishing    AS furnishing,
           p.deposit       AS property_deposit,
           l.full_name     AS landlord_name,
           l.ic_no         AS landlord_ic,
           l.phone         AS landlord_phone,
           ul.email        AS landlord_email,
           a.full_name     AS agent_name,
           a.staff_id      AS agent_staff_id
      FROM tenancies b
      JOIN properties p   ON p.id = b.property_id
      JOIN landlords l    ON l.user_id = b.landlord_id
      JOIN users ul       ON ul.id = b.landlord_id
      LEFT JOIN agents a  ON a.user_id = b.agent_id
     WHERE b.id = ? AND b.agent_id = ?
     LIMIT 1
");
$stmt->execute([$tenancyId, $userId]);
$tenancy = $stmt->fetch();

if (!$tenancy) {
    die('Tenancy not found or you are not the assigned agent.');
}

// === Fetch co-tenants ===
$coTenants = get_co_tenants($tenancyId);

if (empty($coTenants)) {
    die('No tenants found for this tenancy. Please send the co-tenant form first.');
}

// === GATE: ensure primary IC is set ===
$primary = null;
foreach ($coTenants as $ct) {
    if ((int)$ct['is_primary'] === 1) {
        $primary = $ct;
        break;
    }
}

if (!$primary) {
    die('No primary tenant found. Data integrity issue.');
}

if ($primary['ic_number'] === 'PENDING' || empty($primary['ic_number'])) {
    set_flash('warning', 'Primary tenant has not submitted their IC number yet. Send the co-tenant form first.');
    header('Location: ' . BASE_PATH . '/agent/case.php?id=' . $tenancyId);
    exit;
}

// Check landlord IC exists
if (empty($tenancy['landlord_ic'])) {
    set_flash('warning', 'Landlord profile missing IC number. Cannot generate contract.');
    header('Location: ' . BASE_PATH . '/agent/case.php?id=' . $tenancyId);
    exit;
}

// === Determine contract code (reuse if exists, generate if new) ===
$stmt = $pdo->prepare("
    SELECT id, contract_code, status, landlord_signature, landlord_signed_at,
           contract_pdf_path, signed_pdf_path, generated_pdf_path
      FROM contracts WHERE tenancy_id = ? LIMIT 1
");
$stmt->execute([$tenancyId]);
$existingContract = $stmt->fetch();

// Once a contract is active/completed, regenerating it must never reset it
// back to pending_signatures — that would silently un-activate a fully
// signed tenancy. Just hand back the real final PDF instead.
if ($existingContract && in_array($existingContract['status'], ['active', 'completed'], true)) {
    $finalPath = $existingContract['contract_pdf_path']
        ?? $existingContract['signed_pdf_path']
        ?? $existingContract['generated_pdf_path']
        ?? null;
    if ($finalPath) {
        $bytes = rb_storage_get_contents($finalPath);
        if ($bytes !== null) {
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $existingContract['contract_code'] . '.pdf"');
            header('Content-Length: ' . strlen($bytes));
            echo $bytes;
            exit;
        }
    }
    set_flash('info', 'This contract is already ' . $existingContract['status'] . ' — it cannot be regenerated.');
    header('Location: ' . BASE_PATH . '/agent/case.php?id=' . $tenancyId);
    exit;
}

if ($existingContract) {
    $contractId = (int)$existingContract['id'];
    $contractCode = $existingContract['contract_code'];
} else {
    // Generate new contract code
    $year = date('Y');
    $stmt = $pdo->prepare("
        SELECT COALESCE(MAX(CAST(SUBSTRING(contract_code, 9) AS UNSIGNED)), 0) + 1
          FROM contracts WHERE contract_code LIKE ?
    ");
    $stmt->execute(["RB-$year-%"]);
    $nextNum = (int)$stmt->fetchColumn();
    $contractCode = sprintf("RB-%s-%05d", $year, $nextNum);

    // Insert new contract row
    $stmt = $pdo->prepare("
        INSERT INTO contracts
            (contract_code, tenancy_id, student_id, landlord_id, agent_id, property_id,
             start_date, end_date, monthly_rent, deposit, terms, status, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Standard 1-year tenancy.', 'pending_signatures', NOW())
    ");
    $stmt->execute([
        $contractCode,
        $tenancyId,
        (int)$tenancy['student_id'],
        (int)$tenancy['landlord_id'],
        (int)$tenancy['agent_id'],
        (int)$tenancy['property_id'],
        $tenancy['start_date'],
        $tenancy['end_date'],
        (float)$tenancy['monthly_rent'],
        (float)$tenancy['deposit'],
    ]);
    $contractId = (int)$pdo->lastInsertId();
}

// === Build the contract content (embeds whatever real e-signatures already
// exist, blank line for anyone who hasn't signed / chose physical) ===
$data = build_contract_agreement_data($tenancyId);
if ($data === null) {
    set_flash('danger', 'Could not load contract data.');
    header('Location: ' . BASE_PATH . '/agent/case.php?id=' . $tenancyId);
    exit;
}

// === Generate PDF with mPDF ===
try {
    $html = rb_agreement_html($data);
    $relativePath = rb_render_agreement_pdf($html, $contractCode, 'generated_contracts');
    if (!$relativePath) {
        throw new RuntimeException('Failed to render the contract PDF.');
    }
    $pdfBytes = rb_storage_get_contents($relativePath);
    if ($pdfBytes === null) {
        throw new RuntimeException('Rendered PDF could not be read back.');
    }

    // Content fingerprint (NOT a hash of the PDF bytes — mPDF embeds its own
    // creation timestamp on every render, so byte-hashes never match twice
    // even with identical content). Lets the merge feature detect whether
    // anything meaningful changed since this PDF was generated.
    $docHash = contract_agreement_fingerprint($data);

    // Update contract record
    $stmt = $pdo->prepare("
        UPDATE contracts
           SET generated_pdf_path = ?,
               generated_at = NOW(),
               generated_by = ?,
               doc_hash = ?,
               upload_method = 'generated',
               status = 'pending_signatures'
         WHERE id = ?
    ");
    $stmt->execute([$relativePath, $userId, $docHash, $contractId]);

    // Update tenancy status
    $stmt = $pdo->prepare("UPDATE tenancies SET status = 'contract_pending' WHERE id = ?");
    $stmt->execute([$tenancyId]);

    // Notify all parties — only on the first generation. Regenerate is now a
    // normal, repeatable action (e.g. to refresh embedded signatures mid-
    // signing), so it shouldn't re-fire "contract generated" every time.
    if (!$existingContract) {
        notify(
            (int)$tenancy['student_id'],
            'contract_generated',
            'Tenancy contract generated',
            'Agent has generated contract ' . $contractCode . '. The agent will send it to you for signing.',
            '' . BASE_PATH . '/student/tenancy.php?id=' . $tenancyId
        );
        notify(
            (int)$tenancy['landlord_id'],
            'contract_generated',
            'Tenancy contract generated',
            'Agent has generated contract ' . $contractCode . '. You will receive it from the agent for signing.',
            '' . BASE_PATH . '/landlord/tenancy.php?id=' . $tenancyId
        );
    }

    // Stream the PDF to the agent for download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $contractCode . '.pdf"');
    header('Content-Length: ' . strlen($pdfBytes));
    echo $pdfBytes;
    exit;

} catch (Throwable $e) {
    set_flash('danger', 'Failed to generate contract: ' . $e->getMessage());
    header('Location: ' . BASE_PATH . '/agent/case.php?id=' . $tenancyId);
    exit;
}
