import { App } from './app-core';
import { tzLabelFromKeyOrId } from './operator-clocks';

const displayEl = document.getElementById('topbar-time-display');
const utcEl = document.getElementById('topbar-time-utc');
const displayLabelEl = document.getElementById('topbar-time-display-label');
const secondaryEl = document.getElementById('topbar-time-secondary');
const secondaryLabelEl = document.getElementById('topbar-time-secondary-label');
const secondaryBlockEl = document.getElementById('topbar-time-secondary-block');

if (displayEl || utcEl || secondaryEl) {
  function getDisplayTz(): string {
    return App.getDisplayTz ? App.getDisplayTz() : (document.documentElement.dataset.displayTz || 'UTC');
  }

  function getDisplayTzKey(): string {
    return document.documentElement.dataset.displayTzKey || '';
  }

  function getSecondaryTz(): string {
    return App.getSecondaryTz ? App.getSecondaryTz() : (document.documentElement.dataset.secondaryTz || '');
  }

  function getSecondaryTzKey(): string {
    return document.documentElement.dataset.secondaryTzKey || '';
  }

  function displayTzLabel(): string {
    return tzLabelFromKeyOrId(getDisplayTzKey(), getDisplayTz()) || 'Primary';
  }

  function secondaryTzLabel(): string {
    return tzLabelFromKeyOrId(getSecondaryTzKey(), getSecondaryTz()) || 'Second';
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
  let currentSecondaryTz = getSecondaryTz();
  let displayFormatter = buildFormatter(currentDisplayTz);
  let secondaryFormatter = currentSecondaryTz ? buildFormatter(currentSecondaryTz) : null;
  const utcFormatter = buildFormatter('UTC');

  function syncDisplayLabel(): void {
    if (displayLabelEl) {
      displayLabelEl.textContent = displayTzLabel();
    }
    if (secondaryLabelEl) {
      secondaryLabelEl.textContent = secondaryTzLabel();
    }
    if (secondaryBlockEl instanceof HTMLElement) {
      secondaryBlockEl.hidden = !currentSecondaryTz;
    }
  }

  function render(): void {
    const nextTz = getDisplayTz();
    const nextSecondaryTz = getSecondaryTz();
    if (nextTz !== currentDisplayTz) {
      currentDisplayTz = nextTz;
      displayFormatter = buildFormatter(currentDisplayTz);
    }
    if (nextSecondaryTz !== currentSecondaryTz) {
      currentSecondaryTz = nextSecondaryTz;
      secondaryFormatter = currentSecondaryTz ? buildFormatter(currentSecondaryTz) : null;
    }
    const now = new Date();
    syncDisplayLabel();
    if (displayEl) displayEl.textContent = displayFormatter.format(now);
    if (secondaryEl && secondaryFormatter) {
      secondaryEl.textContent = secondaryFormatter.format(now);
    } else if (secondaryEl) {
      secondaryEl.textContent = '--';
    }
    if (utcEl) utcEl.textContent = utcFormatter.format(now);
  }

  window.addEventListener('topbar-tz-change', render);
  syncDisplayLabel();
  render();
  window.setInterval(render, 30000);
}
