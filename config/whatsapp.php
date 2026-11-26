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
    // Max campaign messages per second per phone number (Cloud API allows up to 80 by default).
    'messages_per_second' => (int) env('WHATSAPP_MESSAGES_PER_SECOND', 20),
    // Queue used for campaign sends.
    'send_queue' => env('WHATSAPP_SEND_QUEUE', 'campaigns'),
    // Approximate Meta charge per delivered template message in INR, by category, for the cost estimate
    // shown before sending (India rates; Meta bills the customer directly). Update when Meta changes prices.
    'rates' => [
        'MARKETING' => (float) env('WHATSAPP_RATE_MARKETING', 0.8631),
        'UTILITY' => (float) env('WHATSAPP_RATE_UTILITY', 0.115),
        'AUTHENTICATION' => (float) env('WHATSAPP_RATE_AUTHENTICATION', 0.115),
    ],
    // Meta error codes worth one more try (temporary problems). Others (wrong number, user blocked marketing…) are final.
    'retryable_errors' => ['130429', '131000', '131016', '131048', '131049', '133004'],
];
