<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/friends.php';
require_role('student');

$userId = current_user_id();
$errors = [];

// ---- HANDLE ACTIONS ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $reqId  = (int)($_POST['request_id'] ?? 0);
    $friendId = (int)($_POST['friend_id'] ?? 0);

    if ($action === 'accept' && $reqId > 0) {
        [$ok, $err] = accept_friend_request($reqId, $userId);
        if ($ok) set_flash('success', 'Friend request accepted!');
        else $errors['general'] = $err;
    }
    elseif ($action === 'reject' && $reqId > 0) {
        [$ok, $err] = reject_friend_request($reqId, $userId);
        if ($ok) set_flash('info', 'Friend request rejected.');
        else $errors['general'] = $err;
    }
    elseif ($action === 'cancel' && $reqId > 0) {
        [$ok, $err] = cancel_friend_request($reqId, $userId);
        if ($ok) set_flash('info', 'Friend request cancelled.');
        else $errors['general'] = $err;
    }
    elseif ($action === 'remove' && $friendId > 0) {
        if (remove_friend($userId, $friendId)) {
            set_flash('info', 'Friend removed.');
        }
    }

    if (empty($errors)) {
        header('Location: friends.php');
        exit;
    }
}

$friends         = get_friends($userId);
$incomingPending = get_pending_requests_received($userId);
$outgoingPending = get_pending_requests_sent($userId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My friends · Student · RentBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body style="background: var(--rb-cream);">

<?php include '../includes/header.php'; ?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-end mb-4">
        <div>
            <h1 class="mb-1">My friends</h1>
            <p class="text-secondary mb-0">
                <?= count($friends) ?> friend<?= count($friends) === 1 ? '' : 's' ?>
                <?php if (!empty($incomingPending)): ?>
                    · <span class="text-warning fw-semibold"><?= count($incomingPending) ?> pending request<?= count($incomingPending) === 1 ? '' : 's' ?></span>
                <?php endif; ?>
            </p>
        </div>
        <a href="/rentbridge/student/add_friend.php" class="btn btn-success">
            <i class="bi bi-person-plus me-1"></i> Add friend
        </a>
    </div>

    <?php $flash = get_flash(); if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-danger"><?= e($errors['general']) ?></div>
    <?php endif; ?>

    <!-- Incoming friend requests -->
    <?php if (!empty($incomingPending)): ?>
        <div class="bg-white border rounded-3 p-4 mb-4" style="border-left: 4px solid #D4A017 !important;">
            <h5 class="mb-3">
                <i class="bi bi-person-down text-warning"></i>
                Friend requests (<?= count($incomingPending) ?>)
            </h5>
            <?php foreach ($incomingPending as $r): ?>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div>
                        <strong><?= e($r['preferred_name']) ?></strong>
                        <small class="text-secondary">
                            (<?= e($r['full_name']) ?>) ·
                            <?= e($r['matric_no']) ?> ·
                            <?= e(date('d M Y', strtotime($r['created_at']))) ?>
                        </small>
                        <?php if (!empty($r['message'])): ?>
                            <div class="small text-secondary mt-1">
                                <i class="bi bi-chat-quote"></i> "<?= e($r['message']) ?>"
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex gap-2">
                        <form method="POST" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="accept">
                            <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                            <button class="btn btn-sm btn-success">
                                <i class="bi bi-check-lg"></i> Accept
                            </button>
                        </form>
                        <form method="POST" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                            <button class="btn btn-sm btn-outline-secondary"
                                    onclick="return confirm('Reject this request?');">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Friends list -->
    <h5 class="mb-3">Your friends</h5>
    <?php if (empty($friends)): ?>
        <div class="text-center py-5 bg-white rounded-3 border">
            <i class="bi bi-people" style="font-size: 3rem; color: var(--rb-line);"></i>
            <h4 class="mt-3">No friends yet</h4>
            <p class="text-secondary">Connect with classmates to coordinate housing.</p>
            <a href="/rentbridge/student/add_friend.php" class="btn btn-primary">
                <i class="bi bi-person-plus me-1"></i> Find friends
            </a>
        </div>
    <?php else: ?>
        <div class="row g-3 mb-4">
            <?php foreach ($friends as $f): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="bg-white border rounded-3 p-3">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h6 class="mb-1"><?= e($f['preferred_name']) ?></h6>
                                <small class="text-secondary d-block">
                                    <?= e($f['full_name']) ?>
                                </small>
                                <small class="text-secondary d-block">
                                    <?= e($f['matric_no']) ?>
                                </small>
                                <small class="text-secondary d-block">
                                    Friends since <?= e(date('d M Y', strtotime($f['became_friends_at']))) ?>
                                </small>
                            </div>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-ghost" data-bs-toggle="dropdown">
                                    <i class="bi bi-three-dots"></i>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <form method="POST" class="m-0">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action" value="remove">
                                            <input type="hidden" name="friend_id" value="<?= (int)$f['friend_id'] ?>">
                                            <button class="dropdown-item text-danger"
                                                    onclick="return confirm('Remove <?= e($f['preferred_name']) ?> from friends?');">
                                                <i class="bi bi-person-x me-1"></i> Remove friend
                                            </button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Outgoing pending requests -->
    <?php if (!empty($outgoingPending)): ?>
        <h5 class="mb-3 mt-5">Requests you've sent</h5>
        <div class="bg-white border rounded-3 p-3">
            <?php foreach ($outgoingPending as $r): ?>
                <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                    <div>
                        <strong><?= e($r['preferred_name']) ?></strong>
                        <small class="text-secondary">
                            (<?= e($r['full_name']) ?>) ·
                            <?= e($r['matric_no']) ?> ·
                            Sent <?= e(date('d M Y', strtotime($r['created_at']))) ?>
                        </small>
                    </div>
                    <div>
                        <span class="badge bg-warning text-dark me-2">Pending</span>
                        <form method="POST" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                            <button class="btn btn-sm btn-outline-secondary"
                                    onclick="return confirm('Cancel this request?');">
                                <i class="bi bi-x"></i> Cancel
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>