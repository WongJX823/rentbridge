<?php
require_once __DIR__ . '/auth.php';

const TRANSFER_BATCH_SIZE = 5;

/**
 * Dispatch the next batch of TRANSFER_BATCH_SIZE agents to be offered a transfer.
 * Excludes the requesting agent and any agent who already declined in a prior batch.
 */
function dispatch_transfer_batch(int $transferId): bool {
    $pdo = db();

    $stmt = $pdo->prepare("SELECT * FROM agent_transfer_requests WHERE id = ? LIMIT 1");
    $stmt->execute([$transferId]);
    $req = $stmt->fetch();
    if (!$req || !in_array($req['status'], ['approved', 'finding_agent'], true)) return false;

    // Agents already notified (any outcome)
    $stmt = $pdo->prepare("SELECT agent_id FROM agent_transfer_notifications WHERE transfer_request_id = ?");
    $stmt->execute([$transferId]);
    $alreadyNotified = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    $alreadyNotified[] = (int)$req['requesting_agent_id'];

    // FIFO: order by pending tenancies ASC
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
    $allAgents = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

    $candidates = array_filter($allAgents, fn($id) => !in_array($id, $alreadyNotified, true));
    $candidates = array_values(array_slice($candidates, 0, TRANSFER_BATCH_SIZE));

    if (empty($candidates)) {
        // No more agents — alert admin
        $pdo->prepare("UPDATE agent_transfer_requests SET status = 'finding_agent' WHERE id = ?")
            ->execute([$transferId]);

        $stmt = $pdo->query("SELECT id FROM users WHERE primary_role = 'admin'");
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $adminId) {
            notify((int)$adminId, 'transfer_no_agent',
                'No agents available for property transfer',
                'Transfer request #' . $transferId . ': all eligible agents have declined. Manual reassignment needed.',
                '/rentbridge/admin/transfers.php?id=' . $transferId
            );
        }
        return false;
    }

    $nextBatch = (int)$req['batch_number'] + 1;

    $pdo->beginTransaction();
    try {
        $pdo->prepare("UPDATE agent_transfer_requests SET status = 'finding_agent', batch_number = ? WHERE id = ?")
            ->execute([$nextBatch, $transferId]);

        $prop = $pdo->prepare("SELECT title FROM properties WHERE id = ? LIMIT 1");
        $prop->execute([$req['property_id']]);
        $propTitle = $prop->fetchColumn() ?: 'Property #' . $req['property_id'];

        $insStmt = $pdo->prepare("
            INSERT INTO agent_transfer_notifications (transfer_request_id, agent_id, batch_number)
            VALUES (?, ?, ?)
        ");
        foreach ($candidates as $agentId) {
            $insStmt->execute([$transferId, $agentId, $nextBatch]);
            notify($agentId, 'transfer_offered',
                'Property case transfer offered to you',
                'You have been offered to take over the case for "' . $propTitle . '". Accept or decline in My Cases.',
                '/rentbridge/agent/transfer_response.php?id=' . $transferId
            );
        }

        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return false;
    }
}

/**
 * Complete a transfer: agent $newAgentId accepts transfer request $transferId.
 */
