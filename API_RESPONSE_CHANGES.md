# API Response Changes

## Purpose

The Backend API responses have been cleaned up to return only the data that is actually required by the Website screens.

Some unused keys were removed from the API responses.

> Important: These changes apply to the Website only.
> The Admin Dashboard APIs and functionality are not included in these changes.

---

## Removed Keys by Endpoint

### Endpoint: `GET /api/banner-images/`

#### Removed Keys

* `data.banner_images[].id`
* `data.banner_images[].name`
* `data.banner_images[].created_at`

#### Frontend Action

Search the entire frontend codebase for usage of:

* `banner_images[].id`
* `banner_images[].name`
* `banner_images[].created_at`

Remove or update any code that depends on these fields.

---

### Endpoint: `GET /api/choices/{choice_type}/`

#### Removed Keys

* `data[].choice_type`

#### Frontend Action

Search the entire frontend codebase for usage of:

* `choice_type` on choice dropdown items

Remove or update any code that depends on these fields.

---

### Endpoint: `POST /api/register/`

#### Removed Keys

* `data.civil_id`
* `data.manual_id`
* `data.is_social_login`
* `data.is_banned`
* `data.preferred_language`
* `data.dob`
* `data.birth_year`
* `data.phone_number`
* `data.country_code`
* `data.profile_pic`
* `data.is_verified`
* `data.gender_display`
* `data.organizer_type_display`
* `data.latitude`
* `data.longitude`
* `data.nationality`
* `data.emergency_contact_name`
* `data.emergency_contact_phone`
* `data.emergency_contact_country_code`
* `data.emergency_contact_civil_id`
* `data.emergency_contact_relationship`
* `data.emergency_contact_relationship_display`

#### Frontend Action

Search the entire frontend codebase for usage of the removed register response fields.

The register screen only needs success/failure handling and optional `data.id` / `data.email`.

---

### Endpoint: `POST /api/login/`

#### Removed Keys

* `data.data.is_social_login`
* `data.data.dob`
* `data.data.birth_year`
* `data.data.phone_number`
* `data.data.country_code`
* `data.data.gender_display`
* `data.data.organizer_type_display`
* `data.data.latitude`
* `data.data.longitude`
* `data.data.nationality`
* `data.data.preferred_language`
* `data.data.civil_id`
* `data.data.emergency_contact_name`
* `data.data.emergency_contact_phone`
* `data.data.emergency_contact_country_code`
* `data.data.emergency_contact_civil_id`
* `data.data.emergency_contact_relationship`
* `data.data.emergency_contact_relationship_display`

#### Frontend Action

Search the entire frontend codebase for usage of the removed login user fields.

Keep using: `id`, `first_name`, `last_name`, `email`, `profile_pic`, `social_media_id`, `social_media_provider`, `user_type`, `manual_id`, `is_new_user`, `is_verified`, `is_banned`, `auth_token`, `organization`.

---

### Endpoint: `POST /api/social-auth/`

#### Removed Keys

Same removed user fields as `POST /api/login/`, plus:

* `data.social_profile_pic_url`
* `data.nickname`
* `data.company_name`
* `data.registration_number`
* `data.license_number`
* `data.volunteer`
* `data.latitude`
* `data.longitude`

#### Frontend Action

Search the entire frontend codebase for usage of the removed social-auth user fields.

---

### Endpoint: `GET /api/list-volunteer-opportunities/`

#### Removed Keys

* `approval_status`
* `description_en`
* `description_ar`
* `opportunity_nationality`
* `latitude`
* `longitude`
* `link`
* `is_calendar`
* `primary_language`
* `volunteer_hours_per_day`
* `gender_display`
* `is_public`
* `license_image`
* `is_interview_needed`
* `interests`
* `opportunity_sponsor_images`
* `user_type`
* `registration_link`
* `after_completed_images_count`
* `opportunity_type`
* `is_registered`
* `is_saved_to_calendar`
* `is_kuwaitis`
* `total_roles`
* `all_registered_user[].email`
* `has_scan_permission`
* `manual_tracking`
* `opportunity_images[].id`
* `opportunity_images[].is_after_completed`
* `created_by` nested user fields other than `id`

