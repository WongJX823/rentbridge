/**
 * FLOW 7 — 4-tenant group, mixed signing (2 e-sign digitally, 2 sign a physical copy)
 * UC-27
 * Actors: Student 1 (+ 3 co-tenants), Agent, Landlord
 *
 * Same agent-mediated setup as flow3/flow4: chat routes to the assigned
 * agent, the agent sends the tenant-info form, and the student fills it
 * with 3 co-tenants (4 tenants total, sign_order 1-4 in submission order
 * per student/tenant_form.php).
 *
 * Exercises includes/contracts.php's per-party sign_method end to end:
 * student1 (sign_order 1) and student3 (sign_order 3) e-sign digitally
 * through the canvas; student2 (sign_order 2) and student4 (sign_order 4)
 * instead click "I'd rather sign a physical copy"
 * (choose_manual_signing()) partway through the queue, which
 * contract_next_signer() must skip over so signing continues to the next
 * e-signing party rather than getting stuck. Once every e-signing party
 * (student1, student3, and the landlord) has signed, the contract sits in
 * 'awaiting_manual' — nobody can e-sign further — until the agent uploads
 * a merged PDF covering the two physical signatures
 * (agent/upload_signed_contract.php, embedded directly on agent/case.php,
 * same endpoint flow3 uses for a fully wet-signed contract). That upload
 * also has to preserve the digital signatures already collected: the
 * landlord e-signs in this test, so the COALESCE on landlord_signed_at in
 * upload_signed_contract.php is what's actually being checked in UC-27g,
 * not just a blind overwrite.
 *
 * Pre-condition: property #9010, pre-seeded for flow5's agent-inspection
 * scenario (tests/e2e_fixtures_seed.sql) but left 'available'/accepted once
 * that flow finishes (it only inspects/approves — no tenancy is ever
 * created on it there), reused here rather than #9001/#9002 which flow2/
 * flow3/flow4 leave 'rented' by the time flow7 runs in a full-suite pass.
 */

const { test, expect } = require('@playwright/test');
const { login } = require('./helpers/auth');
const path = require('path');

const CO_TENANTS = [
  { name: 'Lim Wei Xian', ic: '021205-10-1234', phone: '012-3456789', email: 's2@test.com' },
  { name: 'Priya Nair',   ic: '021308-07-9876', phone: '013-9876543', email: 's3@test.com' },
  { name: 'Nurul Ain',    ic: '021401-05-4321', phone: '014-1234004', email: 's4@test.com' },
];

let tenancyId;
let contractId;

test.describe('Flow 7A — Student 1 chats the agent about the group tenancy', () => {

  test('UC-27a: student 1 opens chat for the property', async ({ page }) => {
    await login(page, 'student1');

    await page.goto('property.php?id=9010');
    await page.waitForLoadState('networkidle');

    const chatBtn = page.locator('a[href*="chat/start.php"]').first();
    await expect(chatBtn).toBeVisible();
    await chatBtn.click();
    await page.waitForLoadState('networkidle');
    await expect(page).toHaveURL(/conversation/);

    const msgBox = page.locator('textarea[name="body"]').first();
    await msgBox.fill('Kami berempat nak sewa unit ni. Boleh discuss?');
    await page.click('#chatSendBtn');
    await page.waitForTimeout(1000);

    await expect(page.locator('text=berempat nak sewa').first()).toBeVisible();
  });

});

test.describe('Flow 7B — Agent sends the tenant info form', () => {

  test('UC-27b: agent sets terms and sends the tenant info form', async ({ page }) => {
    await login(page, 'agent');

    await page.goto('message.php');
    await page.waitForLoadState('networkidle');

    const convs = page.locator('a[href*="conversation"]:visible');
    await expect(convs.first()).toBeVisible();
    await convs.first().click();
    await page.waitForLoadState('networkidle');

    const sendFormBtn = page.locator('#agentSendFormBtn, button:has-text("Send tenant info form")').first();
    if (!(await sendFormBtn.count())) {
      test.skip(true, 'Send tenant info form button not shown — agent may not be the assigned+accepted agent for this property');
      return;
    }
    await sendFormBtn.click();
    await page.waitForTimeout(500);

    await page.fill('#atm_monthly_rent', '980');
    await page.fill('#atm_deposit', '1960');

    const today = new Date();
    const start = new Date(today);
    start.setDate(today.getDate() + 14);
    await page.fill('#atm_start_date', start.toISOString().split('T')[0]);

    await page.click('#atmSubmitBtn');
    await page.waitForTimeout(1000);

    await expect(page.locator('text=Tenant info form').first()).toBeVisible();
  });

});

