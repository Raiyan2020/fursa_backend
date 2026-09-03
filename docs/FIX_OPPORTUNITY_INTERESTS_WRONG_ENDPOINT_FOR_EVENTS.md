# `interests` missing on `/opportunities/35/details/` — root cause: wrong endpoint for an event, plus a real bug we found along the way

> **To:** Frontend
> **From:** Backend
> **Date:** 3 September 2026
> **Status:** ✅ Diagnosed and one real bug fixed — one action needed on your side too
> **API:** `https://portal.fursa.raiyan.cc/api/`

Thanks for the correction on `interest_display` vs `interests` — that saved
us chasing the wrong field. The actual finding is a bit different from
"data went missing," though, so please read before retesting.

## 1) The real root cause: `/opportunities/{id}/details/` is volunteer-opportunity-only

```
Route::get('opportunities/{opportunity_id}/details/', [VolunteerOpportunityController::class, 'opportunityDetails']);
```

This route **only ever queries the `volunteer_opportunities` table.** "إفطار صائم"
is an **Event**, not a volunteer opportunity — its correct detail endpoint is:

```
GET /events/{id}/
```

`volunteer_opportunities`, `events`, and `learn_serve_opportunities` are three
separate tables, each with its own auto-incrementing id — so id `35` on one
table has nothing to do with id `35` on another. Calling
`/opportunities/35/details/` for an event either:
- hits an unrelated volunteer opportunity that happens to also have id `35`
  and returns *its* (correct, empty) data — which is what you saw, or
- 404s, if no volunteer opportunity with that id exists.

Reproduced both cases directly: an event with a real "Charity Work" tag,
fetched via the wrong endpoint, plainly 404s
(`{"key":"fail","msg":"Opportunity not found."}`) — confirming this was never
a data-loss bug. **Nothing was lost; the tag was never queried.**

**Action needed on your side:** wherever `/volunteer-event-detail/{id}` (or
any event-detail screen) currently calls `/opportunities/{id}/details/`,
switch it to `GET /events/{id}/`. If there's a shared "opportunity details"
data-fetching layer that doesn't distinguish by type, it'll need to branch
on `opportunity_type` (or however you already track type on the calling
screen) to pick the right endpoint — `/opportunities/{id}/details/` for
`volunteer_opportunity`, `/learn-serve-opportunities/{id}/` for
`learn_serve_opportunity`, `/events/{id}/` for `event`.

## 2) A real bug we found while checking this: Events hadn't been migrated to the `interests` shape at all

Digging into `/events/{id}/` to confirm it would actually solve this for you,
we found it had never been updated to send the new `{ id, name_en, name_ar,
interest_type }` `interests` shape you described — only the legacy
`interest_display` (`{ value_en, value_ar }`, no id, no type). Your
`normalizeInterests()` fallback would have handled that fine (it's a real,
correctly-populated legacy shape, not null), but we brought events to full
parity anyway rather than leaving a silent inconsistency:

- `GET /events/{id}/` now also returns `interests` in the current shape,
  alongside the existing `interest_display`.
- The **write-path** responses (`POST /events/`, `PATCH /events/{id}/`,
  `POST /events/{id}/close-registration/`) had an actual bug: `interests`
  came back as a bare array of ids (`[9]`, not `[{id, name_en, ...}]`) with
  **no `interest_display` at all**. If your create/edit event flow reads
  the response to show tag names immediately, this would have broken
  silently. Fixed to match the read-path shape.

## 3) Re-tested: confirmation

Event "إفطار صائم"-equivalent, tagged "Charity Work":

```
GET /opportunities/{event_id}/details/   →  HTTP 404 (wrong table — confirms no data loss)

GET /events/{event_id}/                  →  HTTP 200
{
  "interests": [{"id": 1, "name_en": "Charity Work", "name_ar": "أعمال خيرية", "interest_type": "volunteer"}],
  "interest_display": [{"value_en": "Charity Work", "value_ar": "أعمال خيرية"}]
}

PATCH /events/{event_id}/                →  HTTP 200, same `interests`/`interest_display` shape as above
```

Covered by an automated regression test
(`tests/Feature/ClientFeedbackRoundTwoTest.php::test_event_details_and_write_responses_include_interests_shape`).

To scope whether this affected other records too (your ask #2): it's not a
data issue at all, so there's no "affected records" to count — every event
was always reachable correctly via `/events/{id}/`; only the endpoint choice
was wrong, which is why the same tag shows up fine at `/opportunities/{a
real volunteer opportunity's id}/details/` for volunteer opportunities.
