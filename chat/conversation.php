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

$otherUserId = ((int)$convo['user_a'] === $userId) ? (int)$convo['user_b'] : (int)$convo['user_a'];
$other = chat_get_user_display($otherUserId);

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

// Quick replies — different per role
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

$pageTitle = 'Chat with ' . $other['name'];
$activeNav = 'chat';

ob_start();
?>

<a href="/rentbridge/chat.php" class="small text-secondary text-decoration-none mb-2 d-inline-block">
    <i class="bi bi-arrow-left"></i> Back to messages
</a>

<div class="chat-shell">

    <!-- HEADER (with property card if applicable) -->
    <div class="chat-header">
        <?php if ($property): ?>
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
                <div class="text-secondary small">
                    Listing ID:<?= (int)$property['id'] ?>
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
                <div>
                    <strong><?= e($other['name']) ?></strong>
                    <span class="badge bg-light text-dark ms-1">
                        <?= e(ucfirst($other['primary_role'])) ?>
                    </span>
                </div>
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
                                    <span class="badge bg-secondary">Awaiting landlord</span>
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
        <form class="chat-input d-flex gap-2 align-items-center" id="chatForm">
            <?= csrf_field() ?>
            <input type="hidden" name="conversation_id" value="<?= (int)$conversationId ?>">
            <button type="button" class="btn btn-link p-2 text-secondary"
                    title="Image attach (coming in v2)" disabled>
                <i class="bi bi-image" style="font-size:1.3rem;"></i>
            </button>
            <textarea name="body" class="form-control" rows="1"
                      placeholder="Type a message..." required
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
</script>

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