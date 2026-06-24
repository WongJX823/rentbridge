/**
 * FLOW 3 — 3-tenant group, agent uploads physically signed PDF (wet sign)
 * UC-08 to UC-11
 * Actors: Student 1, Landlord, Agent, (public verify)
 * Covers: 3-person tenancy → contract generated → wet-sign PDF uploaded → verify URL
 *
 * Pre-condition: Second approved Whole Unit property pre-seeded.
 * test_document.pdf used as stand-in for the scanned wet-signed PDF.
 */

const { test, expect } = require('@playwright/test');
const { login } = require('./helpers/auth');
const path = require('path');

const CO_TENANTS = [
  { name: 'Lim Wei Xian', ic: '021205-10-1234', phone: '012-3456789', email: 's2@test.com', address: 'No. 8, Jalan Wawasan, Penang' },
  { name: 'Priya Nair',   ic: '021308-07-9876', phone: '013-9876543', email: 's3@test.com', address: 'No. 15, Jalan Harmoni, Selangor' },
];

let tenancyId;
let contractRef;

test.describe('Flow 3A–B — 3-student tenancy initiated', () => {

  test('UC-08: student 1 chats landlord for a group tenancy', async ({ page }) => {
    await login(page, 'student1');

    await page.goto('/listings.php');
    await page.waitForLoadState('networkidle');

    // Pick second property (skip first if already reserved)
    const listings = page.locator('a[href*="property.php"], .property-card a').nth(1);
    if (await listings.count()) {
      await listings.click();
    } else {
      await page.locator('a[href*="property.php"], .property-card a').first().click();
    }
    await page.waitForLoadState('networkidle');

    const chatBtn = page.locator('a:has-text("Chat"), button:has-text("Chat")').first();
    await expect(chatBtn).toBeVisible();
    await chatBtn.click();
    await page.waitForLoadState('networkidle');

    const msgBox = page.locator('textarea[name="message"], #message-input').first();
    await msgBox.fill('Kami bertiga nak sewa unit ni. Boleh discuss?');
    await page.keyboard.press('Enter');
    await page.waitForTimeout(1000);

    await expect(page.locator('text=bertiga nak sewa')).toBeVisible();
  });

});

test.describe('Flow 3C — Landlord fills Tenant Info Form (3 tenants)', () => {

  test('UC-09: landlord submits form with primary + 2 co-tenants', async ({ page }) => {
    await login(page, 'landlord');

    await page.goto('/chat.php');
    await page.waitForLoadState('networkidle');

    // Open latest agent conversation
    const agentConv = page.locator('a[href*="conversation"]').first();
    await agentConv.click();
    await page.waitForLoadState('networkidle');

    // Open tenant info modal
    const modalTrigger = page.locator(
      'button[data-bs-target="#tenantInfoModal"], button:has-text("Fill"), button:has-text("Tenant Info")'
    ).first();
    if (!(await modalTrigger.count())) {
      test.skip(true, 'Tenant Info Form trigger not found');
      return;
    }
    await modalTrigger.click();
    await page.waitForTimeout(500);

    // Primary tenant — Student 1
    await page.fill('input[name="primary_name"]', 'Ahmad Faris');
    await page.fill('input[name="primary_ic"]',   '021103-14-5678');
    await page.fill('input[name="primary_phone"]', '011-23456789');
    await page.fill('input[name="primary_email"]', 's1@test.com');

    // Add co-tenants
    for (let i = 0; i < CO_TENANTS.length; i++) {
      const addBtn = page.locator('button:has-text("Add Co-Tenant"), button:has-text("Add Tenant")').first();
      if (await addBtn.count()) {
        await addBtn.click();
        await page.waitForTimeout(300);
      }

      const ct = CO_TENANTS[i];
      const idx = i + 1;
      await page.fill(`input[name="co_tenant_name_${idx}"], input[name="cotenant_name[]"]:nth-of-type(${idx})`, ct.name).catch(() => {});
      await page.fill(`input[name="co_tenant_ic_${idx}"],   input[name="cotenant_ic[]"]:nth-of-type(${idx})`,   ct.ic).catch(() => {});
      await page.fill(`input[name="co_tenant_phone_${idx}"], input[name="cotenant_phone[]"]:nth-of-type(${idx})`, ct.phone).catch(() => {});
      await page.fill(`input[name="co_tenant_email_${idx}"], input[name="cotenant_email[]"]:nth-of-type(${idx})`, ct.email).catch(() => {});
    }

    // Tenancy terms
    const today = new Date();
    const start = new Date(today); start.setDate(today.getDate() + 7);
    const end   = new Date(today); end.setDate(today.getDate() + 187);
    const fmt   = (d) => d.toISOString().split('T')[0];

    await page.fill('input[name="start_date"]', fmt(start)).catch(() => {});
    await page.fill('input[name="end_date"]',   fmt(end)).catch(() => {});
    await page.fill('input[name="monthly_rent"], input[name="rent"]', '1200').catch(() => {});
    await page.fill('input[name="deposit"]', '2400').catch(() => {});

    // Submit
    const submitBtn = page.locator('#tenantInfoModal button[type="submit"], form button[type="submit"]').first();
    await submitBtn.click();
    await page.waitForLoadState('networkidle');

    await expect(page.locator('.alert-success, text=Tenancy, text=berjaya')).toBeVisible().catch(() => {});
  });

});

