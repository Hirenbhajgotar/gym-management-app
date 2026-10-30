<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Social Media Platform Configurations
    'facebook' => [
        'app_id' => env('FACEBOOK_APP_ID'),
        'app_secret' => env('FACEBOOK_APP_SECRET'),
        'access_token' => env('FACEBOOK_ACCESS_TOKEN'),
        'webhook_verify_token' => env('FACEBOOK_WEBHOOK_VERIFY_TOKEN'),
        'api_version' => env('FACEBOOK_API_VERSION', 'v18.0'),
    ],

    'instagram' => [
        'app_id' => env('INSTAGRAM_APP_ID'),
        'app_secret' => env('INSTAGRAM_APP_SECRET'),
        'access_token' => env('INSTAGRAM_ACCESS_TOKEN'),
        'webhook_verify_token' => env('INSTAGRAM_WEBHOOK_VERIFY_TOKEN'),
        'business_account_id' => env('INSTAGRAM_BUSINESS_ACCOUNT_ID'),
    ],

    'indiamart' => [
        'client_id' => env('INDIAMART_CLIENT_ID'),
        'client_secret' => env('INDIAMART_CLIENT_SECRET'),
        'username' => env('INDIAMART_USERNAME'),
        'password' => env('INDIAMART_PASSWORD'),
        'webhook_secret' => env('INDIAMART_WEBHOOK_SECRET'),
    ],

    'justdial' => [
        'api_key' => env('JUSTDIAL_API_KEY'),
        'client_id' => env('JUSTDIAL_CLIENT_ID'),
        'client_secret' => env('JUSTDIAL_CLIENT_SECRET'),
        'webhook_secret' => env('JUSTDIAL_WEBHOOK_SECRET'),
    ],

    'whatsapp' => [
        // Provider selection: 'twilio' or 'waba' (Meta WhatsApp Business API)
        'default_provider' => env('WHATSAPP_DEFAULT_PROVIDER', 'waba'),

        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'api_version' => env('META_API_VERSION', 'v25.0'),
        'welcome_credits' => (int) env('WHATSAPP_WELCOME_CREDITS', 50),
        'estimated_per_message_cost' => (float) env('WHATSAPP_ESTIMATED_PER_MESSAGE_COST', 0.01),
        'category_costs' => [
            'utility' => (float) env('WHATSAPP_UTILITY_COST', 0.002),
            'marketing' => (float) env('WHATSAPP_MARKETING_COST', 0.01),
            'authentication' => (float) env('WHATSAPP_AUTHENTICATION_COST', 0.002),
        ],
        'webhook_verify_token' => env('META_WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
        'template_language' => (string) env('WHATSAPP_TEMPLATE_LANGUAGE', 'en_US'),

        // Default 6-digit 2-step verification PIN used when registering/verifying
        // WhatsApp phone numbers with the Meta Cloud API. Override per-environment
        // via WHATSAPP_DEFAULT_REGISTRATION_PIN. IMPORTANT: this MUST match the PIN
        // configured in Meta Business Manager for the phone number, otherwise the
        // registration call will fail with a PIN mismatch error.
        'default_registration_pin' => env('WHATSAPP_DEFAULT_REGISTRATION_PIN', '123456'),
    ],

    'meta' => [
        'app_id' => env('META_APP_ID'),
        'app_secret' => (string) env('META_APP_SECRET'),
        'api_version' => env('META_API_VERSION', 'v25.0'),
        'redirect_uri' => rtrim(config()->string('app.url'), '/') . '/' . ltrim((string) (env('META_REDIRECT_URI', '') ?: '/whatsapp/embedded-signup/callback'), '/'),
        'config_id' => env('META_CONFIG_ID'),
        'webhook_verify_token' => env('META_WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
    ],

    'twilio' => [
        'master_sid' => env('TWILIO_ACCOUNT_SID'),
        'master_token' => env('TWILIO_AUTH_TOKEN'),

        // Shared WABA (for backward compatibility during migration)
        'shared_waba_id' => env('TWILIO_WABA_ID'),

        // Feature Flags
        'use_individual_waba' => env('TWILIO_USE_INDIVIDUAL_WABA', false),
        'embedded_signup_enabled' => env('TWILIO_EMBEDDED_SIGNUP_ENABLED', false),
        'webhook_verify_token' => env('TWILIO_WHATSAPP_WEBHOOK_VERIFY_TOKEN'),

        // Sandbox Settings
        'use_sandbox' => env('TWILIO_USE_SANDBOX', false),
        'sandbox_number' => env('TWILIO_SANDBOX_NUMBER', '+15557626186'),
        'sandbox_recipient_number' => env('TWILIO_SANDBOX_RECIPIENT_NUMBER'),

        'cost_per_message' => (float) env('TWILIO_COST_PER_MESSAGE', 1.53),
        'number_cost' => (float) env('TWILIO_NUMBER_COST', 136.41),
        'margin_percentage' => (float) env('TWILIO_MARGIN_PERCENTAGE', 0.10),
        'exchange_rate' => (float) env('TWILIO_EXCHANGE_RATE', 90.0),
        'number_surcharge_days' => (int) env('TWILIO_NUMBER_SURCHARGE_DAYS', 30),

        // Pre-approved Template SIDs
        'whatsapp_integration_active_v5_template_sid' => env('TWILIO_WHATSAPP_INTEGRATION_ACTIVE_V5_TEMPLATE_SID', 'HX8285885c2d7330f26cb8c97a4b586d1a'),
        'demo_request_template_sid' => env('TWILIO_DEMO_REQUEST_TEMPLATE_SID', 'HX3ae293398edf997d889fa8edc06a7ebb'),
        'birthday_greeting_template_sid' => env('TWILIO_BIRTHDAY_GREETING_TEMPLATE_SID', 'HXcda1c2b35387313592234ddd828ec017'),
        'membership_expiry_template_sid' => env('TWILIO_MEMBERSHIP_EXPIRY_TEMPLATE_SID', 'HXd00036c65cc7bd610832b215d3ea7b4a'),
        'lead_followup_reminder_template_sid' => env('TWILIO_LEAD_FOLLOWUP_REMINDER_TEMPLATE_SID', 'HX838a55fee59de39be51107c3db2fea9a'),
        'pending_lead_reminder_template_sid' => env('TWILIO_PENDING_LEAD_REMINDER_TEMPLATE_SID', 'HX72ed8ed0e2bae8a24b3d23029e866bac'),
        'low_whatsapp_message_balance_template_sid' => env('LOW_WHATSAPP_MESSAGE_BALANCE_TEMPLATE_SID', 'HXd7f4c8d666c309c57ee24d2353afd1b3'),
    ],

    'razorpay' => [
        'key_id' => (string) env('RAZORPAY_KEY_ID', ''),
        'key_secret' => (string) env('RAZORPAY_KEY_SECRET', ''),
        'webhook_secret' => (string) env('RAZORPAY_WEBHOOK_SECRET', ''),
    ],

    'otp' => [
        'expiry_minutes' => env('OTP_EXPIRY_MINUTES', 5),
    ],
];
