<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = db();

// --- TAB STATE ---
$tab = $_GET['tab'] ?? 'all';
$validTabs = ['all', 'pending', 'inspecting', 'active', 'completed', 'cancelled', 'stuck'];
if (!in_array($tab, $validTabs, true)) $tab = 'all';

// --- FILTER STATE ---
$searchQuery = trim($_GET['q'] ?? '');

// Status groupings
$statusGroups = [
    'pending'    => ['pending_landlord', 'pending_agent'],
    'inspecting' => ['agent_verifying', 'agent_verified', 'contract_pending'],
    'active'     => ['active'],
    'completed'  => ['completed'],
    'cancelled'  => ['rejected_by_landlord', 'verification_failed',
                     'cancelled_by_student', 'cancelled_by_landlord', 'cancelled_by_admin'],
];

// Tab counts
$counts = ['all' => 0];
foreach ($statusGroups as $key => $statuses) {
    $ph = implode(',', array_fill(0, count($statuses), '?'));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tenancies WHERE status IN ($ph)");
    $stmt->execute($statuses);
    $counts[$key] = (int)$stmt->fetchColumn();
    $counts['all'] += $counts[$key];
}
// Stuck: pending_agent with no agent_id OR inspection_aborted (needs admin resolution)
$counts['stuck'] = (int)$pdo->query(
    "SELECT COUNT(*) FROM tenancies WHERE status = 'inspection_aborted' OR (status = 'pending_agent' AND agent_id IS NULL)"
)->fetchColumn();

// --- BUILD QUERY ---
$where  = "1 = 1";
$params = [];

if ($tab === 'stuck') {
    $where .= " AND (b.status = 'inspection_aborted' OR (b.status = 'pending_agent' AND b.agent_id IS NULL))";
} elseif ($tab !== 'all' && isset($statusGroups[$tab])) {
    $ph = implode(',', array_fill(0, count($statusGroups[$tab]), '?'));
    $where .= " AND b.status IN ($ph)";
    $params = array_merge($params, $statusGroups[$tab]);
}

