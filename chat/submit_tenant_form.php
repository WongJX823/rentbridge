<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();  // any logged-in user; we verify role-specific access below
$userRole = current_role();
if (!in_array($userRole, ['landlord', 'student'], true)) {
    echo json_encode(['ok' => false, 'error' => 'Only landlords or students can submit this form']);
    exit;
}

header('Content-Type: application/json');

verify_csrf();

$pdo = db();
$landlordId = current_user_id();

$formId     = (int)($_POST['form_id'] ?? 0);
$convId     = (int)($_POST['conversation_id'] ?? 0);
$propertyId = (int)($_POST['property_id'] ?? 0);
$studentId  = (int)($_POST['student_id'] ?? 0);

if ($formId <= 0 || $convId <= 0 || $propertyId <= 0 || $studentId <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Missing parameters']);
    exit;
}

// Validate input
$tenantName  = trim($_POST['tenant_name'] ?? '');
$tenantIC    = trim($_POST['tenant_ic'] ?? '');
$tenantPhone = trim($_POST['tenant_phone'] ?? '');
$tenantEmail = trim($_POST['tenant_email'] ?? '');
$monthlyRent = (float)($_POST['monthly_rent'] ?? 0);
$deposit     = (float)($_POST['deposit'] ?? 0);
$termMonths  = (int)($_POST['term_months'] ?? 12);
$startDate   = trim($_POST['start_date'] ?? '');
$notes       = trim($_POST['notes'] ?? '');

if ($tenantName === '' || $tenantIC === '' || $startDate === '' || $monthlyRent <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Please fill in all required fields.']);
    exit;
}

// IC format check (12 digits after stripping non-numerics)
$icClean = preg_replace('/[^0-9]/', '', $tenantIC);
if (strlen($icClean) !== 12) {
    echo json_encode(['ok' => false, 'error' => 'Invalid IC format (must be 12 digits).']);
    exit;
}

