<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/chat.php';
require_login();

$userId = current_user_id();
$tab    = in_array($_GET['tab'] ?? '', ['messages','notifications']) ? $_GET['tab'] : 'messages';

// Mark individual notification read via GET (e.g. ?mark_read=123&redirect=/...)
if (isset($_GET['mark_read'])) {
    $nid = (int)$_GET['mark_read'];
    if ($nid > 0) {
        db()->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")
           ->execute([$nid, $userId]);
    }
    $dest = $_GET['redirect'] ?? '/rentbridge/chat.php?tab=notifications';
    header('Location: ' . $dest);
    exit;
}

$conversations = get_user_inbox($userId);

// Unread counts for tab badges
$unreadChat = chat_unread_total($userId);
$stmt = db()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$stmt->execute([$userId]);
$unreadNotif = (int)$stmt->fetchColumn();

// Notifications list (all, newest first)
$stmt = db()->prepare("
    SELECT id, type, title, message, link_url, is_read, created_at
      FROM notifications
     WHERE user_id = ?
     ORDER BY created_at DESC
     LIMIT 60
");
$stmt->execute([$userId]);
$allNotifs = $stmt->fetchAll();

if (!function_exists('_chat_notif_icon')) {
    function _chat_notif_icon(string $type): string {
        return match(true) {
            str_contains($type, 'contract')   => 'bi-file-earmark-check',
            str_contains($type, 'tenancy')    => 'bi-calendar-check',
            str_contains($type, 'agent')      => 'bi-person-badge',
            str_contains($type, 'cotenant')   => 'bi-people',
            str_contains($type, 'inspection') => 'bi-search',
            str_contains($type, 'chat')       => 'bi-chat-dots',
            default                           => 'bi-bell',
        };
    }
}

$pageTitle = 'Messages & Notifications';
$activeNav = 'chat';

ob_start();
?>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'messages' ? 'active' : '' ?>"
           href="/rentbridge/chat.php?tab=messages">
            <i class="bi bi-chat-dots me-1"></i> Messages
            <?php if ($unreadChat > 0): ?>
                <span class="badge bg-danger ms-1"><?= $unreadChat > 99 ? '99+' : $unreadChat ?></span>
            <?php endif; ?>
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $tab === 'notifications' ? 'active' : '' ?>"
           href="/rentbridge/chat.php?tab=notifications">
            <i class="bi bi-bell me-1"></i> Notifications
            <?php if ($unreadNotif > 0): ?>
                <span class="badge bg-danger ms-1"><?= $unreadNotif > 99 ? '99+' : $unreadNotif ?></span>
            <?php endif; ?>
        </a>
    </li>
</ul>

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
                                                      'student'  => '#E4F2EA',
                                                      'landlord' => '#E6ECF4',
                                                      'agent'    => '#FFF4D6',
                                                      'admin'    => '#F8D7DA',
                                                      default    => '#E2E2E2',
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

    <?php if (empty($allNotifs)): ?>
        <div class="text-center py-5 bg-white rounded-3 border">
            <i class="bi bi-bell-slash" style="font-size: 3rem; color: rgba(15,44,82,0.15);"></i>
            <h4 class="mt-3">No notifications</h4>
            <p class="text-secondary small">You're all caught up.</p>
        </div>
    <?php else: ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="text-secondary small"><?= count($allNotifs) ?> notification<?= count($allNotifs) !== 1 ? 's' : '' ?></span>
            <?php if ($unreadNotif > 0): ?>
            <form method="POST" action="/rentbridge/api/mark_notifications_read.php" class="m-0">
                <input type="hidden" name="redirect" value="/rentbridge/chat.php?tab=notifications">
                <button type="submit" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-check2-all me-1"></i> Mark all read
                </button>
            </form>
            <?php endif; ?>
        </div>

        <div class="bg-white border rounded-3 overflow-hidden">
            <?php foreach ($allNotifs as $i => $n):
                $isUnread = !(int)$n['is_read'];
                $href = $n['link_url']
                    ? '/rentbridge/chat.php?mark_read=' . (int)$n['id'] . '&redirect=' . urlencode($n['link_url'])
                    : '/rentbridge/chat.php?mark_read=' . (int)$n['id'] . '&tab=notifications';
            ?>
            <a href="<?= e($href) ?>"
               class="d-flex align-items-start gap-3 p-3 text-decoration-none text-dark <?= $i > 0 ? 'border-top' : '' ?>"
               style="background: <?= $isUnread ? '#FFFBF0' : 'white' ?>; transition: background 0.1s;"
               onmouseover="this.style.background='#FAF8F3'"
               onmouseout="this.style.background='<?= $isUnread ? '#FFFBF0' : 'white' ?>'">

                <div class="flex-shrink-0 mt-1"
                     style="width:36px; height:36px; border-radius:50%;
                            background: <?= $isUnread ? '#FFF4D6' : '#F4F4EE' ?>;
                            display:flex; align-items:center; justify-content:center;
                            color: <?= $isUnread ? '#856404' : '#6c757d' ?>;">
                    <i class="bi <?= _chat_notif_icon($n['type']) ?>"></i>
                </div>

                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start">
                        <span class="<?= $isUnread ? 'fw-semibold' : '' ?> small">
                            <?= e($n['title']) ?>
                        </span>
                        <span class="small text-secondary ms-3 text-nowrap">
                            <?= e(date('d M, H:i', strtotime($n['created_at']))) ?>
                        </span>
                    </div>
                    <?php if (!empty($n['message'])): ?>
                        <div class="small text-secondary mt-1"><?= e($n['message']) ?></div>
                    <?php endif; ?>
                </div>

                <?php if ($isUnread): ?>
                    <div class="flex-shrink-0 mt-2">
                        <span style="width:8px; height:8px; border-radius:50%; background:#dc3545; display:inline-block;"></span>
                    </div>
                <?php endif; ?>
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
