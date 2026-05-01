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
        'web_url' => env('HEMIS_WEB_BASE_URL', 'https://hemis.ttatf.uz'),
        'web_login' => env('HEMIS_WEB_LOGIN'),
        'web_password' => env('HEMIS_WEB_PASSWORD'),
    ],

    'hemis_oauth' => [
        'base_url' => env('HEMIS_OAUTH_BASE_URL', 'https://student.ttatf.uz'),
        'client_id' => env('HEMIS_OAUTH_CLIENT_ID'),
        'client_secret' => env('HEMIS_OAUTH_CLIENT_SECRET'),
        'redirect_uri' => env('HEMIS_OAUTH_REDIRECT_URI'),
    ],

    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'bot_username' => env('TELEGRAM_BOT_USERNAME'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
        'attendance_group_id' => env('TELEGRAM_ATTENDANCE_GROUP_ID'),
        'five_candidate_group_id' => env('TELEGRAM_FIVE_CANDIDATE_GROUP_ID'),
        'registrar_group_id' => env('TELEGRAM_REGISTRAR_GROUP_ID'),
    ],

    'moodle' => [
        'ssh_host' => env('MOODLE_SSH_HOST'),
        'ssh_user' => env('MOODLE_SSH_USER'),
        'ssh_port' => env('MOODLE_SSH_PORT', 22),
        'push_script' => env('MOODLE_PUSH_SCRIPT', '/opt/scripts/moodle_to_lmsttatf_push.php'),
        'sync_secret' => env('MOODLE_SYNC_SECRET'),

        // local_hemisexport plugin web service
        'ws_url' => env('MOODLE_WS_URL'),
        'ws_token' => env('MOODLE_WS_TOKEN'),
        'ws_timeout' => (int) env('MOODLE_WS_TIMEOUT', 30),
        // Window around exam start during which a student may begin attempt (minutes)
        'open_window_minutes' => (int) env('MOODLE_OPEN_WINDOW_MINUTES', 10),
        // After the start cutoff (exam_time + open_window), how many extra minutes
        // remain before timeclose. Must be >= the Moodle quiz timelimit so that
        // students who started right before the cutoff still get their full time.
        'close_buffer_minutes' => (int) env('MOODLE_CLOSE_BUFFER_MINUTES', 30),
        // 0 = use Moodle quiz default; otherwise seconds to override per-attempt limit
        'timelimit_seconds' => (int) env('MOODLE_TIMELIMIT_SECONDS', 0),
        // Quiz idnumber template. Placeholders: {yn} (lowercase: test/oski),
        // {YN} (uppercase), {lang} (uzb/rus/eng), {attempt} (1).
        'quiz_idnumber_template' => env(
            'MOODLE_QUIZ_IDNUMBER_TEMPLATE',
            'YN {yn} ({lang})_{attempt}-urinish'
        ),
        // HEMIS educationLang.code → Moodle quiz_idnumber language token.
        // HEMIS uses both alpha (uz/ru/en) and numeric codes (11/12/13/14/15).
        'lang_map' => [
            'uz' => env('MOODLE_LANG_UZ', 'uzb'),
            'ru' => env('MOODLE_LANG_RU', 'rus'),
            'en' => env('MOODLE_LANG_EN', 'eng'),
            '11' => env('MOODLE_LANG_11', 'uzb'),
            '12' => env('MOODLE_LANG_12', 'uzb'),
            '13' => env('MOODLE_LANG_13', 'rus'),
            '14' => env('MOODLE_LANG_14', 'eng'),
            '15' => env('MOODLE_LANG_15', 'uzb'),
        ],
    ],

    'face_compare' => [
        'url' => env('FACE_COMPARE_URL', 'http://127.0.0.1:5005'),
        'timeout' => (int) env('FACE_COMPARE_TIMEOUT', 60),
    ],

];
