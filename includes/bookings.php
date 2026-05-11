<?php
require_once __DIR__ . '/auth.php';

/* ============================================================
 *  Booking helpers — agent auto-assignment algorithm
 *
 *  This is the heart of the Witnessed Tenancy Model.
 *  Find an available UTeM staff agent, excluding conflicts,
 *  ordered by workload to balance fairly.
 * ============================================================ */

/**
 * Attempt to auto-assign an agent to a booking that is in 'pending_agent' status.
 *
 * Returns the assigned agent's user_id, or null if no eligible agent was found
 * (in which case status stays 'pending_agent' and admin should intervene).
 *
 * Rules:
 *   1. Agent must be active (status='active') and available
 *   2. Caseload < max_caseload
 *   3. NOT in this booking's rejected_agents history
 *   4. NOT the same person as the landlord (conflict-of-interest)
 *   5. Tie-breaker: lowest caseload first, then random
 */
function auto_assign_agent(int $bookingId): ?int {
    $pdo = db();

    // Get booking + its rejected_agents list + landlord_id
    $stmt = $pdo->prepare(
        'SELECT id, landlord_id, rejected_agents, status
           FROM bookings WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch();

    if (!$booking)                                return null;
    if ($booking['status'] !== 'pending_agent')   return null;

    // Build exclusion list: rejected agents + the landlord
    $rejected = [];
    if (!empty($booking['rejected_agents'])) {
        $decoded = json_decode($booking['rejected_agents'], true);
        if (is_array($decoded)) $rejected = array_map('intval', $decoded);
    }
    $rejected[] = (int)$booking['landlord_id'];
    $rejected = array_unique($rejected);

    // Build the NOT IN placeholder dynamically
    $excludeSql = '';
    if (!empty($rejected)) {
        $placeholders = implode(',', array_fill(0, count($rejected), '?'));
        $excludeSql = " AND a.user_id NOT IN ($placeholders)";
    }

    // Find best candidate
    $sql = "
        SELECT a.user_id, a.current_caseload, a.max_caseload
          FROM agents a
          JOIN users u ON u.id = a.user_id
         WHERE u.status       = 'active'
           AND a.availability = 'available'
           AND a.current_caseload < a.max_caseload
           $excludeSql
         ORDER BY a.current_caseload ASC, RAND()
         LIMIT 1
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($rejected);
    $agent = $stmt->fetch();

    if (!$agent) {
        // No eligible agent — admin should be alerted
        notify_admins_no_agent($bookingId);
        return null;
    }

    $agentUserId = (int)$agent['user_id'];

    // Assign agent + increment caseload (transaction for safety)
    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('UPDATE bookings SET agent_id = ? WHERE id = ?');
        $stmt->execute([$agentUserId, $bookingId]);

        $stmt = $pdo->prepare(
            'UPDATE agents SET current_caseload = current_caseload + 1 WHERE user_id = ?'
        );
        $stmt->execute([$agentUserId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return null;
    }

    // Notify the assigned agent
    notify(
        $agentUserId,
        'agent_assignment',
        'New case waiting for your acceptance',
        'You have been assigned as the witness agent for a new booking (#' . $bookingId . ').',
        '/rentbridge/agent/cases.php'
    );

    return $agentUserId;
}

/**
 * Mark the current agent as rejected and try to find a replacement.
 * Called when an agent declines a case.
 */
function reassign_agent(int $bookingId, int $rejectingAgentId, string $reason = ''): ?int {
    $pdo = db();

    $stmt = $pdo->prepare(
        'SELECT id, rejected_agents, agent_id, status
           FROM bookings WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch();

    if (!$booking || (int)$booking['agent_id'] !== $rejectingAgentId)  return null;
    if ($booking['status'] !== 'pending_agent')                         return null;

    // Append this agent to the rejection list
    $rejected = [];
    if (!empty($booking['rejected_agents'])) {
        $decoded = json_decode($booking['rejected_agents'], true);
        if (is_array($decoded)) $rejected = array_map('intval', $decoded);
    }
    if (!in_array($rejectingAgentId, $rejected, true)) {
        $rejected[] = $rejectingAgentId;
    }

    try {
        $pdo->beginTransaction();

        // Clear current agent + save rejection history
        $stmt = $pdo->prepare(
            'UPDATE bookings
                SET agent_id = NULL,
                    rejected_agents = ?
              WHERE id = ?'
        );
        $stmt->execute([json_encode($rejected), $bookingId]);

        // Decrement the rejecting agent's caseload
        $stmt = $pdo->prepare(
            'UPDATE agents SET current_caseload = GREATEST(0, current_caseload - 1)
              WHERE user_id = ?'
        );
        $stmt->execute([$rejectingAgentId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return null;
    }

    // Try to find the next agent
    return auto_assign_agent($bookingId);
}

/**
 * Notify all admins when no agents can be assigned (escalation).
 */
function notify_admins_no_agent(int $bookingId): void {
    $stmt = db()->prepare("SELECT id FROM users WHERE primary_role = 'admin' AND status = 'active'");
    $stmt->execute();
    $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($admins as $adminId) {
        notify(
            (int)$adminId,
            'no_agent_available',
            'Booking needs manual agent assignment',
            'No eligible agent could be auto-assigned to booking #' . $bookingId . '. Please review.',
            '/rentbridge/admin/dashboard.php'
        );
    }
}