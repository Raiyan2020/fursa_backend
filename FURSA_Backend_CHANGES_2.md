# Backend completion report — 2026-09-20 (round 4)

Closing out `FURSA_BACKEND_ISSUES (6).md` — BE-74 and the BE-57 row count. These were the only
two open items in that file.

**All code changes verified with automated tests: `php artisan test` — 382 passed, 1 pre-existing
unrelated failure (`PostmanCollectionCoverageTest`, a Postman-collection gap for an unrelated
route, flagged in earlier rounds and untouched by this one).**

---

## 1. Status lines

| Item | Status |
|---|---|
| **BE-74** — platform fee unreadable on the create/publish form | **Done** |
| **BE-57** — production row count | **Answered — see below. Still open, not a "won't fix."** |

---

## 2. BE-57 row count

```php
App\Models\User::where('nationality', 'other')->whereNull('residency_status')->count();
```

Run against **production**: **101** rows (previously reported as 102 — the population has
shrunk by one since last round, presumably a row got fixed or updated).

**Not zero, so this is not a "won't fix."** As noted last round: we do not have the original
BE-57 ticket text, only this file's one-line restatement. Please resend the original description
(or point us at the archive entry with the full text) so this can be scoped against the real
101-row population instead of just its count.

---

## 3. What changed and how it was verified

### BE-74 — the create/publish form can now read the platform fee before an opportunity exists

The gap: `LearnServeOpportunityResource` (BE-71) only exists once an opportunity has been created,
so the **create** form had nothing live to read and kept a hardcoded `7` while the **edit/repost**
form correctly read the config value.

Rather than add a new endpoint, `platform_fee_percentage` is now also returned on
**`GET /api/organization-profile/`** — the profile payload an authenticated publisher already
loads before creating an opportunity. Same source of truth as BE-71:

- `Config::platformFeePercentage()` is a new static on `App\Models\Config` — the one place that
  reads the `platform_fee_percentage` config row (falls back to `7` if unset).
- `LearnServeOpportunity::platformFeePercentage()` (BE-71) now delegates to it instead of
  duplicating the query, so the create form, the edit form, and the payout calculation all read
  the exact same value — structurally incapable of disagreeing, same reasoning as BE-71.
- `OrganizationProfileResource` adds one field: `platform_fee_percentage`, right beside the
  existing bank-detail fields a publisher already reads from that same payload.
- Nothing on the admin side changed — `platform_fee_percentage` was already editable in the
  dashboard (`Admin\ConfigController` / `resources/views/dashboard/settings/edit.blade.php`);
  this only adds the second read path for it, same as BE-71 added the first.

**Verified:** new test
`PaidOpportunityPayoutTest::test_organization_profile_exposes_platform_fee_percentage_before_an_opportunity_exists`
sets the config to `10`, calls `GET /api/organization-profile/`, and asserts
`data.platform_fee_percentage === 10.0`. Existing BE-71 tests (`payout_after_fee` /
`platform_fee_percentage` on the opportunity create response) pass unchanged.

**Frontend action once you pull this:** `LearnServeForm.tsx` can drop the `PLATFORM_FEE_PERCENT = 7`
constant on the **create** path and read `platform_fee_percentage` from whatever call already
loads the organization profile (`GET /api/organization-profile/`) before the form renders — same
`??`-not-`||` handling as the edit path, so a fee waived to `0` still shows `0`.

---

## 4. Anything changed that isn't a line item above

Nothing schema-level. One new static method (`Config::platformFeePercentage()`), one field added
to an existing resource, one line changed inside an existing method to call the new static instead
of duplicating its query. No renamed fields, no new required parameters, no migration.

---

## 5. Still outstanding, unchanged from last round

- The original BE-57 ticket text / archive entry, so the 101-row population can be scoped.
- The production `EXPOSE_OTP_IN_RESPONSE` value.
- Whether `fursa:backfill-sanitize-rich-text` and `fursa:backfill-generated-link` have been run
  against production.
