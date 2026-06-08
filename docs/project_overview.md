# Project Overview

## Purpose
Erebus Web is an internal operator cockpit for Erebus that surfaces:
- VirusTotal pipeline state (runs, holds, per-sample state)
- Android permission intelligence (coverage, drift, triage, evidence, catalogs)
- cross-database analysis that compares VT confidence with Permission Intel ATT&CK behavior

The UI reflects DB truth; it does not infer or fabricate state.

## Goals
- Provide a stable operator cockpit aligned with DB truth.
- Keep permission workflows auditable and repeatable.
- Reduce unknown-permission churn through evidence-first triage and optional queue -> dictionary maintenance handoff.
- Maintain clear separation: workflow pages mutate state; catalog pages do not.

## Current state
The web app is now database-first and split-catalog aware:
- Primary catalog defaults to `erebus_threat_intel_prod`
- Permission Intel may live in `android_permission_intel`
- The UI reflects MariaDB truth and does not invent operator state
- The CLI/Python stack owns VT enrichment, Permission Intel writes, queue apply, and DB maintenance
- The web stack owns operator visibility, triage edits, queue intent, and read-heavy analysis

## Active work areas
- Tighten Permission Intel truth semantics in Overview / Triage / Review
- Keep queue and triage mutations explicit and auditable
- Make Analysis Fusion and VT confidence first-class review surfaces
- Improve VT operator surfaces without hiding DB-backed fallback behavior
- Remove remaining phase-era and legacy-branding residue from docs and low-value code paths

## Architecture map
Use `docs/system_architecture_synthesis.md` as the current shared mental model
for how the primary catalog, Permission Intel catalog, CLI, and web app fit
together.

## Non-goals (for now)
- No auto-resolution of OEM permissions.
- No UI-side bucket/classification inference.
- No direct UI application of dictionary updates (Python owns apply).
