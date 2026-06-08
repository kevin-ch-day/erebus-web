import type { AppSurface, JsonRecord } from '../types/app-globals';

type StackRuntimeDependency = {
  name?: unknown;
  installed?: unknown;
  wanted?: unknown;
  engines?: {
    node?: unknown;
  } | null;
};

type StackArchitectureRow = {
  layer?: unknown;
  current_shape?: unknown;
  evidence?: unknown;
};

type StackGapRow = {
  severity?: unknown;
  title?: unknown;
  why?: unknown;
};

type StackTrackRow = {
  title?: unknown;
  effort?: unknown;
  candidate_tech?: unknown;
  why?: unknown;
  best_for?: unknown;
};

type StackCliRow = {
  label?: unknown;
  command?: unknown;
  why?: unknown;
};

type StackAnchorRow = {
  label?: unknown;
  url?: unknown;
  why?: unknown;
};

type StackRuntime = {
  php_runtime?: unknown;
  app_package?: unknown;
  app_package_version?: unknown;
  project_root?: unknown;
  frontend_dependencies?: unknown;
};

type StackCapabilities = {
  view_count?: unknown;
  api_endpoint_count?: unknown;
  ts_page_count?: unknown;
  typed_source_page_count?: unknown;
  api_contract_count?: unknown;
  ui_spec_count?: unknown;
};

type StackAuditData = JsonRecord & {
  runtime?: StackRuntime;
  capabilities?: StackCapabilities;
  architecture_profile?: unknown;
  gap_inventory?: unknown;
  upgrade_tracks?: unknown;
  cli_entrypoints?: unknown;
  research_anchors?: unknown;
  project_root?: unknown;
};

type StackAuditMeta = JsonRecord & {
  generated_at_utc?: unknown;
};

const root = document.getElementById('stack-audit-page') as HTMLElement | null;

function asRows<T>(value: unknown): T[] {
  return Array.isArray(value) ? (value as T[]) : [];
}

function toRecord(value: unknown): JsonRecord {
  return value && typeof value === 'object' ? (value as JsonRecord) : {};
}

