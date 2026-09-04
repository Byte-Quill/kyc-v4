#!/usr/bin/env bash
# ---------------------------------------------------------------------------
# KYC Verify — one-shot XAMPP deployment (run with sudo)
#   sudo bash scripts/deploy-xampp.sh
#
# 1. Copies the project into the XAMPP web root (htdocs/kyc-v4)
#    — dev-only folders (.git, frontend, dist, scripts) are excluded
# 2. Makes uploads/ writable by Apache (XAMPP runs Apache as 'daemon')
# 3. Enables PHP OPcache (idempotent) for smooth performance on any device
# 4. Starts Apache + MySQL (restarts Apache when OPcache was just enabled)
# 5. Imports install.sql (creates kyc_system DB + seeded staff accounts)
#    or migrates an existing database (missing indexes / new columns)
# 6. Verifies the app responds
#
# Re-run any time to sync code changes into htdocs. Every step is safe to
# repeat — nothing is applied twice.
# ---------------------------------------------------------------------------
set -e

SRC="/home/thunderstrom/Documents/kyc/kyc-v4"
DEST="/opt/lampp/htdocs/kyc-v4"
MYSQL="/opt/lampp/bin/mysql"
PHP_INI="/opt/lampp/etc/php.ini"

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
    --exclude 'scripts' \
    --exclude 'composer.phar' \
    "$SRC/" "$DEST/"
else
  cp -a "$SRC/." "$DEST/"
  rm -rf "$DEST/.git" "$DEST/frontend" "$DEST/dist" "$DEST/scripts" "$DEST/composer.phar"
fi
echo "Copied project to $DEST"

# --- 2. Upload folder permissions --------------------------------------------
mkdir -p "$DEST/uploads"
chmod -R 0777 "$DEST/uploads"
echo "uploads/ is writable"

# --- 3. Enable PHP OPcache (safe to re-run) ----------------------------------
# OPcache keeps compiled PHP in shared memory so pages render fast even on
# very low-power hardware. The block is appended once and skipped when present.
OPCACHE_CHANGED=0
if ! grep -q "^zend_extension=opcache.so" "$PHP_INI"; then
  cp "$PHP_INI" "${PHP_INI}.bak-kyc-$(date +%Y%m%d-%H%M%S)"
  cat >> "$PHP_INI" <<'EOF'

; --- KYC Verify performance: OPcache (added by scripts/deploy-xampp.sh) -----
; Keeps compiled PHP in shared memory — big speedup on low-power devices.
; validate_timestamps stays ON with a 2s revalidate so code edits apply fast.
zend_extension=opcache.so
opcache.enable=1
opcache.enable_cli=0
opcache.memory_consumption=64
opcache.interned_strings_buffer=8
opcache.max_accelerated_files=4000
opcache.validate_timestamps=1
opcache.revalidate_freq=2
EOF
  OPCACHE_CHANGED=1
  echo "OPcache enabled in $PHP_INI (backup saved alongside)."
else
  echo "OPcache already enabled — skipped."
fi

# --- 4. Start Apache & MySQL ---------------------------------------------------
# Restart Apache when the PHP config just changed so OPcache loads.
if [ "$OPCACHE_CHANGED" = "1" ] && pgrep -x httpd >/dev/null 2>&1; then
  /opt/lampp/lampp stopapache
fi
/opt/lampp/lampp startapache
/opt/lampp/lampp startmysql
sleep 2

# --- 5. Import database schema (only if not already present) -------------------
if [ -z "$("$MYSQL" -uroot -N -e "SHOW DATABASES LIKE 'kyc_system'" 2>/dev/null)" ]; then
  "$MYSQL" -uroot < "$DEST/install.sql"
  echo "Database kyc_system imported (tables + seeded staff accounts)."
else
  echo "Database kyc_system already exists — import skipped (drop it first to re-import)."

  # Performance indexes for databases created before install.sql had them.
  # Each index is only created when missing, so this is safe to re-run.
  ensure_index() {
    local table="$1" index="$2" cols="$3"
    local exists
    exists=$("$MYSQL" -uroot -N -e "SELECT COUNT(*) FROM information_schema.statistics WHERE table_schema='kyc_system' AND table_name='$table' AND index_name='$index'" 2>/dev/null)
    if [ "$exists" = "0" ]; then
      "$MYSQL" -uroot kyc_system -e "CREATE INDEX $index ON $table ($cols)"
      echo "Added index $index on $table($cols)"
    fi
  }
  ensure_index applications idx_applicant applicant_id
  ensure_index applications idx_updated updated_at
  ensure_index applications idx_created created_at
  ensure_index audit_logs idx_application application_id
  ensure_index email_logs idx_status status

  # New identity-document columns added after the first release.
  # Each column is only added when missing, so this is safe to re-run.
  ensure_column() {
    local table="$1" column="$2" definition="$3"
    local exists
    exists=$("$MYSQL" -uroot -N -e "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='kyc_system' AND table_name='$table' AND column_name='$column'" 2>/dev/null)
    if [ "$exists" = "0" ]; then
      "$MYSQL" -uroot kyc_system -e "ALTER TABLE $table ADD COLUMN $column $definition"
      echo "Added column $table.$column"
    fi
  }
  ensure_column applications id_issue_date "DATE NULL AFTER id_number"
  ensure_column applications issuing_district "VARCHAR(100) NULL AFTER id_issue_date"

  # Drop legacy identity-document columns removed after the first release.
  drop_column() {
    local table="$1" column="$2"
    local exists
    exists=$("$MYSQL" -uroot -N -e "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='kyc_system' AND table_name='$table' AND column_name='$column'" 2>/dev/null)
    if [ "$exists" = "1" ]; then
      "$MYSQL" -uroot kyc_system -e "ALTER TABLE $table DROP COLUMN $column"
      echo "Dropped legacy column $table.$column"
    fi
  }
  drop_column applications id_expiry
  drop_column applications issuing_country
fi

# --- 6. Verify ------------------------------------------------------------------
# Regenerate the minified stylesheet so the deployed copy always matches
# the current source CSS (layout.php serves the .min.css when it is fresh).
if command -v php >/dev/null 2>&1; then
  php "$SRC/scripts/minify-css.php" >/dev/null 2>&1 \
    && cp "$SRC/assets/style.min.css" "$DEST/assets/style.min.css" \
    && echo "Minified stylesheet refreshed."
fi

CODE=$(curl -s -o /dev/null -w '%{http_code}' http://localhost/kyc-v4/index.php)
echo
echo "App health check: HTTP $CODE"
echo "App URL:      http://localhost/kyc-v4/"
echo "phpMyAdmin:   http://localhost/phpmyadmin"
