# Fursa — صفحة "الملفات الشخصية" (متطوع / مؤسسة / فريق تطوعي)

موجّه لمطوّر الفرونت إند (قابل للتمرير مباشرةً إلى Claude).

القاعدة: `https://portal.fursa.raiyan.cc/api`

---

## 1) الصفحة الرئيسية (المعاينة المجمّعة) — نفس شكل الصورة

```
GET /api/all-profiles/?limit=6
```

مسار موجود أصلًا، بيرجّع التلات أقسام مجمّعين في استجابة واحدة — ده اللي
تستخدمه في صفحة "الملفات الشخصية" الرئيسية (الصورة اللي بعتها):

```json
{
  "key": "success",
  "msg": "Profiles retrieved successfully.",
  "code": 200,
  "response_status": { "error": false, "validation_errors": [] },
  "data": {
    "volunteer": [
      {
        "id": 1,
        "nickname": "demo_volunteer",
        "is_public": true,
        "user_details": {
          "id": 2,
          "first_name": "Demo",
          "last_name": "Volunteer",
          "profile_pic": null,
          "gender_display": null,
          "user_type": "volunteer"
        }
      }
    ],
    "organization": [ { "...": "نفس الشكل" } ],
    "volunteer_team": [ { "...": "نفس الشكل" } ],
    "meta": {
      "pagination": {
        "volunteer": { "page": 1, "limit": 6, "total": 3, "total_pages": 1 },
        "organization": { "page": 1, "limit": 6, "total": 2, "total_pages": 1 },
        "volunteer_team": { "page": 1, "limit": 6, "total": 1, "total_pages": 1 }
      },
      "timestamp": "2026-08-30T13:49:03+00:00"
    }
  }
}
```

**استخدم `limit=6`** (أو أي عدد بتحطه في الصف الواحد فعليًا) عشان تجيب بالظبط
عدد الكروت اللي هتعرضها تحت كل قسم — الـ endpoint ده مش مخصص لعرض كل شيء،
هو بس معاينة (preview) لكل قسم.

الاسم اللي تعرضه تحت الصورة هو `user_details.first_name` + `user_details.last_name`.
لو محتاج الاسم المعروض (nickname) بدل الاسم الحقيقي، استخدم `nickname` نفسه.

`user_details.profile_pic` ممكن يرجع `null` لو المستخدم مفيهوش صورة — اعرض
افتراضي (placeholder avatar) وقتها.

### فلاتر متاحة (اختيارية)

```
?search=...      ← بحث في الاسم أو الـ nickname
?name=...        ← بحث في first_name/last_name بس
?nickname=...    ← بحث في nickname بس
?user_type=volunteer|organization|volunteer_team   ← يرجّع قسم واحد بس، الباقي فاضي
```

---

## 2) "عرض الكل" — 3 مسارات جديدة، كل واحد paginated لوحده

بدل ما تستخدم `all-profiles/` بفلتر `user_type` (شكل الاستجابة فيه هيفضل
مجمّع بـ 3 مفاتيح حتى لو قسمين فاضيين)، في 3 مسارات مخصصة، كل واحد بيرجّع
`data` كـ **array مسطّح** وpagination عادي زي باقي الـ APIs في المشروع:

```
GET /api/profiles/volunteers/         ← قسم "متطوع" كامل
GET /api/profiles/organizations/      ← قسم "مؤسسة" كامل (بدون فرق التطوع)
GET /api/profiles/volunteer-teams/    ← قسم "فريق تطوعي" كامل
```

كل واحد فيهم بياخد نفس الفلاتر (`search` / `name` / `nickname`) بالإضافة لـ
`page` و`limit` العاديين.

### مثال — `GET /api/profiles/volunteers/?page=1&limit=20`

```json
{
  "key": "success",
  "msg": "Volunteers retrieved successfully.",
  "code": 200,
  "response_status": { "error": false, "validation_errors": [] },
  "data": [
    {
      "id": 1,
      "nickname": "demo_volunteer",
      "is_public": true,
      "user_details": {
        "id": 2,
        "first_name": "Demo",
        "last_name": "Volunteer",
        "profile_pic": null,
        "gender_display": null,
        "user_type": "volunteer"
      }
    }
  ],
  "meta": {
    "pagination": { "page": 1, "limit": 20, "total": 3, "total_pages": 1 },
    "timestamp": "2026-08-30T13:49:03+00:00"
  }
}
```

`organizations/` و`volunteer-teams/` بيرجّعوا بالظبط نفس الشكل (عنصر
`user_details` هو نفسه، مفيش `gender_display` بيتملى للمؤسسات — بيرجع
`null` دايمًا).

### استخدام "عرض الكل" في الفرونت

لما المستخدم يدوس "عرض الكل" تحت أي قسم في الصورة، وديه على شاشة منفصلة
بتنادي على المسار المناسب من التلاتة دول بـ pagination عادي (زرار "تحميل
المزيد" أو infinite scroll زي أي ليستة تانية في التطبيق).

---

## 3) ملاحظة للـ Claude اللي هيقرأ ده

- كل الأسماء والمسارات هنا موجودة فعليًا في الباك إند — متخترعش بدائل.
- `all-profiles/` و`profiles/volunteers|organizations|volunteer-teams/`
  بيرجّعوا نفس شكل عنصر الملف الشخصي بالظبط (`id`, `nickname`, `is_public`,
  `user_details`) — الفرق الوحيد إن `all-profiles/` بيغلّفهم في 3 مفاتيح
  مجمّعة بينما التلاتة التانية بترجع array مسطّح عادي.
- لو عايز تعرف شكل أي حقل بالظبط، اضرب الـ endpoint فعليًا واقرأ الاستجابة
  بدل التخمين.
- المؤسسات اللي نوعها "فريق تطوعي" (Volunteer Team) بتتفلتر تلقائيًا خارج
  `organizations/` وتظهر بس في `volunteer-teams/` — التصنيف بيتحدد من حقل
  `organizer_type` مش من أي حاجة تانية.
