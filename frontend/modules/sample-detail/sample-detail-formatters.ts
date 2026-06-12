import type { AppSurface } from '../../types/app-globals';
import type { SampleDetailNamespace } from '../../types/sample-detail-globals';

const App = window.App as AppSurface | undefined;
const SampleDetail = (window.SampleDetail || (window.SampleDetail = {} as SampleDetailNamespace)) as SampleDetailNamespace;

if (App) {
  SampleDetail.fmtUtc = (value: unknown): string => {
    return value ? App.formatUtc(value) : '--';
  };

  SampleDetail.formatBytes = (value: unknown): string => {
    const raw = Number(value);
    if (!Number.isFinite(raw) || raw <= 0) return '--';
    const mb = raw / (1024 * 1024);
    return `${mb.toFixed(2)} MB (${raw} bytes)`;
  };

  SampleDetail.titleCase = (value: unknown): string => {
    const raw = String(value || '').trim();
    if (!raw) return '--';
    return raw.split(/\s+/).map((part) => {
      return part.charAt(0).toUpperCase() + part.slice(1).toLowerCase();
    }).join(' ');
  };

  SampleDetail.hasDisplayValue = (value: unknown): boolean => {
    if (value === null || value === undefined) return false;
    if (typeof value === 'number') return Number.isFinite(value);
    if (typeof value === 'boolean') return true;
    const raw = String(value).trim();
    return raw !== '' && raw !== '--' && raw.toLowerCase() !== 'null' && raw.toLowerCase() !== 'undefined';
  };
}
