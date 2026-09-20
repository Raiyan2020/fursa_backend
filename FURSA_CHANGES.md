# Backend changes for the mobile app — 2026-09-20

Everything below is already merged on the `updates` branch and covered by automated tests
(`php artisan test`) plus a live HTTP run against a running server (real curl calls, not just
the test DB) — see the "Verified" line under each item.

---

## 1. Status lines

| Item | Mobile action needed? |
|---|---|
| **BE-69** — new attendance-management permission ("إذن تحضير") | **Yes — see §2** |
| **BE-70** — `due_date` is now required when creating/updating a learn & serve opportunity | **Yes — see §3** |
| **BE-68** — guardian columns added to the volunteers export | No — export-only (web/admin download), nothing in the API payload mobile reads changed |
| **BE-71** — `platform_fee_percentage` exposed on learn & serve opportunities | Optional — see §4 |

---

## 2. BE-69 — attendance-management permission

An organizer can now delegate "manage attendance for this opportunity" to another user
(mirrors the existing scan-permission flow but is independent of it, and does **not** depend on
the `organizer_scan_flow_enabled` flag).

### New endpoints

**Grant/revoke — `POST /api/attendance-permissions/bulk-update/`** (opportunity creator only)

Either shape works:
```json
{"opportunity_id": 13, "permissions": [{"user_id": 10, "is_allowed": true}]}
```
or
```json
{"opportunity_id": 13, "user_ids": [10, 11], "is_allowed": true}
```
Returns `200` with the resulting `[{user_id, is_allowed, attendance_permission_id}]` list.

**List holders — `GET /api/attendance-permissions/list/?opportunity_id=13`** (opportunity creator
only, supports `search` by email/first name/last name/civil ID/passport number, paginated).

### What a granted user can now do on that opportunity

- `POST /api/volunteer-opportunity-registrations/direct-register/` — direct-register volunteers
  (payload: `{"opportunity_id": ..., "user_ids": [...]}`)
- `POST /api/volunteer-attendance/manual/` — record manual attendance
- `PATCH /api/volunteer-attendance/{id}/hours/` — edit recorded hours

A user without the grant (and not the owner) gets `403` on all three.

### New field to check before showing attendance-management UI

`GET /api/opportunities/{id}/details/` now returns:
```json
"can_manage_attendance": true
```
`true` for the owner or anyone holding the permission, `false` otherwise (including for
unauthenticated requests). Use this instead of comparing `created_by` to the current user.

**Verified:** `tests/Feature/AttendancePermissionTest.php` (8 tests) + live curl run: granted a
permission, confirmed it in the list, direct-registered as the holder (201) and as a stranger
(403), recorded manual attendance as the holder (200) and as a stranger (403), edited hours as
the holder (200) and as a stranger (403), and confirmed `can_manage_attendance` is `true` for
owner/holder and `false` for a stranger via `/api/opportunities/{id}/details/`.

---

## 3. BE-70 — `due_date` is now required for learn & serve opportunities

`POST /api/learn-serve-opportunities/` and the admin equivalent now reject a missing `due_date`
with a `422` and a `due_date` validation error — it was previously optional. `PATCH` (partial
update) still allows omitting it on updates that don't touch the field. Events are unaffected —
they never required `due_date` and still don't.

If the mobile create/edit form for learn & serve opportunities doesn't already require this
field client-side, requests that omit it will now fail where they previously succeeded.

**Verified:** `tests/Feature/LearnServeDueDateRequiredTest.php` (5 tests) + live curl run:
create without `due_date` → `422` with only that field flagged; create with it → `201`.

---

## 4. BE-71 — `platform_fee_percentage` (optional for mobile)

`LearnServeOpportunityResource` now includes `platform_fee_percentage` (a number, e.g. `7`)
alongside the existing `payout_after_fee`, sourced from the same config value used to compute
the payout — so the two can never disagree. If the mobile app shows a hardcoded "7%" platform
fee anywhere on the paid learn & serve publish/detail screens, it can read this field instead.

**Verified:** `tests/Feature/PaidOpportunityPayoutTest.php` (2 tests) + live curl run: created a
learn & serve opportunity and confirmed `platform_fee_percentage: 7` in the response.

---

## 5. Not verified here

The following are separate, production-only items with no code change on this branch — not
covered by the tests or live run above:

- The production `EXPOSE_OTP_IN_RESPONSE` value.
- Whether `fursa:backfill-sanitize-rich-text` and `fursa:backfill-generated-link` have been run
  against production.
- BE-57 (production row count) — not a mobile-facing item.
