# Fursa — backend issues

**Opened 2026-09-21 against branch `updates`, commit `2d12b39`.** Three new issues from the
client: **BE-75** (the departure scan, four parts), **BE-76** (one announcement image per
opportunity) and **BE-77** (the four-way nationality audience — *blocking, the frontend cannot
ship its half without you*). Nothing else is open.

The previous revision — the BE-74 close and the BE-57 correction — is archived in full at
`FURSA_BACKEND_ISSUES_ARCHIVE_2026-09-21.md`. The BE-57 apology is repeated once at the bottom of
this file because we are not sure it has been read yet; it goes to the archive for good after this
round.

Every closed issue from BE-1 through BE-74 is preserved in
`FURSA_BACKEND_ISSUES_ARCHIVE_2026-09-20.md` and its `b` / `c` / `d` siblings.

---

## BE-75 — The departure scan: a two-hour grace period after the session ends

**The client's rule, verbatim:** an opportunity running **5 → 9** must accept the volunteer's
**leave** scan until **11**. Two hours past the scheduled end, and only for the departure
direction — arrival is unchanged.

**This is volunteering only.** Learn & serve has a single code with no direction, already expiring
two hours after *issuance* (`issueLearnServeAttendanceCode`), so there is nothing to change there.
Please do not go looking.

### The frontend has shipped its half

`VolunteerEvent.tsx` now hides the «مسح رمز الخروج» button once the deadline passes and tells the
volunteer to ask the organizer instead, and it says the deadline out loud while the grace period is
still running. The deadline is computed in `features/opportunities/selfCheckOutWindow.ts` from
`end_time` (or the matching `time_slots[].end_time`) plus two hours, anchored on
`self_attendance.checked_in_at`.

**That gate is cosmetic and we know it.** `POST /volunteer-attendance/self-scan/` still accepts a
departure scan at any hour of the day. Parts A and D below are what actually makes the rule real.

### A. Enforce the deadline in `selfScan()`

`VolunteerAttendanceController::selfScan()` gates on the **day** only:

```php
$attendanceDate = now()->toDateString();
if (! $opportunity->isWithinPreparationWindow($attendanceDate)) { … }
```

`isWithinPreparationWindow()` compares `startOfDay()` values, so there is no time-of-day check
anywhere in the path. A volunteer on a 5 → 9 opportunity can currently scan out at 23:58 and it is
accepted.

For `direction === 'out'`, refuse when `now()` is past **the scheduled end of the session the
check-in belongs to, plus the grace period**:

- session end = the `time_slots` row for the attendance's `attended_date` if there is one, else the
  opportunity's `end_time`, combined with that date;
- if the end is at or before the start, the session runs overnight — roll the end to the next day
  (a 21:00 → 01:00 opportunity otherwise looks like it ended twenty hours ago);
- add the grace period.

**Please make the two hours a config row, not a constant** — `self_check_out_grace_hours`, default
`2`, read through `Config` the way `preparation_validity_hours` and (since BE-74)
`platform_fee_percentage` are. The check-in window's own length has already been changed once by
the client (48 → 72), and this number will be asked about the same way.

Suggested refusal, 400:

- EN: `The departure window closed at {time}. Ask the organizer to record your departure.`
- AR: `انتهت مهلة تسجيل الانصراف في {time}. اطلب من الجهة المنظِّمة تسجيل انصرافك.`

The organizer's manual path stays open — the volunteer is checked in with no check-out, which is a
state the organizer can already close by hand. Nobody should lose their hours over a missed scan.

### B. Send the deadline on the payload: `self_check_out_closes_at`

Add an ISO-8601 `self_check_out_closes_at` to `self_attendance` (or top-level on
`VolunteerOpportunityResource`), set only while a check-out is pending.

**`selfCheckOutWindow.ts` already prefers it over its own derivation** — the moment it appears in
the payload the browser stops computing anything, with no frontend release needed.

This matters more than it looks. Today the browser combines a bare `end_time` string with a local
date, so the deadline is evaluated in **the device's timezone**. A volunteer whose phone is not on
Asia/Kuwait — travelling, or simply a misconfigured handset — sees a deadline that disagrees with
the one the server will enforce in part A. One is a UI that lies; two disagreeing gates is a
support ticket. An absolute instant from the server ends that.

### C. The grace period must not be credited as worked hours

`AttendanceService::selfCheckOut()`:

