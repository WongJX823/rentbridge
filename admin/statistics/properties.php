<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

$pdo = db();

// === CSV EXPORTS ===
if (($_GET['export'] ?? '') === 'pricing_full') {
    $stmt = $pdo->query("
        SELECT city, property_type, furnishing,
               COUNT(*) AS listing_count,
               ROUND(AVG(monthly_rent), 0) AS avg_rent,
               MIN(monthly_rent) AS min_rent,
               MAX(monthly_rent) AS max_rent,
               ROUND(AVG(deposit), 0) AS avg_deposit
          FROM properties
         WHERE status IN ('available','booked','rented')
         GROUP BY city, property_type, furnishing
         ORDER BY city, property_type, furnishing
    ");
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="pricing_matrix_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['City', 'Type', 'Furnishing', 'Listings', 'Avg Rent', 'Min Rent', 'Max Rent', 'Avg Deposit']);
    foreach ($stmt->fetchAll() as $row) {
        fputcsv($out, [
            $row['city'],
            ucfirst(str_replace('_', ' ', $row['property_type'])),
            ucfirst($row['furnishing']),
            $row['listing_count'],
            $row['avg_rent'],
            $row['min_rent'],
            $row['max_rent'],
            $row['avg_deposit']
        ]);
    }
    fclose($out);
    exit;
}

if (($_GET['export'] ?? '') === 'all_properties') {
    $stmt = $pdo->query("
        SELECT p.id, p.title, p.property_type, p.city, p.state,
               p.monthly_rent, p.deposit, p.furnishing, p.status,
               p.created_at, l.full_name AS landlord_name
          FROM properties p
          JOIN landlords l ON l.user_id = p.landlord_id
         ORDER BY p.created_at DESC
    ");
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="all_properties_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Title','Type','City','State','Rent (RM)','Deposit (RM)','Furnishing','Status','Listed','Landlord']);
    foreach ($stmt->fetchAll() as $row) {
        fputcsv($out, [
            $row['id'], $row['title'],
            ucfirst(str_replace('_', ' ', $row['property_type'])),
            $row['city'], $row['state'],
            $row['monthly_rent'], $row['deposit'],
            ucfirst($row['furnishing']),
            ucfirst(str_replace('_', ' ', $row['status'])),
            date('d M Y', strtotime($row['created_at'])),
            $row['landlord_name']
        ]);
    }
    fclose($out);
    exit;
}

// === Date range ===
$range = $_GET['range'] ?? 'all';
$validRanges = ['7d','30d','90d','year','all'];
if (!in_array($range, $validRanges, true)) $range = 'all';
$rangeDate = match($range) {
    '7d'   => date('Y-m-d', strtotime('-7 days')),
    '30d'  => date('Y-m-d', strtotime('-30 days')),
    '90d'  => date('Y-m-d', strtotime('-90 days')),
    'year' => date('Y-01-01'),
    'all'  => '2000-01-01',
};

// === STAT CARDS ===
$totalProperties = (int)$pdo->query("SELECT COUNT(*) FROM properties")->fetchColumn();
$available       = (int)$pdo->query("SELECT COUNT(*) FROM properties WHERE status='available'")->fetchColumn();
$rented          = (int)$pdo->query("SELECT COUNT(*) FROM properties WHERE status='rented'")->fetchColumn();
$pending         = (int)$pdo->query("SELECT COUNT(*) FROM properties WHERE status='pending_approval'")->fetchColumn();
$avgRent         = (float)$pdo->query("SELECT AVG(monthly_rent) FROM properties WHERE status IN ('available','booked','rented')")->fetchColumn();
$verifiedCount   = (int)$pdo->query("SELECT COUNT(*) FROM properties WHERE agent_verified_at IS NOT NULL")->fetchColumn();
$verifiedPct     = $totalProperties > 0 ? round(($verifiedCount / $totalProperties) * 100) : 0;

// === CHART: Listings created per month ===
$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
           COUNT(*) AS total,
           SUM(CASE WHEN status='available'  THEN 1 ELSE 0 END) AS available_count,
           SUM(CASE WHEN status='rented'     THEN 1 ELSE 0 END) AS rented_count,
           SUM(CASE WHEN status='rejected'   THEN 1 ELSE 0 END) AS rejected_count
      FROM properties
     WHERE created_at >= ?
     GROUP BY month
     ORDER BY month ASC
");
$stmt->execute([$rangeDate]);
$listingTrend = $stmt->fetchAll();

// === CHART: Type distribution ===
$stmt = $pdo->query("
    SELECT property_type, COUNT(*) AS cnt
      FROM properties
     WHERE status IN ('available','booked','rented')
     GROUP BY property_type
");
$typeDist = $stmt->fetchAll();

// === CHART: Furnishing distribution ===
$stmt = $pdo->query("
    SELECT furnishing, COUNT(*) AS cnt
      FROM properties
     WHERE status IN ('available','booked','rented')
     GROUP BY furnishing
");
$furnishDist = $stmt->fetchAll();

// === CHART: Average rent by type ===
$stmt = $pdo->query("
    SELECT property_type,
           ROUND(AVG(monthly_rent), 0) AS avg_rent,
           ROUND(MIN(monthly_rent), 0) AS min_rent,
           ROUND(MAX(monthly_rent), 0) AS max_rent
      FROM properties
     WHERE status IN ('available','booked','rented')
     GROUP BY property_type
");
$rentByType = $stmt->fetchAll();

// === FULL PRICING MATRIX (city + type + furnishing) ===
$stmt = $pdo->query("
    SELECT city, property_type, furnishing,
           COUNT(*) AS listing_count,
           ROUND(AVG(monthly_rent), 0) AS avg_rent,
           MIN(monthly_rent) AS min_rent,
           MAX(monthly_rent) AS max_rent
      FROM properties
     WHERE status IN ('available','booked','rented')
     GROUP BY city, property_type, furnishing
     ORDER BY city, property_type, furnishing
");
$priceMatrix = $stmt->fetchAll();

// === MOST EXPENSIVE / AFFORDABLE LEADERBOARDS ===
$stmt = $pdo->query("
    SELECT p.id, p.title, p.city, p.property_type, p.monthly_rent
      FROM properties p
     WHERE p.status IN ('available','booked','rented')
     ORDER BY p.monthly_rent DESC
     LIMIT 5
");
$mostExpensive = $stmt->fetchAll();

$stmt = $pdo->query("
    SELECT p.id, p.title, p.city, p.property_type, p.monthly_rent
      FROM properties p
     WHERE p.status IN ('available','booked','rented')
     ORDER BY p.monthly_rent ASC
     LIMIT 5
");
$mostAffordable = $stmt->fetchAll();

// === PRICE DISTRIBUTION (histogram buckets) ===
$stmt = $pdo->query("
    SELECT
       CASE
         WHEN monthly_rent < 400 THEN '< RM 400'
         WHEN monthly_rent < 600 THEN 'RM 400-599'
         WHEN monthly_rent < 800 THEN 'RM 600-799'
         WHEN monthly_rent < 1000 THEN 'RM 800-999'
         WHEN monthly_rent < 1500 THEN 'RM 1000-1499'
         WHEN monthly_rent < 2000 THEN 'RM 1500-1999'
         ELSE 'RM 2000+'
       END AS bucket,
       COUNT(*) AS cnt
     FROM properties
    WHERE status IN ('available','booked','rented')
    GROUP BY bucket
    ORDER BY MIN(monthly_rent)
");
$priceDistribution = $stmt->fetchAll();

$pageTitle = 'Statistics — Properties';
$activeNav = 'statistics';

// Tabs at the top
$pageTabs = [
    ['label' => 'Summary',     'href' => '/rentbridge/admin/statistics/summary.php',    'active' => true],
    ['label' => 'Users',       'href' => '/rentbridge/admin/statistics/users.php',     'active' => false],
    ['label' => 'Properties',  'href' => '/rentbridge/admin/statistics/properties.php','active' => false],
    ['label' => 'Tenancies',   'href' => '/rentbridge/admin/statistics/tenancies.php', 'active' => false],
    ['label' => 'Financial',   'href' => '/rentbridge/admin/statistics/financial.php', 'active' => false],
];

ob_start();
?>

<style>
.stat-card-clickable {
    transition: transform 0.15s, box-shadow 0.15s, border-color 0.15s;
    cursor: pointer;
}
.stat-card-clickable:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(15, 44, 82, 0.1);
    border-color: rgba(46, 139, 87, 0.3) !important;
}
</style>

<!-- TOP BAR -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <p class="text-secondary mb-0">
        Property + pricing intelligence.
    </p>
    <div class="btn-group btn-group-sm">
        <?php foreach (['7d'=>'7d','30d'=>'30d','90d'=>'90d','year'=>'Year','all'=>'All'] as $r => $label): ?>
            <a href="?range=<?= e($r) ?>"
               class="btn <?= $range === $r ? 'btn-dark' : 'btn-outline-dark' ?>">
                <?= e($label) ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- STAT CARDS -->
<div class="row g-3 mb-4">
    <div class="col-md-4 col-lg-2">
        <div class="bg-white border rounded-3 p-3">
            <div style="width:36px; height:36px; background:#F4F4EE; color:#0F2C52;
                        border-radius:8px; display:flex; align-items:center; justify-content:center;">
                <i class="bi bi-buildings"></i>
            </div>
            <div style="font-family:'Fraunces',serif; font-size:1.5rem; font-weight:600; margin-top:8px;">
                <?= $totalProperties ?>
            </div>
            <div class="small text-secondary">Total listings</div>
        </div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="bg-white border rounded-3 p-3">
            <div style="width:36px; height:36px; background:#E4F2EA; color:#2E8B57;
                        border-radius:8px; display:flex; align-items:center; justify-content:center;">
                <i class="bi bi-check-circle"></i>
            </div>
            <div style="font-family:'Fraunces',serif; font-size:1.5rem; font-weight:600; margin-top:8px;">
                <?= $available ?>
            </div>
            <div class="small text-secondary">Available now</div>
        </div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="bg-white border rounded-3 p-3">
            <div style="width:36px; height:36px; background:#E6ECF4; color:#0F2C52;
                        border-radius:8px; display:flex; align-items:center; justify-content:center;">
                <i class="bi bi-key"></i>
            </div>
            <div style="font-family:'Fraunces',serif; font-size:1.5rem; font-weight:600; margin-top:8px;">
                <?= $rented ?>
            </div>
            <div class="small text-secondary">Currently rented</div>
        </div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="bg-white border rounded-3 p-3">
            <div style="width:36px; height:36px; background:#FFF4D6; color:#D4A017;
                        border-radius:8px; display:flex; align-items:center; justify-content:center;">
                <i class="bi bi-hourglass"></i>
            </div>
            <div style="font-family:'Fraunces',serif; font-size:1.5rem; font-weight:600; margin-top:8px;">
                <?= $pending ?>
            </div>
            <div class="small text-secondary">Pending review</div>
        </div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="bg-white border rounded-3 p-3">
            <div style="width:36px; height:36px; background:#E4F2EA; color:#2E8B57;
                        border-radius:8px; display:flex; align-items:center; justify-content:center;">
                <i class="bi bi-cash"></i>
            </div>
            <div style="font-family:'Fraunces',serif; font-size:1.5rem; font-weight:600; margin-top:8px;">
                RM<?= number_format($avgRent, 0) ?>
            </div>
            <div class="small text-secondary">Average rent</div>
        </div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="bg-white border rounded-3 p-3"
             style="background: linear-gradient(135deg, #fff 0%, #F4FBF7 100%);">
            <div style="width:36px; height:36px; background:#2E8B57; color:white;
                        border-radius:8px; display:flex; align-items:center; justify-content:center;">
                <i class="bi bi-patch-check-fill"></i>
            </div>
            <div style="font-family:'Fraunces',serif; font-size:1.5rem; font-weight:600; margin-top:8px; color:#2E8B57;">
                <?= $verifiedPct ?>%
            </div>
            <div class="small text-secondary">Agent-verified</div>
        </div>
    </div>
</div>

<!-- CHARTS ROW 1 -->
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="bg-white border rounded-3 p-4">
            <h6 class="text-secondary text-uppercase small mb-3">Listings created per month</h6>
            <canvas id="chartListingTrend" style="max-height: 300px;"></canvas>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="bg-white border rounded-3 p-4 h-100">
            <h6 class="text-secondary text-uppercase small mb-3">Property type mix</h6>
            <canvas id="chartTypeDist" style="max-height: 300px;"></canvas>
        </div>
    </div>
</div>

<!-- CHARTS ROW 2 -->
<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="bg-white border rounded-3 p-4 h-100">
            <h6 class="text-secondary text-uppercase small mb-3">Furnishing level</h6>
            <canvas id="chartFurnish" style="max-height: 280px;"></canvas>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="bg-white border rounded-3 p-4 h-100">
            <h6 class="text-secondary text-uppercase small mb-3">Average rent by type</h6>
            <canvas id="chartRentByType" style="max-height: 280px;"></canvas>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="bg-white border rounded-3 p-4 h-100">
            <h6 class="text-secondary text-uppercase small mb-3">Price distribution</h6>
            <canvas id="chartPriceDist" style="max-height: 280px;"></canvas>
        </div>
    </div>
</div>

<!-- LEADERBOARDS -->
<div class="row g-3 mb-4">
    <!-- Most expensive -->
    <div class="col-md-6">
        <div class="bg-white border rounded-3 p-4 h-100">
            <h6 class="text-secondary text-uppercase small mb-3">
                💎 Most expensive listings
            </h6>
            <?php if (empty($mostExpensive)): ?>
                <p class="text-secondary small mb-0">No data.</p>
            <?php else: ?>
                <table class="table table-sm mb-0">
                    <tbody>
                        <?php foreach ($mostExpensive as $i => $p): ?>
                            <tr>
                                <td style="width: 40px;" class="text-center text-secondary fw-bold">
                                    <?= $i + 1 ?>
                                </td>
                                <td>
                                    <a href="/rentbridge/admin/property.php?id=<?= (int)$p['id'] ?>"
                                       class="text-decoration-none text-dark">
                                        <strong class="small"><?= e($p['title']) ?></strong>
                                    </a>
                                    <div class="small text-secondary">
                                        <?= e($p['city']) ?>
                                        · <?= e(ucfirst(str_replace('_',' ',$p['property_type']))) ?>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <strong class="text-emerald">
                                        RM <?= number_format((float)$p['monthly_rent']) ?>
                                    </strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <!-- Most affordable -->
    <div class="col-md-6">
        <div class="bg-white border rounded-3 p-4 h-100">
            <h6 class="text-secondary text-uppercase small mb-3">
                💰 Most affordable listings
            </h6>
            <?php if (empty($mostAffordable)): ?>
                <p class="text-secondary small mb-0">No data.</p>
            <?php else: ?>
                <table class="table table-sm mb-0">
                    <tbody>
                        <?php foreach ($mostAffordable as $i => $p): ?>
                            <tr>
                                <td style="width: 40px;" class="text-center text-secondary fw-bold">
                                    <?= $i + 1 ?>
                                </td>
                                <td>
                                    <a href="/rentbridge/admin/property.php?id=<?= (int)$p['id'] ?>"
                                       class="text-decoration-none text-dark">
                                        <strong class="small"><?= e($p['title']) ?></strong>
                                    </a>
                                    <div class="small text-secondary">
                                        <?= e($p['city']) ?>
                                        · <?= e(ucfirst(str_replace('_',' ',$p['property_type']))) ?>
                                    </div>
                                </td>
                                <td class="text-end">
                                    <strong class="text-emerald">
                                        RM <?= number_format((float)$p['monthly_rent']) ?>
                                    </strong>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- FULL PRICING MATRIX -->
<div class="bg-white border rounded-3 p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <h6 class="text-secondary text-uppercase small mb-0">Full pricing matrix</h6>
            <small class="text-secondary">
                City × Type × Furnishing breakdown. Used for the future
                pricing-suggestion v2 feature.
            </small>
        </div>
        <div class="d-flex gap-2">
            <a href="?export=pricing_full" class="btn btn-sm btn-outline-dark">
                <i class="bi bi-download me-1"></i> Pricing CSV
            </a>
            <a href="?export=all_properties" class="btn btn-sm btn-outline-dark">
                <i class="bi bi-download me-1"></i> All listings CSV
            </a>
        </div>
    </div>

    <?php if (empty($priceMatrix)): ?>
        <p class="text-secondary mb-0">No active listings.</p>
    <?php else: ?>
        <table class="table mb-0 align-middle">
            <thead style="background:#F4F4EE;">
                <tr>
                    <th class="ps-3">City</th>
                    <th>Type</th>
                    <th>Furnishing</th>
                    <th>Count</th>
                    <th>Avg rent</th>
                    <th>Min</th>
                    <th>Max</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $prevCity = '';
                foreach ($priceMatrix as $row):
                    $cityChanged = $row['city'] !== $prevCity;
                    $prevCity = $row['city'];
                ?>
                    <tr <?= $cityChanged ? 'style="border-top: 2px solid rgba(15,44,82,0.08);"' : '' ?>>
                        <td class="ps-3">
                            <?= $cityChanged ? '<strong>' . e($row['city']) . '</strong>' : '' ?>
                        </td>
                        <td>
                            <span class="badge bg-light text-dark">
                                <?= e(ucfirst(str_replace('_',' ', $row['property_type']))) ?>
                            </span>
                        </td>
                        <td class="small">
                            <?= e(ucfirst($row['furnishing'])) ?>
                        </td>
                        <td><?= (int)$row['listing_count'] ?></td>
                        <td>
                            <strong class="text-emerald">
                                RM <?= number_format((float)$row['avg_rent']) ?>
                            </strong>
                        </td>
                        <td class="small text-secondary">RM <?= number_format((float)$row['min_rent']) ?></td>
                        <td class="small text-secondary">RM <?= number_format((float)$row['max_rent']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>
const colors = {
    emerald: '#2E8B57',
    navy: '#0F2C52',
    gold: '#D4A017',
    danger: '#dc3545',
    info: '#0dcaf0',
    purple: '#6f42c1',
    pink: '#d63384',
    grey: '#6c757d',
    cream: '#F4F4EE'
};

// === LISTING TREND ===
new Chart(document.getElementById('chartListingTrend'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($listingTrend, 'month')) ?>,
        datasets: [
            {
                label: 'Total listed',
                data: <?= json_encode(array_map('intval', array_column($listingTrend, 'total'))) ?>,
                borderColor: colors.navy,
                backgroundColor: 'rgba(15, 44, 82, 0.1)',
                tension: 0.3,
                fill: true
            },
            {
                label: 'Became available',
                data: <?= json_encode(array_map('intval', array_column($listingTrend, 'available_count'))) ?>,
                borderColor: colors.emerald,
                tension: 0.3
            },
            {
                label: 'Got rented',
                data: <?= json_encode(array_map('intval', array_column($listingTrend, 'rented_count'))) ?>,
                borderColor: colors.gold,
                tension: 0.3
            }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});

// === TYPE DISTRIBUTION ===
const typeLabels = { room: 'Room', studio: 'Studio', whole_unit: 'Whole unit' };
const typeColors = { room: colors.emerald, studio: colors.navy, whole_unit: colors.gold };
const typeData = <?= json_encode($typeDist) ?>;

new Chart(document.getElementById('chartTypeDist'), {
    type: 'pie',
    data: {
        labels: typeData.map(r => typeLabels[r.property_type] || r.property_type),
        datasets: [{
            data: typeData.map(r => parseInt(r.cnt)),
            backgroundColor: typeData.map(r => typeColors[r.property_type] || colors.grey),
            borderWidth: 2,
            borderColor: 'white'
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } }
    }
});

// === FURNISHING ===
const furnishLabels = { none: 'Unfurnished', partial: 'Partial', full: 'Fully furnished' };
const furnishColors = { none: colors.grey, partial: colors.gold, full: colors.emerald };
const furnishData = <?= json_encode($furnishDist) ?>;

new Chart(document.getElementById('chartFurnish'), {
    type: 'doughnut',
    data: {
        labels: furnishData.map(r => furnishLabels[r.furnishing] || r.furnishing),
        datasets: [{
            data: furnishData.map(r => parseInt(r.cnt)),
            backgroundColor: furnishData.map(r => furnishColors[r.furnishing] || colors.grey),
            borderWidth: 2,
            borderColor: 'white'
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } }
    }
});

// === AVG RENT BY TYPE (bar with range) ===
const rentTypeData = <?= json_encode($rentByType) ?>;
new Chart(document.getElementById('chartRentByType'), {
    type: 'bar',
    data: {
        labels: rentTypeData.map(r => typeLabels[r.property_type] || r.property_type),
        datasets: [
            {
                label: 'Min',
                data: rentTypeData.map(r => parseInt(r.min_rent)),
                backgroundColor: 'rgba(46, 139, 87, 0.3)',
                borderRadius: 4
            },
            {
                label: 'Avg',
                data: rentTypeData.map(r => parseInt(r.avg_rent)),
                backgroundColor: colors.emerald,
                borderRadius: 4
            },
            {
                label: 'Max',
                data: rentTypeData.map(r => parseInt(r.max_rent)),
                backgroundColor: colors.navy,
                borderRadius: 4
            }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' },
            tooltip: {
                callbacks: {
                    label: ctx => ctx.dataset.label + ': RM ' + ctx.raw.toLocaleString()
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: { callback: value => 'RM ' + value }
            }
        }
    }
});

// === PRICE DISTRIBUTION ===
const priceDistData = <?= json_encode($priceDistribution) ?>;
new Chart(document.getElementById('chartPriceDist'), {
    type: 'bar',
    data: {
        labels: priceDistData.map(r => r.bucket),
        datasets: [{
            label: 'Listings',
            data: priceDistData.map(r => parseInt(r.cnt)),
            backgroundColor: colors.purple,
            borderRadius: 4
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, ticks: { stepSize: 1 } },
            x: { ticks: { autoSkip: false, maxRotation: 45, minRotation: 45, font: { size: 10 } } }
        }
    }
});
</script>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../../includes/admin_layout.php';