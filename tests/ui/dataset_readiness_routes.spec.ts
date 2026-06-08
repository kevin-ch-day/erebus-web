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

test('dataset readiness routes render without application errors', async ({ page }) => {
  const errors = trackConsoleErrors(page);

  await page.goto('/index.php?p=dataset_readiness');
  await expect(page.locator('.page-hero-title')).toContainText('Dataset Readiness');
  await expect(page.locator('body')).not.toContainText('Application Error');

  await page.goto('/index.php?p=label_surfaces&q=deVixor&page_size=3');
  await expect(page.locator('.page-hero-title')).toContainText('Label Surfaces');
  await expect(page.locator('body')).toContainText('Sample Comparison');
  await expect(page.locator('body')).not.toContainText('Application Error');

  await page.goto('/index.php?p=type_benchmark');
  await expect(page.locator('.page-hero-title')).toContainText('Type Benchmark');
  await expect(page.locator('body')).toContainText('Governed Type Classes');
  await expect(page.locator('body')).not.toContainText('Application Error');

  await page.goto('/index.php?p=dataset_exports');
  await expect(page.locator('.page-hero-title')).toContainText('Export Readiness');
  await expect(page.locator('body')).not.toContainText('Application Error');

  expect(errors).toEqual([]);
});
