# Fursa — التسجيل وأنواع الحسابات (نقاط العميلة من Trello)

موجّه لمطوّر الفرونت إند (قابل للتمرير مباشرةً إلى Claude).

القاعدة: `https://portal.fursa.raiyan.cc/api`

هنعمل هذا الملف تراكمي — كل نقطة من ليستة العميلة على Trello بنضيفها هنا لما
تخلص، فبعّته تاني كل ما نخلص نقطة جديدة.

---

## ✅ نقاط اتعملت خلاص (من قبل الليستة دي)

مراجعة سريعة قبل ما نبدأ — النقاط دي كانت خلصت أصلًا وموجودة في الباك إند:

- إضافة نوع الجهة (organizer_type) — حكومي / تجاري / تطوعي / فريق تطوعي /
  غير ربحي / جمعية / مجتمع
- إضافة الجنسية (`nationality`) للمستخدم
- كويتي → الرقم المدني إجباري

كل دول شغالين ومغطّيين باختبارات (`OrganizationTypeRestructureTest`).

---

## 1) تعديل مسمى "فرد" → "متطوع"

**لا يوجد تغيير في الباك إند.** كلمة "فرد" مش موجودة في أي مكان في الباك
إند أصلًا — ده نص UI صرف في الفرونت (اسم الكارت في شاشة التسجيل). غيّر
النص من "فرد" لـ "متطوع" في الفرونت وخلاص، الـ API اللي وراه (`user_type:
"volunteer"`) متغيرش.

---

## 2) بطاقة "فريق تطوعي" في شاشة التسجيل + الترتيب الجديد

**لا يوجد تغيير في الباك إند** — الـ API بيدعم ده أصلًا:

عند التسجيل بحساب مؤسسة (`user_type: "organization"`)، فيه حقل
`organizer_type` بياخد `id` من نفس قائمة الأنواع دي:

```
GET /api/choices/org_type/
```

القائمة فيها من ضمن عناصرها عنصر بـ `value_en: "Volunteer Team"` (النوع
اللي اتضاف قبل كده). الفرونت يقدر يبني الكارت الثالث بنفس الطريقة اللي بيبني
بيها بطاقة "جهة" العادية، بس مع تثبيت `organizer_type` على الـ id بتاع
"Volunteer Team" تلقائيًا (من غير ما يسيب المستخدم يختاره من دروب داون).

### الترتيب المطلوب: فرد (متطوع) ← فريق تطوعي ← جهة

ده ترتيب عرض بس في الفرونت — مفيش endpoint بيتحكم في ترتيب البطاقات، رتّبهم
زي ما انت عايز في الـ UI مباشرة.

### طلب التسجيل — مثال لكل بطاقة

```json
// بطاقة "متطوع" (فرد سابقًا)
{ "user_type": "volunteer", "email": "...", "password": "...", "civil_id": "..." }

// بطاقة "فريق تطوعي"
{ "user_type": "organization", "organizer_type": 59, "company_name": "...", "email": "..." }
// 59 هنا مثال بس — لازم تجيبه فعليًا من GET /api/choices/org_type/
// ودوّر على العنصر اللي value_en == "Volunteer Team"

// بطاقة "جهة" (باقي الأنواع، المستخدم يختار من دروب داون)
{ "user_type": "organization", "organizer_type": <أي id تاني من نفس القائمة>, "company_name": "...", "email": "..." }
```

---

## 3) إلغاء حقل "القطاع" (sector)

**لا يوجد تغيير في الباك إند مطلوب — الحقل اختياري أصلًا (`nullable`) في كل
مكان.** حقل `sector` مش موجود في نموذج التسجيل الأساسي أصلًا (مش من ضمن
حقول `POST /api/register/`) — هو موجود بس في **تعديل بيانات المؤسسة بعد
التسجيل** (`PATCH /api/organization-profile/`، أو `POST` بعد الإصلاح اللي
عملناه).

يعني: شيل حقل "القطاع" من فورم تعديل بيانات المؤسسة في الفرونت، وماتبعتش
`sector` خالص — الباك إند مش هيعترض ومفيش أي validation إجباري عليه دلوقتي.
لو حابب نشيله كمان من شاشة عرض تفاصيل المؤسسة في لوحة تحكم الأدمن قولّي.

---

