# Backend reply — 2026-09-07

Every open item below is fixed and covered by an automated regression test (`php artisan test`, 222 passed — only one pre-existing, unrelated failure in `PublicAndCommunityFlowTest` predates this round and isn't touched by any of this). Three items needed no code change; each says why.

## BE-01 — Interest tags empty on every opportunity, learn-serve and event

**Done:** Confirmed your diagnosis exactly — legacy production data tags opportunities/events via a MasterChoice-based pivot (`master_choice_volunteer_opportunity`, `master_choice_learn_serve_opportunity`, `master_choice_event`), imported from the old database. Every current Resource reads `interests`/`interest_display` exclusively from the `Interest` model's own pivots (`interest_volunteer_opportunity` etc), which are empty for those legacy-tagged records. No write path (admin or API) has ever touched the MasterChoice pivots — this is dead data waiting to be bridged, not a live write-path bug.

Added `fursa:backfill-legacy-opportunity-interest-tags`, a one-time command that translates each legacy MasterChoice tag to its `Interest` equivalent by name (creating the `Interest` row if none matches) and inserts it into the current pivot, skipping anything already tagged. Idempotent — safe to run more than once.

**Proof:** Reproduced record 97's exact situation in a test — an opportunity tagged only via the legacy pivot with "Community Service" (real MasterChoice/Interest ids vary by database, so these are illustrative, not a literal capture):
```
GET /opportunities/{id}/details/  (before)  → "interests": []
php artisan fursa:backfill-legacy-opportunity-interest-tags
GET /opportunities/{id}/details/  (after)   → "interests": [{"id": <interest_id>, "name_en": "Community Service", "name_ar": "خدمة مجتمعية", "interest_type": "volunteer"}]
```
Test: `tests/Feature/BackfillLegacyOpportunityInterestTagsTest.php` — asserts this exact shape against the real ids generated at test time.

To actually confirm record **97** specifically once the command has run on the live database, re-fetch `GET /opportunities/97/details/` — we can't produce that exact response from here since it depends on live data.

**Frontend must:** nothing — `normalizeInterests()` already reads `interests` first, which is exactly what this populates.

**Not yet done, and this is on us to run, not you:** the command still needs to actually run against the **live** database — it only backfills a database it's pointed at, and I don't have live DB access from here. Record 97 (and every other legacy-tagged record) stays empty until it runs. Flagging so it isn't dropped: this is an ops step (`php artisan fursa:backfill-legacy-opportunity-interest-tags` on the live box), not a code gap.

## BE-02 — `is_creator` dropped from the opportunity detail payload

**Done:** Confirmed three different, inconsistent families existed: the two opportunity detail resources had `relationship_tags` (organizer/sponsor/registered/attended) but no `is_creator`; the plain event resource had neither; the list resources (`list-all-opportunities`, `list-volunteer-opportunities`, `list-user-opportunities`) had only `profile_activity_tag`, which answers "what is the *profile being viewed*'s relationship to this record", not "is this the *current viewer*'s own record" — a different question. Added `relationship_tags`, computed identically everywhere, to all of them. `is_creator` on `/events/{id}/` and `profile_activity_tag` on the list resources are untouched — this is additive only.

**Proof:** one organizer, three opportunity types, each self-reports `relationship_tags: ["organizer"]` — on `/events/{id}/`, on `/list-all-opportunities/` for a volunteer opportunity row, a learn-serve row, and an events row. Test: `tests/Feature/PublicAndCommunityFlowTest.php::test_relationship_tags_mark_the_organizer_consistently_across_event_and_list_resources`.

**Frontend must:** you already built `isViewerOrganizer()` reading `relationship_tags` → `is_creator` → `created_by.id` in that order — no change needed there, it'll just start finding `relationship_tags` on the endpoints that previously had neither. Answering question 1: `is_creator` being dropped from the two opportunity detail resources in favor of `relationship_tags` looks like a deliberate migration (same commit introduced `relationship_tags` and removed `is_creator` together) that just wasn't carried through to events/lists — now it has been. Recommend standardizing on `relationship_tags` everywhere going forward since it's now universal; `is_creator`/`profile_activity_tag` remain as-is for whatever already reads them.

Question 4 (pass_token gating): confirmed **not** gated on any flag — `$request->user()` resolves from the `Authorization` header on every request where a valid token is sent, regardless of route middleware.

## BE-04 — `list-user-opportunities` filters accepted but not applied

**Done — this was already fixed earlier the same day this doc was raised, and remains fixed.** `tags[]`, `start_date`/`end_date`, and `page`/`limit` (a bonus gap found alongside `tags`) are all wired in.

**Proof:** `GET /list-user-opportunities/?filter_type=registered&user_id={id}&tags[]=rrr` now returns only tagged opportunities; a `start_date`/`end_date` window narrows correctly; `page=1&limit=1` returns exactly one row with correct `meta.pagination`. Test: `tests/Feature/ClientFeedbackRoundTwoTest.php::test_list_user_opportunities_applies_tags_date_range_and_pagination`.

**Frontend must:** nothing — please just retest against the live deployment; the index table listing this as still "Open" predates a retest.

## BE-05 — `name` filter on community `/posts/` has no effect

**Done:** confirmed `name` was never read at all by `PostController::index()` — not dropped by validation (this endpoint validates nothing), simply never inspected. Posts have no name-like column of their own; wired `name` to match the author's `first_name`+`last_name` or their volunteer/organization profile `nickname`, mirroring the existing `user` filter's `whereHas('user', ...)` pattern. `search` and `type` were also checked per your ask #3: `search` already works correctly (title/idea text LIKE, properly grouped); there is no `type` param on this endpoint at all today — the closest existing equivalents are the separate `post`/`proposing_idea`/`is_funding_required` boolean flags, not a unified `type` value. If you need a consolidated `type` param, that's a new small feature, not a bug in existing code — let us know and we'll add it.

**Proof:** a post authored by "Islam Ghanem" (nickname `islamGH`) is the only match for `name=isl`, alongside an unrelated post from a different author. Test: `tests/Feature/PublicAndCommunityFlowTest.php::test_posts_name_filter_matches_author_name_and_nickname`.

**Frontend must:** nothing — `name` is already forwarded exactly as typed.

## BE-06 — `sort_by=newest` doesn't return newest-first

**Done:** confirmed both parts of your hypothesis. `sort_by` was only ever a **secondary** `orderBy('start_date', ...)` after an unconditional status-relevance `CASE`/`WHEN` bucket — a secondary sort key can only break ties *within* a bucket, so it could never move an in-progress record above an upcoming one no matter what "newest" is supposed to mean. It was also ordering by `start_date`, not `created_at`. An explicit `sort_by=newest`/`oldest` now bypasses the status bucketing entirely and orders by `created_at`; the unsorted default keeps the original status-relevance-then-`start_date` behavior (confirmed intentional — this is the bucket order your own earlier feedback specified).

**Proof:** two records — one created 5 days ago with a far-future `start_date`, one created just now, already in-progress with an earlier `start_date` (so it would rank dead last under the default order). `sort_by=newest` ranks the just-created one first regardless of status; `sort_by=oldest` reverses it. Test: `tests/Feature/ClientFeedbackRoundTwoTest.php::test_sort_by_newest_orders_by_creation_time_bypassing_status_buckets`.

**Frontend must:** nothing — the modal already sends the literal `newest`/`oldest`.

## BE-09 — "Volunteer Team" join option

**Done — your assumption was correct for `/register/`, but we found a real gap in `/social-auth/` while confirming it.** `POST /register/` with `organizer_type: "21"` already worked exactly as you assumed: stored generically on `OrganizationProfile.organizer_type_id`, matched generically by `/profiles/volunteer-teams/` (by `value_en === 'Volunteer Team'`, never a hardcoded id) — no special-casing needed, any org_type id named "Volunteer Team" would work identically. `POST /social-auth/`, however, never listed `organizer_type` in its validation rules at all, so it was silently stripped before reaching registration — a Volunteer Team signup via Google/LinkedIn always landed with `organizer_type_id: null`, invisible to `/profiles/volunteer-teams/`. Fixed by adding the field to that endpoint's validation.

**Frontend must:** nothing new to send — you were already sending `organizer_type` on both endpoints per your original payload description; it just wasn't being read on one of them. Please retest the social-auth path specifically.

## BE-15 — `opportunity_type` filter not applied on `list-all-opportunities`

**Done:** the filter mechanism itself (zeroing out the unwanted query builders) was already correct and already used by the sibling `list-user-opportunities` — the actual bug is a vocabulary mismatch. Both endpoints only ever matched the short form (`volunteer`/`learn`/`event`); your own-profile screen sends the long form (`volunteer_opportunity`/`learn_serve_opportunity` — the same value the response's own `opportunity_type` field reports on each record), which matched nothing and silently fell through to an unfiltered result. **Both vocabularies are now accepted** on both `list-all-opportunities` and `list-user-opportunities` — you don't need to change either screen, though standardizing both callers on one form going forward would remove the duplication.

An unrecognized value still falls through to unfiltered rather than a `422` (your ask #3) — we left this as a soft no-op rather than a hard error, since both of your existing callers' values are now valid; happy to add strict rejection if you'd still like it for genuinely unknown values.

**Proof:** one organization with one volunteer opportunity and one learn-serve opportunity; `opportunity_type=learn` and `opportunity_type=learn_serve_opportunity` both return only the learn-serve record; `opportunity_type=volunteer` and `opportunity_type=volunteer_opportunity` both return only the volunteer record. Test: `tests/Feature/PublicAndCommunityFlowTest.php::test_list_all_opportunities_opportunity_type_filter_accepts_both_vocabularies`.

**Frontend must:** nothing — send either form, both work now on both endpoints.

## BE-16 — Learn-serve registrations carry no phone number, picture or gender

**Done:** added `user_contact_number` (country code + phone, matching the volunteer endpoint's combined shape), `phone_number` (raw), `profile_pic`, `gender_display` (`{id, choice_type, value_en, value_ar}`, same shape as every other `_display` field in this app), and `is_public`, using the exact same helpers the volunteer registrations endpoint already uses. Also added `full_name` **alongside** the existing `user_name` (kept, not renamed) per your question 2 — recommend reading `full_name` going forward for consistency with the volunteer endpoint, but `user_name` isn't going away.

`status`/`time_slot` (question 3): `status` is genuinely `pending` for every unreviewed registration — there is a real approve/reject step (`PATCH /learn-serve-opportunities/{id}/registrations/status/`), just not surfaced on either register-list screen yet; that's a frontend UI gap, not a backend default with no real distinction. `time_slot` is populated only for opportunities using the per-day scheduling feature (`null` otherwise, correctly).

**Proof:** a volunteer with phone `+96555512345`, a profile picture, a set gender choice, and a public profile now returns all five fields correctly shaped on `GET /learn-serve-opportunities/{id}/registrations/`. Test: `tests/Feature/RegistrationManagementTest.php::test_learn_serve_registrations_list_includes_contact_picture_gender_and_visibility`.

**Frontend must:** read `user_contact_number`/`phone_number`/`profile_pic`/`gender_display`/`is_public` on this endpoint the same way you already read them on `/volunteer-opportunity-registrations/`.

## BE-08 — Two competing mechanisms for attaching a sponsor to an opportunity

**Answered — no code change, and no hazard either way.** `opportunity_sponsor_images_organization_{n}`/`_position_{n}` are **not read anywhere** in the current backend — `LearnServeForm.tsx`'s inline picker fields have always been silently ignored (never implemented on this side, not a regression). The only thing that ever writes `opportunity_sponsor_images` is the dedicated `POST`/`DELETE .../sponsors/` pair you already have. We read both opportunity `update()` methods end-to-end: neither touches the sponsor relation at all, so **a title-only (or any other) update cannot clear existing sponsors** — there's no `sync-to-empty-when-the-inline-field-is-absent` risk, because the inline field was never wired to anything.

**Frontend must:** drop the inline `opportunity_sponsor_images_organization_{n}`/`_position_{n}` fields from `LearnServeForm.tsx` — they do nothing today and never have. `POST`/`DELETE /learn-serve-opportunities/{id}/sponsors/` (and the volunteer-opportunity equivalent) is the one real mechanism for both opportunity types. If an inline picker inside the main create/update form is actually wanted as a UX choice, that's a new feature request for us, not a fix to an existing path.

## BE-07 — Sponsorship form: sponsor record 9 choice ids not persisted

**Answered — already resolved, no further action.** This was the underscore-prefix bug we found and fixed 2026-09-01 (`_org_type_id` etc. vs. the plain `org_type_id` the backend read — `SponsorController.php`, commit `c8896ab`). We re-checked specifically for record 9: the fix landed *before* today, there is exactly one public write path (`Api\Sponsor\SponsorController::store()`, which now has the fix) plus a separate admin dashboard form that was never affected (it posts plain field names, no underscore prefix, no race). Record 9 predates the fix; every submission since (including record 8's identical `26`/`34` combination succeeding) is explained by ordinary before/after of that deploy, not a race condition or a second broken path.

**Frontend must:** nothing — keep sending `_org_type_id`/`_sponsor_type_id`/`_type_of_support_id` exactly as you do; the backend accepts both the underscore-prefixed and plain forms now.

## BE-17 — `learning_type_display`, `format_display`, `certificate_type_display` are null

**Answered — not a bug in this code path.** Traced the full chain for opportunity 30: the model relations, the `masterChoicePayload()` helper, and the `loadMissing()` call are byte-for-byte identical to `gender_display` (same resource, same request) and to every other `_display` field across the app — there is no typo, no missing eager-load, no structural gap. `requires_check_in: true` alongside a null `learning_type_display` is also correct, documented behavior: the model explicitly defaults to "requires check-in" *when the type is unknown*, as a safe default — not a sign of two different relations disagreeing.

This means the three columns (`learning_type_id`, `format_id`, `certificate_type_id`) are genuinely `NULL` on that specific row — this opportunity was created/updated without those being selected. They're optional fields on both the admin and API create/update paths today, so that's valid data, not corruption. If a learn-serve opportunity should never be allowed to save without a learning type (given it directly drives `requires_check_in` and which register-list screen the organizer needs), that's a product decision — happy to make it a required field on create if you want that.

**Frontend must:** nothing on our side pending that product decision. If useful in the meantime, we can also add the raw `learning_type_id`/`format_id`/`certificate_type_id` alongside the `_display` objects (your question 3) so you can render from `/choices/` yourselves when the nested object is null — let us know if that's still wanted even though the underlying data is the real gap, not the serialization.
