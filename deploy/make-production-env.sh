#!/bin/sh
# Run on your Mac: builds the server .env from your local .env (Meta, brand, billing keys) with production settings.
# Usage: sh deploy/make-production-env.sh .env app.shreedeepinfotech.com DB_PASSWORD > /tmp/shreedeep.env
# The output contains secrets: copy it to the server with scp and delete the local copy. Never commit it.
set -eu
SRC="$1"; DOMAIN="$2"; DB_PASS="$3"
awk -v domain="$DOMAIN" -v dbpass="$DB_PASS" '
BEGIN {
  n = split("APP_ENV=production|APP_DEBUG=false|APP_URL=https://" domain "|APP_KEY=|LOG_LEVEL=warning|LOG_STACK=daily|DB_CONNECTION=mysql|DB_HOST=127.0.0.1|DB_PORT=3306|DB_DATABASE=shreedeep|DB_USERNAME=shreedeep|DB_PASSWORD=" dbpass "|QUEUE_CONNECTION=redis|CACHE_STORE=redis|SESSION_DRIVER=redis|SESSION_SECURE_COOKIE=true|REDIS_CLIENT=phpredis|REDIS_HOST=127.0.0.1|RESELLER_BASE_DOMAIN=" domain "|BILLING_TEST_PAYMENTS=false", kv, "|")
  for (i = 1; i <= n; i++) { k = substr(kv[i], 1, index(kv[i], "=") - 1); val[k] = substr(kv[i], index(kv[i], "=") + 1); order[i] = k }
}
/^[A-Z0-9_]+=/ { k = substr($0, 1, index($0, "=") - 1); if (k in val) { if (!(k in seen)) print k "=" val[k]; seen[k] = 1; next } }
{ print }
END { for (i = 1; i <= n; i++) if (!(order[i] in seen)) print order[i] "=" val[order[i]] }
' "$SRC"
