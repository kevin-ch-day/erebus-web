# Knowledge Retention Audit

Status: retained summary

This file keeps only the caveats that are easy to forget and still costly to
get wrong. Detailed system shape belongs in `docs/system_architecture_synthesis.md`
and `docs/phase2_contract.md`.

## Retained facts

- The web works across two logical catalogs:
  - `erebus_threat_intel_prod` owns VT state, sample catalog, ingest, and older
    derived analytics.
  - `android_permission_intel` owns Permission Intel dictionaries, observations,
    VT permission events, ATT&CK mappings, and governance/signal overlays.
- Do not add a broad `permission_*` routing rule. Some primary-owned tables
  still begin with `permission_`.
- `android_permission_obs_sample` is current per-sample observation evidence.
- `android_permission_enrich_vt_event` is event history.
- `android_permission_enrich_vt_current` is a rollup/cache.
- `android_permission_dict_unknown.seen_count` is historical ledger context,
  not the live prevalence count.
- Queue intent is not evidence truth. Python owns apply.
- The browser must not compute canonical classification, bucket, or authority.

## Queue semantics to retain

Canonical web queue actions:

- `defer`
- `aosp`
- `google`
- `oem`
- `app_defined`
- `reject`

Accepted legacy aliases:

- `aosp_promote -> aosp`
- `gms -> google`
- `ignore -> reject`
- `rejected -> reject`
- `approve/accept -> apply`

## Re-test before bigger changes

- split-catalog routing still sends PI-owned prefixes to `android_permission_intel`
- primary-owned `permission_*` analytics still route to `erebus_threat_intel_prod`
- queue action aliases still normalize correctly
- UI copy still distinguishes current evidence from ledger history
