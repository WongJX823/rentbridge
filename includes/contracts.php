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
            // Everyone gets a "contract active" notification
            $msg = 'Tenancy contract ' . $contract['contract_code'] . ' is now active!';
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