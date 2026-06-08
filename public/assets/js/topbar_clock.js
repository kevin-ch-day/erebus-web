(() => {
  if (!window.App) return;
  const displayEl = document.getElementById('topbar-time-display');
  const utcEl = document.getElementById('topbar-time-utc');
  const displayLabelEl = document.getElementById('topbar-time-display-label');
  if (!displayEl && !utcEl) return;

  function getDisplayTz() {
    if (App.getDisplayTz) {
      return App.getDisplayTz();
    }
    return document.documentElement.dataset.displayTz || 'UTC';
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
  let displayFormatter = buildFormatter(currentDisplayTz);
  const utcFormatter = buildFormatter('UTC');

  function syncDisplayLabel() {
    if (displayLabelEl) {
      displayLabelEl.textContent = currentDisplayTz || 'Display time';
    }
  }

  function render() {
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
  setInterval(render, 30000);
})();
