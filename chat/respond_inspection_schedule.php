<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

header('Content-Type: application/json');
verify_csrf();

$pdo = db();
$userId = current_user_id();
$role   = current_role();

if ($role !== 'landlord') {
    echo json_encode(['ok' => false, 'error' => 'Only landlords can respond to inspection schedule requests.']);
    exit;
}

$convId    = (int)($_POST['conversation_id'] ?? 0);
$formMsgId = (int)($_POST['form_message_id'] ?? 0);
$decision  = $_POST['decision'] ?? '';         // 'confirm' or 'reschedule'
$slotPicked = trim($_POST['slot_picked'] ?? '');
$accessMethod = $_POST['access_method'] ?? '';
$accessDetail = trim($_POST['access_detail'] ?? '');
$consent      = (int)($_POST['consent'] ?? 0);

if ($convId <= 0 || $formMsgId <= 0 || !in_array($decision, ['confirm', 'reschedule'], true)) {
    echo json_encode(['ok' => false, 'error' => 'Missing or invalid fields.']);
    exit;
}

// Fetch the original request message
$stmt = $pdo->prepare("
    SELECT m.id, m.metadata, c.user_a, c.user_b, c.property_id
      FROM messages m
      JOIN conversations c ON c.id = m.conversation_id
     WHERE m.id = ? AND m.conversation_id = ? AND m.message_type = 'inspection_schedule_request'
     LIMIT 1
");
$stmt->execute([$formMsgId, $convId]);
$formMsg = $stmt->fetch();

if (!$formMsg) {
    echo json_encode(['ok' => false, 'error' => 'Original schedule request not found.']);
    exit;
}

// Verify this landlord is a participant
if ((int)$formMsg['user_a'] !== $userId && (int)$formMsg['user_b'] !== $userId) {
    echo json_encode(['ok' => false, 'error' => 'Not authorized.']);
    exit;
}

$propertyId = (int)$formMsg['property_id'];
$meta       = json_decode($formMsg['metadata'], true) ?? [];
$agentId    = ((int)$formMsg['user_a'] === $userId) ? (int)$formMsg['user_b'] : (int)$formMsg['user_a'];
$propTitle  = $meta['property_title'] ?? "property #{$propertyId}";

if ($decision === 'confirm') {
    if ($slotPicked === '') {
        echo json_encode(['ok' => false, 'error' => 'Please enter the confirmed date/time.']);
        exit;
    }
    if (!in_array($accessMethod, ['landlord_present', 'lockbox_code', 'other'], true)) {
        echo json_encode(['ok' => false, 'error' => 'Please select an access method.']);
        exit;
    }
    if ($consent !== 1) {
        echo json_encode(['ok' => false, 'error' => 'Please confirm your consent.']);
        exit;
    }

    $payload = json_encode([
        'source_form_id'  => $formMsgId,
        'property_id'     => $propertyId,
        'property_title'  => $propTitle,
        'slot_confirmed'  => $slotPicked,
        'access_method'   => $accessMethod,
        'access_detail'   => $accessDetail,
        'consent_given'   => true,
    ]);

    $methodLabel = match($accessMethod) {
        'landlord_present' => 'landlord present',
        'lockbox_code'     => 'lockbox',
        'other'            => 'other arrangement',
        default            => $accessMethod,
    };
    $noticeBody = "Inspection confirmed: {$slotPicked}, access: {$methodLabel}.";
    $bodyText   = "Inspection confirmed for {$slotPicked} (access: {$methodLabel})";

    try {
        $pdo->beginTransaction();

        // Save confirmed schedule on property
        $pdo->prepare("
            UPDATE properties
               SET inspection_scheduled_at = ?,
                   inspection_access_method = ?,
                   inspection_access_detail = ?
             WHERE id = ?
        ")->execute([$slotPicked, $accessMethod, $accessDetail, $propertyId]);

        // Post the response message
        $pdo->prepare("
            INSERT INTO messages (conversation_id, sender_id, body, message_type, metadata, sent_at)
            VALUES (?, ?, ?, 'inspection_schedule_response', ?, NOW())
        ")->execute([$convId, $userId, $bodyText, $payload]);

        // Post system notice for both parties
        $pdo->prepare("
            INSERT INTO messages (conversation_id, sender_id, body, message_type, sent_at)
            VALUES (?, ?, ?, 'system_notice', NOW())
        ")->execute([$convId, $userId, $noticeBody]);

        $pdo->prepare("UPDATE conversations SET last_message_at = NOW(), last_message_preview = ?, last_sender_id = ? WHERE id = ?")
            ->execute([substr($noticeBody, 0, 120), $userId, $convId]);

        // Notify agent
        if (function_exists('notify')) {
            notify(
                $agentId,
                'inspection_confirmed',
                'Inspection time confirmed',
                "The landlord confirmed the inspection for \"{$propTitle}\" on {$slotPicked}.",
                "/rentbridge/chat/conversation.php?id={$convId}"
            );
        }

        $pdo->commit();
        echo json_encode(['ok' => true, 'notice' => $noticeBody]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }

} else {
    // Reschedule — just post a reschedule message and agent will resend
    $payload = json_encode([
        'source_form_id' => $formMsgId,
        'property_id'    => $propertyId,
        'property_title' => $propTitle,
        'reason'         => $accessDetail, // 'access_detail' reused as reason in reschedule
    ]);
    $bodyText = 'Landlord requested a reschedule. Please propose new times.';

    try {
        $pdo->beginTransaction();

        $pdo->prepare("
            INSERT INTO messages (conversation_id, sender_id, body, message_type, metadata, sent_at)
            VALUES (?, ?, ?, 'inspection_schedule_reschedule', ?, NOW())
        ")->execute([$convId, $userId, $bodyText, $payload]);

        $pdo->prepare("UPDATE conversations SET last_message_at = NOW(), last_message_preview = ?, last_sender_id = ? WHERE id = ?")
            ->execute([substr($bodyText, 0, 120), $userId, $convId]);

        // Notify agent
        if (function_exists('notify')) {
            notify(
                $agentId,
                'inspection_reschedule',
                'Landlord requested a reschedule',
                "The landlord requested a reschedule for \"{$propTitle}\". Please propose new inspection times.",
                "/rentbridge/chat/conversation.php?id={$convId}"
            );
        }

        $pdo->commit();
        echo json_encode(['ok' => true]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
}
