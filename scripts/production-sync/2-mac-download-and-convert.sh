#!/usr/bin/env bash
# =============================================================================
# Fursa — Mac: download from GCP (optional) + convert PG → MySQL
#
# Usage:
#   cd /path/to/fursa
#   chmod +x scripts/production-sync/2-mac-download-and-convert.sh
#
#   # Convert only (files already in ~/Downloads/fursa-sync/):
#   ./scripts/production-sync/2-mac-download-and-convert.sh --convert
#
#   # Download via gcloud scp + convert:
#   export GCP_VM_USER="raiyansoft_com"
#   export GCP_VM="forsa-web-server"
#   export GCP_ZONE="me-central1-a"
#   ./scripts/production-sync/2-mac-download-and-convert.sh --download --convert
#
#   # Full (download + convert + show upload instructions):
#   ./scripts/production-sync/2-mac-download-and-convert.sh --all
# =============================================================================
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "${SCRIPT_DIR}/../.." && pwd)"
SYNC_DIR="${SYNC_DIR:-${HOME}/Downloads/fursa-sync}"

GCP_VM_USER="${GCP_VM_USER:-raiyansoft_com}"
GCP_VM="${GCP_VM:-forsa-web-server}"
GCP_ZONE="${GCP_ZONE:-me-central1-a}"
GCP_PROJECT="${GCP_PROJECT:-forsa-480208}"

PG_FILE="${SYNC_DIR}/fursa_prod_latest.sql"
MYSQL_FILE="${SYNC_DIR}/fursa_prod_mysql.sql"
MEDIA_TAR="${SYNC_DIR}/fursa_prod_media.tar.gz"

DO_DOWNLOAD=false
DO_CONVERT=false
DO_ALL=false

for arg in "$@"; do
  case "$arg" in
    --download) DO_DOWNLOAD=true ;;
    --convert)  DO_CONVERT=true ;;
    --all)      DO_ALL=true; DO_DOWNLOAD=true; DO_CONVERT=true ;;
    -h|--help)
      sed -n '2,20p' "$0"
      exit 0
      ;;
    *)
      echo "Unknown option: $arg (use --download, --convert, or --all)"
      exit 1
      ;;
  esac
done

if [[ "$DO_DOWNLOAD" == false && "$DO_CONVERT" == false ]]; then
  echo "Nothing to do. Use --convert, --download, or --all"
  echo "Example: $0 --convert"
  exit 1
fi

mkdir -p "${SYNC_DIR}"

echo "=============================================="
echo " Fursa Mac Sync"
echo " Project: ${PROJECT_ROOT}"
echo " Sync dir: ${SYNC_DIR}"
echo "=============================================="

download_from_gcp() {
  echo ""
  echo "[Download] Using gcloud scp..."

  if ! command -v gcloud >/dev/null 2>&1; then
    echo "ERROR: gcloud not installed."
    echo "  brew install --cask google-cloud-sdk"
    echo "  gcloud auth login"
    echo "  gcloud config set project ${GCP_PROJECT}"
    echo ""
    echo "Or download manually from GCP SSH browser:"
    echo "  ⋮ → Download file → /home/${GCP_VM_USER}/fursa_prod_latest.sql"
    echo "  ⋮ → Download file → /home/${GCP_VM_USER}/fursa_prod_media.tar.gz"
    echo "Save to: ${SYNC_DIR}/"
    exit 1
  fi

  gcloud config set project "${GCP_PROJECT}" 2>/dev/null || true

  REMOTE="${GCP_VM_USER}@${GCP_VM}:${HOME}/"
  # Remote paths on VM use VM user's home
  REMOTE_SQL="${GCP_VM_USER}@${GCP_VM}:/home/${GCP_VM_USER}/fursa_prod_latest.sql"
  REMOTE_TAR="${GCP_VM_USER}@${GCP_VM}:/home/${GCP_VM_USER}/fursa_prod_media.tar.gz"

  echo "  VM: ${GCP_VM} (zone: ${GCP_ZONE})"
  gcloud compute scp --zone="${GCP_ZONE}" "${REMOTE_SQL}" "${SYNC_DIR}/" || {
    echo "Try setting GCP_VM_USER (current: ${GCP_VM_USER})"
    exit 1
  }
  gcloud compute scp --zone="${GCP_ZONE}" "${REMOTE_TAR}" "${SYNC_DIR}/" || {
    echo "Media tar download failed — you can download it manually from SSH."
  }

  ls -lh "${SYNC_DIR}/"
}

convert_pg_to_mysql() {
  echo ""
  echo "[Convert] PostgreSQL → MySQL..."

  if [[ ! -f "${PG_FILE}" ]]; then
    echo "ERROR: Missing ${PG_FILE}"
    echo "Download from GCP first or place fursa_prod_latest.sql in ${SYNC_DIR}/"
    exit 1
  fi

  PYTHON="python3"
  command -v python3 >/dev/null 2>&1 || PYTHON="python"

  "${PYTHON}" "${PROJECT_ROOT}/tools/pg_to_mysql.py" "${PG_FILE}" "${MYSQL_FILE}"

  ls -lh "${MYSQL_FILE}"
  echo "  First lines:"
  head -n 5 "${MYSQL_FILE}"

  echo ""
  echo "[Optional checks]"
  if [[ -f "${PROJECT_ROOT}/tools/check_dup_emails.py" ]]; then
    "${PYTHON}" "${PROJECT_ROOT}/tools/check_dup_emails.py" "${MYSQL_FILE}" || true
  fi
  if [[ -f "${PROJECT_ROOT}/tools/check_user_ci_dupes.py" ]]; then
    "${PYTHON}" "${PROJECT_ROOT}/tools/check_user_ci_dupes.py" "${MYSQL_FILE}" || true
  fi
}

print_upload_instructions() {
  echo ""
  echo "=============================================="
  echo " NEXT STEPS — Upload to cPanel"
  echo "=============================================="
  echo ""
  echo "1) phpMyAdmin:"
  echo "   - Backup current DB"
  echo "   - Run: scripts/production-sync/prepare-users.sql"
  echo "   - Import: ${MYSQL_FILE}"
  echo ""
  echo "2) File Manager — upload to portal/storage/app/:"
  echo "   ${MEDIA_TAR}"
  echo ""
  echo "3) cPanel Terminal:"
  echo "   cd ~/portal"
  echo "   bash scripts/production-sync/3-cpanel-import.sh"
  echo ""
  echo "4) Test:"
  echo "   https://portal.fursa.raiyan.cc/api/home/"
  echo "=============================================="
}

[[ "$DO_DOWNLOAD" == true ]] && download_from_gcp
[[ "$DO_CONVERT" == true ]] && convert_pg_to_mysql
[[ "$DO_ALL" == true ]] && print_upload_instructions

echo ""
echo "Done."
