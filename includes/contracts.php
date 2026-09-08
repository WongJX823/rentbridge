<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/storage.php';

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
        'SELECT * FROM tenancies WHERE id = ? AND status = \'agent_assigned\' LIMIT 1'
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
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, \'pending_signatures\')'
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
        $stmt = $pdo->prepare('UPDATE tenancies SET status = \'contract_pending\' WHERE id = ?');
        $stmt->execute([$tenancyId]);

        $pdo->commit();

        // Give account-less co-tenants a signing link token.
        ensure_cotenant_sign_tokens($tenancyId);

        // Notify the student (they sign first)
        notify(
            (int)$tenancy['student_id'],
            'contract_ready',
            'Contract ready for your signature',
            'Your tenancy contract (' . $code . ') is ready. Please review and sign.',
            '' . BASE_PATH . '/contracts/view.php?id=' . $contractId
        );

        return $contractId;

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return null;
    }
}

/**
 * Ensure a contract has its corresponding commission row.
 * Idempotent: safe to call from any activation path or backfill.
 */
function ensure_agent_commission_for_contract(int $contractId, string $status = 'earned'): bool {
    $validStatuses = ['pending', 'earned', 'released', 'paid'];
    if (!in_array($status, $validStatuses, true)) {
        $status = 'earned';
    }

    $pdo = db();

    $stmt = $pdo->prepare('SELECT id FROM agent_commissions WHERE contract_id = ? LIMIT 1');
    $stmt->execute([$contractId]);
    if ($stmt->fetchColumn()) {
        return true;
    }

    $stmt = $pdo->prepare('
        SELECT id, agent_id, monthly_rent, activated_at, created_at
          FROM contracts
         WHERE id = ?
         LIMIT 1
    ');
    $stmt->execute([$contractId]);
    $contract = $stmt->fetch();

    if (!$contract || (int)$contract['agent_id'] <= 0) {
        return false;
    }

    $baseRent = (float)$contract['monthly_rent'];
    if ($baseRent <= 0) {
        return false;
    }

    $commissionAmt = $baseRent;
    $sstAmt = round($commissionAmt * 0.06, 2);
    $totalPayable = round($commissionAmt + $sstAmt, 2);
    $earnedAt = $status === 'pending'
        ? null
        : ($contract['activated_at'] ?: ($contract['created_at'] ?: date('Y-m-d H:i:s')));

    try {
        $stmt = $pdo->prepare('
            INSERT INTO agent_commissions
                (contract_id, agent_id, base_rent, commission_pct, commission_amt,
                 sst_pct, sst_amt, total_payable, status, earned_at)
            VALUES (?, ?, ?, 100.00, ?, 6.00, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $contractId,
            (int)$contract['agent_id'],
            $baseRent,
            $commissionAmt,
            $sstAmt,
            $totalPayable,
            $status,
            $earnedAt,
        ]);
        return true;
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            return true;
        }
        throw $e;
    }
}

/**
 * Backfill commissions for active/completed contracts that predate commission creation.
 */
