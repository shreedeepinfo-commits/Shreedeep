#!/usr/bin/env bash
# One-time server setup for Ubuntu 24.04 (run as root). Safe to run again.
# Usage: APP_DOMAIN=app.shreedeepinfotech.com ACME_EMAIL=you@example.com BRANCH=main bash setup-server.sh
set -euo pipefail

APP_DOMAIN="${APP_DOMAIN:?set APP_DOMAIN}"
ACME_EMAIL="${ACME_EMAIL:?set ACME_EMAIL}"
BRANCH="${BRANCH:-main}"
REPO="${REPO:-https://github.com/shreedeepinfo-commits/Shreedeep.git}"
APP_DIR=/var/www/shreedeep
export DEBIAN_FRONTEND=noninteractive

echo "==> Packages"
apt-get update -q
apt-get install -yq ca-certificates curl gnupg unzip git ufw supervisor redis-server mysql-server \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-redis php8.3-mbstring php8.3-xml php8.3-curl php8.3-zip \
  php8.3-bcmath php8.3-intl php8.3-gd php8.3-sqlite3 debian-keyring debian-archive-keyring apt-transport-https

if ! command -v caddy >/dev/null; then
  curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/gpg.key' | gpg --dearmor -o /usr/share/keyrings/caddy-stable-archive-keyring.gpg
  curl -1sLf 'https://dl.cloudsmith.io/public/caddy/stable/debian.deb.txt' > /etc/apt/sources.list.d/caddy-stable.list
  apt-get update -q && apt-get install -yq caddy
fi
if ! command -v composer >/dev/null; then
  curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

echo "==> Firewall"
ufw allow OpenSSH >/dev/null; ufw allow 80/tcp >/dev/null; ufw allow 443/tcp >/dev/null; ufw --force enable >/dev/null

echo "==> PHP settings"
cat > /etc/php/8.3/fpm/conf.d/99-shreedeep.ini <<INI
upload_max_filesize = 25M
post_max_size = 25M
memory_limit = 512M
max_execution_time = 120
expose_php = Off
INI
systemctl restart php8.3-fpm

echo "==> Database"
if [ ! -f /root/.shreedeep-db-password ]; then
  openssl rand -hex 20 > /root/.shreedeep-db-password && chmod 600 /root/.shreedeep-db-password
fi
DB_PASS="$(cat /root/.shreedeep-db-password)"
mysql -e "CREATE DATABASE IF NOT EXISTS shreedeep CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS 'shreedeep'@'localhost' IDENTIFIED BY '${DB_PASS}'; ALTER USER 'shreedeep'@'localhost' IDENTIFIED BY '${DB_PASS}'; GRANT ALL ON shreedeep.* TO 'shreedeep'@'localhost'; FLUSH PRIVILEGES;"
systemctl enable --now redis-server >/dev/null

echo "==> Code"
if [ ! -d "$APP_DIR/.git" ]; then
  git clone -q "$REPO" "$APP_DIR"
fi
git config --global --add safe.directory "$APP_DIR"
cd "$APP_DIR" && git fetch -q origin "$BRANCH" && git checkout -q "$BRANCH" && git pull -q origin "$BRANCH"
if [ ! -f "$APP_DIR/.env" ]; then
  echo "!! $APP_DIR/.env is missing. Copy the production .env first (see deploy/README.md), then run deploy.sh."
fi

echo "==> Queue worker (Horizon) and scheduler"
cat > /etc/supervisor/conf.d/shreedeep-horizon.conf <<CONF
[program:shreedeep-horizon]
process_name=%(program_name)s
command=php $APP_DIR/artisan horizon
user=www-data
autostart=true
autorestart=true
stopwaitsecs=3600
redirect_stderr=true
stdout_logfile=$APP_DIR/storage/logs/horizon.log
CONF
echo "* * * * * www-data cd $APP_DIR && php artisan schedule:run >> /dev/null 2>&1" > /etc/cron.d/shreedeep
chmod 644 /etc/cron.d/shreedeep

echo "==> Caddy"
cp "$APP_DIR/deploy/Caddyfile" /etc/caddy/Caddyfile
mkdir -p /etc/systemd/system/caddy.service.d
cat > /etc/systemd/system/caddy.service.d/env.conf <<CONF
[Service]
Environment=APP_DOMAIN=$APP_DOMAIN
Environment=ACME_EMAIL=$ACME_EMAIL
CONF
systemctl daemon-reload
systemctl enable caddy >/dev/null

echo "==> Done. Next: put .env in $APP_DIR and run: bash $APP_DIR/deploy/deploy.sh"
echo "    DB password is in /root/.shreedeep-db-password"
