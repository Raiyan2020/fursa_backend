# Fursa — مهام الفرونت إند بعد تحديث الباك إند

هذا الملف موجّه لمطوّر الفرونت إند (وقابل للتمرير مباشرة إلى Claude).
يغطّي شيئين:

1. **ما تغيّر في الـ API** — حقول ومسارات جديدة، ومسارات محذوفة (Breaking).
2. **البنود التي تنفيذها على الفرونت** من ملاحظات العميلة (ميل 14 يونيو + 21 يونيو + 26 يوليو).

الباك إند: Laravel، القاعدة `https://portal.fursa.raiyan.cc/api`.

> **قبل أي شيء:** الباك إند فيه migrations جديدة. تأكّد أن السيرفر متحدّث
> (`git pull && php artisan migrate --force`) وإلا الحقول الجديدة لن تظهر.

---

## 1) ⚠️ Breaking — مسارات وحقول محذوفة

### الكلندر (Calendar) — محذوف بالكامل

بناءً على طلب العميلة «حذف الكلندر». المسارات التالية **لم تعد موجودة** وسترجع 404:

```
GET    /api/my-calendar/
POST   /api/my-calendar/
PUT    /api/my-calendar/{id}/
PATCH  /api/my-calendar/{id}/
DELETE /api/my-calendar/{id}/
POST   /api/upload-ics/
```

**المطلوب:** إزالة قسم الكلندر من الواجهة، وإزالة كل استدعاء لهذه المسارات،
وإزالة زر «إضافة إلى التقويم».

الحقول `is_saved_to_calendar` و`is_calendar` ما زالت ترجع في الـ payload حاليًا
(لتفادي كسر مفاجئ) لكنها **مهملة (deprecated)** — لا تبنِ عليها شيئًا جديدًا.

### نوع الفكرة «تحتاج دعم» — محذوف

الحقل `needs_support` أُزيل من طلب واستجابة المنشورات:

```
POST /api/posts/     ← لا يقبل needs_support بعد الآن
PATCH /api/posts/{id}/ ← نفس الشيء
GET  /api/posts/     ← لا يرجّع needs_support
```

**المطلوب:** إزالة خيار «تحتاج دعم» من فورم الفكرة، وترك «فكرة» فقط.

---

## 2) حقول جديدة في الـ API

### 2.1 `action_state` — حالة زر التسجيل (يحلّ محل الحسابات اليدوية)

موجود الآن في كل payload للفرص والفعاليات (النسخة الداخلية والعامة):

| القيمة | المعنى | الزر المقترح |
|---|---|---|
| `register` | الفرصة مفتوحة | **تسجيل** |
| `unregister` | المستخدم مسجّل بالفعل | **إلغاء التسجيل** |
| `full` | العدد المطلوب اكتمل | **العدد مكتمل** (معطّل) |
| `closed` | انتهى الديودايت أو أُغلق يدويًا | **التسجيل مغلق** (معطّل) |
| `started` | بدأت ولم تنتهِ | **بدأت** (معطّل) |
| `ended` | انتهت | **انتهت** (معطّل) |

الأولوية محسوبة في الباك إند بهذا الترتيب: `ended` → `started` → `unregister`
→ `closed` → `full` → `register`.

**لا تعِد حساب هذه الحالات على الفرونت.** استخدم `action_state` كما هو حتى تتوحّد
الحالة بين كل الشاشات. حقول مساعدة إضافية: `is_full`, `has_started`, `has_ended`,
`is_registration_open`, `is_registration_closed`.

### 2.2 `relationship_tags` — تاقات البروفايل

مصفوفة نصوص في payload الفرص (تطوع + تطور)، محسوبة للمستخدم الحالي:

```json
"relationship_tags": ["organizer", "sponsor", "registered", "attended"]
```

استخدمها لتاقات التابات المطلوبة: `organizer` = منظم، `sponsor` = راعي،
`registered` = مسجل، `attended` = حضر.

### 2.3 `is_expert` — تاق «خبير»

في `GET /api/volunteer-profile/`:

```json
"is_expert": true,
"expert_opportunities_count": 3
```

يعني المتطوع أنشأ ورش/دورات/استشارات خاصة به. اعرض تاق **خبير** على تاب «تطور».

### 2.4 `counter_visibility` — إظهار/إخفاء الكاونترات

في `GET /api/statistics/` و`GET /api/volunteer-profile/`:

```json
"counter_visibility": {
  "volunteer_hours": true,
  "volunteer_opportunities": true,
  "development": false,
  "certificates": false,
  "sponsorship": false,
  "economic_impact": true,
  "beneficiaries": true,
  "outside_kuwait": true
}
```

**القاعدة:** ساعات التطوع وفرص التطوع تظهر دائمًا؛ الباقي يظهر فقط عندما تكون
القيمة `true`. **طبّق نفس المنطق على التابات المرتبطة** (تاب التطور، تاب الشهادات،
تاب الرعاية) — لا تعرض التاب إذا كان الفلاج `false`.

