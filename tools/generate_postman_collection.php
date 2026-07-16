<?php

/**
 * Generates Fursa API Postman Collection v2.1
 * Run: php tools/generate_postman_collection.php
 *
 * Covers ALL routes defined in routes/api.php across 13 top-level folders.
 */

$out = __DIR__.'/../docs/postman/Fursa_API.postman_collection.json';

function fd(array $fields): array
{
    return array_map(static function (array $f) {
        $item = [
            'key' => $f['key'],
            'type' => $f['type'] ?? 'text',
            'description' => $f['description'] ?? '',
        ];
        if (($f['type'] ?? 'text') === 'file') {
            $item['src'] = [];
            if (array_key_exists('value', $f)) {
                // keep empty src for user to attach
            }
        } else {
            $item['value'] = (string) ($f['value'] ?? '');
        }
        if (! empty($f['disabled'])) {
            $item['disabled'] = true;
        }

        return $item;
    }, $fields);
}

function req(
    string $name,
    string $method,
    string $path,
    string $description,
    bool $auth = false,
    array $formdata = [],
    array $query = [],
    ?array $event = null
): array {
    $raw = '{{base_url}}'.ltrim($path, '/');
    if ($query !== []) {
        $qs = [];
        foreach ($query as $q) {
            if (! ($q['disabled'] ?? false)) {
                $qs[] = urlencode($q['key']).'='.urlencode((string) ($q['value'] ?? ''));
            }
        }
        if ($qs !== []) {
            $raw .= (str_contains($raw, '?') ? '&' : '?').implode('&', $qs);
        }
    }

    $url = [
        'raw' => $raw,
        'host' => ['{{base_url}}'],
        'path' => array_values(array_filter(explode('/', trim(explode('?', $path)[0], '/')), static fn ($p) => $p !== '')),
    ];

    if ($query !== []) {
        $url['query'] = array_map(static function ($q) {
            $item = [
                'key' => $q['key'],
                'value' => (string) ($q['value'] ?? ''),
                'description' => $q['description'] ?? '',
            ];
            if (! empty($q['disabled'])) {
                $item['disabled'] = true;
            }

            return $item;
        }, $query);
    }

    $request = [
        'auth' => $auth
            ? [
                'type' => 'bearer',
                'bearer' => [
                    ['key' => 'token', 'value' => '{{token}}', 'type' => 'string'],
                ],
            ]
            : ['type' => 'noauth'],
        'method' => strtoupper($method),
        'header' => [],
        'url' => $url,
        'description' => $description,
    ];

    if ($formdata !== []) {
        $request['body'] = [
            'mode' => 'formdata',
            'formdata' => fd($formdata),
        ];
    }

    $item = [
        'name' => $name,
        'request' => $request,
        'response' => [],
    ];

    if ($event !== null) {
        $item['event'] = $event;
    }

    return $item;
}

/** Shortcut for a paginated GET's standard page/limit query params. */
function pageLimit(int $defaultLimit = 20, int $maxLimit = 100): array
{
    return [
        ['key' => 'page', 'value' => '1', 'description' => 'Page number (starts at 1).'],
        ['key' => 'limit', 'value' => (string) $defaultLimit, 'description' => "Results per page (1-{$maxLimit})."],
    ];
}

$loginEvent = [[
    'listen' => 'test',
    'script' => [
        'type' => 'text/javascript',
        'exec' => [
            "try {",
            "  const json = pm.response.json();",
            "  const token =",
            "    (json.data && json.data.data && json.data.data.auth_token) ||",
            "    (json.data && json.data.auth_token) ||",
            "    (json.data && json.data.token) ||",
            "    json.auth_token ||",
            "    json.token;",
            "  if (token) {",
            "    pm.collectionVariables.set('token', token);",
            "    console.log('Saved {{token}} from login/verify response');",
            "  }",
            "  if (json.data && json.data.data && json.data.data.id) {",
            "    pm.collectionVariables.set('user_id', String(json.data.data.id));",
            "  } else if (json.data && json.data.user_id) {",
            "    pm.collectionVariables.set('user_id', String(json.data.user_id));",
            "  } else if (json.data && json.data.id) {",
            "    pm.collectionVariables.set('user_id', String(json.data.id));",
            "  }",
            "} catch (e) { console.log('No JSON body to parse for token'); }",
        ],
    ],
]];

$verifyEvent = [[
    'listen' => 'test',
    'script' => [
        'type' => 'text/javascript',
        'exec' => [
            "try {",
            "  const json = pm.response.json();",
            "  if (json.data && json.data.token) {",
            "    if (pm.request.body && pm.request.body.urlencoded) { /* noop */ }",
            "    const typeField = (pm.request.body.formdata || []).find(f => f.key === 'type');",
            "    const type = typeField ? typeField.value : '';",
            "    if (type === 'register') {",
            "      pm.collectionVariables.set('token', json.data.token);",
            "      console.log('Saved auth {{token}} from register OTP verify');",
            "    } else if (type === 'password') {",
            "      pm.collectionVariables.set('reset_token', json.data.token);",
            "      console.log('Saved {{reset_token}} for change-password');",
            "    }",
            "  }",
            "  if (json.data && json.data.user_id) {",
            "    pm.collectionVariables.set('user_id', String(json.data.user_id));",
            "  }",
            "} catch (e) {}",
        ],
    ],
]];

$collection = [
    'info' => [
        '_postman_id' => 'fursa-api-collection-2026',
        'name' => 'Fursa API',
        'description' => <<<'MD'
# Fursa (فرصة) REST API

Professional Postman collection for the Laravel Fursa mobile/API backend. It mirrors the legacy Django backend's URL layout (trailing slashes) and covers **every** route registered in `routes/api.php`.

## Setup
1. Import this collection (and, optionally, an environment that overrides `base_url`).
2. `base_url` defaults to `http://fursa.test/api/` — update it to your local/staging host.
3. Run **01 Auth → Login & Session → Login** with a seeded account (see table below). The response test script stores the bearer `{{token}}` automatically.
4. For OTP-based flows, run **Verify OTP Or Token** after registering — its test script stores `{{token}}` (register) or `{{reset_token}}` (password reset).
5. Organization-only endpoints (Events, etc.) require logging in with the seeded `{{org_email}}` account instead.

## Authentication
Protected endpoints use **Bearer Token** auth via the collection variable `{{token}}`.
Header sent: `Authorization: Bearer {{token}}` (the API also accepts the legacy `Authorization: Token <key>` header).
Public/no-auth endpoints are explicitly marked `noauth` on the request.

## Body convention
All write requests (`POST` / `PUT` / `PATCH` / `DELETE` with a body) use **multipart/form-data**, matching how the Laravel controllers read `$request->validate()` / `$request->file()`. File fields are marked `type: file` — attach a real file in Postman before sending.

## Response envelope
Every JSON response follows the shared envelope:

```json
{
  "key": "success",
  "msg": "Human readable message (localized by Accept-Language).",
  "code": 200,
  "response_status": { "error": false, "validation_errors": [] },
  "data": { }
}
```

Paginated list endpoints additionally include `meta.pagination` (page, limit, total, total_pages).

## Seeded demo accounts
| Email | Password | Type |
|-------|----------|------|
| volunteer@fursa.local | Password1 | Volunteer |
| organization@fursa.local | Password1 | Organization |

## Folder map
01 Auth · 02 Base & Lookups · 03 FAQ · 04 Volunteer Profile · 05 Organization Profile · 06 Opportunities · 07 Events · 08 Community · 09 Notifications · 10 Calendar · 11 Sponsors · 12 Contact Us · 13 Statistics & Certificates
MD,
        'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json',
    ],
    'auth' => [
        'type' => 'bearer',
        'bearer' => [
            ['key' => 'token', 'value' => '{{token}}', 'type' => 'string'],
        ],
    ],
    'variable' => [
        ['key' => 'base_url', 'value' => 'http://fursa.test/api/'],
        ['key' => 'token', 'value' => ''],
        ['key' => 'reset_token', 'value' => ''],
        ['key' => 'email', 'value' => 'volunteer@fursa.local'],
        ['key' => 'password', 'value' => 'Password1'],
        ['key' => 'org_email', 'value' => 'organization@fursa.local'],
        ['key' => 'user_id', 'value' => '1'],
        ['key' => 'choice_type', 'value' => 'gender'],
        ['key' => 'volunteer_uuid', 'value' => '00000000-0000-0000-0000-000000000001'],
        ['key' => 'otp', 'value' => '123456'],
        ['key' => 'opportunity_id', 'value' => '1'],
        ['key' => 'event_id', 'value' => '1'],
        ['key' => 'post_id', 'value' => '1'],
        ['key' => 'registration_id', 'value' => '1'],
        ['key' => 'feedback_id', 'value' => '1'],
        ['key' => 'sponsor_id', 'value' => '1'],
        ['key' => 'contact_id', 'value' => '1'],
        ['key' => 'time_slot_id', 'value' => '1'],
        ['key' => 'role_id', 'value' => '1'],
        ['key' => 'team_id', 'value' => '1'],
        ['key' => 'reply_id', 'value' => '1'],
    ],
    'item' => [],
];

// =====================================================================
// 01 Auth
// =====================================================================
$auth = [
    'name' => '01 Auth',
    'description' => 'Registration, login/session, password reset, social auth, and account endpoints.',
    'item' => [
        [
            'name' => 'Registration',
            'description' => 'Create volunteer or organization accounts, and pre-check email/nickname availability.',
            'item' => [
                req(
                    'Register Volunteer',
                    'POST',
                    'register/',
                    "## Register Volunteer\nCreates a new volunteer account and triggers activation (OTP email or activation link, based on `AUTHENTICATION_METHOD`).\n\n**Auth:** Public (no auth).\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| email | Yes | Unique account email |\n| password | Recommended | Min 8 chars, at least 1 uppercase + 1 digit (e.g. `Password1`) |\n| user_type | No | `volunteer` (default) |\n| first_name / last_name | No | Name fields |\n| phone_number / country_code | No | Contact phone |\n| civil_id | Yes (volunteer) | Kuwait civil ID (max 12, unique) |\n| nickname | No | Display nickname |\n| gender | No | `master_choices.id` for gender |\n| nationality | No | Nationality code, e.g. `KW` |\n| birth_year / dob | No | Used for age checks — if age < 18, `emergency_contact_*` become required |\n| preferred_language | No | `en` or `ar` |\n| profile_pic | No | Image file |\n| organization_id | No | Optional `organization_profiles.id` to affiliate with |\n| emergency_contact_* | Conditional | Required when volunteer age < 18 |",
                    false,
                    [
                        ['key' => 'email', 'value' => 'new.volunteer@example.com', 'description' => 'Unique email for the new volunteer account.'],
                        ['key' => 'password', 'value' => 'Password1', 'description' => 'Password: min 8 chars, include uppercase letter and a digit.'],
                        ['key' => 'user_type', 'value' => 'volunteer', 'description' => 'Account type. Use `volunteer` for this request.'],
                        ['key' => 'first_name', 'value' => 'Ahmed', 'description' => 'Volunteer first name.'],
                        ['key' => 'last_name', 'value' => 'Ali', 'description' => 'Volunteer last name.'],
                        ['key' => 'phone_number', 'value' => '50000001', 'description' => 'Mobile phone number without country code (max 15).'],
                        ['key' => 'country_code', 'value' => '+965', 'description' => 'International dialing code.'],
                        ['key' => 'civil_id', 'value' => '290010100001', 'description' => 'Kuwait Civil ID (required & unique for volunteers, max 12).'],
                        ['key' => 'nickname', 'value' => 'ahmed_vol', 'description' => 'Public nickname on the volunteer profile.'],
                        ['key' => 'gender', 'value' => '1', 'description' => 'Gender master choice ID (`master_choices.id`). Get IDs from 02 Base & Lookups → Get Choices.'],
                        ['key' => 'nationality', 'value' => 'KW', 'description' => 'Nationality code/value.'],
                        ['key' => 'birth_year', 'value' => '1995', 'description' => 'Birth year (integer). Used with/without dob for age checks.'],
                        ['key' => 'dob', 'value' => '1995-05-15', 'description' => 'Date of birth `YYYY-MM-DD`. If under 18, emergency contact fields are required.'],
                        ['key' => 'preferred_language', 'value' => 'ar', 'description' => 'Preferred UI language: `en` or `ar`.'],
                        ['key' => 'profile_pic', 'type' => 'file', 'description' => 'Optional profile image upload.'],
                        ['key' => 'organization_id', 'value' => '', 'description' => 'Optional organization_profiles.id to affiliate with.', 'disabled' => true],
                        ['key' => 'emergency_contact_name', 'value' => '', 'description' => 'Guardian name (required if age < 18).', 'disabled' => true],
                        ['key' => 'emergency_contact_phone', 'value' => '', 'description' => 'Guardian phone (required if age < 18).', 'disabled' => true],
                        ['key' => 'emergency_contact_country_code', 'value' => '+965', 'description' => 'Guardian country code (required if age < 18).', 'disabled' => true],
                        ['key' => 'emergency_contact_civil_id', 'value' => '', 'description' => 'Guardian civil ID matching `/^[23]\\d{11}$/` (required if age < 18).', 'disabled' => true],
                        ['key' => 'emergency_contact_relationship', 'value' => '', 'description' => 'Relationship master_choices.id (required if age < 18).', 'disabled' => true],
                    ]
                ),
                req(
                    'Register Organization',
                    'POST',
                    'register/',
                    "## Register Organization (Entity)\nCreates a new organization/entity account. Profile stays pending until admin approval.\n\n**Auth:** Public (no auth).\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| email | Yes | Unique email |\n| password | Recommended | Strong password rule |\n| user_type | Yes | Must be `organization` |\n| company_name | Recommended | Entity display name |\n| nickname | No | Entity nickname |\n| organizer_type | No | `master_choices.id` |\n| registration_number / license_number | No | Official numbers |\n| documents[] | No | Supporting document files |\n| latitude / longitude | No | Map coordinates |\n| profile_pic | No | Logo/avatar image |",
                    false,
                    [
                        ['key' => 'email', 'value' => 'new.org@example.com', 'description' => 'Unique email for the organization account.'],
                        ['key' => 'password', 'value' => 'Password1', 'description' => 'Password: min 8 chars, uppercase + digit.'],
                        ['key' => 'user_type', 'value' => 'organization', 'description' => 'Must be `organization` for entity signup.'],
                        ['key' => 'first_name', 'value' => 'Org', 'description' => 'Contact first name on the user record.'],
                        ['key' => 'last_name', 'value' => 'Manager', 'description' => 'Contact last name on the user record.'],
                        ['key' => 'phone_number', 'value' => '50000002', 'description' => 'Organization contact phone.'],
                        ['key' => 'country_code', 'value' => '+965', 'description' => 'Dialing code.'],
                        ['key' => 'company_name', 'value' => 'Fursa Demo Entity', 'description' => 'Official / display company name.'],
                        ['key' => 'nickname', 'value' => 'fursa_entity', 'description' => 'Short nickname for the entity profile.'],
                        ['key' => 'organizer_type', 'value' => '1', 'description' => 'Organizer type master_choices.id.'],
                        ['key' => 'registration_number', 'value' => 'REG-1001', 'description' => 'Commercial/registration number.'],
                        ['key' => 'license_number', 'value' => 'LIC-2002', 'description' => 'License number if applicable.'],
                        ['key' => 'latitude', 'value' => '29.3759', 'description' => 'Entity latitude.'],
                        ['key' => 'longitude', 'value' => '47.9774', 'description' => 'Entity longitude.'],
                        ['key' => 'preferred_language', 'value' => 'ar', 'description' => '`en` or `ar`.'],
                        ['key' => 'nationality', 'value' => 'KW', 'description' => 'Nationality / country value.'],
                        ['key' => 'profile_pic', 'type' => 'file', 'description' => 'Optional logo/profile image.'],
                        ['key' => 'documents[]', 'type' => 'file', 'description' => 'Organization supporting document file (repeat key for multiple).'],
                    ]
                ),
                req(
                    'Check User',
                    'POST',
                    'check-user/',
                    "## Check User\nChecks whether an email and/or nickname is already taken (used for signup live validation). Provide at least one of `email` or `nickname`.\n\n**Auth:** Public (no auth).\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| email | One of | Email availability check |\n| nickname | One of | Nickname availability across volunteer/org profiles |",
                    false,
                    [
                        ['key' => 'email', 'value' => 'volunteer@fursa.local', 'description' => 'Email to check for existing registration.'],
                        ['key' => 'nickname', 'value' => 'ahmed_vol', 'description' => 'Nickname to check across volunteer/organization profiles.'],
                    ]
                ),
            ],
        ],
        [
            'name' => 'Login & Session',
            'description' => 'Login and the authenticated account read/update endpoints.',
            'item' => [
                req(
                    'Login',
                    'POST',
                    'login/',
                    "## Login\nAuthenticates with email/password and returns `auth_token`.\n\n**Token path in response:** `data.data.auth_token`. A test script saves it into the collection variable `{{token}}` automatically.\n\n**Auth:** Public (no auth).\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| email | Yes | Registered account email |\n| password | Yes | Account password |\n| rememberMe | No | `true`/`1` extends token lifetime (~30d vs ~1d) |\n| is_opportunity | No | When true, restricts login to volunteer accounts |",
                    false,
                    [
                        ['key' => 'email', 'value' => '{{email}}', 'description' => 'Account email. Default seeded volunteer: volunteer@fursa.local.'],
                        ['key' => 'password', 'value' => '{{password}}', 'description' => 'Account password. Seeded demo password: Password1.'],
                        ['key' => 'rememberMe', 'value' => '1', 'description' => 'Pass 1/true for a longer-lived auth token.'],
                        ['key' => 'is_opportunity', 'value' => '0', 'description' => 'Pass 1/true to allow only volunteer users to log in.'],
                    ],
                    [],
                    $loginEvent
                ),
                req(
                    'Get Account',
                    'GET',
                    'account/',
                    "## Get Account\nReturns the authenticated user's account payload (profile fields, badge, interests).\n\n**Auth required:** Bearer `{{token}}`.",
                    true
                ),
                req(
                    'Update Account',
                    'PUT',
                    'account/',
                    "## Update Account\nUpdates account fields for the authenticated user. Supports profile image upload via form-data. `PATCH` is also accepted.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| profile_pic | No | New avatar image file |\n| first_name / last_name | No | Name fields |\n| email | No | New unique email |\n| phone_number / country_code | No | Phone |\n| birth_year / nationality / preferred_language | No | Profile meta |\n| civil_id | No | Unique civil ID |\n| emergency_contact_* | No | Emergency contact block |",
                    true,
                    [
                        ['key' => 'first_name', 'value' => 'Ahmed', 'description' => 'Updated first name.'],
                        ['key' => 'last_name', 'value' => 'Ali', 'description' => 'Updated last name.'],
                        ['key' => 'email', 'value' => '{{email}}', 'description' => 'Updated email (must remain unique).'],
                        ['key' => 'phone_number', 'value' => '50000001', 'description' => 'Updated phone number.'],
                        ['key' => 'country_code', 'value' => '+965', 'description' => 'Updated dialing code.'],
                        ['key' => 'birth_year', 'value' => '1995', 'description' => 'Updated birth year.'],
                        ['key' => 'nationality', 'value' => 'KW', 'description' => 'Updated nationality.'],
                        ['key' => 'preferred_language', 'value' => 'ar', 'description' => '`en` or `ar`.'],
                        ['key' => 'civil_id', 'value' => '290010100001', 'description' => 'Updated civil ID (unique).'],
                        ['key' => 'profile_pic', 'type' => 'file', 'description' => 'Optional new profile image.'],
                        ['key' => 'emergency_contact_name', 'value' => 'Parent Name', 'description' => 'Emergency contact full name.'],
                        ['key' => 'emergency_contact_phone', 'value' => '50001111', 'description' => 'Emergency contact phone.'],
                        ['key' => 'emergency_contact_country_code', 'value' => '+965', 'description' => 'Emergency contact dialing code.'],
                        ['key' => 'emergency_contact_civil_id', 'value' => '290010100002', 'description' => 'Emergency contact civil ID.'],
                        ['key' => 'emergency_contact_relationship', 'value' => '1', 'description' => 'Relationship master_choices.id.'],
                    ]
                ),
            ],
        ],
        [
            'name' => 'Password Reset',
            'description' => 'Forgot-password OTP/link flow, verification, resend, and password change.',
            'item' => [
                req(
                    'Forgot Password',
                    'POST',
                    'forgot-password/',
                    "## Forgot Password\nStarts the password reset flow (sends OTP or reset link depending on `AUTHENTICATION_METHOD`).\n\n**Auth:** Public (no auth).\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| email | Yes | Account email that will receive the reset OTP/link |",
                    false,
                    [
                        ['key' => 'email', 'value' => '{{email}}', 'description' => 'Registered user email to receive the password reset OTP/link.'],
                    ]
                ),
                req(
                    'Verify OTP Or Token',
                    'POST',
                    'verify_otp_or_token/',
                    "## Verify OTP Or Token\nVerifies an OTP for `register` or `password` flows (only active when `AUTHENTICATION_METHOD=OTP`).\n\n**Auth:** Public (no auth).\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| email | Yes | Account email |\n| type | Yes | `register` or `password` |\n| otp | Yes | OTP code from email |\n\n### Response tokens\n- `type=register` → `data.token` is the API auth key (saved to `{{token}}`).\n- `type=password` → `data.token` is the reset token (saved to `{{reset_token}}`).",
                    false,
                    [
                        ['key' => 'email', 'value' => '{{email}}', 'description' => 'Email that received the OTP.'],
                        ['key' => 'type', 'value' => 'register', 'description' => 'OTP purpose: `register` (activation) or `password` (reset).'],
                        ['key' => 'otp', 'value' => '{{otp}}', 'description' => 'One-time password from email (example placeholder 123456).'],
                    ],
                    [],
                    $verifyEvent
                ),
                req(
                    'Resend OTP Or Token',
                    'POST',
                    'resend_otp_or_token/',
                    "## Resend OTP Or Token\nResends the activation or password-reset OTP/link.\n\n**Auth:** Public (no auth).\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| email | Yes | Target account email |\n| type | Yes | `register` or `password` |",
                    false,
                    [
                        ['key' => 'email', 'value' => '{{email}}', 'description' => 'Account email to resend OTP/link to.'],
                        ['key' => 'type', 'value' => 'register', 'description' => '`register` for activation, `password` for reset.'],
                    ]
                ),
                req(
                    'Change Password',
                    'POST',
                    'change-password/',
                    "## Change Password\nSets a new password using either a reset `token` (from Forgot Password → Verify OTP) **or** `old_password`.\n\n**Auth:** Public (no auth) — identity is proven via token or old password.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| email | Yes | Account email |\n| password | Yes | New password (min 8, uppercase + digit) |\n| token | Conditional | Reset token from Verify OTP (`type=password`) |\n| old_password | Conditional | Current password if changing without a reset token |",
                    false,
                    [
                        ['key' => 'email', 'value' => '{{email}}', 'description' => 'Account email.'],
                        ['key' => 'password', 'value' => 'Password1', 'description' => 'New password (min 8, must include uppercase + digit).'],
                        ['key' => 'token', 'value' => '{{reset_token}}', 'description' => 'Password-reset token from Verify OTP (type=password).'],
                        ['key' => 'old_password', 'value' => '', 'description' => 'Current password if not using a reset token.', 'disabled' => true],
                    ]
                ),
            ],
        ],
        [
            'name' => 'Social Auth',
            'description' => 'Google/LinkedIn social login and LinkedIn OAuth code exchange.',
            'item' => [
                req(
                    'Social Auth',
                    'POST',
                    'social-auth/',
                    "## Social Auth\nLogs in or registers via a social provider payload (Google/LinkedIn). Returns `auth_token` at `data.auth_token` (the shared test script also stores `{{token}}` when present).\n\n**Auth:** Public (no auth).\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| email | Yes | Social account email |\n| social_media_provider | Yes | `google` or `linkedin` |\n| social_media_id | No | Provider user id |\n| first_name / last_name | No | Name from provider |\n| social_profile_pic_url | No | Avatar URL |\n| user_type | No | Defaults to `volunteer` for new users |\n| civil_id | Yes for new volunteer | Civil ID |\n| nickname / company_name | No | Profile fields |",
                    false,
                    [
                        ['key' => 'email', 'value' => 'social.user@gmail.com', 'description' => 'Email returned by the social provider.'],
                        ['key' => 'social_media_provider', 'value' => 'google', 'description' => 'Provider name: `google` or `linkedin`.'],
                        ['key' => 'social_media_id', 'value' => 'google-uid-12345', 'description' => 'Unique user id from the social provider.'],
                        ['key' => 'first_name', 'value' => 'Social', 'description' => 'First name from provider profile.'],
                        ['key' => 'last_name', 'value' => 'User', 'description' => 'Last name from provider profile.'],
                        ['key' => 'social_profile_pic_url', 'value' => 'https://lh3.googleusercontent.com/a/default-user', 'description' => 'Profile picture URL from provider.'],
                        ['key' => 'user_type', 'value' => 'volunteer', 'description' => 'Used when creating a new user.'],
                        ['key' => 'civil_id', 'value' => '290010100099', 'description' => 'Required when creating a new volunteer via social auth.'],
                        ['key' => 'nickname', 'value' => 'social_vol', 'description' => 'Optional nickname for the new profile.'],
                        ['key' => 'company_name', 'value' => '', 'description' => 'Optional company name for organization social signup.', 'disabled' => true],
                    ],
                    [],
                    $loginEvent
                ),
                req(
                    'LinkedIn Callback',
                    'POST',
                    'linkedin/callback/',
                    "## LinkedIn Callback\nExchanges a LinkedIn OAuth `code` for the LinkedIn profile + LinkedIn `access_token` (not the Fursa API token — pass the profile fields to Social Auth next).\n\n**Auth:** Public (no auth).\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| code | Yes | LinkedIn OAuth authorization code |\n| redirect_uri | Yes | Exact redirect URI used in the authorize step |",
                    false,
                    [
                        ['key' => 'code', 'value' => 'AQT_LINKEDIN_AUTH_CODE', 'description' => 'Authorization code returned by the LinkedIn OAuth redirect.'],
                        ['key' => 'redirect_uri', 'value' => 'https://your-frontend.example/linkedin/callback', 'description' => 'Must match the redirect_uri configured in the LinkedIn app + authorize URL.'],
                    ]
                ),
            ],
        ],
        [
            'name' => 'Account',
            'description' => 'Public profile lookup by numeric user id.',
            'item' => [
                req(
                    'Public Profile',
                    'GET',
                    'public-profile/{{user_id}}/',
                    "## Public Profile\nReturns a public user profile (volunteer or organization) by numeric user id, including badge and interests.\n\n**Auth:** Public (no auth).\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| userId | Target `users.id` (collection var `{{user_id}}`) |",
                    false
                ),
            ],
        ],
    ],
];

