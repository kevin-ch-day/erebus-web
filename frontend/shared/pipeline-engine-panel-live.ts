import type { AppSurface } from '../types/app-globals';
import {
  asPipelineSnapshot,
  formatQueueLaneSummary,
  pipelineActionTone,
  pipelinePrimaryCommand,
} from './pipeline-posture';
import { bindPipelineEngineCopyButtons, fmtInt, setText } from './dom';

export type PipelineEnginePanelLiveOptions = {
  endpoint: string;
  idPrefix: string;
  liveMetaId?: string;
  refreshSeconds?: number;
  recommendedLaneKey?: string;
};

export function refreshPipelineEnginePanel(
  _app: AppSurface,
  pipelinePayload: unknown,
  options: Pick<PipelineEnginePanelLiveOptions, 'idPrefix' | 'recommendedLaneKey'>,
  meta?: Record<string, unknown> | null,
): void {
  const { idPrefix, recommendedLaneKey = 'recommended_lane' } = options;
  const pipeline = asPipelineSnapshot(pipelinePayload);
  const rec = pipeline.recommendation || {};
  const command = pipelinePrimaryCommand(pipeline);
  const tone = pipelineActionTone(rec.action);
  const summary = String(rec.summary || '').trim();

  setText(`${idPrefix}-queue-pending`, fmtInt(pipeline.pipeline?.queue_pending));
  setText(`${idPrefix}-state-eligible`, fmtInt(pipeline.pipeline?.state_eligible_now));
  setText(`${idPrefix}-lane-summary`, formatQueueLaneSummary(pipeline.queue_lanes) || 'No lane breakdown');
  setText(`${idPrefix}-keys-ready`, fmtInt(pipeline.vt?.keys_ready));
  setText(`${idPrefix}-run-command`, command || '--');
  setText(`${idPrefix}-source`, `source: ${String(pipeline.source || 'db')}`);

  const recommendedLane = String(
    (pipelinePayload && typeof pipelinePayload === 'object'
      ? (pipelinePayload as Record<string, unknown>)[recommendedLaneKey]
      : null)
    || pipeline.run_plan?.lane
    || '',
  ).trim();
  setText(`${idPrefix}-recommended-lane`, recommendedLane || '--');

  const notice = document.getElementById(`${idPrefix}-notice`);
  if (notice) {
    notice.className = `notice ${tone === 'warn' ? 'warn' : 'info'}`;
    notice.textContent = summary !== ''
      ? (command !== '' ? `${summary} · CLI: ${command}` : summary)
      : 'Engine recommendation unavailable.';
  }

  document.querySelectorAll<HTMLButtonElement>(`.pipeline-engine-copy[data-panel-prefix="${idPrefix}"]`).forEach((button) => {
    if (command) button.setAttribute('data-copy-command', command);
  });
  bindPipelineEngineCopyButtons(document.getElementById(`${idPrefix}-panel`) || document);
}

export function initPipelineEnginePanelLive(
  app: AppSurface,
  options: PipelineEnginePanelLiveOptions,
): () => void {
  const refreshSeconds = Math.max(10, options.refreshSeconds ?? 30);
  const liveMetaId = options.liveMetaId;
  const metaEl = liveMetaId ? document.getElementById(liveMetaId) : null;

  bindPipelineEngineCopyButtons(document.getElementById(`${options.idPrefix}-panel`) || document);

  async function load(): Promise<void> {
    if (!options.endpoint) return;
    const res = await app.fetchPayload(options.endpoint);
    if (!res.ok) {
      if (metaEl) metaEl.textContent = 'Live refresh unavailable';
      return;
    }

    refreshPipelineEnginePanel(app, res.data, options, res.meta);
    if (metaEl) {
      metaEl.textContent = `Live refresh: ${String(res.meta?.server_utc_now || 'ok')}`;
    }
  }

  void load();
  const timer = window.setInterval(() => {
    void load();
  }, refreshSeconds * 1000);

  return () => window.clearInterval(timer);
}

export { bindPipelineEngineCopyButtons };
