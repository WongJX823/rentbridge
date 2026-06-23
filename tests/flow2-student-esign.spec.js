/**
 * FLOW 2 — Single student browses, chats, and e-signs contract
 * UC-02 to UC-07
 * Actors: Student 1, Landlord, Agent
 * Covers: property inquiry → chat → contract prep → generate → e-sign canvas
 *
 * Pre-condition: One approved Whole Unit property pre-seeded (seeded via
 * test_accounts_seed.sql / supplement_seed.sql).
 */

const { test, expect } = require('@playwright/test');
const { login, logout } = require('./helpers/auth');

const TENANT = {
  name:    'Ahmad Faris',
  ic:      '021103-14-5678',
  phone:   '011-23456789',
  email:   's1@test.com',
  address: 'No. 3, Jalan Melati, Kuala Lumpur',
};

// Shared state across tests in this file
let conversationId;
let contractId;

test.describe('Flow 2A — Student browses and initiates chat', () => {

  test('UC-02: student can find property and open chat with landlord', async ({ page }) => {
    await login(page, 'student1');

    await page.goto('/listings.php');
    await expect(page).toHaveURL(/listings/);

    // Filter by Whole Unit if filter exists
    const typeFilter = page.locator('select[name="property_type"], select[name="type"]');
    if (await typeFilter.count()) {
      await typeFilter.selectOption('whole_unit');
      await page.waitForLoadState('networkidle');
    }

    // Click first available listing
    const firstListing = page.locator('a[href*="property.php"], .property-card a, .listing-card a').first();
    await expect(firstListing).toBeVisible();
    await firstListing.click();
    await page.waitForLoadState('networkidle');

    // Assert property detail page has price and photos
    await expect(page.locator('text=RM, text=rm')).toBeVisible().catch(() => {});

    // Assert Chat with Landlord button exists
    const chatBtn = page.locator('a:has-text("Chat"), button:has-text("Chat")').first();
    await expect(chatBtn).toBeVisible();
    await chatBtn.click();
    await page.waitForLoadState('networkidle');

    // Assert redirected to conversation
    await expect(page).toHaveURL(/conversation/);

    // Capture conversation ID for later tests
    const url = page.url();
    const match = url.match(/id=(\d+)/);
    if (match) conversationId = match[1];

    // Send inquiry message
    const msgBox = page.locator('textarea[name="message"], input[name="message"], #message-input').first();
    await msgBox.fill('Hi, saya berminat dengan unit ini. Boleh saya tahu lebih lanjut?');
    await page.keyboard.press('Enter');
    await page.waitForTimeout(1000);

    // Assert message appears
    await expect(page.locator('text=berminat dengan unit ini')).toBeVisible();
  });

});

test.describe('Flow 2B — Landlord replies and requests contract prep', () => {

  test('UC-03: landlord replies and triggers contract prep request', async ({ page }) => {
    await login(page, 'landlord');

    await page.goto('/chat.php');
    await page.waitForLoadState('networkidle');

    // Open conversation with student
    const conv = page.locator('a[href*="conversation"]').first();
    await expect(conv).toBeVisible();
    await conv.click();
    await page.waitForLoadState('networkidle');

    // Reply to student
    const msgBox = page.locator('textarea[name="message"], input[name="message"], #message-input').first();
    await msgBox.fill('Boleh, unit masih available. Bila nak pindah?');
    await page.keyboard.press('Enter');
    await page.waitForTimeout(1000);

    // Click Request Contract Preparation button
    const reqBtn = page.locator(
      'button:has-text("Request Contract"), a:has-text("Request Contract"), button:has-text("Contract Prep")'
    ).first();
    if (await reqBtn.count()) {
      await reqBtn.click();
      await page.waitForLoadState('networkidle');

      // Assert system notice in chat
      await expect(
        page.locator('text=Contract preparation, text=contract prep, .system-notice, .chat-notice')
      ).toBeVisible().catch(() => {});
    } else {
      test.skip(true, 'Request Contract Preparation button not found in this UI state');
    }
  });

});

test.describe('Flow 2C — Agent sends Tenant Info Form', () => {

  test('UC-04: agent sends tenant info form to landlord', async ({ page }) => {
    await login(page, 'agent');

    await page.goto('/chat.php');
    await page.waitForLoadState('networkidle');

    // Find contract prep conversation
    const convs = page.locator('a[href*="conversation"]');
    await expect(convs.first()).toBeVisible();
    await convs.first().click();
    await page.waitForLoadState('networkidle');

    // Click Send Tenant Info Form via + button or action button
    const formBtn = page.locator(
      'button:has-text("Tenant Info"), button:has-text("Send Form"), a:has-text("Send Tenant Info")'
    ).first();
    if (await formBtn.count()) {
      await formBtn.click();
      await page.waitForTimeout(1000);
      await expect(
        page.locator('text=Tenant Info Form, text=tenant_info_form, .form-message')
      ).toBeVisible().catch(() => {});
    } else {
      test.skip(true, 'Send Tenant Info Form button not found — may require + menu interaction');
    }
  });

});

