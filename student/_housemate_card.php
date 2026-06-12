<?php
/**
 * Reusable card for displaying a discoverable / searchable student.
 * Expects $u to be set in parent scope with these keys:
 *   user_id, preferred_name, full_name, matric_no, email,
 *   housing_pref_city, housing_pref_max_rent, housing_pref_move_in, housing_bio
 * Some keys are optional (search results don't include preferences).
 */
?>
<div class="col-md-6 col-lg-4">
    <div class="bg-white border rounded-3 p-3 h-100 d-flex flex-column">
        <div class="d-flex justify-content-between align-items-start mb-2">
            <div class="flex-grow-1">
                <h6 class="mb-1"><?= e($u['preferred_name']) ?></h6>
                <small class="text-secondary d-block">
                    <?= e($u['full_name']) ?>
                </small>
                <small class="text-secondary d-block">
                    <i class="bi bi-mortarboard"></i> <?= e($u['matric_no']) ?>
                </small>
            </div>
        </div>

        <?php if (!empty($u['housing_pref_city']) || !empty($u['housing_pref_max_rent'])): ?>
            <div class="small text-secondary mb-2">
                <?php if (!empty($u['housing_pref_city'])): ?>
                    <div><i class="bi bi-geo-alt"></i> <?= e($u['housing_pref_city']) ?></div>
                <?php endif; ?>
                <?php if (!empty($u['housing_pref_max_rent'])): ?>
                    <div><i class="bi bi-cash"></i> up to RM <?= number_format((float)$u['housing_pref_max_rent']) ?>/mo</div>
                <?php endif; ?>
                <?php if (!empty($u['housing_pref_move_in'])): ?>
                    <div><i class="bi bi-calendar3"></i> Move in <?= e(date('d M Y', strtotime($u['housing_pref_move_in']))) ?></div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($u['housing_bio'])): ?>
            <p class="small mb-3"><i class="bi bi-quote"></i> <?= e($u['housing_bio']) ?></p>
        <?php endif; ?>

        <!-- Add button at the bottom -->
        <div class="mt-auto">
            <button class="btn btn-sm btn-success w-100"
                    data-bs-toggle="modal"
                    data-bs-target="#addModal<?= (int)$u['user_id'] ?>">
                <i class="bi bi-person-plus me-1"></i> Connect
            </button>
        </div>
    </div>
</div>

<!-- Connect modal -->
<div class="modal fade" id="addModal<?= (int)$u['user_id'] ?>" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="housemates.php?tab=<?= e($tab) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="send">
                <input type="hidden" name="receiver_id" value="<?= (int)$u['user_id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Connect with <?= e($u['preferred_name']) ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label small fw-semibold">
                        Add a message <small class="text-secondary fw-normal">— optional</small>
                    </label>
                    <textarea name="message" rows="3" maxlength="255"
                              class="form-control"
                              placeholder="Hi! I saw you're also looking for housing in <?= e($u['housing_pref_city'] ?? 'the area') ?>. Want to chat?"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-ghost" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="bi bi-send me-1"></i> Send request
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>