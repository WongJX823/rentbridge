$pageTitle = 'My Profile';
$activeNav = 'profile';
ob_start();
?>

<div class="row g-4">
    <div class="col-lg-8">
        <form method="POST" class="bg-white border rounded-3 p-4">
            <?= csrf_field() ?>

            <h6 class="text-secondary text-uppercase small mb-3">Account</h6>
            <div class="mb-3">
                <label class="form-label">Full name</label>
                <input type="text" class="form-control" value="<?= e($me['full_name']) ?>" disabled>
            </div>
            <div class="mb-3">
                <label class="form-label">Staff ID</label>
                <input type="text" class="form-control" value="<?= e($me['staff_id']) ?>" disabled>
            </div>
            <div class="mb-3">
                <label class="form-label">Department</label>
                <input type="text" class="form-control" value="<?= e($me['department']) ?>" disabled>
            </div>
            <div class="mb-4">
                <label class="form-label">Nickname</label>
                <input type="text" name="preferred_name" class="form-control"
                       value="<?= e($old['preferred_name']) ?>">
            </div>

            <h6 class="text-secondary text-uppercase small mb-3">Contact</h6>
            <div class="mb-3">
                <label class="form-label">Phone <small class="text-danger">*</small></label>
                <input type="text" name="phone"
                       class="form-control <?= isset($errors['phone'])?'is-invalid':'' ?>"
                       value="<?= e($old['phone']) ?>" required>
                <?php if (isset($errors['phone'])): ?>
                    <div class="invalid-feedback"><?= e($errors['phone']) ?></div>
                <?php endif; ?>
            </div>

            <div class="form-check border rounded-3 p-3 mb-4"
                 style="background:#F4F4EE; border-color: rgba(15,44,82,0.1) !important;">
                <input class="form-check-input" type="checkbox"
                       name="allow_whatsapp" id="allow_whatsapp" value="1"
                       <?= $old['allow_whatsapp'] ? 'checked' : '' ?>>
                <label class="form-check-label fw-semibold" for="allow_whatsapp">
                    <i class="bi bi-whatsapp text-success me-1"></i>
                    Allow contact via WhatsApp
                </label>
                <div class="small text-secondary mt-2">
                    Recommended. Students and landlords with active cases can WhatsApp you directly.
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2">
                <a href="/rentbridge/agent/dashboard.php" class="btn btn-outline-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-check2 me-1"></i> Save changes
                </button>
            </div>
        </form>
    </div>

    <div class="col-lg-4">
        <div class="bg-white border rounded-3 p-4">
            <h6 class="text-secondary text-uppercase small mb-3">Account summary</h6>
            <div class="d-flex align-items-center gap-3 mb-3">
                <div style="width:56px; height:56px; background:#FFF4D6; color:#7C5E0A;
                            border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.5rem;">
                    <i class="bi bi-person-badge-fill"></i>
                </div>
                <div>
                    <div class="fw-semibold"><?= e($me['preferred_name'] ?: $me['full_name']) ?></div>
                    <small class="text-secondary">Agent · <?= e($me['department']) ?></small>
                </div>
            </div>
            <div class="small text-secondary">
                <i class="bi bi-envelope"></i> <?= e($me['email']) ?>
            </div>
        </div>
    </div>
</div>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/agent_layout.php';