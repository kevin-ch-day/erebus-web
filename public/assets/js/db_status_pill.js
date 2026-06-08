(() => {
  const targets = Array.from(document.querySelectorAll('[data-db-status]'));
  if (targets.length === 0) return;

  const endpointSource = document.querySelector('[data-health-url]');
  const endpoint = endpointSource ? endpointSource.dataset.healthUrl : null;
  if (!endpoint) return;
  const rawInterval = endpointSource && endpointSource.dataset.healthInterval
    ? Number(endpointSource.dataset.healthInterval)
    : 15;
  const intervalSeconds = Number.isFinite(rawInterval) ? rawInterval : 15;
  const intervalMs = Math.max(5, intervalSeconds) * 1000;

  const utcTime = () => new Date().toISOString().split('T')[1].split('.')[0];
  const noteFromUtc = (value) => {
    if (!value) return utcTime();
    const raw = String(value);
    if (raw.includes(' ')) {
      const parts = raw.split(' ');
      return parts[1] || raw;
    }
    return raw;
  };

  const updateTargets = (status, note, tooltip) => {
    const labelMap = {
      ok: 'DB: OK',
      warn: 'DB: WARN',
      error: 'DB: ERROR',
    };
    const statusText = labelMap[status] || labelMap.error;
    const isOk = status === 'ok';
    const isWarn = status === 'warn';
    const isError = status === 'error';
    targets.forEach((el) => {
      const showUtc = el.dataset.showUtc === '1';
      const suffix = showUtc ? ` | UTC: ${note}` : '';
      el.textContent = statusText + suffix;
      el.classList.toggle('status-ok', isOk);
      el.classList.toggle('status-warn', isWarn);
      el.classList.toggle('status-down', isError);
      if (tooltip) {
        el.title = tooltip;
      } else {
        el.removeAttribute('title');
      }
    });
  };

  const loadHealth = async () => {
    try {
      const res = await App.fetchJson(endpoint);
      if (!res.ok) {
        const detail = res.error ? ` ${res.error}` : '';
        updateTargets('error', utcTime(), `Health API unreachable (HTTP ${res.status || 0}).${detail}`);
        return;
      }

      const data = res.body;
      if (!data || data.ok !== true) {
        const detail = data && data.error ? String(data.error) : 'Backend exception';
        updateTargets('error', utcTime(), `Backend exception: ${detail}`);
        return;
      }

      const schemaStatus = data.schema_guard && data.schema_guard.status ? String(data.schema_guard.status) : '';
      const collationStatus = data.collation_guard && data.collation_guard.status ? String(data.collation_guard.status) : '';
      const rollupStatus = data.rollup_guard && data.rollup_guard.status ? String(data.rollup_guard.status) : '';
      const hasWarn = [schemaStatus, collationStatus, rollupStatus].some((status) => status === 'warn');
      updateTargets(hasWarn ? 'warn' : 'ok', noteFromUtc(data.utc_now), hasWarn ? 'DB reachable; guard checks warn.' : 'DB health check OK.');
    } catch (err) {
      updateTargets('error', utcTime(), 'Health API unreachable.');
    }
  };

  loadHealth();
  setInterval(loadHealth, intervalMs);
})();
