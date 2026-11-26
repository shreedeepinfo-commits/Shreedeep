# Shreedeep WhatsApp Panel

Multi-tenant SaaS panel for WhatsApp Cloud API bulk messaging (Laravel 13, PHP 8.3).

- Customers sign up, get a workspace, and connect their own WhatsApp number via Meta Embedded Signup.
- Every tenant-owned model uses the `BelongsToWorkspace` trait and carries `workspace_id`. Never query tenant data without the workspace scope.
- Meta credentials (app id, secret, config id, webhook verify token) live only in `.env`. Never commit secrets.
- Per-customer access tokens are stored with Laravel's `encrypted` cast.
- Slow work (campaign sends, webhook processing) runs on queues (Redis + Horizon in production).
- UI: Blade views in `resources/views`, Tailwind via CDN (no npm build needed for the MVP).
- Run tests: `php artisan test`
