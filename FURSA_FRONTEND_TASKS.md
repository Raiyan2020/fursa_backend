# Fursa Frontend Integration Notes — BE-75, BE-76, BE-77

> Backend status as of 2026-09-21, branch `updates`. All three items below are implemented, covered by passing automated tests, and ready for frontend integration.

## BE-75 — Self check-out grace period (volunteering only) ✅ Ready

**What changed:**
- `POST /api/volunteer-attendance/self-scan/` (`direction: "out"`) now refuses the scan with a `400` once the scheduled session end + a configurable grace period (default 2 hours) has passed. Before that deadline, check-out is accepted as usual.
- The attendance/check-in response now includes `self_check_out_closes_at` (ISO-8601) whenever a check-out is pending — the frontend can read this directly instead of deriving the deadline client-side in `selfCheckOutWindow.ts`.
- Credited `total_hours` on self check-out is capped to the overlap between actual presence and the scheduled session (e.g. arriving early or leaving during the grace period no longer inflates the hours beyond the scheduled window). The recorded `checked_out_at` timestamp always reflects the real scan time — only `total_hours` is clamped.
- Fixed a bug where a session ending late at night (grace period crossing midnight) could fail to resolve the correct attendance record on check-out; this now resolves correctly.
- The organizer's manual hours-correction endpoint (`PATCH /api/volunteer-attendance/{id}/hours/`) is **not** subject to the scheduled-hours cap — an organizer can record a value above the opportunity's scheduled duration (e.g. 6h on a 4h opportunity) to capture legitimate overtime. This is the intended path for extending a shift beyond what self check-out would credit. (The separate manual check-in endpoint, `POST /api/volunteer-attendance/manual/`, is unaffected and still caps at the scheduled hours.)

**Integration notes for frontend:**
- Use `self_check_out_closes_at` from the attendance/check-in payload as the source of truth for any "check out before X" UI, rather than computing it locally.
- A `400` from the self-scan check-out endpoint after the deadline should be surfaced as "check-out window closed" rather than a generic error.

## BE-76 — One announcement image per opportunity/event ✅ Ready

**What changed:**
- Volunteer opportunities, Learn & Serve opportunities, and events now keep at most one "announcement" image (the pre-completion cover image). Uploading a new one silently replaces the existing one — no error, no confirmation step needed on the frontend.
- If a create/update request includes multiple new announcement images in one call, only the last one is kept.
- The after-completion photo gallery is unaffected and remains multi-image as before.

**Integration notes for frontend:**
- The announcement-image upload UI can be simplified to a single-image picker/replace pattern (matches the already-live 4:5 crop UI) — no need to handle a "too many images" error state for this field.

## BE-77 — Opportunity audience targeting (`opportunity_nationality`) ✅ Ready

**What changed:**
- `opportunity_nationality` is now a real four-value field on volunteer and Learn & Serve opportunities: `all`, `kuwaitis`, `non_kuwaiti_arabic`, `non_arabic`. Defaults to `all`. The legacy `is_kuwaitis` boolean is still accepted and returned for compatibility, kept in sync with the new field.
- A new nullable `speaks_arabic` boolean is available on user/volunteer profile endpoints (registration, profile update, account update). Leaving it unanswered (`null`) does not block registration on Arabic-audience opportunities.
- Registration is now actually enforced against the opportunity's audience: a mismatched user gets a `400` with `key: "fail"` and the message "This opportunity is open to {audience} only." / "هذه الفرصة مخصصة لـ {audience} فقط." — same response shape as the existing age-restriction guard.
- The public opportunity list filter (`?opportunity_nationality=`) now correctly accepts all four values, plus the legacy `non-kuwaitis` alias (meaning "not Kuwaitis-only").

**Integration notes for frontend:**
- Opportunity create/update forms should send `opportunity_nationality` (one of the four values) going forward; `is_kuwaitis` remains supported but is considered legacy.
- Registration flows should handle the new `400`/`key: "fail"` audience-mismatch response the same way the existing age-guard error is handled.
- If the signup/profile form asks about Arabic proficiency, it can be left optional — omitting `speaks_arabic` will not incorrectly block a user from mixed-audience opportunities.

---

All three items are covered by automated backend tests and pass cleanly against the current `updates` branch.
