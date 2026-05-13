<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$userId = (int)($_GET['id'] ?? $_POST['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(400);
    die('Invalid user ID.');
}

$pdo = db();
$stmt = $pdo->prepare("
    SELECT u.id, u.email, u.status AS user_status, u.created_at,
           a.full_name, a.staff_id, a.department, a.phone,
           a.availability, a.current_caseload, a.max_caseload
      FROM users u
      JOIN agents a ON a.user_id = u.id
     WHERE u.id = ? AND u.primary_role = 'agent'
     LIMIT 1
");
$stmt->execute([$userId]);
$agent = $stmt->fetch();

if (!$agent) {
    http_response_code(404);
    die('Agent not found.');
}

$errors = [];

// ---- HANDLE ACTION ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    $allowed = ['approve', 'reject', 'suspend', 'reactivate'];
    if (!in_array($action, $allowed, true)) {
        $errors['general'] = 'Invalid action.';
    } else {
        $newStatus = match ($action) {
            'approve'    => 'active',
            'reject'     => 'rejected',
            'suspend'    => 'suspended',
            'reactivate' => 'active',
        };

        try {
            $stmt = $pdo->prepare('UPDATE users SET status = ? WHERE id = ?');
            $stmt->execute([$newStatus, $userId]);

            // Notify the agent (they'll see it next time they try to log in / view dashboard)
            $msg = match ($action) {
                'approve'    => 'Your agent application has been approved! You can now log in and start accepting cases.',
                'reject'     => 'Your agent application was not approved. Please contact UTeM HEP for clarification.',
                'suspend'    => 'Your account has been suspended by an administrator.',
                'reactivate' => 'Your account has been reactivated. Welcome back!',
            };
            notify($userId, 'agent_status_change', 'Account status changed', $msg, '/rentbridge/auth/login.php');

            set_flash('success', 'Agent ' . $action . 'd successfully.');
            header('Location: /rentbridge/admin/agents.php?status=' . $newStatus);
            exit;

        } catch (Throwable $e) {
            $errors['general'] = 'Error: ' . $e->getMessage();
        }
    }
}

function user_status_badge(string $status): array {
    return match ($status) {
        'pending'   => ['Pending review', 'warning'],
        'active'    => ['Active',          'success'],
        'rejected'  => ['Rejected',        'danger'],
        'suspended' => ['Suspended',       'secondary'],
        default     => [ucfirst($status),  'secondary'],
    };
}
[$badgeLabel, $badgeColor] = user_status_badge($agent['user_status']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Review agent · Admin · RentBridge</title>
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
    <div class="row justify-content-center">
        <div class="col-lg-8">

            <p class="small mb-3">
                <a href="/rentbridge/admin/agents.php" class="text-secondary text-decoration-none">
                    <i class="bi bi-arrow-left"></i> All agents
                </a>
            </p>

            <div class="d-flex justify-content-between align-items-start mb-4">
                <div>
                    <h1 class="mb-1"><?= e($agent['full_name']) ?></h1>
                    <p class="text-secondary mb-0">
                        Applied <?= e(date('d M Y', strtotime($agent['created_at']))) ?>
                    </p>
                </div>
                <span class="badge bg-<?= $badgeColor ?> fs-6"><?= e($badgeLabel) ?></span>
            </div>

            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-danger"><?= e($errors['general']) ?></div>
            <?php endif; ?>

            <div class="bg-white border rounded-3 p-4 mb-4">
                <h6 class="text-secondary text-uppercase small mb-3">Application details</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <small class="text-secondary text-uppercase">Staff ID</small>
                        <div><code><?= e($agent['staff_id']) ?></code></div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-secondary text-uppercase">Department / Faculty</small>
                        <div><?= e($agent['department']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-secondary text-uppercase">UTeM email</small>
                        <div><?= e($agent['email']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-secondary text-uppercase">Phone</small>
                        <div><?= e($agent['phone']) ?></div>
                    </div>
                    <?php if ($agent['user_status'] === 'active'): ?>
                    <div class="col-md-6">
                        <small class="text-secondary text-uppercase">Current caseload</small>
                        <div><?= (int)$agent['current_caseload'] ?> / <?= (int)$agent['max_caseload'] ?></div>
                    </div>
                    <div class="col-md-6">
                        <small class="text-secondary text-uppercase">Availability</small>
                        <div><?= e(ucfirst($agent['availability'])) ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Action panel — buttons depend on current status -->
            <div class="bg-white border rounded-3 p-4">
                <h6 class="text-secondary text-uppercase small mb-3">Admin actions</h6>

                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="user_id" value="<?= (int)$agent['id'] ?>">

                    <?php if ($agent['user_status'] === 'pending'): ?>
                        <p class="text-secondary small">
                            <i class="bi bi-info-circle"></i>
                            Approving will activate this account and allow them to log in and accept cases.
                            Rejecting will permanently deny access.
                        </p>
                        <div class="d-flex gap-2">
                            <button type="submit" name="action" value="approve" class="btn btn-success"
                                    onclick="return confirm('Approve this agent? They will be able to log in immediately.');">
                                <i class="bi bi-check-circle me-1"></i> Approve
                            </button>
                            <button type="submit" name="action" value="reject" class="btn btn-outline-danger"
                                    onclick="return confirm('Reject this agent? They will not be able to log in.');">
                                <i class="bi bi-x-circle me-1"></i> Reject
                            </button>
                        </div>

                    <?php elseif ($agent['user_status'] === 'active'): ?>
                        <p class="text-secondary small">
                            This agent is active. You can suspend them to temporarily block access.
                            <?php if ((int)$agent['current_caseload'] > 0): ?>
                                <br><strong class="text-warning">⚠ This agent has <?= (int)$agent['current_caseload'] ?> open case(s).</strong>
                                Suspending will not auto-reassign them — handle cases first.
                            <?php endif; ?>
                        </p>
                        <button type="submit" name="action" value="suspend" class="btn btn-outline-warning"
                                onclick="return confirm('Suspend this agent? They will no longer be able to log in.');">
                            <i class="bi bi-pause-circle me-1"></i> Suspend account
                        </button>

                    <?php elseif (in_array($agent['user_status'], ['rejected', 'suspended'], true)): ?>
                        <p class="text-secondary small">
                            This account is currently <strong><?= e($agent['user_status']) ?></strong>.
                            Reactivating will allow them to log in again.
                        </p>
                        <button type="submit" name="action" value="reactivate" class="btn btn-success"
                                onclick="return confirm('Reactivate this account?');">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Reactivate
                        </button>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>
</div>

</body>
</html>