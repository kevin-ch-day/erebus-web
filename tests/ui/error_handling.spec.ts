import { expect, test, type Page } from '@playwright/test';

function trackConsoleErrors(page: Page, options: { ignore?: RegExp[] } = {}) {
  const errors: string[] = [];
  page.on('console', (message) => {
    if (message.type() === 'error') {
      const text = message.text();
      const shouldIgnore = (options.ignore || []).some((pattern) => pattern.test(text));
      if (!shouldIgnore) {
        errors.push(text);
      }
    }
  });
  page.on('pageerror', (error) => {
    errors.push(error.message);
  });
  return errors;
}

test('unknown route renders a recovery-oriented error page', async ({ page }) => {
  const errors = trackConsoleErrors(page, {
    ignore: [/Failed to load resource: the server responded with a status of 404/],
  });

  await page.goto('/index.php?p=not_a_real_page');

  await expect(page).toHaveURL(/p=not_a_real_page/);
  await expect(page.getByRole('heading', { name: 'Not Found' })).toBeVisible();
  await expect(page.getByText('The route or bookmark does not map to a live page in this console.')).toBeVisible();
  await expect(page.getByRole('link', { name: 'Open landing' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Request context' })).toBeVisible();
  await expect(page.getByText('Requested page', { exact: true })).toBeVisible();
  await expect(page.getByText('not_a_real_page', { exact: true })).toBeVisible();

  expect(errors).toEqual([]);
});

test('stack audit renders a structured page error for non-json API failures', async ({ page }) => {
  const errors = trackConsoleErrors(page, {
    ignore: [/Failed to load resource: the server responded with a status of 500/],
  });

  await page.route('**/api.php/stack_audit.php**', async (route) => {
    await route.fulfill({
      status: 500,
      contentType: 'text/plain',
      body: 'fatal stack audit failure',
    });
  });

  await page.goto('/index.php?p=stack_audit');

  const errorBox = page.locator('#stack-audit-error');
  await expect(errorBox).toContainText('Tech stack audit unavailable');
  await expect(errorBox).toContainText('HTTP status');
  await expect(errorBox).toContainText('500');
  await expect(errorBox).toContainText('Non-JSON response');
  await expect(errorBox).toContainText('fatal stack audit failure');
  await expect(errorBox.getByRole('link', { name: 'Retry stack audit' })).toBeVisible();
  await expect(errorBox.getByRole('link', { name: 'Back to landing' })).toBeVisible();

  expect(errors).toEqual([]);
});
