<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Backend logic tests for the contract lifecycle and agent-commission maths.
 * Runs against dbrb_2026_test (built by bootstrap.php) using seeded users:
 *   user 2  = student, user 10 = landlord, user 15 = agent, property 1 (RM600).
 */
final class ContractCommissionTest extends TestCase
{
    /** Create a tenancy already at 'agent_assigned' so a contract can be made. */
    private function seedAgentAssignedTenancy(float $rent = 600.00, float $deposit = 1200.00): int
    {
        $pdo = db();
        $stmt = $pdo->prepare(
            "INSERT INTO tenancies
                (student_id, property_id, landlord_id, agent_id, start_date, end_date,
                 duration_type, monthly_rent, deposit, status)
             VALUES (2, 1, 10, 15, '2026-09-01', '2027-06-01', 'academic_8', ?, ?, 'agent_assigned')"
        );
        $stmt->execute([$rent, $deposit]);
        return (int)$pdo->lastInsertId();
    }

    public function test_standard_terms_include_the_semester_break_clause(): void
    {
        $terms = strtolower(standard_tenancy_terms());
        $this->assertStringContainsString('semester', $terms);
        $this->assertStringContainsString('continuously', $terms);
    }

    public function test_contract_code_format(): void
    {
        $this->assertSame('RB-' . date('Y') . '-00042', generate_contract_code(42));
    }

    public function test_create_contract_transitions_status_and_stores_terms(): void
    {
        $tid = $this->seedAgentAssignedTenancy();
        $cid = create_contract_from_tenancy($tid);

        $this->assertIsInt($cid, 'create_contract_from_tenancy should return a contract id');

        $pdo = db();
        $c = $pdo->query("SELECT * FROM contracts WHERE id = " . (int)$cid)->fetch();
        $this->assertSame('pending_signatures', $c['status']);
        $this->assertSame(standard_tenancy_terms(), $c['terms']);
        $this->assertStringStartsWith('RB-', $c['contract_code']);

        $t = $pdo->query("SELECT status FROM tenancies WHERE id = " . (int)$tid)->fetch();
        $this->assertSame('contract_pending', $t['status']);
    }

    public function test_duplicate_contract_is_refused(): void
    {
        $tid = $this->seedAgentAssignedTenancy();
        $first  = create_contract_from_tenancy($tid);
        $this->assertIsInt($first);
        // status is no longer 'agent_assigned', so a second attempt must fail
        $second = create_contract_from_tenancy($tid);
        $this->assertNull($second);
    }

    public function test_commission_is_one_month_rent_plus_6pct_sst(): void
    {
        $tid = $this->seedAgentAssignedTenancy(600.00);
        $cid = create_contract_from_tenancy($tid);

        $this->assertTrue(ensure_agent_commission_for_contract($cid, 'earned'));

        $pdo = db();
        $com = $pdo->query("SELECT * FROM agent_commissions WHERE contract_id = " . (int)$cid)->fetch();
        $this->assertNotFalse($com);
        $this->assertEqualsWithDelta(600.00, (float)$com['base_rent'], 0.001);
        $this->assertEqualsWithDelta(600.00, (float)$com['commission_amt'], 0.001);   // 100%
        $this->assertEqualsWithDelta(36.00,  (float)$com['sst_amt'], 0.001);          // 6%
        $this->assertEqualsWithDelta(636.00, (float)$com['total_payable'], 0.001);
        $this->assertSame('earned', $com['status']);
    }

    public function test_commission_creation_is_idempotent(): void
    {
        $tid = $this->seedAgentAssignedTenancy();
        $cid = create_contract_from_tenancy($tid);

        ensure_agent_commission_for_contract($cid, 'earned');
        ensure_agent_commission_for_contract($cid, 'earned'); // second call must not duplicate

        $pdo = db();
        $n = (int)$pdo->query("SELECT COUNT(*) FROM agent_commissions WHERE contract_id = " . (int)$cid)->fetchColumn();
        $this->assertSame(1, $n);
    }
}
