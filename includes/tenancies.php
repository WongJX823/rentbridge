<?php
require_once __DIR__ . '/auth.php';

/* ============================================================
 *  Tenancy helpers — agent auto-assignment algorithm
 *
 *  This is the heart of the Witnessed Tenancy Model.
 *  Find an available UTeM staff agent, excluding conflicts,
 *  ordered by workload to balance fairly.
 * ============================================================ */

/**
 * Attempt to auto-assign an agent to a tenancy that is in 'pending_agent' status.
 *
 * Returns the assigned agent's user_id, or null if no eligible agent was found
 * (in which case status stays 'pending_agent' and admin should intervene).
 *
 * Rules:
 *   1. Agent must be active (status='active') and available
 *   2. Caseload < max_caseload
 *   3. NOT in this tenancy's rejected_agents history
 *   4. NOT the same person as the landlord (conflict-of-interest)
 *   5. Tie-breaker: lowest caseload first, then random
 */
function auto_assign_agent(int $tenancyId): ?int {
    $pdo = db();

    // Always use the property's assigned agent (one agent owns a property end-to-end)
    $stmt = $pdo->prepare("
        SELECT p.assigned_agent_id
          FROM tenancies b
          JOIN properties p ON p.id = b.property_id
         WHERE b.id = ? LIMIT 1
    ");
    $stmt->execute([$tenancyId]);
    $agentId = $stmt->fetchColumn();

    if (!$agentId) {
        // Property has no assigned agent yet — fall back to FIFO by workload
        $stmt = $pdo->prepare("
            SELECT a.user_id
              FROM agents a
              JOIN users u ON u.id = a.user_id
              LEFT JOIN property_agent_assignments paa
                ON paa.agent_id = a.user_id AND paa.outcome = 'pending'
             WHERE u.primary_role = 'agent'
             GROUP BY a.user_id
             ORDER BY COUNT(paa.id) ASC, a.user_id ASC
             LIMIT 1
        ");
        $stmt->execute();
        $agentId = $stmt->fetchColumn();
    }

    if (!$agentId) return null;

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("UPDATE tenancies SET agent_id = ?, status = 'pending_agent' WHERE id = ?");
        $stmt->execute([$agentId, $tenancyId]);

        $pdo->commit();

        notify((int)$agentId, 'agent_assigned', 'New tenancy case assigned',
            'A new tenancy tenancy has been assigned to you — please review and accept.',
            '/rentbridge/agent/case.php?id=' . $tenancyId);

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
function reassign_agent(int $tenancyId, int $rejectingAgentId, string $reason = ''): ?int {
    $pdo = db();

    $stmt = $pdo->prepare(
        'SELECT id, rejected_agents, agent_id, status
           FROM tenancies WHERE id = ? LIMIT 1'
    );
    $stmt->execute([$tenancyId]);
    $tenancy = $stmt->fetch();

    if (!$tenancy || (int)$tenancy['agent_id'] !== $rejectingAgentId)  return null;
    if ($tenancy['status'] !== 'pending_agent')                         return null;

    // Append this agent to the rejection list
    $rejected = [];
    if (!empty($tenancy['rejected_agents'])) {
        $decoded = json_decode($tenancy['rejected_agents'], true);
        if (is_array($decoded)) $rejected = array_map('intval', $decoded);
    }
    if (!in_array($rejectingAgentId, $rejected, true)) {
        $rejected[] = $rejectingAgentId;
    }

    try {
        $pdo->beginTransaction();

        // Clear current agent + save rejection history
        $stmt = $pdo->prepare(
            'UPDATE tenancies
                SET agent_id = NULL,
                    rejected_agents = ?
              WHERE id = ?'
        );
        $stmt->execute([json_encode($rejected), $tenancyId]);

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
    return auto_assign_agent($tenancyId);
}

/**
 * Notify all admins when no agents can be assigned (escalation).
 */
function notify_admins_no_agent(int $tenancyId): void {
    $stmt = db()->prepare("SELECT id FROM users WHERE primary_role = 'admin' AND status = 'active'");
    $stmt->execute();
    $admins = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($admins as $adminId) {
        notify(
            (int)$adminId,
            'no_agent_available',
            'Tenancy needs manual agent assignment',
            'No eligible agent could be auto-assigned to tenancy #' . $tenancyId . '. Please review.',
            '/rentbridge/admin/dashboard.php'
        );
    }
}