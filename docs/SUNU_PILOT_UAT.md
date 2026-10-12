# SUNU Pilot — Verification gates (2026-09-04)

## Gate 1 — Forks isolated
- `/var/www/html/sunu_agency` (DB `sunu_agency`, APP on :8001)
- `/cust/projects/ITS/sunu_telpo` (`applicationId` `com.sunu.telpo`)
- Original `/var/www/html/agency` and `/cust/projects/ITS/its_telpo` untouched

## Gate 2 — Subscribe + first premium + BO
- CNSS-shaped multipart proposal → `success=true`, proposal number echoed as `voluntary_insured_number`
- `policy_cost` + `first_premium` → policy auto-created, collector uncollected 2500
- Web routes under `/business/sunu/*` registered; Excel/CSV export at `/business/sunu/premiums/export`

## Gate 3 — Card collect
- API: `GET /api/v1/sunu/policies/by-card/{uid}`
- POS: Read Card menu retained; recurring collect uses Encaisser prime flow (proposal/policy number)

## Gate 4 — Cap / pay-in unlock
- Declared pay-in → status `declared`
- Confirm via `CollectorCapService::applyPayInConfirm` → uncollected 0, unblocked
- Cap set to 100 → collection returns HTTP **423** with message about exceeding cap

## Gate 5 — Offline idempotency
- Replay same `reference` → `Premium already recorded (idempotent)` (no double collection)
- POS offline queue: existing CNSS SQLite outbox retained for enrollment/contribution

## Gradle note
- Device APK build not verified here (Gradle dist incomplete on this host). Sources and resources are in place for Android Studio / CI build.
