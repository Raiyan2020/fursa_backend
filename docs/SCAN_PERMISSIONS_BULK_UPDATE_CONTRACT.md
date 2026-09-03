# `POST /scan-permissions/bulk-update/` — contract confirmed

> **To:** Frontend
> **From:** Backend
> **Date:** 2 September 2026
> **Status:** ✅ Resolved — **your existing payload now works. No frontend change needed.**
> **API:** `https://portal.fursa.raiyan.cc/api/`

## TL;DR

Your payload was never wrong in spirit — it just wasn't a shape this endpoint
accepted. Rather than make you migrate to `permissions[]`, **the endpoint now
accepts both shapes**. Deploy the backend and both your flows work unchanged.

```json
{ "opportunity_id": 113, "user_ids": [5, 6], "is_allowed": true }
```

That is now valid. So is the `permissions[]` form. Nothing to change on your side.

---

## 1) What actually happened

The endpoint has only ever validated `permissions[]` — `user_ids` was never
supported (checked the git history; it has never appeared in this controller).
It *is* documented that way in the Postman collection
(`docs/postman/Fursa_API.postman_collection.json` → Bulk Update Scan Permissions,
which lists `permissions[0][user_id]` / `permissions[0][is_allowed]`), so the
contract existed — it just clearly never reached you. That's on us.

Since your flat shape is genuinely the better fit for both your flows (grant N
users, revoke 1 user — one shared flag either way), it made no sense to force a
frontend migration. Both forms are supported now.

## 2) The field table you asked for

| # | Field | Type | Required? | Accepted values / shape | Notes |
|---|-------|------|-----------|--------------------------|-------|
| 1 | `permissions` | array of objects | **One of** `permissions` or `user_ids` | `[{ "user_id": 5, "is_allowed": true }, …]` | Canonical form. Per-entry flag, so one call **can mix** grants and revokes. `min:1`. |
| 2 | `opportunity_id` | integer | **One of** `opportunity_id` or `event_id` | `volunteer_opportunities.id` | Must exist. **Mutually exclusive in practice** — send one. Caller must be `created_by`. |
| 3 | `event_id` | integer | **One of** `opportunity_id` or `event_id` | `events.id` | ✅ **Supported.** Caller must be the event's organization owner. |
| 4 | `user_ids` | array of integers | **One of** `permissions` or `user_ids` | `[5, 6]` | ✅ **Now accepted.** Normalised server-side into `permissions[]` using the shared `is_allowed`. A bare scalar (`5`) is also accepted. |
| 5 | `is_allowed` | boolean | ❌ optional | `true` / `false` | Only meaningful **with `user_ids`**. Applies to every id in the batch. **Omitted ⇒ `true`** (grant), matching the Add Permission flow. Ignored when `permissions[]` is sent, since the flag is per entry there. |

**Precedence:** if you send `permissions[]`, it wins and `user_ids` / `is_allowed`
are ignored. Otherwise `user_ids` + `is_allowed` is converted for you.

### Answers to your specific questions

1. **Exact `permissions` shape** — `[{ "user_id": int, "is_allowed": bool }]`.
   Both keys required per entry. Not a map. But you don't need it — keep `user_ids`.
2. **Grant vs revoke** — same endpoint, no separate route. With `user_ids` it's
   the shared `is_allowed`; with `permissions[]` it's per entry.
3. **Can one call mix grants and revokes?** Yes — **only** via `permissions[]`
   (per-entry flags). With `user_ids` the batch is all-or-nothing by design.
4. **Success response** — standard envelope; `data` is an array of results,
   one per entry. Shape below.
5. **Localized messages** — unchanged. Validation errors stay under
   `response_status.validation_errors`, localized from `Lang` /
   `Accept-Language`. The new "neither shape provided" message is localized too.
6. **Working examples** — below.

## 3) Working examples

### 3a) Bulk grant — several volunteers (Add Permission modal)

```http
POST /api/scan-permissions/bulk-update/
Authorization: Token <token>
Lang: ar
```

```json
{ "opportunity_id": 113, "user_ids": [5, 6], "is_allowed": true }
```

### 3b) Single revoke (trash icon)

```json
{ "opportunity_id": 113, "user_ids": [5], "is_allowed": false }
```

### 3c) Event-scoped (confirmed working)

