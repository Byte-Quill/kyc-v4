#!/usr/bin/env bash
# ============================================================================
# fix-xampp-mysql.sh — Repair XAMPP's broken MariaDB (InnoDB corruption).
#
# Use this when `sudo /opt/lampp/lampp startmysql` starts MySQL but it
# crashes immediately (stale socket, errors about InnoDB or permissions in
# /opt/lampp/var/mysql/LX.err).
#
# What it does:
#   1. Stops XAMPP MySQL (if running)
#   2. Moves the corrupted /opt/lampp/var/mysql aside as a backup
#   3. Re-initializes a fresh MariaDB data directory
#   4. Starts MySQL and sets root@localhost to an empty password
#      (matches DB_PASS="" in .env)
#   5. Imports install.sql (kyc_system database + seeded staff accounts)
#
# Usage:  sudo bash scripts/fix-xampp-mysql.sh
# ============================================================================
set -e

STAMP=$(date +%Y%m%d-%H%M%S)
DATADIR=/opt/lampp/var/mysql
MYSQL=/opt/lampp/bin/mysql
INSTALL_SQL=/opt/lampp/htdocs/kyc-v4/install.sql

if [ "$(id -u)" -ne 0 ]; then
    echo "ERROR: run with sudo:  sudo bash $0"
    exit 1
fi

echo "[1/6] Stopping XAMPP MySQL (if running)..."
/opt/lampp/lampp stopmysql >/dev/null 2>&1 || true
sleep 1

echo "[2/6] Backing up corrupted data dir -> ${DATADIR}.broken-${STAMP}"
if [ -d "$DATADIR" ]; then
    mv "$DATADIR" "${DATADIR}.broken-${STAMP}"
fi

echo "[3/6] Creating fresh data directory..."
mkdir -p "$DATADIR"
chown mysql:mysql "$DATADIR"

echo "[4/6] Initializing fresh MariaDB database files..."
/opt/lampp/bin/mariadb-install-db \
    --user=mysql \
    --datadir="$DATADIR" \
    --auth-root-authentication-method=normal >/dev/null

echo "[5/6] Starting MySQL..."
/opt/lampp/lampp startmysql
sleep 3

# Ensure root works over TCP with an empty password (what .env uses).
"$MYSQL" -uroot -e "ALTER USER 'root'@'localhost' IDENTIFIED VIA mysql_native_password USING PASSWORD(''); FLUSH PRIVILEGES;" 2>/dev/null || true

echo "[6/6] Importing kyc_system schema from install.sql..."
if [ ! -f "$INSTALL_SQL" ]; then
    echo "WARNING: $INSTALL_SQL not found — deploy the app first (sudo bash scripts/deploy-xampp.sh)."
else
    "$MYSQL" -uroot < "$INSTALL_SQL"
    echo
    echo "Seeded accounts:"
    "$MYSQL" -uroot -e "SELECT u.email, ur.role FROM kyc_system.users u JOIN kyc_system.user_roles ur ON ur.user_id = u.id;"
fi

echo
echo "✅ Done. Old corrupted data preserved at ${DATADIR}.broken-${STAMP}"
echo "   App: http://localhost/kyc-v4/   (login: ceo@kyc.local / Password123)"
