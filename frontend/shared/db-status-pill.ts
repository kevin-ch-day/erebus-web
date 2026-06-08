import { App } from './app-core';

const targets = Array.from(document.querySelectorAll<HTMLElement>('[data-db-status]'));

if (targets.length > 0) {
  const endpointSource = document.querySelector<HTMLElement>('[data-health-url]');
  const endpoint = endpointSource?.dataset.healthUrl || null;

  if (endpoint) {
    const rawInterval = endpointSource?.dataset.healthInterval
      ? Number(endpointSource.dataset.healthInterval)
      : 15;
    const intervalSeconds = Number.isFinite(rawInterval) ? rawInterval : 15;
    const intervalMs = Math.max(5, intervalSeconds) * 1000;

    const utcTime = (): string => new Date().toISOString().split('T')[1]?.split('.')[0] || '--:--:--';

    const noteFromUtc = (value: unknown): string => {
      if (!value) return utcTime();
      const raw = String(value);
      if (raw.includes(' ')) {
        const parts = raw.split(' ');
        return parts[1] || raw;
      }
      return raw;
    };

    const updateTargets = (status: 'ok' | 'warn' | 'error', note: string, tooltip: string): void => {
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

    const loadHealth = async (): Promise<void> => {
      try {
        const res = await App.fetchJson(endpoint);
        if (!res.ok) {
          const detail = res.error ? ` ${res.error}` : '';
          updateTargets('error', utcTime(), `Health API unreachable (HTTP ${res.status || 0}).${detail}`);
          return;
        }

        const data = res.body as Record<string, unknown>;
        if (!data || data.ok !== true) {
          const detail = typeof data?.error === 'string' ? data.error : 'Backend exception';
          updateTargets('error', utcTime(), `Backend exception: ${detail}`);
          return;
        }

        const schemaGuard = (data.schema_guard as Record<string, unknown> | undefined) || {};
        const collationGuard = (data.collation_guard as Record<string, unknown> | undefined) || {};
        const rollupGuard = (data.rollup_guard as Record<string, unknown> | undefined) || {};
        const schemaStatus = typeof schemaGuard.status === 'string' ? schemaGuard.status : '';
        const collationStatus = typeof collationGuard.status === 'string' ? collationGuard.status : '';
        const rollupStatus = typeof rollupGuard.status === 'string' ? rollupGuard.status : '';
        const hasWarn = [schemaStatus, collationStatus, rollupStatus].some((status) => status === 'warn');

        updateTargets(
          hasWarn ? 'warn' : 'ok',
          noteFromUtc(data.utc_now),
          hasWarn ? 'DB reachable; guard checks warn.' : 'DB health check OK.'
        );
      } catch {
        updateTargets('error', utcTime(), 'Health API unreachable.');
      }
    };

    void loadHealth();
    window.setInterval(() => {
      void loadHealth();
    }, intervalMs);
  }
}
