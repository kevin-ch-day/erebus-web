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

test('stack audit renders runtime, gaps, upgrade tracks, and CLI entry points', async ({ page }) => {
  const errors = trackConsoleErrors(page);

  await page.goto('/index.php?p=stack_audit');

  await expect(page.getByRole('heading', { name: 'Tech Stack Audit' })).toBeVisible();
  await expect(page.locator('#stack-audit-meta')).toContainText('Loaded:', { timeout: 15000 });
  await expect(page.locator('#stack-audit-runtime')).toContainText('Frontend stack');
  await expect(page.locator('#stack-audit-runtime')).toContainText('@playwright/test');
  await expect(page.locator('#stack-audit-runtime')).toContainText('TS source pages');
  await expect(page.locator('#stack-audit-architecture')).toContainText('Composer-managed PHP project');
  await expect(page.getByRole('link', { name: 'OpenAPI JSON' })).toBeVisible();
  await expect(page.locator('#stack-audit-gaps-body')).toContainText('No platform gaps detected.');
  await expect(page.locator('#stack-audit-tracks')).toContainText('Harden the current PHP + TypeScript islands stack');
  await expect(page.locator('#stack-audit-cli')).toContainText('family:summary');
  await expect(page.locator('#stack-audit-cli')).toContainText('family:export');
  await expect(page.locator('#stack-audit-cli')).toContainText('family:apply-plan');
  await expect(page.locator('#stack-audit-anchors')).toContainText('Playwright testing docs');

  expect(errors).toEqual([]);
});
