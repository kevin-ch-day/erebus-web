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

test('family repair queue renders structured repair lanes and alias review slices', async ({ page }) => {
  const errors = trackConsoleErrors(page);

  await page.goto('/index.php?p=family_taxonomy_queue&limit=25');

  await expect(page.getByRole('heading', { name: 'Family Repair Queue' })).toBeVisible();
  await expect(page.locator('#family-taxonomy-queue-meta')).toContainText('Primary:', { timeout: 15000 });
  await expect(page.locator('#family-taxonomy-queue-active-slice')).toContainText('Broad queue view');
  await expect(page.locator('#family-taxonomy-queue-presets .detail-card').first()).toBeVisible();
  await expect(page.locator('#family-taxonomy-queue-decisions .detail-card').first()).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Conflict hotspots' })).toBeVisible();
  await expect(page.locator('#family-taxonomy-queue-hotspots .detail-card').first()).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Governance targets' })).toBeVisible();
  await expect(page.locator('#family-taxonomy-queue-governance-body tr').first()).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Untargeted conflict drivers' })).toBeVisible();
  await expect(page.locator('#family-taxonomy-queue-untargeted-summary .detail-card').first()).toBeVisible();
  await expect(page.locator('#family-taxonomy-queue-untargeted-body tr').first()).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Dry-run repair plan' })).toBeVisible();
  await expect(page.locator('#family-taxonomy-queue-plan-summary .detail-card').first()).toBeVisible();
  await expect(page.locator('#family-taxonomy-queue-apply-plan-body tr').first()).toBeVisible();
  await expect(page.locator('#family-taxonomy-queue-actions .detail-card').first()).toBeVisible();
  await expect(page.locator('#family-taxonomy-queue-issues .detail-card').first()).toBeVisible();
  await expect(page.locator('#family-taxonomy-queue-opportunities-body tr').first()).toBeVisible();
  await expect(page.locator('#family-taxonomy-queue-rows-body tr').first()).toBeVisible();

  await page.locator('#family-taxonomy-queue-pattern').selectOption('alias_candidate');
  await page.locator('#family-taxonomy-queue-refresh').click();

  await expect(page).toHaveURL(/pattern=alias_candidate/);
  await expect(page.locator('#family-taxonomy-queue-rows-body')).toContainText('alias_candidate', { timeout: 15000 });
  await expect(page.locator('#family-taxonomy-queue-rows-body')).toContainText('repair_after_alias_review');

  const exportHref = await page.locator('#family-taxonomy-queue-export').getAttribute('href');
  expect(exportHref).toBeTruthy();
  expect(exportHref).toContain('family_taxonomy_queue_export.php');
  expect(exportHref).toContain('pattern=alias_candidate');

  const hotspotHref = await page.locator('#family-taxonomy-queue-hotspots .detail-card a').first().getAttribute('href');
  expect(hotspotHref).toBeTruthy();
  expect(hotspotHref).toContain('pair_catalog=');
  expect(hotspotHref).toContain('pair_signal=');

  expect(errors).toEqual([]);
});

test('family repair queue recovers stale focused pair filters before rendering support context', async ({ page }) => {
  const errors = trackConsoleErrors(page);

  await page.goto('/index.php?p=family_taxonomy_queue&limit=100&decision_mode=ask_why_first&pair_catalog=AppLite&pair_signal=hqwar');

  await expect(page.getByRole('heading', { name: 'Current review focus' })).toBeVisible();
  await expect(page.locator('#family-taxonomy-queue-focus')).toContainText('Focused conflict review', { timeout: 15000 });
  await expect(page.locator('#family-taxonomy-queue-focus')).toContainText('AppLite vs hqwar');
  await expect(page.locator('#family-taxonomy-queue-focus')).toContainText('hold_generic_signal');
  await expect(page.locator('#family-taxonomy-queue-focus')).toContainText('automatically reopened the live slice in hold_generic_signal');
  await expect(page.locator('#family-taxonomy-queue-active-slice')).toContainText('Pair focus', { timeout: 15000 });
  await expect(page.locator('#family-taxonomy-queue-active-slice')).toContainText('Decision lane', { timeout: 15000 });
  await expect(page.locator('#family-taxonomy-queue-rows-copy')).toContainText('focused conflict');
  await expect(page.locator('#family-taxonomy-queue-alignment-field')).toHaveAttribute('hidden', '');
  await expect(page.locator('#family-taxonomy-queue-pattern-field')).toHaveAttribute('hidden', '');
  await expect(page.locator('#family-taxonomy-queue-presets-section')).toHaveAttribute('hidden', '');
  await expect(page.locator('#family-taxonomy-queue-hotspots-section')).toHaveAttribute('hidden', '');
  await expect(page.locator('#family-taxonomy-queue-untargeted-section')).toHaveAttribute('hidden', '');
  await expect(page.locator('#family-taxonomy-queue-issues-section')).toHaveAttribute('hidden', '');
  await expect(page.getByRole('heading', { name: 'Repair rows' })).toBeVisible();
  await expect(page.getByRole('heading', { name: 'Support context' })).toBeVisible();
  await expect(page.locator('#family-taxonomy-queue-support-copy')).toContainText('Noise-reduced support view');
  await expect(page.locator('#family-taxonomy-queue-meta')).toContainText('Recovered: ask_why_first -> hold_generic_signal');

  const rowsHeader = page.locator('#family-taxonomy-queue-rows-section');
  await expect(rowsHeader).toBeVisible();
  await expect(page.locator('#family-taxonomy-queue-rows-body tr').first()).toBeVisible();

  expect(errors).toEqual([]);
});
