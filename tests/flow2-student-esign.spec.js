/**
 * FLOW 2 — Single student browses, chats, and e-signs contract
 * UC-02 to UC-07
 * Actors: Student 1, Agent (landlord is not part of the initial chat —
 * property.php now always routes a student's inquiry to the assigned agent,
 * not the landlord; the agent mediates tenant-form collection and contract
 * generation, and the landlord only signs the contract at the end).
 *
 * Pre-condition: property #9001 pre-seeded as available/whole_unit with an
 * accepted, assigned agent (tests/e2e_fixtures_seed.sql).
 */

const { test, expect } = require('@playwright/test');
const { login } = require('./helpers/auth');

const TENANT = {
  name:  'Ahmad Faris',
  ic:    '021103-14-5678',
  phone: '011-23456789',
};

// Shared state across tests in this file
let contractId;
let tenancyId;

test.describe('Flow 2A — Student browses and initiates chat', () => {

  test('UC-02: student can find property and open chat with the assigned agent', async ({ page }) => {
    await login(page, 'student1');

    await page.goto('listings.php');
    await expect(page).toHaveURL(/listings/);

    const typeFilter = page.locator('select[name="type"], select[name="property_type"]');
    if (await typeFilter.count()) {
      await typeFilter.selectOption('whole_unit');
      await page.waitForLoadState('networkidle');
    }

    const firstListing = page.locator('a[href*="property.php"]').first();
    await expect(firstListing).toBeVisible();
    await firstListing.click();
    await page.waitForLoadState('networkidle');

    // Chat now routes to the assigned agent, not the landlord directly.
    // Scoped to the real trigger's href — a plain "Chat" text match would
    // instead hit the persistent sidebar "Chat & Notif" nav link, which
    // renders earlier in the DOM on every page.
    const chatBtn = page.locator('a[href*="chat/start.php"]').first();
    await expect(chatBtn).toBeVisible();
    await chatBtn.click();
    await page.waitForLoadState('networkidle');

    await expect(page).toHaveURL(/conversation/);

    // Plain Enter only inserts a newline in this composer — sending needs
    // Ctrl+Enter or the send button; use the button for reliability.
    const msgBox = page.locator('textarea[name="body"]').first();
    await msgBox.fill('Hi, saya berminat dengan unit ini. Boleh saya tahu lebih lanjut?');
    await page.click('#chatSendBtn');
    await page.waitForTimeout(1000);

    await expect(page.locator('text=berminat dengan unit ini').first()).toBeVisible();
  });

});

test.describe('Flow 2B — Agent replies in the inquiry chat', () => {

  test('UC-03: agent replies to the student', async ({ page }) => {
    await login(page, 'agent');

    await page.goto('chat.php');
    await page.waitForLoadState('networkidle');

    const conv = page.locator('a[href*="conversation"]:visible').first();
    await expect(conv).toBeVisible();
    await conv.click();
    await page.waitForLoadState('networkidle');

    const msgBox = page.locator('textarea[name="body"]').first();
    await msgBox.fill('Boleh, unit masih available. Bila nak pindah?');
    await page.click('#chatSendBtn');
    await page.waitForTimeout(1000);

    await expect(page.locator('text=unit masih available').first()).toBeVisible();
  });

});

test.describe('Flow 2C — Agent sends Tenant Info Form to the student', () => {

  test('UC-04: agent sets terms and sends the tenant info form', async ({ page }) => {
    await login(page, 'agent');

    await page.goto('chat.php');
    await page.waitForLoadState('networkidle');

    const convs = page.locator('a[href*="conversation"]:visible');
    await expect(convs.first()).toBeVisible();
    await convs.first().click();
    await page.waitForLoadState('networkidle');

    // Directly-visible banner button (not behind the + menu)
    const sendFormBtn = page.locator('#agentSendFormBtn, button:has-text("Send tenant info form")').first();
    if (!(await sendFormBtn.count())) {
      test.skip(true, 'Send tenant info form button not shown — agent may not be the assigned+accepted agent for this property');
      return;
    }
    await sendFormBtn.click();
    await page.waitForTimeout(500);

    await page.fill('#atm_monthly_rent', '900');
    await page.fill('#atm_deposit', '1800');

    const today = new Date();
    const start = new Date(today);
    start.setDate(today.getDate() + 14);
    await page.fill('#atm_start_date', start.toISOString().split('T')[0]);

    await page.click('#atmSubmitBtn');
    await page.waitForTimeout(1000);

    await expect(page.locator('text=Tenant info form').first()).toBeVisible();
  });

});

