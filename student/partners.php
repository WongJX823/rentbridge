<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/partners.php';
require_role('student');

$pdo = db();
$userId = current_user_id();

// Check viewer's privacy setting
$stmt = $pdo->prepare("
    SELECT looking_for_housing, housing_pref_city, housing_pref_max_rent
      FROM students WHERE user_id = ?
");
$stmt->execute([$userId]);
$me = $stmt->fetch();

// Filters
$filterCity = trim($_GET['city'] ?? '');
$filterMaxRent = trim($_GET['max_rent'] ?? '');
$filters = [];
if ($filterCity !== '')    $filters['city'] = $filterCity;
if ($filterMaxRent !== '') $filters['max_rent'] = $filterMaxRent;

$posts = list_co_tenancy_posts($userId, $filters);
$myPosts = get_my_co_tenancy_posts($userId);

// All cities for filter dropdown
$cities = $pdo->query("SELECT DISTINCT city FROM properties WHERE status = 'available' ORDER BY city")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Find Housemates';
$activeNav = 'partners';

ob_start();
?>

<?php if (empty($me['looking_for_housing'])): ?>
    <!-- VISIBILITY WARNING -->
    <div class="alert d-flex gap-3 align-items-start mb-4"
         style="background:#FFF4D6; border-color:#D4A017; color:#7C5E0A;">
        <i class="bi bi-eye-slash-fill fs-3"></i>
        <div class="flex-grow-1">
            <strong>You're invisible to other students.</strong><br>
            <small>
                Turn on "Looking for housing" in your Profile Dashboard to be discoverable
                and to see better-matched partners.
            </small>
        </div>
        <a href="/rentbridge/student/profile.php" class="btn btn-sm btn-warning">
            <i class="bi bi-toggle-on me-1"></i> Enable
        </a>
    </div>
<?php endif; ?>

<!-- MY OPEN POSTS -->
<?php if (!empty($myPosts)): ?>
<div class="mb-4">
    <h5 class="mb-2" style="font-family:'Fraunces',serif;">My posts</h5>
    <div class="d-flex flex-column gap-2">
        <?php foreach ($myPosts as $mp):
            $mpColor = match($mp['status']) {
                'open'      => 'success',
                'filled'    => 'primary',
                'cancelled' => 'secondary',
                default     => 'secondary',
            };
        ?>
        <div class="bg-white border rounded-3 p-3 d-flex align-items-center gap-3">
            <div class="flex-grow-1">
                <strong><?= e($mp['property_title']) ?></strong>
                <small class="text-secondary ms-2"><?= e($mp['property_city']) ?></small>
                <span class="badge bg-<?= $mpColor ?> ms-2"><?= ucfirst($mp['status']) ?></span>
                <?php if ($mp['pending_count'] > 0): ?>
                    <span class="badge bg-warning text-dark ms-1"><?= $mp['pending_count'] ?> pending</span>
                <?php endif; ?>
            </div>
            <a href="/rentbridge/student/manage_post.php?id=<?= (int)$mp['id'] ?>"
               class="btn btn-sm btn-outline-primary flex-shrink-0">
                <i class="bi bi-people me-1"></i> Manage
            </a>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- FILTERS -->
<form method="GET" class="d-flex gap-2 mb-4 flex-wrap align-items-center">
    <select name="city" class="form-select form-select-sm" style="max-width:200px;">
        <option value="">All cities</option>
        <?php foreach ($cities as $c): ?>
            <option value="<?= e($c) ?>" <?= $filterCity===$c?'selected':'' ?>><?= e($c) ?></option>
        <?php endforeach; ?>
    </select>
    <input type="number" name="max_rent" value="<?= e($filterMaxRent) ?>"
           class="form-control form-control-sm" placeholder="Max rent RM"
           style="max-width:150px;" step="50">
    <button type="submit" class="btn btn-sm btn-primary">
        <i class="bi bi-funnel"></i> Filter
    </button>
    <?php if ($filterCity || $filterMaxRent): ?>
        <a href="?" class="btn btn-sm btn-outline-secondary">Clear</a>
    <?php endif; ?>
    <span class="text-secondary small ms-auto">
        <?= count($posts) ?> post<?= count($posts) === 1 ? '' : 's' ?>
    </span>
</form>

<!-- POSTS FEED -->
<?php if (empty($posts)): ?>
    <div class="text-center py-5 bg-white rounded-3 border">
        <i class="bi bi-people" style="font-size: 3rem; color: rgba(15,44,82,0.15);"></i>
        <h4 class="mt-3">No co-tenancy posts</h4>
        <p class="text-secondary small">
            <?php if ($filterCity || $filterMaxRent): ?>
                Try removing filters.
            <?php else: ?>
                Browse a property → click "Share with housemates" to start.
            <?php endif; ?>
        </p>
        <a href="/rentbridge/listings.php" class="btn btn-primary mt-2">
            <i class="bi bi-search me-1"></i> Browse properties
        </a>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($posts as $post):
            $perPerson = (float)$post['property_rent'] / max(1, ((int)$post['housemates_needed'] + 1));
            $compat = $post['compatibility'];
        ?>
            <div class="col-md-6 col-lg-4">
                <a href="/rentbridge/property.php?id=<?= (int)$post['property_id'] ?>&from_post=<?= (int)$post['id'] ?>"
                    class="text-decoration-none text-dark d-block h-100">
                    <div class="bg-white border rounded-3 overflow-hidden h-100"
                         style="transition: transform 0.15s, box-shadow 0.15s;"
                         onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(15,44,82,0.08)'"
                         onmouseout="this.style.transform='';this.style.boxShadow=''">

                        <!-- HEADER: Poster + match badge (compact) -->
                        <div class="p-2 d-flex justify-content-between align-items-center"
                             style="border-bottom: 1px solid rgba(15,44,82,0.06);">
                            <div class="d-flex gap-2 align-items-center">
                                <div style="width:28px; height:28px; border-radius:50%;
                                            background:#E4F2EA; color:#0F2C52;
                                            display:flex; align-items:center; justify-content:center;
                                            font-weight:600; font-size:0.85rem;">
                                    <?= strtoupper(substr($post['poster_nickname'] ?: $post['poster_name'], 0, 1)) ?>
                                </div>
                                <div>
                                    <strong style="font-size:0.85rem;"><?= e($post['poster_nickname'] ?: $post['poster_name']) ?></strong>
                                    <div class="text-secondary" style="font-size:0.7rem;">
                                        <?= e(human_time_diff($post['post_created_at'])) ?>
                                    </div>
                                </div>
                            </div>
                            <span class="badge bg-<?= $compat['color'] ?>" style="font-size:0.65rem;"
                                  title="<?= e($compat['description']) ?>">
                                <?= e($compat['label']) ?>
                            </span>
                        </div>

                        <!-- PROPERTY THUMBNAIL (compact 16/9 or just 4/3) -->
                        <div style="aspect-ratio: 4/3; background: linear-gradient(135deg,#E6ECF4,#E4F2EA); position:relative;">
                            <?php if (!empty($post['property_image'])): ?>
                                <img src="/rentbridge/<?= e($post['property_image']) ?>"
                                     style="width:100%; height:100%; object-fit:cover;" alt="">
                            <?php else: ?>
                                <div style="display:flex; align-items:center; justify-content:center; height:100%; color:rgba(15,44,82,0.2);">
                                    <i class="bi bi-camera" style="font-size:2rem;"></i>
                                </div>
                            <?php endif; ?>
                            <span class="badge bg-dark"
                                  style="position:absolute; bottom:8px; right:8px; font-size:0.7rem;">
                                <i class="bi bi-people-fill"></i> Need <?= (int)$post['housemates_needed'] ?>
                            </span>
                        </div>

                        <!-- BODY (tight) -->
                        <div class="p-3">
                            <h6 class="mb-1" style="font-size:0.95rem;">
                                <?= e($post['property_title']) ?>
                            </h6>
                            <div class="text-secondary mb-2" style="font-size:0.75rem;">
                                <i class="bi bi-geo-alt"></i> <?= e($post['property_city']) ?>
                                · <?= e(ucfirst(str_replace('_',' ', $post['property_type']))) ?>
                            </div>

                            <!-- Pricing row -->
                            <div class="d-flex justify-content-between mb-2" style="font-size:0.8rem;">
                                <div>
                                    <span class="text-secondary">Total:</span>
                                    <strong>RM <?= number_format((float)$post['property_rent']) ?></strong>
                                </div>
                                <div>
                                    <span class="text-secondary">Per-person:</span>
                                    <strong class="text-emerald">RM <?= number_format($perPerson) ?></strong>
                                </div>
                            </div>
                            <div style="font-size:0.75rem; color:#6c757d; margin-bottom:6px;">
                                <i class="bi bi-calendar2-range me-1"></i>
                                <?= (int)($post['semesters_needed'] ?? 1) ?> semester<?= ($post['semesters_needed'] ?? 1) > 1 ? 's' : '' ?>
                            </div>

                            <!-- Message (truncated) -->
                            <?php if (!empty($post['message'])): ?>
                                <div style="background:#F4F4EE; border-radius:6px; padding:6px 10px;
                                            font-size:0.75rem; line-height:1.4;
                                            display:-webkit-box; -webkit-line-clamp:2;
                                            -webkit-box-orient:vertical; overflow:hidden;">
                                    "<?= e($post['message']) ?>"
                                </div>
                            <?php endif; ?>

                            <!-- CTA -->
                            <div class="d-flex justify-content-between align-items-center mt-3 pt-2"
                                 style="border-top: 1px solid rgba(15,44,82,0.06);">
                                <button type="button"
                                        onclick="event.preventDefault(); event.stopPropagation(); window.location='/rentbridge/student/housemate_post.php?id=<?= (int)$post['id'] ?>'; return false;"
                                        class="btn btn-sm btn-primary"
                                        style="font-size:0.75rem;">
                                    <i class="bi bi-person-plus"></i> Apply to join
                                </button>
                                <span class="small text-emerald fw-semibold">
                                    Property <i class="bi bi-arrow-right"></i>
                                </span>
                            </div>
                        </div>
                    </div>
                </a>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php
// Simple time diff helper (inline since used only here)
function human_time_diff(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff/60) . 'm ago';
    if ($diff < 86400) return floor($diff/3600) . 'h ago';
    if ($diff < 604800) return floor($diff/86400) . 'd ago';
    return date('d M', strtotime($datetime));
}

$pageContent = ob_get_clean();
require __DIR__ . '/../includes/student_layout.php';