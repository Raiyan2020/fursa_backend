# Certificate issuance: how it actually works, and why "Reeest" will never get one

> **To:** Frontend
> **From:** Backend
> **Date:** 3 September 2026
> **Status:** ℹ️ Business-analysis answer — not a bug, and not something waiting on a window to close
> **API:** `https://portal.fursa.raiyan.cc/api/`

Short version: your `preparation_valid_until_at` hypothesis was reasonable but
isn't the actual reason. **"Reeest" is a `volunteer_opportunity`, and
certificates don't exist at all for that opportunity type in this system
today** — not "not yet," structurally absent. It will stay in the empty
state forever, not just until some window closes. The automatic certificate
pipeline itself is confirmed working end-to-end (evidence below) — it's just
that it only ever applies to Learn&Serve opportunities.

## 1) What triggers certificate generation (ask #1)

Two triggers, both **Learn&Serve-only**:

1. **Manual, volunteer-initiated**: `POST /certificates/{registration_id}/`
   (`CertificateController::store`) — the volunteer/frontend calls this
   directly once attendance is recorded, and it renders + saves the
   certificate immediately.
2. **Automatic, scheduled**: `fursa:backfill-missing-certificates`, a cron
   job that runs **daily at 03:30**. It scans every Learn&Serve registration
   with `is_attended = true` and `is_certified = false`, and — if eligible
   (see below) — renders and saves the certificate the same way the manual
   endpoint does.

There is no organizer/admin manual-approval step in either path.

## 2) What has to be true first, and realistic timing (ask #2, part 1)

For a **Learn&Serve** registration:

1. `is_attended` must be `true`. For workshop/consultation-type opportunities
   (no check-in required) this is set automatically when
   `fursa:advance-statuses` (the daily job that also advances
   `opportunity_status`) marks the opportunity `completed`. For
   internship/course-type opportunities, this comes from an actual
   attendance check-in during the opportunity.
2. The opportunity's `certificate_type` + `learning_type` combination must
   match one of two hardcoded eligibility rules in the backfill command:
   - `certificate_type = "forsa certificate"` **and**
     `learning_type` in `[internship, course]`, or
   - `certificate_type = "organizer's certificate"` (any learning type).

   Anything outside those combinations is silently skipped — no error, no
   flag, it just never gets a certificate. This is a real gap worth knowing
   about even though it wasn't your original question.

**Realistic timing**: once both conditions above are true, expect the
certificate within 24 hours (the next `03:30` run), assuming attendance was
already finalized. It is **not** gated on `preparation_valid_until_at` /
`is_preparation_window_closed` at all — nothing in the certificate code path
reads those fields. That preparation window only governs whether a
check-in is still accepted, which is a separate, earlier step.

For a **volunteer_opportunity**, there is no equivalent — see next section.

## 3) Does it differ by opportunity type? (ask #2, part 2 / ask #3)

Yes, fundamentally — this is the actual answer to your original question:

| Type | Certificate mechanism |
|---|---|
| `learn_serve_opportunity` | Full pipeline described above (manual endpoint + daily auto-backfill) |
| `volunteer_opportunity` | **None.** No certificate columns on `volunteer_opportunity_registrations`, no `Certificate` model, no route, no eligibility logic — nothing |
| `event` | **None**, same as volunteer opportunities |

`preparation_valid_until_at` and `is_preparation_window_closed` only appear
on `volunteer_opportunity` payloads — which is exactly why "Reeest" (the
opportunity carrying those two fields in the response you captured) has no
certificate: it's a `volunteer_opportunity`, and that type was simply never
built to produce certificates. The volunteer's "0 شهادة" stat and empty
certificates tab are both correct/expected given the current implementation
— not a stuck pipeline, and not something that will resolve once any window
closes.

## 4) Is there a "pending" vs "not eligible" status the frontend could show? (ask #3, part 2)

Not today. `GET /user-certificates/` only ever returns rows that already
have `is_certified = true` (or a non-empty `certificate_image`) — there's no
API-surfaced distinction between "will get one soon," "ineligible
certificate/learning type," and "this opportunity type doesn't support
certificates at all." Adding such a status is a real option, but it's a
product/API-shape decision (what should "pending" mean for a
`volunteer_opportunity`, which structurally can never become non-pending?)
that needs your input before we build it — happy to scope it once you've
decided how the certificates tab should represent a `volunteer_opportunity`
completion, since today there's genuinely nothing to surface for that case.

## 5) Confirmed: the automatic pipeline itself is not stuck (ask #2, part 3)

Reproduced end-to-end against a real eligible Learn&Serve registration —
`is_attended = true`, `certificate_type = "forsa certificate"`,
`learning_type = "course"`, `is_certified = false`:

```
GET /user-certificates/?user_id={id}   →   BEFORE: []
```

Ran `php artisan fursa:backfill-missing-certificates` (the actual scheduled
job) —

```
registration.is_certified = true
registration.certificate_image = "certificates/registration_1.html"
```

```
GET /user-certificates/?user_id={id}   →   AFTER:
[{
  "registration_id": 1,
  "certificate_image": "https://portal.fursa.raiyan.cc/storage/certificates/registration_1.html",
  "opportunity__title_en": "Eligible Course",
  "opportunity__title_ar": "دورة مؤهلة",
  "organizer_name": "Test Organization"
}]
```

Then added a **completed `volunteer_opportunity` registration** for the same
user (same shape as "Reeest") and re-checked — the certificates list is
unchanged, still just the one Learn&Serve certificate, confirming
`volunteer_opportunity` completions produce nothing regardless of how much
time passes.

The cycle itself works correctly for the one opportunity type it was built
for; there's no gap between "attendance finalized" and "certificate
generated" beyond the daily 03:30 run.
