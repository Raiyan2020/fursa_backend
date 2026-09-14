# Fursa — backend issues

Findings from the end-to-end integration audit of `fursa-next` against `fursa_backend`
(backend `master` @ `485e511`, frontend `main` @ `da6c9a3`), 2026-09-11.

**Re-verified 2026-09-13 against backend `master` @ `bfddf37`**
("feat: Implement organization approval checks and enhance OTP security").
Four of the six original findings are fixed and have been removed from this file; two remain
open. **BE-45 … BE-49 were added 2026-09-14** — from the public/private opportunity review, the
profile certificate-visibility review, the development-opportunity learning-type review, and the
calendar-feature restore (BE-48 and BE-49) respectively.
**BE-41 was added 2026-09-13** from the events-scope review, and **BE-42 … BE-44 on the
same day** from the form-parity audit (the six create/update/republish flows for events,
volunteer opportunities and learn & serve, compared against both the API validators and the
admin dashboard). No backend implementation file was modified by this audit.

IDs continue the existing sequence in `fursa-next/docs/BACKEND_ISSUES.md`, which ends at BE-34.

| ID | Severity | Status | Title |
|---|---|---|---|
| [BE-37](#be-37--user-authored-html-is-stored-and-served-unsanitized-stored-xss-source) | **HIGH** | **Open — fix written but never called** | User-authored HTML is stored and served unsanitized (stored-XSS source) |
| [BE-36](#be-36--organization-approval-is-still-unchecked-on-most-organization-only-writes) | **MEDIUM** | **Partially fixed** | Organization approval is still unchecked on most organization-only writes |
| [BE-41](#be-41--events-expose-a-registrationattendance-cycle-the-product-does-not-have) | **MEDIUM** | **New — frontend already done** | Events expose a registration/attendance cycle the product does not have |
| [BE-43](#be-43--the-admin-dashboard-writes-only-the-legacy-interest-pivot-so-admin-tag-edits-are-silently-discarded) | **HIGH** | **New** | The admin dashboard writes only the legacy interest pivot, so admin tag edits are silently discarded |
| [BE-42](#be-42--an-empty-existing_image_ids-cannot-be-expressed-so-removing-every-image-is-impossible) | **MEDIUM** | **New** | An empty `existing_image_ids` cannot be expressed, so removing every image is impossible |
| [BE-44](#be-44--the-admin-form-and-the-api-validate-the-same-columns-differently) | **LOW** | **New** | The admin form and the API validate the same columns differently |
| [BE-45](#be-45--private-opportunities-cannot-be-joined-and-leak-into-the-combined-list) | **HIGH** | **New** | Private opportunities cannot be joined, and leak into the combined list |
| [BE-46](#be-46--every-certificate-endpoint-is-unauthenticated-so-anyone-can-read-and-download-any-volunteers-certificates) | **HIGH** | **New — frontend already done** | Every certificate endpoint is unauthenticated, so anyone can read and download any volunteer's certificates |
| [BE-47](#be-47--development-opportunities-certificates-are-not-gated-by-learning-type-and-the-type-names-the-code-matches-on-no-longer-exist) | **HIGH** | **New — frontend already done** | Development opportunities: certificates are not gated by learning type, and the type names the code matches on no longer exist |
| [BE-48](#be-48--my-calendar-saveunsave-cannot-be-built-no-calendar_id-is-ever-returned-the-saved-flag-ignores-deletions-and-events-have-no-flag-at-all) | **MEDIUM** | **New — frontend blocked** | "My calendar" save/unsave cannot be built: no `calendar_id` is ever returned, the saved flag ignores deletions, and events have no flag at all |
| [BE-49](#be-49--get-my-calendar-collapses-nine-entry-types-into-three-keeps-deleted-items-on-the-calendar-and-loads-every-registration-on-every-request) | **MEDIUM** | **New — frontend already done** | `GET /my-calendar/` collapses nine entry types into three, keeps deleted items on the calendar, and loads every registration on every request |

## Closed in `bfddf37`

Verified by reading the commit and the current tree; not re-tested against a running instance
(this workspace still has no PHP runtime — see *Verification that could not be performed here*).

- **BE-35 — `is_banned` never enforced.** *Fixed.* The `expiring-token` guard resolver now
  returns `null` for a banned, inactive or deleted owner (`app/Providers/AuthServiceProvider.php:47-59`),
  so existing tokens stop authenticating immediately. `login()` returns a `403` with a
  localized "account has been suspended" message before issuing a token
  (`AuthController.php:115-123`). `User::revokeAllTokens()` was added and is called from
  `Admin/UserController::ban()`, `Admin/OrganizationProfileController::reject()` and
  `CheckAndBanNonAttendingCommand`, so the auto-ban path is covered too.
- **BE-38 — OTPs written to the log in plaintext.** *Fixed.* The `'otp' => $otp->otp` key is
  gone from both `Log::info` calls in `app/Services/Auth/AuthService.php` (~lines 142, 196);
  `user_id` and `otp_id` remain. `mail_username` was dropped from the mail-diagnostic log line
  as well.
- **BE-39 — No per-account attempt limit on OTP/login.** *Fixed.* Two named limiters were added
  in `RouteServiceProvider` — `auth-attempts` (5/min per IP **and** per submitted email) on
  `login/` and `verify_otp_or_token/`, and `auth-send` (3/min per IP, 10/hour per email) on
  `register/`, `forgot-password/` and `resend_otp_or_token/`. An `attempts` column was added to
  `otp_verifications` (migration `2026_09_11_000001`), and
  `OtpVerification::registerFailedAttempt()` burns the code at `MAX_ATTEMPTS = 5`. Wrong and
  expired OTPs still throw the same `Invalid or expired OTP` message, so the endpoint does not
  confirm which codes existed.
- **BE-40 — `EXPOSE_OTP_IN_RESPONSE` could leak OTPs in production.** *Fixed.*
  `config/fursa.php:11-19` now short-circuits to `false` when `APP_ENV === 'production'`, before
  the env var is read, so the override cannot re-enable it. *Still outstanding on your side:*
  please confirm the deployed production `.env` — it was not readable from this workspace, so
  this was only ever reported as a risk, not an observed leak.

---

## BE-37 — User-authored HTML is stored and served unsanitized (stored-XSS source)

**Severity: HIGH — still open**

> ### ⚠️ Not resolved in `bfddf37` — the sanitizer is dead code
>
> `bfddf37` added `app/Support/HtmlSanitizer.php` (264 lines, an allowlist-based cleaner with
> `clean()` and `cleanFields()`) and `tests/Unit/HtmlSanitizerTest.php` (146 lines, covering
> `<script>`, `onerror`, `javascript:` URLs, `<marquee>`, Arabic content and `target="_blank"`).
> The class is correct as far as the tests go — **but nothing in the application calls it.**
>
> ```
> $ grep -rn "HtmlSanitizer\|cleanFields\|::clean(" app/ | grep -v app/Support/HtmlSanitizer.php
> (no matches)
> ```
>
> The only references anywhere in the repo are inside the unit test. No controller, form request,
> model mutator or middleware invokes it, so **every byte of rich-text HTML is still stored and
> served exactly as submitted.** The validators are unchanged — `VolunteerOpportunityController.php:592`
> is still `'description_en' => [$partial ? 'sometimes' : 'required', 'string']`, and
> `EventController.php:360` is still `'description_en' => ['nullable', 'string']`.
>
> **What is left to do**
>
> 1. Call `HtmlSanitizer::cleanFields($data, ['description_en', 'description_ar'])` on the
>    validated payload in all three opportunity/event controllers, before the model is written:
>    - `Api/Opportunity/VolunteerOpportunityController.php::validateVolunteerPayload()`
>    - `Api/Event/EventController.php` (its validator, ~line 360)
>    - `Api/Opportunity/LearnServeOpportunityController.php`
>    - `Admin/FaqController.php` (`answer_en` / `answer_ar`) and the CMS page bodies
> 2. Run the one-off backfill over existing rows. Nothing stored before this change was ever
>    filtered, so wiring the sanitizer up only protects new writes.
> 3. Add a feature test that posts `<img src=x onerror=alert(1)>` as `description_en` through the
>    real endpoint and asserts the persisted column is clean — the current unit test would pass
>    unchanged even with the sanitizer entirely unwired, which is how this gap survived.

### Location

- `app/Http/Controllers/Api/Opportunity/VolunteerOpportunityController.php::validateVolunteerPayload()`
  (`'description_en' => [... 'string']`, `'description_ar' => [... 'string']`).
- The equivalent validators in `Api/Event/EventController.php` and
  `Api/Opportunity/LearnServeOpportunityController.php`.
- `Admin/FaqController.php` (`answer_en` / `answer_ar`).
- `app/Support/HtmlSanitizer.php` — added in `bfddf37`, currently unreferenced.

### Related API

`POST /volunteer-opportunities/`, `POST /events/`, `POST /learn-serve-opportunities/` and their
update verbs; read back through `GET /opportunities/{id}/details/`, `GET /events/{id}/`,
`GET /learn-serve-opportunities/{id}/`, `GET /faqs/`.

### Current behavior

Descriptions are authored in the frontend's TipTap rich-text editor and stored as raw HTML.
The backend validates them as `string` only. Whatever markup is submitted is stored verbatim and
returned verbatim to every reader of the public detail endpoints.

### Expected behavior

HTML accepted from a non-administrator should be sanitized against a tag/attribute allowlist
before it is stored (or at minimum before it is served), so that stored markup cannot carry
script.

### Why this is a backend issue

The frontend fix (applied — see *Frontend status*) protects this application's own pages, but
the stored value is still hostile. It is served to every other consumer of the same endpoints —
the admin dashboard's Blade views, any export, any future client — none of which inherit the
Next.js app's sanitizer. Sanitizing once on write is the only place that covers all readers,
and defense in depth is the point: two independent layers, not one.

### Impact

Before the frontend fix, any account able to create an opportunity or event could store markup
such as `<img src=x onerror="...">`, which executed for every visitor to the public detail page.
The site's CSP does not mitigate it: `script-src` includes `'unsafe-inline'`
(`fursa-next/next.config.ts`), which permits inline event handlers, and `img-src ... https:`
permits exfiltration to an arbitrary origin. The auth token is in `localStorage`, so the payload
could steal the session of any viewer, including an administrator.

### Evidence

- `grep -rn "HtmlSanitizer\|cleanFields\|::clean(" app/` → matches only inside
  `app/Support/HtmlSanitizer.php` itself.
- `grep -rn "strip_tags\|HTMLPurifier\|Purifier" app/` → no matches.
- `VolunteerOpportunityController.php:592`, `EventController.php:360` — validators unchanged.
- Frontend render sites (sanitized client-side): `EventDetails.tsx:750`,
  `VolunteerEvent.tsx:1641`, `LearnServeDetails.tsx:1291`, `Faq.tsx:164`.
- `fursa-next/next.config.ts` — `script-src 'self' 'unsafe-inline' …`.

### Recommended fix

Wire the sanitizer that already exists — see the three numbered steps in the box above. Allow
roughly the tag set the editor emits (`p, br, h1-h6, strong, em, u, s, ul, ol, li, blockquote,
pre, code, a, img, table…`) and drop every `on*` attribute, `<script>`, `<iframe>`, `<style>`,
and any `javascript:` URL — `HtmlSanitizer` already does this; it just needs to be called.

### Frontend status

**Frontend fixed, no further frontend change required.**
`fursa-next/src/lib/sanitizeRichText.ts` sanitizes this HTML with DOMPurify before it reaches
`dangerouslySetInnerHTML`, in all four render sites. That closes the hole for this client; it
does not make the stored data safe for other consumers.

---

## BE-36 — Organization approval is still unchecked on most organization-only writes

**Severity: MEDIUM — downgraded from HIGH; the main path is fixed**

> ### ⚠️ Partially resolved in `bfddf37`
>
> **What was fixed.** `OrganizationApprovalGate` (`app/Support/OrganizationApprovalGate.php`) is
> now the single definition of "this organization may act", shared by `login()` and a new
> `EnsureOrganizationApproved` middleware registered as `api.approved-org`
> (`app/Http/Kernel.php:65`). `Admin/OrganizationProfileController::reject()` now calls
> `$entity->user?->revokeAllTokens()`, so clicking **Reject** does sign the organization out
> immediately. That closes the headline case from the original report.
>
> **Gap 1 — the middleware is on 5 routes out of ~20 organization-only writes.** It was attached
> only to the three `store` routes and the two `messageRegistrants` routes:
>
> ```
> $ grep -n "api.approved-org" routes/api.php
> 110  POST volunteer-opportunities/            store
> 126  POST learn-serve-opportunities/          store
> 141  POST volunteer-opportunities/{id}/registrations/message/
> 169  POST learn-serve-opportunities/{id}/registrations/message/
> 239  POST events/                             store
> ```
>
> Ungated organization-privileged writes still include: `volunteer-opportunities/{id}/`
> (update, line 112), `close-registration/` (113, 128, 243), `update_images/` (118, 129),
> **`volunteer-opportunities/{id}/certificates/send/` (119)**,
> `learn-serve-opportunities/{id}/` (update, 127), the two `registrations/status/` bulk updates
> (140, 168), `update-attendance/` (170), `events/{id}/` (update, 240), and
> **`certificates/{registration_id}/issue/` (306)**. A rejected organization holding a live token
> can still edit every opportunity it owns, close registration, change attendance and registration
> statuses, and issue certificates.
>
> **Gap 2 — the admin edit form changes status without revoking tokens.**
> `Admin/OrganizationProfileController::update()` (lines 35-49) validates
> `'organization_status' => ['required', Rule::in(ApprovalStatus::values())]` and calls
> `$entity->update($data)` with no `revokeAllTokens()`. An admin who moves an organization back to
> `PENDING` or sets `REJECTED` from the edit screen — rather than via the Reject button — leaves
> the token live, and Gap 1 means the middleware will not catch it on most endpoints either.
>
> **What is left to do**
>
> 1. Attach `api.approved-org` to the remaining organization-only write routes listed above.
>    Applying it to a route group rather than per-route would stop this drifting again.
> 2. Call `revokeAllTokens()` from `OrganizationProfileController::update()` whenever the saved
>    `organization_status` is not `APPROVED` (compare against the original value, so a no-op save
>    on an approved org does not sign it out).

### Location

- `app/Support/OrganizationApprovalGate.php` — the shared check (added `bfddf37`).
- `app/Http/Middleware/EnsureOrganizationApproved.php` — the middleware (added `bfddf37`).
- `routes/api.php` — 5 routes carry `api.approved-org`; the rest do not.
- `app/Http/Controllers/Admin/OrganizationProfileController.php:35-49` — `update()`, no revocation.

### Related API

All organization-only routes that still lack the gate — `PUT/PATCH /volunteer-opportunities/{id}/`,
`PUT/PATCH /events/{id}/`, `PUT/PATCH /learn-serve-opportunities/{id}/`,
`POST /volunteer-opportunities/{id}/certificates/send/`,
`POST /certificates/{registration_id}/issue/`,
`PATCH /volunteer-opportunities/{id}/registrations/status/`,
`PATCH /learn-serve-opportunities/{id}/registrations/status/`,
`PATCH /learn-serve-opportunities/{id}/update-attendance/`, the `close-registration/` and
`update_images/` verbs.

### Expected behavior

Withdrawing approval should withdraw access. An organization that is rejected, or moved back to
pending, should stop being able to act as an approved organization — on every privileged route,
and regardless of which admin screen changed the status.

### Why this is a backend issue

The check is an authorization gate on privileged write endpoints. A client-side check would be
advisory only — the organization's own browser is the untrusted party here.

### Impact

Reduced from the original report but not eliminated. Clicking **Reject** now works. Editing the
status on the entity edit form does not: that organization keeps a valid token and, because the
middleware covers only the `store` and `message` routes, retains the ability to modify its
existing opportunities and events, alter attendance and registration statuses, and issue
certificates — until the token expires (up to 30 days).

### Evidence

- `grep -n "api.approved-org" routes/api.php` → 5 lines (110, 126, 141, 169, 239).
- `app/Http/Controllers/Admin/OrganizationProfileController.php:46` — `$entity->update($data)`
  with `organization_status` in `$data` and no token revocation.
- `app/Http/Controllers/Admin/OrganizationProfileController.php:81` — `reject()` *does* revoke.

### Frontend status

**Frontend correct, no change required.** `ProtectedRoute` gates organization pages on
`user_type` and the app surfaces the API's `403` message. `organization_status` is carried on the
user object (`src/store/authStore.ts:20`) and is available if the UI should also explain the
state, but no frontend change can enforce the rule.

---

## BE-41 — Events expose a registration/attendance cycle the product does not have

**Severity: MEDIUM — new, raised 2026-09-13**

### The product rule

**On Fursa, an event is an announcement and nothing else.** The organizer publishes it and
collects sign-ups on their own channel — their website, WhatsApp, or Instagram — through the
event's `registration_link`. Fursa does not take the registration, does not record who showed
up, and issues no certificate for an event. Volunteer opportunities and learn & serve
opportunities are the surfaces that *do* own a participation record; events must not.

### What the backend gets right today

Worth stating, because most of the risk people expect here is genuinely absent:

- **No certificates.** `grep -rniE "certificate" app/ | grep -i event` returns nothing. No event
  path reaches `VolunteerCertificateService`, `CertificateController`, or the certificate tables.
- **No hours, badges or statistics.** `app/Services/Opportunity/SyncService.php` — which computes
  volunteer hours, badges, and both volunteer and organization statistics — never imports or
  references `Event`.
- **No QR check-in.** `VolunteerAttendanceController::scan()` rejects events outright:
  `app/Http/Controllers/Api/Opportunity/VolunteerAttendanceController.php:50` returns **501
  "Event attendance is not implemented yet."**
- **No auto-crediting.** The event branch of `AdvanceOpportunityStatusesCommand` (lines 114-147)
  only advances `event_status`; the attendance-crediting block above it is gated to
  `LearnServeOpportunity` / `VolunteerOpportunity`.

So the rule is *nearly* respected. What follows is the remainder.

### Current behavior — the gap

**1. `event_registrations.is_attended` is a live, writable attendance record.**

- Writable by the organizer via `PATCH /event-registrations/{id}/` —
  `app/Http/Controllers/Api/Event/EventRegistrationController.php:208` validates
  `'is_attended' => ['sometimes', 'boolean']`.
- Filterable via `?is_attended=` (`:34-35`) and sortable via the `'attendance' => 'is_attended'`
  mapping (`:53`).
- Exported as an **"Attended"** column in the XLSX (`:74` → `app/Support/RegistrationExport.php:17`).
- Serialized to clients in `app/Http/Resources/Event/EventRegistrationResource.php:21`.

**2. Events advertise an `'attended'` relationship tag and a register/unregister action state.**

- `app/Http/Resources/Event/EventResource.php:134` and
  `app/Http/Resources/Website/WebsiteEventResource.php:69` both append `'attended'` to
  `relationship_tags`.
- `WebsiteEventResource.php:109-110` emits `'action_state' => $event->actionState(...)` and
  `'is_full'`, so `HasActionState` returns `register` / `unregister` / `full` **for an event** —
  telling every consumer that Fursa takes event sign-ups.
- `:85-87, 121-123, 142-143` still serialize `registration_required`, `registered_volunteers_count`,
  `participants_needed`, `paid_registration`, `registration_fee` and `remaining_slots`.

**3. Scan permissions can be granted for events that nothing can ever scan.**

`ScanPermissionController::bulkUpdate()` and `list()` both accept and validate `event_id`
(lines 33, 56-73, 136, 151-166), including an "only the event creator" ownership check — yet the
only consumer, `VolunteerAttendanceController::scan()`, returns 501 for events. The write path
exists with no read path.

**4. Dead attendance schema.**

- `event_attendances` table (`database/migrations/2024_01_01_000004_create_event_tables.php:135-145`)
  with `attended_date`, `total_hours`, `is_attended` — and `app/Models/EventAttendance.php`.
  Neither is read or written anywhere:
  `grep -rn "EventAttendance\|event_attendances" app/ routes/ resources/` matches only the model
  itself, the `EventRegistration::attendances()` relation, and the migration.
- `Event::scanPermissions()` (`app/Models/Event.php:115`) is never called.

### Why this is a backend issue

Removing the UI does not remove the capability. The frontend work below is done, but every
endpoint above is still live and publicly callable with a valid organization token. Any other
client — the admin dashboard, a script, a future app — can still create event registrations and
mark people attended, and the API still *tells* callers that events are registrable through
`action_state`, `is_full` and `remaining_slots`. Only the backend can actually retire the cycle.

### Impact

Low security risk, real product and data-hygiene risk. Fursa stores participation records for
events it has no business recording, the XLSX export hands organizers an "Attended" column that
is meaningless, and the API contract contradicts the product rule — which is exactly how this
gets rebuilt by accident in the next client.

### Evidence

- `EventRegistrationController.php:34, 53, 74, 208`.
- `EventResource.php:37-40, 53, 134`; `WebsiteEventResource.php:69, 85-87, 106-110, 121-123, 142-143`.
- `ScanPermissionController.php:33, 56, 136, 151` vs. `VolunteerAttendanceController.php:50`.
- `grep -rn "EventAttendance\|event_attendances" app/ routes/ resources/` → model + relation + migration only.
- `grep -rniE "certificate" app/ | grep -i event` → no matches.
- `grep -n "use App\\Models" app/Services/Opportunity/SyncService.php` → no `Event`.

### Recommended fix — staged

**Stage 1 — remove the attendance record (do this first; it is self-contained).**

1. Drop `'is_attended'` from the validator in `EventRegistrationController::update()` (`:208`),
   the `?is_attended=` filter (`:34-35`) and the `'attendance'` sort key (`:53`).
2. Stop serializing it: remove `'is_attended'` from `EventRegistrationResource.php:21` and the
   `'attended'` tag from `EventResource.php:134` and `WebsiteEventResource.php:69`.
3. Give the event export its own header/row shape without the "Attended" column, or pass a flag
   to `RegistrationExport::download()` — today it is shared with learn-serve and scan-permissions.
4. Reject `event_id` in `ScanPermissionController::bulkUpdate()` and `list()` with the same 400
   the missing-parameter branch already returns, so the write path matches `scan()`'s 501.
5. Drop `event_attendances` in a new migration and delete `app/Models/EventAttendance.php`,
   `EventRegistration::attendances()`, and `Event::scanPermissions()`.
6. Drop `event_registrations.is_attended` in the same migration once 1-3 are deployed.

**Stage 2 — decide whether events keep registrations at all.** *Needs a product call before any
code.* If registration is entirely external, then `POST /events/{id}/register/`,
`POST /event-registrations/`, `POST|DELETE /events/{id}/unregister/`, the `/event-time-slots/*`
family, and the `events.registration_required` / `paid_registration` / `registration_fee` /
`participants_needed` columns are all dead weight, and `WebsiteEventResource` should stop
emitting `action_state`, `is_full`, `remaining_slots` and `registered_volunteers_count` for
events. Please confirm the intended end state before removing these — the frontend no longer
calls any of them, so there is no rush and no breakage either way.

**Not in scope:** event feedback (`/event-feedback/`, `/event-feedback-like/`) is open to any
logged-in user and is not gated on registration or attendance, so it is unaffected by either
stage. Flagging only because "is feedback still meaningful on an announcement?" is a reasonable
product question — it is not a correctness problem.

### Frontend status

**Frontend done (2026-09-13) — no further frontend change required.** `fursa-next` no longer
exposes any event registration or attendance cycle:

- **Deleted:** `features/events/components/EventRegisterList.tsx` (the organizer's registration
  table and its "mark attended" button), `features/events/components/EventThankyou.tsx`, and the
  routes `app/(main)/event-register-list/` and `app/(main)/event-thankyou/`.
- **`EventDetails.tsx`:** removed the register/unregister mutations, the `TimeSlotPicker`, the
  whole registration modal (register / unregister / fee / timeslot), and both "Registered list"
  buttons. The viewer-facing action is now a single button that opens `registration_link`
  externally; the creator keeps Edit/Repost.
- **`EventListCard.tsx` / `ProfileEventCard.tsx`:** card buttons no longer show Register or Full
  for events — non-creators get Details / View / Closed.
- **`services/eventsApi.ts`:** `registerForEvent`, `unregisterFromEvent`, `getEventTimeSlots`,
  `getEventRegistrations`, `updateEventRegistration` and `downloadEventRegistrations` are gone,
  with a comment pointing back at this issue so they are not re-added.
- `EventTimeSlotModal.tsx` is **kept** — it is generic and still used by `LearnServeForm.tsx`.

Verified with `npx tsc --noEmit` (clean) and `npx eslint` on the touched files (0 errors; only
the repo's pre-existing `no-img-element` warnings).

---

## BE-43 — The admin dashboard writes only the legacy interest pivot, so admin tag edits are silently discarded

**Severity: HIGH — new, raised 2026-09-13**

### Location

- `app/Http/Controllers/Admin/EventController.php:90, 138` — `$event->interests()->sync($interestIds)`.
- `app/Http/Controllers/Admin/VolunteerOpportunityController.php:100, 145` — same shape.
- `app/Http/Controllers/Admin/LearnServeOpportunityController.php:101, 146` — same shape.
- `app/Http/Controllers/Api/Concerns/SyncsOpportunityInterests.php:46-47` — the API's sync.
- `app/Http/Resources/Concerns/ResolvesApiPayloads.php:122-135` — `effectiveInterests()`.

### Related API

`GET /events/{id}/`, `GET /opportunities/{id}/details/`, `GET /learn-serve-opportunities/{id}/`
and every list endpoint that renders `interests` / `interest_display` — read back after an admin
edits tags in the dashboard.

### Current behavior

Interest tags live in **two** vocabularies: the legacy `interests` table (pivots `interest_event`,
etc.) and `master_choices` (pivots `master_choice_event`, etc.). The API writes **both**:

```php
$model->masterInterests()->sync($choices->pluck('id'));   // SyncsOpportunityInterests.php:46
$model->interests()->sync($legacyIds);                    // :47
```

The admin dashboard writes **only the legacy one**. `masterInterests` appears nowhere in the
whole `Admin/` namespace:

```
$ grep -rn "interests()->sync\|masterInterests" app/Http/Controllers/Admin/
…EventController.php:90,138                → interests()->sync($interestIds)
…VolunteerOpportunityController.php:100,145 → interests()->sync($interestIds)
…LearnServeOpportunityController.php:101,146 → interests()->sync($interestIds)
(no masterInterests anywhere)
```

Readers resolve through `effectiveInterests()`, which **prefers master and only falls back to
legacy when master is empty**:

```php
if ($master->isNotEmpty()) {
    return $master;          // ← admin's legacy edit never reached here
}
return collect($model->interests ?? []);
```

The two validators also point at different tables for the same field name — the admin uses
`Rule::exists('interests', 'id')` (`Admin/EventController.php:337`,
`Admin/VolunteerOpportunityController.php:449`), the API uses
`Rule::in(MasterChoice … 'event_interest')`. The id spaces are unrelated.

### Expected behavior

Editing an interest tag in the dashboard should change what the API returns for that record,
and the two surfaces should agree on which vocabulary `interest_ids` refers to.

### Why this is a backend issue

Both the write (admin controllers) and the read resolution (`effectiveInterests`) are server-side.
No client can compensate: the frontend correctly sends `master_choices` ids and correctly renders
whatever the resource returns.

### Impact

**An admin's tag edit is silently lost on every record that was created through the app.** Any
event or opportunity created via the API already has a non-empty `masterInterests` set, so
`effectiveInterests()` returns the stale master list and the admin's change is invisible — in the
app, in search, and in every interest filter. The dashboard shows the new tags (it reads
`$event->interests`), so the admin has no way to tell the edit did not take. It only appears to
work on records that have no master interests at all — legacy rows and admin-created ones.

### Evidence

- The three `grep` results quoted above.
- `SyncsOpportunityInterests.php:46-47` writes both pivots; no admin path does.
- `ResolvesApiPayloads.php:130-134` — master wins whenever it is non-empty.
- `Admin/EventController.php:250-252` — the dropdown is populated from
  `Interest::where('interest_type', EVENT)`, i.e. legacy ids.

### Recommended fix

1. Make the admin controllers call the same `syncOpportunityInterests()` the API uses, so both
   pivots stay in step. The trait already maps a master choice to (or creates) its legacy row, so
   this also keeps the dashboard's own read working.
2. Repoint the admin dropdown and the `interest_ids.*` rule at `master_choices` scoped by the
   matching choice type (`event_interest`, `volunteer_opportunity_interest`,
   `learnserve_opportunity_interest`), so both surfaces mean the same thing by `interest_ids`.
3. Decide whether the legacy `interests` table is still needed at all. If it is only a
   compatibility shim, a follow-up to drop it would remove this entire class of bug — but that is
   a separate piece of work, not part of this fix.

### Frontend status

**No frontend change required.** `fursa-next` already sends `master_choices` ids from
`/api/choices/*` on all three forms and renders `interest_display` as returned.

---

## BE-42 — An empty `existing_image_ids` cannot be expressed, so removing every image is impossible

**Severity: MEDIUM — new, raised 2026-09-13**

### Location

- `app/Support/MediaKeepSet.php:11-13` — `if (! $request->exists('existing_image_ids')) return null;`
- `app/Services/Opportunity/RepublishMedia.php:41-48` — `apply()`'s image copy.
- `app/Services/Opportunity/RepublishMedia.php:25-31` — `source()`'s "A source is required" throw.

### Related API

`POST|PUT|PATCH /events/{id}/`, `POST /volunteer-opportunities/`,
`POST /learn-serve-opportunities/` (republish goes through `store()` with `opportunity_id`), and
the corresponding update verbs.

### Current behavior

The keep-set contract is *"send `existing_image_ids[]` listing the images to keep; anything not
listed is dropped"*. Both consumers branch on **whether the key exists**:

```php
// MediaKeepSet::validate — update path
if (! $request->exists('existing_image_ids')) {
    return null;                     // → apply() deletes nothing
}

// RepublishMedia::apply — republish path
if ($request->exists('existing_image_ids')) {
    $images->whereIn('id', $request->input('existing_image_ids'));
}                                    // absent → clones the ENTIRE source gallery
```

The clients are `multipart/form-data`, and **an empty array has no multipart representation**.
`formData.append("existing_image_ids[]", …)` called zero times sends no key at all, which the
backend reads as "keep everything" — the exact opposite of "keep nothing". Sending
`existing_image_ids[]=""` instead fails `['integer', 'distinct']`, and a sentinel like `0` fails
`MediaKeepSet`'s "Images must belong to this record." check (`:19-21`).

### Expected behavior

A client must be able to say "keep none of the existing images" and have it honoured, on both the
update and the republish path.

### Why this is a backend issue

The ambiguity is in the request contract itself — presence of a key is overloaded to mean two
different things, and one of the two states is unreachable over the transport the API requires.
No client-side change can construct the missing state.

### Impact

Two concrete defects, both silent:

1. **Update: removing every image does nothing.** On all three forms the user can delete every
   existing image and upload a replacement (the forms require at least one image, so this is the
   normal "swap the gallery" flow). The keep-set goes out empty → key absent → `MediaKeepSet`
   deletes nothing → the old images survive alongside the new one. The user is shown a success
   toast.
2. **Republish: the licence and the gallery cannot be separated.** `apply()` copies the source's
   licence only when `$source` is set, and `$source` requires `opportunity_id` — but sending
   `opportunity_id` without a keep-set clones the whole source gallery. So "repost, keep the
   licence, start with a fresh gallery" is not expressible. The frontend currently resolves this
   by not sending `opportunity_id` when the keep-set is empty, which keeps the gallery correct and
   loses the licence copy.

### Evidence

- `MediaKeepSet.php:11-13, 19-21`; `RepublishMedia.php:25-31, 41-48`.
- Frontend keep-set emitters, all gated on a non-empty set because an empty one is unsendable:
  `EventForm.tsx:679`, `VolunteerForm.tsx:825`, `LearnServeForm.tsx:1059`.

### Recommended fix

Add an explicit signal rather than overloading key presence. Either is fine:

- accept a scalar `existing_image_ids` of `""` / `"none"` and normalise it to `[]` before
  validation, so the existing key keeps working; **or**
- add a separate `clear_existing_images` boolean that `MediaKeepSet::validate()` and
  `RepublishMedia::apply()` both honour, which leaves the array rule untouched.

Whichever is chosen, please confirm the intended republish semantics for the licence — if
"copy the licence but none of the images" should be possible, `apply()`'s licence branch needs to
stop depending on `$source` being present for the gallery's sake.

### Frontend status

**Frontend is correct within the current contract and needs no change until the backend picks a
signal.** It deliberately never sends a keep-set it cannot express, and the trade-off is
documented at each call site. Once the backend adds a signal, the three forms each need a
one-line change to send it when the keep-set is empty.

---

## BE-44 — The admin form and the API validate the same columns differently

**Severity: LOW — new, raised 2026-09-13**

### Location

- `app/Http/Controllers/Admin/VolunteerOpportunityController.php:407-455` vs.
  `app/Http/Controllers/Api/Opportunity/VolunteerOpportunityController.php:582-635`.
- `app/Http/Controllers/Admin/EventController.php:301-340` vs.
  `app/Http/Controllers/Api/Event/EventController.php:350-403`.

### Current behavior

The two surfaces write the same columns under the same field names but disagree on the rules.
Leaving aside `interest_ids` (that is BE-43), the differences that matter:

| Field | Admin rule | API rule | Consequence |
|---|---|---|---|
| `link` (volunteer) | `string`, `max:500` | `url` | An admin can save a non-URL; the org's next in-app edit then 422s on a field they never touched, because the form resends the loaded value. |
| `opportunity_nationality` | `Rule::in(Nationality)` | **absent** | Admin-only column. The API cannot set it, and `rejectUnknownWriteKeys` would 422 any client that tried. |
| `is_calendar` | `boolean` | **absent** | Same. |
| `after_images` | accepted | **absent** | Same. |
| `time_slots` | **absent** | array of per-day rows | The dashboard cannot see or edit a schedule the app created. |
| `title_ar`, `description_*` (event) | `required` | `nullable` | A record the API accepted can fail to save unchanged in the dashboard. |

`start_time` / `end_time` are **not** a real divergence despite looking like one: the admin
requires `date_format:H:i` and the API accepts any string, but both frontend forms already
normalise to `HH:mm` (`EventForm.tsx:452`, `VolunteerForm.tsx:535-536`), so the stored values
satisfy both.

### Expected behavior

A record saved on one surface should be re-saveable on the other without editing fields the user
did not touch.

### Impact

Low and mostly latent — the one reachable failure today is `link`: a non-URL entered in the
dashboard blocks the organization's next edit in the app with a validation error on a field they
did not change, and the message points at a field the form shows as read-only-ish. The rest is
drift that will bite whoever next assumes the two validators agree.

### Evidence

Both rule arrays, quoted at the locations above. `link`: `Admin/VolunteerOpportunityController.php:427`
(`['nullable', 'string', 'max:500']`) vs. `Api/…/VolunteerOpportunityController.php:603`
(`['nullable', 'url']`).

### Recommended fix

Extract the shared column rules into one place (a form request or a small trait) that both the
admin controller and the API controller compose from, with only the genuinely
surface-specific keys — `created_by`, `approval_status`, `opportunity_status` on the admin side;
`opportunity_id` / `existing_image_ids` on the API side — added separately. At minimum, make
`link` a `url` in the dashboard so the two agree on the reachable case.

### Frontend status

**No frontend change required.** The forms already send `HH:mm` times and validate `link` as a
URL client-side; they cannot control what the dashboard writes.

---

## BE-45 — Private opportunities cannot be joined, and leak into the combined list

**Severity: HIGH — new, raised 2026-09-14**

### The product rule

A volunteer opportunity is either **public** — listed in the app, and anyone of a suitable age
may apply — or **private**: not listed anywhere, but reachable by direct link, and joinable by
anyone who has that link and fits the age range. `is_public` on `volunteer_opportunities` is the
flag; learn & serve opportunities and events have no equivalent, so this applies to volunteer
opportunities only.

### What already works

- **Hidden from the public list.** `listVolunteerOpportunities()`
  (`Api/Opportunity/VolunteerOpportunityController.php:343`) filters `->where('is_public', true)`.
- **Hidden from the homepage.** `Api/Base/HomeController.php:134`, same filter.
- **Hidden from the calendar.** `Api/Calendar/CalendarController.php:77`.
- **Hidden from a public profile's listing.** `listAllOpportunities()` applies the filter when a
  `user_id` is supplied (`:435`).
- **Reachable by direct link.** `opportunityDetails()` (`:402-418`) gates on
  `canViewVolunteerOpportunity()`, which checks deletion and approval but deliberately **not**
  `is_public` (`Concerns/HandlesOpportunities.php:240-251`). This is correct and is the half of
  the feature that behaves as specified.
- **Age eligibility.** `VolunteerOpportunityRegistrationController.php:244-259` enforces
  `from_age` / `to_age` against the registrant's age with a clear localized message.

### Current behavior — the four defects

**1. Registration is impossible for a private opportunity (the headline bug).**

`RegistrationEligibility::reject()` (`app/Services/Opportunity/RegistrationEligibility.php:16-19`)
treats "not public" as "does not exist":

```php
if ($item->is_deleted || $item->approval_status !== ApprovalStatus::APPROVED
    || ($item instanceof VolunteerOpportunity && ! $item->is_public)) {
    return ApiResponse::error('Item not found.', 'العنصر غير موجود.', 404);
}
```

It runs from `rejectIfRegistrationClosed()` (`Concerns/HandlesOpportunities.php:385`), which
`VolunteerOpportunityRegistrationController::register()` calls at `:217` — **before** the age
check. So a volunteer who follows a private link can read the whole opportunity and then gets a
bare `404 Item not found` on the one action the page exists to offer. The detail endpoint says
the opportunity exists; the registration endpoint says it does not.

**2. Private opportunities leak into `/list-all-opportunities/`.**

`applyCombinedFilters()` (`:753-805`) scopes the query per `filter_type`, and BE-18 made an
*unrecognised* `filter_type` a 422. But an **empty or absent** `filter_type` still falls through
with no scoping at all (`elseif ($filterType !== '')` — the empty case matches nothing), and
neither `listAllOpportunities()` nor `applyCombinedFilters()` applies `is_public` outside the
`user_id` branch. `grep -rn "is_public" app/Http/Controllers/Api/Opportunity/Concerns/` returns
nothing.

`resolveListUser()` (`:715-735`) requires a token when `user_id` is absent, so this is not an
anonymous leak — but **`GET /list-all-opportunities/` with any valid token and neither
`filter_type` nor `user_id` returns every opportunity on the platform**, private ones included.
The general path also only excludes `REJECTED` (`:427-432`), so `PENDING` rows come back too.

**3. The share link the UI offers is always empty.**

`VolunteerOpportunityResource.php:66` serialises `'registration_link' => $this->generated_link`,
and the frontend's private-opportunity **Share** button copies exactly that field. But
`generated_link` is never written: `grep -rn "generated_link" app/ database/migrations/` returns
three hits — the `$fillable` entry (`VolunteerOpportunity.php:33`), that resource line, and the
column definition (`2024_01_01_000003_create_opportunity_tables.php:48`). Nothing assigns it, so
it is always `null` and the button shares an empty string. There is no server-generated link for
the "reachable by link" flow at all.

**4. Private becomes public when the opportunity completes.**

`AdvanceOpportunityStatusesCommand.php:62-68` flips the flag on the scheduled run:

```php
if ($model === VolunteerOpportunity::class
    && $newStatus === OpportunityStatus::COMPLETED
    && ! $opp->is_public) {
    $opp->is_public = true;
}
```

Every private opportunity therefore becomes publicly listed once it ends. This reads as
deliberate — presumably so completed work shows in the organization's public record — but it
contradicts "private does not appear to the public", and it happens with no opt-out. Flagging it
as a product decision rather than asserting it is a bug.

### Why this is a backend issue

Visibility and join eligibility are authorization decisions taken server-side, and the share
link has to be minted server-side to be meaningful. The frontend already sends `is_public`
correctly and already renders a private-opportunity share affordance; it cannot make any of the
four behave.

### Impact

The private-opportunity feature does not work end to end. An organization can create one and
send the link, the recipient can read the page, and then registration fails with a 404 that says
the opportunity does not exist — so the feature's entire purpose (invite-only participation) is
unreachable. Separately, any authenticated user can enumerate every private and pending
opportunity on the platform with one unscoped request, which is the opposite of what the flag
promises.

### Evidence

- `app/Services/Opportunity/RegistrationEligibility.php:16-19`; called from
  `Concerns/HandlesOpportunities.php:385`, reached from
  `VolunteerOpportunityRegistrationController.php:217`.
- `VolunteerOpportunityController.php:427-438` and `:795` — the unscoped empty-`filter_type` path.
- `grep -rn "generated_link" app/ database/migrations/` → 3 hits, none assigning.
- `AdvanceOpportunityStatusesCommand.php:62-68`.
- Correct-by-contrast: `HandlesOpportunities.php:240-251` (view gate ignores `is_public`),
  `VolunteerOpportunityController.php:343`, `HomeController.php:134`.

### Recommended fix

1. **Drop the `is_public` clause from `RegistrationEligibility::reject()`.** Privacy is about
   discovery, not eligibility — a private opportunity reached by link should register exactly
   like a public one, subject to the same approval, status, age and capacity checks that already
   follow. This is a one-line change and is the whole of the headline bug.
2. **Filter `is_public` in `listAllOpportunities()` unconditionally**, lifting it out of the
   `user_id` branch, except where the caller is the owner (`filter_type=organized`) — an
   organization must still see its own private rows. Consider making an empty `filter_type`
   a 422 as well, so the unscoped catalogue is never reachable by omission.
3. **Mint `generated_link` on create** (a UUID or signed token appended to the frontend detail
   URL) and backfill existing rows, so the Share button has something to share. If the plain
   `/volunteer-event-detail/{id}` URL is considered link enough, then drop `generated_link` and
   have the resource return that instead — but pick one, because today it returns null.
4. **Confirm the intended behaviour of auto-publishing on completion** before changing it.

### Frontend status

**One frontend bug found and fixed (2026-09-14); the rest is blocked on the backend.**

`VolunteerForm.tsx` maps anything that is not the literal `"public"` to `is_public: 0`, and
`isPrivate` starts as `""` on create and was **absent from the Yup schema** — so an organization
that never touched the "Opportunity should be" dropdown created a **private** opportunity
silently. Combined with defect 1 above, that opportunity was then invisible *and* impossible to
join, with nothing on screen explaining why. The field is now required, so the choice has to be
made explicitly; defaulting it either way would just move the surprise.

Still blocked on the backend: the Share button on a private opportunity
(`VolunteerEvent.tsx:977-989`) copies `registration_link`, which is always `null` until defect 3
is fixed.

---

## BE-46 — Every certificate endpoint is unauthenticated, so anyone can read and download any volunteer's certificates

**Severity: HIGH — New (2026-09-14).**

Three routes that serve certificates sit **outside** the `auth:api` group, and none of the
three checks who is asking:

| Route | File | Guard |
|---|---|---|
| `GET /api/user-certificates/?user_id=N` | `routes/api.php:235` | none — the section is even commented *"Volunteer statistics — public"* |
| `GET /api/certificate/preview/{registration_id}/` | `routes/api.php:104` | none |
| `GET /api/download-certificate/?registration_id=N` | `routes/api.php:105` | none |

`VolunteerStatisticsController::userCertificates()` (~line 342) validates exactly one thing —
`['user_id' => ['required', 'integer']]` — then returns that user's whole certificate set:
image URL, opportunity title in both languages, and organizer name. There is no ownership
check, no token requirement, and no `is_public` check on the volunteer profile it loads.

`OpportunityMediaController::certificateDownload()` (lines 144-175) is worse. It looks the
registration up by primary key and streams the file:

```php
$registration = match ($type) {
    'volunteer'   => VolunteerOpportunityRegistration::query()->find($registrationId),
    'learn_serve' => LearnServeOpportunityRegistration::query()->find($registrationId),
    default       => LearnServeOpportunityRegistration::query()->find($registrationId)
        ?? VolunteerOpportunityRegistration::query()->find($registrationId),
};
```

With `registration_type` omitted it falls back across **both** tables, so incrementing a plain
integer walks every certificate on the platform. Nothing between `find()` and
`Storage::disk('public')->download()` asks who the caller is.

### Impact

A volunteer's certificates carry their real name rendered into the image, which opportunities
they attended, and which organizations ran them. All of it is readable by an **anonymous**
caller who knows or guesses a user id — ids are sequential and are already exposed in public
profile URLs, community posts and the achievements leaderboard.

This also defeats the volunteer privacy setting that already exists: a profile with
`is_public = false` is redirected to the stripped-down `/volunteer-private-profile/{id}` screen,
but `user-certificates/` happily serves that same user's certificates to anyone.

Certificates live on the `public` storage disk, so a URL that has already been handed out stays
fetchable regardless. The defect here is **enumeration** — the endpoints turn "you can open a
link someone gave you" into "you can download everybody's".

### Frontend status — already done

`CommonProfile.tsx` (the `/public-profile/{id}` screen) no longer renders the certificates tab,
no longer renders the grid, and no longer issues the `getUserCertificates()` request at all
unless the signed-in user's id matches the profile being viewed. The owner still sees their
certificates on `/volunteer-profile`, which goes through `ProfileDescriptionTabs.tsx`.

That is a UI change only. It removes the discovery path, not the access — the endpoint is still
callable directly with any `user_id`, so this issue is not mitigated by it.

### Suggested fix

1. Move all three routes into the `auth:api` group.
2. Scope `userCertificates()` to the caller: read `$request->user()->id` and ignore the
   `user_id` parameter, or return `403` when the two differ.
3. Scope `certificateDownload()` / `certificatePreview()` to the registration's owner — `404`
   otherwise, so the endpoint does not confirm that an id exists.
4. Decide explicitly whether an organizer may fetch certificates they issued. That is probably
   wanted (they are the ones who send them), but it should be an allowance that is written
   down, not the current absence of any check.
5. **Keep the LinkedIn share working.** `ProfileDescriptionTabs.tsx` has a share button that
   posts `certificate_image` to LinkedIn, which means the URL must stay publicly fetchable *for
   certificates the owner chose to share*. Mint a per-certificate unguessable token (or a signed
   URL) for that path rather than leaving the sequential registration id as the only key —
   otherwise step 3 breaks the share feature.

---

## BE-47 — Development opportunities: certificates are not gated by learning type, and the type names the code matches on no longer exist

**Severity: HIGH — New (2026-09-14).**

The product rule, as stated by the client: development (learn & serve) opportunities have **four**
types, **all four require registration**, and only **two of them grant a certificate**.

| Type (ar) | Type (en) | Registration | Certificate |
|---|---|---|---|
| درس / ورشة | `Class/Workshop` | yes | **no** |
| استشارة | `Consultation` | yes | **no** |
| دورة | `Course` | yes | yes |
| تدريب عملي | `Internship` | yes | yes |

Registration works for all four — `LearnServeRegistrationController::createRegistration()` has no
type branch, and `time_slot_id` is `nullable`, so every type can be registered for. The
certificate half is where it breaks.

### 1. Certificates can be issued for types that must not grant one — HIGH

`app/Http/Controllers/Api/Opportunity/CertificateController.php` gates `show()` (line ~43) and
`store()` (line ~78) on exactly two things: the caller owns the registration, and
`is_attended` is true. **Neither method looks at the learning type.**

So `POST /api/certificates/{registration_id}/issue/` on a consultation or a class/workshop
registration renders a certificate, writes `certificate_image`, and sets `is_certified = true`.
That registration then appears in the volunteer's certificates tab and counts towards
`total_certificates` on their profile.

This is reachable today, not theoretical: `AdvanceOpportunityStatusesCommand.php:73-80` marks
every registration on a no-check-in opportunity as attended when it completes, which is the only
precondition the endpoint checks.

The intent is already written down elsewhere — `BackfillMissingCertificatesCommand.php:45`
restricts itself to `in_array($learningType, ['internship', 'course'], true)`. The endpoint that
users actually call never got the same guard.

**Fix:** in both `show()` and `store()`, resolve
`strtolower(trim($registration->opportunity?->learningType?->value_en))` and return a `422`
unless it is `course` or `internship`. Please also audit existing rows — any
`learn_serve_opportunity_registrations` record with `is_certified = true` whose opportunity is a
class/workshop or consultation was issued in violation of the rule and should be reviewed before
the guard goes in.

### 2. The learning-type strings the code matches on do not match the live choices — HIGH

Production has the four types above, with **class and workshop merged into one choice** whose
`value_en` is `Class/Workshop`. Two places match against the old, separate names:

```php
// app/Models/LearnServeOpportunity.php:57
public const NO_CHECK_IN_TYPES = ['workshop', 'consultation'];

// app/Services/Opportunity/SyncService.php:276
$countsWithoutAttendance = in_array($learningType, ['class', 'workshop', 'consultation'], true);
```

`strtolower(trim('Class/Workshop'))` is `class/workshop`, which is in neither list. Consultations
still match; the class/workshop half does not. Consequences:

- `requires_check_in` comes back **`true`** for a class/workshop, so the detail screen and the
  registrations list demand an attendance step the product says these types do not have.
- `AdvanceOpportunityStatusesCommand.php:76` only credits registrations when
  `! $opp->requiresCheckIn()`, so a completed workshop's registrants are **never** marked
  attended — their hours never land in anyone's counters.
- `SyncService` skips a completed workshop that has no attendance rows, so it is missing from the
  organizer's monthly opportunity counts.

**Fix:** match on the choice **id** rather than a translated label, or at minimum normalise —
treat `class/workshop` (and any `class` / `workshop` legacy rows) as no-check-in. A label
comparison against user-editable master data will keep drifting; the ids are stable.

### 3. `certificate_type_id` is never rejected for the two types that grant no certificate — MEDIUM

`LearnServeOpportunityController.php:399-406` requires a certificate type for courses and
internships:

```php
if (in_array($type, ['course', 'internship'], true) && ! $certificateId) {
    throw ValidationException::withMessages([...]);
}
```

There is no inverse rule. A class/workshop or consultation can be created or updated with a
`certificate_type_id` set, and `GET` then reports it as granting a certificate. Worse on a
`PATCH`: changing an existing course to a workshop leaves the stored `certificate_type_id`
untouched, because the request simply does not mention it.

**Fix:** reject `certificate_type_id` when the resolved type is not course/internship, **and**
null the column when a partial update moves the opportunity off those types.

The frontend now sends an explicit empty `certificate_type_id` whenever the selected type is not
course/internship, which clears the column through `ConvertEmptyStringsToNull`. That closes the
path through the app but not the one through the API or the admin dashboard.

### 4. `ChoiceTypeSeeder` no longer matches production — LOW

`database/seeders/ChoiceTypeSeeder.php:19-27` seeds **seven** learning types:

```php
'learning_type' => [
    ['Course', 'دورة'], ['Class', 'درس'], ['Consultation', 'استشارة'],
    ['Internship', 'تدريب عملي'], ['Workshop', 'ورشة'],
    ['internship', 'تدريب'], ['course', 'دورة'],
],
```

Separate `Class` and `Workshop`, plus lowercase duplicates of `internship` and `course`. A freshly
seeded environment therefore shows a seven-option dropdown with two visible duplicates, and the
frontend — which keys its ordering and its "does this type grant a certificate" lookups on the
literals `Class/Workshop`, `Course`, `Internship` — behaves differently there than in production.
Please reconcile the seeder with the four approved choices and retire the duplicates.

### Frontend status — already done

Three defects were found and fixed on the frontend during this review:

- `LearnServeForm.tsx` required a certificate type for **internships only**, while the API
  requires it for courses too — a course submitted without one came back as a 422 with no field
  highlighted. Now required for both.
- `LearnServeForm.tsx` kept a previously-picked certificate type in Formik state after the type
  was switched away from course/internship, and re-sent it on submit. It now always sends
  `certificate_type_id`, empty for the two types that grant nothing.
- `LearnServeDetails.tsx` detected consultations by comparing against `"consultationss"` (a typo)
  and `"استشارات"` (plural) — neither is a real choice value, so the branch never fired and
  consultations were registered through the plain confirmation modal with **no time slot booked**.
  Now matched on `Consultation` / `استشارة`.

The detail screen's own certificate row was already correct: `CERTIFICATE_TYPES = ["Course",
"Internship"]` gates it, and the form only shows the certificate field for those two types.

### A note on how the live type names were established

This workspace has no database access, so "production has `Class/Workshop` as a single merged
choice" is inferred, not queried — from the client's screenshot (four options, `درس/ورشة` first)
and from four independent frontend constants that all key on that exact literal:
`Calendar.tsx:70` (`DETAIL_PATH_BY_TYPE`), `Calendar.tsx:81` (`LEARN_SERVE_TYPES`),
`AllOpportuniteFilterModal.tsx:127` (`typeOrder`) and `LearnServeForm.tsx:667`
(`learningTypeOrder`). Please confirm with
`SELECT value_en, value_ar FROM master_choices WHERE choice_type_id = (SELECT id FROM choice_types WHERE name = 'learning_type')`
before acting on part 2 — if the live value really is `Workshop`, part 2 does not apply and only
parts 1, 3 and 4 stand.

---

## BE-48 — "My calendar" save/unsave cannot be built: no `calendar_id` is ever returned, the saved flag ignores deletions, and events have no flag at all

**Severity: MEDIUM — New (2026-09-14).**

Two separate calendar features exist. The **"Add to calendar"** half (generate an `.ics` /
open Google Calendar) needs no backend and has now been restored on the frontend. The
**"My calendar"** half — `my_calendars`, which feeds the `/calendar` screen — is implemented on
the backend but cannot be driven from a client as it stands.

### 1. No endpoint or resource ever returns `calendar_id` — MEDIUM

Removing an item is `DELETE /api/my-calendar/{id}/`, keyed on the **`my_calendars` row id**. But
the opportunity payloads expose only a boolean:

```php
// app/Http/Resources/Opportunity/VolunteerOpportunityResource.php:71
'is_saved_to_calendar' => $this->isSavedToVolunteerCalendar($this->resource, $request),
```

A detail screen therefore knows an item *is* saved but has no id to unsave it with, short of
pulling all of `GET /my-calendar/` and scanning it for a matching item. `formatCalendarRow()`
does attach `calendar_id`, but only on the calendar listing and on the `POST .../save/`
response — never on the opportunity or event itself.

**Fix:** return `calendar_id` next to `is_saved_to_calendar` in the opportunity resources (null
when unsaved), or accept the item reference on delete
(`DELETE /api/my-calendar/?volunteer_opportunity_id=…`) so the caller can use what it already
has.

### 2. `is_saved_to_calendar` ignores soft-deleted rows — MEDIUM

`destroy()` soft-deletes via `softDeleteFlags()`, but the two lookups in
`app/Http/Resources/Concerns/ResolvesOpportunitySerializerFields.php:123-148` do not filter on it:

```php
return MyCalendar::query()
    ->where('user_id', $user->id)
    ->where('volunteer_opportunity_id', $opportunity->id)
    ->where('is_saved', true)
    ->exists();
```

No `->notDeleted()`, unlike every other query in `CalendarController`. So once an item has been
removed from My Calendar, its detail page reports it as **still saved, permanently** — the row
is still there with `is_saved = true` and only `is_deleted` flipped. Adding `->notDeleted()` to
both methods fixes it.

### 3. Events carry no calendar fields — MEDIUM

`POST /api/my-calendar/save/` accepts `event_id`, and `MyCalendar` has the column and the
relation. But the Event resource exposes **neither** `is_saved_to_calendar` nor `calendar_id`, and
there is no `isSavedToEventCalendar()` alongside the volunteer and learn-serve versions. An event
can be saved but its own payload can never say so, so the button would have no state to render.

### 4. Saving the same item twice creates duplicate rows — LOW

`store()` calls `MyCalendar::create()` unconditionally, and
`database/migrations/2024_01_01_000005_create_remaining_tables.php:191-207` puts no unique
constraint on `(user_id, volunteer_opportunity_id / learn_serve_opportunity_id / event_id)`.
Two taps on Save produce two rows, and `GET /my-calendar/` then lists the item twice.
`firstOrCreate()` plus a unique index would settle it.

### 5. The calendar listing double-counts registered-and-saved items — LOW

`index()` concatenates `savedItems()`, `registeredItems()` and `createdItems()` with no
deduplication. An opportunity you registered for **and** saved comes back twice, as `Registered`
and as `Saved`, and renders as two entries on the same day. Keying the merged list by
`(type, id)` — preferring whichever status should win — would fix it.

### 6. `is_calendar` is stored and validated but never read — LOW

Both opportunity models list `is_calendar` as fillable and cast it, both API resources return it,
and both admin controllers validate it — but **no code anywhere reads it**. Either it should gate
something (presumably whether an item may be added to a calendar at all) or it should be retired.
Please confirm which, since the frontend currently ignores it too.

### Frontend status — Add-to-calendar restored, save/unsave blocked

`AddToCalendar.tsx` had been ported during the migration but was **never rendered anywhere** —
zero call sites. It is now on all three detail screens (events, volunteer opportunities, learn &
serve) and offers Google Calendar as well as the `.ics` download, since a downloaded `.ics` puts
nothing into Google Calendar without a manual import. The iPad path still uses
`POST /api/upload-ics/`, which works as-is.

The **save/unsave toggle is not built**, and cannot be until parts 1-3 land: there is no id to
delete with, the saved flag never clears, and events cannot report their state. Until then the
`/calendar` screen shows only registered and created items — `POST /my-calendar/save/` has no
caller, so nothing has ever been written to `my_calendars` from the Next app.

---

## BE-49 — `GET /my-calendar/` collapses nine entry types into three, keeps deleted items on the calendar, and loads every registration on every request

**Severity: MEDIUM — New (2026-09-14).**

Found while restoring the `/calendar` screen in the Next app from the React original
(`fursa_react/src/pages/MyCalander/Index.tsx`).

### 1. The specific entry type is gone — the Python API returned it, Laravel does not — MEDIUM

The React page reads **`type_en` / `type_ar`** off every calendar row and branches on nine
values: `Opportunity`, `Class/Workshop`, `Internship`, `Course`, `Consultation`,
`Exhibition and Carnivals`, `Camps`, `Hub`, `Sports Activities`. That is what the Python backend
returned.

`CalendarController::formatOpportunity()` and `formatEvent()` emit neither field. They return a
coarse bucket instead:

```php
'type' => $type,     // 'Volunteer' | 'Learn' | 'Event'
'status' => $status, // 'Saved' | 'Registered' | 'Organized'
```

So the React page's entire `switch (eventType)` for routing falls through to
`console.error("Unknown event type")`, and every colour rule misses — **the React screen would
render the whole calendar grey and navigate nowhere against this API.** It only ever worked
against the Python backend.

The Next port compensates: it reads `event.type_en || event.type`, maps the three buckets to
their detail routes, and labels them "Volunteer opportunity" / "Learn and serve" / "Event". The
colours come out identical to the original (the old code mapped all four learn types to one
colour and all four event types to another anyway). What is genuinely lost is the **label**: an
entry now reads `Type: Learn and serve` where it used to read `Type: Course`.

**Fix:** add `type_en` / `type_ar` to both formatters, carrying the actual choice — the
opportunity's `learningType` for learn & serve, the event's `event_type` for events, and
`Opportunity` for volunteer opportunities. The frontend already prefers those fields when
present, so no client change is needed once they appear.

### 2. Deleted opportunities and events stay on the calendar — MEDIUM

`notDeleted()` is a **local** scope (`app/Models/Concerns/HasSoftFlags.php:18`), not a global one.
`savedItems()` and `registeredItems()` apply it to the `my_calendars` / registration row but then
reach the source through a plain relation:

```php
->with(['volunteerOpportunity', 'learnServeOpportunity', 'event'])   // savedItems
->with('opportunity')                                               // registeredItems
```

A soft-deleted opportunity still resolves, so it keeps appearing on the user's calendar forever,
and tapping it navigates to a detail page that 404s. `createdItems()` is unaffected — it queries
the opportunity table directly with `notDeleted()`.

**Fix:** constrain the eager loads (`with(['opportunity' => fn ($q) => $q->notDeleted()])`) and
skip rows whose source came back null — `registeredItems()` already has the `if ($reg->opportunity)`
guard that would then do the right thing.

### 3. The whole history is loaded and filtered in PHP on every request — LOW

`registeredItems()` and `createdItems()` fetch **every** registration and **every** created
opportunity for the user with no date bound, then `matchesFilters()` drops the non-matching ones
in PHP — including the `search` term, which is a `str_contains` over the already-hydrated
collection rather than a SQL `LIKE`. For an organization with a few hundred opportunities, opening
the day view loads all of them to render one day.

The date range and the search are both already known at query time and belong in the query.

### Frontend status — page restored

The `/calendar` screen itself was never missing from the Next app; it is a full port of the React
page (mini calendar, upcoming-tasks sidebar, day/week/month/year views, the "+N more" day modal,
debounced search). What was missing is that **"My calendar" had been removed from the header
menu** — a comment in `Header.tsx` recorded it as done "at the client's request" — so the route
existed but nothing linked to it. It is back in both the desktop dropdown and the mobile menu,
for volunteers and organizations, in the same positions the React header used.

One real bug was fixed on the way: the year view sent `time_range=year` with no `date`, so the
API's `dateRangeFromTimeRange()` anchored on `now()` and **always returned the current year** no
matter which year the user navigated to. The client now sends `date`; the API already supported it.

---

## Verification that could not be performed here

This workspace has **no PHP runtime, no Composer, and no installed `vendor/`**, so:

- `php artisan test` was **not** run — including the new `tests/Unit/HtmlSanitizerTest.php`.
- `php artisan route:list` was **not** run — the route table used for this audit was parsed
  directly from `routes/api.php`, including every `Route::match([...])` verb list.
- No endpoint was exercised against a live server or database.

All findings above were reached by reading the implementation. Please re-confirm the BE-35 /
BE-39 fixes against a running instance before treating them as closed, and confirm the
production `.env` value for `EXPOSE_OTP_IN_RESPONSE` (BE-40).

For BE-41, the `event_attendances` table was confirmed unused by static analysis only. Before
dropping it, please check the production database for existing rows — if any were written by an
earlier revision, decide whether they need archiving first.

---

## ✅ Final task — send back a completion report when everything is done

**This is the last task in this file. Please do it after all the work above is finished.**

**هذه آخر مهمة في الملف — من فضلك نفّذها بعد الانتهاء من كل الشغل اللي فوق.**

### What to do / المطلوب

Once you have **finished every issue above, reviewed the changes, and verified that everything
works** — not just written the code, but actually confirmed the behaviour — create a **new
Markdown file** and send it back to us.

بعد ما تخلص **كل النقاط اللي فوق، وتراجعها، وتتأكد إن كل حاجة شغالة تمام** — مش بس تكتب الكود،
لكن تتأكد فعليًا من السلوك — اعمل **ملف Markdown جديد** وابعتهولنا.

**Suggested filename:** `FURSA_BACKEND_DONE.md`

### What the file must contain / اللي لازم يكون في الملف

1. **The confirmation phrase, exactly as written below.** This is how we know the whole job is
   finished — without it we treat the work as still in progress:

   **العبارة دي بالظبط زي ما هي مكتوبة تحت** — بيها نقدر نتأكد إنك خلصت كل الشغل، ومن غيرها
   هنعتبر الشغل لسه شغال:

   ```
   مساء الفل يا ميدو
   ```

2. **A per-issue status line** for every open id — BE-36, BE-37, BE-41 … BE-49 — saying for each
   one whether it is **Done**, **Partially done** (and what is left), or **Not done** (and why).
   Please keep the same BE ids so the two files line up.

   **سطر حالة لكل مشكلة** من المشاكل المفتوحة — BE-36، BE-37، BE-41 … BE-49 — يقول لكل واحدة
   هي **خلصت**، ولا **خلصت جزئيًا** (وإيه الباقي)، ولا **لسه** (وليه). خلّي نفس أرقام BE عشان
   الملفين يتطابقوا.

3. **Answers to the open questions** we asked inside the issues, because some fixes depend on a
   product decision rather than on code:

   **إجابات على الأسئلة المفتوحة** اللي سألناها جوه المشاكل، لأن فيه حاجات محتاجة قرار من
   المنتج مش كود:

   - **BE-40** — the production `.env` value of `EXPOSE_OTP_IN_RESPONSE`.
   - **BE-41** — whether events keep registrations at all (Stage 2), and whether
     `event_attendances` had any production rows before it was dropped.
   - **BE-42** — which signal was chosen for "clear all images", and the intended republish
     semantics for the licence.
   - **BE-45** — whether a private opportunity should really become public when it completes.
   - **BE-47** — the actual result of
     `SELECT value_en, value_ar FROM master_choices WHERE choice_type_id = (SELECT id FROM choice_types WHERE name = 'learning_type')`,
     since part 2 of that issue depends on it.
   - **BE-48** — whether `is_calendar` should gate something or be retired.

4. **How each fix was verified** — the test, the endpoint call, or the screen you checked. Several
   findings in this file survived precisely because a unit test passed while the code under test
   was never wired up (see BE-37), so "the tests are green" on its own is not enough.

   **إزاي اتأكدت من كل إصلاح** — التست، أو الـ endpoint اللي جربته، أو الشاشة اللي شوفتها.
   فيه أكتر من مشكلة هنا عاشت بالظبط لأن التست كان بينجح والكود اللي بيتّست عليه مش متوصّل
   أصلًا (شوف BE-37)، فـ "التستات كلها خضرا" لوحدها مش كفاية.

5. **Anything you changed that is not in this file** — a schema change, a renamed field, a new
   required parameter. Any of those can break the frontend silently, so we need to know before it
   ships.

   **أي حاجة غيّرتها مش موجودة في الملف ده** — تغيير في الداتابيز، اسم حقل اتغير، باراميتر جديد
   مطلوب. أي واحدة من دول ممكن تكسر الفرونت من غير ما حد ياخد باله، فمحتاجين نعرف قبل ما ينزل.

### Done means / يعني إيه خلصت

All of: the code is written, it is reviewed, the behaviour is verified on a running instance, and
the answers above are filled in. If any issue is still open, say so plainly in the file rather
than leaving it out — a partial report with honest gaps is more useful to us than a complete-looking
one.

الكود اتكتب، واتراجع، والسلوك اتأكدنا منه على نسخة شغالة، والأسئلة اللي فوق اتجاوبت. لو فيه أي
نقطة لسه مفتوحة، اكتبها بصراحة في الملف بدل ما تسيبها — تقرير ناقص وواضح أنفع لينا من تقرير
شكله كامل.
