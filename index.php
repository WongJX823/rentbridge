<?php
require_once __DIR__ . '/includes/auth.php';

// Logged-in users → their dashboard
if (is_logged_in()) {
    $dashboardPath = match (current_role()) {
        'student'  => '/rentbridge/student/dashboard.php',
        'landlord' => '/rentbridge/landlord/dashboard.php',
        'agent'    => '/rentbridge/agent/dashboard.php',
        'admin'    => '/rentbridge/admin/dashboard.php',
        default    => '/rentbridge/listings.php',
    };
    header('Location: ' . $dashboardPath);
    exit;
}

$pdo = db();

// Stats
$totalAvailable = (int)$pdo->query("SELECT COUNT(*) FROM properties WHERE status = 'available'")->fetchColumn();
$totalCities    = (int)$pdo->query("SELECT COUNT(DISTINCT city) FROM properties WHERE status = 'available'")->fetchColumn();
$totalLandlords = (int)$pdo->query("SELECT COUNT(*) FROM landlords")->fetchColumn();
$totalStudents  = (int)$pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();

// Featured listings (6 most recent available)
$stmt = $pdo->query("
    SELECT p.id, p.title, p.city, p.monthly_rent, p.property_type, p.furnishing,
           (SELECT image_path FROM property_images
             WHERE property_id = p.id
             ORDER BY is_primary DESC, id LIMIT 1) AS image_path
      FROM properties p
     WHERE p.status = 'available'
     ORDER BY p.agent_verified_at IS NULL, p.created_at DESC
     LIMIT 6
");
$featured = $stmt->fetchAll();

$pageTitle = 'Welcome';
$activeNav = 'home';

ob_start();
?>

<style>
/* ---- Homepage-specific ---- */
.rb-hero {
    background: linear-gradient(135deg, var(--rb-navy) 0%, #1a4a7a 60%, #2E8B57 100%);
    border-radius: 16px;
    padding: 64px 48px;
    color: #fff;
    position: relative;
    overflow: hidden;
    margin-bottom: 48px;
}
.rb-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
    pointer-events: none;
}
.rb-hero-eyebrow {
    font-size: 0.75rem;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: rgba(255,255,255,0.65);
    font-weight: 600;
    margin-bottom: 12px;
}
.rb-hero h1 {
    font-family: 'Fraunces', serif;
    font-size: clamp(2rem, 5vw, 3.2rem);
    font-weight: 700;
    color: #fff;
    line-height: 1.15;
    margin-bottom: 16px;
    letter-spacing: -0.02em;
}
.rb-hero p {
    font-size: 1.05rem;
    color: rgba(255,255,255,0.82);
    max-width: 520px;
    margin-bottom: 32px;
    line-height: 1.65;
}
.rb-hero-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(255,255,255,0.12);
    border: 1px solid rgba(255,255,255,0.2);
    border-radius: 999px;
    padding: 4px 14px;
    font-size: 0.78rem;
    color: rgba(255,255,255,0.9);
    margin-bottom: 20px;
}
.rb-hero-deco {
    position: absolute;
    right: -20px;
    bottom: -30px;
    width: 340px;
    height: 340px;
    border-radius: 50%;
    background: rgba(255,255,255,0.04);
    pointer-events: none;
}
.rb-hero-deco2 {
    position: absolute;
    right: 80px;
    top: -60px;
    width: 200px;
    height: 200px;
    border-radius: 50%;
    background: rgba(46,139,87,0.18);
    pointer-events: none;
}

/* Stats strip */
.rb-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
    margin-bottom: 48px;
}
@media (max-width: 768px) { .rb-stats { grid-template-columns: repeat(2, 1fr); } }
.rb-stat-card {
    background: #fff;
    border: 1px solid var(--rb-line);
    border-radius: 12px;
    padding: 20px;
    text-align: center;
}
.rb-stat-num {
    font-family: 'Fraunces', serif;
    font-size: 2rem;
    font-weight: 700;
    color: var(--rb-navy);
    line-height: 1;
    margin-bottom: 4px;
}
.rb-stat-label {
    font-size: 0.8rem;
    color: var(--rb-text-soft);
    font-weight: 500;
}

/* How it works */
.rb-steps { margin-bottom: 48px; }
.rb-step {
    display: flex;
    gap: 20px;
    align-items: flex-start;
    margin-bottom: 20px;
}
.rb-step-num {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: var(--rb-navy);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.9rem;
    flex-shrink: 0;
}
.rb-step-num.emerald { background: var(--rb-emerald); }

