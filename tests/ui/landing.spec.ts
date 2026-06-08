import { expect, test, type Page } from '@playwright/test';

function trackConsoleErrors(page: Page) {
  const errors: string[] = [];
  page.on('console', (message) => {
    if (message.type() === 'error') {
      errors.push(message.text());
    }
  });
  page.on('pageerror', (error) => {
    errors.push(error.message);
  });
  return errors;
}

test('landing page renders live control deck sections and hotspot links', async ({ page }) => {
  const errors = trackConsoleErrors(page);

  await page.goto('/index.php?p=landing');

  await expect(page.getByRole('heading', { name: /Control Deck/ })).toBeVisible();
  await expect(page.locator('#landing-priority-notice')).not.toContainText('Loading', { timeout: 15000 });
  await expect(page.locator('#landing-health-metric')).not.toHaveText('--', { timeout: 15000 });
  await expect(page.locator('#landing-family-metric')).not.toHaveText('--', { timeout: 15000 });
  await expect(page.locator('#landing-stack-metric')).not.toHaveText('--', { timeout: 15000 });
  await expect(page.getByRole('heading', { name: 'Conflict Hotspots' })).toBeVisible();
  await expect(page.locator('#landing-hotspots .detail-card').first()).toBeVisible();

  const hotspotHref = await page.locator('#landing-hotspots .detail-card a').first().getAttribute('href');
  expect(hotspotHref).toBeTruthy();
  expect(hotspotHref).toContain('family_taxonomy_queue');
  expect(hotspotHref).toContain('pair_catalog=');
  expect(hotspotHref).toContain('pair_signal=');

  expect(errors).toEqual([]);
});
