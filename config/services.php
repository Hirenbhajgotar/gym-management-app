<?php

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

    "postmark" => [
        "token" => env("POSTMARK_TOKEN"),
    ],

    "ses" => [
        "key" => env("AWS_ACCESS_KEY_ID"),
        "secret" => env("AWS_SECRET_ACCESS_KEY"),
        "region" => env("AWS_DEFAULT_REGION", "us-east-1"),
    ],

    "resend" => [
        "key" => env("RESEND_KEY"),
    ],

    "slack" => [
        "notifications" => [
            "bot_user_oauth_token" => env("SLACK_BOT_USER_OAUTH_TOKEN"),
            "channel" => env("SLACK_BOT_USER_DEFAULT_CHANNEL"),
        ],
    ],

    // Social Media Platform Configurations
    "facebook" => [
        "app_id" => env("FACEBOOK_APP_ID"),
        "app_secret" => env("FACEBOOK_APP_SECRET"),
        "access_token" => env("FACEBOOK_ACCESS_TOKEN"),
        "webhook_verify_token" => env("FACEBOOK_WEBHOOK_VERIFY_TOKEN"),
        "api_version" => env("FACEBOOK_API_VERSION", "v18.0"),
    ],

    "instagram" => [
        "app_id" => env("INSTAGRAM_APP_ID"),
        "app_secret" => env("INSTAGRAM_APP_SECRET"),
        "access_token" => env("INSTAGRAM_ACCESS_TOKEN"),
        "webhook_verify_token" => env("INSTAGRAM_WEBHOOK_VERIFY_TOKEN"),
        "business_account_id" => env("INSTAGRAM_BUSINESS_ACCOUNT_ID"),
    ],

    "whatsapp" => [
        "business_account_id" => env("WHATSAPP_BUSINESS_ACCOUNT_ID"),
        "phone_number_id" => env("WHATSAPP_PHONE_NUMBER_ID"),
        "access_token" => env("WHATSAPP_ACCESS_TOKEN"),
        "webhook_verify_token" => env("WHATSAPP_WEBHOOK_VERIFY_TOKEN"),
        "api_version" => env("WHATSAPP_API_VERSION", "v18.0"),
    ],

    "indiamart" => [
        "client_id" => env("INDIAMART_CLIENT_ID"),
        "client_secret" => env("INDIAMART_CLIENT_SECRET"),
        "username" => env("INDIAMART_USERNAME"),
        "password" => env("INDIAMART_PASSWORD"),
        "webhook_secret" => env("INDIAMART_WEBHOOK_SECRET"),
    ],

    "justdial" => [
        "api_key" => env("JUSTDIAL_API_KEY"),
        "client_id" => env("JUSTDIAL_CLIENT_ID"),
        "client_secret" => env("JUSTDIAL_CLIENT_SECRET"),
        "webhook_secret" => env("JUSTDIAL_WEBHOOK_SECRET"),
    ],

    "twilio" => [
        "master_sid" => env("TWILIO_ACCOUNT_SID"),
        "master_token" => env("TWILIO_AUTH_TOKEN"),
        // 'api_key' => env('TWILIO_API_KEY'),
        "use_sandbox" => env("TWILIO_USE_SANDBOX", true),
        "sandbox_number" => env("TWILIO_SANDBOX_NUMBER", "+14155238886"),
        "whatsapp_integration_active_template_sid" => env(
            "TWILIO_WHATSAPP_INTEGRATION_ACTIVE_TEMPLATE_SID",
            "HX5f991f2aceffa348f1d3b37df86c5c09",
        ),
        "demo_request_template_sid" => env(
            "TWILIO_DEMO_REQUEST_TEMPLATE_SID",
            "HX3ae293398edf997d889fa8edc06a7ebb",
        ),
        "birthday_greeting_template_sid" => env(
            "TWILIO_BIRTHDAY_GREETING_TEMPLATE_SID",
            "HXcda1c2b35387313592234ddd828ec017",
        ),
        "membership_expiry_template_sid" => env(
            "TWILIO_MEMBERSHIP_EXPIRY_TEMPLATE_SID",
            "HXd00036c65cc7bd610832b215d3ea7b4a",
        ),
        "lead_followup_reminder_template_sid" => env(
            "TWILIO_LEAD_FOLLOWUP_REMINDER_TEMPLATE_SID",
            "HX838a55fee59de39be51107c3db2fea9a",
        ),
        "pending_lead_reminder_template_sid" => env(
            "TWILIO_PENDING_LEAD_REMINDER_TEMPLATE_SID",
            "HX12b0177e0929c76f4f691e58f7fb97d2",
        ),
    ],

    "razorpay" => [
        "key_id" => env("RAZORPAY_KEY_ID"),
        "key_secret" => env("RAZORPAY_KEY_SECRET"),
        "webhook_secret" => env("RAZORPAY_WEBHOOK_SECRET"),
    ],
];
