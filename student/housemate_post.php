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

$post = get_co_tenancy_post($postId);
if (!$post) {
    set_flash('danger', 'Post not found.');
    header('Location: /rentbridge/student/partners.php');
    exit;
}

// Redirect poster to manage view
if ((int)$post['poster_id'] === $userId) {
    header('Location: /rentbridge/student/manage_post.php?id=' . $postId);
    exit;
}

$myApp = get_my_application($postId, $userId);

// Handle application submission
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'apply') {
        $message = trim($_POST['message'] ?? '');
        if ($message === '') {
            $errors['message'] = 'Please introduce yourself.';
        } else {
            [$ok, $err] = apply_to_co_tenancy_post($postId, $userId, $message);
            if ($ok) {
                set_flash('success', 'Application sent! The poster will review and get back to you.');
                header('Location: /rentbridge/student/housemate_post.php?id=' . $postId);
                exit;
            } else {
                $errors['general'] = $err;
            }
        }
    }
}

$perPerson = (float)$post['property_rent'] / max(1, ((int)$post['housemates_needed'] + 1));
$isClosed  = $post['status'] !== 'open';

// Count accepted so far
$stmt = $pdo->prepare("SELECT COUNT(*) FROM co_tenancy_applications WHERE post_id = ? AND status = 'accepted'");
$stmt->execute([$postId]);
$acceptedCount = (int)$stmt->fetchColumn();

// Group chat link if accepted
$groupConvId = (int)($post['group_conversation_id'] ?? 0);
$hasGroupChat = $groupConvId > 0 && $myApp && $myApp['status'] === 'accepted';

$pageTitle = 'Housemate Post';
$activeNav = 'partners';

ob_start();
?>

<p class="small mb-3">
    <a href="/rentbridge/student/partners.php" class="text-secondary text-decoration-none">
        <i class="bi bi-arrow-left"></i> Back to Find Housemates
    </a>
</p>

<?php if ($isClosed): ?>
<div class="alert alert-secondary">
    <i class="bi bi-lock me-1"></i> This post is closed (status: <strong><?= e($post['status']) ?></strong>).
</div>
<?php endif; ?>

<?php if ($hasGroupChat): ?>
<div class="alert alert-success d-flex align-items-center gap-3">
    <i class="bi bi-people-fill fs-4"></i>
    <div class="flex-grow-1">
        <strong>You're in the group!</strong>
        <div class="small">Your housemate group is ready. Chat with everyone in the group conversation.</div>
    </div>
    <a href="/rentbridge/chat/conversation.php?id=<?= $groupConvId ?>"
       class="btn btn-sm btn-success">
        <i class="bi bi-chat-dots me-1"></i> Open group chat
    </a>
</div>
<?php endif; ?>

