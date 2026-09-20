# Backend changes for the mobile app — 2026-09-20

This covers every backend change made while closing out `FURSA_BACKEND_ISSUES (3).md`
(BE-63 through BE-67). It is written for whoever is building/maintaining the mobile
app, since the mobile client hits the same API as `fursa-next` / `fursa_react` and
needs to match the same contract. All items below are **done, merged to `updates`,
and covered by automated tests** (`php artisan test`: 360 passed, 1 pre-existing
unrelated failure in `PostmanCollectionCoverageTest`).

---

## 1. `due_date` is required again when creating/editing a volunteer opportunity (BE-66)

**What changed:** `POST /api/volunteer-opportunities/` and the update endpoint now
reject a missing/empty `due_date` with a 422.

- Applies **only** to volunteer opportunities (not learn-&-serve, not events).
- A `PATCH`/partial update that simply doesn't mention `due_date` still passes — it's
  only rejected if the field is present and empty, or absent on a full create.
- The database column is still nullable — old rows with `due_date = NULL` are
  untouched and keep falling back to `end_date`.

**Mobile action:** if there's a "create/edit volunteer opportunity" screen (organizer
side), make the due-date field required client-side, matching the web app.

---

## 2. Learn & serve opportunities can now be reopened after being closed (BE-63)

**New route:**

```
POST /api/learn-serve-opportunities/{id}/reopen-registration/
```

Mirrors the existing `close-registration/` route. Same auth (organizer/owner only).

**New rule on all four toggle endpoints** (volunteer close, volunteer reopen,
learn-serve close, learn-serve reopen): the organizer can only open/close
registration **up until one day before `end_date`**. After that, all four return:

```json
422 { "msg": "لم يعد بالإمكان تعديل التسجيل لهذه الفرصة.", ... }
```