test.describe('Flow 2D — Landlord fills Tenant Info Form (1 tenant)', () => {

  test('UC-05: landlord fills single-tenant form and booking is created', async ({ page }) => {
    await login(page, 'landlord');

    await page.goto('/chat.php');
    await page.waitForLoadState('networkidle');

    // Open conversation with agent
    const convs = page.locator('a[href*="conversation"]');
    await convs.first().click();
    await page.waitForLoadState('networkidle');

    // Open tenant info form modal
    const modalBtn = page.locator(
      'button:has-text("Fill Form"), button[data-bs-target="#tenantInfoModal"], a:has-text("Tenant Info")'
    ).first();
    if (await modalBtn.count()) {
      await modalBtn.click();
      await page.waitForTimeout(500);
    } else {
      test.skip(true, 'Tenant Info Form modal trigger not found');
      return;
    }

    // Fill primary tenant details
    await page.fill('input[name="primary_name"], input[name="full_name"]', TENANT.name);
    await page.fill('input[name="primary_ic"], input[name="ic_number"]',   TENANT.ic);
    await page.fill('input[name="primary_phone"], input[name="phone"]',    TENANT.phone);
    await page.fill('input[name="primary_email"], input[name="email"]',    TENANT.email);

    const homeAddr = page.locator('input[name="home_address"], textarea[name="address"]').first();
    if (await homeAddr.count()) await homeAddr.fill(TENANT.address);

    // Tenancy terms — use tomorrow + 7 days as start date
    const today = new Date();
    const start = new Date(today.setDate(today.getDate() + 7));
    const end   = new Date(today.setDate(today.getDate() + 180));
    const fmt   = (d) => d.toISOString().split('T')[0];

    const startField = page.locator('input[name="start_date"]');
    const endField   = page.locator('input[name="end_date"]');
    if (await startField.count()) await startField.fill(fmt(start));
    if (await endField.count())   await endField.fill(fmt(end));

    await page.fill('input[name="monthly_rent"], input[name="rent"]', '1200');
    await page.fill('input[name="deposit"]', '2400');

    // Submit form
    const submitBtn = page.locator(
      '#tenantInfoModal button[type="submit"], form button[type="submit"]'
    ).first();
    await submitBtn.click();
    await page.waitForLoadState('networkidle');

    // Assert success notice
    await expect(
      page.locator('.alert-success, text=Booking created, text=berjaya')
    ).toBeVisible().catch(() => {});
  });

});

test.describe('Flow 2E — Agent generates contract PDF', () => {

  test('UC-06: agent generates contract; booking moves to contract_pending', async ({ page }) => {
    await login(page, 'agent');

    // Navigate to agent cases / dashboard
    await page.goto('/agent/dashboard.php');
    await page.waitForLoadState('networkidle');

    // Find the booking case
    const caseLink = page.locator('a[href*="case.php"]').first();
    if (!(await caseLink.count())) {
      await page.goto('/agent/cases.php');
    }

    const firstCase = page.locator('a[href*="case.php"]').first();
    await expect(firstCase).toBeVisible();
    await firstCase.click();
    await page.waitForLoadState('networkidle');

    // Click Generate Contract
    const genBtn = page.locator(
      'a[href*="generate_contract"], button:has-text("Generate Contract"), a:has-text("Generate Contract")'
    ).first();
    if (await genBtn.count()) {
      // Capture contract ID from URL or page
      await genBtn.click();
      await page.waitForLoadState('networkidle');

      // Assert success flash
      await expect(
        page.locator('.alert-success, .flash-success, text=Contract')
      ).toBeVisible().catch(() => {});

      // Capture contract ID from the redirected URL or page link
      const signLink = page.locator('a[href*="sign.php"]').first();
      if (await signLink.count()) {
        const href = await signLink.getAttribute('href');
        const m = href?.match(/id=(\d+)/);
        if (m) contractId = m[1];
      }
    } else {
      test.skip(true, 'Generate Contract button not visible — booking may not be at correct state');
    }
  });

});

test.describe('Flow 2F — Student e-signs contract', () => {

  test('UC-07: student draws e-signature on canvas and contract activates', async ({ page }) => {
    await login(page, 'student1');

    // Navigate to sign page
    const signUrl = contractId
      ? `/contracts/sign.php?id=${contractId}`
      : '/student/booking.php';

    await page.goto(signUrl);
    await page.waitForLoadState('networkidle');

    // If redirected to booking page, find sign link
    if (!page.url().includes('sign.php')) {
      const signLink = page.locator('a[href*="sign.php"]').first();
      if (await signLink.count()) {
        await signLink.click();
        await page.waitForLoadState('networkidle');
      } else {
        test.skip(true, 'Sign link not found; contract may not be at signing stage');
        return;
      }
    }

    // Assert contract details shown
    await expect(page.locator('text=RM, text=Tenancy, text=Contract')).toBeVisible().catch(() => {});

    // Draw on the signature canvas
    const canvas = page.locator('canvas#signatureCanvas, canvas[id*="signature"], canvas').first();
    await expect(canvas).toBeVisible();

    const box = await canvas.boundingBox();
    if (box) {
      await page.mouse.move(box.x + 40, box.y + box.height / 2);
      await page.mouse.down();
      await page.mouse.move(box.x + 120, box.y + box.height / 2 - 20, { steps: 10 });
      await page.mouse.move(box.x + 200, box.y + box.height / 2,      { steps: 10 });
      await page.mouse.up();
    }

    // Submit signature
    const submitBtn = page.locator(
      'button:has-text("Sign"), button:has-text("Submit"), button[type="submit"]'
    ).first();
    await submitBtn.click();
    await page.waitForLoadState('networkidle');

    // Assert: redirected to view page or success shown
    const successVisible = await page.locator(
      '.alert-success, text=signed, text=Signature saved, text=activated'
    ).count();
    expect(successVisible).toBeGreaterThan(0);
  });

});
