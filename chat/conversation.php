<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/chat.php';
require_login();

$conversationId = (int)($_GET['id'] ?? 0);
if ($conversationId <= 0) {
    http_response_code(400);
    die('Invalid conversation ID.');
}

$userId = current_user_id();
$currentRole = current_role();

$convo = get_conversation_for_user($conversationId, $userId, $currentRole);
if (!$convo) {
    http_response_code(403);
    die('You are not in this conversation.');
}

$isGroupChat = ($convo['context_type'] === 'housemate_group');

// For group chats load all participants; for regular chats load the other party
$groupParticipants = [];
if ($isGroupChat) {
    require_once __DIR__ . '/../includes/partners.php';
    $groupParticipants = get_group_participants($conversationId);
    $other = ['name' => 'Housemate Group', 'primary_role' => 'student'];
    $otherUserId = 0;
} else {
    $otherUserId = ((int)$convo['user_a'] === $userId) ? (int)$convo['user_b'] : (int)$convo['user_a'];
    $other = chat_get_user_display($otherUserId);
}

// Fetch property details if conversation tied to one
$property = null;
if (!empty($convo['property_id'])) {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT p.id, p.title, p.city, p.monthly_rent, p.property_type,
               (SELECT image_path FROM property_images
                 WHERE property_id = p.id
                 ORDER BY is_primary DESC, id LIMIT 1) AS image_path
          FROM properties p
         WHERE p.id = ?
    ");
    $stmt->execute([(int)$convo['property_id']]);
    $property = $stmt->fetch();
}

// Mark unread as read
mark_messages_read($conversationId, $userId);

// Load history
$messages = get_messages($conversationId, null, 200);
$lastMessageId = !empty($messages) ? max(array_column($messages, 'id')) : 0;

$isLocked = (int)($convo['is_locked'] ?? 0) === 1;

// Quick replies — different per context
if ($isGroupChat) {
    $quickReplies = [
        'Hi everyone!',
        'When can we meet to discuss?',
        'Sounds good to me.',
        'Let me check and get back.',
    ];
} else {
    $quickReplies = match ($currentRole) {
        'student' => [
            'Hi, is this still available?',
            'Can I view it?',
            'What\'s included in the rent?',
            'Is the deposit negotiable?',
        ],
        'landlord' => [
            'Yes, still available.',
            'When would you like to view?',
            'Rent includes WiFi and water.',
            'Let me check and get back to you.',
        ],
        'agent' => [
            'I can arrange an inspection.',
            'Let me schedule a viewing.',
            'Inspection report is ready.',
            'Please proceed to sign the contract.',
        ],
        default => [
            'Hello',
            'Thank you',
            'Can we discuss?',
        ],
    };
}

$pageTitle     = $isGroupChat ? 'Housemate Group Chat' : ('Chat with ' . $other['name']);
$showPageTitle = false;
$activeNav = 'chat';

ob_start();
?>

<a href="/rentbridge/chat.php" class="small text-secondary text-decoration-none mb-2 d-inline-block">
    <i class="bi bi-arrow-left"></i> Back to messages
</a>

<div class="chat-shell">

    <!-- HEADER -->
    <div class="chat-header">
        <?php if ($isGroupChat): ?>
            <!-- Group chat header -->
            <div class="d-flex gap-3 align-items-center">
                <div style="width:44px; height:44px; border-radius:50%; background:#E4F2EA;
                            color:#0F2C52; display:inline-flex; align-items:center;
                            justify-content:center; font-size:1.2rem; font-weight:700; flex-shrink:0;">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div class="flex-grow-1">
                    <strong>Housemate Group</strong>
                    <?php if ($property): ?>
                        <div class="small text-secondary">
                            <?= e($property['title']) ?> · <?= e($property['city']) ?>
                        </div>
                    <?php endif; ?>
                    <div class="small text-secondary mt-1">
                        <?php foreach ($groupParticipants as $gp): ?>
                            <span class="badge bg-light text-dark me-1" data-username="<?= e($gp['preferred_name'] ?: $gp['full_name']) ?>">
                                @<?= e($gp['preferred_name'] ?: $gp['full_name']) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php elseif ($property): ?>
            <a href="/rentbridge/property.php?id=<?= (int)$property['id'] ?>"
               class="d-flex gap-3 align-items-start text-decoration-none text-dark">
                <div style="width:60px; height:60px; border-radius:8px; overflow:hidden; flex-shrink:0;
                            background: linear-gradient(135deg,#E6ECF4,#E4F2EA);">
                    <?php if (!empty($property['image_path'])): ?>
                        <img src="/rentbridge/<?= e($property['image_path']) ?>"
                             style="width:100%; height:100%; object-fit:cover;" alt="">
                    <?php endif; ?>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center gap-2">
                        <?php
                        require_once __DIR__ . '/../includes/avatar.php';
                        $_otherAvatar = get_avatar_path((int)$otherUserId, $other['primary_role']);
                        ?>
                        <?php render_avatar($_otherAvatar, $other['name'], 44); ?>
                        <strong class="small"><?= e($other['name']) ?></strong>
                        <span class="badge bg-light text-dark small fw-normal">
                            <?= e(ucfirst($other['primary_role'])) ?>
                        </span>
                    </div>
                    <div class="mt-1 fw-semibold"><?= e($property['title']) ?></div>
                    <div class="small">
                        <span style="color:#C62828; font-weight:600;">
                            RM <?= number_format((float)$property['monthly_rent']) ?>
                        </span>
                        <span class="text-secondary">
                            per month · <?= e($property['city']) ?>
                        </span>
                    </div>
                </div>
                <div class="d-flex flex-column align-items-end gap-2 flex-shrink-0">
                    <div class="text-secondary small">Listing ID:<?= (int)$property['id'] ?></div>
                    <?php if (!$isGroupChat): ?>
                    <button type="button"
                            class="btn btn-sm btn-outline-danger"
                            style="font-size:.75rem; padding:2px 8px;"
                            onclick="event.stopPropagation(); event.preventDefault(); bootstrap.Modal.getOrCreateInstance(document.getElementById('reportModal')).show()">
                        <i class="bi bi-flag me-1"></i>Report
                    </button>
                    <?php endif; ?>
                </div>
            </a>
        <?php else: ?>
            <div class="d-flex gap-3 align-items-center">
                <div style="width:44px; height:44px; border-radius:50%;
                            background: <?php
                                echo match($other['primary_role']) {
                                    'student'=>'#E4F2EA','landlord'=>'#FFE8C7',
                                    'agent'=>'#FFF4D6','admin'=>'#F8D7DA',
                                    default=>'#E2E2E2',
                                };
                            ?>;
                            display:inline-flex; align-items:center; justify-content:center;
                            font-weight:600; color:#0F2C52;">
                    <?= strtoupper(substr($other['name'], 0, 1)) ?>
                </div>
                <div class="flex-grow-1">
                    <strong><?= e($other['name']) ?></strong>
                    <span class="badge bg-light text-dark ms-1">
                        <?= e(ucfirst($other['primary_role'])) ?>
                    </span>
                </div>
                <?php if (!$isGroupChat): ?>
                <button type="button"
                        class="btn btn-sm btn-outline-danger flex-shrink-0"
                        style="font-size:.75rem; padding:2px 8px;"
                        onclick="bootstrap.Modal.getOrCreateInstance(document.getElementById('reportModal')).show()">
                    <i class="bi bi-flag me-1"></i>Report
                </button>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($isLocked): ?>
        <div class="alert alert-secondary m-3 mb-0 small">
            <i class="bi bi-lock-fill"></i>
            This conversation is closed.
            <?php if (!empty($convo['locked_reason'])): ?>
                <?= e($convo['locked_reason']) ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

<?php
$landlordId = current_user_id();

// Find the conversation array — try common names
$convData = $conversation ?? $convo ?? $conv ?? $thread ?? [];

// Try multiple possible key names for property
$propId  = $convData['property_id']  ?? null;
$otherId = $other['id']              ?? null;  // Your key is 'id'
$otherRole = $other['primary_role']  ?? '';

$showContractPrepBtn = false;
if (
    current_role() === 'landlord' &&
    !empty($propId) &&
    !empty($otherId) &&
    $otherRole === 'student'
) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
          FROM messages m
          JOIN conversations c ON c.id = m.conversation_id
         WHERE c.property_id = ?
           AND c.context_type = 'contract_prep'
           AND m.message_type = 'contract_prep_request'
           AND m.sender_id = ?
    ");
    $stmt->execute([(int)$propId, $landlordId]);
    $alreadyRequested = (int)$stmt->fetchColumn() > 0;
    $showContractPrepBtn = !$alreadyRequested;
}
?>
    <?php if ($showContractPrepBtn): ?>
        <div class="contract-prep-bar p-3 mb-2"
            style="background:#FFF4D6; border:1px solid #D4A017; border-radius:10px;">
            <div class="d-flex gap-3 align-items-start">
                <i class="bi bi-exclamation-triangle-fill text-warning fs-4"></i>
                <div class="flex-grow-1">
                    <strong>Ready to proceed with this rental?</strong>
                    <p class="small text-secondary mb-2">
                        Only click below when you and the student have <strong>agreed</strong>
                        to move forward. This will notify the assigned agent to prepare
                        the tenancy contract. This step is final — once initiated, the
                        agent will start collecting tenant details for the legal paperwork.
                    </p>
                    <button type="button" id="contractPrepBtn"
                            class="btn btn-warning text-dark fw-semibold"
                            data-conv-id="<?= (int)($convData['id'] ?? 0) ?>"
                            data-property-id="<?= (int)$propId ?>"
                            data-student-id="<?= (int)$otherId ?>">
                        <i class="bi bi-file-earmark-text me-1"></i>
                        Request contract preparation
                    </button>                
                    </div>
            </div>
        </div>
    <?php endif; ?>
