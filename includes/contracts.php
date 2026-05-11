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