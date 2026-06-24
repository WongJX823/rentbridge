<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

$pdo = db();

// === CSV EXPORT ===
if (($_GET['export'] ?? '') === 'tenancies') {
    $stmt = $pdo->query("
        SELECT b.id, b.status, b.created_at, b.start_date, b.end_date,
               b.monthly_rent, b.deposit,
               p.title AS property_title, p.city,
               s.full_name AS student_name, s.matric_no,
               l.full_name AS landlord_name,
               a.full_name AS agent_name
          FROM tenancies b
          JOIN properties p ON p.id = b.property_id
          JOIN students s ON s.user_id = b.student_id
          JOIN landlords l ON l.user_id = b.landlord_id
          LEFT JOIN agents a ON a.user_id = b.agent_id
         ORDER BY b.created_at DESC
    ");
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="tenancies_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Status','Created','Start','End','Rent (RM)','Deposit (RM)','Property','City','Student','Matric','Landlord','Agent']);
    foreach ($stmt->fetchAll() as $r) {
        fputcsv($out, [
            $r['id'],
            ucfirst(str_replace('_',' ', $r['status'])),
            date('d M Y', strtotime($r['created_at'])),
            $r['start_date'], $r['end_date'],
            $r['monthly_rent'], $r['deposit'],
            $r['property_title'], $r['city'],
            $r['student_name'], $r['matric_no'],
            $r['landlord_name'], $r['agent_name'] ?: '—'
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
$total       = (int)$pdo->query("SELECT COUNT(*) FROM tenancies")->fetchColumn();
$active      = (int)$pdo->query("SELECT COUNT(*) FROM tenancies WHERE status='active'")->fetchColumn();
$pending     = (int)$pdo->query("SELECT COUNT(*) FROM tenancies WHERE status IN ('pending_landlord','pending_agent','agent_assigned','agent_verifying','contract_pending')")->fetchColumn();
$completed   = (int)$pdo->query("SELECT COUNT(*) FROM tenancies WHERE status='completed'")->fetchColumn();
$cancelled   = (int)$pdo->query("SELECT COUNT(*) FROM tenancies WHERE status IN ('cancelled_by_student','cancelled_by_landlord','cancelled_by_admin','rejected_by_landlord','verification_failed')")->fetchColumn();
$totalValue  = (float)$pdo->query("SELECT COALESCE(SUM(monthly_rent),0) FROM tenancies WHERE status='active'")->fetchColumn();

// === FUNNEL: status counts (for conversion chart) ===
$stmt = $pdo->query("
    SELECT
       SUM(CASE WHEN status NOT IN ('rejected_by_landlord') THEN 1 ELSE 0 END) AS submitted,
       SUM(CASE WHEN status NOT IN ('pending_landlord','rejected_by_landlord') THEN 1 ELSE 0 END) AS landlord_approved,
       SUM(CASE WHEN status NOT IN ('pending_landlord','rejected_by_landlord','pending_agent','agent_assigned') THEN 1 ELSE 0 END) AS agent_engaged,
       SUM(CASE WHEN status NOT IN ('pending_landlord','rejected_by_landlord','pending_agent','agent_assigned','agent_verifying','verification_failed','cancelled_by_student','cancelled_by_landlord','cancelled_by_admin') THEN 1 ELSE 0 END) AS verified_or_beyond,
       SUM(CASE WHEN status IN ('active','completed','contract_pending') THEN 1 ELSE 0 END) AS contract_stage,
       SUM(CASE WHEN status IN ('active','completed') THEN 1 ELSE 0 END) AS signed_active,
       SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed
     FROM tenancies
");
$funnel = $stmt->fetch();

// === CHART: Status distribution (pie) ===
$stmt = $pdo->query("
    SELECT status, COUNT(*) AS cnt
      FROM tenancies
     GROUP BY status
     ORDER BY cnt DESC
");
$statusDist = $stmt->fetchAll();

// === CHART: Tenancies per month + outcome breakdown ===
$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
           SUM(CASE WHEN status='active' THEN 1 ELSE 0 END) AS active,
           SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) AS completed,
           SUM(CASE WHEN status IN ('cancelled_by_student','cancelled_by_landlord','cancelled_by_admin','rejected_by_landlord','verification_failed') THEN 1 ELSE 0 END) AS cancelled,
           SUM(CASE WHEN status IN ('pending_landlord','pending_agent','agent_assigned','agent_verifying','contract_pending') THEN 1 ELSE 0 END) AS in_progress,
           COUNT(*) AS total
      FROM tenancies
     WHERE created_at >= ?
     GROUP BY month
     ORDER BY month ASC
");
$stmt->execute([$rangeDate]);
$monthlyTrend = $stmt->fetchAll();

// === CHART: Average tenancy duration ===
$stmt = $pdo->query("
    SELECT duration_type, COUNT(*) AS cnt
      FROM tenancies
     GROUP BY duration_type
");
$durationDist = $stmt->fetchAll();

// === CHART: Cancellation reasons (top reasons) ===
$stmt = $pdo->query("
    SELECT status, COUNT(*) AS cnt
      FROM tenancies
     WHERE status IN ('cancelled_by_student','cancelled_by_landlord','cancelled_by_admin','rejected_by_landlord','verification_failed')
     GROUP BY status
");
$cancellationBreakdown = $stmt->fetchAll();

// === CONVERSION METRICS ===
$conversionData = [
    ['stage' => 'Submitted',         'count' => (int)$funnel['submitted']],
    ['stage' => 'Landlord approved', 'count' => (int)$funnel['landlord_approved']],
    ['stage' => 'Agent engaged',     'count' => (int)$funnel['agent_engaged']],
    ['stage' => 'Verified',          'count' => (int)$funnel['verified_or_beyond']],
    ['stage' => 'Contract stage',    'count' => (int)$funnel['contract_stage']],
    ['stage' => 'Signed active',     'count' => (int)$funnel['signed_active']],
    ['stage' => 'Completed',         'count' => (int)$funnel['completed']],
];
$overallConversion = $funnel['submitted'] > 0
    ? round(((int)$funnel['signed_active'] / (int)$funnel['submitted']) * 100, 1)
    : 0;

$pageTitle = 'Statistics — Tenancies';
$activeNav = 'statistics';

$pageTabs = [
    ['label' => 'Summary',     'href' => '/rentbridge/admin/statistics/summary.php',    'active' => false],
    ['label' => 'Users',       'href' => '/rentbridge/admin/statistics/users.php',     'active' => false],
    ['label' => 'Properties',  'href' => '/rentbridge/admin/statistics/properties.php','active' => false],
    ['label' => 'Tenancies',   'href' => '/rentbridge/admin/statistics/tenancies.php', 'active' => true],
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
        Tenancy conversion funnel and lifecycle analysis.
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
                <i class="bi bi-clipboard-data"></i>
            </div>
            <div style="font-family:'Fraunces',serif; font-size:1.5rem; font-weight:600; margin-top:8px;">
                <?= $total ?>
            </div>
            <div class="small text-secondary">Total tenancies</div>
        </div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="bg-white border rounded-3 p-3">
            <div style="width:36px; height:36px; background:#E4F2EA; color:#2E8B57;
                        border-radius:8px; display:flex; align-items:center; justify-content:center;">
                <i class="bi bi-check-circle"></i>
            </div>
            <div style="font-family:'Fraunces',serif; font-size:1.5rem; font-weight:600; margin-top:8px;">
                <?= $active ?>
            </div>
            <div class="small text-secondary">Currently active</div>
        </div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="bg-white border rounded-3 p-3">
            <div style="width:36px; height:36px; background:#FFF4D6; color:#D4A017;
                        border-radius:8px; display:flex; align-items:center; justify-content:center;">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div style="font-family:'Fraunces',serif; font-size:1.5rem; font-weight:600; margin-top:8px;">
                <?= $pending ?>
            </div>
            <div class="small text-secondary">In progress</div>
        </div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="bg-white border rounded-3 p-3">
            <div style="width:36px; height:36px; background:#E6ECF4; color:#0F2C52;
                        border-radius:8px; display:flex; align-items:center; justify-content:center;">
                <i class="bi bi-archive"></i>
            </div>
            <div style="font-family:'Fraunces',serif; font-size:1.5rem; font-weight:600; margin-top:8px;">
                <?= $completed ?>
            </div>
            <div class="small text-secondary">Completed</div>
        </div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="bg-white border rounded-3 p-3">
            <div style="width:36px; height:36px; background:#F8D7DA; color:#dc3545;
                        border-radius:8px; display:flex; align-items:center; justify-content:center;">
                <i class="bi bi-x-circle"></i>
            </div>
            <div style="font-family:'Fraunces',serif; font-size:1.5rem; font-weight:600; margin-top:8px;">
                <?= $cancelled ?>
            </div>
            <div class="small text-secondary">Cancelled/rejected</div>
        </div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="bg-white border rounded-3 p-3"
             style="background: linear-gradient(135deg, #fff 0%, #F4FBF7 100%);">
            <div style="width:36px; height:36px; background:#2E8B57; color:white;
                        border-radius:8px; display:flex; align-items:center; justify-content:center;">
                <i class="bi bi-bar-chart"></i>
            </div>
            <div style="font-family:'Fraunces',serif; font-size:1.5rem; font-weight:600; margin-top:8px; color:#2E8B57;">
                <?= $overallConversion ?>%
            </div>
            <div class="small text-secondary">Conversion rate</div>
        </div>
    </div>
</div>

<!-- CONVERSION FUNNEL -->
<div class="bg-white border rounded-3 p-4 mb-4">
    <h6 class="text-secondary text-uppercase small mb-3">
        Conversion funnel
        <small class="text-secondary fw-normal ms-2 text-lowercase">
            from application to active tenancy
        </small>
    </h6>
    <p class="text-secondary small mb-3">
        <?= (int)$funnel['signed_active'] ?> of <?= (int)$funnel['submitted'] ?> applications
        reached the signed/active stage — <strong><?= $overallConversion ?>% conversion</strong>.
    </p>
    <canvas id="chartFunnel" style="max-height: 320px;"></canvas>
</div>

<!-- CHARTS ROW 1 -->
<div class="row g-3 mb-4">
    <div class="col-lg-8">
        <div class="bg-white border rounded-3 p-4">
            <h6 class="text-secondary text-uppercase small mb-3">Tenancies created per month</h6>
            <canvas id="chartMonthly" style="max-height: 300px;"></canvas>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="bg-white border rounded-3 p-4 h-100">
            <h6 class="text-secondary text-uppercase small mb-3">Status breakdown</h6>
            <canvas id="chartStatusDist" style="max-height: 300px;"></canvas>
        </div>
    </div>
</div>

<!-- CHARTS ROW 2 -->
<div class="row g-3 mb-4">
    <div class="col-lg-6">
        <div class="bg-white border rounded-3 p-4 h-100">
            <h6 class="text-secondary text-uppercase small mb-3">Tenancy duration preference</h6>
            <canvas id="chartDuration" style="max-height: 280px;"></canvas>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="bg-white border rounded-3 p-4 h-100">
            <h6 class="text-secondary text-uppercase small mb-3">Cancellation breakdown</h6>
            <?php if (empty($cancellationBreakdown)): ?>
                <p class="text-secondary small mb-0 mt-4">
                    No cancellations yet — excellent!
                </p>
            <?php else: ?>
                <canvas id="chartCancellation" style="max-height: 280px;"></canvas>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ACTIVE VALUE + EXPORT -->
<div class="bg-white border rounded-3 p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h6 class="text-secondary text-uppercase small mb-2">Active tenancy value</h6>
            <div style="font-family:'Fraunces',serif; font-size:2rem; font-weight:600; color:#2E8B57;">
                RM <?= number_format($totalValue, 2) ?>
                <small class="text-secondary fs-6">/ month</small>
            </div>
            <small class="text-secondary">
                Sum of monthly rent across <?= $active ?> active tenancies.
                Annualized: RM <?= number_format($totalValue * 12, 2) ?>
            </small>
        </div>
        <a href="?export=tenancies" class="btn btn-outline-dark">
            <i class="bi bi-download me-1"></i> Export all tenancies CSV
        </a>
    </div>
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
    lightEmerald: 'rgba(46, 139, 87, 0.3)',
};

// === FUNNEL (horizontal bar) ===
const funnelData = <?= json_encode($conversionData) ?>;
const baseTotal = funnelData[0].count || 1;

new Chart(document.getElementById('chartFunnel'), {
    type: 'bar',
    data: {
        labels: funnelData.map(d => d.stage),
        datasets: [{
            label: 'Tenancies at this stage',
            data: funnelData.map(d => parseInt(d.count)),
            backgroundColor: funnelData.map((d, i) => {
                const intensity = 1 - (i * 0.1);
                return `rgba(46, 139, 87, ${Math.max(intensity, 0.4)})`;
            }),
            borderRadius: 6
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false },
            tooltip: {
                callbacks: {
                    label: ctx => {
                        const pct = ((ctx.raw / baseTotal) * 100).toFixed(1);
                        return ctx.raw + ' (' + pct + '% of submitted)';
                    }
                }
            }
        },
        scales: {
            x: { beginAtZero: true, ticks: { stepSize: 1 } }
        }
    }
});

// === MONTHLY TREND ===
new Chart(document.getElementById('chartMonthly'), {
    type: 'bar',
    data: {
        labels: <?= json_encode(array_column($monthlyTrend, 'month')) ?>,
        datasets: [
            {
                label: 'Active',
                data: <?= json_encode(array_map('intval', array_column($monthlyTrend, 'active'))) ?>,
                backgroundColor: colors.emerald,
                stack: 'stack1'
            },
            {
                label: 'Completed',
                data: <?= json_encode(array_map('intval', array_column($monthlyTrend, 'completed'))) ?>,
                backgroundColor: colors.navy,
                stack: 'stack1'
            },
            {
                label: 'In progress',
                data: <?= json_encode(array_map('intval', array_column($monthlyTrend, 'in_progress'))) ?>,
                backgroundColor: colors.gold,
                stack: 'stack1'
            },
            {
                label: 'Cancelled',
                data: <?= json_encode(array_map('intval', array_column($monthlyTrend, 'cancelled'))) ?>,
                backgroundColor: colors.danger,
                stack: 'stack1'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom' } },
        scales: {
            x: { stacked: true },
            y: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } }
        }
    }
});

// === STATUS DISTRIBUTION ===
const statusLabels = {
    'pending_landlord':      'Pending landlord',
    'rejected_by_landlord':  'Rejected by landlord',
    'pending_agent':         'Pending agent',
    'agent_assigned':        'Agent assigned',
    'agent_verifying':       'Inspecting',
    'agent_verified':        'Verified',
    'verification_failed':   'Verification failed',
    'contract_pending':      'Contract signing',
    'active':                'Active',
    'completed':             'Completed',
    'cancelled_by_student':  'Cancelled (student)',
    'cancelled_by_landlord': 'Cancelled (landlord)',
    'cancelled_by_admin':    'Cancelled (admin)',
};

const statusColorMap = {
    'pending_landlord':      colors.gold,
    'rejected_by_landlord':  colors.danger,
    'pending_agent':         colors.gold,
    'agent_assigned':        colors.info,
    'agent_verifying':       colors.info,
    'agent_verified':        colors.emerald,
    'verification_failed':   colors.danger,
    'contract_pending':      colors.purple,
    'active':                colors.emerald,
    'completed':             colors.navy,
    'cancelled_by_student':  colors.grey,
    'cancelled_by_landlord': colors.grey,
    'cancelled_by_admin':    colors.grey,
};

const statusDistData = <?= json_encode($statusDist) ?>;
new Chart(document.getElementById('chartStatusDist'), {
    type: 'doughnut',
    data: {
        labels: statusDistData.map(r => statusLabels[r.status] || r.status),
        datasets: [{
            data: statusDistData.map(r => parseInt(r.cnt)),
            backgroundColor: statusDistData.map(r => statusColorMap[r.status] || colors.grey),
            borderWidth: 2,
            borderColor: 'white'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { position: 'bottom', labels: { font: { size: 10 } } } }
    }
});

// === DURATION ===
const durationLabels = {
    '1_semester':  '1 semester',
    '2_semesters': '2 semesters',
    '1_year':      '1 year',
    'custom':      'Custom'
};
const durationData = <?= json_encode($durationDist) ?>;
new Chart(document.getElementById('chartDuration'), {
    type: 'pie',
    data: {
        labels: durationData.map(r => durationLabels[r.duration_type] || r.duration_type),
        datasets: [{
            data: durationData.map(r => parseInt(r.cnt)),
            backgroundColor: [colors.emerald, colors.gold, colors.navy, colors.purple],
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

// === CANCELLATION ===
<?php if (!empty($cancellationBreakdown)): ?>
const cancellationData = <?= json_encode($cancellationBreakdown) ?>;
new Chart(document.getElementById('chartCancellation'), {
    type: 'bar',
    data: {
        labels: cancellationData.map(r => statusLabels[r.status] || r.status),
        datasets: [{
            label: 'Count',
            data: cancellationData.map(r => parseInt(r.cnt)),
            backgroundColor: [colors.danger, colors.grey, colors.gold, colors.pink, colors.purple],
            borderRadius: 6
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { x: { beginAtZero: true, ticks: { stepSize: 1 } } }
    }
});
<?php endif; ?>
</script>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../../includes/admin_layout.php';