(() => {
  if (!window.App) return;
  const displayEl = document.getElementById('topbar-time-display');
  const utcEl = document.getElementById('topbar-time-utc');
  const displayLabelEl = document.getElementById('topbar-time-display-label');
  const secondaryEl = document.getElementById('topbar-time-secondary');
  const secondaryLabelEl = document.getElementById('topbar-time-secondary-label');
  const secondaryBlockEl = document.getElementById('topbar-time-secondary-block');
  if (!displayEl && !utcEl && !secondaryEl) return;

  function getDisplayTz() {
    if (App.getDisplayTz) {
      return App.getDisplayTz();
    }
    return document.documentElement.dataset.displayTz || 'UTC';
  }

  function getDisplayTzKey() {
    return document.documentElement.dataset.displayTzKey || '';
  }

  function getSecondaryTz() {
    return document.documentElement.dataset.secondaryTz || '';
  }

  function getSecondaryTzKey() {
    return document.documentElement.dataset.secondaryTzKey || '';
  }

  function displayTzLabel() {
    return tzLabelFromKeyOrId(getDisplayTzKey(), getDisplayTz()) || 'Primary';
  }

  function secondaryTzLabel() {
    return tzLabelFromKeyOrId(getSecondaryTzKey(), getSecondaryTz()) || 'Second';
  }

  function tzLabelFromKeyOrId(key, tz) {
    const keyLabels = {
      minneapolis: 'Minneapolis',
      denver: 'Denver',
      las_vegas: 'Las Vegas',
      new_york: 'New York',
      anchorage: 'Anchorage',
      honolulu: 'Honolulu',
      amsterdam: 'Amsterdam',
      paris: 'Paris',
      utc: 'UTC',
      dubai: 'Dubai',
      tokyo: 'Tokyo',
    };
    if (key && keyLabels[key]) return keyLabels[key];

    const tzLabels = {
      'America/Chicago': 'Minneapolis',
      'America/Denver': 'Denver',
      'America/Los_Angeles': 'Las Vegas',
      'America/New_York': 'New York',
      'America/Anchorage': 'Anchorage',
      'Pacific/Honolulu': 'Honolulu',
      'Europe/Amsterdam': 'Amsterdam',
      'Europe/Paris': 'Paris',
      UTC: 'UTC',
      'Asia/Dubai': 'Dubai',
      'Asia/Tokyo': 'Tokyo',
    };
    return tzLabels[tz] || tz || '';
  }

  function buildFormatter(timeZone) {
    const options = {
      timeZone,
      hour: 'numeric',
      minute: '2-digit',
      hour12: true,
    };
    try {
      return new Intl.DateTimeFormat('en-US', options);
    } catch (_) {
      return new Intl.DateTimeFormat('en-US', { ...options, timeZone: 'UTC' });
    }
  }

  let currentDisplayTz = getDisplayTz();
  let currentSecondaryTz = getSecondaryTz();
  let displayFormatter = buildFormatter(currentDisplayTz);
  let secondaryFormatter = currentSecondaryTz ? buildFormatter(currentSecondaryTz) : null;
  const utcFormatter = buildFormatter('UTC');

  function syncDisplayLabel() {
    if (displayLabelEl) {
      displayLabelEl.textContent = displayTzLabel();
    }
    if (secondaryLabelEl) {
      secondaryLabelEl.textContent = secondaryTzLabel();
    }
    if (secondaryBlockEl) {
      secondaryBlockEl.hidden = !currentSecondaryTz;
    }
  }

  function render() {
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
    syncDisplayLabel();
    const now = new Date();
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
  setInterval(render, 30000);
})();
