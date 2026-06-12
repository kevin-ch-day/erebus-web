import type { AppSurface } from './app-globals';

export interface SampleDetailSurface {
  renderSample(data: { sample: Record<string, unknown>; last_run?: Record<string, unknown> | null; platform_context?: Record<string, unknown> | null }): {
    isAndroid: boolean;
    lastRun: Record<string, unknown> | null;
  };
  updateHeader(sample: Record<string, unknown>): void;
}

export interface SamplePermissionRow extends Record<string, unknown> {}

export interface SamplePermissionSummaryRow extends Record<string, unknown> {}

export interface SamplePermissionBucketDisplay {
  label: string;
  title: string;
}

export interface SamplePermissionsRenderers {
  renderPermTiles(rows: SamplePermissionRow[]): void;
  populateBucketFilter(rows: SamplePermissionRow[]): void;
  renderUnknownList(rows: SamplePermissionRow[]): void;
  renderPermSummary(rows: SamplePermissionSummaryRow[]): void;
  renderPermDetail(rows: SamplePermissionRow[]): void;
}

export interface SamplePermissionsCsvTools {
  toCsv(rows: SamplePermissionRow[]): string;
  downloadCsv(filename: string, contents: string): void;
}

export interface SamplePermissionsController {
  loadPermissions(sampleIdValue: unknown): Promise<void>;
  setStatus(message: string): void;
  showNonAndroid(): void;
  resetEmpty(): void;
  setExportSampleId(value: unknown): void;
}

export interface SampleMetadataEditor {
  open(sample: Record<string, unknown>): void;
}

export interface SampleDetailNamespace {
  fmtUtc(value: unknown): string;
  formatBytes(value: unknown): string;
  titleCase(value: unknown): string;
  hasDisplayValue(value: unknown): boolean;
  renderSummary(
    summaryEl: HTMLElement | null,
    sample: Record<string, unknown>,
    options?: { showEditButton?: boolean; onEdit?: ((sample: Record<string, unknown>) => void) | null }
  ): void;
  bindSummaryCopy(summaryEl: HTMLElement | null): void;
  createPermissionsController(
    elements: Record<string, HTMLElement | HTMLSelectElement | HTMLButtonElement | null>,
    endpoints: { permSummaryEndpoint?: string; permDetailEndpoint?: string }
  ): SamplePermissionsController;
  createPermissionsRenderers(
    elements: Record<string, HTMLElement | HTMLSelectElement | null>,
    helpers: {
      bucketLabel: (value: unknown) => string;
      bucketDisplay: (row: SamplePermissionRow) => SamplePermissionBucketDisplay;
      isUnknown: (row: SamplePermissionRow) => boolean;
      knownBadge: (row: SamplePermissionRow) => string;
      ruleLabel: (value: unknown) => string;
    }
  ): SamplePermissionsRenderers;
  createPermissionsCsv(helpers: {
    bucketLabel: (value: unknown) => string;
    isUnknown: (row: SamplePermissionRow) => boolean;
    ruleLabel: (value: unknown) => string;
  }): SamplePermissionsCsvTools;
  createEditor(options: Record<string, unknown>): SampleMetadataEditor;
  createSurface(elements: Record<string, HTMLElement | null>): SampleDetailSurface;
}

declare global {
  interface Window {
    App: AppSurface;
    SampleDetail: SampleDetailNamespace;
  }
}
