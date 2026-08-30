# Fursa — رفع صورة الحساب (POST) + إجراءات جديدة على الفرصة

موجّه لمطوّر الفرونت إند (قابل للتمرير مباشرةً إلى Claude).

القاعدة: `https://portal.fursa.raiyan.cc/api`

---

## 1) تحديث الحساب — بقى `POST` بس (مش PATCH/PUT)

```
POST /api/account/
```

**السبب:** لما بتبعت `profile_pic` (صورة) في request بـ method `PATCH` أو `PUT`،
PHP نفسها (مش Laravel) مش بتعمل parse لأجسام `multipart/form-data` غير مع
method اسمه `POST` فعليًا. يعني `$_FILES` كانت بتوصل فاضية، فمهما الصورة
كانت متبعوتة صح من الفرونت، السيرفر كان بيهملها تمامًا — من غير أي خطأ ظاهر.

**الحل:** المسار بقى `POST` بس. اتشالت `PATCH`/`PUT` خالص من الراوت — لو لسه
حد في الفرونت بيبعت PATCH/PUT هيرجعله **405 Method Not Allowed**.

### الطلب

```
POST /api/account/
Content-Type: multipart/form-data
Authorization: Token <token>
```

نفس الحقول القديمة بالظبط (مفيش تغيير في الأسماء)، مثال:

```
profile_pic: <file>
first_name: "أحمد"
last_name: "علي"
phone_number: "12345678"
```

### الاستجابة

```json
{
  "key": "success",
  "msg": "Account information updated",
  "code": 200,
  "response_status": { "error": false, "validation_errors": [] },
  "data": {
    "id": 4,
    "profile_pic": "https://portal.fursa.raiyan.cc/storage/uploads/profile_pics/xxxx.jpg",
    "first_name": "أحمد",
    "last_name": "علي",
    "full_name": "أحمد علي",
    "email": "user@example.com"
  }
}
```

`GET /api/account/` (بيرجع بيانات الحساب) **متغيرش** — لسه `GET` زي ما هو.

---

## 2) فرصة تطوعية — إجراءان جديدان

مضافين على `VolunteerOpportunityController` (نفس الكنترولر بتاع
`/api/volunteer-opportunities/`)، وبيتطلبوا إن المستخدم هو **صاحب الفرصة**
(`created_by`) — أي حد تاني هياخد `404 Not Found` (مش 403، عشان مايوريش إن
الفرصة موجودة أصلًا).

### 2.1 إعادة فتح التسجيل

```
POST /api/volunteer-opportunities/{id}/reopen-registration/
```

عكس `POST /api/volunteer-opportunities/{id}/close-registration/` اللي موجود
أصلًا. مفيش body مطلوب.

```json
{
  "key": "success",
  "msg": "Registration reopened successfully.",
  "code": 200,
  "response_status": { "error": false, "validation_errors": [] },
  "data": { "...": "نفس شكل payload الفرصة الكامل (VolunteerOpportunityResource)" }
}
```

استخدمه لما يكون `is_registration_closed = true` وعايز المستخدم يفتحه تاني —
قبل كده كان ده متاح بس من لوحة تحكم الأدمن، مش من الموقع.

### 2.2 إعادة تقديم فرصة اتعملها Reject

```
POST /api/volunteer-opportunities/{id}/resubmit/
```

بيرجّع `approval_status` من `rejected` لـ `pending` (وبيمسح `rejected_reason`)
عشان الفرصة تدخل تاني في طابور مراجعة الأدمن، من غير ما المستخدم يحتاج
يعمل الفرصة من الأول.

**شرط:** الفرصة لازم تكون `approval_status === 'rejected'` فعلًا، وإلا هيرجع:

```json
{
  "key": "fail",
  "msg": "Only a rejected opportunity can be resubmitted.",
  "code": 400,
  "response_status": { "error": true, "validation_errors": [] },
  "data": null
}
```

الاستخدام المتوقع في الفرونت: لو `approval_status === 'rejected'`، اعرض زرار
"عدّل وأعد الإرسال" — الفرونت يستخدم `PATCH /api/volunteer-opportunities/{id}/`
الأول عشان المستخدم يعدّل البيانات (لو محتاج)، وبعدين ينادي على
`resubmit/` عشان يرجّعها pending. الاستدعاءين منفصلين ومفيش ترتيب إجباري
بينهم غير إن `resubmit` هو اللي بيرجّعها للمراجعة.

بعد النجاح، الاستجابة زي `reopen-registration` بالظبط — `data` هو الفرصة
الكاملة، وبتشوف `approval_status: "pending"`.

---

## 3) تنبيه — نفس مشكلة رفع الصور موجودة في مسارات تانية (لسه مش متصلحة)

لو بتحدّث فرصة (مش الحساب) وبتبعت صور معاها، انتبه:

```
PATCH /api/volunteer-opportunities/{id}/               ← تعديل الفرصة، بيقبل صور
PATCH /api/volunteer-opportunities/{id}/update_images/  ← مخصص لتحديث الصور بس
```

الاتنين لسه `PATCH` فقط، ونفس القيود اللي شرحناها في قسم (1) بتاع الحساب
منطبقة عليهم — أي صورة تتبعت مع الـ request ده مش هتوصل للسيرفر. لو الفرونت
بيرفع صور من خلال المسارين دول وبتلاقيهم مش شغالين، ده هو السبب. قول لينا لو
محتاجين نضيف `POST` عليهم زي ما عملنا مع `/api/account/`.

---

## 4) ملاحظة للـ Claude اللي هيقرأ ده

- كل الأسماء والمسارات هنا موجودة فعليًا في الباك إند — متخترعش بدائل.
- `reopen-registration` و`resubmit` بيرجّعوا نفس شكل payload الفرصة الكامل
  اللي بترجعه `GET /api/volunteer-opportunities/{id}/` — لو عايز تعرف كل
  الحقول، اضرب الـ endpoint ده فعليًا واقرأ الاستجابة بدل التخمين.
- خطأ 404 على `reopen-registration`/`resubmit` معناه إما الفرصة مش موجودة،
  أو إن المستخدم الحالي مش صاحبها — مش خطأ في الكود.
- خطأ 405 على `/api/account/` معناه إن حد لسه بيبعت PATCH/PUT بدل POST.
