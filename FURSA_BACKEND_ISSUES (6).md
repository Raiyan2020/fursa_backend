# Fursa — backend issues

**Re-verified 2026-09-20 (third pass) against branch `updates`, commit `b6ab76c`,** after the
backend's `FURSA_CHANGES.md` report.

**BE-71, BE-72 and BE-73 are all done and have been removed from this file.** Each was checked by
reading the branch — the resource field, the export's header and row arrays, the model method — not
taken from the report.

**Worth saying: you did more than your report claims.** `FURSA_CHANGES.md` gives status lines for
BE-68/69/70/71 and does not mention BE-72 or BE-73 at all — but both are in `b6ab76c` and both are
correct. The report looks to have been written against an older copy of this file. No harm done
here because we read the branch, but a future round could easily have re-filed work you had already
finished.

Earlier revisions of this file, with every closed issue from BE-1 through BE-73 in full, are in
`FURSA_BACKEND_ISSUES_ARCHIVE_2026-09-20.md`, `..._2026-09-20b.md` and `..._2026-09-20c.md`.
Nothing was discarded; this file is only what is still open.

### What is open

| | | Severity |
|---|---|---|
| **BE-74** | The publish form is the one screen that still cannot read the platform fee — the other half of BE-71 | LOW |
| **BE-57** | Carried over: needs one row count read from production before it can be closed or declined | LOW |

**Nothing here blocks anything, and neither item is a bug.** This is the shortest this file has
been.

---

## Closed this round — verified, no action needed

- **BE-71 — `platform_fee_percentage` exposed.** Done the better of the two ways we offered:
  `payoutAfterFee()` was refactored to call the new `platformFeePercentage()`, and the resource
  returns both. They now read the same config value through the same method, so the number a
  publisher is shown and the number their payout uses **cannot** drift apart — which was the whole
  point of the ticket, not merely having the field somewhere.
- **BE-72 — the development participants export.** Phone plus the four guardian columns added on
  the `learn-serve` branch, `Scan allowed` dropped from it, and `with('user.emergencyContactRelationship')`
  on the eager load so the relationship column does not cost a query per row. The `$type`-driven
  branch keeps the events and scan-permission exports untouched, which is what we hoped for rather
  than four unconditional columns.
- **BE-73 — the stale Team column.** Gone from both the header and the row in the volunteers
  export, and **nothing else was touched**: the `team` key on the resource, the `team` parameter on
  the PATCH and the teams endpoints are all still there. That is exactly the line we asked you to
  draw — the column was dead, the API surface is not ours to remove.

---

## BE-74 — The publish form is the one screen that cannot read the platform fee

> **⚠️ Not addressed — and it is the other half of a ticket we under-specified.** BE-71 asked for
> `platform_fee_percentage` "on `LearnServeOpportunityResource`, or on a general config endpoint",
> and you did the first. That was the right choice and it is correct. We simply named the wrong
> screen as the important one, which is our error, not yours.

**Severity: LOW — the fallback is the same number, so nothing is wrong today.**

### The gap

The «يتم خصم ٧٪ من قيمة الفرصة» note sits under the **price field on the publish form**, which is
where a publisher decides what to charge. At that moment no opportunity exists yet, so there is no
`LearnServeOpportunityResource` to read the percentage from.

So the screen that matters most is the one screen still showing a hardcoded constant:

| Screen | Reads |
|---|---|
| Edit / repost a development opportunity | the live `platform_fee_percentage` ✅ |
| **Create** a development opportunity | a `7` constant in `LearnServeForm.tsx` |

Today both say 7, because the constant matches your default. The day an admin sets the fee to 10,
the create form says 7 and the edit form says 10 — on the same screen, for the same opportunity,
one save apart.

### What we need

`platform_fee_percentage` on any endpoint an authenticated publisher already calls before creating
an opportunity — whatever config/settings or profile payload is most natural on your side. We have
no preference, and we are not asking for a new route if an existing payload will carry it.

If there is no such endpoint and adding one is not worth it for a single number, **say so and we
will keep the constant** with a comment recording that you decided it. That is a perfectly good
answer for a value that has never changed.

---

## BE-57 — Carried over, and it only needs a number

Not restated in full — it is in the archive. The position is unchanged across four rounds, and
`FURSA_CHANGES.md` sets it aside again as "not a mobile-facing item", which is true and is not the
question.

**How many user rows have `nationality = 'other'` with `residency_status` NULL?** That is the
population BE-57 affects; everything created before 2026-08-30 is a candidate. **If the answer is
zero, BE-57 is a "won't fix" and we will close it on your word.** We cannot read production from
here. One query closes the issue either way, and it is now the oldest thing in this file by a wide
margin.

---

## What the frontend shipped against this round

| Your change | Ours |
|---|---|
| BE-71 `platform_fee_percentage` | the fee note on the development form reads the live value when editing or reposting, and falls back to the constant only on create (BE-74). Read with `??` rather than `\|\|`, so an admin waiving the fee to **0** shows 0 and not 7 |
| BE-72 | nothing to do — the export is generated server side |
| BE-73 | nothing to do; we had already stopped sending or reading `team` anywhere |