if ($searchQuery !== '') {
    $where .= " AND (p.title LIKE ? OR s.full_name LIKE ? OR l.full_name LIKE ?)";
    $like = '%' . $searchQuery . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$stmt = $pdo->prepare("
    SELECT b.id, b.status, b.start_date, b.end_date, b.monthly_rent, b.created_at,
           b.signed_contract_path, b.signed_uploaded_at,
           p.id AS property_id, p.title AS property_title, p.city,
           s.full_name AS student_name,
           l.full_name AS landlord_name,
           a.full_name AS agent_name,
           (SELECT GROUP_CONCAT(ct.full_name SEPARATOR ', ')
              FROM co_tenants ct
             WHERE ct.tenancy_id = b.id 
               AND ct.status != 'removed'
             ORDER BY ct.sign_order ASC) AS all_tenant_names,
           (SELECT COUNT(*)
              FROM co_tenants ct
             WHERE ct.tenancy_id = b.id 
               AND ct.status != 'removed') AS tenant_count
      FROM tenancies b
      JOIN properties p ON p.id = b.property_id
      JOIN students s ON s.user_id = b.student_id
      JOIN landlords l ON l.user_id = b.landlord_id
      LEFT JOIN agents a ON a.user_id = b.agent_id
     WHERE $where
     ORDER BY b.created_at DESC
");
$stmt->execute($params);
$tenancies = $stmt->fetchAll();

// --- LAYOUT SETUP ---
$pageTitle = 'Tenancies';
$activeNav = 'tenancies';

function build_tenancy_tab_url(string $tab, string $q): string {
    $params = ['tab' => $tab];
    if ($q !== '') $params['q'] = $q;
    return '?' . http_build_query($params);
}

$pageTabs = [
    ['label' => 'All',        'href' => build_tenancy_tab_url('all',        $searchQuery), 'active' => $tab==='all',        'count' => $counts['all']],
    ['label' => 'Pending',    'href' => build_tenancy_tab_url('pending',    $searchQuery), 'active' => $tab==='pending',    'count' => $counts['pending']],
    ['label' => 'Inspecting', 'href' => build_tenancy_tab_url('inspecting', $searchQuery), 'active' => $tab==='inspecting', 'count' => $counts['inspecting']],
    ['label' => 'Active',     'href' => build_tenancy_tab_url('active',     $searchQuery), 'active' => $tab==='active',     'count' => $counts['active']],
    ['label' => 'Completed',  'href' => build_tenancy_tab_url('completed',  $searchQuery), 'active' => $tab==='completed',  'count' => $counts['completed']],
    ['label' => 'Cancelled',  'href' => build_tenancy_tab_url('cancelled',  $searchQuery), 'active' => $tab==='cancelled',  'count' => $counts['cancelled']],
    ['label' => 'Stuck',      'href' => build_tenancy_tab_url('stuck',      $searchQuery), 'active' => $tab==='stuck',      'count' => $counts['stuck']],
];

// Filter drawer
ob_start();
?>
<form method="GET" class="row g-2 align-items-end">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <div class="col-md-9">
        <label class="form-label small fw-semibold text-secondary text-uppercase">Search</label>
        <input type="text" name="q" value="<?= e($searchQuery) ?>"
               class="form-control" placeholder="Property, student or landlord name">
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

function tenancy_status_label_admin(string $status, ?string $signedPath = null): array {
    // If contract is signed, override 'active' display
    if ($signedPath !== null && $status === 'active') {
        return ['✓ Signed & Active', 'success'];
    }
    
    return match ($status) {
        'pending_landlord'      => ['Pending landlord',     'warning'],
        'pending_agent'         => ['Pending agent',        'warning'],
        'agent_verifying'       => ['🔍 Inspecting',        'info'],
        'agent_verified'        => ['✓ Verified',           'success'],
        'verification_failed'   => ['Verification failed',  'danger'],
        'contract_pending'      => ['📝 Awaiting signature','primary'],
        'active'                => ['Active',               'success'],
        'completed'             => ['Completed',            'secondary'],
        'cancelled_by_student'  => ['Cancelled (student)',  'secondary'],
        'cancelled_by_landlord' => ['Cancelled (landlord)', 'secondary'],
        'cancelled_by_admin'    => ['Cancelled (admin)',    'danger'],
        'rejected_by_landlord'  => ['Rejected',             'danger'],
        default                 => [$status,                'secondary'],
    };
}

ob_start();
?>

<?php if (empty($tenancies)): ?>
    <div class="text-center py-5 bg-white rounded-3 border">
        <i class="bi bi-clipboard" style="font-size: 3rem; color: rgba(15,44,82,0.15);"></i>
        <h4 class="mt-3">No tenancies found</h4>
        <p class="text-secondary small">
            <?= $searchQuery ? 'Try a different search.' : 'No tenancies in this tab.' ?>
        </p>
    </div>
<?php else: ?>
    <div class="bg-white border rounded-3 overflow-hidden">
        <table class="table mb-0 align-middle">
            <thead style="background:#F4F4EE;">
                <tr>
                    <th class="ps-3">ID</th>
                    <th>Property</th>
                    <th>Tenants</th>
                    <th>Landlord</th>
                    <th>Agent</th>
                    <th>Status</th>
                    <th class="text-end pe-3"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tenancies as $b):
                    [$label, $color] = tenancy_status_label_admin($b['status'], $b['signed_contract_path'] ?? null);
                ?>
                    <tr>
                        <td class="ps-3">
                            <code class="text-secondary">#<?= (int)$b['id'] ?></code>
                            <div class="small text-secondary">
                                <?= e(date('d M Y', strtotime($b['created_at']))) ?>
                            </div>
                        </td>
                        <td>
                            <a href="/rentbridge/admin/property.php?id=<?= (int)$b['property_id'] ?>"
                               class="text-decoration-none text-dark">
                                <strong class="small"><?= e($b['property_title']) ?></strong>
                            </a>
                            <div class="small text-secondary">
                                <i class="bi bi-geo-alt"></i> <?= e($b['city']) ?>
                            </div>
                        </td>
                        <td class="small">
                            <?php if (!empty($b['all_tenant_names']) && $b['tenant_count'] > 0): ?>
                                <?php
                                // If 2+ tenants, show primary + count
                                if ($b['tenant_count'] > 1):
                                    // Get the first name only for primary display
                                    $names = explode(', ', $b['all_tenant_names']);
                                    $primary = $names[0];
                                    $othersCount = (int)$b['tenant_count'] - 1;
                                ?>
                                    <strong><?= e($primary) ?></strong>
                                    <div class="text-secondary" style="font-size:0.75rem;">
                                        +<?= $othersCount ?> co-tenant<?= $othersCount > 1 ? 's' : '' ?>
                                    </div>
                                <?php else: ?>
                                    <?= e($b['all_tenant_names']) ?>
                                <?php endif; ?>
                            <?php else: ?>
                                <?= e($b['student_name']) ?>
                                <span class="text-secondary" style="font-size:0.7rem;">(no contract yet)</span>
                            <?php endif; ?>
                        </td> 
                        <td class="small"><?= e($b['landlord_name']) ?></td>
                        <td class="small">
                            <?= !empty($b['agent_name']) ? e($b['agent_name'])
                                : '<span class="text-secondary">—</span>' ?>
                        </td>
                        <td><span class="badge bg-<?= $color ?>"><?= e($label) ?></span></td>
                        <td class="text-end pe-3">
                            <a href="/rentbridge/admin/tenancy.php?id=<?= (int)$b['id'] ?>"
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
        Showing <?= count($tenancies) ?> <?= count($tenancies) === 1 ? 'tenancy' : 'tenancies' ?>
        <?php if ($searchQuery): ?> (filtered)<?php endif; ?>
    </p>
<?php endif; ?>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/admin_layout.php';  