// =====================================================================
// 02 Base & Lookups
// =====================================================================
$base = [
    'name' => '02 Base & Lookups',
    'description' => 'Master-choice lookups, banner images/platform stats, image proxy, license checks, and combined profile discovery.',
    'item' => [
        req(
            'Get Choices',
            'GET',
            'choices/{{choice_type}}/',
            "## Get Choices\nReturns master choice options for a given choice-type slug/name (e.g. gender, nationality, org_type).\n\n**Auth:** Public (no auth).\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| choiceType | Choice type key (collection var `{{choice_type}}`, e.g. `gender`, `org_type`) |",
            false
        ),
        req(
            'Banner Images',
            'GET',
            'banner-images/',
            "## Banner Images\nPublic list of homepage banner images plus platform statistics (volunteer/organization/team counts).\n\n**Auth:** Public (no auth).",
            false
        ),
        req(
            'Proxy Image',
            'GET',
            'proxy-image/',
            "## Proxy Image\nProxies a remote image URL or a storage-relative path for CORS-friendly delivery. Returns raw image bytes.\n\n**Auth:** Public (no auth).\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| url | One of | Absolute remote image URL to fetch and proxy |\n| key | One of | Relative path on the `public` disk |\n| path | Alias | Same as `key` |",
            false,
            [],
            [
                ['key' => 'url', 'value' => 'https://via.placeholder.com/150', 'description' => 'Remote image URL to proxy.'],
                ['key' => 'key', 'value' => '', 'description' => 'Alternative: relative public-disk path.', 'disabled' => true],
                ['key' => 'path', 'value' => '', 'description' => 'Alias of `key`.', 'disabled' => true],
            ]
        ),
        req(
            'Proxy Image (CORS Preflight)',
            'OPTIONS',
            'proxy-image/',
            "## Proxy Image (CORS Preflight)\nOPTIONS preflight for the image proxy endpoint. Returns CORS headers only, no body.\n\n**Auth:** Public (no auth).",
            false
        ),
        req(
            'Check License Requirement',
            'GET',
            'check-license-requirement/',
            "## Check License Requirement\nReturns whether the authenticated user's role (`volunteer`, `organization`, or `volunteer_team`) requires uploading a license document.\n\n**Auth required:** Bearer `{{token}}`.",
            true
        ),
        req(
            'All Profiles',
            'GET',
            'all-profiles/',
            "## All Profiles\nCombined public discovery endpoint returning three independently paginated buckets: `volunteer`, `organization`, and `volunteer_team` profiles — useful for a unified directory/search screen.\n\n**Auth:** Public (no auth).\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| search | No | Matches nickname or first/last name across all buckets |\n| name | No | Matches first/last name only |\n| nickname | No | Matches nickname only |\n| user_type | No | Restrict to one bucket: `volunteer`, `organization`, or `volunteer_team` |\n| page | No | Shared default page number for all buckets |\n| volunteer_page / organization_page / volunteer_team_page | No | Per-bucket page override |\n| limit | No | Results per page per bucket (1-100, default 10) |",
            false,
            [],
            [
                ['key' => 'search', 'value' => '', 'description' => 'Search term across nickname/first/last name (all buckets).', 'disabled' => true],
                ['key' => 'name', 'value' => '', 'description' => 'Filter by first/last name.', 'disabled' => true],
                ['key' => 'nickname', 'value' => '', 'description' => 'Filter by nickname.', 'disabled' => true],
                ['key' => 'user_type', 'value' => 'volunteer', 'description' => 'Restrict results to one bucket: volunteer, organization, or volunteer_team.', 'disabled' => true],
                ['key' => 'page', 'value' => '1', 'description' => 'Default page number applied to all buckets unless overridden.'],
                ['key' => 'volunteer_page', 'value' => '1', 'description' => 'Page number override for the volunteer bucket.', 'disabled' => true],
                ['key' => 'organization_page', 'value' => '1', 'description' => 'Page number override for the organization bucket.', 'disabled' => true],
                ['key' => 'volunteer_team_page', 'value' => '1', 'description' => 'Page number override for the volunteer_team bucket.', 'disabled' => true],
                ['key' => 'limit', 'value' => '10', 'description' => 'Results per page per bucket (1-100).'],
            ]
        ),
    ],
];

// =====================================================================
// 03 FAQ
// =====================================================================
$faq = [
    'name' => '03 FAQ',
    'description' => 'Frequently asked questions.',
    'item' => [
        req(
            'List FAQs',
            'GET',
            'faqs/',
            "## List FAQs\nPaginated public FAQ list (bilingual question/answer).\n\n**Auth:** Public (no auth).\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| limit | No | Page size 1-100 (default 10) |\n| page | No | Page number |",
            false,
            [],
            [
                ['key' => 'limit', 'value' => '10', 'description' => 'Number of FAQs per page (1-100).'],
                ['key' => 'page', 'value' => '1', 'description' => 'Page number.'],
            ]
        ),
    ],
];

// =====================================================================
// 04 Volunteer Profile
// =====================================================================
$volunteerProfile = [
    'name' => '04 Volunteer Profile',
    'description' => "The authenticated volunteer's own profile, directory search, QR code, and public UUID verification.",
    'item' => [
        req(
            'Get Volunteer Profile',
            'GET',
            'volunteer-profile/',
            "## Get Volunteer Profile\nReturns the authenticated volunteer's profile (gender, badge, interests).\n\n**Auth required:** Bearer `{{token}}`.",
            true
        ),
        req(
            'Update Volunteer Profile',
            'PUT',
            'volunteer-profile/',
            "## Update Volunteer Profile\nUpdates volunteer profile fields and optionally syncs interests. `PATCH` is also accepted.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| civil_id | Yes | Civil ID |\n| nickname / occupation / experience | No | Profile text |\n| health_concerns | No | `yes` or `no` |\n| is_public / is_verified | No | Booleans as `1`/`0` |\n| gender | No | master_choices.id |\n| email / nationality / dob / birth_year | No | User fields |\n| *_link | No | Social URLs |\n| interest_ids[0..] | No | Interest IDs to sync |",
            true,
            [
                ['key' => 'civil_id', 'value' => '290010100001', 'description' => 'Required civil ID for the volunteer profile update.'],
                ['key' => 'nickname', 'value' => 'ahmed_vol', 'description' => 'Volunteer nickname.'],
                ['key' => 'occupation', 'value' => 'Software Engineer', 'description' => 'Current occupation.'],
                ['key' => 'experience', 'value' => '2 years volunteering in community events', 'description' => 'Free-text experience summary.'],
                ['key' => 'health_concerns', 'value' => 'no', 'description' => 'Health concerns flag: `yes` or `no`.'],
                ['key' => 'is_public', 'value' => '1', 'description' => 'Make profile public (`1`/`0`).'],
                ['key' => 'is_verified', 'value' => '0', 'description' => 'Verified flag (`1`/`0`) — usually managed by system/admin.'],
                ['key' => 'gender', 'value' => '1', 'description' => 'Gender master_choices.id.'],
                ['key' => 'email', 'value' => '{{email}}', 'description' => 'Optional email update (must remain unique).'],
                ['key' => 'nationality', 'value' => 'KW', 'description' => 'Nationality value.'],
                ['key' => 'dob', 'value' => '1995-05-15', 'description' => 'Date of birth `YYYY-MM-DD`.'],
                ['key' => 'birth_year', 'value' => '1995', 'description' => 'Birth year integer.'],
                ['key' => 'instagram_link', 'value' => 'https://instagram.com/example', 'description' => 'Instagram profile URL.'],
                ['key' => 'whatsapp_link', 'value' => 'https://wa.me/96550000001', 'description' => 'WhatsApp link.'],
                ['key' => 'linkedin_link', 'value' => 'https://linkedin.com/in/example', 'description' => 'LinkedIn profile URL.'],
                ['key' => 'facebook_link', 'value' => 'https://facebook.com/example', 'description' => 'Facebook profile URL.'],
                ['key' => 'twitter_link', 'value' => 'https://x.com/example', 'description' => 'X/Twitter profile URL.'],
                ['key' => 'interest_ids[0]', 'value' => '1', 'description' => 'First interest id to sync (from the interests table).'],
                ['key' => 'interest_ids[1]', 'value' => '2', 'description' => 'Second interest id (optional).'],
            ]
        ),
        req(
            'Get Volunteer QR Code',
            'GET',
            'volunteer-profile/qr-code/',
            "## Get Volunteer QR Code\nReturns the QR code URL and manual ID used for attendance scanning/verification.\n\n**Auth required:** Bearer `{{token}}`.",
            true
        ),
        req(
            'List All Volunteers',
            'GET',
            'all-volunteers/',
            "## List All Volunteers\nSearchable/paginated directory of verified, non-banned volunteers.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| search | No | Search nickname/name/email |\n| page | No | Page number |\n| limit | No | Page size 1-100 (default 20) |",
            true,
            [],
            [
                ['key' => 'search', 'value' => '', 'description' => 'Optional search term (nickname, name, email).', 'disabled' => true],
                ...pageLimit(),
            ]
        ),
        req(
            'Verify Volunteer By UUID',
            'GET',
            'verify/{{volunteer_uuid}}/',
            "## Verify Volunteer By UUID\nPublic verification endpoint using the volunteer profile UUID (e.g. scanned from a printed badge/QR code).\n\n**Auth:** Public (no auth).\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| uuid | Volunteer profile UUID (`{{volunteer_uuid}}`) |\n\nReplace `{{volunteer_uuid}}` with a real UUID from a volunteer profile / QR payload.",
            false
        ),
    ],
];

