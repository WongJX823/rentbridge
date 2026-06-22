<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/reports.php';
require_role('admin');

$pdo = db();

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $reportId  = (int)($_POST['report_id'] ?? 0);
    $newStatus = $_POST['new_status'] ?? '';
    $allowed   = ['pending', 'reviewed', 'dismissed', 'actioned'];
    if ($reportId > 0 && in_array($newStatus, $allowed, true)) {
        $pdo->prepare("
            UPDATE reports SET status = ?, reviewed_at = NOW(), reviewed_by = ? WHERE id = ?
        ")->execute([$newStatus, current_user_id(), $reportId]);
    }
    header('Location: /rentbridge/admin/reports.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
    exit;
}

// Filters
$filterStatus = $_GET['filter_status'] ?? '';
$filterUser   = (int)($_GET['filter_user'] ?? 0);
$filterCtx    = $_GET['filter_ctx'] ?? '';

// Summary counts
$summary = $pdo->query("
    SELECT status, COUNT(*) AS cnt FROM reports GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);

// Users with ≥3 reports in past 30 days (flagged)
$flaggedUsers = $pdo->query("
    SELECT reported_user_id,
           COALESCE(st.full_name, ll.full_name, ag.full_name, 'Unknown') AS full_name,
           u.primary_role,
           COUNT(*) AS report_count,
           MAX(r.created_at) AS latest_report
      FROM reports r
      JOIN users u ON u.id = r.reported_user_id
      LEFT JOIN students  st ON st.user_id = u.id
      LEFT JOIN landlords ll ON ll.user_id = u.id
      LEFT JOIN agents    ag ON ag.user_id = u.id
     WHERE r.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
       AND r.status != 'dismissed'
     GROUP BY reported_user_id, u.primary_role
    HAVING COUNT(*) >= " . REPORT_FLAG_THRESHOLD . "
     ORDER BY report_count DESC
")->fetchAll();

// Build main query
$where  = ['1=1'];
$params = [];

if ($filterStatus !== '') {
    $where[]  = 'r.status = ?';
    $params[] = $filterStatus;
}
if ($filterUser > 0) {
    $where[]  = '(r.reporter_id = ? OR r.reported_user_id = ?)';
    $params[] = $filterUser;
    $params[] = $filterUser;
}
if ($filterCtx !== '') {
    $where[]  = 'r.context_type = ?';
    $params[] = $filterCtx;
}

$whereClause = implode(' AND ', $where);

$stmt = $pdo->prepare("
    SELECT r.*,
           COALESCE(rs.full_name, rl.full_name, ra.full_name, 'Unknown') AS reporter_name,
           ru.primary_role AS reporter_role,
           COALESCE(ts.full_name, tl.full_name, ta.full_name, 'Unknown') AS reported_name,
           tu.primary_role AS reported_role
      FROM reports r
      JOIN users ru ON ru.id = r.reporter_id
      LEFT JOIN students  rs ON rs.user_id = ru.id
      LEFT JOIN landlords rl ON rl.user_id = ru.id
      LEFT JOIN agents    ra ON ra.user_id = ru.id
      JOIN users tu ON tu.id = r.reported_user_id
      LEFT JOIN students  ts ON ts.user_id = tu.id
      LEFT JOIN landlords tl ON tl.user_id = tu.id
      LEFT JOIN agents    ta ON ta.user_id = tu.id
     WHERE {$whereClause}
     ORDER BY r.created_at DESC
     LIMIT 200
");
$stmt->execute($params);
$reports = $stmt->fetchAll();

$pageTitle = 'Flag Reports';
$activeNav = 'flagreports';

ob_start();
?>

<!-- SUMMARY CARDS -->
<div class="row g-3 mb-4">
    <?php
    $cards = [
        ['key' => 'pending',   'label' => 'Pending',   'color' => '#F5A623', 'icon' => 'bi-hourglass-split'],
        ['key' => 'reviewed',  'label' => 'Reviewed',  'color' => '#2E8B57', 'icon' => 'bi-check-circle'],
        ['key' => 'dismissed', 'label' => 'Dismissed', 'color' => '#6c757d', 'icon' => 'bi-dash-circle'],
        ['key' => 'actioned',  'label' => 'Actioned',  'color' => '#C62828', 'icon' => 'bi-shield-exclamation'],
    ];
    foreach ($cards as $c):
        $cnt = (int)($summary[$c['key']] ?? 0);
    ?>
    <div class="col-sm-6 col-xl-3">
        <div class="bg-white border rounded-3 p-4 d-flex align-items-center gap-3">
            <div style="width:48px;height:48px;border-radius:12px;background:<?= $c['color'] ?>22;
                        display:flex;align-items:center;justify-content:center;color:<?= $c['color'] ?>;font-size:1.4rem;">
                <i class="bi <?= $c['icon'] ?>"></i>
            </div>
            <div>
                <div class="fs-3 fw-bold lh-1"><?= $cnt ?></div>
                <div class="small text-secondary"><?= $c['label'] ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- FLAGGED USERS ALERT -->
<?php if (!empty($flaggedUsers)): ?>
<div class="alert alert-danger d-flex gap-3 align-items-start mb-4" role="alert">
    <i class="bi bi-exclamation-triangle-fill fs-4 flex-shrink-0 mt-1"></i>
    <div>
        <strong>High-report users (last 30 days)</strong>
        <ul class="mb-0 mt-2 ps-3">
            <?php foreach ($flaggedUsers as $fu): ?>
            <li>
                <a href="?filter_user=<?= (int)$fu['reported_user_id'] ?>" class="alert-link">
                    <?= htmlspecialchars($fu['full_name'], ENT_QUOTES) ?>
                </a>
                (<?= htmlspecialchars(ucfirst($fu['primary_role']), ENT_QUOTES) ?>) —
                <strong><?= (int)$fu['report_count'] ?> reports</strong>,
                latest <?= date('d M Y', strtotime($fu['latest_report'])) ?>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<!-- FILTERS -->
<form method="GET" class="row g-2 mb-4">
    <div class="col-auto">
        <select name="filter_status" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">All statuses</option>
            <option value="pending"   <?= $filterStatus === 'pending'   ? 'selected' : '' ?>>Pending</option>
            <option value="reviewed"  <?= $filterStatus === 'reviewed'  ? 'selected' : '' ?>>Reviewed</option>
            <option value="dismissed" <?= $filterStatus === 'dismissed' ? 'selected' : '' ?>>Dismissed</option>
            <option value="actioned"  <?= $filterStatus === 'actioned'  ? 'selected' : '' ?>>Actioned</option>
        </select>
    </div>
    <div class="col-auto">
        <select name="filter_ctx" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="">All contexts</option>
            <option value="booking" <?= $filterCtx === 'booking' ? 'selected' : '' ?>>Booking</option>
            <option value="message" <?= $filterCtx === 'message' ? 'selected' : '' ?>>Chat message</option>
            <option value="general" <?= $filterCtx === 'general' ? 'selected' : '' ?>>General</option>
        </select>
    </div>
    <?php if ($filterUser > 0): ?>
    <div class="col-auto">
        <a href="/rentbridge/admin/reports.php" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-x"></i> Clear user filter
        </a>
    </div>
    <?php endif; ?>
</form>

<!-- TABLE -->
<div class="bg-white border rounded-3 overflow-hidden">
    <?php if (empty($reports)): ?>
        <div class="p-5 text-center text-secondary">
            <i class="bi bi-flag" style="font-size:3rem;opacity:.3;"></i>
            <div class="mt-2">No reports found.</div>
        </div>
    <?php else: ?>
    <table class="table table-hover mb-0 small">
        <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Reporter</th>
                <th>Reported</th>
                <th>Reason</th>
                <th>Context</th>
                <th>Details</th>
                <th>Date</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($reports as $r):
            $statusColor = match ($r['status']) {
                'pending'   => 'warning',
                'reviewed'  => 'success',
                'dismissed' => 'secondary',
                'actioned'  => 'danger',
                default     => 'secondary',
            };
        ?>
        <tr>
            <td class="text-secondary"><?= (int)$r['id'] ?></td>
            <td>
                <div class="fw-semibold"><?= htmlspecialchars($r['reporter_name'], ENT_QUOTES) ?></div>
                <div class="text-secondary"><?= ucfirst($r['reporter_role']) ?></div>
            </td>
            <td>
                <a href="?filter_user=<?= (int)$r['reported_user_id'] ?>"
                   class="fw-semibold text-danger text-decoration-none">
                    <?= htmlspecialchars($r['reported_name'], ENT_QUOTES) ?>
                </a>
                <div class="text-secondary"><?= ucfirst($r['reported_role']) ?></div>
            </td>
            <td><?= ucfirst(str_replace('_', ' ', $r['reason'])) ?></td>
            <td>
                <span class="badge bg-light text-dark"><?= ucfirst($r['context_type']) ?></span>
                <?php if ($r['context_id']): ?>
                    <span class="text-secondary">#<?= (int)$r['context_id'] ?></span>
                <?php endif; ?>
            </td>
            <td style="max-width:200px;">
                <?php if (!empty($r['details'])): ?>
                    <span title="<?= htmlspecialchars($r['details'], ENT_QUOTES) ?>">
                        <?= htmlspecialchars(mb_strimwidth($r['details'], 0, 60, '…'), ENT_QUOTES) ?>
                    </span>
                <?php else: ?>
                    <span class="text-secondary">—</span>
                <?php endif; ?>
            </td>
            <td class="text-secondary text-nowrap"><?= date('d M Y', strtotime($r['created_at'])) ?></td>
            <td>
                <span class="badge bg-<?= $statusColor ?>">
                    <?= ucfirst($r['status']) ?>
                </span>
            </td>
            <td>
                <form method="POST" class="d-inline">
                    <?= csrf_field() ?>
                    <input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>">
                    <select name="new_status" class="form-select form-select-sm d-inline-block w-auto"
                            onchange="this.form.submit()"
                            style="font-size:.75rem;">
                        <option value="">Change…</option>
                        <option value="pending">Pending</option>
                        <option value="reviewed">Reviewed</option>
                        <option value="dismissed">Dismissed</option>
                        <option value="actioned">Actioned</option>
                    </select>
                </form>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/admin_layout.php';
