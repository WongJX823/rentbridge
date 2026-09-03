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
  title:        'Bilik Sewa Dekat UTeM — Taman Muzaffar',
  type:         'room',
  monthly_rent: '380',
  deposit:      '760',
  address:      'No. 5, Jalan Muzaffar 7',
  city:         'Ayer Keroh',
  postcode:     '75450',
};

test.describe('Flow 1 — Landlord property registration', () => {

  test('UC-01: landlord can submit a new property and it reaches pending_approval', async ({ page }) => {
    await login(page, 'landlord');

    // Assert redirected to landlord dashboard
    await expect(page).toHaveURL(/landlord/);

    // Navigate to add property form
    await page.goto('landlord/add_property.php');
    await expect(page).toHaveURL(/add_property/);

    // Fill core property details
    await page.fill('input[name="title"]', PROPERTY.title);

    const typeSelect = page.locator('select[name="property_type"]');
    if (await typeSelect.count()) {
      await typeSelect.selectOption(PROPERTY.type);
    }

    await page.fill('input[name="monthly_rent"]', PROPERTY.monthly_rent);
    await page.fill('input[name="deposit"]', PROPERTY.deposit);

    const furnishSelect = page.locator('select[name="furnishing"]');
    if (await furnishSelect.count()) {
      await furnishSelect.selectOption('full');
    }

    // Address (rendered as a textarea by rb_address_pin_field())
    await page.fill('textarea[name="address"]', PROPERTY.address);
    // City is a fixed dropdown of Melaka areas
    await page.selectOption('select[name="city"]', PROPERTY.city);
    await page.fill('input[name="postcode"]', PROPERTY.postcode);

    // Required: viewing arrangement + inspection consent
    await page.selectOption('select[name="viewing_mode"]', 'either');
    const consentBox = page.locator('input[name="inspection_consent"]');
    if (await consentBox.count() && !(await consentBox.isChecked())) {
      await consentBox.check();
    }

    // Upload property photo
    const photoInput = page.locator('input[type="file"][name*="photo"], input[type="file"][name*="image"]').first();
    if (await photoInput.count()) {
      await photoInput.setInputFiles(path.join(__dirname, '..', 'test_photo.jpg'));
    }

    // Upload ownership document (must pick a document type or the upload is rejected)
    const docTypeSelect = page.locator('select[name="document_types[]"]').first();
    if (await docTypeSelect.count()) {
      await docTypeSelect.selectOption('ownership_proof');
    }
    const docInput = page.locator('input[type="file"][name*="doc"], input[type="file"][name*="document"]').first();
    if (await docInput.count()) {
      await docInput.setInputFiles(path.join(__dirname, '..', 'test_document.pdf'));
    }

    // Submit (scoped text — a generic button[type="submit"] also matches the
    // persistent "Mark all read" notifications button elsewhere on the page)
    await page.click('button:has-text("Upload property")');
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
    await page.goto('landlord/properties.php');
    await expect(page.locator('text=' + PROPERTY.title)).toBeVisible();

    // Assert: status badge shows pending_approval (rendered as "Pending review")
    await expect(page.getByText('Pending review').first()).toBeVisible();
  });

});