```php
$checkedOutAt = now();
$hours = round($attendance->checked_in_at->floatDiffInHours($checkedOutAt), 2);
```

On a 5 → 9 opportunity, a volunteer who scans out at 10:45 is credited **5.75 hours for a
four-hour shift**, and those hours flow into the profile totals and the certificate.

Until now that was an edge case. **The client has just made it the normal path** — the whole point
of part A is that people will scan out late, on purpose, with permission.

**The client's rule, stated directly: the opportunity's own duration is the ceiling.** A 5 → 9
opportunity can never pay more than four hours, whatever the scans say. Their worked example,
verbatim:

> event from 5pm to 9pm ⇒ 4 hours. If the user scanned 5:30 and left 10:50, it will count from
> 5:30 to 9 → **3 hours 30 mins**.

So the credited interval is the **overlap between the volunteer's presence and the scheduled
session**, not the raw scan-to-scan difference:

```php
$creditFrom  = max($attendance->checked_in_at, $sessionStart);  // scanned in early? starts at 5:00
$creditUntil = min($checkedOutAt, $sessionEnd);                 // scanned out late? stops at 9:00
$hours = max(0, round($creditFrom->floatDiffInHours($creditUntil), 2));
```

Note what this does *not* do: a late arrival is **not** rounded up. 5:30 → 9:00 is 3.5 hours, not
4 — the ceiling is the session length, not a flat award. Both the example and the phrase "the max
time is the event time" say the same thing, and the overlap formula is the only one that satisfies
both ends of it.

