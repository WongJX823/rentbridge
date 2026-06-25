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

9. The rental period runs continuously from the Start Date to the End Date stated in this Agreement, inclusive of any mid-semester or inter-semester break that falls within this period. Monthly rent is payable for every month of the period regardless of academic breaks, and the Tenant retains possession of the property throughout.
TERMS;
}

/**
 * Create a contract for a tenancy, when the agent accepts.
 * Returns the new contract ID, or null on failure.
 */
function create_contract_from_tenancy(int $tenancyId): ?int {
    $pdo = db();

    // Fetch tenancy + verify it's at agent_assigned status
    $stmt = $pdo->prepare(
        'SELECT * FROM tenancies WHERE id = ? AND status = "agent_assigned" LIMIT 1'
    );
    $stmt->execute([$tenancyId]);
    $tenancy = $stmt->fetch();

    if (!$tenancy || !$tenancy['agent_id']) return null;

    // Already has a contract?
    $stmt = $pdo->prepare('SELECT id FROM contracts WHERE tenancy_id = ? LIMIT 1');
    $stmt->execute([$tenancyId]);
    if ($stmt->fetch()) return null;

    try {
        $pdo->beginTransaction();

        // Insert contract (status starts as pending_signatures)
        $stmt = $pdo->prepare(
            'INSERT INTO contracts
                (contract_code, tenancy_id, student_id, landlord_id, agent_id, property_id,
                 start_date, end_date, monthly_rent, deposit, terms, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "pending_signatures")'
        );
        // Placeholder contract_code, we'll update with real code after we have the ID
        $stmt->execute([
            'TEMP',
            $tenancyId,
            (int)$tenancy['student_id'],
            (int)$tenancy['landlord_id'],
            (int)$tenancy['agent_id'],
            (int)$tenancy['property_id'],
            $tenancy['start_date'],
            $tenancy['end_date'],
            (float)$tenancy['monthly_rent'],
            (float)$tenancy['deposit'],
            standard_tenancy_terms(),
        ]);
        $contractId = (int)$pdo->lastInsertId();

        // Update contract_code now that we have the id
        $code = generate_contract_code($contractId);
        $stmt = $pdo->prepare('UPDATE contracts SET contract_code = ? WHERE id = ?');
        $stmt->execute([$code, $contractId]);

        // Bump tenancy status
        $stmt = $pdo->prepare('UPDATE tenancies SET status = "contract_pending" WHERE id = ?');
        $stmt->execute([$tenancyId]);

        $pdo->commit();

        // Notify the student (they sign first)
        notify(
            (int)$tenancy['student_id'],
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
    if (in_array($userId, [(int)$contract['landlord_id'], (int)$contract['agent_id']], true)) return true;
    $pdo = db();
    $stmt = $pdo->prepare("SELECT id FROM co_tenants WHERE tenancy_id = ? AND student_id = ? LIMIT 1");
    $stmt->execute([(int)$contract['tenancy_id'], $userId]);
    return (bool)$stmt->fetchColumn();
}

/**
 * Determine whose turn it is to sign (order: all co-tenants by sign_order → landlord).
 * Returns: ['role' => 'tenant'|'landlord'|'all_done', 'co_tenant_id' => ?int, 'user_id' => ?int, 'name' => string]
 */
function contract_next_signer(array $contract): array {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT id, student_id, full_name FROM co_tenants
         WHERE tenancy_id = ? AND status != 'signed'
         ORDER BY sign_order ASC, id ASC
         LIMIT 1
    ");
    $stmt->execute([(int)$contract['tenancy_id']]);
    $next = $stmt->fetch();

    if ($next) {
        return ['role' => 'tenant', 'co_tenant_id' => (int)$next['id'], 'user_id' => (int)$next['student_id'], 'name' => $next['full_name']];
    }
    if (empty($contract['landlord_signed_at'])) {
        return ['role' => 'landlord', 'co_tenant_id' => null, 'user_id' => (int)$contract['landlord_id'], 'name' => 'Landlord'];
    }
    return ['role' => 'all_done', 'co_tenant_id' => null, 'user_id' => null, 'name' => ''];
}

/**
 * Can this specific user sign right now?
 */
function contract_can_sign(array $contract, int $userId): bool {
    $next = contract_next_signer($contract);
    if ($next['role'] === 'all_done') return false;
    return $userId === $next['user_id'];
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
    $nextInfo = contract_next_signer($contract);
    if ($nextInfo['role'] === 'all_done') {
        return ['success' => false, 'all_signed' => false, 'message' => 'Contract is already fully signed.'];
    }
    if ($userId !== $nextInfo['user_id']) {
        return ['success' => false, 'all_signed' => false, 'message' => 'It is not your turn to sign, or you are not a party to this contract.'];
    }

    $isTenant   = $nextInfo['role'] === 'tenant';
    $coTenantId = $nextInfo['co_tenant_id'];
    $role       = $isTenant ? 'tenant_' . $coTenantId : 'landlord';

    // Save the signature image
    try {
        $sigPath = save_signature_image($dataUrl, $contractId, $role);
    } catch (RuntimeException $e) {
        return ['success' => false, 'all_signed' => false, 'message' => $e->getMessage()];
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? null;

    try {
        $pdo->beginTransaction();

        if ($isTenant) {
            // Save to co_tenants table
            $pdo->prepare("
                UPDATE co_tenants
                   SET status = 'signed', signed_at = NOW(), signature_data = ?
                 WHERE id = ?
            ")->execute([$sigPath, $coTenantId]);
        } else {
            // Save to contracts table (landlord)
            $pdo->prepare("
                UPDATE contracts
                   SET landlord_signature = ?, landlord_signed_at = NOW(), landlord_sign_ip = ?
                 WHERE id = ?
            ")->execute([$sigPath, $ip, $contractId]);
        }

        // Refresh contract + check all co_tenants signed
        $stmt = $pdo->prepare('SELECT * FROM contracts WHERE id = ? LIMIT 1');
        $stmt->execute([$contractId]);
        $contract = $stmt->fetch();

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM co_tenants WHERE tenancy_id = ? AND status != 'signed'");
        $stmt->execute([(int)$contract['tenancy_id']]);
        $unsignedTenants = (int)$stmt->fetchColumn();

        $allSigned = ($unsignedTenants === 0) && !empty($contract['landlord_signed_at']);

        if ($allSigned) {
            $pdo->prepare('UPDATE contracts SET status = "active", activated_at = NOW() WHERE id = ?')
                ->execute([$contractId]);
            $pdo->prepare('UPDATE tenancies SET status = "active" WHERE id = ?')
                ->execute([(int)$contract['tenancy_id']]);
        }

        $pdo->commit();

        // Notifications
        if ($allSigned) {
            $pdfPath = generate_contract_pdf($contractId);
            $msg = 'Tenancy contract ' . $contract['contract_code'] . ' is now active!'
                . ($pdfPath ? ' The signed PDF is now downloadable.' : '');

            foreach ([(int)$contract['landlord_id'], (int)$contract['agent_id']] as $uid) {
                notify($uid, 'contract_active', 'Contract activated', $msg,
                    '/rentbridge/contracts/view.php?id=' . $contractId);
            }
            // Notify all co-tenants
            $stmt = $pdo->prepare("SELECT student_id FROM co_tenants WHERE tenancy_id = ?");
            $stmt->execute([(int)$contract['tenancy_id']]);
            foreach ($stmt->fetchAll() as $ct) {
                notify((int)$ct['student_id'], 'contract_active', 'Contract activated', $msg,
                    '/rentbridge/contracts/view.php?id=' . $contractId);
            }
        } else {
            // Notify the next signer
            $next = contract_next_signer($contract);
            notify(
                $next['user_id'],
                'contract_your_turn',
                'It is your turn to sign',
                'Contract ' . $contract['contract_code'] . ' is ready for your signature.',
                '/rentbridge/contracts/view.php?id=' . $contractId
            );
        }

        return [
            'success'    => true,
            'all_signed' => $allSigned,
            'message'    => $allSigned ? 'Contract activated!' : 'Signature saved.',
        ];

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        @unlink(__DIR__ . '/../' . $sigPath);
        return ['success' => false, 'all_signed' => false, 'message' => 'Database error: ' . $e->getMessage()];
    }
}

/* ============================================================
 *  Formal tenancy-agreement template (shared)
 *  Single source of truth for BOTH the agent-generated blank
 *  agreement and the final signed download. Pass signature image
 *  paths in $d to embed them on the signature lines.
 * ============================================================ */

/**
 * Build the full formal tenancy-agreement HTML (for mPDF).
 *
 * $d keys: contract_code, today, landlord_name, landlord_ic, landlord_phone,
 *          property_type, property_address, term_label, start_short, end_short,
 *          monthly_rent, security_deposit, utility_deposit, tenancy_label,
 *          landlord_sig_img (?abs path), landlord_sig_date (?string),
 *          co_tenants[] => [full_name, ic_number, phone, is_primary,
 *                           sig_img (?abs path), sig_date (?string)]
 */
function rb_agreement_html(array $d): string {
    $esc = fn($v) => htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');

    // One signature block — embeds the signature image when provided.
    $block = function (string $role, string $name, string $ic, string $phone,
                       ?string $sigImg, ?string $sigDate) use ($esc): string {
        $ph = $phone !== '' ? '<br>Contact: ' . $esc($phone) : '';
        if ($sigImg) {
            $sigCell = '<img src="' . $esc($sigImg) . '" style="height:45pt; max-width:180pt;"><br>'
                     . '<span style="font-size:9pt;">' . $esc($sigDate) . '</span>'
                     . '<br>Signature &amp; Date';
        } else {
            $sigCell = '_______________________<br>Signature &amp; Date';
        }
        return '<div style="margin-bottom: 44pt;">'
             . '<p><strong>SIGNED BY ' . $esc($role) . '</strong></p>'
             . '<table width="100%" style="margin-top: 18pt;">'
             . '<tr><td width="50%">NAME: ' . $esc($name) . '<br>NRIC: ' . $esc($ic) . $ph . '</td>'
             . '<td width="50%" style="text-align:right; vertical-align:bottom;">' . $sigCell . '</td></tr>'
             . '</table></div>';
    };

    // Part 3 tenant list + signature blocks
    $tenantListHtml = '';
    $signatureBlocksHtml = $block(
        'LANDLORD', $d['landlord_name'], $d['landlord_ic'], $d['landlord_phone'] ?? '',
        $d['landlord_sig_img'] ?? null, $d['landlord_sig_date'] ?? null
    );
    foreach (($d['co_tenants'] ?? []) as $idx => $ct) {
        $label = ((int)$ct['is_primary'] === 1) ? 'Primary Tenant' : 'Co-Tenant #' . $idx;
        $tenantListHtml .= '<p style="margin-bottom:4px;"><strong>' . $esc($ct['full_name']) . '</strong> '
                         . '(' . $esc($label) . ')<br>NRIC: ' . $esc($ct['ic_number']);
        if (!empty($ct['phone'])) $tenantListHtml .= ' &nbsp; · &nbsp; Tel: ' . $esc($ct['phone']);
        $tenantListHtml .= '</p>';
        $role = ((int)$ct['is_primary'] === 1) ? 'TENANT (Primary)' : 'CO-TENANT';
        $signatureBlocksHtml .= $block(
            $role, $ct['full_name'], $ct['ic_number'], $ct['phone'] ?? '',
            $ct['sig_img'] ?? null, $ct['sig_date'] ?? null
        );
    }

    $cc   = $esc($d['contract_code']);
    $tod  = $esc($d['today']);
    $lname = $esc($d['landlord_name']);
    $lic   = $esc($d['landlord_ic']);
    $lphone = $esc($d['landlord_phone'] ?? '');
    $ptype = $esc($d['property_type']);
    $paddr = $esc($d['property_address']);
    $term  = $esc($d['term_label']);
    $startS = $esc($d['start_short']);
    $endS   = $esc($d['end_short']);
    $rent   = $esc($d['monthly_rent']);
    $secDep = $esc($d['security_deposit']);
    $utilDep = $esc($d['utility_deposit']);
    $tlabel = $esc($d['tenancy_label'] ?? 'TENANTS');

    return <<<HTML
<style>
body { font-family: serif; font-size: 12pt; line-height: 1.5; }
h1, h2 { text-align: center; font-family: serif; }
.cover { text-align: center; padding-top: 200pt; }
.cover h1 { font-size: 28pt; letter-spacing: 4pt; }
.section-title { font-weight: bold; margin-top: 18pt; margin-bottom: 6pt; }
.schedule-item { margin-bottom: 12pt; }
.schedule-item strong { display: inline-block; min-width: 200pt; }
.center { text-align: center; }
table.parties { width: 100%; margin: 20pt 0; }
table.parties td { padding: 8pt; vertical-align: top; }
.footer-code { font-size: 9pt; color: #999; text-align: center; }
</style>

<div class="cover">
    <h1>TENANCY AGREEMENT</h1>
    <div style="margin-top: 80pt; font-size: 11pt; color: #666;">
        Contract Reference<br>
        <strong style="font-size: 14pt; letter-spacing: 2pt;">{$cc}</strong>
    </div>
</div>

<pagebreak />

<h2>TENANCY AGREEMENT</h2>
<p class="center"><strong>DATED THIS {$tod}</strong></p>

<table class="parties">
    <tr><td class="center"><strong>{$lname}</strong><br>{$lic}<br><em>(LANDLORD)</em></td></tr>
    <tr><td class="center" style="padding: 30pt 0;"><strong>AND</strong></td></tr>
    <tr><td class="center"><em>({$tlabel})</em><br><br>{$tenantListHtml}</td></tr>
</table>

<pagebreak />

<h2>TENANCY AGREEMENT</h2>
<p><strong>AN AGREEMENT</strong> made on {$tod}</p>
<p><strong>Between</strong></p>
<p>The party whose name and particulars appear in Part Two of The First Schedule (<strong>"Landlord"</strong>) of the other part.</p>
<p><strong>And</strong></p>
<p>The parties whose names and particulars appear in Part Three of The First Schedule (<strong>"Tenants"</strong>) of the other part.</p>

<p class="section-title">WHEREAS:</p>
<p>1. The Landlord is the registered and/or beneficial owner of the property described in Part Four of The First Schedule ("the Demised Premises").</p>
<p>2. The Landlord is desirous of letting and the Tenants are desirous of taking a tenancy of the Demised Premises upon the terms and subject to the conditions stipulated herein.</p>

<p class="section-title">NOW IT IS HEREBY AGREED as follows:</p>

<p class="section-title">1. Agreement</p>
<p>In consideration of the rent hereinafter reserved and the covenants on the part of the Tenants hereinafter contained, the Landlord hereby lets to the Tenants the whole of the Demised Premises for a term stated in Part Five of The First Schedule commencing on the day and year set out in Part Six of The First Schedule and terminating on the day and year set out in Part Seven of the same at the monthly rent and payable in the manner stipulated in Part Eight of The First Schedule.</p>

<p class="section-title">2. Tenant's Covenants</p>
<p>The Tenants hereby jointly and severally covenant with the Landlord as follows:</p>
<p>(a) To pay the reserved rent on the days and in the manner aforesaid;</p>
<p>(b) To pay on the execution of this Agreement the Rental Deposit and Utility Deposit in respect of electricity, water, indah water and other amenities supplied to and consumed by the Demised Premises as set out in Part Nine of The First Schedule (hereinafter collectively referred to as "the Deposit Sum") to the Landlord as security for the due observance and performance by the Tenants of the stipulated terms and conditions of this Agreement.</p>
<p>(c) The Tenants agree to rent the said Demised Premises for the full term as set out in Part Five, failing which the Landlord shall be entitled to forfeit the Security Deposit.</p>
<p>(d) Any notice requiring to be served hereunder shall be in writing and shall be sufficiently served on the Tenants if left addressed to them at the Demised Premises or forwarded by registered post to the last known address.</p>
<p>(e) The costs and expenses incidental to this Agreement including stamp duty shall be borne and paid by the Tenants.</p>
<p>(f) To use the Demised Premises for residential purposes only.</p>
<p>(g) Not to assign or sub-let the Said Premises without the prior written consent of the Landlord.</p>
<p>(h) To keep the interior of the Demised Premises in good and tenantable repair.</p>
<p>(i) To permit the Landlord and his duly authorized agents at all reasonable times to enter upon Demised Premises to view the state and conditions thereof.</p>

<p class="section-title">3. Landlord's Covenants</p>
<p>The Landlord hereby covenants with the Tenants as follows:</p>
<p>(a) To permit the Tenants, if they punctually pay the rent and observe the covenants herein, peaceably to hold and enjoy the Premises during this Tenancy without disturbances.</p>
<p>(b) To pay all Assessment and Quit Rent from time to time due in respect of the Demised Premises.</p>
<p>(c) To insure and keep insured the Demised Premises from loss or damage by fire.</p>
<p>(d) Upon termination of the Tenancy, the Landlord shall refund to the Tenants the Deposit Sum free of interest after due deductions for damages and arrears.</p>

<p class="section-title">4. Joint and Several Liability</p>
<p>Where there are multiple Tenants, all named Tenants in Part Three of The First Schedule shall be jointly and severally liable for the obligations under this Agreement. Each Tenant acknowledges responsibility for the full rental amount and all covenants, regardless of internal arrangements between the Tenants.</p>

<p class="section-title">5. Mutual Covenants</p>
<p>(a) If the rent shall be in arrears for fourteen (14) days, the Landlord may serve a forfeiture notice and re-enter the Demised Premises.</p>
<p>(b) The Tenants shall not use the Premises for any illegal or unlawful purpose.</p>
<p>(c) The Tenants shall pay all charges for electricity, water, sewerage, and other utilities consumed during the term.</p>

<pagebreak />

<h2>THE FIRST SCHEDULE</h2>
<p class="center"><em>(Which is to be taken and construed as an essential and integral part of this Agreement)</em></p>

<div class="schedule-item"><strong>1. Date of Agreement:</strong> {$tod}</div>
<div class="schedule-item"><strong>2. Landlord:</strong><br>NAME: {$lname}<br>NRIC: {$lic}<br>CONTACT: {$lphone}</div>
<div class="schedule-item"><strong>3. Tenants:</strong><div style="margin-left: 20pt; margin-top: 8pt;">{$tenantListHtml}</div></div>
<div class="schedule-item"><strong>4. Demised Premises:</strong><br>TYPE: {$ptype}<br>ADDRESS: {$paddr}</div>
<div class="schedule-item"><strong>5. Term:</strong> {$term}</div>
<div class="schedule-item"><strong>6. Commencement:</strong> {$startS}</div>
<div class="schedule-item"><strong>7. Termination:</strong> {$endS}</div>
<div class="schedule-item"><strong>8. Monthly Rental:</strong> RM {$rent}<br><strong>Payment:</strong> Before the 10th of every month</div>
<div class="schedule-item"><strong>9. Deposits:</strong><br>Security Deposit: RM {$secDep} (equivalent to 2 months rental)<br>Utility Deposit: RM {$utilDep}</div>
<div class="schedule-item"><strong>10. Authorized Use:</strong> For Residential Use only</div>
<div class="schedule-item"><strong>11. Renewal:</strong> Renewable subject to market price at the time of renewal</div>

<pagebreak />

<h2>SIGNATURES</h2>
<p>THE PARTIES HERETO HAVE SET THEIR HANDS ON THE DAY AND YEAR FIRST ABOVE WRITTEN.</p>

<div style="margin-top: 30pt;">{$signatureBlocksHtml}</div>

<div class="footer-code">Contract Reference: {$cc} · {$tod}</div>
HTML;
}

/**
 * Render a formal-agreement HTML string to a PDF file via mPDF.
 * Returns the relative path, or null on failure.
 */
function rb_render_agreement_pdf(string $html, string $contractCode, string $subDir = 'generated_contracts'): ?string {
    require_once __DIR__ . '/../vendor/autoload.php';
    try {
        $mpdf = new \Mpdf\Mpdf([
            'tempDir' => sys_get_temp_dir(),
            'format' => 'A4',
            'margin_left' => 20, 'margin_right' => 20,
            'margin_top' => 25, 'margin_bottom' => 25,
        ]);
        $mpdf->SetTitle('Tenancy Agreement ' . $contractCode);
        $mpdf->SetAuthor('RentBridge');
        $mpdf->SetWatermarkText($contractCode);
        $mpdf->showWatermarkText = true;
        $mpdf->watermark_font = 'DejaVuSansCondensed';
        $mpdf->watermarkTextAlpha = 0.04;
        $mpdf->SetHTMLHeader('<div style="text-align: right; font-size: 8pt; color: #999;">Contract Reference: ' . htmlspecialchars($contractCode) . '</div>');
        $mpdf->SetHTMLFooter('<div style="text-align: center; font-size: 8pt; color: #999;">Page {PAGENO} of {nbpg} · ' . htmlspecialchars($contractCode) . '</div>');
        $mpdf->WriteHTML($html);

        $absDir = __DIR__ . '/../uploads/' . $subDir;
        if (!is_dir($absDir) && !mkdir($absDir, 0755, true) && !is_dir($absDir)) return null;
        $filename = $contractCode . '_' . time() . '.pdf';
        $relPath  = 'uploads/' . $subDir . '/' . $filename;
        $mpdf->Output(__DIR__ . '/../' . $relPath, \Mpdf\Output\Destination::FILE);
        return $relPath;
    } catch (Throwable $e) {
        error_log('Agreement PDF render failed: ' . $e->getMessage());
        return null;
    }
}

/* ============================================================
 *  PDF generation (dompdf)
 * ============================================================ */

/**
 * Generate a PDF of a signed contract and save it to disk.
 * Returns the relative path (for DB) like 'uploads/contracts/RB-2026-00001.pdf',
 * or null on failure.
 *
 * Uses the SAME formal-agreement template as the unsigned PDF
 * (rb_agreement_html), with signature images embedded on the signature lines.
 */
function generate_contract_pdf(int $contractId): ?string {
    require_once __DIR__ . '/../vendor/autoload.php';

    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT c.*,
               t.duration_type,
               p.title          AS property_title,
               p.property_type,
               p.address        AS property_address,
               p.city           AS property_city,
               p.state          AS property_state,
               p.postcode       AS property_postcode,
               l.full_name      AS landlord_name,
               l.ic_no          AS landlord_ic,
               l.phone          AS landlord_phone
          FROM contracts c
          JOIN tenancies  t ON t.id = c.tenancy_id
          JOIN properties p ON p.id = c.property_id
          JOIN landlords  l ON l.user_id = c.landlord_id
         WHERE c.id = ?
         LIMIT 1
    ");
    $stmt->execute([$contractId]);
    $c = $stmt->fetch();
    if (!$c) return null;

    // Fetch co-tenants with their signatures
    $ctStmt = $pdo->prepare("SELECT * FROM co_tenants WHERE tenancy_id = ? ORDER BY sign_order ASC, id ASC");
    $ctStmt->execute([(int)$c['tenancy_id']]);
    $coTenants = $ctStmt->fetchAll();

    $base = realpath(__DIR__ . '/..') . '/';

    // Helper: resolve a stored relative path to an absolute file path mPDF can read.
    $absSig = function (?string $rel) use ($base): ?string {
        if (empty($rel)) return null;
        $abs = $base . ltrim($rel, '/');
        return file_exists($abs) ? $abs : null;
    };

    // === Build the same data structure rb_agreement_html() expects ===
    $startTs    = strtotime($c['start_date']);
    $endTs      = strtotime($c['end_date']);
    $termMonths = max(1, (int)round(($endTs - $startTs) / (30.44 * 86400)));
    $termLabel  = match($c['duration_type'] ?? '') {
        'three_semesters' => '13 months (3 semesters)',
        'four_semesters'  => '18 months (4 semesters)',
        'two_years'       => '24 months (2 years)',
        'three_years'     => '36 months (3 years)',
        '1_semester'      => '5 months (1 semester)',
        '2_semesters'     => '10 months (2 semesters)',
        '1_year'          => '12 months (1 year)',
        default           => $termMonths . ' months',
    };

    $propertyAddress = $c['property_address'] . ', '
                     . $c['property_city'] . ' ' . $c['property_postcode'] . ', '
                     . $c['property_state'];

    $coTenantsData = [];
    foreach ($coTenants as $ct) {
        $coTenantsData[] = [
            'full_name'  => $ct['full_name'],
            'ic_number'  => $ct['ic_number'],
            'phone'      => $ct['phone'] ?? '',
            'is_primary' => (int)$ct['is_primary'],
            'sig_img'    => $absSig($ct['signature_data'] ?? null),
            'sig_date'   => !empty($ct['signed_at'])
                                ? date('d M Y', strtotime($ct['signed_at']))
                                : null,
        ];
    }

    $data = [
        'contract_code'    => $c['contract_code'],
        'today'            => date('jS \\d\\a\\y \\o\\f F Y',
                                   strtotime($c['activated_at'] ?? $c['created_at'] ?? 'now')),
        'landlord_name'    => $c['landlord_name'],
        'landlord_ic'      => $c['landlord_ic'],
        'landlord_phone'   => $c['landlord_phone'] ?? '',
        'property_type'    => $c['property_type'],
        'property_address' => $propertyAddress,
        'term_label'       => $termLabel,
        'start_short'      => date('d/m/Y', $startTs),
        'end_short'        => date('d/m/Y', $endTs),
        'monthly_rent'     => number_format((float)$c['monthly_rent'], 2),
        'security_deposit' => number_format((float)$c['deposit'], 2),
        'utility_deposit'  => number_format((float)$c['deposit'] * 0.3, 2),
        'tenancy_label'    => 'TENANTS',
        'landlord_sig_img' => $absSig($c['landlord_signature'] ?? null),
        'landlord_sig_date'=> !empty($c['landlord_signed_at'])
                                ? date('d M Y', strtotime($c['landlord_signed_at']))
                                : null,
        'co_tenants'       => $coTenantsData,
    ];

    // === Render with mPDF using shared template ===
    try {
        $html = rb_agreement_html($data);

        $mpdf = new \Mpdf\Mpdf([
            'tempDir'      => sys_get_temp_dir(),
            'format'       => 'A4',
            'margin_left'  => 20, 'margin_right' => 20,
            'margin_top'   => 25, 'margin_bottom' => 25,
        ]);
        $mpdf->SetTitle('Tenancy Agreement ' . $c['contract_code']);
        $mpdf->SetAuthor('RentBridge');
        $mpdf->SetWatermarkText($c['contract_code']);
        $mpdf->showWatermarkText = true;
        $mpdf->watermark_font = 'DejaVuSansCondensed';
        $mpdf->watermarkTextAlpha = 0.04;
        $mpdf->SetHTMLHeader('<div style="text-align: right; font-size: 8pt; color: #999;">Contract Reference: ' . htmlspecialchars($c['contract_code']) . '</div>');
        $mpdf->SetHTMLFooter('<div style="text-align: center; font-size: 8pt; color: #999;">Page {PAGENO} of {nbpg} · ' . htmlspecialchars($c['contract_code']) . '</div>');
        $mpdf->WriteHTML($html);

        $absDir = __DIR__ . '/../uploads/contracts';
        if (!is_dir($absDir) && !mkdir($absDir, 0755, true) && !is_dir($absDir)) return null;

        $filename = $c['contract_code'] . '.pdf';
        $relPath  = 'uploads/contracts/' . $filename;
        $absPath  = __DIR__ . '/../' . $relPath;

        $mpdf->Output($absPath, \Mpdf\Output\Destination::FILE);

        $pdo->prepare('UPDATE contracts SET contract_pdf_path = ? WHERE id = ?')
            ->execute([$relPath, $contractId]);

        return $relPath;
    } catch (Throwable $e) {
        error_log('Contract PDF generation failed: ' . $e->getMessage());
        return null;
    }
}

/**
 * Legacy dompdf-based generator (no longer used by default; retained for reference).
 */
function generate_contract_pdf_legacy_dompdf(int $contractId): ?string {
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

    // Fetch co-tenants with their signatures
    $ctStmt = $pdo->prepare("SELECT * FROM co_tenants WHERE tenancy_id = ? ORDER BY sign_order ASC, id ASC");
    $ctStmt->execute([(int)$c['tenancy_id']]);
    $coTenants = $ctStmt->fetchAll();

    $base        = __DIR__ . '/../';
    $sigLandlord = !empty($c['landlord_signature']) ? realpath($base . $c['landlord_signature']) : null;

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
            <?php foreach ($coTenants as $idx => $ct): $num = $idx + 2; ?>
            <td class="party">
                <h3><?= $num ?>. <?= ((int)$ct['is_primary'] ? 'Primary Tenant' : 'Co-Tenant') ?></h3>
                <strong><?= $h($ct['full_name']) ?></strong><br>
                NRIC: <?= $h($ct['ic_number']) ?><br>
                <?= $h($ct['email']) ?><br>
                <?= $h($ct['phone']) ?>
            </td>
            <?php endforeach; ?>
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
            <td><h3>Duration</h3><strong><?= $months ?> month<?= $months===1?'':'s' ?></strong><br><span class="small">Continuous period, incl. any semester break</span></td>
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
                    <img class="sig-img" src="file:///<?= str_replace('\\', '/', $sigLandlord) ?>" alt="">
                <?php else: ?>
                    <div class="muted small">(not signed)</div>
                <?php endif; ?>
                <div class="sig-meta">
                    <?= !empty($c['landlord_signed_at']) ? $h(date('d M Y, H:i', strtotime($c['landlord_signed_at']))) : '—' ?>
                </div>
            </td>
            <?php foreach ($coTenants as $ct):
                $sigFile = !empty($ct['signature_data']) ? realpath($base . $ct['signature_data']) : null;
            ?>
            <td class="sig-box party">
                <h3><?= (int)$ct['is_primary'] ? 'Primary Tenant' : 'Co-Tenant' ?></h3>
                <?php if ($sigFile && file_exists($sigFile)): ?>
                    <img class="sig-img" src="file:///<?= str_replace('\\', '/', $sigFile) ?>" alt="">
                <?php else: ?>
                    <div class="muted small">(not signed)</div>
                <?php endif; ?>
                <div class="sig-meta">
                    <?= !empty($ct['signed_at']) ? $h(date('d M Y, H:i', strtotime($ct['signed_at']))) : '—' ?>
                </div>
            </td>
            <?php endforeach; ?>
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