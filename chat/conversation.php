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
            <a href="/rentbridge/properties/<?= (int)$property['id'] ?>"
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
                        <div style="width:24px; height:24px; border-radius:50%;
                                    background: <?php
                                        echo match($other['primary_role']) {
                                            'student'=>'#E4F2EA','landlord'=>'#FFE8C7',
                                            'agent'=>'#FFF4D6','admin'=>'#F8D7DA',
                                            default=>'#E2E2E2',
                                        };
                                    ?>;
                                    display:inline-flex; align-items:center; justify-content:center;
                                    font-size:0.7rem; font-weight:600; color:#0F2C52;">
                            <?= strtoupper(substr($other['name'], 0, 1)) ?>
                        </div>
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
            $isMine = (int)$msg['sender_id'] === $userId;
            $msgType = $msg['message_type'] ?? 'text';
        ?>

            <?php if ($msgType === 'co_tenant_form'):
                $meta = json_decode($msg['metadata'] ?? '{}', true);
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
            <?php else: ?>
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
                  action="/rentbridge/chat/submit_cotenants.php">
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
                                   placeholder="030823-02-0465" required>
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

<script>
(function() {
    const modal = document.getElementById('coTenantFormModal');
    const bookingIdInput = document.getElementById('coTenantBookingId');
    const primaryNameInput = document.getElementById('primaryName');
    const primaryIcInput = document.getElementById('primaryIc');
    const listContainer = document.getElementById('coTenantList');
    const addBtn = document.getElementById('addCoTenantBtn');

    let rowIndex = 0;

    function buildRow(idx) {
        const div = document.createElement('div');
        div.className = 'row g-2 mb-2 align-items-end';
        div.dataset.row = idx;
        div.innerHTML = `
            <div class="col-md-5">
                <label class="form-label small">Full name</label>
                <input type="text" class="form-control form-control-sm"
                       name="cotenant[${idx}][full_name]"
                       placeholder="e.g. Ahmad bin Hashim" required>
            </div>
            <div class="col-md-4">
                <label class="form-label small">IC number</label>
                <input type="text" class="form-control form-control-sm"
                       name="cotenant[${idx}][ic_number]"
                       placeholder="030823-02-0465" required>
            </div>
            <div class="col-md-2">
                <label class="form-label small">Phone</label>
                <input type="text" class="form-control form-control-sm"
                       name="cotenant[${idx}][phone]" placeholder="012-345 6789">
            </div>
            <div class="col-md-1 text-end">
                <button type="button" class="btn btn-sm btn-outline-danger remove-row">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        `;
        div.querySelector('.remove-row').onclick = () => div.remove();
        return div;
    }

    addBtn.addEventListener('click', () => {
        listContainer.appendChild(buildRow(rowIndex++));
    });

    // When modal opens via button: capture booking_id + prefill primary name
    modal.addEventListener('show.bs.modal', function(event) {
        const trigger = event.relatedTarget;
        const bookingId = trigger?.dataset.bookingId;
        if (bookingId) bookingIdInput.value = bookingId;

        // Prefill primary name via AJAX (the chat page already knows current user;
        // server-side we'll fetch from session)
        fetch('/rentbridge/chat/get_primary_info.php?booking_id=' + bookingId)
            .then(r => r.json())
            .then(data => {
                primaryNameInput.value = data.full_name || '';
                primaryIcInput.value = data.ic_number === 'PENDING' ? '' : (data.ic_number || '');
            })
            .catch(err => console.warn('Could not prefill', err));
    });

    // Reset on close
    modal.addEventListener('hidden.bs.modal', function() {
        listContainer.innerHTML = '';
        rowIndex = 0;
    });
})();
</script>

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
(function() {
    const csrfToken = '<?= csrf_token() ?>';
    const conversationId = <?= (int)$conversationId ?>;
    const currentUserId = <?= (int)$userId ?>;
    let lastMessageId = <?= (int)$lastMessageId ?>;

    const chatBody = document.getElementById('chatBody');
    const form = document.getElementById('chatForm');
    if (!form) return;

    const textarea = document.getElementById('chatTextarea');
    const sendBtn = document.getElementById('chatSendBtn');

    function scrollToBottom() { chatBody.scrollTop = chatBody.scrollHeight; }
    scrollToBottom();

    function appendMessage(msg) {
        const isMine = parseInt(msg.sender_id) === currentUserId;
        const div = document.createElement('div');
        div.className = 'chat-message ' + (isMine ? 'mine' : 'theirs');
        div.dataset.msgId = msg.id;
        div.textContent = msg.body;

        const meta = document.createElement('div');
        meta.className = 'chat-meta';
        meta.textContent = new Date(msg.sent_at).toLocaleTimeString('en-MY', {
            hour:'2-digit', minute:'2-digit', hour12:false
        });
        div.appendChild(meta);

        chatBody.appendChild(div);
        scrollToBottom();
        if (parseInt(msg.id) > lastMessageId) lastMessageId = parseInt(msg.id);
    }

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        const body = textarea.value.trim();
        if (!body) return;

        sendBtn.disabled = true;
        try {
            const response = await fetch('/rentbridge/chat/send.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    _csrf: csrfToken,        // ← matches name="csrf_token" in csrf_field()
                    conversation_id: conversationId,
                    body: body
                })
            });
            const data = await response.json();
            if (data.ok && data.message) {
                appendMessage(data.message);
                textarea.value = '';
                textarea.style.height = 'auto';
            } else {
                alert('Failed: ' + (data.error || 'Unknown'));
            }
        } catch (err) {
            alert('Network error: ' + err.message);
        } finally {
            sendBtn.disabled = false;
            textarea.focus();
        }
    });

    // Quick reply chips
    document.querySelectorAll('.quick-reply-chip').forEach(chip => {
        chip.addEventListener('click', function() {
            textarea.value = this.dataset.reply;
            textarea.focus();
        });
    });

    // Poll for new messages every 5 seconds
    async function poll() {
        try {
            const response = await fetch('/rentbridge/chat/poll.php?conversation_id='
                + conversationId + '&since=' + lastMessageId);
            const data = await response.json();
            if (data.ok && Array.isArray(data.messages)) {
                data.messages.forEach(appendMessage);
            }
        } catch (e) {}
    }
    setInterval(poll, 5000);

    // Auto-resize textarea
    textarea.addEventListener('input', function() {
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
    });

    // Enter to send, Shift+Enter newline
    textarea.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            form.dispatchEvent(new Event('submit'));
        }
    });
})();
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