// =====================================================================
// 05 Organization Profile
// =====================================================================
$organizationProfile = [
    'name' => '05 Organization Profile',
    'description' => "The authenticated organization's own profile, supporting documents, and org directory listing.",
    'item' => [
        req(
            'Get Organization Profile',
            'GET',
            'organization-profile/',
            "## Get Organization Profile\nReturns the authenticated organization's profile (sector, organizer type, documents).\n\n**Auth required:** Bearer `{{token}}`. Log in with `{{org_email}}` / `{{password}}` first.",
            true
        ),
        req(
            'Update Organization Profile',
            'PUT',
            'organization-profile/',
            "## Update Organization Profile\nUpdates entity profile fields and social links. `PATCH` is also accepted.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| nickname / company_name | No | Identity |\n| sector / organizer_type | No | master_choices IDs |\n| registration_number / license_number | No | Legal numbers |\n| latitude / longitude | No | Location |\n| nationality | No | On linked user |\n| *_link | No | Social URLs |",
            true,
            [
                ['key' => 'nickname', 'value' => 'fursa_entity', 'description' => 'Organization nickname.'],
                ['key' => 'company_name', 'value' => 'Fursa Demo Entity', 'description' => 'Company / entity display name.'],
                ['key' => 'sector', 'value' => '1', 'description' => 'Sector master_choices.id.'],
                ['key' => 'organizer_type', 'value' => '1', 'description' => 'Organizer type master_choices.id.'],
                ['key' => 'registration_number', 'value' => 'REG-1001', 'description' => 'Registration number.'],
                ['key' => 'license_number', 'value' => 'LIC-2002', 'description' => 'License number.'],
                ['key' => 'latitude', 'value' => '29.3759', 'description' => 'Latitude.'],
                ['key' => 'longitude', 'value' => '47.9774', 'description' => 'Longitude.'],
                ['key' => 'nationality', 'value' => 'KW', 'description' => 'Nationality on the linked user.'],
                ['key' => 'instagram_link', 'value' => 'https://instagram.com/example', 'description' => 'Instagram URL.'],
                ['key' => 'whatsapp_link', 'value' => 'https://wa.me/96550000002', 'description' => 'WhatsApp link.'],
                ['key' => 'linkedin_link', 'value' => 'https://linkedin.com/company/example', 'description' => 'LinkedIn URL.'],
                ['key' => 'facebook_link', 'value' => 'https://facebook.com/example', 'description' => 'Facebook URL.'],
                ['key' => 'twitter_link', 'value' => 'https://x.com/example', 'description' => 'X/Twitter URL.'],
            ]
        ),
        req(
            'Update Organization Documents',
            'PUT',
            'organization-profile/documents/',
            "## Update Organization Documents\nSyncs organization documents: keep IDs listed in `existing_ids[]`, upload new files via `new_documents[]`. Any existing document not listed in `existing_ids` is soft-deleted.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| existing_ids[0..] | No | Document IDs to keep |\n| new_documents[] | No | New files to upload |",
            true,
            [
                ['key' => 'existing_ids[0]', 'value' => '1', 'description' => 'Existing organization_documents.id to keep.'],
                ['key' => 'existing_ids[1]', 'value' => '2', 'description' => 'Another existing document id to keep (optional).', 'disabled' => true],
                ['key' => 'new_documents[]', 'type' => 'file', 'description' => 'New document file to upload (attach file in Postman).'],
            ]
        ),
        req(
            'List Organizations',
            'GET',
            'list-organizations/',
            "## List Organizations\nPaginated list of approved organizations (excludes the current user).\n\n**Auth required:** Bearer `{{token}}`.\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| name | No | Filter by company name / nickname |\n| page | No | Page number |\n| limit | No | Page size 1-100 |",
            true,
            [],
            [
                ['key' => 'name', 'value' => '', 'description' => 'Optional name/nickname filter.', 'disabled' => true],
                ...pageLimit(),
            ]
        ),
    ],
];

// =====================================================================
// 06 Opportunities
// =====================================================================

$oppPublicLists = [
    'name' => 'Public Lists',
    'description' => 'Public discovery endpoints for volunteer and learn & serve opportunities (no create/update here).',
    'item' => [
        req(
            'List Volunteer Opportunities (Public)',
            'GET',
            'list-volunteer-opportunities/',
            "## List Volunteer Opportunities (Public)\nSearchable/filterable/sorted public feed of approved, public volunteer opportunities. Urgent + open opportunities are boosted to the top.\n\n**Auth:** Public (no auth) — pass a bearer token to enable `match_my_interest`.\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| search | No | Matches title (en/ar) |\n| type | No | `master_choices.id` under `filter-type` choice type — only `Volunteer` type passes |\n| start_date / end_date | No | Filter by opportunity date range |\n| min_hours / max_hours | No | Filter by `volunteer_hours_per_day` |\n| tags[] | No | Interest name filters |\n| location | No | Location text filter |\n| gender | No | `master_choices.id` or `all` |\n| min_age / max_age | No | Age range filters |\n| opportunity_nationality | No | `kuwaitis` or `non-kuwaitis` |\n| is_relief / is_urgent / is_supports_disabled | No | Boolean flags |\n| match_my_interest | No | `true` to restrict to the caller's interests (requires auth) |\n| status | No | `upcoming`, `inprogress`, or `completed` |\n| page / limit | No | Pagination |",
            false,
            [],
            [
                ['key' => 'search', 'value' => '', 'description' => 'Search opportunity title (en/ar).', 'disabled' => true],
                ['key' => 'type', 'value' => '', 'description' => 'filter-type master_choices.id — only Volunteer type opportunities are returned.', 'disabled' => true],
                ['key' => 'start_date', 'value' => '', 'description' => 'Only opportunities starting on/after this date (YYYY-MM-DD).', 'disabled' => true],
                ['key' => 'end_date', 'value' => '', 'description' => 'Only opportunities ending on/before this date (YYYY-MM-DD).', 'disabled' => true],
                ['key' => 'min_hours', 'value' => '', 'description' => 'Minimum volunteer_hours_per_day.', 'disabled' => true],
                ['key' => 'max_hours', 'value' => '', 'description' => 'Maximum volunteer_hours_per_day.', 'disabled' => true],
                ['key' => 'tags[]', 'value' => '', 'description' => 'Interest name filter (repeat key for multiple tags).', 'disabled' => true],
                ['key' => 'location', 'value' => '', 'description' => 'Location text filter (en/ar).', 'disabled' => true],
                ['key' => 'gender', 'value' => 'all', 'description' => 'Gender master_choices.id, or `all`.', 'disabled' => true],
                ['key' => 'min_age', 'value' => '', 'description' => 'Minimum eligible age.', 'disabled' => true],
                ['key' => 'max_age', 'value' => '', 'description' => 'Maximum eligible age.', 'disabled' => true],
                ['key' => 'opportunity_nationality', 'value' => '', 'description' => '`kuwaitis` or `non-kuwaitis`.', 'disabled' => true],
                ['key' => 'is_relief', 'value' => '', 'description' => 'Boolean flag for relief opportunities.', 'disabled' => true],
                ['key' => 'is_urgent', 'value' => '', 'description' => 'Boolean flag for urgent opportunities.', 'disabled' => true],
                ['key' => 'is_supports_disabled', 'value' => '', 'description' => 'Boolean flag for disability-supportive opportunities.', 'disabled' => true],
                ['key' => 'match_my_interest', 'value' => '', 'description' => 'true to restrict to the caller\'s interests (requires auth).', 'disabled' => true],
                ['key' => 'status', 'value' => '', 'description' => 'upcoming, inprogress, or completed.', 'disabled' => true],
                ...pageLimit(),
            ]
        ),
        req(
            'Opportunity Details',
            'GET',
            'opportunities/{{opportunity_id}}/details/',
            "## Opportunity Details\nReturns full details for a single volunteer opportunity, including roles and teams. Non-approved opportunities are only visible to their creator.\n\n**Auth:** Public (no auth), pass a bearer token to view your own pending opportunity.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| opportunity_id | `volunteer_opportunities.id` |",
            false
        ),
        req(
            'List All Opportunities (Combined)',
            'GET',
            'list-all-opportunities/',
            "## List All Opportunities (Combined)\nCombines Volunteer, Learn & Serve, and Event listings into a single sorted feed (upcoming → in-progress → completed).\n\n**Auth:** Public (no auth); pass a bearer token or `user_id` to scope `filter_type`.\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| user_id | No | View another user's opportunities (implies only approved/public items) |\n| filter_type | No | `organized`, `volunteer`, or `attendee` |\n| opportunity_type | No | `volunteer`, `learn`, or `event` |\n| search | No | Title search (en/ar) |\n| status | No | opportunity_status / event_status |\n| page / limit | No | Pagination |",
            false,
            [],
            [
                ['key' => 'user_id', 'value' => '', 'description' => 'View opportunities for another user id (implies public/approved-only).', 'disabled' => true],
                ['key' => 'filter_type', 'value' => '', 'description' => 'organized, volunteer, or attendee.', 'disabled' => true],
                ['key' => 'opportunity_type', 'value' => '', 'description' => 'volunteer, learn, or event.', 'disabled' => true],
                ['key' => 'search', 'value' => '', 'description' => 'Title search (en/ar).', 'disabled' => true],
                ['key' => 'status', 'value' => '', 'description' => 'opportunity_status / event_status value.', 'disabled' => true],
                ...pageLimit(),
            ]
        ),
        req(
            'List User Opportunities',
            'GET',
            'list-user-opportunities/',
            "## List User Opportunities\nCombines Volunteer + Learn & Serve opportunities for the caller (or `user_id`), filterable by registered/organized and completion status.\n\n**Auth:** Public (no auth) if `user_id` is supplied; otherwise requires a bearer token.\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| user_id | No | View another user's opportunities |\n| filter_type | No | `registered` or `organized` |\n| opportunity_type | No | `volunteer` or `learn` |\n| search | No | Title search (en/ar) |\n| opportunity_status | No | upcoming/inprogress/completed |",
            true,
            [],
            [
                ['key' => 'user_id', 'value' => '', 'description' => 'View opportunities for another user id.', 'disabled' => true],
                ['key' => 'filter_type', 'value' => '', 'description' => 'registered or organized.', 'disabled' => true],
                ['key' => 'opportunity_type', 'value' => '', 'description' => 'volunteer or learn.', 'disabled' => true],
                ['key' => 'search', 'value' => '', 'description' => 'Title search (en/ar).', 'disabled' => true],
                ['key' => 'opportunity_status', 'value' => '', 'description' => 'upcoming, inprogress, or completed.', 'disabled' => true],
            ]
        ),
        req(
            'List Learn & Serve Opportunities (Public)',
            'GET',
            'learn-serve-opportunities/',
            "## List Learn & Serve Opportunities (Public)\nPublic/approved (or own pending) list of Learn & Serve opportunities.\n\n**Auth:** Public (no auth); pass a bearer token to also see your own pending opportunities.\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| search | No | Title search (en/ar) |\n| type | No | filter-type master_choices.id — excludes Volunteer-type results |\n| in_person / online | No | Filter by `format_id` (IN PERSON / ONLINE) |\n| status | No | opportunity_status |\n| page / limit | No | Pagination |",
            false,
            [],
            [
                ['key' => 'search', 'value' => '', 'description' => 'Title search (en/ar).', 'disabled' => true],
                ['key' => 'type', 'value' => '', 'description' => 'filter-type master_choices.id.', 'disabled' => true],
                ['key' => 'in_person', 'value' => '', 'description' => 'true to restrict to IN PERSON format.', 'disabled' => true],
                ['key' => 'online', 'value' => '', 'description' => 'true to restrict to ONLINE format.', 'disabled' => true],
                ['key' => 'status', 'value' => '', 'description' => 'upcoming, inprogress, or completed.', 'disabled' => true],
                ...pageLimit(),
            ]
        ),
        req(
            'Get Learn & Serve Opportunity',
            'GET',
            'learn-serve-opportunities/{{opportunity_id}}/',
            "## Get Learn & Serve Opportunity\nReturns a single Learn & Serve opportunity with interests, images, and time slots.\n\n**Auth:** Public (no auth) for approved opportunities; pass a bearer token to view your own pending opportunity.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `learn_serve_opportunities.id` (collection var `{{opportunity_id}}`) |",
            false
        ),
    ],
];

$oppVolunteerCrud = [
    'name' => 'Volunteer Opportunities CRUD',
    'description' => 'Create/manage volunteer opportunities you created (organization/organizer account).',
    'item' => [
        req(
            'List My Volunteer Opportunities',
            'GET',
            'volunteer-opportunities/',
            "## List My Volunteer Opportunities\nPaginated list of volunteer opportunities created by the authenticated user.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| page / limit | No | Pagination |",
            true,
            [],
            pageLimit()
        ),
        req(
            'Create Volunteer Opportunity',
            'POST',
            'volunteer-opportunities/',
            "## Create Volunteer Opportunity\nCreates a new volunteer opportunity in `pending` approval status.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| title_en / title_ar | Yes | Bilingual title |\n| description_en / description_ar | Yes | Bilingual description |\n| start_date / end_date | Yes | Opportunity date range |\n| due_date | No | Registration cutoff |\n| participants_needed | Yes | Minimum 1 |\n| from_age / to_age | No | Eligible age range |\n| start_time / end_time | No | Daily time window |\n| latitude / longitude | No | Map location |\n| link | No | External URL |\n| location_en / location_ar | No | Bilingual location text |\n| is_public / is_kuwaitis / is_relief / is_urgent / is_supports_disabled / is_interview_needed | No | Boolean flags |\n| volunteer_hours_per_day | No | Numeric |\n| gender_id | No | master_choices.id |\n| primary_language | No | `en` or `ar` |\n| interest_ids[] | No | Interest IDs to attach |",
            true,
            [
                ['key' => 'title_en', 'value' => 'Beach Cleanup Day', 'description' => 'English title.'],
                ['key' => 'title_ar', 'value' => 'يوم تنظيف الشاطئ', 'description' => 'Arabic title.'],
                ['key' => 'description_en', 'value' => 'Join us for a community beach cleanup.', 'description' => 'English description.'],
                ['key' => 'description_ar', 'value' => 'انضم إلينا لتنظيف الشاطئ المجتمعي.', 'description' => 'Arabic description.'],
                ['key' => 'start_date', 'value' => '2026-08-01', 'description' => 'Opportunity start date (YYYY-MM-DD).'],
                ['key' => 'end_date', 'value' => '2026-08-01', 'description' => 'Opportunity end date (YYYY-MM-DD).'],
                ['key' => 'due_date', 'value' => '2026-07-30', 'description' => 'Registration cutoff date.'],
                ['key' => 'participants_needed', 'value' => '20', 'description' => 'Number of volunteer slots (min 1).'],
                ['key' => 'from_age', 'value' => '16', 'description' => 'Minimum eligible age.'],
                ['key' => 'to_age', 'value' => '60', 'description' => 'Maximum eligible age.'],
                ['key' => 'start_time', 'value' => '08:00', 'description' => 'Daily start time.'],
                ['key' => 'end_time', 'value' => '12:00', 'description' => 'Daily end time.'],
                ['key' => 'latitude', 'value' => '29.3759', 'description' => 'Location latitude.'],
                ['key' => 'longitude', 'value' => '47.9774', 'description' => 'Location longitude.'],
                ['key' => 'link', 'value' => '', 'description' => 'Optional external info URL.', 'disabled' => true],
                ['key' => 'location_en', 'value' => 'Kuwait City Beach', 'description' => 'English location text.'],
                ['key' => 'location_ar', 'value' => 'شاطئ مدينة الكويت', 'description' => 'Arabic location text.'],
                ['key' => 'is_public', 'value' => '1', 'description' => 'Visible in public listing (1/0).'],
                ['key' => 'is_kuwaitis', 'value' => '0', 'description' => 'Restricted to Kuwaiti nationals (1/0).'],
                ['key' => 'is_relief', 'value' => '0', 'description' => 'Marks this as a relief opportunity (1/0).'],
                ['key' => 'is_urgent', 'value' => '0', 'description' => 'Boosts visibility as urgent (1/0).'],
                ['key' => 'is_supports_disabled', 'value' => '0', 'description' => 'Accessible for people with disabilities (1/0).'],
                ['key' => 'is_interview_needed', 'value' => '0', 'description' => 'Requires interview before acceptance (1/0).'],
                ['key' => 'volunteer_hours_per_day', 'value' => '4', 'description' => 'Volunteer hours credited per day.'],
                ['key' => 'gender_id', 'value' => '', 'description' => 'Gender restriction master_choices.id.', 'disabled' => true],
                ['key' => 'primary_language', 'value' => 'en', 'description' => 'Primary display language: en or ar.'],
                ['key' => 'interest_ids[0]', 'value' => '1', 'description' => 'First interest id to attach.'],
                ['key' => 'interest_ids[1]', 'value' => '2', 'description' => 'Second interest id to attach (optional).'],
            ]
        ),
        req(
            'Get My Volunteer Opportunity',
            'GET',
            'volunteer-opportunities/{{opportunity_id}}/',
            "## Get My Volunteer Opportunity\nReturns a volunteer opportunity you created, including roles and teams.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `volunteer_opportunities.id` (collection var `{{opportunity_id}}`) |",
            true
        ),
        req(
            'Update Volunteer Opportunity',
            'PUT',
            'volunteer-opportunities/{{opportunity_id}}/',
            "## Update Volunteer Opportunity\nPartially or fully updates a volunteer opportunity you created. `PATCH` is also accepted; all fields are optional (`sometimes`).\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `volunteer_opportunities.id` (collection var `{{opportunity_id}}`) |\n\n### Form-data fields\nSame fields as Create (all optional on update) — see Create Volunteer Opportunity for full field docs.",
            true,
            [
                ['key' => 'title_en', 'value' => 'Beach Cleanup Day (Updated)', 'description' => 'English title.'],
                ['key' => 'participants_needed', 'value' => '25', 'description' => 'Updated number of volunteer slots.'],
                ['key' => 'is_urgent', 'value' => '1', 'description' => 'Mark as urgent (1/0).'],
            ]
        ),
        req(
            'Update Volunteer Opportunity Images',
            'PATCH',
            'volunteer-opportunities/{{opportunity_id}}/update_images/',
            "## Update Volunteer Opportunity Images\nUploads new opportunity images (after-completion gallery) and/or re-attaches existing images by id. Only the opportunity creator may call this.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `volunteer_opportunities.id` (collection var `{{opportunity_id}}`) |\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| new_opportunity_images_0..n | No | New image files (any key starting with `opportunity_images_` or `new_opportunity_images_`) |\n| existing_image_ids[] | No | `opportunity_images.id` values to re-attach to this opportunity |",
            true,
            [
                ['key' => 'new_opportunity_images_0', 'type' => 'file', 'description' => 'New opportunity image file to upload.'],
                ['key' => 'existing_image_ids[0]', 'value' => '', 'description' => 'Existing opportunity_images.id to keep/re-attach.', 'disabled' => true],
            ]
        ),
        req(
            'Delete Volunteer Opportunity',
            'DELETE',
            'volunteer-opportunities/{{opportunity_id}}/',
            "## Delete Volunteer Opportunity\nSoft-deletes a volunteer opportunity you created (sets `is_deleted`/`deleted_at`).\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `volunteer_opportunities.id` (collection var `{{opportunity_id}}`) |",
            true
        ),
    ],
];

