# Frontend Integration: Registration Approval, Rejection, and Messaging

This document is the implementation contract for the frontend. It covers both:

- Volunteer opportunities (`volunteer-opportunities`)
- Learn & Share opportunities, including courses/training (`learn-serve-opportunities`)

All endpoints below require the existing authenticated user token. Only the user who created the opportunity may manage its registrations or message its registrants.

## 1. Registration statuses

Every registration now has one of these exact values in its `status` field:

| Status | Meaning | Suggested Arabic label |
|---|---|---|
| `pending` | Waiting for organizer review | قيد المراجعة |
| `approved` | Accepted by the organizer | مقبول |
| `rejected` | Not accepted / removed by the organizer | مرفوض |

New registrations created by volunteers are now returned as `pending`. Do not show a success message that says the volunteer is confirmed. Suggested message:

> تم إرسال طلب التسجيل وهو قيد مراجعة الجهة المنظمة.

Only `approved` registrations receive automatic three-day and same-day opportunity reminders. Rejected registrations do not consume available capacity.

## 2. Registrations list and status filter

### Volunteer opportunity

```http
GET /api/volunteer-opportunity-registrations/?opportunity_id={opportunityId}&status=pending&page=1&limit=20
```

The `status` query parameter is optional. Supported values: `pending`, `approved`, `rejected`.

### Learn & Share opportunity

```http
GET /api/learn-serve-opportunities/{opportunityId}/registrations/?status=pending&page=1&limit=20
```

The registration object already includes `id`, user details, and `status`. Use the registration `id` (not the user ID) in approval and messaging requests.

## 3. Approve or reject selected registrations

The same payload is used for volunteer and Learn & Share opportunities.

### Volunteer opportunity endpoint

```http
PATCH /api/volunteer-opportunities/{opportunityId}/registrations/status/
Content-Type: application/json
```

### Learn & Share endpoint

```http
PATCH /api/learn-serve-opportunities/{opportunityId}/registrations/status/
Content-Type: application/json
```

### Approve example

```json
{
  "registration_ids": [41, 42, 43],
  "status": "approved"
}
```

### Reject example

```json
{
  "registration_ids": [44, 45],
  "status": "rejected"
}
```

### Success response data

```json
{
  "updated_count": 2,
  "status": "rejected"
}
```

Changing the status automatically sends the volunteer an in-app notification and an email. The frontend must refresh the registrations list after success.

The existing single volunteer-registration endpoint also supports status changes:

```http
PATCH /api/volunteer-opportunity-registrations/{registrationId}/
```

```json
{
  "status": "approved"
}
```

Prefer the new bulk endpoint for the registrations-management screen because it works for one or multiple selected rows.

## 4. Message one, selected, or all registrants

Messages are delivered as both email and in-app notification.

### Volunteer opportunity endpoint

```http
POST /api/volunteer-opportunities/{opportunityId}/registrations/message/
Content-Type: application/json
```

### Learn & Share endpoint

```http
POST /api/learn-serve-opportunities/{opportunityId}/registrations/message/
Content-Type: application/json
```

### Message one or selected registrations

```json
{
  "registration_ids": [41, 42],
  "subject": "تحديث موعد المبادرة",
  "message": "يرجى الحضور الساعة الثامنة صباحاً."
}
```

### Message every registration

```json
{
  "all": true,
  "subject": "تعليمات المبادرة",
  "message": "نرجو قراءة التعليمات قبل الحضور."
}
```

### Message all approved registrations only

```json
{
  "all": true,
  "status": "approved",
  "subject": "تذكير للمقبولين",
  "message": "نراكم غداً في موقع المبادرة."
}
```

The optional `status` filter accepts `pending`, `approved`, or `rejected`. When `all` is not `true`, `registration_ids` must contain at least one registration ID.

Success response:

```json
{
  "sent_count": 12
}
```

## 5. Required UI changes

On each opportunity's registrants screen:

1. Show a status badge on every row.
2. Add status tabs or a filter: All, Pending, Approved, Rejected.
3. Add row checkboxes and a select-all checkbox for the current result set.
4. Add bulk actions:
   - Approve selected
   - Reject selected
   - Message selected
5. Add a `Message all` action. The compose dialog should contain:
   - Recipient option: selected / all
   - Optional status filter when sending to all
   - Subject
   - Message
6. Ask for confirmation before rejection. Rejection replaces organizer-side deletion; do not permanently remove the row from the UI.
7. After an approve/reject request succeeds, refresh the list and update the counts/tabs.
8. Display the API's `updated_count` or `sent_count` in the success toast.

## 6. Important behavior and compatibility notes

- Volunteer self-unregister remains a cancellation/removal initiated by the volunteer.
- Organizer deletion/removal actions now reject the registration instead of deleting it, preserving audit history and preventing reminder emails.
- The legacy volunteer `direct-unregister` response includes both `rejected_count` and `removed_count` for compatibility, but new UI should use `rejected_count`.
- Do not send user IDs in the new bulk actions. Send registration IDs from the list response.
- A `403` means the logged-in user does not own the opportunity.
- A `422` means validation failed, such as an unsupported status. Messaging without `all: true` or any `registration_ids` returns `400`.