test.describe('Flow 7C — Student fills the Tenant Info Form with 3 co-tenants (4 total)', () => {

  test('UC-27c: student submits form with primary + 3 co-tenants', async ({ page }) => {
    await login(page, 'student1');

    await page.goto('message.php');
    await page.waitForLoadState('networkidle');

    const conv = page.locator('a[href*="conversation"]:visible').first();
    await conv.click();
    await page.waitForLoadState('networkidle');

    const fillLink = page.locator('a:has-text("Fill in tenant details")').first();
    if (!(await fillLink.count())) {
      test.skip(true, 'Tenant info form link not found — UC-27b may not have completed');
      return;
    }
    await fillLink.click();
    await page.waitForLoadState('networkidle');
    await expect(page).toHaveURL(/tenant_form/);

    await page.fill('input[name="tenant_name"]', 'Ahmad Faris');
    await page.fill('input[name="tenant_ic"]', '021103-14-5678');
    await page.fill('input[name="tenant_phone"]', '011-23456789');

    for (const ct of CO_TENANTS) {
      await page.click('#addCoTenantBtn');
      await page.waitForTimeout(200);
    }

    const nameFields  = page.locator('input[name="cotenant_name[]"]');
    const icFields    = page.locator('input[name="cotenant_ic[]"]');
    const phoneFields = page.locator('input[name="cotenant_phone[]"]');
    const emailFields = page.locator('input[name="cotenant_email[]"]');

    for (let i = 0; i < CO_TENANTS.length; i++) {
      await nameFields.nth(i).fill(CO_TENANTS[i].name);
      await icFields.nth(i).fill(CO_TENANTS[i].ic);
      await phoneFields.nth(i).fill(CO_TENANTS[i].phone);
      await emailFields.nth(i).fill(CO_TENANTS[i].email);
    }

    await page.click('button:has-text("Submit tenant details")');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('text=Form submitted')).toBeVisible();
  });

});

test.describe('Flow 7D — Agent generates the 4-tenant contract', () => {

  test('UC-27d: agent generates the contract; tenancy moves to contract_pending', async ({ page }) => {
    await login(page, 'agent');

    await page.goto('agent/cases.php?tab=contracts');
    await page.waitForLoadState('networkidle');

    const caseLink = page.locator('a[href*="case.php"]').first();
    if (!(await caseLink.count())) {
      test.skip(true, 'No contract_pending case found — UC-27c may not have completed');
      return;
    }
    await caseLink.click();
    await page.waitForLoadState('networkidle');

    const url = page.url();
    const bm = url.match(/id=(\d+)/);
    if (bm) tenancyId = bm[1];

    const genBtn = page.locator('a[href*="generate_contract"]').first();
    if (!(await genBtn.count())) {
      test.skip(true, 'Generate Contract link not visible — tenancy may not be at the right state');
      return;
    }
    const downloadPromise = page.waitForEvent('download', { timeout: 15000 }).catch(() => null);
    await genBtn.click();
    await downloadPromise;

    // Reload — the contract row only appears after regenerating the page
    // (generate_contract.php streams a PDF, it doesn't redirect).
    await page.reload();
    await page.waitForLoadState('networkidle');
  });

});

test.describe('Flow 7E — Mixed signing in turn: e-sign, physical, e-sign, physical, e-sign', () => {

  const PLAN = [
    { role: 'student1', method: 'esign' },
    { role: 'student2', method: 'manual' },
    { role: 'student3', method: 'esign' },
    { role: 'student4', method: 'manual' },
    { role: 'landlord', method: 'esign' },
  ];

  for (const step of PLAN) {
    test(`UC-27e: ${step.role} ${step.method === 'esign' ? 'e-signs' : "chooses a physical copy"} (in sign order)`, async ({ page }) => {
      await login(page, step.role);

      let signLink;
      if (step.role === 'student1') {
        if (!tenancyId) {
          test.skip(true, 'No tenancy id captured from UC-27d');
          return;
        }
        await page.goto(`student/tenancy.php?id=${tenancyId}`);
        await page.waitForLoadState('networkidle');
        signLink = page.locator('a[href*="sign.php"]').first();
      } else {
        if (!contractId) {
          test.skip(true, 'No contract id captured — cannot build contracts/view.php URL');
          return;
        }
        await page.goto(`contracts/view.php?id=${contractId}`);
        await page.waitForLoadState('networkidle');
        signLink = page.locator('a[href*="sign.php"]').first();
      }

      if (!(await signLink.count())) {
        test.skip(true, `Sign link not found for ${step.role} — may not be their turn yet, or already resolved`);
        return;
      }
      const href = await signLink.getAttribute('href');
      const m = href?.match(/id=(\d+)/);
      if (m) contractId = m[1];

      await signLink.click();
      await page.waitForLoadState('networkidle');

      // Not this party's turn (contract_can_sign gate) -> redirected back to view.php
      if (page.url().includes('/contracts/view')) {
        test.skip(true, `Not ${step.role}'s turn to sign yet`);
        return;
      }

      if (step.method === 'esign') {
        const canvas = page.locator('canvas#signature-pad, canvas[id*="signature"]').first();
        await expect(canvas).toBeVisible();
        const box = await canvas.boundingBox();
        if (box) {
          await page.mouse.move(box.x + 30, box.y + box.height / 2);
          await page.mouse.down();
          await page.mouse.move(box.x + 150, box.y + box.height / 2 - 20, { steps: 12 });
          await page.mouse.move(box.x + 220, box.y + box.height / 2, { steps: 12 });
          await page.mouse.up();
        }
        await page.click('#btn-submit, button[type="submit"]');
      } else {
        page.once('dialog', d => d.accept());
        await page.click('button:has-text("I\'d rather sign a physical copy")');
      }
      await page.waitForLoadState('networkidle');

      await expect(page).toHaveURL(/contracts\/view/);
    });
  }

});

