<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/chat.php';
require_login();

$userId = current_user_id();
$conversations = chat_get_inbox($userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Chat · RentBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="/rentbridge/assets/css/style.css" rel="stylesheet">
</head>
<body style="background: var(--rb-cream);">

<?php include 'includes/header.php'; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <h1 class="mb-1">Chat</h1>
            <p class="text-secondary mb-4">Your conversations with landlords, agents, and students.</p>

            <?php if (empty($conversations)): ?>
                <div class="text-center py-5 bg-white rounded-3 border">
                    <i class="bi bi-chat-dots" style="font-size: 3rem; color: rgba(15,44,82,0.15);"></i>
                    <h4 class="mt-3">No conversations yet</h4>
                    <p class="text-secondary small">
                        Start a chat by clicking "Open RentBridge chat" on any property page.
                    </p>
                </div>
            <?php else: ?>
                <div class="bg-white border rounded-3 overflow-hidden">
                    <?php foreach ($conversations as $i => $c):
                        $other = chat_get_user_display((int)$c['other_user_id']);
                        $unread = (int)$c['unread_count'];
                        $isLocked = (int)$c['is_locked'] === 1;
                    ?>
                        <a href="/rentbridge/chat/conversation.php?id=<?= (int)$c['id'] ?>"
                           class="d-block text-decoration-none text-dark
                                  <?= $i > 0 ? 'border-top' : '' ?>"
                           style="transition: background 0.1s;"
                           onmouseover="this.style.background='#FAF8F3'"
                           onmouseout="this.style.background='white'">
                            <div class="p-3 d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1 me-3">
                                    <div class="d-flex align-items-center gap-2 mb-1">
                                        <strong><?= e($other['name']) ?></strong>
                                        <span class="badge"
                                              style="background: <?php
                                                  echo match($other['primary_role']) {
                                                      'student'  => '#E4F2EA',
                                                      'landlord' => '#E6ECF4',
                                                      'agent'    => '#FFF4D6',
                                                      'admin'    => '#F8D7DA',
                                                      default    => '#E2E2E2',
                                                  };
                                              ?>; color:#0F2C52; font-weight:500;">
                                            <?= e(ucfirst($other['primary_role'])) ?>
                                        </span>
                                        <?php if ($isLocked): ?>
                                            <span class="badge bg-secondary" title="Conversation closed">
                                                <i class="bi bi-lock-fill"></i> Closed
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($c['property_title'])): ?>
                                        <div class="small text-secondary mb-1">
                                            <i class="bi bi-house-door"></i> <?= e($c['property_title']) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($c['last_message_preview'])): ?>
                                        <div class="small text-secondary">
                                            <?php if ((int)$c['last_sender_id'] === $userId): ?>
                                                <span class="text-secondary">You: </span>
                                            <?php endif; ?>
                                            <?= e($c['last_message_preview']) ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="small text-secondary fst-italic">No messages yet</div>
                                    <?php endif; ?>
                                </div>
                                <div class="text-end">
                                    <?php if (!empty($c['last_message_at'])): ?>
                                        <div class="small text-secondary mb-1">
                                            <?= e(date('d M, H:i', strtotime($c['last_message_at']))) ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($unread > 0): ?>
                                        <span class="badge bg-danger rounded-pill"><?= $unread ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>