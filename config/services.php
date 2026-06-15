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
        'pull_secret' => env('MOODLE_PULL_SECRET'),
        // Shared secret for the admin-triggered bulk re-push endpoint
        // (POST /api/moodle/trigger-push), called by the Moodle lmsguard
        // plugin when an admin clicks "Markdan ma'lumot olish".
        'api_key' => env('MOODLE_API_KEY'),

        // local_hemisexport plugin web service
        'ws_url' => env('MOODLE_WS_URL'),
        'ws_token' => env('MOODLE_WS_TOKEN'),
        'quiz_ws_token' => env('MOODLE_QUIZ_WS_TOKEN'),
        'ws_timeout' => (int) env('MOODLE_WS_TIMEOUT', 30),
        // Master toggle for the narrow exam time window. When false
        // (default for now), the booking push opens the quiz for the
        // whole exam day instead of only ±N minutes around the
        // scheduled time — access is then governed solely by:
        //   1. The booking row in local_hemisexport_cutoffs
        //      (no row → fail-closed, blocked).
        //   2. The exam_access computer-binding check (if enabled).
        // Flip to true once the precise time-window flow is fully
        // validated; the narrow-window logic below is still in place
        // and is used as-is when this flag is on.
        'enforce_time_window' => (bool) env('MOODLE_ENFORCE_TIME_WINDOW', false),
        // Window around exam start during which a student may begin attempt (minutes)
        'open_window_minutes' => (int) env('MOODLE_OPEN_WINDOW_MINUTES', 10),
        // Extra minutes after the late-entry cutoff during which a student
        // who finished FaceID right on the edge can still click "Start".
        'attempt_start_buffer_minutes' => (int) env('MOODLE_ATTEMPT_START_BUFFER_MINUTES', 2),
        // After the start cutoff (exam_time + open_window), how many extra minutes
        // remain before timeclose. Must be >= the Moodle quiz timelimit so that
        // students who started right before the cutoff still get their full time.
        'close_buffer_minutes' => (int) env('MOODLE_CLOSE_BUFFER_MINUTES', 30),
        // 0 = use Moodle quiz default; otherwise seconds to override per-attempt limit
        'timelimit_seconds' => (int) env('MOODLE_TIMELIMIT_SECONDS', 0),
        // Total computers in the test centre (used by ComputerAssignmentService)
        'total_computers' => (int) env('MOODLE_TOTAL_COMPUTERS', 60),
        // Quiz timelimit used to compute planned_end of each computer assignment.
        // Should match the actual Moodle quiz timelimit (in minutes).
        'quiz_duration_minutes' => (int) env('MOODLE_QUIZ_DURATION_MINUTES', 25),
        // Buffer between two students sharing the same computer (in minutes)
        'computer_buffer_minutes' => (int) env('MOODLE_COMPUTER_BUFFER_MINUTES', 5),
        // Number of computers reserved as a backup pool (excluded from primary
        // assignment and used for overflow / no-show / broken-PC fallbacks).
        'reserve_computers_count' => (int) env('MOODLE_RESERVE_COMPUTERS_COUNT', 5),
        // Minutes before planned_start at which the assigned computer number
        // is revealed to the student — both the Telegram/LMS push and the
        // portal "curtain". Kept small so neighbours can't collude early.
        'reveal_minutes_before' => (int) env('MOODLE_REVEAL_MINUTES_BEFORE', 10),
        // JIT (just-in-time) assignment: how many minutes before planned_start
        // the system picks a real free computer for each pending student and
        // immediately notifies them. Smaller = harder for neighbors to
        // collude in advance; larger = more buffer for student to walk in.
        'jit_assign_minutes_before' => (int) env('MOODLE_JIT_ASSIGN_MINUTES_BEFORE', 10),
        // Total questions in a typical YN quiz; used to estimate when the
        // previous student is "near the end" so the next student can be warned.
        'quiz_total_questions' => (int) env('MOODLE_QUIZ_TOTAL_QUESTIONS', 25),
        // Trigger "prepare to enter" notification to the next student once the
        // current student has spent this fraction of their attempt time.
        // 0.80 of 25 min ≈ minute 20 ≈ question 20 of 25.
        'quiz_warn_progress_ratio' => (float) env('MOODLE_QUIZ_WARN_PROGRESS_RATIO', 0.80),
        // IP prefix used to derive computer number from student request IP
        // (e.g. "196.168.7." → 196.168.7.103 maps to computer #3).
        'computer_ip_prefix' => env('MOODLE_COMPUTER_IP_PREFIX', '196.168.7.'),
        // Last octet offset: computer #N has IP {prefix}{N + offset}.
        // E.g. with offset=100, computer #3 → 196.168.7.103.
        'computer_ip_offset' => (int) env('MOODLE_COMPUTER_IP_OFFSET', 100),
        // Auto-assign overflow detection: a student is moved to a reserve
        // computer when the previous slot has run over by this many minutes.
        'overflow_grace_minutes' => (int) env('MOODLE_OVERFLOW_GRACE_MINUTES', 0),
        // No-show detection: mark assignment abandoned if actual_start is
        // missing this many minutes after planned_start.
        'no_show_minutes' => (int) env('MOODLE_NO_SHOW_MINUTES', 5),
        // Quiz idnumber template. Placeholders: {yn} (lowercase: test/oski),
        // {YN} (uppercase), {lang} (uzb/rus/eng), {attempt} (1).
        'quiz_idnumber_template' => env(
            'MOODLE_QUIZ_IDNUMBER_TEMPLATE',
            'YN {yn} ({lang})_{attempt}-urinish'
        ),
        // Per-yn_type override. The default template above is what
        // local_hemisexport_book_group_exam writes for *test* quizzes
        // (HEMIS YN test rounds). OSKI quizzes were named without the
        // "YN " prefix and with the type uppercased ("OSKI (uzb)_..."),
        // so they need a separate template. Add new entries here if a
        // future yn_type follows yet another convention.
        'quiz_idnumber_templates' => [
            'oski' => env(
                'MOODLE_QUIZ_IDNUMBER_TEMPLATE_OSKI',
                'OSKI ({lang})_{attempt}-urinish'
            ),
            'test' => env(
                'MOODLE_QUIZ_IDNUMBER_TEMPLATE_TEST',
                'YN test ({lang})_{attempt}-urinish'
            ),
        ],
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
        'timeout' => (int) env('FACE_COMPARE_TIMEOUT', 5),
    ],

    'face_id' => [
        // CORS allowlist for student face photo proxy endpoint.
        // Comma separated, e.g.: https://mark.tashmedunitf.uz,https://student.tashmedunitf.uz
        'photo_allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('FACEID_PHOTO_ALLOWED_ORIGINS', ''))))),
    ],

    'exam_access' => [
        // Whether ExamAccessGuardService should enforce the
        // "student must sit at a specific computer" rule. When false
        // (default), students can take their exam from any test-centre
        // PC within the scheduled time window — the Moodle-side
        // quizaccess_examwindow rule still enforces the ±N-minute
        // entry window. Flip to true ONLY if you re-introduce the
        // strict seat-binding workflow on the LMS side.
        'enforce_computer_binding' => env('EXAM_ENFORCE_COMPUTER_BINDING', false),
    ],

    'anthropic' => [
        // Claude API kaliti (api.anthropic.com). Bo'sh bo'lsa AI tekshiruv o'chiq.
        'api_key' => env('ANTHROPIC_API_KEY'),
        // Sonnet — token narxi arzonroq ($3/$15). Vedomost solishtirish uchun yetarli.
        // Kerak bo'lsa ANTHROPIC_MODEL=claude-opus-4-8 bilan Opus'ga qaytarish mumkin.
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-6'),
        'version' => env('ANTHROPIC_VERSION', '2023-06-01'),
        // Streaming so'rov uchun umumiy vaqt chegarasi (job timeout 300s dan past).
        'timeout' => (int) env('ANTHROPIC_TIMEOUT', 280),
    ],

];