<div class="row g-4">
    <!-- LEFT: Post details -->
    <div class="col-lg-7">
        <!-- Poster info -->
        <div class="bg-white border rounded-3 p-4 mb-4">
            <div class="d-flex gap-3 align-items-start">
                <div style="width:48px; height:48px; border-radius:50%; background:#E4F2EA;
                            color:#0F2C52; display:flex; align-items:center; justify-content:center;
                            font-weight:700; font-size:1.2rem; flex-shrink:0;">
                    <?= strtoupper(substr($post['poster_nickname'] ?: $post['poster_name'], 0, 1)) ?>
                </div>
                <div class="flex-grow-1">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <strong><?= e($post['poster_nickname'] ?: $post['poster_name']) ?></strong>
                            <div class="small text-secondary">
                                <i class="bi bi-mortarboard"></i> <?= e($post['poster_matric']) ?>
                            </div>
                        </div>
                        <?php if (!$isClosed): ?>
                        <span class="badge bg-success">Open</span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($post['poster_bio'])): ?>
                    <div class="mt-2 p-2 rounded" style="background:#F4F4EE; font-size:0.85rem;">
                        "<?= e($post['poster_bio']) ?>"
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Post message -->
            <div class="mt-3 pt-3 border-top">
                <p class="mb-0" style="white-space:pre-line; font-size:0.95rem;">
                    <?= e($post['message']) ?>
                </p>
            </div>

            <!-- Stats -->
            <div class="d-flex gap-4 mt-3 pt-3 border-top small text-secondary flex-wrap">
                <div>
                    <i class="bi bi-people"></i>
                    Looking for <strong class="text-dark"><?= (int)$post['housemates_needed'] ?></strong>
                    more housemate<?= $post['housemates_needed'] > 1 ? 's' : '' ?>
                </div>
                <div>
                    <i class="bi bi-calendar2-range"></i>
                    <strong class="text-dark"><?= (int)($post['semesters_needed'] ?? 1) ?></strong>
                    semester<?= ($post['semesters_needed'] ?? 1) > 1 ? 's' : '' ?>
                </div>
                <div>
                    <i class="bi bi-check-circle text-success"></i>
                    <strong class="text-dark"><?= $acceptedCount ?></strong> accepted
                </div>
                <div>
                    <i class="bi bi-clock"></i>
                    <?= date('d M Y', strtotime($post['created_at'])) ?>
                </div>
            </div>
        </div>

        <!-- Application form or status -->
        <?php if ($isClosed): ?>
            <!-- closed -->
        <?php elseif ($myApp): ?>
            <div class="bg-white border rounded-3 p-4">
                <h5 class="mb-3">Your application</h5>
                <?php if ($myApp['status'] === 'pending'): ?>
                    <div class="alert alert-warning mb-3">
                        <i class="bi bi-hourglass-split me-1"></i>
                        <strong>Pending</strong> — waiting for the poster to review.
                    </div>
                <?php elseif ($myApp['status'] === 'accepted'): ?>
                    <div class="alert alert-success mb-3">
                        <i class="bi bi-check-circle-fill me-1"></i>
                        <strong>Accepted!</strong>
                        <?php if ($hasGroupChat): ?>
                            <a href="/rentbridge/chat/conversation.php?id=<?= $groupConvId ?>" class="ms-2">
                                Open group chat →
                            </a>
                        <?php else: ?>
                            The group chat will be created when the group is full.
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-secondary mb-3">
                        <i class="bi bi-x-circle me-1"></i>
                        <strong>Not accepted</strong> — you can browse other posts.
                    </div>
                <?php endif; ?>
                <div class="p-3 bg-light rounded small">
                    <strong>Your message:</strong>
                    <div class="mt-1" style="white-space:pre-line;"><?= e($myApp['message']) ?></div>
                </div>
            </div>
        <?php else: ?>
            <!-- Application form -->
            <div class="bg-white border rounded-3 p-4">
                <h5 class="mb-1">Apply to join this group</h5>
                <p class="text-secondary small mb-3">
                    Introduce yourself. The poster will review your application.
                    <strong>No direct messaging</strong> — go through this form.
                </p>

                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-danger"><?= e($errors['general']) ?></div>
                <?php endif; ?>

                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="apply">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Your introduction <small class="text-danger">*</small>
                        </label>
                        <textarea name="message" rows="5" required maxlength="600"
                                  class="form-control <?= isset($errors['message']) ? 'is-invalid' : '' ?>"
                                  placeholder="E.g. Hi! I'm Year 2 Software Engineering, looking for a place near campus. Non-smoker, clean, quiet. Available from August."><?= e($_POST['message'] ?? '') ?></textarea>
                        <?php if (isset($errors['message'])): ?>
                            <div class="invalid-feedback"><?= e($errors['message']) ?></div>
                        <?php endif; ?>
                        <small class="text-secondary">
                            Mention your year, lifestyle, move-in date. Max 600 characters.
                        </small>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send me-1"></i> Send application
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <!-- RIGHT: Property info -->
    <div class="col-lg-5">
        <div class="bg-white border rounded-3 overflow-hidden sticky-top" style="top: 80px;">
            <div style="aspect-ratio: 16/9; background: linear-gradient(135deg,#E6ECF4,#E4F2EA);">
                <?php if (!empty($post['property_image'])): ?>
                    <img src="/rentbridge/<?= e($post['property_image']) ?>"
                         style="width:100%; height:100%; object-fit:cover;" alt="">
                <?php endif; ?>
            </div>
            <div class="p-4">
                <h5 class="mb-1"><?= e($post['property_title']) ?></h5>
                <div class="small text-secondary mb-3">
                    <i class="bi bi-geo-alt"></i> <?= e($post['property_city']) ?>
                    <?php if (!empty($post['property_address'])): ?>
                        · <?= e($post['property_address']) ?>
                    <?php endif; ?>
                </div>

                <div class="d-flex justify-content-between mb-3">
                    <div>
                        <small class="text-secondary d-block">Total rent</small>
                        <strong>RM <?= number_format((float)$post['property_rent']) ?>/mo</strong>
                    </div>
                    <div class="text-end">
                        <small class="text-secondary d-block">Per person (~<?= (int)$post['housemates_needed'] + 1 ?> pax)</small>
                        <strong class="text-emerald">RM <?= number_format($perPerson) ?>/mo</strong>
                    </div>
                </div>

                <?php if (!empty($post['property_type'])): ?>
                <div class="mb-2">
                    <span class="badge bg-light text-dark">
                        <?= e(ucfirst(str_replace('_',' ', $post['property_type']))) ?>
                    </span>
                    <?php if (!empty($post['property_furnishing'])): ?>
                    <span class="badge bg-light text-dark ms-1">
                        <?= e(ucfirst($post['property_furnishing'])) ?>
                    </span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <a href="/rentbridge/property.php?id=<?= (int)$post['property_id'] ?>"
                   class="btn btn-outline-primary btn-sm w-100 mt-2" target="_blank">
                    <i class="bi bi-house me-1"></i> View property listing
                </a>
            </div>
        </div>
    </div>
</div>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/student_layout.php';
