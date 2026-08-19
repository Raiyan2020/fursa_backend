# تقرير الـ Owner — مهام الفرونت إند

> **لـ:** مبرمج الفرونت (Vue / الموقع العام)  
> **من:** Backend  
> **التاريخ:** 18 أغسطس 2026  
> **API:** `https://portal.fursa.raiyan.cc/api/`  
> **صفحة الأدمن المشار إليها:** [تعديل فرصة تطور #20](https://portal.fursa.raiyan.cc/dashboard/learn-serve-opportunities/20/edit)

هذا الملف يخص **الموقع العام فقط**. لوحة التحكم Laravel مش ضمن الشغل ده.

بعد ما الـ backend يتعمل له deploy، راجع العقود تحت قبل ما تبني الـ UI.

---

## ملخص سريع

| # | البند | المسؤول |
|---|--------|---------|
| 1 | تغيير «الفريق» → «فرق» في صفحة التسجيل | Frontend |
| 2 | «إغاثي» → «خارج الكويت» + أيقونة طيارة | Frontend (+ label من API) |
| 3 | Learn & Share → Development / تطور في العدادات والأقسام | Frontend (العرض) |
| 4 | أزرار الحالة: Started → بدأت / Ended → انتهت | Frontend |
| 5 | صفحة تفاصيل الفرصة: وصف كامل، حذف View More، حذف Calendar، حذف Needs Support | Frontend |
| 6 | تخطيط البروفايل الجديد (Tabs + tags) | Frontend (+ بيانات API موجودة) |
| 7 | إظهار العدادات حسب القيمة | Frontend |
| 8 | جودة صورة البروفايل | Frontend (ضغط/عرض الصورة) |
| 9 | الرقم المدني تحت اسم المتطوع في التقارير | Frontend (الحقل بقى في الـ API) |
| 10 | Banners ديناميكية | Frontend (الـ API جاهز) |
| 11 | عداد Economic Impact | Frontend (الرقم بقى في الـ API) |
| 12 | حقل رابط الموقع في النماذج | Frontend (حقل `location_url`) |
| 13 | Due date اختياري + زر إغلاق التسجيل | Frontend (+ endpoint جديد) |
| 14 | نوع جهة Community | Frontend (يظهر من `GET /api/choices/org_type/`) |
| 15 | طريقتا التحضير مع بعض (QR + يدوي) | Frontend (الـ flags بقوا true) |

---

## 1) Bugs — عند الفرونت

### 1.1 جودة صورة البروفايل

الـ API بيحفظ الملف **كما هو** من غير compress.

المطلوب في الفرونت:

- متضغطش الصورة قبل الرفع لجودة منخفضة.
- ارفع الملف الأصلي على `PATCH /api/account/` أو `PATCH /api/volunteer-profile/` في الحقل `profile_pic` (multipart).
- بعد الحفظ، استخدم `data.profile_pic` من الـ response فوراً (متفضلش الصورة القديمة من الـ cache / local state).
- أضف cache-bust لو بتعرض نفس الـ URL (`?t=` timestamp).

### 1.2 الرقم المدني في تقارير المتطوع

الـ backend بقى يرجع `civil_id` في:

- قائمة تسجيلات التطوع: `GET /api/volunteer-opportunity-registrations/?opportunity_id=`
- قائمة تسجيلات التطور: `GET /api/learn-serve-opportunities/{id}/registrations/`
- تفاصيل المتطوع: `GET /api/volunteer-detail/` → `data.civil_id` + `data.full_name`

**المطلوب:** اعرض الرقم المدني **تحت اسم المتطوع** في شاشات التقارير / قوائم المسجّلين.

```json
{
  "full_name": "أحمد محمد",
  "civil_id": "292929292929"
}
```

---

## 2) Edits — تعديلات UI

### 2.1 صفحة التسجيل

غيّر النص **«الفريق»** إلى **«فرق»**.

ده نص واجهة، مش من الـ API.

### 2.2 إغاثي → خارج الكويت

- في فورم الفرصة التطوعية: label الزر/الـ checkbox من «إغاثي» إلى **«خارج الكويت»**.
- بدّل الأيقونة لأيكونة **طيارة**.
- الـ API لسه بيستخدم نفس الفلاج: `is_relief` (متغيّرش اسم الحقل).
- الترجمة العربية في `master_choices` بقت `خارج الكويت`.
- عدّاد الإنجازات: استخدم `outside_kuwait_trips` (alias) أو `relief_trips` من `GET /api/statistics/`.

### 2.3 إعادة التسمية: Learn & Share → Development (تطور)

في العدادات، التبويبات، والعناوين:

| كان | يصير EN | يصير AR |
|-----|---------|---------|
| Learn & Share / Learn & Serve | Development | تطور |

الـ routes في الـ API **ما اتغيّرتش** (`/api/learn-serve-opportunities/`). ده تغيير عرض فقط.

في الإحصائيات فيه alias جاهز:

```json
{
  "learn_serve_opportunities_completed": 12,
  "development_opportunities_completed": 12
}
```

### 2.4 حالة الأزرار

| EN الحالي | المطلوب AR |
|-----------|-------------|
| Started | بدأت |
| Ended | انتهت |

لو الحالة جاية من API كـ `upcoming` / `in_progress` / `completed`، اعمل mapping في الفرونت.

### 2.5 صفحة تفاصيل الفرصة

- احذف **View More**.
- اعرض **الوصف كامل** افتراضياً.
- احذف سكشن **Calendar**.
- في نوع الفكرة: احذف **Needs Support**، خلّي **Idea** فقط.

### 2.6 تخطيط البروفايل

ابنِ التبويبات كالتالي:

**Volunteer Profile**

- Opportunities: All / Volunteer / Development  
  Tags الحالة: `Registered` / `Attended`
- Certificates
- Teams

**Opportunities tab (Team / Entity)**

- All / Volunteer / Development  
  Tag: `Organizer`

**Events tab (Team)**

- Tag: `Organizer`

**Entities**

- Opportunities: All / Volunteer / Development  
  Tags: `Organizer` / `Sponsor`
- Events: Tags `Organizer` / `Sponsor`

الفلاتر الحالية في الـ combined list:

- `GET /api/list-all-opportunities/?filter_type=organized_events`
- `opportunity_type`: `event` | `volunteer_opportunity` | `learn_serve_opportunity`

### 2.7 ظهور العدادات

**ظاهر دائماً (حتى لو صفر):**

- Volunteer Hours
- Volunteer Opportunities

**يظهر فقط إذا القيمة > 0:**

- Development (تطور)
- Certificates
- Sponsorship

طبّق نفس المنطق على التبويبات المرتبطة: لو العداد مخفي، التبويب يتخفي أو يفضى حسب التصميم.

---

## 3) Additions — إضافات UI مربوطة بالـ API

### 3.1 حقل رابط الموقع

في **كل نماذج** الفرص والفعاليات أضف حقل:

```
location_url
```

مثال: `https://maps.google.com/?q=...`

الـ response:

```json
{
  "location_en": "...",
  "location_ar": "...",
  "location_url": "https://maps.google.com/?q=kuwait"
}
```

لو `location_url` فاضي، ممكن يقع على `link` القديم.

### 3.2 عداد Economic Impact (صفحة الإنجازات)

`GET /api/statistics/`

```json
{
  "grand_total_hours": 120,
  "economic_impact_rate_kwd": 6,
  "economic_impact_kwd": 720
}
```

الصيغة: **ساعات التطوع × 6 د.ك**.

اعرضه في صفحة Achievements.

### 3.3 Development — due date اختياري + إغلاق التسجيل

- في فورم إنشاء/تعديل فرصة تطور: **due date مش required**.
- لو المستخدم مسبوش due date: التسجيل يفضل مفتوح لحد **آخر يوم للفرصة** (`end_date`).
- الـ API بيرجع:

```json
{
  "due_date": null,
  "is_registration_closed": false,
  "is_registration_open": true
}
```

زر إغلاق التسجيل يدوياً (من داخل الفرصة، لصاحبها):

```
POST /api/learn-serve-opportunities/{id}/close-registration/
POST /api/volunteer-opportunities/{id}/close-registration/
```

Auth: Bearer token لصاحب الفرصة.

بعد النجاح: `is_registration_closed: true` و `is_registration_open: false`.  
أخفي زر التسجيل / اعرض «التسجيل مغلق».

### 3.4 نوع جهة جديد: Community

`GET /api/choices/org_type/` هيرجع خيار:

```json
{ "id": 123, "value_en": "Community", "value_ar": "مجتمعي" }
```

في تسجيل الجهة:

- اعرض **Community** جنب Public.
- **من غير ترخيص** (زي Public في الشكل، لكن `license_required` = false).
- افحص `GET /api/check-license-requirement/` بعد اختيار النوع.

### 3.5 Banners ديناميكية

الـ API جاهز من قبل:

- `GET /api/banner-images/`
- أو `GET /api/home/` → `data.hero.banners`

اعرضها carousel حسب `image` + `banner_url`. متستخدمش صور ثابتة في الفرونت.

### 3.6 طريقتا التحضير / الحضور معاً

الـ API بقى يرجع على تفاصيل الفرصة التطوعية:

```json
{
  "qr_attendance_enabled": true,
  "manual_attendance_enabled": true,
  "manual_tracking": true,
  "preparation_valid_until": "2026-08-25"
}
```

**متخفيش** الـ QR لو `manual_tracking` true. الاتنين يشتغلوا مع بعض.  
نافذة التحضير: أسبوع بعد `end_date` (`preparation_valid_until`).

---

## 4) إصلاحات بروفايل اتعملت في الـ backend (اربطها)

لو لسه ظاهر عندكم إن الحفظ مش بيشتغل، غالباً الفرونت بيبعت اسم حقل غلط أو مش بيقرأ الـ response.

| المشكلة | Endpoint | ابعت |
|---------|----------|------|
| العلامات (اختر العلامات) | `PATCH /api/volunteer-profile/` | `interests` أو `interest_ids` = IDs من `GET /api/choices/user_interest/` |
| الاسم | نفس الـ endpoint | `first_name`, `last_name` |
| رقم الهاتف | نفس الـ endpoint | `phone_number`, `country_code` |
| صلة القرابة | نفس الـ endpoint | `emergency_contact_relationship` = master_choice id |
| الرقم المدني للطوارئ | نفس الـ endpoint | `emergency_contact_civil_id` |
| صورة البروفايل | `PATCH /api/volunteer-profile/` أو `PATCH /api/account/` | `profile_pic` ملف |

بعد الحفظ، العلامات ترجع في `interest_display`.

---

## 5) ملاحظات توجيه الصفحات (مهم)

IDs **مش** unique بين الجداول. كارت ID = 94 ممكن يبقى فرصة تطوع مش فعالية.

استخدم `opportunity_type` من الـ list:

| `opportunity_type` | روح على |
|--------------------|---------|
| `event` | `/api/events/{id}/` → صفحة تفاصيل فعالية |
| `volunteer_opportunity` | `/api/opportunities/{id}/details/` أو volunteer details |
| `learn_serve_opportunity` | `/api/learn-serve-opportunities/{id}/` → تفاصيل تطور |

فلتر الفعاليات المنظمة:

```
GET /api/list-all-opportunities/?filter_type=organized_events
```

فلتر الحالة بقى حسب التواريخ الفعلية: `upcoming` / `inprogress` / `completed`.

تقييم فعالية:

```
POST /api/event-feedback/
{ "event": 9, "rating": 5, "comment": "..." }
```

---

## 6) مش عند الفرونت (backend خلصها — للعلم فقط)

متضيعش وقت عليها في الـ UI إلا لو محتاج تعرض النتيجة:

- Quarter Tops حسب الربع الحالي (`data.cycle_type`, `data.top_individuals`, `data.top_volunteer_teams`, `data.top_companies_and_government` في `GET /api/statistics/top/`)
- عدّادات Classes / Workshops / Consultations للجهة
- إيميلات تحديث الفرصة + Friends of Forsa لكل المتطوعين + إشعارات حسب العمر
- عدّ الشهادات من كل المصادر
- الشهادات بالعنوان العربي في preview

---

## 7) Checklist للتجربة

- [ ] تسجيل جهة: النص «فرق» + خيار Community من غير ترخيص
- [ ] فورم تطوع: «خارج الكويت» + أيقونة طيارة + `is_relief`
- [ ] فورم تطور/تطوع/فعالية: حقل لصق `location_url`
- [ ] فورم تطور: due date اختياري، وزر Close registration يشتغل
- [ ] تفاصيل فرصة: وصف كامل، مفيش View More ولا Calendar ولا Needs Support
- [ ] بروفايل: tabs حسب الجدول فوق + tags Registered/Attended/Organizer/Sponsor
- [ ] عدادات: Hours + Volunteer Opportunities دايماً؛ الباقي لو > 0
- [ ] Achievements: Quarter Tops + Economic Impact (ساعات × 6)
- [ ] Home: banners من الـ API
- [ ] تقارير المتطوعين: civil_id تحت الاسم
- [ ] حضور: QR ويدوي ظاهرين مع بعض
- [ ] حفظ البروفايل: علامات + هاتف + صلة قرابة + مدني طوارئ + صورة تفضل بعد refresh

لو حقل ناقص أو response شكله غريب، ابعت الـ request/response وأنا أراجع العقد.