- Gated on `end_date`, not `due_date` (the due date is the thing this toggle
  overrides, so gating on it would remove the button when it's most needed).
- The cutoff is inclusive of the day before `end_date` — e.g. an opportunity ending
  Sep 30 can still be toggled all through Sep 29, but not on the 30th.
- An opportunity with no `end_date` is never blocked by this rule.

**Mobile action:** if the organizer app has open/close registration buttons, hide
them (or expect a 422) once you're on `end_date - 1`, same rule the web app already
applies client-side.

---

## 3. New `whatsapp_link` field on learn & serve opportunities (BE-65)

Development opportunities (`learn_serve_opportunities`) now have a **second**,
separate URL field alongside the existing `link`:

| Field | Meaning | Who sees it |
|---|---|---|
| `link` | the private meeting URL (Zoom, etc.) | registered participants + organizer only |
| `whatsapp_link` | a direct WhatsApp contact for the opportunity | **everyone**, ungated |

- Nullable, validated as `['nullable', 'url']` — no WhatsApp-domain check server-side,
  same as the web app's approach.
- Present on both `LearnServeOpportunityResource` (detail) and
  `WebsiteLearnServeOpportunityResource` (listing).
- **`link` is unaffected and still means the meeting URL for this model** — do not
  confuse it with the volunteer-opportunity `link`, which *is* the WhatsApp field
  there. The two models use `link` for two different things; that inconsistency was
  not resolved (backend left it as-is per an open question in the original ticket) —
  `whatsapp_link` is a brand-new, unambiguous field, only on learn-&-serve.

**Mobile action:** show a WhatsApp contact button on development-opportunity screens
reading `whatsapp_link`, visible to anonymous/unregistered users. Don't gate it the
way `link` is gated.

---

## 4. Repost/Republish no longer blocked after the deadline (BE-64 — regression fix)

If the app has a "Repost" / "Republish" action on an ended opportunity (volunteer,
learn-&-serve, or event), it now works again. A regression briefly made every repost
fail with a 422 (`"This opportunity/event can no longer be republished after its
deadline"`) — this has been reverted. Repost is *only* ever used on opportunities
that have already ended, so blocking on the deadline made the button 100% broken.

The deadline guard is still correctly enforced on **reopen-registration** (see #2
above) — that's a different action and is meant to be blocked once its window closes.

**Mobile action:** none, other than confirming Repost works if you test it — no
contract change, just a bug fix.

---

## 5. Five fixes on the volunteer registrations / attendance screen (BE-67)

These affect `GET /api/volunteer-opportunity-registrations/`,
`PATCH /api/volunteer-opportunity-registrations/` (assign role/team),
`POST /api/volunteer-opportunity-registrations/direct-register/`, and
`GET/POST /api/scan-permission/` (the "grant scan permission" people-picker).

### 5.1 — `user` is now an object, not a bare id

**Before:**
```json
"user": 42
```

**After:**
```json
"user": {
  "id": 42,
  "emergency_contact_name": "...",
  "emergency_contact_phone": "...",
  "emergency_contact_civil_id": "...",
  "emergency_contact_relationship_display": { "id": 3, "value_en": "Parent", "value_ar": "..." }
}
```

`user_id` is still present at the top level too (unchanged), for whatever already
keys off it (e.g. the unregister call).

**Mobile action:** if any screen reads guardian/emergency-contact fields off a
registration row, read them from `row.user.*` now, not from a top-level flat field.

### 5.2 — Each attendance record now carries its own `id`

New `attendances` array on each registration row:

```json
"attendances": [
  {
    "id": 4412,
    "attended_date": "2026-09-18",
    "total_hours": 5.5,
    "checked_in_at": "2026-09-18T08:01:00+00:00",
    "checked_out_at": "2026-09-18T13:31:00+00:00"
  }
]
```

`date_wise_attended` (the old plain array of dates) is unchanged and still present.

**Mobile action:** if there's an "edit hours" / "undo check-in" action, use
`attendances[].id` as the id for `PATCH /api/volunteer-attendance/{id}/hours/` and the
undo endpoint. Previously there was no reliable way to get this id after a refresh —
this is now fixed at the source.

### 5.3 — Manually-added volunteers now get a confirmation notification

`POST /api/volunteer-opportunity-registrations/direct-register/` (the organizer
"add volunteer manually" action) now sends the same in-app notification + email a
self-registration sends. No response shape change — informational only.

### 5.4 — Search now matches civil ID / passport number

Both:
- `GET /api/volunteer-opportunity-registrations/?search=...`
- `GET /api/scan-permission/?search=...` (the people-picker for granting scan
  permission)

now match `civil_id` and `passport_number` in addition to name/email. No contract
change — same `search` query param, just broader matching.

### 5.5 — Assigning a role beyond its capacity is now rejected

`PATCH /api/volunteer-opportunity-registrations/` with a `role` that has already
reached its `participants_needed` now returns:

```json
422
{
  "msg": "This role has no remaining slots.",
  "response_status": { "error": true, "validation_errors": { "role": ["This role has no remaining slots."] } }
}
```

Re-saving a volunteer who is **already** on that role is not blocked (no-op safe),
so editing an unrelated field on an already-assigned registration still works even
if the role has since filled up around them.

**Mobile action:** if there's a role-assignment picker, show/disable roles that are
already full (the role's `assignedCount()` vs `participants_needed`, or just handle
the 422 with the `role` field error) rather than letting the assignment silently
"succeed" against a full role.

---

## Not built yet — needs a product decision before mobile can plan for it

The ticket also describes a **new** «إذن تحضير» permission (let a volunteer add other
volunteers and set their hours, distinct from QR scan delegation) planned for the
same screen. **This was intentionally left out** — the backend is waiting on
confirmation of whether it's one permission or two separate flags before building
grant/revoke/list endpoints. Nothing to integrate against yet; flagging so it isn't
assumed to exist.

---

# PDF review tasks

A separate review document (`مراجعه مع صور (2) (1).pdf`) was compared against
`FURSA_BACKEND_ISSUES (3).md` to check for backend-relevant notes the ticket file
didn't cover. Most of the PDF is frontend/UI mockup feedback (field ordering, icon
sizing, button styling) and is out of scope here. Four backend items were found and
are covered below; two more turned out to already be built (noted so nobody
re-implements them), and two are flagged as open questions rather than built, since
building them on a guess is worse than asking.