## 4) + 5) الرقم المدني / رقم الجواز حسب الإقامة (حقل جديد)

اتضاف حقل جديد اسمه `residency_status` — **بيظهر بس لما الجنسية "غير
كويتي"** (`nationality: "other"`). القاعدة:

| الجنسية | حالة الإقامة | الحقل الإجباري |
|---|---|---|
| كويتي (`kuwaitis`) أو الحقل فاضي | — | `civil_id` (زي ما هو من قبل، مفيش تغيير) |
| غير كويتي (`other`) | مقيم (`resident`) | `civil_id` |
| غير كويتي (`other`) | غير مقيم (`non_resident`) | `passport_number` |

**مهم:** لو الجنسية "غير كويتي" ومفيش `residency_status` مبعوت خالص، الباك
إند هيرفض بـ 422 على `residency_status` نفسه (يعني لازم تظهر الحقل في
الفورم فور ما المستخدم يختار "غير كويتي"، ومتسيبهوش اختياري).

القيم المقبولة لـ `residency_status`: `"resident"` أو `"non_resident"`.

### فورم التسجيل — الحقول

```
user_type: "volunteer"
nationality: "kuwaitis" | "other"
residency_status: "resident" | "non_resident"   ← إظهار بس لو nationality = "other"
civil_id: "..."            ← لو مفيش نوع إقامة، أو resident
passport_number: "..."     ← لو non_resident
```

### أمثلة استجابة الخطأ (422)

```json
// nationality=other, مفيش residency_status
{
  "key": "fail",
  "msg": "خطأ في التحقق من البيانات",
  "code": 422,
  "response_status": {
    "error": true,
    "validation_errors": { "residency_status": ["حقل حالة الإقامة مطلوب."] }
  },
  "data": null
}

// nationality=other, residency_status=non_resident, مفيش passport_number
{
  "key": "fail",
  "msg": "خطأ في التحقق من البيانات",
  "code": 422,
  "response_status": {
    "error": true,
    "validation_errors": { "passport_number": ["حقل رقم الجواز مطلوب."] }
  },
  "data": null
}
```

`passport_number` لازم يكون فريد (مفيش شخصين برقم جواز واحد) — لو مكرر
هيرجع نفس شكل خطأ `civil_id` المكرر بالظبط (`validation.unique`).

المسارات المتأثرة: `POST /api/register/` و`POST /api/account/` (تعديل
البيانات بعد التسجيل، نفس القاعدة).

---

## 6) تخزين الرقم المدني / الجواز في الداتابيز

خلص — الحقلين اتخزنوا في نفس جدول المستخدم (`users.civil_id` كان موجود،
`users.passport_number` اتضاف جديد)، وبيترجعوا في:

```
GET /api/account/            ← "civil_id", "passport_number", "residency_status"
```

---

## 7) ظهوره بالتقارير

`passport_number` بقى ظاهر جنب `civil_id` في كل التقارير اللي كانت
بترجّع `civil_id` قبل كده:

```
GET /api/volunteer-detail/                            ← تقرير المتطوع لنفسه (civil_id + passport_number)
GET /api/volunteer-opportunity-registrations/                        ← تقرير المنظمة لقائمة المسجلين
GET /api/learn-serve-opportunities/{opportunity_id}/registrations/   ← نفس الشيء لفرص "تعلّم وتطوع"
```

كل عنصر تسجيل بيرجع دلوقتي `civil_id` و`passport_number` مع بعض — واحد
منهم هيكون `null` حسب حالة المتطوع (مقيم يبقى معاه `civil_id`، غير مقيم
يبقى معاه `passport_number`).

تصدير الإكسل من لوحة تحكم الأدمن (`dashboard/users/export`) بقى فيه كمان
عمودين جداد: "Residency status" و"Passport number".

---

## ملاحظة للـ Claude اللي هيقرأ ده

- كل الأسماء والمسارات هنا موجودة فعليًا في الباك إند — متخترعش بدائل.
- `residency_status` مش إجباري إلا لما `nationality = "other"` — لو الفرونت
  القديم مش بيبعت `nationality` أصلًا، السلوك زي ما هو بالظبط من قبل (مفيش
  كسر رجعي).
- لو عايز تعرف شكل أي استجابة بالظبط، اضرب الـ endpoint فعليًا واقرأ
  الاستجابة بدل التخمين.
