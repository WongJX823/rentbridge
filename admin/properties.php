<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = db();

// --- TAB STATE ---
$tab = $_GET['tab'] ?? 'all';
$validTabs = ['all', 'pending', 'available', 'booked', 'rented', 'hidden', 'rejected'];
if (!in_array($tab, $validTabs, true)) $tab = 'all';

// --- FILTER STATE ---
$searchQuery = trim($_GET['q'] ?? '');
$filterCity  = trim($_GET['city'] ?? '');

// --- TAB COUNTS ---
$counts = [];
foreach (['pending_approval', 'available', 'booked', 'rented', 'hidden', 'rejected'] as $s) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM properties WHERE status = ?");
    $stmt->execute([$s]);
    $key = $s === 'pending_approval' ? 'pending' : $s;
    $counts[$key] = (int)$stmt->fetchColumn();
}
$counts['all'] = array_sum($counts);

// --- BUILD QUERY ---
$where  = "1 = 1";
$params = [];

if ($tab !== 'all') {
    $dbStatus = $tab === 'pending' ? 'pending_approval' : $tab;
    $where .= ' AND p.status = ?';
    $params[] = $dbStatus;
}

if ($searchQuery !== '') {
    $where .= ' AND (p.title LIKE ? OR p.address LIKE ?)';
    $like = '%' . $searchQuery . '%';
    $params[] = $like;
    $params[] = $like;
}
if ($filterCity !== '') {
    $where .= ' AND p.city = ?';
    $params[] = $filterCity;
}

