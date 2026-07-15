<?php

/**
 * Generates Fursa API Postman Collection v2.1
 * Run: php tools/generate_postman_collection.php
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

    // trailing slash marker for empty last path segment style like login/
    if (str_ends_with(explode('?', $path)[0], '/') && $url['path'] !== []) {
        // keep Postman path without forced empty segment; raw has the slash
    }

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
        'description' => "# Fursa (فرصة) REST API\n\nProfessional Postman collection for the Laravel Fursa mobile/API backend.\n\n## Setup\n1. Import this collection.\n2. `base_url` is already set to `http://fursa.test/api/`.\n3. Run **Auth → Login** with seeded credentials (`volunteer@fursa.local` / `Password1`).\n4. The test script stores `token` automatically for protected routes.\n\n## Authentication\nProtected endpoints use **Bearer Token** via collection variable `{{token}}`.\nHeader sent: `Authorization: Bearer {{token}}`\n\n> The API also accepts legacy `Authorization: Token <key>`.\n\n## Body convention\nAll write requests use **form-data** (not raw JSON), matching Laravel multipart/form handling.\n\n## Seeded demo accounts\n| Email | Password | Type |\n|-------|----------|------|\n| volunteer@fursa.local | Password1 | Volunteer |\n| organization@fursa.local | Password1 | Organization |\n",
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
    ],
    'item' => [],
];

// ——— Auth ———
$authFolder = [
    'name' => 'Auth',
    'description' => 'Registration, login, OTP verification, password reset, social auth, and public profiles.',
    'item' => [
        [
            'name' => 'Registration',
            'description' => 'Create volunteer or organization accounts.',
            'item' => [
                req(
                    'Register Volunteer',
                    'POST',
                    'register/',
                    "## Register Volunteer\nCreates a new volunteer account and triggers activation (OTP/email) based on `AUTHENTICATION_METHOD`.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| email | Yes | Unique account email |\n| password | Yes* | Min 8 chars, at least 1 uppercase + 1 digit (e.g. `Password1`) |\n| user_type | No | Must be `volunteer` (default) |\n| first_name | No | First name |\n| last_name | No | Last name |\n| phone_number | No | Mobile number (max 15) |\n| country_code | No | Dial code e.g. `+965` |\n| civil_id | Yes (volunteer) | Kuwait civil ID (max 12, unique) |\n| nickname | No | Display nickname |\n| gender | No | `master_choices.id` for gender |\n| nationality | No | e.g. `KW` |\n| birth_year | No | Integer birth year |\n| dob | No | `YYYY-MM-DD` — if age < 18, emergency fields become required |\n| preferred_language | No | `en` or `ar` |\n| profile_pic | No | Image file |\n| organization_id | No | Optional linked entity profile id |\n| emergency_contact_* | Conditional | Required when volunteer age < 18 |\n\n*Password strongly recommended for normal signup.",
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
                        ['key' => 'nickname', 'value' => 'ahmed_vol', 'description' => 'Public nickname on volunteer profile.'],
                        ['key' => 'gender', 'value' => '1', 'description' => 'Gender master choice ID (`master_choices.id`). Get IDs from Base → Get Choices.'],
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
                    "## Register Organization (Entity)\nCreates a new organization/entity account. Profile usually stays pending until admin approval.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| email | Yes | Unique account email |\n| password | Yes* | Strong password rule |\n| user_type | Yes | Must be `organization` |\n| company_name | Recommended | Entity display name |\n| nickname | No | Entity nickname |\n| organizer_type | No | `master_choices.id` |\n| registration_number | No | Official registration number |\n| license_number | No | License number |\n| documents[] | No | Supporting document files |\n| latitude / longitude | No | Map coordinates |\n| profile_pic | No | Logo/avatar image |",
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
            ],
        ],
        req(
            'Login',
            'POST',
            'login/',
            "## Login\nAuthenticates with email/password and returns `auth_token`.\n\n**Token path in response:** `data.data.auth_token`\n\nA test script saves it into collection variable `{{token}}` automatically.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| email | Yes | Registered account email |\n| password | Yes | Account password |\n| rememberMe | No | `true`/`1` extends token lifetime (~30d vs ~1d) |\n| is_opportunity | No | When true, restricts login to volunteer accounts |",
            false,
            [
                ['key' => 'email', 'value' => '{{email}}', 'description' => 'Account email. Default seeded volunteer: volunteer@fursa.local'],
                ['key' => 'password', 'value' => '{{password}}', 'description' => 'Account password. Seeded demo password: Password1'],
                ['key' => 'rememberMe', 'value' => '1', 'description' => 'Pass 1/true for longer-lived auth token.'],
                ['key' => 'is_opportunity', 'value' => '0', 'description' => 'Pass 1/true to allow only volunteer users.'],
            ],
            [],
            $loginEvent
        ),
        req(
            'Forgot Password',
            'POST',
            'forgot-password/',
            "## Forgot Password\nStarts password reset flow (sends OTP or reset link depending on configuration).\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| email | Yes | Account email that will receive the reset OTP/link |",
            false,
            [
                ['key' => 'email', 'value' => '{{email}}', 'description' => 'Registered user email to receive password reset OTP/link.'],
            ]
        ),
        req(
            'Verify OTP Or Token',
            'POST',
            'verify_otp_or_token/',
            "## Verify OTP Or Token\nVerifies OTP for `register` or `password` flows when `AUTHENTICATION_METHOD=OTP`.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| email | Yes | Account email |\n| type | Yes | `register` or `password` |\n| otp | Yes | OTP code from email |\n\n### Response tokens\n- `type=register` → `data.token` is API auth key (saved to `{{token}}`)\n- `type=password` → `data.token` is reset token (saved to `{{reset_token}}`)",
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
            "## Resend OTP Or Token\nResends activation or password-reset OTP/link.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| email | Yes | Target account email |\n| type | Yes | `register` or `password` |",
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
            "## Change Password\nSets a new password using either a reset `token` (from forgot-password OTP verify) **or** `old_password`.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| email | Yes | Account email |\n| password | Yes | New password (min 8, uppercase + digit) |\n| token | Conditional | Reset token from verify OTP (`type=password`) |\n| old_password | Conditional | Current password if changing without reset token |",
            false,
            [
                ['key' => 'email', 'value' => '{{email}}', 'description' => 'Account email.'],
                ['key' => 'password', 'value' => 'Password1', 'description' => 'New password (min 8, must include uppercase + digit).'],
                ['key' => 'token', 'value' => '{{reset_token}}', 'description' => 'Password-reset token from Verify OTP (type=password).'],
                ['key' => 'old_password', 'value' => '', 'description' => 'Current password if not using reset token.', 'disabled' => true],
            ]
        ),
        req(
            'Check User',
            'POST',
            'check-user/',
            "## Check User\nChecks whether an email and/or nickname is already taken.\nProvide at least one of `email` or `nickname`.\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| email | One of | Email availability check |\n| nickname | One of | Nickname availability on volunteer/org profiles |",
            false,
            [
                ['key' => 'email', 'value' => 'volunteer@fursa.local', 'description' => 'Email to check for existing registration.'],
                ['key' => 'nickname', 'value' => 'ahmed_vol', 'description' => 'Nickname to check across volunteer/organization profiles.'],
            ]
        ),
        [
            'name' => 'Social Auth',
            'description' => 'Google/LinkedIn social login and LinkedIn OAuth exchange.',
            'item' => [
                req(
                    'Social Auth',
                    'POST',
                    'social-auth/',
                    "## Social Auth\nLogin/register via social provider payload (Google/LinkedIn).\nReturns `auth_token` at `data.auth_token` (test script still stores `{{token}}` if present).\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| email | Yes | Social account email |\n| social_media_provider | Yes | `google` or `linkedin` |\n| social_media_id | No | Provider user id |\n| first_name / last_name | No | Name from provider |\n| social_profile_pic_url | No | Avatar URL |\n| user_type | No | Defaults to `volunteer` for new users |\n| civil_id | Yes for new volunteer | Civil ID |\n| nickname / company_name | No | Profile fields |",
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
                        ['key' => 'nickname', 'value' => 'social_vol', 'description' => 'Optional nickname for new profile.'],
                        ['key' => 'company_name', 'value' => '', 'description' => 'Optional company name for organization social signup.', 'disabled' => true],
                    ],
                    [],
                    $loginEvent
                ),
                req(
                    'LinkedIn Callback',
                    'POST',
                    'linkedin/callback/',
                    "## LinkedIn Callback\nExchanges LinkedIn OAuth `code` for LinkedIn profile + LinkedIn `access_token` (not the Fursa API token).\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| code | Yes | LinkedIn OAuth authorization code |\n| redirect_uri | Yes | Exact redirect URI used in the authorize step |",
                    false,
                    [
                        ['key' => 'code', 'value' => 'AQT_LINKEDIN_AUTH_CODE', 'description' => 'Authorization code returned by LinkedIn OAuth redirect.'],
                        ['key' => 'redirect_uri', 'value' => 'https://your-frontend.example/linkedin/callback', 'description' => 'Must match the redirect_uri configured in LinkedIn app + authorize URL.'],
                    ]
                ),
            ],
        ],
        req(
            'Public Profile',
            'GET',
            'public-profile/{{user_id}}/',
            "## Public Profile\nReturns a public user profile by numeric user id.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| userId | Target `users.id` (collection var `{{user_id}}`) |",
            false
        ),
        req(
            'Get Account',
            'GET',
            'account/',
            "## Get Account\nReturns the authenticated user's account payload.\n\n**Auth required:** Bearer `{{token}}`",
            true
        ),
        req(
            'Update Account',
            'PUT',
            'account/',
            "## Update Account\nUpdates account fields for the authenticated user. Supports profile image upload via form-data.\n\n**Auth required:** Bearer `{{token}}`\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| profile_pic | No | New avatar image file |\n| first_name / last_name | No | Name fields |\n| email | No | New unique email |\n| phone_number / country_code | No | Phone |\n| birth_year / nationality / preferred_language | No | Profile meta |\n| civil_id | No | Unique civil ID |\n| emergency_contact_* | No | Emergency contact block |",
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
];

// ——— Base ———
$baseFolder = [
    'name' => 'Base',
    'description' => 'Shared lookups, banners, image proxy, and license checks.',
    'item' => [
        req(
            'Get Choices',
            'GET',
            'choices/{{choice_type}}/',
            "## Get Choices\nReturns master choice options for a choice type slug/name.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| choiceType | Choice type key (collection var `{{choice_type}}`, e.g. `gender`, `org_type`) |",
            false
        ),
        req(
            'Banner Images',
            'GET',
            'banner-images/',
            "## Banner Images\nPublic list of banner images plus platform stats (no auth).",
            false
        ),
        req(
            'Proxy Image',
            'GET',
            'proxy-image/',
            "## Proxy Image\nProxies a remote image URL or a storage path for CORS-friendly delivery. Returns image bytes.\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| url | One of | Absolute remote image URL |\n| key | One of | Public disk relative path |\n| path | Alias | Same as `key` |",
            false,
            [],
            [
                ['key' => 'url', 'value' => 'https://via.placeholder.com/150', 'description' => 'Remote image URL to proxy.'],
                ['key' => 'key', 'value' => '', 'description' => 'Alternative: relative public-disk path.', 'disabled' => true],
                ['key' => 'path', 'value' => '', 'description' => 'Alias of `key`.', 'disabled' => true],
            ]
        ),
        req(
            'Proxy Image Options',
            'OPTIONS',
            'proxy-image/',
            "## Proxy Image (CORS Preflight)\nOPTIONS preflight for the image proxy endpoint.",
            false
        ),
        req(
            'Check License Requirement',
            'GET',
            'check-license-requirement/',
            "## Check License Requirement\nReturns whether the authenticated user's role requires a license upload.\n\n**Auth required:** Bearer `{{token}}`",
            true
        ),
    ],
];

// ——— FAQ ———
$faqFolder = [
    'name' => 'FAQ',
    'description' => 'Frequently asked questions.',
    'item' => [
        req(
            'List FAQs',
            'GET',
            'faqs/',
            "## List FAQs\nPaginated public FAQ list.\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| limit | No | Page size 1–100 (default 10) |\n| page | No | Page number |",
            false,
            [],
            [
                ['key' => 'limit', 'value' => '10', 'description' => 'Number of FAQs per page (1–100).'],
                ['key' => 'page', 'value' => '1', 'description' => 'Page number.'],
            ]
        ),
    ],
];

// ——— Volunteer ———
$volunteerFolder = [
    'name' => 'Volunteer',
    'description' => 'Volunteer profile, discovery, QR, and public verification.',
    'item' => [
        [
            'name' => 'Profile',
            'item' => [
                req(
                    'Get Volunteer Profile',
                    'GET',
                    'volunteer-profile/',
                    "## Get Volunteer Profile\nReturns the authenticated volunteer's profile.\n\n**Auth required:** Bearer `{{token}}`",
                    true
                ),
                req(
                    'Update Volunteer Profile',
                    'PUT',
                    'volunteer-profile/',
                    "## Update Volunteer Profile\nUpdates volunteer profile fields and optionally syncs interests.\n\n**Auth required:** Bearer `{{token}}`\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| civil_id | Yes | Civil ID |\n| nickname / occupation / experience | No | Profile text |\n| health_concerns | No | `yes`/`no` style flag |\n| is_public / is_verified | No | Booleans as `1`/`0` |\n| gender | No | master_choices.id |\n| email / nationality / dob / birth_year | No | User fields |\n| *_link | No | Social URLs |\n| interest_ids[0..] | No | Interest IDs to sync |",
                    true,
                    [
                        ['key' => 'civil_id', 'value' => '290010100001', 'description' => 'Required civil ID for volunteer profile update.'],
                        ['key' => 'nickname', 'value' => 'ahmed_vol', 'description' => 'Volunteer nickname.'],
                        ['key' => 'occupation', 'value' => 'Software Engineer', 'description' => 'Current occupation.'],
                        ['key' => 'experience', 'value' => '2 years volunteering in community events', 'description' => 'Experience summary text.'],
                        ['key' => 'health_concerns', 'value' => 'no', 'description' => 'Health concerns flag (`yes`/`no` or free text as accepted by API).'],
                        ['key' => 'is_public', 'value' => '1', 'description' => 'Make profile public (`1`/`0`).'],
                        ['key' => 'is_verified', 'value' => '0', 'description' => 'Verified flag (`1`/`0`) — usually managed by system/admin.'],
                        ['key' => 'gender', 'value' => '1', 'description' => 'Gender master_choices.id.'],
                        ['key' => 'email', 'value' => '{{email}}', 'description' => 'Optional email update (unique).'],
                        ['key' => 'nationality', 'value' => 'KW', 'description' => 'Nationality value.'],
                        ['key' => 'dob', 'value' => '1995-05-15', 'description' => 'Date of birth `YYYY-MM-DD`.'],
                        ['key' => 'birth_year', 'value' => '1995', 'description' => 'Birth year integer.'],
                        ['key' => 'instagram_link', 'value' => 'https://instagram.com/example', 'description' => 'Instagram profile URL.'],
                        ['key' => 'whatsapp_link', 'value' => 'https://wa.me/96550000001', 'description' => 'WhatsApp link.'],
                        ['key' => 'linkedin_link', 'value' => 'https://linkedin.com/in/example', 'description' => 'LinkedIn profile URL.'],
                        ['key' => 'facebook_link', 'value' => 'https://facebook.com/example', 'description' => 'Facebook profile URL.'],
                        ['key' => 'twitter_link', 'value' => 'https://x.com/example', 'description' => 'X/Twitter profile URL.'],
                        ['key' => 'interest_ids[0]', 'value' => '1', 'description' => 'First interest id to sync (from interests table).'],
                        ['key' => 'interest_ids[1]', 'value' => '2', 'description' => 'Second interest id (optional).'],
                    ]
                ),
                req(
                    'Get Volunteer QR Code',
                    'GET',
                    'volunteer-profile/qr-code/',
                    "## Get Volunteer QR Code\nReturns QR code URLs / verification link for the authenticated volunteer.\n\n**Auth required:** Bearer `{{token}}`",
                    true
                ),
            ],
        ],
        req(
            'List All Volunteers',
            'GET',
            'all-volunteers/',
            "## List All Volunteers\nSearchable/paginated list of volunteers.\n\n**Auth required:** Bearer `{{token}}`\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| search | No | Search nickname/name/email |\n| page | No | Page number |\n| limit | No | Page size 1–100 (default 20) |",
            true,
            [],
            [
                ['key' => 'search', 'value' => '', 'description' => 'Optional search term (nickname, name, email).'],
                ['key' => 'page', 'value' => '1', 'description' => 'Page number.'],
                ['key' => 'limit', 'value' => '20', 'description' => 'Results per page (1–100).'],
            ]
        ),
        req(
            'Verify Volunteer By UUID',
            'GET',
            'verify/{{volunteer_uuid}}/',
            "## Verify Volunteer By UUID\nPublic verification endpoint using volunteer profile UUID.\n\n### Path params\n| Param | Description |\n|-------|-------------|\n| uuid | Volunteer profile UUID (`{{volunteer_uuid}}`) |\n\nReplace `{{volunteer_uuid}}` with a real UUID from a volunteer profile / QR payload.",
            false
        ),
    ],
];

// ——— Organization ———
$organizationFolder = [
    'name' => 'Organization',
    'description' => 'Entity/organization profile and documents.',
    'item' => [
        [
            'name' => 'Profile',
            'item' => [
                req(
                    'Get Organization Profile',
                    'GET',
                    'organization-profile/',
                    "## Get Organization Profile\nReturns the authenticated organization's profile.\n\n**Auth required:** Bearer `{{token}}`\n\nTip: Login first with `organization@fursa.local` / `Password1`.",
                    true
                ),
                req(
                    'Update Organization Profile',
                    'PUT',
                    'organization-profile/',
                    "## Update Organization Profile\nUpdates entity profile fields and social links.\n\n**Auth required:** Bearer `{{token}}`\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| nickname / company_name | No | Identity |\n| sector / organizer_type | No | master_choices IDs |\n| registration_number / license_number | No | Legal numbers |\n| latitude / longitude | No | Location |\n| nationality | No | On user |\n| social *_link | No | Social URLs |",
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
                    "## Update Organization Documents\nSyncs organization documents: keep IDs in `existing_ids`, upload new files via `new_documents[]`. Docs not listed in `existing_ids` are soft-deleted.\n\n**Auth required:** Bearer `{{token}}`\n\n### Form-data fields\n| Field | Required | Description |\n|-------|----------|-------------|\n| existing_ids[0..] | No | Document IDs to keep |\n| new_documents[] | No | New files to upload |",
                    true,
                    [
                        ['key' => 'existing_ids[0]', 'value' => '1', 'description' => 'Existing organization_documents.id to keep.'],
                        ['key' => 'existing_ids[1]', 'value' => '2', 'description' => 'Another existing document id to keep (optional).', 'disabled' => true],
                        ['key' => 'new_documents[]', 'type' => 'file', 'description' => 'New document file to upload (attach file in Postman).'],
                    ]
                ),
            ],
        ],
        req(
            'List Organizations',
            'GET',
            'list-organizations/',
            "## List Organizations\nPaginated approved organizations (excludes current user).\n\n**Auth required:** Bearer `{{token}}`\n\n### Query params\n| Param | Required | Description |\n|-------|----------|-------------|\n| name | No | Filter by company/nickname |\n| page | No | Page number |\n| limit | No | Page size 1–100 |",
            true,
            [],
            [
                ['key' => 'name', 'value' => '', 'description' => 'Optional name/nickname filter.'],
                ['key' => 'page', 'value' => '1', 'description' => 'Page number.'],
                ['key' => 'limit', 'value' => '20', 'description' => 'Results per page (1–100).'],
            ]
        ),
    ],
];

$collection['item'] = [
    $authFolder,
    $baseFolder,
    $faqFolder,
    $volunteerFolder,
    $organizationFolder,
];

file_put_contents($out, json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
echo "Wrote {$out}\n";
echo 'Bytes: '.filesize($out)."\n";
echo 'Endpoints approx: '.substr_count(file_get_contents($out), '"method"')."\n";
