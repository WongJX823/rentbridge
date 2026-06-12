
<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/chat.php';
require_login();

$userId = current_user_id();
$role   = current_role();

$conversationId = (int)($_GET['id'] ?? 0);

// Handle send (POST AJAX) — return JSON
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'send') {
    header('Content-Type: application/json');
    verify_csrf();
    $convoId = (int)($_POST['conversation_id'] ?? 0);
    $body    = $_POST['body'] ?? '';

    if (!get_conversation_for_user($convoId, $userId, $role)) {
        echo json_encode(['ok' => false, 'error' => 'Not authorized']);
        exit;
    }

    [$ok, $err, $msgId] = send_message($convoId, $userId, $body);
    echo json_encode(['ok' => $ok, 'error' => $err, 'message_id' => $msgId]);
    exit;
}

// Handle polling (GET AJAX) — return JSON of new messages
if (isset($_GET['poll']) && $conversationId > 0) {
    header('Content-Type: application/json');
    if (!get_conversation_for_user($conversationId, $userId, $role)) {
        echo json_encode(['ok' => false]);
        exit;
    }
    $afterId = (int)($_GET['after'] ?? 0);
    $messages = get_messages($conversationId, $afterId);
    mark_messages_read($conversationId, $userId);
    echo json_encode(['ok' => true, 'messages' => $messages, 'current_user_id' => $userId]);
    exit;
}

