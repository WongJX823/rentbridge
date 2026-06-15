<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = db();

// Mark as read
if (isset($_GET['mark_read'])) {
    $id = (int)$_GET['mark_read'];
    $stmt = $pdo->prepare("UPDATE contact_messages SET status = 'read' WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: /rentbridge/admin/messages.php');
    exit;
}

if (isset($_GET['mark_replied'])) {
    $id = (int)$_GET['mark_replied'];
    $stmt = $pdo->prepare("UPDATE contact_messages SET status = 'replied', replied_at = NOW(), replied_by = ? WHERE id = ?");
    $stmt->execute([current_user_id(), $id]);
    header('Location: /rentbridge/admin/messages.php');
    exit;
}

if (isset($_GET['archive'])) {
    $id = (int)$_GET['archive'];
    $stmt = $pdo->prepare("UPDATE contact_messages SET status = 'archived' WHERE id = ?");
    $stmt->execute([$id]);
    header('Location: /rentbridge/admin/messages.php');
    exit;
}

$statusFilter = $_GET['status'] ?? 'new';
$validStatuses = ['new', 'read', 'replied', 'archived', 'all'];
if (!in_array($statusFilter, $validStatuses, true)) $statusFilter = 'new';

$where = $statusFilter === 'all' ? '' : 'WHERE status = ?';
$params = $statusFilter === 'all' ? [] : [$statusFilter];

$stmt = $pdo->prepare("
    SELECT * FROM contact_messages
    $where
    ORDER BY created_at DESC
    LIMIT 100
");
$stmt->execute($params);
$messages = $stmt->fetchAll();

// Counts per status
$counts = ['new' => 0, 'read' => 0, 'replied' => 0, 'archived' => 0];
$stmt = $pdo->query("SELECT status, COUNT(*) as c FROM contact_messages GROUP BY status");
foreach ($stmt->fetchAll() as $r) {
    $counts[$r['status']] = (int)$r['c'];
}

$pageTitle = 'Contact Messages';
$activeNav = 'messages';

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="mb-0" style="font-family:'Fraunces',serif;">Contact messages</h1>
</div>

<!-- Status filter tabs -->
<ul class="nav nav-pills mb-4">
    <?php foreach (['new', 'read', 'replied', 'archived', 'all'] as $s):
        $count = $s === 'all' ? array_sum($counts) : ($counts[$s] ?? 0);
    ?>
        <li class="nav-item">
            <a class="nav-link <?= $statusFilter === $s ? 'active' : '' ?>"
               href="?status=<?= $s ?>">
                <?= ucfirst($s) ?>
                <span class="badge bg-secondary ms-1"><?= $count ?></span>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<?php if (empty($messages)): ?>
    <div class="bg-white border rounded-3 p-5 text-center">
        <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
        <h5 class="mt-3 mb-2">No messages</h5>
        <p class="text-secondary">No <?= e($statusFilter) ?> messages.</p>
    </div>
<?php else: ?>
    <div class="bg-white border rounded-3 overflow-hidden">
        <?php foreach ($messages as $msg):
            $statusBadge = match($msg['status']) {
                'new'      => 'bg-primary',
                'read'     => 'bg-info text-dark',
                'replied'  => 'bg-success',
                'archived' => 'bg-secondary',
            };
        ?>
            <div class="border-bottom p-4">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <strong><?= e($msg['subject']) ?></strong>
                        <span class="badge <?= $statusBadge ?> ms-2"><?= e($msg['status']) ?></span>
                        <div class="small text-secondary mt-1">
                            From <strong><?= e($msg['name']) ?></strong>
                            &lt;<a href="mailto:<?= e($msg['email']) ?>"><?= e($msg['email']) ?></a>&gt;
                            <?php if ($msg['user_id']): ?>
                                <span class="badge bg-light text-dark">Logged-in user</span>
                            <?php else: ?>
                                <span class="badge bg-light text-dark">Guest</span>
                            <?php endif; ?>
                        </div>
                        <div class="small text-secondary">
                            <i class="bi bi-clock"></i>
                            <?= e(date('d M Y, H:i', strtotime($msg['created_at']))) ?>
                        </div>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <?php if ($msg['status'] === 'new'): ?>
                            <a href="?mark_read=<?= (int)$msg['id'] ?>" class="btn btn-outline-info">Mark read</a>
                        <?php endif; ?>
                        <?php if (in_array($msg['status'], ['new', 'read'], true)): ?>
                            <a href="mailto:<?= e($msg['email']) ?>?subject=Re: <?= rawurlencode($msg['subject']) ?>"
                               class="btn btn-outline-primary">Reply via email</a>
                            <a href="?mark_replied=<?= (int)$msg['id'] ?>" class="btn btn-outline-success">Mark replied</a>
                        <?php endif; ?>
                        <?php if ($msg['status'] !== 'archived'): ?>
                            <a href="?archive=<?= (int)$msg['id'] ?>" class="btn btn-outline-secondary">Archive</a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="mt-3 p-3 bg-light rounded">
                    <?= nl2br(e($msg['message'])) ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/admin_layout.php';