$oppLearnServeCrud = [
    'name' => 'Learn & Serve CRUD',
    'description' => 'Create/manage Learn & Serve opportunities you created.',
    'item' => [
        req(
            'My Learn & Serve Opportunities',
            'GET',
            'learn-serve-opportunities/my_opportunities/',
            "## My Learn & Serve Opportunities\nPaginated list of Learn & Serve opportunities created by the authenticated user, with registrations and sponsor images eager-loaded.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| page / limit | No | Pagination |",
            true,
            [],
            pageLimit()
        ),
        req(
            'Create Learn & Serve Opportunity',
            'POST',
            'learn-serve-opportunities/',
            "## Create Learn & Serve Opportunity\nCreates a new Learn & Serve opportunity in `pending` approval status.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| title_en / title_ar | Yes | Bilingual title |\n| description_en / description_ar | Yes | Bilingual description |\n| start_date / end_date | Yes | Date range |\n| due_date | No | Registration cutoff |\n| participants_needed | Yes | Minimum 1 |\n| from_age / to_age | No | Eligible age range |\n| start_time / end_time | No | Daily time window |\n| latitude / longitude | No | Map location |\n| link | No | External URL |\n| location_en / location_ar | No | Bilingual location text |\n| is_kuwaitis | No | Boolean flag |\n| learning_type_id / gender_id / format_id / certificate_type_id | No | master_choices IDs |\n| primary_language | No | `en` or `ar` |\n| interest_ids[] | No | Interest IDs to attach |",
            true,
            [
                ['key' => 'title_en', 'value' => 'Intro to First Aid Workshop', 'description' => 'English title.'],
                ['key' => 'title_ar', 'value' => 'ورشة مقدمة الإسعافات الأولية', 'description' => 'Arabic title.'],
                ['key' => 'description_en', 'value' => 'Learn essential first-aid skills while volunteering.', 'description' => 'English description.'],
                ['key' => 'description_ar', 'value' => 'تعلم مهارات الإسعافات الأولية الأساسية أثناء التطوع.', 'description' => 'Arabic description.'],
                ['key' => 'start_date', 'value' => '2026-08-10', 'description' => 'Start date (YYYY-MM-DD).'],
                ['key' => 'end_date', 'value' => '2026-08-10', 'description' => 'End date (YYYY-MM-DD).'],
                ['key' => 'due_date', 'value' => '2026-08-08', 'description' => 'Registration cutoff date.'],
                ['key' => 'participants_needed', 'value' => '15', 'description' => 'Number of seats (min 1).'],
                ['key' => 'from_age', 'value' => '14', 'description' => 'Minimum eligible age.'],
                ['key' => 'to_age', 'value' => '', 'description' => 'Maximum eligible age (leave blank for no cap).', 'disabled' => true],
                ['key' => 'start_time', 'value' => '10:00', 'description' => 'Daily start time.'],
                ['key' => 'end_time', 'value' => '13:00', 'description' => 'Daily end time.'],
                ['key' => 'latitude', 'value' => '29.3759', 'description' => 'Location latitude.'],
                ['key' => 'longitude', 'value' => '47.9774', 'description' => 'Location longitude.'],
                ['key' => 'location_en', 'value' => 'Fursa Training Center', 'description' => 'English location text.'],
                ['key' => 'location_ar', 'value' => 'مركز تدريب فرصة', 'description' => 'Arabic location text.'],
                ['key' => 'is_kuwaitis', 'value' => '0', 'description' => 'Restricted to Kuwaiti nationals (1/0).'],
                ['key' => 'learning_type_id', 'value' => '', 'description' => 'Learning type master_choices.id.', 'disabled' => true],
                ['key' => 'gender_id', 'value' => '', 'description' => 'Gender restriction master_choices.id.', 'disabled' => true],
                ['key' => 'format_id', 'value' => '', 'description' => 'Format master_choices.id (IN PERSON / ONLINE).', 'disabled' => true],
                ['key' => 'certificate_type_id', 'value' => '', 'description' => 'Certificate type master_choices.id.', 'disabled' => true],
                ['key' => 'primary_language', 'value' => 'en', 'description' => 'Primary display language: en or ar.'],
                ['key' => 'interest_ids[0]', 'value' => '1', 'description' => 'First interest id to attach.'],
            ]
        ),
        req(
            'Update Learn & Serve Opportunity',
            'PUT',
            'learn-serve-opportunities/{{opportunity_id}}/',
            "## Update Learn & Serve Opportunity\nPartially or fully updates a Learn & Serve opportunity you created. `PATCH` is also accepted; all fields are optional on update.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `learn_serve_opportunities.id` (collection var `{{opportunity_id}}`) |\n\n### Form-data fields\nSame fields as Create (all optional) — see Create Learn & Serve Opportunity for full field docs.",
            true,
            [
                ['key' => 'title_en', 'value' => 'Intro to First Aid Workshop (Updated)', 'description' => 'English title.'],
                ['key' => 'participants_needed', 'value' => '20', 'description' => 'Updated number of seats.'],
            ]
        ),
        req(
            'Update Learn & Serve Opportunity Images',
            'PATCH',
            'learn-serve-opportunities/{{opportunity_id}}/update_images/',
            "## Update Learn & Serve Opportunity Images\nUploads new images and/or re-attaches existing images by id. Only the opportunity creator may call this.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `learn_serve_opportunities.id` (collection var `{{opportunity_id}}`) |\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| new_opportunity_images_0..n | No | New image files |\n| existing_image_ids[] | No | `opportunity_images.id` values to re-attach |",
            true,
            [
                ['key' => 'new_opportunity_images_0', 'type' => 'file', 'description' => 'New opportunity image file to upload.'],
                ['key' => 'existing_image_ids[0]', 'value' => '', 'description' => 'Existing opportunity_images.id to keep/re-attach.', 'disabled' => true],
            ]
        ),
        req(
            'Delete Learn & Serve Opportunity',
            'DELETE',
            'learn-serve-opportunities/{{opportunity_id}}/',
            "## Delete Learn & Serve Opportunity\nSoft-deletes a Learn & Serve opportunity you created.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `learn_serve_opportunities.id` (collection var `{{opportunity_id}}`) |",
            true
        ),
    ],
];

$oppRegistrationsVolunteer = [
    'name' => 'Registrations (Volunteer)',
    'description' => 'Volunteer signs up for a Volunteer Opportunity; organizers manage assignments and direct registration.',
    'item' => [
        req(
            'List Registrations',
            'GET',
            'volunteer-opportunity-registrations/',
            "## List Registrations\nPaginated registrations, optionally filtered by opportunity/role/team/search.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| opportunity_id | No | Filter by opportunity |\n| role_id[] | No | Filter by assigned role id(s) |\n| team_id[] | No | Filter by assigned team id(s) |\n| search | No | Search registrant email/first/last name |\n| page / limit | No | Pagination |",
            true,
            [],
            [
                ['key' => 'opportunity_id', 'value' => '{{opportunity_id}}', 'description' => 'Filter registrations by volunteer_opportunities.id.'],
                ['key' => 'role_id[]', 'value' => '', 'description' => 'Filter by assigned role id (repeatable).', 'disabled' => true],
                ['key' => 'team_id[]', 'value' => '', 'description' => 'Filter by assigned team id (repeatable).', 'disabled' => true],
                ['key' => 'search', 'value' => '', 'description' => 'Search by registrant email/first/last name.', 'disabled' => true],
                ...pageLimit(),
            ]
        ),
        req(
            'Register For Opportunity',
            'POST',
            'volunteer-opportunity-registrations/',
            "## Register For Opportunity\nRegisters the authenticated volunteer for a volunteer opportunity, optionally into a specific role/team. Validates age eligibility and role slot availability.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| opportunity_id | Yes | `volunteer_opportunities.id` |\n| role_id | No | `volunteer_opportunity_roles.id` belonging to the opportunity |\n| team_id | No | `volunteer_opportunity_teams.id` belonging to the opportunity |\n| organization_id | No | Optional organization context |",
            true,
            [
                ['key' => 'opportunity_id', 'value' => '{{opportunity_id}}', 'description' => 'volunteer_opportunities.id to register for.'],
                ['key' => 'role_id', 'value' => '{{role_id}}', 'description' => 'Optional role id to assign into (must belong to the opportunity).', 'disabled' => true],
                ['key' => 'team_id', 'value' => '{{team_id}}', 'description' => 'Optional team id to assign into (must belong to the opportunity).', 'disabled' => true],
                ['key' => 'organization_id', 'value' => '', 'description' => 'Optional organization context id.', 'disabled' => true],
            ]
        ),
        req(
            'Update Assignment',
            'PATCH',
            'volunteer-opportunity-registrations/',
            "## Update Assignment\nUpdates the role/team assignment for an existing registration. Only the opportunity creator may call this.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| registration | Yes | `volunteer_opportunity_registrations.id` |\n| role | No | New `volunteer_opportunity_roles.id` |\n| team | No | New `volunteer_opportunity_teams.id` |",
            true,
            [
                ['key' => 'registration', 'value' => '{{registration_id}}', 'description' => 'Registration id to update the assignment for.'],
                ['key' => 'role', 'value' => '{{role_id}}', 'description' => 'New role id to assign (must belong to the same opportunity).', 'disabled' => true],
                ['key' => 'team', 'value' => '{{team_id}}', 'description' => 'New team id to assign (must belong to the same opportunity).', 'disabled' => true],
            ]
        ),
        req(
            'Direct Register Volunteers',
            'POST',
            'volunteer-opportunity-registrations/direct-register/',
            "## Direct Register Volunteers\nLets the opportunity creator directly register a batch of users (bypassing self-signup), e.g. from the available-volunteers list.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| opportunity_id | Yes | `volunteer_opportunities.id` you created |\n| user_ids[] | Yes | One or more `users.id` to register |",
            true,
            [
                ['key' => 'opportunity_id', 'value' => '{{opportunity_id}}', 'description' => 'volunteer_opportunities.id you created.'],
                ['key' => 'user_ids[0]', 'value' => '{{user_id}}', 'description' => 'First user id to directly register.'],
                ['key' => 'user_ids[1]', 'value' => '', 'description' => 'Second user id to directly register (optional).', 'disabled' => true],
            ]
        ),
        req(
            'Direct Unregister Volunteers',
            'POST',
            'volunteer-opportunity-registrations/direct-unregister/',
            "## Direct Unregister Volunteers\nLets the opportunity creator remove a batch of registered users at once.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| opportunity_id | Yes | `volunteer_opportunities.id` you created |\n| user_ids[] | One of | Users to remove |\n| user_id | One of | Single user shortcut (used if `user_ids[]` is empty) |",
            true,
            [
                ['key' => 'opportunity_id', 'value' => '{{opportunity_id}}', 'description' => 'volunteer_opportunities.id you created.'],
                ['key' => 'user_ids[0]', 'value' => '{{user_id}}', 'description' => 'User id to remove (repeatable).'],
                ['key' => 'user_id', 'value' => '', 'description' => 'Single user id shortcut (used only if user_ids[] is empty).', 'disabled' => true],
            ]
        ),
        req(
            'Get Registration',
            'GET',
            'volunteer-opportunity-registrations/{{registration_id}}/',
            "## Get Registration\nReturns a single registration with user and role/team assignment.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `volunteer_opportunity_registrations.id` (collection var `{{registration_id}}`) |",
            true
        ),
        req(
            'Update Registration Status',
            'PUT',
            'volunteer-opportunity-registrations/{{registration_id}}/',
            "## Update Registration Status\nUpdates the status of a registration. `PATCH` is also accepted.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `volunteer_opportunity_registrations.id` (collection var `{{registration_id}}`) |\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| status | No | New status string (e.g. `approved`, `rejected`) |",
            true,
            [
                ['key' => 'status', 'value' => 'approved', 'description' => 'New registration status.'],
            ]
        ),
        req(
            'Delete Registration',
            'DELETE',
            'volunteer-opportunity-registrations/{{registration_id}}/',
            "## Delete Registration\nSoft-deletes a registration and its assignment (admin/organizer cleanup).\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `volunteer_opportunity_registrations.id` (collection var `{{registration_id}}`) |",
            true
        ),
        req(
            'Unregister From Volunteer Opportunity',
            'POST',
            'volunteer-opportunities/{{opportunity_id}}/unregister/',
            "## Unregister From Volunteer Opportunity\nCancels the authenticated user's own registration for a volunteer opportunity. Both `POST` and `DELETE` are accepted.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| opportunity_id | `volunteer_opportunities.id` (collection var `{{opportunity_id}}`) |",
            true
        ),
    ],
];

$oppRegistrationsLearnServe = [
    'name' => 'Registrations (Learn & Serve)',
    'description' => 'Volunteer signs up for a Learn & Serve opportunity/time slot; organizers manage attendance.',
    'item' => [
        req(
            'Register For Learn & Serve',
            'POST',
            'learn-serve-opportunity-registrations/',
            "## Register For Learn & Serve\nRegisters the authenticated user for a Learn & Serve opportunity, optionally into a specific time slot.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| opportunity_id | Yes | `learn_serve_opportunities.id` |\n| time_slot_id | No | `learn_serve_opportunity_time_slots.id` belonging to the opportunity |",
            true,
            [
                ['key' => 'opportunity_id', 'value' => '{{opportunity_id}}', 'description' => 'learn_serve_opportunities.id to register for.'],
                ['key' => 'time_slot_id', 'value' => '{{time_slot_id}}', 'description' => 'Optional time slot id to register into.', 'disabled' => true],
            ]
        ),
        req(
            'List Registrations By Opportunity',
            'GET',
            'learn-serve-opportunities/{{opportunity_id}}/registrations/',
            "## List Registrations By Opportunity\nPaginated registrations for a Learn & Serve opportunity you created.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| opportunity_id | `learn_serve_opportunities.id` (collection var `{{opportunity_id}}`) |\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| search | No | Search registrant first/last name/email |\n| page / limit | No | Pagination |",
            true,
            [],
            [
                ['key' => 'search', 'value' => '', 'description' => 'Search registrant first/last name/email.', 'disabled' => true],
                ...pageLimit(),
            ]
        ),
        req(
            'Update Attendance (Learn & Serve)',
            'PATCH',
            'learn-serve-opportunities/{{opportunity_id}}/update-attendance/',
            "## Update Attendance (Learn & Serve)\nMarks attendance for specific registrations, or for all registrations at once. Triggers volunteer statistics sync.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| opportunity_id | `learn_serve_opportunities.id` (collection var `{{opportunity_id}}`) |\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| is_attended | Yes | `true`/`false` |\n| mark_all | No | `true` to update every registration for this opportunity |\n| registration_ids[] | Conditional | Required unless `mark_all=true` |",
            true,
            [
                ['key' => 'is_attended', 'value' => '1', 'description' => 'Attendance flag to set (true/false).'],
                ['key' => 'mark_all', 'value' => '0', 'description' => 'true to update every registration for this opportunity.'],
                ['key' => 'registration_ids[0]', 'value' => '{{registration_id}}', 'description' => 'Registration id to mark (required unless mark_all=true).'],
            ]
        ),
        req(
            'Unregister From Learn & Serve',
            'POST',
            'learn-serve-opportunities/{{opportunity_id}}/unregister/',
            "## Unregister From Learn & Serve\nCancels the authenticated user's own registration. Both `POST` and `DELETE` are accepted.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| opportunity_id | `learn_serve_opportunities.id` (collection var `{{opportunity_id}}`) |",
            true
        ),
        req(
            'Unregister User (Organizer)',
            'DELETE',
            'learnserve/{{opportunity_id}}/unregister/{{user_id}}/',
            "## Unregister User (Organizer)\nLets the opportunity creator remove a specific user's registration.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| opportunity_id | `learn_serve_opportunities.id` (collection var `{{opportunity_id}}`) |\n| user_id | `users.id` to remove (collection var `{{user_id}}`) |",
            true
        ),
    ],
];

$oppRoles = [
    'name' => 'Roles',
    'description' => 'Named roles with capacity limits for a volunteer opportunity.',
    'item' => [
        req(
            'List Roles',
            'GET',
            'volunteer-opportunity-roles/',
            "## List Roles\nPaginated roles, optionally filtered by opportunity.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| opportunity_id | No | Filter by opportunity |\n| page / limit | No | Pagination |",
            true,
            [],
            [
                ['key' => 'opportunity_id', 'value' => '{{opportunity_id}}', 'description' => 'Filter roles by volunteer_opportunities.id.'],
                ...pageLimit(),
            ]
        ),
        req(
            'Get Role',
            'GET',
            'volunteer-opportunity-roles/{{role_id}}/',
            "## Get Role\nReturns a single opportunity role.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `volunteer_opportunity_roles.id` (collection var `{{role_id}}`) |",
            true
        ),
        req(
            'Create Role',
            'POST',
            'volunteer-opportunity-roles/',
            "## Create Role\nAdds a named role with a participant capacity to a volunteer opportunity you created.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| opportunity_id | Yes | `volunteer_opportunities.id` you created |\n| role_name_en / role_name_ar | Yes | Bilingual role name (max 100) |\n| instructions_en / instructions_ar | No | Bilingual instructions |\n| participants_needed | Yes | Minimum 1 |",
            true,
            [
                ['key' => 'opportunity_id', 'value' => '{{opportunity_id}}', 'description' => 'volunteer_opportunities.id you created.'],
                ['key' => 'role_name_en', 'value' => 'Registration Desk', 'description' => 'English role name (max 100 chars).'],
                ['key' => 'role_name_ar', 'value' => 'مكتب التسجيل', 'description' => 'Arabic role name (max 100 chars).'],
                ['key' => 'instructions_en', 'value' => 'Greet volunteers and check them in.', 'description' => 'English instructions for this role.'],
                ['key' => 'instructions_ar', 'value' => 'استقبال المتطوعين وتسجيل حضورهم.', 'description' => 'Arabic instructions for this role.'],
                ['key' => 'participants_needed', 'value' => '3', 'description' => 'Number of volunteers needed for this role (min 1).'],
            ]
        ),
        req(
            'Update Role',
            'PUT',
            'volunteer-opportunity-roles/{{role_id}}/',
            "## Update Role\nUpdates a role you created. `PATCH` is also accepted.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `volunteer_opportunity_roles.id` (collection var `{{role_id}}`) |\n\n### Form-data fields\nSame fields as Create Role (all optional).",
            true,
            [
                ['key' => 'role_name_en', 'value' => 'Registration Desk (Lead)', 'description' => 'Updated English role name.'],
                ['key' => 'participants_needed', 'value' => '4', 'description' => 'Updated capacity.'],
            ]
        ),
        req(
            'Delete Role',
            'DELETE',
            'volunteer-opportunity-roles/{{role_id}}/',
            "## Delete Role\nSoft-deletes a single role you created.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `volunteer_opportunity_roles.id` (collection var `{{role_id}}`) |",
            true
        ),
        req(
            'Delete All Roles',
            'DELETE',
            'delete-roles/{{opportunity_id}}/',
            "## Delete All Roles\nDeletes every role for an opportunity you created. Fails with 400 if any volunteers are already assigned to a role.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| opportunity_id | `volunteer_opportunities.id` (collection var `{{opportunity_id}}`) |",
            true
        ),
    ],
];

