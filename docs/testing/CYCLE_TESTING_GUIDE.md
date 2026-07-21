# دليل اختبار دورة Forsa

هذا المرجع يشرح تشغيل دورة النظام يدويًا عبر Postman وآليًا عبر PHPUnit. جميع
مسارات API تستخدم البادئة `/api`، ومسارات الإدارة تستخدم `/dashboard`.

## 1. بيئة الاختبار

الاختبارات الآلية تعمل على SQLite داخل الذاكرة كما هو معرف في `phpunit.xml`،
وتستخدم `RefreshDatabase`. لذلك لا تعدّل قاعدة بيانات التطوير أو الإنتاج.

```bash
php artisan test
php artisan test --filter=RouteSurfaceSmokeTest
php artisan test --filter=LifecycleFlowTest
php artisan test --filter=AdminFlowTest
php artisan test --filter=SchedulerCommandsTest
```

للتجربة اليدوية:

```bash
php artisan migrate:fresh --seed
php artisan serve
```

أرسل هذه الرؤوس مع كل طلب API:

```text
Accept: application/json
Lang: en
Authorization: Token <token>   # للمسارات المحمية فقط
```

شكل الاستجابة الموحد:

```json
{
  "key": "success",
  "msg": "Operation completed.",
  "code": 200,
  "response_status": {
    "error": false,
    "validation_errors": []
  },
  "data": {}
}
```

تحقق دائمًا من أن `code < 500` وأن `Content-Type` هو JSON لمسارات API.

## 2. المستخدمون والرموز

الحسابات التجريبية بعد `db:seed`:

| الدور | البريد | كلمة المرور |
|---|---|---|
| API staff | `admin@fursa.local` | `Password1` |
| متطوع | `volunteer@fursa.local` | `Password1` |
| جهة | `organization@fursa.local` | `Password1` |
| لوحة الإدارة | `admin@fursa.local` | `Password1` |

تسجيل الدخول إلى API:

```http
POST /api/login/
Content-Type: application/json

{"email":"volunteer@fursa.local","password":"Password1"}
```

احفظ `data.data.auth_token` باسم `volunteer_token`. كرر الطلب للجهة واحفظ
`organization_token`. يمكن استخدام `admin@fursa.local` في API كـ staff، وفي
نموذج `/dashboard/login` كحساب لوحة الإدارة.

## 3. دورة التسجيل والتحقق

1. افحص التكرار:
   `POST /api/check-user/` مع `email` و/أو `nickname`.
2. أنشئ متطوعًا:

```http
POST /api/register/

{
  "email": "cycle.volunteer@example.com",
  "password": "Password1",
  "user_type": "volunteer",
  "first_name": "Cycle",
  "last_name": "Volunteer",
  "civil_id": "123456789012",
  "birth_year": 1995
}
```

3. احصل على OTP من البريد/مزود البريد في البيئة الفعلية، ثم:

```http
POST /api/verify_otp_or_token/

{"email":"cycle.volunteer@example.com","type":"register","otp":"<otp>"}
```

4. نفذ `POST /api/login/` واحفظ token.
5. تحقق من `GET /api/account/` ثم حدّث الحساب عبر
   `PATCH /api/account/`.
6. تحقق من `GET /api/volunteer-profile/` ثم حدّث الملف عبر
   `PATCH /api/volunteer-profile/`. يجب إرسال `civil_id` عند الحاجة.
7. افحص QR عبر `GET /api/volunteer-profile/qr-code/`.

مسار استعادة كلمة المرور:

```text
POST /api/forgot-password/
POST /api/verify_otp_or_token/  (type=password)
POST /api/change-password/
```

## 4. الجهة واعتمادها

سجّل الجهة بنفس مسار التسجيل مع `user_type=organization`، ثم حدّث:

```text
GET|PATCH /api/organization-profile/
GET /api/organization-profile/documents/
```

من لوحة الإدارة:

