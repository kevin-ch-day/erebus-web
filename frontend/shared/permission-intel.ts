import { App } from './app-core';
import type { JsonRecord, PermissionIntelSurface } from '../types/app-globals';

type NamespaceRule = {
  key: string;
  label: string;
  className: string;
  prefixes: string[];
};

type LovResponse = {
  namespace_classes?: Array<{
    key?: unknown;
    label?: unknown;
    class_name?: unknown;
    prefixes?: unknown;
  }>;
};

const numberFmt = new Intl.NumberFormat();

let namespaceRules: NamespaceRule[] | null = null;
let lovCache: unknown = null;
let lovPromise: Promise<unknown> | null = null;

const PermissionIntel: PermissionIntelSurface = window.PermissionIntel || ({} as PermissionIntelSurface);

PermissionIntel.normalizeKey = (value: unknown): string => {
  const raw = String(value ?? '').toUpperCase().trim();
  if (!raw) return '';
  return raw.replace(/[^A-Z0-9]+/g, '_').replace(/_+/g, '_').replace(/^_+|_+$/g, '');
};

PermissionIntel.formatCount = (value: unknown): string => {
  const num = Number(value ?? 0);
  return Number.isFinite(num) ? numberFmt.format(num) : '--';
};

PermissionIntel.formatPct = (value: unknown): string => {
  if (value === null || value === undefined || Number.isNaN(Number(value))) return '--';
  const num = Number(value);
  return `${num.toFixed(1)}%`;
};

PermissionIntel.resolveWorkflowUnknownBacklog = (...sources: Array<Record<string, unknown> | null | undefined>): number => {
  for (const source of sources) {
    if (!source || typeof source !== 'object') continue;
    const value =
      source.current_evidence_backlog ??
      source.current_evidence_review_backlog ??
      source.workflow_unknown_backlog ??
      source.effective_unknown_compat_legacy ??
      source.effective_unknown;
    const num = Number(value ?? 0);
    if (num > 0) return num;
  }
  for (const source of sources) {
    if (!source || typeof source !== 'object') continue;
    const fallback = Number(source.unknown_dict_count ?? source.unknown_count ?? 0);
    if (fallback > 0) return fallback;
  }
  return 0;
};

PermissionIntel.resolveActionableReviewBacklog = (...sources: Array<Record<string, unknown> | null | undefined>): number => {
  for (const source of sources) {
    if (!source || typeof source !== 'object') continue;
    const value = source.current_evidence_review_backlog ?? source.actionable_review_backlog ?? source.actionable_workflow_unknowns;
    const num = Number(value ?? 0);
    if (num > 0) return num;
  }
  return 0;
};

PermissionIntel.statusFromUnknownPct = (value: unknown) => {
  const num = Number(value ?? 0);
  if (num > 8) return { label: 'Maintenance Required', className: 'badge err' };
  if (num >= 3) return { label: 'Watch', className: 'badge warn' };
  return { label: 'Healthy', className: 'badge ok' };
};

PermissionIntel.riskHint = (permission: unknown, namespace: unknown) => {
  const raw = String(permission ?? '').toLowerCase();
  const ns = String(namespace ?? '').toLowerCase();
  if (/(sms|mms|send_sms|receive_sms|read_sms|write_sms)/.test(raw)) {
    return { label: 'High', className: 'badge err' };
  }
  if (/(accessibility|device_admin|bind_device_admin|notification_listener|package_usage_stats|usage_stats)/.test(raw)) {
    return { label: 'High', className: 'badge err' };
  }
  if (/(request_install_packages|install_packages|manage_external_storage|manage_all_files|query_all_packages)/.test(raw)) {
    return { label: 'High', className: 'badge err' };
  }
  if (/(record_audio|camera|read_contacts|read_call_log|read_phone|read_phone_state|access_fine_location|access_background_location)/.test(raw)) {
    return { label: 'High', className: 'badge err' };
  }
  if (/(secure_element|security_center|inject_key_events|read_clipboard)/.test(raw)) {
    return { label: 'High', className: 'badge err' };
  }
  if (/(overlay|system_alert_window|draw_over_other_apps)/.test(raw)) {
    return { label: 'High', className: 'badge err' };
  }
  if (/(account|accounts)/.test(raw)) {
    return { label: 'High', className: 'badge err' };
  }
  if (/(ignore_battery_optimizations|schedule_exact_alarm|use_exact_alarm|bind_vpn_service|write_settings)/.test(raw)) {
    return { label: 'Medium', className: 'badge warn' };
  }
  if (/(launcher|oem|vendor)/.test(raw) || /(com\.huawei|com\.oppo|com\.samsung|com\.xiaomi|com\.vivo)/.test(ns)) {
    return { label: 'Medium', className: 'badge warn' };
  }
  if (/(analytics|ads|adservice|adid|ad_id)/.test(raw)) {
    return { label: 'Low', className: 'badge muted' };
  }
  return { label: 'Medium', className: 'badge warn' };
};