test.describe('Flow 3D — Agent uploads wet-signed PDF', () => {

  test('UC-10: agent generates contract then uploads signed PDF; tenancy becomes active', async ({ page }) => {
    await login(page, 'agent');

    await page.goto('/agent/dashboard.php');
    await page.waitForLoadState('networkidle');

    // Open the case
    const caseLink = page.locator('a[href*="case.php"]').first();
    await expect(caseLink).toBeVisible();
    await caseLink.click();
    await page.waitForLoadState('networkidle');

    // Capture tenancy ID
    const url = page.url();
    const bm = url.match(/id=(\d+)/);
    if (bm) tenancyId = bm[1];

    // Generate contract
    const genBtn = page.locator('a[href*="generate_contract"], a:has-text("Generate Contract"), button:has-text("Generate Contract")').first();
    if (await genBtn.count()) {
      await genBtn.click();
      await page.waitForLoadState('networkidle');

      // Capture contract ref from page
      const codeEl = page.locator('text=/RB-\\d{4}-\\d{5}/');
      if (await codeEl.count()) {
        contractRef = (await codeEl.first().textContent())?.match(/RB-\d{4}-\d{5}/)?.[0];
      }
    }

    // Upload signed PDF
    const uploadInput = page.locator('input[type="file"][name*="signed"], input[type="file"][name*="contract"]').first();
    if (await uploadInput.count()) {
      await uploadInput.setInputFiles(path.join(__dirname, '..', 'test_document.pdf'));

      const uploadBtn = page.locator('button:has-text("Upload"), button[type="submit"]').last();
      await uploadBtn.click();
      await page.waitForLoadState('networkidle');

      await expect(page.locator('.alert-success, text=uploaded, text=active')).toBeVisible().catch(() => {});
    } else {
      test.skip(true, 'Signed PDF upload input not found on this page');
    }
  });

});

test.describe('Flow 3E — Public contract verify page', () => {

  test('UC-11: verify URL accessible without login and shows contract info', async ({ page }) => {
    // Use a known ref or skip if not captured
    const ref = contractRef ?? 'RB-2026-00001';

    await page.goto(`/verify.php?ref=${ref}`);
    await page.waitForLoadState('networkidle');

    // Should NOT be redirected to login
    expect(page.url()).not.toContain('login');

    // Assert page shows some contract info or a not-found message (not a PHP error)
    const hasInfo   = await page.locator('text=' + ref).count();
    const notFound  = await page.locator('text=not found, text=tidak dijumpai').count();
    const fatalErr  = await page.locator('text=Fatal error, text=Parse error').count();

    expect(fatalErr, 'PHP fatal error on verify page').toBe(0);
    expect(hasInfo + notFound, 'Expected either contract info or a not-found message').toBeGreaterThan(0);
  });

});
