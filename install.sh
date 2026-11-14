#!/usr/bin/env bash
# Installer Chizu House untuk Ubuntu 22.04 / 24.04 (Apache + PHP + MariaDB)
# Pemakaian:
#   sudo bash install.sh                      # akses via IP server
#   sudo DOMAIN=toko.contoh.com bash install.sh
#   sudo DOMAIN=toko.contoh.com SSL_EMAIL=kamu@email.com bash install.sh   # + HTTPS Let's Encrypt
set -euo pipefail

REPO_URL="${REPO_URL:-https://github.com/khanhecate/chizuhouse.git}"
APP_DIR="${APP_DIR:-/var/www/chizuhouse}"
DB_NAME="${DB_NAME:-chizuhouse}"
DB_USER="${DB_USER:-chizu}"
DOMAIN="${DOMAIN:-_}"
SSL_EMAIL="${SSL_EMAIL:-}"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [[ $EUID -ne 0 ]]; then
  echo "Jalankan sebagai root: sudo bash install.sh" >&2
  exit 1
fi

if ! grep -qi ubuntu /etc/os-release; then
  echo "Script ini ditujukan untuk Ubuntu." >&2
  exit 1
fi

export DEBIAN_FRONTEND=noninteractive

echo "==> [1/6] Install paket"
apt-get update -y
apt-get install -y apache2 mariadb-server git curl openssl \
  php libapache2-mod-php php-mysql php-mbstring php-xml
if [[ -n "$SSL_EMAIL" && "$DOMAIN" != "_" ]]; then
  apt-get install -y certbot python3-certbot-apache
fi
systemctl enable --now apache2 mariadb

echo "==> [2/6] Ambil source code ke $APP_DIR"
if [[ -f "$SCRIPT_DIR/index.php" && -f "$SCRIPT_DIR/koneksi.php" ]]; then
  # Dijalankan dari dalam folder project: salin ke APP_DIR
  mkdir -p "$APP_DIR"
  if [[ "$SCRIPT_DIR" != "$APP_DIR" ]]; then
    cp -a "$SCRIPT_DIR/." "$APP_DIR/"
  fi
elif [[ -d "$APP_DIR/.git" ]]; then
  git -C "$APP_DIR" pull --ff-only
else
  git clone "$REPO_URL" "$APP_DIR"
fi

echo "==> [3/6] Siapkan database"
CONFIG="$APP_DIR/config.local.php"
if [[ -f "$CONFIG" ]]; then
  # Pakai password yang sudah ada agar install ulang tidak merusak koneksi
  DB_PASS="$(sed -n "s/^\$pass *= *'\(.*\)';/\1/p" "$CONFIG")"
else
  DB_PASS="$(openssl rand -hex 16)"
fi

mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL
mysql "$DB_NAME" < "$APP_DIR/database.sql"

echo "==> [4/6] Tulis konfigurasi koneksi"
cat > "$CONFIG" <<PHP
<?php
\$host = 'localhost';
\$user = '${DB_USER}';
\$pass = '${DB_PASS}';
\$db   = '${DB_NAME}';
PHP
chmod 640 "$CONFIG"

echo "==> [5/6] Konfigurasi Apache"
chown -R www-data:www-data "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 755 {} \;
find "$APP_DIR" -type f -exec chmod 644 {} \;
chmod 640 "$CONFIG"

cat > /etc/apache2/sites-available/chizuhouse.conf <<CONF
<VirtualHost *:80>
    ServerName ${DOMAIN}
    DocumentRoot ${APP_DIR}

    <Directory ${APP_DIR}>
        Options -Indexes +FollowSymLinks
        AllowOverride None
        Require all granted
    </Directory>

    <FilesMatch "^(config\.local\.php|database\.sql|install\.sh|\.gitignore)$">
        Require all denied
    </FilesMatch>
    RedirectMatch 404 /\.git

    ErrorLog \${APACHE_LOG_DIR}/chizuhouse_error.log
    CustomLog \${APACHE_LOG_DIR}/chizuhouse_access.log combined
</VirtualHost>
CONF

a2dissite 000-default >/dev/null 2>&1 || true
a2ensite chizuhouse >/dev/null
apache2ctl configtest
systemctl reload apache2

echo "==> [6/6] Firewall & HTTPS"
if command -v ufw >/dev/null && ufw status | grep -q "Status: active"; then
  ufw allow 'Apache Full' >/dev/null
fi
if [[ -n "$SSL_EMAIL" && "$DOMAIN" != "_" ]]; then
  certbot --apache -d "$DOMAIN" -m "$SSL_EMAIL" --agree-tos --no-eff-email --redirect -n
fi

IP="$(curl -s https://ifconfig.me || hostname -I | awk '{print $1}')"
echo
echo "Instalasi selesai."
if [[ "$DOMAIN" != "_" ]]; then echo "Buka: http://${DOMAIN}"; else echo "Buka: http://${IP}"; fi
echo "Database : ${DB_NAME}  | User: ${DB_USER}  | Password tersimpan di ${CONFIG}"
