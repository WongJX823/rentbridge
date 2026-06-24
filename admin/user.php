<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$userId = (int)($_GET['id'] ?? $_POST['user_id'] ?? 0);
if ($userId <= 0) {
    http_response_code(400);
    die('Invalid user ID.');
}

$pdo = db();

// Fetch base user
$stmt = $pdo->prepare("SELECT id, email, primary_role, status, created_at FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(404);
    die('User not found.');
}

$role   = $user['primary_role'];
$profile = null;

if ($role === 'student') {
    $stmt = $pdo->prepare("SELECT full_name, matric_no, ic_no, phone, university FROM students WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $profile = $stmt->fetch();
} elseif ($role === 'landlord') {
    $stmt = $pdo->prepare("SELECT full_name, ic_no, phone, verified FROM landlords WHERE user_id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $profile = $stmt->fetch();
} elseif ($role === 'admin') {
    // admin has no extra profile table
    $profile = ['full_name' => explode('@', $user['email'])[0]];
}

if (!$profile) {
    http_response_code(404);
    die('Profile not found for this user.');
}

$errors = [];

// ---- HANDLE ACTIONS ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $allowed = ['suspend', 'reactivate'];
    if (!in_array($action, $allowed, true)) {
        $errors['general'] = 'Invalid action.';
    } else {
        $newStatus = $action === 'suspend' ? 'suspended' : 'active';
        try {
            $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$newStatus, $userId]);
            $msg = $action === 'suspend'
                ? 'Your account has been suspended by an administrator. Contact support for help.'
                : 'Your account has been reactivated. Welcome back!';
            notify($userId, 'account_status', 'Account status updated', $msg, '/rentbridge/auth/login.php');
            set_flash('success', 'User ' . $action . 'd.');
            header('Location: /rentbridge/admin/user.php?id=' . $userId);
            exit;
        } catch (Throwable $e) {
            $errors['general'] = 'Error: ' . $e->getMessage();
        }
    }
    // Refresh user status after action
    $stmt = $pdo->prepare("SELECT id, email, primary_role, status, created_at FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
}

// ---- ROLE-SPECIFIC DATA ----
$tenancies      = [];
$properties     = [];
$activeContract = null;

