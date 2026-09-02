# Fixed: 500 on re-registering after unregistering

> **To:** Frontend
> **From:** Backend
> **Date:** 2 September 2026
> **Status:** ✅ Fixed and tested — no frontend changes needed
> **API:** `https://portal.fursa.raiyan.cc/api/`

Your diagnosis was correct on both counts. `unregister` soft-deletes, and the
unique index was not scoped to exclude soft-deleted rows, so the second `POST`
collided with the retired row.

**No frontend changes required.** `registerForVolunteerOpportunity` /
`unregisterFromVolunteerOpportunity` already send the right payload and endpoint.
Deploy the backend and the flow works as-is.

---

## 1) What was wrong

`store()` did an unconditional `Model::create()`. The pre-existing duplicate
guard above it queried with `notDeleted()`, so a **cancelled** row passed the
guard and then hit the unique index `vol_opp_reg_unique` on
`(opportunity_id, user_id)` → `SQLSTATE[23000] 1062`.

There was a **second** unique index we hit while fixing this:
`vol_assign_reg_uq` on `volunteer_opportunity_assignments.registration_id`.
Because the fix reuses the registration row, the leftover assignment from the
cancelled sign-up blocked a new one. Both are handled now.

## 2) What changed

Three changes, all in `VolunteerOpportunityRegistrationController::store()`:

1. **Revive instead of insert.** An existing row for
   `(opportunity_id, user_id)` — deleted or not — is updated back to active
   (`is_deleted = false`, `deleted_at = null`, `status = pending`,
   `registration_date = now()`), keeping the **same registration id**.
2. **Reuse the assignment row.** The role/team assignment is updated in place
   rather than inserted, so `vol_assign_reg_uq` is not violated. Re-registering
   with no role/team clears the old assignment.
3. **1062 safety net.** `UniqueConstraintViolationException` is caught and
   returned as the normal envelope with **422**. Two concurrent sign-ups can
   still race past the revive; that now fails safely instead of leaking a trace.

The registration-confirmation email and in-app notification fire on
re-registration too, exactly as on a first registration.

## 3) Re-tested repro

All three steps re-run against your exact payloads:

| Step | Request | Before | After |
|------|---------|--------|-------|
| 1 | `POST /api/volunteer-opportunity-registrations/` `{opportunity_id:"97", role_id:"30"}` | `201` ✅ | `201` ✅ |
| 2 | `DELETE /api/volunteer-opportunities/97/unregister/` | `200` ✅ | `200` ✅ |
| 3 | `POST /api/volunteer-opportunity-registrations/` **same payload** | **`500`** ❌ | **`201`** ✅ |

## 4) Exact response shapes

### 4a) Fresh registration — `201`

```json
{
  "key": "success",
  "msg": "تم التسجيل بنجاح في الفرصة.",
  "code": 201,
  "response_status": { "error": false, "validation_errors": [] },
  "data": {
    "registration": {
      "id": 981,
      "opportunity": 97,
      "user": 690,
      "registration_date": "2026-09-02T12:07:03+00:00",
      "status": "pending",
      "full_name": "Ahmed Abdullah",
      "user_email": "ahmed@example.com",
      "team": null,
      "role": { "id": 30, "name_en": "طالب", "name_ar": "طالب" },
      "user_contact_number": "+965-60074170",
      "qr_code_url": "https://portal.fursa.raiyan.cc/storage/volunteer_qr_codes/.../....png",
      "volunteer_uuid": "35d31001-1cd3-443d-b743-356378bfd95f",
      "is_attended": false,
      "date_wise_attended": [],
      "created_at": "2026-09-02T12:07:03+00:00",
      "phone_number": "60074170",
      "civil_id": "295101912002"
    },
    "assignment_id": 690,
    "remaining_slots": 11,
    "user_age": 31,
    "required_age_from": 1,
    "required_age_to": 91,
    "meets_age_requirement": true
  }
}
```

### 4b) Re-registration after cancelling — `201`

**Identical shape.** The only thing worth knowing:

> **`data.registration.id` is the same id as the original registration**, because
> the cancelled row is revived rather than replaced.

If you cache or key off the registration id, a re-registration returns the id you
already had — it is not a new one. `status` is back to `pending` and
`registration_date` is refreshed to the moment of re-registration.

### 4c) Registering twice **without** cancelling — `400` (unchanged)

The existing guard still applies, so this is not silently turned into a
re-registration:

```json
{
  "key": "fail",
  "msg": "أنت مسجل بالفعل في هذه الفرصة.",
  "code": 400,
  "response_status": { "error": true, "validation_errors": [] },
  "data": null
}
```

### 4d) Duplicate that races past the revive — `422` (new)

Only reachable if two registration requests for the same pair land
concurrently. Previously a raw `500`:

```json
{
  "key": "fail",
  "msg": "أنت مسجل بالفعل في هذه الفرصة.",
  "code": 422,
  "response_status": { "error": true, "validation_errors": [] },
  "data": null
}
```

Treat `400` and `422` here as the same user-facing case: "already registered".

### 4e) Unregistering twice — `404` (unchanged)

```json
{
  "key": "fail",
  "msg": "التسجيل غير موجود.",
  "code": 404,
  "response_status": { "error": true, "validation_errors": [] },
  "data": null
}
```

## 5) Test coverage

`tests/Feature/ReRegisterAfterUnregisterTest.php` — 7 tests, all passing:

- register → unregister → register again succeeds
- re-registration reuses the same row (exactly one row for the pair, active again)
- re-registration with a role reassigns it correctly
- **re-registration does not consume two slots of a capacity-1 role** — the
  cancelled assignment must not keep occupying the slot
- registering twice without unregistering is still rejected
- unregistering twice returns `404`, not an error
- three full register/unregister cycles still leave exactly one row

Full suite: **198 tests passing**.

## 6) What to verify on your side

- [ ] Step 3 of the repro returns `201` instead of `500`
- [ ] Re-registration returns the **same** `data.registration.id` as the original
- [ ] Registering twice without cancelling still shows "already registered"
- [ ] `422` is handled the same as `400` in your error mapping
- [ ] The role selected on re-registration is the one reflected in
      `data.registration.role`

Anything off, send the request/response and I will check the contract.
