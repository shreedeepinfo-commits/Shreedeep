#!/usr/bin/env bash
# Deploy the latest code on the server (run as root). Usage: BRANCH=main bash deploy/deploy.sh
set -euo pipefail
APP_DIR=/var/www/shreedeep
BRANCH="${BRANCH:-$(git -C "$APP_DIR" rev-parse --abbrev-ref HEAD)}"
cd "$APP_DIR"
[ -f .env ] || { echo "Missing $APP_DIR/.env"; exit 1; }

git fetch -q origin "$BRANCH" && git checkout -q "$BRANCH" && git reset -q --hard "origin/$BRANCH"
composer install -q --no-dev --optimize-autoloader --no-interaction
grep -q '^APP_KEY=base64:' .env || php artisan key:generate --force
chown -R www-data:www-data storage bootstrap/cache
chmod 640 .env && chown root:www-data .env

php artisan migrate --force
php artisan storage:link >/dev/null 2>&1 || true
php artisan optimize:clear >/dev/null
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
chown -R www-data:www-data storage bootstrap/cache

systemctl reload php8.3-fpm
supervisorctl reread >/dev/null && supervisorctl update >/dev/null
php artisan horizon:terminate >/dev/null 2>&1 || true
supervisorctl start shreedeep-horizon >/dev/null 2>&1 || true
systemctl reload caddy 2>/dev/null || systemctl restart caddy
echo "Deployed $(git rev-parse --short HEAD) on $BRANCH"
