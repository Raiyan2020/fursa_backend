# Fursa — backend issues

Findings from the end-to-end integration audit of `fursa-next` against `fursa_backend`.

**Re-verified 2026-09-15 against branch `updates` (`38068bd`).** The backend team's
`FURSA_FRONTEND_HANDOFF.md` described a contract covering the seven issues that were still open;
**all seven are confirmed resolved by reading the branch** and have been moved to _Closed_ below.
Nothing from the earlier rounds has regressed.

**Open: BE-56 only** — a small side effect of the BE-50 fix, raised by this review, with a frontend
workaround already shipped. Plus one product question the round did not answer (licence exemption
for `Governmental`), listed under *Open questions*.

IDs continue the existing sequence in `fursa-next/docs/BACKEND_ISSUES.md`, which ends at BE-34.

| ID | Severity | Status | Title |
|---|---|---|---|
| [BE-56](#be-56--account-now-demands-an-identity-document-on-saves-that-never-touch-one) | **LOW** | **Open — frontend worked around** | `/account/` now demands an identity document on saves that never touch one |

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

## BE-56 — `/account/` now demands an identity document on saves that never touch one

**Severity: LOW — new, introduced by the BE-50 fix. A frontend workaround is already shipped, so
this is not blocking; it is filed so the backend behaviour gets fixed at the source rather than
depending on one screen remembering to compensate.**

### What happens

`AuthController::updateAccount()` runs `IdentityDocumentValidator::validate()` for **every**
volunteer account save, on a request that may only contain a phone number:

```php
if ($user->isVolunteer()) {
    $identityValidator = ValidatorFacade::make($request->all(), []);
    $identityValidator->after(function ($validator) use ($request, $user) {
        IdentityDocumentValidator::validate($request, $validator, $user->id);
    });
    $identityValidator->validate();
}
```

The fallbacks just above it are the right idea and cover the normal case:

```php
if (! $request->has('civil_id') && $user->civil_id) {
    $request->merge(['civil_id' => $user->civil_id]);
}
```

But each one is guarded on the stored value being **non-empty**. When it is empty, nothing is
merged, the validator sees no identifier, and the save fails:

| Stored state | Request | Result |
|---|---|---|
| Kuwaiti with `civil_id` | `phone_number` only | ✅ fallback fills `civil_id` |
| Non-resident with `passport_number` | `phone_number` only | ✅ fallback fills both |
| **`nationality` and `civil_id` both null** | `phone_number` only | ❌ **422 `civil_id` — required** |

The third row is not hypothetical. Before BE-50, `socialAuth()` validated `civil_id` as
`['nullable', 'string', 'max:12']` and asked for no nationality at all, so **every volunteer who
signed up with Google or LinkedIn before this branch has both columns null.** BE-50 only tightened
the rule for *new* social signups; it did not backfill the existing ones.

### Why it was a hard block

`VolunteerAccountInformation` saves in two calls: `/account/` first, then `/volunteer-profile/`. The
identity fields live on the profile call. So an affected user editing their phone number got a 422
naming `civil_id` from the first call, the submit aborted before the second, and the profile call
that would have supplied the missing `civil_id` never ran — the account could not repair itself
through the UI at all.

### What the frontend did

`VolunteerAccountInformation.tsx` now appends the current nationality/residency selection and the
matching identifier to the `/account/` request **whenever that request is already being made**, so
the check sees the real answer instead of falling back to an empty column, and the stored row is
repaired as a side effect. It deliberately does not make those fields a reason to call `/account/`
on their own. That unblocks the screen, and `/account/` already accepts all four keys, so nothing
trips `rejectUnknownWriteKeys`.

### Recommended fix

Either of these is fine, and they are not exclusive:

1. **Validate identity only when the request is actually about identity** — skip the check when none
   of `nationality`, `residency_status`, `civil_id`, `passport_number` is present in the request and
   none is stored. A request that touches no identity field should not be able to fail on one.
2. **Backfill the legacy rows**, or treat a null `nationality` as "not yet answered" rather than as
   the Kuwaiti default. `Nationality::tryFromInput(null)` returning `null` currently falls through
   to `requireUniqueCivilId()`, which silently makes "unanswered" mean "Kuwaiti".

The same guard exists in `VolunteerProfileUpdateRequest::prepareForValidation()`. It is not reachable
today because the only caller always sends the fields, but it will bite the same way the first time
another screen patches the profile partially — a visibility toggle, say.

### Frontend status

**Worked around, not waiting.** The fix above can land whenever; the frontend behaviour is correct
either way and the extra fields on `/account/` remain harmless once it does.

---

## Open questions

One decision from the last round is still unanswered. It is a product call, not a bug.

- **Licence exemption for `Governmental`.** `fursa-next/src/data/orgTypes.ts` exempts `Association`,
  `Community`, `Society` and `Public` from the sign-up licence upload, and deliberately **does not**
  exempt `Governmental` (or its predecessor `Institution`). The reason is that the first restructure
  folded both `Public` (exempt) and `Government` (not exempt) into `Institution`, so the correct
  answer for the merged type was genuinely ambiguous. The frontend currently errs toward *asking*
  for a licence, because the backend rejects an unnecessary licence far more gracefully than it
  accepts a missing one. **Please confirm whether a governmental body should be asked for a licence
  at sign-up** — `GET /api/check-license-requirement/` cannot answer this, because there is no token
  yet at that point in the flow.

---

## Verification that could not be performed here

This workspace has **no PHP runtime, no Composer, and no installed `vendor/`**, so:

- `php artisan test` was **not** run — the backend team's own suite runs are the only execution
  evidence for the closed items.
- `php artisan route:list` was **not** run — the route table used for this audit was parsed directly
  from `routes/api.php`, including every `Route::match([...])` verb list.
- No endpoint was exercised against a live server or database.

Everything marked *Closed* above was verified by **reading the branch** — the call site, the rule,
the migration — not by trusting the report. Two things still need confirming on a running instance,
because they are data rather than code:

- **The two new migrations have actually run in each environment.**
  `2026_09_15_000001_restructure_organization_types_round_two` and
  `2026_09_15_000002_add_certificate_filter_choices` are the whole of BE-51 and half of BE-53. Until
  they run, `GET /api/choices/org_type/` still returns the old vocabulary and
  `GET /api/choices/certificate_filter_type/` returns nothing, and the frontend quietly falls back —
  the org dropdown keeps showing the old labels, the certificate chips show the hardcoded three. No
  error appears anywhere; it just looks stale.
- **Legacy identity rows** — how many volunteers have a null `nationality` or `civil_id`. That is
  the population BE-56 affects.

Also still outstanding from the earlier rounds: the production `EXPOSE_OTP_IN_RESPONSE` value, any
pre-existing `event_attendances` rows before the drop migration runs, the live `learning_type` rows,
and the two backfill commands (`fursa:backfill-sanitize-rich-text`, `fursa:backfill-generated-link`).

---

## ✅ Final task — send back a completion report when everything is done

**This is the last task in this file. Please do it after all the work above is finished.**

**هذه آخر مهمة في الملف — من فضلك نفّذها بعد الانتهاء من كل الشغل اللي فوق.**

Thank you — this was a genuinely good round. `FURSA_FRONTEND_HANDOFF.md` was the most useful thing
you have sent: it described the contract rather than the changes, which is exactly what the frontend
needs. Every claim in it checked out against the branch, and on BE-44 you took the structural fix
(the shared rule set) rather than the quick one. **Same format again, please.**

شكرًا — الجولة دي كانت كويسة فعلًا. ملف الـ handoff كان أنفع حاجة بعتّها: وصف الـ contract مش
التغييرات، وده بالظبط اللي الفرونت محتاجه. كل نقطة فيه اتأكدنا منها في الكود، وفي BE-44 عملت الحل
الصح (الـ rule set المشترك) مش الحل السريع. **نفس الشكل تاني من فضلك.**

### What is left / الباقي

**BE-56** only — and it is low, with a frontend workaround already shipped, so it is not urgent.
Everything else in this file is closed.

**BE-56** بس — ودي بسيطة، والفرونت عامل لها حل مؤقت خلاص، فمش مستعجلة. كل اللي غير كده في الملف
ده اتقفل.

### What the file must contain / اللي لازم يكون في الملف

1. **The confirmation phrase, exactly as written below.** This is how we know the whole job is
   finished — without it we treat the work as still in progress:

   **العبارة دي بالظبط زي ما هي مكتوبة تحت** — بيها نقدر نتأكد إنك خلصت كل الشغل، ومن غيرها
   هنعتبر الشغل لسه شغال:

   ```
   مساء الفل يا ميدو
   ```

2. **A status line for BE-56** — **Done**, **Partially done** (and what is left), or **Not done**
   (and why, including "won't fix" if that is the decision — that is a perfectly good answer here).

   **سطر حالة لـ BE-56** — **خلصت**، ولا **خلصت جزئيًا** (وإيه الباقي)، ولا **لسه** (وليه، ولو
   القرار إنها مش هتتعمل قول كده — ده رد مقبول تمامًا في الحالة دي).

3. **An answer to the one open question** under *Open questions* above: whether a governmental
   organization should be asked for a licence at sign-up. This is a product decision, not code — the
   frontend cannot infer it and currently guesses toward asking.

   **إجابة على السؤال المفتوح** اللي فوق: هل الجهة الحكومية يتطلب منها ترخيص وقت التسجيل؟ ده قرار
   منتج مش كود — الفرونت مش هيقدر يعرفه لوحده وحاليًا بيسأل عن الترخيص احتياطيًا.

4. **Migration status per environment** — confirm that
   `2026_09_15_000001_restructure_organization_types_round_two` and
   `2026_09_15_000002_add_certificate_filter_choices` have run wherever the frontend points. Both
   fail silently from the frontend's side: the dropdowns just keep showing the old values, with no
   error to notice.

   **حالة الـ migrations في كل بيئة** — أكّد إن الاتنين اشتغلوا في السيرفر اللي الفرونت شغال عليه.
   الاتنين بيفشلوا بصمت من ناحية الفرونت: القوائم بتفضل تعرض القيم القديمة من غير أي رسالة خطأ.

5. **How each fix was verified** — the test, the endpoint call, or the screen you checked. Several
   findings in this file survived precisely because a unit test passed while the code under test was
   never wired up (see the BE-37 note), so "the tests are green" on its own is not enough.

   **إزاي اتأكدت من كل إصلاح** — التست، أو الـ endpoint اللي جربته، أو الشاشة اللي شوفتها.
   فيه أكتر من مشكلة عاشت بالظبط لأن التست كان بينجح والكود اللي بيتّست عليه مش متوصّل أصلًا،
   فـ "التستات كلها خضرا" لوحدها مش كفاية.

6. **Anything you changed that is not in this file** — a schema change, a renamed field, a new
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
