# Backend changes for the mobile app — 2026-09-20

This covers every backend change made while closing out the open items in
`FURSA_BACKEND_ISSUES (6).md` (BE-68, BE-69, BE-70). It is written for whoever is
building/maintaining the mobile app, since the mobile client hits the same API as
`fursa-next` / `fursa_react` and needs to match the same contract.

**All items below are done, and verified with automated backend tests
(`php artisan test`): 378 passed, 1 pre-existing unrelated failure in
`PostmanCollectionCoverageTest`** (a Postman-collection gap for an unrelated route,
flagged before this round and untouched by it). Nothing here was verified by hand in
Postman — every claim below is backed by a test that hits the real endpoint.

---

## 1. New «إذن تحضير» attendance-management permission (BE-69)

A brand-new, opportunity-scoped permission for **volunteer opportunities only**
(not learn-&-serve, not events). A volunteer holding it can:

1. Directly register other volunteers for the opportunity.
2. Record and edit their attendance hours.

It is **not** QR scanning and is **not** the existing `/scan-permission` screen —
that flow is being retired (BE-61 Part C) and this permission does not depend on it
or on its feature flag.

### Grant / revoke

```
POST /api/attendance-permissions/bulk-update/
```

Organizer/creator-only. Two accepted shapes, same as `/scan-permissions/`:

```json
{
  "opportunity_id": 123,
  "permissions": [
    { "user_id": 42, "is_allowed": true },
    { "user_id": 43, "is_allowed": false }
  ]
}
```

or the flat form:

```json
{ "opportunity_id": 123, "user_ids": [42, 43], "is_allowed": true }
```

(`is_allowed` defaults to `true` if omitted in the flat form — i.e. this is the
"Add Permission" shape.)

Response:

```json
{
  "data": [
    { "user_id": 42, "is_allowed": true, "attendance_permission_id": 501 }
  ]
}
```

### List current holders

```
GET /api/attendance-permissions/list/?opportunity_id=123&search=<optional>
```

Organizer/creator-only. Same people-picker search as the scan-permission screen
(name, email, civil id, passport number). Each row is the same shape as a
scan-permission list row: a full user object plus `is_allowed` and
`attendance_permission_id`.

### Where this unlocks access

A holder (not just the organizer) can now call:

- `POST /api/volunteer-opportunity-registrations/direct-register/`
- `POST /api/volunteer-attendance/manual/`
- `PATCH /api/volunteer-attendance/{id}/hours/`

Anyone else calling these for an opportunity they don't own and don't hold the
permission for still gets a 403, unchanged.

### New flag on the opportunity payload

```json
"can_manage_attendance": true
```

Added to the volunteer opportunity resource (`GET /api/volunteer-opportunities/{id}/`
and `GET /api/opportunities/{id}/details/`). `true` for the organizer and for anyone
holding the permission; `false` otherwise.

**Mobile action:** if there's a manage-attendance / add-volunteers screen for
organizers, gate it on `can_manage_attendance` instead of "is this the organizer"
so a granted volunteer sees the same screen. If there's an "add permission" flow on
that screen (organizer side only), wire it to
`POST /api/attendance-permissions/bulk-update/` and
`GET /api/attendance-permissions/list/` — a new, separate feature from
scan-permission, not a rename of it.

---

## 2. `due_date` is now required when creating a development (learn & serve) opportunity (BE-70)

Matches the rule already in place for volunteer opportunities.

- `POST /api/learn-serve-opportunities/` rejects a missing/empty `due_date` with a
  422 (`response_status.validation_errors.due_date`).
- A `PATCH` that simply doesn't mention `due_date` still passes — only a create, or
  an explicit empty value, is rejected.
- The database column is still nullable — legacy rows with `due_date = NULL` are
  untouched and keep falling back to `end_date` for their registration window.
- **Events are unaffected** — `due_date` stays optional there.

**Mobile action:** if there's a "create development opportunity" screen (organizer
side), make the due-date field required client-side, same as the volunteer-opportunity
create screen already does.

---

## 3. Volunteer registrations Excel export now includes guardian columns (BE-68)

Four new columns on the exported sheet (`GET
/api/volunteer-opportunity-registrations/?download=true&opportunity_id=...`):
Guardian Name, Guardian Phone, Guardian Civil ID, Guardian Relationship.

This is a web/admin-dashboard feature (file download), not something the mobile app
renders — listed here only for completeness. No API contract changed for any screen
the mobile app reads from; the JSON registration payload (`user.emergency_contact_*`)
was already correct from the previous round.

**Mobile action:** none.

---

## How this was verified

- **BE-69:** `tests/Feature/AttendancePermissionTest.php` — 8 tests covering grant/
  revoke via both request shapes, a non-owner being refused the ability to grant,
  list + search, a holder successfully calling direct-register/manual-attendance/
  hours-update while a plain volunteer is refused (403) on the same calls, and the
  `can_manage_attendance` flag being `true` for the owner and the holder and `false`
  for a stranger.
- **BE-70:** `tests/Feature/LearnServeDueDateRequiredTest.php` — 5 tests covering the
  API rejecting a create without `due_date`, accepting one with it, a partial update
  omitting it still passing, the admin form session-erroring without it, and events
  staying unaffected.
- **BE-68:** `tests/Feature/RegistrationManagementTest.php::test_download_export_includes_guardian_columns`
  — unzips the generated `.xlsx` and asserts the guardian columns and values are
  actually present in the sheet, not just that the file exists.
- Full regression pass: `php artisan test` — 378 passed, 1 pre-existing unrelated
  failure (see above).

## Not verified here (needs production access, unchanged from the last report)

- **BE-57 row count** (`nationality = 'other'` with `residency_status` NULL) — still
  needs to be read from production; this workspace has no production database
  access.
- The production `EXPOSE_OTP_IN_RESPONSE` value.
- Whether `fursa:backfill-sanitize-rich-text` and `fursa:backfill-generated-link`
  have been run against production.
