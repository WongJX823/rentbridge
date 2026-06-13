<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('landlord');

$pdo = db();
$userId = current_user_id();

$tab = $_GET['tab'] ?? 'all';
$validTabs = ['all','pending','available','booked','rented','hidden','rejected'];
if (!in_array($tab, $validTabs, true)) $tab = 'all';

$searchQuery = trim($_GET['q'] ?? '');

// Status mapping per tab
$statusGroups = [
    'all'        => ['pending_approval','available','booked','rented','hidden','rejected'],
    'pending'    => ['pending_approval'],
    'available'  => ['available'],
    'booked'     => ['booked'],
    'rented'     => ['rented'],
    'hidden'     => ['hidden'],
    'rejected'   => ['rejected'],
];

// Counts per tab
$counts = [];
foreach ($statusGroups as $key => $statuses) {
    $ph = implode(',', array_fill(0, count($statuses), '?'));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM properties WHERE landlord_id = ? AND status IN ($ph)");
    $stmt->execute(array_merge([$userId], $statuses));
    $counts[$key] = (int)$stmt->fetchColumn();
}

// Build query for current tab
$selected = $statusGroups[$tab];
$ph = implode(',', array_fill(0, count($selected), '?'));
$where  = "p.landlord_id = ? AND p.status IN ($ph)";
$params = array_merge([$userId], $selected);

if ($searchQuery !== '') {
    $where .= " AND (p.title LIKE ? OR p.city LIKE ?)";
    $like = '%' . $searchQuery . '%';
    $params[] = $like;
    $params[] = $like;
}

