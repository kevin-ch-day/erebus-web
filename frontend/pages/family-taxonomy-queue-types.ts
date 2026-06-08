import type { JsonRecord } from '../types/app-globals';

export type PageParams = Record<string, string>;

export type QueuePreset = {
  title?: unknown;
  count?: unknown;
  description?: unknown;
  button_label?: unknown;
  button_tone?: unknown;
  alignment?: unknown;
  pattern?: unknown;
  decision_mode?: unknown;
};

export type FixActionInventory = {
  total_rows?: unknown;
  action_counts?: unknown;
  top_target_families?: unknown;
};

export type DecisionInventory = {
  decision_mode_counts?: unknown;
  decision_priority_counts?: unknown;
};

export type ApplyPlanRow = {
  plan_action?: unknown;
  target_family?: unknown;
  row_count?: unknown;
  sample_id_count?: unknown;
  sample_ids?: unknown;
  decision_modes?: unknown;
  confidence_buckets?: unknown;
  sql_preview?: unknown;
};

export type ApplyPlanPayload = {
  dry_run?: unknown;
  supported_actions?: unknown;
  plan_rows?: unknown;
  summary?: unknown;
};

export type RepairOpportunity = {
  suggested_fix_action?: unknown;
  suggested_fix_reason?: unknown;
  suggested_target_family?: unknown;
  row_count?: unknown;
  high_confidence_rows?: unknown;
  dominant_issue_kind?: unknown;
  decision_priority?: unknown;
  decision_mode?: unknown;
  catalog_label_examples?: unknown;
  signal_label_examples?: unknown;
  sample_id_preview?: unknown;
};

export type SummaryRow = {
  alignment_status?: unknown;
  row_count?: unknown;
  generic_label_count?: unknown;
};

export type QueueRow = {
  sample_id?: unknown;
  sample_label?: unknown;
  android_package_name?: unknown;
  sha256?: unknown;
  family_label?: unknown;
  generic_label_flag?: unknown;
  popular_threat_name?: unknown;
  popular_threat_label?: unknown;
  popular_threat_category?: unknown;
  parse_version?: unknown;
  confidence_bucket?: unknown;
  confidence_score?: unknown;
  recommended_action?: unknown;
  issue_kind?: unknown;
  issue_reason?: unknown;
  suggested_fix_action?: unknown;
  suggested_fix_confidence?: unknown;
  suggested_target_family?: unknown;
  suggested_fix_reason?: unknown;
  decision_priority?: unknown;
  decision_mode?: unknown;
  decision_why?: unknown;
  alignment_status?: unknown;
  review_lane?: unknown;
};

export type QueueMeta = JsonRecord & {
  pair_catalog?: unknown;
  pair_signal?: unknown;
  fix_action?: unknown;
  target_family?: unknown;
  decision_mode?: unknown;
  requested_decision_mode?: unknown;
  recovered_decision_mode?: unknown;
  generated_at_utc?: unknown;
  schema_available?: unknown;
  primary_database?: unknown;
};

export type QueueData = JsonRecord & {
  queue_presets?: unknown;
  fix_action_inventory?: unknown;
  decision_inventory?: unknown;
  apply_plan?: unknown;
  repair_opportunities?: unknown;
  summary?: unknown;
  rows?: unknown;
};

export function asRows<T>(value: unknown): T[] {
  return Array.isArray(value) ? (value as T[]) : [];
}

export function toRecord(value: unknown): JsonRecord {
  return value && typeof value === 'object' ? (value as JsonRecord) : {};
}

export function mapEntries(value: unknown): Array<[string, unknown]> {
  const record = toRecord(value);
  return Object.entries(record);
}