// Normal page load
if ($conversationId <= 0) {
    // Show inbox
    $inbox = get_user_inbox($userId);
    $showInbox = true;
} else {
    $convo = get_conversation_for_user($conversationId, $userId, $role);
    if (!$convo) {
        http_response_code(404);
        die('Conversation not found or access denied.');
    }
    $otherUserId   = ($userId === (int)$convo['user_a']) ? (int)$convo['user_b'] : (int)$convo['user_a'];
    $otherUserName = user_display_name($otherUserId);
    $messages      = get_messages($conversationId);
    mark_messages_read($conversationId, $userId);
    $showInbox = false;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Messages · RentBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .chat-window {
            height: 60vh;
            display: flex;
            flex-direction: column;
        }
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            background: var(--rb-cream);
        }
        .msg-bubble {
            max-width: 70%;
            padding: 0.6rem 0.9rem;
            border-radius: 14px;
            margin-bottom: 0.5rem;
            word-wrap: break-word;
        }
        .msg-bubble.mine {
            background: var(--rb-navy);
            color: white;
            margin-left: auto;
            border-bottom-right-radius: 4px;
        }
        .msg-bubble.theirs {
            background: white;
            color: var(--rb-navy);
            border: 1px solid var(--rb-line);
            border-bottom-left-radius: 4px;
        }
        .msg-meta {
            font-size: 0.7rem;
            opacity: 0.7;
            margin-top: 0.2rem;
        }
        .inbox-row {
            transition: background 0.15s;
            cursor: pointer;
        }
        .inbox-row:hover { background: var(--rb-cream); }
        .inbox-row.unread { background: #FFF4D6; }
        .unread-badge {
            background: var(--rb-emerald);
            color: white;
            border-radius: 999px;
            padding: 2px 8px;
            font-size: 0.75rem;
            font-weight: 600;
        }
    </style>
</head>
<body>

<?php include 'includes/header.php'; ?>

<div class="container py-5">

<?php if ($showInbox): ?>
    <!-- ============== INBOX ============== -->
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <h1 class="mb-4">Messages</h1>

            <?php if (empty($inbox)): ?>
                <div class="text-center py-5 bg-white rounded-3 border">
                    <i class="bi bi-chat-dots" style="font-size: 3rem; color: var(--rb-line);"></i>
                    <h4 class="mt-3">No conversations yet</h4>
                    <p class="text-secondary">
                        Browse properties to ask landlords questions, or message friends to coordinate housing.
                    </p>
                    <a href="/rentbridge/listings.php" class="btn btn-primary">
                        <i class="bi bi-search me-1"></i> Browse properties
                    </a>
                </div>
            <?php else: ?>
                <div class="bg-white border rounded-3">
                    <?php foreach ($inbox as $i => $convo):
                        $otherName = user_display_name((int)$convo['other_user_id']);
                        $unread = (int)$convo['unread_count'] > 0;
                        $isLast = ($i === count($inbox) - 1);
                    ?>
                        <a href="/rentbridge/chat.php?id=<?= (int)$convo['id'] ?>"
                           class="d-block text-decoration-none text-dark p-3 inbox-row <?= $unread ? 'unread' : '' ?> <?= !$isLast ? 'border-bottom' : '' ?>">
                            <div class="d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <strong><?= e($otherName) ?></strong>
                                        <?php if (!empty($convo['property_title'])): ?>
                                            <small class="text-secondary">
                                                · re: <?= e(mb_substr($convo['property_title'], 0, 40)) ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-secondary small">
                                        <?php if (!empty($convo['last_message_preview'])): ?>
                                            <?= e(mb_substr($convo['last_message_preview'], 0, 80)) ?>
                                            <?= mb_strlen($convo['last_message_preview']) > 80 ? '…' : '' ?>
                                        <?php else: ?>
                                            <em>No messages yet</em>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="text-end">
                                    <?php if (!empty($convo['last_message_at'])): ?>
                                        <small class="text-secondary d-block">
                                            <?= e(date('d M, H:i', strtotime($convo['last_message_at']))) ?>
                                        </small>
                                    <?php endif; ?>
                                    <?php if ($unread): ?>
                                        <span class="unread-badge mt-1"><?= (int)$convo['unread_count'] ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>
    <!-- ============== SINGLE CONVERSATION ============== -->
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <p class="small mb-3">
                <a href="/rentbridge/chat.php" class="text-secondary text-decoration-none">
                    <i class="bi bi-arrow-left"></i> All conversations
                </a>
            </p>

            <div class="bg-white border rounded-3 overflow-hidden chat-window">
                <!-- Header -->
                <div class="p-3 border-bottom" style="background: var(--rb-navy); color: white;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <strong><?= e($otherUserName) ?></strong>
                            <?php if (!empty($convo['property_title'])): ?>
                                <small class="d-block opacity-75">
                                    re: <?= e($convo['property_title']) ?>
                                </small>
                            <?php endif; ?>
                        </div>
                        <span class="badge bg-light text-dark">
                            <?= e(str_replace('_', ' ', ucfirst($convo['context_type']))) ?>
                        </span>
                    </div>
                </div>

                <!-- Messages -->
                <div id="chat-messages" class="chat-messages">
                    <?php foreach ($messages as $msg):
                        $isMine = (int)$msg['sender_id'] === $userId;
                    ?>
                        <div class="d-flex <?= $isMine ? 'justify-content-end' : 'justify-content-start' ?>">
                            <div class="msg-bubble <?= $isMine ? 'mine' : 'theirs' ?>">
                                <?= nl2br(e($msg['body'])) ?>
                                <div class="msg-meta text-end">
                                    <?= e(date('d M, H:i', strtotime($msg['sent_at']))) ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Composer -->
                <div class="p-3 border-top bg-white">
                    <form id="send-form" onsubmit="return sendMessage(event);">
                        <?= csrf_field() ?>
                        <input type="hidden" name="conversation_id" value="<?= (int)$convo['id'] ?>">
                        <div class="input-group">
                            <input type="text" id="msg-input" name="body"
                                   class="form-control" placeholder="Type a message…"
                                   maxlength="2000" required autocomplete="off" autofocus>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-send"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    (function() {
        const convoId = <?= (int)$convo['id'] ?>;
        const csrfToken = '<?= e(csrf_token()) ?>';
        const currentUserId = <?= (int)$userId ?>;
        const messagesEl = document.getElementById('chat-messages');
        const form = document.getElementById('send-form');
        const input = document.getElementById('msg-input');

        // Track the highest message ID we have
        let lastMessageId = <?= !empty($messages) ? (int)end($messages)['id'] : 0 ?>;

        // Scroll to bottom on load
        messagesEl.scrollTop = messagesEl.scrollHeight;

        function escapeHtml(s) {
            return s.replace(/[&<>"']/g, function(c) {
                return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
            });
        }

        function appendMessage(msg) {
            const isMine = parseInt(msg.sender_id) === currentUserId;
            const dt = new Date(msg.sent_at.replace(' ', 'T'));
            const time = dt.toLocaleString('en-GB', {
                day:'2-digit', month:'short', hour:'2-digit', minute:'2-digit'
            });

            const wrapper = document.createElement('div');
            wrapper.className = 'd-flex ' + (isMine ? 'justify-content-end' : 'justify-content-start');
            wrapper.innerHTML = `
                <div class="msg-bubble ${isMine ? 'mine' : 'theirs'}">
                    ${escapeHtml(msg.body).replace(/\n/g, '<br>')}
                    <div class="msg-meta text-end">${time}</div>
                </div>
            `;
            messagesEl.appendChild(wrapper);
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }

        // Polling — check for new messages every 3 seconds
        async function poll() {
            try {
                const res = await fetch(`/rentbridge/chat.php?id=${convoId}&poll=1&after=${lastMessageId}`);
                const data = await res.json();
                if (data.ok && data.messages.length > 0) {
                    data.messages.forEach(msg => {
                        appendMessage(msg);
                        lastMessageId = Math.max(lastMessageId, parseInt(msg.id));
                    });
                }
            } catch (e) {
                console.warn('Poll failed:', e);
            }
        }
        setInterval(poll, 3000);

        // Send message
        window.sendMessage = async function(ev) {
            ev.preventDefault();
            const body = input.value.trim();
            if (!body) return false;

            const fd = new FormData();
            fd.append('action', 'send');
            fd.append('conversation_id', convoId);
            fd.append('body', body);
            fd.append('csrf_token', csrfToken);

            try {
                const res = await fetch('/rentbridge/chat.php', {
                    method: 'POST',
                    body: fd
                });
                const data = await res.json();
                if (data.ok) {
                    input.value = '';
                    // The polling will pick up the new message and append it
                    poll();
                } else {
                    alert('Could not send: ' + (data.error || 'Unknown error'));
                }
            } catch (e) {
                alert('Network error: ' + e.message);
            }
            return false;
        };
    })();
    </script>

<?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>