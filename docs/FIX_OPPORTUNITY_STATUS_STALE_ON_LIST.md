# Fixed: `opportunity_status` always "upcoming" on `list-all-opportunities`

> **To:** Frontend
> **From:** Backend
> **Date:** 3 September 2026
> **Status:** ✅ Fixed and tested — no frontend changes needed
> **API:** `https://portal.fursa.raiyan.cc/api/`

Your diagnosis was right: `opportunity_status`/`event_status` was not being
recomputed the same way `action_state`/`has_started`/`has_ended` are.

**No frontend changes required.** `ProfileVolunteerCard.tsx` already reads
`opportunity_status` correctly — keep doing that, do not switch to
`action_state`. Deploy the backend and the badge will show the real state.

---

## 1) What was wrong

`opportunity_status` (and `event_status`) is a **stored database column**.
The only thing that ever updates it is a scheduled command,
`fursa:advance-statuses`, which is supposed to run once a day and flip each
opportunity's status based on its dates. Everything else on the payload —
`action_state`, `has_started`, `has_ended` — is **computed live** on every
request by comparing `now()` against `start_date`/`end_date`.

If that daily command doesn't run for a stretch (scheduler not reliably wired
into the server's cron, or an opportunity created after the day's run already
happened), the stored column just sits at whatever it was set to at creation —
`upcoming` — forever, while the live-computed fields correctly move on. That's
why every record in your two captured responses showed `upcoming` regardless
of its real dates: the stored column for those specific rows simply hadn't
been advanced yet.

This was **not** specific to `list-all-opportunities` — `/opportunities/{id}/details/`
had the exact same bug, reading the same stale column. It looked fine there
only because whichever records you happened to check that endpoint against had
already been advanced by the cron.

## 2) Answering your question #2: are they the same concept?

No — they're deliberately different, and both are still meaningful:

- **`opportunity_status`** (`upcoming` / `inprogress` / `completed` / `cancelled`)
  is the opportunity's **lifecycle state**. It's what should drive a status badge
  like yours. `cancelled` in particular is an admin action, not something dates
  can tell you.
- **`action_state`** (`register` / `unregister` / `full` / `closed` / `started` /
  `ended`) is the **registration button state** — it also folds in capacity and
  whether the current viewer is already registered, which `opportunity_status`
  has no concept of.

Keep using `opportunity_status` for the badge, exactly as you're doing now.

## 3) What changed

`opportunity_status`/`event_status` is now derived **live** from the dates
everywhere it's sent to a client — the same logic the daily cron uses
(`start_date > today` → `upcoming`, `start_date <= today <= end_date` →
`inprogress`, else `completed`) — with one exception: a manually set
`cancelled` status is still respected, since that can't be derived from dates.

This was applied to every endpoint that returns `opportunity_status`/`event_status`,
not just `list-all-opportunities`: `/opportunities/{id}/details/`,
`/learn-serve-opportunities/{id}/details/`, `/events/{id}/details/`, and the
admin dashboard status badges — so they'll now always agree with `action_state`,
`has_started`, and `has_ended` on the same payload.

The daily cron still runs and still owns two things this live value doesn't
replace: flipping a completed volunteer opportunity's `is_public` to `true`,
and crediting attendance for no-check-in learn/serve opportunities on
completion. Nothing about those side effects changed.

## 4) Re-tested repro

Reproduced your exact reported condition — records whose stored
`opportunity_status` column is stuck at `upcoming` (as if the cron hadn't
advanced them yet) — and hit `list-all-opportunities` for them:

```
GET /list-all-opportunities/?filter_type=organized&user_id={org_id}&page=1&limit=30
```

| title | start_date | end_date | opportunity_status | action_state | has_started | has_ended |
|---|---|---|---|---|---|---|
| Ended 3 days ago | 2026-08-29 | 2026-08-31 | **completed** | ended | false | true |
| In progress, started today | 2026-09-03 | 2026-09-18 | **inprogress** | started | true | false |
| Ended today (last day still counts as in-progress) | 2026-09-01 | 2026-09-03 | **inprogress** | started | true | false |
| In progress, started yesterday | 2026-09-02 | 2026-09-06 | **inprogress** | started | true | false |
| Upcoming | 2026-09-05 | 2026-09-08 | **upcoming** | register | false | false |

`opportunity_status` now varies correctly per item and agrees with
`action_state`/`has_started`/`has_ended` on every row, including the stored
column's stale-`upcoming` starting condition that reproduced your bug.

Covered by an automated regression test
(`tests/Feature/ApprovedOpportunityVisibilityTest.php`) that fails without
this fix and passes with it, so this can't silently regress again.
