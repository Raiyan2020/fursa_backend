#!/usr/bin/env bash
# =============================================================================
# Fursa — Production export on GCP VM (forsa-web-server)
# Run via: SSH → bash 1-gcp-export.sh
# =============================================================================
set -euo pipefail

WEB_CONTAINER="${WEB_CONTAINER:-fursa_web_prod}"
DB_CONTAINER="${DB_CONTAINER:-fursa_db_prod}"
GCS_BUCKET="${GCS_BUCKET:-gs://forsa/public/}"
OUT_DIR="${HOME}"
SQL_FILE="${OUT_DIR}/fursa_prod_latest.sql"
MEDIA_DIR="${OUT_DIR}/prod_media"
TAR_FILE="${OUT_DIR}/fursa_prod_media.tar.gz"

echo "=============================================="
echo " Fursa Production Export"
echo " VM: $(hostname)"
echo " Date: $(date)"
echo "=============================================="

# --- Read DB password from container .env ---
echo ""
echo "[1/4] Reading DB credentials from ${WEB_CONTAINER}..."
DB_PASSWORD="$(sudo docker exec "${WEB_CONTAINER}" sh -c 'grep ^DB_PASSWORD= /app/.env' | cut -d= -f2- | tr -d "\r\"'" || true)"

if [[ -z "${DB_PASSWORD}" ]]; then
  echo "Could not read DB_PASSWORD automatically."
  read -rsp "Enter DB_PASSWORD from .env: " DB_PASSWORD
  echo ""
fi

DB_NAME="$(sudo docker exec "${WEB_CONTAINER}" sh -c 'grep ^DB_NAME= /app/.env' | cut -d= -f2- | tr -d "\r\"'" || echo "fursa")"
DB_USER="$(sudo docker exec "${WEB_CONTAINER}" sh -c 'grep ^DB_USER= /app/.env' | cut -d= -f2- | tr -d "\r\"'" || echo "postgres")"

echo "  DB_NAME=${DB_NAME}"
echo "  DB_USER=${DB_USER}"

# --- PostgreSQL dump ---
echo ""
echo "[2/4] Dumping PostgreSQL → ${SQL_FILE}..."
sudo docker exec -e PGPASSWORD="${DB_PASSWORD}" "${DB_CONTAINER}" \
  pg_dump -U "${DB_USER}" -d "${DB_NAME}" -F p > "${SQL_FILE}"

if ! head -n 3 "${SQL_FILE}" | grep -qi postgres; then
  echo "ERROR: Dump does not look like PostgreSQL. Check password/container."
  exit 1
fi

ls -lh "${SQL_FILE}"
echo "  OK: first lines:"
head -n 5 "${SQL_FILE}"

# --- GCS media ---
echo ""
echo "[3/4] Downloading media from ${GCS_BUCKET}..."

sudo docker cp "${WEB_CONTAINER}:/app/service_key.json" "${HOME}/service_key.json" 2>/dev/null || {
  echo "WARN: service_key.json not found in container. Trying gsutil with VM default credentials..."
}

export GOOGLE_APPLICATION_CREDENTIALS="${HOME}/service_key.json"

rm -rf "${MEDIA_DIR}"
mkdir -p "${MEDIA_DIR}"

if command -v gsutil >/dev/null 2>&1; then
  gsutil -m cp -r "${GCS_BUCKET}"* "${MEDIA_DIR}/"
else
  echo "ERROR: gsutil not found on VM."
  echo "Install Cloud SDK or download from Console:"
  echo "  Cloud Storage → Buckets → forsa → public/ → Download"
  exit 1
fi

echo "  Media folders:"
ls -la "${MEDIA_DIR}" | head -20

# --- Tar for download ---
echo ""
echo "[4/4] Creating archive → ${TAR_FILE}..."
tar -czf "${TAR_FILE}" -C "${MEDIA_DIR}" .
ls -lh "${TAR_FILE}"

echo ""
echo "=============================================="
echo " DONE — Download these files to your Mac:"
echo "   ${SQL_FILE}"
echo "   ${TAR_FILE}"
echo ""
echo " SSH browser: ⋮ → Download file"
echo " Or from Mac (gcloud):"
echo "   gcloud compute scp ${USER}@$(curl -s -H Metadata-Flavor:Google http://metadata.google.internal/computeMetadata/v1/instance/name 2>/dev/null || echo 'forsa-web-server'):${SQL_FILE} ~/Downloads/fursa-sync/"
echo "=============================================="