$oppTeams = [
    'name' => 'Teams',
    'description' => 'Named teams for a volunteer opportunity (grouping without capacity tracking).',
    'item' => [
        req(
            'List Teams',
            'GET',
            'volunteer-opportunity-teams/',
            "## List Teams\nPaginated teams, optionally filtered by opportunity.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| opportunity_id | No | Filter by opportunity |\n| page / limit | No | Pagination |",
            true,
            [],
            [
                ['key' => 'opportunity_id', 'value' => '{{opportunity_id}}', 'description' => 'Filter teams by volunteer_opportunities.id.'],
                ...pageLimit(),
            ]
        ),
        req(
            'Get Team',
            'GET',
            'volunteer-opportunity-teams/{{team_id}}/',
            "## Get Team\nReturns a single opportunity team.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `volunteer_opportunity_teams.id` (collection var `{{team_id}}`) |",
            true
        ),
        req(
            'Create Team',
            'POST',
            'volunteer-opportunity-teams/',
            "## Create Team\nAdds a named team to a volunteer opportunity you created.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| opportunity_id | Yes | `volunteer_opportunities.id` you created |\n| team_name_en / team_name_ar | Yes | Bilingual team name |",
            true,
            [
                ['key' => 'opportunity_id', 'value' => '{{opportunity_id}}', 'description' => 'volunteer_opportunities.id you created.'],
                ['key' => 'team_name_en', 'value' => 'Logistics Team', 'description' => 'English team name.'],
                ['key' => 'team_name_ar', 'value' => 'فريق اللوجستيات', 'description' => 'Arabic team name.'],
            ]
        ),
        req(
            'Update Team',
            'PUT',
            'volunteer-opportunity-teams/{{team_id}}/',
            "## Update Team\nUpdates a team you created. `PATCH` is also accepted.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `volunteer_opportunity_teams.id` (collection var `{{team_id}}`) |\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| team_name_en / team_name_ar | No | Updated bilingual team name |",
            true,
            [
                ['key' => 'team_name_en', 'value' => 'Logistics Team (Updated)', 'description' => 'Updated English team name.'],
            ]
        ),
        req(
            'Delete Team',
            'DELETE',
            'volunteer-opportunity-teams/{{team_id}}/',
            "## Delete Team\nSoft-deletes a team you created.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `volunteer_opportunity_teams.id` (collection var `{{team_id}}`) |",
            true
        ),
    ],
];

$oppTimeSlots = [
    'name' => 'Time Slots',
    'description' => 'Date/time slots with a participant cap for Learn & Serve opportunities.',
    'item' => [
        req(
            'List Time Slots',
            'GET',
            'time-slots/',
            "## List Time Slots\nReturns time slots for a Learn & Serve opportunity that still have remaining capacity, plus overall remaining participants.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| opportunity_id | Yes | `learn_serve_opportunities.id` |",
            true,
            [],
            [
                ['key' => 'opportunity_id', 'value' => '{{opportunity_id}}', 'description' => 'learn_serve_opportunities.id (required).'],
            ]
        ),
        req(
            'Get Time Slot',
            'GET',
            'time-slots/{{time_slot_id}}/',
            "## Get Time Slot\nReturns a single Learn & Serve time slot with its assignment count.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `learn_serve_opportunity_time_slots.id` (collection var `{{time_slot_id}}`) |",
            true
        ),
        req(
            'Create Time Slot',
            'POST',
            'time-slots/',
            "## Create Time Slot\nAdds a date/time slot with a participant cap to a Learn & Serve opportunity you created.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| opportunity_id | Yes | `learn_serve_opportunities.id` you created |\n| date | Yes | Slot date `YYYY-MM-DD` |\n| start_time / end_time | Yes | Time window |\n| participants_needed | Yes | Minimum 1 |",
            true,
            [
                ['key' => 'opportunity_id', 'value' => '{{opportunity_id}}', 'description' => 'learn_serve_opportunities.id you created.'],
                ['key' => 'date', 'value' => '2026-08-10', 'description' => 'Slot date (YYYY-MM-DD).'],
                ['key' => 'start_time', 'value' => '10:00', 'description' => 'Slot start time.'],
                ['key' => 'end_time', 'value' => '12:00', 'description' => 'Slot end time.'],
                ['key' => 'participants_needed', 'value' => '10', 'description' => 'Capacity for this slot (min 1).'],
            ]
        ),
        req(
            'Update Time Slot',
            'PUT',
            'time-slots/{{time_slot_id}}/',
            "## Update Time Slot\nUpdates a time slot you created. `PATCH` is also accepted.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `learn_serve_opportunity_time_slots.id` (collection var `{{time_slot_id}}`) |\n\n### Form-data fields\nSame fields as Create (all optional).",
            true,
            [
                ['key' => 'participants_needed', 'value' => '12', 'description' => 'Updated capacity for this slot.'],
            ]
        ),
        req(
            'Delete Time Slot',
            'DELETE',
            'time-slots/{{time_slot_id}}/',
            "## Delete Time Slot\nSoft-deletes a single time slot you created.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `learn_serve_opportunity_time_slots.id` (collection var `{{time_slot_id}}`) |",
            true
        ),
        req(
            'Delete All Time Slots',
            'DELETE',
            'delete-time-slots/{{opportunity_id}}/',
            "## Delete All Time Slots\nDeletes every time slot for an opportunity you created. Fails with 400 if volunteers are already registered in any slot.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| opportunity_id | `learn_serve_opportunities.id` (collection var `{{opportunity_id}}`) |",
            true
        ),
    ],
];

$oppAttendanceScan = [
    'name' => 'Attendance & Scan Permissions',
    'description' => 'QR-based attendance scanning/history and delegated scan permissions for volunteer opportunities/events.',
    'item' => [
        req(
            'Scan Attendance',
            'POST',
            'volunteer-attendance/scan/',
            "## Scan Attendance\nRecords attendance for one or more volunteers by scanning their QR (UUID). Provide exactly one of `opportunity_id`/`event_id`, and one of `volunteer_uuid`/`volunteer_ids[]`.\n\n**Auth required:** Bearer `{{token}}` — caller must be the opportunity creator or have an active scan permission.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| opportunity_id | One of | `volunteer_opportunities.id` (event attendance is not yet implemented) |\n| event_id | One of | `events.id` (currently returns 501 Not Implemented) |\n| volunteer_uuid | One of | Single volunteer profile UUID scanned |\n| volunteer_ids[] | One of | Batch of volunteer profile UUIDs |\n| attendance_date | No | Defaults to today; must fall within the opportunity's date range |",
            true,
            [
                ['key' => 'opportunity_id', 'value' => '{{opportunity_id}}', 'description' => 'volunteer_opportunities.id to record attendance for.'],
                ['key' => 'event_id', 'value' => '', 'description' => 'events.id (event attendance not yet implemented — returns 501).', 'disabled' => true],
                ['key' => 'volunteer_uuid', 'value' => '{{volunteer_uuid}}', 'description' => 'Single volunteer profile UUID scanned from the QR code.'],
                ['key' => 'volunteer_ids[0]', 'value' => '', 'description' => 'Batch of volunteer profile UUIDs (alternative to volunteer_uuid).', 'disabled' => true],
                ['key' => 'attendance_date', 'value' => '', 'description' => 'Attendance date YYYY-MM-DD (defaults to today).', 'disabled' => true],
            ]
        ),
        req(
            'Attendance History',
            'GET',
            'volunteer-attendance/history/',
            "## Attendance History\nPaginated attendance records with a `total_hours` summary. Non-admin/staff users only see records for opportunities they created or attended.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| opportunity_id | No | Filter by opportunity |\n| volunteer_uuid | No | Filter by volunteer profile UUID |\n| registration_id | No | Filter by registration |\n| start_date / end_date | No | Filter by attended_date range |\n| page / limit | No | Pagination |",
            true,
            [],
            [
                ['key' => 'opportunity_id', 'value' => '{{opportunity_id}}', 'description' => 'Filter by volunteer_opportunities.id.', 'disabled' => true],
                ['key' => 'volunteer_uuid', 'value' => '{{volunteer_uuid}}', 'description' => 'Filter by volunteer profile UUID.', 'disabled' => true],
                ['key' => 'registration_id', 'value' => '{{registration_id}}', 'description' => 'Filter by registration id.', 'disabled' => true],
                ['key' => 'start_date', 'value' => '', 'description' => 'Only records attended on/after this date.', 'disabled' => true],
                ['key' => 'end_date', 'value' => '', 'description' => 'Only records attended on/before this date.', 'disabled' => true],
                ...pageLimit(),
            ]
        ),
        req(
            'Bulk Update Scan Permissions',
            'POST',
            'scan-permissions/bulk-update/',
            "## Bulk Update Scan Permissions\nGrants/revokes attendance-scanning permission for a list of users on an opportunity or event. Only the creator may call this.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| opportunity_id | One of | `volunteer_opportunities.id` |\n| event_id | One of | `events.id` |\n| permissions[0][user_id] | Yes | User to grant/revoke |\n| permissions[0][is_allowed] | Yes | `true`/`false` |",
            true,
            [
                ['key' => 'opportunity_id', 'value' => '{{opportunity_id}}', 'description' => 'volunteer_opportunities.id (provide this or event_id).'],
                ['key' => 'event_id', 'value' => '', 'description' => 'events.id (alternative to opportunity_id).', 'disabled' => true],
                ['key' => 'permissions[0][user_id]', 'value' => '{{user_id}}', 'description' => 'User id to grant/revoke scan permission for.'],
                ['key' => 'permissions[0][is_allowed]', 'value' => '1', 'description' => 'true to grant, false to revoke.'],
            ]
        ),
        req(
            'List Scan Permissions',
            'GET',
            'scan-permissions/list/',
            "## List Scan Permissions\nLists users currently allowed to scan attendance for an opportunity or event.\n\n**Auth required:** Bearer `{{token}}` — caller must be the creator.\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| opportunity_id | One of | `volunteer_opportunities.id` |\n| event_id | One of | `events.id` |\n| search | No | Search by user email/first/last name |",
            true,
            [],
            [
                ['key' => 'opportunity_id', 'value' => '{{opportunity_id}}', 'description' => 'volunteer_opportunities.id (provide this or event_id).'],
                ['key' => 'event_id', 'value' => '', 'description' => 'events.id (alternative to opportunity_id).', 'disabled' => true],
                ['key' => 'search', 'value' => '', 'description' => 'Search by user email/first/last name.', 'disabled' => true],
            ]
        ),
    ],
];

$oppFeedbackMedia = [
    'name' => 'Feedback & Media',
    'description' => 'Ratings/comments on Learn & Serve opportunities, plus image/certificate downloads.',
    'item' => [
        req(
            'List Opportunity Feedbacks',
            'GET',
            'opportunity-feedbacks/',
            "## List Opportunity Feedbacks\nReturns all feedback entries, optionally filtered by opportunity.\n\n**Auth:** Public (no auth).\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| opportunity_id | No | Filter by `learn_serve_opportunities.id` |",
            false,
            [],
            [
                ['key' => 'opportunity_id', 'value' => '{{opportunity_id}}', 'description' => 'Filter feedback by learn_serve_opportunities.id.', 'disabled' => true],
            ]
        ),
        req(
            'Get Opportunity Feedback',
            'GET',
            'opportunity-feedbacks/{{feedback_id}}/',
            "## Get Opportunity Feedback\nReturns a single feedback entry with its likes.\n\n**Auth:** Public (no auth).\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `opportunity_feedbacks.id` (collection var `{{feedback_id}}`) |",
            false
        ),
        req(
            'Create Opportunity Feedback',
            'POST',
            'opportunity-feedbacks/',
            "## Create Opportunity Feedback\nSubmits a rating/comment for a Learn & Serve opportunity.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| learn_serve_opportunity_id | Yes | `learn_serve_opportunities.id` |\n| rating | Yes | Integer 1-5 |\n| comment_en / comment_ar | No | Bilingual comment |\n| primary_language | No | `en` or `ar` |",
            true,
            [
                ['key' => 'learn_serve_opportunity_id', 'value' => '{{opportunity_id}}', 'description' => 'learn_serve_opportunities.id being reviewed.'],
                ['key' => 'rating', 'value' => '5', 'description' => 'Star rating, integer 1-5.'],
                ['key' => 'comment_en', 'value' => 'Great experience, well organized!', 'description' => 'English comment.'],
                ['key' => 'comment_ar', 'value' => 'تجربة رائعة ومنظمة جيدًا!', 'description' => 'Arabic comment.'],
                ['key' => 'primary_language', 'value' => 'en', 'description' => 'Primary language of the comment: en or ar.'],
            ]
        ),
        req(
            'Update Opportunity Feedback',
            'PUT',
            'opportunity-feedbacks/{{feedback_id}}/',
            "## Update Opportunity Feedback\nUpdates your own feedback entry. `PATCH` is also accepted.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `opportunity_feedbacks.id` (collection var `{{feedback_id}}`) |\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| rating | No | Integer 1-5 |\n| comment_en / comment_ar | No | Updated bilingual comment |",
            true,
            [
                ['key' => 'rating', 'value' => '4', 'description' => 'Updated star rating (1-5).'],
                ['key' => 'comment_en', 'value' => 'Updated comment text.', 'description' => 'Updated English comment.'],
            ]
        ),
        req(
            'Delete Opportunity Feedback',
            'DELETE',
            'opportunity-feedbacks/{{feedback_id}}/',
            "## Delete Opportunity Feedback\nSoft-deletes your own feedback entry.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `opportunity_feedbacks.id` (collection var `{{feedback_id}}`) |",
            true
        ),
        req(
            'Like/Unlike Feedback',
            'POST',
            'opportunity-feedback/{{feedback_id}}/like/',
            "## Like/Unlike Feedback\nToggles the authenticated user's like on a feedback entry (creates on first call, flips `is_liked` afterwards).\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| feedback_id | `opportunity_feedbacks.id` (collection var `{{feedback_id}}`) |",
            true
        ),
        req(
            'Get Image Download URL',
            'GET',
            'download-url/',
            "## Get Image Download URL\nStreams a stored opportunity image file as a download.\n\n**Auth:** Public (no auth).\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| image_id | Yes | `opportunity_images.id` |",
            false,
            [],
            [
                ['key' => 'image_id', 'value' => '1', 'description' => 'opportunity_images.id to download.'],
            ]
        ),
        req(
            'Certificate Preview',
            'GET',
            'certificate/preview/{{registration_id}}/',
            "## Certificate Preview\nReturns the data needed to render a certificate preview (name, course, dates, instructor, organization) for a Learn & Serve registration.\n\n**Auth:** Public (no auth).\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| registration_id | `learn_serve_opportunity_registrations.id` (collection var `{{registration_id}}`) |",
            false
        ),
        req(
            'Download Certificate',
            'GET',
            'download-certificate/',
            "## Download Certificate\nDownloads the generated certificate image file for a Learn & Serve registration.\n\n**Auth:** Public (no auth).\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| registration_id | Yes | `learn_serve_opportunity_registrations.id` |",
            false,
            [],
            [
                ['key' => 'registration_id', 'value' => '{{registration_id}}', 'description' => 'learn_serve_opportunity_registrations.id to download the certificate for.'],
            ]
        ),
        req(
            'Delete Opportunity Image(s)',
            'DELETE',
            'delete-opportunity-image/',
            "## Delete Opportunity Image(s)\nDeletes one or more opportunity images (and their files) that you have permission for.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| image_ids[] | Yes | One or more `opportunity_images.id` |\n| type | Yes | `volunteer` or `learnserve` — which opportunity type the images belong to |",
            true,
            [
                ['key' => 'image_ids[0]', 'value' => '1', 'description' => 'opportunity_images.id to delete.'],
                ['key' => 'type', 'value' => 'volunteer', 'description' => 'Opportunity type the images belong to: volunteer or learnserve.'],
            ]
        ),
    ],
];

$oppDeletion = [
    'name' => 'Deletion Requests',
    'description' => 'Creator-initiated deletion requests for opportunities, plus admin approval/rejection.',
    'item' => [
        req(
            'Request Volunteer Opportunity Deletion',
            'POST',
            'opportunities/{{opportunity_id}}/request-deletion/',
            "## Request Volunteer Opportunity Deletion\nSubmits a deletion request for an opportunity you created; it stays pending until an admin approves/rejects it. Use `type=volunteer`.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| opportunity_id | `volunteer_opportunities.id` or `learn_serve_opportunities.id` depending on `type` (collection var `{{opportunity_id}}`) |\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| type | Yes | `volunteer` or `learnserve` |\n| reason | No | Free-text reason |",
            true,
            [
                ['key' => 'type', 'value' => 'volunteer', 'description' => 'Opportunity type: volunteer or learnserve.'],
                ['key' => 'reason', 'value' => 'Event was cancelled by the venue.', 'description' => 'Optional free-text reason for deletion.'],
            ]
        ),
        req(
            'Request Learn & Serve Opportunity Deletion',
            'POST',
            'opportunities/{{opportunity_id}}/request-deletion/',
            "## Request Learn & Serve Opportunity Deletion\nSame endpoint as volunteer deletion requests, using `type=learnserve` to target a Learn & Serve opportunity.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| opportunity_id | `learn_serve_opportunities.id` (collection var `{{opportunity_id}}`) |\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| type | Yes | Must be `learnserve` |\n| reason | No | Free-text reason |",
            true,
            [
                ['key' => 'type', 'value' => 'learnserve', 'description' => 'Must be learnserve for this request.'],
                ['key' => 'reason', 'value' => 'Duplicate opportunity created by mistake.', 'description' => 'Optional free-text reason for deletion.'],
            ]
        ),
        req(
            'Admin Opportunity Deletion Action',
            'POST',
            'admin/opportunity-deletion-action/',
            "## Admin Opportunity Deletion Action\nApproves (hard-hides via soft delete) or rejects a pending opportunity deletion request. Admin only.\n\n**Auth required:** Bearer `{{token}}` with an admin account.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| opportunity_id | Yes | Target opportunity id |\n| type | Yes | `volunteer` or `learnserve` |\n| action | Yes | `approve` or `reject` |\n| rejection_reason | No | Required context when rejecting |",
            true,
            [
                ['key' => 'opportunity_id', 'value' => '{{opportunity_id}}', 'description' => 'Target volunteer/learn-serve opportunity id.'],
                ['key' => 'type', 'value' => 'volunteer', 'description' => 'Opportunity type: volunteer or learnserve.'],
                ['key' => 'action', 'value' => 'approve', 'description' => 'approve or reject.'],
                ['key' => 'rejection_reason', 'value' => '', 'description' => 'Optional reason shown to the creator when rejecting.', 'disabled' => true],
            ]
        ),
    ],
];

