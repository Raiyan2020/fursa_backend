# Backend completion report — 2026-09-20 (round 3)

Closing out `FURSA_BACKEND_ISSUES (7).md` — BE-72, BE-73, BE-71, and the BE-57 row count.

**All code changes verified with automated tests: `php artisan test` — 381 passed, 1 pre-existing
unrelated failure (`PostmanCollectionCoverageTest`, a Postman-collection gap for an unrelated route,
flagged in earlier rounds and untouched by this one).**

---

## 1. Status lines

| Item | Status |
|---|---|
| **BE-72** — development participants export missing columns | **Done** |
| **BE-73** — drop `Team` column from the volunteers export | **Done** |
| **BE-71** — expose `platform_fee_percentage` | **Done** |
| **BE-57** — production row count | **Answered — see below. Not a code fix; see note.** |

---

## 2. BE-57 row count

```php
\App\Models\User::where('nationality', 'other')->whereNull('residency_status')->count();
// = 102
```

**Not zero — 102 real rows.** This is not a "won't fix." We do not have the original BE-57 ticket
text (only this file's one-line restatement has ever reached us — the full description lives in
`FURSA_BACKEND_ISSUES_ARCHIVE_2026-09-20.md` / `...20b.md`, which we don't have a copy of). Please
resend the original BE-57 description, or a copy of the relevant archive entry, so this can be
scoped and built against the real 102-row population instead of just its count.

---

## 3. What changed and how it was verified

### BE-72 — Learn & serve participants export now has the columns the screen shows

`app/Support/RegistrationExport.php` (shared by the events, learn-serve, and scan-permission
exports) now takes a `learn-serve`-specific branch:

- Adds four columns: Phone, Guardian Name, Guardian Phone, Guardian Civil ID, Guardian Relationship
  — the same four guardian fields BE-68 added to the volunteers export, plus contact number.
- Drops `Scan allowed` for this export type only — it read a column
  (`LearnServeOpportunityRegistration::is_allowed`) that doesn't exist on that model and always
  showed `0`. Still present, unchanged, on the scan-permission export it's real for.
- Eager-loads `user.emergencyContactRelationship` so the relationship column doesn't cost a query
  per row.
- The `events` export path is untouched — no guardian columns, `Scan allowed` still present there.

**Verified:** new test
`BackendIssuesFromPdfReviewTest::test_learn_serve_participants_export_includes_contact_and_guardian_columns_but_not_scan_allowed`
downloads the real `.xlsx`, unzips it, and asserts the guardian values and contact number are
present in the sheet and `"Scan allowed"` is not. Existing tests for the `events` and
`scan-permission` export paths (`BackendMissingItemsTest`) still pass unchanged, confirming the
shared helper's refactor didn't affect them.

### BE-73 — `Team` column removed from the volunteers export

`VolunteerOpportunityRegistrationController`'s `download` branch no longer emits the `Team` header
or `$assignment?->team?->team_name_en` value. Nothing else touched — the `team` key on
`VolunteerOpportunityRegistrationResource`, the `team` parameter on `PATCH
/volunteer-opportunity-registrations/`, and the teams endpoints are all still there, per your
explicit ask to leave them for the mobile app / admin dashboard.

**Verified:** new test `RegistrationManagementTest::test_download_export_no_longer_has_a_team_column`
creates a registration with a real team assignment (a distinctive team name), downloads the export,
and asserts neither the `Team` header nor the team name appear anywhere in the sheet.

### BE-71 — `platform_fee_percentage` now readable by a publisher

- New `LearnServeOpportunity::platformFeePercentage()`, reused internally by the existing
  `payoutAfterFee()` so the two numbers can never disagree.
- Exposed as `platform_fee_percentage` on `LearnServeOpportunityResource`, right beside
  `payout_after_fee` (option 1 from the ticket).
- Defaults to `7` when the `platform_fee_percentage` config row is unset, matching the existing
  `payoutAfterFee()` default.

**Verified:** two new tests in `PaidOpportunityPayoutTest` — one sets the admin config to `10` and
confirms the create response returns `platform_fee_percentage: 10` alongside the correctly-computed
`payout_after_fee`; the other confirms it defaults to `7` when unconfigured.

**Frontend action once you pull this:** `LearnServeForm.tsx`'s hardcoded `PLATFORM_FEE_PERCENT = 7`
constant can now read `data.platform_fee_percentage` from the create/detail response instead, so the
copy under the price field can never drift from the real config value.

---

## 4. Anything changed that isn't a line item above

Nothing schema-level this round. All three changes are additive fields/columns on existing resources
and exports — no renamed fields, no new required parameters, no migration.

---

## 5. Still outstanding, unchanged from last round

- The production `EXPOSE_OTP_IN_RESPONSE` value.
- Whether `fursa:backfill-sanitize-rich-text` and `fursa:backfill-generated-link` have been run
  against production.