## 6. New volunteer opportunity category: "Administrative" (إداري)

`VolunteerCategory` gained a fifth case: `administrative` / «إداري», alongside the
existing `environmental`, `charity`, `organizational`, `educational`. It behaves
like any other category — no special counting logic (unlike `charity`, which counts
beneficiaries).

```json
"volunteer_category": "administrative",
"volunteer_category_display": { "en": "Administrative", "ar": "إداري" }
```

**Mobile action:** if the category picker on the volunteer-opportunity form is a
hardcoded list rather than driven by an API enum, add this fifth option.

**Note on ambiguity:** the PDF only said *"إضافة نوع فرصة جديد: إداري"* ("add a new
opportunity type: Administrative") with no further detail on where it belongs. This
was interpreted as a new `volunteer_category` value, since that field is exactly
"the opportunity's type" for volunteering and already has a fixed, small enum of
options shown as a dropdown on the create form. If "إداري" was actually meant for
something else (a learn-&-serve `learning_type`, or an event category), say so and
it'll move.

## 7. Learn-&-serve participants Excel export had a blank Name column

Root cause: the shared `RegistrationExport::download()` helper (used by the
learn-&-serve participants export **and** the scan-permission-picker export) read
`$user->full_name`, which was never a real field or accessor on `User` — it silently
evaluated to `null` on every row, in every export that uses this helper. Fixed by
adding a real `full_name` accessor to the `User` model. No response shape change —
the same `downloadUrl` flow, just with the Name column actually populated now.

**Mobile action:** none — this only affects the generated `.xlsx` file’s contents,
not any JSON field mobile reads.

## 8. Sponsor name/logo now hidden on a paid learn-&-serve opportunity

`GET /api/learn-serve-opportunities/{id}/` — `opportunity_sponsor_images` now
returns `[]` for a paid opportunity (`price` is not null), even if sponsors are
attached in the database. It's still returned normally for a free opportunity. The
reasoning: a sponsor funds a free opportunity; a paid one is already funded by
participants, so showing a sponsor's name on it doesn't make sense.

**Mobile action:** if a sponsor logo/name is rendered on the learn-&-serve detail
screen, it will now correctly disappear for paid opportunities without any
client-side check needed — just don't assume the array is always non-empty.

## Already built — nothing to do, flagging so it isn't redone

- **Payout / bank-details onboarding for paid opportunities.** The PDF asked for
  publishers to add bank details before publishing a paid opportunity, with a 7%
  platform fee deducted. **This was already fully implemented** (commit `9310cde`,
  predates this round): an individual (Volunteer Team) or Association publisher
  must have `bank_name` / `bank_account_holder_name` / `bank_account_number` on
  `PUT /api/organization-profile/` before `POST /api/learn-serve-opportunities/`
  will accept `is_paid: true` — a full Organization is exempt. The response
  includes `payout_after_fee`, computed from the admin-configurable
  `platform_fee_percentage` (defaults to 7). Covered by
  `tests/Feature/PaidOpportunityPayoutTest.php`. No money is actually moved by the
  app — the transfer itself is manual, outside the system.
- **Attendance hours capped at the opportunity's scheduled/daily hours.** The PDF
  flagged manually-entered or corrected attendance hours as accepting more than a
  day's scheduled duration. Checked the code: both
  `POST /api/volunteer-attendance/manual/` and
  `PATCH /api/volunteer-attendance/{id}/hours/` already reject an entry above the
  opportunity's scheduled hours for that day (computed per time-slot when one
  exists). The QR self-scan flow is intentionally uncapped, since it's meant to
  record *real* elapsed time, not the scheduled figure — that's by design, not a gap.