$opportunities = [
    'name' => '06 Opportunities',
    'description' => 'Volunteer opportunities and Learn & Serve opportunities: public discovery, CRUD, registrations, roles/teams, time slots, attendance, feedback, media, and deletion workflow.',
    'item' => [
        $oppPublicLists,
        $oppVolunteerCrud,
        $oppLearnServeCrud,
        $oppRegistrationsVolunteer,
        $oppRegistrationsLearnServe,
        $oppRoles,
        $oppTeams,
        $oppTimeSlots,
        $oppAttendanceScan,
        $oppFeedbackMedia,
        $oppDeletion,
    ],
];

// =====================================================================
// 07 Events
// =====================================================================

$eventsPublic = [
    'name' => 'Public',
    'description' => 'Public event discovery and feedback listing.',
    'item' => [
        req(
            'List Events',
            'GET',
            'events/',
            "## List Events\nFilterable/sorted list of events. Non-staff see approved events plus their own organization's events; guests see approved events only.\n\n**Auth:** Public (no auth); pass a bearer token to also see your own pending events.\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| organization | No | Filter by `created_by` (organization_profiles.id) |\n| event | No | Filter by `event_type_id` |\n| event_type | No | Filter by event_type value_en (resolved to id) |\n| status | No | upcoming/inprogress/completed |\n| start_date / end_date | No | Date range filters |\n| location | No | Location text filter |\n| gender | No | master_choices.id or `all` |\n| min_age / max_age | No | Age range filters |\n| tags[] | No | Interest name filters |\n| free_event / paid_event | No | `true` to filter by paid_registration |\n| free_event_with_registration | No | `true` for free + registration_required events |\n| search | No | Title search (en/ar) |\n| participation_type | No | master_choices.id |\n| page / limit | No | Pagination |",
            false,
            [],
            [
                ['key' => 'organization', 'value' => '', 'description' => 'Filter by organizer (organization_profiles.id).', 'disabled' => true],
                ['key' => 'event', 'value' => '', 'description' => 'Filter by event_type_id.', 'disabled' => true],
                ['key' => 'event_type', 'value' => '', 'description' => 'Filter by event type value_en.', 'disabled' => true],
                ['key' => 'status', 'value' => '', 'description' => 'upcoming, inprogress, or completed.', 'disabled' => true],
                ['key' => 'start_date', 'value' => '', 'description' => 'Only events starting on/after this date.', 'disabled' => true],
                ['key' => 'end_date', 'value' => '', 'description' => 'Only events ending on/before this date.', 'disabled' => true],
                ['key' => 'location', 'value' => '', 'description' => 'Location text filter (en/ar).', 'disabled' => true],
                ['key' => 'gender', 'value' => 'all', 'description' => 'Gender master_choices.id, or all.', 'disabled' => true],
                ['key' => 'min_age', 'value' => '', 'description' => 'Minimum eligible age.', 'disabled' => true],
                ['key' => 'max_age', 'value' => '', 'description' => 'Maximum eligible age.', 'disabled' => true],
                ['key' => 'tags[]', 'value' => '', 'description' => 'Interest name filter (repeatable).', 'disabled' => true],
                ['key' => 'free_event', 'value' => '', 'description' => 'true to only show free events.', 'disabled' => true],
                ['key' => 'paid_event', 'value' => '', 'description' => 'true to only show paid events.', 'disabled' => true],
                ['key' => 'free_event_with_registration', 'value' => '', 'description' => 'true for free events that require registration.', 'disabled' => true],
                ['key' => 'search', 'value' => '', 'description' => 'Title search (en/ar).', 'disabled' => true],
                ['key' => 'participation_type', 'value' => '', 'description' => 'participation_type_id master_choices.id.', 'disabled' => true],
                ...pageLimit(),
            ]
        ),
        req(
            'Get Event',
            'GET',
            'events/{{event_id}}/',
            "## Get Event\nReturns full event details. Increments `view_count` for non-owner viewers, and adds `remaining_slots` when registration is required.\n\n**Auth:** Public (no auth) for approved events; pass a bearer token to view your own pending event.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `events.id` (collection var `{{event_id}}`) |",
            false
        ),
        req(
            'List Event Feedback',
            'GET',
            'event-feedback/',
            "## List Event Feedback\nReturns feedback entries, optionally filtered by event.\n\n**Auth:** Public (no auth).\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| event_id | No | Filter by `events.id` |",
            false,
            [],
            [
                ['key' => 'event_id', 'value' => '{{event_id}}', 'description' => 'Filter feedback by events.id.', 'disabled' => true],
            ]
        ),
        req(
            'Get Event Feedback',
            'GET',
            'event-feedback/{{feedback_id}}/',
            "## Get Event Feedback\nReturns a single event feedback entry with likes.\n\n**Auth:** Public (no auth).\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `event_feedbacks.id` (collection var `{{feedback_id}}`) |",
            false
        ),
    ],
];

$eventsCrud = [
    'name' => 'CRUD & Actions',
    'description' => 'Organization-only event management: create/update/delete, approval workflow, and quick register.',
    'item' => [
        req(
            'Create Event',
            'POST',
            'events/',
            "## Create Event\nCreates a new event owned by the authenticated organization. Starts in `pending` approval status.\n\n**Auth required:** Bearer `{{token}}` with an organization account.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| title_en | Yes | English title |\n| title_ar / description_en / description_ar | No | Bilingual text |\n| event_type_id | No | master_choices.id |\n| due_date / start_date / end_date | No | Date range + registration cutoff |\n| start_time / end_time | No | Daily time window |\n| registration_required | No | Boolean |\n| participants_needed | No | Integer >= 0 |\n| paid_registration / registration_fee | No | Paid event settings |\n| latitude / longitude / location_en / location_ar | No | Location |\n| from_age / to_age / gender_id | No | Eligibility |\n| attendance_type_id / participation_type_id | No | master_choices IDs |\n| registration_link | No | External registration URL |\n| primary_language | No | `en` or `ar` |\n| interest_ids[] | No | Interest IDs to attach |\n| images[] | No | Event gallery images |\n| sponsor_images[] | No | Sponsor logo images |\n| license_image | No | License document image |",
            true,
            [
                ['key' => 'title_en', 'value' => 'Community Health Fair', 'description' => 'English title (required).'],
                ['key' => 'title_ar', 'value' => 'معرض الصحة المجتمعي', 'description' => 'Arabic title.'],
                ['key' => 'description_en', 'value' => 'Free health screenings and awareness booths.', 'description' => 'English description.'],
                ['key' => 'description_ar', 'value' => 'فحوصات صحية مجانية وأركان توعية.', 'description' => 'Arabic description.'],
                ['key' => 'event_type_id', 'value' => '', 'description' => 'Event type master_choices.id.', 'disabled' => true],
                ['key' => 'due_date', 'value' => '2026-09-01', 'description' => 'Registration cutoff date.'],
                ['key' => 'start_date', 'value' => '2026-09-05', 'description' => 'Event start date.'],
                ['key' => 'end_date', 'value' => '2026-09-05', 'description' => 'Event end date.'],
                ['key' => 'start_time', 'value' => '09:00', 'description' => 'Daily start time.'],
                ['key' => 'end_time', 'value' => '17:00', 'description' => 'Daily end time.'],
                ['key' => 'registration_required', 'value' => '1', 'description' => 'Whether attendees must register (1/0).'],
                ['key' => 'participants_needed', 'value' => '100', 'description' => 'Capacity when registration_required is true.'],
                ['key' => 'paid_registration', 'value' => '0', 'description' => 'Whether registration requires payment (1/0).'],
                ['key' => 'registration_fee', 'value' => '0', 'description' => 'Fee amount when paid_registration is true.'],
                ['key' => 'latitude', 'value' => '29.3759', 'description' => 'Venue latitude.'],
                ['key' => 'longitude', 'value' => '47.9774', 'description' => 'Venue longitude.'],
                ['key' => 'location_en', 'value' => 'Fursa Community Center', 'description' => 'English location text.'],
                ['key' => 'location_ar', 'value' => 'مركز فرصة المجتمعي', 'description' => 'Arabic location text.'],
                ['key' => 'from_age', 'value' => '', 'description' => 'Minimum eligible age.', 'disabled' => true],
                ['key' => 'to_age', 'value' => '', 'description' => 'Maximum eligible age.', 'disabled' => true],
                ['key' => 'gender_id', 'value' => '', 'description' => 'Gender restriction master_choices.id.', 'disabled' => true],
                ['key' => 'attendance_type_id', 'value' => '', 'description' => 'Attendance type master_choices.id.', 'disabled' => true],
                ['key' => 'participation_type_id', 'value' => '', 'description' => 'Participation type master_choices.id.', 'disabled' => true],
                ['key' => 'registration_link', 'value' => '', 'description' => 'External registration URL, if any.', 'disabled' => true],
                ['key' => 'primary_language', 'value' => 'en', 'description' => 'Primary display language: en or ar.'],
                ['key' => 'interest_ids[0]', 'value' => '1', 'description' => 'First interest id to attach.'],
                ['key' => 'images[]', 'type' => 'file', 'description' => 'Event gallery image (repeat key for multiple).'],
                ['key' => 'sponsor_images[]', 'type' => 'file', 'description' => 'Sponsor logo image (repeat key for multiple).'],
                ['key' => 'license_image', 'type' => 'file', 'description' => 'License document image, if required.'],
            ]
        ),
        req(
            'Update Event',
            'PUT',
            'events/{{event_id}}/',
            "## Update Event\nUpdates an event you own. `PATCH` is also accepted; all fields are optional on update.\n\n**Auth required:** Bearer `{{token}}` with the owning organization account.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `events.id` (collection var `{{event_id}}`) |\n\n### Form-data fields\nSame fields as Create Event (all optional) — see Create Event for full field docs.",
            true,
            [
                ['key' => 'title_en', 'value' => 'Community Health Fair (Updated)', 'description' => 'Updated English title.'],
                ['key' => 'participants_needed', 'value' => '120', 'description' => 'Updated capacity.'],
            ]
        ),
        req(
            'Delete Event',
            'DELETE',
            'events/{{event_id}}/',
            "## Delete Event\nSoft-deletes an event you own.\n\n**Auth required:** Bearer `{{token}}` with the owning organization account.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `events.id` (collection var `{{event_id}}`) |",
            true
        ),
        req(
            'Approve Event',
            'POST',
            'events/{{event_id}}/approve/',
            "## Approve Event\nApproves a pending event, making it publicly visible. Staff only.\n\n**Auth required:** Bearer `{{token}}` with a staff account.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `events.id` (collection var `{{event_id}}`) |",
            true
        ),
        req(
            'Reject Event',
            'POST',
            'events/{{event_id}}/reject/',
            "## Reject Event\nRejects a pending event with a reason. Staff only.\n\n**Auth required:** Bearer `{{token}}` with a staff account.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `events.id` (collection var `{{event_id}}`) |\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| rejected_reason | Yes | Reason shown to the organizer |",
            true,
            [
                ['key' => 'rejected_reason', 'value' => 'Missing required license documentation.', 'description' => 'Reason shown to the event organizer.'],
            ]
        ),
        req(
            'Register For Event (Quick)',
            'POST',
            'events/{{event_id}}/register/',
            "## Register For Event (Quick)\nConvenience alias that registers the authenticated user for the given event id (internally forwards to Event Registrations → Register For Event).\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `events.id` (collection var `{{event_id}}`) |\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| time_slot_id | No | `event_time_slots.id` to register into |",
            true,
            [
                ['key' => 'time_slot_id', 'value' => '{{time_slot_id}}', 'description' => 'Optional event time slot id to register into.', 'disabled' => true],
            ]
        ),
    ],
];

$eventsRegistrations = [
    'name' => 'Registrations',
    'description' => 'Attendee sign-up for events, plus organizer management of registrations.',
    'item' => [
        req(
            'List Registrations',
            'GET',
            'event-registrations/',
            "## List Registrations\nFor organizations: all registrations across their events. For attendees: only their own registrations. Supports search/sort/filter.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| event_id / event | No | Filter by event |\n| status | No | registration_status |\n| payment_status | No | Payment status |\n| is_attended | No | Boolean attendance filter |\n| search | No | Search registrant name/email/phone |\n| sort_by | No | `registration_date`, `name`, `email`, `status`, `attendance`, `payment` |\n| sort_order | No | `asc` or `desc` (default desc) |\n| page / limit | No | Pagination |",
            true,
            [],
            [
                ['key' => 'event_id', 'value' => '{{event_id}}', 'description' => 'Filter by events.id.', 'disabled' => true],
                ['key' => 'status', 'value' => '', 'description' => 'Filter by registration_status.', 'disabled' => true],
                ['key' => 'payment_status', 'value' => '', 'description' => 'Filter by payment_status.', 'disabled' => true],
                ['key' => 'is_attended', 'value' => '', 'description' => 'Boolean attendance filter.', 'disabled' => true],
                ['key' => 'search', 'value' => '', 'description' => 'Search registrant name/email/phone.', 'disabled' => true],
                ['key' => 'sort_by', 'value' => 'registration_date', 'description' => 'registration_date, name, email, status, attendance, or payment.'],
                ['key' => 'sort_order', 'value' => 'desc', 'description' => 'asc or desc.'],
                ...pageLimit(),
            ]
        ),
        req(
            'My Registrations',
            'GET',
            'event-registrations/my-registrations/',
            "## My Registrations\nPaginated list of the authenticated user's own event registrations.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| status | No | Filter by registration_status |\n| time | No | `upcoming` or `past` |\n| page / limit | No | Pagination |",
            true,
            [],
            [
                ['key' => 'status', 'value' => '', 'description' => 'Filter by registration_status.', 'disabled' => true],
                ['key' => 'time', 'value' => '', 'description' => 'upcoming or past.', 'disabled' => true],
                ...pageLimit(),
            ]
        ),
        req(
            'Register For Event',
            'POST',
            'event-registrations/',
            "## Register For Event\nRegisters the authenticated user for an event (checks due date, duplicate registration, and remaining capacity).\n\n**Auth required:** Bearer `{{token}}`.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| event | Yes | `events.id` |\n| time_slot_id | No | `event_time_slots.id` belonging to the event |",
            true,
            [
                ['key' => 'event', 'value' => '{{event_id}}', 'description' => 'events.id to register for.'],
                ['key' => 'time_slot_id', 'value' => '{{time_slot_id}}', 'description' => 'Optional event time slot id to register into.', 'disabled' => true],
            ]
        ),
        req(
            'List Registrations By Event',
            'GET',
            'event-registrations/{{event_id}}/',
            "## List Registrations By Event\nOrganizer-only view of every registration for a specific event (accepts the same filters as List Registrations).\n\n**Auth required:** Bearer `{{token}}` with the owning organization account.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| event_id | `events.id` (collection var `{{event_id}}`) |",
            true
        ),
        req(
            'Get Registration',
            'GET',
            'event-registrations/{{registration_id}}/',
            "## Get Registration\nReturns a single event registration with user, event, and time slot.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `event_registrations.id` (collection var `{{registration_id}}`) |",
            true
        ),
        req(
            'List Registrations For Event (Alias)',
            'GET',
            'event-registrations/{{registration_id}}/registrations/',
            "## List Registrations For Event (Alias)\nAlias of List Registrations By Event, addressed by the event id in this path segment (kept for parity with the legacy API shape).\n\n**Auth required:** Bearer `{{token}}` with the owning organization account.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `events.id` (collection var `{{registration_id}}` — reuse as an event id here) |",
            true
        ),
        req(
            'Update Registration',
            'PUT',
            'event-registrations/{{registration_id}}/',
            "## Update Registration\nOrganizer-only update of a registration's status/payment/attendance/time slot. `PATCH` is also accepted.\n\n**Auth required:** Bearer `{{token}}` with the owning organization account.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `event_registrations.id` (collection var `{{registration_id}}`) |\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| registration_status (or status) | No | New registration status |\n| payment_status | No | New payment status |\n| is_attended | No | Boolean attendance flag |\n| time_slot_id | No | Reassign to a different time slot |",
            true,
            [
                ['key' => 'registration_status', 'value' => 'approved', 'description' => 'New registration status (accepts `status` as an alias key too).'],
                ['key' => 'payment_status', 'value' => 'paid', 'description' => 'New payment status.'],
                ['key' => 'is_attended', 'value' => '1', 'description' => 'Mark attendance (1/0).'],
                ['key' => 'time_slot_id', 'value' => '{{time_slot_id}}', 'description' => 'Reassign to a different event time slot.', 'disabled' => true],
            ]
        ),
        req(
            'Cancel Registration',
            'DELETE',
            'event-registrations/{{registration_id}}/',
            "## Cancel Registration\nCancels a registration (soft-delete). Allowed for the event organizer or the registrant themself.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `event_registrations.id` (collection var `{{registration_id}}`) |",
            true
        ),
        req(
            'Unregister From Event',
            'POST',
            'events/{{event_id}}/unregister/',
            "## Unregister From Event\nCancels the authenticated user's own registration for an event (hard delete). Both `POST` and `DELETE` are accepted.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| event_id | `events.id` (collection var `{{event_id}}`) |",
            true
        ),
    ],
];

