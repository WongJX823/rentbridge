<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests the mixed (account + account-less) contract signing flow:
 *  - account-less co-tenants get a signing token,
 *  - link-based signing enforces the signing order,
 *  - once every party has signed, the contract activates.
 * Runs against dbrb_2026_test (seeded users 2=student, 10=landlord, 15=agent).
 */
final class SigningTest extends TestCase
{
    /** A small but valid PNG data URL (>100 bytes) for save_signature_image(). */
    private function pngDataUrl(): string
    {
        $im = imagecreatetruecolor(80, 30);
        imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
        imageline($im, 5, 20, 75, 12, imagecolorallocate($im, 0, 0, 0));
        ob_start();
        imagepng($im);
        $data = ob_get_clean();
        imagedestroy($im);
        return 'data:image/png;base64,' . base64_encode($data);
    }

    /** Seed a tenancy + contract with one account-holder and one account-less co-tenant. */
    private function seedMixedContract(): array
    {
        $pdo = db();
        $pdo->prepare("INSERT INTO tenancies
            (student_id, property_id, landlord_id, agent_id, start_date, end_date,
             duration_type, monthly_rent, deposit, status)
            VALUES (2,1,10,15,'2026-09-01','2027-06-01','academic_8',600,1200,'agent_assigned')")
            ->execute();
        $tid = (int)$pdo->lastInsertId();

        $pdo->prepare("INSERT INTO co_tenants
            (tenancy_id, student_id, is_primary, full_name, ic_number, phone, email, sign_order, added_by, status)
            VALUES (?,2,1,'Primary Signer','010101010101','012-0000000',NULL,1,2,'pending')")->execute([$tid]);
        $pdo->prepare("INSERT INTO co_tenants
            (tenancy_id, student_id, is_primary, full_name, ic_number, phone, email, sign_order, added_by, status)
            VALUES (?,NULL,0,'Guest Signer','020202020202','013-0000000','guest@test.com',2,2,'pending')")->execute([$tid]);

        $cid = create_contract_from_tenancy($tid);
        return ['tenancy_id' => $tid, 'contract_id' => (int)$cid];
    }

    private function accountlessToken(int $tenancyId): string
    {
        return (string)db()->query(
            "SELECT sign_token FROM co_tenants WHERE tenancy_id = $tenancyId AND student_id IS NULL LIMIT 1"
        )->fetchColumn();
    }

    public function test_accountless_cotenant_gets_a_token(): void
    {
        $ids = $this->seedMixedContract();
        $this->assertNotEmpty($this->accountlessToken($ids['tenancy_id']),
            'account-less co-tenant should be minted a sign_token');
        $this->assertStringContainsString('sign_link.php?token=',
            cotenant_sign_url('abc123'));
    }

    public function test_invalid_token_is_rejected(): void
    {
        $res = apply_signature_by_token('not-a-real-token', $this->pngDataUrl());
        $this->assertFalse($res['success']);
    }

    public function test_token_signing_waits_for_earlier_signer(): void
    {
        $ids   = $this->seedMixedContract();
        $token = $this->accountlessToken($ids['tenancy_id']);
        // Primary (sign_order 1) has NOT signed yet, so the account-less one must wait.
        $res = apply_signature_by_token($token, $this->pngDataUrl());
        $this->assertFalse($res['success']);
        $this->assertNotEmpty($res['wait'] ?? null, 'should be told to wait for the earlier signer');
    }

    public function test_full_mixed_signing_activates_contract(): void
    {
        $ids = $this->seedMixedContract();
        $cid = $ids['contract_id'];
        $pdo = db();

        // 1. Primary (account holder, user 2) signs.
        $r1 = apply_signature($cid, 2, $this->pngDataUrl());
        $this->assertTrue($r1['success'], $r1['message'] ?? '');
        $this->assertFalse($r1['all_signed']);

        // 2. Account-less co-tenant signs via their token link.
        $r2 = apply_signature_by_token($this->accountlessToken($ids['tenancy_id']), $this->pngDataUrl());
        $this->assertTrue($r2['success'], $r2['message'] ?? '');
        $this->assertFalse($r2['all_signed']);

        // 3. Landlord (user 10) signs -> everyone has signed.
        $r3 = apply_signature($cid, 10, $this->pngDataUrl());
        $this->assertTrue($r3['success'], $r3['message'] ?? '');
        $this->assertTrue($r3['all_signed'], 'contract should be fully signed');

        // Contract + tenancy are now active, and every signature is stored.
        $this->assertSame('active', $pdo->query("SELECT status FROM contracts WHERE id = $cid")->fetchColumn());
        $unsigned = (int)$pdo->query(
            "SELECT COUNT(*) FROM co_tenants WHERE tenancy_id = {$ids['tenancy_id']} AND status != 'signed'"
        )->fetchColumn();
        $this->assertSame(0, $unsigned);
    }
}
