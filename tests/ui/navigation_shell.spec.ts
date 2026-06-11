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

test('navigation shell promotes priority lanes and grouped navigation', async ({ page }) => {
  const errors = trackConsoleErrors(page);

  await page.goto('/index.php?p=landing');

  await expect(page.locator('.topbar-eyebrow')).toContainText('Threat Workspace');
  await expect(page.locator('.topbar-title')).toContainText('Home');

  await expect(page.locator('.sidebar-priority')).toContainText('Priority Lanes');
  await expect(page.locator('.sidebar-priority-link')).toHaveCount(4);

  const workspaceSection = page.locator('.nav-section[data-section="console"]');
  await expect(workspaceSection.locator('.nav-section-toggle')).toContainText('Threat Workspace');
  await expect(workspaceSection.locator('.nav-group-label')).toHaveCount(0);
  await expect(workspaceSection.locator('.nav-link').nth(0)).toContainText('Home');
  await expect(workspaceSection.locator('.nav-link').nth(1)).toContainText('Malware Samples');
  await expect(workspaceSection.locator('.nav-link').nth(2)).toContainText('Check Hash');
  await expect(workspaceSection.locator('.nav-link').nth(3)).toContainText('Submit Artifact');
  await expect(workspaceSection.locator('.nav-link').nth(4)).toContainText('Ingest Backlog');
  await expect(workspaceSection.locator('.nav-link').nth(5)).toContainText('Pending Source Mix');
  await expect(page.locator('.nav-section[data-section="intake"]')).toHaveCount(0);

  const permissionsToggle = page.locator('.nav-section[data-section="permissions"] .nav-section-toggle');
  await permissionsToggle.click();
  await expect(page.locator('.nav-section[data-section="permissions"] .nav-group-label')).toContainText([
    'Review workflow',
    'Reference surfaces',
  ]);

  const pipelineToggle = page.locator('.nav-section[data-section="pipeline"] .nav-section-toggle');
  await pipelineToggle.click();
  await expect(page.locator('.nav-section[data-section="pipeline"]')).toContainText('Queue state');

  expect(errors).toEqual([]);
});
