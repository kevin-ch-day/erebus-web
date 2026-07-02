import type { JsonRecord } from '../types/app-globals';

export type QueueLanePosture = {
  lamda_pending?: unknown;
  reservoir_pending?: unknown;
  lamda_vt_ready?: unknown;
  top_workload_lane?: unknown;
  top_lane_count?: unknown;
};

export type PipelineRecommendation = {
  action?: unknown;
  summary?: unknown;
  command?: unknown;
};

export type PipelineStatusSnapshot = {
  pipeline?: {
    queue_pending?: unknown;
    queue_processing?: unknown;
    state_eligible_now?: unknown;
    reanalyze_orphans?: unknown;
  };
  recommendation?: PipelineRecommendation;
  queue_lanes?: QueueLanePosture;
  run_plan?: {
    mode?: unknown;
    lane?: unknown;
    reason?: unknown;
    command?: unknown;
  };
  vt?: {
    hold_active?: unknown;
    hold_until_utc?: unknown;
    hold_reason_code?: unknown;
    keys_ready?: unknown;
    quota_remaining?: unknown;
  };
  source?: unknown;
};

export function pipelinePrimaryCommand(snapshot: PipelineStatusSnapshot | null | undefined): string {
  if (!snapshot) return '';
  const runPlan = snapshot.run_plan;
  const runCommand = String(runPlan?.command || '').trim();
  if (runCommand !== '') return runCommand;
  return String(snapshot.recommendation?.command || '').trim();
}

export type PipelineActionTone = 'ok' | 'info' | 'warn' | 'error';

export function pipelineActionTone(action: unknown): PipelineActionTone {
  const normalized = String(action || '').trim().toLowerCase();
  if (normalized === 'wait_vt_blocked') return 'warn';
  if (normalized === 'idle') return 'ok';
  if (normalized === 'run_state') return 'info';
  if (normalized === 'run_queue') return 'info';
  return 'info';
}

export function pipelineMetricValue(snapshot: PipelineStatusSnapshot | null | undefined): string {
  if (!snapshot) return '--';
  const queuePending = Number(snapshot.pipeline?.queue_pending ?? 0);
  const stateEligible = Number(snapshot.pipeline?.state_eligible_now ?? 0);
  if (queuePending > 0 && stateEligible === 0) return queuePending.toLocaleString();
  if (queuePending > 0 && queuePending >= stateEligible) return queuePending.toLocaleString();
  if (stateEligible > 0) return stateEligible.toLocaleString();
  return queuePending.toLocaleString();
}

export function formatQueueLaneSummary(lanes: QueueLanePosture | null | undefined): string {
  if (!lanes) return '';
  const parts: string[] = [];
  const lamdaPending = Number(lanes.lamda_pending ?? 0);
  const reservoirPending = Number(lanes.reservoir_pending ?? 0);
  const lamdaVtReady = lanes.lamda_vt_ready;

  if (lamdaPending > 0) {
    parts.push(`LAMDA ${lamdaPending.toLocaleString()} pending`);
  }
  if (reservoirPending > 0) {
    parts.push(`reservoir ${reservoirPending.toLocaleString()} pending`);
  }
  if (lamdaVtReady !== null && lamdaVtReady !== undefined && lamdaVtReady !== '') {
    const ready = Number(lamdaVtReady);
    if (Number.isFinite(ready) && ready > 0) {
      parts.push(`${ready.toLocaleString()} LAMDA VT-ready`);
    }
  }
  const topLane = String(lanes.top_workload_lane || '').trim();
  if (topLane !== '' && parts.length === 0) {
    parts.push(`top lane ${topLane}`);
  }
  return parts.join(' · ');
}

export function pipelineEngineHint(snapshot: PipelineStatusSnapshot | null | undefined): string | null {
  if (!snapshot) return null;
  const runPlan = snapshot.run_plan;
  const runCommand = String(runPlan?.command || '').trim();
  const runSummary = String(snapshot.recommendation?.summary || '').trim();
  if (runSummary !== '') {
    const command = runCommand || String(snapshot.recommendation?.command || '').trim();
    return command !== '' ? `Engine: ${runSummary} (${command})` : `Engine: ${runSummary}`;
  }
  return null;
}

export function pipelineLaneChips(snapshot: PipelineStatusSnapshot | null | undefined): string[] {
  const lanes = snapshot?.queue_lanes;
  const summary = formatQueueLaneSummary(lanes);
  if (!summary) return [];
  return summary.split(' · ').filter(Boolean);
}

export function asPipelineSnapshot(value: unknown): PipelineStatusSnapshot {
  return value && typeof value === 'object' ? (value as PipelineStatusSnapshot) : {};
}

export function pipelineRecord(value: unknown): JsonRecord {
  return value && typeof value === 'object' ? (value as JsonRecord) : {};
}
