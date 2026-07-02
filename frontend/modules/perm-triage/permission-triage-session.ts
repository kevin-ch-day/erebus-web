import type { AppSurface, PermissionIntelSurface } from '../../types/app-globals';
import type { PermTriageNamespace, SessionHeaderElements, TriageRow } from '../../types/perm-triage-globals';

const PermTriage = (window.PermTriage || (window.PermTriage = {} as PermTriageNamespace)) as PermTriageNamespace;

if (window.App && window.PermissionIntel) {
  const App = window.App as AppSurface;
  const PI = window.PermissionIntel as PermissionIntelSurface;
  const formatUtc = App.formatUtc;
  const {
    formatCount,
    riskHint,
    resolveActionableReviewBacklog,
    resolveWorkflowUnknownBacklog,
    triagePriorityBucket,
  } = PI;

  PermTriage.priorityScore = (row: TriageRow) => {
    const risk = riskHint(row.permission_string, row.namespace).label.toLowerCase();
    const status = String(row.triage_status ?? '').toLowerCase();
    const riskScore = risk === 'high' ? 0 : (risk === 'medium' ? 1 : 2);
    const statusScore = typeof triagePriorityBucket === 'function' ? triagePriorityBucket(status) : 3;
    return (statusScore * 10) + riskScore;
  };

  PermTriage.findNextRow = (rows: TriageRow[]) => {
    if (!Array.isArray(rows) || rows.length === 0) return null;
    const currentEvidenceRows = rows.some((row) =>
      Object.prototype.hasOwnProperty.call(row || {}, 'current_unknown_samples')
      || Object.prototype.hasOwnProperty.call(row || {}, 'current_unknown_obs_rows')
    );
    if (currentEvidenceRows) {
      return rows[0] || null;
    }
    const candidates = rows.slice().sort((a, b) => PermTriage.priorityScore(a) - PermTriage.priorityScore(b));
    return candidates[0] || null;
  };

  PermTriage.renderSessionHeader = (
    triageStatusCounts,
    session,
    health,
    taxonomy,
    elements: SessionHeaderElements,
    metrics,
    operatorSummary,
    currentEvidenceRows,
  ) => {
    const hasOwn = (obj: object | null | undefined, key: string) => !!obj && Object.prototype.hasOwnProperty.call(obj, key);
    const triageCounts = (metrics && metrics.triage_status_counts) || triageStatusCounts || {};
    const riskCounts = ((metrics && metrics.current_evidence_risk_counts)
      || (session && session.current_evidence_risk_counts)
      || (session && session.new_risk_counts)
      || {}) as Record<string, number>;
    const summary = operatorSummary || {};
    const counts = {
      high: Number(riskCounts.high || 0),
      medium: Number(riskCounts.medium || 0),
      low: Number(riskCounts.low || 0),
      total: 0,
    };

    const hasExplicitEvidenceCount = hasOwn(summary, 'current_evidence_review_backlog')
      || hasOwn(session, 'current_evidence_review_backlog')
      || hasOwn(metrics, 'current_evidence_review_backlog');
    const evidenceCount = Number(
      hasOwn(summary, 'current_evidence_review_backlog')
        ? summary.current_evidence_review_backlog
        : (hasOwn(session, 'current_evidence_review_backlog')
          ? session.current_evidence_review_backlog
          : (hasOwn(metrics, 'current_evidence_review_backlog')
            ? metrics.current_evidence_review_backlog
            : 0))
    );

    if (hasExplicitEvidenceCount) {
      counts.total = evidenceCount;
    } else if (Array.isArray(currentEvidenceRows)) {
      counts.total = currentEvidenceRows.length;
    }
    if (!counts.total && !hasExplicitEvidenceCount) {
      counts.total = resolveActionableReviewBacklog(
        summary,
        session && {
          actionable_review_backlog: session.actionable_review_backlog,
          actionable_workflow_unknowns: session.actionable_workflow_unknowns,
        },
        metrics,
        health
      );
    }
    if (!counts.total && !hasExplicitEvidenceCount) {
      counts.total = resolveWorkflowUnknownBacklog(
        summary,
        session && {
          workflow_unknown_backlog: session.workflow_unknown_backlog,
          effective_unknown_compat_legacy: session.unknown_total_effective,
        },
        metrics,
        health
      );
    }

    if (elements.sessionHighEl) elements.sessionHighEl.textContent = formatCount(counts.high);
    if (elements.sessionMediumEl) elements.sessionMediumEl.textContent = formatCount(counts.medium);
    if (elements.sessionLowEl) elements.sessionLowEl.textContent = formatCount(counts.low);
    if (elements.sessionTotalEl) elements.sessionTotalEl.textContent = formatCount(counts.total);

    if (elements.sessionLastOkEl) {
      const lastObserved = health && health.last_observed_at_utc ? health.last_observed_at_utc : null;
      elements.sessionLastOkEl.textContent = lastObserved ? formatUtc(lastObserved) : '--';
    }
    if (elements.sessionTaxonomyEl) {
      elements.sessionTaxonomyEl.textContent = taxonomy.version ? String(taxonomy.version) : '--';
    }
    if (elements.sessionNoteEl) {
      const lastObserved = health && health.last_observed_at_utc ? health.last_observed_at_utc : null;
      const lastOkMs = App.parseUtcToMs(lastObserved);
      if (lastOkMs && (Date.now() - lastOkMs) > (24 * 60 * 60 * 1000)) {
        elements.sessionNoteEl.textContent = 'No new permission observations in the last 24h. Pipeline may be paused.';
      } else {
        elements.sessionNoteEl.textContent = 'Review current evidence-backed rows first, then governed current UNKNOWNs, then ledger diagnostics.';
      }
    }
  };
}
