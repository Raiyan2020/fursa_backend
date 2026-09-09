# Backend reply — 2026-09-09

This replaces the earlier reply for this date and covers every open ID in BACKEND_ISSUESـNEW.md, plus the BE-14 and BE-20 follow-ups. Changes are implemented in this checkout; **production deployment and production data verification have not been performed**. The proof below is from automated API tests with isolated SQLite fixtures and a fake storage disk. Response excerpts describe those verified fixtures, not live production records. Paths are relative to `/api/`.

## BE-01 — Legacy interest tags

**Done:** Existing master-choice-pivot reads were already implemented. Fixed the remaining `interest_display` inconsistency on event/volunteer/learn-serve resources: it now uses the same effective tags as `interests`, with populated `value_en`/`value_ar`. The existing backfill command is retained. Production execution is still outstanding; live opportunity 97 and its historical IDs 71/74/80 have not been verified.

**Proof:** `BackendMissingItemsTest::test_interest_bridge_uses_names_and_rejects_mixed_invalid_ids_atomically` creates an opportunity with a picker ID; its 201 response contains `data.interest_display[0].id` equal to that picker ID and `value_en` equal to the stored choice label. `BackfillLegacyOpportunityInterestTagsTest` tests the backfill independently. These are not production backfill counts.

**Frontend must:** nothing. Deployment operator must run `php artisan fursa:backfill-legacy-opportunity-interest-tags`, retain its inserted/created/skipped counts, and verify `GET /opportunities/97/details/` after deployment.

## BE-23 — Interest IDs and bridge

**Done:** The existing picker-ID acceptance was incomplete: it silently accepted mixed valid/invalid IDs and mirrored unrelated legacy rows when numeric IDs coincided. Every ID is now validated against the selected context before saving. Both pivots are synchronized; legacy `Interest` rows are matched/created by name, not by numeric ID. Empty arrays clear both pivots.

| Write endpoint | Picker endpoint |
|---|---|
| `/volunteer-opportunities/` | `/choices/volunteer_opportunity_interest/` |
| `/learn-serve-opportunities/` | `/choices/learnserve_opportunity_interest/` |
| `/events/` | `/choices/event_interest/` |

**Proof:** `BackendIssuesRoundTwoTest` verifies 201 and persisted tags for all three types. The additional collision regression creates an unrelated `Interest` with the same numeric ID as a valid choice; the API writes the correctly named tag instead. `POST /volunteer-opportunities/` with `[valid_choice_id,999999]` returns 422 and creates no record. The equivalent PATCH returns 422 without changing the title or existing tags. Read excerpts: `interests: [{id,name_en,name_ar,interest_type}]`; `interest_display: [{id,choice_type,value_en,value_ar}]`, with master-choice IDs when master tags exist.

**Frontend must:** Continue sending `interest_ids[]` from the matching picker. Remove `features/shared/interestIdsFallback.ts` after deployment is verified; an invalid ID should no longer cause a second, tagless create.

## BE-24 — Calendar

**Done:** Registered all five authenticated routes: `GET /my-calendar/`, `POST /my-calendar/save/`, `PATCH /my-calendar/{id}/`, `DELETE /my-calendar/{id}/`, `POST /upload-ics/`. Save checks source visibility; update/delete remain user-scoped. ICS accepts `ics_file`, a `.ics` file containing `BEGIN:VCALENDAR`, up to 1 MiB. This uploads a calendar file; it does not import events into the database.

**Proof:** `test_calendar_routes_scope_saved_items_and_upload_ics`: saving `{event_id: fixture_id}` returns 201; owner PATCH returns 200 and DELETE 204; another user's PATCH/DELETE return 404. ICS upload returns 201 with `data: {file_url,webcal_url}`. GET returns 200.

**Frontend must:** nothing; use the listed paths and `ics_file` multipart field.

## BE-25 — Republish

**Done:** Implemented ownership-protected copying that creates a new pending record and preserves the source. Gallery and inherited license bytes are copied to independent paths. No registrations, attendance, or certificates are copied.

| Type | Republish request |
|---|---|
| Event | `POST /event/republish/{source_id}` |
| Volunteer | `POST /volunteer-opportunities/` with `opportunity_id=source_id` |
| Learn & Serve | `POST /learn-serve-opportunities/` with `opportunity_id=source_id` |

Send the regular create fields, including new dates, and optionally:

- `existing_image_ids[]`: source gallery IDs to copy. Omitted copies all active source images; `[]` copies none. A foreign/deleted image ID returns 422.
- `license_image`: replacement uploaded JPG/JPEG/PNG/WebP/PDF, max 10 MiB. Replacement takes precedence over removal.
- `license_image_removed=true`: omit the inherited license when no replacement is uploaded.
- Neither license field: inherit the source license. Missing source bytes return 422, rather than returning a broken copied URL.
- New event gallery files: `images[]`. New opportunity gallery files retain the existing `new_opportunity_images_N` / `opportunity_images_N` convention.