if ($role === 'student') {
    $stmt = $pdo->prepare("
        SELECT b.id, b.status, b.created_at, b.start_date, b.end_date,
               p.title AS property_title, p.city, p.monthly_rent,
               l.full_name AS landlord_name
          FROM tenancies b
          JOIN properties p ON p.id = b.property_id
          JOIN landlords  l ON l.user_id = b.landlord_id
         WHERE b.student_id = ?
         ORDER BY b.created_at DESC
         LIMIT 20
    ");
    $stmt->execute([$userId]);
    $tenancies = $stmt->fetchAll();
}

if ($role === 'landlord') {
    $stmt = $pdo->prepare("
        SELECT id, title, city, monthly_rent, status, created_at
          FROM properties
         WHERE landlord_id = ?
         ORDER BY created_at DESC
         LIMIT 20
    ");
    $stmt->execute([$userId]);
    $properties = $stmt->fetchAll();
}

function status_badge_class(string $status): string {
    return match ($status) {
        'active'                => 'success',
        'suspended'             => 'danger',
        'pending'               => 'warning',
        'rejected'              => 'danger',
        'pending_agent',
        'pending_landlord'      => 'warning',
        'agent_verifying',
        'agent_assigned'        => 'info',
        'contract_pending'      => 'primary',
        'completed',
        'available'             => 'success',
        'reserved'              => 'info',
        'rented'                => 'secondary',
        'pending_approval'      => 'warning',
        'hidden'                => 'secondary',
        default                 => 'secondary',
    };
}

$fullName   = $profile['full_name'] ?? $user['email'];
$backLink   = match ($role) {
    'student'  => '/rentbridge/admin/students.php',
    'landlord' => '/rentbridge/admin/landlords.php',
    default    => '/rentbridge/admin/users.php',
};
$backLabel  = match ($role) {
    'student'  => 'All students',
    'landlord' => 'All landlords',
    default    => 'All users',
};

$statusColor = match ($user['status']) {
    'active'    => 'success',
    'suspended' => 'danger',
    'pending'   => 'warning',
    default     => 'secondary',
};
$statusLabel = match ($user['status']) {
    'active'    => 'Active',
    'suspended' => 'Suspended',
    'pending'   => 'Pending',
    default     => ucfirst($user['status']),
};

$pageTitle = $fullName;
$activeNav  = match ($role) {
    'student'  => 'students',
    'landlord' => 'landlords',
    default    => 'users',
};

ob_start();
?>

<p class="small mb-3">
    <a href="<?= e($backLink) ?>" class="text-secondary text-decoration-none">
        <i class="bi bi-arrow-left"></i> <?= e($backLabel) ?>
    </a>
</p>

<div class="d-flex justify-content-between align-items-start mb-4">
    <div>
        <h2 style="font-family:'Fraunces',serif;" class="mb-1"><?= e($fullName) ?></h2>
        <span class="badge bg-<?= $statusColor ?>"><?= $statusLabel ?></span>
        <span class="text-secondary small ms-2">
            #<?= (int)$user['id'] ?> · <?= e(ucfirst($role)) ?> · Joined <?= e(date('d M Y', strtotime($user['created_at']))) ?>
        </span>
    </div>
</div>

<?php if (!empty($errors['general'])): ?>
    <div class="alert alert-danger"><?= e($errors['general']) ?></div>
<?php endif; ?>

<div class="row g-4">

    <!-- Profile details -->
    <div class="col-md-5">
        <div class="bg-white border rounded-3 p-4 mb-4">
            <h6 class="text-secondary text-uppercase small mb-3">Profile</h6>
            <div class="row g-3">
                <div class="col-12">
                    <small class="text-secondary text-uppercase">Email</small>
                    <div><?= e($user['email']) ?></div>
                </div>
                <?php if ($role === 'student'): ?>
                    <div class="col-6">
                        <small class="text-secondary text-uppercase">Matric No.</small>
                        <div><code><?= e($profile['matric_no'] ?? '—') ?></code></div>
                    </div>
                    <div class="col-6">
                        <small class="text-secondary text-uppercase">University</small>
                        <div><?= e($profile['university'] ?? '—') ?></div>
                    </div>
                    <div class="col-6">
                        <small class="text-secondary text-uppercase">IC No.</small>
                        <div><code><?= e($profile['ic_no'] ?: '—') ?></code></div>
                    </div>
                    <div class="col-6">
                        <small class="text-secondary text-uppercase">Phone</small>
                        <div><?= e($profile['phone'] ?? '—') ?></div>
                    </div>
                <?php elseif ($role === 'landlord'): ?>
                    <div class="col-6">
                        <small class="text-secondary text-uppercase">IC No.</small>
                        <div><code><?= e($profile['ic_no'] ?? '—') ?></code></div>
                    </div>
                    <div class="col-6">
                        <small class="text-secondary text-uppercase">Phone</small>
                        <div><?= e($profile['phone'] ?? '—') ?></div>
                    </div>
                    <div class="col-12">
                        <small class="text-secondary text-uppercase">Verified</small>
                        <div>
                            <?php if (!empty($profile['verified'])): ?>
                                <span class="badge bg-success"><i class="bi bi-patch-check-fill me-1"></i>Verified</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Not verified</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Admin actions -->
        <div class="bg-white border rounded-3 p-4">
            <h6 class="text-secondary text-uppercase small mb-3">Admin actions</h6>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                <?php if ($user['status'] === 'suspended'): ?>
                    <p class="text-secondary small mb-3">This account is suspended. Reactivating restores access.</p>
                    <button type="submit" name="action" value="reactivate" class="btn btn-success w-100"
                            onclick="return confirm('Reactivate this account?');">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Reactivate account
                    </button>
                <?php elseif ($user['status'] === 'active'): ?>
                    <p class="text-secondary small mb-3">Suspend to block login access. This does not delete any data.</p>
                    <button type="submit" name="action" value="suspend" class="btn btn-outline-danger w-100"
                            onclick="return confirm('Suspend this account? They will not be able to log in.');">
                        <i class="bi bi-slash-circle me-1"></i> Suspend account
                    </button>
                <?php else: ?>
                    <p class="text-secondary small mb-0">No actions available for status: <strong><?= e($user['status']) ?></strong>.</p>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <!-- Role-specific content -->
    <div class="col-md-7">

        <?php if ($role === 'student' && !empty($tenancies)): ?>
        <div class="bg-white border rounded-3 overflow-hidden mb-4">
            <div class="px-4 pt-4 pb-2">
                <h6 class="text-secondary text-uppercase small mb-0">Tenancy history</h6>
            </div>
            <table class="table table-sm mb-0 align-middle">
                <thead style="background:#F4F4EE;">
                    <tr>
                        <th class="ps-4">Property</th>
                        <th>Period</th>
                        <th>Status</th>
                        <th class="pe-4 text-end"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tenancies as $t): ?>
                    <tr>
                        <td class="ps-4">
                            <strong class="small"><?= e($t['property_title']) ?></strong>
                            <div class="small text-secondary"><?= e($t['city']) ?> · RM <?= number_format((float)$t['monthly_rent']) ?>/mo</div>
                        </td>
                        <td class="small text-secondary">
                            <?= e(date('M Y', strtotime($t['start_date']))) ?>
                            <?php if (!empty($t['end_date'])): ?> – <?= e(date('M Y', strtotime($t['end_date']))) ?><?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-<?= status_badge_class($t['status']) ?>">
                                <?= e(ucfirst(str_replace('_', ' ', $t['status']))) ?>
                            </span>
                        </td>
                        <td class="pe-4 text-end">
                            <a href="/rentbridge/admin/tenancy.php?id=<?= (int)$t['id'] ?>"
                               class="btn btn-sm btn-outline-dark">View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php elseif ($role === 'student'): ?>
        <div class="bg-white border rounded-3 p-4 text-center text-secondary small">
            <i class="bi bi-house" style="font-size:2rem; opacity:.2;"></i>
            <p class="mt-2 mb-0">No tenancies yet.</p>
        </div>
        <?php endif; ?>

        <?php if ($role === 'landlord' && !empty($properties)): ?>
        <div class="bg-white border rounded-3 overflow-hidden mb-4">
            <div class="px-4 pt-4 pb-2">
                <h6 class="text-secondary text-uppercase small mb-0">Properties</h6>
            </div>
            <table class="table table-sm mb-0 align-middle">
                <thead style="background:#F4F4EE;">
                    <tr>
                        <th class="ps-4">Property</th>
                        <th>Rent</th>
                        <th>Status</th>
                        <th class="pe-4 text-end"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($properties as $p): ?>
                    <tr>
                        <td class="ps-4">
                            <strong class="small"><?= e($p['title']) ?></strong>
                            <div class="small text-secondary"><?= e($p['city']) ?></div>
                        </td>
                        <td class="small">RM <?= number_format((float)$p['monthly_rent']) ?>/mo</td>
                        <td>
                            <span class="badge bg-<?= status_badge_class($p['status']) ?>">
                                <?= e(ucfirst(str_replace('_', ' ', $p['status']))) ?>
                            </span>
                        </td>
                        <td class="pe-4 text-end">
                            <a href="/rentbridge/admin/property.php?id=<?= (int)$p['id'] ?>"
                               class="btn btn-sm btn-outline-dark">View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php elseif ($role === 'landlord'): ?>
        <div class="bg-white border rounded-3 p-4 text-center text-secondary small">
            <i class="bi bi-building" style="font-size:2rem; opacity:.2;"></i>
            <p class="mt-2 mb-0">No properties listed yet.</p>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/admin_layout.php';
