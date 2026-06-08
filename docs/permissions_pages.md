# Permissions Pages (Operator Guide)

This guide describes each permissions page, its purpose, and what an operator
should do there. Workflow pages mutate state; catalog pages do not.

## Workflow pages

### Permission Overview
**Purpose:** "Is the pipeline healthy, and where should I go next?"
- Shows current evidence posture, governed UNKNOWNs, ledger diagnostics, classification gaps, and maintenance signals.
- No edits.
- If current evidence needs review, go to Triage.
- If VT confidence and Permission Intel behavior disagree, go to Analysis Fusion.
- Queue counts are maintenance handoff diagnostics, not primary workflow priority.

### Permission Drift
**Purpose:** "Which namespaces are changing?"
- Diagnostic view (Core / Expected / OEM / Anomalous).
- No edits.
- Use it to scope triage by namespace, then confirm sample-level behavior in Triage/Evidence/Fusion.
- Counts are "Occurrences observed" (vt_event row counts; no distinct-sample inference).

### Permission Triage
**Purpose:** "Which unknowns need attention now?"
- Evidence-first review workspace for current UNKNOWNs.
- Review/Classify opens the Review page.
- Active lane shows current evidence-backed UNKNOWNs that need review.
- Governed lane shows current UNKNOWNs that are explained, governed, malformed, or missing ledger context.
- Ledger lane shows historical workflow residue and diagnostics.
- Queue status is context only. Queued != applied.
- No apply/retry controls in the UI (Python-only).

### Permission Review
**Purpose:** "Make a decision for a specific permission."
Steps:
1) Set triage status.
2) Add notes (optional).
3) Queue dictionary update only when the decision should become Python-maintained dictionary work.
Save returns to Triage by default.
- Queue updates propose a change; they do not apply it.
- Classification and bucket values are backend-owned. The browser displays them, but does not infer them.

### Permission Queue
**Purpose:** "What dictionary maintenance intent is queued vs applied right now?"
- Read-only view of `android_permission_dict_queue`.
- Shows queue status, action, timestamps, and any error text.
- Use it for diagnostics, apply visibility, and static-import residue investigation.
- No apply, retry, or reset controls in the UI (Python-only).
- Unknown codes are displayed verbatim as `unknown:<raw_code>`.

### Permission Evidence
**Purpose:** "Proof for decisions."
- Read-only evidence from `android_permission_enrich_vt_event`.
- Use for audit and justification.
- Use the workflow links to return to Triage or Review.
- OEM candidate evidence rows show an `OEM candidate` badge.
- Evidence counts reflect vt_event row counts; do not infer distinct samples.

### Analysis Fusion
**Purpose:** "Where do VT confidence and Permission Intel behavior disagree?"
- Read-only cross-database analysis page.
- Joins Erebus VT confidence with Permission Intel ATT&CK behavior.
- Prioritize `behavior_outpaces_vt` and `vt_without_permission_behavior` buckets.
- Use it to find false positives, missing behavior mappings, and corroborated high-risk samples.

## Catalog pages (read-only)

### AOSP Permissions
**Purpose:** Reference baseline for AOSP-defined permissions.
- Use for docs-backed validation.

### Google Permissions
**Purpose:** Reference GMS / Play Services permissions.
- Use for Google-defined / Play Services validation.

### OEM Registry
**Purpose:** Vendor / namespace overview.
- Guides OEM maintenance, not decisions.

### OEM Permissions
**Purpose:** Permission-level OEM maintenance.
- Read-only until OEM workflows are wired.

## Check Hash workflow (hash-first intake)
- Operators can search by MD5/SHA-1/SHA-256.
- If no match is found, the intake form appears to queue a new artifact.

## Submit Artifact workflow (bulk intake)
- Bulk rows accept MD5/SHA-1/SHA-256.
- Use the source dropdown (VirusTotal alert, OSINT, Vendor report, Internal hunt, Manual, Other).

## Operator flow (recommended)
Overview -> Triage -> Review -> Evidence.

Use Drift to scope namespace pressure. Use Analysis Fusion when VT confidence and Permission Intel behavior disagree. Use Queue only for dictionary maintenance handoff/diagnostics.

## Common pitfalls (avoid)
- Treating "queued" as "applied".
- Treating Queue as evidence truth.
- Re-queueing applied/error items without an explicit reset action.
- Trying to edit buckets from the UI.
- Assuming no evidence means failure (it can mean no OK run).
- Interpreting counts as distinct samples (they are vt_event row counts).
