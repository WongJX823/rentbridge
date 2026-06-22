<?php
require_once __DIR__ . '/auth.php';

/* ============================================================
 *  Contract helpers
 * ============================================================ */

/**
 * Generate a unique contract code like "RB-2026-00042"
 */
function generate_contract_code(int $contractId): string {
    return sprintf('RB-%s-%05d', date('Y'), $contractId);
}

/**
 * Standard tenancy terms text — embedded in every contract.
 * Single source of truth (easy to update, applies to all new contracts).
 */
function standard_tenancy_terms(): string {
    return <<<TERMS
1. The Landlord shall deliver vacant possession of the property on the start date in good, habitable condition with all utilities functioning.

2. The Tenant shall pay the monthly rent on or before the agreed payment date each month, and shall use the property strictly for residential purposes.

3. The Tenant shall not sublet or transfer the tenancy without the Landlord's prior written consent.

4. The Security Deposit shall be refunded by the Landlord within 14 days of tenancy termination, subject to deductions for damages beyond normal wear and tear.

5. Either party may terminate this Agreement by giving thirty (30) days' written notice. Early termination by the Tenant may forfeit the Security Deposit unless mutually agreed in writing.

6. The Tenant shall maintain the property in cleanliness and promptly report any damage or maintenance issues to the Landlord and the Agent.

7. The Agent (UTeM staff) serves as a neutral witness to this Agreement and as the first point of contact for any dispute. The Agent does not assume financial liability for the Tenant's or Landlord's obligations.

8. All disputes arising shall first be referred to the Agent for mediation. If unresolved, parties may seek redress through the Tribunal Tuntutan Penyewa dan Penyewa Rumah (TPPR) under Malaysian tenancy law.
TERMS;
}

/**
 * Create a contract for a booking, when the agent accepts.
 * Returns the new contract ID, or null on failure.
 */