PermissionIntel.riskReasonLabels = {
  sms_or_messaging: 'SMS / messaging',
  special_access_or_admin: 'Special access / admin',
  installer_storage_or_package_visibility: 'Installer, storage, or package visibility',
  privacy_sensor_or_identity: 'Privacy sensor or identity',
  privileged_control_surface: 'Privileged control surface',
  overlay_or_ui_deception: 'Overlay / UI deception',
  account_access: 'Account access',
  special_settings_or_persistence: 'Special settings / persistence',
  oem_vendor_or_launcher: 'OEM, vendor, or launcher',
  advertising_or_analytics: 'Advertising / analytics',
  generic_permission_context: 'Generic permission context',
};

PermissionIntel.riskReasonLabel = (value: unknown): string => {
  const key = String(value || '').toLowerCase();
  return PermissionIntel.riskReasonLabels[key] || String(value || 'Generic permission context');
};

PermissionIntel.queueStatusLabels = {
  queued: 'Queued / not applied',
  claimed: 'Claimed',
  applied: 'Applied',
  error: 'Apply error',
  rejected: 'Rejected',
  skipped: 'Skipped',
};

PermissionIntel.queueStatusLabel = (statusKey: unknown): string => {
  const key = String(statusKey || '').toLowerCase();
  return PermissionIntel.queueStatusLabels[key] || String(statusKey || '');
};

PermissionIntel.queueStatusBadge = (statusKey: unknown): { label: string; className: string } => {
  const key = String(statusKey || '').toLowerCase();
  if (!key) return { label: 'Not queued', className: 'badge muted' };
  const classMap: Record<string, string> = {
    queued: 'badge warn',
    claimed: 'badge warn',
    applied: 'badge ok',
    error: 'badge err',
    rejected: 'badge muted',
    skipped: 'badge muted',
  };
  return {
    label: PermissionIntel.queueStatusLabel(key),
    className: classMap[key] || 'badge muted',
  };
};

PermissionIntel.setLov = (lov: unknown): void => {
  lovCache = lov || null;
  const classes = (lov as LovResponse | null)?.namespace_classes;
  namespaceRules = Array.isArray(classes)
    ? classes.map((item) => ({
        key: String(item?.key || '').toLowerCase(),
        label: String(item?.label || '').trim() || 'Anomalous',
        className: String(item?.class_name || '').trim() || 'err',
        prefixes: Array.isArray(item?.prefixes) ? item.prefixes.map((prefix) => String(prefix).toLowerCase()) : [],
      }))
    : [];
};

PermissionIntel.getLov = () => lovCache;

PermissionIntel.fetchLov = async (endpoint: string): Promise<unknown> => {
  if (!endpoint) return null;
  if (lovCache) return lovCache;
  if (lovPromise) return lovPromise;
  lovPromise = App.fetchJson(endpoint).then((res) => {
    if (res.ok) {
      const body = res.body as JsonRecord & { data?: unknown };
      PermissionIntel.setLov(body.data || {});
    }
    return lovCache;
  });
  return lovPromise;
};

PermissionIntel.classifyNamespace = (namespace: unknown) => {
  const ns = String(namespace ?? '').toLowerCase();
  if (namespaceRules && namespaceRules.length) {
    for (const rule of namespaceRules) {
      if (!rule.prefixes.length) continue;
      for (const prefix of rule.prefixes) {
        if (ns === prefix || ns.startsWith(`${prefix}.`)) {
          return { label: rule.label, className: rule.className };
        }
      }
    }
    const fallback = namespaceRules.find((rule) => rule.key === 'anomalous');
    if (fallback) {
      return { label: fallback.label, className: fallback.className };
    }
  }

  if (ns === '' || ns === '--') return { label: 'Anomalous', className: 'err' };
  if (ns === 'android' || ns === 'android.permission' || ns.startsWith('android.')) {
    return { label: 'Core', className: 'ok' };
  }
  if (ns.startsWith('com.google')) return { label: 'Expected', className: 'ok' };
  if (
    ns.startsWith('com.huawei') ||
    ns.startsWith('com.oppo') ||
    ns.startsWith('com.samsung') ||
    ns.startsWith('com.xiaomi') ||
    ns.startsWith('com.vivo')
  ) {
    return { label: 'OEM', className: 'warn' };
  }
  return { label: 'Anomalous', className: 'err' };
};

PermissionIntel.formatUtcDual = (value: unknown) => {
  const primary = App.formatUtc(value);
  const secondaryTz = App.getSecondaryTz ? App.getSecondaryTz() : '';
  if (!secondaryTz) {
    return { primary, secondary: '' };
  }
  return { primary, secondary: App.formatUtc(value, { timeZone: secondaryTz }) };
};