Also shipped this round, from the client's review of the participants list
(`/learn-share-register-list`) — no backend change needed, but **one of them is worth reading**
because it is the third time this number has come back:

| | |
|---|---|
| Page order | search, then the window banner, then the export button above the table. Export used to sit at the very bottom of the page |
| The «٧٠٪» sentence | moved *inside* the banner frame, at the client's request — it says what recording attendance means, so it belongs with the deadline for recording it |
| **«خلي التحضير 3 أيام بالكثير»** | **Already 3 days. No change made.** The client read «متبقٍ شهر واحد» as the window's length; it is today-to-deadline on an opportunity that has not ended yet. The opportunity ends 31 Oct and the window closes 3 Nov 23:59 — `end_date->endOfDay()->addHours(72)`, exactly 72 hours, exactly as configured |

That last one has now been reported as a bug **three times** (once as "7 days not 3", once as
"2 months"), and each time the setting was correct and the wording was the problem. So the banner
now states the window's own length — «نافذة التحضير تُغلق بعد ٣ أيام من انتهاء الفرصة» — **computed
from `end_date` and the window's end, never hardcoded**, precisely because
`preparation_validity_hours` is yours to change and a "3" written into our copy would go stale in
silence the moment you did. If you ever change it, our copy follows on its own.

---

## Verification that could not be performed here

This workspace has **no PHP runtime, no Composer and no installed `vendor/`**, so `php artisan test`
was **not** run. Everything marked closed above was verified by **reading the branch**. Your live
curl run against a running server is a real improvement on "the tests are green" and is noted.

Still outstanding from earlier rounds, alongside the BE-57 count: the production
`EXPOSE_OTP_IN_RESPONSE` value, and whether `fursa:backfill-sanitize-rich-text` and
`fursa:backfill-generated-link` have been run against production.

---

## ✅ Final task — send back a completion report when everything is done

**This is the last task in this file. Please do it after all the work above is finished.**

**هذه آخر مهمة في الملف — من فضلك نفّذها بعد الانتهاء من كل الشغل اللي فوق.**

Two things from this round are worth naming:

- **BE-71 was not done the lazy way.** Adding a `platform_fee_percentage` field beside
  `payout_after_fee` would have satisfied the ticket and still allowed the two to disagree later.
  Refactoring `payoutAfterFee()` to call the same method means they are now structurally incapable
  of disagreeing. That is the difference between closing a ticket and fixing the thing behind it.
- **BE-73 stopped exactly where we asked.** The dead column went; the `team` key, the PATCH
  parameter and the teams endpoints stayed. Removing them too would have looked tidier and broken
  the mobile app.

The one thing to fix is the **report, not the code**: `FURSA_CHANGES.md` does not mention BE-72 or
BE-73, both of which you actually did. Please write the report against the copy of this file you
worked from, so finished work is not invisible.

حاجتين تستاهلوا الذكر: BE-71 اتعملت بالطريقة الصح — `payoutAfterFee()` بقى بينده نفس الميثود، فالرقم
اللي بيتعرض والرقم اللي بيتحسب بيه مستحيل يختلفوا؛ وBE-73 وقفت عند الحد اللي طلبناه بالظبط. الملاحظة
الوحيدة على **التقرير مش على الكود**: `FURSA_CHANGES.md` مش فيه ذكر لـ BE-72 ولا BE-73 رغم إنك
عملتهم — اكتب التقرير من نفس نسخة الملف اللي اشتغلت عليها.

### What is left / الباقي

Two, and both are one-liners:

1. **BE-74** — the fee on the publish form, or a line saying you would rather we keep the constant.
2. **BE-57** — one row count from production, which closes it either way.

### What to send back / المطلوب في التقرير

1. **A status line for BE-74** — **Done**, or **Not doing** (and why). Both are fine answers.

   **سطر حالة لـ BE-74** — **خلصت** ولا **مش هتتعمل** (وليه). الردين مقبولين.

2. **The BE-57 row count**, read from production. Fourth round of asking.

   **عدد الصفوف بتاع BE-57** من الـ production. دي رابع مرة نسأل عليها.

3. **Anything you changed that is not in this file** — a schema change, a renamed field, a new
   required parameter.

   **أي حاجة غيّرتها مش موجودة في الملف ده** — تغيير في الداتابيز، اسم حقل اتغير، باراميتر جديد مطلوب.

### Done means / يعني إيه خلصت

The code is written, it is reviewed, the behaviour is verified on a running instance, and the BE-57
number is filled in. If something is still open, say so plainly in the file rather than leaving it
out.

الكود اتكتب، واتراجع، والسلوك اتأكدنا منه على نسخة شغالة، ورقم BE-57 اتكتب. لو فيه حاجة لسه
مفتوحة، اكتبها بصراحة بدل ما تسيبها.
