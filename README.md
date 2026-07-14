# Fursa Backend (Laravel 10)

إعادة بناء مشروع Fursa من Django/DRF إلى Laravel 10.

## المتطلبات
- PHP 8.1+
- Composer
- SQLite (افتراضي محلياً) أو PostgreSQL

## التثبيت

```bash
cd D:\Heard\fursa
composer install
copy .env.example .env
php artisan key:generate
# SQLite: تأكد من وجود database/database.sqlite
php artisan migrate --seed
php artisan storage:link
php artisan serve
```

## المصادقة
Header مطابق لـ Django DRF:

```
Authorization: Token <key>
```

مدة التوكن: يوم واحد، أو 30 يوماً مع `rememberMe=true`.

## Endpoints المنفّذة حالياً

| Method | Path |
|--------|------|
| GET | `/health/` |
| POST | `/api/register/` |
| POST | `/api/login/` |
| POST | `/api/forgot-password/` |
| POST | `/api/change-password/` |
| POST | `/api/verify_otp_or_token/` |
| POST | `/api/resend_otp_or_token/` |
| POST | `/api/check-user/` |
| POST | `/api/social-auth/` |
| POST | `/api/linkedin/callback/` |
| GET | `/api/public-profile/{id}/` |
| GET/PUT/PATCH | `/api/account/` |
| GET | `/api/choices/{choiceType}/` |
| GET | `/api/banner-images/` |
| GET | `/api/proxy-image/` |
| GET | `/api/check-license-requirement/` |
| GET | `/api/faqs/` |
| GET/PUT/PATCH | `/api/volunteer-profile/` |
| GET | `/api/all-volunteers/` |
| GET | `/api/volunteer-profile/qr-code/` |
| GET | `/api/verify/{uuid}/` |
| GET/PUT/PATCH | `/api/organization-profile/` |
| PUT | `/api/organization-profile/documents/` |
| GET | `/api/list-organizations/` |

شكل الاستجابة:

```json
{
  "status": "success|error",
  "code": 200,
  "message_en": "",
  "message_ar": "",
  "data": {},
  "meta": {}
}
```

## الاختبارات

```bash
php artisan test
```

تغطي `tests/Feature/AllEndpointsCompatibilityTest.php` كل الـ endpoints المنفّذة وتتأكد من:
- شكل `CustomResponse` كما في Django (`status`, `code`, `message_en`, `message_ar`, `data`, `meta`)
- رسائل النجاح/الخطأ ثنائية اللغة المطابقة للمشروع الأصلي
- شكل Login: `data.data.auth_token`
- Health: `{status, service, database}`
- Pagination meta مثل `CustomPagination`
- Header المصادقة: `Authorization: Token <key>`

## ما تم إنجازه
- Schema كامل (~كل جداول التقرير)
- Models الأساسية + Auth/Profiles/Base
- Expiring Token Auth
- Auth APIs + Base APIs + FAQ
- Seeders (Config, Choices, Badges, Approvals, License)

## التالي (حسب التقرير)
- Volunteer / Organization profile controllers
- Opportunity + Event modules
- Community, Notifications, Calendar, Sponsors
- Jobs / Scheduler (reminders, bans, certificates)
- SyncService + Badges

> Docker / CI/CD / AWS غير مطلوبين في هذه المرحلة — التخزين محلي (`public` disk).