#### Frontend Action

Search the entire frontend codebase for usage of the removed volunteer opportunity list fields.

---

### Endpoint: `GET /api/learn-serve-opportunities/`

#### Removed Keys

Same list-card cleanup as volunteer opportunities, plus:

* `certificate_type_display`
* `timeslots_display`
* `is_attended`
* `description_en`
* `description_ar`
* `opportunity_type`
* `is_registered`
* `is_saved_to_calendar`
* `license_image`
* `all_registered_user[].email`
* `opportunity_sponsor_images`
* `interests`
* `opportunity_images[].id`
* `opportunity_images[].is_after_completed`

#### Frontend Action

Search the entire frontend codebase for usage of the removed learn & serve list fields.

---

### Endpoint: `GET /api/events/`

#### Removed Keys

* `approval_status`
* `deletion_status`
* `gender_id`
* `attendance_type_id`
* `event_type_id`
* `participation_type_id`
* `paid_registration` (list cards only)
* `registration_fee`
* `latitude`
* `longitude`
* `registration_link`
* `license_image`
* `primary_language`
* `images`
* `sponsor_images`
* `interests` (raw id array)
* `created_at`
* `updated_at`
* `created_by` as raw organization id

#### Frontend Action

Search the entire frontend codebase for usage of:

* `images` on events — use `event_images` instead
* other removed event list fields above

---

### Endpoint: `GET /api/events/{id}/`

#### Removed Keys

All keys removed from the event list response, plus on detail:

* `approval_status`
* `deletion_status`
* `gender_id`
* `attendance_type_id`
* `event_type_id`
* `participation_type_id`
* `images`
* `sponsor_images`
* `interests` (raw id array)
* `created_at`
* `updated_at`

#### Frontend Action

Search the entire frontend codebase for usage of the removed event detail fields.

Use: `event_images`, `event_sponsor_images`, `participation_type_display`, `attendance_type_display`, `event_type_display`, `gender_display`, `interest_display`, `created_by`, `is_creator`, `is_registered`, `remaining_slots`.

---

### Endpoint: `GET /api/posts/`

#### Removed Keys

* `user_id`
* `primary_language`
* `needs_support`
* `is_displayed`
* `user.username`
* `user.first_name`
* `user.last_name`
* `images` — replaced by `post_images`
* `mentioned_users`
* `replies` (list endpoint only)

#### Frontend Action

Search the entire frontend codebase for usage of:

* `images` on posts — use `post_images`
* `mentioned_users`
* `needs_support`
* `is_displayed`

---

### Endpoint: `GET /api/posts/{id}/`

#### Removed Keys

Same as post list, except `replies` is still returned on detail.

Removed:

* `user_id`
* `primary_language`
* `needs_support`
* `is_displayed`
* `mentioned_users`
* `images` — use `post_images`

---

### Endpoint: `POST /api/posts/` and `PATCH /api/posts/{id}/`

#### Removed Keys

* `mentioned_users`
* `user_id`
* `primary_language`
* `needs_support`
* `is_displayed`
* `images` — use `post_images`

`forbidden_words_detected` is still returned when applicable.

---

### Endpoint: `GET /api/replies/`

#### Removed Keys

* `user_id`
* `text_en` / `text_ar` naming unchanged
* `primary_language`
* `is_displayed`
* `mentioned_users`
* `images` — use `reply_images`
* `user.username`
* `user.first_name`
* `user.last_name`

#### Frontend Action

Search for `images` on replies — use `reply_images`.

---

### Endpoint: `GET /api/sponsors/`

#### Removed Keys

