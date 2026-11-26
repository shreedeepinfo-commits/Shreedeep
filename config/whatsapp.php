<?php

return [
    // Meta app (Tech Provider) credentials. Set these in .env only.
    'app_id' => env('META_APP_ID'),
    'app_secret' => env('META_APP_SECRET'),
    // Facebook Login for Business configuration id for WhatsApp Embedded Signup.
    'config_id' => env('META_EMBEDDED_SIGNUP_CONFIG_ID'),
    'graph_version' => env('META_GRAPH_VERSION', 'v24.0'),
    'graph_url' => env('META_GRAPH_URL', 'https://graph.facebook.com'),
    // Token Meta sends when verifying the webhook URL.
    'webhook_verify_token' => env('META_WEBHOOK_VERIFY_TOKEN'),
];