// Build query — JOIN current active booking's agent if any
$stmt = $pdo->prepare("
    SELECT p.id, p.title, p.property_type, p.city, p.state, p.monthly_rent,
           p.status, p.agent_verified_at, p.created_at,
           l.full_name AS landlord_name,
           u.id        AS landlord_user_id,
           -- Find most recent active booking with assigned agent
           (SELECT b.id FROM bookings b
              WHERE b.property_id = p.id
                AND b.status IN ('agent_verifying','agent_verified','contract_pending','active')
              ORDER BY b.created_at DESC LIMIT 1) AS active_booking_id,
           (SELECT a.full_name FROM bookings b
              JOIN agents a ON a.user_id = b.agent_id
              WHERE b.property_id = p.id
                AND b.status IN ('agent_verifying','agent_verified','contract_pending','active')
              ORDER BY b.created_at DESC LIMIT 1) AS active_agent_name,
           (SELECT b.status FROM bookings b
              WHERE b.property_id = p.id
                AND b.status IN ('agent_verifying','agent_verified','contract_pending','active')
              ORDER BY b.created_at DESC LIMIT 1) AS active_booking_status
      FROM properties p
      JOIN users u ON u.id = p.landlord_id
      JOIN landlords l ON l.user_id = u.id
     WHERE $where
     ORDER BY
       CASE p.status WHEN 'pending_approval' THEN 0 ELSE 1 END,
       p.created_at DESC
");
$stmt->execute($params);
$properties = $stmt->fetchAll();

// All cities (for filter dropdown)
$cityStmt = $pdo->query("SELECT DISTINCT city FROM properties ORDER BY city");
$cities = $cityStmt->fetchAll(PDO::FETCH_COLUMN);

// --- LAYOUT SETUP ---
$pageTitle = 'Properties';
$activeNav = 'properties';

function build_property_tab_url(string $tab, string $q, string $city): string {
    $params = ['tab' => $tab];
    if ($q !== '')    $params['q'] = $q;
    if ($city !== '') $params['city'] = $city;
    return '?' . http_build_query($params);
}

$pageTabs = [
    ['label' => 'All',       'href' => build_property_tab_url('all',       $searchQuery, $filterCity), 'active' => $tab==='all',       'count' => $counts['all']],
    ['label' => 'Pending',   'href' => build_property_tab_url('pending',   $searchQuery, $filterCity), 'active' => $tab==='pending',   'count' => $counts['pending']],
    ['label' => 'Available', 'href' => build_property_tab_url('available', $searchQuery, $filterCity), 'active' => $tab==='available', 'count' => $counts['available']],
    ['label' => 'Booked',    'href' => build_property_tab_url('booked',    $searchQuery, $filterCity), 'active' => $tab==='booked',    'count' => $counts['booked']],
    ['label' => 'Rented',    'href' => build_property_tab_url('rented',    $searchQuery, $filterCity), 'active' => $tab==='rented',    'count' => $counts['rented']],
    ['label' => 'Hidden',    'href' => build_property_tab_url('hidden',    $searchQuery, $filterCity), 'active' => $tab==='hidden',    'count' => $counts['hidden']],
    ['label' => 'Rejected',  'href' => build_property_tab_url('rejected',  $searchQuery, $filterCity), 'active' => $tab==='rejected',  'count' => $counts['rejected']],
];

// Filter drawer
ob_start();
?>
<form method="GET" class="row g-2 align-items-end">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <div class="col-md-6">
        <label class="form-label small fw-semibold text-secondary text-uppercase">Search</label>
        <input type="text" name="q" value="<?= e($searchQuery) ?>"
               class="form-control" placeholder="Property title or address">
    </div>
    <div class="col-md-3">
        <label class="form-label small fw-semibold text-secondary text-uppercase">City</label>
        <select name="city" class="form-select">
            <option value="">All cities</option>
            <?php foreach ($cities as $c): ?>
                <option value="<?= e($c) ?>" <?= $filterCity===$c?'selected':'' ?>><?= e($c) ?></option>
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
function property_status_badge(string $status): array {
    return match ($status) {
        'pending_approval' => ['Pending review', 'warning'],
        'available'        => ['Available',      'success'],
        'booked'           => ['Booked',         'info'],
        'rented'           => ['Rented',         'primary'],
        'hidden'           => ['Hidden',         'secondary'],
        'rejected'         => ['Rejected',       'danger'],
        default            => [$status,          'secondary'],
    };
}

function booking_short_label(string $status): string {
    return match ($status) {
        'agent_verifying'   => '🔍 inspecting',
        'agent_verified'    => '✓ verified',
        'contract_pending'  => '📝 signing',
        'active'            => '✓ active tenancy',
        default             => $status,
    };
}

// Page content
ob_start();
?>

<?php if (empty($properties)): ?>
    <div class="text-center py-5 bg-white rounded-3 border">
        <i class="bi bi-house" style="font-size: 3rem; color: rgba(15,44,82,0.15);"></i>
        <h4 class="mt-3">No properties found</h4>
        <p class="text-secondary small">
            <?php if ($searchQuery || $filterCity): ?>
                Try adjusting your filters.
            <?php else: ?>
                No properties in this tab yet.
            <?php endif; ?>
        </p>
    </div>
<?php else: ?>
    <div class="bg-white border rounded-3 overflow-hidden">
        <table class="table mb-0 align-middle">
            <thead style="background:#F4F4EE;">
                <tr>
                    <th class="ps-3">Property</th>
                    <th>Landlord</th>
                    <th>Rent</th>
                    <th>Status</th>
                    <th>Inspecting agent</th>
                    <th class="text-end pe-3"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($properties as $p):
                    [$label, $color] = property_status_badge($p['status']);
                    $verified = !empty($p['agent_verified_at']);
                ?>
                    <tr>
                        <td class="ps-3">
                            <strong><?= e($p['title']) ?></strong>
                            <?php if ($verified): ?>
                                <span class="badge bg-success ms-1" title="Agent-verified">✓</span>
                            <?php endif; ?>
                            <div class="small text-secondary">
                                <i class="bi bi-geo-alt"></i> <?= e($p['city']) ?>, <?= e($p['state']) ?>
                                &nbsp;·&nbsp; <?= e(ucfirst($p['property_type'])) ?>
                            </div>
                        </td>
                        <td>
                            <a href="/rentbridge/admin/user.php?id=<?= (int)$p['landlord_user_id'] ?>"
                               class="text-decoration-none text-dark">
                                <?= e($p['landlord_name']) ?>
                            </a>
                        </td>
                        <td>
                            <strong>RM <?= number_format((float)$p['monthly_rent']) ?></strong>
                            <div class="small text-secondary">/ month</div>
                        </td>
                        <td><span class="badge bg-<?= $color ?>"><?= e($label) ?></span></td>
                        <td>
                            <?php if (!empty($p['active_agent_name'])): ?>
                                <strong class="small"><?= e($p['active_agent_name']) ?></strong>
                                <div class="small text-secondary">
                                    <?= e(booking_short_label($p['active_booking_status'])) ?>
                                    <a href="/rentbridge/admin/booking.php?id=<?= (int)$p['active_booking_id'] ?>"
                                       class="text-decoration-none ms-1">
                                        #<?= (int)$p['active_booking_id'] ?>
                                    </a>
                                </div>
                            <?php else: ?>
                                <span class="text-secondary small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-3">
                            <a href="/rentbridge/admin/property.php?id=<?= (int)$p['id'] ?>"
                               class="btn btn-sm btn-outline-dark">
                                Review <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <p class="text-secondary small mt-3 mb-0">
        Showing <?= count($properties) ?> <?= count($properties) === 1 ? 'property' : 'properties' ?>
        <?php if ($searchQuery || $filterCity): ?>
            (filtered)
        <?php endif; ?>
    </p>
<?php endif; ?>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/admin_layout.php';