<?php
// Agent-led: agent can send tenant info form directly to student
$showAgentSendFormBtn = false;
if (
    current_role() === 'agent' &&
    !empty($propId) &&
    !empty($otherId) &&
    $otherRole === 'student'
) {
    // Verify this is an agent-led property AND I am the assigned agent
    $stmt = $pdo->prepare("
        SELECT viewing_mode, assigned_agent_id, agent_status 
          FROM properties 
         WHERE id = ?
    ");
    $stmt->execute([$propId]);
    $propRow = $stmt->fetch();
    
    if (
        $propRow &&
        in_array($propRow['viewing_mode'], ['agent_led', 'either'], true) &&
        (int)$propRow['assigned_agent_id'] === current_user_id() &&
        $propRow['agent_status'] === 'accepted'
    ) {
        // Check: no unanswered form already pending for this student
        $stmt = $pdo->prepare("
            SELECT m.id
              FROM messages m
             WHERE m.conversation_id = ? 
               AND m.message_type = 'tenant_info_form'
               AND JSON_EXTRACT(m.metadata, '$.student_id') = ?
               AND NOT EXISTS (
                   SELECT 1 FROM messages r
                    WHERE r.conversation_id = m.conversation_id
                      AND r.message_type = 'tenant_info_response'
                      AND JSON_EXTRACT(r.metadata, '$.source_form_id') = m.id
               )
             LIMIT 1
        ");
        $stmt->execute([(int)$convData['id'], $otherId]);
        $pendingForm = $stmt->fetchColumn();
        $showAgentSendFormBtn = !$pendingForm;
    }
}
?>

<?php if ($showAgentSendFormBtn): ?>
    <div class="agent-send-form-bar p-3 mb-2"
         style="background:#E4F2EA; border:1px solid #2E8B57; border-radius:10px;">
        <div class="d-flex gap-3 align-items-start">
            <i class="bi bi-clipboard-data fs-4 text-success"></i>
            <div class="flex-grow-1">
                <strong>Ready to start tenant paperwork?</strong>
                <p class="small text-secondary mb-2">
                    When you've agreed with the student to proceed, send them the
                    tenant info form. The student will fill their details and
                    co-tenants, then you generate the contract PDF.
                </p>
                <button type="button" id="agentSendFormBtn"
                        class="btn btn-success fw-semibold"
                        data-conv-id="<?= (int)$convData['id'] ?>"
                        data-property-id="<?= (int)$propId ?>"
                        data-student-id="<?= (int)$otherId ?>">
                    <i class="bi bi-send me-1"></i>
                    Send tenant info form to student
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
// Show inspection schedule button if agent↔landlord for an 'inspecting' property
$showInspectionScheduleBtn = false;
$inspectionPropId = null;
if (
    current_role() === 'agent' &&
    !empty($propId) &&
    !empty($otherId) &&
    $otherRole === 'landlord'
) {
    $stmt = $pdo->prepare("SELECT agent_status, assigned_agent_id FROM properties WHERE id = ?");
    $stmt->execute([$propId]);
    $inspRow = $stmt->fetch();
    if ($inspRow && $inspRow['agent_status'] === 'inspecting' && (int)$inspRow['assigned_agent_id'] === current_user_id()) {
        // Check no pending unanswered schedule request
        $stmt = $pdo->prepare("
            SELECT m.id FROM messages m
             WHERE m.conversation_id = ?
               AND m.message_type = 'inspection_schedule_request'
               AND NOT EXISTS (
                   SELECT 1 FROM messages r
                    WHERE r.conversation_id = m.conversation_id
                      AND r.message_type IN ('inspection_schedule_response','inspection_schedule_reschedule')
                      AND r.sent_at > m.sent_at
               )
             ORDER BY m.id DESC LIMIT 1
        ");
        $stmt->execute([(int)$convData['id']]);
        $showInspectionScheduleBtn = !$stmt->fetchColumn();
        $inspectionPropId = (int)$propId;
    }
}
?>
<?php if ($showInspectionScheduleBtn): ?>
    <div class="p-3 mb-2" style="background:#EAF6FB; border:1px solid #0dcaf0; border-radius:10px;">
        <div class="d-flex gap-3 align-items-start">
            <i class="bi bi-calendar-plus fs-4 text-info"></i>
            <div class="flex-grow-1">
                <strong>Schedule inspection visit</strong>
                <p class="small text-secondary mb-2">
                    Propose one or more date/time options for the landlord to pick from.
                </p>
                <button type="button" class="btn btn-info btn-sm text-white"
                        data-bs-toggle="modal"
                        data-bs-target="#sendInspectionScheduleModal"
                        data-conv-id="<?= (int)($convData['id'] ?? 0) ?>"
                        data-prop-id="<?= $inspectionPropId ?>">
                    <i class="bi bi-calendar2-plus me-1"></i> Propose inspection times
                </button>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- MESSAGES AREA -->
    <div class="chat-body" id="chatBody">
        <?php if (empty($messages)): ?>
            <div class="text-center text-secondary small py-4 my-auto">
                <i class="bi bi-shield-check d-block mb-2" style="font-size: 2rem; opacity:0.3;"></i>
                Start chatting with your contact.<br>
                Remember to keep your phone number and banking info safe.
            </div>
        <?php endif; ?>

        <?php
        require_once __DIR__ . '/../includes/co_tenants.php';
        foreach ($messages as $msg):
            $isMine  = (int)$msg['sender_id'] === $userId;
            $msgType = $msg['message_type'] ?? 'text';
            $meta    = !empty($msg['metadata']) ? json_decode($msg['metadata'], true) : null;
        ?>

            <?php if ($msgType === 'system_notice'): ?>
                <!-- System notice: centered italic badge -->
                <div class="text-center my-3">
                    <span class="badge bg-light text-secondary border px-3 py-2"
                          style="font-style: italic; font-weight: normal;">
                        <i class="bi bi-info-circle me-1"></i>
                        <?= e($msg['body']) ?>
                    </span>
                </div>

            <?php elseif ($msgType === 'inspection_schedule_request'):
                // Agent proposed inspection times — landlord picks one
                $propId    = (int)($meta['property_id'] ?? 0);
                $propTitle = $meta['property_title'] ?? 'this property';
                $slots     = $meta['slots'] ?? '';
                $note      = $meta['note'] ?? '';
                // Has it already been answered?
                $stmt = $pdo->prepare("
                    SELECT id FROM messages
                     WHERE conversation_id = ?
                       AND message_type IN ('inspection_schedule_response','inspection_schedule_reschedule')
                       AND JSON_EXTRACT(metadata,'$.source_form_id') = ?
                     LIMIT 1
                ");
                $stmt->execute([$msg['conversation_id'], $msg['id']]);
                $answered = (bool)$stmt->fetchColumn();
            ?>
                <div class="my-3 d-flex justify-content-center">
                    <div class="card border-info" style="max-width:520px; background:#EAF6FB; width:100%;">
                        <div class="card-body">
                            <div class="d-flex gap-2 align-items-start mb-2">
                                <i class="bi bi-calendar-check fs-4 text-info"></i>
                                <div>
                                    <h6 class="mb-0">Inspection schedule request</h6>
                                    <small class="text-secondary">Property: <strong><?= e($propTitle) ?></strong></small>
                                </div>
                            </div>
                            <div class="mb-2">
                                <strong class="small">Proposed times:</strong>
                                <p class="small mb-0" style="white-space:pre-line;"><?= nl2br(e($slots)) ?></p>
                            </div>
                            <?php if ($note): ?>
                                <div class="small text-secondary mb-2"><?= nl2br(e($note)) ?></div>
                            <?php endif; ?>

                            <?php if ($answered): ?>
                                <span class="badge bg-success"><i class="bi bi-check2-circle"></i> Responded</span>
                            <?php elseif (!$isMine && $currentRole === 'landlord'): ?>
                                <button type="button" class="btn btn-info btn-sm text-white"
                                        data-bs-toggle="modal"
                                        data-bs-target="#inspectionScheduleModal"
                                        data-form-msg-id="<?= (int)$msg['id'] ?>"
                                        data-conv-id="<?= (int)$msg['conversation_id'] ?>"
                                        data-slots="<?= e($slots) ?>"
                                        data-prop-id="<?= $propId ?>"
                                        data-prop-title="<?= e($propTitle) ?>">
                                    <i class="bi bi-calendar2-check me-1"></i> Confirm or reschedule
                                </button>
                            <?php else: ?>
                                <span class="badge bg-secondary">Awaiting landlord response</span>
                            <?php endif; ?>

                            <div class="chat-meta mt-2"><?= e(date('H:i', strtotime($msg['sent_at']))) ?></div>
                        </div>
                    </div>
                </div>

            <?php elseif ($msgType === 'inspection_schedule_response'):
                $slotPicked    = $meta['slot_confirmed'] ?? '—';
                $accessMethod  = $meta['access_method'] ?? '—';
                $accessDetail  = $meta['access_detail'] ?? '';
                $methodLabel   = match($accessMethod) {
                    'landlord_present' => 'Landlord will be present',
                    'lockbox_code'     => 'Lockbox code',
                    'other'            => 'Other',
                    default            => $accessMethod,
                };
            ?>
                <div class="my-3 d-flex justify-content-center">
                    <div class="card border-success" style="max-width:520px; background:#E4F2EA; width:100%;">
                        <div class="card-body">
                            <div class="d-flex gap-2 align-items-start mb-2">
                                <i class="bi bi-check-circle-fill fs-4 text-success"></i>
                                <div>
                                    <h6 class="mb-0">Inspection confirmed</h6>
                                    <small class="text-secondary">Property: <strong><?= e($meta['property_title'] ?? '—') ?></strong></small>
                                </div>
                            </div>
                            <div class="row g-2 small">
                                <div class="col-6">
                                    <div class="text-secondary">Date / time</div>
                                    <strong><?= e($slotPicked) ?></strong>
                                </div>
                                <div class="col-6">
                                    <div class="text-secondary">Access method</div>
                                    <strong><?= e($methodLabel) ?></strong>
                                </div>
                                <?php if ($accessDetail): ?>
                                <div class="col-12">
                                    <div class="text-secondary">Details / code</div>
                                    <code class="small"><?= e($accessDetail) ?></code>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php if ($currentRole === 'agent'): ?>
                                <div class="mt-2">
                                    <a href="/rentbridge/agent/property_review.php?id=<?= (int)($meta['property_id'] ?? 0) ?>"
                                       class="btn btn-sm btn-outline-success">
                                        <i class="bi bi-house-check me-1"></i> Go to property review
                                    </a>
                                </div>
                            <?php endif; ?>
                            <div class="chat-meta mt-2"><?= e(date('H:i', strtotime($msg['sent_at']))) ?></div>
                        </div>
                    </div>
                </div>

            <?php elseif ($msgType === 'inspection_schedule_reschedule'): ?>
                <div class="my-3 d-flex justify-content-center">
                    <div class="card border-warning" style="max-width:480px; background:#FFF4D6; width:100%;">
                        <div class="card-body">
                            <div class="d-flex gap-2 align-items-start">
                                <i class="bi bi-calendar-x fs-4 text-warning"></i>
                                <div>
                                    <h6 class="mb-0">Reschedule requested</h6>
                                    <p class="small text-secondary mb-1"><?= e($msg['body']) ?></p>
                                    <?php if (!empty($meta['reason'])): ?>
                                        <p class="small mb-0">Reason: <?= e($meta['reason']) ?></p>
                                    <?php endif; ?>
                                    <?php if ($currentRole === 'agent'): ?>
                                        <button type="button" class="btn btn-sm btn-warning mt-2"
                                                data-bs-toggle="modal"
                                                data-bs-target="#sendInspectionScheduleModal"
                                                data-conv-id="<?= (int)$msg['conversation_id'] ?>"
                                                data-prop-id="<?= (int)($meta['property_id'] ?? 0) ?>">
                                            <i class="bi bi-calendar-plus me-1"></i> Propose new times
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="chat-meta mt-2"><?= e(date('H:i', strtotime($msg['sent_at']))) ?></div>
                        </div>
                    </div>
                </div>

            <?php elseif ($msgType === 'contract_prep_request'): ?>
                <!-- Contract preparation request card -->
                <div class="my-3 d-flex justify-content-center">
                    <div class="card border-warning" style="max-width: 500px; background: #FFF4D6;">
                        <div class="card-body">
                            <div class="d-flex gap-2 align-items-start mb-2">
                                <i class="bi bi-file-earmark-text fs-4 text-warning"></i>
                                <div>
                                    <h6 class="mb-0">Contract preparation requested</h6>
                                    <small class="text-secondary">
                                        For property: <strong><?= e($meta['property_title'] ?? '—') ?></strong>
                                    </small>
                                </div>
                            </div>
                            <p class="small text-secondary mb-3">
                                The landlord has confirmed agreement with the tenant.
                                Please send the tenant info form to begin contract preparation.
                            </p>
                            <?php if (current_role() === 'agent'): ?>
                                <button type="button" class="btn btn-warning btn-sm send-tenant-form-btn"
                                        data-message-id="<?= (int)$msg['id'] ?>"
                                        data-conv-id="<?= (int)$convo['id'] ?>"
                                        data-property-id="<?= (int)($meta['property_id'] ?? 0) ?>"
                                        data-student-id="<?= (int)($meta['student_id'] ?? 0) ?>">
                                    <i class="bi bi-send me-1"></i> Send tenant info form
                                </button>
                            <?php else: ?>
                                <span class="badge bg-secondary">Awaiting agent response</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

            <?php elseif ($msgType === 'co_tenant_form'):
                $bookingIdMsg = (int)($meta['booking_id'] ?? 0);
                $isReceiver = !$isMine;
                $existingCoTenants = $bookingIdMsg > 0 ? get_co_tenants($bookingIdMsg) : [];
                $hasSubmitted = count($existingCoTenants) > 1;
            ?>
                <div class="chat-message <?= $isMine ? 'mine' : 'theirs' ?>"
                     style="background:#FFF4D6; border:1px solid #D4A017; color:#0F2C52; max-width:480px;"
                     data-msg-id="<?= (int)$msg['id'] ?>">
                    <div class="d-flex gap-2 align-items-start mb-2">
                        <i class="bi bi-clipboard-data" style="font-size:1.5rem; color:#D4A017;"></i>
                        <div>
                            <strong>Co-tenant details requested</strong>
                            <div class="small text-secondary">
                                Property: <?= e($meta['property_title'] ?? 'this property') ?>
                            </div>
                        </div>
                    </div>
                    <p class="small mb-3">
                        The agent needs the names and IC numbers of everyone who
                        will rent with you. This appears on the contract.
                    </p>

                    <?php if ($isReceiver && !$hasSubmitted): ?>
                        <button type="button" class="btn btn-warning btn-sm"
                                data-bs-toggle="modal"
                                data-bs-target="#coTenantFormModal"
                                data-booking-id="<?= $bookingIdMsg ?>">
                            <i class="bi bi-pencil-square me-1"></i> Fill in co-tenant details
                        </button>
                    <?php elseif ($hasSubmitted): ?>
                        <span class="badge bg-success">
                            <i class="bi bi-check2-circle"></i>
                            <?= count($existingCoTenants) - 1 ?> co-tenant<?= count($existingCoTenants) === 2 ? '' : 's' ?> submitted
                        </span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Awaiting student response</span>
                    <?php endif; ?>

                    <div class="chat-meta">
                        <?= e(date('H:i', strtotime($msg['sent_at']))) ?>
                    </div>
                </div>

                <?php elseif ($msgType === 'tenant_info_form'): 
                    $recipientRole = $meta['recipient_role'] ?? 'landlord';
                    $isReceiver = !$isMine && current_role() === $recipientRole;
                    $hasResponded = false;
                    // Check if landlord already submitted this form
                    $stmt = $pdo->prepare("
                        SELECT COUNT(*) FROM messages
                        WHERE conversation_id = ?
                        AND message_type = 'tenant_info_response'
                        AND JSON_EXTRACT(metadata, '$.source_form_id') = ?
                    ");
                    $stmt->execute([$msg['conversation_id'], $msg['id']]);
                    $hasResponded = (int)$stmt->fetchColumn() > 0;
                ?>
                    <div class="my-3 d-flex justify-content-center">
                        <div class="card border-warning" style="max-width: 520px; background: #FFF4D6;">
                            <div class="card-body">
                                <div class="d-flex gap-2 align-items-start mb-2">
                                    <i class="bi bi-clipboard-data fs-4 text-warning"></i>
                                    <div>
                                        <h6 class="mb-0">Tenant info form</h6>
                                        <small class="text-secondary">
                                            Property: <strong><?= e($meta['property_title'] ?? '—') ?></strong>
                                        </small>
                                    </div>
                                </div>
                                <p class="small text-secondary mb-3">
                                    Please fill in the tenant (and any co-tenants') details.
                                    This information appears on the legal contract.
                                </p>

                                <?php if ($hasResponded): ?>
                                    <span class="badge bg-success">
                                        <i class="bi bi-check2-circle"></i> Form submitted
                                    </span>
                                <?php elseif ($isReceiver && current_role() === 'student'): ?>
                                    <a href="/rentbridge/student/tenant_form.php?form_id=<?= (int)$msg['id'] ?>&conv_id=<?= (int)$msg['conversation_id'] ?>&property_id=<?= (int)($meta['property_id'] ?? 0) ?>"
                                       class="btn btn-warning btn-sm">
                                        <i class="bi bi-pencil-square me-1"></i> Fill in tenant details
                                    </a>
                                <?php elseif ($isReceiver): ?>
                                    <button type="button" class="btn btn-warning btn-sm fill-tenant-form-btn"
                                            data-bs-toggle="modal" data-bs-target="#tenantInfoModal"
                                            data-form-id="<?= (int)$msg['id'] ?>"
                                            data-property-id="<?= (int)($meta['property_id'] ?? 0) ?>"
                                            data-student-id="<?= (int)($meta['student_id'] ?? 0) ?>"
                                            data-prefill='<?= e(json_encode($meta['prefill'] ?? [])) ?>'
                                            data-conv-id="<?= (int)$msg['conversation_id'] ?>">
                                        <i class="bi bi-pencil-square me-1"></i> Fill in tenant details
                                    </button>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Awaiting student</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php elseif ($msgType === 'tenant_info_response'): 
                        // Fetch current booking status to decide which buttons to show
                        $bookingStatus = null;
                        if (!empty($meta['booking_id'])) {
                            $stmt = $pdo->prepare("SELECT status FROM bookings WHERE id = ?");
                            $stmt->execute([(int)$meta['booking_id']]);
                            $bookingStatus = $stmt->fetchColumn();
                        }
                    ?>
                        <div class="my-3 d-flex justify-content-center">
                            <div class="card border-success" style="max-width: 500px; background: #E4F2EA;">
                                <div class="card-body">
                                    <div class="d-flex gap-2 align-items-start mb-2">
                                        <i class="bi bi-check-circle-fill fs-4 text-success"></i>
                                        <div>
                                            <h6 class="mb-0">Tenant info submitted</h6>
                                            <small class="text-secondary"><?= e($msg['body']) ?></small>
                                        </div>
                                    </div>
                                    
                                    <?php if (current_role() === 'agent' && !empty($meta['booking_id'])): ?>
                                        <?php if ($bookingStatus === 'active'): ?>
                                            <span class="badge bg-success">
                                                <i class="bi bi-check2-all"></i> Tenancy active
                                            </span>
                                        <?php elseif ($bookingStatus === 'contract_pending'): ?>
                                            <div class="d-flex gap-2 flex-wrap">
                                                <a href="/rentbridge/agent/upload_signed_contract.php?booking_id=<?= (int)$meta['booking_id'] ?>"
                                                class="btn btn-success btn-sm">
                                                    <i class="bi bi-upload me-1"></i> Upload signed contract
                                                </a>
                                                <a href="/rentbridge/agent/generate_contract.php?booking_id=<?= (int)$meta['booking_id'] ?>"
                                                class="btn btn-outline-success btn-sm">
                                                    <i class="bi bi-arrow-clockwise me-1"></i> Regenerate
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <a href="/rentbridge/agent/generate_contract.php?booking_id=<?= (int)$meta['booking_id'] ?>"
                                            class="btn btn-success btn-sm">
                                                <i class="bi bi-file-earmark-pdf me-1"></i> Generate contract
                                            </a>
                                        <?php endif; ?>
                                    <?php elseif ($bookingStatus === 'active'): ?>
                                        <span class="badge bg-success">
                                            <i class="bi bi-check2-all"></i> Tenancy active
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-success">Ready for contract</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>            
                        <?php else: ?>
                <!-- Regular text bubble (default) -->
                <div class="chat-message <?= $isMine ? 'mine' : 'theirs' ?>"
                     data-msg-id="<?= (int)$msg['id'] ?>">
                    <?= nl2br(e($msg['body'])) ?>
                    <div class="chat-meta">
                        <?= e(date('H:i', strtotime($msg['sent_at']))) ?>
                    </div>
                </div>
            <?php endif; ?>

        <?php endforeach; ?>
    </div>

    <!-- QUICK REPLIES -->
    <?php if (!$isLocked && !empty($quickReplies)): ?>
        <div class="chat-quick-replies" id="quickReplies">
            <?php foreach ($quickReplies as $reply): ?>
                <button type="button" class="quick-reply-chip"
                        data-reply="<?= e($reply) ?>">
                    <?= e($reply) ?>
                </button>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- INPUT -->
    <?php if (!$isLocked): ?>
        <!-- Plus-menu popover (role-specific actions) -->
        <form class="chat-input d-flex gap-2 align-items-center position-relative" id="chatForm">
            <?= csrf_field() ?>
            <input type="hidden" name="conversation_id" value="<?= (int)$conversationId ?>">

            <!-- Plus-menu popover — anchored to the form (position-relative) -->
            <div class="chat-plus-menu d-none" id="chatPlusMenu"
                 style="position:absolute; bottom:calc(100% + 8px); left:0; z-index:200;
                        background:white; border:1px solid rgba(15,44,82,0.12);
                        border-radius:12px; box-shadow:0 4px 20px rgba(0,0,0,0.12);
                        min-width:220px; overflow:hidden;">
                <label class="chat-plus-item d-flex gap-2 align-items-center px-3 py-2"
                       style="cursor:pointer;" for="chatImageInput">
                    <i class="bi bi-image text-secondary"></i>
                    <span class="small">Send photo</span>
                    <input type="file" id="chatImageInput" name="chat_image"
                           accept="image/*" class="d-none">
                </label>
                <label class="chat-plus-item d-flex gap-2 align-items-center px-3 py-2"
                       style="cursor:pointer;" for="chatDocInput">
                    <i class="bi bi-file-earmark text-secondary"></i>
                    <span class="small">Send document</span>
                    <input type="file" id="chatDocInput" name="chat_doc"
                           accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" class="d-none">
                </label>
                <?php if ($currentRole === 'agent' && $showInspectionScheduleBtn): ?>
                <button type="button"
                        class="chat-plus-item d-flex gap-2 align-items-center px-3 py-2 w-100 border-0 bg-transparent text-start"
                        data-bs-toggle="modal" data-bs-target="#sendInspectionScheduleModal"
                        data-conv-id="<?= (int)($convData['id'] ?? 0) ?>"
                        data-prop-id="<?= (int)($inspectionPropId ?? 0) ?>"
                        onclick="document.getElementById('chatPlusMenu').classList.add('d-none')">
                    <i class="bi bi-calendar-plus text-info"></i>
                    <span class="small">Propose inspection time</span>
                </button>
                <?php endif; ?>
                <?php if ($currentRole === 'agent' && $showAgentSendFormBtn): ?>
                <button type="button"
                        class="chat-plus-item d-flex gap-2 align-items-center px-3 py-2 w-100 border-0 bg-transparent text-start"
                        id="agentSendFormBtnPlus"
                        data-conv-id="<?= (int)($convData['id'] ?? 0) ?>"
                        data-property-id="<?= (int)$propId ?>"
                        data-student-id="<?= (int)$otherId ?>"
                        onclick="document.getElementById('chatPlusMenu').classList.add('d-none')">
                    <i class="bi bi-clipboard-data text-success"></i>
                    <span class="small">Send tenant info form</span>
                </button>
                <?php endif; ?>
                <?php if ($currentRole === 'landlord' && $showContractPrepBtn): ?>
                <button type="button"
                        class="chat-plus-item d-flex gap-2 align-items-center px-3 py-2 w-100 border-0 bg-transparent text-start"
                        id="contractPrepBtnPlus"
                        data-conv-id="<?= (int)($convData['id'] ?? 0) ?>"
                        data-property-id="<?= (int)$propId ?>"
                        data-student-id="<?= (int)$otherId ?>"
                        onclick="document.getElementById('chatPlusMenu').classList.add('d-none')">
                    <i class="bi bi-file-earmark-text text-warning"></i>
                    <span class="small">Request contract prep</span>
                </button>
                <?php endif; ?>
            </div>
            <button type="button" id="chatPlusBtn"
                    class="btn btn-outline-secondary p-0 d-flex align-items-center justify-content-center"
                    style="width:36px; height:36px; border-radius:50%; flex-shrink:0; border-color:rgba(15,44,82,0.2);"
                    title="Attachments &amp; forms">
                <i class="bi bi-plus-lg" style="font-size:1.1rem;"></i>
            </button>
            <textarea name="body" class="form-control" rows="1"
                      placeholder="Type a message..."
                      maxlength="2000" id="chatTextarea"></textarea>
            <button type="submit" class="btn btn-primary px-4" id="chatSendBtn">
                Send
            </button>
        </form>
    <?php else: ?>
        <div class="chat-input text-center text-secondary small py-3">
            <i class="bi bi-lock-fill"></i> Sending disabled — conversation closed.
        </div>
    <?php endif; ?>

</div>

<!-- AGENT: Send inspection schedule request modal -->
<div class="modal fade" id="sendInspectionScheduleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form id="inspectionScheduleForm" class="modal-content">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="conversation_id" id="iss_conv_id">
            <input type="hidden" name="property_id" id="iss_prop_id">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-calendar-plus text-info me-2"></i>Propose inspection time</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <label class="form-label fw-semibold">Proposed date/time slot(s) <small class="text-danger">*</small></label>
                <textarea name="slots" class="form-control mb-3" rows="4" required
                          placeholder="e.g.&#10;Monday 23 Jun 2026, 10:00 AM&#10;Tuesday 24 Jun 2026, 2:00 PM&#10;Wednesday 25 Jun 2026, 11:00 AM"></textarea>
                <label class="form-label fw-semibold">Note to landlord (optional)</label>
                <textarea name="note" class="form-control" rows="2"
                          placeholder="Any instructions or context for the landlord"></textarea>
                <div id="issError" class="alert alert-danger small d-none mt-3"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-info text-white">
                    <i class="bi bi-send me-1"></i> Send to landlord
                </button>
            </div>
        </form>
    </div>
</div>

<!-- LANDLORD: Respond to inspection schedule request modal -->
<div class="modal fade" id="inspectionScheduleModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <form id="inspectionResponseForm" class="modal-content">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="conversation_id" id="isr_conv_id">
            <input type="hidden" name="form_message_id" id="isr_form_msg_id">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-calendar2-check text-info me-2"></i>Confirm inspection</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Proposed slots</label>
                    <div id="isr_slots" class="p-2 border rounded small bg-light" style="white-space:pre-line;"></div>
                </div>

                <div id="isr_confirm_section">
                    <label class="form-label fw-semibold">Confirmed date/time <small class="text-danger">*</small></label>
                    <input type="text" name="slot_picked" class="form-control mb-3"
                           placeholder="e.g. Monday 23 Jun 2026, 10:00 AM" required>

                    <label class="form-label fw-semibold">Access method <small class="text-danger">*</small></label>
                    <select name="access_method" class="form-select mb-2" id="isr_access_method" required>
                        <option value="">Select...</option>
                        <option value="landlord_present">I will be present</option>
                        <option value="lockbox_code">Lockbox / key box (provide code below)</option>
                        <option value="other">Other arrangement</option>
                    </select>
                    <div id="isr_detail_wrap" class="d-none mb-3">
                        <input type="text" name="access_detail" class="form-control"
                               placeholder="Lockbox code or access instructions">
                    </div>

                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="consent" value="1"
                               id="isr_consent" required>
                        <label class="form-check-label small" for="isr_consent">
                            I authorize the assigned agent to inspect my property at the confirmed time on my behalf.
                        </label>
                    </div>

                    <input type="hidden" name="decision" value="confirm">
                </div>

                <hr>
                <div class="text-center">
                    <button type="button" class="btn btn-sm btn-outline-warning"
                            id="isrRequestReschedule">
                        <i class="bi bi-calendar-x me-1"></i> Request reschedule instead
                    </button>
                </div>
                <div id="isr_reschedule_section" class="d-none mt-3">
                    <label class="form-label fw-semibold small">Reason for reschedule (optional)</label>
                    <input type="text" id="isr_reschedule_reason" class="form-control form-control-sm"
                           placeholder="e.g. I'm not available on those dates. Can we try next week?">
                </div>

                <div id="isrError" class="alert alert-danger small d-none mt-3"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success" id="isrSubmitBtn">
                    <i class="bi bi-check-circle me-1"></i> Confirm inspection
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Co-tenant form modal -->
<div class="modal fade" id="coTenantFormModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-people-fill text-emerald"></i> Co-tenant details
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="coTenantForm" method="POST"
                  action="/rentbridge/chat/submit_tenant_form.php.php">
                <?= csrf_field() ?>
                <input type="hidden" name="booking_id" id="coTenantBookingId">

                <div class="modal-body">
                    <div class="alert alert-light border small mb-3">
                        <strong>How this works:</strong> Add the name and IC number of
                        every person who will rent with you. They don't need a RentBridge
                        account — but the names will appear on the legal contract.
                    </div>

                    <!-- Your own info (primary tenant) -->
                    <h6 class="text-secondary text-uppercase small mt-2 mb-2">Your info (primary tenant)</h6>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label small">Full name</label>
                            <input type="text" class="form-control form-control-sm"
                                   id="primaryName" readonly disabled>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small">
                                Your IC number <small class="text-danger">*</small>
                            </label>
                            <input type="text" class="form-control form-control-sm"
                                   name="primary_ic" id="primaryIc"
                                   placeholder="030303-03-0303" required>
                        </div>
                    </div>

                    <hr>

                    <!-- Additional co-tenants -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="text-secondary text-uppercase small mb-0">Additional co-tenants</h6>
                        <button type="button" class="btn btn-sm btn-outline-primary"
                                id="addCoTenantBtn">
                            <i class="bi bi-plus-lg me-1"></i> Add person
                        </button>
                    </div>
                    <p class="small text-secondary">
                        If you're renting alone, leave this section empty.
                    </p>

                    <div id="coTenantList">
                        <!-- Rows injected by JS -->
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check2 me-1"></i> Submit to agent
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- TENANT INFO FORM MODAL -->
<div class="modal fade" id="tenantInfoModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <form id="tenantInfoForm" class="modal-content">
            <input type="hidden" name="_csrf" value="<?= csrf_token() ?>">
            <input type="hidden" name="form_id" id="tf_form_id">
            <input type="hidden" name="conversation_id" id="tf_conv_id">
            <input type="hidden" name="property_id" id="tf_property_id">
            <input type="hidden" name="student_id" id="tf_student_id">
            
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-clipboard-data me-1 text-warning"></i>
                    Tenant info for contract
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body">
                <p class="small text-secondary">
                    Confirm the primary tenant's details and add any co-tenants. 
                    All listed tenants will appear on the legal contract.
                </p>

                <!-- PRIMARY TENANT -->
                <h6 class="text-uppercase small text-secondary mt-3 mb-2">Primary tenant (student)</h6>
                <div class="row g-2 mb-3 p-3 border rounded" style="background:#FAF8F3;">
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Full name <small class="text-danger">*</small></label>
                        <input type="text" name="tenant_name" id="tf_tenant_name"
                               class="form-control form-control-sm" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">NRIC <small class="text-danger">*</small></label>
                        <input type="text" name="tenant_ic" id="tf_tenant_ic"
                               class="form-control form-control-sm"
                               placeholder="020815-04-1234" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Phone</label>
                        <input type="text" name="tenant_phone" id="tf_tenant_phone"
                               class="form-control form-control-sm">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Email</label>
                        <input type="email" name="tenant_email" id="tf_tenant_email"
                               class="form-control form-control-sm" readonly>
                    </div>
                </div>

                <!-- CO-TENANTS -->
                <h6 class="text-uppercase small text-secondary mb-2">Co-tenants (optional)</h6>
                <p class="small text-secondary mb-2">
                    Add others who will rent with the primary tenant.
                </p>
                <div id="tfCoTenantsList"></div>
                <button type="button" id="tfAddCoTenantBtn" class="btn btn-sm btn-outline-secondary mb-3">
                    <i class="bi bi-plus-circle me-1"></i> Add co-tenant
                </button>

                <!-- TENANCY TERMS -->
                <h6 class="text-uppercase small text-secondary mt-3 mb-2">Tenancy terms</h6>
                <div class="row g-2">
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Monthly rent (RM) <small class="text-danger">*</small></label>
                        <input type="number" name="monthly_rent" id="tf_monthly_rent"
                               class="form-control form-control-sm" min="0" step="50" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Deposit (RM)</label>
                        <input type="number" name="deposit" id="tf_deposit"
                               class="form-control form-control-sm" min="0" step="50">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-semibold">Term (months) <small class="text-danger">*</small></label>
                        <input type="number" name="term_months" id="tf_term_months"
                               class="form-control form-control-sm" min="1" max="60" value="12" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-semibold">Start date <small class="text-danger">*</small></label>
                        <input type="date" name="start_date" id="tf_start_date"
                               class="form-control form-control-sm" required>
                    </div>
                    <div class="col-12 mt-2">
                        <label class="form-label small fw-semibold">Special terms / notes (optional)</label>
                        <textarea name="notes" rows="2" class="form-control form-control-sm"
                                  placeholder="Any special conditions agreed by both parties"></textarea>
                    </div>
                </div>

                <div id="tenantFormError" class="alert alert-danger small d-none mt-3"></div>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-warning text-dark fw-semibold">
                    <i class="bi bi-check-circle me-1"></i> Submit form
                </button>
            </div>
        </form>
    </div>
</div>
<style>
.chat-shell {
    background: white;
    border: 1px solid rgba(15,44,82,0.08);
    border-radius: 12px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    height: calc(100vh - 200px);
    min-height: 500px;
}
.chat-header {
    padding: 16px 20px;
    border-bottom: 1px solid rgba(15,44,82,0.08);
    background: #FAF8F3;
}
.chat-body {
    flex-grow: 1;
    overflow-y: auto;
    padding: 16px 20px;
    display: flex;
    flex-direction: column;
    gap: 8px;
    background: #F4F4EE;
}
.chat-message {
    max-width: 70%;
    padding: 10px 14px;
    border-radius: 18px;
    word-wrap: break-word;
}
.chat-message.mine {
    align-self: flex-end;
    background: #2E8B57;
    color: white;
    border-bottom-right-radius: 4px;
}
.chat-message.theirs {
    align-self: flex-start;
    background: white;
    color: #0F2C52;
    border-bottom-left-radius: 4px;
    border: 1px solid rgba(15,44,82,0.06);
}
.chat-meta {
    font-size: 0.7rem;
    opacity: 0.7;
    margin-top: 2px;
    text-align: right;
}
.chat-quick-replies {
    padding: 8px 16px;
    border-top: 1px solid rgba(15,44,82,0.08);
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
    background: white;
}
.quick-reply-chip {
    background: white;
    border: 1px solid rgba(15,44,82,0.15);
    border-radius: 999px;
    padding: 5px 12px;
    font-size: 0.8rem;
    color: #0F2C52;
    cursor: pointer;
    transition: all 0.15s;
}
.quick-reply-chip:hover {
    background: #E4F2EA;
    border-color: #2E8B57;
    color: #2E8B57;
}
.chat-input {
    border-top: 1px solid rgba(15,44,82,0.08);
    padding: 10px 16px;
    background: white;
}
.chat-input textarea {
    resize: none;
    border-radius: 20px;
    border: 1px solid rgba(15,44,82,0.15);
    padding: 8px 16px;
}
.chat-plus-item {
    transition: background 0.12s;
}
.chat-plus-item:hover {
    background: #F4F8FF;
}
</style>

<script>
// ============================================================
// TENANT INFO FORM — landlord fills tenant + co-tenants
// SINGLE source of truth. Do NOT duplicate elsewhere.
// ============================================================

(function() {
    const modal = document.getElementById('tenantInfoModal');
    if (!modal) return;  // not on this page

    let coTenantCount = 0;

    // === Add co-tenant row ===
    function addCoTenantRow() {
        coTenantCount++;
        const list = document.getElementById('tfCoTenantsList');
        if (!list) return;

        const row = document.createElement('div');
        row.className = 'row g-2 mb-2 align-items-end p-2 border rounded';
        row.style.background = '#FAF8F3';
        row.innerHTML = `
            <div class="col-md-5">
                <label class="form-label small fw-semibold">Co-tenant ${coTenantCount} name</label>
                <input type="text" name="cotenant_name[]" class="form-control form-control-sm" required>
            </div>
            <div class="col-md-5">
                <label class="form-label small fw-semibold">NRIC</label>
                <input type="text" name="cotenant_ic[]" class="form-control form-control-sm"
                       placeholder="020815-04-1234" required>
            </div>
            <div class="col-md-2 text-end">
                <button type="button" class="btn btn-sm btn-outline-danger remove-cotenant">
                    <i class="bi bi-x"></i>
                </button>
            </div>
        `;
        list.appendChild(row);
        row.querySelector('.remove-cotenant').addEventListener('click', () => row.remove());
    }

    // Bind ONCE
    const addBtn = document.getElementById('tfAddCoTenantBtn');
    if (addBtn) {
        addBtn.addEventListener('click', addCoTenantRow);
    }

    // === Open modal: populate fields ===
    document.querySelectorAll('.fill-tenant-form-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const prefill = JSON.parse(this.dataset.prefill || '{}');

            document.getElementById('tf_form_id').value     = this.dataset.formId;
            document.getElementById('tf_conv_id').value     = this.dataset.convId;
            document.getElementById('tf_property_id').value = this.dataset.propertyId;
            document.getElementById('tf_student_id').value  = this.dataset.studentId;

            document.getElementById('tf_tenant_name').value  = prefill.tenant_name || '';
            document.getElementById('tf_tenant_phone').value = prefill.tenant_phone || '';
            document.getElementById('tf_tenant_email').value = prefill.tenant_email || '';
            document.getElementById('tf_monthly_rent').value = prefill.monthly_rent || '';
            document.getElementById('tf_deposit').value      = prefill.deposit || '';

            document.getElementById('tf_start_date').value = new Date().toISOString().split('T')[0];

            // Reset co-tenants list when reopening
            document.getElementById('tfCoTenantsList').innerHTML = '';
            coTenantCount = 0;
        });
    });

    // === Submit ===
    const form = document.getElementById('tenantInfoForm');
    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            const errorBox = document.getElementById('tenantFormError');
            errorBox.classList.add('d-none');

            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Submitting...';

            try {
                const formData = new FormData(this);
                const resp = await fetch('/rentbridge/chat/submit_tenant_form.php', {
                    method: 'POST',
                    body: formData,
                });
                const text = await resp.text();
                console.log('[submit-tenant-form] response:', resp.status, text);

                let data;
                try { data = JSON.parse(text); }
                catch (e) {
                    errorBox.textContent = 'Server returned non-JSON: ' + text.substring(0, 300);
                    errorBox.classList.remove('d-none');
                    return;
                }

                if (data.ok) {
                    alert(data.message);
                    bootstrap.Modal.getInstance(modal).hide();
                    location.reload();
                } else {
                    errorBox.textContent = data.error;
                    errorBox.classList.remove('d-none');
                }
            } catch (err) {
                errorBox.textContent = 'Network error: ' + err.message;
                errorBox.classList.remove('d-none');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Submit form';
            }
        });
    }
})();