$eventsTimeSlots = [
    'name' => 'Time Slots',
    'description' => 'Date/time slots with a participant cap for events.',
    'item' => [
        req(
            'List Event Time Slots',
            'GET',
            'event-time-slots/',
            "## List Event Time Slots\nReturns time slots for an event that still have remaining capacity, plus overall remaining participants in `meta`.\n\n**Auth:** Public (no auth) at the controller level, though most callers will be authenticated organizers.\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| event_id | Yes | `events.id` |\n| date | No | Filter to a specific date |",
            true,
            [],
            [
                ['key' => 'event_id', 'value' => '{{event_id}}', 'description' => 'events.id (required).'],
                ['key' => 'date', 'value' => '', 'description' => 'Filter to a specific date (YYYY-MM-DD).', 'disabled' => true],
            ]
        ),
        req(
            'Get Event Time Slot',
            'GET',
            'event-time-slots/{{time_slot_id}}/',
            "## Get Event Time Slot\nReturns a single event time slot plus remaining participants in `meta`.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `event_time_slots.id` (collection var `{{time_slot_id}}`) |",
            true
        ),
        req(
            'Create Event Time Slot',
            'POST',
            'event-time-slots/',
            "## Create Event Time Slot\nAdds a date/time slot with a participant cap to an event you own.\n\n**Auth required:** Bearer `{{token}}` with the owning organization account.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| event | Yes | `events.id` you own |\n| date | Yes | Slot date `YYYY-MM-DD` |\n| start_time / end_time | Yes | Time window |\n| participants_needed | Yes | Minimum 1 |",
            true,
            [
                ['key' => 'event', 'value' => '{{event_id}}', 'description' => 'events.id you own.'],
                ['key' => 'date', 'value' => '2026-09-05', 'description' => 'Slot date (YYYY-MM-DD).'],
                ['key' => 'start_time', 'value' => '09:00', 'description' => 'Slot start time.'],
                ['key' => 'end_time', 'value' => '11:00', 'description' => 'Slot end time.'],
                ['key' => 'participants_needed', 'value' => '30', 'description' => 'Capacity for this slot (min 1).'],
            ]
        ),
        req(
            'Update Event Time Slot',
            'PUT',
            'event-time-slots/{{time_slot_id}}/',
            "## Update Event Time Slot\nUpdates a time slot for an event you own. `PATCH` is also accepted.\n\n**Auth required:** Bearer `{{token}}` with the owning organization account.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `event_time_slots.id` (collection var `{{time_slot_id}}`) |\n\n### Form-data fields\nSame fields as Create (all optional).",
            true,
            [
                ['key' => 'participants_needed', 'value' => '40', 'description' => 'Updated capacity for this slot.'],
            ]
        ),
        req(
            'Delete Event Time Slot',
            'DELETE',
            'event-time-slots/{{time_slot_id}}/',
            "## Delete Event Time Slot\nSoft-deletes a time slot for an event you own.\n\n**Auth required:** Bearer `{{token}}` with the owning organization account.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `event_time_slots.id` (collection var `{{time_slot_id}}`) |",
            true
        ),
    ],
];

$eventsFeedback = [
    'name' => 'Feedback',
    'description' => 'Ratings/comments on events (write endpoints; see 07 Events → Public for read endpoints).',
    'item' => [
        req(
            'Create Event Feedback',
            'POST',
            'event-feedback/',
            "## Create Event Feedback\nSubmits a rating/comment for an event.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| event_id | Yes | `events.id` |\n| rating | Yes | Integer 1-5 |\n| comment_en / comment_ar | No | Bilingual comment |\n| primary_language | No | `en` or `ar` |",
            true,
            [
                ['key' => 'event_id', 'value' => '{{event_id}}', 'description' => 'events.id being reviewed.'],
                ['key' => 'rating', 'value' => '5', 'description' => 'Star rating, integer 1-5.'],
                ['key' => 'comment_en', 'value' => 'Really enjoyed this event!', 'description' => 'English comment.'],
                ['key' => 'comment_ar', 'value' => 'استمتعت جدًا بهذا الحدث!', 'description' => 'Arabic comment.'],
                ['key' => 'primary_language', 'value' => 'en', 'description' => 'Primary language of the comment: en or ar.'],
            ]
        ),
        req(
            'Update Event Feedback',
            'PUT',
            'event-feedback/{{feedback_id}}/',
            "## Update Event Feedback\nUpdates your own event feedback entry. `PATCH` is also accepted.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `event_feedbacks.id` (collection var `{{feedback_id}}`) |\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| rating | No | Integer 1-5 |\n| comment_en / comment_ar | No | Updated bilingual comment |",
            true,
            [
                ['key' => 'rating', 'value' => '4', 'description' => 'Updated star rating (1-5).'],
                ['key' => 'comment_en', 'value' => 'Updated comment text.', 'description' => 'Updated English comment.'],
            ]
        ),
        req(
            'Delete Event Feedback',
            'DELETE',
            'event-feedback/{{feedback_id}}/',
            "## Delete Event Feedback\nSoft-deletes your own event feedback entry.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `event_feedbacks.id` (collection var `{{feedback_id}}`) |",
            true
        ),
    ],
];

$eventsFeedbackLikes = [
    'name' => 'Feedback Likes',
    'description' => 'Likes on event feedback (full CRUD, scoped to the authenticated user).',
    'item' => [
        req(
            'List My Feedback Likes',
            'GET',
            'event-feedback-like/',
            "## List My Feedback Likes\nReturns the authenticated user's own likes, optionally filtered by feedback entry.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| feedback | No | Filter by `event_feedbacks.id` |",
            true,
            [],
            [
                ['key' => 'feedback', 'value' => '{{feedback_id}}', 'description' => 'Filter by event_feedbacks.id.', 'disabled' => true],
            ]
        ),
        req(
            'Create/Set Feedback Like',
            'POST',
            'event-feedback-like/',
            "## Create/Set Feedback Like\nCreates or updates (upsert) the authenticated user's like on an event feedback entry.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| feedback | Yes | `event_feedbacks.id` |\n| is_liked | No | Defaults to `true` |",
            true,
            [
                ['key' => 'feedback', 'value' => '{{feedback_id}}', 'description' => 'event_feedbacks.id to like/unlike.'],
                ['key' => 'is_liked', 'value' => '1', 'description' => 'Like state to set (defaults to true).'],
            ]
        ),
        req(
            'Get Feedback Like',
            'GET',
            'event-feedback-like/{{feedback_id}}/',
            "## Get Feedback Like\nReturns a single like record owned by the authenticated user.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `event_feedback_likes.id` (collection var `{{feedback_id}}`) |",
            true
        ),
        req(
            'Update Feedback Like',
            'PUT',
            'event-feedback-like/{{feedback_id}}/',
            "## Update Feedback Like\nUpdates the `is_liked` flag on your own like record. `PATCH` is also accepted.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `event_feedback_likes.id` (collection var `{{feedback_id}}`) |\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| is_liked | Yes | `true`/`false` |",
            true,
            [
                ['key' => 'is_liked', 'value' => '0', 'description' => 'New like state (true/false).'],
            ]
        ),
        req(
            'Delete Feedback Like',
            'DELETE',
            'event-feedback-like/{{feedback_id}}/',
            "## Delete Feedback Like\nRemoves your own like record.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `event_feedback_likes.id` (collection var `{{feedback_id}}`) |",
            true
        ),
    ],
];

$eventsDeletion = [
    'name' => 'Deletion',
    'description' => 'Organizer-initiated event deletion requests and admin approval/rejection.',
    'item' => [
        req(
            'Request Event Deletion',
            'POST',
            'events/{{event_id}}/request-deletion/',
            "## Request Event Deletion\nSubmits a deletion request for an event you own; blocked within 7 days of the event's due date, and while another request is already pending.\n\n**Auth required:** Bearer `{{token}}` with the owning organization account.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| event_id | `events.id` (collection var `{{event_id}}`) |",
            true
        ),
        req(
            'Admin Event Deletion Action',
            'POST',
            'admin/event-deletion-action/',
            "## Admin Event Deletion Action\nApproves (soft-deletes) or rejects a pending event deletion request. Staff/superuser only.\n\n**Auth required:** Bearer `{{token}}` with a staff/superuser account.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| event_id | Yes | `events.id` with a pending deletion request |\n| action | Yes | `approve` or `reject` |\n| rejection_reason | No | Shown to the organizer when rejecting |",
            true,
            [
                ['key' => 'event_id', 'value' => '{{event_id}}', 'description' => 'events.id with a pending deletion request.'],
                ['key' => 'action', 'value' => 'approve', 'description' => 'approve or reject.'],
                ['key' => 'rejection_reason', 'value' => '', 'description' => 'Optional reason shown to the organizer when rejecting.', 'disabled' => true],
            ]
        ),
    ],
];

$events = [
    'name' => '07 Events',
    'description' => 'Organization-hosted events: public discovery, CRUD & approval workflow, registrations, time slots, feedback, feedback likes, and deletion requests.',
    'item' => [
        $eventsPublic,
        $eventsCrud,
        $eventsRegistrations,
        $eventsTimeSlots,
        $eventsFeedback,
        $eventsFeedbackLikes,
        $eventsDeletion,
    ],
];

// =====================================================================
// 08 Community
// =====================================================================

$communityPosts = [
    'name' => 'Posts',
    'description' => 'Community posts/ideas, with tag browsing and creator contact.',
    'item' => [
        req(
            'List Posts',
            'GET',
            'posts/',
            "## List Posts\nPaginated, filterable feed of visible community posts (with nested top-level replies preloaded).\n\n**Auth:** Public (no auth).\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| title_en / title_ar | No | Title filters |\n| start_date / end_date | No | Created-at date range |\n| user | No | Filter by author username/first/last name |\n| proposing_idea | No | `true` for idea posts (excludes funding-required) |\n| is_funding_required | No | `true` for funding-required posts |\n| post | No | `true` for plain posts (excludes ideas/funding) |\n| search | No | Search title/idea text (en/ar) |\n| tags[] / tag | No | Filter by community tag name |\n| page / limit | No | Pagination |",
            false,
            [],
            [
                ['key' => 'title_en', 'value' => '', 'description' => 'Filter by English title (partial match).', 'disabled' => true],
                ['key' => 'title_ar', 'value' => '', 'description' => 'Filter by Arabic title (partial match).', 'disabled' => true],
                ['key' => 'start_date', 'value' => '', 'description' => 'Only posts created on/after this date.', 'disabled' => true],
                ['key' => 'end_date', 'value' => '', 'description' => 'Only posts created on/before this date.', 'disabled' => true],
                ['key' => 'user', 'value' => '', 'description' => 'Filter by author username/first/last name.', 'disabled' => true],
                ['key' => 'proposing_idea', 'value' => '', 'description' => 'true for idea posts.', 'disabled' => true],
                ['key' => 'is_funding_required', 'value' => '', 'description' => 'true for funding-required posts.', 'disabled' => true],
                ['key' => 'post', 'value' => '', 'description' => 'true for plain (non-idea, non-funding) posts.', 'disabled' => true],
                ['key' => 'search', 'value' => '', 'description' => 'Search title/idea text (en/ar).', 'disabled' => true],
                ['key' => 'tags[]', 'value' => '', 'description' => 'Filter by community tag name (repeatable).', 'disabled' => true],
                ...pageLimit(),
            ]
        ),
        req(
            'Get Post',
            'GET',
            'posts/{{post_id}}/',
            "## Get Post\nReturns a single visible post with images, tags, and nested replies.\n\n**Auth:** Public (no auth).\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `posts.id` (collection var `{{post_id}}`) |",
            false
        ),
        req(
            'All Tags',
            'GET',
            'posts/all_tags/',
            "## All Tags\nReturns every community tag that has at least one visible post attached to it.\n\n**Auth:** Public (no auth).",
            false
        ),
        req(
            'Posts By Tag',
            'GET',
            'posts/by_tag/',
            "## Posts By Tag\nShortcut for List Posts filtered to a single tag name.\n\n**Auth:** Public (no auth).\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| tag | Yes | Community tag name |",
            false,
            [],
            [
                ['key' => 'tag', 'value' => 'volunteering', 'description' => 'Community tag name to filter posts by.'],
            ]
        ),
        req(
            'Create Post',
            'POST',
            'posts/',
            "## Create Post\nCreates a community post/idea. Automatically hidden (`is_displayed=false`) if forbidden words are detected in the title/text.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| title_en / title_ar | No | Bilingual title |\n| idea_text_en / idea_text_ar | No | Bilingual body text — supports `@username` mentions |\n| primary_language | No | `en` or `ar` |\n| proposing_idea | No | Boolean — marks this as an idea proposal |\n| needs_support | No | Boolean |\n| is_funding_required | No | Boolean — marks this as a funding request |\n| tags[] | No | Tag names (created if new) |\n| images[] | No | Post image files |",
            true,
            [
                ['key' => 'title_en', 'value' => 'Looking for volunteers this weekend', 'description' => 'English title.'],
                ['key' => 'title_ar', 'value' => 'نبحث عن متطوعين هذا الأسبوع', 'description' => 'Arabic title.'],
                ['key' => 'idea_text_en', 'value' => 'We need help distributing food packages. cc @ahmed_vol', 'description' => 'English body text (supports @username mentions).'],
                ['key' => 'idea_text_ar', 'value' => 'نحتاج مساعدة في توزيع الطرود الغذائية.', 'description' => 'Arabic body text.'],
                ['key' => 'primary_language', 'value' => 'en', 'description' => 'Primary language: en or ar.'],
                ['key' => 'proposing_idea', 'value' => '0', 'description' => 'true if this post proposes a new idea.'],
                ['key' => 'needs_support', 'value' => '1', 'description' => 'true if this post is asking for support/help.'],
                ['key' => 'is_funding_required', 'value' => '0', 'description' => 'true if this post is a funding request.'],
                ['key' => 'tags[0]', 'value' => 'volunteering', 'description' => 'Tag name to attach (created automatically if new).'],
                ['key' => 'images[]', 'type' => 'file', 'description' => 'Post image file (repeat key for multiple).'],
            ]
        ),
        req(
            'Update Post',
            'PUT',
            'posts/{{post_id}}/',
            "## Update Post\nUpdates your own post. `PATCH` is also accepted. Re-runs the forbidden-word check on the merged title/text.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `posts.id` (collection var `{{post_id}}`) |\n\n### Form-data fields\nSame fields as Create Post (all optional).",
            true,
            [
                ['key' => 'idea_text_en', 'value' => 'Updated: still need 5 more volunteers.', 'description' => 'Updated English body text.'],
                ['key' => 'images[]', 'type' => 'file', 'description' => 'Additional post image file (repeat key for multiple).'],
            ]
        ),
        req(
            'Delete Post',
            'DELETE',
            'posts/{{post_id}}/',
            "## Delete Post\nSoft-deletes your own post and cascades a soft-delete to its replies.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `posts.id` (collection var `{{post_id}}`) |",
            true
        ),
        req(
            'Contact Post Creator',
            'POST',
            'posts/{{post_id}}/contact-creator/',
            "## Contact Post Creator\nSends a message to the post's creator (delivery handled asynchronously server-side).\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `posts.id` (collection var `{{post_id}}`) |\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| message | Yes | Message text to send to the post creator |",
            true,
            [
                ['key' => 'message', 'value' => "Hi! I'd like to help with this — how can I get involved?", 'description' => 'Message text to send to the post creator.'],
            ]
        ),
    ],
];

$communityReplies = [
    'name' => 'Replies',
    'description' => 'Threaded replies (with nested children) on community posts.',
    'item' => [
        req(
            'List Replies',
            'GET',
            'replies/',
            "## List Replies\nPaginated top-level replies for a post (each including its nested child replies).\n\n**Auth:** Public (no auth).\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| post_id | No | Filter by `posts.id` |\n| page / limit | No | Pagination |",
            false,
            [],
            [
                ['key' => 'post_id', 'value' => '{{post_id}}', 'description' => 'Filter replies by posts.id.', 'disabled' => true],
                ...pageLimit(),
            ]
        ),
        req(
            'Get Reply',
            'GET',
            'replies/{{reply_id}}/',
            "## Get Reply\nReturns a single visible reply with its children.\n\n**Auth:** Public (no auth).\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `replies.id` (collection var `{{reply_id}}`) |",
            false
        ),
        req(
            'Create Reply',
            'POST',
            'replies/',
            "## Create Reply\nAdds a reply to a post, optionally nested under a parent reply. Requires text and/or images. Supports `@username` mentions.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| post | Yes | `posts.id` |\n| parent | No | `replies.id` to nest under (must belong to the same post) |\n| text_en / text_ar | Conditional | At least one of text/images required |\n| primary_language | No | `en` or `ar` |\n| images[] | Conditional | Reply image files |",
            true,
            [
                ['key' => 'post', 'value' => '{{post_id}}', 'description' => 'posts.id to reply to.'],
                ['key' => 'parent', 'value' => '', 'description' => 'Optional replies.id to nest this reply under (same post).', 'disabled' => true],
                ['key' => 'text_en', 'value' => "I'm interested, count me in!", 'description' => 'English reply text (text and/or images required).'],
                ['key' => 'text_ar', 'value' => 'أنا مهتم، احسبني معكم!', 'description' => 'Arabic reply text.'],
                ['key' => 'primary_language', 'value' => 'en', 'description' => 'Primary language: en or ar.'],
                ['key' => 'images[]', 'type' => 'file', 'description' => 'Reply image file (repeat key for multiple).'],
            ]
        ),
        req(
            'Update Reply',
            'PUT',
            'replies/{{reply_id}}/',
            "## Update Reply\nUpdates your own reply text and/or appends new images. `PATCH` is also accepted.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `replies.id` (collection var `{{reply_id}}`) |\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| text_en / text_ar | Conditional | Updated bilingual text |\n| images[] | No | Additional image files |",
            true,
            [
                ['key' => 'text_en', 'value' => 'Updated reply text.', 'description' => 'Updated English reply text.'],
                ['key' => 'images[]', 'type' => 'file', 'description' => 'Additional reply image file (repeat key for multiple).'],
            ]
        ),
        req(
            'Delete Reply',
            'DELETE',
            'replies/{{reply_id}}/',
            "## Delete Reply\nSoft-deletes your own reply.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `replies.id` (collection var `{{reply_id}}`) |",
            true
        ),
    ],
];

$communityLikesMentions = [
    'name' => 'Likes & Mentions',
    'description' => 'Like toggling for posts/replies and @mention username autocomplete.',
    'item' => [
        req(
            'Toggle Like',
            'POST',
            'likes/toggle/',
            "## Toggle Like\nToggles the authenticated user's like on a post or a reply (provide exactly one of `post_id` / `reply_id`).\n\n**Auth required:** Bearer `{{token}}`.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| post_id | One of | `posts.id` to like/unlike |\n| reply_id | One of | `replies.id` to like/unlike |",
            true,
            [
                ['key' => 'post_id', 'value' => '{{post_id}}', 'description' => 'posts.id to like/unlike (provide this or reply_id, not both).'],
                ['key' => 'reply_id', 'value' => '', 'description' => 'replies.id to like/unlike (alternative to post_id).', 'disabled' => true],
            ]
        ),
        req(
            'Mention Suggestions',
            'GET',
            'mention-suggestions/',
            "## Mention Suggestions\nPaginated username autocomplete suggestions for `@mention` typing in posts/replies.\n\n**Auth:** Public (no auth).\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| q | No | Username prefix to search (with or without leading `@`) |\n| page / limit | No | Pagination |",
            false,
            [],
            [
                ['key' => 'q', 'value' => 'ahm', 'description' => 'Username prefix to search (leading @ is stripped automatically).'],
                ...pageLimit(),
            ]
        ),
    ],
];

