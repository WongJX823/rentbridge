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
    // Find agent with lowest caseload + available
    $stmt = $pdo->prepare("
        SELECT user_id FROM agents
         WHERE availability = 'available'
           AND current_caseload < max_caseload
         ORDER BY current_caseload ASC, RAND()
         LIMIT 1
    ");
    $stmt->execute();
    $agentId = $stmt->fetchColumn();
    
    if (!$agentId) return null;
    
    $pdo->beginTransaction();
    try {
        // Assign
        $stmt = $pdo->prepare("UPDATE bookings SET agent_id = ?, status = 'agent_assigned' WHERE id = ?");
        $stmt->execute([$agentId, $bookingId]);
        
        // Increment caseload
        $stmt = $pdo->prepare("UPDATE agents SET current_caseload = current_caseload + 1 WHERE user_id = ?");
        $stmt->execute([$agentId]);
        
        // Notify agent
        notify((int)$agentId, 'agent_assigned', 'New case assigned',
            'You have been assigned to inspect a new tenancy.',
            '/rentbridge/agent/case.php?id=' . $bookingId);
        
        $pdo->commit();
        return (int)$agentId;
    } catch (Throwable $e) {
        $pdo->rollBack();
        return null;
    }
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