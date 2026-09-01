# Fursa — تابات البروفايل، الرعاة، وإصلاحات API

موجّه لمطوّر الفرونت إند (قابل للتمرير مباشرةً إلى Claude).

القاعدة: `https://portal.fursa.raiyan.cc/api`

---

## 1) إصلاحات في `list-volunteer-opportunities/`

### الترتيب (`sort_by`) — كان معطوب تمامًا، اتصلح

كان الباك إند بيتجاهل `sort_by` خالص (oldest وnewest كانوا بيرجعوا نفس الترتيب). دلوقتي:

```
GET /api/list-volunteer-opportunities/?sort_by=oldest   ← الافتراضي، الأقدم start_date الأول
GET /api/list-volunteer-opportunities/?sort_by=newest   ← يعكس الترتيب
```

الأولوية الأساسية (طارئ/عاجل الأول، ثم المتاح، ثم المكتمل العدد، ثم اللي بدأ) **زي ما هي** — `sort_by` بيتحكم بس في الترتيب الفرعي جوه كل مجموعة.

### الجنس (`gender`) — شغالة، بس سلوكها مقصود

`gender=<id>` بيرجّع الفرص المطابقة للجنس المطلوب **+ أي فرصة مفتوحة للكل (`gender_id` فاضي)**. مش استبعاد صارم — ده تصميم متعمد ("الفرص المفتوحة للجميع تفضل تظهر لأي فلتر جنس"). لو محتاجينها صارمة (بس المطابق) قولّي.

### الجنسية (`opportunity_nationality`) — شغالة صح

القيم المقبولة: `kuwaitis` أو `non-kuwaitis` بالظبط.

---

## 2) `interest_display` في تفاصيل الفرصة — كان `null` دايمًا، اتصلح

`GET /api/opportunities/{id}/details/` و`GET /api/learn-serve-opportunities/{id}/` (والـ API resources التانية للفرص) بترجع دلوقتي:

```json
"interest_display": [
  { "id": 71, "choice_type": "volunteer_opportunity_interest", "value_en": "Community Service", "value_ar": "خدمة مجتمعية" }
]
```

`choice_type` بيبقى `volunteer_opportunity_interest` للفرص التطوعية، `learnserve_opportunity_interest` لفرص التطوير. لو مفيش اهتمامات مرتبطة بالفرصة، الحقل بيرجع `null` (مش array فاضي).

---

## 3) "تطابق اهتماماتي" (`match_my_interest`) — توضيح

المفتاح مظبوط زي ما الفرونت بيبعته. السلوك: بيرجّع بس الفرص اللي اهتماماتها بتتقاطع مع اهتمامات **المستخدم المسجّل دخوله** (مش اهتمامات أي حد تاني). لو المستخدم لسه محددش أي اهتمامات في بروفايله، بيرجع **قائمة فاضية** (مش كل الفرص) — قرار متعمد في الكود، لو عايزين نغيره لـ "يرجع الكل لو مفيش اهتمامات" قولّي.

---

## 4) تاب البروفايل — Tag لكل فرصة (`profile_activity_tag`)

مسار جديد استُخدم على الحقول الموجودة أصلًا، مش endpoint جديد:

```
GET /api/list-user-opportunities/?user_id={id}&filter_type=registered   ← فرص تطوعية + تطوير المستخدم مسجّل فيها
GET /api/list-all-opportunities/?user_id={id}&opportunity_type=...      ← يشمل الفرص اللي المستخدم أنشأها كمان
```

كل عنصر في `data` بقى فيه حقل جديد `profile_activity_tag`:

| القيمة | المعنى | يظهر لمين |
|---|---|---|
| `"registered"` | مسجّل في فرصة تطوعية، لسه ما حضرش | **صاحب البروفايل بس** |
| `"attended"` | حضر فرصة تطوعية فعليًا | صاحب البروفايل + العامة |
| `"participant"` | حضر/شارك في فرصة تطوير (كورس/ورشة) | صاحب البروفايل + العامة |
| `"provider"` | هو نفسه اللي قدّم الكورس/الورشة | صاحب البروفايل + العامة |
| `null` | لا ينطبق، أو (مسجل بس + الزائر مش صاحب البروفايل) | — |

**القاعدة المهمة:** الباك إند هو اللي بيقرر متى يظهر `"registered"` — بيعتمد على هل الشخص اللي عامل login هو نفسه `user_id` المطلوب ولا لأ. الفرونت **متحسبش الحالة محليًا** ولا يفترض إنه لو الحقل `null` يبقى مفيش نشاط — يمكن يكون النشاط موجود بس مخفي عمدًا عن الزائر الحالي.

هذا الحقل بيظهر بس لما تبعت `user_id` (تعرض بروفايل حد) — مش موجود في القوائم العامة (`list-volunteer-opportunities/` مثلًا).

---

## 5) كاونتر "فرص تطور" بدل "الشهادات" على البروفايل

`GET /api/volunteer-profile/` (بروفايلي) و`GET /api/public-profile/{user_id}/` (بروفايل حد تاني) بقى فيهم:

```json
{
  "development_opportunities_count": 3,
  "is_expert": true,
  "counter_visibility": {
    "volunteer_hours": true,
    "volunteer_opportunities": true,
    "development": true,
    "certificates": false,
    "sponsorship": false
  }
}
```

