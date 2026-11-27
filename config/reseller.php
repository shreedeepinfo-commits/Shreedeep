<?php

return [
    // Resellers get slug.<base_domain> (e.g. partner.wa.yourdomain.com). Point a wildcard DNS record at the server.
    'base_domain' => env('RESELLER_BASE_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost'),

    // Public IP of the server, shown to resellers who point a root domain (example.com) with an A record.
    'server_ip' => env('SERVER_IP'),

    // Default monthly cost for a new top-level reseller, as a share of the list price.
    'default_cost_percent' => (int) env('RESELLER_DEFAULT_COST_PERCENT', 60),

    // Auto renewal tries this many hours before a plan ends.
    'renew_before_hours' => 24,
    'max_trial_days' => 14,
];
