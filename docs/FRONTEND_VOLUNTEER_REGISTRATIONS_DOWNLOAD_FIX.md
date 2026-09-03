# Backend Fix: Volunteer Registrations Download

The backend implementation is complete. The frontend can keep its current request and download flow.

## Endpoint

```http
GET /api/volunteer-opportunity-registrations/?opportunity_id={id}&download=true&mark_attendance=true&date=YYYY-MM-DD
Authorization: Token {token}
```

Only the creator of the volunteer opportunity can download its registrations sheet or apply the optional attendance side effect.

## Response contract

The API now returns the normal Fursa response envelope with the download information inside `data`:

```json
{
  "key": "success",
  "code": 200,
  "data": {
    "downloadUrl": "https://portal.fursa.raiyan.cc/storage/exports/volunteer-opportunity-113-registrations-20260902-120000-abc123.xlsx",
    "download_url": "https://portal.fursa.raiyan.cc/storage/exports/volunteer-opportunity-113-registrations-20260902-120000-abc123.xlsx",
    "file_format": "xlsx",
    "registrations_count": 10,
    "attendance_marked_count": 8,
    "attendance_already_marked_count": 2
  }
}
```

`downloadUrl` is the canonical camelCase field expected by the current frontend. `download_url` is also returned as a compatibility alias.

If the frontend API wrapper already unwraps the Fursa `data` field, the existing code remains correct:

```ts
const response = await downloadVolunteerRegistrations(params);

if (response.downloadUrl) {
  const link = document.createElement("a");
  link.href = response.downloadUrl;
  link.download = "List of Registered Volunteers.xlsx";
  link.click();
}
```

If the wrapper returns the complete HTTP body instead, read `response.data.downloadUrl`.

## Generated file

The backend generates a real `.xlsx` workbook containing:

- Registration ID
- Full name
- Email
- Phone
- Registration status
- Role
- Team
- Civil ID
- Passport number
- Registration date
- Attendance date
- Attendance state

The sheet includes a styled header, frozen first row, column filters, and readable column widths.

## Attendance behavior

`mark_attendance` is optional.

### Download without changing attendance

```http
GET /api/volunteer-opportunity-registrations/?opportunity_id=113&download=true
```

This only generates the spreadsheet.

### Download and mark attendance

```http
GET /api/volunteer-opportunity-registrations/?opportunity_id=113&download=true&mark_attendance=true&date=2026-09-02
```

Behavior:

1. `date` is required when `mark_attendance=true`.
2. The date must be inside the opportunity's allowed attendance/check-in window.
3. Attendance is marked only for active registrations with status `approved`.
4. Pending, rejected, cancelled, and deleted registrations are not marked.
5. Repeating the same request is safe. Existing attendance rows are skipped and reported in `attendance_already_marked_count`; volunteer hours are not duplicated.
6. Attendance uses the opportunity's configured start/end time to calculate hours and records the method as `manual`.

The query-string values `mark_attendance=true`, `false`, `1`, and `0` are supported.

## Errors

### Missing opportunity ID or invalid parameters

Returns `422` using the existing validation-error envelope.

### User does not own the opportunity

Returns `403`.

### Attendance date outside the allowed window

Returns `400`. No attendance is changed and no spreadsheet link is returned.

## Frontend action

No endpoint change is required. Keep the current request and confirm whether the service returns the unwrapped `data` object or the complete envelope. The success toast should be shown only after `downloadUrl` exists and the browser download has been triggered. If the response succeeds without a URL, show an error rather than a success toast.
