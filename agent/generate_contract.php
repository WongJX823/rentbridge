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
$startTs    = strtotime($tenancy['start_date']);
$endTs      = strtotime($tenancy['end_date']);
$termMonths = max(1, (int)round(($endTs - $startTs) / (30.44 * 86400)));
$termLabel  = match($tenancy['duration_type']) {
    'three_semesters' => '13 months (3 semesters)',
    'four_semesters'  => '18 months (4 semesters)',
    'two_years'       => '24 months (2 years)',
    'three_years'     => '36 months (3 years)',
    '1_semester'      => '5 months (1 semester)',
    '2_semesters'     => '10 months (2 semesters)',
    '1_year'          => '12 months (1 year)',
    'custom'          => $termMonths . ' months',
    default           => $termMonths . ' months',
};
$propertyAddress = $tenancy['property_address'] . ', ' . $tenancy['property_city'] . ' ' . $tenancy['property_postcode'] . ', ' . $tenancy['property_state'];

$coTenantsData = [];
foreach ($coTenants as $ct) {
    $coTenantsData[] = [
        'full_name'  => $ct['full_name'],
        'ic_number'  => $ct['ic_number'],
        'phone'      => $ct['phone'] ?? '',
        'is_primary' => (int)$ct['is_primary'],
        'sig_img'    => null,
        'sig_date'   => null,
    ];
}

$data = [
    'contract_code'    => $contractCode,
    'today'            => date('jS \\d\\a\\y \\o\\f F Y'),
    'landlord_name'    => $tenancy['landlord_name'],
    'landlord_ic'      => $tenancy['landlord_ic'],
    'landlord_phone'   => $tenancy['landlord_phone'] ?? '',
    'property_type'    => $tenancy['property_type'],
    'property_address' => $propertyAddress,
    'term_label'       => $termLabel,
    'start_short'      => date('d/m/Y', $startTs),
    'end_short'        => date('d/m/Y', $endTs),
    'monthly_rent'     => number_format((float)$tenancy['monthly_rent'], 2),
    'security_deposit' => number_format((float)$tenancy['deposit'], 2),
    'utility_deposit'  => number_format((float)$tenancy['deposit'] * 0.3, 2),
    'tenancy_label'    => 'TENANTS',
    'landlord_sig_img' => null,
    'landlord_sig_date'=> null,
    'co_tenants'       => $coTenantsData,
];

// === Generate PDF with mPDF ===
try {
    $html = rb_agreement_html($data);
    $relativePath = rb_render_agreement_pdf($html, $contractCode, 'generated_contracts');
    if (!$relativePath) {
        throw new RuntimeException('Failed to render the contract PDF.');
    }
    $absolutePath = __DIR__ . '/../' . $relativePath;

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
