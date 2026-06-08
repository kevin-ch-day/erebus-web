# Erebus System Architecture Synthesis

This document is the cross-system working model for the web app, Erebus CLI,
and the two MariaDB catalogs. Use it before changing workflows, page semantics,
or SQL joins.

## System roles

| Layer | Responsibility | Does not own |
| --- | --- | --- |
| Primary Erebus catalog | VT state, malware/sample catalog, artifact intake, run ledgers, VT confidence, operational audit | Permission dictionary truth |
| Permission Intel catalog | `android_permission_*` dictionaries, concepts/tokens, observations, VT permission events, unknown lifecycle, queue intent, ATT&CK permission surfaces, governance/signal tables | VT lookup lifecycle or sample registry |
| Erebus CLI / Python | VT enrichment, durable persistence, Permission Intel writers, queue apply, diagnostics, migrations | Browser-only classification logic |
| Erebus Web | Operator cockpit, read-heavy analysis, triage edits, queue intent, health/schema visibility | Applying dictionary changes or inventing DB state |

The web app should display and filter DB truth. It should not compute canonical
classification, bucket, or provenance in the browser.

## Database model

The primary catalog defaults to `erebus_threat_intel_prod`. It is the owner of
hash/sample records, VT state, confidence surfaces, ingest queues, migrations,
and audit data.

The Permission Intel catalog may be colocated in the same schema or split into
`android_permission_intel`. In split mode, `android_permission_*`,
`android_attack_*`, and intentionally PI-owned governance/signal surfaces are
read from the Permission Intel catalog. Cross-catalog features must be explicit
about which side owns each table.

High-value fact layers:

| Layer | Canonical examples | Meaning |
| --- | --- | --- |
| Sample and VT state | `malware_sample_catalog`, `virustotal_sample_state` | What sample/hash exists and what VT knows about it |
| VT confidence | `vt_sample_verdict_confidence_current` | Current VT-based confidence and false-positive review posture |
| Permission observation | `android_permission_obs_sample` | A sample requested a permission string |
| Permission event history | `android_permission_enrich_vt_event` | VT/Androguard-derived permission evidence over time |
| Permission lifecycle | `android_permission_dict_unknown` | Intake/triage ledger; not simply "currently unknown" |
| Queue intent | `android_permission_dict_queue` | Operator-approved maintenance intent for Python apply |
| Behavior mapping | `v_android_permission_attack_surface_current`, summary views | Permission behavior and ATT&CK context for analysis |
| Concept/token intelligence | `android_permission_concept`, token alias/anomaly/family tables | Raw-token and namespace intelligence used to reduce false positives |
| Governance/signal overlays | `permission_governance_*`, `permission_signal_*` | Research checkpoints and future structured signal mapping |

Important rule: `android_permission_dict_unknown.seen_count` is not the live
sample or event count. Prefer evidence/event/current views for workload and
risk ranking.

Routing note: the current web helper automatically treats
`android_permission_*`, `v_android_permission_*`, `vw_permission_*`,
`android_attack_*`, `permission_governance_*`, and `permission_signal_*`
objects as Permission Intel objects. Do not add a broad `permission_*` rule:
the primary catalog still owns older derived-analysis tables such as
`permission_coverage_report` and `permission_discriminability_rank`.

## Core flows

Artifact and VT flow:

```text
hash/artifact intent
  -> primary artifact queue / sample catalog
  -> VT lookup and raw response handling in Python
  -> normalized VT state and confidence rows in primary catalog
  -> permission observations/events written through Permission Intel paths
  -> audit and operator summaries
```

Permission review flow:

```text
obs_sample + enrich_vt_event + dictionaries
  -> backend classification / bucket / unknown lifecycle rows
  -> web Overview/Triage/Review/Evidence
  -> optional queue intent in android_permission_dict_queue
  -> Python queue apply
  -> dictionaries + audit + applied queue state
```

Cross-signal analysis flow:

```text
primary VT confidence
  + Permission Intel ATT&CK / permission behavior
  -> Analysis Fusion buckets
  -> operator review candidates
```

Fusion is read-only. It should point to gaps and disagreements; it should not
mutate triage, dictionaries, or sample state.

## Current web structure

The app is still a PHP/MySQL application with progressive TypeScript islands:

| Area | Current shape |
| --- | --- |
| Routing and views | PHP page controllers and templates |
| Backend data access | PHP services using primary and Permission Intel DB connections |
| APIs | PHP JSON endpoints under `public/api.php/...` |
| Frontend | Vite-built TypeScript shell at `public/assets/build/app-shell.js` |
| Legacy JS | Kept only where a page has not migrated to the shell bundle |

This is a valid near-term structure. The highest ROI is not a full SPA rewrite;
it is to keep moving page controllers into typed modules, keep APIs explicit,
and keep database ownership clear.

## Tech-stack direction

Short term:

- Keep PHP routing and MySQL services stable.
- Use TypeScript for new or migrated browser logic.
- Add API contracts before changing page behavior.
- Keep queue apply in Python.

Medium term:

- Consider a typed backend boundary for new analysis APIs if PHP services become
  too broad.
- If a larger rebuild is approved, prefer a deliberate API-first split:
  Python/FastAPI or a structured PHP framework for backend APIs, plus a
  TypeScript operator console.
- Do not run two competing UI shells in parallel longer than necessary.

## Design guardrails

- Database truth beats UI assumptions.
- Classification and buckets are backend-owned.
- Queue means intent, not evidence and not applied state.
- Canonical web queue actions are `defer`, `aosp`, `google`, `oem`,
  `app_defined`, and `reject`; Python also accepts legacy aliases such as
  `aosp_promote` for older rows.
- Permission Evidence and Analysis Fusion are read-only proof surfaces.
- Web writes should be small, auditable workflow mutations.
- CLI/Python is the owner of enrichment, queue apply, migrations, and heavy DB
  maintenance.
- Split-catalog deployments must work as first-class deployments, not edge
  cases.

## Next high-ROI work

- Finish TypeScript migration for Permission Triage and Permission Overview.
- Add/keep contract tests for cross-database APIs before broad UI changes.
- Surface schema/capability gaps clearly instead of failing pages.
- Keep pruning old phase-era pages or scripts that imply Queue is the primary
  permission workflow.
- Align any CLI API endpoints with the same names and semantics used by the web.
