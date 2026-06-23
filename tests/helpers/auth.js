/** Shared login helper — reused across all flow specs. */

const ACCOUNTS = {
  student1:  { email: 's1@test.com',      password: 'Test@1234', name: 'Ahmad Faris' },
  student2:  { email: 's2@test.com',      password: 'Test@1234', name: 'Lim Wei Xian' },
  student3:  { email: 's3@test.com',      password: 'Test@1234', name: 'Priya Nair' },
  student4:  { email: 's4@test.com',      password: 'Test@1234', name: 'Nurul Ain' },
  student5:  { email: 's5@test.com',      password: 'Test@1234', name: 'Tan Jia Hui' },
  student6:  { email: 's6@test.com',      password: 'Test@1234', name: 'Hafiz Zulkifli' },
  landlord:  { email: 'll@test.com',      password: 'Test@1234', name: 'Encik Roslan' },
  agent:     { email: 'agt@test.com',     password: 'Test@1234', name: 'Agent Siti' },
  admin:     { email: 'admin@test.com',   password: 'Test@1234', name: 'Admin RentBridge' },
};

/**
 * Log in as the given role.
 * @param {import('@playwright/test').Page} page
 * @param {'student1'|'student2'|'student3'|'student4'|'student5'|'student6'|'landlord'|'agent'|'admin'} role
 */
async function login(page, role) {
  const creds = ACCOUNTS[role];
  await page.goto('/auth/login.php');
  await page.fill('input[name="email"]', creds.email);
  await page.fill('input[name="password"]', creds.password);
  await page.click('button[type="submit"]');
  await page.waitForLoadState('networkidle');
}

async function logout(page) {
  await page.goto('/auth/logout.php');
}

module.exports = { login, logout, ACCOUNTS };