// Verify the user is allowed to submit
if ($userRole === 'landlord') {
    // Landlord-led: this user must own the property
    $stmt = $pdo->prepare("
        SELECT p.id, p.landlord_id, p.assigned_agent_id, p.title
          FROM properties p
         WHERE p.id = ? AND p.landlord_id = ?
    ");
    $stmt->execute([$propertyId, current_user_id()]);
} else {
    // Agent-led: this user must be the student in the form
    if ((int)current_user_id() !== $studentId) {
        echo json_encode(['ok' => false, 'error' => 'You can only submit for yourself']);
        exit;
    }
    $stmt = $pdo->prepare("
        SELECT p.id, p.landlord_id, p.assigned_agent_id, p.title
          FROM properties p
         WHERE p.id = ?
    ");
    $stmt->execute([$propertyId]);
}
$prop = $stmt->fetch();
if (!$prop) {
    echo json_encode(['ok' => false, 'error' => 'Property not found']);
    exit;
}

$agentId = (int)$prop['assigned_agent_id'];

// Verify the form message exists
$stmt = $pdo->prepare("
    SELECT id FROM messages
     WHERE id = ? AND conversation_id = ? AND message_type = 'tenant_info_form'
");
$stmt->execute([$formId, $convId]);
if (!$stmt->fetchColumn()) {
    echo json_encode(['ok' => false, 'error' => 'Form not found']);
    exit;
}

// Already responded?
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM messages
     WHERE conversation_id = ? 
       AND message_type = 'tenant_info_response'
       AND JSON_EXTRACT(metadata, '$.source_form_id') = ?
");
$stmt->execute([$convId, $formId]);
if ((int)$stmt->fetchColumn() > 0) {
    echo json_encode(['ok' => false, 'error' => 'Form already submitted.']);
    exit;
}

// Collect co-tenants
$coTenants = [];
$coNames = $_POST['cotenant_name'] ?? [];
$coICs   = $_POST['cotenant_ic'] ?? [];
foreach ($coNames as $i => $name) {
    $name = trim($name);
    $ic   = trim($coICs[$i] ?? '');
    if ($name === '' && $ic === '') continue;
    if ($name === '' || $ic === '') {
        echo json_encode(['ok' => false, 'error' => "Co-tenant #" . ($i + 1) . " missing name or IC"]);
        exit;
    }
    $icClean2 = preg_replace('/[^0-9]/', '', $ic);
    if (strlen($icClean2) !== 12) {
        echo json_encode(['ok' => false, 'error' => "Co-tenant #" . ($i + 1) . " has invalid IC"]);
        exit;
    }
    $coTenants[] = ['name' => $name, 'ic' => $ic];
}

// Compute end_date from start_date + termMonths
try {
    $startDt = new DateTime($startDate);
    $endDt   = (clone $startDt)->modify('+' . $termMonths . ' months');
    $endDate = $endDt->format('Y-m-d');
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'Invalid start date']);
    exit;
}

// Map term_months to duration_type
$durationType = match(true) {
    $termMonths === 4 || $termMonths === 5  => '1_semester',
    $termMonths === 8 || $termMonths === 10 => '2_semesters',
    $termMonths === 12                       => '1_year',
    default                                  => 'custom',
};

try {
    $pdo->beginTransaction();

    // Create booking row
    $stmt = $pdo->prepare("
        INSERT INTO bookings 
            (student_id, property_id, landlord_id, agent_id,
             start_date, end_date, duration_type,
             monthly_rent, deposit, status,
             student_note, landlord_response, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'contract_pending', NULL, ?, NOW())
    ");
    $stmt->execute([
        $studentId, $propertyId, $landlordId, $agentId,
        $startDate, $endDate, $durationType,
        $monthlyRent, $deposit,
        $notes !== '' ? $notes : null,
    ]);
    $bookingId = (int)$pdo->lastInsertId();

    // Insert primary tenant (the student)
    // In the co_tenants INSERT:
    $submitterId = current_user_id();

    $stmt = $pdo->prepare("
        INSERT INTO co_tenants 
            (booking_id, student_id, is_primary, full_name, ic_number, phone, email, sign_order, added_by, status)
        VALUES (?, ?, 1, ?, ?, ?, ?, 1, ?, 'pending')
    ");
    $stmt->execute([
        $bookingId, $studentId,
        $tenantName, $tenantIC,
        $tenantPhone ?: null, $tenantEmail ?: null,
        $submitterId,  // ← submitter, could be landlord or student
    ]);

    // Insert co-tenants
    $order = 2;
    foreach ($coTenants as $co) {
        $stmt = $pdo->prepare("
            INSERT INTO co_tenants 
                (booking_id, student_id, is_primary, full_name, ic_number, sign_order, added_by, status)
            VALUES (?, NULL, 0, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$bookingId, $co['name'], $co['ic'], $order, $landlordId]);
        $order++;
    }

    // Post tenant_info_response message
    $tenantCount = 1 + count($coTenants);
    $responsePayload = json_encode([
        'source_form_id' => $formId,
        'booking_id'     => $bookingId,
        'tenant_count'   => $tenantCount,
    ]);
    $bodyText = sprintf(
        'Tenant info submitted for "%s" — %d tenant%s · Booking #%d',
        $prop['title'], $tenantCount, $tenantCount > 1 ? 's' : '', $bookingId
    );

    $stmt = $pdo->prepare("
        INSERT INTO messages 
            (conversation_id, sender_id, body, message_type, metadata, sent_at)
        VALUES (?, ?, ?, 'tenant_info_response', ?, NOW())
    ");
    $stmt->execute([$convId, $landlordId, $bodyText, $responsePayload]);

    // Update conversation preview
    $stmt = $pdo->prepare("
        UPDATE conversations
           SET last_message_at = NOW(),
               last_message_preview = ?,
               last_sender_id = ?
         WHERE id = ?
    ");
    $stmt->execute([substr($bodyText, 0, 120), $landlordId, $convId]);

    // Notify agent
    if (function_exists('notify') && $agentId) {
        notify(
            $agentId,
            'tenant_info_submitted',
            'Tenant info submitted',
            'Landlord submitted tenant details for "' . $prop['title'] . '". Ready to generate contract.',
            "/rentbridge/chat/conversation.php?id={$convId}"
        );
    }

    $pdo->commit();
    echo json_encode([
        'ok' => true,
        'booking_id' => $bookingId,
        'message' => 'Form submitted. Agent will now generate the contract.',
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}

// Notify the landlord that their property is being rented
if (function_exists('notify')) {
    notify(
        (int)$prop['landlord_id'],
        'tenancy_in_progress',
        'Your property is being rented',
        sprintf('A student is submitting paperwork to rent "%s". The agent is handling the contract.', $prop['title']),
        "/rentbridge/landlord/properties.php"
    );
}

// Optionally: post a message in landlord-agent chat
$agentId = (int)$prop['assigned_agent_id'];
$landlordId = (int)$prop['landlord_id'];
if ($agentId > 0 && $landlordId > 0) {
    // Find or create landlord-agent conversation
    $low = min($landlordId, $agentId);
    $high = max($landlordId, $agentId);
    
    $stmt = $pdo->prepare("
        SELECT id FROM conversations
         WHERE property_id = ? AND user_a = ? AND user_b = ?
           AND context_type IN ('contract_prep', 'agent_case', 'other')
         LIMIT 1
    ");
    $stmt->execute([$propertyId, $low, $high]);
    $laConvId = (int)$stmt->fetchColumn();
    
    if (!$laConvId) {
        $stmt = $pdo->prepare("
            INSERT INTO conversations 
                (user_a, user_b, property_id, context_type, created_at)
            VALUES (?, ?, ?, 'agent_case', NOW())
        ");
        $stmt->execute([$low, $high, $propertyId]);
        $laConvId = (int)$pdo->lastInsertId();
    }
    
    $bodyText = sprintf(
        'Student %s is renting "%s" — booking #%d created. Contract paperwork in progress.',
        $tenantName, $prop['title'], $bookingId
    );
    $stmt = $pdo->prepare("
        INSERT INTO messages 
            (conversation_id, sender_id, body, message_type, sent_at)
        VALUES (?, ?, ?, 'system_notice', NOW())
    ");
    $stmt->execute([$laConvId, current_user_id(), $bodyText]);
}