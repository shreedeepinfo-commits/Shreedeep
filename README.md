# Shreedeep WhatsApp Panel

Multi-tenant panel for bulk WhatsApp messaging on the official Meta Cloud API. Customers sign up, connect their own WhatsApp Business number through Meta Embedded Signup, import contacts, sync templates and send campaigns with delivery reports.

## Local setup

```sh
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan serve
```

Open http://localhost:8000 and create an account.

## Secrets

Meta App ID, App Secret, Embedded Signup config id and webhook verify token go in `.env` only. `.env` is git-ignored; never commit it.

## Queues and scheduler

Campaign sends, CSV imports and webhook processing run on queues.

- Local: `php artisan queue:work --queue=webhooks,campaigns,imports,default`
- Production: set `QUEUE_CONNECTION=redis`, `CACHE_STORE=redis`, run `php artisan horizon` under Supervisor, and add the scheduler cron:
  `* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1`

Sending speed per WhatsApp number is set by `WHATSAPP_MESSAGES_PER_SECOND` (default 20). Meta rate-limit errors are retried automatically.