1. افتح `/dashboard/entities`.
2. افتح سجل الجهة.
3. نفّذ approve أو reject من الإجراء المعروض.
4. تحقق من `organization_profiles.organization_status`.

## 5. المحتوى العام وCMS

يجب أن تعمل بلا token:

```text
GET /api/home/
GET /api/pages/
GET /api/pages/about/
GET /api/pages/privacy/
GET /api/pages/terms/
GET /api/faqs/
GET /api/banner-images/
GET /api/choices/{choice_type}/
```

تحقق في `/api/home/` من الأقسام:

```text
hero, statistics, sponsors, why_fursa, opportunities, community,
learn_share, share_idea, events, achievements, footer
```

إدارة المحتوى:

```text
/dashboard/pages
/dashboard/home-sections
/dashboard/why-fursa
/dashboard/site-settings
/dashboard/banner-images
/dashboard/faqs
```

لكل CRUD اختبر: index، create، validation، store، edit، update، delete، ثم
تحقق من قاعدة البيانات والملف في `storage/app/public` عند وجود upload.

## 6. الفرص التطوعية

أنشئ بالـ `organization_token`:

```http
POST /api/volunteer-opportunities/

{
  "title_en": "Cycle opportunity",
  "title_ar": "فرصة دورة",
  "description_en": "Cycle test",
  "description_ar": "اختبار دورة",
  "start_date": "2030-01-10",
  "end_date": "2030-01-12",
  "due_date": "2030-01-09",
  "participants_needed": 10,
  "from_age": 18,
  "is_public": true
}
```

احفظ `data.id` باسم `volunteer_opportunity_id`. من لوحة الإدارة نفذ:

```text
POST /dashboard/volunteer-opportunities/{id}/approve
```

ثم تحقق من:

```text
GET /api/list-volunteer-opportunities/
GET /api/list-volunteer-opportunities/{id}/
POST /api/volunteer-opportunity-registrations/
```

Payload التسجيل:

```json
{"opportunity_id": 1}
```

اختبر كذلك الأدوار والفرق والتعيين، طلب الحذف، سجل الحضور، scan permission
والمسارات الموجودة تحت `volunteer-opportunity-*`.

## 7. Learn & Serve

أنشئ عبر `POST /api/learn-serve-opportunities/` بنفس الحقول الأساسية للفرصة،
ثم اعتمد من:

```text
POST /dashboard/learn-serve-opportunities/{id}/approve
```

التسجيل:

```http
POST /api/learn-serve-opportunity-registrations/

{"opportunity_id":1,"time_slot_id":null}
```

الحضور بواسطة مالك الفرصة:

```http
PATCH /api/learn-serve-opportunities/{id}/update-attendance/

{"is_attended":true,"registration_ids":[1]}
```

اختبر time slots، feedback، likes، certificate preview/download، الصور وإلغاء
التسجيل. تحقق من `learn_serve_opportunity_registrations.is_attended`.

## 8. الأحداث

```http
POST /api/events/

{
  "title_en": "Cycle event",
  "title_ar": "فعالية دورة",
  "start_date": "2030-01-10",
  "end_date": "2030-01-10",
  "due_date": "2030-01-09 18:00:00",
  "registration_required": true,
  "participants_needed": 10
}
```

اعتمد عبر `POST /dashboard/events/{id}/approve`، ثم سجّل المتطوع:

```http
POST /api/event-registrations/

{"event":1}
```

المساران التاليان مختلفان عمدًا:

```text
GET /api/event-registrations/by-event/{event_id}/  # للجهة المنظمة
GET /api/event-registrations/{registration_id}/   # تفاصيل تسجيل واحد
```

حدّث الحضور بواسطة الجهة:

```http
PATCH /api/event-registrations/{registration_id}/

{"is_attended":true}
```

## 9. المجتمع والرعاة والتواصل

إنشاء منشور محمي:

```http
POST /api/posts/

{"title_en":"Cycle idea","idea_text_en":"Safe community idea","tags":["cycle"]}
```

