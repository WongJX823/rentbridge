<?php
require_once __DIR__ . '/auth.php';

const ASSIGNMENT_TIMEOUT_HOURS = 24;

/**
 * Pick the next agent for a property — FIFO by pending-review workload.
 * Strategy:
 *   1. Order agents by number of currently-pending property assignments ASC
 *      (fewest pending reviews goes first), ties broken by user_id ASC.
 *   2. Skip the currently-assigned agent.
 *   3. Skip any agent who already has a terminal outcome for this property
 *      (passed, rejected, timeout, reassigned).
 */
function pick_next_agent_for_property(int $propertyId, ?int $currentAgentId = null): ?int {
    $pdo = db();

    // Agents ordered by pending review workload (FIFO)
    $stmt = $pdo->query("
        SELECT a.user_id
          FROM agents a
          JOIN users u ON u.id = a.user_id
          LEFT JOIN property_agent_assignments paa
            ON paa.agent_id = a.user_id AND paa.outcome = 'pending'
         WHERE u.primary_role = 'agent'
           AND u.status = 'active'
           AND a.availability != 'off_duty'
         GROUP BY a.user_id
         ORDER BY COUNT(paa.id) ASC, a.user_id ASC
    ");
    $allAgents = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($allAgents)) return null;

    // Agents who already have a terminal outcome for this property
    $stmt = $pdo->prepare("
        SELECT DISTINCT agent_id
          FROM property_agent_assignments
         WHERE property_id = ?
           AND outcome IN ('passed', 'rejected_listing', 'timeout', 'reassigned')
    ");
    $stmt->execute([$propertyId]);
    $excluded = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    if ($currentAgentId !== null) {
        $excluded[] = $currentAgentId;
    }

    foreach ($allAgents as $agentId) {
        if (!in_array((int)$agentId, $excluded, true)) {
            return (int)$agentId;
        }
    }
    return null;
}

/**
 * Assign an agent to a property. Creates audit row and updates property.
 * Returns ['ok' => bool, 'agent_id' => int|null, 'error' => string|null]
 */
function assign_agent_to_property(int $propertyId): array {
    $pdo = db();

    // Get current state
    $stmt = $pdo->prepare("
        SELECT assigned_agent_id, assignment_round
          FROM properties
         WHERE id = ?
    ");
    $stmt->execute([$propertyId]);
    $prop = $stmt->fetch();
    if (!$prop) return ['ok' => false, 'agent_id' => null, 'error' => 'Property not found'];

    $currentAgentId = $prop['assigned_agent_id'] ? (int)$prop['assigned_agent_id'] : null;
    $currentRound   = (int)$prop['assignment_round'];

    $nextAgentId = pick_next_agent_for_property($propertyId, $currentAgentId);

    if ($nextAgentId === null) {
        // No more agents available — escalate to admin
        $stmt = $pdo->prepare("
            UPDATE properties
               SET assigned_agent_id = NULL,
                   agent_status = NULL,
                   status = 'needs_admin'
             WHERE id = ?
        ");
        $stmt->execute([$propertyId]);
        return ['ok' => false, 'agent_id' => null, 'error' => 'No agents available; escalated to admin'];
    }

    $newRound = $currentRound + 1;

    // Update property
    $stmt = $pdo->prepare("
        UPDATE properties
           SET assigned_agent_id = ?,
               agent_assigned_at = NOW(),
               agent_status = 'pending',
               assignment_round = ?
         WHERE id = ?
    ");
    $stmt->execute([$nextAgentId, $newRound, $propertyId]);

    // Insert audit row
    $stmt = $pdo->prepare("
        INSERT INTO property_agent_assignments
            (property_id, agent_id, round_number, outcome)
        VALUES (?, ?, ?, 'pending')
    ");
    $stmt->execute([$propertyId, $nextAgentId, $newRound]);

    // Notify the new agent
    if (function_exists('notify')) {
        notify(
            $nextAgentId,
            'property_assignment',
            'New property assigned for review',
            "You've been assigned to review property #{$propertyId}",
            "/rentbridge/agent/property_review.php?id={$propertyId}"
        );
    }

    return ['ok' => true, 'agent_id' => $nextAgentId, 'error' => null];
}

/**
 * Agent accepts an assignment and starts the inspection process.
 * Creates (or finds) the agent↔landlord conversation for this property,
 * posts a system_notice, and sets agent_status = 'inspecting'.
 * Property does NOT go live yet — that happens after inspection via agent_approve_listing().
 */
function agent_accept_property(int $propertyId, int $agentId): array {
    $pdo = db();

    $stmt = $pdo->prepare("
        SELECT p.assigned_agent_id, p.agent_status, p.landlord_id, p.title
          FROM properties p
         WHERE p.id = ?
    ");
    $stmt->execute([$propertyId]);
    $prop = $stmt->fetch();

    if (!$prop) return ['ok' => false, 'error' => 'Property not found'];
    if ((int)$prop['assigned_agent_id'] !== $agentId) {
        return ['ok' => false, 'error' => 'You are not the assigned agent'];
    }
    if ($prop['agent_status'] !== 'pending') {
        return ['ok' => false, 'error' => 'This assignment is no longer pending'];
    }

    $landlordId = (int)$prop['landlord_id'];

    // Update property → inspecting (inspection in progress, not live yet)
    $stmt = $pdo->prepare("
        UPDATE properties
           SET agent_status = 'inspecting'
         WHERE id = ?
    ");
    $stmt->execute([$propertyId]);

    // Update audit row
    $stmt = $pdo->prepare("
        UPDATE property_agent_assignments
           SET outcome = 'accepted',
               responded_at = NOW()
         WHERE property_id = ? AND agent_id = ? AND outcome = 'pending'
         ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$propertyId, $agentId]);

    // Find or create the agent↔landlord conversation for this property
    require_once __DIR__ . '/chat.php';
    $lo  = min($agentId, $landlordId);
    $hi  = max($agentId, $landlordId);

    $stmt = $pdo->prepare("
        SELECT id FROM conversations
         WHERE user_a = ? AND user_b = ?
           AND (property_id <=> ?) AND context_type = 'agent_case'
         LIMIT 1
    ");
    $stmt->execute([$lo, $hi, $propertyId]);
    $convoId = $stmt->fetchColumn();

    if (!$convoId) {
        $stmt = $pdo->prepare("
            INSERT INTO conversations (user_a, user_b, property_id, tenancy_id, context_type)
            VALUES (?, ?, ?, NULL, 'agent_case')
        ");
        $stmt->execute([$lo, $hi, $propertyId]);
        $convoId = (int)$pdo->lastInsertId();
    }

    // Fetch agent's display name
    $stmt = $pdo->prepare("SELECT full_name FROM agents WHERE user_id = ?");
    $stmt->execute([$agentId]);
    $agentName = $stmt->fetchColumn() ?: 'Agent';

    // Post system_notice in the conversation
    $noticeBody = "Agent {$agentName} accepted this property for inspection — propose an inspection time below.";
    $stmt = $pdo->prepare("
        INSERT INTO messages (conversation_id, sender_id, body, message_type, sent_at)
        VALUES (?, ?, ?, 'system_notice', NOW())
    ");
    $stmt->execute([$convoId, $agentId, $noticeBody]);
    $pdo->prepare("UPDATE conversations SET last_message_at = NOW(), last_message_preview = ?, last_sender_id = ? WHERE id = ?")
        ->execute([substr($noticeBody, 0, 120), $agentId, $convoId]);

    // Notify landlord
    if ($landlordId && function_exists('notify')) {
        notify(
            $landlordId,
            'agent_accepted',
            'Agent accepted your property for inspection',
            "Agent {$agentName} has accepted your property \"{$prop['title']}\" and will schedule an inspection.",
            "/rentbridge/chat/conversation.php?id={$convoId}"
        );
    }

    return ['ok' => true, 'conversation_id' => (int)$convoId];
}

/**
 * Agent marks the inspection as complete (physical visit done).
 * Sets inspection_completed_at. Approve/reject decision can now be made.
 */
function agent_complete_inspection(int $propertyId, int $agentId): array {
    $pdo = db();

    $stmt = $pdo->prepare("
        SELECT assigned_agent_id, agent_status, landlord_id, title
          FROM properties WHERE id = ?
    ");
    $stmt->execute([$propertyId]);
    $prop = $stmt->fetch();

    if (!$prop) return ['ok' => false, 'error' => 'Property not found'];
    if ((int)$prop['assigned_agent_id'] !== $agentId) {
        return ['ok' => false, 'error' => 'You are not the assigned agent'];
    }
    if (!in_array($prop['agent_status'], ['inspecting','pending'], true)) {
        return ['ok' => false, 'error' => 'Not in inspection phase'];
    }

    $stmt = $pdo->prepare("
        UPDATE properties SET inspection_completed_at = NOW() WHERE id = ?
    ");
    $stmt->execute([$propertyId]);

    // Post a system_notice in the agent↔landlord conversation
    $landlordId = (int)$prop['landlord_id'];
    $lo  = min($agentId, $landlordId);
    $hi  = max($agentId, $landlordId);
    $stmt = $pdo->prepare("
        SELECT id FROM conversations
         WHERE user_a = ? AND user_b = ? AND (property_id <=> ?) AND context_type = 'agent_case'
         LIMIT 1
    ");
    $stmt->execute([$lo, $hi, $propertyId]);
    $convoId = $stmt->fetchColumn();

    if ($convoId) {
        $body = "Inspection complete. The agent is now reviewing the property for final approval.";
        $pdo->prepare("INSERT INTO messages (conversation_id, sender_id, body, message_type, sent_at) VALUES (?, ?, ?, 'system_notice', NOW())")
            ->execute([(int)$convoId, $agentId, $body]);
        $pdo->prepare("UPDATE conversations SET last_message_at = NOW(), last_message_preview = ?, last_sender_id = ? WHERE id = ?")
            ->execute([substr($body, 0, 120), $agentId, (int)$convoId]);
    }

    return ['ok' => true];
}

/**
 * Agent approves the listing after completing inspection.
 * Marks property as 'available' (goes live).
 */
function agent_approve_listing(int $propertyId, int $agentId): array {
    $pdo = db();

    $stmt = $pdo->prepare("
        SELECT assigned_agent_id, agent_status, inspection_completed_at, landlord_id, title
          FROM properties WHERE id = ?
    ");
    $stmt->execute([$propertyId]);
    $prop = $stmt->fetch();

    if (!$prop) return ['ok' => false, 'error' => 'Property not found'];
    if ((int)$prop['assigned_agent_id'] !== $agentId) {
        return ['ok' => false, 'error' => 'You are not the assigned agent'];
    }
    if (empty($prop['inspection_completed_at'])) {
        return ['ok' => false, 'error' => 'Mark inspection complete before approving'];
    }

    $stmt = $pdo->prepare("
        UPDATE properties
           SET agent_status = 'accepted',
               status = 'available',
               agent_verified_at = NOW(),
               agent_verified_by = ?
         WHERE id = ?
    ");
    $stmt->execute([$agentId, $propertyId]);

    $landlordId = (int)$prop['landlord_id'];
    if ($landlordId && function_exists('notify')) {
        notify(
            $landlordId,
            'property_approved',
            'Your property is now live!',
            "Agent inspection passed. Your property \"{$prop['title']}\" is now listed on RentBridge.",
            "/rentbridge/landlord/properties.php"
        );
    }

    return ['ok' => true];
}

/**
 * Agent passes an assignment — "I can't handle this but it looks valid."
 * Triggers FIFO reassignment to the next available agent.
 * Property stays pending_approval.
 */
function agent_pass_property(int $propertyId, int $agentId, string $reason = ''): array {
    $pdo = db();

    $stmt = $pdo->prepare("SELECT assigned_agent_id, agent_status FROM properties WHERE id = ?");
    $stmt->execute([$propertyId]);
    $prop = $stmt->fetch();

    if (!$prop) return ['ok' => false, 'error' => 'Property not found'];
    if ((int)$prop['assigned_agent_id'] !== $agentId) {
        return ['ok' => false, 'error' => 'You are not the assigned agent'];
    }
    if ($prop['agent_status'] !== 'pending') {
        return ['ok' => false, 'error' => 'This assignment is no longer pending'];
    }

    // Mark audit row as passed
    $stmt = $pdo->prepare("
        UPDATE property_agent_assignments
           SET outcome = 'passed',
               responded_at = NOW(),
               rejection_reason = ?
         WHERE property_id = ? AND agent_id = ? AND outcome = 'pending'
         ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$reason, $propertyId, $agentId]);

    // Reassign to next agent
    $result = assign_agent_to_property($propertyId);

    return [
        'ok'            => true,
        'reassigned_to' => $result['agent_id'] ?? null,
        'no_agents_left'=> ($result['agent_id'] === null),
    ];
}

/**
 * Agent rejects the listing outright — fake docs, scam, illegal property.
 * Sets status = 'rejected', notifies landlord, no reassignment.
 */
function agent_reject_listing(int $propertyId, int $agentId, string $reason = ''): array {
    $pdo = db();

    $stmt = $pdo->prepare("SELECT assigned_agent_id, agent_status, landlord_id FROM properties WHERE id = ?");
    $stmt->execute([$propertyId]);
    $prop = $stmt->fetch();

    if (!$prop) return ['ok' => false, 'error' => 'Property not found'];
    if ((int)$prop['assigned_agent_id'] !== $agentId) {
        return ['ok' => false, 'error' => 'You are not the assigned agent'];
    }
    if (!in_array($prop['agent_status'], ['pending', 'inspecting'], true)) {
        return ['ok' => false, 'error' => 'This assignment is no longer active'];
    }

    // Mark property as rejected — no reassignment
    $stmt = $pdo->prepare("
        UPDATE properties
           SET agent_status = 'rejected',
               status = 'rejected'
         WHERE id = ?
    ");
    $stmt->execute([$propertyId]);

    // Mark audit row
    $stmt = $pdo->prepare("
        UPDATE property_agent_assignments
           SET outcome = 'rejected_listing',
               responded_at = NOW(),
               rejection_reason = ?
         WHERE property_id = ? AND agent_id = ? AND outcome = 'pending'
         ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$reason, $propertyId, $agentId]);

    // Notify landlord
    $landlordId = (int)$prop['landlord_id'];
    if ($landlordId && function_exists('notify')) {
        notify(
            $landlordId,
            'property_rejected',
            'Your property listing was rejected',
            "Your property listing has been rejected by our agent. Reason: {$reason}",
            "/rentbridge/landlord/properties.php"
        );
    }

    return ['ok' => true];
}

/**
 * Lazy timeout check — called on dashboard loads.
 * Finds pending assignments older than 24h and reassigns.
 */
function check_and_reassign_timeouts(): int {
    $pdo = db();
    $count = 0;

    $stmt = $pdo->prepare("
        SELECT id, assigned_agent_id
          FROM properties
         WHERE agent_status = 'pending'
           AND agent_assigned_at < DATE_SUB(NOW(), INTERVAL ? HOUR)
    ");
    $stmt->execute([ASSIGNMENT_TIMEOUT_HOURS]);
    $stale = $stmt->fetchAll();

    foreach ($stale as $row) {
        // Mark current as timeout
        $stmt = $pdo->prepare("
            UPDATE property_agent_assignments
               SET outcome = 'timeout',
                   responded_at = NOW()
             WHERE property_id = ? AND agent_id = ? AND outcome = 'pending'
             ORDER BY id DESC LIMIT 1
        ");
        $stmt->execute([$row['id'], $row['assigned_agent_id']]);

        // Reassign
        assign_agent_to_property((int)$row['id']);
        $count++;
    }

    return $count;
}