`$sessionStart` / `$sessionEnd` are the same pair as part A (the `time_slots` row for that date,
else the opportunity's `start_time`/`end_time`, with the overnight roll).

**Keep the real `checked_out_at` as recorded** — the audit trail should show when they actually
scanned, and the organizer needs to see that someone left at 10:50. Only `total_hours` is clamped.

Two consequences worth confirming when you implement it:

- **`max(0, …)`** is not decoration. A volunteer who scans in *after* the session end — possible
  today, since arrival has no time gate — would otherwise produce a negative interval and subtract
  hours from their profile total through `AttendanceService::updateHours()`, which applies a delta.
- **The organizer's manual override stays above this cap.** `PATCH /volunteer-attendance/{id}/hours/`
  is how a genuinely extended shift gets recorded, and clamping that too would remove the only way
  to record legitimate overtime. The cap belongs on the self-scan path, not on `updateHours()`.

### D. The cross-midnight lookup — the part that makes the grace period unusable tonight

This one is load-bearing, so it is spelled out.

`selfScan()` finds the row to close with:

```php
$attendanceDate = now()->toDateString();
$attendance = VolunteerOpportunityAttendance::query()
    ->where('registration_id', $registration->id)
    ->whereDate('attended_date', $attendanceDate)
    ->first();
```

Take an opportunity running **21:00 → 23:00**. Its grace period ends at **01:00 the next day** —
squarely inside the window the client just asked for. A volunteer who scans out at 00:30 hits a
lookup for **today's** date, finds no row (their check-in is filed under yesterday), and gets:

> You must check in before checking out. — يجب تسجيل الحضور قبل الانصراف.

So the feature is silently dead for every evening opportunity that ends within two hours of
midnight, and it fails with a message telling the volunteer they did something they demonstrably
did do.

Resolve the row from **the open check-in** rather than from today's date: the volunteer's most
recent attendance on this registration that has `checked_in_at` set, `checked_out_at` null, and
whose session end plus the grace period has not passed. Today's-date matching can stay as the
first attempt; this is the fallback when it comes back empty.

Note this also affects the `isWithinPreparationWindow($attendanceDate)` call two lines above — at
00:30 it is asked about the wrong day. Once the row is resolved first, pass
`$attendance->attended_date` into it instead of `now()`.

### Acceptance — what we will check

A 5 → 9 opportunity throughout.

| Scans | Expected |
|---|---|
| in 5:00, out 9:00 | accepted, `total_hours` **4.0** |
| in 5:30, out 10:50 | accepted, `total_hours` **3.5** — the client's own example |
| in 5:00, out 10:59 | accepted, `total_hours` **4.0**, `checked_out_at` still 10:59 |
| in 4:30, out 9:00 | accepted, `total_hours` **4.0** — early arrival is not paid |
| in 5:00, out 11:01 | **refused**, with the message in part A |
| 21:00 → 23:00, out-scan 00:30 next day | **accepted**, filed against yesterday's row |
| 21:00 → 23:00, out-scan 01:30 next day | refused |
| any in-scan | unchanged — no new gate on arrival |
| organizer `PATCH …/hours/` with 6 | **6 is kept** — the cap is on self-scan only |
| `self_attendance` with a check-out pending | carries `self_check_out_closes_at` |
| config `self_check_out_grace_hours` = 3 | the deadline moves, no deploy |

---

## BE-76 — One announcement image per opportunity / event, cropped 4:5

**The client's rule:** the «تحميل صورة» field on the publish forms takes **exactly one image**, and
the crop is **4:5 — Instagram portrait**.

### Shipped on the frontend

- The field is capped at one, end to end: the OS picker no longer offers multi-select, only one
  preview can exist, and the submitted array holds one file. `VolunteerForm`, `LearnServeForm` and
  `EventForm` all pass `singleFileArray` now.
- **The 4:5 crop was already live** and needs nothing — `cropWidth={600} cropHeight={750}` with
  `cropDisplayMode="opportunity"`, and the cropper frame renders at that ratio. Mentioned only so
  you do not go looking for work that is not there.

In edit mode an opportunity that already carries several images still shows all of them, and the
publisher removes down to one by hand. **We are not deleting anyone's data from the browser.**

### A. Enforce the cap — and only on the announcement image

`HandlesOpportunities::storeAnnouncementImagesFromRequest()` loops over every
`new_opportunity_images_*` file in the request and creates an `OpportunityImage` row per file, with
no count check anywhere. The admin dashboard, the mobile app and plain curl can all still attach
five, and the web form's new limit does nothing about that.

**The scoping detail that matters more than the cap itself:**

> The limit is on `is_after_completed = false` **only**.

`is_after_completed = true` is the after-completion gallery, uploaded through
`updateOpportunityImages()` and meant to hold many photos of the day. A blanket "max one image per
opportunity" would take that feature out, and it would look like a small change on the way in. The
publish forms only ever read and write the `false` set (`VolunteerForm` filters
`is_after_completed === false` when loading the editable set, and sends
`new_opportunity_images_is_after_completed_{i}=0` on submit), so the two sets are already cleanly
separated — please keep them that way.

Same rule for all three: volunteer opportunities, learn & serve opportunities, and events.

**One decision is yours:** on update, should a second announcement image **replace** the existing
one, or be refused with a 422? The form's own behaviour is replace-after-remove, so either is
consistent with it. Tell us which you pick and we will match the frontend to it.

### B. How many records already have more than one? — a go / no-go, and we will act on it

Please give us the count of opportunities and events holding **two or more** announcement images
(`is_after_completed = false`).

We are not asking you to delete anything, and if the number is zero we will close this half on the
spot. If it is not, the question for the client is whether a backfill is wanted or whether
publishers tidy their own as they edit — and that is a question we cannot put to them without the
number.

Said plainly, because we got this wrong recently: this is a **go / no-go before building**, the
count will be used the day it arrives, and it will not be asked for a second time.

### C. Aspect ratio on the server — a question, not a request

The browser always crops to 4:5 before upload, so every image from the web form is already
correct. A direct API call is not, and `opportunity_card_images()` returns whatever is stored.

We are **not** asking you to reject off-ratio uploads — that would break the admin dashboard and
the mobile app for no benefit the client has asked for. The question is only whether you already
have an image pipeline that could store a 4:5 derivative for the cards. If not, leave it: the
cards centre-crop and the result is acceptable.

### Acceptance

| | Expected |
|---|---|
| create with 1 announcement image | unchanged |
| create with 3 announcement images | 1 stored (or 422 — your call in A) |
| update adding a 2nd announcement image | replaces, or 422 — consistently with create |
| `POST …/images/` with 6 after-completion photos | **all 6 stored** — the gallery is untouched |
| existing record with 4 announcement images, opened and saved | not silently pruned |

---

## BE-77 — `is_kuwaitis` becomes a four-way audience, and it must block registration

**⚠️ This is the one issue here that blocks us.** The other two degrade politely; this one cannot.
Details in "Why we are blocked" below.

### The client's rule

The nationality field on every publish form becomes a **single select with four values**:

| value | العربية | English |
|---|---|---|
| `all` | كل الجنسيات | All nationalities |
| `kuwaitis` | كويتيين | Kuwaitis |
| `non_kuwaiti_arabic` | غير كويتي ناطق بالعربية | Non-Kuwaiti Arabic speakers |
| `non_arabic` | غير ناطق بالعربية | Non-Arabic speakers |

The three specific values **partition the population** — every person is exactly one of them — so
`all` is their union and the default, not a fifth group.

**And the client has asked for it to be enforced: a user who does not match must be refused
registration.** Today `is_kuwaitis` blocks nothing; it appears only in the list filters and on the
detail page. We checked the whole registration path for a nationality guard and there is none.

### A. The column

Replace the `is_kuwaitis` boolean on `volunteer_opportunities` and `learn_serve_opportunities`
with `opportunity_nationality`, one of the four values above, default `all`.

Backfill is exact and lossless: `is_kuwaitis = true → kuwaitis`, `false → all`. Nothing else was
expressible, so nothing is guessed.

Please **keep accepting and returning `is_kuwaitis`** for at least one release. The admin
dashboard, the mobile app and our own fallback all still read it, and it maps cleanly in both
directions for the two legacy values.

### B. The part that needs a decision before any code: **there is no data to match on**

`kuwaitis` is checkable — `users.nationality` already holds `kuwaitis | other`.

**The other two are not.** Nothing anywhere in the schema records whether a person speaks Arabic.
The individual registration form asks for nationality and residency
(`kuwaiti` / `non_kuwaiti_resident` / `non_kuwaiti_non_resident`) and no language question. So:

- `non_kuwaiti_arabic` — needs "non-Kuwaiti" **and** "speaks Arabic". We have the first half.
- `non_arabic` — needs "does not speak Arabic". We have none of it.

Blocking registration therefore requires a **new user attribute**, e.g.
`users.speaks_arabic` (boolean, nullable), asked on individual registration and editable in the
profile. That is a change to the signup flow, which is the client's call as much as yours, and it
brings a third question with it:

> **What happens to the ~existing accounts that have no answer?** A `null` cannot be blocked
> without locking real users out of opportunities they qualify for, and cannot be allowed through
> without making the rule advisory for everyone who registered before today.

Our suggestion, for you to accept or replace: **`null` passes.** The rule then applies to everyone
who has answered, tightens naturally as accounts are updated, and never refuses somebody because
of a question we did not ask them. A prompt to complete the field can come later.

**We are not building the frontend for the new signup question until this is settled** — tell us
the field name and whether `null` passes, and we will add it to registration and the profile in
the same round you ship the column.

### C. The refusal

Same shape as the age guard, which already works this way — `ConfirmVolunteerRegistrationModal`
reads the reason out of the envelope and shows it, including the HTTP 200 + `key: "fail"` form. So
**you need no new frontend work for the block itself**, only a message worth reading:

- EN: `This opportunity is open to {audience} only.`
- AR: `هذه الفرصة مخصصة لـ {audience} فقط.`

Both volunteer and learn & serve registration.

### D. The list filter

`opportunity_nationality` as a query param currently understands `kuwaitis` and `non-kuwaitis`
only, and **silently ignores anything else** — the list then comes back unfiltered, which reads as
a correct answer. Please make it accept the four values (and keep `non-kuwaitis` working).

### Why we are blocked — and what we shipped anyway

Two things stop us finishing without you, and it is worth being exact about which:

1. **`RejectsUnknownWriteKeys` 422s the whole publish form** on an unrecognised key, so we cannot
   send `opportunity_nationality` before you accept it. (That trait is doing its job here — this
   is exactly the silent-drop class of bug it was added for. We are not asking you to weaken it.)
2. **Two of the four values have nowhere to land.** `non_kuwaiti_arabic` and `non_arabic` both
   collapse to `is_kuwaitis = false`, i.e. «كل الجنسيات». A publisher picking either one today
   would have their choice quietly downgraded on save.

So the frontend is **built and dormant**. All four options render in both publish forms and both
detail pages read the new field with a fallback to the boolean. One constant,
`OPPORTUNITY_NATIONALITY_FIELD_LIVE` in `src/data/Constants.ts`, currently `false`, gates the wire
format: flip it and the forms start sending the field and the filter offers all four values.
Nothing else changes.

**Until you ship, the two new options are not safe to use.** We have told the client that.

### Acceptance

| | Expected |
|---|---|
| existing `is_kuwaitis = true` row after backfill | `opportunity_nationality = kuwaitis` |
| existing `is_kuwaitis = false` row after backfill | `all` |
| publish with `opportunity_nationality=non_arabic` | stored, returned on the resource |
| publish with `is_kuwaitis=1` and no new key | still works — one release of overlap |
| Kuwaiti user registers on a `kuwaitis` opportunity | allowed |
| Kuwaiti user registers on a `non_arabic` opportunity | refused, with the message in C |
| user with no `speaks_arabic` answer | passes (or your rule, once B is settled) |
| `GET …?opportunity_nationality=non_kuwaiti_arabic` | filtered, not ignored |

---

## Not issues — three production questions, still open

Unchanged, nothing blocked on them, listed so they are not lost:

- The production `EXPOSE_OTP_IN_RESPONSE` value.
- Whether `fursa:backfill-sanitize-rich-text` has been run against production.
- Whether `fursa:backfill-generated-link` has been run against production.

---

## BE-57 — repeated once, then it goes to the archive

Written in the previous revision and repeated here only because we do not know whether it reached
you. **BE-57 was fixed and closed by you on 2026-09-17** — the guard is at
`IdentityDocumentValidator.php:42`. We carried it forward as an open item for three rounds anyway,
which is why you were asked for the row count twice (102, then 101) and for the original ticket
text twice. There was nothing to scope, because the fix had already shipped. The line "fourth round
of asking" was wrong and is withdrawn.

**Nothing to build, nothing to scope, no ticket text to resend.** Please drop it from your
outstanding list. From now on a closed issue is *absent* from this file rather than carried with a
note — that copy-forward is exactly what caused this.

---

## ✅ When you report back

One report covering BE-75 and BE-76, naming the exact filename you worked from at the top as you
have been doing.

Three things to read twice:

- **BE-75 part D** — A and B are new rules, but D is a bug that is already there and that the new
  rule will walk straight into.
- **BE-76 part A** — the cap must land on `is_after_completed = false` only. The obvious
  implementation takes out the after-completion gallery.
- **BE-77 part B** — please answer this one before writing any code. Two of the four audiences
  cannot be matched against a user at all today, and the answer decides whether this is a column
  change or a signup-flow change.

**BE-77 is the blocking one.** BE-75 and BE-76 improve behaviour that already works; BE-77 is a
feature the client has asked for that we have built and cannot turn on.

**تقرير واحد لـ BE-75.** الجزء **D** هو الأهم — ده مش قاعدة جديدة، ده باج موجود دلوقتي: أي فرصة
بتخلص قبل منتصف الليل بساعتين، تسجيل الانصراف فيها هيفشل ويقول للمتطوع «سجّل حضورك الأول» وهو
مسجّله فعلاً.

والجزء **C** قاعدة العميل نصاً: **مدة الفرصة نفسها هي الحد الأقصى.** فرصة من ٥ لـ ٩ عمرها ما
تدّي أكتر من ٤ ساعات. ومثاله: دخل ٥:٣٠ وخرج ١٠:٥٠ ⇒ تتحسب من ٥:٣٠ لـ ٩ = **٣ ساعات ونص**. يعني
التقاطع بين وجود المتطوع والوقت المجدول — مش الفرق بين المسحتين، ومش تقريب لأعلى: اللي اتأخر
نصف ساعة بياخد ٣.٥ مش ٤.

و**BE-76**: صورة واحدة بس لكل فرصة/فعالية، والقص 4:5 (ده شغّال خلاص من قبل كده، مش محتاج حاجة).
أهم نقطة: الحد ده على **صور الإعلان بس** (`is_after_completed = false`) — جاليري ما بعد الانتهاء
لازم يفضل يقبل صور كتير. أسهل تنفيذ للحد هو اللي هيقفل الجاليري من غير ما حد ياخد باله.

و**BE-77** هو المعطِّل الوحيد: حقل الجنسية بقى أربع اختيارات، والعميل عايزه **يمنع التسجيل** مش
يعرض بس. مشكلتين: `RejectsUnknownWriteKeys` بيرجّع 422 على أي مفتاح جديد، فمش قادرين نبعت الحقل
قبل ما تقبلوه؛ والأهم إن **مفيش أي بيانات في الداتابيز تقول إن الشخص بيتكلم عربي ولا لأ**، يعني
اختيارين من الأربعة مالهمش أي طريقة يتطابقوا بيها مع مستخدم. الجزء **B** فيه السؤال ده والاقتراح
بتاعنا — محتاجين ردكم قبل أي كود. الفرونت مبني وجاهز ومقفول على ثابت واحد بنفتحه يوم ما الحقل ينزل.
