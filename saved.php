<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/saved.php';
require_once __DIR__ . '/includes/save_button.php';

require_login();

$userId = current_user_id();
$saved = list_saved_properties($userId);

$pageTitle = 'Saved Properties';
$activeNav = 'saved';

ob_start();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="mb-1" style="font-family:'Fraunces',serif;">Saved Properties</h1>
        <p class="text-secondary mb-0">
            <?= count($saved) ?> saved propert<?= count($saved) === 1 ? 'y' : 'ies' ?>
        </p>
    </div>
    <a href="/rentbridge/listings.php" class="btn btn-outline-primary">
        <i class="bi bi-search me-1"></i> Browse more
    </a>
</div>

<?php if (empty($saved)): ?>
    <div class="bg-white border rounded-3 p-5 text-center">
        <i class="bi bi-heart" style="font-size: 3rem; color: #ccc;"></i>
        <h5 class="mt-3 mb-2">No saved properties yet</h5>
        <p class="text-secondary mb-3">
            Tap the heart icon on any property to save it for later comparison.
        </p>
        <a href="/rentbridge/listings.php" class="btn btn-primary">
            Start browsing
        </a>
    </div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($saved as $prop): ?>
            <div class="col-lg-4 col-md-6">
                <div class="bg-white border rounded-3 overflow-hidden h-100"
                     style="transition: transform 0.15s, box-shadow 0.15s;"
                     onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(0,0,0,0.08)';"
                     onmouseout="this.style.transform='';this.style.boxShadow='';">

                    <div style="position: relative;">
                        <a href="/rentbridge/property.php?id=<?= (int)$prop['id'] ?>">
                        <?php if (!empty($prop['image_path'])): ?>
                                <img src="/rentbridge/<?= e($prop['image_path']) ?>"
                                     style="width:100%; height:180px; object-fit:cover;" alt="">
                            </a>
                        <?php else: ?>
                            <div style="height:180px; background: linear-gradient(135deg,#E6ECF4,#E4F2EA);
                                        display:flex; align-items:center; justify-content:center;">
                                <i class="bi bi-house" style="font-size:3rem; opacity:0.2;"></i>
                            </div>
                        <?php endif; ?>

                        <?php render_save_button((int)$prop['id'], true, 'md', 'overlay'); ?>

                        <?php if ($prop['status'] !== 'available'): ?>
                            <span class="badge bg-secondary position-absolute"
                                  style="bottom: 8px; left: 8px;">
                                <?= e(ucfirst($prop['status'])) ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="p-3">
                        <a href="/rentbridge/property.php?id=<?= (int)$prop['id'] ?>"
                           class="text-decoration-none text-dark">
                            <h6 class="mb-1"><?= e($prop['title']) ?></h6>
                            <p class="small text-secondary mb-2">
                                <i class="bi bi-geo-alt"></i>
                                <?= e($prop['city']) ?>, <?= e($prop['state']) ?>
                            </p>
                            <div class="d-flex justify-content-between align-items-center">
                                <strong style="color:#C62828;">
                                    RM <?= number_format((float)$prop['monthly_rent']) ?>
                                    <small class="fw-normal text-secondary">/mo</small>
                                </strong>
                                <span class="badge bg-light text-dark fw-normal">
                                    <?= e(ucfirst($prop['property_type'])) ?>
                                </span>
                            </div>
                        </a>
                        <small class="text-secondary d-block mt-2">
                            Saved <?= e(date('d M Y', strtotime($prop['saved_at']))) ?>
                        </small>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php render_save_button_script(); ?>

<?php
$pageContent = ob_get_clean();
$role = current_role();
$layoutFile = match($role) {
    'student'  => 'student_layout.php',
    'landlord' => 'landlord_layout.php',
    'agent'    => 'agent_layout.php',
    default    => 'public_layout.php',
};

require __DIR__ . '/includes/' . $layoutFile;