if (root && window.App) {
  const App = window.App as AppSurface;
  const endpoint = root.dataset.endpoint || '';

  if (!endpoint) {
    // no-op
  } else {
    const metaEl = document.getElementById('stack-audit-meta');
    const runtimeEl = document.getElementById('stack-audit-runtime');
    const architectureEl = document.getElementById('stack-audit-architecture');
    const gapsBodyEl = document.getElementById('stack-audit-gaps-body');
    const tracksEl = document.getElementById('stack-audit-tracks');
    const cliEl = document.getElementById('stack-audit-cli');
    const anchorsEl = document.getElementById('stack-audit-anchors');
    const errorEl = document.getElementById('stack-audit-error');

    const esc = App.escapeHtml;
    const formatUtc = App.formatUtc;

    function severityBadge(value: unknown): string {
      const key = String(value || '').toLowerCase();
      if (key === 'critical') return 'badge err';
      if (key === 'warn') return 'badge warn';
      if (key === 'info') return 'badge muted';
      return 'badge muted';
    }

    function renderRuntime(runtime: StackRuntime, capabilities: StackCapabilities): void {
      if (!runtimeEl) return;
      const frontendDeps = asRows<StackRuntimeDependency>(runtime.frontend_dependencies);
      const projectRoot = String(runtime.project_root || '');
      runtimeEl.innerHTML = `
        <div class="detail-card">
          <div class="detail-card-title">Runtime</div>
          <div class="detail-row"><div class="detail-label">PHP runtime</div><div class="detail-value">${esc(runtime.php_runtime || '--')}</div></div>
          <div class="detail-row"><div class="detail-label">App package</div><div class="detail-value">${esc(runtime.app_package || '--')} ${esc(runtime.app_package_version || '')}</div></div>
          <div class="detail-row"><div class="detail-label">Project root</div><div class="detail-value mono">${esc(projectRoot.split('/').slice(-2).join('/'))}</div></div>
        </div>
        <div class="detail-card">
          <div class="detail-card-title">Frontend stack</div>
          ${frontendDeps.map((dep) => `
            <div class="detail-row">
              <div class="detail-label">${esc(dep.name || '--')}</div>
              <div class="detail-value">${esc(dep.installed || dep.wanted || '--')}</div>
            </div>
            ${dep.engines && dep.engines.node ? `<div class="detail-row"><div class="detail-label muted">Node requirement</div><div class="detail-value muted">${esc(dep.engines.node)}</div></div>` : ''}
          `).join('')}
        </div>
        <div class="detail-card">
          <div class="detail-card-title">Current tooling</div>
          <div class="detail-row"><div class="detail-label">Views</div><div class="detail-value">${esc(String(capabilities.view_count || 0))}</div></div>
          <div class="detail-row"><div class="detail-label">API endpoints</div><div class="detail-value">${esc(String(capabilities.api_endpoint_count || 0))}</div></div>
          <div class="detail-row"><div class="detail-label">TS page modules</div><div class="detail-value">${esc(String(capabilities.ts_page_count || 0))}</div></div>
          <div class="detail-row"><div class="detail-label">TS source pages</div><div class="detail-value">${esc(String(capabilities.typed_source_page_count || 0))}</div></div>
          <div class="detail-row"><div class="detail-label">API contracts</div><div class="detail-value">${esc(String(capabilities.api_contract_count || 0))}</div></div>
          <div class="detail-row"><div class="detail-label">UI specs</div><div class="detail-value">${esc(String(capabilities.ui_spec_count || 0))}</div></div>
        </div>
      `;
    }

    function renderArchitecture(rows: StackArchitectureRow[]): void {
      if (!architectureEl) return;
      if (!rows.length) {
        architectureEl.innerHTML = '<div class="detail-card"><div class="muted">No architecture profile available.</div></div>';
        return;
      }
      architectureEl.innerHTML = rows.map((row) => `
        <div class="detail-card">
          <div class="detail-card-title">${esc(row.layer || '--')}</div>
          <div class="detail-row"><div class="detail-label">Current shape</div><div class="detail-value">${esc(row.current_shape || '--')}</div></div>
          <div class="detail-row"><div class="detail-label">Evidence</div><div class="detail-value">${esc(row.evidence || '--')}</div></div>
        </div>
      `).join('');
    }

    function renderGaps(rows: StackGapRow[]): void {
      if (!gapsBodyEl) return;
      if (!rows.length) {
        gapsBodyEl.innerHTML = '<tr><td colspan="3" class="muted">No platform gaps detected.</td></tr>';
        return;
      }
      gapsBodyEl.innerHTML = rows.map((row) => `
        <tr>
          <td><span class="${severityBadge(row.severity)}">${esc(row.severity || '--')}</span></td>
          <td>${esc(row.title || '--')}</td>
          <td class="cell-wrap">${esc(row.why || '--')}</td>
        </tr>
      `).join('');
    }

    function renderTracks(rows: StackTrackRow[]): void {
      if (!tracksEl) return;
      if (!rows.length) {
        tracksEl.innerHTML = '<div class="detail-card"><div class="muted">No upgrade tracks available.</div></div>';
        return;
      }
      tracksEl.innerHTML = rows.map((row) => `
        <div class="detail-card">
          <div class="detail-card-title">${esc(row.title || '--')}</div>
          <div class="detail-row"><div class="detail-label">Effort</div><div class="detail-value">${esc(row.effort || '--')}</div></div>
          <div class="detail-row"><div class="detail-label">Candidate tech</div><div class="detail-value cell-wrap">${esc(row.candidate_tech || '--')}</div></div>
          <div class="detail-row"><div class="detail-label">Why</div><div class="detail-value cell-wrap">${esc(row.why || '--')}</div></div>
          <div class="detail-row"><div class="detail-label">Best for</div><div class="detail-value cell-wrap">${esc(row.best_for || '--')}</div></div>
        </div>
      `).join('');
    }

    function renderCliEntrypoints(rows: StackCliRow[]): void {
      if (!cliEl) return;
      if (!rows.length) {
        cliEl.innerHTML = '<div class="detail-card"><div class="muted">No CLI entry points available.</div></div>';
        return;
      }
      cliEl.innerHTML = rows.map((row) => `
        <div class="detail-card">
          <div class="detail-card-title">${esc(row.label || '--')}</div>
          <div class="detail-row"><div class="detail-label">Command</div><div class="detail-value mono">${esc(row.command || '--')}</div></div>
          <div class="detail-row"><div class="detail-label">Why</div><div class="detail-value cell-wrap">${esc(row.why || '--')}</div></div>
        </div>
      `).join('');
    }

    function renderAnchors(rows: StackAnchorRow[]): void {
      if (!anchorsEl) return;
      if (!rows.length) {
        anchorsEl.innerHTML = '<li class="muted">No research anchors available.</li>';
        return;
      }
      anchorsEl.innerHTML = rows.map((row) => `
        <li>
          <a class="table-link" href="${esc(row.url || '#')}" target="_blank" rel="noreferrer noopener">${esc(row.label || '--')}</a>
          <span class="muted"> — ${esc(row.why || '--')}</span>
        </li>
      `).join('');
    }

    async function load(): Promise<void> {
      App.clearPageError(errorEl);
      try {
        const res = await App.fetchJson(endpoint);
        if (!res.ok) {
          App.renderPageError(errorEl, {
            title: 'Tech stack audit unavailable',
            summary: 'The stack audit API did not return usable data, so runtime facts and upgrade-track guidance are unavailable.',
            detail: res.error,
            status: res.status,
            raw: res.raw,
            hint: 'Retry this page first. If the failure persists, verify the stack audit endpoint and supporting CLI inventory.',
            primaryActionHref: App.currentPageUrl(),
            primaryActionLabel: 'Retry stack audit',
            secondaryActionHref: App.pageUrl('landing'),
            secondaryActionLabel: 'Back to landing',
          });
          return;
        }

        const body = toRecord(res.body);
        const data = toRecord(body.data) as StackAuditData;
        const meta = toRecord(body.meta) as StackAuditMeta;
        const runtime = toRecord(data.runtime) as StackRuntime;
        runtime.project_root = data.project_root || '';

        renderRuntime(runtime, toRecord(data.capabilities) as StackCapabilities);
        renderArchitecture(asRows<StackArchitectureRow>(data.architecture_profile));
        renderGaps(asRows<StackGapRow>(data.gap_inventory));
        renderTracks(asRows<StackTrackRow>(data.upgrade_tracks));
        renderCliEntrypoints(asRows<StackCliRow>(data.cli_entrypoints));
        renderAnchors(asRows<StackAnchorRow>(data.research_anchors));

        if (metaEl) {
          metaEl.textContent = `Loaded: ${formatUtc(meta.generated_at_utc || new Date().toISOString())}`;
        }
      } catch (err) {
        App.renderPageError(errorEl, {
          title: 'Tech stack audit load failed',
          summary: 'The browser hit an unexpected failure while rendering the stack inventory.',
          detail: err instanceof Error ? err.message : String(err),
          hint: 'Reload once. If it still fails, inspect the audit endpoint and recent frontend changes.',
          primaryActionHref: App.currentPageUrl(),
          primaryActionLabel: 'Reload stack audit',
          secondaryActionHref: App.pageUrl('landing'),
          secondaryActionLabel: 'Back to landing',
        });
      }
    }

    void load();
  }
}
