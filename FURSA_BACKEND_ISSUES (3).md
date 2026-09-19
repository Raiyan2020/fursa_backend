# Fursa — backend issues

Findings from the end-to-end integration audit of `fursa-next` against `fursa_backend`.

**Re-verified 2026-09-17 against branch `updates` (`e31f0fa` → `9310cde` → `0252545`),** after the
backend's completion report. **BE-57 … BE-62 are all genuinely done** — every claim in that report
was checked against the code rather than taken on trust, and each one held up. Details are in the
banner under each issue's heading.

**One regression came in with that work: [BE-64](#be-64--the-new-post-deadline-guard-kills-repost-on-all-three-types-which-is-the-one-thing-repost-is-for).**
A post-deadline guard meant for *reopen registration* was also applied to *republish*, which is a
different operation — it creates a new opportunity from a finished one. Repost is now refused on
volunteering, learn & serve and events alike, in exactly the situation it exists for. It is the only
HIGH item in this file.

**Open: BE-63, BE-64, BE-65, BE-66, BE-67.** BE-57 … BE-62 are closed — each one verified by reading the code, not by taking the completion report's word for it.

**BE-61** is a **client-requested feature** and by far the largest: QR codes the participant scans
themselves, which the current data model cannot express at all — the existing QR belongs to the
volunteer and is scanned by the organizer, the exact opposite. **The client answered our open
questions on 2026-09-17 and the scope grew**: volunteering gets two permanent printed IN/OUT codes,
learn-&-serve gets a single code issued on the last day and valid two hours, and the whole
organizer-scans-volunteer flow is retired. **This is now built and verified** — the frontend is
unblocked and can start integrating against the new routes and the `self_attendance` field.

**BE-62** comes out of the same round: the client renamed «الاستشارة» to «المساحة». That sounds
cosmetic, but the value is matched as a string literal in five places across both codebases, so the
obvious implementation breaks consultation booking silently.

**BE-59** is a **bug the client reported**: publishing an opportunity notifies nobody in the
dashboard, so approval requests can sit unseen. **BE-58** and **BE-60** both come from new frontend
work rather than from an audit — the volunteer opportunity form now offers non-consecutive days and
a pasted maps link, and in each case the write side already works while the read side gets in the
way. **BE-57** is the same failure BE-56 described, one column over, on a population the BE-56 guard
does not cover.

### Where to start

**BE-64.** It is a live regression on a feature organizers use, it arrived with the last release, and
the fix is to narrow a guard rather than build anything. Nothing else in this file is blocked on it.

**Then BE-63**, whose first half (the missing learn-&-serve `reopen-registration` route) is a
twenty-line twin of code that already exists. Read its status banner first: the PDF round already
added a guard on one of the four methods, with a cutoff that disagrees with ours, so question 1 in
that issue needs answering before the other three are written.

IDs continue the existing sequence in `fursa-next/docs/BACKEND_ISSUES.md`, which ends at BE-34.

| ID | Severity | Status | Title |
|---|---|---|---|
| [BE-67](#be-67--five-gaps-on-the-volunteers-list-volunteerlist-four-of-them-reported-by-the-client) | **HIGH** | **Open — client-reported; one is a regression vs the live React site** | Five gaps on the volunteers list (`/volunteerlist`), four of them reported by the client |
| [BE-64](#be-64--the-new-post-deadline-guard-kills-repost-on-all-three-types-which-is-the-one-thing-repost-is-for) | **HIGH** | **Open — regression introduced by `9310cde`** | The new post-deadline guard kills Repost on all three types, which is the one thing Repost is for |
| [BE-61](#be-61--self-check-in-qr-two-printable-codes-for-volunteering-one-expiring-code-for-learn-and-serve-and-retiring-the-organizer-scans-volunteer-flow) | **HIGH** | **✅ Closed — verified 2026-09-17** (one sub-item still gated on the client) | Self check-in QR: two printable codes for volunteering, one expiring code for learn and serve, and retiring the organizer-scans-volunteer flow |
| [BE-62](#be-62--rename-the-consultation-learning-type-to-مساحة-and-please-give-master_choices-a-stable-key-while-you-are-in-there) | **MEDIUM** | **✅ Closed — verified 2026-09-17** | Rename the `Consultation` learning type to «مساحة», and give `master_choices` a stable key |
| [BE-59](#be-59--publishing-an-opportunity-notifies-nobody-in-the-dashboard-and-the-notification-system-cannot-target-an-admin-at-all) | **MEDIUM** | **✅ Closed — verified 2026-09-17** | Publishing an opportunity notifies nobody in the dashboard, and the notification system cannot target an admin at all |
| [BE-58](#be-58--websitevolunteeropportunityresource-cannot-express-a-non-consecutive-schedule-so-every-card-overstates-it) | **MEDIUM** | **✅ Closed — verified 2026-09-17** | `WebsiteVolunteerOpportunityResource` cannot express a non-consecutive schedule, so every card overstates it |
| [BE-63](#be-63--learn--serve-can-be-closed-but-never-reopened-and-neither-model-enforces-the-reopen-window) | **MEDIUM** | **Open — not addressed; partly collides with the PDF round** | Learn & serve can be closed but never reopened, and neither model enforces the reopen window |
| [BE-65](#be-65--a-development-opportunity-has-nowhere-to-put-a-whatsapp-contact-because-link-is-already-its-meeting-url) | **MEDIUM** | **Open — client-requested; volunteering shipped, development blocked** | A development opportunity has nowhere to put a WhatsApp contact, because `link` is already its meeting URL |
| [BE-66](#be-66--due_date-is-required-again-on-a-volunteer-opportunity-and-the-api-still-accepts-it-empty) | **MEDIUM** | **Open — reverted product decision; frontend already enforces it** | `due_date` is required again on a volunteer opportunity, and the API still accepts it empty |
| [BE-60](#be-60--location_url-falls-back-to-the-whatsapp-link-so-an-empty-location-is-indistinguishable-from-a-contact-number) | **LOW** | **✅ Closed — verified 2026-09-17** | `location_url` falls back to the WhatsApp link, so an empty location is indistinguishable from a contact number |
| [BE-57](#be-57--the-be-56-guard-misses-a-legacy-row-that-has-a-nationality-but-no-residency_status) | **LOW** | **✅ Closed — verified 2026-09-17** (102 rows affected) | The BE-56 guard misses a legacy row that has a `nationality` but no `residency_status` |

---

## Closed in `updates` (`576918c`)

- **BE-56 — `/account/` demanded an identity document on saves that never touch one.** *Fixed, and
  wired.* `IdentityDocumentValidator::isApplicable()` returns false when the request carries none of
  `nationality` / `residency_status` / `civil_id` / `passport_number` **and** the account has none
  stored, so a phone-only save on a legacy social signup no longer 422s. It is called at both
  surfaces — `AuthController::updateAccount():368` and
  `VolunteerProfileUpdateRequest::prepareForValidation():92` — and, critically, **at both it is read
  before the fallback merges run**, which is the one ordering that makes the guard work: after the
  merges every request would look like it had "touched" identity. Both call sites carry a comment
  saying exactly that, and the request object holds the result in a declared `protected bool
  $identityCheckApplicable` rather than a dynamic property. Four tests in `AuthApiTest` cover both
  directions on both endpoints — the unrelated save succeeds, and a save that *does* send identity
  fields still 422s on the missing `civil_id`.

  Checked against the three rows of the table this issue was filed with: a Kuwaiti with a stored
  `civil_id` and a non-resident with a stored `passport_number` both still validate through the
  fallback merges, and the third row — `nationality` and `civil_id` both null — now skips the check
  and saves. The repair path is intact too: the moment a client *does* send an identifier, the check
  runs and the row gets filled in.

  **Frontend:** the workaround in `VolunteerAccountInformation.tsx` is kept deliberately, not
  forgotten — it is what makes BE-57 below unreachable from the website, and it repairs legacy rows
  as a side effect. It costs nothing now that the backend no longer needs it.

### Licence exemption for `Governmental` — answered

The product decision came back: **a governmental organization is not asked for a commercial licence
at sign-up.** Admin approval verifies it from an official governmental document or authorization
letter instead. `fursa-next/src/data/orgTypes.ts` now lists `Governmental` and its predecessor
`Institution` in `LICENSE_EXEMPT_ORG_TYPES` alongside `Association` / `Community` / `Society` /
`Public`. This closes the only open question in the file.

### Migration status — answered, and worth recording

Both migrations are confirmed run, including on production. Worth keeping in the record:
`2026_09_15_000002_add_certificate_filter_choices` **had not actually run** when the team checked
this round, even though its code had been in place since the previous one. That is precisely the
silent failure this file flagged — the certificate chips were falling back to their hardcoded three
with no error anywhere to notice. It is fixed now; the lesson is that "the code is merged" and "the
vocabulary is live" are separate facts for anything served out of `master_choices`.

---

## Closed in `updates` (`38068bd`)

Each item was **verified by reading the branch** — the migration, the rule, the call site — not
taken from the handoff. Execution evidence is still the backend team's own suite run; see
*Verification that could not be performed here*.

- **BE-44 — Admin form and API validated the same columns differently.** *Fixed, and the structural
  recommendation was actually taken.* `app/Support/Opportunity/OpportunityValidationRules::core()`
  is the shared rule set both writers compose from, with `partial` / `participantsRequired` /
  `secondaryContentRequired` switches for the genuinely surface-specific differences. Every row of
  the drift table is closed: the API now validates `opportunity_nationality`
  (`Rule::in(Nationality::values())`), `is_calendar` (`boolean`) and `after_images[]` (`image`,
  `max:10240`, stored via the new `storeImageArrayFromRequest()`), and all three are in the
  `rejectUnknownWriteKeys` allowlist; the dashboard gained a `time_slots` repeater
  (`form.blade.php:130-161`) validated at `Admin/VolunteerOpportunityController.php:456-459` and
  applied through the new `VolunteerOpportunitySchedule` helper; and `Admin/EventController.php:301`
  calls `OpportunityValidationRules::core(participantsRequired: false, secondaryContentRequired:
  false)`, so `title_ar` / `description_*` are `nullable` on the dashboard exactly as they are on the
  API. A record either surface accepts can now be saved unchanged by the other.
- **BE-50 — The three-way nationality rule existed only on `/register/`.** *Fixed, all four
  surfaces.* `app/Support/Auth/IdentityDocumentValidator::validate()` is the one implementation, and
  it is called from `RegisterRequest:159`, `VolunteerProfileUpdateRequest:65`,
  `AuthController::updateAccount():431` and `AuthController::socialAuth():520`. Social signup now
  validates `nationality` / `residency_status` / `passport_number` and can complete the non-resident
  path, which it previously could not express at all. `Nationality::personValues()` excludes `all`,
  so the audience-only value is rejected on user writes. Both update surfaces fall back to the
  stored value for a field the client omitted, so an unrelated save does not re-ask for an
  identifier — with one gap, now **BE-56** below.
- **BE-51 — Second `org_type` restructure.** *Fixed.* Migration
  `2026_09_15_000001_restructure_organization_types_round_two` renames in place —
  `Institution → Governmental حكومي`, `Commercial → تجاري`, `Education → Educational تعليمي`,
  `NGO → NonProfit غير ربحي`, `Society → Association جمعية` — and revives the soft-deleted
  `Community مجتمع` row rather than inserting a duplicate. **The open question is answered in the
  migration itself:** renaming `Society` in place means every existing society becomes `Association`
  by construction, and because ids never change, `organizer_type_id` and `sponsors.org_type_id`
  both stay valid with nothing to repoint. `Volunteer Team` stays an internal choice, omitted from
  the public dropdown — which is exactly what `EntitiesRegistrationForm` already filters on.
- **BE-52 — Public profile lists served every volunteer's real name.** *Fixed.*
  `WebsiteProfileListItemResource` no longer emits `user_details.first_name` / `last_name` on the
  volunteer branch, and `Auth/PublicProfileResource` plus `Auth/OrganizationPublicProfileResource`
  are deleted outright. Per the handoff, both `search` and the legacy `name` parameter now match
  nickname only for volunteer queries — **which answers the open question** (the filter stays, but
  it searches the field that is actually returned). Organization and volunteer-team name behaviour
  is unchanged.
- **BE-53 — Certificates filter needed a param and a choice type.** *Fixed, both parts.* Migration
  `2026_09_15_000002_add_certificate_filter_choices` creates the `certificate_filter_type` choice
  type with exactly `Volunteer تطوع`, `Course دورة`, `Internship تدريب` — the three values the
  frontend already falls back to. `userCertificates()` validates `certificate_type` against those
  rows case-insensitively, 422s an unknown value under
  `response_status.validation_errors.certificate_type` (the BE-18 convention), and filters by
  joining `opportunity.learningType`; `Volunteer` correctly excludes the learn-serve branch and
  keeps the volunteer branch. No new endpoint was added, as asked.
- **BE-54 — `profile_activity_tag` filter and the development counter.** *Fixed, both parts.*
  `applyProfileActivityTagFilter()` is wired into **both** listings —
  `listAllOpportunities():456` and `listUserOpportunities():563` — scoping the learn-serve query
  only, matching `strtolower(trim(...))` so the frontend's capitalised `Participant` / `Provider`
  works, and 422ing anything else. **This closes the casing question:** rows keep emitting lowercase,
  the param accepts either. `Participant` filters on an attended registration (not merely a
  registered one) and `Provider` on `created_by`, matching the per-row tag. **The counter question
  is answered as combined:** `ResolvesApiPayloads::developmentActivityCount()` returns attended +
  provided, and `SyncService::syncVolunteer()` now writes `opportunities_organized` (approved,
  not-deleted learn-serve created by the user) and adds attended development opportunities into
  `total_opportunities`, which nothing wrote before.
- **BE-55 — Organization counters always answered `0`.** *Fixed.* The four hardcoded zeros in
  `OrganizationProfileResource` are gone; `OrganizationProfile` now derives `organization_hours`,
  `vol_opportunity_organized`, `learn_opportunity_organized` and `sponsored_count` from the
  per-year `organization_statistics` rollup rows (`month IS NULL`), summed in one cached query
  shared by all four accessors. `WebsitePublicProfileResource:98-105` reads the same accessors, so
  the public profile and the org's own profile can no longer disagree. The existing
  `profile.sponsored ?? profile.sponsored_count` fallback in the frontend stays correct.

---

## Closed in `updates` (`0fc0a37`)

Each item below was **verified by reading the branch**, not taken from the report. Not exercised
against a running instance — this workspace has no PHP runtime (see *Verification that could not be
performed here*), so the backend team's own test run is the only execution evidence.

- **BE-36 — Organization approval unchecked on most org writes.** *Fixed.* `api.approved-org` is
  now on **30** routes (was 5), including every one the issue named — `volunteer-opportunities/{id}/`
  update and destroy, `certificates/send/`, `certificates/{registration_id}/issue/`, both
  `registrations/status/`, `update-attendance/`, and `events/{id}/`. Gap 2 is closed too:
  `Admin/OrganizationProfileController::update()` now calls `$entity->user?->revokeAllTokens()`
  whenever the saved status is not `APPROVED`.
- **BE-37 — Rich-text HTML stored unsanitized.** *Fixed.* `HtmlSanitizer::cleanFields()` is now
  actually called — `Api/Event/EventController.php:405`,
  `Api/Opportunity/VolunteerOpportunityController.php:648`,
  `Api/Opportunity/LearnServeOpportunityController.php:401`, plus `Admin/FaqController` and
  `Admin/PageController`. A `fursa:backfill-sanitize-rich-text` command covers existing rows. This
  is the issue that survived a passing unit test because nothing called the class; it is now wired
  at every write path the issue listed.
- **BE-41 — Events expose a registration/attendance cycle.** *Fixed (Stage 1).* `is_attended` is
  gone from the event registration validator, filter, sort, export and resource; the `attended`
  relationship tag is gone from both event resources; `app/Models/EventAttendance.php` is deleted
  and `2026_09_14_000001_drop_event_attendance_cycle` drops the table. **Stage 2 answered:** event
  registration and time-slot endpoints are deliberately kept as a compatibility surface, while
  attendance is out of the contract.
- **BE-42 — Empty `existing_image_ids` unrepresentable.** *Fixed.* `MediaKeepSet::normalizeEmptySignal()`
  maps a scalar `""` / `"none"` to `[]` before validation, so a client can say "keep none" over
  multipart. Republish can now copy the licence without cloning the gallery.
- **BE-43 — Admin wrote only the legacy interest pivot.** *Fixed.* All three admin controllers call
  the shared `syncOpportunityInterests()` with the right choice type and load `masterInterests`, so
  an admin tag edit is no longer discarded by `effectiveInterests()`.
- **BE-45 — Private opportunities unjoinable and leaking.** *Fixed, all four parts.* The `is_public`
  clause is out of `RegistrationEligibility::reject()` (privacy governs discovery, not eligibility);
  `applyCombinedFilters()` applies `->where('is_public', true)` unconditionally except for
  `filter_type=organized`, and the unscoped path now also excludes `PENDING`; `registration_link`
  returns a real frontend detail URL instead of a null UUID; and the private→public flip on
  completion is removed from `AdvanceOpportunityStatusesCommand`.
- **BE-46 — Certificate endpoints unauthenticated.** *Fixed.* All three routes moved inside the
  `auth:api` group, and `userCertificates()` now reads `$request->user()->id` and **ignores the
  `user_id` param entirely**, so enumeration is gone. Note for the frontend: that param is now
  inert — the endpoint only ever returns the caller's own certificates.
- **BE-47 — Certificates not gated by learning type.** *Fixed, all four parts.* `CertificateController`
  gates both `show()` and `store()` on `learningTypeGrantsCertificate()` (course/internship only,
  422 otherwise); `NO_CHECK_IN_TYPES` now matches `class/workshop` by substring and `SyncService`
  reuses the same constant instead of its own list; a certificate type is rejected for the two types
  that grant none and cleared on a partial update that moves off them; and `ChoiceTypeSeeder` is
  reconciled to the four approved learning types with the lowercase duplicates removed.
- **BE-48 — "My calendar" save/unsave unbuildable.** *Fixed, all six parts.* `calendar_id` is
  returned next to `is_saved_to_calendar` on all three resources; the shared `calendarEntryId()`
  filters `notDeleted()`, so a removed item stops reporting as saved; events carry both fields;
  `store()` uses `firstOrCreate()` and revives a soft-deleted row instead of stacking duplicates;
  the listing deduplicates. **`is_calendar` decision recorded:** kept as a deprecated compatibility
  field that gates nothing.
- **BE-49 — Calendar listing collapsed types and kept deleted items.** *Fixed, all three parts.*
  Rows carry `type_en` / `type_ar` from the real learning/event choice; the eager loads are
  constrained with `notDeleted()` so a deleted source drops out; and search/date filtering moved
  into the query.

## Closed in `bfddf37`

- **BE-35 — `is_banned` never enforced.** *Fixed.* The `expiring-token` guard resolver returns
  `null` for a banned, inactive or deleted owner, so existing tokens stop authenticating
  immediately. `login()` returns a `403` before issuing a token. `User::revokeAllTokens()` is called
  from `Admin/UserController::ban()`, `Admin/OrganizationProfileController::reject()` and
  `CheckAndBanNonAttendingCommand`.
- **BE-38 — OTPs written to the log in plaintext.** *Fixed.* The `'otp' => $otp->otp` key is gone
  from both `Log::info` calls in `app/Services/Auth/AuthService.php`; `user_id` and `otp_id` remain.
- **BE-39 — No per-account attempt limit on OTP/login.** *Fixed.* Named limiters `auth-attempts`
  (5/min per IP **and** per submitted email) and `auth-send` (3/min per IP, 10/hour per email), plus
  an `attempts` column on `otp_verifications` that burns the code at `MAX_ATTEMPTS = 5`.
- **BE-40 — `EXPOSE_OTP_IN_RESPONSE` could leak OTPs in production.** *Fixed.* `config/fursa.php`
  short-circuits to `false` when `APP_ENV === 'production'`, before the env var is read. *Still
  outstanding on your side:* confirm the deployed production `.env`.

---

## BE-61 — Self check-in QR: two printable codes for volunteering, one expiring code for learn and serve, and retiring the organizer-scans-volunteer flow

> **✅ Closed — verified in code 2026-09-17** against `e31f0fa` / `9310cde` / `0252545`.
> Part A (two printed IN/OUT codes, `self-scan`, real elapsed hours, `self_attendance` on the resource) and Part B (one code, last day only, 2h expiry, internship refused) are implemented and match this issue. All five Part-A scan guards are present in `VolunteerAttendanceController::selfScan()`. Part C is code-complete behind `fursa.organizer_scan_flow_enabled` (default `true`), as agreed. **One sub-item is still open and correctly so:** removing automatic attendance from `LearnServeOpportunity::NO_CHECK_IN_TYPES` is untouched — it was gated on client sign-off that has not arrived. The completion report calls Part B "fully implemented" without mentioning it; that is a reporting gap, not a code gap.

**Severity: HIGH — a client-requested feature, and one the current data model cannot express at all.
Backend-first: no part of the frontend can be built until the endpoints exist, so nothing has been
shipped on our side.**

> **Updated 2026-09-17 — the client answered, and the scope grew.** Both proposed decisions are
> approved, the three questions we asked are settled, and a follow-up clarified that «المساحات» is
> their new name for «الاستشارة» (→ **BE-62**). The feature now covers **learn-&-serve as well**,
> with a *different* code model, and the old organizer-scans-volunteer flow is **removed entirely**.
> All of that is written into the requirements below. Two questions to the client remain open; they
> are listed under [Answers we still need](#answers-we-still-need) and they gate **one step of Part
> B only** — see the readiness table immediately below.

### Readiness — what you can start today

| | Start now? | What gates it |
|---|---|---|
| **Part A** — volunteering, two printed codes | **Yes, all of it.** | Nothing. Every decision is made. |
| **Part B** — learn-&-serve, one expiring code | **Yes, almost all of it.** Build the code, the expiry, the scan endpoint and the resource flag. | **One step only:** removing the automatic attendance from `NO_CHECK_IN_TYPES` changes live organizer statistics, and the client has not signed that off yet. Build it last, behind the rest. |
| **Part C** — retire the old flow | **Yes to write, not yet to ship.** | A release date agreed with us, so the endpoints and our screens go together. |
| **BE-62** — the rename | Arabic label: yes, today. English label: your call — **tell us before it ships**. | |

**The four questions under "From you (backend)" are ours to receive, not yours to wait on** —
answer them in your completion report. Nothing in this issue is blocked on us.

### What the client asked for

> التحضير الذاتي QR: لديّو QR إن وأوت، يطبعهم الناشر، المتطوع يسوي سكان دخول وخروج يحسبله الساعات
> الفعلية

In flow terms, on `/volunteer-event-detail/{id}`:

1. The organizer creates the opportunity, then generates **two QR codes — arrival (IN) and
   departure (OUT)** — from a button on the opportunity detail page, and **prints them**.
2. A volunteer **registered for that opportunity** sees a scanner button on the same page, opens
   their camera, and scans the **IN** code on arrival → marked as arrived.
3. Having scanned IN, the volunteer can then scan the **OUT** code on leaving → marked as departed.
4. The system computes the volunteer's **actual** hours from the two timestamps.

### The blocker: today's QR points the other way

This is the part worth reading carefully, because it is not an extension of the existing scanner —
it is its mirror image.

| | Today | Needed |
|---|---|---|
| Who owns the QR | the **volunteer** — `volunteer_profiles.qr_code`, identified by `uuid` | the **opportunity**, two codes |
| Who holds the camera | the **organizer**, or a delegate granted `ScanPermission` | the **volunteer** |
| Endpoint | `POST /volunteer-attendance/scan/` takes `volunteer_uuid` and is gated on `canManageAttendance()` | none exists |
| Result for a volunteer calling it | **403** | should be the normal path |

So the button the client is pointing at — labelled `التحضير الذاتي QR` in the UI, wired to
`COMMON.SCAN_PERMISSION` and `/scan-permission` — is not a QR generator at all. It is the screen
where an organizer **delegates scanning rights to other people**. The feature being asked for does
not exist in any form.

### What the schema cannot currently hold

`volunteer_opportunity_attendances` (created in `2024_01_01_000003_create_opportunity_tables`) is:

```php
$table->id();
$table->unsignedBigInteger('registration_id');
$table->date('attended_date');
$table->float('total_hours')->nullable();
$table->boolean('is_attended')->default(false);
// + is_deleted / deleted_at / timestamps, and:
$table->unique(['registration_id', 'attended_date'], 'vol_att_day_unique');
```

Three consequences:

1. **There is no check-in or check-out time.** Attendance is a boolean and a date. Both timestamps
   are new columns.
2. **`total_hours` is the *scheduled* duration, not the worked one.**
   `HandlesOpportunities::computeAttendanceHours()` returns the time-slot duration, or
   `start_time`→`end_time`. The client is explicitly asking for **الساعات الفعلية**, which is a
   different number and needs a different source.
3. **One row per volunteer per day** — that unique index is genuinely helpful here: IN and OUT
   belong on the same row as two columns, and one printed pair of codes works for a multi-day
   opportunity because the server stamps the date at scan time. Please keep the index.

### Decisions taken by the client — please build to these

All of the following are **settled**; they are not open for re-litigation on our side.

- **Static printed codes, with no geofence.** One permanent IN code and one permanent OUT code per
  volunteer opportunity. This matches *يطبعهم الناشر* exactly. We raised that a printed code can be
  photographed and scanned from elsewhere, and offered to bind the scan to the volunteer's GPS
  position; the client **declined the geofence and accepted the risk** rather than lose
  printability. **So the server-side guards below are the only thing standing between this and
  fabricated hours — please do not treat them as optional.**
- **A missing OUT credits zero hours.** If a volunteer scans IN and never scans OUT, attendance is
  recorded but `total_hours` stays `0`, and the organizer corrects it by hand through the existing
  `PATCH /volunteer-attendance/{attendance_id}/hours/`. Deliberately *not* falling back to the
  scheduled duration: the whole point of the feature is that the recorded number is the real one.
- **No per-opportunity opt-in.** The feature is on for every opportunity in scope, for a uniform
  experience. `qr_attendance_enabled` therefore stays a constant `true` for volunteering — but see
  Part B, where it has to become type-derived for learn-&-serve.
- **Manual attendance survives and runs alongside QR.** The client was explicit: *يبقى التحضير
  اليدوي متاح بجانب QR (الاثنين يعملان معًا)*. `POST /volunteer-attendance/manual/` and
  `PATCH /learn-serve-opportunities/{id}/update-attendance/` both stay.

---

### Part A — Volunteering: two printed codes (the original request)

**1. Schema.** Two nullable timestamps on `volunteer_opportunity_attendances` —
`checked_in_at`, `checked_out_at` — plus whatever column carries the opportunity's codes
(a single `attendance_code` on `volunteer_opportunities` with the direction supplied at scan time
is enough; two columns work equally well).

**2. Generate/fetch the codes** — organizer only, owner of the opportunity:

```http
GET /api/volunteer-opportunities/{id}/attendance-qr/
```

Returning both codes in a form we can render and print. Please say whether you return image URLs or
raw payload strings — we can render either, we just need to know which. Generated on demand and
stable across calls, so a printed sheet does not stop working.

**3. The volunteer scan endpoint** — the new one, authenticated as the volunteer:

```http
POST /api/volunteer-attendance/self-scan/
{ "code": "<scanned payload>", "direction": "in" | "out" }
```

Server-side guards, all of them necessary given the codes are static and printed:

- the caller is **registered** for that opportunity and the registration is not deleted;
- today is a day the opportunity actually runs — `isWithinPreparationWindow()` already answers this
  and correctly honours a scattered `time_slots` schedule;
- `direction: "out"` is **rejected unless a check-in exists** for that volunteer on that date;
- neither direction can be recorded twice on the same date;
- the code belongs to the opportunity the volunteer is registered for.

**4. Hours.** On a successful OUT, set `total_hours` to the real elapsed time
(`checked_out_at − checked_in_at`). On IN alone, `total_hours` stays `0` per the decision above.
Please also confirm that `SyncService::syncVolunteer()` picks the new value up, so the profile
counter and certificates reflect worked hours rather than scheduled ones.

**5. Expose the state on the detail payload** so the volunteer's button knows what to show —
something like `self_attendance: { checked_in_at, checked_out_at, next_action: "in"|"out"|"done" }`
on `VolunteerOpportunityResource`. Without it the frontend has to guess which of the two scans is
next, and would show the wrong button after a page refresh.

---

### Part B — Learn & serve: one code, issued on the last day, valid two hours

**This is new scope from the client's 2026-09-17 answer, and it is a different mechanism from
Part A — please do not try to serve both from one implementation.** Their words:

> الدورات والورش والمساحات: رمز واحد (مو رمزين دخول/خروج زي التطوع) يُصدر آخر يوم من الفرصة — سواء
> كانت الفرصة يوم واحد أو عدة أيام، وصالح لمدة ساعتين من لحظة الإصدار. متاح بالإضافة للتحضير
> اليدوي. […] التدريب العملي (الانترنشيب): يبقى يدوي فقط — بدون QR إطلاقًا.

| | Volunteering (Part A) | Learn & serve (Part B) |
|---|---|---|
| Number of codes | **two** — IN and OUT | **one** |
| Lifetime | permanent, printed once | **2 hours from issuance** |
| When issued | any time after creation | **on the opportunity's last day** |
| What a scan records | a timestamp, and real hours | `is_attended = true`, nothing more |
| Applies to | all volunteer opportunities | **Course**, **Class/Workshop**, **Consultation** — *not* Internship |

The good news is that the learn-&-serve side needs **no schema change for attendance**:
`learn_serve_opportunity_registrations` already carries a single `is_attended` boolean with a
`(opportunity_id, user_id)` unique index, and one scan is all it has to flip. Only the code itself
and its expiry need storing.

**1. Issue the code** — organizer only, and *mutating*, so POST rather than GET:

```http
POST /api/learn-serve-opportunities/{id}/attendance-qr/
→ { "code": "...", "expires_at": "2026-09-30T14:00:00Z" }
```

- rejected unless **today is `end_date`** (the client said *آخر يوم من الفرصة*, explicitly
  regardless of whether the opportunity runs one day or several);
- `expires_at = now + 2 hours`;
- rejected outright for `learning_type = Internship` — please match this on the stable key asked
  for in **BE-62**, not on the label, for the reason that ticket explains.

Please return `expires_at`; the organizer's screen shows a live countdown from it, and without the
field we would have to start our own timer and drift out of step with the server.

**2. The participant scan endpoint:**

```http
POST /api/learn-serve-attendance/self-scan/
{ "code": "<scanned payload>" }
```

Guards: the caller is registered and not deleted, the code has not expired, the code belongs to
that opportunity, and `is_attended` is not already `true`.

**One consequence worth stating so nobody invents a fix for it:** `is_attended` is a single boolean
on the registration, and the code exists only on the last day. So a participant who attended days
1–3 and missed day 4 is recorded as *not* attended, and one who showed up only on the last day is
recorded as attended. That follows directly from the client's design, and the manual attendance
screen is the correction path. **Please do not add a per-day attendance table for learn-&-serve to
work around it** — if the client later wants per-day accuracy here, that is a new ticket.

**3. `qr_attendance_enabled` must stop being a constant here.** In
`LearnServeOpportunityResource` it is hardcoded `true` for every row. With Internship excluded it
has to be derived from `learning_type` instead — that is the flag our scanner button reads, and
right now it would offer QR on an internship.

**4. Scope of Part B is now fully resolved.** The client confirmed on 2026-09-17 that
**«المساحات» is their new name for «الاستشارة»** — not a fifth type. So Part B covers **three** of
the four learning types: `Course`, `Class/Workshop` and `Consultation`. Only `Internship` is
excluded. The rename itself is filed separately as **BE-62**, and it is not cosmetic — read it
before touching the seeder.

---

### Part C — Retire the organizer-scans-volunteer flow entirely

The client's answer to "replace or keep both" was unambiguous:

> تُلغى طريقة "الجهة تمسح رمز المتطوع" بالكامل — لكن يبقى التحضير اليدوي متاح بجانب QR للتطوع
> (الاثنين يعملان معًا).

So the *delegated scanning* half goes, and the *manual* half stays. What that means concretely:

| Piece | Fate |
|---|---|
| `POST /volunteer-attendance/scan/` (takes `volunteer_uuid`) | **retire** |
| `ScanPermission` model, its table, and the permission list/bulk-update/download endpoints | **retire** |
| `volunteer_profiles.qr_code` / the volunteer's personal QR | **retire** (nothing else reads it — please confirm) |
| `POST /volunteer-attendance/manual/` | **keep** |
| `PATCH /volunteer-attendance/{id}/hours/`, `.../undo/`, `.../history/` | **keep** — Part A depends on `hours/` |

On our side that removes four screens: `/scan-permission`, `/scan-qr`, `/volunteer-qr-code` and
`/volunteer-scan-qr`, plus `ScanPermission.tsx` and `features/opportunities/services/attendance.ts`.

**Please tell us which release drops those endpoints, so our screens go in the same one.** If
yours go first, our four buttons start returning 404 in the user's face — that is the one outcome
to avoid. If ours go first nothing actually breaks, because manual attendance stays and organizers
keep a working path; your delegation endpoints just sit live with no UI. So **ours-first is the safe
fallback** if the two cannot be synchronised — but shipping them together is cleaner, and we only
need a date to do it.

---

### Already satisfied — please do not rebuild this

> التحضير اليدوي يُغلق بعد 3 أيام من انتهاء الفرصة (ينطبق على تحضير التطوع والتطور معًا).

**This already works, for both models.** `HasRegistrationWindow::naturalPreparationValidUntil()`
resolves to `end_date + 3 days, end of day` by default, and
`isWithinPreparationWindow()` / `isPreparationWindowClosed()` enforce it; both
`VolunteerOpportunity` and `LearnServeOpportunity` use the trait. The frontend already reads
`preparation_valid_until*` and `is_preparation_window_closed` through
`features/opportunities/checkInWindow.ts`, and shows the countdown.

One thing to check rather than build: the window is **admin-configurable**, and
`preparation_validity_hours` takes precedence over `preparation_validity_days` when it is non-zero.
Please confirm the production `Config` row is either empty (→ the 3-day default) or set to exactly
72 hours. If it has drifted to something else, the client's "3 days" is quietly not what is running.

### Republish already produces a new code — please confirm it stays that way

> في حالة إعادة النشر يتم إنشاء رمز QR جديد للحضور.

For opportunities, "republish" in this codebase **creates a new opportunity record** — the frontend
prefills the create form from the old one (`isRepublish` in `LearnServeForm.tsx` /
`VolunteerCard.tsx`) and POSTs a new row. So as long as the code is keyed to the opportunity, a
republished opportunity gets a fresh code for free, and the old printed sheet stops working because
it points at the old record. That satisfies the requirement.

Two caveats worth a line in your reply:

- **Do not derive the code from anything stable across records** (the organizer id, a slug, a hash
  of the title). Derive it from the opportunity id plus a random component, or store it.
- The only true in-place republish is `POST /event/republish/{id}`, and **events are out of scope**
  — the client confirmed the event attendance position from BE-41 stands unchanged.

---

### Answers we still need

**From you (backend):**

1. **Codes as image URLs or payload strings?** We can render either; we just need to know which,
   for both Part A and Part B.
2. **The shape of the volunteer-facing state field** (Part A point 5) — our button cannot pick
   between "scan IN" and "scan OUT" after a refresh without it.
3. **Which release retires the Part C endpoints**, so we can land the screen deletions with it.
4. **Does anything besides the scanner read `volunteer_profiles.qr_code`?** Certificates, exports,
   the admin dashboard — if so it outlives Part C.

**Answered by the client on 2026-09-17 — recorded so nobody re-asks:**

- **"المساحات" is not a fifth learning type.** It is the client's **new name for «الاستشارة»**.
  There are still exactly four: `Course` / دورة, `Class/Workshop` / درس/ورشة, `Consultation` /
  استشارة, `Internship` / تدريب عملي. The rename is **BE-62**.
- **Consultation is therefore in Part B's scope** and gets the single expiring code, like courses
  and workshops. Only `Internship` is excluded.

**Still from the client — we are chasing these, listed here so you are not blocked by surprise:**

1. **This reverses an earlier decision, and you should both know it.**
   `LearnServeOpportunity::NO_CHECK_IN_TYPES = ['workshop', 'consultation', 'class',
   'class/workshop']` means workshops, classes **and consultations** currently have **no attendance
   step at all** — `requiresCheckIn()` is `false`, `manual_attendance_enabled` is `false`, and
   `AdvanceOpportunityStatusesCommand` marks their registrations attended automatically on
   completion, with `SyncService` counting them that way. Note that this now covers **every type in
   Part B's scope except Course** — the constant will end up empty. Giving these types both QR *and*
   manual attendance means that auto-attend has to go, and **organizer statistics will drop** for
   anyone who does not actually take attendance. That is a visible change to live numbers, not just
   a new button.
2. **Can the 2-hour code be re-issued?** A three-hour workshop, or a latecomer, both outlive one
   window. We assume the organizer can press the button again for a fresh 2 hours, invalidating the
   previous code — please confirm, because "issued once, ever" needs a different guard.

### Frontend status

**Blocked, and nothing built.** Part A's organizer print screen and volunteer scanner, and Part B's
issue-with-countdown screen, all wait on the contract above. The Part C deletions are ready to write
but deliberately held until the retirement release is known.

---

## BE-62 — Rename the `Consultation` learning type to «مساحة», and please give `master_choices` a stable key while you are in there

> **✅ Closed — verified in code 2026-09-17** against `e31f0fa` / `9310cde` / `0252545`.
> `master_choices.slug` added, exposed on `/api/choices/{type}/`; `isInternship()` matches `slug === 'internship'` with a `value_en` fallback; Arabic label renamed, `value_en` untouched.

**Severity: MEDIUM — a one-word product rename that, done the obvious way, silently changes
behaviour in five places across both codebases. Small to do, easy to get wrong.**

**Ready to start:** yes. The Arabic rename can ship today and breaks nothing. The English rename is
your decision — just tell us the new value before it ships, so our four string matchers land in the
same release.

### What the client asked for

> المساحات هي الاستشارات — غيّر مسمى «استشارة» لـ «مساحة»

This also closes the "what is المساحات?" question from BE-61: it is not a fifth learning type, it is
a new name for the existing `Consultation` row. Consequently **consultations are in BE-61 Part B's
scope** and get the single expiring QR code.

### Where the value lives

`Consultation` / `استشارة` is seeded **twice**, in two different choice types:

| Choice type | Line | Used by |
|---|---|---|
| `learning_type` | `ChoiceTypeSeeder.php:26` | the opportunity's own type — `learn_serve_opportunities.learning_type_id` |
| `filter-type` | `ChoiceTypeSeeder.php:231` | the listing filter chips |

**Both need renaming**, or the filter chip and the opportunity label will disagree on screen.

Please **rename in place** rather than soft-deleting and inserting — exactly as
`2026_09_15_000001_restructure_organization_types_round_two` did for `org_type` (BE-51). Ids must
not change: `learning_type_id` is a live foreign key on every learn-&-serve opportunity, and an
insert-plus-delete would orphan all of them.

### The part that needs a decision: does `value_en` change too?

This is the whole risk in this ticket. **Nothing in either codebase matches on `value_ar`, and
almost everything matches on `value_en`** — a user-editable label. So:

**Option 1 — rename `value_ar` only (استشارة → مساحة), keep `value_en = Consultation`.**
Zero behavioural change anywhere. The Arabic UI shows the new name immediately. The English UI
keeps saying "Consultation", which may or may not matter to the client. **This is what we
recommend shipping first**, because it is reversible and breaks nothing.

**Option 2 — rename both (`Consultation` → `Space`).** Then the following all change behaviour, and
four of the five change it *silently*:

| Where | What happens |
|---|---|
| `LearnServeOpportunity::NO_CHECK_IN_TYPES` | contains `'consultation'`. A renamed row stops matching, so `requiresCheckIn()` flips `false → true` **by accident**. That happens to be what BE-61 wants — but as a side effect of an unrelated label edit, not as a decision. Please change that constant deliberately in BE-61 instead. |
| `LearnServeDetails.tsx` `isConsultationType()` | matches `"consultation"` / `"استشارة"`. Breaks the **time-slot picker**, so consultations would be registered with no slot at all. |
| `Calendar.tsx` `DETAIL_PATH_BY_TYPE` | keyed `"Consultation"`. The calendar entry loses its detail route. |
| `Calendar.tsx` `LEARN_SERVE_TYPES` | keyed `"Consultation"`. The entry stops being classified as learn-&-serve. |
| `LearnServeForm.tsx` `learningTypeOrder` | keyed `"Consultation"`. Cosmetic only — the dropdown loses its ordering. |

`CertificateController::learningTypeGrantsCertificate()` is **not** affected either way: it
allowlists `['course','internship']`, so a consultation grants no certificate under any name. Worth
confirming that stays true after the rename, since the client did not mention certificates.

**If you pick Option 2, tell us before it ships** — the four frontend matchers are a small change
on our side, but they have to land in the same release or consultations break in production.

### The underlying problem, which is worth twenty minutes

`master_choices` has only `id`, `choice_type_id`, `value_en`, `value_ar`. There is **no stable
key**, so every behavioural rule in both codebases is matched against a display label that an admin
can edit from the dashboard. This file already records two bugs caused by exactly that:

- **BE-47 part 2** — merging `Class` and `Workshop` into `Class/Workshop` silently stopped
  `NO_CHECK_IN_TYPES` from matching, which is why `requiresCheckIn()` now has a `str_contains` loop
  and a comment saying *"a label match against user-editable master data will keep drifting"*.
- **`isConsultationType()`** in `LearnServeDetails.tsx` carries a comment recording that it used to
  look for `"consultationss"` / `"استشارات"` — neither of which exists — so the slot picker never
  opened.

This ticket is the third instance. **Please add a `slug` (or `code`) column to `master_choices`** —
immutable, set once per row, exposed on `/api/choices/{type}/`. Then a rename is purely cosmetic,
matching stops depending on spelling, and neither side has to re-audit its string literals every
time the client renames something. We will switch our matchers to it as soon as it is in the
payload.

### What we are asking for

1. Rename `Consultation` → «مساحة» in **both** `learning_type` and `filter-type`, **in place**, via
   a migration that also updates the seeder so a fresh environment matches production.
2. Tell us whether `value_en` changed, and to what.
3. Add a stable `slug` to `master_choices` and expose it on `/api/choices/{type}/`.
4. Confirm the choice is **not** soft-deleted and re-inserted, so `learning_type_id` stays valid.

### Frontend status

**Nothing changed yet, deliberately.** Labels come from `/api/choices/learning_type/` and render
themselves, so Option 1 needs nothing from us at all. If you take Option 2, the four matchers above
are ready to update the moment you confirm the new English value — we are not changing them on a
guess, because a wrong guess breaks consultation booking silently rather than loudly.

---

## BE-59 — Publishing an opportunity notifies nobody in the dashboard, and the notification system cannot target an admin at all

> **✅ Closed — verified in code 2026-09-17** against `e31f0fa` / `9310cde` / `0252545`.
> `admin_notifications` + `notifyAdminsWithPermission()` verified wired at **all six** submission points — volunteer `store()`/`resubmit()`, event `store()`/`republish()`, learn-serve `store()`, and `AuthService::register()`.

**Severity: MEDIUM — reported by the client as a bug, not a feature request. Backend-only; there is
nothing the frontend can do here.**

**Ready to start:** yes. One product decision inside it is yours to make (who receives the
notification — see point 3); it changes the table design, so make it before you build, not after.

### What the client reported

An organization publishes an opportunity from the website. It lands as `PENDING` and waits for
admin approval — but **no notification reaches the dashboard**, so a request can sit unreviewed
indefinitely. Nobody is told it exists. The only way an admin finds out is by opening the
opportunities list and noticing a new row.

### What happens now

`VolunteerOpportunityController::store()` creates the record and returns. Nothing else fires:

```php
$opportunity = VolunteerOpportunity::create(array_merge($data, [
    'created_by' => $request->user()->id,
    'approval_status' => ApprovalStatus::PENDING,
    ...
]));
```

The same is true of `LearnServeOpportunityController::store()`, `EventController::store()`, and of
`resubmit()` — which moves a rejected opportunity **back to `PENDING`** (line 240) and is arguably
the more urgent case, since somebody is waiting on a second look.

### The blocker — this cannot be done with the existing notification system

`NotificationService::createForUsers()` is called from about twenty places, and every one targets a
volunteer or an organization. None targets an admin, and none **can**:

| | |
|---|---|
| `config/auth.php` | admins are their own guard and provider — `admin` → `admins` → `App\Models\Admin` |
| `user_notifications` | `$table->foreignId('user_id')->constrained()` → foreign key onto **`users.id`** |
| `UserNotification::user()` | `belongsTo(User::class)` |

So an admin id is not a valid `user_id`, and the database rejects the row outright. This is not a
missing call — the channel does not exist.

The dashboard's own "Notifications" screen (`Admin/NotificationController`, the bell in
`sidebar.blade.php:183`) is the opposite direction: it is a **composer** where an admin writes a
broadcast and picks which users receive it. There is no admin inbox for the system to write into.

### What we are asking for

**1. An admin-addressable notification channel.** Whichever of these fits your plans:

- an `admin_notifications` table mirroring `user_notifications` (`admin_id`, `notification_id`,
  `is_read`), reusing the existing `notifications` rows so the bilingual title/message shape stays
  the same; or
- Laravel's own `notifications` table with `MorphTo notifiable`, which handles both `User` and
  `Admin` without a second table — a bigger change, but it removes the split for good; or
- `NotificationService::createForAdmins(...)` alongside `createForUsers(...)`, if you prefer to keep
  the two paths visibly separate.

**2. A notification raised on every transition into `PENDING`**, not only on first create:

- `Api/Opportunity/VolunteerOpportunityController::store()` and `::resubmit()`
- `Api/Opportunity/LearnServeOpportunityController::store()` (and its resubmit path)
- `Api/Event/EventController::store()`
- and the organization-approval request path, if it has the same gap — worth checking while you are
  in there, since it is the same "something is waiting for review" shape.

Content should carry enough to act on without a lookup: the opportunity type, its title, the
organization that submitted it, and a link straight to the review screen.

**3. Who receives it.** Please decide and tell us: every admin, or only those holding the relevant
permission? There is already a `Admin/PermissionController` and a roles system, so scoping it to
the admins who can actually approve seems right — but that is your call, and it affects the shape of
part 1.

**4. A visible unread count in the dashboard.** A notification nobody sees is the same bug with
extra steps. A badge on the sidebar bell, or on the opportunities entry, is what turns this from a
record into an alert.

### Worth considering, not required

Real-time delivery (websockets/Pusher) would be nicer than polling, but is **not** what the client
asked for and we would not hold this up for it — a row written on submit and read on page load
already fixes the reported problem. Mentioning it only so the table design does not foreclose it.

### Frontend status

**Nothing to do on our side, and nothing we can do.** The website already submits correctly and the
record already lands as `PENDING`; everything missing is between the API write and the dashboard.
We do not need any new response field for this — please just confirm when it is live, and say if
you want the website to surface anything to the organization in return (for example "your request
is awaiting review", which we do not show today).

---

## BE-58 — `WebsiteVolunteerOpportunityResource` cannot express a non-consecutive schedule, so every card overstates it

> **✅ Closed — verified in code 2026-09-17** against `e31f0fa` / `9310cde` / `0252545`.
> `has_custom_schedule` and `time_slots` present on `WebsiteVolunteerOpportunityResource`, derived from the eager-loaded relation rather than `hasCustomSchedule()`, so the N+1 this issue warned about is avoided.

**Severity: MEDIUM — the write side is already done and correct; this is the read side of the same
feature. The frontend has shipped the form, so scattered-day opportunities can be created today and
are being rendered as unbroken ranges on every listing.**

**Ready to start:** yes. Pick either response shape (full array, or flag plus count) and tell us
which — both work for us, we only need to know what to read.

### Context — what already works

The client asked for a volunteer opportunity to be schedulable either as consecutive days (a range,
the default) or as separate days — a Saturday clinic on the 12th, 15th, 19th and 22nd, say. **The
backend already supports all of it**, and better than the issue that prompted this one expected:

- `time_slots` is validated and persisted by the API writer
  (`VolunteerOpportunityController::store()/update()` → `VolunteerOpportunitySchedule::sync()`);
- `VolunteerOpportunity::runsOnDate()` explicitly honours it — *"With a custom schedule only the
  scheduled days count, which is what makes non-consecutive days work"*;
- `update()` can clear a schedule as well as set one (`$data['time_slots'] ?? []`), so switching an
  opportunity back to a range is representable over multipart — the `existing_image_ids` problem
  from BE-42 does **not** repeat here;
- `VolunteerOpportunityResource` returns `time_slots` and `has_custom_schedule`.

So no write-side change is requested. The frontend now sends `time_slots[n][date]` for separate
days and an empty `time_slots` for a range.

### The gap

`GET /api/opportunities/{id}/details/` returns `VolunteerOpportunityResource`, which carries
`time_slots` — so the **detail page** can render the real days, and now does.

Every **listing** endpoint returns `WebsiteVolunteerOpportunityResource`, which carries only:

```php
'start_date' => $this->formatDate($opportunity->start_date),
'end_date'   => $this->formatDate($opportunity->end_date),
```

There is no `time_slots` and no `has_custom_schedule` anywhere in that resource. Since `start_date`
and `end_date` are merely the first and last scheduled day, an opportunity running on 4 separate
days inside a fortnight is indistinguishable from one running all 14 days straight.

Affected endpoints — all of them read by the website:

- `GET /api/list-volunteer-opportunities/`
- `GET /api/list-all-opportunities/`
- `GET /api/list-user-opportunities/`

### What it costs

| Surface | What it shows | What is true |
|---|---|---|
| Opportunity card | `12 Oct – 22 Oct` | 4 days: 12, 15, 19, 22 |
| Card duration / hours | 11 days × hours-per-day | 4 days × hours-per-day |
| Profile activity listing | an 11-day commitment | a 4-day commitment |

The hours figure is the one that matters: a volunteer deciding whether to sign up is shown a total
commitment that can be several times the real one. The detail page is now correct, so the card and
the page it opens will **disagree with each other** until this lands.

### Requested fix

Add both fields to `WebsiteVolunteerOpportunityResource`, matching the names
`VolunteerOpportunityResource` already uses so the frontend reads one shape everywhere:

```php
'has_custom_schedule' => $this->resource->hasCustomSchedule(),
'time_slots' => $this->whenLoaded('timeSlots', fn () => $this->timeSlots
    ->filter(fn ($slot) => ! $slot->is_deleted)
    ->sortBy('date')
    ->map(fn ($slot) => [
        'date' => optional($slot->date)->toDateString(),
        'start_time' => $slot->start_time,
        'end_time' => $slot->end_time,
    ])->values()),
```

Two notes on doing it safely:

1. **Eager-load `timeSlots` on the listing queries.** `hasCustomSchedule()` runs
   `$this->timeSlots()->notDeleted()->exists()` as its own query, so calling it per row on a
   paginated listing is an N+1 — 20 extra queries a page. The listing queries at
   `listAllOpportunities()` and `listUserOpportunities()` already eager-load `timeSlots` for the
   non-website path; the website path needs the same.
2. **A count alone would do**, if the full array is unwanted on a card. `scheduled_days_count`
   plus `has_custom_schedule` is enough for the card to print "4 days" and compute hours correctly;
   the exact dates are only needed on the detail page, which already has them. Either shape works —
   please just say which, so the frontend reads the right one.

### Frontend status

**Shipped and live without it.** `/volunteer-form` offers both modes now, and
`VolunteerEvent` (the detail page) reads `time_slots` to list the real days and to compute the hours
total from the number of days actually worked rather than the calendar span. The **cards cannot be
fixed from our side** — the data is not in the response.

---

## BE-60 — `location_url` falls back to the WhatsApp link, so an empty location is indistinguishable from a contact number

> **✅ Closed — verified in code 2026-09-17** against `e31f0fa` / `9310cde` / `0252545`.
> No `?:` fallback remains on `location_url` in any of the six resources — the three named here plus `WebsiteLearnServeOpportunityResource` and `WebsiteEventResource`.

**Severity: LOW — worked around in two places on our side, but the workaround is a guess and every
client has to repeat it.**

**Ready to start:** yes. It is one line, with nothing waiting on anybody.

### The line

`VolunteerOpportunityResource.php:89`:

```php
'location_url' => $this->location_url ?: $this->link,
```

`link` is the **WhatsApp contact link** for the opportunity. It has nothing to do with where the
opportunity takes place, so an opportunity with no location URL is served one anyway — somebody's
phone number, in a field named `location_url`.

### Why it now matters

Until this round the frontend always sent `location_url` as `""` and never read it back, so the
fallback was invisible. The volunteer form now offers two ways to answer "where": paste a maps link
(`location_url`), or pick the spot on the map (`latitude`/`longitude`). So the field is both written
and read, and the fallback actively misleads:

| | Without a workaround |
|---|---|
| Edit screen | loads the WhatsApp URL into the location field, and re-saves it as the location |
| Detail page | clicking the address opens a WhatsApp chat instead of a map |

We work around it by treating `location_url` as absent whenever it equals `link` —
`VolunteerForm` `initialValues` and `VolunteerEvent`'s `locationUrl`. That is a **heuristic, not a
rule**: an organiser who genuinely pastes the same URL into both fields defeats it, and every other
client (the mobile app included) has to know to do the same thing.

### Requested fix

Return the column, and only the column:

```php
'location_url' => $this->location_url,
```

If something downstream depends on the current behaviour — a mobile screen that uses
`location_url` as a general "open this" link, say — then please **keep the fallback out of the
resource and put it in that consumer**, or expose it under a second, honestly-named key. A field
called `location_url` should not be able to return a phone number.

Worth checking while you are there: `LearnServeOpportunityResource` and the event resources, in
case the same `?: $this->link` fallback was copied across. `WebsiteVolunteerOpportunityResource`
should get the same treatment as whatever is decided.

### Frontend status

**Working, with the heuristic above in place.** Once the field returns only the column we will
delete both workarounds — they exist solely to undo this line. No response shape change is needed
beyond dropping the fallback, so this will not break us whenever it lands.

---

## BE-57 — The BE-56 guard misses a legacy row that has a `nationality` but no `residency_status`

> **✅ Closed — verified in code 2026-09-17** against `e31f0fa` / `9310cde` / `0252545`.
> `IdentityDocumentValidator::isApplicable()` returns false for a stored `nationality = other` with `residency_status = NULL` unless the request carries an identity field. 102 production rows were affected, so this was worth fixing.

**Severity: LOW — not reachable from the website, reachable from any other client. This is not a
complaint about the BE-56 fix, which is correct for the case it was filed for; it is the same shape
of bug on a row the guard deliberately treats as "already answered".**

**Ready to start:** yes — but run the row count first (see *Verification that could not be performed
here*). If the count is zero, "won't fix" is a perfectly good answer and we would rather have the
number than the patch.

### The gap

`isApplicable()` decides the account has answered the identity question if *any* of three columns is
set:

```php
return $user->nationality !== null
    || (string) $user->civil_id !== ''
    || (string) $user->passport_number !== '';
```

A row with `nationality = 'other'` and `residency_status = NULL` satisfies the first clause, so the
check runs. The fallback merge then cannot fill `residency_status`, because it too is guarded on the
stored value being non-empty:

```php
if (! $request->has('residency_status') && $user->residency_status) {
    $request->merge(['residency_status' => $user->residency_status->value]);
}
```

`validate()` therefore sees a non-Kuwaiti with no residency status and stops at:

```php
if ($residencyStatus === null) {
    $validator->errors()->add('residency_status', __('validation.required', ...));
    return;
}
```

→ **422 `residency_status` — required, on a request that only sent a phone number.** Exactly the
BE-56 failure, one column over.

### Why the population is not small

`residency_status` was added by `2026_08_30_000001_add_residency_status_and_passport_number_to_users_table`
as `->nullable()` with no backfill. **Every user row created before 2026-08-30 has it NULL**, and any
of those whose `nationality` is `other` is in this state. Before BE-50, `/account/` validated
`nationality` and `residency_status` as independently `nullable`, so an account could also be put
into this state deliberately by the old two-option nationality form, which sent `nationality` alone.

### Why the website does not hit it

`VolunteerAccountInformation.tsx` always sends the current nationality/residency selection on an
`/account/` save that is already happening, and `nationalityResidencyValueFrom('other', null)`
resolves a stored non-Kuwaiti with no residency to `non_kuwaiti_resident` — which is what the old
two-option form effectively meant. So the request carries `residency_status: 'resident'` and the
check passes, repairing the row on the way through.

That is luck, not coverage. Any client that doesn't do this — the mobile app on the same
`/account/` endpoint, or a future screen that patches one field — gets the 422.

### Recommended fix

Make the guard consistent with what `validate()` actually needs, rather than with what is merely
non-null. The narrowest version:

```php
// A non-Kuwaiti row with no residency_status has not finished answering the
// question; treat it like the never-answered case rather than a complete one.
if ($user->nationality === Nationality::OTHER && $user->residency_status === null) {
    return false;   // unless the request itself carries an identity field
}
```

The alternative — defaulting a stored `other` + NULL to `resident`, matching what the old
two-option form meant — is also fine and additionally repairs the data. Either way the rule to hold
onto is the one BE-56 established: **a request that touches no identity field must not be able to
fail on one.**

### Frontend status

**No change needed.** The workaround already in place covers the website; it stays regardless.

---

## BE-63 — Learn & serve can be closed but never reopened, and neither model enforces the reopen window

> **⚠️ Not addressed — status re-checked 2026-09-17 against `e31f0fa` / `9310cde` / `0252545`.**
> This issue was written after the copy the backend worked from (`FURSA_BACKEND_ISSUES (4).md`,
> BE-57…BE-62), so nothing here was skipped deliberately. Where the PDF round happened to touch the
> same code, the result only half matches:
>
> - **Part 1 — learn & serve `reopen-registration` — still missing.** No route in `routes/api.php`,
>   no `reopenRegistration()` on `LearnServeOpportunityController`. Our frontend workaround through
>   the partial-update endpoint is live and working, so nothing is broken for users.
> - **Part 2 — the window — enforced on one method of the four.**
>   `VolunteerOpportunityController::reopenRegistration()` now refuses once
>   `registrationClosesAt()` has passed. **Volunteer close, learn-serve close and learn-serve reopen
>   still have no guard at all.**
> - **The cutoff is not the one this issue asked for, and the two now disagree in a way users will
>   hit.** We gate on `end_date − 1 day`; `registrationClosesAt()` prefers **`due_date`**, falling
>   back to `end_date`. For the ordinary case where registration closes well before the opportunity
>   ends — runs 1–30 Sep, `due_date` 5 Sep — our button stays visible until 29 Sep while the API has
>   refused since 5 Sep. **That is exactly the "visible and then 422" collision question 1 below
>   warned about.** Please answer question 1 before adding the guard to the other three methods, so
>   both sides land on the same date.

**Severity: MEDIUM — client-reported. Half of it is a missing route; half is a rule the API does not
enforce at all.**

**Ready to start:** yes. Nothing here is blocked and nothing depends on BE-61. Part 1 is a
twenty-line copy of code that already exists.

### What the client asked for

> «إغلاق الفرصة لا يمكن إعادة فتحها — المفروض: يحق للناشر فتح وإغلاق الفرصة لحد يوم قبل تاريخ الانتهاء»

Closing an opportunity is currently one-way. It should not be: the publisher may **open and close**
the opportunity as often as they like, **up until one day before its end date**.

### Part 1 — `reopen-registration` exists for volunteering only

`routes/api.php`:

```php
Route::post('volunteer-opportunities/{id}/close-registration/',  [VolunteerOpportunityController::class, 'closeRegistration']);
Route::post('volunteer-opportunities/{id}/reopen-registration/', [VolunteerOpportunityController::class, 'reopenRegistration']);
Route::post('learn-serve-opportunities/{id}/close-registration/',[LearnServeOpportunityController::class, 'closeRegistration']);
// ← no learn-serve reopen-registration
```

`LearnServeOpportunityController::closeRegistration()` is a line-for-line twin of the volunteer one,
so the missing half is `$opportunity->update(['is_registration_closed' => false])` in a
`reopenRegistration()` method plus its route. Please mirror the volunteer version exactly, including
the `OpportunityChangeNotifier::notify()` call around the snapshot.

**What the frontend does meanwhile.** It reopens a learn & serve opportunity through the ordinary
update endpoint — `POST /learn-serve-opportunities/{id}/` with `_method=PATCH` and a body of nothing
but `is_registration_closed=0`. That works today: `validatePayload($request, partial: true)` already
declares the key as `nullable|boolean`, `mapLocationAttributes()` only writes keys the payload
actually contains, and `opportunitySnapshot()` includes `is_registration_closed`, so the change
notification fires identically either way. It is a workaround, not a preference — we would rather
call a route named after what it does, and a partial update is a much wider blast radius than the
one column we want to touch. `reopenLearnServeOpportunityRegistration()` in
`fursa-next/src/features/opportunities/services/learnServe.ts` is one line to repoint once the route
exists.

### Part 2 — the window is not enforced anywhere

This is the part that needs a decision, not just code.

Neither `closeRegistration()` nor `reopenRegistration()` looks at a date. Both are:

```php
$opportunity = ...->where('created_by', $request->user()->id)->find($id);
if (! $opportunity) { return 404; }
$opportunity->update(['is_registration_closed' => true /* or false */]);
```

So today the API will happily reopen registration on an opportunity that **ended last month**, and
`HasRegistrationWindow::isRegistrationOpen()` will then report it open if `due_date`/`end_date`
parsing allows — because that method only reads `is_registration_closed` and
`registrationClosesAt()`, and the manual flag short-circuits nothing about the past.

**The frontend now hides both buttons after `end_date` minus one day**
(`canToggleRegistration()` in `fursa-next/src/features/shared/opportunityButtonState.ts`). That is
presentation only and stops nobody using the endpoint directly. Please add the same guard server
side, on **all four** methods (volunteer close, volunteer reopen, learn-serve close, learn-serve
reopen), returning a 422 rather than a silent no-op:

```php
// The publisher's window closes a day before the opportunity ends.
if ($opportunity->end_date && now()->startOfDay()->gt(
        Carbon::parse($opportunity->end_date)->subDay()->endOfDay())) {
    return ApiResponse::error(
        'Registration can no longer be changed for this opportunity.',
        'لم يعد بالإمكان تعديل التسجيل لهذه الفرصة.', 422);
}
```

Since all four methods need it, `HasRegistrationWindow` is the natural home — e.g.
`registrationToggleClosesAt(): ?Carbon` next to the existing `registrationClosesAt()`, so the
resource can expose it too.

### From you (backend) — three questions

1. **`end_date` or `due_date`?** We read the client's «تاريخ الانتهاء» as `end_date`, and deliberately
   not `due_date`: `due_date` is the registration deadline the publisher is *overriding* with this
   very control, so gating on it would remove the button exactly when it is wanted. Confirm you
   agree before you write the guard — if you gate on `due_date` and we gate on `end_date`, the
   button will be visible and 422.
2. **Is the last allowed day inclusive?** We implemented "the whole of the day before `end_date`" —
   an opportunity ending on the 30th is toggleable all through the 29th, and not on the 30th. The
   Arabic is ambiguous between that and "up to the 30th".
3. **What about a record with no `end_date`?** Legacy rows have one; our fallback order is
   `end_date` → `due_date` → always allowed. Say if you would rather refuse.

### Not in scope

**Events.** `EventController::closeRegistration()` already takes `is_registration_closed=false` to
reopen, so events have no missing half — and the frontend surfaces no close-registration control for
events at all since they became announcement-only (BE-41). Leave them alone unless the client asks.

---

## BE-64 — The new post-deadline guard kills Repost on all three types, which is the one thing Repost is for

**Severity: HIGH — a regression introduced by `9310cde`, not a pre-existing gap. It breaks a live
creator feature on volunteering, learn & serve and events at once.**

**Ready to start:** yes, immediately. Nothing else depends on it, and the fix is to narrow a guard
that was added in the wrong place.

### What changed

`9310cde` added a post-deadline refusal in three places, described in the completion report as:

> **Republish/reopen blocked after the deadline.** `Event::republish()`/`closeRegistration()`,
> `VolunteerOpportunity::reopenRegistration()`, and the shared `RepublishMedia::source()` … now all
> refuse once `registrationClosesAt()` has passed — matching the PDF's "closed opportunity can't be
> reopened" note.

Two different operations got collapsed into one rule:

| | What it means | Should a passed deadline block it? |
|---|---|---|
| **Reopen registration** | re-open the *existing* opportunity to new registrations | Yes — that is the PDF's note, and BE-63 |
| **Republish / Repost** | create a **brand-new** opportunity copied from an old one; the source is untouched | **No.** It is *only ever* used on a finished one |

The PDF line is about the first. The guard was applied to both.

### Why this stops Repost entirely

Repost is the creator's action on an opportunity that has **already ended** — that is precisely when
the button appears. `isCreatorRepostState()` derives it from the backend's own `action_state`, and
the label flips from "Edit" to "Repost" exactly once the opportunity is over. So the guard fires on
100 % of real reposts:

- **Events** — `EventController::republish()` returns 422 before it validates anything:

  ```php
  if ($source->registrationClosesAt()?->isPast()) {
      return ApiResponse::error('This event can no longer be republished after the deadline.', …, 422);
  }
  ```

- **Volunteering and learn & serve** — worse, because the refusal is buried in the shared media
  helper. `RepublishMedia::source()` throws `ValidationException`, and `store()` calls it *before*
  the transaction, so the whole create fails:

  ```php
  if (method_exists($source, 'registrationClosesAt')) {
      $deadline = $source->registrationClosesAt();
      if ($deadline && now()->gt($deadline)) {
          throw ValidationException::withMessages([
              'opportunity_id' => ['This opportunity can no longer be republished after its deadline.'],
          ]);
      }
  }
  ```

  The error surfaces on the field `opportunity_id`, which no form on our side renders, so the user
  gets a generic failure toast with no usable message.

`tests/Feature/PdfBackendTasksTest.php::test_expired_event_cannot_be_reopened_or_republished` asserts
this behaviour, so it is deliberate rather than accidental — which is why we are raising it as a
requirements question rather than a bug in passing.

### One extra reason the volunteer/learn-serve half fires on every repost now

We changed the frontend on 2026-09-17 so that a repost **always** sends `opportunity_id`, not only
when it keeps images: it is what lets `RepublishMedia::apply()` copy the **licence document** to the
new opportunity, and our licence validation treats a repost as already satisfied. Before that change
a repost that dropped all images would have slipped past the new guard by never entering
`source()`. It no longer can. (That frontend change is itself a client request — a repost must not
reuse the previous run's gallery — and is already shipped.)

### What we are asking for

**Remove the deadline check from the republish paths; keep it on reopen-registration.** Concretely:

1. Delete the block from `RepublishMedia::source()`. That helper exists to copy media, and a
   permission rule does not belong in it — it is shared by both opportunity types' `store()` *and*
   their `update()`.
2. Delete the block from `EventController::republish()`.
3. Keep the guard in `VolunteerOpportunityController::reopenRegistration()` and in
   `EventController::closeRegistration()`'s reopen direction — those are the operation the PDF meant.
4. Update `test_expired_event_cannot_be_reopened_or_republished` to assert the reopen half only, and
   add a test that an **ended** opportunity of each of the three types **can** still be republished —
   that is the real-world case and nothing covers it today.

If you believe a repost genuinely should be blocked at some point, tell us the rule you have in mind
before implementing it — but note it cannot be "after the registration deadline", because every
repost by definition happens then.

---

## BE-65 — A development opportunity has nowhere to put a WhatsApp contact, because `link` is already its meeting URL

**Severity: MEDIUM — client-requested feature, half-shippable. Volunteering is done and live;
development is blocked on one column.**

**Ready to start:** yes. One nullable column, one validation rule, one resource field.

### What the client asked for

A **direct WhatsApp contact on the opportunity itself**, so a volunteer reading a listing can
message whoever is running *that* opportunity — on both volunteering and development.

### Volunteering already had the field; development does not

| | `volunteer_opportunities.link` | `learn_serve_opportunities.link` |
|---|---|---|
| Form label | «رابط واتساب» (`COMMON.WHATSAPP_LINK`), required | «الرابط الإلكتروني» — the **online meeting URL** |
| Validated as | a real WhatsApp URL (`YupWhatsAppLink`: https, `wa.me` / `whatsapp.com` hosts) | a plain URL |
| Written when | always | only when the format is **online** |
| Shown to | everyone | **registered participants and the organizer only** |

So the two models spell the same column completely differently. We shipped the contact button on
`VolunteerEvent.tsx` and stopped at development, because reusing `link` there would do two bad
things at once: label a Zoom link "contact on WhatsApp", and **publish a link the screen
deliberately gates behind registration** to every anonymous visitor.

Worth noting for your own records: `LearnServeOpportunityResource.php:80` carries a comment reading
«BE-60: `link` is the WhatsApp contact link, not a location». That is true of the volunteer model
and **not** of this one — the comment travelled with the BE-60 fix. The column is the meeting URL
here.

### What we need

A separate nullable contact column on `learn_serve_opportunities`, mirroring what volunteering
already has in spirit but under an unambiguous name — `whatsapp_link` rather than a second `link`:

1. `whatsapp_link` (nullable string) on `learn_serve_opportunities`.
2. Accepted on create and update — `['nullable', 'url']` is enough; we validate the WhatsApp domains
   client-side and would rather you not duplicate that list.
3. Exposed on `LearnServeOpportunityResource` **and** `WebsiteLearnServeOpportunityResource`, and
   **ungated** — unlike `link`, this one is meant for people who have *not* registered yet. That is
   the entire point of it.
4. Please do **not** fold it into `link` or make `link` polymorphic. One column, one meaning: the
   confusion above is exactly what a second overload would deepen.

Once it lands we add the field to `LearnServeForm.tsx` and drop the button into
`LearnServeDetails.tsx` — the markup is already written and commented out in place, so it is a
few minutes' work on our side.

### A question back to you

**Should volunteering's `link` be renamed too?** It means WhatsApp and is named `link`, which is how
this confusion started and what BE-60's stray comment reflects. We are not asking for a breaking
rename now — but if you ever add `whatsapp_link` to the volunteer model as an alias, tell us and we
will migrate the frontend to it. Leaving it as-is is a perfectly good answer.

---

## BE-66 — `due_date` is required again on a volunteer opportunity, and the API still accepts it empty

**Severity: MEDIUM — a reverted product decision. The frontend already enforces it; right now the
API is the only thing that would let a client through without one.**

**Ready to start:** yes. One rule, but read *Where not to put it* first — the obvious place breaks
three other screens.

### The decision

The registration deadline on a **volunteer opportunity** was made optional a few weeks ago, with an
empty value falling back to `end_date` through
`HasRegistrationWindow::registrationClosesAt()`. **The client has reverted that.** Every volunteer
opportunity states its own deadline again.

Shipped on our side already: `dueDate` carries `YupRequiredString`, the field lost its clear button,
and the submit no longer sends an empty `due_date` (it used to, deliberately, so an edit could null
the column — that path is gone). So the website cannot produce one without a deadline. Please make
the API agree, so the mobile app and any other client cannot either.

### Where not to put it

`due_date` is declared once, in `OpportunityValidationRules::core()`:

```php
'due_date' => ['nullable', 'date'],
```

**Do not simply flip that to `$required`.** `core()` has six callers:

| Caller | Should `due_date` become required? |
|---|---|
| `Api/Opportunity/VolunteerOpportunityController` | **Yes** — this is the request |
| `Admin/VolunteerOpportunityController` | **Yes** — see below |
| `Api/Opportunity/LearnServeOpportunityController` | **Not yet** — open question |
| `Admin/LearnServeOpportunityController` | **Not yet** — same |
| `Api/Event/EventController` | **No** |
| `Admin/EventController` | **No** |

Events in particular are announcement-only now and would start rejecting perfectly good submissions.
An extra parameter on `core()` alongside the existing `participantsRequired` /
`secondaryContentRequired` is the natural shape — e.g. `dueDateRequired: false` by default, passed
`true` from the two volunteer controllers.

### Please keep the column nullable

Validation-level required only. **No `NOT NULL` migration.** Rows created while the field was
optional legitimately have `due_date IS NULL`, and the fallback to `end_date` is what keeps their
registration windows correct — `VolunteerOpportunityController` itself relies on it in the listing
sort (`AND (due_date IS NULL OR DATE(due_date) >= ?)`, three times around line 454). Backfilling
those rows would change published deadlines. Leave them alone; the rule only has to bind new and
edited records.

`$partial ? 'sometimes' : 'required'` handles the edit case correctly on its own: a PATCH that does
not mention `due_date` still passes, and one that sends it empty is rejected.

### The admin dashboard has to match

`Admin/VolunteerOpportunityController::store()` calls `core()` with no `$partial`, so whatever you
do to the rule applies there too — but the Blade form needs the field marked required as well, or an
admin fills the form, submits, and gets a validation error on a field that looked optional.

### One question back

**Should development (learn & serve) match?** We deliberately left `LearnServeForm.tsx` optional,
because the client's revert named the volunteer form only. If they meant both, say so and we will
flip ours in the same release as your rule — the two sides must not disagree, or the form will
accept an empty deadline and the API will 422 it.

---

## BE-67 — Five gaps on the volunteers list (`/volunteerlist`), four of them reported by the client

**Severity: HIGH — one of these (the guardian data) is a live regression against the React site the
client is still running, and two of them make the attendance screen unusable after a page refresh.**

**Ready to start:** yes, all five. They are independent of each other and of every other open issue.

Grouped into one ticket because they all land on the same screen and three of them touch the same
resource. Split them if that suits your workflow better.

### 67.1 — `VolunteerOpportunityRegistrationResource` flattened `user`, and took the guardian data with it

**The client's words:** «بيانات ولي الأمر لا تظهر بالجدول، وملف الإكسيل المُصدّر يطلع فارغ من
البيانات (بينما يعمل صح بالموقع الفعلي)» — "the live site" being the React app still serving users
until the Next rewrite lands.

Both frontends read the guardian fields the same way, nested under a `user` object:

```ts
rowData?.user?.emergency_contact_name          // fursa-next  VolunteerRegistrationsTable.tsx
rowData?.user?.emergency_contact_relationship_display
```

```ts
rowData?.user?.emergency_contact_name          // fursa_react  RegisterList.tsx:82
```

The resource sends **`'user' => $this->user_id`** — a bare integer. So `rowData.user.…` is
`undefined` everywhere, the four guardian columns never render (the table hides them when no row has
the data), and the export is empty for the same reason. Nothing is wrong on either frontend; they
were written against a payload that used to nest the user.

**What we need:** the guardian fields reachable from a registration row — either
`user` as an object carrying `emergency_contact_name` / `_phone` / `_civil_id` /
`emergency_contact_relationship_display`, or those four flattened onto the row like `user_email` and
`phone_number` already are. Either shape works; **tell us which and we will follow it.** Please keep
`user_id` reachable too, under whatever name, since the unregister call uses it.

### 67.2 — The registrations payload still has no attendance id or per-date hours

Raised in round one, never closed, and it is what the client is now reporting as «لا يمكن التراجع
بعد التعديل».

`GET /volunteer-opportunity-registrations/` returns `date_wise_attended` — an array of dates and
nothing else. Editing hours (`PATCH /volunteer-attendance/{id}/hours/`) and undoing a check-in both
need that attendance record's **id**, which the payload never carries. Our workaround is to remember
the ids returned by check-ins **made in the current browser session**, which is why both actions
work right up until the organizer refreshes the page and then silently vanish.

**What we need:** for each attended date, its `attendance_id` and its `total_hours`. Shape suggestion,
replacing or sitting beside `date_wise_attended`:

```json
"attendances": [
  { "id": 4412, "attended_date": "2026-09-18", "total_hours": 5.5,
    "checked_in_at": "...", "checked_out_at": "..." }
]
```

The two timestamps are optional-but-welcome now that BE-61 records them.

### 67.3 — A manually added volunteer gets no email

**Client:** «عند إضافة متطوع يدوياً: لا يصله إيميل».

When an organizer adds a volunteer by hand from this screen, nothing is sent to that person. They
are registered for an opportunity they were never told about. Please send the same notification a
self-registration sends.

### 67.4 — A manually added volunteer cannot be found by search

**Client:** «لا يمكن البحث عنه بالاسم/الرقم المدني/الجواز».

Two places need this and the client listed both:

1. The registrations search on this screen.
2. The people-picker used to grant a permission — «عند البحث عن شخص لاعطائه اذن التحضير يجب البحث
   عنه بالاسم (الاسم او الرقم المدني او الجواز)».

The row does carry `civil_id` and `passport_number`, so the fields exist — the **search** does not
match on them. Please have the `search` parameter match **name OR civil_id OR passport_number** on
both endpoints. Worth checking whether a manually created user is indexed at all, since the client
reports they cannot be found even by name.

### 67.5 — A role can be assigned beyond the number the opportunity asked for

**Client:** «اختبار إضافة دور فوق العدد المطلوب — النظام يقبله بدون تحقق (باق)», marked as still
outstanding from a previous round.

Assigning volunteers to a role accepts more than that role's `participants_needed` with no
validation. Please reject the assignment that would exceed it, with a field error we can show.

---

### New feature on the same screen — «إذن تحضير»

Not a bug, and **not** the existing `/scan-permission`. The client defined it for us:

> إذن يعطى لمتطوع لاضافه متطوعين في الفرصه وتحديد عدد ساعات التطوع وليس التحضير باستخدام الـ QR

A permission an organizer grants **to a volunteer**, letting that volunteer (a) add volunteers to
the opportunity and (b) set their volunteer hours. **Explicitly not QR scanning** — which matters,
because the QR-delegation flow this sounds like is the one BE-61 Part C retires.

The mockup puts a fourth button for it beside «إرسال الشهادات» / «إضافة دور» / «إضافة متطوع». We have
shipped the other three and **left this one out rather than ship a dead button**, so the frontend is
waiting on:

1. Grant / revoke / list endpoints for the permission, scoped to an opportunity.
2. The people-picker search from 67.4 (same requirement, which is why the client filed them together).
3. Authorization on the existing add-volunteer and set-hours endpoints so a holder of this permission
   passes, where today only the organizer does.

Please confirm the shape before building — in particular whether one permission covers both abilities
or they are separate flags.

---

## Verification that could not be performed here

This workspace has **no PHP runtime, no Composer, and no installed `vendor/`**, so:

- `php artisan test` was **not** run — the backend team's own suite runs (304 tests this round) are
  the only execution evidence for the closed items.
- `php artisan route:list` was **not** run — the route table used for this audit was parsed directly
  from `routes/api.php`, including every `Route::match([...])` verb list.
- No endpoint was exercised against a live server or database.

Everything marked *Closed* above was verified by **reading the branch** — the call site, the rule,
the migration, and for BE-56 the *ordering* of the guard against the merges it depends on — not by
trusting the report.

Both `2026_09_15_*` migrations are now confirmed run, including on production, so the open data
question from the last round is closed. Three remain, one from the last round and two raised by this
one — all three are single queries, and each decides how an issue gets built:

- **How many user rows have `nationality = 'other'` with `residency_status` NULL.** That is the
  population BE-57 affects. Everything created before 2026-08-30 is a candidate. If the answer is
  zero, BE-57 is a "won't fix" and we will close it on your word.
- **What the production `config` row holds for `preparation_validity_hours` and
  `preparation_validity_days`.** BE-61 depends on this: the client asked for manual attendance to
  close 3 days after an opportunity ends, and the code already does that **by default** — but hours
  take precedence over days when non-zero, so a drifted setting means the client's "3 days" is
  quietly not what production runs. Nothing to build if it is empty or 72; please just read it.
- **Which `learning_type` rows actually exist in production, with their ids.** BE-62 renames one of
  them in place, and the seeder is not proof of what is live — BE-47 already found lowercase
  duplicates in one environment. Confirm there is exactly one `Consultation` row before renaming it.

Also still outstanding from the earlier rounds: the production `EXPOSE_OTP_IN_RESPONSE` value, any
pre-existing `event_attendances` rows before the drop migration runs, and the two backfill commands (`fursa:backfill-sanitize-rich-text`, `fursa:backfill-generated-link`).

---

## ✅ Final task — send back a completion report when everything is done

**This is the last task in this file. Please do it after all the work above is finished.**

**هذه آخر مهمة في الملف — من فضلك نفّذها بعد الانتهاء من كل الشغل اللي فوق.**

Thank you — BE-56 was done properly. You didn't just add the guard, you put it **before** the
fallback merges at both call sites, which is the one detail that makes it work at all, and you wrote
the tests for both directions. Telling us that `2026_09_15_000002` had never actually run was the
most useful line in your report: that was a real silent failure in production behaviour that nobody
would have seen from either side. **Same format again, please — it is working well.**

شكرًا — BE-56 اتعملت صح. مش بس ضفت الـ guard، لكن حطيتها **قبل** الـ merges في الاتنين، وده
بالظبط التفصيلة اللي بتخلّيها تشتغل أصلًا، وكتبت تستات للاتجاهين. وأنفع سطر في التقرير كان إنك
قلت إن `2026_09_15_000002` معتش اشتغلت فعلًا — دي كانت مشكلة حقيقية وصامتة مكانش حد هيشوفها من
الناحيتين. **نفس الشكل تاني من فضلك — الطريقة دي شغالة كويس.**

### What is left / الباقي

Six, in the order we would tackle them:

- **BE-61** — the biggest by a wide margin, and the one the client is waiting on. **It was
  rewritten on 2026-09-17 after the client answered**, and it grew: learn-&-serve is now in scope
  with a *second*, different code mechanism, and the old organizer-scans-volunteer flow is retired
  outright. Please re-read the whole section rather than diffing it. Our side is fully blocked:
  there is nothing to call until the endpoints exist. Two things in it need no work at all — the
  3-day manual-attendance close is **already implemented**, and republish already yields a fresh
  code; both are flagged inline so they are not rebuilt.
- **BE-62** — small, and coupled to BE-61: it settles what «المساحات» meant. Doing it carelessly
  renames a row that five string literals depend on, so please read it rather than skimming it.
- **BE-59** — the client reported this one directly too. Large, because the notification system has
  no way to address an admin at all.
- **BE-58** — the read side of a feature whose write side you already built. Small.
- **BE-60** — one line in a resource. Smaller still, and it deletes two workarounds on our side.
- **BE-57** — low; the website cannot hit it.

Everything else in this file is closed, and the previous round's open questions are all answered.

ستة، بالترتيب اللي كنا هنبدأ بيه:

- **BE-61** — الأكبر بفارق كبير، واللي العميل مستنيها. **اتكتبت من أول وجديد يوم 2026-09-17 بعد
  ما العميل رد**، وكبرت: فرص التطور بقت داخلة في النطاق بآلية رمز **تانية ومختلفة**، وطريقة «الجهة
  تمسح رمز المتطوع» هتتشال بالكامل. من فضلك اقرا القسم كله من الأول مش بس الفرق. إحنا متوقفين
  تمامًا: مفيش حاجة نستدعيها لحد ما الـ endpoints تبقى موجودة. وفيه حاجتين مش محتاجين شغل خالص —
  قفل التحضير اليدوي بعد ٣ أيام **معمول فعلًا**، وإعادة النشر بتطلّع رمز جديد لوحدها؛ الاتنين
  متعلّمين جوه القسم عشان محدش يعيد بناهم.
- **BE-62** — صغيرة، ومربوطة بـ BE-61: هي اللي بتحسم معنى «المساحات». بس لو اتعملت بسرعة هتغيّر
  اسم صف خمس أماكن في الكودين بيدوّروا عليه كنص، فياريت تتقري مش تتلسّع.
- **BE-59** — دي كمان العميل بلّغ عنها بنفسه. كبيرة، لأن نظام الإشعارات مفيهوش طريقة توصل لأدمن
  أصلًا.
- **BE-58** — الجزء الخاص بالقراءة من فيتشر إنت عامل جزء الكتابة بتاعه خلاص. صغيرة.
- **BE-60** — سطر واحد في الـ resource. أصغر، وهتشيل حلّين مؤقتين من عندنا.
- **BE-57** — بسيطة؛ الموقع مش بيقدر يوصلها.

كل اللي غير كده في الملف ده اتقفل، وأسئلة الجولة اللي فاتت كلها اتجاوبت.

### What the file must contain / اللي لازم يكون في الملف

1. **The confirmation phrase, exactly as written below.** This is how we know the whole job is
   finished — without it we treat the work as still in progress:

   **العبارة دي بالظبط زي ما هي مكتوبة تحت** — بيها نقدر نتأكد إنك خلصت كل الشغل، ومن غيرها
   هنعتبر الشغل لسه شغال:

   ```
   مساء الفل يا ميدو
   ```

2. **A status line for each of BE-57 … BE-62** — **Done**, **Partially done** (and what is
   left), or **Not done** (and why). On BE-57, "won't fix, the row state is impossible in our data"
   is a perfectly good answer — but please say so with the row count behind it, not as an assumption.

   **سطر حالة لكل واحدة من BE-57 لحد BE-62** — **خلصت**، ولا **خلصت جزئيًا** (وإيه الباقي)،
   ولا **لسه** (وليه). وفي BE-57، لو قلت "مش هتتعمل لأن الحالة دي مش موجودة في الداتا" ده رد مقبول —
   بس ياريت بعدد الصفوف الفعلي، مش كافتراض.

3. **The decisions we need in order to build our side.** BE-61 is blocked on the first four —
   we cannot write a single screen without them, so please answer them even if the code is not
   finished yet.

   **القرارات اللي محتاجينها عشان نبني الجزء بتاعنا.** BE-61 متوقفة على أول أربعة — مش هنقدر نكتب
   ولا شاشة من غيرهم، فياريت تجاوبهم حتى لو الكود لسه مخلصش.

   - **BE-61** — do the codes come back as **image URLs or raw payload strings**? Same answer for
     Part A and Part B, or different?
   - **BE-61** — the **shape of the volunteer state field** (Part A, point 5). Without it our button
     cannot tell "scan IN" from "scan OUT" after a page refresh.
   - **BE-61** — **which release retires the Part C endpoints**, so our four screens go with them.
   - **BE-61** — does **anything besides the scanner read `volunteer_profiles.qr_code`**?
     Certificates, exports, the dashboard — if so it outlives Part C.
   - **BE-62** — whether `value_en` changes alongside `value_ar`, and to what. If it does, four
     string matchers on our side have to ship in the same release or consultation booking breaks
     silently.
   - **BE-58** — which shape the listing resource will carry: the full `time_slots` array, or just
     `has_custom_schedule` plus a day count. Either works for us; we only need to know which to read.
   - **BE-59** — who receives the new-request notification: every admin, or only those with the
     permission to approve. That is a product call and it changes the table design.

4. **Three numbers read straight from production** — all in *Verification that could not be
   performed here*, all single queries, and each one decides how an issue gets built:

   **تلات أرقام من الداتا بتاعة الـ production** — كلها queries بسيطة، وكل واحدة بتحدد الحل:

   - the **BE-57 row count** — users with `nationality = 'other'` and `residency_status` NULL;
   - the production **`preparation_validity_hours` / `_days`** config values (BE-61);
   - the live **`learning_type` rows and their ids** (BE-62).

5. **How each fix was verified** — the test, the endpoint call, or the screen you checked. Several
   findings in this file survived precisely because a unit test passed while the code under test was
   never wired up (see the BE-37 note), and one survived because the migration had never run (see
   the BE-53 note this round). "The tests are green" on its own is not enough.

   **إزاي اتأكدت من كل إصلاح** — التست، أو الـ endpoint اللي جربته، أو الشاشة اللي شوفتها.
   فيه مشاكل عاشت لأن التست كان بينجح والكود مش متوصّل، وواحدة عاشت لأن الـ migration معتش اشتغلت
   أصلًا. فـ "التستات كلها خضرا" لوحدها مش كفاية.

6. **Anything you changed that is not in this file** — a schema change, a renamed field, a new
   required parameter. Any of those can break the frontend silently, so we need to know before it
   ships.

   **أي حاجة غيّرتها مش موجودة في الملف ده** — تغيير في الداتابيز، اسم حقل اتغير، باراميتر جديد
   مطلوب. أي واحدة من دول ممكن تكسر الفرونت من غير ما حد ياخد باله، فمحتاجين نعرف قبل ما ينزل.

### Done means / يعني إيه خلصت

All of: the code is written, it is reviewed, the behaviour is verified on a running instance, and the
three production numbers above are filled in. If the issue is still open, say so plainly in the file rather than
leaving it out — a partial report with honest gaps is more useful to us than a complete-looking one.

الكود اتكتب، واتراجع، والسلوك اتأكدنا منه على نسخة شغالة، والتلات أرقام اللي فوق اتكتبت. لو النقطة
لسه مفتوحة، اكتبها بصراحة في الملف بدل ما تسيبها — تقرير ناقص وواضح أنفع لينا من تقرير شكله كامل.