// ============================================================
// PLUS BUTTON — toggle attachment / form menu
// ============================================================
(function () {
    const plusBtn  = document.getElementById('chatPlusBtn');
    const plusMenu = document.getElementById('chatPlusMenu');
    if (!plusBtn || !plusMenu) return;

    plusBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        plusMenu.classList.toggle('d-none');
    });

    // Close on outside click
    document.addEventListener('click', function (e) {
        if (!plusMenu.contains(e.target) && e.target !== plusBtn) {
            plusMenu.classList.add('d-none');
        }
    });

    // Wire up file inputs to auto-send as chat message (placeholder: show filename in textarea)
    ['chatImageInput', 'chatDocInput'].forEach(id => {
        const inp = document.getElementById(id);
        if (!inp) return;
        inp.addEventListener('change', function () {
            if (this.files.length) {
                const textarea = document.getElementById('chatTextarea');
                if (textarea && textarea.value === '') {
                    textarea.value = '[File: ' + this.files[0].name + ']';
                }
            }
            plusMenu.classList.add('d-none');
        });
    });

    // Plus-menu clone of agentSendFormBtn
    const agentSendFormBtnPlus = document.getElementById('agentSendFormBtnPlus');
    const agentSendFormBtn = document.getElementById('agentSendFormBtn');
    if (agentSendFormBtnPlus && agentSendFormBtn) {
        agentSendFormBtnPlus.addEventListener('click', () => agentSendFormBtn.click());
    }

    // Plus-menu clone of contractPrepBtn
    const contractPrepBtnPlus = document.getElementById('contractPrepBtnPlus');
    const contractPrepBtn = document.getElementById('contractPrepBtn');
    if (contractPrepBtnPlus && contractPrepBtn) {
        contractPrepBtnPlus.addEventListener('click', () => contractPrepBtn.click());
    }
})();

