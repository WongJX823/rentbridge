<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = db();

// Optional filter: ?status=pending|active|rejected|suspended
$filter = $_GET['status'] ?? 'pending';
$validStatuses = ['pending', 'active', 'rejected', 'suspended', 'all'];
if (!in_array($filter, $validStatuses, true)) $filter = 'pending';

$where = "u.primary_role = 'agent'";
$params = [];
if ($filter !== 'all') {
    $where .= ' AND u.status = ?';
    $params[] = $filter;
}

$stmt = $pdo->prepare("
    SELECT u.id, u.email, u.status AS user_status, u.created_at,
           a.full_name, a.staff_id, a.department, a.phone,
           a.availability, a.current_caseload, a.max_caseload
      FROM users u
      JOIN agents a ON a.user_id = u.id
     WHERE $where
     ORDER BY (u.status = 'pending') DESC, u.created_at DESC
");
$stmt->execute($params);
$agents = $stmt->fetchAll();

// Count for tabs
$counts = [];
foreach (['pending', 'active', 'rejected', 'suspended'] as $s) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE primary_role = 'agent' AND status = ?");
    $stmt->execute([$s]);
    $counts[$s] = (int)$stmt->fetchColumn();
}
$counts['all'] = array_sum($counts);

function user_status_badge(string $status): array {
    return match ($status) {
        'pending'   => ['Pending review', 'warning'],
        'active'    => ['Active',          'success'],
        'rejected'  => ['Rejected',        'danger'],
        'suspended' => ['Suspended',       'secondary'],
        default     => [ucfirst($status),  'secondary'],
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Agent management · Admin · RentBridge</title>
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
            <h1 class="mb-1">Agent management</h1>
            <p class="text-secondary mb-0">UTeM staff who serve as witness agents.</p>
        </div>
        <a href="/rentbridge/admin/dashboard.php" class="btn btn-ghost">
            <i class="bi bi-arrow-left me-1"></i> Back to dashboard
        </a>
    </div>

    <!-- Status filter tabs -->
    <ul class="nav nav-pills mb-4">
        <?php foreach (['pending', 'active', 'rejected', 'suspended', 'all'] as $s):
            $label = ucfirst($s);
        ?>
            <li class="nav-item">
                <a class="nav-link <?= $filter === $s ? 'active' : '' ?>"
                   href="?status=<?= $s ?>">
                    <?= $label ?>
                    <span class="badge bg-light text-dark ms-1"><?= $counts[$s] ?></span>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if (empty($agents)): ?>
        <div class="text-center py-5 bg-white rounded-3 border">
            <i class="bi bi-people" style="font-size: 3rem; color: var(--rb-line);"></i>
            <h4 class="mt-3">No agents in "<?= e($filter) ?>"</h4>
        </div>
    <?php else: ?>
        <div class="bg-white border rounded-3 overflow-hidden">
            <table class="table mb-0">
                <thead style="background: var(--rb-cream);">
                    <tr>
                        <th class="ps-4">Name</th>
                        <th>Staff ID</th>
                        <th>Department</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th class="pe-4 text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($agents as $a):
                    [$badgeLabel, $badgeColor] = user_status_badge($a['user_status']);
                ?>
                    <tr>
                        <td class="ps-4">
                            <strong><?= e($a['full_name']) ?></strong>
                            <?php if ((int)$a['current_caseload'] > 0): ?>
                                <small class="text-secondary d-block">
                                    Cases: <?= (int)$a['current_caseload'] ?>/<?= (int)$a['max_caseload'] ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td><code><?= e($a['staff_id']) ?></code></td>
                        <td><?= e($a['department']) ?></td>
                        <td><small><?= e($a['email']) ?></small></td>
                        <td><span class="badge bg-<?= $badgeColor ?>"><?= e($badgeLabel) ?></span></td>
                        <td><small><?= e(date('d M Y', strtotime($a['created_at']))) ?></small></td>
                        <td class="pe-4 text-end">
                            <a href="/rentbridge/admin/agent.php?id=<?= (int)$a['id'] ?>"
                               class="btn btn-sm btn-outline-dark">
                                Review <i class="bi bi-arrow-right ms-1"></i>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

</body>
</html>