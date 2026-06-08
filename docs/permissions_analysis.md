# Permission Intel Model (Operator + Workflow Contract)

This document explains how Android Permission Intel works in Erebus Web and
how it maps to the backend pipeline. Use this as the shared mental model for
web and Python teams.

## Core idea
Permissions are not just strings. Each permission is:
- observed in a sample package,
- classified by origin (AOSP/Google/OEM/App),
- bucketed by taxonomy,
- correlated with VT confidence and ATT&CK behavior when available,
- triaged by a human,
- optionally queued for dictionary maintenance.

## Golden rule
Permissions are observed first, classified later, and only applied to dictionaries through the Python / operator workflow.
The web UI reflects the current stage; it records triage and optional queue intent but does not execute dictionary apply.

## Analysis layers (ordered)
1) **Observation (ground truth)**
   - Table: `android_permission_obs_sample`
   - Meaning: "This sample requested this permission."
   - Read-only, no interpretation.
   - UI must not dedupe, hide, or "correct" observations.

2) **Enrichment (VT event evidence)**
   - Table: `android_permission_enrich_vt_event` (immutable evidence)
   - Rollup: `android_permission_enrich_vt_current` (latest view)
   - UI wording: "Occurrences observed" (vt_event row counts)
   - UI must not infer distinct samples or dedupe client-side.

3) **Classification (origin)**
   - Enum: `AOSP | GOOGLE | OEM | APP_DEFINED | UNKNOWN`
   - Owned by backend; UI displays, never computes.

4) **Taxonomy bucket (treatment)**
   - Buckets: `AOSP_EXACT | AOSP_HIDDEN_PRIV | GOOGLE_GMS | APP_DEFINED_OTHER | OEM_EXACT | UNKNOWN`
   - Owned by backend; UI displays labels from LOVs.
   - Evidence may include `OEM_CANDIDATE` as a hint; UI maps it to Unknown in rollups and shows a row badge.

5) **Triage workflow (human decision)**
   - Table: `android_permission_dict_unknown`
   - Fields: `triage_status`, `notes`
   - This is a workflow table, not a dictionary.
   - Current web triage is evidence-first:
     - active lane: current evidence-backed UNKNOWNs that need review
     - governed lane: current UNKNOWNs that are explained/governed/malformed/missing ledger context
     - ledger lane: historical workflow residue and diagnostics
   - `seen_count` in the ledger is historical context, not the primary live workload metric.

6) **Queue staging (handoff to Python)**
   - Table: `android_permission_dict_queue`
   - Meaning: "Apply this dictionary maintenance intent later."
   - Status: `queued | claimed | applied | error` (legacy values may appear but are not canonical)
   - Action: `defer | aosp | google | oem | app_defined | reject`
   - Legacy aliases may exist in stored rows; for example, `aosp_promote` is normalized to `aosp`.
   - Current state only (one row per permission_string); history lives in audit tables.
   - Queue is a maintenance handoff/diagnostics surface, not evidence truth and not the default triage endpoint.

7) **Dictionary apply (Python)**
   - Python consumes queue rows and updates dictionary tables.
   - Must write audit and mark queue row `applied`.

8) **Cross-signal analysis**
   - VT confidence lives in the primary Erebus catalog.
   - Permission ATT&CK mapping lives in Permission Intel.
   - Analysis Fusion joins those surfaces and highlights:
     - permission behavior that outpaces weak/review-only VT confidence
     - strong VT confidence with no mapped permission behavior
     - aligned high-signal samples where both sources corroborate risk
   - Fusion is read-only; it points analysts to review candidates rather than mutating workflow state.

## Canonical enums (must match exactly)
Classification:
- `AOSP | GOOGLE | OEM | APP_DEFINED | UNKNOWN`

Buckets:
- `AOSP_EXACT | AOSP_HIDDEN_PRIV | GOOGLE_GMS | APP_DEFINED_OTHER | OEM_EXACT | UNKNOWN`

Queue actions:
- `defer | aosp | google | oem | app_defined | reject`
- accepted legacy aliases include `aosp_promote -> aosp`, `gms -> google`, and `ignore/rejected -> reject`

## Dictionary tables
- `android_permission_dict_aosp`: scraped docs (prefer read-only).
- `android_permission_dict_oem`: curated OEM permissions (safe to update).
- (Recommended) `android_permission_dict_aosp_curated`: curated overrides.

## Evidence
Evidence is read-only and comes from:
- `android_permission_enrich_vt_event`
- The UI should never infer or alter evidence.

## Collation and matching
- Permission string columns use case-insensitive collation (utf8mb4_unicode_ci).
- UI can search case-insensitively, but should display the stored string as canonical.

## Audit and history (Python-owned)
- `erebus_admin_audit` and `android_permission_audit_classification` hold history.
- Use audit tables for apply history; `android_permission_dict_queue` is not history.

## What "done" means
We do not aim for zero unknowns. We aim for:
- High-risk current evidence-backed UNKNOWNs near zero.
- Low average age of active review rows.
- Governed UNKNOWNs explained instead of mixed into active review.
- Ledger residue visible as diagnostics, not mistaken for live workload.
- Cross-signal disagreement reviewed in Fusion/classification-gap surfaces.

## Operator expectations
- Triage decision updates the workflow table only.
- Queue update is optional unless the decision should become dictionary maintenance work.
- "Queued" means pending Python apply, not applied and not evidence truth.