function complete_transfer(int $transferId, int $newAgentId): bool {
    $pdo = db();

    $stmt = $pdo->prepare("
        SELECT atr.*, p.title AS property_title
          FROM agent_transfer_requests atr
          JOIN properties p ON p.id = atr.property_id
         WHERE atr.id = ? AND atr.status = 'finding_agent'
         LIMIT 1
    ");
    $stmt->execute([$transferId]);
    $req = $stmt->fetch();
    if (!$req) return false;

    // Verify agent was actually offered this transfer in the current batch
    $stmt = $pdo->prepare("
        SELECT id FROM agent_transfer_notifications
         WHERE transfer_request_id = ? AND agent_id = ? AND outcome = 'pending'
         LIMIT 1
    ");
    $stmt->execute([$transferId, $newAgentId]);
    if (!$stmt->fetch()) return false;

    $propertyId    = (int)$req['property_id'];
    $oldAgentId    = (int)$req['requesting_agent_id'];

    $pdo->beginTransaction();
    try {
        // Update property
        $pdo->prepare("UPDATE properties SET assigned_agent_id = ? WHERE id = ?")
            ->execute([$newAgentId, $propertyId]);

        // Re-assign all pending/active tenancies for this property
        $pdo->prepare("
            UPDATE tenancies
               SET agent_id = ?
             WHERE property_id = ?
               AND agent_id = ?
               AND status IN ('pending_agent','agent_assigned','agent_verifying','agent_verified','contract_pending','active')
        ")->execute([$newAgentId, $propertyId, $oldAgentId]);

        // Update also property_agent_assignments record
        $pdo->prepare("
            UPDATE property_agent_assignments
               SET outcome = 'reassigned'
             WHERE property_id = ? AND agent_id = ? AND outcome = 'pending'
        ")->execute([$propertyId, $oldAgentId]);

        // Mark this notification as accepted
        $pdo->prepare("
            UPDATE agent_transfer_notifications
               SET outcome = 'accepted', responded_at = NOW()
             WHERE transfer_request_id = ? AND agent_id = ?
        ")->execute([$transferId, $newAgentId]);

        // Decline all other pending notifications in same batch
        $pdo->prepare("
            UPDATE agent_transfer_notifications
               SET outcome = 'declined', responded_at = NOW()
             WHERE transfer_request_id = ? AND agent_id != ? AND outcome = 'pending'
        ")->execute([$transferId, $newAgentId]);

        // Close the request
        $pdo->prepare("
            UPDATE agent_transfer_requests
               SET status = 'completed', new_agent_id = ?
             WHERE id = ?
        ")->execute([$newAgentId, $transferId]);

        $pdo->commit();

        // Notify old agent, new agent, property owner
        notify($oldAgentId, 'transfer_completed',
            'Your property case has been transferred',
            '"' . $req['property_title'] . '" has been successfully handed to another agent.',
            '/rentbridge/agent/cases.php'
        );
        notify($newAgentId, 'transfer_accepted',
            'You are now handling "' . $req['property_title'] . '"',
            'You accepted the case transfer. All active tenancies for this property are now assigned to you.',
            '/rentbridge/agent/cases.php?tab=properties'
        );

        // Notify landlord
        $stmt = $pdo->prepare("SELECT landlord_id FROM properties WHERE id = ? LIMIT 1");
        $stmt->execute([$propertyId]);
        $landlordId = $stmt->fetchColumn();
        if ($landlordId) {
            notify((int)$landlordId, 'agent_changed',
                'Your property agent has changed',
                'A new agent has taken over the case for "' . $req['property_title'] . '".',
                '/rentbridge/landlord/properties.php'
            );
        }

        return true;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return false;
    }
}

/**
 * Agent declines a transfer offer. If all agents in the batch declined, dispatch next batch.
 */
function decline_transfer_notification(int $transferId, int $agentId): bool {
    $pdo = db();

    $stmt = $pdo->prepare("
        SELECT id, batch_number FROM agent_transfer_notifications
         WHERE transfer_request_id = ? AND agent_id = ? AND outcome = 'pending'
         LIMIT 1
    ");
    $stmt->execute([$transferId, $agentId]);
    $notif = $stmt->fetch();
    if (!$notif) return false;

    $pdo->prepare("
        UPDATE agent_transfer_notifications
           SET outcome = 'declined', responded_at = NOW()
         WHERE id = ?
    ")->execute([$notif['id']]);

    // Check if whole batch is exhausted
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM agent_transfer_notifications
         WHERE transfer_request_id = ? AND batch_number = ? AND outcome = 'pending'
    ");
    $stmt->execute([$transferId, $notif['batch_number']]);
    $remaining = (int)$stmt->fetchColumn();

    if ($remaining === 0) {
        // Dispatch next batch
        dispatch_transfer_batch($transferId);
    }

    return true;
}
