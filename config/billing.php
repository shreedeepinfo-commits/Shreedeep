<?php

/*
 * Rental plans. Prices are in rupees per month, before GST.
 * Customers pay Meta directly for WhatsApp conversations; these plans are only for the panel.
 * A limit of null means unlimited.
 */
return [
    'currency' => 'INR',

    'trial_days' => (int) env('BILLING_TRIAL_DAYS', 14),
    'trial_plan' => 'growth', // limits used during the free trial

    // Discount for paying for several months at once.
    'cycles' => [
        'monthly' => ['label' => 'Monthly', 'months' => 1, 'discount' => 0],
        'quarterly' => ['label' => 'Quarterly', 'months' => 3, 'discount' => 10],
        'yearly' => ['label' => 'Yearly', 'months' => 12, 'discount' => 20],
    ],

    'plans' => [
        'starter' => [
            'name' => 'Starter', 'price' => (int) env('PLAN_STARTER_PRICE', 999),
            'tagline' => 'For small shops getting started',
            'limits' => ['numbers' => 1, 'members' => 2, 'contacts' => 5000, 'flows' => 3],
            'features' => ['Bulk campaigns', 'Live chat', 'Templates', 'Basic automations'],
        ],
        'growth' => [
            'name' => 'Growth', 'price' => (int) env('PLAN_GROWTH_PRICE', 2499), 'popular' => true,
            'tagline' => 'For growing teams',
            'limits' => ['numbers' => 2, 'members' => 5, 'contacts' => 25000, 'flows' => 15],
            'features' => ['Everything in Starter', 'Chatbot builder', 'Drip & date automations', 'CRM pipeline'],
        ],
        'pro' => [
            'name' => 'Pro', 'price' => (int) env('PLAN_PRO_PRICE', 4999),
            'tagline' => 'For agencies and large teams',
            'limits' => ['numbers' => 5, 'members' => 15, 'contacts' => null, 'flows' => null],
            'features' => ['Everything in Growth', 'Unlimited contacts', 'Unlimited chatbots', 'Priority support'],
        ],
    ],

    'limit_labels' => ['numbers' => 'WhatsApp numbers', 'members' => 'Team members', 'contacts' => 'Contacts', 'flows' => 'Chatbot flows'],

    // GST invoice details of the panel owner (seller).
    'gst_rate' => 18,
    'seller' => [
        'name' => env('BILLING_SELLER_NAME', 'Shree Deep Infotech'),
        'gstin' => env('BILLING_SELLER_GSTIN'),
        'address' => env('BILLING_SELLER_ADDRESS'),
        'state' => env('BILLING_SELLER_STATE'),
        'email' => env('BILLING_SELLER_EMAIL'),
        'sac' => env('BILLING_SAC', '998315'),
        'invoice_prefix' => env('BILLING_INVOICE_PREFIX', 'INV'),
    ],

    // Payment gateways. Keys only in .env, never in the code.
    'razorpay' => [
        'key_id' => env('RAZORPAY_KEY_ID'),
        'key_secret' => env('RAZORPAY_KEY_SECRET'),
        'webhook_secret' => env('RAZORPAY_WEBHOOK_SECRET'),
    ],
    'cashfree' => [
        'app_id' => env('CASHFREE_APP_ID'),
        'secret_key' => env('CASHFREE_SECRET_KEY'),
        'sandbox' => (bool) env('CASHFREE_SANDBOX', true),
        'api_version' => '2023-08-01',
    ],

    // Lets you try the whole flow on your computer without a gateway (APP_ENV=local only).
    'test_payments' => (bool) env('BILLING_TEST_PAYMENTS', true),

    'states' => [
        'Andaman and Nicobar Islands', 'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chandigarh', 'Chhattisgarh',
        'Dadra and Nagar Haveli and Daman and Diu', 'Delhi', 'Goa', 'Gujarat', 'Haryana', 'Himachal Pradesh', 'Jammu and Kashmir',
        'Jharkhand', 'Karnataka', 'Kerala', 'Ladakh', 'Lakshadweep', 'Madhya Pradesh', 'Maharashtra', 'Manipur', 'Meghalaya',
        'Mizoram', 'Nagaland', 'Odisha', 'Puducherry', 'Punjab', 'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura',
        'Uttar Pradesh', 'Uttarakhand', 'West Bengal', 'Outside India',
    ],
];
