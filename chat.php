<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/chat.php';
require_login();

$userId = current_user_id();
$tab    = in_array($_GET['tab'] ?? '', ['messages','notifications']) ? $_GET['tab'] : 'messages';

$conversations = get_user_inbox($userId);

// Fetch notifications (unread first, then recent read ones)
$pdo  = db();
$stmt = $pdo->prepare("
    SELECT * FROM notifications
     WHERE user_id = ?
     ORDER BY is_read ASC, created_at DESC
     LIMIT 50
");
$stmt->execute([$userId]);
$allNotifications  = $stmt->fetchAll();
$unreadNotifCount  = count(array_filter($allNotifications, fn($n) => !(int)$n['is_read']));

// Auto-mark all notifications as read when user opens the tab
if ($tab === 'notifications' && $unreadNotifCount > 0) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0")
        ->execute([$userId]);
    // Reflect immediately in the list
    foreach ($allNotifications as &$n) { $n['is_read'] = 1; }
    unset($n);
    $unreadNotifCount = 0;
}

$unreadMsgCount = array_sum(array_column($conversations, 'unread_count'));

function notif_icon(string $type): string {
    return match(true) {
        str_contains($type, 'contract')   => 'bi-file-earmark-check',
        str_contains($type, 'booking')    => 'bi-calendar-check',
        str_contains($type, 'agent')      => 'bi-person-badge',
        str_contains($type, 'cotenant') || str_contains($type, 'co_tenant') => 'bi-people',
        str_contains($type, 'manual')     => 'bi-pencil',
        str_contains($type, 'inspection') => 'bi-search',
        str_contains($type, 'transfer')   => 'bi-arrow-left-right',
        str_contains($type, 'property')   => 'bi-house-door',
        default                           => 'bi-bell',
    };
}

function notif_time(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)    return 'Just now';
    if ($diff < 3600)  return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('d M Y', strtotime($datetime));
}

$pageTitle = 'Messages';
$activeNav = 'chat';

ob_start();
?>

<!-- Tab bar -->
<div class="d-flex gap-1 mb-4" style="border-bottom: 2px solid #E5E1D8;">
    <a href="/rentbridge/chat.php?tab=messages"
       class="px-4 py-2 text-decoration-none fw-semibold small d-flex align-items-center gap-2"
       style="border-bottom: 3px solid <?= $tab === 'messages' ? '#0F2C52' : 'transparent' ?>;
              color: <?= $tab === 'messages' ? '#0F2C52' : '#6B7B91' ?>;
              margin-bottom: -2px;">
        <i class="bi bi-chat-dots"></i> Messages
        <?php if ($unreadMsgCount > 0): ?>
            <span class="badge bg-danger rounded-pill"><?= $unreadMsgCount ?></span>
        <?php endif; ?>
    </a>
    <a href="/rentbridge/chat.php?tab=notifications"
       class="px-4 py-2 text-decoration-none fw-semibold small d-flex align-items-center gap-2"
       style="border-bottom: 3px solid <?= $tab === 'notifications' ? '#0F2C52' : 'transparent' ?>;
              color: <?= $tab === 'notifications' ? '#0F2C52' : '#6B7B91' ?>;
              margin-bottom: -2px;">
        <i class="bi bi-bell"></i> Notifications
        <?php if ($unreadNotifCount > 0): ?>
            <span class="badge bg-danger rounded-pill"><?= $unreadNotifCount ?></span>
        <?php endif; ?>
    </a>
</div>

