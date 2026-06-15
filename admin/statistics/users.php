<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

$pdo = db();
$dateRange = $_GET['range'] ?? '90d';

// Compute date filter
$dateFilters = [
    '7d'   => '7 DAY',
    '30d'  => '30 DAY',
    '90d'  => '90 DAY',
    'year' => '1 YEAR',
    'all'  => null,
];
$intervalSql = $dateFilters[$dateRange] ?? '90 DAY';
$dateWhere = $intervalSql
    ? "WHERE u.created_at >= DATE_SUB(NOW(), INTERVAL {$intervalSql})"
    : '';

// === KPI cards ===
$stmt = $pdo->query("SELECT primary_role, COUNT(*) AS cnt FROM users GROUP BY primary_role");
$roleCounts = [];
foreach ($stmt->fetchAll() as $r) {
    $roleCounts[$r['primary_role']] = (int)$r['cnt'];
}
$totalUsers = array_sum($roleCounts);

// Active students (logged in last 30 days — proxy: any chat activity, or fallback to created_at)
$stmt = $pdo->query("
    SELECT COUNT(DISTINCT u.id)
      FROM users u
     WHERE u.primary_role = 'student'
       AND u.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$recentStudents = (int)$stmt->fetchColumn();

// Landlords with at least 1 property
$stmt = $pdo->query("
    SELECT COUNT(DISTINCT p.landlord_id) FROM properties p
");
$landlordsWithProps = (int)$stmt->fetchColumn();

// Students looking for housing
$stmt = $pdo->query("SELECT COUNT(*) FROM students WHERE looking_for_housing = 1");
$studentsLooking = (int)$stmt->fetchColumn();

// Landlords verified
$stmt = $pdo->query("SELECT COUNT(*) FROM landlords WHERE verified = 1");
$landlordsVerified = (int)$stmt->fetchColumn();

// Avg properties per landlord
$stmt = $pdo->query("
    SELECT AVG(prop_count) FROM (
        SELECT COUNT(*) AS prop_count
          FROM properties
         GROUP BY landlord_id
    ) t
");
$avgPropsPerLandlord = (float)$stmt->fetchColumn();

// === User growth chart (monthly, stacked by role) ===
$stmt = $pdo->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
           primary_role,
           COUNT(*) AS cnt
      FROM users
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
     GROUP BY month, primary_role
     ORDER BY month ASC
");
$growthRaw = $stmt->fetchAll();

// Pivot for chart
$months = [];
$growthByRole = ['student' => [], 'landlord' => [], 'agent' => [], 'admin' => []];
foreach ($growthRaw as $r) {
    $months[$r['month']] = true;
}
$months = array_keys($months);
sort($months);

foreach ($months as $m) {
    foreach ($growthByRole as $role => &$arr) {
        $arr[$m] = 0;
    }
    unset($arr);
}
foreach ($growthRaw as $r) {
    if (isset($growthByRole[$r['primary_role']])) {
        $growthByRole[$r['primary_role']][$r['month']] = (int)$r['cnt'];
    }
}

// === University distribution (students only) ===
$stmt = $pdo->query("
    SELECT university, COUNT(*) AS cnt
      FROM students
     GROUP BY university
     ORDER BY cnt DESC
");
$universities = $stmt->fetchAll();

// === Recent signups ===
$stmt = $pdo->query("
    SELECT u.id, u.email, u.primary_role, u.created_at,
           COALESCE(s.full_name, l.full_name, a.full_name, u.email) AS display_name
      FROM users u
      LEFT JOIN students s  ON s.user_id = u.id
      LEFT JOIN landlords l ON l.user_id = u.id
      LEFT JOIN agents a    ON a.user_id = u.id
     ORDER BY u.created_at DESC
     LIMIT 10
");
$recentSignups = $stmt->fetchAll();

// === CSV export ===
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="rentbridge_users_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['User ID', 'Email', 'Display Name', 'Primary Role', 'Joined']);
    $stmt = $pdo->query("
        SELECT u.id, u.email, u.primary_role, u.created_at,
               COALESCE(s.full_name, l.full_name, a.full_name, u.email) AS display_name
          FROM users u
          LEFT JOIN students s  ON s.user_id = u.id
          LEFT JOIN landlords l ON l.user_id = u.id
          LEFT JOIN agents a    ON a.user_id = u.id
         ORDER BY u.created_at DESC
    ");
    while ($row = $stmt->fetch()) {
        fputcsv($out, [$row['id'], $row['email'], $row['display_name'], $row['primary_role'], $row['created_at']]);
    }
    fclose($out);
    exit;
}

$pageTitle = 'Statistics — Users';
$activeNav = 'statistics';

$pageTabs = [
    ['label' => 'Summary',     'href' => '/rentbridge/admin/statistics/summary.php',    'active' => false],
    ['label' => 'Users',       'href' => '/rentbridge/admin/statistics/users.php',      'active' => true],
    ['label' => 'Properties',  'href' => '/rentbridge/admin/statistics/properties.php', 'active' => false],
    ['label' => 'Tenancies',   'href' => '/rentbridge/admin/statistics/tenancies.php',  'active' => false],
    ['label' => 'Financial',   'href' => '/rentbridge/admin/statistics/financial.php',  'active' => false],
];

ob_start();
?>

<!-- HEADER + TABS + FILTERS -->
<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h1 class="mb-1" style="font-family:'Fraunces',serif;">User Analytics</h1>
        <p class="text-secondary mb-0">Signups, growth, and role distribution</p>
    </div>
    <div class="d-flex gap-2">
        <select class="form-select form-select-sm" style="width: auto;"
                onchange="window.location.href='?range=' + this.value">
            <?php foreach (['7d' => 'Last 7 days', '30d' => 'Last 30 days', '90d' => 'Last 90 days', 'year' => 'Last year', 'all' => 'All time'] as $k => $v): ?>
                <option value="<?= $k ?>" <?= $dateRange === $k ? 'selected' : '' ?>><?= e($v) ?></option>
            <?php endforeach; ?>
        </select>
        <a href="?range=<?= e($dateRange) ?>&export=csv" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-download me-1"></i> CSV
        </a>
    </div>
</div>

<!-- KPI CARDS -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="bg-white border rounded-3 p-3 text-center" style="border-left:4px solid #0F2C52 !important;">
            <div class="text-secondary small text-uppercase">Total users</div>
            <strong class="fs-3"><?= number_format($totalUsers) ?></strong>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="bg-white border rounded-3 p-3 text-center" style="border-left:4px solid #2E8B57 !important;">
            <div class="text-secondary small text-uppercase">Students</div>
            <strong class="fs-3"><?= number_format($roleCounts['student'] ?? 0) ?></strong>
            <div class="small text-secondary"><?= $recentStudents ?> new (30d)</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="bg-white border rounded-3 p-3 text-center" style="border-left:4px solid #C9923F !important;">
            <div class="text-secondary small text-uppercase">Landlords</div>
            <strong class="fs-3"><?= number_format($roleCounts['landlord'] ?? 0) ?></strong>
            <div class="small text-secondary"><?= $landlordsWithProps ?> with listings</div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="bg-white border rounded-3 p-3 text-center" style="border-left:4px solid #D4A017 !important;">
            <div class="text-secondary small text-uppercase">Agents</div>
            <strong class="fs-3"><?= number_format($roleCounts['agent'] ?? 0) ?></strong>
            <div class="small text-secondary">UTeM staff</div>
        </div>
    </div>
</div>

<!-- CHARTS ROW 1 -->
<div class="row g-3 mb-4">
    <!-- Growth chart -->
    <div class="col-lg-8">
        <div class="bg-white border rounded-3 p-4 h-100">
            <h6 class="text-secondary text-uppercase small mb-3">User growth (last 12 months)</h6>
            <canvas id="growthChart" height="100"></canvas>
        </div>
    </div>
    <!-- Role distribution doughnut -->
    <div class="col-lg-4">
        <div class="bg-white border rounded-3 p-4 h-100">
            <h6 class="text-secondary text-uppercase small mb-3">Role distribution</h6>
            <canvas id="roleChart"></canvas>
        </div>
    </div>
</div>

<!-- INSIGHTS ROW -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="bg-white border rounded-3 p-4">
            <h6 class="text-secondary text-uppercase small mb-3">Looking for housing</h6>
            <strong class="fs-2 text-emerald"><?= $studentsLooking ?></strong>
            <span class="text-secondary">
                of <?= $roleCounts['student'] ?? 0 ?> students
            </span>
            <div class="small text-secondary mt-2">
                <?= ($roleCounts['student'] ?? 0) > 0
                    ? round(($studentsLooking / $roleCounts['student']) * 100, 1)
                    : 0 ?>% actively searching
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="bg-white border rounded-3 p-4">
            <h6 class="text-secondary text-uppercase small mb-3">Verified landlords</h6>
            <strong class="fs-2"><?= $landlordsVerified ?></strong>
            <span class="text-secondary">
                of <?= $roleCounts['landlord'] ?? 0 ?>
            </span>
            <div class="small text-secondary mt-2">
                <?= ($roleCounts['landlord'] ?? 0) > 0
                    ? round(($landlordsVerified / $roleCounts['landlord']) * 100, 1)
                    : 0 ?>% verified by admin
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="bg-white border rounded-3 p-4">
            <h6 class="text-secondary text-uppercase small mb-3">Avg properties per landlord</h6>
            <strong class="fs-2"><?= number_format($avgPropsPerLandlord, 1) ?></strong>
            <div class="small text-secondary mt-2">
                Across <?= $landlordsWithProps ?> active landlords
            </div>
        </div>
    </div>
</div>

<!-- UNIVERSITY DISTRIBUTION + RECENT SIGNUPS -->
<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="bg-white border rounded-3 p-4">
            <h6 class="text-secondary text-uppercase small mb-3">Student universities</h6>
            <?php if (empty($universities)): ?>
                <p class="text-secondary small">No university data yet.</p>
            <?php else: ?>
                <canvas id="uniChart" height="200"></canvas>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="bg-white border rounded-3 p-4">
            <h6 class="text-secondary text-uppercase small mb-3">Recent signups</h6>
            <?php if (empty($recentSignups)): ?>
                <p class="text-secondary small">No signups yet.</p>
            <?php else: ?>
                <table class="table table-sm">
                    <thead style="background:#F4F4EE;">
                        <tr>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentSignups as $u):
                            $roleColor = match($u['primary_role']) {
                                'student'  => 'bg-success',
                                'landlord' => 'bg-warning text-dark',
                                'agent'    => 'bg-info text-dark',
                                'admin'    => 'bg-danger',
                                default    => 'bg-secondary',
                            };
                        ?>
                            <tr>
                                <td>
                                    <strong><?= e($u['display_name']) ?></strong><br>
                                    <small class="text-secondary"><?= e($u['email']) ?></small>
                                </td>
                                <td>
                                    <span class="badge <?= $roleColor ?>"><?= e($u['primary_role']) ?></span>
                                </td>
                                <td class="small text-secondary">
                                    <?= e(date('d M Y', strtotime($u['created_at']))) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- CHART JS -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
(function() {
    const months = <?= json_encode($months) ?>;
    const studentsData = <?= json_encode(array_values($growthByRole['student'])) ?>;
    const landlordsData = <?= json_encode(array_values($growthByRole['landlord'])) ?>;
    const agentsData = <?= json_encode(array_values($growthByRole['agent'])) ?>;

    // Format month labels (e.g. "2026-03" → "Mar 26")
    const monthLabels = months.map(m => {
        const [y, mo] = m.split('-');
        const date = new Date(parseInt(y), parseInt(mo) - 1, 1);
        return date.toLocaleString('en', { month: 'short', year: '2-digit' });
    });

    // Growth chart
    new Chart(document.getElementById('growthChart'), {
        type: 'line',
        data: {
            labels: monthLabels,
            datasets: [
                { label: 'Students', data: studentsData, borderColor: '#2E8B57', backgroundColor: 'rgba(46,139,87,0.1)', tension: 0.3, fill: true },
                { label: 'Landlords', data: landlordsData, borderColor: '#C9923F', backgroundColor: 'rgba(201,146,63,0.1)', tension: 0.3, fill: true },
                { label: 'Agents', data: agentsData, borderColor: '#D4A017', backgroundColor: 'rgba(212,160,23,0.1)', tension: 0.3, fill: true },
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } },
            scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });

    // Role doughnut
    new Chart(document.getElementById('roleChart'), {
        type: 'doughnut',
        data: {
            labels: ['Students', 'Landlords', 'Agents', 'Admins'],
            datasets: [{
                data: [
                    <?= $roleCounts['student']  ?? 0 ?>,
                    <?= $roleCounts['landlord'] ?? 0 ?>,
                    <?= $roleCounts['agent']    ?? 0 ?>,
                    <?= $roleCounts['admin']    ?? 0 ?>,
                ],
                backgroundColor: ['#2E8B57', '#C9923F', '#D4A017', '#0F2C52'],
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } }
        }
    });

    // University bar
    <?php if (!empty($universities)): ?>
    new Chart(document.getElementById('uniChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode(array_column($universities, 'university')) ?>,
            datasets: [{
                label: 'Students',
                data: <?= json_encode(array_map(fn($u) => (int)$u['cnt'], $universities)) ?>,
                backgroundColor: '#2E8B57',
            }]
        },
        options: {
            responsive: true,
            indexAxis: 'y',
            plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } }
        }
    });
    <?php endif; ?>
})();
</script>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../../includes/admin_layout.php';