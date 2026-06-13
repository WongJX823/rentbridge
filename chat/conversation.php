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
        <?php foreach ($messages as $msg):
            $isMine = (int)$msg['sender_id'] === $userId;
        ?>
            <div class="chat-message <?= $isMine ? 'mine' : 'theirs' ?>" data-msg-id="<?= (int)$msg['id'] ?>">
                <?= nl2br(e($msg['body'])) ?>
                <div class="chat-meta">
                    <?= e(date('H:i', strtotime($msg['sent_at']))) ?>
                </div>
            </div>
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