- `development_opportunities_count`: عدد فرص التطوير (شارك فيها كـ Participant + قدّمها كـ Provider، مجموع الاتنين).
- `counter_visibility.certificates` بقت **دايمًا `false`** — الكاونتر اتشال من كارت البروفايل نهائيًا.
- **تاب الشهادات نفسه متأثرش خالص** — `GET /api/user-certificates/?user_id=...` لسه شغال زي ما هو بالظبط، ولسه بيرجع كل الشهادات.
- استخدم `counter_visibility` زي ما هو (نفس القاعدة القديمة: اخفِ الكاونتر لو `false`، اعرضه لو `true`) — متحسبش الشرط بنفسك.

---

## 6) الرعاة (Sponsors) — فيتشر جديد كامل

مكانش فيه أي طريقة لإضافة راعي لفرصة تطوعية/تطويرية قبل كده (كان موجود بس للفعاليات، وده كمان كان فيه مشكلة). دلوقتي:

```
POST   /api/volunteer-opportunities/{id}/sponsors/              { "organization_id": 12 }
DELETE /api/volunteer-opportunities/{id}/sponsors/{sponsorId}/

POST   /api/learn-serve-opportunities/{id}/sponsors/             { "organization_id": 12 }
DELETE /api/learn-serve-opportunities/{id}/sponsors/{sponsorId}/
```

- المستخدم لازم يكون **صاحب الفرصة** (`created_by`).
- `organization_id` لازم يكون جهة معتمدة (`organization_status: approved`) **وليست فريق تطوعي** — لو حاولت تضيف فريق تطوعي كراعي هترجع 422.
- نفس الجهة متتضافش مرتين لنفس الفرصة (400 لو مكررة).

### دروبداون اختيار الراعي (استبعاد فرق التطوع)

```
GET /api/list-organizations/?for_sponsorship=true
```

نفس مسار "list-organizations" الموجود، بس مع `for_sponsorship=true` بيستبعد فرق التطوع تلقائيًا. استخدمه لأي شاشة فيها اختيار "جهة راعية".

### الاستجابة (نفس شكل الفرصة الكامل)

بعد الإضافة، عنصر الراعي بيظهر في `opportunity_sponsor_images` (موجود أصلًا في payload الفرصة):

```json
"opportunity_sponsor_images": [
  { "id": 5, "image": "https://.../logo.jpg", "organization": { "id": 12, "full_name": "..." }, "position": null }
]
```

`image` بييجي من صورة بروفايل الجهة نفسها تلقائيًا — مفيش حاجة لرفع صورة منفصلة للراعي.

### كاونتر الرعاية

كاونتر الرعاية بتاع الجهة (`sponsored` في بروفايل الجهة) **بيستثني**:
- الفعاليات (Events) — كان مستثنى أصلًا.
- فرص التطوير **المدفوعة** (`is_paid: true`) — جديد.

---

## 7) `is_paid` — حقل جديد على فرص التطوير (Learn & Serve)

```
POST /api/learn-serve-opportunities/       { ..., "is_paid": true }
PUT  /api/learn-serve-opportunities/{id}/  { ..., "is_paid": false }
```

بيرجع في كل payload لفرص التطوير (`GET`, listings) كـ `"is_paid": true|false`. الافتراضي `false`.

---

## 8) Provider / Participant — توضيح النطاق

التاجات دي خاصة **بفرص التطوير فقط** (كورسات/ورش) — مش بتظهر على الفرص التطوعية العادية:
- فرصة تطوعية عادية: `registered` / `attended` (زي ما كان).
- فرصة تطوير (learn-serve): `participant` (حضر) / `provider` (هو اللي قدّمها).

مفيش تغيير في `relationship_tags` (الموجود على شاشة تصفح الفرص نفسها) — التاجات الجديدة دي خاصة بـ `profile_activity_tag` بس (قسم 4 فوق).

---

## 9) التابات (فرد / فريق تطوعي / جهة) — لا يوجد تغيير باك إند، توضيح فقط

كل التابات المطلوبة (الكل / تطوع / تطوير، تاب الفعاليات) بتتغطى بالفلاتر الموجودة أصلًا:

```
GET /api/list-all-opportunities/?user_id={id}&opportunity_type=volunteer   ← تاب "تطوع"
GET /api/list-all-opportunities/?user_id={id}&opportunity_type=learn       ← تاب "تطوير"
GET /api/list-all-opportunities/?user_id={id}&filter_type=organized_events ← تاب "فعاليات" (للجهات/الفرق)
```

مفيش endpoint بيتحكم في "الكل" كتاب افتراضي واحد — لازم تبعت `filter_type` واضح (`registered`, `organized`, `organized_events`, `sponsored`) حسب التاب، وإلا هترجع كل الفرص في النظام مش بس بتاعة صاحب البروفايل.

---

## ملاحظة للـ Claude اللي هيقرأ ده

- كل الأسماء والمسارات هنا موجودة فعليًا في الباك إند — متخترعش بدائل.
- `profile_activity_tag` هو مصدر الحقيقة الوحيد لحالة "مسجل/حضر/مشارك/مقدم" — الباك إند بيطبّق قاعدة الخصوصية (إخفاء "مسجل" عن غير صاحب البروفايل)، فلازم الفرونت يعرضه زي ما هو من غير أي منطق إضافي.
- لو عايز تعرف شكل أي استجابة بالظبط، اضرب الـ endpoint فعليًا واقرأ الاستجابة بدل التخمين.