<?php if ($tab === 'messages'): ?>

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
                $isGroup  = ($c['context_type'] === 'housemate_group');
                $other    = $isGroup ? null : chat_get_user_display((int)$c['other_user_id']);
                $unread   = (int)$c['unread_count'];
                $isLocked = (int)($c['is_locked'] ?? 0) === 1;
            ?>
                <a href="/rentbridge/chat/conversation.php?id=<?= (int)$c['id'] ?>"
                   class="d-block text-decoration-none text-dark <?= $i > 0 ? 'border-top' : '' ?>"
                   style="transition: background 0.1s;"
                   onmouseover="this.style.background='#FAF8F3'"
                   onmouseout="this.style.background='white'">
                    <div class="p-3 d-flex justify-content-between align-items-start">
                        <div class="d-flex gap-3 flex-grow-1 me-3">
                            <?php if ($isGroup): ?>
                                <div style="width:44px; height:44px; border-radius:50%; background:#E4F2EA;
                                            color:#0F2C52; display:flex; align-items:center; justify-content:center;
                                            font-size:1.2rem; flex-shrink:0;">
                                    <i class="bi bi-people-fill"></i>
                                </div>
                            <?php else: ?>
                                <?php
                                require_once __DIR__ . '/includes/avatar.php';
                                $_otherAvatar = get_avatar_path((int)$other['id'], $other['primary_role']);
                                ?>
                                <?php render_avatar($_otherAvatar, $other['name'], 44); ?>
                            <?php endif; ?>
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <?php if ($isGroup): ?>
                                        <strong>Housemate Group</strong>
                                        <span class="badge bg-success" style="font-weight:500;">Group</span>
                                    <?php else: ?>
                                        <strong><?= e($other['name']) ?></strong>
                                        <span class="badge"
                                              style="background: <?php
                                                  echo match($other['primary_role']) {
                                                      'student'=>'#E4F2EA','landlord'=>'#E6ECF4',
                                                      'agent'=>'#FFF4D6','admin'=>'#F8D7DA',
                                                      default=>'#E2E2E2',
                                                  };
                                              ?>; color:#0F2C52; font-weight:500;">
                                            <?= e(ucfirst($other['primary_role'])) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($isLocked): ?>
                                        <span class="badge bg-secondary" title="Closed">
                                            <i class="bi bi-lock-fill"></i>
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

<?php else: /* notifications tab */ ?>

    <?php if (empty($allNotifications)): ?>
        <div class="text-center py-5 bg-white rounded-3 border">
            <i class="bi bi-bell-slash" style="font-size: 3rem; color: rgba(15,44,82,0.15);"></i>
            <h4 class="mt-3">No notifications</h4>
            <p class="text-secondary small">You're all caught up!</p>
        </div>
    <?php else: ?>
        <div class="bg-white border rounded-3 overflow-hidden">
            <?php foreach ($allNotifications as $i => $notif):
                $isUnread = !(int)$notif['is_read'];
                $icon     = notif_icon($notif['type']);
                $link     = $notif['link_url'] ?? '#';
            ?>
                <a href="<?= e($link) ?>"
                   class="d-block text-decoration-none text-dark <?= $i > 0 ? 'border-top' : '' ?>"
                   style="background: <?= $isUnread ? '#FFFBF0' : 'white' ?>; transition: background 0.1s;"
                   onmouseover="this.style.background='#FAF8F3'"
                   onmouseout="this.style.background='<?= $isUnread ? '#FFFBF0' : 'white' ?>'">
                    <div class="p-3 d-flex align-items-start gap-3">
                        <!-- Icon -->
                        <div style="width:40px; height:40px; border-radius:50%; flex-shrink:0;
                                    background: <?= $isUnread ? '#FFF4D6' : '#F0F0EF' ?>;
                                    display:flex; align-items:center; justify-content:center;">
                            <i class="bi <?= $icon ?>"
                               style="color: <?= $isUnread ? '#D4A017' : '#6B7B91' ?>; font-size:1.1rem;"></i>
                        </div>
                        <!-- Content -->
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between align-items-start">
                                <strong class="small <?= $isUnread ? 'text-dark' : 'text-secondary' ?>">
                                    <?= e($notif['title']) ?>
                                </strong>
                                <span class="small text-secondary ms-3 text-nowrap">
                                    <?= notif_time($notif['created_at']) ?>
                                </span>
                            </div>
                            <div class="small text-secondary mt-1"><?= e($notif['message']) ?></div>
                        </div>
                        <?php if ($isUnread): ?>
                            <div style="width:8px; height:8px; border-radius:50%; background:#DC3545;
                                        flex-shrink:0; margin-top:6px;"></div>
                        <?php endif; ?>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

<?php endif; ?>

<?php
$pageContent = ob_get_clean();
$role = current_role();
if ($role === 'student') {
    require __DIR__ . '/includes/student_layout.php';
} elseif ($role === 'landlord') {
    require __DIR__ . '/includes/landlord_layout.php';
} elseif ($role === 'agent') {
    require __DIR__ . '/includes/agent_layout.php';
} else {
    require __DIR__ . '/includes/admin_layout.php';
}
