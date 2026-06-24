<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = db();

// --- TAB STATE ---
$tab = $_GET['tab'] ?? 'all';
$validTabs = ['all', 'active', 'assigned', 'pending', 'rejected', 'suspended'];
if (!in_array($tab, $validTabs, true)) $tab = 'all';

// --- FILTER STATE ---
$searchQuery = trim($_GET['q'] ?? '');
$filterDept  = trim($_GET['dept'] ?? '');

// --- TAB COUNTS ---
$counts = [];
foreach (['active', 'pending', 'rejected', 'suspended'] as $s) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE primary_role = 'agent' AND status = ?");
    $stmt->execute([$s]);
    $counts[$s] = (int)$stmt->fetchColumn();
}
// "Assigned" tab: active agents who have a current inspection case
$stmt = $pdo->query("
    SELECT COUNT(DISTINCT u.id)
      FROM users u
      JOIN agents a ON a.user_id = u.id
      JOIN tenancies b ON b.agent_id = u.id
     WHERE u.primary_role = 'agent'
       AND u.status = 'active'
       AND b.status IN ('agent_verifying','agent_verified','contract_pending')
");
$counts['assigned'] = (int)$stmt->fetchColumn();
$counts['all'] = $counts['active'] + $counts['pending'] + $counts['rejected'] + $counts['suspended'];

// --- BUILD QUERY ---
$where  = "u.primary_role = 'agent'";
$params = [];
$joinAssigned = '';

if ($tab === 'assigned') {
    // Special tab: agents with active inspection cases
    $joinAssigned = "
        JOIN tenancies b ON b.agent_id = u.id
            AND b.status IN ('agent_verifying','agent_verified','contract_pending')
        JOIN properties p ON p.id = b.property_id
    ";
    $where .= " AND u.status = 'active'";
} elseif ($tab !== 'all') {
    $where .= ' AND u.status = ?';
    $params[] = $tab;
}

if ($searchQuery !== '') {
    $where .= ' AND (a.full_name LIKE ? OR a.staff_id LIKE ? OR u.email LIKE ?)';
    $like = '%' . $searchQuery . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($filterDept !== '') {
    $where .= ' AND a.department = ?';
    $params[] = $filterDept;
}

// For "assigned" tab, include property + tenancy details
$selectCols = "u.id, u.email, u.status, u.created_at,
               a.full_name, a.staff_id, a.department,
               a.current_caseload, a.max_caseload, a.availability";

if ($tab === 'assigned') {
    $selectCols .= ",
                   b.id AS tenancy_id,
                   b.status AS tenancy_status,
                   b.created_at AS tenancy_created,
                   p.id AS property_id,
                   p.title AS property_title,
                   p.city AS property_city,
                   v.deadline_at AS inspection_deadline,
                   v.outcome AS inspection_outcome";
    $joinAssigned .= " LEFT JOIN agent_verifications v ON v.tenancy_id = b.id ";
}

$stmt = $pdo->prepare("
    SELECT $selectCols
      FROM users u
      JOIN agents a ON a.user_id = u.id
      $joinAssigned
     WHERE $where
     ORDER BY
       CASE u.status WHEN 'pending' THEN 0 WHEN 'active' THEN 1 ELSE 2 END,
       a.full_name ASC
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

// All departments (for filter dropdown)
$deptStmt = $pdo->query("SELECT DISTINCT department FROM agents ORDER BY department");
$departments = $deptStmt->fetchAll(PDO::FETCH_COLUMN);

// --- LAYOUT SETUP ---
$pageTitle = 'Agents';
$activeNav = 'agents';

function build_tab_url(string $tab, string $q, string $dept): string {
    $params = ['tab' => $tab];
    if ($q !== '')    $params['q'] = $q;
    if ($dept !== '') $params['dept'] = $dept;
    return '?' . http_build_query($params);
}

$pageTabs = [
    ['label' => 'All',       'href' => build_tab_url('all',       $searchQuery, $filterDept), 'active' => $tab==='all',       'count' => $counts['all']],
    ['label' => 'Active',    'href' => build_tab_url('active',    $searchQuery, $filterDept), 'active' => $tab==='active',    'count' => $counts['active']],
    ['label' => 'Assigned',  'href' => build_tab_url('assigned',  $searchQuery, $filterDept), 'active' => $tab==='assigned',  'count' => $counts['assigned']],
    ['label' => 'Pending',   'href' => build_tab_url('pending',   $searchQuery, $filterDept), 'active' => $tab==='pending',   'count' => $counts['pending']],
    ['label' => 'Rejected',  'href' => build_tab_url('rejected',  $searchQuery, $filterDept), 'active' => $tab==='rejected',  'count' => $counts['rejected']],
    ['label' => 'Suspended', 'href' => build_tab_url('suspended', $searchQuery, $filterDept), 'active' => $tab==='suspended', 'count' => $counts['suspended']],
];

// Filter drawer
ob_start();
?>
<form method="GET" class="row g-2 align-items-end">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <div class="col-md-6">
        <label class="form-label small fw-semibold text-secondary text-uppercase">Search</label>
        <input type="text" name="q" value="<?= e($searchQuery) ?>"
               class="form-control" placeholder="Name, staff ID, or email">
    </div>
    <div class="col-md-3">
        <label class="form-label small fw-semibold text-secondary text-uppercase">Department</label>
        <select name="dept" class="form-select">
            <option value="">All departments</option>
            <?php foreach ($departments as $d): ?>
                <option value="<?= e($d) ?>" <?= $filterDept===$d?'selected':'' ?>><?= e($d) ?></option>
            <?php endforeach; ?>
        </select>
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

// Helpers
function agent_status_badge(string $status): array {
    return match ($status) {
        'active'    => ['Active',    'success'],
        'pending'   => ['Pending',   'warning'],
        'rejected'  => ['Rejected',  'danger'],
        'suspended' => ['Suspended', 'secondary'],
        default     => [$status,     'secondary'],
    };
}

function tenancy_status_label(string $status): array {
    return match ($status) {
        'agent_verifying'   => ['🔍 Inspecting',      'info'],
        'agent_verified'    => ['✓ Verified',         'success'],
        'contract_pending'  => ['📝 Contract signing','primary'],
        default             => [ucfirst(str_replace('_', ' ', $status)), 'secondary'],
    };
}

// Page content
ob_start();
?>

<?php if (empty($rows)): ?>
    <div class="text-center py-5 bg-white rounded-3 border">
        <i class="bi bi-person-badge" style="font-size: 3rem; color: rgba(15,44,82,0.15);"></i>
        <h4 class="mt-3">No agents found</h4>
        <p class="text-secondary small">
            <?php if ($tab === 'assigned'): ?>
                No agents currently have active inspection cases.
            <?php elseif ($searchQuery || $filterDept): ?>
                Try adjusting your filters.
            <?php else: ?>
                No agents in this tab yet.
            <?php endif; ?>
        </p>
    </div>
<?php elseif ($tab === 'assigned'): ?>
    <!-- ASSIGNED TAB: special view showing agent → property relation -->
    <p class="text-secondary small mb-3">
        <i class="bi bi-info-circle me-1"></i>
        Showing agents currently assigned to a property inspection or contract.
    </p>

    <div class="bg-white border rounded-3 overflow-hidden">
        <table class="table mb-0 align-middle">
            <thead style="background:#F4F4EE;">
                <tr>
<th class="ps-3">ID</th>
        <th>Agent</th>
                            <th>Department</th>
                    <th>Property under inspection</th>
                    <th>Status</th>
                    <th>Deadline</th>
                    <th class="text-end pe-3"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r):
                    [$bStatusLabel, $bStatusColor] = tenancy_status_label($r['tenancy_status']);
                    $deadlineTs = $r['inspection_deadline'] ? strtotime($r['inspection_deadline']) : null;
                    $overdue    = $deadlineTs && time() > $deadlineTs;
                ?>
                    <tr>
                        <td class="ps-3">
                            <code class="text-secondary">#<?= (int)$r['id'] ?></code>
                        </td>
                        <td>
                            <strong><?= e($r['full_name']) ?></strong>
                            <div class="small text-secondary">
                                <code><?= e($r['staff_id']) ?></code>
                            </div>
                        </td>
                        <td><?= e($r['department']) ?></td>
                        <td>
                            <a href="/rentbridge/admin/property.php?id=<?= (int)$r['property_id'] ?>"
                               class="text-decoration-none">
                                <strong><?= e($r['property_title']) ?></strong>
                            </a>
                            <div class="small text-secondary">
                                <i class="bi bi-geo-alt"></i> <?= e($r['property_city']) ?>
                                &nbsp;·&nbsp; Tenancy #<?= (int)$r['tenancy_id'] ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge bg-<?= $bStatusColor ?>"><?= e($bStatusLabel) ?></span>
                        </td>
                        <td>
                            <?php if ($deadlineTs): ?>
                                <span class="<?= $overdue ? 'text-danger fw-semibold' : 'text-secondary' ?>">
                                    <?= e(date('d M, H:i', $deadlineTs)) ?>
                                    <?php if ($overdue): ?>
                                        <br><small><i class="bi bi-exclamation-triangle"></i> Overdue</small>
                                    <?php endif; ?>
                                </span>
                            <?php else: ?>
                                <span class="text-secondary small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-3">
                            <a href="/rentbridge/admin/tenancy.php?id=<?= (int)$r['tenancy_id'] ?>"
                               class="btn btn-sm btn-outline-dark">
                                View case <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php else: ?>
    <!-- ALL OTHER TABS: standard agent list -->
    <div class="bg-white border rounded-3 overflow-hidden">
        <table class="table mb-0 align-middle">
            <thead style="background:#F4F4EE;">
                <tr>
<th class="ps-3">ID</th>
        <th>Agent</th>                    <th>Department</th>
                    <th>Staff ID</th>
                    <th>Caseload</th>
                    <th>Status</th>
                    <th class="text-end pe-3"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r):
                    [$label, $color] = agent_status_badge($r['status']);
                ?>
                    <tr>
                        <td class="ps-3">
                            <code class="text-secondary">#<?= (int)$r['id'] ?></code>
                        </td>
                        <td>
                            <strong><?= e($r['full_name']) ?></strong>
                            <div class="small text-secondary"><?= e($r['email']) ?></div>
                        </td>
                        <td><?= e($r['department']) ?></td>
                        <td><code><?= e($r['staff_id']) ?></code></td>
                        <td>
                            <?= (int)$r['current_caseload'] ?> / <?= (int)$r['max_caseload'] ?>
                        </td>
                        <td><span class="badge bg-<?= $color ?>"><?= e($label) ?></span></td>
                        <td class="text-end pe-3">
                            <a href="/rentbridge/admin/agent.php?id=<?= (int)$r['id'] ?>"
                               class="btn btn-sm btn-outline-dark">
                                Review <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<p class="text-secondary small mt-3 mb-0">
    Showing <?= count($rows) ?> <?= count($rows) === 1 ? 'agent' : 'agents' ?>
    <?php if ($searchQuery || $filterDept): ?>
        (filtered)
    <?php endif; ?>
</p>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/admin_layout.php';