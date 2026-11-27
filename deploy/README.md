# Production deploy (Ubuntu 24.04 VPS)

Stack: Caddy (automatic HTTPS, on-demand certificates for reseller domains) → PHP 8.3-FPM → MySQL 8 + Redis.
Horizon runs the queues under Supervisor. Cron runs the Laravel scheduler every minute.

## DNS (main domain)

| Type | Name | Value |
|---|---|---|
| A | `app` | server IP |
| A | `*.app` | server IP (reseller subdomains like `agency.app.example.com`) |

A reseller's own domain needs one record in **their** DNS: `CNAME wa.theirdomain.com → app.example.com`.
They also save the domain in Reseller panel → Brand & prices. Caddy issues the certificate on the first visit,
and only for domains the panel confirms at `/internal/tls-check`.

## First install (as root on the server)

```sh
git clone https://github.com/shreedeepinfo-commits/Shreedeep.git /var/www/shreedeep
APP_DOMAIN=app.example.com ACME_EMAIL=you@example.com BRANCH=main bash /var/www/shreedeep/deploy/setup-server.sh
```

Build the production `.env` on the Mac from the local `.env`, which holds the Meta keys and brand settings, and copy it up:

```sh
DBPASS=$(ssh root@SERVER cat /root/.shreedeep-db-password)
sh deploy/make-production-env.sh .env app.example.com "$DBPASS" > /tmp/shreedeep.env
scp /tmp/shreedeep.env root@SERVER:/var/www/shreedeep/.env && rm /tmp/shreedeep.env
ssh root@SERVER 'bash /var/www/shreedeep/deploy/deploy.sh && cd /var/www/shreedeep && php artisan admin:make you@example.com --name="Owner"'
```

## Every update

```sh
ssh root@SERVER 'BRANCH=main bash /var/www/shreedeep/deploy/deploy.sh'
```

## Meta app settings

- Webhook callback URL: `https://app.example.com/webhooks/whatsapp`. The verify token is `META_WEBHOOK_VERIFY_TOKEN`. Subscribe to **messages**.
- App domains / allowed domains for the JavaScript SDK: `app.example.com` only. Reseller domains open
  Embedded Signup in a popup on the main domain, so they never need to be added to Meta.
- Payment gateway webhooks: `https://app.example.com/webhooks/razorpay` and `https://app.example.com/webhooks/cashfree`.
