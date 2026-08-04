# Social Auth (Google + LinkedIn) — Backend vs Vue Frontend

> آخر تحديث: أغسطس 2026 — متوافق مع Laravel API الحالي.

**الخلاصة السريعة**

| | جاهز؟ | مين يفعل؟ |
|---|---|---|
| Backend API لـ Google / LinkedIn login & register | **نعم** | Backend (موجود) |
| Google Sign-In UI + OAuth في المتصفح | **لا (عند الفرونت)** | مبرمج Vue |
| LinkedIn OAuth redirect في المتصفح | **لا (عند الفرونت)** | مبرمج Vue |
| LinkedIn Client ID / Secret في `.env` السيرفر | مطلوب للـ LinkedIn callback | Backend / DevOps |
| Google Client ID (للمتصفح) | مطلوب للزر في Vue | Frontend + صاحب حساب Google Cloud |
| Google Client Secret على الـ Backend | **مش مطلوب** | — |

الـ Backend **ما بيعملش** Google OAuth لوحده.  
الـ Frontend (Vue) بيجيب بيانات المستخدم من Google/LinkedIn، وبعدين ينادي Backend عشان يسجّل الدخول / ينشئ الحساب ويرجع `auth_token`.

---

## URLs الحالية

| | القيمة |
|--|--------|
| Frontend (موقع) | `https://fursa.raiyan.cc` |
| Backend API | `https://portal.fursa.raiyan.cc/api/` |
| LinkedIn redirect المسجّل حاليًا في App البرودكشن القديمة | `https://joinforsa.net/linkedin-callback` |

> **مهم جدًا لـ LinkedIn:**  
> لو الفرونت شغّال على `fursa.raiyan.cc`، لازم:
> 1. تضيف Redirect URI جديد في LinkedIn Developer App، مثل:  
>    `https://fursa.raiyan.cc/linkedin-callback`
> 2. تستخدم **نفس** الرابط حرفيًا في:
>    - Authorize URL في Vue
>    - body بتاع `POST /api/linkedin/callback/`
>    - `LINKEDIN_REDIRECT_URI` في `.env` بتاع Laravel  
> لو استخدمت رابط مختلف عن المسجّل في LinkedIn App → الـ callback هيفشل.

---

## Endpoints الموجودة أصلًا

Base URL: `https://portal.fursa.raiyan.cc/api/`

### 1) `POST /api/social-auth/`

تسجيل دخول أو تسجيل جديد بعد ما الفرونت يخلص OAuth ويجيب بيانات المستخدم.

**Auth:** Public (بدون Bearer)

**Body (JSON أو form-data):**

| Field | Required | Notes |
|-------|----------|--------|
| `email` | Yes | من Google / LinkedIn |
| `social_media_provider` | Yes | `google` أو `linkedin` |
| `social_media_id` | No | Provider user id (`sub` / Google id) |
| `first_name` | No | |
| `last_name` | No | |
| `social_profile_pic_url` | No | URL صورة البروفايل |
| `user_type` | No | افتراضي `volunteer` — أو `organization` |
| `civil_id` | **Yes لو مستخدم جديد متطوع** | الرقم المدني (max 12) |
| `nickname` | No | |
| `company_name` | No | للـ organization |

**سلوك الـ Backend:**

- لو الإيميل موجود بحساب **كلمة مرور عادي** → Error 400 (مش social).
- لو الإيميل مش موجود → إنشاء يوزر social + `is_active=true` + بدون password.
- لو الإيميل موجود وهو social أصلاً → تحديث بيانات خفيفة + إصدار توكن.
- متطوع جديد **لازم** `civil_id` وإلا Error 400.

**Success response (مختصر):**

```json
{
  "key": "success",
  "msg": "...",
  "code": 200,
  "data": {
    "id": 1,
    "email": "user@gmail.com",
    "first_name": "...",
    "last_name": "...",
    "user_type": "volunteer",
    "is_new_user": true,
    "auth_token": "........",
    "social_media_provider": "google",
    "social_media_id": "...."
  }
}
```

> الـ `data` ممكن يحتوي حقول إضافية من بروفايل المستخدم (حسب `WebsiteLoginUserResource`). المهم للفرونت: `auth_token` و `is_new_user`.