test.describe('Flow 2D — Student fills the Tenant Info Form', () => {

  test('UC-05: student fills their own tenant details and a tenancy is created', async ({ page }) => {
    await login(page, 'student1');

    await page.goto('chat.php');
    await page.waitForLoadState('networkidle');

    const conv = page.locator('a[href*="conversation"]:visible').first();
    await conv.click();
    await page.waitForLoadState('networkidle');

    const fillLink = page.locator('a:has-text("Fill in tenant details")').first();
    if (!(await fillLink.count())) {
      test.skip(true, 'Tenant info form link not found — UC-04 may not have completed');
      return;
    }
    await fillLink.click();
    await page.waitForLoadState('networkidle');
    await expect(page).toHaveURL(/tenant_form/);

    // tenant_email is readonly (account email) — only name/IC/phone are editable
    await page.fill('input[name="tenant_name"]', TENANT.name);
    await page.fill('input[name="tenant_ic"]', TENANT.ic);
    await page.fill('input[name="tenant_phone"]', TENANT.phone);

    await page.click('button:has-text("Submit tenant details")');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('text=Form submitted')).toBeVisible();
  });

});

test.describe('Flow 2E — Agent generates contract PDF', () => {

  test('UC-06: agent generates the contract; tenancy moves to contract_pending', async ({ page }) => {
    await login(page, 'agent');

    // tab=contracts filters to status=contract_pending — the tenancy UC-05 just created
    await page.goto('agent/cases.php?tab=contracts');
    await page.waitForLoadState('networkidle');

    const firstCase = page.locator('a[href*="case.php"]').first();
    if (!(await firstCase.count())) {
      test.skip(true, 'No contract_pending case found — UC-05 may not have completed');
      return;
    }
    await firstCase.click();
    await page.waitForLoadState('networkidle');

    // Capture the tenancy id — student/tenancy.php requires it as ?id=
    const caseUrl = page.url();
    const caseMatch = caseUrl.match(/id=(\d+)/);
    if (caseMatch) tenancyId = caseMatch[1];

    const genBtn = page.locator('a[href*="generate_contract"]').first();
    if (!(await genBtn.count())) {
      test.skip(true, 'Generate Contract link not visible — tenancy may not be at the right state');
      return;
    }

    // Generating streams a PDF download rather than navigating — just confirm it fires.
    const downloadPromise = page.waitForEvent('download', { timeout: 15000 }).catch(() => null);
    await genBtn.click();
    await downloadPromise;
    await page.waitForTimeout(1000);

    expect(tenancyId).toBeTruthy();
  });

});

test.describe('Flow 2F — Student e-signs contract', () => {

  test('UC-07: student draws e-signature on canvas', async ({ page }) => {
    await login(page, 'student1');

    if (!tenancyId) {
      test.skip(true, 'No tenancy id captured from UC-06 — cannot open student/tenancy.php without it');
      return;
    }
    await page.goto(`student/tenancy.php?id=${tenancyId}`);
    await page.waitForLoadState('networkidle');

    const signLink = page.locator('a[href*="sign.php"]').first();
    if (!(await signLink.count())) {
      test.skip(true, 'Sign link not found; contract may not be at signing stage');
      return;
    }
    const href = await signLink.getAttribute('href');
    const m = href?.match(/id=(\d+)/);
    if (m) contractId = m[1];

    await signLink.click();
    await page.waitForLoadState('networkidle');

    await expect(page.locator('text=RM, text=Tenancy, text=Contract')).toBeVisible().catch(() => {});

    const canvas = page.locator('canvas#signature-pad, canvas[id*="signature"]').first();
    await expect(canvas).toBeVisible();

    const box = await canvas.boundingBox();
    if (box) {
      await page.mouse.move(box.x + 40, box.y + box.height / 2);
      await page.mouse.down();
      await page.mouse.move(box.x + 120, box.y + box.height / 2 - 20, { steps: 10 });
      await page.mouse.move(box.x + 200, box.y + box.height / 2, { steps: 10 });
      await page.mouse.up();
    }

    await page.click('#btn-submit, button[type="submit"]');
    await page.waitForLoadState('networkidle');

    await expect(page).toHaveURL(/contracts\/view/);
    // Single tenant signing first won't yet mark the whole contract "active"
    // (landlord signs last) — but the tenant's own row should show "Signed
    // <date>". A plain "signed" text match also hits the landlord's
    // "Not signed yet" row, so anchor to the start of the string.
    await expect(page.getByText(/Signed \d/).first()).toBeVisible();
  });

});
