#!/usr/bin/env bash
# =============================================================================
# Fursa — cPanel: extract media + Laravel post-import commands
# Run on server after uploading fursa_prod_media.tar.gz to storage/app/
#
# Usage:
#   cd ~/portal   # adjust path
#   bash 3-cpanel-import.sh
# =============================================================================
set -euo pipefail

# --- Adjust if your path differs ---
PORTAL_DIR="${PORTAL_DIR:-${HOME}/portal}"
STORAGE_APP="${PORTAL_DIR}/storage/app"
PUBLIC_DIR="${STORAGE_APP}/public"
TAR_FILE="${STORAGE_APP}/fursa_prod_media.tar.gz"

echo "=============================================="
echo " Fursa cPanel Import (media + artisan)"
echo " Portal: ${PORTAL_DIR}"
echo "=============================================="

if [[ ! -d "${PORTAL_DIR}" ]]; then
  echo "ERROR: ${PORTAL_DIR} not found."
  echo "Set PORTAL_DIR, e.g.:"
  echo "  PORTAL_DIR=~/public_html/_apps/fursa-backend bash 3-cpanel-import.sh"
  exit 1
fi

if [[ ! -f "${TAR_FILE}" ]]; then
  echo "ERROR: ${TAR_FILE} not found."
  echo "Upload fursa_prod_media.tar.gz to ${STORAGE_APP}/ first."
  exit 1
fi

echo ""
echo "[1/4] Backup current public media (optional)..."
BACKUP="${STORAGE_APP}/public_backup_$(date +%Y%m%d_%H%M%S)"
mkdir -p "${BACKUP}"
cp -a "${PUBLIC_DIR}/." "${BACKUP}/" 2>/dev/null || true
echo "  Backup: ${BACKUP}"

echo ""
echo "[2/4] Extract media archive..."
mkdir -p "${PUBLIC_DIR}"
tar -xzf "${TAR_FILE}" -C "${PUBLIC_DIR}"

echo "  Sample folders:"
for d in banner_images event_images opportunity_images post_images profile_pics; do
  if [[ -d "${PUBLIC_DIR}/${d}" ]]; then
    echo "    ${d}/ — $(find "${PUBLIC_DIR}/${d}" -type f 2>/dev/null | wc -l | tr -d ' ') files"
  fi
done

echo ""
echo "[3/4] Permissions..."
chmod -R 775 "${PUBLIC_DIR}" 2>/dev/null || chmod -R 755 "${PUBLIC_DIR}"

echo ""
echo "[4/4] Laravel commands..."
cd "${PORTAL_DIR}"
php artisan storage:link 2>/dev/null || true
php artisan migrate --force
php artisan config:clear
php artisan config:cache
php artisan optimize:clear 2>/dev/null || true

echo ""
echo "=============================================="
echo " DONE"
echo ""
echo " Verify image URL (replace path from DB):"
echo "   https://portal.fursa.raiyan.cc/storage/banner_images/..."
echo ""
echo " Optional — remove archive after verify:"
echo "   rm ${TAR_FILE}"
echo "=============================================="
