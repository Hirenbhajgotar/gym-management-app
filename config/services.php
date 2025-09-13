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

    'whatsapp' => [
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
        'api_version' => env('WHATSAPP_API_VERSION', 'v18.0'),
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

];