function backfill_agent_commissions_for_active_contracts(): int {
    $pdo = db();
    $stmt = $pdo->query("
        SELECT c.id
          FROM contracts c
          LEFT JOIN agent_commissions ac ON ac.contract_id = c.id
         WHERE c.status IN ('active', 'completed')
           AND ac.id IS NULL
         ORDER BY c.activated_at ASC, c.id ASC
    ");

    $created = 0;
    foreach ($stmt->fetchAll() as $row) {
        if (ensure_agent_commission_for_contract((int)$row['id'], 'earned')) {
            $created++;
        }
    }

    return $created;
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
 * Parties who already chose 'manual' (sign on a physical copy) are skipped —
 * they're resolved later by the agent uploading a merged signed PDF, not
 * through this e-sign queue.
 * Returns: ['role' => 'tenant'|'landlord'|'awaiting_manual'|'all_done', 'co_tenant_id' => ?int, 'user_id' => ?int, 'name' => string]
 */
function contract_next_signer(array $contract): array {
    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT id, student_id, full_name FROM co_tenants
         WHERE tenancy_id = ? AND status != 'signed' AND sign_method != 'manual'
         ORDER BY sign_order ASC, id ASC
         LIMIT 1
    ");
    $stmt->execute([(int)$contract['tenancy_id']]);
    $next = $stmt->fetch();

    if ($next) {
        return ['role' => 'tenant', 'co_tenant_id' => (int)$next['id'], 'user_id' => (int)$next['student_id'], 'name' => $next['full_name']];
    }

    $landlordIsManual = ($contract['landlord_sign_method'] ?? 'esign') === 'manual';
    if (empty($contract['landlord_signed_at']) && !$landlordIsManual) {
        return ['role' => 'landlord', 'co_tenant_id' => null, 'user_id' => (int)$contract['landlord_id'], 'name' => 'Landlord'];
    }

    // Every e-signing party is done — but if anyone (tenant or landlord)
    // chose manual and hasn't been resolved yet, the contract isn't
    // actually finished; it's waiting on the agent's physical-copy upload.
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM co_tenants
         WHERE tenancy_id = ? AND status != 'signed' AND sign_method = 'manual'
    ");
    $stmt->execute([(int)$contract['tenancy_id']]);
    $manualTenantsPending = (int)$stmt->fetchColumn() > 0;
    $manualLandlordPending = $landlordIsManual && empty($contract['landlord_signed_at']);

    if ($manualTenantsPending || $manualLandlordPending) {
        return ['role' => 'awaiting_manual', 'co_tenant_id' => null, 'user_id' => null, 'name' => ''];
    }

    return ['role' => 'all_done', 'co_tenant_id' => null, 'user_id' => null, 'name' => ''];
}

/**
 * Can this specific user sign right now?
 */
function contract_can_sign(array $contract, int $userId): bool {
    $next = contract_next_signer($contract);
    if ($next['role'] === 'all_done' || $next['role'] === 'awaiting_manual') return false;
    return $userId === $next['user_id'];
}

/**
 * Record that it's this user's turn and they chose to sign a physical copy
 * instead of e-signing. Does NOT mark them as signed — the agent still has
 * to collect the physical signature and upload the merged PDF
 * (agent/upload_signed_contract.php) before the contract can activate.
 */
function choose_manual_signing(int $contractId, int $userId): array {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM contracts WHERE id = ? LIMIT 1');
    $stmt->execute([$contractId]);
    $contract = $stmt->fetch();

    if (!$contract) {
        return ['success' => false, 'message' => 'Contract not found.'];
    }
    if ($contract['status'] !== 'pending_signatures') {
        return ['success' => false, 'message' => 'Contract is no longer accepting signatures.'];
    }

    $nextInfo = contract_next_signer($contract);
    if ($userId !== $nextInfo['user_id']) {
        return ['success' => false, 'message' => 'It is not your turn to sign, or you are not a party to this contract.'];
    }

    if ($nextInfo['role'] === 'tenant') {
        $pdo->prepare("UPDATE co_tenants SET sign_method = 'manual' WHERE id = ?")
            ->execute([$nextInfo['co_tenant_id']]);
    } else {
        $pdo->prepare("UPDATE contracts SET landlord_sign_method = 'manual' WHERE id = ?")
            ->execute([$contractId]);
    }

    // Notify the agent so they know to arrange physical signature collection.
    if (function_exists('notify') && !empty($contract['agent_id'])) {
        notify(
            (int)$contract['agent_id'],
            'contract_manual_signing_chosen',
            'A party chose to sign a physical copy',
            $nextInfo['name'] . ' will sign contract ' . $contract['contract_code']
                . ' on a physical copy instead of e-signing — collect their signature and upload the merged PDF.',
            '' . BASE_PATH . '/agent/case.php?id=' . (int)$contract['tenancy_id']
        );
    }

    // The queue advances past this party (contract_next_signer() skips
    // sign_method='manual' rows) — re-check and notify whoever is now next,
    // same as apply_signature() does after an e-sign. Without this, the next
    // co-tenant/landlord in line is never told it's their turn.
    $stmt = $pdo->prepare('SELECT * FROM contracts WHERE id = ? LIMIT 1');
    $stmt->execute([$contractId]);
    $refreshed = $stmt->fetch();
    $following = contract_next_signer($refreshed);
    if (function_exists('notify') && $following['user_id'] !== null) {
        notify(
            $following['user_id'],
            'contract_your_turn',
            'It is your turn to sign',
            'Contract ' . $contract['contract_code'] . ' is ready for your signature.',
            '' . BASE_PATH . '/contracts/view.php?id=' . $contractId
        );
    }

    return ['success' => true, 'message' => 'Noted — you\'ll sign a physical copy instead. Your agent has been notified.'];
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

    // Filename: sig_{contract}_{role}_{uniq}.png
    $filename = sprintf('sig_%d_%s_%s.png', $contractId, $role, bin2hex(random_bytes(4)));
    $relPath  = 'uploads/signatures/' . $filename;

    if (!rb_storage_put_contents($binary, $relPath)) {
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
            $pdo->prepare('UPDATE contracts SET status = \'active\', activated_at = NOW() WHERE id = ?')
                ->execute([$contractId]);
            $pdo->prepare('UPDATE tenancies SET status = \'active\' WHERE id = ?')
                ->execute([(int)$contract['tenancy_id']]);
            ensure_agent_commission_for_contract($contractId, 'earned');
        }

        $pdo->commit();

        // Notifications
        if ($allSigned) {
            $pdfPath = generate_contract_pdf($contractId);
            $msg = 'Tenancy contract ' . $contract['contract_code'] . ' is now active!'
                . ($pdfPath ? ' The signed PDF is now downloadable.' : '');

            foreach ([(int)$contract['landlord_id'], (int)$contract['agent_id']] as $uid) {
                notify($uid, 'contract_active', 'Contract activated', $msg,
                    '' . BASE_PATH . '/contracts/view.php?id=' . $contractId);
            }
            // Notify all co-tenants
            $stmt = $pdo->prepare("SELECT student_id FROM co_tenants WHERE tenancy_id = ?");
            $stmt->execute([(int)$contract['tenancy_id']]);
            foreach ($stmt->fetchAll() as $ct) {
                notify((int)$ct['student_id'], 'contract_active', 'Contract activated', $msg,
                    '' . BASE_PATH . '/contracts/view.php?id=' . $contractId);
            }
        } else {
            // Notify the next signer — 'awaiting_manual' (some parties chose
            // a physical copy and are resolved later by the agent's upload,
            // not through this queue) and 'all_done' have no concrete user
            // to notify.
            $next = contract_next_signer($contract);
            if ($next['user_id'] !== null) {
                notify(
                    $next['user_id'],
                    'contract_your_turn',
                    'It is your turn to sign',
                    'Contract ' . $contract['contract_code'] . ' is ready for your signature.',
                    '' . BASE_PATH . '/contracts/view.php?id=' . $contractId
                );
            } elseif ($next['role'] === 'awaiting_manual' && !empty($contract['agent_id'])) {
                // Everyone e-signing is done — only physical signature(s)
                // remain, which this signature completion just unblocked.
                notify(
                    (int)$contract['agent_id'],
                    'contract_awaiting_manual',
                    'E-signing complete — physical signature needed',
                    'All e-signing parties have signed contract ' . $contract['contract_code']
                        . '. Collect the remaining physical signature(s) and upload the scanned page(s).',
                    '' . BASE_PATH . '/agent/upload_signed_contract.php?tenancy_id=' . (int)$contract['tenancy_id']
                );
            }
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

/**
 * List everyone on a contract who chose to sign physically and hasn't been
 * resolved yet — what the agent picks from when applying a cropped signature.
 * Each entry: ['target' => 'landlord' | 'tenant_<co_tenant_id>', 'name' => string].
 */
function contract_pending_manual_signers(int $contractId): array {
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM contracts WHERE id = ? LIMIT 1');
    $stmt->execute([$contractId]);
    $contract = $stmt->fetch();
    if (!$contract) return [];

    $out = [];
    $stmt = $pdo->prepare("
        SELECT id, full_name FROM co_tenants
         WHERE tenancy_id = ? AND status != 'signed' AND status != 'removed' AND sign_method = 'manual'
         ORDER BY sign_order ASC
    ");
    $stmt->execute([(int)$contract['tenancy_id']]);
    foreach ($stmt->fetchAll() as $row) {
        $out[] = ['target' => 'tenant_' . $row['id'], 'name' => $row['full_name']];
    }
    if (($contract['landlord_sign_method'] ?? 'esign') === 'manual' && empty($contract['landlord_signed_at'])) {
        $out[] = ['target' => 'landlord', 'name' => 'Landlord'];
    }
    return $out;
}

/**
 * Apply a physical signer's signature, cropped by the agent from a scanned
 * page, to a contract. Mirrors apply_signature() (same save-image / all-
 * signed / activate / notify logic) but is invoked BY THE AGENT on behalf of
 * whichever party chose to sign physically, instead of by the signer
 * themselves via the live canvas pad — the resulting image is stored and
 * treated identically either way, so the final PDF always regenerates from
 * complete, current data rather than an imported snapshot of an old page.
 *
 * @param string $target 'landlord' or 'tenant_<co_tenant_id>' (see
 *                        contract_pending_manual_signers()).
 */
function apply_manual_signature(int $contractId, int $agentId, string $target, string $dataUrl): array {
    $pdo = db();

    $stmt = $pdo->prepare('SELECT * FROM contracts WHERE id = ? LIMIT 1');
    $stmt->execute([$contractId]);
    $contract = $stmt->fetch();

    if (!$contract) {
        return ['success' => false, 'all_signed' => false, 'message' => 'Contract not found.'];
    }
    if ((int)$contract['agent_id'] !== $agentId) {
        return ['success' => false, 'all_signed' => false, 'message' => 'You are not the assigned agent for this contract.'];
    }
    if ($contract['status'] !== 'pending_signatures') {
        return ['success' => false, 'all_signed' => false, 'message' => 'Contract is no longer accepting signatures.'];
    }

    $isTenant   = str_starts_with($target, 'tenant_');
    $coTenantId = $isTenant ? (int)substr($target, strlen('tenant_')) : null;

    if ($isTenant) {
        $stmt = $pdo->prepare("
            SELECT id FROM co_tenants
             WHERE id = ? AND tenancy_id = ? AND status != 'signed' AND status != 'removed' AND sign_method = 'manual'
             LIMIT 1
        ");
        $stmt->execute([$coTenantId, (int)$contract['tenancy_id']]);
        if (!$stmt->fetchColumn()) {
            return ['success' => false, 'all_signed' => false, 'message' => 'That tenant is not awaiting a physical signature.'];
        }
    } else {
        if (($contract['landlord_sign_method'] ?? 'esign') !== 'manual' || !empty($contract['landlord_signed_at'])) {
            return ['success' => false, 'all_signed' => false, 'message' => 'The landlord is not awaiting a physical signature.'];
        }
    }

    $role = $isTenant ? 'tenant_' . $coTenantId : 'landlord';

    try {
        $sigPath = save_signature_image($dataUrl, $contractId, $role);
    } catch (RuntimeException $e) {
        return ['success' => false, 'all_signed' => false, 'message' => $e->getMessage()];
    }

    try {
        $pdo->beginTransaction();

        if ($isTenant) {
            $pdo->prepare("
                UPDATE co_tenants
                   SET status = 'signed', signed_at = NOW(), signature_data = ?
                 WHERE id = ?
            ")->execute([$sigPath, $coTenantId]);
        } else {
            $pdo->prepare("
                UPDATE contracts
                   SET landlord_signature = ?, landlord_signed_at = NOW()
                 WHERE id = ?
            ")->execute([$sigPath, $contractId]);
        }

        $stmt = $pdo->prepare('SELECT * FROM contracts WHERE id = ? LIMIT 1');
        $stmt->execute([$contractId]);
        $contract = $stmt->fetch();

        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM co_tenants
             WHERE tenancy_id = ? AND status != 'signed' AND status != 'removed'
        ");
        $stmt->execute([(int)$contract['tenancy_id']]);
        $unsignedTenants = (int)$stmt->fetchColumn();

        $allSigned = ($unsignedTenants === 0) && !empty($contract['landlord_signed_at']);

        if ($allSigned) {
            $pdo->prepare("UPDATE contracts SET status = 'active', activated_at = NOW() WHERE id = ?")
                ->execute([$contractId]);
            $pdo->prepare("UPDATE tenancies SET status = 'active' WHERE id = ?")
                ->execute([(int)$contract['tenancy_id']]);
            $pdo->prepare("UPDATE properties SET status = 'rented' WHERE id = ?")
                ->execute([(int)$contract['property_id']]);
            ensure_agent_commission_for_contract($contractId, 'earned');
        }

        $pdo->commit();

        if ($allSigned) {
            $pdfPath = generate_contract_pdf($contractId);
            $msg = 'Tenancy contract ' . $contract['contract_code'] . ' is now active!'
                . ($pdfPath ? ' The signed PDF is now downloadable.' : '');

            foreach ([(int)$contract['student_id'], (int)$contract['landlord_id']] as $uid) {
                notify($uid, 'contract_active', 'Contract activated', $msg,
                    '' . BASE_PATH . '/contracts/view.php?id=' . $contractId);
            }
            $stmt = $pdo->prepare("SELECT student_id FROM co_tenants WHERE tenancy_id = ? AND student_id IS NOT NULL");
            $stmt->execute([(int)$contract['tenancy_id']]);
            foreach ($stmt->fetchAll() as $ct) {
                notify((int)$ct['student_id'], 'contract_active', 'Contract activated', $msg,
                    '' . BASE_PATH . '/contracts/view.php?id=' . $contractId);
            }
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
 *  Link-based signing for co-tenants without an account
 * ============================================================ */

/**
 * Give every account-less co-tenant of a tenancy a secure signing token
 * (so they can sign via a link). Idempotent; safe no-op if the sign_token
 * column has not been added yet (migrations/add_cotenant_sign_token.sql).
 */
function ensure_cotenant_sign_tokens(int $tenancyId): void {
    try {
        $pdo = db();
        $stmt = $pdo->prepare("
            SELECT id FROM co_tenants
             WHERE tenancy_id = ? AND student_id IS NULL
               AND (sign_token IS NULL OR sign_token = '')
        ");
        $stmt->execute([$tenancyId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $id) {
            $pdo->prepare("UPDATE co_tenants SET sign_token = ? WHERE id = ?")
                ->execute([bin2hex(random_bytes(24)), (int)$id]);
        }
    } catch (Throwable $e) {
        // sign_token column missing (migration not applied yet) — ignore.
    }
}

/** Absolute path to the link-signing page for a token. */
function cotenant_sign_url(string $token): string {
    return '' . BASE_PATH . '/contracts/sign_link.php?token=' . urlencode($token);
}

/**
 * Email the signing link to each account-less co-tenant who still needs to sign.
 * Triggered by the agent from the contract page (not automatic).
 * Returns ['sent'=>int, 'skipped'=>int, 'errors'=>string[]].
 */
function send_cotenant_sign_links(int $contractId): array {
    require_once __DIR__ . '/mailer.php';
    $pdo = db();

    $stmt = $pdo->prepare("
        SELECT c.contract_code, c.tenancy_id, p.title AS property_title
          FROM contracts c JOIN properties p ON p.id = c.property_id
         WHERE c.id = ? LIMIT 1
    ");
    $stmt->execute([$contractId]);
    $c = $stmt->fetch();
    if (!$c) return ['sent'=>0,'skipped'=>0,'errors'=>['Contract not found.']];

    ensure_cotenant_sign_tokens((int)$c['tenancy_id']);

    $stmt = $pdo->prepare("
        SELECT id, full_name, email, sign_token
          FROM co_tenants
         WHERE tenancy_id = ? AND student_id IS NULL AND status != 'signed'
    ");
    $stmt->execute([(int)$c['tenancy_id']]);
    $rows = $stmt->fetchAll();

    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $sent = 0; $skipped = 0; $errors = [];

    foreach ($rows as $r) {
        if (empty($r['email']) || empty($r['sign_token'])) { $skipped++; continue; }
        $url  = $scheme . '://' . $host . cotenant_sign_url($r['sign_token']);
        $name = htmlspecialchars($r['full_name'], ENT_QUOTES);
        $code = htmlspecialchars($c['contract_code'], ENT_QUOTES);
        $prop = htmlspecialchars($c['property_title'], ENT_QUOTES);
        $safe = htmlspecialchars($url, ENT_QUOTES);
        $subject = 'Sign your tenancy contract ' . $c['contract_code'];
        $html = "<p>Hi {$name},</p>"
              . "<p>You are named as a co-tenant on the tenancy for <strong>{$prop}</strong> "
              . "(contract {$code}).</p>"
              . "<p>Please review and sign the contract using your secure link below:</p>"
              . "<p><a href=\"{$safe}\" style=\"display:inline-block;padding:10px 18px;background:#2E8B57;color:#fff;text-decoration:none;border-radius:6px;\">Sign the contract</a></p>"
              . "<p style=\"font-size:12px;color:#888\">If the button does not work, copy this link:<br>{$safe}</p>"
              . "<p>&mdash; RentBridge</p>";
        $plain = "Hi {$r['full_name']},\n\nSign your tenancy contract {$c['contract_code']} here:\n{$url}\n\n- RentBridge";
        $res = send_email($r['email'], $r['full_name'], $subject, $html, $plain);
        if (!empty($res['ok'])) $sent++;
        else $errors[] = $r['full_name'] . ': ' . ($res['error'] ?? 'send failed');
    }
    return ['sent'=>$sent, 'skipped'=>$skipped, 'errors'=>$errors];
}

/**
 * Apply a signature to a contract via a co-tenant signing token (no login).
 * Enforces the same signing order as apply_signature(): the token holder can
 * only sign when they are the next unsigned party.
 *
 * Returns: ['success'=>bool, 'all_signed'=>bool, 'message'=>string,
 *           'wait'=>bool (true when it is not their turn yet)]
 */
function apply_signature_by_token(string $token, string $dataUrl): array {
    $token = trim($token);
    if ($token === '') return ['success'=>false,'all_signed'=>false,'message'=>'Missing signing token.'];

    $pdo = db();
    $stmt = $pdo->prepare("
        SELECT ct.id AS co_tenant_id, ct.tenancy_id, ct.status AS ct_status
          FROM co_tenants ct
         WHERE ct.sign_token = ? LIMIT 1
    ");
    $stmt->execute([$token]);
    $row = $stmt->fetch();
    if (!$row)                      return ['success'=>false,'all_signed'=>false,'message'=>'Invalid signing link.'];
    if ($row['ct_status'] === 'signed') return ['success'=>false,'all_signed'=>false,'message'=>'You have already signed this contract.'];

    $coTenantId = (int)$row['co_tenant_id'];
    $stmt = $pdo->prepare("SELECT * FROM contracts WHERE tenancy_id = ? LIMIT 1");
    $stmt->execute([(int)$row['tenancy_id']]);
    $contract = $stmt->fetch();
    if (!$contract)                              return ['success'=>false,'all_signed'=>false,'message'=>'Contract not found.'];
    if ($contract['status'] !== 'pending_signatures') return ['success'=>false,'all_signed'=>false,'message'=>'This contract is no longer accepting signatures.'];

    $next = contract_next_signer($contract);
    if ($next['role'] !== 'tenant' || (int)$next['co_tenant_id'] !== $coTenantId) {
        return ['success'=>false,'all_signed'=>false,'wait'=>true,
                'message'=>'It is not your turn yet. Please wait until the earlier signer(s) have signed.'];
    }

    $contractId = (int)$contract['id'];
    try {
        $sigPath = save_signature_image($dataUrl, $contractId, 'tenant_' . $coTenantId);
    } catch (RuntimeException $e) {
        return ['success'=>false,'all_signed'=>false,'message'=>$e->getMessage()];
    }

    try {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE co_tenants SET status='signed', signed_at=NOW(), signature_data=? WHERE id=?")
            ->execute([$sigPath, $coTenantId]);

        $stmt = $pdo->prepare('SELECT * FROM contracts WHERE id = ? LIMIT 1');
        $stmt->execute([$contractId]);
        $contract = $stmt->fetch();

        $u = $pdo->prepare("SELECT COUNT(*) FROM co_tenants WHERE tenancy_id = ? AND status != 'signed'");
        $u->execute([(int)$contract['tenancy_id']]);
        $allSigned = ((int)$u->fetchColumn() === 0) && !empty($contract['landlord_signed_at']);

        if ($allSigned) {
            $pdo->prepare('UPDATE contracts SET status=\'active\', activated_at=NOW() WHERE id=?')->execute([$contractId]);
            $pdo->prepare('UPDATE tenancies SET status=\'active\' WHERE id=?')->execute([(int)$contract['tenancy_id']]);
            ensure_agent_commission_for_contract($contractId, 'earned');
        }
        $pdo->commit();

        if ($allSigned) {
            $pdfPath = generate_contract_pdf($contractId);
            $msg = 'Tenancy contract ' . $contract['contract_code'] . ' is now active!'
                 . ($pdfPath ? ' The signed PDF is now downloadable.' : '');
            foreach ([(int)$contract['landlord_id'], (int)$contract['agent_id']] as $uid) {
                if ($uid > 0) notify($uid, 'contract_active', 'Contract activated', $msg,
                    '' . BASE_PATH . '/contracts/view.php?id=' . $contractId);
            }
        } else {
            $nx = contract_next_signer($contract);
            if (!empty($nx['user_id'])) {
                notify($nx['user_id'], 'contract_your_turn', 'It is your turn to sign',
                    'Contract ' . $contract['contract_code'] . ' is ready for your signature.',
                    '' . BASE_PATH . '/contracts/view.php?id=' . $contractId);
            }
        }
        return ['success'=>true,'all_signed'=>$allSigned,
                'message'=>$allSigned ? 'Contract activated!' : 'Signature saved.',
                'contract_code'=>$contract['contract_code']];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        @unlink(__DIR__ . '/../' . $sigPath);
        return ['success'=>false,'all_signed'=>false,'message'=>'Database error: ' . $e->getMessage()];
    }
}

/* ============================================================
 *  Content fingerprint (staleness/integrity check)
 * ============================================================ */

/**
 * Build the rb_agreement_html() input array for a tenancy's contract right
 * now — embedding whatever real e-signatures currently exist (blank line for
 * anyone who hasn't signed, or chose physical signing). This is the single
 * source of truth for "what should the contract PDF contain", used both to
 * actually render it (agent/generate_contract.php) and to fingerprint its
 * content for the mixed-signing merge's staleness check, without needing to
 * render anything.
 *
 * @return array|null null if the tenancy or its contract don't exist yet.
 */
function build_contract_agreement_data(int $tenancyId): ?array {
    require_once __DIR__ . '/co_tenants.php';
    $pdo = db();

    $stmt = $pdo->prepare("
        SELECT b.*,
               p.address AS property_address, p.city AS property_city,
               p.state AS property_state, p.postcode AS property_postcode,
               p.property_type AS property_type,
               l.full_name AS landlord_name, l.ic_no AS landlord_ic, l.phone AS landlord_phone
          FROM tenancies b
          JOIN properties p ON p.id = b.property_id
          JOIN landlords l  ON l.user_id = b.landlord_id
         WHERE b.id = ?
         LIMIT 1
    ");
    $stmt->execute([$tenancyId]);
    $tenancy = $stmt->fetch();
    if (!$tenancy) return null;

    $stmt = $pdo->prepare("
        SELECT id, contract_code, landlord_signature, landlord_signed_at
          FROM contracts WHERE tenancy_id = ? LIMIT 1
    ");
    $stmt->execute([$tenancyId]);
    $contract = $stmt->fetch();
    if (!$contract) return null;

    $coTenants = get_co_tenants($tenancyId);

    $startTs    = strtotime($tenancy['start_date']);
    $endTs      = strtotime($tenancy['end_date']);
    $termMonths = max(1, (int)round(($endTs - $startTs) / (30.44 * 86400)));
    $termLabel  = match($tenancy['duration_type']) {
        'three_semesters' => '13 months (3 semesters)',
        'four_semesters'  => '18 months (4 semesters)',
        'two_years'       => '24 months (2 years)',
        'three_years'     => '36 months (3 years)',
        '1_semester'      => '5 months (1 semester)',
        '2_semesters'     => '10 months (2 semesters)',
        '1_year'          => '12 months (1 year)',
        'custom'          => $termMonths . ' months',
        default           => $termMonths . ' months',
    };
    $propertyAddress = $tenancy['property_address'] . ', ' . $tenancy['property_city'] . ' '
        . $tenancy['property_postcode'] . ', ' . $tenancy['property_state'];

    $absSig = function (?string $rel): ?string {
        if (empty($rel)) return null;
        return rb_storage_data_uri($rel, 'image/png');
    };

    $coTenantsData = [];
    foreach ($coTenants as $ct) {
        $coTenantsData[] = [
            'full_name'  => $ct['full_name'],
            'ic_number'  => $ct['ic_number'],
            'phone'      => $ct['phone'] ?? '',
            'is_primary' => (int)$ct['is_primary'],
            'sig_img'    => $absSig($ct['signature_data'] ?? null),
            'sig_date'   => !empty($ct['signed_at']) ? date('d M Y', strtotime($ct['signed_at'])) : null,
        ];
    }

    return [
        'contract_code'    => $contract['contract_code'],
        'today'            => date('jS \\d\\a\\y \\o\\f F Y'),
        'landlord_name'    => $tenancy['landlord_name'],
        'landlord_ic'      => $tenancy['landlord_ic'],
        'landlord_phone'   => $tenancy['landlord_phone'] ?? '',
        'property_type'    => $tenancy['property_type'],
        'property_address' => $propertyAddress,
        'term_label'       => $termLabel,
        'start_short'      => date('d/m/Y', $startTs),
        'end_short'        => date('d/m/Y', $endTs),
        'monthly_rent'     => number_format((float)$tenancy['monthly_rent'], 2),
        'security_deposit' => number_format((float)$tenancy['deposit'], 2),
        'utility_deposit'  => number_format((float)$tenancy['deposit'] * 0.3, 2),
        'tenancy_label'    => 'TENANTS',
        'landlord_sig_img' => $absSig($contract['landlord_signature'] ?? null),
        'landlord_sig_date'=> !empty($contract['landlord_signed_at'])
                                ? date('d M Y', strtotime($contract['landlord_signed_at']))
                                : null,
        'co_tenants'       => $coTenantsData,
    ];
}

/**
 * A stable content fingerprint of what a contract PDF would contain right
 * now. Excludes 'today' (deliberately just the render timestamp — comparing
 * raw PDF bytes doesn't work for this because mPDF embeds its own creation
 * timestamp in every render, which differs on every call even when nothing
 * meaningful changed). Two calls with identical rent/dates/parties/signatures
 * produce the same fingerprint regardless of when they're rendered.
 */
function contract_agreement_fingerprint(array $data): string {
    unset($data['today']);
    return hash('sha256', json_encode($data));
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
        // Some shared hosts (e.g. InfinityFree) return a system temp dir from
        // sys_get_temp_dir() that PHP cannot actually write to, which makes
        // mPDF's font/temp cache fail silently. Use a directory inside our
        // own writable uploads tree instead.
        $mpdfTempDir = __DIR__ . '/../uploads/mpdf_tmp';
        if (!is_dir($mpdfTempDir)) {
            mkdir($mpdfTempDir, 0755, true);
        }
        $mpdf = new \Mpdf\Mpdf([
            'tempDir' => $mpdfTempDir,
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

        $filename = $contractCode . '_' . time() . '.pdf';
        $relPath  = 'uploads/' . $subDir . '/' . $filename;
        $pdfBytes = $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
        if (!rb_storage_put_contents($pdfBytes, $relPath)) {
            throw new RuntimeException('Could not save the rendered PDF.');
        }
        return $relPath;
    } catch (Throwable $e) {
        error_log('Agreement PDF render failed: ' . $e->getMessage());
        throw new RuntimeException('Failed to render the contract PDF: ' . $e->getMessage(), 0, $e);
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

    // Helper: resolve a stored signature path to a data: URI mPDF can embed
    // directly — works whether the file lives on local disk or in R2.
    $absSig = function (?string $rel): ?string {
        if (empty($rel)) return null;
        return rb_storage_data_uri($rel, 'image/png');
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

        // sys_get_temp_dir() can point somewhere unwritable on shared hosts
        // (see rb_render_agreement_pdf()'s equivalent fix) — use the app's
        // own writable uploads tree instead.
        $mpdfTempDir = __DIR__ . '/../uploads/mpdf_tmp';
        if (!is_dir($mpdfTempDir)) {
            mkdir($mpdfTempDir, 0755, true);
        }
        $mpdf = new \Mpdf\Mpdf([
            'tempDir'      => $mpdfTempDir,
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

        $filename = $c['contract_code'] . '.pdf';
        $relPath  = 'uploads/contracts/' . $filename;
        $pdfBytes = $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
        if (!rb_storage_put_contents($pdfBytes, $relPath)) return null;

        $pdo->prepare('UPDATE contracts SET contract_pdf_path = ? WHERE id = ?')
            ->execute([$relPath, $contractId]);

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
            "" . BASE_PATH . "/contracts/view.php?id={$c['id']}"
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
            "" . BASE_PATH . "/contracts/view.php?id={$c['id']}"
        );
    }
}