function create_contract_from_booking(int $bookingId): ?int {
    $pdo = db();

    // Fetch booking + verify it's at agent_assigned status
    $stmt = $pdo->prepare(
        'SELECT * FROM bookings WHERE id = ? AND status = "agent_assigned" LIMIT 1'
    );
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch();

    if (!$booking || !$booking['agent_id']) return null;

    // Already has a contract?
    $stmt = $pdo->prepare('SELECT id FROM contracts WHERE booking_id = ? LIMIT 1');
    $stmt->execute([$bookingId]);
    if ($stmt->fetch()) return null;

    try {
        $pdo->beginTransaction();

        // Insert contract (status starts as pending_signatures)
        $stmt = $pdo->prepare(
            'INSERT INTO contracts
                (contract_code, booking_id, student_id, landlord_id, agent_id, property_id,
                 start_date, end_date, monthly_rent, deposit, terms, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending_signatures")'
        );
        // Placeholder contract_code, we'll update with real code after we have the ID
        $stmt->execute([
            'TEMP',
            $bookingId,
            (int)$booking['student_id'],
            (int)$booking['landlord_id'],
            (int)$booking['agent_id'],
            (int)$booking['property_id'],
            $booking['start_date'],
            $booking['end_date'],
            (float)$booking['monthly_rent'],
            (float)$booking['deposit'],
            standard_tenancy_terms(),
        ]);
        $contractId = (int)$pdo->lastInsertId();

        // Update contract_code now that we have the id
        $code = generate_contract_code($contractId);
        $stmt = $pdo->prepare('UPDATE contracts SET contract_code = ? WHERE id = ?');
        $stmt->execute([$code, $contractId]);

        // Bump booking status
        $stmt = $pdo->prepare('UPDATE bookings SET status = "contract_pending" WHERE id = ?');
        $stmt->execute([$bookingId]);

        $pdo->commit();

        // Notify the student (they sign first)
        notify(
            (int)$booking['student_id'],
            'contract_ready',
            'Contract ready for your signature',
            'Your tenancy contract (' . $code . ') is ready. Please review and sign.',
            '/rentbridge/contracts/view.php?id=' . $contractId
        );

        return $contractId;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return null;
    }
}

/**
 * Helpers for "who can do what" on a contract.
 */
function contract_can_view(array $contract, int $userId, string $role): bool {
    if ($role === 'admin') return true;
    return in_array($userId, [
        (int)$contract['student_id'],
        (int)$contract['landlord_id'],
        (int)$contract['agent_id'],
    ], true);
}

/**
 * Determine whose turn it is to sign (based on strict order: student → landlord → agent).
 * Returns: 'student' | 'landlord' | 'agent' | 'all_done'
 */
function contract_next_signer(array $contract): string {
    if (empty($contract['student_signed_at']))  return 'student';
    if (empty($contract['landlord_signed_at'])) return 'landlord';
    if (empty($contract['agent_signed_at']))    return 'agent';
    return 'all_done';
}

/**
 * Can this specific user sign right now?
 * Enforces the strict student → landlord → agent order.
 */
function contract_can_sign(array $contract, int $userId): bool {
    $next = contract_next_signer($contract);
    if ($next === 'all_done') return false;

    return match ($next) {
        'student'  => $userId === (int)$contract['student_id']  && empty($contract['student_signed_at']),
        'landlord' => $userId === (int)$contract['landlord_id'] && empty($contract['landlord_signed_at']),
        'agent'    => $userId === (int)$contract['agent_id']    && empty($contract['agent_signed_at']),
        default    => false,
    };
}

/* ============================================================
 *  Signature handling (base64 PNG → file on disk)
 * ============================================================ */

/**
 * Decode a base64 data URL and save as PNG.
 * Returns the relative path (for DB) like 'uploads/signatures/sig_XXX.png'
 * Throws RuntimeException on failure.
 */
function save_signature_image(string $dataUrl, int $contractId, string $role): string {
    // Expected format: "data:image/png;base64,iVBORw0..."
    if (!preg_match('#^data:image/png;base64,#', $dataUrl)) {
        throw new RuntimeException('Invalid signature image format.');
    }

    $base64 = substr($dataUrl, strlen('data:image/png;base64,'));
    $binary = base64_decode($base64, true);

    if ($binary === false || strlen($binary) < 100) {
        throw new RuntimeException('Signature image is empty or corrupt.');
    }
    if (strlen($binary) > 2 * 1024 * 1024) {
        throw new RuntimeException('Signature image too large (>2 MB).');
    }

    // Ensure target folder exists (auto-create if missing)
    $absDir = __DIR__ . '/../uploads/signatures';
    if (!is_dir($absDir)) {
        if (!mkdir($absDir, 0755, true) && !is_dir($absDir)) {
            throw new RuntimeException('Failed to create signatures directory.');
        }
    }

    // Filename: sig_{contract}_{role}_{uniq}.png
    $filename = sprintf('sig_%d_%s_%s.png', $contractId, $role, bin2hex(random_bytes(4)));
    $relPath  = 'uploads/signatures/' . $filename;
    $absPath  = __DIR__ . '/../' . $relPath;

    if (file_put_contents($absPath, $binary) === false) {
        throw new RuntimeException('Failed to save signature file.');
    }

    return $relPath;
}

/**
 * Apply a signature to a contract.
 *
 * Returns: array with 'success' (bool), 'all_signed' (bool), 'message' (string)
 */
function apply_signature(int $contractId, int $userId, string $dataUrl): array {
    $pdo = db();

    // Fetch contract
    $stmt = $pdo->prepare('SELECT * FROM contracts WHERE id = ? LIMIT 1');
    $stmt->execute([$contractId]);
    $contract = $stmt->fetch();

    if (!$contract) {
        return ['success' => false, 'all_signed' => false, 'message' => 'Contract not found.'];
    }

    if ($contract['status'] !== 'pending_signatures') {
        return ['success' => false, 'all_signed' => false, 'message' => 'Contract is no longer accepting signatures.'];
    }

    // Enforce strict order
    if (!contract_can_sign($contract, $userId)) {
        return ['success' => false, 'all_signed' => false, 'message' => 'It is not your turn to sign, or you are not a party to this contract.'];
    }

    // Determine which role this user has in the contract
    if      ($userId === (int)$contract['student_id'])  $role = 'student';
    elseif  ($userId === (int)$contract['landlord_id']) $role = 'landlord';
    elseif  ($userId === (int)$contract['agent_id'])    $role = 'agent';
    else    return ['success' => false, 'all_signed' => false, 'message' => 'You are not a party.'];

    // Save the signature image
    try {
        $sigPath = save_signature_image($dataUrl, $contractId, $role);
    } catch (RuntimeException $e) {
        return ['success' => false, 'all_signed' => false, 'message' => $e->getMessage()];
    }

    // Update DB
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    $sigCol  = $role . '_signature';
    $timeCol = $role . '_signed_at';
    $ipCol   = $role . '_sign_ip';

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE contracts
               SET $sigCol = ?, $timeCol = NOW(), $ipCol = ?
             WHERE id = ?
        ");
        $stmt->execute([$sigPath, $ip, $contractId]);

        // Refresh contract to check final state
        $stmt = $pdo->prepare('SELECT * FROM contracts WHERE id = ? LIMIT 1');
        $stmt->execute([$contractId]);
        $contract = $stmt->fetch();

        $allSigned = !empty($contract['student_signed_at'])
                  && !empty($contract['landlord_signed_at'])
                  && !empty($contract['agent_signed_at']);

        if ($allSigned) {
            // Activate the contract
            $stmt = $pdo->prepare(
                'UPDATE contracts SET status = "active", activated_at = NOW() WHERE id = ?'
            );
            $stmt->execute([$contractId]);

            // Bump booking too
            $stmt = $pdo->prepare(
                'UPDATE bookings SET status = "active" WHERE id = ?'
            );
            $stmt->execute([(int)$contract['booking_id']]);
        }

        $pdo->commit();

        // ─── Notify the right people ───────────────────────────────
        if ($allSigned) {
            // Auto-generate the signed PDF
            $pdfPath = generate_contract_pdf($contractId);

            $msg = 'Tenancy contract ' . $contract['contract_code'] . ' is now active!'
                . ($pdfPath ? ' The signed PDF is now downloadable.' : '');

            foreach (['student_id', 'landlord_id', 'agent_id'] as $col) {
                notify(
                    (int)$contract[$col],
                    'contract_active',
                    'Contract activated',
                    $msg,
                    '/rentbridge/contracts/view.php?id=' . $contractId
                );
            }
            // (Module 9.3 will also auto-generate the PDF here.)
        } else {
            // Notify the NEXT signer
            $next = contract_next_signer($contract);
            $nextUserId = (int)$contract[$next . '_id'];
            notify(
                $nextUserId,
                'contract_your_turn',
                'It is your turn to sign',
                'Contract ' . $contract['contract_code'] . ' is ready for your signature.',
                '/rentbridge/contracts/view.php?id=' . $contractId
            );
        }

        return [
            'success'     => true,
            'all_signed'  => $allSigned,
            'message'     => $allSigned ? 'Contract activated!' : 'Signature saved.',
        ];

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        // Clean up the orphan file
        @unlink(__DIR__ . '/../' . $sigPath);
        return ['success' => false, 'all_signed' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/* ============================================================
 *  PDF generation (dompdf)
 * ============================================================ */

/**
 * Generate a PDF of a signed contract and save it to disk.
 * Returns the relative path (for DB) like 'uploads/contracts/RB-2026-00001.pdf',
 * or null on failure.
 */
function generate_contract_pdf(int $contractId): ?string {
    // Load the composer-installed dompdf library
    require_once __DIR__ . '/../vendor/autoload.php';

    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT c.*,
               p.title       AS property_title,
               p.property_type,
               p.address     AS property_address,
               p.city        AS property_city,
               p.state       AS property_state,
               p.postcode    AS property_postcode,
               p.furnishing,
               p.facilities,
               s.full_name   AS student_name,
               s.matric_no   AS student_matric,
               s.phone       AS student_phone,
               us.email      AS student_email,
               l.full_name   AS landlord_name,
               l.ic_no       AS landlord_ic,
               l.phone       AS landlord_phone,
               ul.email      AS landlord_email,
               a.full_name   AS agent_name,
               a.staff_id    AS agent_staff_id,
               a.department  AS agent_department,
               a.phone       AS agent_phone,
               ua.email      AS agent_email
          FROM contracts c
          JOIN properties p ON p.id = c.property_id
          JOIN students   s ON s.user_id = c.student_id
          JOIN users      us ON us.id = c.student_id
          JOIN landlords  l ON l.user_id = c.landlord_id
          JOIN users      ul ON ul.id = c.landlord_id
          JOIN agents     a ON a.user_id = c.agent_id
          JOIN users      ua ON ua.id = c.agent_id
         WHERE c.id = ?
         LIMIT 1
    ");
    $stmt->execute([$contractId]);
    $c = $stmt->fetch();
    if (!$c) return null;

    // Build absolute paths to signature images (dompdf needs absolute paths)
    $base = __DIR__ . '/../';
    $sigStudent  = !empty($c['student_signature'])  ? realpath($base . $c['student_signature'])  : null;
$sigLandlord = !empty($c['landlord_signature']) ? realpath($base . $c['landlord_signature']) : null;
$sigAgent    = !empty($c['agent_signature'])    ? realpath($base . $c['agent_signature'])    : null;

    // Helper for safe HTML escape
    $h = fn(?string $v): string => htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');

    // Calculate months
    $startTs = strtotime($c['start_date']);
    $endTs   = strtotime($c['end_date']);
    $months  = max(1, (int)round(($endTs - $startTs) / (30.44 * 86400)));
    $total   = $months * (float)$c['monthly_rent'];

    // Build PDF HTML (note: dompdf has slightly different CSS support than browsers)
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            @page { margin: 50px 60px; }
            body  { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #0F2C52; line-height: 1.5; }
            h1    { font-size: 22px; margin: 0 0 4px; }
            h2    { font-size: 14px; margin: 22px 0 8px; border-bottom: 2px solid #0F2C52; padding-bottom: 4px; }
            h3    { font-size: 11px; margin: 0 0 4px; text-transform: uppercase; letter-spacing: 0.08em; color: #6B7B91; }
            .center  { text-align: center; }
            .small   { font-size: 9.5px; color: #6B7B91; }
            .muted   { color: #6B7B91; }
            .accent  { color: #2E8B57; font-weight: bold; }
            table  { width: 100%; border-collapse: collapse; margin-top: 4px; }
            table td { padding: 6px 8px; border: 1px solid #E5E1D8; vertical-align: top; }
            .party { width: 33%; }
            .terms-list { white-space: pre-wrap; }
            .sig-box   { border: 1px solid #E5E1D8; padding: 12px; text-align: center; }
            .sig-img   { max-height: 70px; max-width: 200px; }
            .sig-meta  { font-size: 9px; color: #6B7B91; margin-top: 4px; }
            .header-rule { border-top: 4px double #0F2C52; margin: 6px 0 18px; }
            .footer  { margin-top: 30px; padding-top: 12px; border-top: 1px solid #E5E1D8; font-size: 9px; color: #6B7B91; text-align: center; }
        </style>
    </head>
    <body>

    <div class="center">
        <h3 class="muted">Tripartite Tenancy Agreement</h3>
        <h1>RentBridge Contract</h1>
        <div class="small">
            Contract code: <strong><?= $h($c['contract_code']) ?></strong>
            &nbsp;·&nbsp; Generated <?= $h(date('d M Y', strtotime($c['created_at']))) ?>
            <?php if (!empty($c['activated_at'])): ?>
                &nbsp;·&nbsp; Activated <?= $h(date('d M Y, H:i', strtotime($c['activated_at']))) ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="header-rule"></div>

    <h2>Parties to this Agreement</h2>
    <table>
        <tr>
            <td class="party">
                <h3>1. Landlord</h3>
                <strong><?= $h($c['landlord_name']) ?></strong><br>
                IC: <?= $h($c['landlord_ic']) ?><br>
                <?= $h($c['landlord_email']) ?><br>
                <?= $h($c['landlord_phone']) ?>
            </td>
            <td class="party">
                <h3>2. Tenant</h3>
                <strong><?= $h($c['student_name']) ?></strong><br>
                Matric: <?= $h($c['student_matric']) ?><br>
                <?= $h($c['student_email']) ?><br>
                <?= $h($c['student_phone']) ?>
            </td>
            <td class="party">
                <h3>3. Witness Agent</h3>
                <strong><?= $h($c['agent_name']) ?></strong><br>
                UTeM Staff ID: <?= $h($c['agent_staff_id']) ?><br>
                <?= $h($c['agent_department']) ?><br>
                <?= $h($c['agent_email']) ?>
            </td>
        </tr>
    </table>

    <h2>Property</h2>
    <table>
        <tr>
            <td>
                <strong><?= $h($c['property_title']) ?></strong><br>
                <?= $h($c['property_address']) ?>,
                <?= $h($c['property_city']) ?> <?= $h($c['property_postcode']) ?>,
                <?= $h($c['property_state']) ?>
                <br><br>
                Type: <strong><?= $h(ucfirst(str_replace('_',' ', $c['property_type']))) ?></strong>
                &nbsp;·&nbsp;
                Furnishing: <strong><?= $h(ucfirst($c['furnishing'] ?? '')) ?></strong>
                <?php if (!empty($c['facilities'])): ?>
                    <br>Facilities: <?= $h($c['facilities']) ?>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <h2>Tenancy Terms</h2>
    <table>
        <tr>
            <td><h3>Start Date</h3><strong><?= $h(date('d M Y', $startTs)) ?></strong></td>
            <td><h3>End Date</h3><strong><?= $h(date('d M Y', $endTs)) ?></strong></td>
            <td><h3>Duration</h3><strong><?= $months ?> month<?= $months===1?'':'s' ?></strong></td>
        </tr>
        <tr>
            <td><h3>Monthly Rent</h3><strong class="accent">RM <?= number_format((float)$c['monthly_rent']) ?></strong></td>
            <td><h3>Security Deposit</h3><strong>RM <?= number_format((float)$c['deposit']) ?></strong></td>
            <td><h3>Total Contract Value</h3><strong class="accent">RM <?= number_format($total) ?></strong></td>
        </tr>
    </table>

    <h2>Standard Terms</h2>
    <div class="terms-list"><?= $h($c['terms']) ?></div>

    <h2>Signatures</h2>
    <table>
        <tr>
            <td class="sig-box party">
                <h3>Landlord</h3>
                <?php if ($sigLandlord && file_exists($sigLandlord)): ?>
                    <img class="sig-img" src="file://<?= str_replace('\\', '/', $sigLandlord) ?>" alt="">
                <?php else: ?>
                    <div class="muted small">(not signed)</div>
                <?php endif; ?>
                <div class="sig-meta">
                    <?= !empty($c['landlord_signed_at']) ? $h(date('d M Y, H:i', strtotime($c['landlord_signed_at']))) : '—' ?><br>
                    IP: <?= $h($c['landlord_sign_ip'] ?? '—') ?>
                </div>
            </td>
            <td class="sig-box party">
                <h3>Tenant</h3>
                <?php if ($sigStudent && file_exists($sigStudent)): ?>
                    <img class="sig-img" src="file://<?= str_replace('\\', '/', $sigStudent) ?>" alt="">                
                    <?php else: ?>
                    <div class="muted small">(not signed)</div>
                <?php endif; ?>
                <div class="sig-meta">
                    <?= !empty($c['student_signed_at']) ? $h(date('d M Y, H:i', strtotime($c['student_signed_at']))) : '—' ?><br>
                    IP: <?= $h($c['student_sign_ip'] ?? '—') ?>
                </div>
            </td>
            <td class="sig-box party">
                <h3>Witness Agent</h3>
                <?php if ($sigAgent && file_exists($sigAgent)): ?>
                    <img class="sig-img" src="file://<?= str_replace('\\', '/', $sigAgent) ?>" alt="">
                <?php else: ?>
                    <div class="muted small">(not signed)</div>
                <?php endif; ?>
                <div class="sig-meta">
                    <?= !empty($c['agent_signed_at']) ? $h(date('d M Y, H:i', strtotime($c['agent_signed_at']))) : '—' ?><br>
                    IP: <?= $h($c['agent_sign_ip'] ?? '—') ?>
                </div>
            </td>
        </tr>
    </table>

    <div class="footer">
        This document is generated by RentBridge from cryptographically-stored signature records.
        Verify authenticity at rentbridge.com/verify/<?= $h($c['contract_code']) ?>
    </div>

    </body>
    </html>
    <?php
    $html = ob_get_clean();

    // Render with dompdf
    try {
        $options = new \Dompdf\Options();
$options->set('isRemoteEnabled', true);  // Allow file:// access for signature images
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');
// Tell dompdf which directories it can read images from
$options->setChroot([
    realpath(__DIR__ . '/../uploads'),
    realpath(__DIR__ . '/../'),
]);

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // Save PDF to disk
        $absDir = __DIR__ . '/../uploads/contracts';
        if (!is_dir($absDir)) {
            if (!mkdir($absDir, 0755, true) && !is_dir($absDir)) return null;
        }

        $filename = $c['contract_code'] . '.pdf';
        $relPath  = 'uploads/contracts/' . $filename;
        $absPath  = __DIR__ . '/../' . $relPath;

        if (file_put_contents($absPath, $dompdf->output()) === false) return null;

        // Save path in DB
        $stmt = $pdo->prepare('UPDATE contracts SET contract_pdf_path = ? WHERE id = ?');
        $stmt->execute([$relPath, $contractId]);

        return $relPath;

    } catch (Throwable $e) {
        error_log('Contract PDF generation failed: ' . $e->getMessage());
        return null;
    }
}

/**
 * Lazy check — send contract expiry notifications.
 * Call this on dashboard loads. Idempotent: skips if already notified.
 *
 * Rules:
 *   • Student: 4-month early warning (all durations)
 *   • Landlord: 2-month early warning (only for contracts ≥ 2 semesters ≈ 6 months)
 *
 * Uses notification types 'contract_expiring_4m' / 'contract_expiring_2m' to avoid duplicates.
 */
function check_contract_expiry_notifications(): void {
    if (!function_exists('notify')) return;

    $pdo = db();

    // Student: within 4 months of end, not yet notified
    $stmt = $pdo->query("
        SELECT c.id, c.contract_code, c.student_id, c.end_date,
               p.title AS property_title
          FROM contracts c
          JOIN properties p ON p.id = c.property_id
         WHERE c.status = 'active'
           AND c.end_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 4 MONTH)
           AND NOT EXISTS (
               SELECT 1 FROM notifications n
                WHERE n.user_id = c.student_id
                  AND n.type = 'contract_expiring_4m'
                  AND n.link_url LIKE CONCAT('%/contracts/view.php?id=', c.id, '%')
           )
    ");
    foreach ($stmt->fetchAll() as $c) {
        $endDate = date('d M Y', strtotime($c['end_date']));
        notify(
            (int)$c['student_id'],
            'contract_expiring_4m',
            'Your tenancy ends in ~4 months',
            "Your contract ({$c['contract_code']}) for \"{$c['property_title']}\" ends {$endDate}. "
            . "Plan your next tenancy or move-out. Standard notice to landlord: 2 months before end date.",
            "/rentbridge/contracts/view.php?id={$c['id']}"
        );
    }

    // Landlord: within 2 months of end, only for contracts >= 6 months (≈ 2 semesters)
    $stmt = $pdo->query("
        SELECT c.id, c.contract_code, c.landlord_id, c.end_date,
               DATEDIFF(c.end_date, c.start_date) AS duration_days,
               p.title AS property_title
          FROM contracts c
          JOIN properties p ON p.id = c.property_id
         WHERE c.status = 'active'
           AND c.end_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 2 MONTH)
           AND DATEDIFF(c.end_date, c.start_date) >= 180
           AND NOT EXISTS (
               SELECT 1 FROM notifications n
                WHERE n.user_id = c.landlord_id
                  AND n.type = 'contract_expiring_2m'
                  AND n.link_url LIKE CONCAT('%/contracts/view.php?id=', c.id, '%')
           )
    ");
    foreach ($stmt->fetchAll() as $c) {
        $endDate = date('d M Y', strtotime($c['end_date']));
        notify(
            (int)$c['landlord_id'],
            'contract_expiring_2m',
            'Tenancy ending soon — plan ahead',
            "Contract {$c['contract_code']} for \"{$c['property_title']}\" ends {$endDate}. "
            . "The tenant was notified 4 months ago. If you plan to re-list, update your property listing.",
            "/rentbridge/contracts/view.php?id={$c['id']}"
        );
    }
}