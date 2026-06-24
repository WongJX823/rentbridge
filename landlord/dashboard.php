<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/contracts.php';
require_role('landlord');

$pdo = db();
$userId = current_user_id();

// Check for contract expiry notifications (lazy, deduped)
check_contract_expiry_notifications();

// Landlord profile
$stmt = $pdo->prepare("SELECT full_name, preferred_name FROM landlords WHERE user_id = ?");
$stmt->execute([$userId]);
$me = $stmt->fetch();

// Counts
$counts = [];

$stmt = $pdo->prepare("SELECT COUNT(*) FROM properties WHERE landlord_id = ?");
$stmt->execute([$userId]);
$counts['total_properties'] = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM properties WHERE landlord_id = ? AND status = 'available'");
$stmt->execute([$userId]);
$counts['available'] = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM properties WHERE landlord_id = ? AND status = 'pending_approval'");
$stmt->execute([$userId]);
$counts['pending_approval'] = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM properties WHERE landlord_id = ? AND status = 'rented'");
$stmt->execute([$userId]);
$counts['rented'] = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tenancies WHERE landlord_id = ? AND status = 'pending_landlord'");
$stmt->execute([$userId]);
$counts['pending_tenancies'] = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tenancies WHERE landlord_id = ? AND status = 'active'");
$stmt->execute([$userId]);
$counts['active_tenancies'] = (int)$stmt->fetchColumn();

