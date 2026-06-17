<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/agent_assignment.php';
require_role('agent');

$pdo = db();
$userId = current_user_id();

// Lazy timeout check on dashboard load
check_and_reassign_timeouts();

// Fetch this agent's pending property reviews
$stmt = $pdo->prepare("
    SELECT p.id, p.title, p.city, p.monthly_rent, p.agent_assigned_at,
           l.full_name AS landlord_name,
           TIMESTAMPDIFF(HOUR, p.agent_assigned_at, NOW()) AS hours_pending
      FROM properties p
      JOIN landlords l ON l.user_id = p.landlord_id
     WHERE p.assigned_agent_id = ?
       AND p.agent_status = 'pending'
     ORDER BY p.agent_assigned_at ASC
");
$stmt->execute([$userId]);
$pendingReviews = $stmt->fetchAll();

// Bookings awaiting signed contract upload
$stmt = $pdo->prepare("
    SELECT b.id, b.property_id, b.created_at, b.start_date,
           p.title, p.city,
           s.full_name AS student_name
      FROM bookings b
      JOIN properties p ON p.id = b.property_id
      JOIN students s ON s.user_id = b.student_id
     WHERE b.agent_id = ?
       AND b.status = 'contract_pending'
     ORDER BY b.created_at DESC
");
$stmt->execute([$userId]);
$pendingUploads = $stmt->fetchAll();

// Counts for dashboard cards
$counts = [];
$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE agent_id = ? AND status = 'pending_agent'");
$stmt->execute([$userId]);
$counts['pending'] = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE agent_id = ? AND status = 'agent_verifying'");
$stmt->execute([$userId]);
$counts['verifying'] = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE agent_id = ? AND status IN ('contract_pending')");
$stmt->execute([$userId]);
$counts['contracts'] = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE agent_id = ? AND status = 'active'");
$stmt->execute([$userId]);
$counts['active'] = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE agent_id = ? AND status = 'completed'");
$stmt->execute([$userId]);
$counts['completed'] = (int)$stmt->fetchColumn();

// Total commission earned (lifetime)
$stmt = $pdo->prepare("
    SELECT COALESCE(SUM(total_payable), 0) FROM agent_commissions
     WHERE agent_id = ? AND status IN ('earned','released','paid')
");
$stmt->execute([$userId]);
$totalEarned = (float)$stmt->fetchColumn();

// Urgent cases (need action)
$stmt = $pdo->prepare("
    SELECT b.id, b.status, b.created_at,
           p.title AS property_title, p.city,
           s.full_name AS student_name,
           v.deadline_at, v.outcome AS verification_outcome
      FROM bookings b
      JOIN properties p ON p.id = b.property_id
      JOIN students s ON s.user_id = b.student_id
      LEFT JOIN agent_verifications v ON v.booking_id = b.id
     WHERE b.agent_id = ?
       AND b.status IN ('pending_agent','agent_verifying')
     ORDER BY b.created_at ASC
     LIMIT 5
");
$stmt->execute([$userId]);
$urgentCases = $stmt->fetchAll();

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';

ob_start();
?>

<p class="text-secondary mb-4">Overview of your cases and commissions.</p>

<!-- STAT CARDS -->
<div class="row g-3 mb-4">

    <div class="col-md-4 col-lg-3">
        <a href="/rentbridge/agent/cases.php?tab=pending"
           class="d-block bg-white border rounded-3 p-3 text-decoration-none text-dark h-100"
           style="transition: transform 0.15s, box-shadow 0.15s;"
           onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(15,44,82,0.08)'"
           onmouseout="this.style.transform='';this.style.boxShadow=''">
            <div style="width:40px; height:40px; background:#FFF4D6; color:#D4A017;
                        border-radius:10px; display:flex; align-items:center; justify-content:center;">
                <i class="bi bi-hourglass-split"></i>
            </div>
            <div style="font-family:'Fraunces',serif; font-size:1.8rem; font-weight:600; margin-top:8px;">
                <?= $counts['pending'] ?>
            </div>
            <div class="small text-secondary">Pending acceptance</div>
        </a>
    </div>

    <div class="col-md-4 col-lg-3">
        <a href="/rentbridge/agent/cases.php?tab=verifying"
           class="d-block bg-white border rounded-3 p-3 text-decoration-none text-dark h-100"
           style="transition: transform 0.15s, box-shadow 0.15s;"
           onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(15,44,82,0.08)'"
           onmouseout="this.style.transform='';this.style.boxShadow=''">
            <div style="width:40px; height:40px; background:#E6ECF4; color:#0F2C52;
                        border-radius:10px; display:flex; align-items:center; justify-content:center;">
                <i class="bi bi-search"></i>
            </div>
            <div style="font-family:'Fraunces',serif; font-size:1.8rem; font-weight:600; margin-top:8px;">
                <?= $counts['verifying'] ?>
            </div>
            <div class="small text-secondary">Inspections in progress</div>
        </a>
    </div>

    <div class="col-md-4 col-lg-3">
        <a href="/rentbridge/agent/contracts.php"
           class="d-block bg-white border rounded-3 p-3 text-decoration-none text-dark h-100"
           style="transition: transform 0.15s, box-shadow 0.15s;"
           onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(15,44,82,0.08)'"
           onmouseout="this.style.transform='';this.style.boxShadow=''">
            <div style="width:40px; height:40px; background:#E4F2EA; color:#2E8B57;
                        border-radius:10px; display:flex; align-items:center; justify-content:center;">
                <i class="bi bi-file-earmark-text"></i>
            </div>
            <div style="font-family:'Fraunces',serif; font-size:1.8rem; font-weight:600; margin-top:8px;">
                <?= $counts['contracts'] ?>
            </div>
            <div class="small text-secondary">Contracts awaiting signing</div>
        </a>
    </div>

    <div class="col-md-4 col-lg-3">
        <a href="/rentbridge/agent/cases.php?tab=active"
           class="d-block bg-white border rounded-3 p-3 text-decoration-none text-dark h-100"
           style="transition: transform 0.15s, box-shadow 0.15s;"
           onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(15,44,82,0.08)'"
           onmouseout="this.style.transform='';this.style.boxShadow=''">
            <div style="width:40px; height:40px; background:#E4F2EA; color:#2E8B57;
                        border-radius:10px; display:flex; align-items:center; justify-content:center;">
                <i class="bi bi-check-circle"></i>
            </div>
            <div style="font-family:'Fraunces',serif; font-size:1.8rem; font-weight:600; margin-top:8px;">
                <?= $counts['active'] ?>
            </div>
            <div class="small text-secondary">Active tenancies</div>
        </a>
    </div>

    <div class="col-md-4 col-lg-3">
        <div class="bg-white border rounded-3 p-3 h-100">
            <div style="width:40px; height:40px; background:#F4F4EE; color:#6c5e3a;
                        border-radius:10px; display:flex; align-items:center; justify-content:center;">
                <i class="bi bi-archive"></i>
            </div>
            <div style="font-family:'Fraunces',serif; font-size:1.8rem; font-weight:600; margin-top:8px;">
                <?= $counts['completed'] ?>
            </div>
            <div class="small text-secondary">Completed tenancies</div>
        </div>
    </div>

    <div class="col-md-8 col-lg-6">
        <a href="/rentbridge/agent/earnings.php"
           class="d-block bg-white border rounded-3 p-3 text-decoration-none text-dark h-100"
           style="transition: transform 0.15s, box-shadow 0.15s;
                  background: linear-gradient(135deg, #fff 0%, #F4FBF7 100%);"
           onmouseover="this.style.transform='translateY(-2px)';this.style.boxShadow='0 4px 12px rgba(15,44,82,0.08)'"
           onmouseout="this.style.transform='';this.style.boxShadow=''">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div style="width:40px; height:40px; background:#2E8B57; color:white;
                                border-radius:10px; display:flex; align-items:center; justify-content:center;">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <div class="small text-secondary mt-2">Total commission earned</div>
                </div>
                <i class="bi bi-arrow-right text-secondary"></i>
            </div>
            <div style="font-family:'Fraunces',serif; font-size:2rem; font-weight:600; margin-top:12px; color:#2E8B57;">
                RM <?= number_format($totalEarned, 2) ?>
            </div>
            <small class="text-secondary">View commission history →</small>
        </a>
    </div>

</div>

<?php if (!empty($pendingReviews)): ?>
<div class="bg-white border rounded-3 p-4 mb-4" style="border-left:4px solid #C9923F !important;">
    <h5 class="mb-3">
        <i class="bi bi-clipboard-check text-warning"></i>
        Properties pending your review
        <span class="badge bg-warning text-dark"><?= count($pendingReviews) ?></span>
    </h5>
    <p class="small text-secondary mb-3">
        You've been auto-assigned to verify these new properties.
        Decisions expected within 24 hours.
    </p>
    <table class="table table-sm">
        <thead>
            <tr>
                <th>Property</th>
                <th>Landlord</th>
                <th>Assigned</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pendingReviews as $r): ?>
                <tr>
                    <td>
                        <strong><?= e($r['title']) ?></strong>
                        <div class="small text-secondary"><?= e($r['city']) ?> · RM <?= number_format((float)$r['monthly_rent']) ?></div>
                    </td>
                    <td><?= e($r['landlord_name']) ?></td>
                    <td class="small">
                        <?= (int)$r['hours_pending'] ?>h ago
                        <?php if ($r['hours_pending'] > 18): ?>
                            <span class="badge bg-danger ms-1">Urgent</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="/rentbridge/agent/property_review.php?id=<?= (int)$r['id'] ?>"
                           class="btn btn-sm btn-primary">Review</a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php if (!empty($pendingUploads)): ?>
<div class="bg-white border rounded-3 p-4 mb-4" style="border-left:4px solid #2E8B57 !important;">
    <h5 class="mb-3">
        <i class="bi bi-file-earmark-arrow-up text-success"></i>
        Pending signed contract uploads
        <span class="badge bg-success"><?= count($pendingUploads) ?></span>
    </h5>
    <p class="small text-secondary mb-3">
        Contracts generated, awaiting offline signatures + your upload of the signed PDF.
    </p>
    <table class="table table-sm">
        <thead>
            <tr>
                <th>Property</th>
                <th>Tenant</th>
                <th>Generated</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($pendingUploads as $u): ?>
                <tr>
                    <td>
                        <strong><?= e($u['title']) ?></strong>
                        <div class="small text-secondary"><?= e($u['city']) ?></div>
                    </td>
                    <td><?= e($u['student_name']) ?></td>
                    <td class="small text-secondary">
                        <?= e(date('d M', strtotime($u['created_at']))) ?>
                    </td>
                    <td>
                        <a href="/rentbridge/agent/upload_signed_contract.php?booking_id=<?= (int)$u['id'] ?>"
                           class="btn btn-sm btn-success">
                            <i class="bi bi-upload me-1"></i> Upload signed PDF
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- URGENT CASES -->
<?php if (!empty($urgentCases)): ?>
    <h5 class="mb-3" style="font-family:'Fraunces',serif;">
        <i class="bi bi-exclamation-triangle text-warning"></i>
        Cases needing action
    </h5>
    <div class="bg-white border rounded-3 overflow-hidden mb-4">
        <table class="table mb-0 align-middle">
            <thead style="background:#F4F4EE;">
                <tr>
                    <th class="ps-3">ID</th>
                    <th>Property</th>
                    <th>Student</th>
                    <th>Status</th>
                    <th>Deadline</th>
                    <th class="text-end pe-3"></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($urgentCases as $c):
                    $deadlineTs = $c['deadline_at'] ? strtotime($c['deadline_at']) : null;
                    $overdue = $deadlineTs && time() > $deadlineTs;
                ?>
                    <tr>
                        <td class="ps-3">
                            <code class="text-secondary">#<?= (int)$c['id'] ?></code>
                        </td>
                        <td>
                            <strong class="small"><?= e($c['property_title']) ?></strong>
                            <div class="small text-secondary">
                                <i class="bi bi-geo-alt"></i> <?= e($c['city']) ?>
                            </div>
                        </td>
                        <td class="small"><?= e($c['student_name']) ?></td>
                        <td>
                            <?php if ($c['status'] === 'pending_agent'): ?>
                                <span class="badge bg-warning text-dark">⏳ Awaiting acceptance</span>
                            <?php else: ?>
                                <span class="badge bg-info text-dark">🔍 Inspecting</span>
                            <?php endif; ?>
                        </td>
                        <td class="small">
                            <?php if ($deadlineTs): ?>
                                <span class="<?= $overdue ? 'text-danger fw-semibold' : 'text-secondary' ?>">
                                    <?= e(date('d M, H:i', $deadlineTs)) ?>
                                    <?php if ($overdue): ?>⚠️<?php endif; ?>
                                </span>
                            <?php else: ?>
                                <span class="text-secondary">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end pe-3">
                            <?php if ($c['status'] === 'pending_agent'): ?>
                                <a href="/rentbridge/agent/case.php?id=<?= (int)$c['id'] ?>"
                                   class="btn btn-sm btn-primary">
                                    Review <i class="bi bi-arrow-right"></i>
                                </a>
                            <?php else: ?>
                                <a href="/rentbridge/agent/inspection.php?booking_id=<?= (int)$c['id'] ?>"
                                   class="btn btn-sm btn-outline-dark">
                                    Continue <i class="bi bi-arrow-right"></i>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php else: ?>
    <div class="bg-white border rounded-3 p-4 text-center">
        <i class="bi bi-cup-hot" style="font-size: 2.5rem; color: rgba(15,44,82,0.15);"></i>
        <h5 class="mt-3 mb-1">All caught up!</h5>
        <p class="text-secondary small mb-0">No urgent cases waiting for your action.</p>
    </div>
<?php endif; ?>

<?php
$pageContent = ob_get_clean();
require __DIR__ . '/../includes/agent_layout.php';