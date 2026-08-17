# Volunteer Profile — Events / "Our Sponsorships" (رعايتنا) — Frontend Fix Guide

> **Page:** `/volunteer-profile`  
> **Tab path:** Events (الفعاليات) → **Our Sponsorships** (رعايتنا)  
> **Backend status:** filter bug fixed in Laravel — pending deploy on `portal.fursa.raiyan.cc`  
> **This document is for the Website (Next.js) frontend developer only.**

---

## Quick summary

| Issue | Owner | Status |
|---|---|---|
| API returns wrong data for `filter_type=sponsored_events` | Backend | Fixed in code — deploy required |
| No rows in `event_sponsor_images` table (empty DB) | Backend / Admin data entry | No sponsorship records yet |
| White screen when list is empty | **Frontend** | Must fix |
| 404 when clicking a card | **Frontend** (+ was caused by wrong API data) | Must fix |
| Volunteer account sees "Our Sponsorships" tab | **Frontend UX** | Should hide or show message |

---

## What "Our Sponsorships" means (business rule)

**Not** the public sponsors directory (`GET /api/sponsors/`).

This tab shows **events where the logged-in user's organization appears as a sponsor** in `event_sponsor_images`.

| User type | Expected behaviour |
|---|---|
| `volunteer` | **No sponsorships** — user has no `organization_profile`. Tab should be hidden or show "Not available for volunteers". |
| `organization` | Show events linked via `event_sponsor_images.organization_id = organization_profiles.id` |

Test account used in QA: `ahmednabeeh98@gmail.com` → `user_type: volunteer`, `id: 690` → **must not show sponsorship data**.

---

## API contract (after backend deploy)

### Endpoint used by profile Events list

```
GET {API_BASE}/list-all-opportunities/
```

**Query params for "Our Sponsorships" tab:**

| Param | Value |
|---|---|
| `filter_type` | `sponsored_events` |
| `user_id` | profile user id (when viewing public profile) or omit for own profile |
| `search` | optional |
| `start_date` / `end_date` | optional (from filters) |
| `page` / `limit` | pagination |

**Query params for "Organized Events" tab:**

| Param | Value |
|---|---|
| `filter_type` | `organized_events` |

### Expected responses

**Volunteer (no org profile):**

```json
{
  "key": "success",
  "data": []
}
```

**Organization with sponsorships:**

```json
{
  "key": "success",
  "data": [
    {
      "id": 8,
      "opportunity_type": "event",
      "title_en": "...",
      "title_ar": "...",
      "event_status": "completed",
      "event_images": [{ "id": 8, "image": "https://..." }],
      ...
    }
  ]
}
```

**Important:** Every item in `sponsored_events` / `organized_events` responses must have:

```json
"opportunity_type": "event"
```

Do **not** assume volunteer or learn-serve items will appear in these tabs after backend fix.

### Other profile tabs (for reference)

| UI tab | `filter_type` |
|---|---|
| Organized (opportunities section) | `organized` |
| Our Sponsorships (opportunities section) | `sponsored` |
| Registered | `registered` |
| Volunteer participations | `volunteer` |

---

## Frontend bugs to fix

### 1) White screen when list is empty (critical)

**Problem:** When API returns `data: []` and loading is finished, the Events / Sponsorships section renders nothing (blank white area).

**Likely cause:** render condition similar to:

```javascript
// BAD — hides UI when list is empty AND not loading
if (!items.length || isLoading || isFetching) {
  return <Grid>...</Grid>;
}
return null; // or nothing → white screen
```

**Required fix:**

```javascript
if (isLoading || isFetching) {
  return <Loader />;
}

if (!items.length) {
  return (
    <EmptyState
      title={t('COMMON.NO_SPONSORED_EVENTS')}   // e.g. "لا توجد رعايات"
      description={t('COMMON.NO_SPONSORED_EVENTS_DESC')}
    />
  );
}

return <EventsGrid items={items} />;
```

Apply the same pattern to:

- `organized_events` tab
- `sponsored_events` tab ("رعايتنا")
- any infinite-scroll list that uses `profile-events` query key

---

### 2) Wrong navigation / 404 on card click (critical)

**Problem:** Clicking a card opens `/event-details/{id}` but page returns 404 or breaks.

**Causes found:**

1. Backend previously returned non-event items (volunteer/learn opportunities) in `sponsored_events` response — cards used event layout/link on wrong type.
2. Frontend must only link to event detail when `opportunity_type === 'event'`.

**Required fix:**

```javascript
function getEventCardHref(item) {
  if (item.opportunity_type !== 'event') {
    return null; // should not happen after backend fix; log warning in dev
  }
  return `/event-details/${item.id}`;
}
```

