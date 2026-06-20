<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/saved.php';
require_once __DIR__ . '/includes/save_button.php';

// Pre-fetch saved status for all properties on this page (if logged in)
$savedMap = [];
if (is_logged_in() && !empty($properties)) {
    $propertyIds = array_column($properties, 'id');
    $savedMap = get_saved_property_ids(current_user_id(), $propertyIds);
}

$pdo = db();

// Filters
$searchQuery = trim($_GET['q'] ?? '');
$filterCity  = trim($_GET['city'] ?? '');
$filterType  = trim($_GET['type'] ?? '');
$filterMin   = trim($_GET['min_rent'] ?? '');
$filterMax   = trim($_GET['max_rent'] ?? '');
$sortBy      = $_GET['sort'] ?? 'recent';

// Build query
$where = "p.status = 'available'";
$params = [];

// Landlords don't see their own properties
if (is_logged_in() && current_role() === 'landlord') {
    $where .= " AND p.landlord_id != ?";
    $params[] = current_user_id();
}

if ($searchQuery !== '') {
    $where .= " AND (p.title LIKE ? OR p.address LIKE ? OR p.city LIKE ?)";
    $like = '%' . $searchQuery . '%';
    array_push($params, $like, $like, $like);
}
if ($filterCity !== '') {
    $where .= " AND p.city = ?";
    $params[] = $filterCity;
}
if ($filterType !== '') {
    $where .= " AND p.property_type = ?";
    $params[] = $filterType;
}
if ($filterMin !== '' && is_numeric($filterMin)) {
    $where .= " AND p.monthly_rent >= ?";
    $params[] = (float)$filterMin;
}
if ($filterMax !== '' && is_numeric($filterMax)) {
    $where .= " AND p.monthly_rent <= ?";
    $params[] = (float)$filterMax;
}

$orderBy = match($sortBy) {
    'price_asc'  => 'p.monthly_rent ASC',
    'price_desc' => 'p.monthly_rent DESC',
    'verified'   => 'p.agent_verified_at DESC, p.created_at DESC',
    default      => 'p.created_at DESC',
};

