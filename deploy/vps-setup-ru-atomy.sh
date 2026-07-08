#!/bin/bash
set -euo pipefail

SITE_ROOT="/var/www/ru-atomy.ru"
DB_NAME="ru_atomy"
DB_USER="ru_atomy"
CREDS_FILE="/root/ru-atomy-credentials.txt"
DOMAIN="ru-atomy.ru"
WP_CLI="/usr/local/bin/wp --allow-root"
ADMIN_USER="atomyadmin"
SITE_TITLE="Atomy Russia"

export DEBIAN_FRONTEND=noninteractive

gen_pass() {
  openssl rand -base64 32 | tr -dc 'A-Za-z0-9' | head -c 24
}

DB_PASS="$(gen_pass)"
ADMIN_PASS="$(gen_pass)"

umask 077
cat > "${CREDS_FILE}" <<EOF
# ru-atomy.ru credentials (VPS only)
Generated: $(date -u +"%Y-%m-%dT%H:%M:%SZ")
MySQL database: ${DB_NAME}
MySQL user: ${DB_USER}
MySQL password: ${DB_PASS}
WordPress admin user: ${ADMIN_USER}
WordPress admin password: ${ADMIN_PASS}
WordPress admin email: admin@${DOMAIN}
Site root: ${SITE_ROOT}
EOF
chmod 600 "${CREDS_FILE}"

mysql -e "
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
"

mkdir -p "${SITE_ROOT}"
chown -R www-data:www-data "${SITE_ROOT}"

if ! command -v postfix >/dev/null 2>&1; then
  echo "postfix postfix/main_mailer_type select Local only" | debconf-set-selections
  echo "postfix postfix/mailname string ${DOMAIN}" | debconf-set-selections
  apt-get update -y
  apt-get install -y postfix mailutils
  systemctl enable postfix
  systemctl start postfix
fi

if [ ! -f "${SITE_ROOT}/wp-load.php" ]; then
  ${WP_CLI} core download --path="${SITE_ROOT}" --locale=ru_RU 2>/dev/null \
    || ${WP_CLI} core download --path="${SITE_ROOT}"
fi

if [ ! -f "${SITE_ROOT}/wp-config.php" ]; then
  ${WP_CLI} config create \
    --path="${SITE_ROOT}" \
    --dbname="${DB_NAME}" \
    --dbuser="${DB_USER}" \
    --dbpass="${DB_PASS}" \
    --dbhost="localhost" \
    --dbcharset="utf8mb4" \
    --skip-check
fi

${WP_CLI} config set DISALLOW_FILE_EDIT true --raw --path="${SITE_ROOT}"
${WP_CLI} config set WP_DEBUG false --raw --path="${SITE_ROOT}"
${WP_CLI} config set WP_DEBUG_LOG true --raw --path="${SITE_ROOT}"
${WP_CLI} config set FORCE_SSL_ADMIN true --raw --path="${SITE_ROOT}"

if ! ${WP_CLI} core is-installed --path="${SITE_ROOT}" 2>/dev/null; then
  ${WP_CLI} core install \
    --path="${SITE_ROOT}" \
    --url="http://${DOMAIN}" \
    --title="${SITE_TITLE}" \
    --admin_user="${ADMIN_USER}" \
    --admin_password="${ADMIN_PASS}" \
    --admin_email="admin@${DOMAIN}" \
    --skip-email
fi

${WP_CLI} plugin install woocommerce --activate --path="${SITE_ROOT}"
${WP_CLI} option update woocommerce_currency RUB --path="${SITE_ROOT}"
${WP_CLI} rewrite structure '/%postname%/' --path="${SITE_ROOT}"
${WP_CLI} rewrite flush --path="${SITE_ROOT}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONF_SRC="${SCRIPT_DIR}/ru-atomy.ru.conf"
[ -f "${CONF_SRC}" ] || CONF_SRC="/root/deploy/ru-atomy.ru.conf"
cp "${CONF_SRC}" /etc/apache2/sites-available/ru-atomy.ru.conf
a2ensite ru-atomy.ru.conf
apache2ctl configtest
systemctl reload apache2

# Certbot requires DNS A record: ru-atomy.ru -> 162.35.161.95 (and www if used)
CERT_OK=0
if ! command -v certbot >/dev/null 2>&1; then
  apt-get install -y certbot python3-certbot-apache
fi
if certbot --apache -d "${DOMAIN}" -d "www.${DOMAIN}" \
  --non-interactive --agree-tos -m "admin@${DOMAIN}" --redirect; then
  CERT_OK=1
fi

if [ "${CERT_OK}" = "1" ]; then
  ${WP_CLI} option update home "https://${DOMAIN}/" --path="${SITE_ROOT}"
  ${WP_CLI} option update siteurl "https://${DOMAIN}/" --path="${SITE_ROOT}"
  ${WP_CLI} config set WP_HOME "https://${DOMAIN}/" --path="${SITE_ROOT}"
  ${WP_CLI} config set WP_SITEURL "https://${DOMAIN}/" --path="${SITE_ROOT}"
fi

chown -R www-data:www-data "${SITE_ROOT}"
echo "Done. Credentials: ${CREDS_FILE}"
