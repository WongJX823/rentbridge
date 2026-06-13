<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

// CSV export — pricing table
if (($_GET['export'] ?? '') === 'pricing') {
    $stmt = $pdo->query("
        SELECT city, property_type,
               COUNT(*) AS listing_count,
               ROUND(AVG(monthly_rent), 0) AS avg_rent,
               MIN(monthly_rent) AS min_rent,
               MAX(monthly_rent) AS max_rent
          FROM properties
         WHERE status IN ('available','booked','rented')
         GROUP BY city, property_type
         ORDER BY city ASC, property_type ASC
    ");
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="pricing_benchmark_' . date('Y-m-d') . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, ['City', 'Property Type', 'Listings', 'Average Rent (RM)', 'Min Rent (RM)', 'Max Rent (RM)']);
    foreach ($rows as $row) {
        fputcsv($out, [
            $row['city'],
            ucfirst(str_replace('_', ' ', $row['property_type'])),
            $row['listing_count'],
            $row['avg_rent'],
            $row['min_rent'],
            $row['max_rent']
        ]);
    }
    fclose($out);
    exit;
}

$pdo = db();

// Date range filter
$range = $_GET['range'] ?? '30d';
$validRanges = ['7d', '30d', '90d', 'year', 'all'];
if (!in_array($range, $validRanges, true)) $range = '30d';

$rangeDate = match ($range) {
    '7d'   => date('Y-m-d', strtotime('-7 days')),
    '30d'  => date('Y-m-d', strtotime('-30 days')),
    '90d'  => date('Y-m-d', strtotime('-90 days')),
    'year' => date('Y-01-01'),
    'all'  => '2000-01-01',
};

// === TOP STAT CARDS ===
$totalStudents   = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE primary_role='student' AND status='active'")->fetchColumn();
$totalLandlords  = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE primary_role='landlord' AND status='active'")->fetchColumn();
$totalProperties = (int)$pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn();
$totalTenancies  = (int)$pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$activeTenancies = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status='active'")->fetchColumn();
$totalRevenue    = (float)$pdo->query("SELECT COALESCE(SUM(total_payable),0) FROM agent_commissions WHERE status IN ('earned','released','paid')")->fetchColumn();

// === CHART 1: New users joined per month ===
$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
           SUM(CASE WHEN primary_role='student'  THEN 1 ELSE 0 END) AS students,
           SUM(CASE WHEN primary_role='landlord' THEN 1 ELSE 0 END) AS landlords,
           SUM(CASE WHEN primary_role='agent'    THEN 1 ELSE 0 END) AS agents
      FROM users
     WHERE created_at >= ?
     GROUP BY month
     ORDER BY month ASC
");
$stmt->execute([$rangeDate]);
$userGrowth = $stmt->fetchAll();

// === CHART 2: Property status distribution (donut) ===
$stmt = $pdo->query("
    SELECT status, COUNT(*) AS cnt
      FROM properties
     GROUP BY status
");
$propStatus = $stmt->fetchAll();

// === CHART 3: Properties per city (bar) ===
$stmt = $pdo->query("
    SELECT city, COUNT(*) AS cnt
      FROM properties
     WHERE status IN ('available','booked','rented')
     GROUP BY city
     ORDER BY cnt DESC
     LIMIT 10
");
$propByCity = $stmt->fetchAll();

// === CHART 4: Tenancies per month (line) ===
$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
           COUNT(*) AS total,
           SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) AS active,
           SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed
      FROM bookings
     WHERE created_at >= ?
     GROUP BY month
     ORDER BY month ASC
");
$stmt->execute([$rangeDate]);
$tenancyTrend = $stmt->fetchAll();

// === PRICING BENCHMARK TABLE ===
$stmt = $pdo->query("
    SELECT city,
           property_type,
           COUNT(*) AS listing_count,
           ROUND(AVG(monthly_rent), 0) AS avg_rent,
           MIN(monthly_rent) AS min_rent,
           MAX(monthly_rent) AS max_rent
      FROM properties
     WHERE status IN ('available','booked','rented')
     GROUP BY city, property_type
     ORDER BY city ASC, property_type ASC
");
$priceTable = $stmt->fetchAll();

$pageTitle = 'Statistics — Summary';
$activeNav = 'statistics';
$pageTabs = [
    ['label' => 'Summary',     'href' => '/rentbridge/admin/statistics/summary.php',    'active' => true],
    ['label' => 'Users',       'href' => '/rentbridge/admin/statistics/users.php',     'active' => false],
    ['label' => 'Properties',  'href' => '/rentbridge/admin/statistics/properties.php','active' => false],
    ['label' => 'Tenancies',   'href' => '/rentbridge/admin/statistics/tenancies.php', 'active' => false],
    ['label' => 'Financial',   'href' => '/rentbridge/admin/statistics/financial.php', 'active' => false],
];
ob_start()
?>

<!-- DATE RANGE FILTER -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <p class="text-secondary mb-0">
        Platform overview with charts.
        <small>Time-series data: <?= e(str_replace(['7d','30d','90d','year','all'], ['Last 7 days','Last 30 days','Last 90 days','This year','All time'], $range)) ?></small>
    </p>
    <div class="btn-group btn-group-sm">
        <?php foreach (['7d'=>'7d', '30d'=>'30d', '90d'=>'90d', 'year'=>'Year', 'all'=>'All'] as $r => $label): ?>
            <a href="?range=<?= e($r) ?>"
               class="btn <?= $range === $r ? 'btn-dark' : 'btn-outline-dark' ?>">
                <?= e($label) ?>
            </a>
        <?php endforeach; ?>
        <button type="button" class="btn btn-outline-secondary ms-2" disabled
                title="PDF report export — coming soon">
            <i class="bi bi-file-pdf"></i> Generate Report
        </button>
    </div>
</div>

<!-- TOP STAT CARDS -->
<div class="row g-3 mb-4">
    <!-- TOP STAT CARDS -->
<div class="row g-3 mb-4">

    <!-- Students -> users.php (when built) -->
    <div class="col-md-4 col-lg-2">
        <a href="/rentbridge/admin/statistics/users.php"
           class="d-block text-decoration-none text-dark">
            <div class="bg-white border rounded-3 p-3 h-100 stat-card-clickable">
                <div style="width:36px; height:36px; background:#E4F2EA; color:#2E8B57;
                            border-radius:8px; display:flex; align-items:center; justify-content:center;">
                    <i class="bi bi-mortarboard"></i>
                </div>
                <div style="font-family:'Fraunces',serif; font-size:1.5rem; font-weight:600; margin-top:8px;">
                    <?= $totalStudents ?>
                </div>
                <div class="small text-secondary d-flex justify-content-between align-items-center">
                    <span>Students</span>
                    <i class="bi bi-arrow-right small"></i>
                </div>
            </div>
        </a>
    </div>

    <!-- Landlords -> users.php -->
    <div class="col-md-4 col-lg-2">
        <a href="/rentbridge/admin/statistics/users.php"
           class="d-block text-decoration-none text-dark">
            <div class="bg-white border rounded-3 p-3 h-100 stat-card-clickable">
                <div style="width:36px; height:36px; background:#E6ECF4; color:#0F2C52;
                            border-radius:8px; display:flex; align-items:center; justify-content:center;">
                    <i class="bi bi-person-badge"></i>
                </div>
                <div style="font-family:'Fraunces',serif; font-size:1.5rem; font-weight:600; margin-top:8px;">
                    <?= $totalLandlords ?>
                </div>
                <div class="small text-secondary d-flex justify-content-between align-items-center">
                    <span>Landlords</span>
                    <i class="bi bi-arrow-right small"></i>
                </div>
            </div>
        </a>
    </div>

    <!-- Properties -> properties.php -->
    <div class="col-md-4 col-lg-2">
        <a href="/rentbridge/admin/statistics/properties.php"
           class="d-block text-decoration-none text-dark">
            <div class="bg-white border rounded-3 p-3 h-100 stat-card-clickable">
                <div style="width:36px; height:36px; background:#FFF4D6; color:#D4A017;
                            border-radius:8px; display:flex; align-items:center; justify-content:center;">
                    <i class="bi bi-house"></i>
                </div>
                <div style="font-family:'Fraunces',serif; font-size:1.5rem; font-weight:600; margin-top:8px;">
                    <?= $totalProperties ?>
                </div>
                <div class="small text-secondary d-flex justify-content-between align-items-center">
                    <span>Properties</span>
                    <i class="bi bi-arrow-right small"></i>
                </div>
            </div>
        </a>
    </div>

    <!-- Tenancies -> tenancies.php (when built) -->
    <div class="col-md-4 col-lg-2">
        <a href="/rentbridge/admin/statistics/tenancies.php"
           class="d-block text-decoration-none text-dark">
            <div class="bg-white border rounded-3 p-3 h-100 stat-card-clickable">
                <div style="width:36px; height:36px; background:#F4F4EE; color:#0F2C52;
                            border-radius:8px; display:flex; align-items:center; justify-content:center;">
                    <i class="bi bi-clipboard-data"></i>
                </div>
                <div style="font-family:'Fraunces',serif; font-size:1.5rem; font-weight:600; margin-top:8px;">
                    <?= $totalTenancies ?>
                </div>
                <div class="small text-secondary d-flex justify-content-between align-items-center">
                    <span>Tenancies</span>
                    <i class="bi bi-arrow-right small"></i>
                </div>
            </div>
        </a>
    </div>

    <!-- Active now -> tenancies.php -->
    <div class="col-md-4 col-lg-2">
        <a href="/rentbridge/admin/statistics/tenancies.php"
           class="d-block text-decoration-none text-dark">
            <div class="bg-white border rounded-3 p-3 h-100 stat-card-clickable">
                <div style="width:36px; height:36px; background:#E4F2EA; color:#2E8B57;
                            border-radius:8px; display:flex; align-items:center; justify-content:center;">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div style="font-family:'Fraunces',serif; font-size:1.5rem; font-weight:600; margin-top:8px;">
                    <?= $activeTenancies ?>
                </div>
                <div class="small text-secondary d-flex justify-content-between align-items-center">
                    <span>Active now</span>
                    <i class="bi bi-arrow-right small"></i>
                </div>
            </div>
        </a>
    </div>

    <!-- Total commission -> financial.php (when built) -->
    <div class="col-md-4 col-lg-2">
        <a href="/rentbridge/admin/statistics/financial.php"
           class="d-block text-decoration-none text-dark">
            <div class="bg-white border rounded-3 p-3 h-100 stat-card-clickable"
                 style="background: linear-gradient(135deg, #fff 0%, #F4FBF7 100%);">
                <div style="width:36px; height:36px; background:#2E8B57; color:white;
                            border-radius:8px; display:flex; align-items:center; justify-content:center;">
                    <i class="bi bi-cash-stack"></i>
                </div>
                <div style="font-family:'Fraunces',serif; font-size:1.5rem; font-weight:600; margin-top:8px; color:#2E8B57;">
                    RM<?= number_format($totalRevenue, 0) ?>
                </div>
                <div class="small text-secondary d-flex justify-content-between align-items-center">
                    <span>Total commission</span>
                    <i class="bi bi-arrow-right small"></i>
                </div>
            </div>
        </a>
    </div>

</div>
</div>

<!-- CHARTS ROW 1 -->
<div class="row g-3 mb-4">
    <!-- USER GROWTH (line) -->
    <div class="col-lg-8">
        <div class="bg-white border rounded-3 p-4">
            <h6 class="text-secondary text-uppercase small mb-3">User growth (joined per month)</h6>
            <canvas id="chartUserGrowth" style="max-height: 300px;"></canvas>
        </div>
    </div>
    <!-- PROPERTY STATUS (donut) -->
    <div class="col-lg-4">
        <div class="bg-white border rounded-3 p-4 h-100">
            <h6 class="text-secondary text-uppercase small mb-3">Property status</h6>
            <canvas id="chartPropStatus" style="max-height: 300px;"></canvas>
        </div>
    </div>
</div>

<!-- CHARTS ROW 2 -->
<div class="row g-3 mb-4">
    <!-- TENANCY TREND (line) -->
    <div class="col-lg-8">
        <div class="bg-white border rounded-3 p-4">
            <h6 class="text-secondary text-uppercase small mb-3">Tenancies created per month</h6>
            <canvas id="chartTenancyTrend" style="max-height: 300px;"></canvas>
        </div>
    </div>
    <!-- PROPERTIES PER CITY (bar) -->
    <div class="col-lg-4">
        <div class="bg-white border rounded-3 p-4 h-100">
            <h6 class="text-secondary text-uppercase small mb-3">Properties per city</h6>
            <canvas id="chartPropCity" style="max-height: 300px;"></canvas>
        </div>
    </div>
</div>

<!-- PRICING BENCHMARK TABLE -->
<div class="bg-white border rounded-3 p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h6 class="text-secondary text-uppercase small mb-0">
            Pricing benchmark by city + type
        </h6>
        <a href="?export=pricing" class="btn btn-sm btn-outline-dark">
            <i class="bi bi-download me-1"></i> Export CSV
        </a>
    </div>
    <p class="text-secondary small mb-3">
        Aggregated from <?= count($priceTable) ?> active/rented listing groups.
        Useful as a market benchmark for new landlords pricing their properties.
    </p>
    <?php if (empty($priceTable)): ?>
        <p class="text-secondary mb-0">No data yet.</p>
    <?php else: ?>
        <table class="table mb-0 align-middle">
            <thead style="background:#F4F4EE;">
                <tr>
                    <th class="ps-3">City</th>
                    <th>Type</th>
                    <th>Listings</th>
                    <th>Avg rent</th>
                    <th>Min</th>
                    <th>Max</th>
                    <th>Range</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($priceTable as $row): ?>
                    <tr>
                        <td class="ps-3"><strong><?= e($row['city']) ?></strong></td>
                        <td><span class="badge bg-light text-dark"><?= e(ucfirst(str_replace('_',' ', $row['property_type']))) ?></span></td>
                        <td><?= (int)$row['listing_count'] ?></td>
                        <td><strong class="text-emerald">RM <?= number_format((float)$row['avg_rent']) ?></strong></td>
                        <td><span class="small text-secondary">RM <?= number_format((float)$row['min_rent']) ?></span></td>
                        <td><span class="small text-secondary">RM <?= number_format((float)$row['max_rent']) ?></span></td>
                        <td>
                            <span class="small text-secondary">
                                ±RM <?= number_format(((float)$row['max_rent'] - (float)$row['min_rent']) / 2) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
// Color palette
const colors = {
    emerald: '#2E8B57',
    navy: '#0F2C52',
    gold: '#D4A017',
    danger: '#dc3545',
    info: '#0dcaf0',
    purple: '#6f42c1',
    pink: '#d63384',
    grey: '#6c757d'
};

// === USER GROWTH ===
new Chart(document.getElementById('chartUserGrowth'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($userGrowth, 'month')) ?>,
        datasets: [
            {
                label: 'Students',
                data: <?= json_encode(array_map('intval', array_column($userGrowth, 'students'))) ?>,
                borderColor: colors.emerald,
                backgroundColor: 'rgba(46, 139, 87, 0.1)',
                tension: 0.3,
                fill: true
            },
            {
                label: 'Landlords',
                data: <?= json_encode(array_map('intval', array_column($userGrowth, 'landlords'))) ?>,
                borderColor: colors.navy,
                backgroundColor: 'rgba(15, 44, 82, 0.1)',
                tension: 0.3,
                fill: true
            },
            {
                label: 'Agents',
                data: <?= json_encode(array_map('intval', array_column($userGrowth, 'agents'))) ?>,
                borderColor: colors.gold,
                backgroundColor: 'rgba(212, 160, 23, 0.1)',
                tension: 0.3,
                fill: true
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});

// === PROPERTY STATUS DONUT ===
const propStatusData = <?= json_encode($propStatus) ?>;
const statusLabels = {
    'pending_approval': 'Pending',
    'available': 'Available',
    'booked': 'Booked',
    'rented': 'Rented',
    'hidden': 'Hidden',
    'rejected': 'Rejected'
};
const statusColors = {
    'pending_approval': colors.gold,
    'available': colors.emerald,
    'booked': colors.info,
    'rented': colors.navy,
    'hidden': colors.grey,
    'rejected': colors.danger
};

new Chart(document.getElementById('chartPropStatus'), {
    type: 'doughnut',
    data: {
        labels: propStatusData.map(r => statusLabels[r.status] || r.status),
        datasets: [{
            data: propStatusData.map(r => parseInt(r.cnt)),
            backgroundColor: propStatusData.map(r => statusColors[r.status] || colors.grey),
            borderWidth: 2,
            borderColor: 'white'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } }
    }
});

// === TENANCY TREND ===
new Chart(document.getElementById('chartTenancyTrend'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($tenancyTrend, 'month')) ?>,
        datasets: [
            {
                label: 'Total created',
                data: <?= json_encode(array_map('intval', array_column($tenancyTrend, 'total'))) ?>,
                borderColor: colors.navy,
                backgroundColor: 'rgba(15, 44, 82, 0.1)',
                tension: 0.3,
                fill: true
            },
            {
                label: 'Active',
                data: <?= json_encode(array_map('intval', array_column($tenancyTrend, 'active'))) ?>,
                borderColor: colors.emerald,
                tension: 0.3,
                fill: false
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});

// === PROPERTIES PER CITY ===
new Chart(document.getElementById('chartPropCity'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($propByCity, 'city')) ?>,
        datasets: [{
            label: 'Listings',
            data: <?= json_encode(array_map('intval', array_column($propByCity, 'cnt'))) ?>,
            backgroundColor: colors.emerald,
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        plugins: { legend: { display: false } },
        scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});
</script>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../../includes/admin_layout.php';