Volunteer/learn-serve requests supply the complete create payload; event scalar fields not sent inherit the source, but normal required create fields still apply. Event due date resets to null unless supplied; registration closure resets to false. Event interests inherit when omitted. Volunteer/learn-serve interest IDs must be sent to retain their selections. Sponsors and time slots must be explicitly configured for the new record.

**Proof:** `test_republish_copies_replaces_removes_and_does_not_mutate_source` covers all three types and license modes. Each successful request returns 201 with a different `data.id`, `approval_status=pending`, copied gallery bytes, and the expected inherited/replaced/null license. It compares the source's complete persisted attributes before and after. Unauthorized sources return 403 for events, 404 for opportunities.

**Frontend must:** Implement the paths and multipart fields above; use the returned new ID. Do not submit gallery URLs as files. Send `existing_image_ids[]`, not repeated bare `existing_image_ids` keys. For an empty multipart keep-list, omit no field accidentally: send JSON where possible or use the existing delete-image workflow after creation.

## BE-26 — XLSX exports

**Done:** Added owner-protected export branches using the existing XLSX writer. Exports use the same filtered query before pagination, return every matching row, and do not mark attendance. `mark_attendance=true` returns 422 on these three flows. Files include ID, name, email, status, attended, and scan-allowed columns, with non-applicable columns empty/false.

**Proof:** `test_exports_return_real_filtered_xlsx_files_and_reject_nonowners` verifies each request below returns 200 with `data.downloadUrl`, checks ZIP/XLSX bytes and the expected registrant's email in the worksheet, and verifies a nonowner receives 403:

```http
GET /event-registrations/by-event/{event_id}/?status=approved&download=true
GET /learn-serve-opportunities/{opportunity_id}/registrations/?status=approved&download=true
GET /scan-permissions/list/?event_id={event_id}&download=true
```

Response shape: `{"key":"success","data":{"downloadUrl":"https://<configured-host>/storage/exports/<type>/<uuid>.xlsx"}}` (additional standard envelope fields omitted). Event export also works through `/event-registrations/?event_id=...&download=true`. Scan export supports the existing `opportunity_id` selector as well.

**Frontend must:** Download `data.downloadUrl`; stop sending `mark_attendance` on these exports. Use the separate attendance mutation endpoint when attendance must change.

## BE-27 — Registration eligibility

**Done:** All three public registration create flows now share approval/visibility/lifecycle/capacity checks. Pending, rejected, deleted, and private volunteer opportunities are hidden with 404. Upcoming/in-progress items can accept registrations while their registration window remains open; cancelled, completed, expired, closed, or full items cannot. Capacity counts active pending/approved registrations. Parent-row locks serialize public registration creation and its capacity checks.

**Proof:** `test_registration_rejects_hidden_closed_full_and_invalid_lifecycle_items`: each of the three registration POST routes returns 404 for pending/rejected parents, 400 for manually closed/cancelled/full parents, and 201 for an eligible fixture. Private volunteer opportunities return 404. There is no `is_public` field on events or learn-serve records; their public visibility is governed by approval/deletion.

**Frontend must:** nothing; surface the API failure normally. Owner-controlled direct volunteer enrollment remains a separate administrative flow.

## BE-28 — Registration and scan permissions

**Done:** Event registration details require the registrant or event organizer. Updates remain organizer-only. Create/update time slots must belong to that event and be active. Event scan-permission lists now check event ownership just like opportunity lists.

**Proof:** `test_event_registration_and_scan_permission_ownership_and_slot_scoping`: `GET /event-registrations/{id}/` is 200 for registrant/organizer and 403 for another user. POST/PATCH with another event's `time_slot_id` is 422. `GET /scan-permissions/list/?event_id=...` is 403 for another user and 200 for the organizer.

**Frontend must:** nothing; submit only the selected event's time-slot ID.

## BE-29 — Contact/sponsor administration

**Done:** Preserved the existing `auth:api` + `api.staff` protection and middleware. Contact list/detail/update/delete and sponsor update/delete require authenticated staff. Added throttling to public contact and sponsor submissions: 10 requests/minute per existing Laravel throttle key. Sponsor list/detail and the two public submission flows remain public.

**Proof:** `AdminApiRouteProtectionTest` covers unauthenticated 401, nonstaff 403, and staff access across protected methods. Public routes remain registered separately; submission routes now carry `throttle:10,1`.

**Frontend must:** nothing for public forms. Administrative callers must use staff credentials and handle 429 on excessive submissions.

## BE-30 — Media removal

