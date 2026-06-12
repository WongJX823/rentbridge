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

if (!chat_can_view($conversationId, $userId)) {
    http_response_code(403);
    die('You are not in this conversation.');
}

// Fetch conversation details
$pdo = db();
$stmt = $pdo->prepare("
    SELECT c.*,
           CASE WHEN c.user_a = ? THEN c.user_b ELSE c.user_a END AS other_user_id,
           p.title AS property_title,
           p.id    AS property_id_full
      FROM conversations c
      LEFT JOIN properties p ON p.id = c.property_id
     WHERE c.id = ?
     LIMIT 1
");
$stmt->execute([$userId, $conversationId]);
$convo = $stmt->fetch();

$other = chat_get_user_display((int)$convo['other_user_id']);

// Mark messages from other person as read
chat_mark_read($conversationId, $userId);

// Load message history
$messages = chat_get_messages($conversationId, 0);
$lastMessageId = !empty($messages) ? max(array_column($messages, 'id')) : 0;

$isLocked = (int)$convo['is_locked'] === 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Chat with <?= e($other['name']) ?> · RentBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/rentbridge/assets/css/style.css" rel="stylesheet">
    <style>
        .chat-shell {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border: 1px solid rgba(15,44,82,0.08);
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            height: calc(100vh - 180px);
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
        }
        .chat-message {
            max-width: 70%;
            padding: 10px 14px;
            border-radius: 18px;
            word-wrap: break-word;
            white-space: pre-wrap;
        }
        .chat-message.mine {
            align-self: flex-end;
            background: #2E8B57;
            color: white;
            border-bottom-right-radius: 4px;
        }
        .chat-message.theirs {
            align-self: flex-start;
            background: #F4F4EE;
            color: #0F2C52;
            border-bottom-left-radius: 4px;
        }
        .chat-meta {
            font-size: 0.7rem;
            opacity: 0.7;
            margin-top: 2px;
        }
        .chat-input {
            border-top: 1px solid rgba(15,44,82,0.08);
            padding: 12px 16px;
            background: white;
        }
        .chat-input textarea {
            resize: none;
            border-radius: 20px;
        }
    </style>
</head>
<body style="background: var(--rb-cream);">

<?php include __DIR__ . '/../includes/header.php'; ?>

<div class="container py-4">
    <p class="small mb-3">
        <a href="/rentbridge/chat.php" class="text-secondary text-decoration-none">
            <i class="bi bi-arrow-left"></i> All conversations
        </a>
    </p>

    <div class="chat-shell">

        <!-- Header -->
        <div class="chat-header d-flex justify-content-between align-items-center">
            <div>
                <strong><?= e($other['name']) ?></strong>
                <span class="badge ms-2"
                      style="background: <?php
                          echo match($other['primary_role']) {
                              'student'=>'#E4F2EA','landlord'=>'#E6ECF4',
                              'agent'=>'#FFF4D6','admin'=>'#F8D7DA',default=>'#E2E2E2',
                          };
                      ?>; color:#0F2C52; font-weight:500;">
                    <?= e(ucfirst($other['primary_role'])) ?>
                </span>
                <?php if (!empty($convo['property_title'])): ?>
                    <div class="small text-secondary mt-1">
                        <i class="bi bi-house-door"></i>
                        Re:
                        <a href="/rentbridge/properties/<?= (int)$convo['property_id_full'] ?>"
                           class="text-decoration-none">
                            <?= e($convo['property_title']) ?>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
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

        <!-- Messages -->
        <div class="chat-body" id="chatBody">
            <?php if (empty($messages)): ?>
                <div class="text-center text-secondary small py-4">
                    No messages yet. Say hi!
                </div>
            <?php endif; ?>
            <?php foreach ($messages as $msg):
                $isMine = (int)$msg['sender_id'] === $userId;
            ?>
                <div class="chat-message <?= $isMine ? 'mine' : 'theirs' ?>"
                     data-msg-id="<?= (int)$msg['id'] ?>">
                    <?= e($msg['body']) ?>
                    <div class="chat-meta text-end">
                        <?= e(date('d M, H:i', strtotime($msg['sent_at']))) ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Input -->
        <?php if (!$isLocked): ?>
            <form class="chat-input d-flex gap-2" id="chatForm">
                <?= csrf_field() ?>
                <input type="hidden" name="conversation_id" value="<?= (int)$conversationId ?>">
                <textarea name="body" class="form-control" rows="1"
                          placeholder="Type a message..." required
                          maxlength="2000" id="chatBody2"></textarea>
                <button type="submit" class="btn btn-primary" id="chatSendBtn">
                    <i class="bi bi-send"></i>
                </button>
            </form>
        <?php else: ?>
            <div class="chat-input text-center text-secondary small py-3">
                <i class="bi bi-lock-fill"></i> Sending disabled — conversation closed.
            </div>
        <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<?php if (!$isLocked): ?>
<script>
(function() {
    const csrfToken = '<?= csrf_token() ?>';
    const conversationId = <?= (int)$conversationId ?>;
    const currentUserId = <?= (int)$userId ?>;
    let lastMessageId = <?= (int)$lastMessageId ?>;

    const chatBody = document.getElementById('chatBody');
    const form = document.getElementById('chatForm');
    const textarea = document.getElementById('chatBody2');
    const sendBtn = document.getElementById('chatSendBtn');

    function scrollToBottom() {
        chatBody.scrollTop = chatBody.scrollHeight;
    }
    scrollToBottom();

    function appendMessage(msg) {
        const isMine = parseInt(msg.sender_id) === currentUserId;
        const div = document.createElement('div');
        div.className = 'chat-message ' + (isMine ? 'mine' : 'theirs');
        div.dataset.msgId = msg.id;

        const safeBody = document.createElement('span');
        safeBody.textContent = msg.body;

        const meta = document.createElement('div');
        meta.className = 'chat-meta text-end';
        meta.textContent = new Date(msg.sent_at).toLocaleString('en-MY', {
            day:'2-digit', month:'short', hour:'2-digit', minute:'2-digit'
        });

        div.appendChild(safeBody);
        div.appendChild(meta);
        chatBody.appendChild(div);
        scrollToBottom();

        if (parseInt(msg.id) > lastMessageId) lastMessageId = parseInt(msg.id);
    }

    // Send message
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
                    csrf_token: csrfToken,
                    conversation_id: conversationId,
                    body: body
                })
            });
            const data = await response.json();

            if (data.ok && data.message) {
                appendMessage(data.message);
                textarea.value = '';
            } else {
                alert('Failed to send: ' + (data.error || 'Unknown error'));
            }
        } catch (err) {
            alert('Network error: ' + err.message);
        } finally {
            sendBtn.disabled = false;
            textarea.focus();
        }
    });

    // Poll for new messages every 5 seconds
    async function pollNewMessages() {
        try {
            const response = await fetch('/rentbridge/chat/poll.php?conversation_id='
                + conversationId + '&since=' + lastMessageId);
            const data = await response.json();
            if (data.ok && Array.isArray(data.messages)) {
                data.messages.forEach(appendMessage);
            }
        } catch (e) {
            // Silent fail — try again next tick
        }
    }
    setInterval(pollNewMessages, 5000);

    // Auto-resize textarea
    textarea.addEventListener('input', function() {
        textarea.style.height = 'auto';
        textarea.style.height = Math.min(textarea.scrollHeight, 120) + 'px';
    });

    // Enter to send, Shift+Enter for newline
    textarea.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            form.dispatchEvent(new Event('submit'));
        }
    });
})();
</script>
<?php endif; ?>
</body>
</html>