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

// All cities for filter dropdown
$cities = $pdo->query("SELECT DISTINCT city FROM properties WHERE status IN ('available','booked') ORDER BY city")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Find Partners';
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

<!-- INTRO -->
<div class="bg-white border rounded-3 p-4 mb-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div class="flex-grow-1">
            <h5 class="mb-1" style="font-family:'Fraunces',serif;">
                <i class="bi bi-people-fill text-emerald"></i>
                Co-tenancy posts
            </h5>
            <p class="text-secondary small mb-0">
                Students looking to share a property they've found.
                Found a place that's too expensive alone? Post it from any property page.
            </p>
        </div>
        <a href="/rentbridge/listings.php" class="btn btn-outline-dark">
            <i class="bi bi-search me-1"></i> Browse properties
        </a>
    </div>
</div>

<!-- FILTERS -->
<form method="GET" class="bg-white border rounded-3 p-3 mb-4">
    <div class="row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label small fw-semibold text-secondary text-uppercase">City</label>
            <select name="city" class="form-select form-select-sm">
                <option value="">All cities</option>
                <?php foreach ($cities as $c): ?>
                    <option value="<?= e($c) ?>" <?= $filterCity===$c?'selected':'' ?>><?= e($c) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label small fw-semibold text-secondary text-uppercase">Max property rent (RM)</label>
            <input type="number" name="max_rent" value="<?= e($filterMaxRent) ?>"
                   class="form-control form-control-sm" placeholder="e.g. 1000" step="50">
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn btn-sm btn-primary flex-fill">
                <i class="bi bi-funnel me-1"></i> Filter
            </button>
            <a href="?" class="btn btn-sm btn-outline-secondary">Clear</a>
        </div>
    </div>
</form>

<!-- POSTS FEED -->
<?php if (empty($posts)): ?>
    <div class="text-center py-5 bg-white rounded-3 border">
        <i class="bi bi-people" style="font-size: 3rem; color: rgba(15,44,82,0.15);"></i>
        <h4 class="mt-3">No co-tenancy posts yet</h4>
        <p class="text-secondary small">
            <?php if ($filterCity || $filterMaxRent): ?>
                Try removing some filters.
            <?php else: ?>
                Be the first! Browse properties → click "Share with housemates" on any listing.
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
            <div class="col-md-6">
                <div class="bg-white border rounded-3 overflow-hidden h-100"
                     style="transition: transform 0.15s, box-shadow 0.15s;"
                     onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(15,44,82,0.08)'"
                     onmouseout="this.style.transform='';this.style.boxShadow=''">

                    <!-- HEADER: Poster info + match badge -->
                    <div class="p-3 d-flex justify-content-between align-items-start"
                         style="border-bottom: 1px solid rgba(15,44,82,0.06);">
                        <div class="d-flex gap-3 align-items-center flex-grow-1">
                            <div style="width:40px; height:40px; border-radius:50%;
                                        background:#E4F2EA; color:#0F2C52;
                                        display:flex; align-items:center; justify-content:center;
                                        font-weight:600;">
                                <?= strtoupper(substr($post['poster_nickname'] ?: $post['poster_name'], 0, 1)) ?>
                            </div>
                            <div>
                                <strong class="small"><?= e($post['poster_nickname'] ?: $post['poster_name']) ?></strong>
                                <div class="small text-secondary">
                                    <code><?= e($post['poster_matric']) ?></code>
                                    · posted <?= e(human_time_diff($post['post_created_at'])) ?>
                                </div>
                            </div>
                        </div>
                        <span class="badge bg-<?= $compat['color'] ?>"
                              title="<?= e($compat['description']) ?>">
                            <?= e($compat['label']) ?>
                        </span>
                    </div>

                    <!-- PROPERTY THUMBNAIL -->
                    <div style="aspect-ratio: 16/9; background: linear-gradient(135deg,#E6ECF4,#E4F2EA); position:relative;">
                        <?php if (!empty($post['property_image'])): ?>
                            <img src="/rentbridge/<?= e($post['property_image']) ?>"
                                 style="width:100%; height:100%; object-fit:cover;" alt="">
                        <?php endif; ?>
                        <span class="badge bg-dark"
                              style="position:absolute; bottom:10px; right:10px;">
                            <i class="bi bi-people-fill"></i>
                            Need <?= (int)$post['housemates_needed'] ?> more
                        </span>
                    </div>

                    <!-- BODY -->
                    <div class="p-3">
                        <h6 class="mb-1">
                            <a href="/rentbridge/properties/<?= (int)$post['property_id'] ?>"
                               class="text-decoration-none text-dark">
                                <?= e($post['property_title']) ?>
                            </a>
                        </h6>
                        <div class="small text-secondary mb-2">
                            <i class="bi bi-geo-alt"></i> <?= e($post['property_city']) ?>
                            · <?= e(ucfirst(str_replace('_',' ', $post['property_type']))) ?>
                        </div>

                        <!-- Pricing -->
                        <div class="row g-2 mb-2 small">
                            <div class="col-6">
                                <div class="text-secondary">Total monthly</div>
                                <strong>RM <?= number_format((float)$post['property_rent']) ?></strong>
                            </div>
                            <div class="col-6">
                                <div class="text-secondary">Per person (est.)</div>
                                <strong class="text-emerald">RM <?= number_format($perPerson) ?></strong>
                            </div>
                        </div>

                        <!-- Message -->
                        <?php if (!empty($post['message'])): ?>
                            <div class="small mt-2"
                                 style="background:#F4F4EE; border-radius:6px; padding:8px 12px;">
                                "<?= e($post['message']) ?>"
                            </div>
                        <?php endif; ?>

                        <!-- Bio -->
                        <?php if (!empty($post['poster_bio'])): ?>
                            <div class="small text-secondary mt-2">
                                <i class="bi bi-info-circle"></i> About <?= e($post['poster_nickname'] ?: 'them') ?>:
                                <?= e($post['poster_bio']) ?>
                            </div>
                        <?php endif; ?>

                        <!-- Actions -->
                        <div class="d-flex gap-2 mt-3">
                            <a href="/rentbridge/chat/start.php?with=<?= (int)$post['poster_id'] ?>&property_id=<?= (int)$post['property_id'] ?>"
                               class="btn btn-sm btn-primary flex-fill">
                                <i class="bi bi-chat-dots me-1"></i> Message
                            </a>
                            <a href="/rentbridge/properties/<?= (int)$post['property_id'] ?>"
                               class="btn btn-sm btn-outline-dark">
                                <i class="bi bi-house"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <p class="text-secondary small mt-3">
        Showing <?= count($posts) ?>
        <?= count($posts) === 1 ? 'post' : 'posts' ?>
        <?php if ($filterCity || $filterMaxRent): ?>(filtered)<?php endif; ?>
    </p>
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