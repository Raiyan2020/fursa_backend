# Fursa backend final completion report

مساء الفل يا ميدو

All six issues in `FURSA_BACKEND_ISSUES (4).md` — **BE-57 through BE-62** — are done. Nothing from
earlier rounds has regressed.

## Issue status

- **BE-61 — Done, with one deliberate exception.** Part A (volunteering, two permanent printed
  IN/OUT codes) and Part B (learn-&-serve, one 2-hour code on the last day) are both fully
  implemented, wired into routes, and covered by tests. Part C (retiring the
  organizer-scans-volunteer flow) is **code-complete but deliberately not live**: every retired
  endpoint (`scan/`, `scan-permissions/bulk-update/`, `scan-permissions/list/`) is gated behind
  `config('fursa.organizer_scan_flow_enabled')`, which **defaults to `true`** — nothing changes in
  production until that flag is flipped, which should happen in the same release as your four
  screens coming out. Two sub-items needed no work at all, per the issue's own note: the 3-day
  manual-attendance close was already implemented, and republish already issues a fresh code.
  `volunteer_profiles.qr_code` was **not** touched or scheduled for removal — see decision below.
- **BE-62 — Done.** `Consultation`'s Arabic label is «مساحة» in both `learning_type` and
  `filter-type`; `value_en` is untouched, so nothing that matches on it needed to change.
  `master_choices` has a new nullable `slug` column, assigned once per row and never overwritten by
  a later rename, exposed on `/api/choices/{type}/` and every `masterChoicePayload()` field.
  `LearnServeOpportunity::isInternship()` now matches on `slug === 'internship'` instead of the
  label. Production has exactly one `Consultation` row (id 15) in `learning_type` — confirmed
  before renaming, see production numbers below.
- **BE-59 — Done.** Admins are now notified the moment something needs review: a new
  `admin_notifications` table (mirroring `user_notifications`, since `Admin` is a separate
  model/guard from `User`) plus `NotificationService::createForAdmins()`/`notifyAdminsWithPermission()`.
  Wired into `VolunteerOpportunityController::store()`/`resubmit()`,
  `LearnServeOpportunityController::store()` (no resubmit endpoint exists for this type — flagged,
  not built silently), `EventController::store()` and `republish()` (the latter wasn't named in the
  issue but has the identical gap), and the organization-registration path in
  `AuthService::register()` (the "organization-approval request path" the issue asked me to check).
  A sidebar badge and a new `dashboard/notifications-inbox` page complete the loop — see decision
  below for who receives each one. This is a plain in-app inbox, not push/FCM — nothing like that
  exists anywhere in this codebase for admins or users; the badge updates on the next page load.
- **BE-58 — Done.** `WebsiteVolunteerOpportunityResource` now returns `has_custom_schedule` and the
  full `time_slots` array on all three listing endpoints (`list-volunteer-opportunities`,
  `list-all-opportunities`, `list-user-opportunities`), matching `VolunteerOpportunityResource`'s
  shape. `has_custom_schedule` is derived from the eager-loaded `timeSlots` relation rather than
  calling `hasCustomSchedule()` per row — that method fires its own query regardless of eager
  loading, so calling it directly would have kept the N+1 the issue warned about.
- **BE-60 — Done, and wider than reported.** All five occurrences of the `?: link` /
  `?: registration_link` fallback are gone: the three named in the issue
  (`VolunteerOpportunityResource`, `LearnServeOpportunityResource`, `WebsiteVolunteerOpportunityResource`)
  plus two more found while checking, as asked — `WebsiteLearnServeOpportunityResource` (same
  `?: link` bug) and `WebsiteEventResource` (its own variant, falling back to `registration_link`
  instead).
- **BE-57 — Done. 102 rows affected — not a "won't fix."** `IdentityDocumentValidator::isApplicable()`
  now treats a stored `nationality = other` with `residency_status = NULL` as "not yet answered,"
  the same way the BE-56 fix already treats a fully-empty row — unless the request itself sends an
  identity field. Both call sites (`AuthController::updateAccount()`,
  `VolunteerProfileUpdateRequest`) are covered.

## Decisions

- **BE-61 — codes come back as raw payload strings**, not image URLs, for both Part A and Part B.
  QR image rendering and camera scanning are entirely your side's responsibility; the backend only
  ever compares decoded strings against a DB column.
- **BE-61 — the volunteer state field** is `self_attendance` on `VolunteerOpportunityResource`:
  `{ checked_in_at, checked_out_at, next_action }`, where `next_action` is `'in' | 'out' | 'done'`,
  `null` if the viewer isn't registered or isn't authenticated. Learn-&-serve reuses the existing
  `is_attended` boolean on the registration — no new field needed there.
- **BE-61 — which release retires the Part C endpoints**: not yet decided on either side, and it
  doesn't need to be before this ships — the code is ready and safely inert behind
  `ORGANIZER_SCAN_FLOW_ENABLED` (defaults `true`). Flip it to `false` in whichever release removes
  your four screens; nothing on the backend needs to change at that point.
