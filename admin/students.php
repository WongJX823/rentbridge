<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = db();

// --- TAB STATE ---
$tab = $_GET['tab'] ?? 'all';
$validTabs = ['all', 'tenanted', 'active', 'deactivated'];
if (!in_array($tab, $validTabs, true)) $tab = 'all';

// --- SEARCH ---
$searchQuery = trim($_GET['q'] ?? '');

// --- TAB COUNTS ---
$counts = [];

// All students
$counts['all'] = (int)$pdo->query(
    "SELECT COUNT(*) FROM users WHERE primary_role = 'student'"
)->fetchColumn();

// Tenanted: students with an active contract
$counts['tenanted'] = (int)$pdo->query("
    SELECT COUNT(DISTINCT u.id)
      FROM users u
      JOIN contracts c ON c.student_id = u.id
     WHERE u.primary_role = 'student'
       AND c.status = 'active'
")->fetchColumn();

// Active: status = 'active'
$counts['active'] = (int)$pdo->query(
    "SELECT COUNT(*) FROM users WHERE primary_role = 'student' AND status = 'active'"
)->fetchColumn();

// Deactivated: status = 'suspended'
$counts['deactivated'] = (int)$pdo->query(
    "SELECT COUNT(*) FROM users WHERE primary_role = 'student' AND status = 'suspended'"
)->fetchColumn();

// --- BUILD QUERY ---
$where  = "u.primary_role = 'student'";
$params = [];
$extraJoin = '';

if ($tab === 'tenanted') {
    $extraJoin = "JOIN contracts c ON c.student_id = u.id AND c.status = 'active'";
} elseif ($tab === 'active') {
    $where .= " AND u.status = 'active'";
} elseif ($tab === 'deactivated') {
    $where .= " AND u.status = 'suspended'";
}

if ($searchQuery !== '') {
    $where .= " AND (s.full_name LIKE ? OR s.matric_no LIKE ? OR u.email LIKE ?)";
    $like = '%' . $searchQuery . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$stmt = $pdo->prepare("
    SELECT DISTINCT
           u.id, u.email, u.status, u.created_at,
           s.full_name, s.matric_no, s.phone, s.university,
           (SELECT COUNT(*) FROM contracts c WHERE c.student_id = u.id AND c.status = 'active') AS active_contracts,
           (SELECT p.title FROM contracts c
              JOIN properties p ON p.id = c.property_id
             WHERE c.student_id = u.id AND c.status = 'active'
             ORDER BY c.activated_at DESC LIMIT 1) AS current_property
      FROM users u
      JOIN students s ON s.user_id = u.id
      $extraJoin
     WHERE $where
     ORDER BY u.created_at DESC
");
$stmt->execute($params);
$students = $stmt->fetchAll();

// --- LAYOUT SETUP ---
$pageTitle = 'Students';
$activeNav = 'students';

function build_student_tab_url(string $tab, string $q): string {
    $params = ['tab' => $tab];
    if ($q !== '') $params['q'] = $q;
    return '?' . http_build_query($params);
}

$pageTabs = [
    ['label' => 'All',         'href' => build_student_tab_url('all',         $searchQuery), 'active' => $tab==='all',         'count' => $counts['all']],
    ['label' => 'Tenanted',    'href' => build_student_tab_url('tenanted',    $searchQuery), 'active' => $tab==='tenanted',    'count' => $counts['tenanted']],
    ['label' => 'Active',      'href' => build_student_tab_url('active',      $searchQuery), 'active' => $tab==='active',      'count' => $counts['active']],
    ['label' => 'Deactivated', 'href' => build_student_tab_url('deactivated', $searchQuery), 'active' => $tab==='deactivated', 'count' => $counts['deactivated']],
];

// Search drawer (simplified — single search box)
ob_start();
?>
<form method="GET" class="row g-2 align-items-end">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <div class="col-md-9">
        <label class="form-label small fw-semibold text-secondary text-uppercase">Search</label>
        <input type="text" name="q" value="<?= e($searchQuery) ?>"
               class="form-control" placeholder="Search by name, matric number, or email">
    </div>
    <div class="col-md-3 d-flex gap-2">
        <button type="submit" class="btn btn-primary flex-fill">
            <i class="bi bi-search me-1"></i> Search
        </button>
        <a href="?tab=<?= e($tab) ?>" class="btn btn-outline-secondary">Clear</a>
    </div>
</form>
<?php
$filterContent = ob_get_clean();

// Status badge helper
function student_status_badge(string $status): array {
    return match ($status) {
        'active'    => ['Active',      'success'],
        'suspended' => ['Deactivated', 'danger'],
        default     => [$status,       'secondary'],
    };
}

// Page content
ob_start();
?>

<?php if (empty($students)): ?>
    <div class="text-center py-5 bg-white rounded-3 border">
        <i class="bi bi-mortarboard" style="font-size: 3rem; color: rgba(15,44,82,0.15);"></i>
        <h4 class="mt-3">No students found</h4>
        <p class="text-secondary small">
            <?= $searchQuery ? 'Try a different search.' : 'No students in this tab.' ?>
        </p>
    </div>
<?php else: ?>
    <div class="bg-white border rounded-3 overflow-hidden">
        <table class="table mb-0 align-middle">
            <thead style="background:#F4F4EE;">
    <tr>
        <th class="ps-3">ID</th>
        <th>Student</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Current tenancy</th>
        <th>Status</th>
        <th>Joined</th>
        <th class="text-end pe-3"></th>
    </tr>
</thead>
            <tbody>
                <?php foreach ($students as $s):
                    [$label, $color] = student_status_badge($s['status']);
                ?>
                    <tr>
    <td class="ps-3">
        <code class="text-secondary">#<?= (int)$s['id'] ?></code>
    </td>
    <td>
        <strong><?= e($s['full_name']) ?></strong>
                            <div class="small text-secondary">
                                <code><?= e($s['matric_no']) ?></code>
                                · <?= e($s['university']) ?>
                            </div>
                        </td>
                        <td class="small"><?= e($s['email']) ?></td>
                        <td class="small"><?= e($s['phone'] ?? '—') ?></td>
                        <td class="small">
                            <?php if (!empty($s['current_property'])): ?>
                                <span class="text-success">
                                    <i class="bi bi-house-check"></i> <?= e($s['current_property']) ?>
                                </span>
                            <?php else: ?>
                                <span class="text-secondary">—</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge bg-<?= $color ?>"><?= e($label) ?></span></td>
                        <td class="small text-secondary">
                            <?= e(date('d M Y', strtotime($s['created_at']))) ?>
                        </td>
                        <td class="text-end pe-3">
                            <a href="/rentbridge/admin/user.php?id=<?= (int)$s['id'] ?>"
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
        Showing <?= count($students) ?> <?= count($students) === 1 ? 'student' : 'students' ?>
        <?php if ($searchQuery): ?> (filtered)<?php endif; ?>
    </p>
<?php endif; ?>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/admin_layout.php';