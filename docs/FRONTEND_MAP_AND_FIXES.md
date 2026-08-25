# Fursa — تحديث حقل الموقع + إصلاحات API

موجّه لمطوّر الفرونت إند (قابل للتمرير مباشرةً إلى Claude).

القاعدة: `https://portal.fursa.raiyan.cc/api`

> **مطلوب أولاً:** الباك إند فيه migrations جديدة. لازم السيرفر يتحدّث
> (`git pull && php artisan migrate --force && php artisan config:cache`)
> قبل ما أي حاجة من ده تشتغل.

---

## 1) حقل الموقع → خريطة (map_desc / lat / lng)

### العقد المطلوب

الفرونت يبعت التلاتة دول في `POST` و`PATCH` للفرص والفعاليات:

```json
{
  "map_desc": "شارع الجمهورية، المنصورة",
  "lat": 31.0409,
  "lng": 31.3785
}
```

| الحقل | النوع | ملاحظات |
|---|---|---|
| `map_desc` | string | وصف نصي للموقع، بحد أقصى 500 حرف |
| `lat` | number | بين ‎-90 و 90. **رقم مش string** |
| `lng` | number | بين ‎-180 و 180. **رقم مش string** |

الباك إند يقبل كمان `latitude` / `longitude` بالاسم الكامل — نفس النتيجة تمامًا.
استخدم `lat`/`lng` لأنها أقصر وهي المتفق عليها.

### المسارات المتأثرة

```
POST  /api/volunteer-opportunities/
PATCH /api/volunteer-opportunities/{id}/
POST  /api/learn-serve-opportunities/
PATCH /api/learn-serve-opportunities/{id}/
POST  /api/events/
PATCH /api/events/{id}/
```

### الاستجابة

كل payload للفرص والفعاليات بقى يرجّع:

```json
{
  "map_desc": "شارع الجمهورية، المنصورة",
  "lat": 31.0409,
  "lng": 31.3785,

  "latitude": 31.0409,
  "longitude": 31.3785,
  "location_en": "شارع الجمهورية، المنصورة",
  "location_ar": "شارع الجمهورية، المنصورة"
}
```

`lat`/`lng` **أرقام** (اتعمِلها cast في الباك إند)، فتقدر تمرّرها لـ Leaflet مباشرةً:

```js
L.marker([item.lat, item.lng]).addTo(map);
```

### `location_en` / `location_ar` — مهملة (deprecated)

الحقلين لسه بيترجعوا ولسه بيتملّوا تلقائيًا من `map_desc` عشان السجلات القديمة
والشاشات اللي لسه ما اتحدثتش. **متبعتهمش من الفورم الجديد**، واقرأ `map_desc` بس.

لو بعت `location_en` أو `location_ar` صراحةً، الباك إند بيحترم اللي بعته
ومابيدهسش عليه بـ `map_desc`.

### السجلات القديمة

الـ migration بيملّي `map_desc` من `location_ar` (أو `location_en` لو العربي فاضي)،
فأي فرصة قديمة هتفتح في الخريطة بالوصف اللي كان مكتوب فيها. لو `lat`/`lng` كانوا
فاضيين هيرجعوا `null` — الفرونت يعرض الخريطة على موقع افتراضي وقتها.

### فلتر البحث بالموقع

`?location=المنصورة` بقى بيدوّر في `location_en` و`location_ar` **و`map_desc`**.

---

## 2) إصلاحات API — مسارات وحقول جديدة

### 2.1 الإشعارات — مسارات لعنصر واحد (جديدة، الأنضف)

المسارات القديمة بتاخد الـ ids في الـ body، وده سهل يتلخبط (خصوصًا `DELETE`
لأن كتير من HTTP clients بتشيل الـ body منه). المسارات الجديدة بتاخد الـ id من
الـ path فمفيش مجال للخطأ:

```
POST   /api/notifications/{id}/read/       ← علّم كمقروء
POST   /api/notifications/{id}/unread/     ← علّم كغير مقروء
DELETE /api/notifications/{id}/            ← احذف إشعار واحد
```

`{id}` هو `id` اللي بيرجع في عنصر الليستة (مش `notification.id` الداخلي).

**استخدم دي بدل القديمة.** المسارات القديمة لسه شغالة وبقت أكثر تسامحًا:

```
POST /api/notifications/mark-read/   { "notification_ids": [5], "is_read": true }
POST /api/notifications/mark-read/   { "notification_id": 5 }        ← بقى مقبول
POST /api/notifications/mark-read/   { "id": 5 }                     ← بقى مقبول
DELETE /api/notifications/delete/    { "notification_ids": [5] }
```

`is_read` بقى اختياري وقيمته الافتراضية `true`.

**كمان اتصلّح:**
- الإشعار المحذوف من لوحة التحكم بقى يختفي من الموقع فعلًا
- `created_at` بقى بيترجع صح (كان `null` فالفرونت يعرضه 1 يناير 1970)

