<?php
require_once __DIR__ . '/auth.php';

const REPORT_FLAG_THRESHOLD = 3;

/**
 * Submit a report. Returns false on self-report or exact duplicate.
 */
function submit_report(
    int     $reporterId,
    int     $reportedUserId,
    string  $contextType,
    ?int    $contextId,
    string  $reason,
    string  $details = ''
): bool {
    if ($reporterId === $reportedUserId) return false;

    $pdo = db();

    // Prevent exact duplicate on same context
    if ($contextId !== null) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM reports
             WHERE reporter_id = ? AND reported_user_id = ?
               AND context_type = ? AND context_id = ?
        ");
        $stmt->execute([$reporterId, $reportedUserId, $contextType, $contextId]);
        if ((int)$stmt->fetchColumn() > 0) return false;
    }

    $pdo->prepare("
        INSERT INTO reports (reporter_id, reported_user_id, context_type, context_id, reason, details)
        VALUES (?, ?, ?, ?, ?, ?)
    ")->execute([$reporterId, $reportedUserId, $contextType, $contextId, $reason, $details]);

    _maybe_notify_admins_flagged($reportedUserId);
    return true;
}

/**
 * Notify admins if the reported user crosses the threshold — at most once per 24 h.
 */
function _maybe_notify_admins_flagged(int $userId): void {
    $pdo = db();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM reports
         WHERE reported_user_id = ?
           AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
           AND status != 'dismissed'
    ");
    $stmt->execute([$userId]);
    $count = (int)$stmt->fetchColumn();

    if ($count < REPORT_FLAG_THRESHOLD) return;

    // Deduplicate: skip if we already sent this admin alert in the past 24 h
    $dedup = $pdo->prepare("
        SELECT COUNT(*) FROM notifications
         WHERE type = 'user_flagged'
           AND link_url LIKE ?
           AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $dedup->execute(["%filter_user={$userId}%"]);
    if ((int)$dedup->fetchColumn() > 0) return;

    $nameRow = $pdo->prepare("SELECT full_name FROM users WHERE id = ? LIMIT 1");
    $nameRow->execute([$userId]);
    $userName = $nameRow->fetchColumn() ?: 'User #' . $userId;

    $admins = $pdo->query("SELECT id FROM users WHERE primary_role = 'admin'")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($admins as $adminId) {
        notify(
            (int)$adminId,
            'user_flagged',
            "High reports: {$userName}",
            "{$userName} has received {$count} reports in the last 30 days. Review and consider action.",
            "/rentbridge/admin/reports.php?filter_user={$userId}"
        );
    }
}

/**
 * Emit the shared report modal + AJAX script.
 * Call once per page, before </body>.
 *
 * $subjects = [
 *   ['id' => 5, 'name' => 'John Doe', 'role' => 'landlord'],
 *   ['id' => 8, 'name' => 'Jane Agent', 'role' => 'agent'],
 * ]
 */
function render_report_modal(array $subjects, string $contextType, int $contextId = 0): void {
    $ctxType = htmlspecialchars($contextType, ENT_QUOTES);
    $ctxId   = (int)$contextId;
    ?>
    <!-- REPORT MODAL -->
    <div class="modal fade" id="reportModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form id="reportForm">
                    <div class="modal-header border-0 pb-0">
                        <h5 class="modal-title">
                            <i class="bi bi-flag-fill text-danger me-2"></i>Report an issue
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body pt-3">
                        <p class="text-secondary small mb-3">
                            Your report is confidential. Admin will review and take appropriate action.
                        </p>

                        <?php if (count($subjects) > 1): ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Who are you reporting?</label>
                            <select class="form-select" id="reportTargetSelect" name="reported_user_id" required>
                                <option value="">— select person —</option>
                                <?php foreach ($subjects as $s): ?>
                                    <option value="<?= (int)$s['id'] ?>">
                                        <?= htmlspecialchars($s['name'], ENT_QUOTES) ?>
                                        (<?= htmlspecialchars(ucfirst($s['role']), ENT_QUOTES) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php else:
                            $s = $subjects[0] ?? null;
                        ?>
                        <input type="hidden" name="reported_user_id"
                               value="<?= $s ? (int)$s['id'] : 0 ?>">
                        <?php if ($s): ?>
                            <p class="small mb-3">
                                Reporting: <strong><?= htmlspecialchars($s['name'], ENT_QUOTES) ?></strong>
                                (<?= htmlspecialchars(ucfirst($s['role']), ENT_QUOTES) ?>)
                            </p>
                        <?php endif; ?>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Reason <span class="text-danger">*</span></label>
                            <select class="form-select" name="reason" id="reportReasonSelect" required>
                                <option value="">— select reason —</option>
                                <option value="harassment">Harassment or threatening behaviour</option>
                                <option value="scam">Scam or fraudulent listing</option>
                                <option value="fake_information">Fake or misleading information</option>
                                <option value="misconduct">Professional misconduct</option>
                                <option value="fraud">Financial fraud</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Details <small class="fw-normal text-secondary">(optional)</small></label>
                            <textarea class="form-control" name="details" id="reportDetails"
                                      rows="3" maxlength="2000"
                                      placeholder="Describe what happened…"></textarea>
                        </div>

                        <input type="hidden" name="context_type" value="<?= $ctxType ?>">
                        <input type="hidden" name="context_id" value="<?= $ctxId ?>">

                        <div id="reportFeedback" class="d-none alert mb-0"></div>
                    </div>
                    <div class="modal-footer border-0 pt-0">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger" id="reportSubmitBtn">
                            <i class="bi bi-flag me-1"></i> Submit report
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
    (function () {
        const form = document.getElementById('reportForm');
        const feedback = document.getElementById('reportFeedback');
        const submitBtn = document.getElementById('reportSubmitBtn');

        if (!form) return;

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            feedback.className = 'd-none alert mb-0';
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Submitting…';

            const fd = new FormData(form);

            fetch('/rentbridge/api/submit_report.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.ok) {
                        feedback.className = 'alert alert-success mb-0';
                        feedback.textContent = 'Report submitted. Thank you — admin will review shortly.';
                        submitBtn.style.display = 'none';
                        form.querySelectorAll('select,textarea').forEach(el => el.disabled = true);
                    } else {
                        feedback.className = 'alert alert-danger mb-0';
                        feedback.textContent = data.error || 'Could not submit report. Please try again.';
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="bi bi-flag me-1"></i> Submit report';
                    }
                })
                .catch(() => {
                    feedback.className = 'alert alert-danger mb-0';
                    feedback.textContent = 'Network error. Please try again.';
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-flag me-1"></i> Submit report';
                });
        });

        // Reset modal on close
        document.getElementById('reportModal').addEventListener('hidden.bs.modal', function () {
            form.reset();
            feedback.className = 'd-none alert mb-0';
            submitBtn.disabled = false;
            submitBtn.style.display = '';
            submitBtn.innerHTML = '<i class="bi bi-flag me-1"></i> Submit report';
            form.querySelectorAll('select,textarea').forEach(el => el.disabled = false);
        });
    })();
    </script>
    <?php
}
