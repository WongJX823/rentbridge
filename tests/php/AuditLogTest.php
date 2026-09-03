<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Verifies the audit_log triggers from migrations/add_audit_log.sql fire and
 * record old/new snapshots for the legal/financial tables.
 */
final class AuditLogTest extends TestCase
{
    private function newContract(): int
    {
        $pdo = db();
        $pdo->prepare(
            "INSERT INTO tenancies
                (student_id, property_id, landlord_id, agent_id, start_date, end_date,
                 duration_type, monthly_rent, deposit, status)
             VALUES (2, 1, 10, 15, '2026-09-01', '2027-06-01', 'academic_8', 600, 1200, 'agent_assigned')"
        )->execute();
        $tid = (int)$pdo->lastInsertId();
        return (int)create_contract_from_tenancy($tid);
    }

    public function test_contract_insert_is_audited(): void
    {
        $cid = $this->newContract();
        $pdo = db();
        $row = $pdo->query(
            "SELECT * FROM audit_log
              WHERE table_name='contracts' AND row_id=" . (int)$cid . " AND action='insert'
              LIMIT 1"
        )->fetch();

        $this->assertNotFalse($row, 'contract INSERT should create an audit_log row');
        $this->assertStringContainsString('pending_signatures', (string)$row['new_values']);
        $this->assertNull($row['old_values']);
    }

    public function test_contract_update_records_old_and_new(): void
    {
        $cid = $this->newContract();
        $pdo = db();
        $pdo->exec("UPDATE contracts SET status='active', activated_at=NOW() WHERE id=" . (int)$cid);

        $row = $pdo->query(
            "SELECT * FROM audit_log
              WHERE table_name='contracts' AND row_id=" . (int)$cid . " AND action='update'
              ORDER BY id DESC LIMIT 1"
        )->fetch();

        $this->assertNotFalse($row);
        $this->assertStringContainsString('pending_signatures', (string)$row['old_values']); // was
        $this->assertStringContainsString('active', (string)$row['new_values']);             // now
    }

    public function test_commission_insert_is_audited(): void
    {
        $cid = $this->newContract();
        ensure_agent_commission_for_contract($cid, 'earned');

        $pdo = db();
        $n = (int)$pdo->query(
            "SELECT COUNT(*) FROM audit_log
              WHERE table_name='agent_commissions' AND action='insert'"
        )->fetchColumn();

        $this->assertGreaterThanOrEqual(1, $n, 'commission INSERT should be audited');
    }
}