// ============================================================
// INSPECTION SCHEDULE — agent sends request
// ============================================================
(function () {
    // Populate modal before open
    const sendModal = document.getElementById('sendInspectionScheduleModal');
    if (sendModal) {
        sendModal.addEventListener('show.bs.modal', function (e) {
            const btn = e.relatedTarget;
            if (!btn) return;
            document.getElementById('iss_conv_id').value = btn.dataset.convId || '';
            document.getElementById('iss_prop_id').value = btn.dataset.propId || '';
        });

        const form = document.getElementById('inspectionScheduleForm');
        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            const errBox = document.getElementById('issError');
            errBox.classList.add('d-none');
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Sending...';

            try {
                const resp = await fetch('/rentbridge/chat/send_inspection_schedule.php', {
                    method: 'POST', body: new FormData(this)
                });
                const data = await resp.json();
                if (data.ok) {
                    bootstrap.Modal.getInstance(sendModal).hide();
                    location.reload();
                } else {
                    errBox.textContent = data.error;
                    errBox.classList.remove('d-none');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="bi bi-send me-1"></i> Send to landlord';
                }
            } catch (err) {
                errBox.textContent = 'Network error: ' + err.message;
                errBox.classList.remove('d-none');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-send me-1"></i> Send to landlord';
            }
        });
    }
})();

