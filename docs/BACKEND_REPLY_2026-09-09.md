# Backend reply — 2026-09-09

All three open items are done. **Shape 1**, because one of them is a deliberate
breaking change on write endpoints that you asked for (BE-22 ask 4) and you need the
per-endpoint key list to check your payloads against.

---

## BE-23 — `interest_ids` rejects every id we can obtain

**Done.** Your diagnosis was exactly right, and it was worse than "an `exists` rule points
at the wrong table":

- `interest_ids.*` validated `exists:interests,id` — the legacy `Interest` table, which no
  endpoint exposes.
- The **read** side had the mirror-image bug: the models only declared
  `interests()` → `interest_volunteer_opportunity` (legacy, effectively empty), while
  production data actually lives on the `master_choice_*` pivots. That is why
  `interest_display` was `[]` everywhere.

So writes were rejected and reads were blank for the same reason: two tag vocabularies,
and the opportunity/event endpoints were wired to the wrong one on both sides.

**Ask 1 taken** — MasterChoice ids are now accepted directly, no bridging to `Interest`
and no frontend change. Concretely:

- Added `masterInterests()` to `VolunteerOpportunity`, `LearnServeOpportunity` and `Event`
  (the `master_choice_*` pivots).
- Writes resolve ids against master choices first, falling back to legacy `Interest` ids
  so older records/clients keep working.
- Reads prefer the master-choice pivot and fall back to the legacy one.
- `exists:interests,id` is gone.

**Ask 3 — what `interest_ids` should contain, per resource.** Scoped, so an id from the
wrong picker fails with a message naming the right endpoint instead of silently attaching
a foreign tag:

| Endpoint | Choice type | Live ids |
|---|---|---|
| `POST/PATCH /volunteer-opportunities/` | `volunteer_opportunity_interest` | 18 rows, 71–88 |
| `POST/PATCH /learn-serve-opportunities/` | `learnserve_opportunity_interest` | 21 rows, 89–109 |
| `POST/PATCH /events/` | `event_interest` | 24 rows, 110–158 |

Note `event_interest` is 110–**158**, not 110–132 as your item says — 24 rows, not
contiguous. Keep feeding each picker from its own `/choices/{type}/` and you are already
correct.

**Proof.** `tests/Feature/BackendIssuesRoundTwoTest.php`, all passing:

- `test_be23_volunteer_opportunity_accepts_master_choice_interest_ids` — posts three ids
  from `/choices/volunteer_opportunity_interest/`, asserts `201`, asserts the
  `master_choice_volunteer_opportunity` rows exist, and asserts the response's
  `data.interests` has the three tags. **That single round trip closes BE-01 and BE-23
  together**, which was your ask 4.
- `test_be23_learn_serve_and_event_accept_their_own_vocabularies` — same for the other two.
- `test_be23_an_id_from_the_wrong_vocabulary_is_rejected_with_a_clear_error` — a
  `user_interest` id posted to a volunteer opportunity returns 422 whose error text
  contains `/api/choices/volunteer_opportunity_interest/`.

**Frontend must:** nothing. Your payload is already correct and the response shape is
unchanged — see the shape note under BE-22 below. You can delete
`features/shared/interestIdsFallback.ts`: the first attempt now succeeds, so the retry
path is dead code.

---

## BE-22 — write contract: relation field names and array encoding

**Ask 1 — the rename table is correct.** Confirmed against the rules, per endpoint:

| Endpoint | Accepted |
|---|---|
| `/events/` | `event_type_id` (**required**), `participation_type_id`, `gender_id`, `interest_ids[]` |
| `/volunteer-opportunities/` | `gender_id`, `interest_ids[]`, `volunteer_category` (bare) |
| `/learn-serve-opportunities/` | `format_id`, `gender_id`, `learning_type_id`, `certificate_type_id`, `interest_ids[]` |

The bare names are **not** accepted on these three. `*_id` is canonical and the only form.
Your reading of BE-20 was right: `event_type` never reached the column on any save.

**The profile/registration endpoints genuinely do use a different vocabulary, and it is
stable.** They take bare `gender`, `sector`, `organizer_type`,
`emergency_contact_relationship` and map them to the `*_id` columns internally, and their
interest field accepts any of `interest_ids`, `interests`, `tags`, `master_interest_ids`,
`_interests`. Leave them as they are. This asymmetry is not deliberate design — it is
history — but it works and changing it would break more than it fixes.

**Ask 3 — `volunteer_category` is an enum**, not a relation:
`Rule::in(['environmental','charity','organizational'])`. Bare name is correct.

**Ask 2 — array encoding. Use the bracketed form `field[]` everywhere.**

You were right that a repeated **bare** key is lossy — PHP keeps only the last value and
there is nothing the backend can do to recover the earlier ones. So:

