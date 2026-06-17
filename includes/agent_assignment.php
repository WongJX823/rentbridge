<?php
require_once __DIR__ . '/auth.php';

const ASSIGNMENT_TIMEOUT_HOURS = 24;

/**
 * Pick the next agent for a property.
 * Strategy: 
 *   1. Get list of all agent users, ordered by id ASC
 *   2. Skip agents who already declined this property
 *   3. Skip the currently-assigned agent (if any)
 *   4. Return the first eligible agent_id, or null if none available
 */
function pick_next_agent_for_property(int $propertyId, ?int $currentAgentId = null): ?int {
    $pdo = db();

    // Get all agents (by user_id ascending)
    $stmt = $pdo->query("
        SELECT u.id
          FROM users u
          JOIN agents a ON a.user_id = u.id
         WHERE u.primary_role = 'agent'
         ORDER BY u.id ASC
    ");
    $allAgents = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($allAgents)) return null;

    // Get agents who already have a non-pending outcome for this property
    $stmt = $pdo->prepare("
        SELECT DISTINCT agent_id
          FROM property_agent_assignments
         WHERE property_id = ?
           AND outcome IN ('rejected', 'timeout', 'reassigned')
    ");
    $stmt->execute([$propertyId]);
    $excluded = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if ($currentAgentId !== null) {
        $excluded[] = $currentAgentId;
    }

    foreach ($allAgents as $agentId) {
        if (!in_array((int)$agentId, array_map('intval', $excluded), true)) {
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
 * Agent accepts an assignment.
 * Marks property as 'available' (goes live).
 */
function agent_accept_property(int $propertyId, int $agentId): array {
    $pdo = db();

    // Verify the agent IS the currently assigned one
    $stmt = $pdo->prepare("
        SELECT assigned_agent_id, agent_status
          FROM properties
         WHERE id = ?
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

    // Update property → available
    $stmt = $pdo->prepare("
        UPDATE properties
           SET agent_status = 'accepted',
               status = 'available'
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

    // Notify the landlord
    $stmt = $pdo->prepare("SELECT landlord_id FROM properties WHERE id = ?");
    $stmt->execute([$propertyId]);
    $landlordId = (int)$stmt->fetchColumn();
    if ($landlordId && function_exists('notify')) {
        notify(
            $landlordId,
            'property_approved',
            'Your property is now live',
            "Your property has been verified and is now listed.",
            "/rentbridge/landlord/properties.php"
        );
    }

    return ['ok' => true];
}

/**
 * Agent rejects an assignment.
 * Triggers reassignment to next agent.
 */
function agent_reject_property(int $propertyId, int $agentId, string $reason = ''): array {
    $pdo = db();

    $stmt = $pdo->prepare("
        SELECT assigned_agent_id, agent_status, assignment_round
          FROM properties
         WHERE id = ?
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

    // Mark audit row as rejected
    $stmt = $pdo->prepare("
        UPDATE property_agent_assignments
           SET outcome = 'rejected',
               responded_at = NOW(),
               rejection_reason = ?
         WHERE property_id = ? AND agent_id = ? AND outcome = 'pending'
         ORDER BY id DESC LIMIT 1
    ");
    $stmt->execute([$reason, $propertyId, $agentId]);

    // Reassign to next agent
    $result = assign_agent_to_property($propertyId);

    return [
        'ok' => true,
        'reassigned_to' => $result['agent_id'] ?? null,
        'no_agents_left' => ($result['agent_id'] === null),
    ];
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