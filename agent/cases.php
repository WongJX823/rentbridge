<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('agent');

$pdo = db();
$userId = current_user_id();

$tab = $_GET['tab'] ?? 'all';
$validTabs = ['all','pending','verifying','contracts','active','completed','properties'];
if (!in_array($tab, $validTabs, true)) $tab = 'all';

$searchQuery = trim($_GET['q'] ?? '');

// Status mapping per tab
$statusGroups = [
    'all'        => ['pending_agent','agent_verifying','contract_pending','active','completed','verification_failed','inspection_aborted'],
    'pending'    => ['pending_agent'],
    'verifying'  => ['agent_verifying'],
    'contracts'  => ['contract_pending'],
    'active'     => ['active'],
    'completed'  => ['completed'],
];

// Tab counts
$counts = [];
foreach ($statusGroups as $key => $statuses) {
    $ph = implode(',', array_fill(0, count($statuses), '?'));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM tenancies WHERE agent_id = ? AND status IN ($ph)");
    $stmt->execute(array_merge([$userId], $statuses));
    $counts[$key] = (int)$stmt->fetchColumn();
}

// Count pending property inspections (awaiting acceptance) — for Pending tab delta
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM properties
    WHERE assigned_agent_id = ? AND agent_status = 'pending'
");
$stmt->execute([$userId]);
$pendingPropCount = (int)$stmt->fetchColumn();

// Count active property inspections (pending + inspecting) — for All tab delta
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM properties
    WHERE assigned_agent_id = ? AND agent_status IN ('pending','inspecting')
");
$stmt->execute([$userId]);
$activePropCount = (int)$stmt->fetchColumn();

$counts['pending'] += $pendingPropCount;
$counts['all']     += $activePropCount;

