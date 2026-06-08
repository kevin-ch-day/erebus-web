import { App } from '../shared/app-core';
import { PermissionIntel } from '../shared/permission-intel';
import type { JsonRecord } from '../types/app-globals';

type BucketMeta = {
  label: string;
  className: string;
  hint: string;
};

type FusionSummaryRow = {
  fusion_bucket?: unknown;
  sample_count?: unknown;
};

type FusionRow = {
  sample_id?: unknown;
  sha256?: unknown;
  package_name?: unknown;
  sample_label?: unknown;
  confidence_bucket?: unknown;
  confidence_score?: unknown;
  vt_malicious_count?: unknown;
  vt_total_engines?: unknown;
  attack_technique_count?: unknown;
  mapped_permission_count?: unknown;
  attack_technique_ids?: unknown;
  tactics?: unknown;
  fusion_bucket?: unknown;
  fusion_reason?: unknown;
  recommended_action?: unknown;
};

type AttackSummaryRow = {
  attack_technique_id?: unknown;
  attack_name?: unknown;
  sample_count?: unknown;
  mapped_permission_observations?: unknown;
  tactic?: unknown;
};

const root = document.getElementById('analysis-fusion-page') as HTMLElement | null;

if (root) {
  const endpoint = root.dataset.endpoint || '';
  const limit = root.dataset.limit || '50';

  const summaryEl = document.getElementById('analysis-fusion-summary');
  const metaEl = document.getElementById('analysis-fusion-meta');
  const rowsBodyEl = document.getElementById('analysis-fusion-rows-body');
  const attackSummaryEl = document.getElementById('analysis-fusion-attack-summary');
  const errorEl = document.getElementById('analysis-fusion-error');

  const esc = App.escapeHtml;
  const fmt = App.fmt;
  const formatUtc = App.formatUtc;
  const { formatCount } = PermissionIntel;

  const buckets: Record<string, BucketMeta> = {
    behavior_outpaces_vt: {
      label: 'Behavior outpaces VT',
      className: 'badge err',
      hint: 'Mapped permission behavior exists, but VT confidence is weak, review-only, or missing.',
    },
    vt_without_permission_behavior: {
      label: 'VT without permission behavior',
      className: 'badge warn',
      hint: 'Strong VT evidence exists, but no permission ATT&CK mapping is present.',
    },
    aligned_high_signal: {
      label: 'Aligned high signal',
      className: 'badge ok',
      hint: 'VT confidence and permission behavior corroborate each other.',
    },
    behavior_with_moderate_vt: {
      label: 'Behavior with moderate VT',
      className: 'badge warn',
      hint: 'Permission behavior exists with moderate or non-high VT support.',
    },
    vt_only_context: {
      label: 'VT-only context',
      className: 'badge muted',
      hint: 'VT confidence exists without mapped Permission Intel behavior.',
    },
  };

  function asRows<T>(value: unknown): T[] {
    return Array.isArray(value) ? (value as T[]) : [];
  }

  function bucketMeta(bucket: unknown): BucketMeta {
    const key = String(bucket || '').toLowerCase();
    return buckets[key] || { label: fmt(bucket), className: 'badge muted', hint: '' };
  }

  function confidenceBadge(bucket: unknown): string {
    const key = String(bucket || '').toLowerCase();
    if (key === 'high' || key === 'strong') return 'badge ok';
    if (key === 'moderate') return 'badge warn';
    if (key === 'review' || key === 'weak') return 'badge err';
    return 'badge muted';
  }

  function renderUnavailable(data: JsonRecord, meta: JsonRecord): void {
    if (metaEl) {
      metaEl.textContent = `Primary: ${fmt(meta.primary_database)} | PI: ${fmt(meta.permission_intel_database)} | schema unavailable`;
    }
    const missing = Array.isArray(data.schema_missing) ? data.schema_missing : [];
    if (summaryEl) {
      summaryEl.innerHTML = `
        <div class="detail-card">
          <div class="detail-card-title">Schema unavailable</div>
          <div class="muted">Apply the VT confidence and Permission ATT&amp;CK database surfaces before using fused analysis.</div>
          <div class="muted" style="margin-top:8px;">Missing items: ${esc(String(missing.length))}</div>
        </div>
      `;
    }
    if (rowsBodyEl) rowsBodyEl.innerHTML = '<tr><td colspan="5" class="muted">No fusion rows available.</td></tr>';
    if (attackSummaryEl) attackSummaryEl.innerHTML = '<li class="muted">Unavailable until schema is present.</li>';
  }

  function renderSummary(rows: FusionSummaryRow[]): void {
    if (!summaryEl) return;
    if (!rows.length) {
      summaryEl.innerHTML = '<div class="detail-card"><div class="muted">No fusion buckets found.</div></div>';
      return;
    }
    summaryEl.innerHTML = rows.map((row) => {
      const meta = bucketMeta(row.fusion_bucket);
      return `
        <div class="detail-card">
          <div class="detail-card-title"><span class="${meta.className}">${esc(meta.label)}</span></div>
          <div class="detail-row">
            <div class="detail-label">Samples</div>
            <div class="detail-value">${esc(formatCount(row.sample_count))}</div>
          </div>
          <div class="muted">${esc(meta.hint)}</div>
        </div>
      `;
    }).join('');
  }

  function renderRows(rows: FusionRow[]): void {
    if (!rowsBodyEl) return;
    if (!rows.length) {
      rowsBodyEl.innerHTML = '<tr><td colspan="5" class="muted">No fusion rows found.</td></tr>';
      return;
    }
    rowsBodyEl.innerHTML = rows.map((row) => {
      const hash = row.sha256 ? String(row.sha256).slice(0, 16) : '--';
      const bucket = bucketMeta(row.fusion_bucket);
      const confidenceBucket = fmt(row.confidence_bucket);
      return `
        <tr>
          <td><code>${esc(hash)}</code><br><span class="muted">sample ${esc(fmt(row.sample_id))} | ${esc(fmt(row.package_name || row.sample_label))}</span></td>
          <td><span class="${bucket.className}">${esc(bucket.label)}</span></td>
          <td>
            <span class="${confidenceBadge(confidenceBucket)}">${esc(confidenceBucket)}</span>
            <br><span class="muted">score=${esc(fmt(row.confidence_score))} mal=${esc(fmt(row.vt_malicious_count))}/${esc(fmt(row.vt_total_engines))}</span>
          </td>
          <td>
            ${esc(formatCount(row.attack_technique_count))} techniques / ${esc(formatCount(row.mapped_permission_count))} permissions
            <br><span class="muted">${esc(fmt(row.attack_technique_ids))}</span>
            <br><span class="muted">${esc(fmt(row.tactics, ''))}</span>
          </td>
          <td>${esc(fmt(row.fusion_reason))}<br><span class="muted">${esc(fmt(row.recommended_action, ''))}</span></td>
        </tr>
      `;
    }).join('');
  }

  function renderAttackSummary(rows: AttackSummaryRow[]): void {
    if (!attackSummaryEl) return;
    if (!rows.length) {
      attackSummaryEl.innerHTML = '<li class="muted">No ATT&CK summary rows found.</li>';
      return;
    }
    attackSummaryEl.innerHTML = rows.map((row) => {
      return `<li><strong>${esc(fmt(row.attack_technique_id))}</strong> ${esc(fmt(row.attack_name, ''))}: ${esc(formatCount(row.sample_count))} samples, ${esc(formatCount(row.mapped_permission_observations))} mapped observations <span class="muted">${esc(fmt(row.tactic, ''))}</span></li>`;
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
        if (errorEl) errorEl.textContent = `Analysis fusion API error: HTTP ${res.status} ${res.error || ''}`;
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
        const split = meta.permission_intel_split ? 'split' : 'unified';
        metaEl.textContent = `Primary: ${fmt(meta.primary_database)} | PI: ${fmt(meta.permission_intel_database)} (${split}) | Updated: ${generated}`;
      }
      renderSummary(asRows<FusionSummaryRow>(data.summary));
      renderRows(asRows<FusionRow>(data.fusion_rows));
      renderAttackSummary(asRows<AttackSummaryRow>(data.attack_surface_summary));
    } catch (err) {
      if (errorEl) {
        errorEl.textContent = `Analysis fusion load failed: ${err instanceof Error ? err.message : String(err)}`;
      }
    }
  }

  void load();
}
