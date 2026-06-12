<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = db();

// --- TAB STATE ---
$tab = $_GET['tab'] ?? 'all';
$validTabs = ['all', 'active', 'pending'];
if (!in_array($tab, $validTabs, true)) $tab = 'all';

// --- SEARCH ---
$searchQuery = trim($_GET['q'] ?? '');

// --- TAB COUNTS ---
$counts = [];

$counts['all'] = (int)$pdo->query(
    "SELECT COUNT(*) FROM users WHERE primary_role = 'landlord'"
)->fetchColumn();

// Active: landlord has at least one approved property
$counts['active'] = (int)$pdo->query("
    SELECT COUNT(DISTINCT u.id)
      FROM users u
      JOIN properties p ON p.landlord_id = u.id
     WHERE u.primary_role = 'landlord'
       AND u.status = 'active'
       AND p.status IN ('available','booked','rented')
")->fetchColumn();

// Pending: landlord has at least one property in pending_approval
$counts['pending'] = (int)$pdo->query("
    SELECT COUNT(DISTINCT u.id)
      FROM users u
      JOIN properties p ON p.landlord_id = u.id
     WHERE u.primary_role = 'landlord'
       AND p.status = 'pending_approval'
")->fetchColumn();

// --- BUILD QUERY ---
$where  = "u.primary_role = 'landlord'";
$params = [];
$extraJoin = '';

if ($tab === 'active') {
    $extraJoin = "JOIN properties p ON p.landlord_id = u.id AND p.status IN ('available','booked','rented')";
    $where .= " AND u.status = 'active'";
} elseif ($tab === 'pending') {
    $extraJoin = "JOIN properties p ON p.landlord_id = u.id AND p.status = 'pending_approval'";
}

if ($searchQuery !== '') {
    $where .= " AND (l.full_name LIKE ? OR u.email LIKE ?)";
    $like = '%' . $searchQuery . '%';
    $params[] = $like;
    $params[] = $like;
}

$stmt = $pdo->prepare("
    SELECT DISTINCT
           u.id, u.email, u.status, u.created_at,
           l.full_name, l.ic_no, l.phone, l.verified,
           (SELECT COUNT(*) FROM properties WHERE landlord_id = u.id) AS total_properties,
           (SELECT COUNT(*) FROM properties WHERE landlord_id = u.id AND status = 'pending_approval') AS pending_properties,
           (SELECT COUNT(*) FROM properties WHERE landlord_id = u.id AND status IN ('available','booked','rented')) AS active_properties
      FROM users u
      JOIN landlords l ON l.user_id = u.id
      $extraJoin
     WHERE $where
     ORDER BY u.created_at DESC
");
$stmt->execute($params);
$landlords = $stmt->fetchAll();

// --- LAYOUT SETUP ---
$pageTitle = 'Landlords';
$activeNav = 'landlords';

function build_landlord_tab_url(string $tab, string $q): string {
    $params = ['tab' => $tab];
    if ($q !== '') $params['q'] = $q;
    return '?' . http_build_query($params);
}

$pageTabs = [
    ['label' => 'All',     'href' => build_landlord_tab_url('all',     $searchQuery), 'active' => $tab==='all',     'count' => $counts['all']],
    ['label' => 'Active',  'href' => build_landlord_tab_url('active',  $searchQuery), 'active' => $tab==='active',  'count' => $counts['active']],
    ['label' => 'Pending', 'href' => build_landlord_tab_url('pending', $searchQuery), 'active' => $tab==='pending', 'count' => $counts['pending']],
];

// Search drawer
ob_start();
?>
<form method="GET" class="row g-2 align-items-end">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <div class="col-md-9">
        <label class="form-label small fw-semibold text-secondary text-uppercase">Search</label>
        <input type="text" name="q" value="<?= e($searchQuery) ?>"
               class="form-control" placeholder="Search by name or email">
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

// Page content
ob_start();
?>

<?php if (empty($landlords)): ?>
    <div class="text-center py-5 bg-white rounded-3 border">
        <i class="bi bi-house-heart" style="font-size: 3rem; color: rgba(15,44,82,0.15);"></i>
        <h4 class="mt-3">No landlords found</h4>
        <p class="text-secondary small">
            <?= $searchQuery ? 'Try a different search.' : 'No landlords in this tab.' ?>
        </p>
    </div>
<?php else: ?>
    <div class="bg-white border rounded-3 overflow-hidden">
        <table class="table mb-0 align-middle">
            <thead style="background:#F4F4EE;">
    <tr>
        <th class="ps-3">ID</th>
        <th>Landlord</th>
        <th>Email</th>
                    <th>Phone</th>
                    <th>Properties</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th class="text-end pe-3"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($landlords as $l):
                    $hasPending = $l['pending_properties'] > 0;
                ?>
                    <tr>
    <td class="ps-3">
        <code class="text-secondary">#<?= (int)$l['id'] ?></code>
    </td>
    <td>
        <strong><?= e($l['full_name']) ?></strong>  
                            <?php if ($l['verified']): ?>
                                <i class="bi bi-patch-check-fill text-success ms-1" title="Verified"></i>
                            <?php endif; ?>
                            <div class="small text-secondary">
                                IC: <code><?= e($l['ic_no']) ?></code>
                            </div>
                        </td>
                        <td class="small"><?= e($l['email']) ?></td>
                        <td class="small"><?= e($l['phone'] ?? '—') ?></td>
                        <td class="small">
                            <strong><?= (int)$l['total_properties'] ?></strong> total
                            <?php if ($hasPending): ?>
                                <div class="text-warning">
                                    <i class="bi bi-clock"></i>
                                    <?= (int)$l['pending_properties'] ?> pending
                                </div>
                            <?php endif; ?>
                            <?php if ((int)$l['active_properties'] > 0): ?>
                                <div class="text-success small">
                                    <i class="bi bi-check-circle"></i>
                                    <?= (int)$l['active_properties'] ?> active
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($l['status'] === 'suspended'): ?>
                                <span class="badge bg-danger">Suspended</span>
                            <?php elseif ($hasPending): ?>
                                <span class="badge bg-warning text-dark">Pending review</span>
                            <?php else: ?>
                                <span class="badge bg-success">Active</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-secondary">
                            <?= e(date('d M Y', strtotime($l['created_at']))) ?>
                        </td>
                        <td class="text-end pe-3">
                            <a href="/rentbridge/admin/user.php?id=<?= (int)$l['id'] ?>"
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
        Showing <?= count($landlords) ?> <?= count($landlords) === 1 ? 'landlord' : 'landlords' ?>
        <?php if ($searchQuery): ?> (filtered)<?php endif; ?>
    </p>
<?php endif; ?>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/admin_layout.php';