ثم اختبر:

```text
GET /api/posts/
GET /api/posts/{id}/
PATCH /api/posts/{id}/
POST /api/likes/toggle/  {"post_id":1}
POST /api/replies/
DELETE /api/posts/{id}/
```

طلب الرعاية العام يتطلب `org_name`, `person_name`, `email`:

```http
POST /api/sponsors/

{"org_name":"Cycle Sponsor","person_name":"Contact","email":"sponsor@example.com"}
```

التواصل:

```http
POST /api/contact-us/

{"name_en":"Cycle User","email":"user@example.com","message_en":"Hello"}
```

## 10. الإحصائيات والإشعارات والجدولة

بعد الحضور:

```text
POST /api/sync-statistics/
GET /api/statistics/
GET /api/statistics/top/
GET /api/notifications/
```

تشغيل المهام يدويًا:

```bash
php artisan fursa:advance-statuses
php artisan fursa:backfill-missing-certificates
php artisan fursa:check-and-ban-non-attending
php artisan fursa:delete-expired-tokens
php artisan fursa:generate-missing-qr-codes
php artisan fursa:notify-fursa-friends-backup
php artisan fursa:send-completion-notification
php artisan fursa:send-day-of-notification
php artisan fursa:send-three-day-reminder
php artisan fursa:sync-all-statistics
php artisan fursa:unban-users
```

لرؤية الجدول الفعلي:

```bash
php artisan schedule:list
php artisan schedule:run
```

## 11. الحذف وإنهاء الدورة

1. نفذ طلب الحذف من API للفرصة/الحدث.
2. تحقق من `deletion_status=requested`.
3. من لوحة الإدارة نفذ approve-deletion أو reject-deletion.
4. عند الاعتماد تحقق من `is_deleted=1` ووجود `deleted_at`.
5. اختبر logout للـ API إن وجد، ثم
   `POST /dashboard/logout` للإدارة.

## 12. Checklist

- [ ] register وOTP وlogin يعملون ولا توجد استجابة 500.
- [ ] token المتطوع لا يصل إلى المسارات المحمية بدور آخر.
- [ ] ملف المتطوع والجهة قابلان للقراءة والتحديث.
- [ ] اعتماد الجهة محفوظ في قاعدة البيانات.
- [ ] CMS و`/api/home/` يعيدان كل الأقسام الديناميكية.
- [ ] إنشاء واعتماد وعرض الفرصة والـ Learn & Serve والحدث يعمل.
- [ ] التسجيل المكرر والسعة والعمر يعيدون خطأ business وليس exception.
- [ ] الحضور يحدّث سجل التسجيل.
- [ ] مزامنة الإحصائيات تنشئ `volunteer_statistics`.
- [ ] feedback/community/likes لا ترمي exception للسجلات المحذوفة.
- [ ] sponsor/contact validation يمنع payload ناقصًا قبل قاعدة البيانات.
- [ ] كل صفحات الإدارة العامة render بنجاح.
- [ ] جميع أوامر `fursa:*` تنتهي بكود 0.
- [ ] `RouteSurfaceSmokeTest` يثبت عدم وجود route duplicate أو استجابة 500.
- [ ] `php artisan test` كامل يمر بنجاح.

## 13. الاختبارات المقابلة

| المجال | الاختبار |
|---|---|
| Auth/Profile/Base | `AllEndpointsCompatibilityTest`, `AuthApiTest` |
| كل route مسجلة | `RouteSurfaceSmokeTest` |
| دورة المحتوى والتسجيل والحضور | `LifecycleFlowTest` |
| CMS العام والمجتمع والرعاة | `PublicAndCommunityFlowTest` |
| لوحة الإدارة | `AdminFlowTest` |
| Scheduler | `SchedulerCommandsTest` |

عند إضافة route جديدة، يجب أن تظهر تلقائيًا في route-surface sweep. أضف كذلك
اختبار success flow متخصص إذا كانت route تنشئ أو تعدّل بيانات.
