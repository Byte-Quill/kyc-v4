#!/usr/bin/env bash
# ============================================================================
# enable-opcache.sh — Turn on PHP OPcache for XAMPP (idempotent).
#
# OPcache keeps the compiled PHP bytecode in shared memory so pages render
# fast even on very low-power hardware. XAMPP ships the extension but leaves
# it disabled; this script appends a clearly-marked configuration block to
# /opt/lampp/etc/php.ini (backing the original up first) and restarts Apache.
#
# Safe to re-run: if OPcache is already configured, nothing happens.
#
# Usage:  sudo bash scripts/enable-opcache.sh
# ============================================================================
set -e

PHP_INI=/opt/lampp/etc/php.ini

if [ "$(id -u)" -ne 0 ]; then
    echo "ERROR: run with sudo:  sudo bash $0"
    exit 1
fi

if [ ! -f "$PHP_INI" ]; then
    echo "ERROR: $PHP_INI not found — is XAMPP installed at /opt/lampp?"
    exit 1
fi

if grep -q "^zend_extension=opcache.so" "$PHP_INI"; then
    echo "OPcache is already enabled in $PHP_INI — nothing to do."
    exit 0
fi

BACKUP="${PHP_INI}.bak-kyc-$(date +%Y%m%d-%H%M%S)"
cp "$PHP_INI" "$BACKUP"
echo "Backed up php.ini -> $BACKUP"

cat >> "$PHP_INI" <<'EOF'

; --- KYC Verify performance: OPcache (added by scripts/enable-opcache.sh) ---
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
echo "OPcache configuration appended to $PHP_INI"

# Restart Apache so the new settings take effect.
/opt/lampp/lampp stopapache >/dev/null 2>&1 || true
/opt/lampp/lampp startapache
echo
echo "✅ OPcache enabled. Verify with: /opt/lampp/bin/php -m | grep -i opcache"