// Count ALL assigned properties including approved — for Properties tab
$stmt = $pdo->prepare("
    SELECT COUNT(*) FROM properties
    WHERE assigned_agent_id = ? AND agent_status IN ('pending','inspecting','accepted')
");
$stmt->execute([$userId]);
$counts['properties'] = (int)$stmt->fetchColumn();

// Fetch active property inspections (pending + inspecting) for Pending/All tab blocks
$activeProperties = [];
$stmt = $pdo->prepare("
    SELECT p.id, p.title, p.city, p.monthly_rent,
           p.agent_status, p.agent_assigned_at,
           l.full_name AS landlord_name,
           TIMESTAMPDIFF(HOUR, p.agent_assigned_at, NOW()) AS hours_pending
      FROM properties p
      JOIN landlords l ON l.user_id = p.landlord_id
     WHERE p.assigned_agent_id = ? AND p.agent_status IN ('pending','inspecting')
     ORDER BY p.agent_assigned_at ASC
");
$stmt->execute([$userId]);
$activeProperties = $stmt->fetchAll();
$pendingProperties = array_filter($activeProperties, fn($p) => $p['agent_status'] === 'pending');

// Fetch assigned properties including accepted/live for Properties tab
$assignedProperties = [];
if ($tab === 'properties') {
    $stmt = $pdo->prepare("
        SELECT p.id, p.title, p.city, p.monthly_rent,
               p.agent_status, p.agent_assigned_at,
               l.full_name AS landlord_name,
               TIMESTAMPDIFF(HOUR, p.agent_assigned_at, NOW()) AS hours_pending
          FROM properties p
          JOIN landlords l ON l.user_id = p.landlord_id
         WHERE p.assigned_agent_id = ? AND p.agent_status IN ('pending','inspecting','accepted')
         ORDER BY
           FIELD(p.agent_status,'pending','inspecting','accepted'),
           p.agent_assigned_at ASC
    ");
    $stmt->execute([$userId]);
    $assignedProperties = $stmt->fetchAll();
}

// Build tenancy query — Properties tab has no tenancy rows
$selected = $statusGroups[$tab] ?? [];
$cases = [];
if ($selected !== []) {
    $ph = implode(',', array_fill(0, count($selected), '?'));
    $where  = "b.agent_id = ? AND b.status IN ($ph)";
    $params = array_merge([$userId], $selected);

    if ($searchQuery !== '') {
        $where .= " AND (p.title LIKE ? OR s.full_name LIKE ?)";
        $like = '%' . $searchQuery . '%';
        $params[] = $like;
        $params[] = $like;
    }

    $stmt = $pdo->prepare("
        SELECT b.id, b.status, b.start_date, b.end_date, b.monthly_rent, b.created_at,
               p.id AS property_id, p.title AS property_title, p.city,
               s.full_name AS student_name, s.matric_no
          FROM tenancies b
          JOIN properties p ON p.id = b.property_id
          JOIN students s ON s.user_id = b.student_id
         WHERE $where
         ORDER BY
           CASE b.status
             WHEN 'pending_agent' THEN 0
             WHEN 'agent_verifying' THEN 1
             WHEN 'contract_pending' THEN 2
             ELSE 3
           END, b.created_at DESC
    ");
    $stmt->execute($params);
    $cases = $stmt->fetchAll();
}

$pageTitle = 'My Cases';
$activeNav = 'cases';

function build_case_tab_url(string $tab, string $q): string {
    $params = ['tab' => $tab];
    if ($q !== '') $params['q'] = $q;
    return '?' . http_build_query($params);
}

$pageTabs = [
    ['label'=>'All',        'href'=>build_case_tab_url('all',        $searchQuery), 'active'=>$tab==='all',        'count'=>$counts['all']],
    ['label'=>'Pending',    'href'=>build_case_tab_url('pending',    $searchQuery), 'active'=>$tab==='pending',    'count'=>$counts['pending']],
    ['label'=>'Verifying',  'href'=>build_case_tab_url('verifying',  $searchQuery), 'active'=>$tab==='verifying',  'count'=>$counts['verifying']],
    ['label'=>'Contracts',  'href'=>build_case_tab_url('contracts',  $searchQuery), 'active'=>$tab==='contracts',  'count'=>$counts['contracts']],
    ['label'=>'Active',     'href'=>build_case_tab_url('active',     $searchQuery), 'active'=>$tab==='active',     'count'=>$counts['active']],
    ['label'=>'Completed',  'href'=>build_case_tab_url('completed',  $searchQuery), 'active'=>$tab==='completed',  'count'=>$counts['completed']],
    ['label'=>'Properties', 'href'=>build_case_tab_url('properties', ''),           'active'=>$tab==='properties', 'count'=>$counts['properties']],
];

ob_start();
?>
<form method="GET" class="row g-2 align-items-end">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <div class="col-md-9">
        <label class="form-label small fw-semibold text-secondary text-uppercase">Search</label>
        <input type="text" name="q" value="<?= e($searchQuery) ?>"
               class="form-control" placeholder="Property name or student name">
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

function case_status_label(string $status): array {
    return match ($status) {
        'pending_agent'       => ['⏳ Awaiting acceptance','warning'],
        'agent_verifying'     => ['🔍 Inspecting','info'],
        'verification_failed'  => ['Inspection failed','danger'],
        'inspection_aborted'   => ['Inspection aborted','danger'],
        'contract_pending'    => ['📝 Awaiting signatures','primary'],
        'active'              => ['Active','success'],
        'completed'           => ['Completed','secondary'],
        default               => [$status,'secondary'],
    };
}

ob_start();
?>

<?php
// Reusable property rows helper
function render_property_rows(array $props): void { ?>
    <?php foreach ($props as $p): ?>
    <tr>
        <td class="ps-3" colspan="2">
            <strong class="small"><?= e($p['title']) ?></strong>
            <div class="small text-secondary">
                <i class="bi bi-geo-alt"></i> <?= e($p['city']) ?>
                · RM <?= number_format((float)$p['monthly_rent']) ?>/mo
            </div>
        </td>
        <td class="small"><?= e($p['landlord_name']) ?></td>
        <td class="small text-secondary">
            <?= (int)$p['hours_pending'] ?>h ago
            <?php if ($p['hours_pending'] > 18): ?>
                <span class="badge bg-danger ms-1">Urgent</span>
            <?php endif; ?>
        </td>
        <td>
            <?php if ($p['agent_status'] === 'pending'): ?>
                <span class="badge bg-warning text-dark">⏳ Awaiting acceptance</span>
            <?php elseif ($p['agent_status'] === 'inspecting'): ?>
                <span class="badge bg-info text-dark">🔍 Inspecting</span>
            <?php else: ?>
                <span class="badge bg-success">✓ Live</span>
            <?php endif; ?>
        </td>
        <td class="text-end pe-3">
            <a href="/rentbridge/agent/property_review.php?id=<?= (int)$p['id'] ?>"
               class="btn btn-sm btn-primary">
                Review <i class="bi bi-arrow-right"></i>
            </a>
        </td>
    </tr>
    <?php endforeach;
}
?>

<?php if ($tab === 'properties'): ?>
    <?php if (empty($assignedProperties)): ?>
        <div class="text-center py-5 bg-white rounded-3 border">
            <i class="bi bi-clipboard-check" style="font-size: 3rem; color: rgba(15,44,82,0.15);"></i>
            <h4 class="mt-3">No properties assigned</h4>
            <p class="text-secondary small">You have no active property listing assignments.</p>
        </div>
    <?php else: ?>
        <div class="bg-white border rounded-3 overflow-hidden">
            <table class="table mb-0 align-middle">
                <thead style="background:#F4F4EE;">
                    <tr>
                        <th class="ps-3" colspan="2">Property</th>
                        <th>Landlord</th>
                        <th>Assigned</th>
                        <th>Status</th>
                        <th class="text-end pe-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php render_property_rows($assignedProperties); ?>
                </tbody>
            </table>
        </div>
        <p class="text-secondary small mt-3 mb-0">
            <?= count($assignedProperties) ?> propert<?= count($assignedProperties) === 1 ? 'y' : 'ies' ?> assigned
        </p>
    <?php endif; ?>

<?php elseif ($tab === 'pending' && !empty($pendingProperties)): ?>
    <!-- Pending property inspections -->
    <div class="bg-white border rounded-3 overflow-hidden mb-4">
        <div class="px-3 py-2" style="background:#FFF4D6; border-bottom:1px solid rgba(0,0,0,.06);">
            <span class="small fw-semibold text-warning-emphasis">
                <i class="bi bi-house-exclamation me-1"></i>
                Property inspections awaiting your acceptance
            </span>
        </div>
        <table class="table mb-0 align-middle">
            <thead style="background:#F4F4EE;">
                <tr>
                    <th class="ps-3" colspan="2">Property</th>
                    <th>Landlord</th>
                    <th>Assigned</th>
                    <th>Status</th>
                    <th class="text-end pe-3"></th>
                </tr>
            </thead>
            <tbody>
                <?php render_property_rows($pendingProperties); ?>
            </tbody>
        </table>
    </div>
    <?php if (!empty($cases)): ?>
    <!-- Pending tenancy cases below -->
    <div class="bg-white border rounded-3 overflow-hidden">
        <div class="px-3 py-2" style="background:#F4F4EE; border-bottom:1px solid rgba(0,0,0,.06);">
            <span class="small fw-semibold text-secondary">
                <i class="bi bi-clipboard-check me-1"></i>
                Tenancy cases awaiting your acceptance
            </span>
        </div>
        <table class="table mb-0 align-middle">
            <thead style="background:#F4F4EE;">
                <tr>
                    <th class="ps-3">ID</th>
                    <th>Property</th>
                    <th>Student</th>
                    <th>Period</th>
                    <th>Status</th>
                    <th class="text-end pe-3"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cases as $c):
                    [$label, $color] = case_status_label($c['status']); ?>
                <tr>
                    <td class="ps-3"><code class="text-secondary">#<?= (int)$c['id'] ?></code></td>
                    <td>
                        <strong class="small"><?= e($c['property_title']) ?></strong>
                        <div class="small text-secondary"><i class="bi bi-geo-alt"></i> <?= e($c['city']) ?></div>
                    </td>
                    <td class="small">
                        <?= e($c['student_name']) ?>
                        <div class="text-secondary"><code><?= e($c['matric_no']) ?></code></div>
                    </td>
                    <td class="small">
                        <?= e(date('d M Y', strtotime($c['start_date']))) ?> →
                        <?= e(date('d M Y', strtotime($c['end_date']))) ?>
                    </td>
                    <td><span class="badge bg-<?= $color ?>"><?= e($label) ?></span></td>
                    <td class="text-end pe-3">
                        <a href="/rentbridge/agent/case.php?id=<?= (int)$c['id'] ?>"
                           class="btn btn-sm btn-primary">Review <i class="bi bi-arrow-right"></i></a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <p class="text-secondary small mt-3 mb-0">
        <?= count($pendingProperties) + count($cases) ?> item(s) pending your action
    </p>

<?php elseif (empty($cases) && ($tab !== 'all' || empty($activeProperties))): ?>
    <div class="text-center py-5 bg-white rounded-3 border">
        <i class="bi bi-clipboard" style="font-size: 3rem; color: rgba(15,44,82,0.15);"></i>
        <h4 class="mt-3">No cases here</h4>
        <p class="text-secondary small">
            <?= $searchQuery ? 'Try a different search.' : 'No cases in this tab.' ?>
        </p>
    </div>
<?php else: ?>
    <?php if ($tab === 'all' && !empty($activeProperties)): ?>
    <div class="bg-white border rounded-3 overflow-hidden mb-4">
        <div class="px-3 py-2" style="background:#FFF4D6; border-bottom:1px solid rgba(0,0,0,.06);">
            <span class="small fw-semibold text-warning-emphasis">
                <i class="bi bi-house-exclamation me-1"></i>
                Property assignments
            </span>
        </div>
        <table class="table mb-0 align-middle">
            <thead style="background:#F4F4EE;">
                <tr>
                    <th class="ps-3" colspan="2">Property</th>
                    <th>Landlord</th>
                    <th>Assigned</th>
                    <th>Status</th>
                    <th class="text-end pe-3"></th>
                </tr>
            </thead>
            <tbody>
                <?php render_property_rows($activeProperties); ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <div class="bg-white border rounded-3 overflow-hidden">
        <table class="table mb-0 align-middle">
            <thead style="background:#F4F4EE;">
                <tr>
                    <th class="ps-3">ID</th>
                    <th>Property</th>
                    <th>Student</th>
                    <th>Period</th>
                    <th>Status</th>
                    <th class="text-end pe-3"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($cases as $c):
                    [$label, $color] = case_status_label($c['status']);
                ?>
                    <tr>
                        <td class="ps-3">
                            <code class="text-secondary">#<?= (int)$c['id'] ?></code>
                        </td>
                        <td>
                            <strong class="small"><?= e($c['property_title']) ?></strong>
                            <div class="small text-secondary">
                                <i class="bi bi-geo-alt"></i> <?= e($c['city']) ?>
                            </div>
                        </td>
                        <td class="small">
                            <?= e($c['student_name']) ?>
                            <div class="text-secondary"><code><?= e($c['matric_no']) ?></code></div>
                        </td>
                        <td class="small">
                            <?= e(date('d M Y', strtotime($c['start_date']))) ?> →
                            <?= e(date('d M Y', strtotime($c['end_date']))) ?>
                        </td>
                        <td><span class="badge bg-<?= $color ?>"><?= e($label) ?></span></td>
                        <td class="text-end pe-3">
                            <?php if ($c['status'] === 'pending_agent'): ?>
                                <a href="/rentbridge/agent/case.php?id=<?= (int)$c['id'] ?>"
                                   class="btn btn-sm btn-primary">
                                    Review <i class="bi bi-arrow-right"></i>
                                </a>
                            <?php elseif ($c['status'] === 'agent_verifying'): ?>
                                <a href="/rentbridge/agent/inspection.php?tenancy_id=<?= (int)$c['id'] ?>"
                                   class="btn btn-sm btn-primary">
                                    Inspect <i class="bi bi-arrow-right"></i>
                                </a>
                            <?php else: ?>
                                <a href="/rentbridge/agent/case.php?id=<?= (int)$c['id'] ?>"
                                   class="btn btn-sm btn-outline-dark">
                                    View <i class="bi bi-arrow-right"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p class="text-secondary small mt-3 mb-0">
        Showing <?= count($cases) ?> <?= count($cases) === 1 ? 'case' : 'cases' ?>
        <?php if ($searchQuery): ?> (filtered)<?php endif; ?>
    </p>
<?php endif; ?>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/agent_layout.php';