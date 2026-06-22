<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/partners.php';
require_role('student');

$pdo = db();
$userId = current_user_id();
$postId = (int)($_GET['id'] ?? 0);

if ($postId <= 0) {
    set_flash('danger', 'Invalid post.');
    header('Location: /rentbridge/student/partners.php');
    exit;
}

// Verify this user is the poster
$stmt = $pdo->prepare("SELECT * FROM co_tenancy_posts WHERE id = ? AND poster_id = ?");
$stmt->execute([$postId, $userId]);
$post = $stmt->fetch();
if (!$post) {
    set_flash('danger', 'Post not found or not yours.');
    header('Location: /rentbridge/student/partners.php');
    exit;
}

// Fetch property details
$stmt = $pdo->prepare("SELECT title, city, monthly_rent FROM properties WHERE id = ?");
$stmt->execute([$post['property_id']]);
$property = $stmt->fetch();

// Handle POST actions
$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'respond') {
        $appId    = (int)($_POST['application_id'] ?? 0);
        $decision = $_POST['decision'] ?? '';
        if ($appId > 0 && in_array($decision, ['accepted', 'rejected'], true)) {
            [$ok, $err, $groupConvId] = respond_to_application($appId, $userId, $decision);
            if ($ok) {
                $msg = $decision === 'accepted' ? 'Applicant accepted.' : 'Applicant rejected.';
                if ($groupConvId) {
                    $msg .= ' Group is full — <a href="/rentbridge/chat/conversation.php?id=' . $groupConvId . '">group chat created</a>.';
                }
                $flash = ['type' => 'success', 'html' => $msg];
            } else {
                $flash = ['type' => 'danger', 'html' => e($err)];
            }
        }
    } elseif ($action === 'cancel_post') {
        cancel_co_tenancy_post($postId, $userId);
        set_flash('info', 'Post cancelled.');
        header('Location: /rentbridge/student/partners.php');
        exit;
    }

    // Reload post after changes
    $stmt = $pdo->prepare("SELECT * FROM co_tenancy_posts WHERE id = ? AND poster_id = ?");
    $stmt->execute([$postId, $userId]);
    $post = $stmt->fetch();
}

$applications = get_post_applications($postId, $userId);
$perPerson    = (float)($property['monthly_rent'] ?? 0) / max(1, (int)$post['housemates_needed'] + 1);
$acceptedCount = count(array_filter($applications, fn($a) => $a['status'] === 'accepted'));
$pendingCount  = count(array_filter($applications, fn($a) => $a['status'] === 'pending'));
$groupConvId   = (int)($post['group_conversation_id'] ?? 0);

$pageTitle = 'Manage Post';
$activeNav = 'partners';

ob_start();
?>

<p class="small mb-3">
    <a href="/rentbridge/student/partners.php" class="text-secondary text-decoration-none">
        <i class="bi bi-arrow-left"></i> Back to Find Housemates
    </a>
</p>

<?php if ($flash): ?>
<div class="alert alert-<?= e($flash['type']) ?>">
    <?= $flash['html'] ?>
</div>
<?php endif; ?>

<!-- Post header -->
<div class="bg-white border rounded-3 p-4 mb-4">
    <div class="d-flex justify-content-between align-items-start mb-3">
        <div>
            <h4 class="mb-1"><?= e($property['title'] ?? 'Your post') ?></h4>
            <div class="text-secondary small">
                <i class="bi bi-geo-alt"></i> <?= e($property['city'] ?? '') ?>
                · RM <?= number_format((float)($property['monthly_rent'] ?? 0)) ?>/mo total
                · <strong class="text-emerald">RM <?= number_format($perPerson) ?>/person</strong>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php
            $statusColors = ['open' => 'success', 'filled' => 'primary', 'cancelled' => 'secondary', 'expired' => 'secondary'];
            $statusColor  = $statusColors[$post['status']] ?? 'secondary';
            ?>
            <span class="badge bg-<?= $statusColor ?>">
                <?= ucfirst($post['status']) ?>
            </span>
            <?php if ($post['status'] === 'open'): ?>
            <form method="POST" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="cancel_post">
                <button type="submit" class="btn btn-sm btn-outline-danger"
                        onclick="return confirm('Close this post?');">
                    <i class="bi bi-x-circle"></i> Close post
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Stats row -->
    <div class="d-flex gap-4 small">
        <div>
            <span class="text-secondary">Looking for</span>
            <strong class="ms-1"><?= (int)$post['housemates_needed'] ?> more</strong>
        </div>
        <div>
            <span class="text-secondary">Accepted</span>
            <strong class="ms-1 text-success"><?= $acceptedCount ?></strong>
        </div>
        <div>
            <span class="text-secondary">Pending</span>
            <strong class="ms-1 text-warning"><?= $pendingCount ?></strong>
        </div>
        <?php if ($groupConvId > 0): ?>
        <div>
            <a href="/rentbridge/chat/conversation.php?id=<?= $groupConvId ?>"
               class="btn btn-sm btn-outline-primary">
                <i class="bi bi-chat-dots me-1"></i> Group chat
            </a>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($post['message'])): ?>
    <div class="mt-3 pt-3 border-top small text-secondary" style="white-space:pre-line;">
        <?= e($post['message']) ?>
    </div>
    <?php endif; ?>
