<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/co_tenants.php';
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
    header('Location: /rentbridge/agent/case.php?id=' . $tenancyId);
    exit;
}

// Check landlord IC exists
if (empty($tenancy['landlord_ic'])) {
    set_flash('warning', 'Landlord profile missing IC number. Cannot generate contract.');
    header('Location: /rentbridge/agent/case.php?id=' . $tenancyId);
    exit;
}

// === Determine contract code (reuse if exists, generate if new) ===
$stmt = $pdo->prepare("SELECT id, contract_code FROM contracts WHERE tenancy_id = ? LIMIT 1");
$stmt->execute([$tenancyId]);
$existingContract = $stmt->fetch();

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

// === Generate the PDF ===
$startDate  = date('jS \\d\\a\\y \\o\\f F Y', strtotime($tenancy['start_date']));
$startShort = date('d/m/Y', strtotime($tenancy['start_date']));
$endShort   = date('d/m/Y', strtotime($tenancy['end_date']));
$today      = date('jS \\d\\a\\y \\o\\f F Y');
$monthlyRent = number_format((float)$tenancy['monthly_rent'], 2);
$securityDeposit = number_format((float)$tenancy['deposit'], 2);
$utilityDeposit  = number_format((float)$tenancy['deposit'] * 0.3, 2); // typically ~30% of security

// Build co-tenant list for Part 3 (First Schedule)
$tenantListHtml = '';
foreach ($coTenants as $idx => $ct) {
    $label = ((int)$ct['is_primary'] === 1) ? 'Primary Tenant' : 'Co-Tenant #' . $idx;
    $tenantListHtml .= '<p style="margin-bottom: 4px;"><strong>' . htmlspecialchars($ct['full_name']) . '</strong> '
                     . '(' . htmlspecialchars($label) . ')<br>'
                     . 'NRIC: ' . htmlspecialchars($ct['ic_number']);
    if (!empty($ct['phone'])) {
        $tenantListHtml .= ' &nbsp; · &nbsp; Tel: ' . htmlspecialchars($ct['phone']);
    }
    $tenantListHtml .= '</p>';
}

// Build signature blocks
$signatureBlocksHtml = '';
$signatureBlocksHtml .= buildSignatureBlock('LANDLORD', $tenancy['landlord_name'], $tenancy['landlord_ic'], $tenancy['landlord_phone']);
foreach ($coTenants as $idx => $ct) {
    $role = ((int)$ct['is_primary'] === 1) ? 'TENANT (Primary)' : 'CO-TENANT';
    $signatureBlocksHtml .= buildSignatureBlock($role, $ct['full_name'], $ct['ic_number'], $ct['phone'] ?? '');
}
// Agent witness block
if (!empty($tenancy['agent_name'])) {
    $signatureBlocksHtml .= buildSignatureBlock(
        'WITNESSED BY AGENT',
        $tenancy['agent_name'],
        $tenancy['agent_staff_id'] ?? '',
        '',
        true
    );
}

function buildSignatureBlock(string $role, string $name, string $ic, string $phone = '', bool $isWitness = false): string {
    $ph = $phone ? '<br>Contact: ' . htmlspecialchars($phone) : '';
    $icLabel = $isWitness ? 'Staff ID' : 'NRIC';
    return '<div style="margin-bottom: 50px;">'
         . '<p><strong>SIGNED BY ' . htmlspecialchars($role) . '</strong></p>'
         . '<table width="100%" style="margin-top: 20px;">'
         . '<tr><td width="50%">NAME: ' . htmlspecialchars($name) . '<br>'
         . $icLabel . ': ' . htmlspecialchars($ic) . $ph
         . '</td><td width="50%" style="text-align: right;">_______________________<br>Signature & Date</td></tr>'
         . '</table>'
         . '</div>';
}

// Build full HTML
$propertyAddress = $tenancy['property_address'] . ', ' . $tenancy['property_city'] . ' ' . $tenancy['property_postcode'] . ', ' . $tenancy['property_state'];

