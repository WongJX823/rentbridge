<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('admin');

$pdo = db();

$role = $_GET['role'] ?? 'all';
$validRoles = ['all', 'student', 'landlord', 'agent', 'admin'];
if (!in_array($role, $validRoles, true)) $role = 'all';

$search = trim($_GET['q'] ?? '');

$where = '1=1';
$params = [];

if ($role !== 'all') {
    $where .= ' AND u.primary_role = ?';
    $params[] = $role;
}

if ($search !== '') {
    $where .= ' AND (u.email LIKE ? OR
                    COALESCE(s.full_name, l.full_name, a.full_name) LIKE ?)';
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

$stmt = $pdo->prepare("
    SELECT u.id, u.email, u.primary_role, u.status, u.created_at,
           COALESCE(s.full_name, l.full_name, a.full_name) AS full_name,
           COALESCE(s.matric_no, l.ic_no, a.staff_id) AS identifier
      FROM users u
      LEFT JOIN students  s ON s.user_id = u.id
      LEFT JOIN landlords l ON l.user_id = u.id
      LEFT JOIN agents    a ON a.user_id = u.id
     WHERE $where
     ORDER BY u.created_at DESC
     LIMIT 100
");
$stmt->execute($params);
$users = $stmt->fetchAll();

function user_status_badge(string $status): array {
    return match ($status) {
        'pending'   => ['Pending',   'warning'],
        'active'    => ['Active',    'success'],
        'rejected'  => ['Rejected',  'danger'],
        'suspended' => ['Suspended', 'secondary'],
        default     => [ucfirst($status), 'secondary'],
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>All users · Admin · RentBridge</title>
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
            <h1 class="mb-1">All users</h1>
            <p class="text-secondary mb-0"><?= count($users) ?> shown (max 100)</p>
        </div>
        <a href="/rentbridge/admin/dashboard.php" class="btn btn-ghost">
            <i class="bi bi-arrow-left me-1"></i> Back
        </a>
    </div>

    <!-- Filter form -->
    <form method="GET" class="bg-white border rounded-3 p-3 mb-4">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-semibold text-secondary">SEARCH BY NAME OR EMAIL</label>
                <input type="text" name="q" value="<?= e($search) ?>"
                       class="form-control" placeholder="Name or email...">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-semibold text-secondary">ROLE</label>
                <select name="role" class="form-select">
                    <?php foreach ($validRoles as $r): ?>
                        <option value="<?= $r ?>" <?= $role === $r ? 'selected' : '' ?>>
                            <?= ucfirst($r) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="bi bi-search"></i> Filter
                </button>
            </div>
            <div class="col-md-3 text-end">
                <a href="users.php" class="btn btn-ghost btn-sm">Clear filters</a>
            </div>
        </div>
    </form>

    <?php if (empty($users)): ?>
        <div class="text-center py-5 bg-white rounded-3 border">
            <i class="bi bi-search" style="font-size: 3rem; color: var(--rb-line);"></i>
            <h4 class="mt-3">No users match your filter</h4>
        </div>
    <?php else: ?>
        <div class="bg-white border rounded-3 overflow-hidden">
            <table class="table mb-0">
                <thead style="background: var(--rb-cream);">
                    <tr>
                        <th class="ps-4">Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Identifier</th>
                        <th>Status</th>
                        <th class="pe-4">Joined</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u):
                    [$badgeLabel, $badgeColor] = user_status_badge($u['status']);
                ?>
                    <tr>
                        <td class="ps-4">
                            <strong><?= e($u['full_name'] ?? '—') ?></strong>
                        </td>
                        <td><small><?= e($u['email']) ?></small></td>
                        <td>
                            <span class="badge bg-light text-dark border">
                                <?= e(ucfirst($u['primary_role'])) ?>
                            </span>
                        </td>
                        <td><small><code><?= e($u['identifier'] ?? '—') ?></code></small></td>
                        <td><span class="badge bg-<?= $badgeColor ?>"><?= e($badgeLabel) ?></span></td>
                        <td class="pe-4"><small><?= e(date('d M Y', strtotime($u['created_at']))) ?></small></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

</body>
</html>