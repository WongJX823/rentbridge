<?php
/**
 * Notification bell dropdown.
 * Requires: $userId (int), db() and e() available.
 */

$_nb_pdo = db();

// Unread chat senders — distinct people who sent messages I haven't read
$_nb_chatStmt = $_nb_pdo->prepare("
    SELECT u.id,
        COALESCE(
            NULLIF(s.preferred_name, ''), s.full_name,
            NULLIF(l.preferred_name, ''), l.full_name,
            NULLIF(a.preferred_name, ''), a.full_name,
            u.email
        ) AS display_name,
        MAX(m.sent_at) AS last_msg
    FROM messages m
    JOIN conversations c ON m.conversation_id = c.id
    JOIN users u ON u.id = m.sender_id
    LEFT JOIN students  s ON s.user_id = u.id
    LEFT JOIN landlords l ON l.user_id = u.id
    LEFT JOIN agents    a ON a.user_id = u.id
    WHERE m.read_at IS NULL
      AND m.sender_id != :me
      AND (c.user_a = :me2 OR c.user_b = :me3)
    GROUP BY u.id
    ORDER BY last_msg DESC
    LIMIT 6
");
$_nb_chatStmt->execute([':me' => $userId, ':me2' => $userId, ':me3' => $userId]);
$_nb_chatSenders = $_nb_chatStmt->fetchAll(PDO::FETCH_ASSOC);
$_nb_chatCount   = count($_nb_chatSenders);

// Unread system notifications
$_nb_notifStmt = $_nb_pdo->prepare("
    SELECT * FROM notifications
    WHERE user_id = :uid AND is_read = 0
    ORDER BY created_at DESC
    LIMIT 8
");
$_nb_notifStmt->execute([':uid' => $userId]);
$_nb_notifs     = $_nb_notifStmt->fetchAll(PDO::FETCH_ASSOC);
$_nb_notifCount = count($_nb_notifs);

$_nb_total = $_nb_chatCount + $_nb_notifCount;

// Chat summary text: "Alice, Bob, and 3 more people messaged you"
$_nb_chatText = '';
if ($_nb_chatCount > 0) {
    $_nb_shown = array_slice($_nb_chatSenders, 0, 2);
    $_nb_names = array_column($_nb_shown, 'display_name');
    $_nb_more  = $_nb_chatCount - count($_nb_names);
    $_nb_chatText = implode(', ', $_nb_names);
    if ($_nb_more > 0) {
        $_nb_chatText .= ', and ' . $_nb_more . ' more ' . ($_nb_more === 1 ? 'person' : 'people');
    }
    $_nb_chatText .= ' messaged you';
}

if (!function_exists('_nb_icon')) {
    function _nb_icon(string $type): string {
        return match(true) {
            str_contains($type, 'contract')   => 'bi-file-earmark-check',
            str_contains($type, 'booking')    => 'bi-calendar-check',
            str_contains($type, 'agent')      => 'bi-person-badge',
            str_contains($type, 'cotenant')   => 'bi-people',
            str_contains($type, 'inspection') => 'bi-search',
            default                           => 'bi-bell',
        };
    }
}

if (!function_exists('_nb_time_ago')) {
    function _nb_time_ago(string $datetime): string {
        $diff = time() - strtotime($datetime);
        if ($diff < 60)    return 'Just now';
        if ($diff < 3600)  return floor($diff / 60) . 'm ago';
        if ($diff < 86400) return floor($diff / 3600) . 'h ago';
        return floor($diff / 86400) . 'd ago';
    }
}
?>

<div class="topbar-notif dropdown me-1">
    <button class="topbar-notif-btn"
            type="button"
            data-bs-toggle="dropdown"
            data-bs-auto-close="outside"
            aria-expanded="false"
            aria-label="Notifications">
        <i class="bi bi-bell"></i>
        <?php if ($_nb_total > 0): ?>
        <span class="notif-badge"><?= min($_nb_total, 99) ?></span>
        <?php endif; ?>
    </button>

    <div class="dropdown-menu dropdown-menu-end notif-panel p-0">

        <!-- Header -->
        <div class="notif-header">
            <span class="fw-semibold">Notifications</span>
            <?php if ($_nb_total > 0): ?>
            <form method="POST" action="/rentbridge/api/mark_notifications_read.php" class="d-inline m-0">
                <input type="hidden" name="redirect" value="<?= e($_SERVER['REQUEST_URI']) ?>">
                <button type="submit" class="notif-mark-all">Mark all read</button>
            </form>
            <?php endif; ?>
        </div>

        <!-- Body -->
        <div class="notif-body">
            <?php if ($_nb_total === 0): ?>
            <div class="notif-empty">
                <i class="bi bi-check-circle"></i>
                You're all caught up!
            </div>

            <?php else: ?>

                <?php if ($_nb_chatCount > 0): ?>
                <a href="/rentbridge/chat.php" class="notif-item notif-item--chat text-decoration-none">
                    <div class="notif-icon notif-icon--chat"><i class="bi bi-chat-dots"></i></div>
                    <div class="notif-content">
                        <div class="notif-title"><?= e($_nb_chatText) ?></div>
                        <div class="notif-meta">Messages</div>
                    </div>
                    <i class="bi bi-chevron-right notif-arrow"></i>
                </a>
                <?php endif; ?>

                <?php foreach ($_nb_notifs as $_nb_n): ?>
                <a href="<?= e($_nb_n['link_url'] ?? '#') ?>"
                   class="notif-item text-decoration-none"
                   data-notif-id="<?= (int)$_nb_n['id'] ?>">
                    <div class="notif-icon"><i class="bi <?= _nb_icon($_nb_n['type']) ?>"></i></div>
                    <div class="notif-content">
                        <div class="notif-title"><?= e($_nb_n['title']) ?></div>
                        <div class="notif-meta"><?= _nb_time_ago($_nb_n['created_at']) ?></div>
                    </div>
                    <i class="bi bi-chevron-right notif-arrow"></i>
                </a>
                <?php endforeach; ?>

            <?php endif; ?>
        </div>

    </div>
</div>
