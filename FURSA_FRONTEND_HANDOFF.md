# Fursa frontend integration handoff

This handoff describes the backend contract now available for the remaining items in `FURSA_BACKEND_ISSUES (1).md`. Existing frontend work for the registration question, certificate chips, profile activity chips, and organization counters can now use the server behavior below without client-side filtering.

## Identity and nationality

The same volunteer identity rule now applies to registration, social signup, account update, and volunteer-profile update.

| User selection | Request fields | Required identifier |
|---|---|---|
| Kuwaiti | `nationality=kuwaitis` | `civil_id` |
| Non-Kuwaiti resident | `nationality=other`, `residency_status=resident` | `civil_id` |
| Non-Kuwaiti non-resident | `nationality=other`, `residency_status=non_resident` | `passport_number` |

Relevant writes:

- `POST /api/register/`
- `POST /api/social-auth/`
- `POST /api/account/`
- `PUT|PATCH /api/volunteer-profile/`

`nationality=all` is an opportunity-audience value only and is rejected for users. Social signup now supports the non-resident passport path, so `VolunteerMandateDetails` can use the shared three-option nationality/residency list. `VolunteerAccountInformation` can do the same. Profile responses include `passport_number` and `residency_status`.

## Organization types

`GET /api/choices/org_type/` returns exactly this ordered public list:

| `value_en` | `value_ar` |
|---|---|
| `Governmental` | `حكومي` |
| `Commercial` | `تجاري` |
| `Educational` | `تعليمي` |
| `NonProfit` | `غير ربحي` |
| `Association` | `جمعية` |
| `Community` | `مجتمع` |

`Volunteer Team` remains an internal stored choice for the Join Us shortcut and is intentionally omitted from this public dropdown. Existing `Society` records migrate to `Association`. The frontend compatibility aliases for Society may remain during rollout but are no longer returned after migration.

## Public volunteer privacy

The volunteer branch of these public endpoints no longer returns `user_details.first_name` or `user_details.last_name`:

- `GET /api/all-profiles/`
- `GET /api/profiles/volunteers/`
- `GET /api/profiles/organizations/`
- `GET /api/profiles/volunteer-teams/`

Volunteer cards should continue to display `nickname` only. For volunteer queries, both `search` and the legacy `name` parameter search nickname only. Organization and volunteer-team name behavior is unchanged.

## Certificate filter

Fetch filter chips from:

```http
GET /api/choices/certificate_filter_type/
```

The exact values are `Volunteer`, `Course`, and `Internship`. Filter the authenticated user's list with:

```http
GET /api/user-certificates/?certificate_type=Course
```

The match is case-insensitive. Omit `certificate_type` for all certificates. An unknown value returns 422 with `response_status.validation_errors.certificate_type`. The old `user_id` parameter remains ignored for privacy; the token owner is always used.

## Profile development activity filter

Both activity-list endpoints accept the role filter:

```http
GET /api/list-user-opportunities/?user_id={id}&opportunity_type=learn_serve_opportunity&profile_activity_tag=Participant
GET /api/list-all-opportunities/?user_id={id}&opportunity_type=learn_serve_opportunity&profile_activity_tag=Provider
```

- `Participant` means an attended development opportunity.
- `Provider` means a development opportunity created by the profile owner.
- Matching is case-insensitive. Lowercase `participant` and `provider` are canonical in each row's `profile_activity_tag` response.
- Omit the parameter for both roles.
- Unknown values return 422 with `response_status.validation_errors.profile_activity_tag`.
- Continue sending this filter only with `opportunity_type=learn_serve_opportunity`.

Profile counters now use these meanings:

- `development_opportunities_count`: combined attended plus provided development activities; use this for the single Development counter.
- `opportunities_organized`: separate provider count retained for compatibility/detail views.
- `total_opportunities`: volunteer opportunities attended plus development opportunities attended.

## Organization profile counters

No frontend calculation is needed. The public profile returns:

```json
{
  "organization_hours": 18,
  "vol_opportunity_organized": 3,
  "learn_opportunity_organized": 5,
  "sponsored": 5
}
```

`GET /api/organization-profile/` returns the same values but keeps the existing `sponsored_count` spelling. The current `profile.sponsored ?? profile.sponsored_count` fallback remains correct. Sponsorship counts volunteer and development opportunities only; events are excluded by the backend.

## Opportunity writer compatibility

Volunteer and development opportunity API writes now accept the dashboard-compatible optional fields `opportunity_nationality`, `is_calendar`, and multipart `after_images[]`. Optional localized event content (`title_ar`, `description_en`, and `description_ar`) can be omitted in both the API and dashboard. Core localized content, schedule, date, age, and participant rules are shared between the two writer surfaces.

These changes add capabilities without renaming response fields or introducing a new required frontend parameter.