* `sponsor_type_id`
* `org_type_id`
* `email`
* `country_code`
* `phone_number`
* `type_of_support_id`
* `sponsorship_details`
* `why_interested`
* `resources_expected`
* `approval_status`
* `preferred_language`
* `documents`
* `created_at`

#### Frontend Action

Search for removed sponsor list fields.

Use: `id`, `org_name`, `person_name`, `sponsor_logo`, `_sponsor_type`, `_type_of_support`.

---

### Endpoint: `GET /api/faqs/`

#### Removed Keys

* `created_at`
* `updated_at`
* `is_deleted`
* `deleted_at`

---

### Endpoint: `GET /api/all-profiles/`

#### Removed Keys

From each profile item:

* all `VolunteerProfileWithUserResource` / `OrganizationProfileResource` fields except list-card data
* full `UserResource` in `user_details`

Removed examples:

* `occupation`, `experience`, `health_concerns`
* `socialMedia`, `interests`, `statistics`, `badge_info`
* `documents`, `sector_display`, `organizer_type_display`
* `user_details.dob`, `user_details.email`, `user_details.phone_number`, etc.

#### Frontend Action

Use only:

* `id`, `nickname`, `is_public`, `user_details.id`, `user_details.first_name`, `user_details.last_name`, `user_details.profile_pic`, `user_details.gender_display`, `user_details.user_type`

---

### Endpoint: `GET /api/public-profile/{user_id}/`

#### Removed Keys

From `profile_data` (volunteer):

* `current_badge`

From `profile_data` (organization):

* `field`
* `social_media`
* `organization_status`
* `full_name` duplicate fields not used by website profile screen

#### Frontend Action

Search for removed public profile nested keys.

---

### Endpoint: `GET /api/list-user-opportunities/` and `GET /api/list-all-opportunities/`

#### Removed Keys

Same opportunity list cleanup as `/api/list-volunteer-opportunities/` and `/api/learn-serve-opportunities/`.

For events inside `list-all-opportunities`, removed the old minimal stub shape:

* `type`
* raw `opportunity_status` only response

Events now use the same website event card shape as `/api/events/`.

---

### Endpoint: `GET /api/statistics/`

#### Removed Keys

* `results`

Use `yearly_hours` for charts.

---

### Endpoint: `GET /api/statistics/top/`

#### Removed Keys

* `cycle_info`
* `individuals[].volunteer_hours`
* `individuals[].organizing_hours`

#### Frontend Action

Use `individuals[].total_hours` instead of separate hour breakdown fields.

---

### Endpoint: `GET /api/volunteer-detail/`

#### Removed Keys

* `profile`
* `year_statistics`
* `recent_registrations`

#### New shape

* `total_volunteer_hours`
* `total_opportunities`
* `total_certificates`
* `opportunities.data[]` with `title_en`, `title_ar`, `year`
* `opportunities.meta.pagination`

#### Frontend Action

Update achievement reports to read stats from the root keys above instead of `profile.*`.

---

### Endpoint: `GET /api/event-feedback/`

#### Removed Keys

* `user.username`
* `user.first_name`
* `user.last_name`
* `is_liked_by_me`

#### Frontend Action

Use `user.full_name`, `user.nickname`, `user.profile_pic`.

---

### Endpoint: `GET /api/user-certificates/`

No keys removed. Shape unchanged:

* `registration_id`
* `certificate_image`
* `opportunity__title_en`
* `opportunity__title_ar`

---

## Important Rules

* The API response is now the source of truth.
* Do not assume that previously available keys still exist.
* Do not recreate removed keys on the frontend.
* Do not modify Admin Dashboard functionality.
* Do not change unrelated screens.
* Do not change the API endpoint URL or HTTP method unless explicitly mentioned.
* Verify that all affected Website screens still work correctly.

---

## Validation Checklist

* All removed keys were documented.
* Every affected endpoint is listed.
* No Admin Dashboard API changes are included.
* The removed keys are accurately listed based on the actual Backend changes.
