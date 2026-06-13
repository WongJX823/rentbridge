<?php
require_once __DIR__ . '/../../includes/auth.php';
require_role('admin');

$pdo = db();

// === CSV EXPORT ===
if (($_GET['export'] ?? '') === 'commissions') {
    $stmt = $pdo->query("
        SELECT ac.id, c.contract_code, p.title AS property_title, p.city,
               a.full_name AS agent_name, a.staff_id,
               ac.base_rent, ac.commission_amt, ac.sst_amt, ac.total_payable,
               ac.status, ac.earned_at, ac.released_at, ac.paid_at
          FROM agent_commissions ac
          JOIN contracts c  ON c.id = ac.contract_id
          JOIN properties p ON p.id = c.property_id
          JOIN agents a     ON a.user_id = ac.agent_id
         ORDER BY ac.earned_at DESC, ac.id DESC
    ");
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="commissions_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID','Contract','Property','City','Agent','Staff ID','Base Rent','Commission','SST','Total','Status','Earned','Released','Paid']);
    foreach ($stmt->fetchAll() as $r) {
        fputcsv($out, [
            $r['id'], $r['contract_code'],
            $r['property_title'], $r['city'],
            $r['agent_name'], $r['staff_id'],
            $r['base_rent'], $r['commission_amt'], $r['sst_amt'], $r['total_payable'],
            ucfirst($r['status']),
            $r['earned_at'] ? date('Y-m-d', strtotime($r['earned_at'])) : '',
            $r['released_at'] ? date('Y-m-d', strtotime($r['released_at'])) : '',
            $r['paid_at'] ? date('Y-m-d', strtotime($r['paid_at'])) : '',
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
$totalEarned = (float)$pdo->query("
    SELECT COALESCE(SUM(total_payable),0) FROM agent_commissions
     WHERE status IN ('earned','released','paid')
")->fetchColumn();
$totalPaid = (float)$pdo->query("
    SELECT COALESCE(SUM(total_payable),0) FROM agent_commissions
     WHERE status = 'paid'
")->fetchColumn();
$pendingPayout = (float)$pdo->query("
    SELECT COALESCE(SUM(total_payable),0) FROM agent_commissions
     WHERE status IN ('earned','released')
")->fetchColumn();
$totalSst = (float)$pdo->query("
    SELECT COALESCE(SUM(sst_amt),0) FROM agent_commissions
     WHERE status IN ('earned','released','paid')
")->fetchColumn();
$contractCount = (int)$pdo->query("SELECT COUNT(*) FROM agent_commissions")->fetchColumn();
$avgCommission = $contractCount > 0
    ? (float)$pdo->query("SELECT AVG(total_payable) FROM agent_commissions WHERE status != 'pending'")->fetchColumn()
    : 0;

// Projected revenue (active contracts × monthly rent ÷ commission factor)
// Each contract = 1 month rent commission, so it's already captured in earned/paid.
// Projection is "what would I earn if all CURRENTLY pending contracts close successfully?"
$projectedRevenue = (float)$pdo->query("
    SELECT COALESCE(SUM(total_payable),0) FROM agent_commissions
     WHERE status = 'pending'
")->fetchColumn();

// === CHART: Revenue over time (line) ===
$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(earned_at, '%Y-%m') AS month,
           COALESCE(SUM(commission_amt),0) AS commission_total,
           COALESCE(SUM(sst_amt),0) AS sst_total,
           COALESCE(SUM(total_payable),0) AS grand_total,
           COUNT(*) AS contract_count
      FROM agent_commissions
     WHERE earned_at IS NOT NULL
       AND earned_at >= ?
     GROUP BY month
     ORDER BY month ASC
");
$stmt->execute([$rangeDate]);
$revenueTrend = $stmt->fetchAll();

// === CHART: Status breakdown ===
$stmt = $pdo->query("
    SELECT status,
           COUNT(*) AS cnt,
           COALESCE(SUM(total_payable),0) AS amount
      FROM agent_commissions
     GROUP BY status
");
$statusBreakdown = $stmt->fetchAll();

// === LEADERBOARD: Agent earnings ===
$stmt = $pdo->query("
    SELECT a.full_name AS agent_name,
           a.staff_id, a.department,
           COUNT(ac.id) AS contracts_closed,
           COALESCE(SUM(ac.total_payable),0) AS total_earned,
           COALESCE(SUM(CASE WHEN ac.status = 'paid' THEN ac.total_payable ELSE 0 END),0) AS total_paid,
           COALESCE(SUM(CASE WHEN ac.status IN ('earned','released') THEN ac.total_payable ELSE 0 END),0) AS pending_payout
      FROM agents a
      LEFT JOIN agent_commissions ac ON ac.agent_id = a.user_id
                                     AND ac.status IN ('earned','released','paid')
     GROUP BY a.user_id, a.full_name, a.staff_id, a.department
     ORDER BY total_earned DESC
");
$agentLeaderboard = $stmt->fetchAll();

// === RECENT COMMISSIONS ===
$stmt = $pdo->query("
    SELECT ac.*, c.contract_code, p.title AS property_title,
           a.full_name AS agent_name
      FROM agent_commissions ac
      JOIN contracts c  ON c.id = ac.contract_id
      JOIN properties p ON p.id = c.property_id
      JOIN agents a     ON a.user_id = ac.agent_id
     ORDER BY ac.earned_at DESC, ac.id DESC
     LIMIT 10
");
$recentCommissions = $stmt->fetchAll();

$pageTitle = 'Statistics — Financial';
$activeNav = 'statistics';

$pageTabs = [
    ['label' => 'Summary',     'href' => '/rentbridge/admin/statistics/summary.php',    'active' => false],
    ['label' => 'Users',       'href' => '/rentbridge/admin/statistics/users.php',     'active' => false],
    ['label' => 'Properties',  'href' => '/rentbridge/admin/statistics/properties.php','active' => false],
    ['label' => 'Tenancies',   'href' => '/rentbridge/admin/statistics/tenancies.php', 'active' => false],
    ['label' => 'Financial',   'href' => '/rentbridge/admin/statistics/financial.php', 'active' => true],
];

ob_start();
?>

<!-- TOP BAR -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <p class="text-secondary mb-0">
        Commission revenue, agent earnings, and payout tracking.
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
        <div class="bg-white border rounded-3 p-3"
             style="background: linear-gradient(135deg, #fff 0%, #F4FBF7 100%);">
            <div style="width:36px; height:36px; background:#2E8B57; color:white;
                        border-radius:8px; display:flex; align-items:center; justify-content:center;">
                <i class="bi bi-cash-stack"></i>
            </div>
            <div style="font-family:'Fraunces',serif; font-size:1.4rem; font-weight:600; margin-top:8px; color:#2E8B57;">
                RM<?= number_format($totalEarned, 0) ?>
            </div>
            <div class="small text-secondary">Total earned</div>
        </div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="bg-white border rounded-3 p-3">
            <div style="width:36px; height:36px; background:#E4F2EA; color:#2E8B57;
                        border-radius:8px; display:flex; align-items:center; justify-content:center;">
                <i class="bi bi-check2-circle"></i>
            </div>
            <div style="font-family:'Fraunces',serif; font-size:1.4rem; font-weight:600; margin-top:8px;">
                RM<?= number_format($totalPaid, 0) ?>
            </div>
            <div class="small text-secondary">Paid out</div>
        </div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="bg-white border rounded-3 p-3">
            <div style="width:36px; height:36px; background:#FFF4D6; color:#D4A017;
                        border-radius:8px; display:flex; align-items:center; justify-content:center;">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div style="font-family:'Fraunces',serif; font-size:1.4rem; font-weight:600; margin-top:8px;">
                RM<?= number_format($pendingPayout, 0) ?>
            </div>
            <div class="small text-secondary">Pending payout</div>
        </div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="bg-white border rounded-3 p-3">
            <div style="width:36px; height:36px; background:#E6ECF4; color:#0F2C52;
                        border-radius:8px; display:flex; align-items:center; justify-content:center;">
                <i class="bi bi-receipt"></i>
            </div>
            <div style="font-family:'Fraunces',serif; font-size:1.4rem; font-weight:600; margin-top:8px;">
                RM<?= number_format($totalSst, 0) ?>
            </div>
            <div class="small text-secondary">SST (6%)</div>
        </div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="bg-white border rounded-3 p-3">
            <div style="width:36px; height:36px; background:#F4F4EE; color:#0F2C52;
                        border-radius:8px; display:flex; align-items:center; justify-content:center;">
                <i class="bi bi-graph-up"></i>
            </div>
            <div style="font-family:'Fraunces',serif; font-size:1.4rem; font-weight:600; margin-top:8px;">
                RM<?= number_format($avgCommission, 0) ?>
            </div>
            <div class="small text-secondary">Avg / contract</div>
        </div>
    </div>
    <div class="col-md-4 col-lg-2">
        <div class="bg-white border rounded-3 p-3">
            <div style="width:36px; height:36px; background:#F4F4EE; color:#6c757d;
                        border-radius:8px; display:flex; align-items:center; justify-content:center;">
                <i class="bi bi-clipboard-data"></i>
            </div>
            <div style="font-family:'Fraunces',serif; font-size:1.4rem; font-weight:600; margin-top:8px;">
                <?= $contractCount ?>
            </div>
            <div class="small text-secondary">Total contracts</div>
        </div>
    </div>
</div>

<!-- REVENUE TREND CHART -->
<div class="bg-white border rounded-3 p-4 mb-4">
    <h6 class="text-secondary text-uppercase small mb-3">Commission revenue over time</h6>
    <?php if (empty($revenueTrend)): ?>
        <p class="text-secondary mb-0">No commission data in this date range.</p>
    <?php else: ?>
        <canvas id="chartRevenue" style="max-height: 320px;"></canvas>
    <?php endif; ?>
</div>

<!-- STATUS + AGENT LEADERBOARD -->
<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="bg-white border rounded-3 p-4 h-100">
            <h6 class="text-secondary text-uppercase small mb-3">Commission lifecycle</h6>
            <?php if (empty($statusBreakdown)): ?>
                <p class="text-secondary small mb-0">No data yet.</p>
            <?php else: ?>
                <canvas id="chartStatus" style="max-height: 280px;"></canvas>
                <div class="mt-3">
                    <?php foreach ($statusBreakdown as $s): ?>
                        <div class="d-flex justify-content-between small py-1">
                            <span><?= e(ucfirst($s['status'])) ?></span>
                            <span class="text-secondary">
                                <?= (int)$s['cnt'] ?> ·
                                <strong>RM <?= number_format((float)$s['amount'], 0) ?></strong>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="bg-white border rounded-3 p-4 h-100">
            <h6 class="text-secondary text-uppercase small mb-3">
                🏆 Top agents by earnings
            </h6>
            <?php if (empty($agentLeaderboard)): ?>
                <p class="text-secondary small mb-0">No data yet.</p>
            <?php else: ?>
                <table class="table table-sm mb-0">
                    <thead style="background:#F4F4EE;">
                        <tr>
                            <th style="width:30px;"></th>
                            <th>Agent</th>
                            <th class="text-end">Contracts</th>
                            <th class="text-end">Total earned</th>
                            <th class="text-end">Paid</th>
                            <th class="text-end">Pending</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($agentLeaderboard as $i => $a): ?>
                            <tr>
                                <td class="text-secondary fw-bold">
                                    <?php if ($i === 0): ?>🥇
                                    <?php elseif ($i === 1): ?>🥈
                                    <?php elseif ($i === 2): ?>🥉
                                    <?php else: ?><?= $i + 1 ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <strong class="small"><?= e($a['agent_name']) ?></strong>
                                    <div class="small text-secondary">
                                        <code><?= e($a['staff_id']) ?></code>
                                        · <?= e($a['department']) ?>
                                    </div>
                                </td>
                                <td class="text-end"><?= (int)$a['contracts_closed'] ?></td>
                                <td class="text-end">
                                    <strong class="text-emerald">
                                        RM <?= number_format((float)$a['total_earned'], 0) ?>
                                    </strong>
                                </td>
                                <td class="text-end small text-secondary">
                                    RM <?= number_format((float)$a['total_paid'], 0) ?>
                                </td>
                                <td class="text-end small text-secondary">
                                    RM <?= number_format((float)$a['pending_payout'], 0) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- RECENT COMMISSIONS + EXPORT -->
<div class="bg-white border rounded-3 p-4 mb-4">
    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <h6 class="text-secondary text-uppercase small mb-0">
            Recent commissions
        </h6>
        <a href="?export=commissions" class="btn btn-sm btn-outline-dark">
            <i class="bi bi-download me-1"></i> Export all commissions CSV
        </a>
    </div>
    <?php if (empty($recentCommissions)): ?>
        <p class="text-secondary small mb-0">No commissions yet.</p>
    <?php else: ?>
        <table class="table mb-0 align-middle">
            <thead style="background:#F4F4EE;">
                <tr>
                    <th class="ps-3">Contract</th>
                    <th>Property</th>
                    <th>Agent</th>
                    <th class="text-end">Base rent</th>
                    <th class="text-end">Commission</th>
                    <th class="text-end">SST</th>
                    <th class="text-end">Total</th>
                    <th>Status</th>
                    <th>Earned</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentCommissions as $c):
                    $statusColor = match($c['status']) {
                        'pending'  => 'secondary',
                        'earned'   => 'warning',
                        'released' => 'info',
                        'paid'     => 'success',
                        default    => 'secondary',
                    };
                ?>
                    <tr>
                        <td class="ps-3"><code><?= e($c['contract_code']) ?></code></td>
                        <td class="small"><?= e($c['property_title']) ?></td>
                        <td class="small"><?= e($c['agent_name']) ?></td>
                        <td class="text-end">RM <?= number_format((float)$c['base_rent'], 0) ?></td>
                        <td class="text-end">RM <?= number_format((float)$c['commission_amt'], 0) ?></td>
                        <td class="text-end small text-secondary">RM <?= number_format((float)$c['sst_amt'], 0) ?></td>
                        <td class="text-end">
                            <strong class="text-emerald">
                                RM <?= number_format((float)$c['total_payable'], 0) ?>
                            </strong>
                        </td>
                        <td><span class="badge bg-<?= $statusColor ?>"><?= e(ucfirst($c['status'])) ?></span></td>
                        <td class="small text-secondary">
                            <?= $c['earned_at'] ? e(date('d M Y', strtotime($c['earned_at']))) : '—' ?>
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
const colors = {
    emerald: '#2E8B57',
    navy: '#0F2C52',
    gold: '#D4A017',
    danger: '#dc3545',
    info: '#0dcaf0',
    purple: '#6f42c1',
    grey: '#6c757d'
};

<?php if (!empty($revenueTrend)): ?>
// === REVENUE TREND ===
new Chart(document.getElementById('chartRevenue'), {
    type: 'line',
    data: {
        labels: <?= json_encode(array_column($revenueTrend, 'month')) ?>,
        datasets: [
            {
                label: 'Commission (excl. SST)',
                data: <?= json_encode(array_map('floatval', array_column($revenueTrend, 'commission_total'))) ?>,
                borderColor: colors.emerald,
                backgroundColor: 'rgba(46, 139, 87, 0.1)',
                tension: 0.3,
                fill: true,
                yAxisID: 'y'
            },
            {
                label: 'SST (6%)',
                data: <?= json_encode(array_map('floatval', array_column($revenueTrend, 'sst_total'))) ?>,
                borderColor: colors.gold,
                tension: 0.3,
                yAxisID: 'y'
            },
            {
                label: 'Contracts closed',
                data: <?= json_encode(array_map('intval', array_column($revenueTrend, 'contract_count'))) ?>,
                borderColor: colors.navy,
                borderDash: [5, 5],
                tension: 0.3,
                yAxisID: 'y1'
            }
        ]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' },
            tooltip: {
                callbacks: {
                    label: ctx => {
                        if (ctx.dataset.label === 'Contracts closed') return ctx.dataset.label + ': ' + ctx.raw;
                        return ctx.dataset.label + ': RM ' + parseFloat(ctx.raw).toLocaleString();
                    }
                }
            }
        },
        scales: {
            y: {
                beginAtZero: true,
                position: 'left',
                title: { display: true, text: 'RM' },
                ticks: { callback: v => 'RM ' + v }
            },
            y1: {
                beginAtZero: true,
                position: 'right',
                grid: { drawOnChartArea: false },
                title: { display: true, text: 'Contracts' },
                ticks: { stepSize: 1 }
            }
        }
    }
});
<?php endif; ?>

<?php if (!empty($statusBreakdown)): ?>
// === STATUS DOUGHNUT ===
const statusData = <?= json_encode($statusBreakdown) ?>;
const statusColors = {
    pending: colors.grey,
    earned: colors.gold,
    released: colors.info,
    paid: colors.emerald
};
new Chart(document.getElementById('chartStatus'), {
    type: 'doughnut',
    data: {
        labels: statusData.map(r => r.status.charAt(0).toUpperCase() + r.status.slice(1)),
        datasets: [{
            data: statusData.map(r => parseFloat(r.amount)),
            backgroundColor: statusData.map(r => statusColors[r.status] || colors.grey),
            borderWidth: 2,
            borderColor: 'white'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: 'bottom' },
            tooltip: {
                callbacks: {
                    label: ctx => ctx.label + ': RM ' + parseFloat(ctx.raw).toLocaleString()
                }
            }
        }
    }
});
<?php endif; ?>
</script>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../../includes/admin_layout.php';