<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/saved.php';
require_once __DIR__ . '/includes/save_button.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(404);
    die('Property not found.');
}

// === Fetch property + landlord ===
$stmt = db()->prepare("
    SELECT p.*,
           l.user_id        AS landlord_user_id,
           l.full_name      AS landlord_name,
           l.preferred_name AS landlord_preferred_name,
           l.phone          AS landlord_phone,
           l.allow_whatsapp AS landlord_whatsapp,
           l.verified       AS landlord_verified,
           l.avatar_path    AS landlord_avatar
      FROM properties p
      JOIN landlords l ON l.user_id = p.landlord_id
     WHERE p.id = ?
       AND p.status = 'available'
     LIMIT 1
");
$stmt->execute([$id]);
$prop = $stmt->fetch();

if (!$prop) {
    http_response_code(404);
    die('Property not found or no longer available.');
}

// === Saved status ===
$isSaved = false;
if (is_logged_in()) {
    $isSaved = is_property_saved(current_user_id(), (int)$prop['id']);
}

// === Photos ===
$stmt = db()->prepare("
    SELECT image_path FROM property_images
     WHERE property_id = ?
     ORDER BY is_primary DESC, id ASC
");
$stmt->execute([$id]);
$photos = $stmt->fetchAll();

// === Co-tenancy post arrival? ===
$fromPostId = (int)($_GET['from_post'] ?? 0);
$fromPost = null;
if ($fromPostId > 0) {
    $stmt = db()->prepare("
        SELECT ctp.*,
               s.full_name      AS poster_name,
               s.preferred_name AS poster_nickname,
               s.matric_no      AS poster_matric,
               s.housing_bio    AS poster_bio
          FROM co_tenancy_posts ctp
          JOIN students s ON s.user_id = ctp.poster_id
         WHERE ctp.id = ? AND ctp.property_id = ? AND ctp.status = 'open'
    ");
    $stmt->execute([$fromPostId, (int)$prop['id']]);
    $fromPost = $stmt->fetch();
}

// === All active posts on this property ===
$stmt = db()->prepare("
    SELECT ctp.id, ctp.message, ctp.housemates_needed, ctp.created_at,
           s.full_name      AS poster_name,
           s.preferred_name AS poster_nickname,
           s.matric_no      AS poster_matric
      FROM co_tenancy_posts ctp
      JOIN students s ON s.user_id = ctp.poster_id
     WHERE ctp.property_id = ? AND ctp.status = 'open'
     ORDER BY ctp.created_at DESC
");
$stmt->execute([(int)$prop['id']]);
$allPosts = $stmt->fetchAll();

// === WhatsApp link helper ===
$waPhone = '';
$waUrl = '';
if ((int)$prop['landlord_whatsapp'] === 1 && !empty($prop['landlord_phone'])) {
    // Normalize phone for wa.me (strip non-digits, prepend 60 for MY if needed)
    $waPhone = preg_replace('/[^0-9]/', '', $prop['landlord_phone']);
    if (str_starts_with($waPhone, '0')) {
        $waPhone = '60' . substr($waPhone, 1);
    }
    $waMsg = rawurlencode("Hi, I'm interested in your property listed on RentBridge: " . $prop['title']);
    $waUrl = "https://wa.me/{$waPhone}?text={$waMsg}";
}

$pageTitle = $prop['title'];
$activeNav = 'browse';

ob_start();
?>

<a href="/rentbridge/listings.php" class="small text-secondary text-decoration-none mb-3 d-inline-block">
    <i class="bi bi-arrow-left"></i> Back to listings
</a>

<?php if ($fromPost): ?>
<!-- BANNER: arrived from a specific co-tenancy post -->
<div class="alert d-flex gap-3 align-items-start mb-4"
     style="background:#E4F2EA; border-color:#2E8B57; color:#0F2C52;">
    <div style="width:40px; height:40px; border-radius:50%;
                background:white; color:#0F2C52;
                display:flex; align-items:center; justify-content:center;
                font-weight:600; flex-shrink:0;">
        <?= strtoupper(substr($fromPost['poster_nickname'] ?: $fromPost['poster_name'], 0, 1)) ?>
    </div>
    <div class="flex-grow-1">
        <strong><?= e($fromPost['poster_nickname'] ?: $fromPost['poster_name']) ?></strong>
        <span class="text-secondary small">
            (<code><?= e($fromPost['poster_matric']) ?></code>)
        </span>
        is looking for <strong><?= (int)$fromPost['housemates_needed'] ?> more housemate<?= $fromPost['housemates_needed']==1?'':'s' ?></strong>
        for this property.
        <?php if (!empty($fromPost['message'])): ?>
            <div class="small mt-2" style="background:white; border-radius:6px; padding:8px 12px;">
                "<?= e($fromPost['message']) ?>"
            </div>
        <?php endif; ?>
    </div>
    <a href="/rentbridge/chat/start.php?type=partner_inquiry&with=<?= (int)$fromPost['poster_id'] ?>&post_id=<?= (int)$fromPost['id'] ?>"
        class="btn btn-success btn-sm" style="flex-shrink:0;">
        <i class="bi bi-chat-dots me-1"></i> Message
    </a>
</div>
<?php elseif (!empty($allPosts) && is_logged_in() && current_role() === 'student'): ?>
<!-- BANNER: this property has open co-tenancy posts -->
<div class="alert alert-light border d-flex gap-3 align-items-start mb-4">
    <i class="bi bi-people-fill text-emerald fs-3"></i>
    <div class="flex-grow-1">
        <strong><?= count($allPosts) ?>
        student<?= count($allPosts)===1?'':'s' ?> looking for housemates here.</strong>
        <div class="small text-secondary">
            <?php foreach (array_slice($allPosts, 0, 3) as $i => $p): ?>
                <?= e($p['poster_nickname'] ?: $p['poster_name']) ?> needs <?= (int)$p['housemates_needed'] ?><?= $i < min(count($allPosts), 3) - 1 ? ', ' : '' ?>
            <?php endforeach; ?>
            <?php if (count($allPosts) > 3): ?>
                + <?= count($allPosts) - 3 ?> more
            <?php endif; ?>
        </div>
    </div>
    <a href="/rentbridge/student/partners.php?city=<?= e($prop['city']) ?>"
       class="btn btn-sm btn-outline-dark" style="flex-shrink:0;">
        View posts <i class="bi bi-arrow-right ms-1"></i>
    </a>
</div>
<?php endif; ?>

<!-- 2-COLUMN LAYOUT: main + sticky rail -->
<div class="property-shell">

    <!-- CENTER: photos + details -->
    <div class="property-main">

        <!-- IMAGE SCROLLER -->
        <?php if (!empty($photos)): ?>
            <div class="property-images mb-4">
                <div class="property-main-photo">
                    <img id="propMainImg"
                         src="/rentbridge/<?= e($photos[0]['image_path']) ?>"
                         alt="<?= e($prop['title']) ?>">
                </div>
                <?php if (count($photos) > 1): ?>
                    <div class="property-thumb-row">
                        <?php foreach ($photos as $idx => $img): ?>
                            <button type="button"
                                    class="property-thumb <?= $idx === 0 ? 'active' : '' ?>"
                                    data-src="/rentbridge/<?= e($img['image_path']) ?>"
                                    aria-label="View photo <?= $idx + 1 ?>">
                                <img src="/rentbridge/<?= e($img['image_path']) ?>" alt="">
                            </button>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="property-no-photo mb-4">
                <i class="bi bi-image text-secondary"></i>
                <p class="text-secondary mt-2 mb-0">No photos available</p>
            </div>
        <?php endif; ?>

        <!-- TITLE + meta -->
        <div class="property-header">
            <span class="badge bg-light text-secondary border mb-2">
                <?= e(ucfirst(str_replace('_', ' ', $prop['property_type']))) ?>
            </span>
            <h1 class="property-title"><?= e($prop['title']) ?></h1>
            <p class="property-location text-secondary mb-3">
                <i class="bi bi-geo-alt"></i>
                <?= e($prop['address']) ?>,
                <?= e($prop['city']) ?> <?= e($prop['postcode']) ?>,
                <?= e($prop['state']) ?>
            </p>
        </div>

        <hr>

        <!-- KEY FACTS row -->
        <div class="row g-3 mb-4">
            <div class="col-sm-4">
                <small class="text-secondary fw-semibold text-uppercase">Monthly rent</small>
                <div class="fs-4 fw-semibold text-emerald">
                    RM <?= number_format((float)$prop['monthly_rent']) ?>
                </div>
            </div>
            <div class="col-sm-4">
                <small class="text-secondary fw-semibold text-uppercase">Deposit</small>
                <div class="fs-4 fw-semibold">
                    RM <?= number_format((float)$prop['deposit']) ?>
                </div>
            </div>
            <div class="col-sm-4">
                <small class="text-secondary fw-semibold text-uppercase">Furnishing</small>
                <div class="fs-4 fw-semibold">
                    <?= e(ucfirst($prop['furnishing'])) ?>
                </div>
            </div>
        </div>

        <!-- DESCRIPTION -->
        <?php if (!empty($prop['description'])): ?>
            <h5 class="mt-4">About this place</h5>
            <p style="white-space: pre-line;"><?= e($prop['description']) ?></p>
        <?php endif; ?>

        <!-- FACILITIES -->
        <?php if (!empty($prop['facilities'])): ?>
            <h5 class="mt-4">Facilities</h5>
            <div class="d-flex flex-wrap gap-2">
                <?php foreach (explode(',', $prop['facilities']) as $f):
                    $f = trim($f);
                    if ($f === '') continue;
                ?>
                    <span class="badge bg-light text-dark border px-3 py-2 fw-normal">
                        <?= e($f) ?>
                    </span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- RIGHT RAIL (sticky on desktop, hidden on mobile) -->
    <aside class="property-rail d-none d-lg-flex flex-column">

        <!-- Landlord card -->
        <div class="rail-landlord">
            <div class="d-flex align-items-center gap-3 mb-2">
                <?php
                require_once __DIR__ . '/includes/avatar.php';
                render_avatar(
                    $prop['landlord_avatar'] ?? null,
                    $prop['landlord_preferred_name'] ?: $prop['landlord_name'],
                    48
                );
                ?>
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-semibold text-truncate">
                        <?= e($prop['landlord_preferred_name'] ?: $prop['landlord_name']) ?>
                        <?php if ((int)$prop['landlord_verified'] === 1): ?>
                            <i class="bi bi-patch-check-fill text-success ms-1"
                               title="Verified landlord"></i>
                        <?php endif; ?>
                    </div>
                    <small class="text-secondary">Landlord</small>
                </div>
            </div>
            <div class="rail-price">
                <span class="rail-rent">RM <?= number_format((float)$prop['monthly_rent']) ?></span>
                <span class="rail-rent-unit">/month</span>
            </div>
        </div>

        <hr>

        <!-- Action buttons -->
        <div class="rail-actions">
            <?php if (is_logged_in()): ?>
                <!-- PRIMARY: Chat -->
                <?php
                    // Route chat target based on property's viewing mode
                    $chatTargetUserId = null;
                    $chatTargetLabel  = '';
                    $chatTargetIcon   = 'bi-chat-dots';
                    $chatDisabled     = false;
                    $chatBlockReason  = '';

                    $viewingMode = $prop['viewing_mode'] ?? 'either';

                    if ($viewingMode === 'agent_led') {
                        // Must go to assigned agent
                        if (!empty($prop['assigned_agent_id']) && ($prop['agent_status'] ?? '') === 'accepted') {
                            $chatTargetUserId = (int)$prop['assigned_agent_id'];
                            $chatTargetLabel  = 'Chat with agent';
                            $chatTargetIcon   = 'bi-person-badge';
                        } else {
                            $chatDisabled = true;
                            $chatBlockReason = 'Agent not yet verified for this property. Try again later.';
                        }
                    } elseif ($viewingMode === 'either') {
                        // Slice 4 deferral — for now, route to landlord (will be revisited)
                        $chatTargetUserId = (int)$prop['landlord_user_id'];
                        $chatTargetLabel  = 'Chat with landlord';
                    } else {
                        // landlord_led (default)
                        $chatTargetUserId = (int)$prop['landlord_user_id'];
                        $chatTargetLabel  = 'Chat with landlord';
                    }
                    ?>

                    <?php if ($chatDisabled): ?>
                        <button type="button" class="btn btn-secondary w-100" disabled>
                            <i class="bi bi-clock-history me-1"></i> Chat unavailable
                        </button>
                        <small class="text-secondary d-block mt-1"><?= e($chatBlockReason) ?></small>
                    <?php else: ?>
                        <a href="/rentbridge/chat/start.php?type=property_inquiry&with=<?= $chatTargetUserId ?>&property_id=<?= (int)$prop['id'] ?>"
                        class="btn btn-primary w-100">
                            <i class="bi <?= $chatTargetIcon ?> me-1"></i> <?= e($chatTargetLabel) ?>
                        </a>
                    <?php endif; ?>

                <!-- SECONDARY: WhatsApp -->
                <?php if (!empty($waUrl)): ?>
                    <a href="<?= e($waUrl) ?>" target="_blank"
                       class="btn btn-outline-success rail-btn-secondary">
                        <i class="bi bi-whatsapp me-1"></i> WhatsApp
                    </a>
                <?php endif; ?>

                <!-- SECONDARY: Save -->
                <button type="button"
                        class="btn btn-outline-dark rail-btn-secondary save-property-btn"
                        data-property-id="<?= (int)$prop['id'] ?>"
                        data-saved="<?= $isSaved ? '1' : '0' ?>"
                        data-logged-in="1">
                    <i class="bi <?= $isSaved ? 'bi-heart-fill text-danger' : 'bi-heart' ?> me-1"></i>
                    <span class="save-label"><?= $isSaved ? 'Saved' : 'Save' ?></span>
                </button>

                <!-- STUDENT-ONLY: Post co-tenancy -->
                <?php if (current_role() === 'student'): ?>
                    <a href="/rentbridge/student/find_housemates.php?property_id=<?= (int)$prop['id'] ?>"
                       class="btn btn-outline-primary rail-btn-secondary">
                        <i class="bi bi-people-fill me-1"></i> Post for housemates
                    </a>
                <?php endif; ?>

            <?php else: ?>
                <!-- GUEST -->
                <button type="button" class="btn btn-success rail-btn-primary"
                        data-bs-toggle="modal" data-bs-target="#loginPromptModal">
                    <i class="bi bi-chat-dots-fill me-1"></i> Chat with landlord
                </button>
                <?php if (!empty($waUrl)): ?>
                    <button type="button" class="btn btn-outline-success rail-btn-secondary"
                            data-bs-toggle="modal" data-bs-target="#loginPromptModal">
                        <i class="bi bi-whatsapp me-1"></i> WhatsApp
                    </button>
                <?php endif; ?>
                <button type="button" class="btn btn-outline-dark rail-btn-secondary"
                        data-bs-toggle="modal" data-bs-target="#loginPromptModal">
                    <i class="bi bi-heart me-1"></i> Save
                </button>
            <?php endif; ?>
        </div>

        <p class="text-secondary small text-center mb-0 mt-3">
            <i class="bi bi-shield-check"></i>
            Every tenancy is verified by a UTeM agent
        </p>
    </aside>

</div>

<!-- MOBILE ACTION BAR (fixed bottom, hidden on desktop) -->
<div class="property-mobile-bar d-lg-none">
    <?php if (is_logged_in()): ?>
        <a href="/rentbridge/chat/start.php?type=property_inquiry&with=<?= (int)$prop['landlord_user_id'] ?>&property_id=<?= (int)$prop['id'] ?>"
           class="mobile-bar-btn primary">
            <i class="bi bi-chat-dots-fill"></i>
            <span>Chat</span>
        </a>
        <?php if (!empty($waUrl)): ?>
            <a href="<?= e($waUrl) ?>" target="_blank" class="mobile-bar-btn">
                <i class="bi bi-whatsapp"></i>
                <span>WhatsApp</span>
            </a>
        <?php endif; ?>
        <button type="button" class="mobile-bar-btn save-property-btn"
                data-property-id="<?= (int)$prop['id'] ?>"
                data-saved="<?= $isSaved ? '1' : '0' ?>"
                data-logged-in="1">
            <i class="bi <?= $isSaved ? 'bi-heart-fill text-danger' : 'bi-heart' ?>"></i>
            <span><?= $isSaved ? 'Saved' : 'Save' ?></span>
        </button>
        <?php if (current_role() === 'student'): ?>
            <a href="/rentbridge/student/find_housemates.php?property_id=<?= (int)$prop['id'] ?>"
               class="mobile-bar-btn">
                <i class="bi bi-people-fill"></i>
                <span>Post</span>
            </a>
        <?php endif; ?>
    <?php else: ?>
        <button type="button" class="mobile-bar-btn primary"
                data-bs-toggle="modal" data-bs-target="#loginPromptModal">
            <i class="bi bi-chat-dots-fill"></i>
            <span>Chat</span>
        </button>
        <button type="button" class="mobile-bar-btn"
                data-bs-toggle="modal" data-bs-target="#loginPromptModal">
            <i class="bi bi-heart"></i>
            <span>Save</span>
        </button>
    <?php endif; ?>
</div>

<?php if (!is_logged_in()): ?>
<!-- Login prompt modal -->
<div class="modal fade" id="loginPromptModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center px-4 pb-4 pt-0">
                <i class="bi bi-lock" style="font-size:3rem; color: rgba(15,44,82,0.3);"></i>
                <h5 class="mt-3 mb-2">Log in to continue</h5>
                <p class="text-secondary small mb-4">
                    Create a free account to message landlords, save properties, and book tenancies.
                </p>
                <div class="d-grid gap-2">
                    <a href="/rentbridge/auth/login.php" class="btn btn-primary">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Log in now
                    </a>
                    <a href="/rentbridge/auth/register.php" class="btn btn-outline-dark">
                        I don't have an account
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<style>
/* ===== Property page layout ===== */
.property-shell {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 32px;
    align-items: start;
}
@media (max-width: 991.98px) {
    .property-shell { grid-template-columns: 1fr; }
}

/* Left main */
.property-main { min-width: 0; }

.property-title {
    font-family: 'Fraunces', serif;
    font-size: 1.75rem;
    margin: 0 0 4px;
    color: #0F2C52;
}
.property-location { font-size: 0.95rem; }

/* IMAGE SCROLLER */
.property-images {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    border: 1px solid rgba(15,44,82,0.08);
}
.property-main-photo {
    aspect-ratio: 16/10;
    background: linear-gradient(135deg,#E6ECF4,#E4F2EA);
}
.property-main-photo img {
    width: 100%; height: 100%; object-fit: cover; display: block;
    transition: opacity 0.2s;
}
.property-thumb-row {
    display: flex; gap: 6px; padding: 8px;
    overflow-x: auto;
    scrollbar-width: thin;
    background: white;
    border-top: 1px solid rgba(15,44,82,0.04);
}
.property-thumb {
    flex-shrink: 0;
    width: 80px; height: 60px;
    border: 2px solid transparent;
    border-radius: 6px;
    overflow: hidden;
    padding: 0;
    background: none;
    cursor: pointer;
    transition: border-color 0.15s, transform 0.15s;
}
.property-thumb img {
    width: 100%; height: 100%; object-fit: cover; display: block;
}
.property-thumb:hover { transform: translateY(-1px); }
.property-thumb.active { border-color: #2E8B57; }

.property-no-photo {
    aspect-ratio: 16/9;
    background: linear-gradient(135deg,#E6ECF4,#E4F2EA);
    border-radius: 12px;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    color: rgba(15,44,82,0.3);
    font-size: 3rem;
}

/* RIGHT RAIL */
.property-rail {
    position: sticky;
    top: calc(var(--user-topbar-h, 56px) + 16px);
    background: white;
    border: 1px solid rgba(15,44,82,0.08);
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(15,44,82,0.04);
    max-height: calc(100vh - var(--user-topbar-h, 56px) - 32px);
    overflow-y: auto;
}

.rail-price { margin-top: 6px; }
.rail-rent {
    font-family: 'Fraunces', serif;
    font-size: 1.6rem;
    font-weight: 700;
    color: #2E8B57;
}
.rail-rent-unit {
    color: #6c757d;
    font-size: 0.9rem;
}

.rail-actions {
    display: flex; flex-direction: column; gap: 8px;
}
.rail-btn-primary, .rail-btn-secondary {
    width: 100%;
    padding: 10px 14px;
    font-weight: 600;
    border-radius: 8px;
}
.rail-btn-primary { font-size: 1rem; }
.rail-btn-secondary { font-size: 0.9rem; font-weight: 500; }

.min-w-0 { min-width: 0; }

/* MOBILE ACTION BAR */
.property-mobile-bar {
    position: fixed;
    bottom: 0; left: 0; right: 0;
    background: white;
    border-top: 1px solid rgba(15,44,82,0.1);
    padding: 10px 12px;
    display: flex;
    justify-content: space-around;
    gap: 8px;
    z-index: 1020;
    box-shadow: 0 -2px 12px rgba(0,0,0,0.06);
}
.mobile-bar-btn {
    flex: 1;
    background: transparent;
    border: none;
    color: #0F2C52;
    text-decoration: none;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
    padding: 6px 0;
    font-size: 0.7rem;
    font-weight: 500;
    cursor: pointer;
    border-radius: 6px;
    transition: background 0.15s;
}
.mobile-bar-btn i { font-size: 1.25rem; }
.mobile-bar-btn.primary i { color: #2E8B57; }
.mobile-bar-btn:hover { background: rgba(46,139,87,0.06); color: #2E8B57; }

/* leave room for fixed mobile bar */
@media (max-width: 991.98px) {
    body { padding-bottom: 72px; }
}
</style>

<script>
// Image thumbnail switching
(function() {
    const mainImg = document.getElementById('propMainImg');
    if (!mainImg) return;
    document.querySelectorAll('.property-thumb').forEach(thumb => {
        thumb.addEventListener('click', function() {
            const src = this.dataset.src;
            mainImg.style.opacity = '0.4';
            const newImg = new Image();
            newImg.onload = () => {
                mainImg.src = src;
                mainImg.style.opacity = '1';
            };
            newImg.src = src;
            document.querySelectorAll('.property-thumb').forEach(t => t.classList.remove('active'));
            this.classList.add('active');
        });
    });
})();

// Sync save buttons across rail + mobile bar (clicking one updates both)
(function() {
    document.body.addEventListener('click', function(e) {
        const btn = e.target.closest('.save-property-btn');
        if (!btn) return;
        // Defer to render_save_button_script() — it already handles the AJAX.
        // Just sync the other instance's visual state on success.
        setTimeout(() => {
            const newState = btn.dataset.saved;
            const propId = btn.dataset.propertyId;
            document.querySelectorAll('.save-property-btn[data-property-id="'+propId+'"]').forEach(other => {
                if (other === btn) return;
                other.dataset.saved = newState;
                const icon = other.querySelector('i');
                const label = other.querySelector('.save-label, span');
                if (newState === '1') {
                    icon?.classList.add('bi-heart-fill', 'text-danger');
                    icon?.classList.remove('bi-heart');
                    if (label) label.textContent = 'Saved';
                } else {
                    icon?.classList.remove('bi-heart-fill', 'text-danger');
                    icon?.classList.add('bi-heart');
                    if (label) label.textContent = 'Save';
                }
            });
        }, 100);
    });
})();
</script>

<?php render_save_button_script(); ?>

<?php
$pageContent = ob_get_clean();

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