**Verified working route on production:**

```
GET https://fursa.raiyan.cc/event-details/1  → 200
GET https://fursa.raiyan.cc/events/1           → 404  ❌ do not use
```

Use **`/event-details/{id}`** only.

---

### 3) Hide or disable "Our Sponsorships" for volunteers (UX)

**Problem:** Volunteers see "رعايتنا" tab but can never have sponsorship data.

**Recommended options (pick one):**

**Option A — Hide tab (preferred):**

```javascript
const isOrganization = user?.user_type === 'organization';
// or: user has organization_profile

{isOrganization && (
  <Tab value="sponsored_events">{t('COMMON.SPONSORED')}</Tab>
)}
```

**Option B — Show tab with explanation:**

If tab stays visible for volunteers, show empty state:

> "رعايات الفعاليات متاحة لحسابات المنظمات فقط"

**Note:** Frontend already has logic for volunteer teams:

```javascript
// existing pattern in minified bundle — keep/enforce:
if (isVolunteerTeam && activeTab === 'sponsored_events') {
  setActiveTab('organized_events');
}
```

Extend similar guard for `user_type === 'volunteer'`.

---

### 4) Defensive parsing of API response

**Problem:** Component assumes every item has `event_images`, `event_status`, etc.

**Required:**

```javascript
const events = (response?.data ?? []).filter(
  (item) => item?.opportunity_type === 'event'
);
```

If filtered length !== raw length, log once in development:

```javascript
console.warn('[profile-events] Non-event items received for sponsored_events', raw);
```

---

### 5) Loading / error states

| State | UI |
|---|---|
| `isLoading` first page | Skeleton or spinner |
| `isFetching` next page | Infinite scroll loader |
| `data: []` after success | Empty state message (not blank) |
| API error 401 | Redirect to login |
| API error 5xx | Error banner + retry button |

---

## Component / query reference (from production bundle analysis)

Search the Next.js repo for:

| Search term | Purpose |
|---|---|
| `filter_type:"sponsored_events"` | Sponsorship events tab |
| `filter_type:"organized_events"` | Organized events tab |
| `queryKey:["profile-events"` | React Query hook for events list |
| `getAllOpportunities` | API service call |
| `href:\`/event-details/` | Event card link |
| `COMMON.SPONSORED` | i18n key for "رعايتنا" |

Profile page route: `app/(main)/volunteer-profile/...`

---

## Test plan (frontend QA)

### After backend deploy + frontend fix

| # | Steps | Expected |
|---|---|---|
| 1 | Login as **volunteer** (`ahmednabeeh98@gmail.com`) → Profile → Events → رعايتنا | Empty state **or** tab hidden — **no white screen** |
| 2 | Login as **organization** with no sponsorships → رعايتنا | Empty state: "لا توجد رعايات" |
| 3 | Login as **organization** with sponsorship rows in DB → رعايتنا | Event cards visible |
| 4 | Click any event card | Opens `/event-details/{id}` — **no 404** |
| 5 | Switch to "Organized events" tab | Only that org's created events |
| 6 | Network tab: `list-all-opportunities?filter_type=sponsored_events` | Response items all have `opportunity_type: "event"` |

### Organization account for testing (from DB)

| email | org_profile_id | company |
|---|---|---|
| `forsa@joinforsa.net` | 4 | منصة فرصة |
| `Pr@warbabank.com` | 6 | Warba Bank |

> Sponsorship cards will only appear after admin creates `event_sponsor_images` rows linking an event to the org.

---

## What is NOT frontend work

Do **not** implement these on frontend — backend/data team handles them:

- Creating `event_sponsor_images` records
- Fixing `GET /api/sponsors/{id}/` (public sponsor directory — separate feature)
- Changing volunteer users into organizations

---

## Acceptance criteria (Definition of Done)

- [ ] Empty `sponsored_events` response shows a visible empty state (Arabic + English)
- [ ] No white/blank screen in Events → رعايتنا under any account type
- [ ] Event cards link only to `/event-details/{id}`
- [ ] Volunteer accounts do not see misleading "رعايتنا" content (hidden tab or clear message)
- [ ] Loading spinner while fetching; no flash of empty then content
- [ ] Manual QA passed for volunteer + organization accounts on staging/production

---

## Related backend PR / files (for context only)

Backend fix location (already in Laravel repo):

- `app/Http/Controllers/Api/Opportunity/VolunteerOpportunityController.php`
  - Added support for `filter_type`: `sponsored_events`, `organized_events`, `sponsored`

Deploy backend **before** final frontend QA on production.
