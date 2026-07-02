import type { JsonRecord } from './app-globals';

export type TriageStatusOption = {
  key: string;
  label: string;
};

export type TriageFilters = {
  term?: string;
  view?: string;
  namespace?: string;
  risk?: string;
  status?: string;
  allowedStatuses?: string[];
  queued?: string;
  includeResolved?: boolean;
  sort?: string;
};

export type TriagePaging = {
  page?: number;
  page_size?: number;
  total_count?: number;
  total_pages?: number;
  has_more?: boolean;
  sort?: string;
};

export type TriageRow = JsonRecord;

export type TriageTableOptions = {
  bodyEl: HTMLElement | null;
  triageStatusMap?: Map<string, string>;
  onReview?: (row: TriageRow) => void;
  onEvidence?: (row: TriageRow) => void;
  onQueue?: (row: TriageRow) => void;
  onInspect?: (row: TriageRow) => void;
  showQueue?: boolean;
  emptyMessage?: string;
  reviewLaneLabelMap?: Record<string, string>;
  diagnosticLabelMap?: Record<string, string>;
};

export type SessionHeaderElements = {
  sessionHighEl: HTMLElement | null;
  sessionMediumEl: HTMLElement | null;
  sessionLowEl: HTMLElement | null;
  sessionTotalEl: HTMLElement | null;
  sessionLastOkEl: HTMLElement | null;
  sessionTaxonomyEl: HTMLElement | null;
  sessionNoteEl: HTMLElement | null;
};

export type PermTriageNamespace = {
  queueStatuses: string[];
  priorityScore: (row: TriageRow) => number;
  findNextRow: (rows: TriageRow[]) => TriageRow | null;
  renderSessionHeader: (
    triageStatusCounts: JsonRecord,
    session: JsonRecord,
    health: JsonRecord,
    taxonomy: JsonRecord,
    elements: SessionHeaderElements,
    metrics: JsonRecord,
    operatorSummary: JsonRecord,
    currentEvidenceRows: TriageRow[],
  ) => void;
  applyFilters: (rows: TriageRow[], filters: TriageFilters) => TriageRow[];
  emptyMessage: (unknownRows: TriageRow[], healthData: JsonRecord) => string;
  populateNamespaceFilter: (namespaceEl: HTMLSelectElement | null, rows: TriageRow[], initialNamespace: string) => string;
  populateStatusFilter: (statusEl: HTMLSelectElement | null, triageStatuses: TriageStatusOption[]) => void;
  renderUnknownTable: (rows: TriageRow[], options: TriageTableOptions) => void;
  renderCurrentEvidenceTable: (rows: TriageRow[], options: TriageTableOptions) => void;
  renderLedgerDiagnosticsTable: (rows: TriageRow[], options: TriageTableOptions) => void;
  renderFilterSummary: (
    summaryEl: HTMLElement | null,
    filters: TriageFilters,
    paging: TriagePaging,
    triageStatusMap: Map<string, string>,
  ) => void;
  statusLabel: (triageStatusMap: Map<string, string>, statusKey: string) => string;
  queueStatusLabel: (status: string) => string;
};

declare global {
  interface Window {
    PermTriage: PermTriageNamespace;
  }
}
