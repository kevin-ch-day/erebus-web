import type { AppSurface, JsonRecord } from '../types/app-globals';
import { fmtInt, setText } from '../shared/dom';
import { refreshPipelineEnginePanel } from '../shared/pipeline-engine-panel-live';

const root = document.getElementById('ingest-backlog-page') as HTMLElement | null;

if (root && window.App) {
  const App = window.App as AppSurface;
  const endpoint = root.dataset.endpoint || '';
  const refreshSeconds = Number(root.dataset.refreshSeconds || '20') || 20;
  const refreshMs = Math.max(10, refreshSeconds) * 1000;
  const metaEl = document.getElementById('ingest-backlog-live-meta');

  async function load(): Promise<void> {
    if (!endpoint) return;
    const res = await App.fetchPayload(endpoint);
    if (!res.ok) {
      if (metaEl) metaEl.textContent = 'Live refresh unavailable';
      return;
    }

    const data = (res.data && typeof res.data === 'object') ? res.data as JsonRecord : {};
    const totals = (data.totals && typeof data.totals === 'object') ? data.totals as JsonRecord : {};

    setText('ingest-tile-pending', fmtInt(totals.pending_rows));
    setText('ingest-tile-processing', fmtInt(totals.processing_rows));
    setText('ingest-tile-failed', fmtInt(totals.failed_rows));
    setText('ingest-tile-queue-rows', fmtInt(totals.queue_rows));
    setText('ingest-tile-lanes', fmtInt(totals.lane_count));

    refreshPipelineEnginePanel(App, data.pipeline, {
      idPrefix: 'ingest-engine',
      recommendedLaneKey: 'recommended_lane',
    }, res.meta);

    const recommendedLane = String(data.recommended_lane || '').trim();
    setText('ingest-engine-recommended-lane', recommendedLane || '--');

    if (metaEl) {
      metaEl.textContent = `Live refresh: ${String(res.meta?.server_utc_now || 'ok')}`;
    }
  }

  void load();
  window.setInterval(() => {
    void load();
  }, refreshMs);
}
