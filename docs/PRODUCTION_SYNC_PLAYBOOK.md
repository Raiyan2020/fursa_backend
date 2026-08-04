# Fursa — Production Data & Media Sync Playbook

دليل تسليم / تحديث: سحب **أحدث داتابيز Production** و**أحدث الصور** من GCP، تحويلها لـ MySQL، واستيرادها على سيرفر Laravel مع رفع الملفات في `storage`.

> **ملاحظة أمان:** كلمات المرور الحقيقية اقرأها من `.env` على السيرفر — لا تعتمد على نسخ قديمة من الشات.

---

## نظرة سريعة

| المصدر | القيمة |
|--------|--------|
| Cloud | GCP — حساب `joinforsa@gmail.com` |
| مشروع GCP | `forsa-480208` (مش Raiyansoft) |
| Production VM | `forsa-web-server` — IP `34.18.149.205` |
| Staging VM | `forsa-staging-server` — **متستخدمهوش** للتسليم |
| GCS Bucket (Prod) | `gs://forsa/public/` |
| Laravel project path | `/home/fursanew/portal` |
| Media target | `/home/fursanew/portal/storage/app/public/` |
| Public URL | `https://portal.fursa.raiyan.cc/storage/{path}` |

مسارات الداتابيز (أمثلة):

```text
banner_images/...
event_images/...
opportunity_images/...
post_images/...
profile_pics/...
```

لازم تبقى نفس الهيكل تحت `storage/app/public/`.

---

## 0) قبل ما تبدأ

