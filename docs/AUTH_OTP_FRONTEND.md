# Inactive account → OTP screen (frontend)

Paste this into Cursor on the Vue app.

## Problem

If a user registers, reaches the OTP step, then leaves without activating:

- Logging in with the same email **resends OTP** but the UI stays on Login and only shows an error.
- There is no link in the email or on the site to reopen the OTP page.

The backend now returns a machine-readable **`data.action`** so the frontend can open the OTP screen automatically.

---

## The key

```ts
data.action === 'verify_otp'
```

When you see this (on **success or fail**), navigate to the OTP / activate-account page and prefill `data.email`.

```ts
type OtpAction = {
  action: 'verify_otp'
  otp_type: 'register' | 'password'
  email: string
  is_active: boolean
}
```

---

## Where it appears

Base: `https://portal.fursa.raiyan.cc/api/`

### 1) First-time register — `POST /register/`

HTTP `201`, `key: success`

```json
{
  "key": "success",
  "code": 201,
  "data": {
    "id": 1,
    "email": "user@example.com",
    "action": "verify_otp",
    "otp_type": "register",
    "is_active": false
  }
}
```

→ Go to OTP page with that email.

### 2) Register again with the same email (account still inactive)

HTTP `403`, `key: fail`  
OTP is resent.

```json
{
  "key": "fail",
  "code": 403,
  "msg": "الحساب غير مفعل. أدخل رمز OTP المرسل إلى بريدك الإلكتروني.",
  "data": {
    "action": "verify_otp",
    "otp_type": "register",
    "email": "user@example.com",
    "is_active": false
  }
}
```

→ **Do not stay on the register form.** Open OTP page with `data.email`.

### 3) Login while account is not activated — `POST /login/`

Same `403` + `data.action = "verify_otp"`. OTP is resent.

→ Open OTP page. Do not treat this as a normal login error toast only.

### 4) Resend OTP — `POST /resend_otp_or_token/`

```json
{
  "key": "success",
  "code": 200,
  "data": {
    "action": "verify_otp",
    "otp_type": "register",
    "email": "user@example.com",
    "is_active": false
  }
}
```

### 5) Optional pre-check — `POST /check-user/`

```json
{
  "data": {
    "email": {
      "is_new_user": false,
      "is_active": false,
      "needs_activation": true
    }
  }
}
```

If `needs_activation === true`, you can send them to OTP (and call resend) instead of register.

---

## OTP page APIs (already exist)

Submit code:

```http
POST /api/verify_otp_or_token/
Content-Type: application/json

{
  "email": "user@example.com",
  "type": "register",
  "otp": "123456"
}
```

Resend:

```http
POST /api/resend_otp_or_token/

{
  "email": "user@example.com",
  "type": "register"
}
```

The activation email contains the **OTP code**, not a magic link. The website must provide the OTP input page.

---

## Implementation rule

```ts
function handleAuthResponse(json: any) {
  if (json?.data?.action === 'verify_otp') {
    router.push({
      path: '/verify-otp', // your existing OTP route
      query: { email: json.data.email, type: json.data.otp_type || 'register' },
    })
    return
  }

  if (json.key === 'fail') {
    showError(json.msg)
  }
}
```

Call this after **register**, **login**, and **resend**.

## Checklist

- [ ] After `POST /register/` (`201`), go to OTP using `data.email`
- [ ] After `POST /login/` if `data.action === 'verify_otp'`, go to OTP (do not only toast)
- [ ] After `POST /register/` if `403` + `action === 'verify_otp'`, go to OTP
- [ ] OTP page has email field (readonly/prefilled) + OTP inputs + resend button
- [ ] Resend uses `POST /resend_otp_or_token/` with `type: "register"`