</div>

<!-- Applications -->
<h5 class="mb-3">
    Applications
    <?php if ($pendingCount > 0): ?>
        <span class="badge bg-warning text-dark"><?= $pendingCount ?> pending</span>
    <?php endif; ?>
</h5>

<?php if (empty($applications)): ?>
<div class="bg-white border rounded-3 p-5 text-center text-secondary">
    <i class="bi bi-inbox" style="font-size:2.5rem; opacity:0.3;"></i>
    <p class="mt-3 mb-0">No applications yet. Your post is visible to other students.</p>
</div>
<?php else: ?>
<div class="d-flex flex-column gap-3">
    <?php foreach ($applications as $app):
        $statusBadge = match($app['status']) {
            'pending'  => ['warning', 'Pending'],
            'accepted' => ['success', 'Accepted'],
            'rejected' => ['secondary', 'Rejected'],
            default    => ['secondary', $app['status']],
        };
    ?>
    <div class="bg-white border rounded-3 p-4 <?= $app['status'] === 'accepted' ? 'border-success' : '' ?>">
        <div class="d-flex justify-content-between align-items-start">
            <div class="d-flex gap-3 align-items-start flex-grow-1">
                <!-- Avatar -->
                <div style="width:40px; height:40px; border-radius:50%; background:#E6ECF4;
                            color:#0F2C52; display:flex; align-items:center; justify-content:center;
                            font-weight:700; flex-shrink:0;">
                    <?= strtoupper(substr($app['applicant_nick'] ?: $app['applicant_name'], 0, 1)) ?>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <strong><?= e($app['applicant_nick'] ?: $app['applicant_name']) ?></strong>
                        <span class="badge bg-<?= $statusBadge[0] ?> small"><?= $statusBadge[1] ?></span>
                    </div>
                    <div class="small text-secondary mb-2">
                        <i class="bi bi-mortarboard"></i> <?= e($app['matric_no']) ?>
                        <?php if (!empty($app['housing_pref_city'])): ?>
                            · Looking in <?= e($app['housing_pref_city']) ?>
                        <?php endif; ?>
                        <?php if (!empty($app['housing_pref_max_rent'])): ?>
                            · Budget RM <?= number_format((float)$app['housing_pref_max_rent']) ?>
                        <?php endif; ?>
                    </div>

                    <!-- Applicant's housing bio -->
                    <?php if (!empty($app['housing_bio'])): ?>
                    <div class="mb-2 p-2 rounded small"
                         style="background:#F4F4EE; font-size:0.82rem;">
                        <strong>Bio:</strong> "<?= e($app['housing_bio']) ?>"
                    </div>
                    <?php endif; ?>

                    <!-- Applicant's message -->
                    <div class="p-2 rounded small border" style="font-size:0.85rem; white-space:pre-line;">
                        <?= e($app['message']) ?>
                    </div>
                    <div class="text-secondary" style="font-size:0.75rem; margin-top:4px;">
                        Applied <?= date('d M Y, H:i', strtotime($app['created_at'])) ?>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <?php if ($app['status'] === 'pending' && $post['status'] === 'open'): ?>
            <div class="d-flex gap-2 ms-3 flex-shrink-0">
                <form method="POST" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="respond">
                    <input type="hidden" name="application_id" value="<?= (int)$app['id'] ?>">
                    <input type="hidden" name="decision" value="accepted">
                    <button type="submit" class="btn btn-sm btn-success">
                        <i class="bi bi-check-lg"></i> Accept
                    </button>
                </form>
                <form method="POST" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="respond">
                    <input type="hidden" name="application_id" value="<?= (int)$app['id'] ?>">
                    <input type="hidden" name="decision" value="rejected">
                    <button type="submit" class="btn btn-sm btn-outline-secondary"
                            onclick="return confirm('Reject this applicant?');">
                        <i class="bi bi-x-lg"></i> Reject
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/student_layout.php';
