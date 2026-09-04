<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/co_tenants.php';

$pageTitle     = 'Verify Contract';
$activeNav     = '';
$showPageTitle = false;

$ref = trim($_GET['ref'] ?? '');
$contract = null;
$tenantCount = 0;
$searched = $ref !== '';

if ($searched) {
    $pdo = db();
    // Deliberately no tenant/landlord name or unit-level address here — a
    // contract_code is a sequential, guessable ID (RB-YYYY-NNNNN), so this
    // endpoint is reachable by anyone without proving they hold the document.
    // Showing "who lives where" would let it be scraped as a PII directory.
    $stmt = $pdo->prepare("
        SELECT c.contract_code, c.tenancy_id, c.start_date, c.end_date, c.monthly_rent,
               c.status, c.generated_at,
               p.city AS property_city, p.property_type
          FROM contracts c
          JOIN properties p ON p.id = c.property_id
         WHERE c.contract_code = ?
         LIMIT 1
    ");
    $stmt->execute([strtoupper($ref)]);
    $contract = $stmt->fetch();

    if ($contract) {
        $tenantCount = count(get_co_tenants((int)$contract['tenancy_id'])) ?: 1;
    }
}

$statusLabels = [
    'pending_signatures' => ['Pending signatures', 'warning'],
    'active'             => ['Active', 'success'],
    'completed'          => ['Completed', 'secondary'],
    'terminated'         => ['Terminated', 'danger'],
];

ob_start();
?>

<div class="row justify-content-center">
    <div class="col-lg-7">

        <div class="text-center mb-4">
            <i class="bi bi-patch-check-fill text-primary" style="font-size: 2.5rem;"></i>
            <h3 class="mt-2 mb-1">Verify a Contract</h3>
            <p class="text-secondary">
                Enter a contract reference code to confirm it was issued by RentBridge.
            </p>
        </div>

        <form method="get" action="/rentbridge/verify.php" class="card border-0 shadow-sm mb-4">
            <div class="card-body p-4">
                <label for="ref" class="form-label small text-secondary">Contract reference code</label>
                <div class="input-group">
                    <input type="text" class="form-control" id="ref" name="ref"
                           placeholder="e.g. RB-2026-00012" value="<?= e($ref) ?>" autofocus>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-search"></i> Verify
                    </button>
                </div>
            </div>
        </form>

        <?php if ($searched): ?>
            <?php if (!$contract): ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4 text-center">
                        <i class="bi bi-x-circle text-danger" style="font-size: 2rem;"></i>
                        <h5 class="mt-2">Contract not found</h5>
                        <p class="text-secondary mb-0">
                            No contract matches reference <strong><?= e($ref) ?></strong>.
                            Double-check the code and try again — it's printed at the top of every
                            RentBridge tenancy agreement (e.g. RB-2026-00012).
                        </p>
                    </div>
                </div>
            <?php else: ?>
                <?php
                    [$statusLabel, $statusColor] = $statusLabels[$contract['status']] ?? [ucfirst($contract['status']), 'secondary'];
                ?>
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <div class="text-secondary small">Contract reference</div>
                                <div class="fs-5 fw-semibold"><?= e($contract['contract_code']) ?></div>
                            </div>
                            <span class="badge bg-<?= e($statusColor) ?>"><?= e($statusLabel) ?></span>
                        </div>

                        <div class="alert alert-success d-flex align-items-center gap-2 mb-4">
                            <i class="bi bi-shield-check fs-5"></i>
                            <div>This is a genuine contract issued through RentBridge.</div>
                        </div>

                        <dl class="row mb-0">
                            <dt class="col-sm-4 text-secondary">Property type</dt>
                            <dd class="col-sm-8">
                                <?= e(ucfirst(str_replace('_', ' ', $contract['property_type']))) ?>
                                in <?= e($contract['property_city']) ?>
                            </dd>

                            <dt class="col-sm-4 text-secondary">Tenancy period</dt>
                            <dd class="col-sm-8">
                                <?= e(date('d M Y', strtotime($contract['start_date']))) ?>
                                &ndash;
                                <?= e(date('d M Y', strtotime($contract['end_date']))) ?>
                            </dd>

                            <dt class="col-sm-4 text-secondary">Monthly rent</dt>
                            <dd class="col-sm-8">RM <?= number_format((float)$contract['monthly_rent'], 2) ?></dd>

                            <dt class="col-sm-4 text-secondary">Tenant(s)</dt>
                            <dd class="col-sm-8"><?= (int)$tenantCount ?> on this contract</dd>

                            <?php if ($contract['generated_at']): ?>
                                <dt class="col-sm-4 text-secondary">Issued</dt>
                                <dd class="col-sm-8"><?= e(date('d M Y', strtotime($contract['generated_at']))) ?></dd>
                            <?php endif; ?>
                        </dl>
                    </div>
                </div>
                <p class="text-secondary small text-center mt-3 mb-0">
                    No names, addresses, IC numbers, phone numbers, or signatures are ever
                    shown here — this only confirms a contract with this reference exists.
                </p>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</div>

<?php
$pageContent = ob_get_clean();
if (is_logged_in()) {
    $role = current_role();
    $layoutFile = match($role) {
        'student'  => 'student_layout.php',
        'landlord' => 'landlord_layout.php',
        'agent'    => 'agent_layout.php',
        'admin'    => 'admin_layout.php',
        default    => 'public_layout.php',
    };
    require __DIR__ . '/includes/' . $layoutFile;
} else {
    require __DIR__ . '/includes/public_layout.php';
}
