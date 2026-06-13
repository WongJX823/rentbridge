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
foreach ($commissions as $c) {
    $totals[$c['status']] += (float)$c['total_payable'];
}

$pageTitle = 'Earnings';
$activeNav = 'earnings';

ob_start();
?>

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
            <div class="small text-secondary">Earned</div>
            <div style="font-family:'Fraunces',serif; font-size:1.4rem; font-weight:600; color:#0F2C52;">
                RM <?= number_format($totals['earned'], 2) ?>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="bg-white border rounded-3 p-3">
            <div class="small text-secondary">Released</div>
            <div style="font-family:'Fraunces',serif; font-size:1.4rem; font-weight:600; color:#0F2C52;">
                RM <?= number_format($totals['released'], 2) ?>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="bg-white border rounded-3 p-3" style="background:linear-gradient(135deg,#fff,#F4FBF7);">
            <div class="small text-secondary">Paid</div>
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
                    <th>SST</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th>Earned</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($commissions as $c): ?>
                    <tr>
                        <td class="ps-3"><code><?= e($c['contract_code']) ?></code></td>
                        <td class="small"><?= e($c['property_title']) ?></td>
                        <td>RM <?= number_format((float)$c['base_rent'], 2) ?></td>
                        <td>RM <?= number_format((float)$c['commission_amt'], 2) ?></td>
                        <td>RM <?= number_format((float)$c['sst_amt'], 2) ?></td>
                        <td><strong class="text-emerald">RM <?= number_format((float)$c['total_payable'], 2) ?></strong></td>
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