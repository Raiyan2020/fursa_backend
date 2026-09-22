# Fursa Frontend Integration Notes — BE-75 Part B, BE-78

> Backend status as of 2026-09-22, branch `updates`. All items below are implemented, covered by passing automated tests (`tests/Feature/Be78AttendanceScannerTest.php`), and ready for frontend integration.

## BE-75 Part B — `self_check_out_closes_at` on the opportunity detail page ✅ Ready

**What changed:**
- `GET /api/opportunities/{id}/details/` now includes `self_check_out_closes_at` (ISO-8601, ready for the volunteer's registered opportunity) inside the `self_attendance` block — not just on the attendance/self-scan response as before. It is non-null only while `self_attendance.next_action === "out"`.
- Fixed a bug where, for a session ending late at night, `self_attendance.next_action` could incorrectly come back `"in"` during the post-midnight grace period (i.e. it would offer the arrival button to someone who is already checked in and needs to check out). It now correctly reports `"out"` in that window.

**Integration notes for frontend:**
- `selfCheckOutWindow.ts` can now read `self_attendance.self_check_out_closes_at` from the opportunity detail payload directly, instead of deriving the deadline client-side with a hardcoded grace-hours constant. This matters because the grace period is admin-configurable (`self_check_out_grace_hours`) — a local constant can silently disagree with the server.
- No breaking change: the field is additive. Existing consumers of `self_attendance.next_action` / `checked_in_at` / `checked_out_at` are unaffected.

## BE-78 — Navbar attendance scanner ✅ Ready (Parts A and B); Part C confirmed as policy

### Part A — `GET /api/my-attendance-scans/`

**What's new:**
A lightweight, purpose-built endpoint for the navbar scan button — replaces polling `GET /list-user-opportunities/?filter_type=registered&opportunity_status=inprogress` and filtering client-side on `start_time`/`end_time`/`time_slots`.

**Auth required:** Bearer token. Response shape:

```json
{
  "data": [
    {
      "opportunity_id": 412,
      "opportunity_type": "volunteer_opportunity",
      "title_en": "...", "title_ar": "...",
      "next_action": "in",
      "scan_opens_at":  "2026-09-22T13:00:00+00:00",
      "scan_closes_at": "2026-09-22T20:00:00+00:00"
    }
  ]
}
```

- Empty array (`"data": []`) is the common case — cheap to poll.
- `opportunity_type` is `"volunteer_opportunity"` or `"learn_serve_opportunity"`.
- `next_action` is `"in"` or `"out"` for volunteer opportunities; always `null` for learn & serve (single code, no direction).
- `scan_opens_at` — volunteer opportunities: one hour before the session's scheduled start. Learn & serve: two hours before the organizer-issued code's expiry (the code is only ever live for a 2h window).
- `scan_closes_at` — volunteer opportunities: session end + the configured grace period (same value as `self_attendance.self_check_out_closes_at` above). Learn & serve: the code's expiry timestamp.
- An opportunity only appears while "now" is within `[scan_opens_at, scan_closes_at]` (volunteer) or while the organizer's code is live and not yet attended (learn & serve).

**Integration notes for frontend:**
- `features/opportunities/hooks/useLiveAttendanceScans.ts` can now call this endpoint directly instead of `list-user-opportunities` + client-side filtering.
- `features/opportunities/attendanceScanWindow.ts`'s local time-window logic is no longer needed — the endpoint already resolves it server-side.

### Part B — `direction` is now optional on the self-scan endpoint

**What changed:**
`POST /api/volunteer-attendance/self-scan/` no longer requires `direction`. When omitted, the server looks the scanned `code` up against both the IN and OUT columns and infers the direction from whichever matched. A code that matches neither still returns the existing `400` ("Scanned QR code is not valid.").

- Passing an explicit `direction` (`"in"` or `"out"`) still works exactly as before — no need to change the opportunity-detail page's existing call.
- Every check downstream of the code lookup (registration, preparation window, already-checked-in `409`, must-check-in-first, grace deadline) is unchanged.

**Integration notes for frontend:**
- `components/layout/AttendanceScanButton.tsx` can now be: open camera → scan → `POST self-scan` with just `code` → done. No more secondary `GET /opportunities/{id}/details/` lookup to read `next_action` before opening the scanner, and no more picker step when a volunteer is registered in two things at once.
- The interim `next_action` override in the navbar code (forcing `"out"` when `checked_in_at` is set and `checked_out_at` is null) is no longer needed — `GET /my-attendance-scans/` now reports the correct `next_action` directly, including across the midnight grace window (see BE-75 Part B fix above).

### Part C — `qr_attendance_enabled` hardcode: confirmed intended

Every volunteering opportunity takes QR attendance (`VolunteerOpportunityResource.qr_attendance_enabled` stays hardcoded `true`) — this is permanent, unlike learn & serve where internships are excluded via `requires_check_in`. No frontend change needed; the navbar scanner can keep assuming every volunteer-opportunity registration is scannable.

---

All items are covered by automated backend tests and pass cleanly against the current `updates` branch.
