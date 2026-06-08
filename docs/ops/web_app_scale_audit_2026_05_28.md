# Web App Scale Audit

Date: 2026-05-28
Status: archival summary

This note is retained only as a short record of why the Erebus Web app needed
navigation cleanup, naming cleanup, and service-layer hardening.

## Main findings retained

- route, view, API, and page-controller naming had drifted
- service files had become the main complexity sink
- several audit/detail pages were over-exposed in the main operator path
- fallback APIs had become real product surfaces and needed clearer treatment
- the landing page was over-emphasizing platform audit over curation work

## High-value actions already taken later

- navigation was reorganized around malware curation, dataset curation, VT
  pipeline, and admin support
- obsolete VT/status pages were collapsed into the live health surface
- hidden or low-value routes were demoted from the sidebar instead of deleted
- dataset readiness, type benchmark, and authority consistency debt became the
  main governed dataset surfaces
- docs and route labels were standardized around current names

## What this file is not

- not the current structure guide
- not the current product roadmap
- not the source of truth for live route names

Use these instead:

- `docs/structure.md`
- `docs/project_overview.md`
- `docs/system_architecture_synthesis.md`