// ============================================================
// INSPECTION SCHEDULE — landlord responds
// ============================================================
(function () {
    const responseModal = document.getElementById('inspectionScheduleModal');
    if (!responseModal) return;

    responseModal.addEventListener('show.bs.modal', function (e) {
        const btn = e.relatedTarget;
        if (!btn) return;
        document.getElementById('isr_conv_id').value    = btn.dataset.convId || '';
        document.getElementById('isr_form_msg_id').value = btn.dataset.formMsgId || '';
        document.getElementById('isr_slots').textContent = btn.dataset.slots || '';
        document.getElementById('isrError').classList.add('d-none');
        document.getElementById('isr_reschedule_section').classList.add('d-none');
        document.getElementById('isr_confirm_section').classList.remove('d-none');
        document.getElementById('isrSubmitBtn').dataset.mode = 'confirm';
        document.getElementById('isrSubmitBtn').innerHTML = '<i class="bi bi-check-circle me-1"></i> Confirm inspection';
    });

    // Toggle access detail field
    const accessSelect = document.getElementById('isr_access_method');
    if (accessSelect) {
        accessSelect.addEventListener('change', function () {
            const wrap = document.getElementById('isr_detail_wrap');
            wrap.classList.toggle('d-none', this.value === 'landlord_present' || this.value === '');
        });
    }

    // Request reschedule toggle
    const rescheduleBtn = document.getElementById('isrRequestReschedule');
    if (rescheduleBtn) {
        rescheduleBtn.addEventListener('click', function () {
            const confirmSection = document.getElementById('isr_confirm_section');
            const rescheduleSection = document.getElementById('isr_reschedule_section');
            const submitBtn = document.getElementById('isrSubmitBtn');
            const isReschedule = submitBtn.dataset.mode === 'reschedule';

            if (isReschedule) {
                confirmSection.classList.remove('d-none');
                rescheduleSection.classList.add('d-none');
                submitBtn.dataset.mode = 'confirm';
                submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i> Confirm inspection';
                this.innerHTML = '<i class="bi bi-calendar-x me-1"></i> Request reschedule instead';
            } else {
                confirmSection.classList.add('d-none');
                rescheduleSection.classList.remove('d-none');
                submitBtn.dataset.mode = 'reschedule';
                submitBtn.innerHTML = '<i class="bi bi-send me-1"></i> Send reschedule request';
                this.innerHTML = '<i class="bi bi-arrow-left me-1"></i> Back to confirm';
            }
        });
    }

    // Submit
    const responseForm = document.getElementById('inspectionResponseForm');
    if (responseForm) {
        responseForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const errBox = document.getElementById('isrError');
            errBox.classList.add('d-none');
            const submitBtn = document.getElementById('isrSubmitBtn');
            const mode = submitBtn.dataset.mode || 'confirm';

            const formData = new FormData(this);
            formData.set('decision', mode);
            if (mode === 'reschedule') {
                const reason = document.getElementById('isr_reschedule_reason').value;
                formData.set('access_detail', reason);
                formData.set('slot_picked', '');
                formData.set('access_method', 'other');
                formData.set('consent', '0');
            }

            submitBtn.disabled = true;
            const origText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Submitting...';

            try {
                const resp = await fetch('/rentbridge/chat/respond_inspection_schedule.php', {
                    method: 'POST', body: formData
                });
                const data = await resp.json();
                if (data.ok) {
                    bootstrap.Modal.getInstance(responseModal).hide();
                    location.reload();
                } else {
                    errBox.textContent = data.error;
                    errBox.classList.remove('d-none');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = origText;
                }
            } catch (err) {
                errBox.textContent = 'Network error: ' + err.message;
                errBox.classList.remove('d-none');
                submitBtn.disabled = false;
                submitBtn.innerHTML = origText;
            }
        });
    }
})();

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.send-tenant-form-btn').forEach(btn => {
        if (btn.dataset.bound === '1') return;
        btn.dataset.bound = '1';
        
        btn.addEventListener('click', async function() {
            if (!confirm('Send tenant info form to landlord?')) return;
            
            this.disabled = true;
            const originalHTML = this.innerHTML;
            this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Sending...';

            try {
                const formData = new FormData();
                formData.append('_csrf', '<?= csrf_token() ?>');
                formData.append('conversation_id', this.dataset.convId);
                formData.append('property_id', this.dataset.propertyId);
                formData.append('student_id', this.dataset.studentId);

                const resp = await fetch('/rentbridge/chat/send_tenant_form.php', {
                    method: 'POST',
                    body: formData,
                });
                const text = await resp.text();
                console.log('[send-form] response:', resp.status, text);
                
                let data;
                try { data = JSON.parse(text); }
                catch (e) {
                    alert('Server returned non-JSON: ' + text.substring(0, 300));
                    this.disabled = false;
                    this.innerHTML = originalHTML;
                    return;
                }

                if (data.ok) {
                    alert(data.message);
                    location.reload();
                } else {
                    alert('Failed: ' + data.error);
                    this.disabled = false;
                    this.innerHTML = originalHTML;
                }
            } catch (err) {
                alert('Network error: ' + err.message);
                this.disabled = false;
                this.innerHTML = originalHTML;
            }
        });
    });
});
document.addEventListener('DOMContentLoaded', function() {
    const btn = document.getElementById('agentSendFormBtn');
    if (!btn) return;

    btn.addEventListener('click', async function() {
        if (!confirm('Send tenant info form to the student? The student will fill in their own details and any co-tenants. Only proceed if you have agreed to move forward.')) return;

        this.disabled = true;
        this.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Sending...';

        try {
            const formData = new FormData();
            formData.append('_csrf', '<?= csrf_token() ?>');
            formData.append('conversation_id', this.dataset.convId);
            formData.append('property_id', this.dataset.propertyId);
            formData.append('student_id', this.dataset.studentId);
            formData.append('recipient_role', 'student');  // ← key parameter

            const resp = await fetch('/rentbridge/chat/send_tenant_form.php', {
                method: 'POST',
                body: formData,
            });
            const text = await resp.text();
            let data;
            try { data = JSON.parse(text); }
            catch (e) {
                alert('Server returned non-JSON: ' + text.substring(0, 300));
                return;
            }

            if (data.ok) {
                alert(data.message);
                location.reload();
            } else {
                alert('Failed: ' + data.error);
                this.disabled = false;
                this.innerHTML = '<i class="bi bi-send me-1"></i> Send tenant info form to student';
            }
        } catch (err) {
            alert('Network error: ' + err.message);
            this.disabled = false;
            this.innerHTML = '<i class="bi bi-send me-1"></i> Send tenant info form to student';
        }
    });
});