**Done:** Event, post, and reply updates support `existing_image_ids` as an active-image keep-list, applied in the same database transaction as the edit. Omitted leaves existing media unchanged; `[]` removes all existing images; a nonempty list retains only those IDs. Newly uploaded files are added afterwards. Invalid/foreign/deleted IDs return 422. Soft-deleted images are excluded from read payloads. Physical bytes are retained with historical soft-deleted rows; there is no destructive storage purge in this change.

**Proof:** `test_media_keep_sets_remove_one_of_three_and_cannot_take_foreign_images` PATCHes each resource with two of three IDs, verifies exactly those two active rows remain, rejects an invalid ID without further deletion, and verifies an empty array removes all active rows.

**Frontend must:** Send the keep-list on edits; use bracketed multipart IDs for nonempty arrays. JSON `existing_image_ids: []` represents removal of all media. If the multipart client cannot encode an empty array, perform a JSON PATCH with `[]` and upload new files separately. Do not send `existing_image_ids=""` or the string `"[]"`.

## BE-31 — Sponsor relations

**Done:** Opportunity sponsor add restores a soft-deleted relation and persists `position`. Active duplicates still return 400. Added equivalent event POST/DELETE routes. Read payloads exclude deleted sponsors, sort by position, and event details include `organization: {id,full_name,profile_pic}` plus position. Sponsor candidates remain approved eligible organizations, excluding Volunteer Teams.

**Proof:** `test_sponsors_can_be_removed_then_restored_with_position_for_all_types`: POST `{organization_id: fixture_id, position:4}` returns 201, DELETE returns 200, then POST returns 201 with the same relation ID and `position:4`; exactly one active relation remains. Foreign creators cannot add sponsors.

**Frontend must:** Use `POST /events/{id}/sponsors/` with `organization_id` and `position`; remove via `DELETE /events/{id}/sponsors/{relation_id}/`. The existing opportunity sponsor paths have the same contract. Restore event picker selection from `event_sponsor_images[].organization.id`. Indexed `event_sponsor_images_organization_N` fields are not the contract; use these relation endpoints after saving the event. To change a relation's position, remove/re-add it with the new position.

## BE-32 — Participation contract

**Done:** The selected named participation choice derives the two behavior flags. Explicit conflicting flags fail with 422. Changing participation also clears incompatible inherited fee/capacity/link fields.

| Choice `value_en` | `registration_required` | `paid_registration` | Additional rule |
|---|---|---|---|
| Paid Event | true | true | Positive `registration_fee` required |
| Free Event | false | false | Fee/capacity zero; no registration link |
| Free Event (Registration Required) | true | false | Fee zero |
| Individual | explicitly supplied | explicitly supplied | Audience label only; paid requires registration and positive fee |
| Team | explicitly supplied | explicitly supplied | Same explicit rules; does not create team/group registrations |

For registration-required events, omitted/zero `participants_needed` means unlimited; a positive value is the cap. Empty `registration_link` uses internal registration. A nonempty URL means external registration: the internal registration POST returns 422, so direct API calls cannot create a second internal registration. No-registration events also reject internal registrations.

`attendance_type_id` remains an independent choice, not derived from payment or participation. Existing values (Subscription, Fixed Date Entry, Flexible One-Day Entry, Open, Ticketed) do not automatically create a schedule or ticket/payment integration. Internal registration accepts an optional active `time_slot_id` from that event. This round does not add automatic slot selection or subscription/group billing. Paid internal registration retains the existing `payment_status=pending` behavior; it does not charge a card.

For compatibility, records/clients with no `participation_type_id` retain the previous explicit-flag behavior. New frontend forms should always send a named participation choice. No blanket update is made to legacy records.

**Proof:** `test_participation_matrix_and_required_learning_choices` verifies each of the three named fee choices returns the derived flags, and contradictions return 422. `test_event_choice_updates_and_external_registration_cannot_drift` verifies paid→free resets, 422 for free/external internal registration, and explicit flags required for Individual/Team.

**Frontend must:** Send `participation_type_id`; collect a positive fee for Paid Event, optional capacity for registration-required events, and optional external URL. Render internal/external/no-registration actions from the returned flags/link. Either hide Individual/Team from this payment picker or expose both explicit flags for them. Do not infer attendance mode from participation type.

## BE-17 — Learn & Serve choices

**Done:** `learning_type_id` and `format_id` are required on create and cannot be explicitly cleared on update. IDs must come from their respective choice vocabularies. Course/Internship require `certificate_type_id`, matching the existing certificate issuance rule; other learning types may omit it. Certificate IDs must belong to `learn_serve_certificate_type`. Raw IDs now accompany display fields, including website resources, and `requires_check_in` remains authoritative. Unknown legacy types continue to require check-in.

