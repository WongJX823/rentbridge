<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('student');

$pdo = db();
$userId = current_user_id();

// Get student profile
$stmt = $pdo->prepare('SELECT * FROM students WHERE user_id = ?');
$stmt->execute([$userId]);
$me = $stmt->fetch();

// Pending contracts (need signing)
$stmt = $pdo->prepare("
    SELECT c.id, c.contract_code, p.title AS property_title
      FROM contracts c
      JOIN properties p ON p.id = c.property_id
     WHERE c.student_id = ?
       AND c.status = 'pending_signatures'
       AND c.student_signed_at IS NULL
     ORDER BY c.created_at DESC
");
$stmt->execute([$userId]);
$pendingContracts = $stmt->fetchAll();

// Recent properties (top 6, available)
$stmt = $pdo->prepare("
    SELECT p.id, p.title, p.city, p.monthly_rent, p.property_type,
           (SELECT image_path FROM property_images
             WHERE property_id = p.id ORDER BY is_primary DESC, id LIMIT 1) AS image_path
      FROM properties p
     WHERE p.status = 'available'
     ORDER BY p.agent_verified_at IS NULL, p.created_at DESC
     LIMIT 6
");
$stmt->execute();
$recentProperties = $stmt->fetchAll();

// Total available properties
$totalAvailable = (int)$pdo->query("SELECT COUNT(*) FROM properties WHERE status = 'available'")->fetchColumn();

$pageTitle = 'Browse properties';
$activeNav = 'dashboard';

ob_start();
?>

<!-- WELCOME -->
<div class="mb-4">
    <h4 class="mb-1" style="font-family: 'Fraunces', serif;">
        Welcome back, <?= e($me['preferred_name'] ?: $me['full_name']) ?>.
    </h4>
    <p class="text-secondary mb-0">
        <?= $totalAvailable ?> propert<?= $totalAvailable === 1 ? 'y' : 'ies' ?> available near UTeM.
    </p>
</div>

<!-- PENDING CONTRACT ALERT -->
<?php foreach ($pendingContracts as $pc): ?>
    <div class="alert d-flex align-items-center gap-3 mb-3"
         style="background:#FFF4D6; border-color:#D4A017; color:#7C5E0A;">
        <i class="bi bi-pen-fill fs-4"></i>
        <div class="flex-grow-1">
            <strong>Contract ready for your signature</strong>
            <div class="small">
                <?= e($pc['contract_code']) ?> — <?= e($pc['property_title']) ?>
            </div>
        </div>
        <a href="/rentbridge/contracts/view.php?id=<?= (int)$pc['id'] ?>"
           class="btn btn-sm btn-success">
            Review &amp; sign <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>
<?php endforeach; ?>

<!-- QUICK ACTIONS -->
<div class="row g-3 mb-4">
    <div class="col-md-6">
        <a href="/rentbridge/listings.php"
           class="d-block bg-white rounded-3 border p-4 text-decoration-none text-dark h-100">
            <div class="d-flex align-items-center gap-3">
                <div style="width:48px; height:48px; background:#E4F2EA; border-radius:12px;
                            display:flex; align-items:center; justify-content:center; color:#2E8B57;">
                    <i class="bi bi-search fs-3"></i>
                </div>
                <div>
                    <h5 class="mb-1">All listings</h5>
                    <small class="text-secondary">Browse every available property</small>
                </div>
            </div>
        </a>
    </div>
    <div class="col-md-6">
        <a href="/rentbridge/student/saved.php"
           class="d-block bg-white rounded-3 border p-4 text-decoration-none text-dark h-100">
            <div class="d-flex align-items-center gap-3">
                <div style="width:48px; height:48px; background:#FFF4D6; border-radius:12px;
                            display:flex; align-items:center; justify-content:center; color:#D4A017;">
                    <i class="bi bi-bookmark-heart fs-3"></i>
                </div>
                <div>
                    <h5 class="mb-1">Saved properties</h5>
                    <small class="text-secondary">Properties you bookmarked</small>
                </div>
            </div>
        </a>
    </div>
</div>

<!-- RECENT PROPERTIES -->
<?php if (!empty($recentProperties)): ?>
<h5 class="mb-3" style="font-family: 'Fraunces', serif;">Recent listings</h5>
<div class="row g-3 mb-4">
    <?php foreach ($recentProperties as $p): ?>
        <div class="col-md-4">
            <a href="/rentbridge/properties/<?= (int)$p['id'] ?>"
               class="d-block text-decoration-none text-dark">
                <div class="bg-white border rounded-3 overflow-hidden h-100"
                     style="transition: transform 0.15s, box-shadow 0.15s;"
                     onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(15,44,82,0.08)'"
                     onmouseout="this.style.transform='';this.style.boxShadow=''">
                    <div style="aspect-ratio: 4/3; background: linear-gradient(135deg,#E6ECF4,#E4F2EA);">
                        <?php if (!empty($p['image_path'])): ?>
                            <img src="/rentbridge/<?= e($p['image_path']) ?>"
                                 style="width:100%; height:100%; object-fit:cover;" alt="">
                        <?php endif; ?>
                    </div>
                    <div class="p-3">
                        <h6 class="mb-1"><?= e($p['title']) ?></h6>
                        <div class="small text-secondary mb-2">
                            <i class="bi bi-geo-alt"></i> <?= e($p['city']) ?>
                            · <?= e(ucfirst(str_replace('_', ' ', $p['property_type']))) ?>
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
require __DIR__ . '/../includes/student_layout.php';