<?php if ($isGroupChat && !empty($groupParticipants)): ?>
// ============================================================
// @MENTION AUTOCOMPLETE — group chats only
// ============================================================
(function () {
    const ta = document.getElementById('chatTextarea');
    if (!ta) return;

    const participants = <?= json_encode(array_map(fn($p) => $p['preferred_name'] ?: $p['full_name'], $groupParticipants)) ?>;

    let mentionStart = -1;
    let menuEl = null;

    function showMenu(query, caretPos) {
        removeMenu();
        const matches = participants.filter(n => n.toLowerCase().startsWith(query.toLowerCase()));
        if (!matches.length) return;

        menuEl = document.createElement('div');
        menuEl.style.cssText = 'position:absolute; background:white; border:1px solid #ddd; border-radius:6px; box-shadow:0 4px 12px rgba(0,0,0,0.1); z-index:9999; min-width:160px; overflow:hidden;';
        matches.forEach(name => {
            const item = document.createElement('div');
            item.textContent = '@' + name;
            item.style.cssText = 'padding:8px 12px; cursor:pointer; font-size:0.9rem;';
            item.addEventListener('mouseenter', () => item.style.background = '#F4F4EE');
            item.addEventListener('mouseleave', () => item.style.background = '');
            item.addEventListener('mousedown', e => {
                e.preventDefault();
                const val = ta.value;
                ta.value = val.substring(0, mentionStart) + '@' + name + ' ' + val.substring(caretPos);
                ta.selectionStart = ta.selectionEnd = mentionStart + name.length + 2;
                removeMenu();
                ta.focus();
            });
            menuEl.appendChild(item);
        });

        // Position near caret
        const rect = ta.getBoundingClientRect();
        menuEl.style.bottom = (window.innerHeight - rect.top + 4) + 'px';
        menuEl.style.left = rect.left + 'px';
        document.body.appendChild(menuEl);
    }

    function removeMenu() {
        if (menuEl) { menuEl.remove(); menuEl = null; }
        mentionStart = -1;
    }

    ta.addEventListener('input', function () {
        const pos = this.selectionStart;
        const before = this.value.substring(0, pos);
        const atIdx = before.lastIndexOf('@');
        if (atIdx === -1 || /\s/.test(before.substring(atIdx + 1))) {
            removeMenu();
            return;
        }
        const query = before.substring(atIdx + 1);
        mentionStart = atIdx;
        showMenu(query, pos);
    });

    ta.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') removeMenu();
    });

    document.addEventListener('click', e => {
        if (!menuEl || menuEl.contains(e.target) || e.target === ta) return;
        removeMenu();
    });
})();
<?php endif; ?>
</script>