**Proof:** A create without learning/format choices returns 422 containing both field errors. A Course create without certificate type returns 422. Republish tests create Learn & Serve records with all three valid IDs successfully. Existing no-check-in behavior remains covered by the feature suite.

**Frontend must:** Send `learning_type_id`, `format_id`, and a certificate choice for Course/Internship; use `requires_check_in` rather than interpreting a null format as Online. Repair policy: export rows with missing choices, have the owning organization confirm real values, then PATCH those exact IDs. Do not bulk-fill guesses. The owner-confirmed repair of production opportunity 30 is still pending.

## BE-18 — My events and unknown filters

**Done:** Preserved the existing `myevents` implementation and rejection of unknown `filter_type` values. It is the authenticated user's active event registrations; attended events are included through their registration, not through somebody else's organizer/sponsor relationship.

**Proof:** `ListAllOpportunitiesFilterTypeTest`: `GET /list-all-opportunities/?filter_type=myevents` includes the fixture's registered event and excludes a different event. An unsupported value returns 422 with a `filter_type` validation error. The mixed-table ID assertion in another test was corrected: event ID 1 and volunteer-opportunity ID 1 are different entities.

**Frontend must:** Use `filter_type=myevents` when listing the volunteer's registered events. Treat IDs together with `opportunity_type` when merging multiple resource types.

## BE-21 — Certificate registration type

**Done:** Already implemented; preserved `registration_type: volunteer|learn_serve` in `/user-certificates/`.

**Proof:** `BackendIssuesRoundTwoTest::test_be21_user_certificates_rows_carry_registration_type` verifies both certificate types in one user's response, and its companion test demonstrates colliding IDs across the two tables. Existing certificate tests exercise the volunteer download path with `registration_type=volunteer`.

**Frontend must:** nothing; continue passing the row's `registration_type` to `/download-certificate/?registration_id=...&registration_type=...`.

## BE-33 — Community search

**Done:** Already implemented in the working tree; preserved search across author identity and post text. No new `q` parameter. The separate `name` parameter remains an additional author filter.

**Proof:** `CommunitySearchWideningTest` covers name/nickname matches, text matches, and combined filters through `GET /posts/?search=...`.

**Frontend must:** nothing for the search box; continue sending `search`. Use `name` only when the separate author filter is intentionally applied.

## BE-14 — Certificate corrections and production backfill

**Done:** An actual `total_hours` change refreshes an already-issued volunteer certificate immediately, without sending another issuance notification/email. The certificate is invalidated before rendering so a render failure can be retried by the existing issuance/backfill path. Repeating the same hours does not regenerate it or change totals. No model/schema migration was needed. Existing eligibility is completed opportunity plus attended rows; the registration is the idempotency identity and `is_certified` is the issuance guard. `filter_type=attended` and the manual certificate send endpoint remain unchanged.

**Proof:** `test_correcting_hours_reissues_certificate_without_double_counting`: issue at 1 hour, `PATCH /volunteer-attendance/{id}/hours/` with `{"total_hours":24}` returns 200; rendered certificate data/bytes show 24, profile hours equal 24, and certificate count stays 1. Repeat PATCH leaves bytes/totals unchanged; the next eligible issuance pass returns 0. `VolunteerOpportunityCertificatesTest` covers completion, manual issuance, downloads and local backfill.

Production registration 996, its reported 47-hour aggregate, and its live download have **not** been verified. The command has **not** been run in production from this session.

**Frontend must:** nothing. Deployment operator must run `php artisan fursa:backfill-missing-volunteer-certificates` in production and verify registration 996, its download, and the aggregate. That command may send issuance notifications/email, as before.

## BE-20 — Event type production audit

**Done:** Required event type and display serialization already existed; preserved. No inferred corrections were applied to historical rows.

**Proof:** Event creation in the regression suite supplies `event_type_id` and succeeds. No live SQL output or response from event 19 was obtained; the claim that all event rows are null is not verified.

**Frontend must:** nothing in rendering. The deployment/data owner still needs `SELECT id, event_type_id FROM events ORDER BY id DESC LIMIT 20;`, then owner-confirmed repair of genuinely missing values, including event 19 if affected.

## Deployment and verification

No database migration or new dependency is required. Deploy the code and clear/rebuild Laravel's cached configuration/routes using the project's normal deployment procedure. Ensure the existing public storage URL/link serves generated exports and copied media. The production backfills and owner-confirmed data repairs above remain outstanding; this reply is not a claim of production completion.

Validation: `php artisan test --compact` (final result recorded after the suite completes). Additional tests are in `tests/Feature/BackendMissingItemsTest.php`; the earlier round's relevant tests are retained. BE-19's PDF work and BE-22's already-resolved frontend write naming were not rebuilt. Volunteer updates now accept POST, PUT and PATCH, preserving the old multipart POST path.