| Field | Send as | Note |
|---|---|---|
| `interest_ids` | `interest_ids[]` | already correct |
| `existing_image_ids` | `existing_image_ids[]` | **change this** — bare-repeated has only ever delivered the last id |
| `existing_ids` (organizer docs) | `existing_ids[]` | it validates `array`, so bare-repeated was 422-ing, not silently truncating |
| `images` (`ReplyOfReplyForm`) | `images[]` | file field; multiple files need brackets |

Both id fields also now accept a single scalar and a comma-separated string, so switching
to `[]` cannot make things worse — the flip-side failure you were worried about does not
exist.

**Ask 4 — unknown write keys now return 422.** Implemented on the three create/update
endpoints. An unrecognised key produces a normal validation envelope with one entry per
offending key, so the field name is named explicitly instead of being dropped.

⚠️ **This is a breaking change and it will reject payloads that used to "work".** Two of
our own tests were sending `volunteer_category` and `is_public` to
`/learn-serve-opportunities/`, where neither field exists — both were being silently
discarded, and both now 422. Please check your `LearnServeForm` for the same thing before
you ship against this.

There is a kill switch if it misfires in production: `REJECT_UNKNOWN_WRITE_KEYS=false` in
`.env`, no deploy needed.

Keys allowed beyond the validated fields: `_method`, `_token`, `lang`, `pass_token`,
`interest_ids`, `existing_image_ids`, `time_slots`, `opportunity_images_*`,
`new_opportunity_images_*`, and on `/events/` also `images`, `sponsor_images`,
`license_image`.

**Ask 5 — verification.**

- `test_be22_the_documented_field_names_are_accepted` — a create with `gender_id` and
  `interest_ids[]` returns 201.
- `test_be22_unknown_write_key_is_rejected` — the same create with `gender` (bare) returns
  422 with `gender` in `validation_errors`.
- `test_be22_existing_image_ids_keeps_all_three_images` — three ids sent as an array,
  all three still attached afterwards.
- Event create with `event_type_id`: covered by
  `test_be23_learn_serve_and_event_accept_their_own_vocabularies`, which asserts the stored
  pivot rows on the created event.

**Also fixed while in here:** `update_images` was `POST` for volunteer opportunities but
`PATCH` for learn-serve. Both now accept **`POST` and `PATCH`**, so that 405 is gone.

**Frontend must:**
1. Change `existing_image_ids`, `existing_ids` and `images` to the bracketed form
   (`formData.append("existing_image_ids[]", id)`).
2. Audit your three create/update payloads for keys not in the tables above — they will now
   422 instead of being ignored. Specifically check whether `LearnServeForm` sends
   `volunteer_category` or `is_public`.
3. Nothing for asks 1 and 3 — your rename shipped correct.

---

## BE-21 — `/user-certificates/` rows carry no registration type

**Done, ask 1.** Each row now carries `registration_type`, `"volunteer"` or
`"learn_serve"`.

```json
{
  "registration_id": 996,
  "registration_type": "volunteer",
  "certificate_image": "https://…",
  "opportunity__title_en": "Reeest",
  "opportunity__title_ar": "Reeest",
  "organizer_name": "…"
}
```

**Ask 2 — the ids do collide, and not rarely.** They are two tables with independent
`AUTO_INCREMENT` sequences, so every low id exists in both. Proven, not asserted:
`test_be21_registration_ids_do_collide_across_the_two_tables` creates the first row of each
table and asserts the two ids are equal. So keep passing the param — the fallback search
order really can hand back the wrong certificate.

**Proof.** `test_be21_user_certificates_rows_carry_registration_type` builds one volunteer
and one learn-serve certificate for the same user and asserts both rows come back tagged,
one of each.

**Frontend must:** nothing — you said `downloadUserCertificate` already passes the field
through opportunistically. It is now always present, so the fallback path is no longer
reachable from the certificates tab.

---

## Not done / still open

- **`fursa:backfill-missing-volunteer-certificates` has still not been run** against the
  live database, so registration 996 has no certificate yet. Unchanged from the 09-08
  reply — it needs someone with production DB access to execute it.
- **Re-issue after an hours correction** (clearing `is_certified` when `total_hours`
  changes) is **not** implemented. Not forgotten — flagging it explicitly as you asked.
- **BE-20's data question is answered by BE-23, and the answer is worse than expected.**
  `event_type_id` being `NULL` on event 19 was not a one-off: the form was sending
  `event_type`, so *no* event ever received the value. That column needs checking across
  the table, not just for id 19 — `SELECT id, event_type_id FROM events ORDER BY id DESC;`
  — and any `NULL` needs a real value picking. We cannot infer the right one.
- **`fursa:backfill-legacy-opportunity-interest-tags` is now largely redundant.** Reads
  come off the master-choice pivot directly, so historical tags surface without translating
  any data. Harmless to run, no longer necessary.
- One pre-existing test failure, unrelated and untouched:
  `PublicAndCommunityFlowTest > list all opportunities organized events returns only
  events`. It fails identically on the previous commit; not caused by this round.

**Deploy notes.** No migration in this round. After `git pull`:
`php artisan optimize:clear && php artisan config:cache`.
Test suite: 236 passing, 1 pre-existing failure.
