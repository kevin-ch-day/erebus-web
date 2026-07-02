import type { AppSurface, JsonRecord } from '../types/app-globals';
import { asRows, toRecord } from '../shared/dom';
import {
  asPipelineSnapshot,
  pipelineEngineHint,
  pipelineLaneChips,
} from '../shared/pipeline-posture';

type LandingPair = {
  catalog_family_label?: unknown;
  signal_family_name?: unknown;
  row_count?: unknown;
  pair_kind?: unknown;
  resolution_action?: unknown;
};

const root = document.getElementById('landing-page') as HTMLElement | null;

if (root && window.App) {
  const App = window.App as AppSurface;
  const endpoint = root.dataset.endpoint || '';
  const refreshSeconds = Number(root.dataset.refreshSeconds || '30') || 30;
  const refreshMs = Math.max(10, refreshSeconds) * 1000;
  const liveMetaEl = document.getElementById('landing-live-meta');
  const esc = App.escapeHtml;
  const fmt = App.fmt;

  const priorityNoticeEl = document.getElementById('landing-priority-notice');
  const eligibleEl = document.getElementById('landing-metric-eligible');
  const eligibleNoteEl = document.getElementById('landing-metric-eligible-note');
  const retryEl = document.getElementById('landing-metric-retry');
  const retryNoteEl = document.getElementById('landing-metric-retry-note');
  const familyMismatchEl = document.getElementById('landing-metric-family-mismatch');
  const familyNoteEl = document.getElementById('landing-metric-family-note');
  const stackGapEl = document.getElementById('landing-metric-stack-gaps');
  const stackNoteEl = document.getElementById('landing-metric-stack-note');

  const healthMetricEl = document.getElementById('landing-health-metric');
  const healthSummaryEl = document.getElementById('landing-health-summary');
  const healthChipsEl = document.getElementById('landing-health-chips');
  const healthIngestLinkEl = document.getElementById('landing-health-ingest-link');

  const familyMetricEl = document.getElementById('landing-family-metric');
  const familySummaryEl = document.getElementById('landing-family-summary');
  const familyChipsEl = document.getElementById('landing-family-chips');

  const stackMetricEl = document.getElementById('landing-stack-metric');
  const stackSummaryEl = document.getElementById('landing-stack-summary');
  const stackChipsEl = document.getElementById('landing-stack-chips');

  const hotspotsEl = document.getElementById('landing-hotspots');

  function renderChipRow(el: HTMLElement | null, items: string[]): void {
    if (!el) return;
    if (!items.length) {
      el.innerHTML = '<span class="landing-chip landing-empty">No current items</span>';
      return;
    }
    el.innerHTML = items.map((item) => `<span class="landing-chip">${esc(item)}</span>`).join('');
  }

  function renderUnavailable(): void {
    if (eligibleEl) eligibleEl.textContent = '--';
    if (eligibleNoteEl) eligibleNoteEl.textContent = 'Snapshot unavailable';
    if (retryEl) retryEl.textContent = '--';
    if (retryNoteEl) retryNoteEl.textContent = 'Snapshot unavailable';
    if (familyMismatchEl) familyMismatchEl.textContent = '--';
    if (familyNoteEl) familyNoteEl.textContent = 'Snapshot unavailable';
    if (stackGapEl) stackGapEl.textContent = '--';
    if (stackNoteEl) stackNoteEl.textContent = 'Snapshot unavailable';
    if (healthMetricEl) healthMetricEl.textContent = '--';
    if (healthSummaryEl) healthSummaryEl.textContent = 'Landing snapshot unavailable.';
    if (familyMetricEl) familyMetricEl.textContent = '--';
    if (familySummaryEl) familySummaryEl.textContent = 'Landing snapshot unavailable.';
    if (stackMetricEl) stackMetricEl.textContent = '--';
    if (stackSummaryEl) stackSummaryEl.textContent = 'Landing snapshot unavailable.';
    renderChipRow(healthChipsEl, []);
    renderChipRow(familyChipsEl, []);
    renderChipRow(stackChipsEl, []);
    if (hotspotsEl) {
      hotspotsEl.innerHTML = '<div class="detail-card"><div class="landing-hotspot-title">Conflict hotspots unavailable</div><div class="muted">Landing snapshot data could not be loaded.</div></div>';
    }
    if (priorityNoticeEl) priorityNoticeEl.textContent = 'Live data is unavailable. Start with VT & pipeline health.';
  }

  function render(snapshot: JsonRecord): void {
    const pipeline = asPipelineSnapshot(snapshot.pipeline);
    const pipelineRec = pipeline.recommendation || {};
    const queuePending = Number(pipeline.pipeline?.queue_pending || 0);
    const stateEligible = Number(pipeline.pipeline?.state_eligible_now || 0);
    const laneChips = pipelineLaneChips(pipeline);

    const health = toRecord(snapshot.health);
    const family = toRecord(snapshot.family);
    const familySummary = toRecord(family.summary);
    const dataset = toRecord(snapshot.dataset);
    const pairs = asRows<LandingPair>(family.top_mismatch_pairs).slice(0, 6);

    const eligible = Number(health.eligible_now || 0);
    const processing = Number(health.processing_now || 0);
    const errors = Number(health.error_count || 0);
    const retryWait = Number(health.retry_wait_count || 0);
    const staleClaims = Number(health.stale_claims || 0);
    const holdUntil = String(health.hold_until_utc || '').trim();
    const reasons = asRows<JsonRecord>(health.reason_breakdown).slice(0, 4)
      .map((row) => `${String(row.reason_code || 'UNKNOWN')}: ${fmt(row.count || 0)}`);

    const mismatch = Number(familySummary.mismatch_rows || 0);
    const signalOnly = Number(familySummary.signal_only_rows || 0);
    const catalogOnly = Number(familySummary.catalog_only_rows || 0);
    const highConflict = Number(familySummary.high_conflict_rows || 0);
    const riskClass = String(familySummary.risk_class || '--');

    const cleanBenchmarkRows = Number(dataset.clean_benchmark_rows || 0);
    const heldPersistedRows = Number(dataset.held_persisted_authority_consistency_debt_count || 0);
    const projectionDebt = Number(dataset.projection_materialization_debt_count || 0);
    const unresolvedAuthority = Number(dataset.unresolved_authority_count || 0);
    const genericPolicyHold = Number(dataset.generic_policy_hold_count || 0);
    const classCount = Number(dataset.class_count || 0);
    const trainableN10 = Number(dataset.trainable_class_count_n10 || 0);
    const topClass = String(dataset.top_class || '--');
    const topClassShare = Number(dataset.top_class_share || 0);

    if (eligibleEl) eligibleEl.textContent = fmt(eligible);
    if (eligibleNoteEl) {
      eligibleNoteEl.textContent = holdUntil !== ''
        ? `Hold active until ${holdUntil}`
        : `${fmt(processing)} processing now`;
    }
    if (retryEl) retryEl.textContent = fmt(retryWait + staleClaims);
    if (retryNoteEl) retryNoteEl.textContent = `${fmt(retryWait)} retry wait | ${fmt(staleClaims)} stale claims`;
    if (familyMismatchEl) familyMismatchEl.textContent = fmt(mismatch);
    if (familyNoteEl) familyNoteEl.textContent = `${fmt(signalOnly)} signal only | ${fmt(catalogOnly)} catalog only`;
    if (stackGapEl) stackGapEl.textContent = fmt(cleanBenchmarkRows);
    if (stackNoteEl) stackNoteEl.textContent = `${fmt(projectionDebt)} projection debt | ${fmt(heldPersistedRows)} held persisted`;

    if (healthMetricEl) {
      if (holdUntil !== '') {
        healthMetricEl.textContent = 'Held';
      } else if (queuePending > 0 && stateEligible === 0) {
        healthMetricEl.textContent = fmt(queuePending);
      } else if (queuePending > 0 && queuePending >= stateEligible) {
        healthMetricEl.textContent = fmt(queuePending);
      } else {
        healthMetricEl.textContent = fmt(eligible);
      }
    }
    if (healthSummaryEl) {
      healthSummaryEl.textContent = holdUntil !== ''
        ? 'VT is currently held. Stay on state, keys, and recent run errors before touching workflow queues.'
        : laneChips.length > 0
          ? `Eligible now ${fmt(eligible)} | ${laneChips.join(' · ')}.`
          : `Eligible now ${fmt(eligible)} | processing ${fmt(processing)} | errors ${fmt(errors)}.`;
    }
    renderChipRow(healthChipsEl, [
      ...laneChips,
      ...reasons.length ? reasons : ['No active reason breakdown'],
    ].filter(Boolean));

    if (familyMetricEl) familyMetricEl.textContent = fmt(highConflict);
    if (familySummaryEl) {
      familySummaryEl.textContent = `Mismatch ${fmt(mismatch)} | high-conflict ${fmt(highConflict)} | risk ${riskClass}.`;
    }
    renderChipRow(
      familyChipsEl,
      pairs.slice(0, 4).map((pair) => `${String(pair.catalog_family_label || '(empty)')} vs ${String(pair.signal_family_name || '(empty)')} (${fmt(pair.row_count || 0)})`)
    );

    if (stackMetricEl) stackMetricEl.textContent = `${fmt(trainableN10)}/${fmt(classCount)}`;
    if (stackSummaryEl) {
      stackSummaryEl.textContent = `Clean benchmark ${fmt(cleanBenchmarkRows)} | projection debt ${fmt(projectionDebt)} | unresolved ${fmt(unresolvedAuthority)}.`;
    }
    renderChipRow(stackChipsEl, [
      `top class ${topClass}`,
      `${fmt(topClassShare)}% top share`,
      `${fmt(genericPolicyHold)} generic holds`,
      `${fmt(heldPersistedRows)} authority debt`,
    ].filter(Boolean));

    if (!hotspotsEl) return;
    if (!pairs.length) {
      hotspotsEl.innerHTML = '<div class="detail-card"><div class="landing-hotspot-title">No current conflict hotspots</div><div class="muted">The mismatch-pair slice is currently empty.</div></div>';
    } else {
      hotspotsEl.innerHTML = pairs.map((pair) => {
        const href = App.pageUrl('family_taxonomy_queue', {
          limit: 100,
          decision_mode: 'ask_why_first',
          pair_catalog: pair.catalog_family_label || '',
          pair_signal: pair.signal_family_name || '',
        });
        return `
          <div class="detail-card">
            <div class="landing-map-tag">Conflict hotspot</div>
            <div class="landing-hotspot-pair">${esc(pair.catalog_family_label || '(empty)')} <span class="muted">vs</span> ${esc(pair.signal_family_name || '(empty)')}</div>
            <div class="landing-hotspot-meta">
              <div class="detail-row"><div class="detail-label">Rows</div><div class="detail-value">${esc(fmt(pair.row_count || 0))}</div></div>
              <div class="detail-row"><div class="detail-label">Kind</div><div class="detail-value">${esc(pair.pair_kind || '--')}</div></div>
            </div>
            <div class="muted">${esc(pair.resolution_action || '--')}</div>
            <div style="margin-top: auto;">
              <a class="btn btn-primary" href="${esc(href)}">Open conflict queue</a>
            </div>
          </div>
        `;
      }).join('');
    }

    let recommendation = 'Dataset curation is stable enough to work the governed benchmark and taxonomy queues directly.';
    const engineHint = pipelineEngineHint(pipeline);
    if (engineHint !== null) {
      recommendation = engineHint.replace(/^Engine:\s*/, '');
    } else if (holdUntil !== '') {
      recommendation = 'Start with VT & pipeline health. Enrichment is currently held.';
    } else if (queuePending > 0 && eligible === 0) {
      recommendation = `${fmt(queuePending)} ingest-queue row(s) pending with no vt_state-eligible work — open Ingest Backlog or run erebus pipeline run.`;
    } else if (errors > 0 || retryWait > 20 || staleClaims > 0) {
      recommendation = 'Start with VT & pipeline health. Scheduler residue or retries are creating operator drag.';
    } else if (mismatch > 800 || highConflict > 500) {
      recommendation = 'Family taxonomy is the main backlog. Start with conflict hotspots or the family repair queue.';
    } else if (projectionDebt > 500 || heldPersistedRows > 0 || unresolvedAuthority > 0) {
      recommendation = 'Dataset curation still has authority debt. Use Type Benchmark and Authority Consistency Debt before making stronger readiness claims.';
    }
    if (priorityNoticeEl) priorityNoticeEl.textContent = recommendation;
    if (healthIngestLinkEl) {
      healthIngestLinkEl.style.display = queuePending > 0 ? '' : 'none';
    }
  }

  async function load(): Promise<void> {
    if (!endpoint) {
      renderUnavailable();
      return;
    }
    const res = await App.fetchPayload(endpoint);
    if (!res.ok) {
      renderUnavailable();
      return;
    }
    render(toRecord(res.data));
    if (liveMetaEl) {
      liveMetaEl.textContent = `Live refresh: ${String(res.meta?.generated_at_utc || res.meta?.server_utc_now || 'ok')}`;
    }
  }

  void load();
  window.setInterval(() => {
    void load();
  }, refreshMs);
}