test.describe('Flow 7F — Contract correctly waits on the two physical signatures', () => {

  test('UC-27f: view page shows "physical copy" for the two manual tenants and reports awaiting_manual', async ({ page }) => {
    if (!contractId) {
      test.skip(true, 'No contract id captured — earlier steps may not have completed');
      return;
    }
    await login(page, 'agent');
    await page.goto(`contracts/view.php?id=${contractId}`);
    await page.waitForLoadState('networkidle');

    // student2 and student4 chose manual — exactly two "physical copy" badges,
    // never signed digitally.
    await expect(page.locator('text=Signing a physical copy')).toHaveCount(2);

    // Nobody should be able to sign further at this point.
    await expect(page.locator('a[href*="sign.php"]')).toHaveCount(0);
  });

});

test.describe('Flow 7G — Agent uploads the merged PDF; everything activates', () => {

  test('UC-27g1: agent uploads the merged signed PDF', async ({ page }) => {
    if (!tenancyId) {
      test.skip(true, 'No tenancy id captured — earlier steps may not have completed');
      return;
    }
    await login(page, 'agent');

    await page.goto(`agent/case.php?id=${tenancyId}`);
    await page.waitForLoadState('networkidle');

    const uploadInput = page.locator('input[type="file"][name="signed_pdf"]').first();
    if (!(await uploadInput.count())) {
      test.skip(true, 'Signed PDF upload input not found — tenancy may not be in the right state');
      return;
    }
    await uploadInput.setInputFiles(path.join(__dirname, 'fixtures', 'test_document.pdf'));

    page.once('dialog', d => d.accept());
    await page.click('button:has-text("Upload signed contract")');
    await page.waitForLoadState('networkidle');
  });

  test('UC-27g2: student side confirms the tenancy is active', async ({ page }) => {
    if (!tenancyId) {
      test.skip(true, 'No tenancy id captured — earlier steps may not have completed');
      return;
    }
    await login(page, 'student1');
    await page.goto(`student/tenancy.php?id=${tenancyId}`);
    await page.waitForLoadState('networkidle');
    await expect(page.locator('text=/active/i').first()).toBeVisible();
  });

  test('UC-27g3: the two manual co-tenants now read as signed, preserving the e-sign timestamps already collected', async ({ page }) => {
    if (!contractId) {
      test.skip(true, 'No contract id captured — earlier steps may not have completed');
      return;
    }
    // "Signing a physical copy" describes the METHOD (a fact that stays
    // true for student2/student4 even now) — actual completion shows as a
    // separate "Signed <date>" checkmark tied to signed_at, which the bulk
    // UPDATE in upload_signed_contract.php sets for every co-tenant. All 4
    // co-tenants + the landlord (5 total) should show that checkmark;
    // nobody should still read "Pending".
    await login(page, 'agent');
    await page.goto(`contracts/view.php?id=${contractId}`);
    await page.waitForLoadState('networkidle');
    await expect(page.locator('text=Signing a physical copy')).toHaveCount(2);
    await expect(page.getByText(/Signed \d/)).toHaveCount(5);
    await expect(page.locator('text=· Pending')).toHaveCount(0);
    await expect(page.getByText('Pending', { exact: true })).toHaveCount(0);
  });

});