$stmt = $pdo->prepare("
    SELECT p.*,
           l.full_name      AS landlord_name,
           l.preferred_name AS landlord_preferred_name,
           (SELECT image_path FROM property_images
             WHERE property_id = p.id ORDER BY is_primary DESC, id LIMIT 1) AS image_path
      FROM properties p
      JOIN landlords l ON l.user_id = p.landlord_id
     WHERE $where
     ORDER BY $orderBy
");
$stmt->execute($params);
$properties = $stmt->fetchAll();

// Cities for filter
$cities = $pdo->query("SELECT DISTINCT city FROM properties WHERE status='available' ORDER BY city")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Browse Properties';
$activeNav = 'browse';

ob_start();
?>

<!-- HEADER -->
<div class="d-flex justify-content-between align-items-end mb-3 flex-wrap gap-2">
    <div>
        <h2 class="mb-1" style="font-family:'Fraunces',serif;">Browse properties</h2>
        <p class="text-secondary mb-0 small">
            <?= count($properties) ?> propert<?= count($properties)===1?'y':'ies' ?> available
        </p>
    </div>
    <select name="sort" class="form-select form-select-sm" style="max-width:200px;"
            onchange="window.location='?<?= http_build_query(array_filter(array_merge($_GET, ['sort' => '__SORT__']))) ?>'.replace('__SORT__', this.value)">
        <option value="recent"     <?= $sortBy==='recent'?'selected':'' ?>>Sort: Newest first</option>
        <option value="verified"   <?= $sortBy==='verified'?'selected':'' ?>>Sort: Verified first</option>
        <option value="price_asc"  <?= $sortBy==='price_asc'?'selected':'' ?>>Sort: Price (low to high)</option>
        <option value="price_desc" <?= $sortBy==='price_desc'?'selected':'' ?>>Sort: Price (high to low)</option>
    </select>
</div>

<!-- FILTERS (compact inline) -->
<form method="GET" class="bg-white border rounded-3 p-3 mb-4">
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <input type="text" name="q" value="<?= e($searchQuery) ?>"
                   class="form-control form-control-sm" placeholder="Search by title or area">
        </div>
        <div class="col-md-2">
            <select name="city" class="form-select form-select-sm">
                <option value="">All cities</option>
                <?php foreach ($cities as $c): ?>
                    <option value="<?= e($c) ?>" <?= $filterCity===$c?'selected':'' ?>><?= e($c) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="type" class="form-select form-select-sm">
                <option value="">Any type</option>
                <option value="room"       <?= $filterType==='room'?'selected':'' ?>>Room</option>
                <option value="studio"     <?= $filterType==='studio'?'selected':'' ?>>Studio</option>
                <option value="whole_unit" <?= $filterType==='whole_unit'?'selected':'' ?>>Whole unit</option>
            </select>
        </div>
        <div class="col-md-1">
            <input type="number" name="min_rent" value="<?= e($filterMin) ?>"
                   class="form-control form-control-sm" placeholder="Min" step="50">
        </div>
        <div class="col-md-1">
            <input type="number" name="max_rent" value="<?= e($filterMax) ?>"
                   class="form-control form-control-sm" placeholder="Max" step="50">
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-primary flex-fill">
                <i class="bi bi-funnel"></i> Filter
            </button>
            <?php if ($searchQuery || $filterCity || $filterType || $filterMin || $filterMax): ?>
                <a href="?" class="btn btn-sm btn-outline-secondary">Clear</a>
            <?php endif; ?>
        </div>
    </div>
</form>

<!-- LOGIN PROMPT for guests -->
<?php if (!is_logged_in()): ?>
<div class="alert alert-light border d-flex gap-3 align-items-center mb-4 small">
    <i class="bi bi-info-circle text-secondary fs-4"></i>
    <div class="flex-grow-1">
        Browsing as guest. <strong>Sign up</strong> or <strong>log in</strong>
        to save listings, message landlords, and book properties.
    </div>
    <div class="d-flex gap-2">
        <a href="/rentbridge/auth/login.php" class="btn btn-sm btn-primary">Log in</a>
        <a href="/rentbridge/auth/register_student.php" class="btn btn-sm btn-outline-secondary">Sign up</a>
    </div>
</div>
<?php endif; ?>

<!-- PROPERTY GRID -->
<?php if (empty($properties)): ?>
    <div class="text-center py-5 bg-white rounded-3 border">
        <i class="bi bi-house" style="font-size: 3rem; color: rgba(15,44,82,0.15);"></i>
        <h4 class="mt-3">No properties match your filters</h4>
        <p class="text-secondary small">Try clearing some filters or broadening your search.</p>
        <a href="/rentbridge/listings.php" class="btn btn-outline-dark mt-2">
            Clear all filters
        </a>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($properties as $p): ?>
            <div class="col-md-6 col-lg-4 col-xl-3">
                <a href="/rentbridge/property.php?id=<?= (int)$p['id'] ?>"
                   class="text-decoration-none text-dark">
                    <div class="bg-white border rounded-3 overflow-hidden h-100"
                         style="transition: transform 0.15s, box-shadow 0.15s;"
                         onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(15,44,82,0.08)'"
                         onmouseout="this.style.transform='';this.style.boxShadow=''">

                        <div style="aspect-ratio: 4/3; background: linear-gradient(135deg,#E6ECF4,#E4F2EA); position:relative;">
                            <?php if (!empty($p['image_path'])): ?>
                                <img src="/rentbridge/<?= e($p['image_path']) ?>"
                                     style="width:100%; height:100%; object-fit:cover;" alt="">
                            <?php endif; ?>
                            <?php if (!empty($p['agent_verified_at'])): ?>
                                <span class="badge bg-success"
                                      style="position:absolute; top:10px; left:10px;">
                                    <i class="bi bi-patch-check-fill"></i> Verified
                                </span>
                            <?php endif; ?>
                                <?php render_save_button((int)$p['id'], isset($savedMap[$p['id']]), 'md', 'overlay'); ?>

                        </div>

                        <div class="p-3">
                            <div class="d-flex justify-content-between mb-1">
                                <small class="text-secondary">
                                    <?= e(ucfirst(str_replace('_',' ', $p['property_type']))) ?>
                                </small>
                                <small class="text-secondary">
                                    <?= e(ucfirst($p['furnishing'])) ?>
                                </small>
                            </div>
                            <h6 class="mb-2" style="font-size:0.95rem;"><?= e($p['title']) ?></h6>
                            <div class="small text-secondary mb-2">
                                <i class="bi bi-geo-alt"></i> <?= e($p['city']) ?>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong class="text-emerald">RM <?= number_format((float)$p['monthly_rent']) ?></strong>
                                    <small class="text-secondary">/ mo</small>
                                </div>
                                <small class="text-secondary text-truncate ms-2" style="max-width:120px;">
                                    <?= e($p['landlord_preferred_name'] ?: $p['landlord_name']) ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php render_save_button_script(); ?>

<?php
$pageContent = ob_get_clean();


// Use appropriate layout: public for guests, role layout for logged-in users
if (is_logged_in()) {
    $role = current_role();
    $layoutFile = match($role) {
        'student'  => 'student_layout.php',
        'landlord' => 'landlord_layout.php',
        'agent'    => 'agent_layout.php',
        'admin'    => 'admin_layout.php',
        default    => 'public_layout.php',
    };
    require __DIR__ . '/includes/' . $layoutFile;
} else {
    require __DIR__ . '/includes/public_layout.php';
}