// Pending tenancies that need attention
$stmt = $pdo->prepare("
    SELECT b.id, b.created_at, b.monthly_rent,
           p.title AS property_title,
           s.full_name AS student_name,
           s.matric_no
      FROM tenancies b
      JOIN properties p ON p.id = b.property_id
      JOIN students s ON s.user_id = b.student_id
     WHERE b.landlord_id = ?
       AND b.status = 'pending_landlord'
     ORDER BY b.created_at ASC
     LIMIT 5
");
$stmt->execute([$userId]);
$pendingTenancies = $stmt->fetchAll();

// Recent properties (top 4)
$stmt = $pdo->prepare("
    SELECT p.id, p.title, p.city, p.monthly_rent, p.status,
           (SELECT image_path FROM property_images
             WHERE property_id = p.id ORDER BY is_primary DESC, id LIMIT 1) AS image_path
      FROM properties p
     WHERE p.landlord_id = ?
     ORDER BY p.created_at DESC
     LIMIT 4
");
$stmt->execute([$userId]);
$recentProperties = $stmt->fetchAll();

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';

function landlord_prop_status_badge(string $status): array {
    return match ($status) {
        'pending_approval' => ['Pending review', 'warning'],
        'available'        => ['Available',      'success'],
        'reserved'           => ['Reserved',         'info'],
        'rented'           => ['Rented',         'primary'],
        'hidden'           => ['Hidden',         'secondary'],
        'rejected'         => ['Rejected',       'danger'],
        default            => [$status,          'secondary'],
    };
}

ob_start();
?>

<!-- WELCOME -->
<div class="mb-4">
    <h4 class="mb-1" style="font-family: 'Fraunces', serif;">
        Welcome back, <?= e($me['preferred_name'] ?: $me['full_name']) ?>.
    </h4>
    <p class="text-secondary mb-0">
        You have <?= $counts['total_properties'] ?>
        propert<?= $counts['total_properties'] === 1 ? 'y' : 'ies' ?> listed.
    </p>
</div>

<!-- STAT CARDS -->
<div class="row g-3 mb-4">
    <div class="col-md-4 col-lg-3">
        <div class="bg-white border rounded-3 p-3">
            <div style="width:40px; height:40px; background:#E4F2EA; color:#2E8B57;
                        border-radius:10px; display:flex; align-items:center; justify-content:center;">
                <i class="bi bi-house-check"></i>
            </div>
            <div style="font-family:'Fraunces',serif; font-size:1.8rem; font-weight:600; margin-top:8px;">
                <?= $counts['available'] ?>
            </div>
            <div class="small text-secondary">Available</div>
        </div>
    </div>
    <div class="col-md-4 col-lg-3">
        <div class="bg-white border rounded-3 p-3">
            <div style="width:40px; height:40px; background:#FFF4D6; color:#D4A017;
                        border-radius:10px; display:flex; align-items:center; justify-content:center;">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div style="font-family:'Fraunces',serif; font-size:1.8rem; font-weight:600; margin-top:8px;">
                <?= $counts['pending_approval'] ?>
            </div>
            <div class="small text-secondary">Pending approval</div>
        </div>
    </div>
    <div class="col-md-4 col-lg-3">
        <div class="bg-white border rounded-3 p-3">
            <div style="width:40px; height:40px; background:#E6ECF4; color:#0F2C52;
                        border-radius:10px; display:flex; align-items:center; justify-content:center;">
                <i class="bi bi-key-fill"></i>
            </div>
            <div style="font-family:'Fraunces',serif; font-size:1.8rem; font-weight:600; margin-top:8px;">
                <?= $counts['rented'] ?>
            </div>
            <div class="small text-secondary">Currently rented</div>
        </div>
    </div>
    <div class="col-md-4 col-lg-3">
        <div class="bg-white border rounded-3 p-3"
             style="background: linear-gradient(135deg, #fff 0%, #FFF8E5 100%);">
            <div style="width:40px; height:40px; background:#D4A017; color:white;
                        border-radius:10px; display:flex; align-items:center; justify-content:center;">
                <i class="bi bi-bell-fill"></i>
            </div>
            <div style="font-family:'Fraunces',serif; font-size:1.8rem; font-weight:600; margin-top:8px;
                        color:<?= $counts['pending_tenancies'] > 0 ? '#7C5E0A' : '#0F2C52' ?>;">
                <?= $counts['pending_tenancies'] ?>
            </div>
            <div class="small text-secondary">Tenancy requests</div>
        </div>
    </div>
</div>

<!-- PENDING TENANCY REQUESTS -->
<?php if (!empty($pendingTenancies)): ?>
    <h5 class="mb-3" style="font-family:'Fraunces',serif;">
        <i class="bi bi-exclamation-circle text-warning"></i>
        Tenancy applications waiting for your response
    </h5>
    <div class="bg-white border rounded-3 overflow-hidden mb-4">
        <table class="table mb-0 align-middle">
            <thead style="background:#F4F4EE;">
                <tr>
                    <th class="ps-3">ID</th>
                    <th>Property</th>
                    <th>Student</th>
                    <th>Monthly rent</th>
                    <th>Submitted</th>
                    <th class="text-end pe-3"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($pendingTenancies as $t): ?>
                    <tr>
                        <td class="ps-3">
                            <code class="text-secondary">#<?= (int)$t['id'] ?></code>
                        </td>
                        <td class="small">
                            <strong><?= e($t['property_title']) ?></strong>
                        </td>
                        <td class="small">
                            <?= e($t['student_name']) ?>
                            <div class="text-secondary"><code><?= e($t['matric_no']) ?></code></div>
                        </td>
                        <td>RM <?= number_format((float)$t['monthly_rent']) ?></td>
                        <td class="small text-secondary">
                            <?= e(date('d M Y', strtotime($t['created_at']))) ?>
                        </td>
                        <td class="text-end pe-3">
                            <a href="/rentbridge/landlord/tenancy.php?id=<?= (int)$t['id'] ?>"
                               class="btn btn-sm btn-primary">
                                Review <i class="bi bi-arrow-right"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<!-- QUICK ACTIONS -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <a href="/rentbridge/landlord/add_property.php"
           class="d-block bg-white rounded-3 border p-4 text-decoration-none text-dark h-100">
            <div class="d-flex align-items-center gap-3">
                <div style="width:48px; height:48px; background:#E4F2EA; border-radius:12px;
                            display:flex; align-items:center; justify-content:center; color:#2E8B57;">
                    <i class="bi bi-plus-square fs-3"></i>
                </div>
                <div>
                    <h5 class="mb-1">List a new property</h5>
                    <small class="text-secondary">Add a room, studio, or whole unit</small>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-6">
        <a href="/rentbridge/landlord/properties.php"
           class="d-block bg-white rounded-3 border p-4 text-decoration-none text-dark h-100">
            <div class="d-flex align-items-center gap-3">
                <div style="width:48px; height:48px; background:#E6ECF4; border-radius:12px;
                            display:flex; align-items:center; justify-content:center; color:#0F2C52;">
                    <i class="bi bi-buildings fs-3"></i>
                </div>
                <div>
                    <h5 class="mb-1">My properties</h5>
                    <small class="text-secondary">Manage your listings</small>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- RECENT PROPERTIES -->
<?php if (!empty($recentProperties)): ?>
<h5 class="mb-3" style="font-family:'Fraunces',serif;">Your recent listings</h5>
<div class="row g-3">
    <?php foreach ($recentProperties as $p):
        [$statusLabel, $statusColor] = landlord_prop_status_badge($p['status']);
    ?>
        <div class="col-md-3 col-sm-6">
            <a href="/rentbridge/landlord/property.php?id=<?= (int)$p['id'] ?>"
               class="d-block text-decoration-none text-dark">
                <div class="bg-white border rounded-3 overflow-hidden h-100"
                     style="transition: transform 0.15s, box-shadow 0.15s;"
                     onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(15,44,82,0.08)'"
                     onmouseout="this.style.transform='';this.style.boxShadow=''">
                    <div style="aspect-ratio: 4/3; background: linear-gradient(135deg,#E6ECF4,#E4F2EA); position:relative;">
                        <?php if (!empty($p['image_path'])): ?>
                            <img src="/rentbridge/<?= e($p['image_path']) ?>"
                                 style="width:100%; height:100%; object-fit:cover;" alt="">
                        <?php endif; ?>
                        <span class="badge bg-<?= $statusColor ?>"
                              style="position:absolute; top:8px; right:8px;">
                            <?= e($statusLabel) ?>
                        </span>
                    </div>
                    <div class="p-3">
                        <h6 class="mb-1 small"><?= e($p['title']) ?></h6>
                        <div class="small text-secondary mb-2">
                            <i class="bi bi-geo-alt"></i> <?= e($p['city']) ?>
                        </div>
                        <strong class="text-emerald">
                            RM <?= number_format((float)$p['monthly_rent']) ?>
                        </strong>
                        <small class="text-secondary">/ month</small>
                    </div>
                </div>
            </a>
        </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/landlord_layout.php';