# Fixed: `list-user-opportunities` was ignoring `tags`, `start_date`/`end_date`, and `page`/`limit`

> **To:** Frontend
> **From:** Backend
> **Date:** 3 September 2026
> **Status:** ✅ Fixed and tested — no frontend changes needed
> **API:** `https://portal.fursa.raiyan.cc/api/`

Your diagnosis was right on `tags`, and the "please re-verify everything" ask
turned up two more real bugs on the same endpoint.

**No frontend changes required.** `tags[]=value` (repeated) is the correct
format — keep sending it exactly as you already do for `teams[]`/`roles[]`
elsewhere.

---

## 1) What was wrong

`listUserOpportunities` (the controller behind `/list-user-opportunities/`)
read `filter_type`, `opportunity_type`, `search`, and `opportunity_status`
directly, but never called the shared filter helper
(`applyVolunteerPublicFilters`) that the sibling endpoint
`list-volunteer-opportunities` uses — the one that actually knows how to
apply `tags` and `start_date`/`end_date`. Those two params were accepted
(200 OK) and silently had zero effect on the query, exactly as you observed.

Re-testing every param per your ask #1 also turned up:

- **`page`/`limit` were also silently ignored** — the endpoint always
  returned the full unfiltered/unsorted result set regardless of pagination
  params. (`list-all-opportunities`, the sibling endpoint, already paginates
  correctly — this one just never had the same code.)

`opportunity_type`, `search`, and `opportunity_status` were already working
correctly — confirmed below.

## 2) Answering your question #2: `tags` param format

`tags[]=value` (repeated query param, i.e. `tags[]=rrr&tags[]=other`) is the
correct and only supported format — same convention as `teams[]`/`roles[]`
on the volunteer registrations list. No shape mismatch; the filter simply
wasn't implemented on this endpoint until now.

## 3) What changed

`tags` and `start_date`/`end_date` filtering (extracted from the existing,
already-working `list-volunteer-opportunities` logic) and `page`/`limit`
pagination (matching `list-all-opportunities`) are now applied on
`list-user-opportunities` for all `filter_type` values (`registered`,
`organized`, `sponsored`) and both opportunity kinds it returns
(volunteer + learn/serve).

## 4) Re-tested: every filter, before/after

Test data: volunteer registered for 3 opportunities —
**"Beach Cleanup"** (tagged `rrr`, starts in 10 days), **"Food Drive"**
(untagged, starts in 25 days), **"Ended Cleanup"** (ended 8 days ago).

```
GET /list-user-opportunities/?filter_type=registered&user_id={id}
```
**Before any filter:** `["Beach Cleanup", "Food Drive", "Ended Cleanup"]`

| Filter added | Request | Result |
|---|---|---|
| `tags[]=rrr` | `...&tags[]=rrr` | `["Beach Cleanup"]` — only the tagged item |
| `start_date`/`end_date` (a window covering only Food Drive's dates) | `...&start_date=...&end_date=...` | `["Food Drive"]` |
| `search=Beach` | `...&search=Beach` | `["Beach Cleanup"]` |
| `opportunity_status=completed` | `...&opportunity_status=completed` | `["Ended Cleanup"]` |
| `page=1&limit=1` | `...&page=1&limit=1` | `data: ["Beach Cleanup"]`, `meta.pagination: {page:1, limit:1, total:3, total_pages:3}` |

Every filter now actually narrows the result set, and the response is
correctly paginated when `page`/`limit` are sent.

Covered by an automated regression test
(`tests/Feature/ClientFeedbackRoundTwoTest.php::test_list_user_opportunities_applies_tags_date_range_and_pagination`)
that fails without this fix and passes with it.
