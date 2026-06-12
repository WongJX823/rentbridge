<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = db();

// --- TAB STATE ---
$tab = $_GET['tab'] ?? 'all';
$validTabs = ['all', 'student', 'landlord', 'admin', 'suspended'];
if (!in_array($tab, $validTabs, true)) $tab = 'all';

// --- FILTER STATE ---
$searchQuery = trim($_GET['q'] ?? '');

// --- TAB COUNTS ---
$counts = [];
foreach (['student', 'landlord', 'admin'] as $role) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE primary_role = ? AND status != 'suspended'");
    $stmt->execute([$role]);
    $counts[$role] = (int)$stmt->fetchColumn();
}
$counts['suspended'] = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'suspended'")->fetchColumn();
$counts['all'] = $counts['student'] + $counts['landlord'] + $counts['admin'];

// --- BUILD QUERY ---
$where  = "1 = 1";
$params = [];

if ($tab === 'suspended') {
    $where .= " AND u.status = 'suspended'";
} elseif ($tab !== 'all') {
    $where .= " AND u.primary_role = ? AND u.status != 'suspended'";
    $params[] = $tab;
} else {
    $where .= " AND u.primary_role IN ('student','landlord','admin')";
}

if ($searchQuery !== '') {
    $where .= " AND (u.email LIKE ? OR
                     COALESCE(s.full_name, l.full_name, '') LIKE ?)";
    $like = '%' . $searchQuery . '%';
    $params[] = $like;
    $params[] = $like;
}

$stmt = $pdo->prepare("
    SELECT u.id, u.email, u.primary_role, u.status, u.created_at,
           COALESCE(s.full_name, l.full_name) AS full_name,
           COALESCE(s.matric_no, '')          AS matric_no,
           COALESCE(s.phone, l.phone, '')     AS phone
      FROM users u
      LEFT JOIN students  s ON s.user_id = u.id
      LEFT JOIN landlords l ON l.user_id = u.id
     WHERE $where
     ORDER BY u.created_at DESC
");
$stmt->execute($params);
$users = $stmt->fetchAll();

// --- LAYOUT SETUP ---
$pageTitle = 'Users';
$activeNav = 'users';

function build_user_tab_url(string $tab, string $q): string {
    $params = ['tab' => $tab];
    if ($q !== '') $params['q'] = $q;
    return '?' . http_build_query($params);
}

$pageTabs = [
    ['label' => 'All',       'href' => build_user_tab_url('all',       $searchQuery), 'active' => $tab==='all',       'count' => $counts['all']],
    ['label' => 'Students',  'href' => build_user_tab_url('student',   $searchQuery), 'active' => $tab==='student',   'count' => $counts['student']],
    ['label' => 'Landlords', 'href' => build_user_tab_url('landlord',  $searchQuery), 'active' => $tab==='landlord',  'count' => $counts['landlord']],
    ['label' => 'Admins',    'href' => build_user_tab_url('admin',     $searchQuery), 'active' => $tab==='admin',     'count' => $counts['admin']],
    ['label' => 'Suspended', 'href' => build_user_tab_url('suspended', $searchQuery), 'active' => $tab==='suspended', 'count' => $counts['suspended']],
];

// Filter drawer
ob_start();
?>
<form method="GET" class="row g-2 align-items-end">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <div class="col-md-9">
        <label class="form-label small fw-semibold text-secondary text-uppercase">Search</label>
        <input type="text" name="q" value="<?= e($searchQuery) ?>"
               class="form-control" placeholder="Name or email">
    </div>
    <div class="col-md-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-fill">
            <i class="bi bi-search me-1"></i> Apply
        </button>
        <a href="?tab=<?= e($tab) ?>" class="btn btn-outline-secondary">Clear</a>
    </div>
</form>
<?php
$filterContent = ob_get_clean();

function user_role_badge(string $role): array {
    return match ($role) {
        'student'  => ['Student',  '#E4F2EA', '#1e6b3f'],
        'landlord' => ['Landlord', '#E6ECF4', '#0F2C52'],
        'agent'    => ['Agent',    '#FFF4D6', '#7C5E0A'],
        'admin'    => ['Admin',    '#F8D7DA', '#842029'],
        default    => [$role,      '#E2E2E2', '#444'],
    };
}

ob_start();
?>

<?php if (empty($users)): ?>
    <div class="text-center py-5 bg-white rounded-3 border">
        <i class="bi bi-people" style="font-size: 3rem; color: rgba(15,44,82,0.15);"></i>
        <h4 class="mt-3">No users found</h4>
        <p class="text-secondary small">
            <?= $searchQuery ? 'Try a different search.' : 'No users in this tab.' ?>
        </p>
    </div>
<?php else: ?>
    <div class="bg-white border rounded-3 overflow-hidden">
        <table class="table mb-0 align-middle">
            <thead style="background:#F4F4EE;">
                <tr>
                    <th class="ps-3">Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Joined</th>
                    <th>Status</th>
                    <th class="text-end pe-3"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u):
                    [$roleLabel, $roleBg, $roleColor] = user_role_badge($u['primary_role']);
                ?>
                    <tr>
                        <td class="ps-3">
                            <strong><?= e($u['full_name'] ?? '—') ?></strong>
                            <?php if (!empty($u['matric_no'])): ?>
                                <div class="small text-secondary"><?= e($u['matric_no']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="small"><?= e($u['email']) ?></td>
                        <td>
                            <span class="badge"
                                  style="background: <?= $roleBg ?>; color: <?= $roleColor ?>;">
                                <?= e($roleLabel) ?>
                            </span>
                        </td>
                        <td class="small text-secondary">
                            <?= e(date('d M Y', strtotime($u['created_at']))) ?>
                        </td>
                        <td>
                            <?php if ($u['status'] === 'suspended'): ?>
                                <span class="badge bg-danger">Suspended</span>
                            <?php elseif ($u['status'] === 'active'): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary"><?= e($u['status']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-3">
                            <a href="/rentbridge/admin/user.php?id=<?= (int)$u['id'] ?>"
                               class="btn btn-sm btn-outline-dark">
                                View <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p class="text-secondary small mt-3 mb-0">
        Showing <?= count($users) ?> <?= count($users) === 1 ? 'user' : 'users' ?>
        <?php if ($searchQuery): ?> (filtered)<?php endif; ?>
    </p>
<?php endif; ?>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/admin_layout.php';