$stmt = $pdo->prepare("
    SELECT p.id, p.title, p.property_type, p.city, p.monthly_rent, p.status,
           p.agent_verified_at, p.created_at,
           (SELECT image_path FROM property_images
             WHERE property_id = p.id ORDER BY is_primary DESC, id LIMIT 1) AS image_path,
           (SELECT COUNT(*) FROM bookings
             WHERE property_id = p.id AND status = 'pending_landlord') AS pending_requests
      FROM properties p
     WHERE $where
     ORDER BY
       CASE
         WHEN p.status = 'pending_approval' THEN 0
         WHEN p.status = 'available' THEN 1
         ELSE 2
       END,
       p.created_at DESC
");
$stmt->execute($params);
$properties = $stmt->fetchAll();

$pageTitle = 'Property Register';
$activeNav = 'properties';

function build_landlord_tab_url(string $tab, string $q): string {
    $params = ['tab' => $tab];
    if ($q !== '') $params['q'] = $q;
    return '?' . http_build_query($params);
}

$pageTabs = [
    ['label'=>'All',       'href'=>build_landlord_tab_url('all',       $searchQuery), 'active'=>$tab==='all',       'count'=>$counts['all']],
    ['label'=>'Pending',   'href'=>build_landlord_tab_url('pending',   $searchQuery), 'active'=>$tab==='pending',   'count'=>$counts['pending']],
    ['label'=>'Available', 'href'=>build_landlord_tab_url('available', $searchQuery), 'active'=>$tab==='available', 'count'=>$counts['available']],
    ['label'=>'Booked',    'href'=>build_landlord_tab_url('booked',    $searchQuery), 'active'=>$tab==='booked',    'count'=>$counts['booked']],
    ['label'=>'Rented',    'href'=>build_landlord_tab_url('rented',    $searchQuery), 'active'=>$tab==='rented',    'count'=>$counts['rented']],
    ['label'=>'Hidden',    'href'=>build_landlord_tab_url('hidden',    $searchQuery), 'active'=>$tab==='hidden',    'count'=>$counts['hidden']],
    ['label'=>'Rejected',  'href'=>build_landlord_tab_url('rejected',  $searchQuery), 'active'=>$tab==='rejected',  'count'=>$counts['rejected']],
];

ob_start();
?>
<form method="GET" class="row g-2 align-items-end">
    <input type="hidden" name="tab" value="<?= e($tab) ?>">
    <div class="col-md-9">
        <label class="form-label small fw-semibold text-secondary text-uppercase">Search</label>
        <input type="text" name="q" value="<?= e($searchQuery) ?>"
               class="form-control" placeholder="Property title or city">
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

function landlord_status_badge(string $status): array {
    return match ($status) {
        'pending_approval' => ['Pending review', 'warning'],
        'available'        => ['Available',      'success'],
        'booked'           => ['Booked',         'info'],
        'rented'           => ['Rented',         'primary'],
        'hidden'           => ['Hidden',         'secondary'],
        'rejected'         => ['Rejected',       'danger'],
        default            => [$status, 'secondary'],
    };
}

ob_start();
?>

<!-- ADD PROPERTY BUTTON -->
<div class="d-flex justify-content-end mb-3">
    <a href="/rentbridge/landlord/add_property.php" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> List a new property
    </a>
</div>

<?php if (empty($properties)): ?>
    <div class="text-center py-5 bg-white rounded-3 border">
        <i class="bi bi-buildings" style="font-size: 3rem; color: rgba(15,44,82,0.15);"></i>
        <h4 class="mt-3">No properties here</h4>
        <p class="text-secondary small">
            <?php if ($searchQuery): ?>
                Try a different search.
            <?php elseif ($tab === 'all'): ?>
                You haven't listed any property yet. Click "List a new property" to start.
            <?php else: ?>
                Nothing in the "<?= e($tab) ?>" category.
            <?php endif; ?>
        </p>
    </div>
<?php else: ?>

    <!-- PROPERTY GRID -->
    <div class="row g-3">
        <?php foreach ($properties as $p):
            [$statusLabel, $statusColor] = landlord_status_badge($p['status']);
        ?>
            <div class="col-md-6 col-lg-4">
                <a href="/rentbridge/landlord/property.php?id=<?= (int)$p['id'] ?>"
                   class="d-block text-decoration-none text-dark">
                    <div class="bg-white border rounded-3 overflow-hidden h-100"
                         style="transition: transform 0.15s, box-shadow 0.15s;"
                         onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(15,44,82,0.08)'"
                         onmouseout="this.style.transform='';this.style.boxShadow=''">

                        <!-- IMAGE -->
                        <div style="aspect-ratio: 16/10; background: linear-gradient(135deg,#E6ECF4,#E4F2EA); position:relative;">
                            <?php if (!empty($p['image_path'])): ?>
                                <img src="/rentbridge/<?= e($p['image_path']) ?>"
                                     style="width:100%; height:100%; object-fit:cover;" alt="">
                            <?php else: ?>
                                <div style="display:flex; align-items:center; justify-content:center;
                                            height:100%; color:rgba(15,44,82,0.2);">
                                    <i class="bi bi-camera" style="font-size:2rem;"></i>
                                </div>
                            <?php endif; ?>

                            <!-- Status badge top-right -->
                            <span class="badge bg-<?= $statusColor ?>"
                                  style="position:absolute; top:10px; right:10px;">
                                <?= e($statusLabel) ?>
                            </span>

                            <!-- Pending request badge -->
                            <?php if ((int)$p['pending_requests'] > 0): ?>
                                <span class="badge bg-danger"
                                      style="position:absolute; top:10px; left:10px;">
                                    <i class="bi bi-bell-fill"></i>
                                    <?= (int)$p['pending_requests'] ?>
                                    request<?= $p['pending_requests'] === 1 ? '' : 's' ?>
                                </span>
                            <?php endif; ?>

                            <!-- Agent verified badge -->
                            <?php if (!empty($p['agent_verified_at'])): ?>
                                <span class="badge bg-success"
                                      style="position:absolute; bottom:10px; left:10px;">
                                    <i class="bi bi-patch-check-fill"></i> Verified
                                </span>
                            <?php endif; ?>
                        </div>

                        <!-- DETAILS -->
                        <div class="p-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <code class="text-secondary small">#<?= (int)$p['id'] ?></code>
                                <small class="text-secondary">
                                    <?= e(ucfirst(str_replace('_',' ', $p['property_type']))) ?>
                                </small>
                            </div>

                            <h6 class="mb-2"><?= e($p['title']) ?></h6>

                            <div class="small text-secondary mb-2">
                                <i class="bi bi-geo-alt"></i> <?= e($p['city']) ?>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong class="text-emerald">
                                        RM <?= number_format((float)$p['monthly_rent']) ?>
                                    </strong>
                                    <small class="text-secondary">/ month</small>
                                </div>
                                <small class="text-secondary">
                                    <?= e(date('d M Y', strtotime($p['created_at']))) ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>

    <p class="text-secondary small mt-3 mb-0">
        Showing <?= count($properties) ?>
        propert<?= count($properties) === 1 ? 'y' : 'ies' ?>
        <?php if ($searchQuery): ?>(filtered)<?php endif; ?>
    </p>

<?php endif; ?>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/landlord_layout.php';