الفرونت يخزّن `data.auth_token` ويستعمله كـ:

```http
Authorization: Token <auth_token>
```

---

### 2) `POST /api/linkedin/callback/`

استبدال `code` بتاع LinkedIn OAuth ببروفايل المستخدم.

**Auth:** Public

**Body:**

| Field | Required |
|-------|----------|
| `code` | Yes — من redirect query بعد LinkedIn login |
| `redirect_uri` | Yes — **نفس** الـ redirect_uri المستخدم في authorize URL وفي LinkedIn App |

**Success:**

```json
{
  "key": "success",
  "data": {
    "linkedin_id": "...",
    "first_name": "...",
    "last_name": "...",
    "email": "...",
    "picture": "...",
    "access_token": "..."
  }
}
```

بعدها الفرونت ينادي `POST /api/social-auth/` بالقيم دي:

- `email` ← `data.email`
- `social_media_provider` ← `linkedin`
- `social_media_id` ← `data.linkedin_id`
- `first_name` / `last_name` / `social_profile_pic_url` ← من الرد (`picture`)

---

## تقسيم الشغل

### أ) Backend (تم / جاهز)

1. Routes شغالة:
   - `POST /api/social-auth/`
   - `POST /api/linkedin/callback/`
2. LinkedIn keys موجودة في `.env` من Production القديمة.
3. **Google:** مش محتاج Client Secret على الـ Backend.
4. CORS لازم يسمح بأصل موقع Vue (`https://fursa.raiyan.cc`).

> ملاحظة: لو مفيش LinkedIn keys في `.env`، `linkedin/callback` هيفشل. `social-auth` نفسه شغال لأي provider لو الفرونت بعت البيانات صح.

**للفرونت فقط (مش Secret):**  
LinkedIn **Client ID** العام (يُستخدم في Authorize URL):

```text
78t1ltk7ivlu2e
```

Client Secret **مايتبعتش** للفرونت ولا يتحط في Vue.

---

### ب) Frontend Vue — المطلوب من مبرمج الفرونت

#### 1) Google Sign-In