PermissionIntel.bindEnterReload = (elements, handler): void => {
  (elements || []).forEach((el) => {
    if (!(el instanceof HTMLElement)) return;
    el.addEventListener('keydown', (event: KeyboardEvent) => {
      if (event.key !== 'Enter') return;
      event.preventDefault();
      handler();
    });
  });
};

PermissionIntel.renderError = (errorEl: HTMLElement | null, title: string, detail: string): void => {
  if (!errorEl) return;
  errorEl.innerHTML = `<pre>${App.escapeHtml(title)}\n${App.escapeHtml(detail)}</pre>`;
};

PermissionIntel.createReadonlyCatalogPage = (options: Record<string, unknown>) => {
  const {
    endpoint,
    lovEndpoint = '',
    pageSize = 200,
    bodyEl,
    metaEl,
    errorEl,
    colSpan = 1,
    loadMessage = 'Loading...',
    emptyMessage = 'No rows found.',
    emptyMeta = '--',
    renderMetaText = null,
    buildParams = null,
    renderRow = null,
    loadLov = null,
  } = options || {};

  const readonly = App.readonly || {};
  const setTableMessage = readonly.setTableMessage || ((el: HTMLElement | null, span: number, message: string, className = 'muted') => {
    if (!el) return;
    el.innerHTML = `<tr><td colspan="${span}" class="${className}">${App.escapeHtml(message)}</td></tr>`;
  });

  let page = 1;

  const setPage = (value: unknown): void => {
    const next = Number(value);
    page = Number.isFinite(next) && next > 0 ? Math.floor(next) : 1;
  };

  const buildQueryString = (): string => {
    const params = new URLSearchParams();
    params.set('page', String(page));
    params.set('page_size', String(pageSize));
    if (typeof buildParams === 'function') {
      const extra = buildParams() || {};
      if (extra instanceof URLSearchParams) {
        extra.forEach((value, key) => {
          if (value !== null && value !== undefined && value !== '') {
            params.set(String(key), String(value));
          }
        });
      } else if (extra && typeof extra === 'object') {
        Object.entries(extra as Record<string, unknown>).forEach(([key, value]) => {
          if (value !== null && value !== undefined && value !== '') {
            params.set(String(key), String(value));
          }
        });
      }
    }
    return params.toString();
  };

  const renderMeta = (meta: unknown): void => {
    if (!(metaEl instanceof HTMLElement)) return;
    if (!meta) {
      metaEl.textContent = String(emptyMeta);
      return;
    }
    if (typeof renderMetaText === 'function') {
      metaEl.textContent = String(renderMetaText(meta));
      return;
    }
    metaEl.textContent = String(emptyMeta);
  };

  const renderRows = (rows: unknown): void => {
    if (!(bodyEl instanceof HTMLElement)) return;
    bodyEl.innerHTML = '';
    if (!Array.isArray(rows) || rows.length === 0) {
      setTableMessage(bodyEl, Number(colSpan), String(emptyMessage));
      renderMeta(null);
      return;
    }
    rows.forEach((row) => {
      const tr = document.createElement('tr');
      if (typeof renderRow === 'function') {
        tr.innerHTML = String(renderRow(row));
      }
      bodyEl.appendChild(tr);
    });
  };

  const loadPage = async (): Promise<void> => {
    if (!endpoint || !(bodyEl instanceof HTMLElement)) return;
    if (errorEl instanceof HTMLElement) errorEl.textContent = '';
    setTableMessage(bodyEl, Number(colSpan), String(loadMessage));
    try {
      const res = await App.fetchJson(`${String(endpoint)}?${buildQueryString()}`);
      if (!res.ok) {
        PermissionIntel.renderError(
          errorEl instanceof HTMLElement ? errorEl : null,
          `${String(loadMessage).replace(/\.\.\.$/, '')} failed.`,
          `HTTP ${res.status}\nerror: ${res.error || 'Request failed'}`
        );
        return;
      }
      const body = res.body as JsonRecord & { data?: unknown; meta?: unknown };
      renderRows(body.data || []);
      renderMeta(body.meta || {});
    } catch (err) {
      PermissionIntel.renderError(
        errorEl instanceof HTMLElement ? errorEl : null,
        `${String(loadMessage).replace(/\.\.\.$/, '')} failed.`,
        err instanceof Error ? err.message : String(err)
      );
    }
  };

  const resetAndLoad = async (): Promise<void> => {
    setPage(1);
    return loadPage();
  };

  const primeLov = async (): Promise<void> => {
    if (!lovEndpoint || typeof loadLov !== 'function') return;
    const lov = await PermissionIntel.fetchLov(String(lovEndpoint));
    if (lov) {
      loadLov(lov);
    }
  };

  return {
    loadPage,
    resetAndLoad,
    primeLov,
    setPage,
    getPage: () => page,
  };
};

window.PermissionIntel = PermissionIntel;

export { PermissionIntel };
