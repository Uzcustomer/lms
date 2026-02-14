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
    'hemis' => [
        'base_url' => env('HEMIS_API_BASE_URL', 'https://student.ttatf.uz/rest/v1/'),
        'token' => env('HEMIS_API_TOKEN'),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'bot_username' => env('TELEGRAM_BOT_USERNAME'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
    ],

    'moodle' => [
        'ssh_host' => env('MOODLE_SSH_HOST'),
        'ssh_user' => env('MOODLE_SSH_USER'),
        'ssh_port' => env('MOODLE_SSH_PORT', 22),
        'push_script' => env('MOODLE_PUSH_SCRIPT', '/opt/scripts/moodle_to_lmsttatf_push.php'),
        'sync_secret' => env('MOODLE_SYNC_SECRET'),
    ],

];
