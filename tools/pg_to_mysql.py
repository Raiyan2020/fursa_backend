#!/usr/bin/env python3
"""Convert a Django/PostgreSQL pg_dump (COPY format) into a MySQL SQL file
that matches the Laravel (fursa) migration schema.

Usage:
    python pg_to_mysql.py <input_pg_dump.sql> <output_mysql.sql>
"""

import re
import sys

# ---------------------------------------------------------------------------
# Target Laravel table columns (only these are emitted; anything else dropped)
# ---------------------------------------------------------------------------
LARAVEL_COLUMNS = {
    "choice_types": ["id", "name", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "master_choices": ["id", "choice_type_id", "value_en", "value_ar", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "master_choice_related_tags": ["id", "master_choice_id", "related_master_choice_id"],
    "banner_images": ["id", "image", "name", "banner_url", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "configs": ["id", "cycle_type", "cycle_scope", "cycle_year", "cycle_index", "unit", "duration", "number_of_opportunities", "time_duration", "time_unit", "manual_attendance_threshold", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "expiring_tokens": ["id", "key", "user_id", "expires_at", "created_at"],
    "user_role_license_requirements": ["id", "user_role", "license_required", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "interests": ["id", "name_en", "name_ar", "interest_type", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "interest_user": ["id", "user_id", "interest_id"],
    "master_choice_user": ["id", "user_id", "master_choice_id"],
    "otp_verifications": ["id", "user_id", "verification_type", "otp", "is_used", "created_at"],
    "token_verifications": ["id", "user_id", "verification_type", "token", "is_used", "created_at"],
    "user_type_approvals": ["id", "user_type", "requires_approval", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "users": ["id", "username", "first_name", "last_name", "email", "email_verified_at", "password", "remember_token", "is_staff", "is_active", "is_superuser", "last_login", "date_joined", "dob", "phone_number", "country_code", "is_social_login", "social_media_id", "social_media_provider", "social_profile_pic_url", "manual_id", "profile_pic", "instagram_link", "whatsapp_link", "linkedin_link", "facebook_link", "twitter_link", "user_type", "preferred_language", "password_length", "nationality", "birth_year", "civil_id", "emergency_contact_name", "emergency_contact_phone", "emergency_contact_country_code", "emergency_contact_civil_id", "emergency_contact_relationship_id", "is_banned", "banned_time", "manually_banned", "badge_id", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "badges": ["id", "name", "description", "min_hours", "max_hours", "priority", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "organization_profiles": ["id", "user_id", "nickname", "organizer_type_id", "registration_number", "license_number", "company_name", "sector_id", "organization_status", "rejection_reason", "latitude", "longitude", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "organization_documents": ["id", "organizer_profile_id", "document", "uploaded_at", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "volunteer_profiles": ["id", "user_id", "organization_id", "nickname", "gender_id", "uuid", "qr_code", "occupation", "experience", "health_concerns", "is_public", "is_verified", "total_volunteer_hours", "total_opportunities", "total_certificates", "opportunities_organized", "current_rank", "current_year_hours", "current_badge_id", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "volunteer_statistics": ["id", "user_id", "year", "month", "volunteer_hours", "opportunities_participated", "opportunities_organized", "certificates_earned", "rank", "badge_id", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "organization_statistics": ["id", "user_id", "year", "month", "organization_hours", "vol_opportunity_organized", "learn_opportunity_organized", "sponsored", "badge_id", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "sponsors": ["id", "sponsor_type_id", "org_name", "org_type_id", "person_name", "email", "country_code", "phone_number", "type_of_support_id", "sponsorship_details", "why_interested", "resources_expected", "sponsor_logo", "approval_status", "preferred_language", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "sponsor_documents": ["id", "partner_id", "document", "uploaded_at", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "fursa_friends": ["id", "user_id", "added_by", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "community_tags": ["id", "name", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "forbidden_words": ["id", "word_en", "word_ar", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "posts": ["id", "user_id", "title_en", "title_ar", "idea_text_en", "idea_text_ar", "primary_language", "proposing_idea", "needs_support", "is_funding_required", "is_displayed", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "community_tag_post": ["id", "post_id", "community_tag_id"],
    "post_images": ["id", "post_id", "image", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "replies": ["id", "user_id", "post_id", "parent_id", "text_en", "text_ar", "primary_language", "is_displayed", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "reply_images": ["id", "reply_id", "image", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "likes": ["id", "user_id", "post_id", "reply_id", "is_liked", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "email_templates": ["id", "name", "language", "subject", "content", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "faqs": ["id", "question_en", "question_ar", "answer_en", "answer_ar", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "notifications": ["id", "title_en", "title_ar", "message_en", "message_ar", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "user_notifications": ["id", "user_id", "notification_id", "is_read", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "contact_us": ["id", "name_en", "name_ar", "email", "message_en", "message_ar", "primary_language", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "my_calendars": ["id", "user_id", "volunteer_opportunity_id", "learn_serve_opportunity_id", "event_id", "is_saved", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "volunteer_opportunities": ["id", "approval_status", "opportunity_status", "title_en", "title_ar", "description_en", "description_ar", "due_date", "start_date", "end_date", "participants_needed", "from_age", "to_age", "start_time", "end_time", "latitude", "longitude", "link", "is_calendar", "primary_language", "rejected_reason", "location_en", "location_ar", "opportunity_nationality", "deletion_status", "deletion_rejected_reason", "is_kuwaitis", "created_by", "volunteer_hours_per_day", "gender_id", "is_public", "license_image", "is_relief", "is_interview_needed", "is_urgent", "is_supports_disabled", "generated_link", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "learn_serve_opportunities": ["id", "approval_status", "opportunity_status", "title_en", "title_ar", "description_en", "description_ar", "due_date", "start_date", "end_date", "participants_needed", "from_age", "to_age", "start_time", "end_time", "latitude", "longitude", "link", "is_calendar", "primary_language", "rejected_reason", "location_en", "location_ar", "opportunity_nationality", "deletion_status", "deletion_rejected_reason", "is_kuwaitis", "created_by", "learning_type_id", "gender_id", "format_id", "certificate_type_id", "license_image", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "interest_volunteer_opportunity": ["id", "volunteer_opportunity_id", "interest_id"],
    "master_choice_volunteer_opportunity": ["id", "volunteer_opportunity_id", "master_choice_id"],
    "interest_learn_serve_opportunity": ["id", "learn_serve_opportunity_id", "interest_id"],
    "master_choice_learn_serve_opportunity": ["id", "learn_serve_opportunity_id", "master_choice_id"],
    "opportunity_images": ["id", "volunteer_opportunity_id", "learn_serve_opportunity_id", "image", "is_after_completed", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "opportunity_sponsor_images": ["id", "volunteer_opportunity_id", "learn_serve_opportunity_id", "image", "organization_id", "position", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "volunteer_opportunity_registrations": ["id", "opportunity_id", "user_id", "registration_date", "status", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "volunteer_opportunity_teams": ["id", "opportunity_id", "team_name_en", "team_name_ar", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "volunteer_opportunity_roles": ["id", "opportunity_id", "role_name_en", "role_name_ar", "instructions_en", "instructions_ar", "participants_needed", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "volunteer_opportunity_assignments": ["id", "registration_id", "team_id", "role_id", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "learn_serve_opportunity_time_slots": ["id", "opportunity_id", "date", "start_time", "end_time", "participants_needed", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "learn_serve_opportunity_registrations": ["id", "opportunity_id", "user_id", "registration_date", "status", "certificate_image", "is_certified", "is_attended", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "learn_serve_opportunity_assignments": ["id", "registration_id", "time_slot_id", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "opportunity_feedbacks": ["id", "user_id", "learn_serve_opportunity_id", "rating", "comment_en", "comment_ar", "primary_language", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "feedback_likes": ["id", "user_id", "feedback_id", "is_liked", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "volunteer_opportunity_attendances": ["id", "registration_id", "attended_date", "total_hours", "is_attended", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "events": ["id", "approval_status", "rejected_reason", "deletion_status", "deletion_rejected_reason", "event_status", "from_age", "to_age", "gender_id", "attendance_type_id", "title_en", "title_ar", "event_type_id", "due_date", "start_date", "end_date", "start_time", "end_time", "registration_required", "participants_needed", "paid_registration", "registration_fee", "latitude", "longitude", "location_en", "location_ar", "description_en", "description_ar", "participation_type_id", "registration_link", "created_by", "license_image", "view_count", "primary_language", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "interest_event": ["id", "event_id", "interest_id"],
    "master_choice_event": ["id", "event_id", "master_choice_id"],
    "event_images": ["id", "event_id", "image", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "event_sponsor_images": ["id", "event_id", "image", "organization_id", "position", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "event_time_slots": ["id", "event_id", "date", "start_time", "end_time", "participants_needed", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "event_registrations": ["id", "event_id", "user_id", "time_slot_id", "registration_date", "registration_status", "is_attended", "payment_status", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "event_feedbacks": ["id", "user_id", "event_id", "rating", "comment_en", "comment_ar", "primary_language", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "event_feedback_likes": ["id", "user_id", "feedback_id", "is_liked", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "event_attendances": ["id", "registration_id", "attended_date", "total_hours", "is_attended", "is_deleted", "deleted_at", "created_at", "updated_at"],
    "scan_permissions": ["id", "user_id", "opportunity_id", "event_id", "is_allowed", "is_deleted", "deleted_at", "created_at", "updated_at"],
}

# Django table -> Laravel table. Order respects FK dependencies (parents first).
TABLE_MAP = [
    ("volunteerprofile_badge", "badges"),
    ("base_choicetype", "choice_types"),
    ("base_masterchoice", "master_choices"),
    ("base_masterchoice_related_tags", "master_choice_related_tags"),
    ("authentication_interest", "interests"),
    ("base_config", "configs"),
    ("base_bannerimage", "banner_images"),
    ("base_userrolelicenserequirement", "user_role_license_requirements"),
    ("authentication_usertypeapproval", "user_type_approvals"),
    ("community_tag", "community_tags"),
    ("community_forbiddenword", "forbidden_words"),
    ("email_template_emailtemplate", "email_templates"),
    ("faq_faq", "faqs"),
    ("notification_notification", "notifications"),
    ("contactUs_contactusmodel", "contact_us"),
    ("authentication_customuser", "users"),
    ("authtoken_token", "expiring_tokens"),  # merged with base_expiringtoken
    ("authentication_otpverification", "otp_verifications"),
    ("authentication_tokenverification", "token_verifications"),
    ("authentication_customuser_interests", "interest_user"),
    ("authentication_customuser__interests", "master_choice_user"),
    ("organizationprofile_organizationprofile", "organization_profiles"),
    ("volunteerprofile_volunteerprofile", "volunteer_profiles"),
    ("organizationprofile_organizationdocument", "organization_documents"),
    ("volunteerprofile_volunteerstatistics", "volunteer_statistics"),
    ("organizationprofile_organizationstatistics", "organization_statistics"),
    ("sponsors_sponsor", "sponsors"),
    ("sponsors_sponsordocument", "sponsor_documents"),
    ("fursa_friend_fursafriend", "fursa_friends"),
    ("notification_usernotification", "user_notifications"),
    ("community_post", "posts"),
    ("community_post_tags", "community_tag_post"),
    ("community_postimage", "post_images"),
    ("community_reply", "replies"),
    ("community_replyimage", "reply_images"),
    ("community_like", "likes"),
    ("opportunity_volunteeropportunity", "volunteer_opportunities"),
    ("opportunity_learnserveopportunity", "learn_serve_opportunities"),
    ("opportunity_volunteeropportunity_interests", "interest_volunteer_opportunity"),
    ("opportunity_volunteeropportunity__interests", "master_choice_volunteer_opportunity"),
    ("opportunity_learnserveopportunity_interests", "interest_learn_serve_opportunity"),
    ("opportunity_learnserveopportunity__interests", "master_choice_learn_serve_opportunity"),
    ("opportunity_opportunityimage", "opportunity_images"),
    ("opportunity_opportunitysponsorimage", "opportunity_sponsor_images"),
    ("opportunity_volunteeropportunityregistration", "volunteer_opportunity_registrations"),
    ("opportunity_volunteeropportunityteam", "volunteer_opportunity_teams"),
    ("opportunity_volunteeropportunityrole", "volunteer_opportunity_roles"),
    ("opportunity_volunteeropportunityassignment", "volunteer_opportunity_assignments"),
    ("opportunity_learnserveopportuntiytimeslot", "learn_serve_opportunity_time_slots"),
    ("opportunity_learnserveopportunityregistration", "learn_serve_opportunity_registrations"),
    ("opportunity_learnserveopportunityassignment", "learn_serve_opportunity_assignments"),
    ("opportunity_opportunityfeedback", "opportunity_feedbacks"),
    ("opportunity_feedbacklike", "feedback_likes"),
    ("opportunity_volunteeropportunityattendance", "volunteer_opportunity_attendances"),
    ("event_event", "events"),
    ("event_event_interests", "interest_event"),
    ("event_event__interests", "master_choice_event"),
    ("event_eventimage", "event_images"),
    ("event_eventsponsorimage", "event_sponsor_images"),
    ("event_eventtimeslot", "event_time_slots"),
    ("event_eventregistration", "event_registrations"),
    ("event_eventfeedback", "event_feedbacks"),
    ("event_eventfeedbacklike", "event_feedback_likes"),
    ("event_eventopportunityattendance", "event_attendances"),
    ("opportunity_scanpermission", "scan_permissions"),
    ("my_calendar_mycalendar", "my_calendars"),
]

# Per-Django-table explicit column renames (pg_col -> laravel_col).
RENAMES = {
    "base_masterchoice_related_tags": {"from_masterchoice_id": "master_choice_id", "to_masterchoice_id": "related_master_choice_id"},
    "authentication_customuser_interests": {"customuser_id": "user_id"},
    "authentication_customuser__interests": {"customuser_id": "user_id", "masterchoice_id": "master_choice_id"},
    "fursa_friend_fursafriend": {"added_by_id": "added_by"},
    "community_post_tags": {"tag_id": "community_tag_id"},
    "opportunity_volunteeropportunity": {"created_by_id": "created_by"},
    "opportunity_learnserveopportunity": {"created_by_id": "created_by"},
    "opportunity_volunteeropportunity_interests": {"volunteeropportunity_id": "volunteer_opportunity_id"},
    "opportunity_volunteeropportunity__interests": {"volunteeropportunity_id": "volunteer_opportunity_id", "masterchoice_id": "master_choice_id"},
    "opportunity_learnserveopportunity_interests": {"learnserveopportunity_id": "learn_serve_opportunity_id"},
    "opportunity_learnserveopportunity__interests": {"learnserveopportunity_id": "learn_serve_opportunity_id", "masterchoice_id": "master_choice_id"},
    "event_event": {"created_by_id": "created_by"},
    "event_event__interests": {"masterchoice_id": "master_choice_id"},
}

# Column names whose 't'/'f' values are booleans (safety: only these convert).
# We convert any exact 't'/'f' regardless, which is safe for this data set.

DT_RE = re.compile(r"^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2}:\d{2})(?:\.\d+)?(?:[+-]\d{2}(?::?\d{2})?)?$")


def copy_unescape(field: str) -> str:
    """Unescape a single field from Postgres COPY text format."""
    out = []
    i = 0
    n = len(field)
    while i < n:
        c = field[i]
        if c == "\\" and i + 1 < n:
            nx = field[i + 1]
            mapping = {"n": "\n", "t": "\t", "r": "\r", "b": "\b", "f": "\f", "v": "\v", "\\": "\\"}
            if nx in mapping:
                out.append(mapping[nx])
                i += 2
                continue
            out.append(nx)
            i += 2
            continue
        out.append(c)
        i += 1
    return "".join(out)


def mysql_quote(value):
    if value is None:
        return "NULL"
    if value is True:
        return "1"
    if value is False:
        return "0"
    s = value
    s = s.replace("\\", "\\\\").replace("'", "\\'")
    s = s.replace("\n", "\\n").replace("\r", "\\r").replace("\t", "\\t")
    return "'" + s + "'"


def clean_value(raw):
    """raw is the COPY field string or None (for \\N). Return python value."""
    if raw is None:
        return None
    if raw == "t":
        return True
    if raw == "f":
        return False
    # datetime with timezone / microseconds -> MySQL datetime
    m = DT_RE.match(raw)
    if m:
        return m.group(1) + " " + m.group(2)
    # Strip the GCS "public/" storage prefix so paths work with Laravel
    # public disk + `php artisan storage:link` (served under /storage/...).
    if raw.startswith("public/"):
        return raw[len("public/"):]
    return raw


def parse_copy_blocks(text):
    """Yield (table_name, columns, list_of_rows) for each COPY block.
    Each row is a list of field-strings or None for NULL."""
    lines = text.split("\n")
    i = 0
    n = len(lines)
    copy_re = re.compile(r'^COPY (?:public\.)?"?([A-Za-z0-9_]+)"?\s*\(([^)]*)\) FROM stdin;')
    while i < n:
        line = lines[i]
        m = copy_re.match(line)
        if not m:
            i += 1
            continue
        table = m.group(1)
        cols_raw = m.group(2)
        cols = [c.strip().strip('"') for c in cols_raw.split(",")]
        i += 1
        rows = []
        while i < n and lines[i] != "\\.":
            data_line = lines[i]
            fields = data_line.split("\t")
            row = [None if f == "\\N" else copy_unescape(f) for f in fields]
            rows.append(row)
            i += 1
        yield table, cols, rows
        i += 1  # skip the \.


def main():
    if len(sys.argv) < 3:
        print("Usage: python pg_to_mysql.py <input.sql> <output.sql>")
        sys.exit(1)

    inp, outp = sys.argv[1], sys.argv[2]
    with open(inp, "r", encoding="utf-8") as f:
        text = f.read()

    # Collect all COPY blocks keyed by django table.
    blocks = {}
    for table, cols, rows in parse_copy_blocks(text):
        blocks[table] = (cols, rows)

    # Special: expiring_tokens = authtoken_token JOIN base_expiringtoken
    expires_map = {}
    if "base_expiringtoken" in blocks:
        ecols, erows = blocks["base_expiringtoken"]
        ti = ecols.index("token_ptr_id")
        ei = ecols.index("expires_at")
        for r in erows:
            expires_map[r[ti]] = r[ei]

    map_target = dict(TABLE_MAP)
    out = []
    out.append("-- Auto-generated MySQL dump from PostgreSQL (Django -> Laravel schema)")
    out.append("SET NAMES utf8mb4;")
    out.append("SET FOREIGN_KEY_CHECKS=0;")
    out.append("SET sql_mode='NO_AUTO_VALUE_ON_ZERO';")
    out.append("")

    report = []

    for pg_table, my_table in TABLE_MAP:
        if pg_table not in blocks:
            continue
        target_cols = LARAVEL_COLUMNS[my_table]
        cols, rows = blocks[pg_table]

        # Build (source_index -> target_col) mapping
        renames = RENAMES.get(pg_table, {})
        col_plan = []  # list of (target_col, source_index)
        for idx, c in enumerate(cols):
            tc = renames.get(c)
            if tc is None:
                tc = c[1:] if c.startswith("_") else c
            if tc in target_cols:
                col_plan.append((tc, idx))

        # Special handling for expiring_tokens: add expires_at + generated id
        gen_id = None
        if my_table == "expiring_tokens":
            gen_id = 0

        if not rows:
            report.append(f"{my_table:42s} 0")
            continue

        out.append(f"TRUNCATE TABLE `{my_table}`;")

        emit_cols = [tc for tc, _ in col_plan]
        extra_expires = my_table == "expiring_tokens" and "expires_at" not in emit_cols
        if gen_id is not None and "id" not in emit_cols:
            emit_cols = ["id"] + emit_cols
        if extra_expires:
            emit_cols = emit_cols + ["expires_at"]

        col_sql = ", ".join(f"`{c}`" for c in emit_cols)

        value_rows = []
        for r in rows:
            vals = []
            if gen_id is not None and "id" == emit_cols[0]:
                gen_id += 1
                vals.append(str(gen_id))
            for tc, idx in col_plan:
                vals.append(mysql_quote(clean_value(r[idx])))
            if extra_expires:
                key_idx = cols.index("key")
                keyval = r[key_idx]
                exp = expires_map.get(keyval)
                vals.append(mysql_quote(clean_value(exp)))
            value_rows.append("(" + ", ".join(vals) + ")")

        # batch inserts
        BATCH = 200
        for b in range(0, len(value_rows), BATCH):
            chunk = value_rows[b:b + BATCH]
            out.append(f"INSERT INTO `{my_table}` ({col_sql}) VALUES")
            out.append(",\n".join(chunk) + ";")
        out.append("")
        report.append(f"{my_table:42s} {len(rows)}")

    out.append("SET FOREIGN_KEY_CHECKS=1;")
    out.append("")

    with open(outp, "w", encoding="utf-8") as f:
        f.write("\n".join(out))

    print("=== ROW COUNTS (target table -> rows) ===")
    for line in report:
        print(line)
    print(f"\nWrote: {outp}")


if __name__ == "__main__":
    main()