### 2.5 `GET /api/statistics/` — حقول إضافية

```json
{
  "grand_total_hours": 12345,
  "economic_impact_kwd": 74070,          // = الساعات × النسبة
  "economic_impact_rate_kwd": 6,          // قابلة للتعديل من لوحة التحكم
  "beneficiaries_count": 890,
  "beneficiaries_breakdown": {
    "volunteer_opportunities": 640,       // من فرص «خيري» فقط
    "course_learners": 250                // من حضروا الدورات
  },
  "outside_kuwait_trips": 12,             // بديل relief_trips
  "development_opportunities_completed": 40,
  "certificates_count": 300,
  "sponsors_count": 5,
  "counter_visibility": { ... }
}
```

**كاونتر الأثر الاقتصادي** جديد — أضِفه في صفحة الإنجازات.
لا تكتب `× 6` في الكود؛ استخدم `economic_impact_kwd` جاهزًا (النسبة قابلة للتغيير من اللوحة).

### 2.6 البانرات الـ dynamic

```
GET /api/banner-images/?placement=home
GET /api/banner-images/?placement=opportunities
GET /api/banner-images/?placement=development
GET /api/banner-images/?placement=events
```

بدون `placement` يرجّع بانرات الرئيسية (السلوك القديم). كل عنصر يرجّع
`{ image, banner_url, placement }`، ويحترم تواريخ الجدولة تلقائيًا.

**المطلوب:** صفحة الفرص وصفحة فرص التطور وصفحة الفعاليات تسحب بانرها من هذا
المسار بالـ placement الخاص بها بدل أي صورة ثابتة في الكود.

### 2.7 الشهادات — رندر من السيرفر (يحل مشكلة الأسماء العربية)

مشكلة «الأسماء العربية لا تظهر صح» سببها الرندر على العميل (canvas/PDF لا يشكّل
العربية). الحل: الباك إند أصبح يرندر الشهادة HTML كاملة، والمتصفح يشكّل العربية صح.

```
GET  /api/certificates/{registration_id}/        ← يرجّع HTML جاهزة (text/html)
POST /api/certificates/{registration_id}/issue/  ← يحفظها ويرجّع رابطها الدائم
```

و`GET /api/certificate/preview/{registration_id}/` صار يرجّع أيضًا:

```json
"certificate_html_url": "https://portal.fursa.raiyan.cc/api/certificates/123/",
"stored_certificate_url": "https://.../storage/certificates/registration_123.html",
"civil_id": "290052601121",
"certificate_type": "شهادة فرصة"
```

**المطلوب:** استبدال الرندر الحالي بعرض `certificate_html_url` في `<iframe>`،
وزر «تحميل / طباعة» يستخدم طباعة الـ iframe (الـ CSS جاهز بـ `@page A4 landscape`).
هذا يحل مشكلة العربية نهائيًا بدون أي مكتبة على الفرونت.

### 2.8 إغلاق التسجيل يدويًا

```
POST /api/volunteer-opportunities/{id}/close-registration/
POST /api/learn-serve-opportunities/{id}/close-registration/
POST /api/events/{id}/close-registration/        ← جديد
```

مسار الفعاليات يقبل اختياريًا `{"is_registration_closed": false}` لإعادة الفتح.
**المطلوب:** زر «إغلاق التسجيل» داخل صفحة الفرصة/الفعالية لمالكها.

### 2.9 حقول أخرى متاحة

| الحقل | الموجود في | الاستخدام |
|---|---|---|
| `location_url` | الفرص + الفعاليات | اعرض «الموقع» كرابط قابل للنقر |
| `volunteer_category` / `volunteer_category_display` | فرص التطوع | بيئي / خيري / تنظيمي |
| `beneficiaries_count` / `supports_beneficiaries_count` | فرص التطوع | حقل المستفيدين يظهر فقط عندما `supports_beneficiaries_count = true` (أي «خيري») |
| `is_emergency` | فرص التطوع | شارة طوارئ |
| `is_relief` | فرص التطوع | تصنيف «خارج الكويت» |
| `preparation_valid_until_at` / `is_preparation_window_closed` | فرص التطوع | نافذة التحضير |
| `civil_id` + `full_name` | `GET /api/volunteer-detail/` | الرقم المدني تحت اسم المتطوع في التقرير |

---

## 3) بنود من ملاحظات العميلة — تنفيذها على الفرونت

### نصوص وتسميات

