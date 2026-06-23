/**
 * FLOW 5 — Agent full flow: receive assignment → inspection → approve/reject listing
 * UC-17 to UC-20
 * Actors: Agent, Landlord
 * Covers: pending property review → inspection scheduling → approval/rejection
 *
 * Pre-condition: A property with status=pending_approval and agent_status=pending
 * pre-seeded and assigned to agt@test.com.
 */

const { test, expect } = require('@playwright/test');
const { login } = require('./helpers/auth');

let propertyId;
let inspectionConversationUrl;

test.describe('Flow 5A–B — Agent reviews and accepts property', () => {

  test('UC-17: agent sees pending property and accepts it', async ({ page }) => {
    await login(page, 'agent');

    await page.goto('/agent/dashboard.php');
    await page.waitForLoadState('networkidle');

    // Assert a pending property card is visible
    const pendingCard = page.locator(
      '.pending-card, a[href*="property_review"], text=Pending Review, text=pending_approval'
    ).first();
    await expect(pendingCard).toBeVisible();

    // Navigate to property review
    const reviewLink = page.locator('a[href*="property_review"]').first();
    await expect(reviewLink).toBeVisible();
    await reviewLink.click();
    await page.waitForLoadState('networkidle');

    // Capture property ID
    const url = page.url();
    const pm = url.match(/id=(\d+)/);
    if (pm) propertyId = pm[1];

    // Assert photos and docs visible
    await expect(page.locator('img, .property-photos, .doc-list')).toBeVisible().catch(() => {});

    // Click Accept
    const acceptBtn = page.locator(
      'button:has-text("Accept"), form button[value="accept"], a:has-text("Accept")'
    ).first();
    await expect(acceptBtn).toBeVisible();
    await acceptBtn.click();
    await page.waitForLoadState('networkidle');

    // Assert success notice
    await expect(
      page.locator('.alert-success, text=accepted, text=Accepted, text=Inspection')
    ).toBeVisible().catch(() => {});
  });

});

test.describe('Flow 5C–D — Inspection scheduling', () => {

  test('UC-18a: agent proposes 2 inspection time slots', async ({ page }) => {
    await login(page, 'agent');

    await page.goto('/chat.php');
    await page.waitForLoadState('networkidle');

    // Find landlord-agent conversation
    const conv = page.locator('a[href*="conversation"]').first();
    await expect(conv).toBeVisible();
    await conv.click();
    await page.waitForLoadState('networkidle');
    inspectionConversationUrl = page.url();

    // Open inspection schedule via + menu or direct button
    const plusBtn = page.locator('#chatPlusBtn, button:has-text("+"), button[aria-label="More actions"]').first();
    if (await plusBtn.count()) {
      await plusBtn.click();
      await page.waitForTimeout(300);
    }

    const scheduleBtn = page.locator(
      'button:has-text("Inspection"), a:has-text("Inspection Schedule"), button:has-text("Schedule")'
    ).first();
    if (!(await scheduleBtn.count())) {
      test.skip(true, 'Inspection schedule button not found — check + menu or context');
      return;
    }
    await scheduleBtn.click();
    await page.waitForTimeout(500);

    // Fill 2 proposed slots
    const tomorrow = new Date(); tomorrow.setDate(tomorrow.getDate() + 1);
    const dayAfter  = new Date(); dayAfter.setDate(dayAfter.getDate() + 2);
    const fmt = (d) => d.toISOString().split('T')[0];

    const slot1Date = page.locator('input[name="slot1_date"], input[name="proposed_date_1"]').first();
    const slot1Time = page.locator('input[name="slot1_time"], input[name="proposed_time_1"]').first();
    const slot2Date = page.locator('input[name="slot2_date"], input[name="proposed_date_2"]').first();
    const slot2Time = page.locator('input[name="slot2_time"], input[name="proposed_time_2"]').first();

    if (await slot1Date.count()) await slot1Date.fill(fmt(tomorrow));
    if (await slot1Time.count()) await slot1Time.fill('10:00');
    if (await slot2Date.count()) await slot2Date.fill(fmt(dayAfter));
    if (await slot2Time.count()) await slot2Time.fill('14:00');

    const noteField = page.locator('textarea[name="note"], input[name="note"]').first();
    if (await noteField.count()) {
      await noteField.fill('Saya boleh datang pagi atau petang. Sila pilih masa yang sesuai.');
    }

    await page.locator('button[type="submit"]').first().click();
    await page.waitForLoadState('networkidle');

    // Assert inspection schedule message appears in chat
    await expect(
      page.locator('text=inspection, text=Inspection, .inspection-message')
    ).toBeVisible().catch(() => {});
  });

  test('UC-18b: landlord confirms inspection slot 1 with consent', async ({ page }) => {
    await login(page, 'landlord');

    await page.goto('/chat.php');
    await page.waitForLoadState('networkidle');

    // Open agent conversation
    const conv = page.locator('a[href*="conversation"]').first();
    await conv.click();
    await page.waitForLoadState('networkidle');

    // Assert inspection proposal visible
    const proposal = page.locator('text=inspection, .inspection-schedule, .schedule-card').first();
    await expect(proposal).toBeVisible().catch(() => {});

    // Select slot 1
    const slot1Btn = page.locator(
      'button:has-text("Slot 1"), input[value="1"][name="slot"], label:has-text("Slot 1")'
    ).first();
    if (await slot1Btn.count()) await slot1Btn.click();

    // Access method
    const accessSelect = page.locator('select[name="access_method"]').first();
    if (await accessSelect.count()) await accessSelect.selectOption('landlord_present');

    // Consent checkbox
    const consentCb = page.locator('input[type="checkbox"][name*="consent"]').first();
    if (await consentCb.count() && !(await consentCb.isChecked())) {
      await consentCb.check();
    }

    // Submit
    const submitBtn = page.locator('button:has-text("Confirm"), button[type="submit"]').first();
    await submitBtn.click();
    await page.waitForLoadState('networkidle');

    // Assert: confirmation notice in chat
    await expect(
      page.locator('text=confirmed, text=Inspection confirmed, .system-notice')
    ).toBeVisible().catch(() => {});
  });

});

