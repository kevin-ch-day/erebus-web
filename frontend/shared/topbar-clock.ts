import { App } from './app-core';

const displayEl = document.getElementById('topbar-time-display');
const utcEl = document.getElementById('topbar-time-utc');
const displayLabelEl = document.getElementById('topbar-time-display-label');

if (displayEl || utcEl) {
  function getDisplayTz(): string {
    return App.getDisplayTz ? App.getDisplayTz() : (document.documentElement.dataset.displayTz || 'UTC');
  }

  function buildFormatter(timeZone: string): Intl.DateTimeFormat {
    const options: Intl.DateTimeFormatOptions = {
      timeZone,
      hour: 'numeric',
      minute: '2-digit',
      hour12: true,
    };
    try {
      return new Intl.DateTimeFormat('en-US', options);
    } catch {
      return new Intl.DateTimeFormat('en-US', { ...options, timeZone: 'UTC' });
    }
  }

  let currentDisplayTz = getDisplayTz();
  let displayFormatter = buildFormatter(currentDisplayTz);
  const utcFormatter = buildFormatter('UTC');

  function syncDisplayLabel(): void {
    if (displayLabelEl) {
      displayLabelEl.textContent = currentDisplayTz || 'Display time';
    }
  }

  function render(): void {
    const nextTz = getDisplayTz();
    if (nextTz !== currentDisplayTz) {
      currentDisplayTz = nextTz;
      displayFormatter = buildFormatter(currentDisplayTz);
      syncDisplayLabel();
    }
    const now = new Date();
    if (displayEl) displayEl.textContent = displayFormatter.format(now);
    if (utcEl) utcEl.textContent = utcFormatter.format(now);
  }

  window.addEventListener('topbar-tz-change', render);
  syncDisplayLabel();
  render();
  window.setInterval(render, 30000);
}