| # | البند | ملاحظة |
|---|---|---|
| Edits 1 | «الفريق» → «فرق» في صفحة التسجيل | نص في الفرونت، ليس في ترجمات الباك إند |
| Edits 2 | «إغاثي» → «خارج الكويت» + **أيقونة طيارة** | التصنيف والكاونتر جاهزان في الباك إند؛ الأيقونة عليك |
| Edits 3 | «Learn & Share» → «تطور / Development» | ترجمات الباك إند حُدّثت؛ حدّث نصوص الفرونت |
| §9 | توحيد الترجمة (localization لا يعمل جيدًا) | راجع مفاتيح i18n الناقصة |

### تخطيط وعرض

| # | البند | ملاحظة |
|---|---|---|
| Edits 6 | حالات الأزرار «بدأت / انتهت» | استخدم `action_state` (بند 2.1) |
| Edits 7 | حذف «View More» وعرض الوصف كاملًا افتراضيًا | الوصف الكامل يرجع في الـ API |
| Edits 9 | إعادة تنظيم البروفايلات تابات + تاقات | استخدم `relationship_tags` و`is_expert` |
| Edits 10 | إظهار/إخفاء الكاونترات والتابات | استخدم `counter_visibility` |
| §6 | البروفايل: **الاسم لا يظهر** — بدلًا منه: الصورة + الباج + اليوزرنيم + الوضع الحالي + الاهتمامات | كل الحقول متاحة في `GET /api/volunteer-profile/` |
| §6 | رقم ترخيص الجهات لا يظهر للعلن | أخفِ `license_image` عن الزوار |
| §9 | توحيد مقاس البطاقات (مربع مثل إنستغرام) + منع تغيّر صورة الفعالية من كسر صفحة التفاصيل | CSS/layout |
| §2 | ترتيب الكاونترات على الموبايل أفقيًا (الأربعة بجانب بعض) | CSS |
| §5 | إظهار «عدد النتائج» في صفحة البحث | استخدم `meta.pagination.total` |
| §5 | اللوكيشن كرابط | `location_url` |

### وظائف

| # | البند | ملاحظة |
|---|---|---|
| Bugs 8 | جودة صورة البروفايل | **المشكلة على الفرونت**: أداة القص تصدّر بجودة/أبعاد منخفضة. الباك إند يخزّن الملف كما يستقبله بدون ضغط. ارفع جودة/أبعاد مخرج الـ cropper |
| Bugs 1 | الأسماء العربية في الشهادات | استخدم `certificate_html_url` (بند 2.7) |
| Bugs 9 | الرقم المدني تحت اسم المتطوع في التقرير | `civil_id` متاح |
| §7 | المتطوع يقدر يعمل ورش/دورات/استشارة | **الباك إند يسمح بذلك أصلًا** (`POST /api/learn-serve-opportunities/` بلا قيد على نوع المستخدم). الخيار مخفي في الفرونت — أظهره للمتطوعين، وأظهر تاق «خبير» عبر `is_expert` |
| §1 | ترتيب الفرص: التي بدأت تظهر آخرًا | الباك إند يرتّب (طوارئ ← قادمة ← جارية)؛ تأكّد أن الفرونت لا يعيد الترتيب |
| §3 | زر «قراءة الكل» للإشعارات | `POST /api/notifications/mark-read/` بـ `{"mark_all": true}` |
| §11 | أنواع الجهات الجديدة | `GET /api/choices/org_type/` يرجّع: Institution / Education / Society / NGO / Volunteer Team / Commercial. **«القطاع» أُزيل** — احذفه من فورم التسجيل |

---

## 4) نقاط تحتاج قرارًا من العميلة (لا تنفّذها قبل التأكيد)

1. **نافذة التحضير:** ميل يونيو قال «أسبوع»، والمتطلبات الأحدث قالت «72 ساعة».
   الباك إند الآن **قابل للتعديل من لوحة التحكم** (`الإعدادات → نافذة التحضير بالساعات`:
   72 = 3 أيام، 168 = أسبوع). الافتراضي 72.
2. **البانرات:** نُفّذت كبانر مستقل لكل صفحة (`placement`). إذا كان المقصود صورة غلاف
   كل فرصة/فعالية فهي موجودة أصلًا في `opportunity_images` / `event_images`.
3. **استبدال أسماء الدورات بشعارات (لوقو):** سؤال معلّق في ملاحظات العميلة — لم يُنفّذ.

---

## 5) ملاحظة للـ Claude الذي يقرأ هذا

- كل الحقول أعلاه موجودة فعليًا في الباك إند؛ لا تخترع أسماء بديلة.
- `action_state` و`counter_visibility` و`relationship_tags` هي **مصدر الحقيقة**؛
  الغرض منها إلغاء منطق مكرّر على الفرونت، فلا تعِد حسابه محليًا.
- المسارات المحذوفة في القسم 1 سترجع 404 — أزل استدعاءاتها، لا تعالجها بـ try/catch.
- عند الشك في شكل الـ payload، اضرب المسار فعليًا واقرأ الاستجابة بدل التخمين.
