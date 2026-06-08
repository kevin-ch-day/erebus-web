import { App } from '../shared/app-core';

export type LovEntry = { key?: string; label?: string };

export type ReviewRow = {
  permission_string?: string;
  namespace?: string;
  event_count?: number | string;
  sample_count?: number | string;
  seen_count?: number | string;
  last_seen_at_utc?: string | null;
  triage_status?: string | null;
  bucket?: string | null;
  classification?: string | null;
  notes?: string | null;
};

export type ReviewMeta = {
  taxonomy_version?: string;
};

export type QueueResponseData = {
  queued?: number;
  queue_id?: number | string;
  operation?: string;
};

export function suggestedQueueAction(statusRaw: string): string {
  const key = String(statusRaw || '').toLowerCase();
  const map: Record<string, string> = {
    aosp_missing: 'aosp',
    gms_known: 'google',
    oem_candidate: 'oem',
    app_defined: 'app_defined',
    ignore: 'reject',
    malformed: 'reject',
  };
  return map[key] || '';
}

export function queueActionLabel(actions: LovEntry[], actionKey: string): string {
  const key = String(actionKey || '').toLowerCase();
  const match = actions.find((entry) => String(entry.key || '').toLowerCase() === key);
  return String(match?.label || match?.key || actionKey);
}

export function suggestedQueueBucket(statusRaw: string): string {
  const key = String(statusRaw || '').toLowerCase();
  const map: Record<string, string> = {
    aosp_missing: 'AOSP_EXACT',
    gms_known: 'GOOGLE_GMS',
    oem_candidate: 'OEM_EXACT',
    app_defined: 'APP_DEFINED_OTHER',
  };
  return map[key] || '';
}

export function suggestedQueueClassification(statusRaw: string): string {
  const key = String(statusRaw || '').toLowerCase();
  const map: Record<string, string> = {
    aosp_missing: 'AOSP',
    gms_known: 'GOOGLE',
    oem_candidate: 'OEM',
    app_defined: 'APP_DEFINED',
  };
  return map[key] || '';
}

export function backlogEffectLabel(statusRaw: string): string {
  const key = String(statusRaw || '').toLowerCase();
  if (!key || key === 'new') return 'Stays in active review backlog';
  return 'Leaves default review backlog';
}

export function summaryChip(label: string, value: string, tone = ''): string {
  const toneClass = tone ? ` review-summary-chip-${tone}` : '';
  return `<span class="review-summary-chip${toneClass}"><span class="review-summary-chip-label">${App.escapeHtml(label)}</span><span class="review-summary-chip-value">${App.escapeHtml(value)}</span></span>`;
}

export function setSelectOptions(
  selectEl: HTMLSelectElement | null,
  items: LovEntry[],
  current: string,
  fallbackLabel = 'Unavailable'
): void {
  if (!selectEl) return;
  selectEl.innerHTML = '';
  if (!items.length) {
    const opt = document.createElement('option');
    opt.value = '';
    opt.textContent = fallbackLabel;
    selectEl.appendChild(opt);
    return;
  }
  items.forEach((item) => {
    const key = String(item.key || '');
    const opt = document.createElement('option');
    opt.value = key;
    opt.textContent = String(item.label || item.key || key || 'Unknown');
    if (current && current.toLowerCase() === key.toLowerCase()) {
      opt.selected = true;
    }
    selectEl.appendChild(opt);
  });
}
