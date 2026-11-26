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