$community = [
    'name' => '08 Community',
    'description' => 'Community posts/ideas, threaded replies, likes, and @mention suggestions.',
    'item' => [
        $communityPosts,
        $communityReplies,
        $communityLikesMentions,
    ],
];

// =====================================================================
// 09 Notifications
// =====================================================================
$notifications = [
    'name' => '09 Notifications',
    'description' => "The authenticated user's notifications: list/unread-count, mark read/unread, and delete.",
    'item' => [
        req(
            'List Notifications',
            'GET',
            'notifications/',
            "## List Notifications\nReturns the authenticated user's notifications with an `unread_count`. Omit `page`/`limit` to receive the full unpaginated list; include either to get a paginated response.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| is_read | No | `true` or `false` to filter by read state |\n| page / limit | No | Pagination (omit both for the full list) |",
            true,
            [],
            [
                ['key' => 'is_read', 'value' => '', 'description' => 'true or false to filter by read state.', 'disabled' => true],
                ['key' => 'page', 'value' => '', 'description' => 'Page number (omit both page and limit for the full unpaginated list).', 'disabled' => true],
                ['key' => 'limit', 'value' => '', 'description' => 'Results per page (1-100).', 'disabled' => true],
            ]
        ),
        req(
            'Mark Notifications Read/Unread',
            'PATCH',
            'notifications/mark-read/',
            "## Mark Notifications Read/Unread\nMarks specific notifications, or all of them, as read or unread. Provide exactly one of `notification_ids[]` / `mark_all`.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| notification_ids[] | One of | Specific `user_notifications.id` values |\n| mark_all | One of | `true` to affect every notification |\n| is_read | Yes | `true` to mark read, `false` to mark unread |",
            true,
            [
                ['key' => 'notification_ids[0]', 'value' => '1', 'description' => 'user_notifications.id to mark (provide this or mark_all).'],
                ['key' => 'mark_all', 'value' => '', 'description' => 'true to mark every notification (alternative to notification_ids[]).', 'disabled' => true],
                ['key' => 'is_read', 'value' => '1', 'description' => 'true to mark read, false to mark unread.'],
            ]
        ),
        req(
            'Delete Notifications',
            'DELETE',
            'notifications/delete/',
            "## Delete Notifications\nSoft-deletes specific notifications, or all of them. Provide exactly one of `notification_ids[]` / `delete_all`.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| notification_ids[] | One of | Specific `user_notifications.id` values |\n| delete_all | One of | `true` to delete every notification |",
            true,
            [
                ['key' => 'notification_ids[0]', 'value' => '1', 'description' => 'user_notifications.id to delete (provide this or delete_all).'],
                ['key' => 'delete_all', 'value' => '', 'description' => 'true to delete every notification (alternative to notification_ids[]).', 'disabled' => true],
            ]
        ),
    ],
];

// =====================================================================
// 10 Calendar
// =====================================================================
$calendar = [
    'name' => '10 Calendar',
    'description' => "The authenticated user's saved/registered/organized items (volunteer, learn & serve, event), plus ICS import.",
    'item' => [
        req(
            'List My Calendar Items',
            'GET',
            'my-calendar/',
            "## List My Calendar Items\nCombines saved bookmarks, registered items, and organized items (volunteer opportunities, learn & serve opportunities, and events) into one list.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| item_type | No | `volunteer_opportunity`, `learn_serve_opportunity`, or `event` |\n| is_saved | No | Restricts the *saved* bucket to this flag (default true) |\n| search | No | Title search (en/ar) |\n| start_date / end_date | No | Explicit date range filter |\n| time_range | No | `day`, `week`, `month`, or `year` (used if start/end not given) |\n| date | No | Anchor date for `time_range` (defaults to today) |",
            true,
            [],
            [
                ['key' => 'item_type', 'value' => '', 'description' => 'volunteer_opportunity, learn_serve_opportunity, or event.', 'disabled' => true],
                ['key' => 'is_saved', 'value' => 'true', 'description' => 'Restrict the saved bucket to this flag.'],
                ['key' => 'search', 'value' => '', 'description' => 'Title search (en/ar).', 'disabled' => true],
                ['key' => 'start_date', 'value' => '', 'description' => 'Explicit start date filter (YYYY-MM-DD).', 'disabled' => true],
                ['key' => 'end_date', 'value' => '', 'description' => 'Explicit end date filter (YYYY-MM-DD).', 'disabled' => true],
                ['key' => 'time_range', 'value' => '', 'description' => 'day, week, month, or year (used if start/end not given).', 'disabled' => true],
                ['key' => 'date', 'value' => '', 'description' => 'Anchor date for time_range (defaults to today).', 'disabled' => true],
            ]
        ),
        req(
            'Save Calendar Item',
            'POST',
            'my-calendar/',
            "## Save Calendar Item\nBookmarks exactly one of a volunteer opportunity, learn & serve opportunity, or event to the calendar.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| volunteer_opportunity_id | One of | `volunteer_opportunities.id` |\n| learn_serve_opportunity_id | One of | `learn_serve_opportunities.id` |\n| event_id | One of | `events.id` |\n| is_saved | No | Defaults to `true` |",
            true,
            [
                ['key' => 'volunteer_opportunity_id', 'value' => '{{opportunity_id}}', 'description' => 'volunteer_opportunities.id to save (exactly one of the three ids is required).'],
                ['key' => 'learn_serve_opportunity_id', 'value' => '', 'description' => 'learn_serve_opportunities.id to save.', 'disabled' => true],
                ['key' => 'event_id', 'value' => '', 'description' => 'events.id to save.', 'disabled' => true],
                ['key' => 'is_saved', 'value' => '1', 'description' => 'Save state to set (defaults to true).'],
            ]
        ),
        req(
            'Update Calendar Item',
            'PUT',
            'my-calendar/{{time_slot_id}}/',
            "## Update Calendar Item\nToggles the `is_saved` flag on your own calendar entry. `PATCH` is also accepted.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `my_calendars.id` (reuse `{{time_slot_id}}` as a generic numeric id placeholder) |\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| is_saved | No | `true`/`false` |",
            true,
            [
                ['key' => 'is_saved', 'value' => '0', 'description' => 'New saved state (true/false).'],
            ]
        ),
        req(
            'Remove Calendar Item',
            'DELETE',
            'my-calendar/{{time_slot_id}}/',
            "## Remove Calendar Item\nSoft-deletes your own calendar entry.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `my_calendars.id` (reuse `{{time_slot_id}}` as a generic numeric id placeholder) |",
            true
        ),
        req(
            'Upload ICS File',
            'POST',
            'upload-ics/',
            "## Upload ICS File\nUploads an `.ics` calendar file and returns its public URL plus a `webcal://` URL for one-tap calendar subscription.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| ics_file | Yes | `.ics` file to upload |",
            true,
            [
                ['key' => 'ics_file', 'type' => 'file', 'description' => 'The .ics calendar file to upload (required).'],
            ]
        ),
    ],
];

// =====================================================================
// 11 Sponsors
// =====================================================================
$sponsors = [
    'name' => '11 Sponsors',
    'description' => 'Sponsorship interest submissions and public sponsor directory (Django-style ViewSet, public CRUD).',
    'item' => [
        req(
            'List Sponsors',
            'GET',
            'sponsors/',
            "## List Sponsors\nReturns approved sponsors with their documents and lookup relations.\n\n**Auth:** Public (no auth).\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| sponsor_type | No | Matches `sponsor_type.value_en` (partial match) |",
            false,
            [],
            [
                ['key' => 'sponsor_type', 'value' => '', 'description' => 'Filter by sponsor type value_en (partial match).', 'disabled' => true],
            ]
        ),
        req(
            'Get Sponsor',
            'GET',
            'sponsors/{{sponsor_id}}/',
            "## Get Sponsor\nReturns a single approved sponsor with documents and lookup relations.\n\n**Auth:** Public (no auth).\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `sponsors.id` (collection var `{{sponsor_id}}`) |",
            false
        ),
        req(
            'Create Sponsor Application',
            'POST',
            'sponsors/',
            "## Create Sponsor Application\nSubmits a sponsorship interest form; stored in `pending` approval status.\n\n**Auth:** Public (no auth).\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| sponsor_type_id | No | master_choices.id |\n| org_name | No | Organization name (max 255) |\n| org_type_id | No | master_choices.id |\n| person_name | No | Contact person name (max 255) |\n| email | Yes | Contact email |\n| country_code / phone_number | No | Contact phone |\n| type_of_support_id | No | master_choices.id |\n| sponsorship_details / why_interested / resources_expected | No | Free text |\n| preferred_language | No | `en` or `ar` |\n| sponsor_logo | No | Logo image file |\n| new_sponsor_documents[] | No | Supporting document files |",
            false,
            [
                ['key' => 'sponsor_type_id', 'value' => '', 'description' => 'Sponsor type master_choices.id.', 'disabled' => true],
                ['key' => 'org_name', 'value' => 'Acme Foundation', 'description' => 'Sponsoring organization name (max 255).'],
                ['key' => 'org_type_id', 'value' => '', 'description' => 'Organization type master_choices.id.', 'disabled' => true],
                ['key' => 'person_name', 'value' => 'Sara Al-Sabah', 'description' => 'Contact person name (max 255).'],
                ['key' => 'email', 'value' => 'sponsor@example.com', 'description' => 'Contact email (required).'],
                ['key' => 'country_code', 'value' => '+965', 'description' => 'Contact dialing code (max 10).'],
                ['key' => 'phone_number', 'value' => '50003333', 'description' => 'Contact phone number (max 20).'],
                ['key' => 'type_of_support_id', 'value' => '', 'description' => 'Type of support master_choices.id.', 'disabled' => true],
                ['key' => 'sponsorship_details', 'value' => 'Interested in sponsoring the annual beach cleanup drive.', 'description' => 'Details about the intended sponsorship.'],
                ['key' => 'why_interested', 'value' => 'Aligns with our CSR goals.', 'description' => 'Motivation for sponsoring.'],
                ['key' => 'resources_expected', 'value' => 'Branding on event materials.', 'description' => 'Resources/benefits expected in return.'],
                ['key' => 'preferred_language', 'value' => 'en', 'description' => 'Preferred language: en or ar.'],
                ['key' => 'sponsor_logo', 'type' => 'file', 'description' => 'Optional sponsor logo image.'],
                ['key' => 'new_sponsor_documents[]', 'type' => 'file', 'description' => 'Optional supporting document file (repeat key for multiple).'],
            ]
        ),
        req(
            'Update Sponsor',
            'PUT',
            'sponsors/{{sponsor_id}}/',
            "## Update Sponsor\nUpdates a sponsor record. `PATCH` is also accepted. Uploading `new_sponsor_documents[]` soft-deletes previous documents and replaces them.\n\n**Auth:** Public (no auth) at the route level.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `sponsors.id` (collection var `{{sponsor_id}}`) |\n\n### Form-data fields\nSame fields as Create Sponsor Application (all optional on update).",
            false,
            [
                ['key' => 'sponsorship_details', 'value' => 'Updated sponsorship details.', 'description' => 'Updated sponsorship details text.'],
                ['key' => 'new_sponsor_documents[]', 'type' => 'file', 'description' => 'New document file — replaces existing documents (repeat key for multiple).'],
            ]
        ),
        req(
            'Delete Sponsor',
            'DELETE',
            'sponsors/{{sponsor_id}}/',
            "## Delete Sponsor\nSoft-deletes a sponsor record.\n\n**Auth:** Public (no auth) at the route level.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `sponsors.id` (collection var `{{sponsor_id}}`) |",
            false
        ),
    ],
];

// =====================================================================
// 12 Contact Us
// =====================================================================
$contactUs = [
    'name' => '12 Contact Us',
    'description' => 'Contact form submissions (Django-style ViewSet, public CRUD).',
    'item' => [
        req(
            'List Contact Messages',
            'GET',
            'contact-us/',
            "## List Contact Messages\nPaginated list of submitted contact messages.\n\n**Auth:** Public (no auth) at the route level.\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| page / limit | No | Pagination |",
            false,
            [],
            pageLimit()
        ),
        req(
            'Get Contact Message',
            'GET',
            'contact-us/{{contact_id}}/',
            "## Get Contact Message\nReturns a single contact message.\n\n**Auth:** Public (no auth) at the route level.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `contact_us.id` (collection var `{{contact_id}}`) |",
            false
        ),
        req(
            'Create Contact Message',
            'POST',
            'contact-us/',
            "## Create Contact Message\nSubmits a contact-us form message.\n\n**Auth:** Public (no auth).\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| name_en / name_ar | No | Bilingual sender name (max 100) |\n| email | Yes | Sender email |\n| message_en / message_ar | No | Bilingual message body |\n| primary_language | No | `en` or `ar` |",
            false,
            [
                ['key' => 'name_en', 'value' => 'John Doe', 'description' => 'Sender name in English (max 100).'],
                ['key' => 'name_ar', 'value' => 'جون دو', 'description' => 'Sender name in Arabic (max 100).'],
                ['key' => 'email', 'value' => 'john.doe@example.com', 'description' => 'Sender email (required).'],
                ['key' => 'message_en', 'value' => 'I would like more information about volunteering opportunities.', 'description' => 'Message body in English.'],
                ['key' => 'message_ar', 'value' => 'أرغب في معرفة المزيد عن فرص التطوع.', 'description' => 'Message body in Arabic.'],
                ['key' => 'primary_language', 'value' => 'en', 'description' => 'Preferred reply language: en or ar.'],
            ]
        ),
        req(
            'Update Contact Message',
            'PUT',
            'contact-us/{{contact_id}}/',
            "## Update Contact Message\nUpdates a contact message record. `PATCH` is also accepted.\n\n**Auth:** Public (no auth) at the route level.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `contact_us.id` (collection var `{{contact_id}}`) |\n\n### Form-data fields\nSame fields as Create Contact Message (all optional).",
            false,
            [
                ['key' => 'message_en', 'value' => 'Updated message body.', 'description' => 'Updated English message body.'],
            ]
        ),
        req(
            'Delete Contact Message',
            'DELETE',
            'contact-us/{{contact_id}}/',
            "## Delete Contact Message\nSoft-deletes a contact message record.\n\n**Auth:** Public (no auth) at the route level.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| id | `contact_us.id` (collection var `{{contact_id}}`) |",
            false
        ),
    ],
];

// =====================================================================
// 13 Statistics & Certificates
// =====================================================================
$statistics = [
    'name' => '13 Statistics & Certificates',
    'description' => 'Platform-wide and per-volunteer statistics, leaderboards, certificates, and QR downloads.',
    'item' => [
        req(
            'Volunteer Hours Statistics',
            'GET',
            'statistics/',
            "## Volunteer Hours Statistics\nReturns yearly volunteer-hours totals (current year + next 6), grand total, and completed-opportunity counters.\n\n**Auth:** Public (no auth).",
            false
        ),
        req(
            'Top Volunteers',
            'GET',
            'statistics/top/',
            "## Top Volunteers\nReturns the current year's top 10 individual volunteers and top 10 volunteer teams, each with badge info.\n\n**Auth:** Public (no auth).",
            false
        ),
        req(
            'User Certificates',
            'GET',
            'user-certificates/',
            "## User Certificates\nReturns every certified Learn & Serve certificate image for a given user.\n\n**Auth:** Public (no auth).\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| user_id | Yes | `users.id` whose certificates to return |",
            false,
            [],
            [
                ['key' => 'user_id', 'value' => '{{user_id}}', 'description' => 'users.id whose certificates to return (required).'],
            ]
        ),
        req(
            'Available Volunteers',
            'GET',
            'available-volunteers/',
            "## Available Volunteers\nLists verified volunteers eligible to be directly registered for a specific opportunity you created (excludes already-registered and, for volunteer opportunities, date-conflicting volunteers).\n\n**Auth required:** Bearer `{{token}}` — caller must own the opportunity.\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| opportunity_id | Yes | Target opportunity id |\n| type | Yes | `volunteer` or `learnserve` |\n| search | No | Search nickname/email/first/last name/civil id |\n| page / limit | No | Pagination |",
            true,
            [],
            [
                ['key' => 'opportunity_id', 'value' => '{{opportunity_id}}', 'description' => 'Target opportunity id you own (required).'],
                ['key' => 'type', 'value' => 'volunteer', 'description' => 'volunteer or learnserve (required).'],
                ['key' => 'search', 'value' => '', 'description' => 'Search nickname/email/first/last name/civil id.', 'disabled' => true],
                ...pageLimit(),
            ]
        ),
        req(
            'Volunteer Detail (Self)',
            'GET',
            'volunteer-detail/',
            "## Volunteer Detail (Self)\nReturns the authenticated volunteer's profile summary, current-year statistics, and recent registrations. Volunteers only.\n\n**Auth required:** Bearer `{{token}}`.\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| download | No | `true` requests a PDF export (stub — not yet implemented) |",
            true,
            [],
            [
                ['key' => 'download', 'value' => '', 'description' => 'true to request a PDF export (currently a stub response).', 'disabled' => true],
            ]
        ),
        req(
            'Download Volunteer QR Code',
            'GET',
            'download-qr-code/',
            "## Download Volunteer QR Code\nDownloads the stored QR code image file for a volunteer profile.\n\n**Auth:** Public (no auth) at the route level.\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| volunteer_id | Yes | `volunteer_profiles.id` |",
            false,
            [],
            [
                ['key' => 'volunteer_id', 'value' => '1', 'description' => 'volunteer_profiles.id to download the QR code for (required).'],
            ]
        ),
        req(
            'Sync My Statistics',
            'POST',
            'sync-statistics/',
            "## Sync My Statistics\nRecomputes and stores the authenticated volunteer's current-month statistics and profile totals from attendance data.\n\n**Auth required:** Bearer `{{token}}`.",
            true
        ),
    ],
];

$collection['item'] = [
    $auth,
    $base,
    $faq,
    $volunteerProfile,
    $organizationProfile,
    $opportunities,
    $events,
    $community,
    $notifications,
    $calendar,
    $sponsors,
    $contactUs,
    $statistics,
];

$json = json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

$dir = dirname($out);
if (! is_dir($dir)) {
    mkdir($dir, 0777, true);
}

file_put_contents($out, $json);

$methodCount = substr_count($json, '"method"');
$folderCount = count($collection['item']);

echo "Wrote {$out}\n";
echo 'Bytes: '.filesize($out)."\n";
echo "Top-level folders: {$folderCount}\n";
echo "Requests (method count): {$methodCount}\n";
