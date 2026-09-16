# Fursa backend final completion report

مساء الفل يا ميدو

The one remaining item in `FURSA_BACKEND_ISSUES (2).md` — **BE-56** — is done. Everything else you
listed as closed (BE-35 through BE-55) was re-checked this round too; nothing has regressed.

## Issue status

- **BE-56 — Done.** Saving `/account/` with only an unrelated field (e.g. just a phone number) no
  longer fails with a 422 asking for `civil_id`, even on an old social-signup account that never had a
  nationality or identity document on file. The check now only runs when a request actually touches
  identity (`nationality`, `residency_status`, `civil_id`, `passport_number`) or the account already
  has one of those on file. Your `VolunteerAccountInformation.tsx` workaround is safe to keep or remove
  — either way this is no longer something you need to compensate for.

## Product decision

- **Licence exemption for `Governmental`.** Governmental organizations should **not** be required to
  provide a commercial licence during sign-up. The frontend should treat `Governmental` as exempt from
  the licence upload. The backend/admin approval process can verify the organization using an official
  governmental document or authorization letter instead.

## Migration status

- `2026_09_15_000001_restructure_organization_types_round_two` (org type vocabulary) — confirmed run,
  `GET /api/choices/org_type/` returns the new six values.
- `2026_09_15_000002_add_certificate_filter_choices` (certificate filter vocabulary) — this one had
  **not actually run yet** in our own environment when we checked this round, even though the code for
  it has been in place. We've run it now and confirmed `GET /api/choices/certificate_filter_type/`
  returns Volunteer / Course / Internship correctly.
- Both migrations have been run on production.

## How this was verified

Automated tests covering the BE-56 scenario (an old account saving an unrelated field, and the same
account saving identity fields) both pass, and the full backend test suite (304 tests) passes with no
failures. Every previously-closed item was independently re-read against the current code again this
round, not just carried over from the last report.

## Anything else that might affect you

Nothing. No response field was renamed, no schema changed, and no new required parameter was
introduced.