/* Listing cards */
.rb-listing-card {
    background: #fff;
    border: 1px solid var(--rb-line);
    border-radius: 12px;
    overflow: hidden;
    text-decoration: none;
    color: inherit;
    display: block;
    transition: transform 0.15s, box-shadow 0.15s;
    height: 100%;
}
.rb-listing-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 24px rgba(15,44,82,0.1);
    color: inherit;
}
.rb-listing-img {
    aspect-ratio: 4/3;
    background: linear-gradient(135deg, #E6ECF4, #E4F2EA);
    overflow: hidden;
}
.rb-listing-img img { width: 100%; height: 100%; object-fit: cover; }
.rb-listing-body { padding: 16px; }
.rb-listing-price {
    font-family: 'Fraunces', serif;
    font-size: 1.2rem;
    font-weight: 700;
    color: var(--rb-emerald);
}

/* CTA strips */
.rb-cta-strip {
    background: var(--rb-cream);
    border: 1px solid var(--rb-line);
    border-radius: 16px;
    padding: 48px;
    text-align: center;
    margin-bottom: 16px;
}
.rb-cta-strip h2 {
    font-family: 'Fraunces', serif;
    margin-bottom: 12px;
}
.rb-landlord-strip {
    background: linear-gradient(135deg, #0F2C52, #1a4a7a);
    border-radius: 16px;
    padding: 48px;
    color: #fff;
    display: flex;
    gap: 40px;
    align-items: center;
    margin-bottom: 48px;
}
@media (max-width: 768px) { .rb-landlord-strip { flex-direction: column; text-align: center; } }
.rb-landlord-strip h2 { color: #fff; font-family: 'Fraunces', serif; margin-bottom: 10px; }
.rb-landlord-strip p { color: rgba(255,255,255,0.8); margin-bottom: 0; }
</style>

<!-- ============================================================
     HERO
     ============================================================ -->
<div class="rb-hero">
    <div class="rb-hero-deco"></div>
    <div class="rb-hero-deco2"></div>
    <div style="position:relative;">
        <div class="rb-hero-badge">
            <i class="bi bi-mortarboard-fill"></i>
            Built for UTeM students
        </div>
        <h1>Find your perfect<br>home near campus.</h1>
        <p>
            RentBridge connects UTeM students with verified landlords —
            every listing is checked by an assigned agent before it goes live.
        </p>
        <div class="d-flex gap-3 flex-wrap">
            <a href="/rentbridge/listings.php" class="btn btn-success btn-lg">
                <i class="bi bi-search me-2"></i> Browse properties
            </a>
            <a href="/rentbridge/auth/register_student.php" class="btn btn-outline-light btn-lg">
                <i class="bi bi-person-plus me-2"></i> Create account
            </a>
        </div>
    </div>
</div>

<!-- ============================================================
     STATS
     ============================================================ -->
<div class="rb-stats">
    <div class="rb-stat-card">
        <div class="rb-stat-num"><?= $totalAvailable ?>+</div>
        <div class="rb-stat-label">Available listings</div>
    </div>
    <div class="rb-stat-card">
        <div class="rb-stat-num"><?= $totalCities ?></div>
        <div class="rb-stat-label">Cities covered</div>
    </div>
    <div class="rb-stat-card">
        <div class="rb-stat-num"><?= $totalLandlords ?>+</div>
        <div class="rb-stat-label">Verified landlords</div>
    </div>
    <div class="rb-stat-card">
        <div class="rb-stat-num"><?= $totalStudents ?>+</div>
        <div class="rb-stat-label">Students housed</div>
    </div>
</div>

<!-- ============================================================
     FEATURED LISTINGS
     ============================================================ -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0" style="font-size:1.4rem;">Recent listings</h2>
    <a href="/rentbridge/listings.php" class="btn btn-sm btn-outline-primary">
        View all <i class="bi bi-arrow-right ms-1"></i>
    </a>
</div>

<?php if (!empty($featured)): ?>
<div class="row g-3 mb-5">
    <?php foreach ($featured as $p): ?>
    <div class="col-md-4">
        <a href="/rentbridge/property.php?id=<?= (int)$p['id'] ?>" class="rb-listing-card">
            <div class="rb-listing-img">
                <?php if (!empty($p['image_path']) && !str_contains($p['image_path'], 'placeholder')): ?>
                    <img src="/rentbridge/<?= e($p['image_path']) ?>" alt="<?= e($p['title']) ?>">
                <?php else: ?>
                    <div style="display:flex; align-items:center; justify-content:center; height:100%; color:rgba(15,44,82,0.15);">
                        <i class="bi bi-camera" style="font-size:2.5rem;"></i>
                    </div>
                <?php endif; ?>
            </div>
            <div class="rb-listing-body">
                <h6 class="mb-1" style="font-family:'Fraunces',serif; font-size:0.95rem;">
                    <?= e($p['title']) ?>
                </h6>
                <div class="small text-secondary mb-2">
                    <i class="bi bi-geo-alt"></i> <?= e($p['city']) ?>
                    <span class="ms-2">
                        <i class="bi bi-house"></i>
                        <?= e(ucfirst(str_replace('_', ' ', $p['property_type']))) ?>
                    </span>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="rb-listing-price">RM <?= number_format((float)$p['monthly_rent']) ?></span>
                        <span class="text-secondary small">/mo</span>
                    </div>
                    <?php if (!empty($p['furnishing']) && $p['furnishing'] !== 'none'): ?>
                    <span class="badge bg-light text-dark small">
                        <?= e(ucfirst($p['furnishing'])) ?>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ============================================================
     HOW IT WORKS
     ============================================================ -->
<div class="row g-4 mb-5">
    <div class="col-lg-5">
        <h2 class="mb-1">How it works</h2>
        <p class="text-secondary mb-4">
            From browsing to move-in, RentBridge handles every step with transparency.
        </p>
        <div class="rb-steps">
            <div class="rb-step">
                <div class="rb-step-num">1</div>
                <div>
                    <strong>Browse & shortlist</strong>
                    <div class="small text-secondary mt-1">
                        Search by city, budget, and room type. Save listings you like.
                    </div>
                </div>
            </div>
            <div class="rb-step">
                <div class="rb-step-num">2</div>
                <div>
                    <strong>Chat with landlords</strong>
                    <div class="small text-secondary mt-1">
                        Message landlords directly. Ask about utilities, deposit, and move-in date.
                    </div>
                </div>
            </div>
            <div class="rb-step">
                <div class="rb-step-num emerald">3</div>
                <div>
                    <strong>Sign & move in</strong>
                    <div class="small text-secondary mt-1">
                        An assigned agent oversees the contract — e-sign from any device.
                    </div>
                </div>
            </div>
        </div>
        <a href="/rentbridge/auth/register_student.php" class="btn btn-primary">
            <i class="bi bi-person-plus me-1"></i> Get started free
        </a>
    </div>

    <div class="col-lg-7">
        <!-- Find housemates CTA -->
        <div class="rb-cta-strip h-100 d-flex flex-column justify-content-center">
            <div style="width:52px; height:52px; border-radius:14px; background:#E4F2EA;
                        color:var(--rb-emerald); display:flex; align-items:center;
                        justify-content:center; font-size:1.5rem; margin:0 auto 16px;">
                <i class="bi bi-people-fill"></i>
            </div>
            <h2 style="font-size:1.3rem;">Looking for housemates?</h2>
            <p class="text-secondary mb-4" style="max-width:380px; margin:0 auto 24px;">
                Found a place but need people to split the rent?
                Post a co-tenancy ad and connect with other UTeM students.
            </p>
            <div class="d-flex gap-2 justify-content-center flex-wrap">
                <a href="/rentbridge/auth/register_student.php" class="btn btn-success">
                    <i class="bi bi-megaphone me-1"></i> Find housemates
                </a>
                <a href="/rentbridge/listings.php" class="btn btn-outline-secondary">
                    Browse listings first
                </a>
            </div>
        </div>
    </div>
</div>

<!-- ============================================================
     LANDLORD CTA
     ============================================================ -->
<div class="rb-landlord-strip">
    <div style="flex-shrink:0; width:60px; height:60px; border-radius:16px; background:rgba(255,255,255,0.12);
                display:flex; align-items:center; justify-content:center; font-size:1.8rem; color:#fff;">
        <i class="bi bi-building-check"></i>
    </div>
    <div class="flex-grow-1">
        <h2 style="font-size:1.2rem; margin-bottom:6px;">Are you a landlord?</h2>
        <p style="font-size:0.9rem; color:rgba(255,255,255,0.8); margin:0;">
            List your property on RentBridge. An agent will inspect and verify your listing —
            giving students confidence to rent from you.
        </p>
    </div>
    <div class="d-flex gap-2 flex-shrink-0 flex-wrap">
        <a href="/rentbridge/auth/register_landlord.php" class="btn btn-outline-light">
            List a property
        </a>
        <a href="/rentbridge/auth/login.php" class="btn"
           style="background:rgba(255,255,255,0.15); color:#fff; border:1px solid rgba(255,255,255,0.25);">
            Sign in
        </a>
    </div>
</div>

<!-- ============================================================
     TRUST / FEATURES
     ============================================================ -->
<div class="row g-3 mb-4">
    <?php
    $features = [
        ['bi-shield-check',    'Agent-verified listings',  'Every property is inspected by a licensed agent before going live.'],
        ['bi-chat-dots',       'Built-in messaging',       'Chat directly with landlords or agents from any device.'],
        ['bi-file-earmark-text','Digital contracts',       'Sign tenancy agreements online. No printing, no fax.'],
        ['bi-people',          'Co-tenancy support',       'Find and coordinate with housemates for shared rentals.'],
    ];
    foreach ($features as [$icon, $title, $desc]):
    ?>
    <div class="col-md-6">
        <div class="d-flex gap-3 bg-white border rounded-3 p-3 h-100">
            <div style="width:40px; height:40px; border-radius:10px; background:#E6ECF4;
                        color:var(--rb-navy); display:flex; align-items:center;
                        justify-content:center; flex-shrink:0;">
                <i class="bi <?= $icon ?>"></i>
            </div>
            <div>
                <strong class="d-block mb-1" style="font-size:0.9rem;"><?= $title ?></strong>
                <span class="small text-secondary"><?= $desc ?></span>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/includes/public_layout.php';