1. ادخل [Google Cloud Console](https://console.cloud.google.com) بحساب له صلاحية على مشروع **Forsa** (`forsa-480208`).
2. فوق شمال: غيّر المشروع من `Raiyansoft` إلى مشروع Forsa.
3. تأكد: **Compute Engine → VM instances** فيه `forsa-web-server`.
4. تأكد: **Cloud Storage → Buckets** فيه bucket اسمه `forsa`.

---

## 1) سحب أحدث داتابيز Production (PostgreSQL)

### 1.1 ادخل على الـ VM

`Compute Engine → VM instances → forsa-web-server → SSH`

### 1.2 اقرأ بيانات الاتصال من `.env`

```bash
sudo docker exec fursa_web_prod sh -c 'grep -E "^(DB_|GCS_|STORAGE_)" /app/.env'
```

المتوقع تقريبًا:

```text
DB_HOST=fursa_db_prod
DB_NAME=fursa
DB_USER=postgres
DB_PASSWORD=********
DB_PORT=5432
STORAGE_TYPE=gcp
GCS_BUCKET_NAME=forsa
GCS_PROJECT_ID=forsa-480208
STORAGE_PATH=public/
```

### 1.3 اعمل dump

استبدل `PASS` بقيمة `DB_PASSWORD` من الأمر السابق:

```bash
sudo docker exec -e PGPASSWORD='PASS' fursa_db_prod \
  pg_dump -U postgres -d fursa -F p > ~/fursa_prod_latest.sql

ls -lh ~/fursa_prod_latest.sql
head -n 15 ~/fursa_prod_latest.sql
```

لازم تشوف في أول الملف: `PostgreSQL database dump`.

### 1.4 نزّل الملف لجهازك

من نافذة SSH: **⋮ → Download file**

Path (مسار السيرفر، مش جهازك):

```text
/home/raiyansoft_com/fursa_prod_latest.sql
```

> اسم الـ home user ممكن يختلف — لو مش `raiyansoft_com` استخدم:

```bash
echo $HOME
ls -lh ~/fursa_prod_latest.sql
```

احفظ الملف محليًا مثلاً:

```text
D:\Heard\fursa\fursa_prod_latest.sql
```

---

## 2) سحب أحدث صور Production من GCS

على نفس SSH لـ `forsa-web-server`:

```bash
mkdir -p ~/prod_media

# انسخ Service Account من الكونتينر
sudo docker cp fursa_web_prod:/app/service_key.json ~/service_key.json

export GOOGLE_APPLICATION_CREDENTIALS=~/service_key.json

# حمّل كل ملفات public من Bucket الإنتاج
gsutil -m cp -r gs://forsa/public/* ~/prod_media/

# اضغط للتحميل
tar -czf ~/fursa_prod_media.tar.gz -C ~/prod_media .
ls -lh ~/fursa_prod_media.tar.gz
du -sh ~/prod_media
ls ~/prod_media
```

Download من SSH:

```text
/home/raiyansoft_com/fursa_prod_media.tar.gz
```

احفظه مثلاً:

```text
C:\Users\user\Downloads\fursa_prod_media.tar.gz
```

### بديل من Console (بدون gsutil)

`Cloud Storage → Buckets → forsa → public/ → Download`

---

## 3) تحويل PostgreSQL → MySQL (على جهاز التطوير)

من مجلد مشروع Laravel:

```powershell
cd D:\Heard\fursa
python tools\pg_to_mysql.py fursa_prod_latest.sql fursa_prod_mysql.sql
```

- **استورد:** `fursa_prod_mysql.sql`
- **متستوردش:** `fursa_prod_latest.sql` (PostgreSQL)

فحص تكرارات حساسة لحالة الأحرف (اختياري):

```powershell
python tools\check_dup_emails.py fursa_prod_mysql.sql
python tools\check_user_ci_dupes.py fursa_prod_mysql.sql
```

---

## 4) استيراد MySQL على سيرفر Laravel (phpMyAdmin)

### 4.1 Backup أولًا

من phpMyAdmin: Export لقاعدة بيانات Laravel الحالية.

### 4.2 migrations موجودة

على السيرفر:

```bash
cd ~/portal
php artisan migrate --force
```

### 4.3 جهّز `users` لقبول داتا Production كما هي

PostgreSQL كان case-sensitive على `username` / `email`. MySQL العادي مش كده.

في phpMyAdmin → SQL:

```sql
SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM `users`;

ALTER TABLE `users`
  MODIFY `username` VARCHAR(255)
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_bin
  NULL;

ALTER TABLE `users`
  MODIFY `email` VARCHAR(255)
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_bin
  NOT NULL;

SET FOREIGN_KEY_CHECKS = 1;
```

> استخدم `DELETE` مش `TRUNCATE` لو ظهر خطأ `#1701` بسبب Foreign Keys.

### 4.4 Import

phpMyAdmin → اختَر الـ DB → **Import** → ارفع:

```text
fursa_prod_mysql.sql
```

### 4.5 بعد الاستيراد

```bash
cd ~/portal
php artisan migrate --force
php artisan storage:link
php artisan config:clear
php artisan config:cache
```

---

## 5) رفع الصور إلى Laravel Storage

الهدف:

```text
/home/fursanew/portal/storage/app/public/
```

بحيث يصبح:

```text
.../public/banner_images/...
.../public/event_images/...
.../public/opportunity_images/...
.../public/post_images/...
```

### 5.1 الطريقة الموصى بها (tar.gz على السيرفر)

1. من cPanel File Manager ارفع `fursa_prod_media.tar.gz` إلى:

```text
portal/storage/app/
```

2. من **cPanel → Terminal**:

```bash
cd ~/portal/storage/app

# Backup للميديا الحالية (اختياري)
mkdir -p public_backup_$(date +%Y%m%d)
cp -a public/. public_backup_$(date +%Y%m%d)/ 2>/dev/null || true

# فك داخل public (مش داخل مجلد باسم fursa_prod_media)
tar -xzf fursa_prod_media.tar.gz -C public

ls public/banner_images | head
ls public/event_images | head
ls public/opportunity_images | head
ls public/post_images | head

chmod -R 775 public
cd ~/portal
php artisan storage:link
```

3. احذف الأرشيف بعد التأكد (اختياري):

```bash
rm ~/portal/storage/app/fursa_prod_media.tar.gz
```

### 5.2 لو الأرشيف اتفك محليًا على ويندوز

المجلد المحلي:

```text
C:\Users\user\Downloads\fursa_prod_media\
```

ارفع **محتويات** المجلد (المجلدات والملفات اللي جواه) إلى:

```text
/home/fursanew/portal/storage/app/public/
```

**غلط:**  
`public/fursa_prod_media/banner_images/...`

**صح:**  
`public/banner_images/...`

يمكن باستخدام FileZilla:

| | |
|--|--|
| Host | `fursa.raiyan.cc` / سيرفر cPanel |
| User | يوزر cPanel (`fursanew`) |
| Remote | `/home/fursanew/portal/storage/app/public/` |
| Local | محتويات `fursa_prod_media` |

### 5.3 تحقق من رابط صورة

```text
https://portal.fursa.raiyan.cc/storage/opportunity_images/<uuid>/cropped-image.jpg
```

أو أي مسار موجود في الداتابيز عبر `getimg()`.

---

## 6) أوامر سريعة (Copy/Paste checklist)

### على GCP Production VM

```bash
# DB credentials
sudo docker exec fursa_web_prod sh -c 'grep -E "^(DB_|GCS_)" /app/.env'

# Dump DB
sudo docker exec -e PGPASSWORD='PASS_FROM_ENV' fursa_db_prod \
  pg_dump -U postgres -d fursa -F p > ~/fursa_prod_latest.sql

# Media
sudo docker cp fursa_web_prod:/app/service_key.json ~/service_key.json
export GOOGLE_APPLICATION_CREDENTIALS=~/service_key.json
mkdir -p ~/prod_media
gsutil -m cp -r gs://forsa/public/* ~/prod_media/
tar -czf ~/fursa_prod_media.tar.gz -C ~/prod_media .
ls -lh ~/fursa_prod_latest.sql ~/fursa_prod_media.tar.gz
```

### على جهاز التطوير (Windows)

```powershell
cd D:\Heard\fursa
python tools\pg_to_mysql.py fursa_prod_latest.sql fursa_prod_mysql.sql
```

### على سيرفر Laravel (cPanel Terminal)

```bash
cd ~/portal/storage/app
tar -xzf fursa_prod_media.tar.gz -C public
chmod -R 775 public
cd ~/portal
php artisan storage:link
php artisan migrate --force
php artisan config:cache
```

---

## 7) أخطاء شائعة

| الخطأ | السبب | الحل |
|-------|--------|------|
| VM instances فاضية | مشروع GCP غلط (`Raiyansoft`) | اختَر مشروع Forsa / `forsa-480208` |
| `#1062 Duplicate entry ... email/username` | MySQL case-insensitive | `COLLATE utf8mb4_bin` على العمودين |
| `#1701 Cannot truncate ... foreign key` | TRUNCATE مع FK | `DELETE FROM` بدل `TRUNCATE` |
| صور 404 | مسار غلط أو مفيش `storage:link` | تأكد المسار تحت `public/` + `php artisan storage:link` |
| مسارات `public/...` في DB | بقايا Django/GCS | السكربت يشيلها؛ أو migration `normalize_stored_image_paths` |
| استخدام `forsa_staging` bucket | Staging مش Production | استخدم `gs://forsa/public/` فقط |

---

## 8) ملفات مساعدة في المشروع

| الملف | الوظيفة |
|------|---------|
| `tools/pg_to_mysql.py` | تحويل dump Django/PostgreSQL → MySQL Laravel |
| `tools/import_sql.php` | استيراد SQL عبر Eloquent/DB محليًا |
| `tools/build_download_script.py` | تحويل signed URLs إلى سكربت تحميل |
| `tools/check_dup_emails.py` | كشف إيميلات مكررة |
| `tools/check_user_ci_dupes.py` | كشف تكرارات username/email case-insensitive |
| `docs/PRODUCTION_SYNC_PLAYBOOK.md` | هذا الدليل |

---

## 9) ترتيب التنفيذ عند التسليم

```text
1. GCP Console → المشروع الصحيح → SSH على forsa-web-server
2. pg_dump → Download fursa_prod_latest.sql
3. gsutil/media → Download fursa_prod_media.tar.gz
4. python tools/pg_to_mysql.py → fursa_prod_mysql.sql
5. Backup DB على Laravel
6. ALTER username/email إلى utf8mb4_bin + مسح users إن لزم
7. Import fursa_prod_mysql.sql في phpMyAdmin
8. رفع/فك الميديا إلى portal/storage/app/public/
9. php artisan storage:link && migrate && config:cache
10. اختبار روابط الصور من الداتابيز
```
