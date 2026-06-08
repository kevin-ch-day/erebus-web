# Current Smoke Test Results (Template)

Use this template to log a current web/API smoke pass.

## Run metadata
- Date/time (UTC):
- Tester:
- Web commit hash:
- API base URL:
- Feature flags:
- Notes:

## Checks (pass/fail)
- VT & Pipeline Health and VT Key Drilldown pages load (no 500s)
- Permission Overview / Triage / Review load (no 500s)
- Permission Evidence and Permission Queue load or show explicit empty/schema states
- Analysis Fusion and VT Confidence load or show explicit schema-unavailable states
- "Applied at ... UTC" renders for applied items (triage + review)
- Non-applied items show no "Applied at"
- UTC formatting looks consistent
- Unknown codes render as unknown:<raw>
- No JS console errors

## Evidence
- Screenshots (links):
- Logs (if any):
