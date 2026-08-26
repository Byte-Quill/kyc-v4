#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# KYC Verify — one-shot XAMPP deployment (run with sudo)
#   sudo bash deploy-xampp.sh
#
# 1. Copies the project into the XAMPP web root (htdocs/kyc-v4)
# 2. Makes uploads/ writable by Apache (XAMPP runs Apache as 'daemon')
# 3. Starts Apache + MySQL
# 4. Imports install.sql (creates kyc_system DB + seeded staff accounts)
# 5. Verifies the app responds
#
# Re-run any time to sync code changes into htdocs.
# ---------------------------------------------------------------------------
set -e

SRC="/home/thunderstrom/Documents/kyc/kyc-v4"
DEST="/opt/lampp/htdocs/kyc-v4"
MYSQL="/opt/lampp/bin/mysql"

if [ "$(id -u)" -ne 0 ]; then
  echo "Please run this script with sudo:  sudo bash $0"
  exit 1
fi

# --- 1. Copy project into web root ------------------------------------------
mkdir -p "$DEST"
if command -v rsync >/dev/null 2>&1; then
  rsync -a --delete \
    --exclude '.git' \
    --exclude 'frontend' \
    --exclude 'dist' \
    --exclude 'composer.phar' \
    --exclude 'deploy-xampp.sh' \
    "$SRC/" "$DEST/"
else
  cp -a "$SRC/." "$DEST/"
  rm -rf "$DEST/.git" "$DEST/frontend" "$DEST/dist" "$DEST/composer.phar" "$DEST/deploy-xampp.sh"
fi
echo "Copied project to $DEST"

# --- 2. Upload folder permissions --------------------------------------------
mkdir -p "$DEST/uploads"
chmod -R 0777 "$DEST/uploads"
echo "uploads/ is writable"

# --- 3. Start Apache & MySQL ---------------------------------------------------
/opt/lampp/lampp startapache
/opt/lampp/lampp startmysql
sleep 2

# --- 4. Import database schema (only if not already present) -------------------
if [ -z "$("$MYSQL" -uroot -N -e "SHOW DATABASES LIKE 'kyc_system'" 2>/dev/null)" ]; then
  "$MYSQL" -uroot < "$DEST/install.sql"
  echo "Database kyc_system imported (tables + seeded staff accounts)."
else
  echo "Database kyc_system already exists — import skipped (drop it first to re-import)."
fi

# --- 5. Verify ------------------------------------------------------------------
CODE=$(curl -s -o /dev/null -w '%{http_code}' http://localhost/kyc-v4/index.php)
echo
echo "App health check: HTTP $CODE"
echo "App URL:      http://localhost/kyc-v4/"
echo "phpMyAdmin:   http://localhost/phpmyadmin"
