# Fursa backend final completion report

مساء الفل يا ميدو

All remaining items in `FURSA_BACKEND_ISSUES (1).md` are implemented and verified on the running Laravel test application.

## Issue status

- **BE-44 — Done.** Admin and API opportunity writers now compose the same core rules. The API accepts `opportunity_nationality`, `is_calendar`, and `after_images`; the volunteer-opportunity dashboard displays and replaces per-day `time_slots`; the dashboard accepts the same optional localized event content as the API. The shared schedule writer prevents the API and dashboard implementations from drifting.
- **BE-50 — Done.** Registration, volunteer-profile update, account update, and new volunteer social signup all enforce the same three-way identity rule. A non-Kuwaiti non-resident can sign up socially and edit their profile with `passport_number`; `nationality=all` is rejected for people.
- **BE-51 — Done.** The public organization choices are exactly Governmental, Commercial, Educational, NonProfit, Association, and Community in the requested order. Existing Society rows become Association in place. The internal `society` licence-role key is retained and mapped to Association; Community retains the `community` role. Volunteer Team remains stored for the shortcut but is hidden from the public choice endpoint.
- **BE-52 — Done.** Public volunteer directory payloads no longer contain `first_name` or `last_name`. Both `search` and `name` match only the volunteer's public nickname, so a real name cannot be used to identify a nickname.
- **BE-53 — Done.** `/api/user-certificates/` filters the authenticated user's certificates by Volunteer, Course, or Internship, case-insensitively, and returns 422 for unknown values. The deployable `certificate_filter_type` master-choice vocabulary is included in both a migration and the seeder.
- **BE-54 — Done.** Both profile-activity list endpoints accept `profile_activity_tag=participant|provider` case-insensitively and reject unknown values with 422. Participant means attended; Provider means created by the profile owner. `development_opportunities_count` is the combined participant-plus-provider figure. `opportunities_organized` remains the separate provider count, while `total_opportunities` now also includes attended development activities.
- **BE-55 — Done.** Public and owner organization profiles read the all-time rollups from `organization_statistics`. Public profiles return `sponsored`; owner profiles retain `sponsored_count`. Events remain excluded from sponsorship by the existing opportunities-only schema and sync calculation.

## Product decisions

- **BE-50:** non-Kuwaiti non-residents are allowed to sign up with Google or LinkedIn when they provide `nationality=other`, `residency_status=non_resident`, and a unique `passport_number`.
- **BE-51:** existing Society organizations and sponsors remain on the same choice id, renamed to Association. The internal `society` licence-role key is kept for compatibility. Community is restored as a separate new selection.
- **BE-52:** real-name search is disabled for volunteers. The legacy `name` query parameter searches nickname only for volunteer lists.
- **BE-54:** the profile exposes one combined `development_opportunities_count`. Lowercase `participant` and `provider` are canonical response values; filter input is case-insensitive, so the frontend's current `Participant` and `Provider` values remain valid. Provider credit requires an approved, non-deleted development opportunity and does not wait for completion.

## Verification evidence

- **BE-44:** API multipart creation persisted the two formerly admin-only columns and an after-completion image; a dashboard edit request displayed an API-created schedule and replaced its active day; an event created without optional localized fields was then saved successfully through the dashboard.
- **BE-50:** live requests verified non-resident profile editing, account rejection when the required passport is cleared, successful Google-style social signup with a passport, and rejection of `nationality=all`.
- **BE-51:** live `/api/choices/org_type/` output was asserted against the exact six-value ordered list; migration and seeder behavior are covered by refreshed-database tests.
- **BE-52:** an anonymous volunteer-list request was inspected for absence of the seeded real name, and a real-name `name` search returned zero records.
- **BE-53:** live certificate-list requests independently returned one Volunteer, Course, and Internship certificate; Class returned 422; the choice endpoint returned the exact three options.
- **BE-54:** live profile-list requests returned only the attended row for Participant and only the created row for Provider, accepted both casings, and rejected an invalid tag. Sync wrote one attended and one organized development activity, and the public combined counter returned two.
- **BE-55:** live public and authenticated owner-profile requests summed two yearly rollups and returned identical non-zero hours, volunteer count, development count, and sponsorship values under their existing keys. The existing sync test also verifies that paid development sponsorship is excluded.

The dedicated final-round suite passes 11 tests. The complete project suite passes **302 tests and 4,083 assertions with zero failures**. All changed PHP files pass syntax validation, Laravel Pint, and `git diff --check`.

## Changes outside the issue list

- Added two deployment migrations: the second organization-type restructure and the certificate-filter choice vocabulary.
- Added shared opportunity core validation and a shared volunteer schedule writer.
- Deleted the unused legacy `Auth\PublicProfileResource` and `Auth\OrganizationPublicProfileResource`; the live route already uses `WebsitePublicProfileResource`.
- No existing response field was renamed and no new required frontend parameter was introduced.
