<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/friends.php';
require_role('student');

$userId = current_user_id();
$tab    = $_GET['tab'] ?? 'my';
if (!in_array($tab, ['my', 'find', 'requests'], true)) $tab = 'my';

// ---- HANDLE POST ACTIONS (accept/reject/cancel/remove/send request) ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action   = $_POST['action'] ?? '';
    $reqId    = (int)($_POST['request_id'] ?? 0);
    $friendId = (int)($_POST['friend_id']   ?? 0);
    $receiver = (int)($_POST['receiver_id'] ?? 0);
    $message  = trim($_POST['message'] ?? '');

    if ($action === 'accept' && $reqId > 0) {
        [$ok, $err] = accept_friend_request($reqId, $userId);
        set_flash($ok ? 'success' : 'danger', $ok ? 'Connected!' : $err);
    }
    elseif ($action === 'reject' && $reqId > 0) {
        reject_friend_request($reqId, $userId);
        set_flash('info', 'Request declined.');
    }
    elseif ($action === 'cancel' && $reqId > 0) {
        cancel_friend_request($reqId, $userId);
        set_flash('info', 'Request cancelled.');
    }
    elseif ($action === 'remove' && $friendId > 0) {
        remove_friend($userId, $friendId);
        set_flash('info', 'Removed from housemates.');
    }
    elseif ($action === 'send' && $receiver > 0) {
        [$ok, $err] = send_friend_request($userId, $receiver, $message);
        set_flash($ok ? 'success' : 'danger', $ok ? 'Request sent!' : $err);
    }

    header('Location: housemates.php?tab=' . $tab);
    exit;
}

// ---- LOAD DATA FOR EACH TAB ----
$friends         = get_friends($userId);
$incomingPending = get_pending_requests_received($userId);
$outgoingPending = get_pending_requests_sent($userId);

$searchQuery = trim($_GET['q'] ?? '');
$filterCity  = trim($_GET['city'] ?? '');
$filterRent  = trim($_GET['max_rent'] ?? '');

$searchResults  = ($searchQuery !== '') ? search_students_to_befriend($userId, $searchQuery) : [];
$discoverList   = ($tab === 'find' && $searchQuery === '')
                  ? discover_housemates($userId, ['city' => $filterCity, 'max_rent' => $filterRent])
                  : [];

