import type { AppSurface, JsonRecord } from '../types/app-globals';
import {
  asPipelineSnapshot,
  pipelinePrimaryCommand,
  pipelineActionTone,
} from '../shared/pipeline-posture';

type SourceRow = JsonRecord & {
  artifact_source?: unknown;
  pending_rows?: unknown;
};

const root = document.getElementById('pending-source-mix-page') as HTMLElement | null;

function fmtInt(value: unknown): string {
  const num = Number(value ?? 0);
  return Number.isFinite(num) ? num.toLocaleString() : '--';
}

if (root && window.App) {
  const App = window.App as AppSurface;
  const endpoint = root.dataset.endpoint || '';
  const refreshSeconds = Number(root.dataset.refreshSeconds || '30') || 30;
  const metaEl = document.getElementById('source-mix-live-meta');
  const noticeEl = document.getElementById('source-mix-engine-notice');
  const pendingTotalEl = document.getElementById('source-mix-pending-total');
  const sourceCountEl = document.getElementById('source-mix-source-count');

  function classifySources(sources: SourceRow[]): { android: SourceRow[]; generic: SourceRow[]; other: SourceRow[] } {
    const android: SourceRow[] = [];
    const generic: SourceRow[] = [];
    const other: SourceRow[] = [];
    sources.forEach((row) => {
      const source = String(row.artifact_source || '').toLowerCase();
      if (source.includes('android') || source.includes('zimperium') || source.includes('lamda') || source.includes('beacon')) {
        android.push(row);
        return;
      }
      if (source.startsWith('raw_hash_reservoir')) {
        generic.push(row);
        return;
      }
      other.push(row);
    });
    return { android, generic, other };
  }

  async function load(): Promise<void> {
    if (!endpoint) return;
    const res = await App.fetchPayload(endpoint);
    if (!res.ok) {
      if (metaEl) metaEl.textContent = 'Live refresh unavailable';
      return;
    }

    const data = (res.data && typeof res.data === 'object') ? res.data as JsonRecord : {};
    const sources = Array.isArray(data.sources) ? data.sources as SourceRow[] : [];
    const totals = (data.totals && typeof data.totals === 'object') ? data.totals as JsonRecord : {};
    const pipeline = asPipelineSnapshot(data.pipeline);
    const rec = pipeline.recommendation || {};
    const command = pipelinePrimaryCommand(pipeline);
    const tone = pipelineActionTone(rec.action);
    const summary = String(rec.summary || '').trim();

    const pendingFromSources = sources.reduce((sum, row) => sum + Number(row.pending_rows || 0), 0);
    if (pendingTotalEl) pendingTotalEl.textContent = fmtInt(totals.pending_rows ?? pendingFromSources);
    if (sourceCountEl) sourceCountEl.textContent = fmtInt(sources.length);

    if (noticeEl) {
      noticeEl.className = `notice ${tone === 'warn' ? 'warn' : 'info'}`;
      noticeEl.textContent = summary !== ''
        ? (command !== '' ? `${summary} · CLI: ${command}` : summary)
        : 'Engine recommendation unavailable.';
    }

    const { android, generic, other } = classifySources(sources);
    setText('source-mix-android-count', String(android.length));
    setText('source-mix-generic-count', String(generic.length));
    setText('source-mix-other-count', String(other.length));
    setText('source-mix-android-pending', fmtInt(android.reduce((s, r) => s + Number(r.pending_rows || 0), 0)));
    setText('source-mix-generic-pending', fmtInt(generic.reduce((s, r) => s + Number(r.pending_rows || 0), 0)));
    setText('source-mix-other-pending', fmtInt(other.reduce((s, r) => s + Number(r.pending_rows || 0), 0)));

    if (metaEl) {
      const meta = res.meta && typeof res.meta === 'object' ? res.meta as JsonRecord : {};
      metaEl.textContent = `Live refresh: ${String(meta.server_utc_now || 'ok')}`;
    }
  }

  function setText(id: string, value: string): void {
    const el = document.getElementById(id);
    if (el) el.textContent = value;
  }

  void load();
  window.setInterval(() => {
    void load();
  }, Math.max(10, refreshSeconds) * 1000);
}
