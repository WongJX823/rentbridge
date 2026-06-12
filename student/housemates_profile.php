<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/friends.php';
require_role('student');

$userId = current_user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    update_housing_profile($userId, $_POST);
    set_flash('success', 'Housing profile updated!');
    header('Location: housemates.php');
    exit;
}

$profile = get_my_housing_profile($userId) ?: [];

$states = [
    'Ayer Keroh', 'Durian Tunggal', 'Bukit Beruang', 'Cheng', 'Krubong',
    'Bukit Katil', 'Hang Tuah Jaya', 'Bandar Hilir', 'Klebang', 'Other'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My housing profile · RentBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body style="background: var(--rb-cream);">

<?php include '../includes/header.php'; ?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-7">

            <p class="small mb-3">
                <a href="housemates.php" class="text-secondary text-decoration-none">
                    <i class="bi bi-arrow-left"></i> Back to housemates
                </a>
            </p>

            <h1 class="mb-1">My housing profile</h1>
            <p class="text-secondary mb-4">
                Set your preferences so other students can find you when searching for housemates.
            </p>

            <div class="bg-white border rounded-3 p-4">
                <form method="POST">
                    <?= csrf_field() ?>

                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox"
                               name="looking_for_housing" id="lfh" value="1"
                               <?= !empty($profile['looking_for_housing']) ? 'checked' : '' ?>>
                        <label class="form-check-label fw-semibold" for="lfh">
                            I'm looking for housing
                        </label>
                        <div class="small text-secondary">
                            When ON, you appear in the "Find housemates" discovery feed.
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Preferred area / city</label>
                        <input type="text" name="housing_pref_city" list="city-suggestions"
                               class="form-control"
                               value="<?= e($profile['housing_pref_city'] ?? '') ?>"
                               placeholder="e.g. Ayer Keroh, Durian Tunggal">
                        <datalist id="city-suggestions">
                            <?php foreach ($states as $st): ?>
                                <option value="<?= e($st) ?>">
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <div class="row g-3 mb-3">
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Max rent per month (RM)</label>
                            <input type="number" name="housing_pref_max_rent" min="100" step="50"
                                   class="form-control"
                                   value="<?= e($profile['housing_pref_max_rent'] ?? '') ?>"
                                   placeholder="500">
                        </div>
                        <div class="col-sm-6">
                            <label class="form-label fw-semibold">Earliest move-in date</label>
                            <input type="date" name="housing_pref_move_in"
                                   class="form-control"
                                   value="<?= e($profile['housing_pref_move_in'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label fw-semibold">About me <small class="text-secondary fw-normal">— what kind of housemate are you?</small></label>
                        <textarea name="housing_bio" rows="3" maxlength="255"
                                  class="form-control"
                                  placeholder="e.g. Non-smoker, quiet, prefer female housemates, doesn't cook much"><?= e($profile['housing_bio'] ?? '') ?></textarea>
                        <small class="text-secondary">Max 255 characters. Helps potential housemates know if you'd be compatible.</small>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-check2 me-1"></i> Save profile
                    </button>
                </form>
            </div>

            <p class="text-secondary small mt-3 text-center">
                <i class="bi bi-shield-lock"></i>
                Your preferences are only visible to other students when you turn ON "looking for housing."
            </p>
        </div>
    </div>
</div>

</body>
</html>