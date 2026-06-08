import { App } from '../shared/app-core';
import type { JsonRecord } from '../types/app-globals';

type ConfidenceSummaryRow = {
  confidence_bucket?: unknown;
  recommended_action?: unknown;
  sample_count?: unknown;
  min_confidence_score?: unknown;
  avg_confidence_score?: unknown;
  max_confidence_score?: unknown;
};

type FalsePositiveSummaryRow = {
  review_reason?: unknown;
  sample_count?: unknown;
};

type FalsePositiveCandidateRow = {
  sample_id?: unknown;
  sha256?: unknown;
  android_package_name?: unknown;
  sample_label?: unknown;
  family_label?: unknown;
  platform?: unknown;
  vt_malicious_count?: unknown;
  vt_suspicious_count?: unknown;
  vt_harmless_count?: unknown;
  vt_total_engines?: unknown;
  confidence_score?: unknown;
  confidence_bucket?: unknown;
  recommended_action?: unknown;
  review_reason?: unknown;
};

const root = document.getElementById('vt-confidence-page') as HTMLElement | null;

if (root) {
  const endpoint = root.dataset.endpoint || '';
  const limit = root.dataset.limit || '25';

  const bucketsEl = document.getElementById('vt-confidence-buckets');
  const metaEl = document.getElementById('vt-confidence-meta');
  const fpSummaryEl = document.getElementById('vt-fp-summary-list');
  const candidatesBodyEl = document.getElementById('vt-confidence-candidates-body');
  const errorEl = document.getElementById('vt-confidence-error');

  const esc = App.escapeHtml;
  const fmt = App.fmt;
  const formatUtc = App.formatUtc;

  function asRows<T>(value: unknown): T[] {
    return Array.isArray(value) ? (value as T[]) : [];
  }

  function badgeForBucket(bucket: unknown): string {
    const key = String(bucket || '').toLowerCase();
    if (key === 'high' || key === 'strong') return 'badge ok';
    if (key === 'moderate') return 'badge warn';
    if (key === 'review' || key === 'weak') return 'badge err';
    return 'badge muted';
  }

  function renderUnavailable(data: JsonRecord, meta: JsonRecord): void {
    if (metaEl) {
      metaEl.textContent = `Primary: ${fmt(meta.primary_database)} | schema unavailable`;
    }
    const missing = Array.isArray(data.schema_missing) ? data.schema_missing : [];
    if (bucketsEl) {
      bucketsEl.innerHTML = `
        <div class="detail-card">
          <div class="detail-card-title">Schema unavailable</div>
          <div class="muted">Apply the VT confidence database surface before using this page.</div>
          <div class="muted" style="margin-top:8px;">Missing items: ${esc(String(missing.length))}</div>
        </div>
      `;
    }
    if (fpSummaryEl) fpSummaryEl.innerHTML = '<li class="muted">Unavailable until schema is present.</li>';
    if (candidatesBodyEl) candidatesBodyEl.innerHTML = '<tr><td colspan="5" class="muted">No candidates available.</td></tr>';
  }

  function renderBuckets(rows: ConfidenceSummaryRow[]): void {
    if (!bucketsEl) return;
    if (!rows.length) {
      bucketsEl.innerHTML = '<div class="detail-card"><div class="muted">No confidence bucket rows found.</div></div>';
      return;
    }
    bucketsEl.innerHTML = rows.map((row) => {
      const bucket = fmt(row.confidence_bucket);
      const action = fmt(row.recommended_action);
      return `
        <div class="detail-card">
          <div class="detail-card-title">
            <span class="${badgeForBucket(bucket)}">${esc(bucket)}</span>
            ${esc(action)}
          </div>
          <div class="detail-row">
            <div class="detail-label">Samples</div>
            <div class="detail-value">${esc(fmt(row.sample_count))}</div>
          </div>
          <div class="detail-row">
            <div class="detail-label">Score range</div>
            <div class="detail-value">${esc(fmt(row.min_confidence_score))} - ${esc(fmt(row.max_confidence_score))}</div>
          </div>
          <div class="detail-row">
            <div class="detail-label">Average</div>
            <div class="detail-value">${esc(fmt(row.avg_confidence_score))}</div>
          </div>
        </div>
      `;
    }).join('');
  }

  function renderFpSummary(rows: FalsePositiveSummaryRow[]): void {
    if (!fpSummaryEl) return;
    if (!rows.length) {
      fpSummaryEl.innerHTML = '<li class="muted">No false-positive review buckets found.</li>';
      return;
    }
    fpSummaryEl.innerHTML = rows.map((row) => {
      return `<li>${esc(fmt(row.review_reason))}: <strong>${esc(fmt(row.sample_count))}</strong></li>`;
    }).join('');
  }

  function renderCandidates(rows: FalsePositiveCandidateRow[]): void {
    if (!candidatesBodyEl) return;
    if (!rows.length) {
      candidatesBodyEl.innerHTML = '<tr><td colspan="5" class="muted">No review candidates found.</td></tr>';
      return;
    }
    candidatesBodyEl.innerHTML = rows.map((row) => {
      const hash = row.sha256 ? String(row.sha256).slice(0, 16) : '--';
      const score = fmt(row.confidence_score);
      const bucket = fmt(row.confidence_bucket);
      return `
        <tr>
          <td><code>${esc(hash)}</code><br><span class="muted">sample ${esc(fmt(row.sample_id))}</span></td>
          <td>${esc(fmt(row.android_package_name || row.sample_label))}<br><span class="muted">${esc(fmt(row.family_label || row.platform, ''))}</span></td>
          <td>mal=${esc(fmt(row.vt_malicious_count))} susp=${esc(fmt(row.vt_suspicious_count))}<br><span class="muted">harmless=${esc(fmt(row.vt_harmless_count))} total=${esc(fmt(row.vt_total_engines))}</span></td>
          <td><span class="${badgeForBucket(bucket)}">${esc(bucket)}</span><br><span class="muted">score=${esc(score)}</span></td>
          <td>${esc(fmt(row.review_reason))}<br><span class="muted">${esc(fmt(row.recommended_action))}</span></td>
        </tr>
      `;
    }).join('');
  }

  async function load(): Promise<void> {
    if (!endpoint) return;
    if (errorEl) errorEl.textContent = '';
    try {
      const url = new URL(endpoint, window.location.origin);
      url.searchParams.set('limit', String(limit));
      const res = await App.fetchJson(url.toString());
      if (!res.ok) {
        if (errorEl) errorEl.textContent = `VT confidence API error: HTTP ${res.status} ${res.error || ''}`;
        return;
      }
      const body = res.body as JsonRecord & { data?: JsonRecord; meta?: JsonRecord };
      const data = body.data || {};
      const meta = body.meta || {};
      if (meta.schema_available === false) {
        renderUnavailable(data, meta);
        return;
      }
      if (metaEl) {
        const generated = meta.generated_at_utc ? formatUtc(meta.generated_at_utc) : '--';
        metaEl.textContent = `Primary: ${fmt(meta.primary_database)} | Updated: ${generated}`;
      }
      renderBuckets(asRows<ConfidenceSummaryRow>(data.summary));
      renderFpSummary(asRows<FalsePositiveSummaryRow>(data.false_positive_review_summary));
      renderCandidates(asRows<FalsePositiveCandidateRow>(data.false_positive_review_candidates));
    } catch (err) {
      if (errorEl) {
        errorEl.textContent = `VT confidence load failed: ${err instanceof Error ? err.message : String(err)}`;
      }
    }
  }

  void load();
}
