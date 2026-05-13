<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = db();

$filter = $_GET['status'] ?? 'pending_signatures';
$validStatuses = ['pending_signatures', 'active', 'completed', 'terminated', 'all'];
if (!in_array($filter, $validStatuses, true)) $filter = 'pending_signatures';

$search = trim($_GET['q'] ?? '');

$where = '1=1';
$params = [];

if ($filter !== 'all') {
    $where .= ' AND c.status = ?';
    $params[] = $filter;
}

if ($search !== '') {
    $where .= ' AND c.contract_code LIKE ?';
    $params[] = '%' . $search . '%';
}

$stmt = $pdo->prepare("
    SELECT c.id, c.contract_code, c.status, c.contract_pdf_path,
           c.created_at, c.activated_at,
           c.student_signed_at, c.landlord_signed_at, c.agent_signed_at,
           c.start_date, c.end_date, c.monthly_rent,
           p.title          AS property_title,
           p.city           AS property_city,
           s.full_name      AS student_name,
           l.full_name      AS landlord_name,
           a.full_name      AS agent_name
      FROM contracts c
      JOIN properties p ON p.id = c.property_id
      JOIN students   s ON s.user_id = c.student_id
      JOIN landlords  l ON l.user_id = c.landlord_id
      JOIN agents     a ON a.user_id = c.agent_id
     WHERE $where
     ORDER BY
       CASE WHEN c.status = 'pending_signatures' THEN 0 ELSE 1 END,
       c.created_at DESC
");
$stmt->execute($params);
$contracts = $stmt->fetchAll();

// Counts for tabs
$counts = [];
foreach (['pending_signatures', 'active', 'completed', 'terminated'] as $s) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM contracts WHERE status = ?");
    $stmt->execute([$s]);
    $counts[$s] = (int)$stmt->fetchColumn();
}
$counts['all'] = array_sum($counts);

function contract_status_badge(string $status): array {
    return match ($status) {
        'pending_signatures' => ['Pending signatures', 'warning'],
        'active'             => ['Active',             'success'],
        'completed'          => ['Completed',          'secondary'],
        'terminated'         => ['Terminated',         'danger'],
        default              => [ucfirst($status),     'secondary'],
    };
}