$html = <<<HTML
<style>
body { font-family: serif; font-size: 12pt; line-height: 1.5; }
h1, h2 { text-align: center; font-family: serif; }
.cover { text-align: center; padding-top: 200pt; }
.cover h1 { font-size: 28pt; letter-spacing: 4pt; }
.section-title { font-weight: bold; margin-top: 18pt; margin-bottom: 6pt; }
.schedule-item { margin-bottom: 12pt; }
.schedule-item strong { display: inline-block; min-width: 200pt; }
.center { text-align: center; }
table.parties { width: 100%; margin: 20pt 0; }
table.parties td { padding: 8pt; vertical-align: top; }
.contract-code { position: fixed; top: 10pt; right: 10pt; font-size: 9pt; color: #999; }
.footer-code { font-size: 9pt; color: #999; text-align: center; }
</style>

<!-- COVER PAGE -->
<div class="cover">
    <h1>TENANCY AGREEMENT</h1>
    <div style="margin-top: 80pt; font-size: 11pt; color: #666;">
        Contract Reference<br>
        <strong style="font-size: 14pt; letter-spacing: 2pt;">{$contractCode}</strong>
    </div>
</div>

<pagebreak />

<!-- PARTIES PAGE -->
<h2>TENANCY AGREEMENT</h2>
<p class="center"><strong>DATED THIS {$today}</strong></p>

<table class="parties">
    <tr>
        <td class="center">
            <strong>{$tenancy['landlord_name']}</strong><br>
            {$tenancy['landlord_ic']}<br>
            <em>(LANDLORD)</em>
        </td>
    </tr>
    <tr>
        <td class="center" style="padding: 30pt 0;"><strong>AND</strong></td>
    </tr>
    <tr>
        <td class="center">
            <em>(TENANT{$tenancyId})</em><br><br>
            {$tenantListHtml}
        </td>
    </tr>
</table>

<pagebreak />

<!-- AGREEMENT BODY -->
<h2>TENANCY AGREEMENT</h2>

<p><strong>AN AGREEMENT</strong> made on {$today}</p>
<p><strong>Between</strong></p>
<p>The party whose name and particulars appear in Part Two of The First Schedule (<strong>"Landlord"</strong>) of the other part.</p>
<p><strong>And</strong></p>
<p>The parties whose names and particulars appear in Part Three of The First Schedule (<strong>"Tenants"</strong>) of the other part.</p>

<p class="section-title">WHEREAS:</p>
<p>1. The Landlord is the registered and/or beneficial owner of the property described in Part Four of The First Schedule ("the Demised Premises").</p>
<p>2. The Landlord is desirous of letting and the Tenants are desirous of taking a tenancy of the Demised Premises upon the terms and subject to the conditions stipulated herein.</p>

<p class="section-title">NOW IT IS HEREBY AGREED as follows:</p>

<p class="section-title">1. Agreement</p>
<p>In consideration of the rent hereinafter reserved and the covenants on the part of the Tenants hereinafter contained, the Landlord hereby lets to the Tenants the whole of the Demised Premises for a term stated in Part Five of The First Schedule commencing on the day and year set out in Part Six of The First Schedule and terminating on the day and year set out in Part Seven of the same at the monthly rent and payable in the manner stipulated in Part Eight of The First Schedule.</p>

<p class="section-title">2. Tenant's Covenants</p>
<p>The Tenants hereby jointly and severally covenant with the Landlord as follows:</p>
<p>(a) To pay the reserved rent on the days and in the manner aforesaid;</p>
<p>(b) To pay on the execution of this Agreement the Rental Deposit and Utility Deposit in respect of electricity, water, indah water and other amenities supplied to and consumed by the Demised Premises as set out in Part Nine of The First Schedule (hereinafter collectively referred to as "the Deposit Sum") to the Landlord as security for the due observance and performance by the Tenants of the stipulated terms and conditions of this Agreement.</p>
<p>(c) The Tenants agree to rent the said Demised Premises for the full term as set out in Part Five, failing which the Landlord shall be entitled to forfeit the Security Deposit.</p>
<p>(d) Any notice requiring to be served hereunder shall be in writing and shall be sufficiently served on the Tenants if left addressed to them at the Demised Premises or forwarded by registered post to the last known address.</p>
<p>(e) The costs and expenses incidental to this Agreement including stamp duty shall be borne and paid by the Tenants.</p>
<p>(f) To use the Demised Premises for residential purposes only.</p>
<p>(g) Not to assign or sub-let the Said Premises without the prior written consent of the Landlord.</p>
<p>(h) To keep the interior of the Demised Premises in good and tenantable repair.</p>
<p>(i) To permit the Landlord and his duly authorized agents at all reasonable times to enter upon Demised Premises to view the state and conditions thereof.</p>

<p class="section-title">3. Landlord's Covenants</p>
<p>The Landlord hereby covenants with the Tenants as follows:</p>
<p>(a) To permit the Tenants, if they punctually pay the rent and observe the covenants herein, peaceably to hold and enjoy the Premises during this Tenancy without disturbances.</p>
<p>(b) To pay all Assessment and Quit Rent from time to time due in respect of the Demised Premises.</p>
<p>(c) To insure and keep insured the Demised Premises from loss or damage by fire.</p>
<p>(d) Upon termination of the Tenancy, the Landlord shall refund to the Tenants the Deposit Sum free of interest after due deductions for damages and arrears.</p>

<p class="section-title">4. Joint and Several Liability</p>
<p>Where there are multiple Tenants, all named Tenants in Part Three of The First Schedule shall be jointly and severally liable for the obligations under this Agreement. Each Tenant acknowledges responsibility for the full rental amount and all covenants, regardless of internal arrangements between the Tenants.</p>

<p class="section-title">5. Mutual Covenants</p>
<p>(a) If the rent shall be in arrears for fourteen (14) days, the Landlord may serve a forfeiture notice and re-enter the Demised Premises.</p>
<p>(b) The Tenants shall not use the Premises for any illegal or unlawful purpose.</p>
<p>(c) The Tenants shall pay all charges for electricity, water, sewerage, and other utilities consumed during the term.</p>

<pagebreak />

<!-- FIRST SCHEDULE -->
<h2>THE FIRST SCHEDULE</h2>
<p class="center"><em>(Which is to be taken and construed as an essential and integral part of this Agreement)</em></p>

<div class="schedule-item">
    <strong>1. Date of Agreement:</strong> {$today}
</div>

<div class="schedule-item">
    <strong>2. Landlord:</strong><br>
    NAME: {$tenancy['landlord_name']}<br>
    NRIC: {$tenancy['landlord_ic']}<br>
    CONTACT: {$tenancy['landlord_phone']}
</div>

<div class="schedule-item">
    <strong>3. Tenants:</strong>
    <div style="margin-left: 20pt; margin-top: 8pt;">
        {$tenantListHtml}
    </div>
</div>

<div class="schedule-item">
    <strong>4. Demised Premises:</strong><br>
    TYPE: {$tenancy['property_type']}<br>
    ADDRESS: {$propertyAddress}
</div>

<div class="schedule-item">
    <strong>5. Term:</strong> 12 months (or as agreed)
</div>

<div class="schedule-item">
    <strong>6. Commencement:</strong> {$startShort}
</div>

<div class="schedule-item">
    <strong>7. Termination:</strong> {$endShort}
</div>

<div class="schedule-item">
    <strong>8. Monthly Rental:</strong> RM {$monthlyRent}<br>
    <strong>Payment:</strong> Before the 10th of every month
</div>

<div class="schedule-item">
    <strong>9. Deposits:</strong><br>
    Security Deposit: RM {$securityDeposit} (equivalent to 2 months rental)<br>
    Utility Deposit: RM {$utilityDeposit}
</div>

<div class="schedule-item">
    <strong>10. Authorized Use:</strong> For Residential Use only
</div>

<div class="schedule-item">
    <strong>11. Renewal:</strong> Renewable subject to market price at the time of renewal
</div>

<pagebreak />

<!-- SIGNATURES -->
<h2>SIGNATURES</h2>
<p>IN WITNESS WHEREOF THE PARTIES HERETO HAVE HEREUNTO SET THEIR HANDS DAY AND YEAR FIRST ABOVE WRITTEN.</p>

<div style="margin-top: 30pt;">
    {$signatureBlocksHtml}
</div>

<div class="footer-code">
    Contract Reference: {$contractCode} · Generated: {$today} · Verify: https://rentbridge.com/verify/{$contractCode}
</div>

HTML;

// === Generate PDF with mPDF ===
try {
    $mpdf = new \Mpdf\Mpdf([
        'tempDir' => sys_get_temp_dir(),
        'format' => 'A4',
        'margin_left' => 20,
        'margin_right' => 20,
        'margin_top' => 25,
        'margin_bottom' => 25,
    ]);
    $mpdf->SetTitle('Tenancy Agreement ' . $contractCode);
    $mpdf->SetAuthor('RentBridge');

    // Watermark with contract code (background)
    $mpdf->SetWatermarkText($contractCode);
    $mpdf->showWatermarkText = true;
    $mpdf->watermark_font = 'DejaVuSansCondensed';
    $mpdf->watermarkTextAlpha = 0.04;

    // Header on every page (contract code top-right)
    $mpdf->SetHTMLHeader('<div style="text-align: right; font-size: 8pt; color: #999;">Contract Reference: ' . $contractCode . '</div>');
    $mpdf->SetHTMLFooter('<div style="text-align: center; font-size: 8pt; color: #999;">Page {PAGENO} of {nbpg} · ' . $contractCode . '</div>');

    $mpdf->WriteHTML($html);

    // Save to file
    $filename = $contractCode . '_' . time() . '.pdf';
    $relativePath = 'uploads/generated_contracts/' . $filename;
    $absolutePath = __DIR__ . '/../' . $relativePath;

    $mpdf->Output($absolutePath, \Mpdf\Output\Destination::FILE);

    // Hash for integrity
    $docHash = hash_file('sha256', $absolutePath);

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

    // Notify all parties
    notify(
        (int)$tenancy['student_id'],
        'contract_generated',
        'Tenancy contract generated',
        'Agent has generated contract ' . $contractCode . '. The agent will send it to you for signing.',
        '/rentbridge/student/tenancy.php?id=' . $tenancyId
    );
    notify(
        (int)$tenancy['landlord_id'],
        'contract_generated',
        'Tenancy contract generated',
        'Agent has generated contract ' . $contractCode . '. You will receive it from the agent for signing.',
        '/rentbridge/landlord/tenancy.php?id=' . $tenancyId
    );

    // Stream the PDF to the agent for download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . $contractCode . '.pdf"');
    header('Content-Length: ' . filesize($absolutePath));
    readfile($absolutePath);
    exit;

} catch (Throwable $e) {
    set_flash('danger', 'Failed to generate contract: ' . $e->getMessage());
    header('Location: /rentbridge/agent/case.php?id=' . $tenancyId);
    exit;
}