1. من [Google Cloud Console](https://console.cloud.google.com/) أنشئ OAuth Client ID من نوع **Web**.
2. Authorized JavaScript origins: `https://fursa.raiyan.cc` (+ `http://localhost:3000` للتطوير).
3. في Vue استخدم Google Identity Services (GIS) أو مكتبة مناسبة.
4. بعد نجاح Google، خد من البروفايل: `email`, `sub` (id), `given_name`, `family_name`, `picture`.
5. نادِ:

```http
POST https://portal.fursa.raiyan.cc/api/social-auth/
Content-Type: application/json
Lang: ar   # أو en حسب لغة الواجهة

{
  "email": "user@gmail.com",
  "social_media_provider": "google",
  "social_media_id": "<google-sub>",
  "first_name": "...",
  "last_name": "...",
  "social_profile_pic_url": "https://...",
  "user_type": "volunteer",
  "civil_id": "290010100099"
}
```

6. لو اليوزر **جديد** ومتطوع: اعرض فورم يطلب `civil_id` قبل/مع أول `social-auth` (الـ API بيرفض بدونها).
7. خزّن `auth_token` واستخدمه في الطلبات المحمية.

**مفيش** endpoint اسمه `google/callback` على الـ Backend — كله من الفرونت → `social-auth`.

---

#### 2) LinkedIn Login

**Flow:**

```
Vue → LinkedIn authorize URL → user login
  → redirect إلى https://fursa.raiyan.cc/linkedin-callback?code=...
     (أو joinforsa.net/linkedin-callback لو لسه نفس الـ App القديم)
  → Vue ياخد code
  → POST /api/linkedin/callback/ { code, redirect_uri }
  → Vue ياخد email / linkedin_id / name / picture
  → POST /api/social-auth/ { provider: linkedin, ... }
  → خزّن auth_token
```

**Authorize URL مثال (دومين Raiyan):**

```
https://www.linkedin.com/oauth/v2/authorization
  ?response_type=code
  &client_id=78t1ltk7ivlu2e
  &redirect_uri=https://fursa.raiyan.cc/linkedin-callback
  &scope=openid%20profile%20email
  &state=<random>
```

> لازم نفس `redirect_uri` يكون مضاف في LinkedIn Developer App.

**مهم:** `redirect_uri` في authorize URL = نفس القيمة في `POST /api/linkedin/callback/` حرفيًا (بما فيها `/` أو `-`).

المسار القديم في App البرودكشن كان:

```text
https://joinforsa.net/linkedin-callback
```

(لاحظ: `linkedin-callback` بـ **شرطة** `-` مش `/linkedin/callback`).

---

#### 3) UX مطلوب من الفرونت

| حالة | المطلوب من Vue |
|------|----------------|
| زر Google / LinkedIn في صفحات Login & Register | نعم |
| مستخدم جديد متطوع بدون `civil_id` | فورم يكمل الرقم المدني ثم يعيد `social-auth` |
| حساب موجود بباسورد عادي | اعرض رسالة الـ API: الحساب موجود بتسجيل دخول بكلمة مرور |
| نجاح الدخول | خزّن التوكن + روح للـ home / intended route |
| لغة الواجهة | ابعت `Lang: en` أو `Lang: ar` مع الطلبات |

---

## Checklist سريع تبعته للفرونت

- [ ] Google Client ID (Web) جاهز ومربوط على دومين الموقع (`VITE_GOOGLE_CLIENT_ID`)
- [ ] زر Google Sign-In ينادي `POST /api/social-auth/` بعد نجاح Google
- [ ] LinkedIn App: Redirect URI مطابق لصفحة callback في Vue
- [ ] صفحة callback في Vue تاخد `code` وتنادي `POST /api/linkedin/callback/`
- [ ] بعدها `POST /api/social-auth/` بـ `social_media_provider: "linkedin"`
- [ ] جمع `civil_id` للمتطوع الجديد قبل/مع أول social-auth
- [ ] تخزين `data.auth_token` واستخدامه: `Authorization: Token ...`
- [ ] إرسال هيدر `Lang` حسب لغة الموقع
- [ ] **مفيش** LinkedIn Client Secret في كود Vue

---

## Checklist Backend

- [x] `SOCIAL_AUTH_LINKEDIN_OAUTH2_KEY` / `SECRET` في `.env`
- [ ] `LINKEDIN_REDIRECT_URI` مطابق لصفحة Vue النهائية (حدّثه لو اتحولت من joinforsa.net لـ fursa.raiyan.cc)
- [ ] `php artisan config:clear` بعد أي تعديل `.env` على السيرفر
- [ ] CORS يسمح لـ `https://fursa.raiyan.cc`
- [ ] اختبار Postman / ApiDog لـ `social-auth` و `linkedin/callback`

---

## إيه اللي **مش** مطلوب

- Backend **مش** هيحط Google Client Secret في الفلوس الحالي.
- Frontend **مش** هيخزّن LinkedIn Client Secret في Vue (السر على السيرفر فقط).
- Google Client ID عام للمتصفح → عادي في Vue env (`VITE_GOOGLE_CLIENT_ID`).

---

## رسائل Error مهمة للفرونت

| Situation | HTTP | معنى |
|-----------|------|------|
| إيميل موجود بباسورد | 400 | خلّي المستخدم يعمل login عادي |
| متطوع جديد بدون civil_id | 400 | افتح فورم الرقم المدني |
| LinkedIn code/redirect غلط | 400 | راجع redirect_uri وClient keys |
| Validation | 422 | حقول ناقصة/غلط |

---

## ملخص جملة واحدة تبعتهاله

> الـ Backend جاهز: `POST /api/social-auth/` (Google + LinkedIn) و `POST /api/linkedin/callback/` على `https://portal.fursa.raiyan.cc/api/`.  
> شغل Vue = أزرار Google/LinkedIn OAuth في المتصفح، جمع `civil_id` للمستجدين، ثم مناداة الـ APIs وتخزين `auth_token`.  
> Google Client ID في Vue فقط (مفيش Google Secret على الباك).  
> LinkedIn Client ID للـ authorize URL؛ الـ Secret على السيرفر فقط.  
> خلي `redirect_uri` لـ LinkedIn متطابق 100% بين Vue و LinkedIn App وطلب الـ API.