<?php if (!$isGroupChat && $otherUserId > 0): ?>
<!-- RIGHT-CLICK CONTEXT MENU on other party's messages -->
<div id="msgContextMenu" class="d-none"
     style="position:fixed; z-index:9999; background:white; border:1px solid rgba(0,0,0,0.1);
            border-radius:10px; box-shadow:0 4px 16px rgba(0,0,0,0.12); min-width:164px; overflow:hidden;">
    <button type="button"
            id="msgCtxReportBtn"
            class="btn btn-link w-100 text-start text-danger px-3 py-2 d-flex align-items-center gap-2"
            style="font-size:.875rem; text-decoration:none;">
        <i class="bi bi-flag"></i> Report message
    </button>
</div>

<?php
require_once __DIR__ . '/../includes/reports.php';
$reportSubjects = [['id' => $otherUserId, 'name' => $other['name'], 'role' => $other['primary_role']]];
render_report_modal($reportSubjects, 'message', 0);
?>

<script>
(function () {
    const menu = document.getElementById('msgContextMenu');
    let activeMessageId = 0;

    function showCtxMenu(x, y) {
        menu.classList.remove('d-none');
        const mw = 164, mh = 52;
        menu.style.left = Math.min(x, window.innerWidth  - mw - 8) + 'px';
        menu.style.top  = Math.min(y, window.innerHeight - mh - 8) + 'px';
    }

    document.querySelectorAll('.chat-message.theirs[data-msg-id]').forEach(function (bubble) {
        bubble.addEventListener('contextmenu', function (e) {
            e.preventDefault();
            activeMessageId = parseInt(bubble.dataset.msgId, 10) || 0;
            showCtxMenu(e.clientX, e.clientY);
        });

        let pressTimer;
        bubble.addEventListener('touchstart', function (e) {
            pressTimer = setTimeout(function () {
                const t = e.changedTouches[0];
                activeMessageId = parseInt(bubble.dataset.msgId, 10) || 0;
                showCtxMenu(t.clientX, t.clientY);
            }, 600);
        }, { passive: true });
        bubble.addEventListener('touchend',  function () { clearTimeout(pressTimer); });
        bubble.addEventListener('touchmove', function () { clearTimeout(pressTimer); });
    });

    document.addEventListener('click', function (e) {
        if (!menu.contains(e.target)) menu.classList.add('d-none');
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') menu.classList.add('d-none');
    });

    document.getElementById('msgCtxReportBtn').addEventListener('click', function () {
        menu.classList.add('d-none');
        const ctxInput = document.querySelector('#reportModal [name="context_id"]');
        if (ctxInput) ctxInput.value = activeMessageId;
        bootstrap.Modal.getOrCreateInstance(document.getElementById('reportModal')).show();
    });
})();
</script>
<?php endif; ?>

<?php
$pageContent = ob_get_clean();
$role = current_role();
if ($role === 'student') {
    require __DIR__ . '/../includes/student_layout.php';
} elseif ($role === 'landlord') {
    require __DIR__ . '/../includes/landlord_layout.php';
} elseif ($role === 'agent') {
    require __DIR__ . '/../includes/agent_layout.php';
} else {
    require __DIR__ . '/../includes/admin_layout.php';
}