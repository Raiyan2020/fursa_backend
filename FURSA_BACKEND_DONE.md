# Fursa backend — mobile/frontend completion report

مساء الفل يا ميدو

**All backend updates requested by the mobile/frontend team in `FURSA_BACKEND_ISSUES.md` are done and tested successfully.**

## Completed updates

- **BE-36 — Done.** Organization approval is checked on all organization-owned write operations. Existing tokens stop working after approval is withdrawn, and admin status changes revoke tokens.
- **BE-37 — Done.** Rich-text descriptions, FAQ answers, and CMS page content are sanitized before storage. A backfill command is included for old rows.
- **BE-41 — Done for the mobile contract.** Event attendance fields and tags were removed, attendance is no longer writable/filterable/exported, and event scan permissions are rejected. Mobile no longer receives or depends on an event-attendance cycle.
- **BE-42 — Done.** Mobile can clear every existing image by sending multipart `existing_image_ids=none`. Republish can copy the licence without copying gallery images.
- **BE-43 — Done.** Admin and API interest tags now use the same master-choice ids and keep both compatibility pivots synchronized.
- **BE-44 — Done for mobile/admin compatibility.** URL and interest validation are aligned so records written in the dashboard can be edited through the API without unrelated validation failures.
- **BE-45 — Done.** Private opportunities are hidden from lists, joinable through their direct link, return a usable frontend share URL, remain visible to their owner, and stay private after completion.
- **BE-46 — Done.** Certificate endpoints require authentication and allow only the certificate owner or issuing organization. Other users cannot enumerate certificates.
- **BE-47 — Done.** Only Course and Internship grant certificates. Class/Workshop and Consultation do not require check-in, invalid certificate types are rejected/cleared, and the seeder now provides the four approved learning types.
- **BE-48 — Done.** All three detail resources return `is_saved_to_calendar` and `calendar_id`; delete clears the saved state; repeat saves reuse the same row; event calendar state is supported; duplicate calendar cards are removed.
- **BE-49 — Done.** Calendar entries return specific bilingual types, omit deleted sources, deduplicate overlapping saved/registered/organized entries, and apply search/date filtering in the database.

## Successful verification

The focused backend/mobile integration suite passed:

```text
29 tests passed
698 assertions passed
0 failures
```

The verification includes real endpoint tests for:

- stored-XSS sanitization through the create endpoint;
- rejected organization tokens on protected writes;
- private opportunity registration, visibility, share URL, and completion behavior;
- clear-all image and republish licence behavior;
- learning-type and certificate rules;
- certificate authentication and ownership;
- calendar save, repeat-save, unsave, `calendar_id`, event state, deletion, type, deduplication, and filters;
- event scan-permission rejection;
- admin/API interest synchronization.

All changed PHP files pass syntax validation, and Laravel Pint passes with no formatting errors.

## Postman collection

Both Postman files were updated and are identical:

- `Fursa_API.postman_collection.json`
- `docs/postman/Fursa_API.postman_collection.json`

All API endpoints appear in the collection. Requests that need no payload have no body. Payload requests are configured as form-data, including file fields where required. Incorrect account and calendar methods/paths were corrected.

## Decisions recorded

- **BE-42:** use `existing_image_ids=none` to clear all existing images.
- **BE-45:** private opportunities remain private after completion.
- **BE-48:** `is_calendar` remains a deprecated compatibility field and does not block calendar saving.
- Event registration/time-slot compatibility endpoints remain available, but event attendance is removed from the mobile/API contract.

## Deployment-only checks

These do not block the completed mobile/backend code, but must be checked on production during deployment because production access is not available from this workspace:

- confirm the production `EXPOSE_OTP_IN_RESPONSE` value (production code hard-disables it regardless);
- check/archive any old `event_attendances` rows before running the drop migration;
- inspect production learning-type rows, run `ChoiceTypeSeeder`, and review any old certificates issued for non-certificate learning types;
- run `php artisan fursa:backfill-sanitize-rich-text` and `php artisan fursa:backfill-generated-link`.

No existing API field was renamed. New response fields required by mobile are `calendar_id`, event `is_saved_to_calendar`, and calendar `type_en` / `type_ar`.