$myProfile = get_my_housing_profile($userId);
$flash     = get_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Housemates · RentBridge</title>
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
            <h1 class="mb-1">Housemates</h1>
            <p class="text-secondary mb-0">Connect with classmates, find housemates, manage your housing network.</p>
        </div>
        <a href="/rentbridge/student/housemates_profile.php" class="btn btn-ghost">
            <i class="bi bi-person-gear me-1"></i> My housing profile
        </a>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endif; ?>

    <!-- Status banner: am I looking for housing? -->
    <?php if (!$myProfile || !$myProfile['looking_for_housing']): ?>
        <div class="alert alert-info d-flex align-items-center gap-3 mb-4">
            <i class="bi bi-info-circle fs-4"></i>
            <div class="flex-grow-1">
                <strong>You're not visible to other housemate-seekers</strong>
                <div class="small">Switch on "I'm looking for housing" in your profile to appear in discovery.</div>
            </div>
            <a href="/rentbridge/student/housemates_profile.php" class="btn btn-sm btn-primary">
                Update profile
            </a>
        </div>
    <?php else: ?>
        <div class="alert alert-success d-flex align-items-center gap-3 mb-4">
            <i class="bi bi-check-circle-fill fs-4"></i>
            <div class="flex-grow-1">
                <strong>You're visible to other housemate-seekers</strong>
                <div class="small">
                    Looking in <?= e($myProfile['housing_pref_city'] ?: 'any area') ?>
                    <?php if (!empty($myProfile['housing_pref_max_rent'])): ?>
                        · up to RM <?= number_format((float)$myProfile['housing_pref_max_rent']) ?>/mo
                    <?php endif; ?>
                </div>
            </div>
            <a href="/rentbridge/student/housemates_profile.php" class="btn btn-sm btn-outline-success">
                Edit
            </a>
        </div>
    <?php endif; ?>

    <!-- Tabs -->
    <ul class="nav nav-pills mb-4">
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'my' ? 'active' : '' ?>" href="?tab=my">
                <i class="bi bi-people-fill me-1"></i> My housemates
                <span class="badge bg-light text-dark ms-1"><?= count($friends) ?></span>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'find' ? 'active' : '' ?>" href="?tab=find">
                <i class="bi bi-search me-1"></i> Find housemates
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $tab === 'requests' ? 'active' : '' ?>" href="?tab=requests">
                <i class="bi bi-inbox me-1"></i> Requests
                <?php if (!empty($incomingPending)): ?>
                    <span class="badge bg-warning text-dark ms-1"><?= count($incomingPending) ?></span>
                <?php endif; ?>
            </a>
        </li>
    </ul>

    <!-- ===================== TAB 1: MY HOUSEMATES ===================== -->
    <?php if ($tab === 'my'): ?>
        <?php if (empty($friends)): ?>
            <div class="text-center py-5 bg-white rounded-3 border">
                <i class="bi bi-people" style="font-size: 3rem; color: var(--rb-line);"></i>
                <h4 class="mt-3">No housemates yet</h4>
                <p class="text-secondary">Find classmates to coordinate your housing search with.</p>
                <a href="?tab=find" class="btn btn-primary">
                    <i class="bi bi-search me-1"></i> Find housemates
                </a>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($friends as $f): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="bg-white border rounded-3 p-3 h-100">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?= e($f['preferred_name']) ?></h6>
                                    <small class="text-secondary d-block"><?= e($f['full_name']) ?></small>
                                    <small class="text-secondary d-block">
                                        <i class="bi bi-mortarboard"></i> <?= e($f['matric_no']) ?>
                                    </small>
                                </div>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-ghost p-1" data-bs-toggle="dropdown">
                                        <i class="bi bi-three-dots"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li>
                                            <form method="POST" class="m-0">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="action" value="remove">
                                                <input type="hidden" name="friend_id" value="<?= (int)$f['friend_id'] ?>">
                                                <button class="dropdown-item text-danger"
                                                        onclick="return confirm('Remove <?= e($f['preferred_name']) ?>?');">
                                                    <i class="bi bi-person-x me-1"></i> Remove
                                                </button>
                                            </form>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <small class="text-secondary d-block mb-3">
                                Since <?= e(date('d M Y', strtotime($f['became_friends_at']))) ?>
                            </small>
                            <div class="d-flex gap-2">
                                <a href="/rentbridge/chat/start.php?type=friend&friend_id=<?= (int)$f['friend_id'] ?>"
                                   class="btn btn-sm btn-outline-primary flex-fill">
                                    <i class="bi bi-chat-dots me-1"></i> Message
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <!-- ===================== TAB 2: FIND HOUSEMATES ===================== -->
    <?php elseif ($tab === 'find'): ?>

        <!-- Search bar -->
        <form method="GET" class="bg-white border rounded-3 p-3 mb-4">
            <input type="hidden" name="tab" value="find">
            <div class="row g-2">
                <div class="col-md-5">
                    <input type="text" name="q" value="<?= e($searchQuery) ?>"
                           class="form-control" placeholder="Search by name, matric, or email">
                </div>
                <div class="col-md-3">
                    <input type="text" name="city" value="<?= e($filterCity) ?>"
                           class="form-control" placeholder="City (e.g. Ayer Keroh)">
                </div>
                <div class="col-md-2">
                    <input type="number" name="max_rent" value="<?= e($filterRent) ?>"
                           class="form-control" placeholder="Max RM/mo">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search"></i> Search
                    </button>
                </div>
            </div>
            <small class="text-secondary">Search by name OR filter by housing preferences.</small>
        </form>

        <?php if ($searchQuery !== ''): ?>
            <!-- Direct search results -->
            <h6 class="text-secondary text-uppercase small mb-3">
                Search results for "<?= e($searchQuery) ?>" (<?= count($searchResults) ?>)
            </h6>
            <?php if (empty($searchResults)): ?>
                <div class="text-center py-5 bg-white rounded-3 border">
                    <p class="text-secondary mb-0">
                        No matches. They might already be your housemate, or have a pending request with you.
                    </p>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($searchResults as $u): ?>
                        <?php include __DIR__ . '/_housemate_card.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <!-- Discovery feed -->
            <h6 class="text-secondary text-uppercase small mb-3">
                <i class="bi bi-fire text-warning"></i>
                Students looking for housing (<?= count($discoverList) ?>)
            </h6>
            <?php if (empty($discoverList)): ?>
                <div class="text-center py-5 bg-white rounded-3 border">
                    <i class="bi bi-search" style="font-size: 3rem; color: var(--rb-line);"></i>
                    <h5 class="mt-3">No housemate-seekers right now</h5>
                    <p class="text-secondary small">
                        Try adjusting filters, or search for someone by name above.
                    </p>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($discoverList as $u): ?>
                        <?php include __DIR__ . '/_housemate_card.php'; ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

    <!-- ===================== TAB 3: REQUESTS ===================== -->
    <?php elseif ($tab === 'requests'): ?>

        <h6 class="text-secondary text-uppercase small mb-3">
            <i class="bi bi-inbox-fill"></i> Incoming requests (<?= count($incomingPending) ?>)
        </h6>
        <?php if (empty($incomingPending)): ?>
            <div class="bg-white border rounded-3 p-4 mb-4 text-center text-secondary">
                <small>No pending requests to review.</small>
            </div>
        <?php else: ?>
            <div class="bg-white border rounded-3 p-3 mb-4">
                <?php foreach ($incomingPending as $r): ?>
                    <div class="d-flex justify-content-between align-items-start py-2 border-bottom">
                        <div class="flex-grow-1">
                            <strong><?= e($r['preferred_name']) ?></strong>
                            <small class="text-secondary">
                                — <?= e($r['matric_no']) ?> ·
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
                                        onclick="return confirm('Decline this request?');">
                                    <i class="bi bi-x-lg"></i>
                                </button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <h6 class="text-secondary text-uppercase small mb-3">
            <i class="bi bi-send"></i> Outgoing requests (<?= count($outgoingPending) ?>)
        </h6>
        <?php if (empty($outgoingPending)): ?>
            <div class="bg-white border rounded-3 p-4 text-center text-secondary">
                <small>You haven't sent any pending requests.</small>
            </div>
        <?php else: ?>
            <div class="bg-white border rounded-3 p-3">
                <?php foreach ($outgoingPending as $r): ?>
                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                        <div>
                            <strong><?= e($r['preferred_name']) ?></strong>
                            <small class="text-secondary">
                                — Sent <?= e(date('d M Y', strtotime($r['created_at']))) ?>
                            </small>
                        </div>
                        <form method="POST" class="d-inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="cancel">
                            <input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>">
                            <button class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-x"></i> Cancel
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>