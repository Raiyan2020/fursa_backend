# Fixed: "Match My Interest" returned zero results for every user

> **To:** Frontend
> **From:** Backend
> **Date:** 3 September 2026
> **Status:** ✅ Fixed and tested — **no frontend changes needed**
> **API:** `https://portal.fursa.raiyan.cc/api/`

## TL;DR

Real backend bug, reproduced and fixed. It was **neither** of the two causes you
hypothesised — not missing interest tags on opportunities, and not an empty test
profile. Your payload and toggle were correct throughout.

`match_my_interest` was matching against the **wrong one of two** user-interest
tables, so it returned zero for **every** user who picked interests through the
normal profile UI. Additionally, the learn-serve listing ignored the flag
entirely.

## 1) Root cause

A user's interests are stored in one of two places:

| Relation | Table | Written by |
|----------|-------|-----------|
| `masterInterests` | `master_choice_user` → `MasterChoice` (choice type `user_interest`) | **the current profile UI** |
| `interests` | `interest_user` → `Interest` | legacy / older accounts |

Opportunities are only ever tagged with **`Interest`** rows
(`interest_volunteer_opportunity`).

`syncProfileInterests()` writes whichever table matches the submitted ids and
**returns early**. Because the profile screen sends MasterChoice ids (from
`GET /api/choices/user_interest/`), it writes `masterInterests` and leaves
`interests` empty.

The filter read **only** `interests`:

```php
$userInterestIds = $request->user()->interests()->pluck('interests.id');
if ($userInterestIds->isEmpty()) {
    $query->whereRaw('0 = 1');   // ← always taken
}
```

So for any user who used the real UI, that list was empty, the query was forced
to `0 = 1`, and the response was `total: 0`. Structurally guaranteed, no error —
exactly the symptom you saw.

**This is unrelated to `docs/OPPORTUNITY_INTERESTS_MISSING_ON_DETAILS.md`.** The
opportunity side was fine; the user side was being read from the wrong table.

### Answering your specific questions

1. **Did the test user have interests saved?** Almost certainly **yes** — but in
   `masterInterests`, which the filter wasn't reading. So it was a real bug, not
   expected behaviour for that account.
2. **Downstream of the missing-interests bug?** No. Different cause, different
   table. Verified with a user/opportunity pair confirmed to share a tag.
3. **Was `type=146` contributing?** No. `type` is a separate filter and behaves
   correctly; the zero result came from `match_my_interest` alone. Retested with
   and without it.

## 2) What changed

**a) Match spans both relations.** A new `resolveUserInterestIds()` reads
`interests` **and** `masterInterests`, translating master choices to their
`Interest` equivalents by name (case- and whitespace-insensitive), then merges
both sets. Legacy accounts and current-UI accounts both work.

**b) Learn-serve now supports the flag.** `GET /learn-serve-opportunities/` had
no `match_my_interest` handling at all — it silently ignored it and returned the
unfiltered list. It now applies the same logic.

> Note this means learn-serve results **will change** for that request: it was
> previously ignoring your filter, so you may have been getting a full list where
> you expected a filtered one. Now it filters properly.

## 3) Verified request

```http
GET /api/list-volunteer-opportunities/?match_my_interest=true
Authorization: Token <volunteer token>
```

Setup: opportunity "Beach cleanup" tagged with interest **Environment**; the
volunteer picked **Environment** through `PATCH /api/volunteer-profile/`.

```json
{
  "key": "success",
  "msg": "Opportunities retrieved successfully.",
  "code": 200,
  "response_status": { "error": false, "validation_errors": [] },
  "data": [
    { "id": 1, "title_en": "Beach cleanup", "opportunity_type": "volunteer_opportunity" }
  ],
  "meta": { "pagination": { "page": 1, "limit": 20, "total": 1, "total_pages": 1 } }
}
```

Before the fix this same request returned `"data": []`, `"total": 0`.

Same for `GET /api/learn-serve-opportunities/?match_my_interest=true`.

## 4) Behaviour matrix

| Case | Result |
|------|--------|
| User picked interests via profile UI, opportunity shares one | ✅ returned |
| Opportunity shares **no** interest with the user | correctly excluded |
| Legacy account with `interests` populated directly | ✅ returned |
| **User has no interests at all** | `data: []` — **correct**, not a bug |
| **Guest** (no token) | filter **ignored**, full list returned |
| Flag omitted | full list returned |

Note rows 4 and 5 — they answer your "we can't distinguish" concern:

- **No interests selected → empty list.** Still indistinguishable from the
  frontend by design. If you want to warn the user ("pick interests first"),
  read their profile interests from `GET /api/volunteer-profile/` →
  `interest_display` and disable/annotate the toggle when it's empty. Say the
  word if you'd prefer a dedicated flag on the response instead — happy to add
  one, but I didn't want to change the envelope shape unasked.
- **Guests get the unfiltered list**, not an empty one. Your toggle is already
  gated behind `{user && (...)}`, so this shouldn't arise.

## 5) Test coverage

`tests/Feature/MatchMyInterestFilterTest.php` — 7 tests, all passing. They
exercise the **real** path (`PATCH /api/volunteer-profile/` with MasterChoice
ids), not a shortcut that writes the table directly, so the bug could not have
slipped through:

- matches when the user picked interests through the profile UI ← the repro
- excludes opportunities that do not share the interest
- matches via the legacy `interests` relation too
- a user with no interests gets an empty result
- the filter is ignored for guests
- omitting the flag returns everything
- the learn-serve listing honours the filter too

Full suite: **215 tests passing**.

## 6) Your checklist

- [ ] `match_my_interest=true` returns matching opportunities on
      `/list-volunteer-opportunities/`
- [ ] Same on `/learn-serve-opportunities/` (**newly filtered** — was ignored)
- [ ] `type=146` combined with the flag behaves sensibly
- [ ] Consider disabling the toggle when the user has no interests saved (§4)

## 7) Unrelated: local environment note

While verifying this I found the machine's `php.ini` files (Herd php82/83/84) had
been overwritten with ~71KB of leading NUL bytes, which made PHP stop parsing
before any directive and silently load **no extensions** — no `mbstring` (PHPUnit
refuses to start) and no `sqlite3` (the test database). The extension DLLs were
all still present.

I rebuilt `php84/php.ini` from the salvaged Herd block plus the needed
`extension=` lines, and kept the corrupted original as
`php.ini.corrupt-<timestamp>`. **php82 and php83 are still corrupted** — if you
use either, they'll need the same treatment. Nothing to do with the app code, but
worth knowing if tests suddenly refuse to run.
