<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('agent');

$pdo = db();
$userId = current_user_id();

// Earnings list
$stmt = $pdo->prepare("
    SELECT ac.*, c.contract_code, p.title AS property_title
      FROM agent_commissions ac
      JOIN contracts c ON c.id = ac.contract_id
      JOIN properties p ON p.id = c.property_id
     WHERE ac.agent_id = ?
     ORDER BY ac.earned_at DESC, ac.id DESC
");
$stmt->execute([$userId]);
$commissions = $stmt->fetchAll();

$totals = [
    'pending'  => 0.0,
    'earned'   => 0.0,
    'released' => 0.0,
    'paid'     => 0.0,
];
$totalBaseRent = 0.0;
foreach ($commissions as $c) {
    $totals[$c['status']] += (float)$c['total_payable'];
    if (in_array($c['status'], ['earned','released','paid'])) {
        $totalBaseRent += (float)$c['base_rent'];
    }
}

// 70/30 split: agent receives 30% of base rent per contract
$agentShare   = $totalBaseRent * 0.30;
$utemShare    = $totalBaseRent * 0.70;

$pageTitle = 'Earnings';
$activeNav = 'earnings';

ob_start();
?>

<!-- Commission split info banner -->
<div class="alert border mb-4 d-flex gap-3 align-items-start"
     style="background:#F4F8FF; border-color:#0F2C52;">
    <i class="bi bi-info-circle text-secondary fs-4"></i>
    <div class="small">
        <strong>Commission structure:</strong> Each successful contract earns 1&times; base rent as commission.
        <strong>30%</strong> goes to you (agent) and <strong>70%</strong> is remitted to UTeM.
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="bg-white border rounded-3 p-3">
            <div class="small text-secondary">Pending</div>
            <div style="font-family:'Fraunces',serif; font-size:1.4rem; font-weight:600; color:#7C5E0A;">
                RM <?= number_format($totals['pending'], 2) ?>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="bg-white border rounded-3 p-3">
            <div class="small text-secondary">Earned (30% share)</div>
            <div style="font-family:'Fraunces',serif; font-size:1.4rem; font-weight:600; color:#0F2C52;">
                RM <?= number_format($agentShare, 2) ?>
            </div>
            <div class="small text-secondary mt-1">From RM <?= number_format($totalBaseRent, 2) ?> total commission</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="bg-white border rounded-3 p-3">
            <div class="small text-secondary">UTeM share (70%)</div>
            <div style="font-family:'Fraunces',serif; font-size:1.4rem; font-weight:600; color:#6c757d;">
                RM <?= number_format($utemShare, 2) ?>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="bg-white border rounded-3 p-3" style="background:linear-gradient(135deg,#fff,#F4FBF7);">
            <div class="small text-secondary">Paid out</div>
            <div style="font-family:'Fraunces',serif; font-size:1.4rem; font-weight:600; color:#2E8B57;">
                RM <?= number_format($totals['paid'], 2) ?>
            </div>
        </div>
    </div>
</div>

<?php if (empty($commissions)): ?>
    <div class="text-center py-5 bg-white rounded-3 border">
        <i class="bi bi-cash-stack" style="font-size:3rem;color:rgba(15,44,82,0.15);"></i>
        <h4 class="mt-3">No commissions yet</h4>
        <p class="text-secondary small">Complete your first inspection + contract to earn commission.</p>
    </div>
<?php else: ?>
    <div class="bg-white border rounded-3 overflow-hidden">
        <table class="table mb-0 align-middle">
            <thead style="background:#F4F4EE;">
                <tr>
                    <th class="ps-3">Contract</th>
                    <th>Property</th>
                    <th>Base rent</th>
                    <th>Commission</th>
                    <th>Your share (30%)</th>
                    <th>UTeM (70%)</th>
                    <th>Status</th>
                    <th>Earned</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($commissions as $c):
                    $base = (float)$c['commission_amt'];
                    $myShare   = $base * 0.30;
                    $utemPart  = $base * 0.70;
                ?>
                    <tr>
                        <td class="ps-3"><code><?= e($c['contract_code']) ?></code></td>
                        <td class="small"><?= e($c['property_title']) ?></td>
                        <td>RM <?= number_format((float)$c['base_rent'], 2) ?></td>
                        <td class="small text-secondary">RM <?= number_format($base, 2) ?></td>
                        <td><strong class="text-emerald">RM <?= number_format($myShare, 2) ?></strong></td>
                        <td class="small text-secondary">RM <?= number_format($utemPart, 2) ?></td>
                        <td>
                            <?php
                            $color = match($c['status']) {
                                'pending'=>'secondary','earned'=>'warning',
                                'released'=>'info','paid'=>'success',default=>'secondary'
                            };
                            ?>
                            <span class="badge bg-<?= $color ?>"><?= e(ucfirst($c['status'])) ?></span>
                        </td>
                        <td class="small text-secondary">
                            <?= $c['earned_at'] ? e(date('d M Y', strtotime($c['earned_at']))) : '—' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/agent_layout.php';