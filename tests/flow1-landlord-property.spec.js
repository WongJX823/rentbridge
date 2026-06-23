/**
 * FLOW 1 — Landlord registers a new property
 * UC-01
 * Actor: Landlord (ll@test.com)
 * Covers: login → add property → upload photo + doc → submit → pending_approval
 */

const { test, expect } = require('@playwright/test');
const { login } = require('./helpers/auth');
const path = require('path');

const PROPERTY = {
  title:      'Bilik Sewa Dekat UTeM — Taman Muzaffar',
  type:       'room',
  size:       '100',
  price:      '380',
  deposit:    '760',
  address:    'No. 5, Jalan Muzaffar 7',
  city:       'Ayer Keroh',
  state:      'Melaka',
  postcode:   '75450',
  distance:   '3.2',
};

test.describe('Flow 1 — Landlord property registration', () => {

  test('UC-01: landlord can submit a new property and it reaches pending_approval', async ({ page }) => {
    await login(page, 'landlord');

    // Assert redirected to landlord dashboard
    await expect(page).toHaveURL(/landlord/);

    // Navigate to add property form
    await page.goto('/landlord/add_property.php');
    await expect(page).toHaveURL(/add_property/);

    // Fill core property details
    await page.fill('input[name="title"]', PROPERTY.title);

    const typeSelect = page.locator('select[name="property_type"]');
    if (await typeSelect.count()) {
      await typeSelect.selectOption(PROPERTY.type);
    }

    await page.fill('input[name="size"]', PROPERTY.size);
    await page.fill('input[name="price"]', PROPERTY.price);
    await page.fill('input[name="deposit"]', PROPERTY.deposit);

    const furnishSelect = page.locator('select[name="furnishing"]');
    if (await furnishSelect.count()) {
      await furnishSelect.selectOption('fully_furnished');
    }

    // Address fields
    await page.fill('input[name="address"]', PROPERTY.address);
    await page.fill('input[name="city"]',    PROPERTY.city);
    await page.fill('input[name="state"]',   PROPERTY.state);
    await page.fill('input[name="postcode"]', PROPERTY.postcode);

    // Distance to UTeM
    const distField = page.locator('input[name="distance_to_utem"], input[name="distance"]');
    if (await distField.count()) {
      await distField.first().fill(PROPERTY.distance);
    }

    // Amenities checkboxes (WiFi, Air-conditioning, Water Heater)
    for (const val of ['wifi', 'aircond', 'water_heater']) {
      const cb = page.locator(`input[type="checkbox"][value="${val}"]`);
      if (await cb.count() && !(await cb.isChecked())) {
        await cb.check();
      }
    }

    // Upload property photo
    const photoInput = page.locator('input[type="file"][name*="photo"], input[type="file"][name*="image"]').first();
    if (await photoInput.count()) {
      await photoInput.setInputFiles(path.join(__dirname, '..', 'test_photo.jpg'));
    }

    // Upload ownership document
    const docInput = page.locator('input[type="file"][name*="doc"], input[type="file"][name*="document"]').first();
    if (await docInput.count()) {
      await docInput.setInputFiles(path.join(__dirname, '..', 'test_document.pdf'));
    }

    // Submit
    await page.click('button[type="submit"], input[type="submit"]');
    await page.waitForLoadState('networkidle');

    // Assert: success feedback
    const successIndicators = [
      page.locator('.alert-success'),
      page.locator('text=success'),
      page.locator('text=berjaya'),
      page.locator('text=submitted'),
    ];
    let successFound = false;
    for (const loc of successIndicators) {
      if (await loc.count()) { successFound = true; break; }
    }
    expect(successFound, 'Expected a success message after property submission').toBe(true);

    // Assert: property visible in landlord properties list
    await page.goto('/landlord/properties.php');
    await expect(page.locator('text=' + PROPERTY.title)).toBeVisible();

    // Assert: status badge shows pending_approval
    const statusBadge = page.locator('text=pending_approval, text=Pending Approval, .badge:has-text("pending")');
    await expect(statusBadge.first()).toBeVisible();
  });

});