- **BE-61 — yes, other things read `volunteer_profiles.qr_code`**: the achievement-report PDF
  (`AchievementReportRenderer`), the volunteer's own profile screen (`qr_code_url`), a self-download
  statistics endpoint, and `VolunteerOpportunityRegistrationResource` (`qr_code_url`/`volunteer_uuid`
  on the registration list, left untouched — out of Part C's scope). **The column must not be
  dropped.**
- **BE-59 — who receives the notification**: admins holding the module-specific `.approve`
  permission (`volunteer-opportunities.approve`, `learn-serve-opportunities.approve`,
  `events.approve`, `entities.approve`) — not a blanket broadcast to every admin. This uses the
  existing per-module permission scheme (`Admin/PermissionController`, Spatie roles) rather than
  adding a new one.

## Verification that could not be performed before — now answered

- **BE-57 row count**: **102** users with `nationality = 'other'` and `residency_status IS NULL`,
  read from production via `\App\Models\User::where('nationality', 'other')->whereNull('residency_status')->count()`.
- **Production `preparation_validity_hours` / `_days`**: `hours = 72`, `days = 7`. Hours take
  precedence when non-zero, so production actually runs the client's "3 days" exactly (72h = 3d) —
  no drift, nothing to fix. The `_days` value (7) is dead as long as `_hours` stays non-zero, worth
  knowing but not a bug.
- **Live `learning_type` rows**: exactly four, ids **13 (Course), 14 (Class/Workshop), 15
  (Consultation), 16 (Internship)** — one `Consultation` row, confirmed before renaming it, per the
  issue's own ask.

## How each fix was verified

All six ran through `php artisan test` against the project's SQLite in-memory test DB, plus targeted
runs of each new test file individually before the full-suite pass. Full suite: **335/337 passing**
— the 2 failures are pre-existing and unrelated (an order-dependent assertion on
`certificate_filter_type` row ordering in `BackendIssuesFinalRoundTest`, present before any of this
round's changes; reproduced on a clean stash to confirm before touching anything).

- **BE-61**: `tests/Feature/SelfCheckInQrTest.php` (Part A, 4 tests), `tests/Feature/LearnServeSelfCheckInQrTest.php`
  (Part B, 4 tests), `tests/Feature/RetireOrganizerScanFlowTest.php` (Part C gating, 4 tests) —
  full IN/OUT cycles with real elapsed hours via `travelTo()`, code expiry, owner-only issuance,
  410 responses when the flag is off, self-scan unaffected by the flag.
- **BE-62**: `tests/Feature/RenameConsultationSlugTest.php` (4 tests) — label renamed without an id
  change, every row gets a unique slug per choice type (including the known
  `learn_serve_certificate_type` case-variant duplicate), `isInternship()` matches via slug with a
  label fallback, `/api/choices/*` returns the new label and the slug.
- **BE-59**: `tests/Feature/AdminOpportunityNotificationTest.php` (5 tests) — an admin with the
  matching `.approve` permission gets notified and one without it does not, resubmission notifies
  again, and each of the three opportunity types plus organization registration is covered
  end-to-end through the real HTTP endpoints.
- **BE-58**: `tests/Feature/WebsiteVolunteerOpportunityScheduleTest.php` (4 tests) — all three
  listing endpoints report the real scattered days and `has_custom_schedule`, a plain-range
  opportunity reports `false`/`[]`.
- **BE-60**: `tests/Feature/LocationUrlNoLinkFallbackTest.php` (3 tests) — a volunteer opportunity,
  a learn-&-serve opportunity, and an event with `location_url` empty but a `link`/`registration_link`
  present all now return `null`, on both detail and listing surfaces.
- **BE-57**: 4 new assertions added to `tests/Feature/AuthApiTest.php` — an unrelated save on a
  legacy `other`/`NULL` row succeeds on both `/api/account/` and `/api/volunteer-profile/`, and both
  still correctly demand `residency_status` when the request touches identity fields.

## Anything else that might affect you

- **New tables**: `admin_notifications` (BE-59).
- **New columns**: `master_choices.slug` (BE-62); `notifications.link` (BE-59);
  `volunteer_opportunities.attendance_code_in`/`attendance_code_out`,
  `volunteer_opportunity_attendances.checked_in_at`/`checked_out_at` (BE-61 Part A);
  `learn_serve_opportunities.attendance_code`/`attendance_code_expires_at` (BE-61 Part B).
- **New config**: `fursa.organizer_scan_flow_enabled` (env `ORGANIZER_SCAN_FLOW_ENABLED`, default
  `true`) — BE-61 Part C's kill switch.
- **New routes**: `GET volunteer-opportunities/{id}/attendance-qr/`,
  `POST volunteer-attendance/self-scan/`, `POST learn-serve-opportunities/{id}/attendance-qr/`,
  `POST learn-serve-attendance/self-scan/` (all BE-61) — none of these existed before, so nothing
  currently calls them; the frontend can start integrating.
- **New response fields**: `self_attendance` on `VolunteerOpportunityResource`; `recorded_via`,
  `checked_in_at`, `checked_out_at` on `VolunteerAttendanceResource`; `slug` on every
  `masterChoicePayload()`-shaped field and on `/api/choices/{type}/`; `has_custom_schedule` and
  `time_slots` on `WebsiteVolunteerOpportunityResource`.
- **Removed fallback (breaking only if something relied on the bug)**: `location_url` no longer
  ever returns `link`/`registration_link` on any of the five resources listed under BE-60 — if
  anything downstream still depends on that fallback, it will now see `null` instead.
- **New admin-side surface** (not requested, needed to make BE-59 usable): `dashboard/notifications-inbox`
  page and a per-admin unread-count badge on the sidebar bell — the old `Admin/NotificationController`
  was a broadcast composer only, with nowhere for the system's own notifications to be read.
- **Standalone Artisan command**, safe to run more than once: `fursa:rename-consultation-to-space`
  (`--dry-run` supported), as an alternative to running the BE-62 migration directly.
