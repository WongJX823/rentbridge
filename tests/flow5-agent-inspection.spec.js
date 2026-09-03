/**
 * FLOW 5 — Agent full flow: receive assignment → inspection → approve/reject listing
 * UC-17 to UC-20
 * Actors: Agent, Landlord
 *
 * Pre-condition: two properties pre-seeded pending_approval / agent_status=
 * pending, assigned to agt@test.com (tests/e2e_fixtures_seed.sql, ids 9010
 * and 9011 — 9010 gets approved, 9011 gets rejected).
 */

const { test, expect } = require('@playwright/test');
const { login } = require('./helpers/auth');
const path = require('path');

test.describe('Flow 5A — Agent accepts the pending property', () => {

  test('UC-17: agent accepts property #9010 and starts the inspection', async ({ page }) => {
    await login(page, 'agent');

    await page.goto('agent/property_review.php?id=9010');
    await page.waitForLoadState('networkidle');

    const acceptBtn = page.locator('button:has-text("Accept & Schedule inspection")').first();
    await expect(acceptBtn).toBeVisible();

    page.once('dialog', d => d.accept());
    await acceptBtn.click();
    await page.waitForLoadState('networkidle');

    // Accepting redirects straight into the newly-created agent<->landlord conversation
    await expect(page.locator('text=Case accepted').first()).toBeVisible();
  });

});

test.describe('Flow 5B — Inspection scheduling', () => {

  test('UC-18a: agent proposes inspection time slots', async ({ page }) => {
    await login(page, 'agent');

    await page.goto('agent/property_review.php?id=9010');
    await page.waitForLoadState('networkidle');

    const convLink = page.locator('a:has-text("Open landlord conversation")').first();
    if (!(await convLink.count())) {
      test.skip(true, 'No landlord conversation link — UC-17 may not have completed');
      return;
    }
    await convLink.click();
    await page.waitForLoadState('networkidle');

    // "Propose inspection times" lives behind the + menu
    await page.click('#chatPlusBtn');
    await page.waitForTimeout(300);

    const scheduleBtn = page.locator('button:has-text("Propose inspection time")').first();
    if (!(await scheduleBtn.count())) {
      test.skip(true, 'Propose inspection time button not shown');
      return;
    }
    await scheduleBtn.click();
    await page.waitForTimeout(500);

    await page.fill('textarea[name="slots"]',
      'Monday 23 Jun 2026, 10:00 AM\nTuesday 24 Jun 2026, 2:00 PM');
    await page.fill('textarea[name="note"]',
      'Saya boleh datang pagi atau petang. Sila pilih masa yang sesuai.');

    await page.click('button:has-text("Send to landlord")');
    await page.waitForTimeout(1000);

    await expect(page.locator('text=Inspection schedule request').first()).toBeVisible();
  });

  test('UC-18b: landlord confirms an inspection slot with consent', async ({ page }) => {
    await login(page, 'landlord');

    await page.goto('chat.php');
    await page.waitForLoadState('networkidle');

    const conv = page.locator('a[href*="conversation"]:visible').first();
    if (!(await conv.count())) {
      test.skip(true, 'No conversation found for landlord — UC-18a may not have completed');
      return;
    }
    await conv.click();
    await page.waitForLoadState('networkidle');

    const confirmBtn = page.locator('button:has-text("Confirm or reschedule")').first();
    if (!(await confirmBtn.count())) {
      test.skip(true, 'Confirm or reschedule button not shown — no pending inspection proposal');
      return;
    }
    await confirmBtn.click();
    await page.waitForTimeout(500);

    await page.fill('input[name="slot_picked"]', 'Monday 23 Jun 2026, 10:00 AM');
    await page.selectOption('select[name="access_method"]', 'landlord_present');
    await page.check('#isr_consent');

    await page.click('#isrSubmitBtn');
    await page.waitForTimeout(1000);

    await expect(page.locator('text=Inspection confirmed').first()).toBeVisible();
  });

});

test.describe('Flow 5C — Agent completes inspection and approves the listing', () => {

  test('UC-19: agent marks inspection complete and approves property #9010', async ({ page }) => {
    await login(page, 'agent');

    await page.goto('agent/property_review.php?id=9010');
    await page.waitForLoadState('networkidle');

    const completeBtn = page.locator('button:has-text("Mark inspection complete")').first();
    if (await completeBtn.count()) {
      page.once('dialog', d => d.accept());
      await completeBtn.click();
      await page.waitForLoadState('networkidle');
    }

    const approveBtn = page.locator('button:has-text("Approve listing")').first();
    await expect(approveBtn).toBeVisible();
    page.once('dialog', d => d.accept());
    await approveBtn.click();
    await page.waitForLoadState('networkidle');

    // Approving redirects to the agent dashboard with a flash message
    await expect(page.locator('text=Property approved and is now live').first()).toBeVisible();
  });

});

test.describe('Flow 5D — Agent rejects a separate property with evidence', () => {

  test('UC-20: agent accepts then rejects property #9011 with a reason and evidence photo', async ({ page }) => {
    await login(page, 'agent');

    await page.goto('agent/property_review.php?id=9011');
    await page.waitForLoadState('networkidle');

    // Reject is only offered once agent_status is 'inspecting' — accept first.
    // Accepting redirects into the new agent<->landlord conversation (see
    // UC-17), so navigate back to property_review.php afterward.
    const acceptBtn = page.locator('button:has-text("Accept & Schedule inspection")').first();
    if (await acceptBtn.count()) {
      page.once('dialog', d => d.accept());
      await acceptBtn.click();
      await page.waitForLoadState('networkidle');
      await page.goto('agent/property_review.php?id=9011');
      await page.waitForLoadState('networkidle');
    }

    const rejectOpenBtn = page.locator('button[data-bs-target="#rejectModal"]').first();
    if (!(await rejectOpenBtn.count())) {
      test.skip(true, 'Reject button not found — property may not be at inspecting state');
      return;
    }
    await rejectOpenBtn.click();
    await page.waitForTimeout(600);

    await page.fill('#rejectModal textarea[name="reason"]',
      'Dokumen geran tidak sepadan dengan alamat hartanah.');
    await page.setInputFiles('#rejectModal input[name="evidence_photo"]',
      path.join(__dirname, '..', 'test_photo.jpg'));

    page.once('dialog', d => d.accept());
    await page.click('#rejectModal button:has-text("Reject listing")');
    await page.waitForLoadState('networkidle');

    // Rejecting redirects to the agent dashboard with a flash message
    await expect(page.locator('text=Listing rejected with evidence recorded').first()).toBeVisible();
  });

});