```json
{ "event_id": 42, "user_ids": [5, 6], "is_allowed": true }
```

### 3d) Mixed grant + revoke in one call (optional, if ever useful)

```json
{
  "opportunity_id": 113,
  "permissions": [
    { "user_id": 5, "is_allowed": true },
    { "user_id": 6, "is_allowed": false }
  ]
}
```

## 4) Success response — `200`

```json
{
  "key": "success",
  "msg": "تم تحديث أذونات المسح بنجاح.",
  "code": 200,
  "response_status": { "error": false, "validation_errors": [] },
  "data": [
    { "user_id": 5, "is_allowed": true, "scan_permission_id": 87 },
    { "user_id": 6, "is_allowed": true, "scan_permission_id": 88 }
  ]
}
```

`data` carries **one entry per user**, in request order:

| Key | Meaning |
|-----|---------|
| `user_id` | echoed back |
| `is_allowed` | the **persisted** value — trust this over what you sent |
| `scan_permission_id` | the `scan_permissions` row id (same id on repeat calls) |

Writes are `updateOrCreate` on `(user_id, opportunity_id, event_id)`, so repeating
a grant **updates** rather than duplicating — `scan_permission_id` stays stable.

## 5) Error responses

### 5a) Neither `permissions` nor `user_ids` — `422`

```json
{
  "key": "fail",
  "msg": "خطأ في التحقق من البيانات",
  "code": 422,
  "response_status": {
    "error": true,
    "validation_errors": {
      "permissions": ["يجب توفير permissions[] أو user_ids[] مع is_allowed."]
    }
  },
  "data": null
}
```

The message now names **both** accepted shapes instead of the bare "field is
required" you were seeing. Your toast prints it verbatim.

### 5b) Neither `opportunity_id` nor `event_id` — `400`

```json
{
  "key": "fail",
  "msg": "مطلوب opportunity_id أو event_id.",
  "code": 400,
  "response_status": { "error": true, "validation_errors": [] },
  "data": null
}
```

> Note this one is `400` with the reason in `msg`, **not** in
> `validation_errors`. Your `getApiErrorMessages()` fallback to `msg` covers it.

### 5c) Not the owner — `403`

```json
{
  "key": "fail",
  "msg": "فقط منشئ الفرصة يمكنه تحديث أذونات المسح.",
  "code": 403,
  "response_status": { "error": true, "validation_errors": [] },
  "data": null
}
```

### 5d) Unknown `user_id` / `opportunity_id` — `422`

Standard Laravel `exists` errors under `validation_errors`, keyed
`permissions.0.user_id` (even when you sent `user_ids`, since normalisation
happens before validation).

> ⚠️ Worth handling: a bad id in a `user_ids` batch reports as
> `permissions.N.user_id`, where `N` is the index in your array. If you want to
> highlight the offending row, map that index back to `user_ids[N]`.

## 6) Note on `GET /scan-permissions/list/`

`list` returns only rows with `is_allowed = true`. After a revoke the row is
kept with `is_allowed = false`, so it **disappears from `list`** rather than
appearing as denied. Refetching after a revoke is the correct way to refresh the
table.

## 7) Test coverage

`tests/Feature/ScanPermissionBulkUpdateTest.php` — 10 tests, all passing:

- `user_ids` + `is_allowed: true` grants
- `user_ids` + `is_allowed: false` revokes
- omitting `is_allowed` defaults to granting
- canonical `permissions[]` still works
- `permissions[]` can mix grants and revokes
- repeating a grant updates rather than duplicating (one row, stable id)
- neither shape → localized `422` under `validation_errors.permissions`
- non-owner → `403`
- missing both scopes → `400`
- unknown `user_id` → `422`

Full suite: **208 tests passing**.

## 8) Your checklist

- [ ] Grant from the Add Permission modal succeeds with the **unchanged** payload
- [ ] Revoke via the trash icon succeeds
- [ ] Event-scoped permissions work with `event_id`
- [ ] Read `is_allowed` from `data[]` rather than assuming what you sent
- [ ] Handle `400` (missing scope) via the `msg` fallback, not `validation_errors`
- [ ] Refetch `list` after a revoke (revoked rows drop out — see §6)

Nothing else needed. Anything unexpected, send the request/response and I'll check.