### 2.2 تفعيل الحساب — `otp_type` بقى مقبول

كان الـ API يرجّع `otp_type` في استجابة «الحساب غير مفعّل» بينما
`verify_otp_or_token` يتوقّع `type` — فالتفعيل كان بيفشل بـ 422 صامت
والمستخدم يفضل يتطلب منه OTP للأبد.

دلوقتي الاتنين مقبولين في `/api/verify_otp_or_token/` و`/api/resend_otp_or_token/`:

```json
{ "email": "...", "type": "register", "otp": "123456" }
{ "email": "...", "otp_type": "register", "otp": "123456" }
```

> **مهم:** لازم الفرونت يعرض أخطاء الـ 422 للمستخدم. لو كان بيعرضها، المشكلة دي
> كانت هتبان من أول يوم بدل ما تتحوّل لتشخيص غلط.

### 2.3 فلتر نوع الفعاليات — الـ id بقى مقبول

`?event_type=63` كان بيتجاهَل بصمت ويرجّع كل النتائج (الباك إند كان مستني الاسم).
دلوقتي بيقبل الـ id أو الاسم:

```
GET /api/events/?event_type=63
GET /api/events/?event_type=Hub
GET /api/events/?event_type=مساحة
GET /api/events/?event=63            ← لسه شغال
```

ولو القيمة مش موجودة بيرجّع **صفر نتائج** بدل ما يرجّع الكل — الفلتر الغلط بقى
باين بدل ما يكون مخفي.

### 2.4 التسجيل في فرصة — سنة الميلاد

`POST /api/volunteer-opportunity-registrations/` كان بيرفض بـ 400
«يرجى تقديم سنة ميلادك» لو `birth_year` فاضي — حتى لو `dob` موجود.
دلوقتي الباك إند بيشتق السنة من `dob` تلقائيًا.

### 2.5 حالة زر التسجيل — `action_state`

موجود في كل payload للفرص والفعاليات:

| القيمة | الزر |
|---|---|
| `register` | تسجيل |
| `unregister` | إلغاء التسجيل |
| `full` | العدد مكتمل (معطّل) |
| `closed` | التسجيل مغلق (معطّل) |
| `started` | بدأت (معطّل) |
| `ended` | انتهت (معطّل) |

**استخدمه كما هو ومتحسبش الحالة محليًا.** حقول مساعدة: `is_full` · `has_started` · `has_ended` · `is_registration_open`.

> ⚠️ **متستخدمش `is_registration_closed`** — ده فلاج الإغلاق **اليدوي** بس، وممكن
> يكون `false` بينما التسجيل مقفول (لأن الديودايت عدّى). الحالة المحسوبة هي
> `is_registration_open` أو `action_state`.

---

## 3) بنود على الفرونت (الباك إند سليم فيها)

| البند | التفاصيل |
|---|---|
| **placeholder التاريخ غلط** في فورم إضافة فرصة/فعالية | نص في الفرونت |
| **«لازم تغيّر التاريخ» عند تعديل فرصة قديمة** | الباك إند **مفيهوش** أي قاعدة `after_or_equal:today` على `start_date` / `end_date` / `due_date` — لا في الـ API ولا في لوحة التحكم. المصدر هو `min` على الـ date picker أو مُحقّق في الفرونت. لما تكون في وضع التعديل، شيل الحد الأدنى أو خليه تاريخ الفرصة نفسه |
| **الوسوم مش مترجمة** | الـ API بيرجّع العربي فعلًا: `GET /api/choices/volunteer_opportunity_interest/` يرجّع `value_en` و`value_ar` لكل عنصر. اعرض `value_ar` لما اللغة عربي. نفس الشيء لكل `/api/choices/{type}/` |
| **الأرقام راجعة عربية كنص** | حقول زي `participants_needed` و`registered_volunteers_count` بترجع `'٢٠'` (نص بأرقام هندية) في مسارات الموقع العامة. متحسبش عليها في JS — استخدم `is_full` من الباك إند |

---

## 4) ملاحظة للـ Claude اللي هيقرأ ده

- كل الأسماء هنا موجودة فعليًا في الباك إند — متخترعش بدائل.
- `action_state` و`counter_visibility` و`relationship_tags` و`map_desc/lat/lng` هي
  **مصدر الحقيقة**؛ الغرض منها إلغاء منطق مكرر على الفرونت.
- عند الشك في شكل أي payload، اضرب المسار فعليًا واقرأ الاستجابة بدل التخمين.
- أي بارامتر فلترة الباك إند مش فاهمه **بيتجاهله** (ما عدا `event_type` بعد الإصلاح) —
  فلو فلتر مش شغال، أول حاجة تتأكد من اسم البارامتر في تاب Network.
