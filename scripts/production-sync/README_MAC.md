# Fursa — Production Sync من Mac

دليل تنفيذ سحب **داتابيز + صور Production** من GCP ورفعها على Laravel (cPanel).

## الملفات الجاهزة

| الملف | أين تشغّله |
|-------|-------------|
| `1-gcp-export.sh` | على GCP VM (`forsa-web-server`) عبر SSH |
| `2-mac-download-and-convert.sh` | على Mac (بعد ما تنزل الملفات أو بـ gcloud scp) |
| `3-cpanel-import.sh` | على cPanel Terminal |
| `prepare-users.sql` | phpMyAdmin قبل Import |

---

## المتطلبات على Mac

```bash
# Python 3 (غالبًا موجود)
python3 --version

# اختياري — لتحميل الملفات من GCP بدون Browser
brew install --cask google-cloud-sdk
gcloud auth login
gcloud config set project forsa-480208
```

---

## الخطوات بالترتيب

### 1) GCP — سحب DB + Media

1. افتح [Google Cloud Console](https://console.cloud.google.com)
2. اختَر مشروع **forsa-480208** (مش Raiyansoft)
3. **Compute Engine → VM instances → forsa-web-server → SSH**
4. انسخ السكربت للـ VM أو اكتبه يدويًا:

```bash
# على الـ VM — ارفع السكript أو انسخ محتواه
nano ~/1-gcp-export.sh
# الصق محتوى scripts/production-sync/1-gcp-export.sh
chmod +x ~/1-gcp-export.sh
~/1-gcp-export.sh
```

5. السكربت هيطلب `DB_PASSWORD` من `.env` — أو اقرأها يدويًا:

```bash
sudo docker exec fursa_web_prod sh -c 'grep ^DB_PASSWORD= /app/.env'
```

6. بعد ما يخلص، هتلاقي:
   - `~/fursa_prod_latest.sql`
   - `~/fursa_prod_media.tar.gz`

---

### 2) Mac — تنزيل + تحويل

#### طريقة A: Download من Browser (SSH window)

في نافذة SSH: **⋮ → Download file**

- `/home/YOUR_USER/fursa_prod_latest.sql`
- `/home/YOUR_USER/fursa_prod_media.tar.gz`

احفظهم في:

```text
~/Downloads/fursa-sync/
```

#### طريقة B: gcloud scp من Mac

```bash
cd /path/to/fursa
chmod +x scripts/production-sync/2-mac-download-and-convert.sh

# عدّل USER في السكript أو مرّر:
export GCP_VM_USER="raiyansoft_com"
export GCP_VM="forsa-web-server"
export GCP_ZONE="me-central1-a"   # عدّل الـ zone حسب VM

./scripts/production-sync/2-mac-download-and-convert.sh --download
```

#### تحويل PostgreSQL → MySQL

```bash
cd /path/to/fursa

# لو نزلت يدويًا:
mkdir -p ~/Downloads/fursa-sync
# حط fursa_prod_latest.sql في ~/Downloads/fursa-sync/

./scripts/production-sync/2-mac-download-and-convert.sh --convert
```

النتيجة: `~/Downloads/fursa-sync/fursa_prod_mysql.sql`

---

### 3) cPanel — Import DB + Media

#### 3.1 Backup

phpMyAdmin → Export للـ DB الحالية.

#### 3.2 SQL قبل Import

phpMyAdmin → SQL → نفّذ محتوى `prepare-users.sql`

#### 3.3 Import

phpMyAdmin → Import → `fursa_prod_mysql.sql`

#### 3.4 رفع الصور

**File Manager:** ارفع `fursa_prod_media.tar.gz` إلى:

```text
portal/storage/app/
```

**Terminal:**

```bash
cd ~/portal   # أو مسار مشروعك
bash 3-cpanel-import.sh
```

(انسخ `3-cpanel-import.sh` للسيرفر أو الصق أوامره)

---

## مسارات مهمة

| | |
|--|--|
| GCP Project | `forsa-480208` |
| Production VM | `forsa-web-server` |
| GCS Bucket | `gs://forsa/public/` |
| Laravel (cPanel) | `~/portal` أو `~/public_html/_apps/fursa-backend` |
| Media target | `storage/app/public/` |

---

## تحقق

```text
https://portal.fursa.raiyan.cc/api/home/
https://portal.fursa.raiyan.cc/storage/banner_images/...
```

---

## أخطاء شائعة

| المشكلة | الحل |
|---------|------|
| `permission denied` على Mac | `chmod +x scripts/production-sync/*.sh` |
| `python3: command not found` | `brew install python` |
| `gsutil: command not found` على VM | السكript يحاول تثبيت أو استخدم Console Download |
| Duplicate email على Import | نفّذ `prepare-users.sql` أولًا |
| صور 404 | `php artisan storage:link` + تأكد المسار `public/banner_images/` |