function pretty_filter(string $s): string {
    return match ($s) {
        'pending_signatures' => 'Pending',
        'all'                => 'All',
        default              => ucfirst($s),
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All contracts · Admin · RentBridge</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../assets/css/style.css" rel="stylesheet">
</head>
<body>

<?php include '../includes/header.php'; ?>

<div class="container py-5">
    <div class="d-flex justify-content-between align-items-end mb-4">
        <div>
            <h1 class="mb-1">All contracts</h1>
            <p class="text-secondary mb-0"><?= count($contracts) ?> contract<?= count($contracts) === 1 ? '' : 's' ?> shown</p>
        </div>
        <a href="/rentbridge/admin/dashboard.php" class="btn btn-ghost">
            <i class="bi bi-arrow-left me-1"></i> Back to dashboard
        </a>
    </div>

    <!-- Search + filter form -->
    <form method="GET" class="bg-white border rounded-3 p-3 mb-4">
        <div class="row g-2 align-items-end">
            <div class="col-md-7">
                <label class="form-label small fw-semibold text-secondary">SEARCH BY CONTRACT CODE</label>
                <input type="text" name="q" value="<?= e($search) ?>"
                       class="form-control" placeholder="RB-2026-00001">
            </div>
            <div class="col-md-3">
                <input type="hidden" name="status" value="<?= e($filter) ?>">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Search
                </button>
            </div>
            <div class="col-md-2 text-end">
                <a href="contracts.php" class="btn btn-ghost btn-sm">Clear</a>
            </div>
        </div>
    </form>

    <!-- Status filter tabs -->
    <ul class="nav nav-pills mb-4 flex-wrap">
        <?php foreach (['pending_signatures', 'active', 'completed', 'terminated', 'all'] as $s): ?>
            <li class="nav-item">
                <a class="nav-link <?= $filter === $s ? 'active' : '' ?>"
                   href="?status=<?= $s ?><?= $search !== '' ? '&q=' . urlencode($search) : '' ?>">
                    <?= e(pretty_filter($s)) ?>
                    <span class="badge bg-light text-dark ms-1"><?= $counts[$s] ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if (empty($contracts)): ?>
        <div class="text-center py-5 bg-white rounded-3 border">
            <i class="bi bi-file-earmark-text" style="font-size: 3rem; color: var(--rb-line);"></i>
            <h4 class="mt-3">No contracts <?= $search !== '' ? 'match your search' : 'in "' . e(pretty_filter($filter)) . '"' ?></h4>
        </div>
    <?php else: ?>
        <div class="row g-3">
            <?php foreach ($contracts as $c):
                [$label, $color] = contract_status_badge($c['status']);
                $isPending = $c['status'] === 'pending_signatures';
                $pdfFullPath = !empty($c['contract_pdf_path']) ? __DIR__ . '/../' . $c['contract_pdf_path'] : null;
                $cacheBust = ($pdfFullPath && file_exists($pdfFullPath)) ? '?v=' . filemtime($pdfFullPath) : '';
            ?>
                <div class="col-12">
                    <div class="bg-white border rounded-3 p-4 <?= $isPending ? 'booking-row--urgent' : '' ?>">
                        <div class="row g-3 align-items-center">

                            <!-- Code + status -->
                            <div class="col-md-3">
                                <div class="d-flex align-items-center gap-2 mb-1">
                                    <code class="fs-6"><?= e($c['contract_code']) ?></code>
                                </div>
                                <span class="badge bg-<?= $color ?>"><?= e($label) ?></span>
                            </div>

                            <!-- Parties -->
                            <div class="col-md-4">
                                <small class="text-secondary text-uppercase">Parties</small>
                                <div class="small">
                                    <div><i class="bi bi-person"></i> <?= e($c['student_name']) ?></div>
                                    <div><i class="bi bi-house"></i> <?= e($c['landlord_name']) ?></div>
                                    <div><i class="bi bi-person-badge"></i> <?= e($c['agent_name']) ?></div>
                                </div>
                            </div>

                            <!-- Property + dates -->
                            <div class="col-md-3">
                                <small class="text-secondary text-uppercase">Property</small>
                                <div class="small fw-semibold"><?= e($c['property_title']) ?></div>
                                <div class="text-secondary small">
                                    <?= e($c['property_city']) ?>
                                    &nbsp;·&nbsp;
                                    <?= e(date('d M Y', strtotime($c['start_date']))) ?> →
                                    <?= e(date('d M Y', strtotime($c['end_date']))) ?>
                                </div>
                            </div>

                            <!-- Sign progress + actions -->
                            <div class="col-md-2 text-md-end">
                                <small class="text-secondary text-uppercase d-block mb-1">Signed</small>
                                <div class="mb-2 small">
                                    <span class="<?= !empty($c['student_signed_at'])  ? 'text-success' : 'text-secondary' ?>" title="Student"><?= !empty($c['student_signed_at'])  ? '✓' : '○' ?></span>
                                    <span class="<?= !empty($c['landlord_signed_at']) ? 'text-success' : 'text-secondary' ?>" title="Landlord"><?= !empty($c['landlord_signed_at']) ? '✓' : '○' ?></span>
                                    <span class="<?= !empty($c['agent_signed_at'])    ? 'text-success' : 'text-secondary' ?>" title="Agent"><?= !empty($c['agent_signed_at'])    ? '✓' : '○' ?></span>
                                </div>
                                <div class="d-flex gap-1 justify-content-md-end">
                                    <?php if (!empty($c['contract_pdf_path']) && $pdfFullPath && file_exists($pdfFullPath)): ?>
                                        <a href="/rentbridge/<?= e($c['contract_pdf_path']) ?><?= $cacheBust ?>"
                                           target="_blank" class="btn btn-sm btn-success" title="Download PDF">
                                            <i class="bi bi-download"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="/rentbridge/contracts/view.php?id=<?= (int)$c['id'] ?>"
                                       class="btn btn-sm btn-outline-dark">
                                        Open <i class="bi bi-arrow-right ms-1"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</body>
</html>