## Open questions — not built, needs an answer first

### أسئلة معلّقة لمطور الواجهة الأمامية

**١. حقل "آخر موعد" (`due_date`):** ملف الـ PDF يطلب أن يكون اختيارياً، بينما طلبكم
السابق في **BE-66** كان يطلبه إلزامياً — وهو الوضع الحالي المطبّق فعلياً على الـ API
(`POST/PATCH /api/volunteer-opportunities/` يرفض القيمة الفارغة). **المطور واقف
بانتظار قراركم: أي الرأيين نعتمد؟** لم يتم تغيير أي كود على هذه النقطة لحد ما يوصلنا
رد.

**٢. نافذة صلاحية الحضور (3 أيام أم 7 أيام):** تُركت معلّقة لحين التأكد من القيمة
الحقيقية المضبوطة في إعدادات السيرفر (`preparation_validity_hours` /
`preparation_validity_days` في جدول `config`) بدل ما نبني على تخمين من نص الـ PDF.
لسه محتاجين نقرأ القيمة الفعلية على الإنتاج قبل ما نقرر لو فيه حاجة تتغيّر.

---

- **Due date optionality contradicts BE-66.** The PDF says the volunteer
  opportunity's due-date field should become **non-required**; `FURSA_BACKEND_ISSUES
  (3).md`'s BE-66 says the opposite — due_date was made required again, and that's
  what's currently live. These two documents disagree; nothing was changed on this
  point pending a decision on which one is current.
- **Preparation/attendance window: 3 days vs 7 days.** The PDF's note on this is
  ambiguous about which number is "current" and which is "should be" — it may
  simply be flagging that production has drifted to 7 days when it should be 3 (the
  code's default, and what `FURSA_BACKEND_ISSUES (3).md` also expects). Worth a
  direct read of the production `config` row for `preparation_validity_hours` /
  `preparation_validity_days` rather than acting on the PDF text alone.

---

## Quick reference — routes touched this round

| Method | Route | Status |
|---|---|---|
| POST | `/api/learn-serve-opportunities/{id}/reopen-registration/` | **new** |
| POST | `/api/volunteer-opportunities/{id}/close-registration/` | now 422s within 1 day of `end_date` |
| POST | `/api/volunteer-opportunities/{id}/reopen-registration/` | now 422s within 1 day of `end_date` |
| POST | `/api/learn-serve-opportunities/{id}/close-registration/` | now 422s within 1 day of `end_date` |
| POST | `/api/volunteer-opportunities/` / update | `due_date` required |
| POST | `/api/learn-serve-opportunities/` / update | `whatsapp_link` accepted (nullable) |
| POST | `/api/events/republish/`, `/api/volunteer-opportunities/` (repost), `/api/learn-serve-opportunities/` (repost) | deadline-block regression reverted |
| GET | `/api/volunteer-opportunity-registrations/` | `user` now an object; `attendances[]` added |
| POST | `/api/volunteer-opportunity-registrations/direct-register/` | now sends a confirmation notification |
| GET | `/api/volunteer-opportunity-registrations/?search=` | matches civil_id/passport_number |
| GET | `/api/scan-permission/?search=` | matches civil_id/passport_number |
| PATCH | `/api/volunteer-opportunity-registrations/` | role capacity now enforced |
| POST | `/api/volunteer-opportunities/` / update | `volunteer_category` accepts `administrative` |
| GET | `/api/learn-serve-opportunities/{opportunity_id}/registrations/?download=true` | export Name column fixed |
| GET | `/api/learn-serve-opportunities/{id}/` | `opportunity_sponsor_images` empty when `price` is not null |
