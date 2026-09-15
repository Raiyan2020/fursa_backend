# Fursa — backend issues

Findings from the end-to-end integration audit of `fursa-next` against `fursa_backend`.

**Re-verified 2026-09-14 against branch `updates` (`0fc0a37`).** The backend team's
`FURSA_BACKEND_DONE.md` reported eleven issues done. **Ten are confirmed resolved by reading the
branch and have been removed from this file** (summarised under _Closed_ below); one — BE-44 — is
resolved only in part and stays open in trimmed form.

**Still open: BE-44, BE-50 … BE-55.** BE-50 to BE-54 were raised *after* that branch was cut, so
they were never in its scope — they are not regressions and nothing was missed. BE-54 is now
**partially** done: the backend already emits `profile_activity_tag` as a per-row field, but no
endpoint accepts it as a filter.

IDs continue the existing sequence in `fursa-next/docs/BACKEND_ISSUES.md`, which ends at BE-34.

| ID | Severity | Status | Title |
|---|---|---|---|
| [BE-50](#be-50--the-three-way-nationality-rule-exists-only-on-register-profile-update-demands-a-civil-id-the-user-may-not-have-and-social-signup-cannot-accept-a-passport-at-all) | **HIGH** | **Open — frontend partly blocked** | The three-way nationality rule exists only on `/register/`: profile update demands a civil ID the user may not have, and social signup cannot accept a passport at all |
| [BE-51](#be-51--second-org_type-restructure-six-client-approved-types-with-society-splitting-into-two) | **MEDIUM** | **Open — frontend prepared** | Second `org_type` restructure: six client-approved types, with `Society` splitting into two |
| [BE-52](#be-52--the-public-profile-list-endpoints-still-serve-every-volunteers-real-first-and-last-name) | **MEDIUM** | **Open — frontend already done** | The public profile-list endpoints still serve every volunteer's real first and last name |
| [BE-53](#be-53--the-certificates-filter-needs-two-things-from-the-backend-a-certificate_type-param-on-user-certificates-and-a-certificate_filter_type-choice) | **MEDIUM** | **Open — frontend blocked** | The certificates filter needs two things from the backend: a `certificate_type` param on `/user-certificates/`, and a `certificate_filter_type` choice |
| [BE-54](#be-54--profile_activity_tag-split-a-profiles-activity-listing-by-participant--provider--and-make-the-development-counter-follow-the-same-rule) | **MEDIUM** | **Partially done** | `profile_activity_tag`: split a profile's activity listing by Participant / Provider — and make the development counter follow the same rule |
| [BE-55](#be-55--organization-counters-incl-sponsorship-are-computed-but-never-returned-public-profile-and-organization-profile-always-answer-0) | **HIGH** | **Open — frontend shows 0 for every org counter** | Organization counters (incl. sponsorship) are computed but never returned: `/public-profile/{id}/` and `/organization-profile/` always answer `0` |
| [BE-44](#be-44--the-admin-form-and-the-api-still-validate-the-same-columns-differently-reduced) | **LOW** | **Partially fixed** | The admin form and the API still validate the same columns differently (reduced) |

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

## BE-44 — The admin form and the API still validate the same columns differently (reduced)

**Severity: LOW — partially fixed in `updates`.**

> ### ✅ The one reachable failure is fixed
>
> `Admin/VolunteerOpportunityController.php:424` is now `['nullable', 'url', 'max:500']`, matching
> the API's `['nullable', 'url']`. That closes the only case that actually bit anyone: an admin
> could save a non-URL `link`, and the organization's next in-app edit then 422'd on a field they
> had never touched, because the form resends the loaded value.
>
> The interest vocabularies also now agree, as a side effect of BE-43.

### What is still drifting

The structural recommendation — one shared rule set both surfaces compose from — was not done, so
these remain:

| Field | Admin rule | API rule | Consequence |
|---|---|---|---|
| `opportunity_nationality` | `Rule::in(Nationality)` | **absent** | Admin-only column. The API cannot set it, and `rejectUnknownWriteKeys` would 422 any client that tried. |
| `is_calendar` | `boolean` | **absent** | Same. (And nothing reads it — see the BE-48 note.) |
| `after_images` | accepted | **absent** | Same. |
| `time_slots` | **absent** | array of per-day rows | The dashboard cannot see or edit a schedule the app created. |
| `title_ar`, `description_*` (event) | `required` | `nullable` | A record the API accepted can fail to save unchanged in the dashboard. |

None of these is reachable from the app today, which is why this stays **LOW**. It is drift that
will bite whoever next assumes the two validators agree.

### Recommended fix

Extract the shared column rules into one place (a form request or a small trait) that both the
admin controller and the API controller compose from, with only the genuinely surface-specific
keys — `created_by`, `approval_status`, `opportunity_status` on the admin side; `opportunity_id` /
`existing_image_ids` on the API side — added separately.

### Frontend status

**No frontend change required.** The forms already send `HH:mm` times and validate `link` as a URL
client-side; they cannot control what the dashboard writes.

---

## BE-50 — The three-way nationality rule exists only on `/register/`: profile update demands a civil ID the user may not have, and social signup cannot accept a passport at all

**Severity: HIGH — open.**

### The product rule

The registration question has **three** answers, and each decides which identifier is mandatory:

| Choice (ar) | Choice (en) | `nationality` | `residency_status` | Required identifier |
|---|---|---|---|---|
| كويتي | Kuwaiti | `kuwaitis` | — | `civil_id` |
| غير كويتي مقيم | Non-Kuwaiti resident | `other` | `resident` | `civil_id` |
| غير كويتي غير مقيم | Non-Kuwaiti non-resident | `other` | `non_resident` | `passport_number` |

### What already works — credit where it is due

`POST /api/register/` implements this **exactly**, and did before this review:

- `app/Enums/ResidencyStatus.php` — `RESIDENT` / `NON_RESIDENT`, with the rule stated in its own
  docblock.
- `RegisterRequest::validateIdentityDocument()` (lines ~152-186) — a Kuwaiti (or a client that
  omits `nationality`, preserving old behaviour) must send `civil_id`; a non-Kuwaiti must first
  declare `residency_status`, which then selects `requireUniqueCivilId()` or
  `requireUniquePassportNumber()`.
- `users` stores all the columns — `nationality`, `residency_status`, `civil_id`,
  `passport_number` — and `AccountResource`, `UserResource` and both registration resources
  return them.

**Storage is done. Registration validation is done.** The gaps are everywhere else the same fields
are written.

### 1. A non-resident volunteer can never save their profile — HIGH

`VolunteerProfileController::update()` (`app/Http/Controllers/Api/Volunteer/VolunteerProfileController.php:86`)
— **still true on `updates`:**

```php
'civil_id' => ['required', 'string', 'max:12', Rule::unique('users', 'civil_id')->ignore($user->id)],
'nationality' => ['nullable', 'string', Rule::in(\App\Enums\Nationality::values())],
```

`civil_id` is **required**, and the method accepts neither `residency_status` nor
`passport_number` — they are not in the rule array at all, so even a client that sent them would
have them dropped.

A volunteer who registers as **non-Kuwaiti non-resident** is told at signup *not* to give a civil
ID, and is then permanently unable to save anything on their profile screen: every save 422s on a
field they were never asked to fill, and their `passport_number` can never be corrected because the
endpoint will not accept it. Registration succeeds and the account is stuck.

**Fix:** move `validateIdentityDocument()` into something both `RegisterRequest` and this method
use (a shared form request or trait), add `residency_status` and `passport_number` to the rule
array, and stop hard-requiring `civil_id`.

### 2. A non-resident cannot sign up with Google or LinkedIn at all — HIGH

`AuthController::socialAuth()` validates only
`['email', 'social_media_provider', 'social_media_id', 'first_name', 'last_name',
'social_profile_pic_url', 'user_type', 'civil_id', 'nickname', 'company_name', 'organizer_type']`
— no `nationality`, no `residency_status`, no `passport_number` — and then hard-blocks a new
volunteer without a civil ID:

```php
if ($userType === UserType::VOLUNTEER && empty($data['civil_id'])) {
    return ApiResponse::error(..., 400, ['civil_id' => [__('validation.required', ...)]]);
}
```

So the entire social-signup path is closed to non-Kuwaiti non-residents. The frontend's mandate
screen (`VolunteerMandateDetails.tsx`) has therefore been **left on the old two-option question** —
offering the third choice there would only produce a 400 the user cannot resolve.

**Fix:** accept the same three fields here and apply the same conditional rule.

### 3. `POST /account/` accepts the fields but enforces no relationship between them — MEDIUM

The account update takes `nationality`, `residency_status`, `civil_id` and `passport_number`, all
`nullable`. Nothing checks they agree, so an account can be saved as
`residency_status: non_resident` while keeping a civil ID and no passport, or with both identifiers
cleared. Whatever shared validator fixes parts 1 and 2 should run here too.

### 4. `Nationality::ALL` is a filter value that a person can be saved as — LOW

`app/Enums/Nationality.php` has three cases: `KUWAITIS`, `ALL`, `OTHER`. `ALL` exists for the
opportunity filter (`opportunity_nationality` — "open to all nationalities"), but
`Rule::in(Nationality::values())` is used unchanged on `/register/`, `/account/` and
`/volunteer-profile/`, so a **user** can be stored with `nationality = all`, which is not a
nationality. Worth splitting the person enum from the filter enum, or excluding `ALL` on the user
routes.

### Frontend status — done for registration, blocked elsewhere

The three-option question is live on both screens that POST `/register/`:
`IndividualRegistrationForm.tsx` (the `/individual-form` page) and
`RegisterVolunteerModalForm.tsx`. One dropdown is shown, and the pair the API wants is derived from
it on submit — `nationality` + `residency_status` — with the identifier field below it switching
between Civil ID and Passport number, required accordingly, and the unused one cleared so a stale
value cannot be submitted against the wrong question.

Worth noting what this also fixed: the forms previously offered only *Kuwaiti / Other* and never
sent `residency_status`, so **choosing "Other" made registration fail outright** — the API adds a
"residency status is required" error that the old form had no field for.

Two screens are deliberately **not** changed and still ask the two-option question, because the
third answer has nowhere to go until the above is fixed:

- `VolunteerAccountInformation.tsx` — blocked by part 1 (`civil_id` required, passport not accepted).
- `VolunteerMandateDetails.tsx` — blocked by part 2 (social signup requires a civil ID).

Both need a one-line swap to the shared `nationalityResidencyOptions` list once the endpoints accept
the fields.

---

## BE-51 — Second `org_type` restructure: six client-approved types, with `Society` splitting into two

**Severity: MEDIUM — open. Backend data change; the frontend is already prepared.**

### What the client asked for

The entity sign-up question on `/entities-form` should offer exactly these six, in this order:

| # | Arabic | English |
|---|---|---|
| 1 | حكومي | Governmental |
| 2 | تجاري | Commercial |
| 3 | تعليمي | Educational |
| 4 | غير ربحي | Non-profit |
| 5 | جمعية | Association |
| 6 | مجتمع | Community |

These are `master_choices` rows under `choice_type = 'org_type'`, served by
`GET /api/choices/org_type/`. **The frontend renders whatever that endpoint returns and cannot
change a single label** — this is entirely a backend data change.

### Mapping from the current list

`database/migrations/2026_08_23_000003_restructure_organization_types.php` established the current
six, and `ChoiceTypeSeeder` still carries them unchanged on `updates`. Four are renames, one splits
in two, and one is new:

| Current `value_en` | Current `value_ar` | → | New `value_en` | New `value_ar` | Change |
|---|---|---|---|---|---|
| `Institution` | وزارة / هيئة حكومية | → | `Governmental` | حكومي | rename |
| `Commercial` | شركة تجارية / براند | → | `Commercial` | تجاري | label only |
| `Education` | جامعة / مدرسة / معهد | → | `Educational` | تعليمي | rename |
| `NGO` | جمعية خيرية / غير ربحية | → | `NonProfit` | غير ربحي | rename |
| `Society` | جمعية تعاونية / مجتمع | → | `Association` | جمعية | **splits** |
| — | — | → | `Community` | مجتمع | **new** |
| `Volunteer Team` | فريق تطوعي | → | `Volunteer Team` | فريق تطوعي | unchanged, stays hidden |

`Volunteer Team` must survive: it is never offered in the dropdown, but the JoinUs shortcut selects
it by `value_en` and `OrganizationProfile.is_volunteer_team` depends on it.

### The split is the part that needs a decision

`Society` currently means *"جمعية تعاونية / مجتمع"* — one row covering both a cooperative society
and a community group. The new list separates them, so **every existing organization whose
`organizer_type_id` points at `Society` has to be assigned to one of the two**, and the row itself
does not say which.

Please decide the default before writing the migration. Suggested: repoint all existing `Society`
profiles to `Association`, since "جمعية" is the more specific of the two and the current `value_ar`
leads with it — but that is a product call, and any organization that is really a community group
will need correcting by hand afterwards.

### Two places hardcode `Society` / `Community` and will break on the rename

**1. The backend's own licence lookup.**
`app/Http/Controllers/Api/Base/BaseController.php:150-166` resolves the licence rule by matching the
English name:

```php
$societyType = MasterChoice::query()
    ->whereHas('choiceType', fn ($q) => $q->where('name', 'org_type'))
    ->whereIn('value_en', ['Society', 'Community'])
    ->pluck('value_en', 'id');
…
$userRoleType = $matchedSociety === 'Society' ? 'society' : 'community';
```

Renaming `Society` → `Association` makes this match nothing, so every cooperative society falls
through to `$userRoleType = 'organization'` and is asked for a licence it is exempt from. The
`user_role_license_requirements` table also keys on the string `society`, so that row needs a
decision too: rename the role to `association`, or keep `society` as the role name and only change
the choice label.

**2. `check-license-requirement` cannot be called during sign-up.** It needs a token, and the
sign-up form has none, which is why the frontend keeps its own copy of the exemption list. That copy
has been updated to accept **both** `Society` and `Association`, so the frontend survives the rename
either way — but the backend copy above still needs fixing.

### Recommended migration shape

Follow `2026_08_23_000003`, which got this right and is worth copying:

1. Insert `Community` if it is not already there (it exists as a **soft-deleted** row retired by
   that migration — restore it rather than inserting a duplicate).
2. Update `value_en` / `value_ar` in place for the four renames, so `organizer_type_id` foreign keys
   stay valid and no profile is orphaned.
3. Repoint `Society` profiles per the decision above, before any hiding.
4. Check `sponsors.org_type_id` too — the previous migration did, and the same rows are affected.
5. Update `ChoiceTypeSeeder::$map['org_type']` to the new six plus `Volunteer Team`, so a freshly
   seeded environment matches production. (The same file was just reconciled for `learning_type`
   under BE-47 part 4 — this is the same treatment for the other list.)
6. Fix `BaseController.php:153` and the `user_role_license_requirements` row in the same change.

### Frontend status — prepared, no further change needed

`src/data/orgTypes.ts` recognises **all three generations** of names at once, so the dropdown keeps
working before, during and after the migration:

- `isLicenseExemptOrgType()` accepts `Society`, `Association`, `Community` and the pre-2026-08
  `Public`. `Institution` and `Governmental` are both deliberately **absent** — see the note in that
  file; whether a government body is licence-exempt is still an open question, and the safe default
  is to ask for the licence.
- `sortOrgTypeOptions()` puts the six into the client's order (حكومي، تجاري، تعليمي، غير ربحي،
  جمعية، مجتمع), keyed on both old and new English names, and sorts any unrecognised type to the end
  rather than dropping it. Applied on `EntitiesRegistrationForm.tsx` (`/entities-form`),
  `CompleteDetails.tsx` (the social sign-up path) and `OrganizerAccountInformation.tsx`, so all three
  screens agree.

Nothing in the frontend needs to change when the migration lands — the new labels appear as soon as
`GET /api/choices/org_type/` returns them.

---

## BE-52 — The public profile-list endpoints still serve every volunteer's real first and last name

**Severity: MEDIUM — open.**

The client's rule is that an individual volunteer is identified by **nickname only** — their real
name is not public. `/public-profile/{id}` already honours it: `full_name` is returned as `null` for
a volunteer on purpose, and the frontend renders `display_name || nickname`.

The list endpoints do not. `WebsiteProfileListItemResource::toArray()` returns, for the volunteer
branch — **still true on `updates`:**

```php
'user_details' => [
    'id' => $profile->user->id,
    'first_name' => $profile->user->first_name,
    'last_name'  => $profile->user->last_name,
    …
],
```

Those four routes are **public**, under the comment *"Organization — public (Django AllowAny)"*:

| Route | Serves |
|---|---|
| `GET /api/all-profiles/` | all three buckets |
| `GET /api/profiles/volunteers/` | volunteers |
| `GET /api/profiles/organizations/` | organizations |
| `GET /api/profiles/volunteer-teams/` | volunteer teams |

So an anonymous caller can page through `/api/profiles/volunteers/` and read every volunteer's real
name paired with their nickname, profile picture and id — the exact mapping the nickname rule exists
to prevent.

There is a second route to the same data: `applyProfileSearch()` accepts a **`name`** filter that the
Members screen passes through, so a caller can also confirm a specific real name by searching for it
and seeing which nickname comes back — even with the field hidden in the UI.

### Fix

Drop `first_name` / `last_name` from the volunteer branch of the resource. The organization branch
can keep them — an organization's name is its public identity, not personal information. Then decide
whether the `name` search filter should still apply to volunteers; if the name is not public,
searching by it probably should not be either.

### Frontend status — already done

`MoreProfile.tsx` (`/more-profile?tab=volunteer`) previously fell back to
`first_name + " " + last_name` whenever a volunteer had no nickname, and used the same pair as the
avatar's `alt` text. Both now render nickname only for an individual, falling back to `"—"` rather
than to the real name — matching `/public-profile/{id}`. Organizations and volunteer teams keep the
name fallback; the check is on `user_details.user_type`, not on the active tab, so an individual
stays anonymous wherever the card is rendered.

That is a UI change only. The names are still in the payload until the resource is fixed.

---

## BE-53 — The certificates filter needs two things from the backend: a `certificate_type` param on `/user-certificates/`, and a `certificate_filter_type` choice

**Severity: MEDIUM — open.**

The client asked for a filter on the certificates tab of `/volunteer-profile`, matching the pill row
on the opportunities tab: **تطوع / دورات / تدريب** (volunteer / courses / internship), with the types
coming from the backend rather than hardcoded in the app, and the filtering done by the **existing**
`/user-certificates/` endpoint rather than a new one.

Two pieces are missing — the **param** it filters by, and the **types** it offers.

### 1. `GET /api/user-certificates/` takes no type filter

After the BE-46 fix the endpoint takes no useful parameter at all: `user_id` is now ignored and the
caller's own id is used, and nothing else is read. `userCertificates()` unions the learn-serve and
volunteer registration tables and returns everything.

**Fix:** accept an optional `certificate_type` and scope the union with it:

```
GET /api/user-certificates/?certificate_type=Course
```

| `certificate_type` | Returns |
|---|---|
| *(omitted)* | everything, as today |
| `Volunteer` | volunteer-opportunity certificates only |
| `Course` | learn-serve certificates whose opportunity's learning type is `Course` |
| `Internship` | learn-serve certificates whose opportunity's learning type is `Internship` |

The value is the choice's stored `value_en` (part 2). Matching it against the opportunity's
`learningType->value_en` is the natural implementation — but please match on the **choice id** rather
than the English string if you can, or at least normalise case: BE-47 part 2 was a live example of
exactly this kind of label comparison drifting when a choice was renamed, and the fix there settled
on substring matching for the same reason.

An unrecognised value should be a `422` rather than a silently unfiltered list — BE-18 established
that convention for `filter_type`, and a typo returning "everything" is the failure mode that hides
best.

`Class` and `Consultation` never need handling here: per BE-47 they grant no certificate, so they can
only ever match zero rows.

**Note:** the frontend already sends this param. An unknown query param is ignored by
`$request->validate()`, so today every chip returns the full list — the filter looks wired but does
nothing until this lands.

### 2. The filter's own options need a `certificate_filter_type` choice

The filter's options should be admin-editable master data like every other dropdown in the app, not
a list hardcoded in the frontend. Please add a choice type named **`certificate_filter_type`** with
exactly these three:

| `value_en` | `value_ar` |
|---|---|
| `Volunteer` | تطوع |
| `Course` | دورة |
| `Internship` | تدريب |

It is served by the existing `GET /api/choices/{choice_type}/` — no new endpoint. Seed it in
`ChoiceTypeSeeder` alongside the others.

These `value_en` strings are what the frontend sends back as `certificate_type` in part 1, so the two
must agree exactly.

**Please do not reuse the existing `filter-type` choice.** It already carries five values —
`Volunteer`, `Course`, `Class`, `Internship`, `Consultation` — and is shared with the opportunities
filter modal (`AllOpportuniteFilterModal.tsx:117`), so it cannot be trimmed to three without changing
that screen. `Class` and `Consultation` can never carry a certificate (BE-47), so they must not
appear on this filter.

### Frontend status — fully wired, waiting on both parts

`ProfileDescriptionTabs.tsx` shows the chip row on the certificates tab, using the same `FilterChips`
component as the opportunities tab, with paging reset on each change.

- **Options** are fetched from `GET /api/choices/certificate_filter_type/`. That type does not exist
  yet, so the request 404s and the three types above are used as a transitional fallback — the chips
  appear correct today and become admin-editable the moment the choice is seeded, with no frontend
  change. The fallback is marked in the code to be deleted then.
- **Filtering** is sent as `certificate_type` on `/user-certificates/`, with no second filter in the
  client — duplicating the rule is how the two drift apart, and it would also mean paging a list the
  server never returned.

So the filter is complete on this side and **does nothing until part 1 lands**: every chip currently
returns the full list, because the param is ignored. Both parts are small, and neither needs a
frontend change once done.

---

## BE-54 — `profile_activity_tag`: split a profile's activity listing by Participant / Provider — and make the development counter follow the same rule

**Severity: MEDIUM — partially done. Part filter, part counter defect.**

> ### ⚠️ Half of this already exists on `updates` — as an output field, not a filter
>
> `BuildsWebsiteFields::profileActivityTag()` computes exactly the right thing and both
> `WebsiteVolunteerOpportunityResource` and `WebsiteLearnServeOpportunityResource` return a
> **`profile_activity_tag`** on every row: `provider` when the profile owner created a development
> activity, `participant` when they attended one, plus `attended` / `registered` for the
> non-development cases.
>
> **But no controller accepts it as a query param**, so the listing cannot be narrowed by it — which
> is what the filter needs. Two follow-ups:
>
> 1. Accept it as a filter (below). The per-row field is not a substitute: the list is paged, so
>    filtering after the fact would page a list the server never agreed to.
> 2. **The values are lowercase** (`provider` / `participant`) while the frontend sends the
>    capitalised `Provider` / `Participant`. Please match case-insensitively, or tell us which
>    casing is canonical and the frontend will send that.

### The product rule

> يُحتسب داخل كاونتر فرص تطور كل نشاط تطور قام فيه الفرد بأحد الدورين:
> شارك في النشاط كـ **Participant | مشارك**، أو قدّم النشاط كـ **Provider | مقدم**.

A development activity counts towards the individual's counter if they played **either** role — they
took part in it, or they ran it. The client wants the activity listing on a profile to be splittable
by that role.

### What is needed

Accept an optional **`profile_activity_tag`** query param on the two endpoints that back a profile's
activity listing:

- `GET /api/list-user-opportunities/` — used by `/volunteer-profile` and by the volunteer branch of
  `/public-profile/{id}`
- `GET /api/list-all-opportunities/` — used by the organization branch of `/public-profile/{id}` and
  by the legacy tabs

| `profile_activity_tag` | Returns |
|---|---|
| *(omitted)* | both roles, as today |
| `Participant` | activities the profile owner **attended** (the `attended` set, not merely registered) |
| `Provider` | activities the profile owner **created** (`created_by = <that user>`) |

It composes with the existing params rather than replacing them — `filter_type`, `opportunity_type`,
`search`, `tags`, the date range and `user_id` all still apply, so "Development + Provider" is a
valid combination and must narrow by both.

An unrecognised value should be a `422`, not a silently unfiltered list — the convention BE-18 set
for `filter_type`.

### Scope: development activities only — confirmed by the client

`profile_activity_tag` is **only ever sent together with
`opportunity_type=learn_serve_opportunity`**. The client confirmed the split applies to فرص تطور
only, so the frontend shows the chips solely while the Development type chip is active and clears the
role the moment the type moves off it. The param will never arrive alongside a volunteer opportunity,
and does not need handling there.

### `Participant` means attended — confirmed by the client

> إذا **حضر** الفرد دورة/ورشة/فرصة تطور كمشارك تُحسب فرصة تطور واحدة، وإذا **قدّم** دورة أو ورشة
> كمدرب تُحسب أيضًا فرصة تطور واحدة.

So `Participant` is **attended**, not merely registered — the same set the `attended` /
`filter_type=attended` query already returns, and the same reading `profileActivityTag()` already
implements. `Provider` is the trainer who ran it, i.e. `created_by`. Each role counts as **one**
development opportunity.

### The same rule applies to the counter — and the counter does not follow it today

The sentence above is a statement about the **فرص تطور counter**, not only the filter. Neither half
of it currently holds.

**a. Attending a development opportunity counts nothing.**
`SyncService::syncUser()` computes the participated figure from volunteer opportunities only:

```php
$strictTotalOpportunities = VolunteerOpportunity::query()
    ->where('is_deleted', false)
    ->whereHas('registrations', function ($q) use ($user) {
        $q->where('user_id', $user->id)
            ->where('is_deleted', false)
            ->whereHas('attendances', fn ($aq) => $aq->where('is_attended', true)->where('is_deleted', false));
    })
    ->distinct()
    ->count('volunteer_opportunities.id');
```

`LearnServeOpportunity` is not in the query at all, so an attended course or workshop adds nothing to
`total_opportunities`.

Good news: the **BE-47 part 2** blocker is gone — `requiresCheckIn()` now matches `class/workshop`,
so a workshop's registrations are credited on completion again. Adding learn & serve to this query
will therefore actually count them.

**b. The Provider side is exposed but never computed.**
`volunteer_profiles.opportunities_organized` is fillable, returned by four resources
(`VolunteerProfileResource:59`, `VolunteerPublicProfileResource:57`,
`WebsitePublicProfileResource:57`, `VolunteerVerificationResource:25`) and carried in the
`statistics.all_time` block — but **nothing ever writes it**. The only assignment anywhere is a
`firstOrCreate` default of `0` in `AttendanceService.php:169`; `SyncService`'s
`$volunteer->update([...])` writes hours, opportunities, certificates and badge, and not this.

So a volunteer who runs courses shows `opportunities_organized: 0` forever — even though the backend
already knows they created one, since that is exactly what both the "خبير / Expert" badge and the new
`profile_activity_tag: provider` are derived from.

**Fix**, all in `SyncService::syncUser()`:

1. Include learn & serve in the participated count — opportunities with an attended registration for
   this user.
2. Compute `opportunities_organized` from `LearnServeOpportunity::where('created_by', $user->id)`
   (approved and not deleted; decide whether it must also be completed).
3. **Please confirm how it should be displayed.** The client's wording — "تُحسب فرصة تطور واحدة" for
   each role — reads as a *single* development-opportunities figure that both roles feed. If that is
   right, the API should expose that combined number rather than leaving the frontend to add two
   fields together; if the two should stay separate on screen, say so and the profile will show them
   as two counters.

### Frontend status — chips shipped, inert until the filter lands

Both screens show an **All / مشارك / مقدم** chip row above the opportunities listing, appearing next
to the existing All / Volunteer / Development chips **while Development is selected**:

- `/volunteer-profile` → `ProfileDescriptionTabs.tsx` → `ProfileVolunteerCard`
- `/public-profile/{id}` → `CommonProfile.tsx`

The selected value rides in `FiltersData.profile_activity_tag` and is sent as `profile_activity_tag`
on both listing calls; "All" omits the param entirely. There is no client-side filtering — the API
owns it, so the paged list is always the list the server returned.

An unknown query param is ignored by `$request->validate()`, so **today all three chips return the
same list**. Nothing on the frontend needs to change once the param is honoured, except the casing
question flagged at the top of this issue.

---

## BE-55 — Organization counters (incl. sponsorship) are computed but never returned: `/public-profile/{id}/` and `/organization-profile/` always answer `0`

**Severity: HIGH — open. Verified by reading branch `updates` (`0fc0a37`).**

### The product rule

> كاونتر الرعاية يحسب فقط (فرص التطوع - فرص التطور) لا يحسب الفعاليات.

The sponsorship counter counts sponsoring in **opportunities only** — volunteer
opportunities and development (learn-serve) opportunities. Sponsored **events**
must not be counted.

### What the backend already does right — no change needed here

`SyncService::syncOrganization()` (`app/Services/Opportunity/SyncService.php:298-315`)
counts exactly the right set:

- It iterates `OpportunitySponsorImage` rows for the organization. That model
  (`app/Models/OpportunitySponsorImage.php:13-31`) carries only
  `volunteer_opportunity_id` + `learn_serve_opportunity_id` — there is **no
  event foreign key**. Event sponsorships live in the separate
  `EventSponsorImage` model/table, so they can never leak into this count. The
  opportunities-only rule holds **by schema**, not by a filter someone could
  drop.
- Each row resolves `$img->volunteerOpportunity ?? $img->learnServeOpportunity`
  and counts it only when the opportunity is not deleted, has an `end_date`,
  and is `COMPLETED`; paid learn-serve rows are skipped.

So the stored `organization_statistics.sponsored` value already is
"opportunities, not events". Please do not change the counting rule — the bug
is purely that no profile endpoint returns the stored value.

### The bug: the rollup is written and never read

Counter columns live on **`organization_statistics`** (monthly rows plus
`month = NULL` yearly rollups — migration
`2024_01_01_000002_create_profile_tables.php:96-104`, model
`app/Models/OrganizationStatistic.php:12`). `OrganizationProfile` itself has no
counter columns and no accessors (`app/Models/OrganizationProfile.php:15-29`
fillable). But both profile serializers read the counters from the wrong place:

1. **`WebsitePublicProfileResource:98-101`** — the actual
   `GET /api/public-profile/{id}/` (`AuthController::publicProfile:589`):
   reads `$organization?->organization_hours`,
   `->learn_opportunity_organized`, `->vol_opportunity_organized` and
   `->sponsored_count` off `OrganizationProfile`. None exists, so **all four
   entity counters are always `null`** — hours, both organized counts, and
   sponsorship. Volunteer teams go through the same resource, which is why
   every team profile shows all zeros even with organised activity.
2. **`Organization/OrganizationProfileResource:52-55`** — the owner's own
   `/organization-profile/` — hardcodes all four (`organization_hours`,
   `learn_opportunity_organized`, `vol_opportunity_organized`,
   `sponsored_count`) to literal `0`.
3. **`Auth/OrganizationPublicProfileResource:54-57`** hardcodes the same four
   to `0`. It is only reachable via the legacy `Auth\PublicProfileResource`
   delegation — please confirm whether any live route still uses it, and
   either fix or delete it so the next reader does not copy the pattern.

The only reader of the rollup anywhere is the leaderboard
(`VolunteerStatisticsController:202` sums the same table), which is why the
top-teams screen can show numbers the profiles themselves report as zero.

### Fix

Return the all-time rollup (sum over the user's `organization_statistics`
rows, or the `month = NULL` yearly rows summed) in both resources:

- `WebsitePublicProfileResource` (organization branch): `organization_hours`,
  `vol_opportunity_organized`, `learn_opportunity_organized`, `sponsored`.
- `Organization/OrganizationProfileResource`: the same four under its existing
  keys (`sponsored_count` for the sponsored one).

Keep the key names exactly as they are — the frontend already accepts both
sponsored spellings and needs no change. Do not add events to the count; the
current computation is the specified behaviour.

### Proof required

1. `GET /api/public-profile/{id}/` for one entity known to sponsor at least
   one completed opportunity: show a non-zero `sponsored` that equals the rows
   in its sponsored-opportunities tab, and confirm sponsoring an event leaves
   the counter unchanged.
2. The same endpoint for one active volunteer team: non-zero
   `organization_hours` / `vol_opportunity_organized` /
   `learn_opportunity_organized` matching its organized tabs.
3. `GET /api/organization-profile/` (owner view) showing the same four
   non-zero values instead of the hardcoded zeros.

### Frontend status — nothing to change for sponsorship

`CommonProfile.tsx` renders `profile.sponsored ?? profile.sponsored_count`
verbatim. The API sends a single number that already excludes events, so there
is no client-side split to make — the counter will read correctly the moment
the endpoints return the stored rollup. (The volunteer development card in the
same file now reads the `development_opportunities_count` this branch already
emits, with the legacy aliases as fallback.)

---

## Verification that could not be performed here

This workspace has **no PHP runtime, no Composer, and no installed `vendor/`**, so:

- `php artisan test` was **not** run — the backend team's own suite run (29 tests / 698 assertions)
  is the only execution evidence for the closed items.
- `php artisan route:list` was **not** run — the route table used for this audit was parsed directly
  from `routes/api.php`, including every `Route::match([...])` verb list.
- No endpoint was exercised against a live server or database.

Everything marked *Closed in `updates`* above was verified by **reading the branch** — the call site,
the rule, the migration — not by trusting the report. Please still confirm the deployment-only items
the team listed: the production `EXPOSE_OTP_IN_RESPONSE` value, any pre-existing `event_attendances`
rows before the drop migration runs, the live `learning_type` rows, and the two backfill commands
(`fursa:backfill-sanitize-rich-text`, `fursa:backfill-generated-link`).

---

## ✅ Final task — send back a completion report when everything is done

**This is the last task in this file. Please do it after all the work above is finished.**

**هذه آخر مهمة في الملف — من فضلك نفّذها بعد الانتهاء من كل الشغل اللي فوق.**

Thank you for the last round — `FURSA_BACKEND_DONE.md` was clear, and every item it claimed checked
out against the branch. **Same format again, please, for what is left.**

شكرًا على الجولة اللي فاتت — التقرير كان واضح، وكل نقطة فيه اتأكدنا منها في الكود. **نفس الشكل تاني
من فضلك، للباقي.**

### What is left / الباقي

**BE-44** (partially fixed), **BE-50**, **BE-51**, **BE-52**, **BE-53**, **BE-54** (partially done),
**BE-55**.
Nothing else — the other ten are closed.

### What the file must contain / اللي لازم يكون في الملف

1. **The confirmation phrase, exactly as written below.** This is how we know the whole job is
   finished — without it we treat the work as still in progress:

   **العبارة دي بالظبط زي ما هي مكتوبة تحت** — بيها نقدر نتأكد إنك خلصت كل الشغل، ومن غيرها
   هنعتبر الشغل لسه شغال:

   ```
   مساء الفل يا ميدو
   ```

2. **A per-issue status line** for each of BE-44, BE-50 … BE-55 — **Done**, **Partially done** (and
    what is left), or **Not done** (and why). Please keep the same BE ids so the two files line up.

    **سطر حالة لكل مشكلة** من BE-44 و BE-50 … BE-55 — **خلصت**، ولا **خلصت جزئيًا** (وإيه الباقي)،
    ولا **لسه** (وليه). خلّي نفس أرقام BE عشان الملفين يتطابقوا.

3. **Answers to the open questions**, because some fixes depend on a product decision rather than on
   code:

   **إجابات على الأسئلة المفتوحة**، لأن فيه حاجات محتاجة قرار من المنتج مش كود:

   - **BE-50** — whether a non-Kuwaiti non-resident should be able to sign up socially at all, or
     whether that path stays civil-ID only by design.
   - **BE-51** — which of `Association` / `Community` existing `Society` organizations were repointed
     to, and whether the `society` licence role was renamed or kept.
   - **BE-52** — whether the `name` search filter should still apply to volunteers once the name is
     no longer returned.
   - **BE-54** — whether the profile should expose one combined development-opportunities figure
     (participant + provider) or the two separately, and which casing is canonical for
     `profile_activity_tag`. (Settled already: the tag is development-only, and `Participant` means
     attended.)

4. **How each fix was verified** — the test, the endpoint call, or the screen you checked. Several
   findings in this file survived precisely because a unit test passed while the code under test was
   never wired up (see the BE-37 note), so "the tests are green" on its own is not enough.

   **إزاي اتأكدت من كل إصلاح** — التست، أو الـ endpoint اللي جربته، أو الشاشة اللي شوفتها.
   فيه أكتر من مشكلة عاشت بالظبط لأن التست كان بينجح والكود اللي بيتّست عليه مش متوصّل أصلًا،
   فـ "التستات كلها خضرا" لوحدها مش كفاية.

5. **Anything you changed that is not in this file** — a schema change, a renamed field, a new
   required parameter. Any of those can break the frontend silently, so we need to know before it
   ships.

   **أي حاجة غيّرتها مش موجودة في الملف ده** — تغيير في الداتابيز، اسم حقل اتغير، باراميتر جديد
   مطلوب. أي واحدة من دول ممكن تكسر الفرونت من غير ما حد ياخد باله، فمحتاجين نعرف قبل ما ينزل.

### Done means / يعني إيه خلصت

All of: the code is written, it is reviewed, the behaviour is verified on a running instance, and the
answers above are filled in. If any issue is still open, say so plainly in the file rather than
leaving it out — a partial report with honest gaps is more useful to us than a complete-looking one.

الكود اتكتب، واتراجع، والسلوك اتأكدنا منه على نسخة شغالة، والأسئلة اللي فوق اتجاوبت. لو فيه أي
نقطة لسه مفتوحة، اكتبها بصراحة في الملف بدل ما تسيبها — تقرير ناقص وواضح أنفع لينا من تقرير
شكله كامل.