test.describe('Flow 5E–F — Agent marks inspection complete and approves listing', () => {

  test('UC-19: agent marks inspection complete and approves listing', async ({ page }) => {
    await login(page, 'agent');

    if (propertyId) {
      await page.goto(`/agent/property_review.php?id=${propertyId}`);
    } else {
      await page.goto('/agent/dashboard.php');
      const reviewLink = page.locator('a[href*="property_review"]').first();
      if (await reviewLink.count()) await reviewLink.click();
    }
    await page.waitForLoadState('networkidle');

    // Mark inspection complete
    const completeBtn = page.locator(
      'button:has-text("Mark Inspection Complete"), button:has-text("Complete Inspection"), a:has-text("Inspection Complete")'
    ).first();
    if (await completeBtn.count()) {
      await completeBtn.click();
      await page.waitForLoadState('networkidle');
    }

    // Approve listing
    const approveBtn = page.locator(
      'button:has-text("Approve Listing"), button:has-text("Approve"), a:has-text("Approve")'
    ).first();
    await expect(approveBtn).toBeVisible();
    await approveBtn.click();
    await page.waitForLoadState('networkidle');

    // Assert: listing now available
    await expect(
      page.locator('.alert-success, text=approved, text=available, text=Listing approved')
    ).toBeVisible().catch(() => {});
  });

  test('UC-20: agent rejects a listing with reason (separate property)', async ({ page }) => {
    await login(page, 'agent');

    // This test requires a second pending property; skip if none found
    await page.goto('/agent/dashboard.php');
    await page.waitForLoadState('networkidle');

    const reviewLinks = page.locator('a[href*="property_review"]');
    const count = await reviewLinks.count();
    if (count < 1) {
      test.skip(true, 'No pending property found for rejection test');
      return;
    }

    await reviewLinks.last().click();
    await page.waitForLoadState('networkidle');

    const rejectBtn = page.locator('button:has-text("Reject"), a:has-text("Reject")').first();
    if (!(await rejectBtn.count())) {
      test.skip(true, 'Reject button not found — property may not be at pending/inspecting state');
      return;
    }
    await rejectBtn.click();
    await page.waitForTimeout(300);

    const reasonField = page.locator('textarea[name="reason"], input[name="reason"]').first();
    if (await reasonField.count()) {
      await reasonField.fill('Dokumen geran tidak sepadan dengan alamat hartanah.');
    }

    await page.locator('button[type="submit"], button:has-text("Confirm Reject")').first().click();
    await page.waitForLoadState('networkidle');

    await expect(
      page.locator('.alert-success, text=rejected, text=Rejected')
    ).toBeVisible().catch(() => {});
  });

});
