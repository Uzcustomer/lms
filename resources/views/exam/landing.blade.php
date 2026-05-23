{{-- Trilingual exam language picker.
     Rendered AFTER a successful Moodle auth_faceid login and BEFORE the
     student enters the Moodle quiz, so mixed-language groups can let each
     student pick uz / ru / en at exam time. Every static label is stacked
     in all three languages on purpose — this page is the only chance the
     student gets to read instructions in their own language, so we don't
     gate it behind a current-locale switch. --}}
@php
    /** @var \App\Models\Student|null $student */
    /** @var array $exams */
    /** @var string $token */
    /** @var bool $invalidToken */
    $invalidToken = $invalidToken ?? false;
    $exams = $exams ?? [];
    $studentName = $student?->full_name ?? ($student?->short_name ?? '');

    // Pretty labels per UI lang code (uz/ru/en) for the three big buttons.
    $langButtons = [
        'uz' => ['label' => "O‘zbek tilida", 'native' => "O‘zbekcha"],
        'ru' => ['label' => 'На русском',    'native' => 'Русский'],
        'en' => ['label' => 'In English',    'native' => 'English'],
    ];
@endphp
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Imtihon tili / Язык экзамена / Exam language</title>
    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />
    <style>
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; min-height: 100%; }
        body {
            font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            background: linear-gradient(160deg, #eff6ff 0%, #f5f3ff 100%);
            color: #0f172a;
            -webkit-font-smoothing: antialiased;
        }
        .wrap {
            max-width: 880px;
            margin: 0 auto;
            padding: 28px 18px 60px;
        }
        .hero {
            background: #ffffff;
            border-radius: 16px;
            padding: 22px 24px;
            box-shadow: 0 6px 24px rgba(15, 23, 42, .06);
            border: 1px solid #e2e8f0;
        }
        .hero h1 {
            font-size: 22px;
            margin: 0 0 6px;
            font-weight: 700;
            color: #1e293b;
        }
        .hero .greeting-line {
            font-size: 15px;
            color: #475569;
            margin: 2px 0;
        }
        .hero .student {
            font-size: 17px;
            font-weight: 600;
            color: #0f172a;
            margin-top: 10px;
        }

        .exam-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 20px 22px 22px;
            box-shadow: 0 6px 24px rgba(15, 23, 42, .06);
            border: 1px solid #e2e8f0;
            margin-top: 18px;
        }
        .subject {
            font-size: 19px;
            font-weight: 700;
            color: #0f172a;
            margin: 0 0 4px;
            line-height: 1.35;
        }
        .meta {
            display: flex;
            flex-wrap: wrap;
            gap: 14px;
            font-size: 13px;
            color: #475569;
            margin: 0 0 12px;
        }
        .meta .pill {
            background: #f1f5f9;
            border-radius: 999px;
            padding: 4px 12px;
            font-weight: 500;
        }
        .meta .pill.time { background: #dbeafe; color: #1d4ed8; }
        .meta .pill.yn   { background: #fef3c7; color: #92400e; }

        .prompt {
            margin: 6px 0 14px;
            padding: 10px 14px;
            background: #f8fafc;
            border-left: 3px solid #3b82f6;
            border-radius: 0 8px 8px 0;
        }
        .prompt div {
            font-size: 14px;
            line-height: 1.45;
            color: #334155;
        }
        .prompt div + div { margin-top: 2px; }

        .lang-buttons {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 12px;
            margin-top: 8px;
        }
        @media (max-width: 640px) {
            .lang-buttons { grid-template-columns: 1fr; }
        }
        .lang-btn {
            display: block;
            width: 100%;
            text-align: center;
            background: linear-gradient(180deg, #ffffff, #f8fafc);
            border: 1.5px solid #cbd5e1;
            border-radius: 14px;
            padding: 18px 12px;
            font-size: 17px;
            font-weight: 600;
            color: #0f172a;
            cursor: pointer;
            transition: transform .08s ease, border-color .15s ease, box-shadow .15s ease, background .15s ease;
        }
        .lang-btn:hover:not(:disabled) {
            border-color: #3b82f6;
            box-shadow: 0 4px 14px rgba(59, 130, 246, .18);
            background: linear-gradient(180deg, #eff6ff, #dbeafe);
        }
        .lang-btn:active:not(:disabled) { transform: scale(.98); }
        .lang-btn .small {
            display: block;
            font-size: 12px;
            font-weight: 500;
            color: #64748b;
            margin-top: 4px;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .lang-btn:disabled { opacity: .55; cursor: progress; }
        .lang-btn.loading { background: #f1f5f9; }

        .error {
            margin-top: 10px;
            background: #fee2e2;
            color: #991b1b;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 13px;
            display: none;
        }
        .error.show { display: block; }

        .empty-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 28px 24px;
            box-shadow: 0 6px 24px rgba(15, 23, 42, .06);
            border: 1px solid #e2e8f0;
            margin-top: 18px;
            text-align: center;
        }
        .empty-card .icon {
            font-size: 38px;
            margin-bottom: 6px;
        }
        .empty-card .line {
            font-size: 15px;
            color: #334155;
            margin: 4px 0;
            line-height: 1.45;
        }

        .footer-note {
            margin-top: 22px;
            text-align: center;
            color: #94a3b8;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="hero">
            <h1>Imtihon tili / Язык экзамена / Exam language</h1>
            <div class="greeting-line">Salom, quyidagi imtihonlaringizdan birini tanlang va kerakli tilni belgilang.</div>
            <div class="greeting-line">Здравствуйте, выберите экзамен ниже и предпочитаемый язык.</div>
            <div class="greeting-line">Hello, please pick one of your exams below and choose your language.</div>
            @if($studentName !== '')
                <div class="student">{{ $studentName }}</div>
            @endif
        </div>

        @if($invalidToken)
            {{-- Token unknown / expired. Show a trilingual "session expired"
                 panel so the student knows to log in again rather than
                 staring at a blank screen. --}}
            <div class="empty-card">
                <div class="icon">⏳</div>
                <div class="line">Sessiya muddati o‘tdi yoki havola noto‘g‘ri. Iltimos, FaceID orqali qaytadan kiring.</div>
                <div class="line">Срок сессии истёк или ссылка недействительна. Пожалуйста, войдите снова через FaceID.</div>
                <div class="line">Your session has expired or the link is invalid. Please log in again via FaceID.</div>
            </div>
        @elseif(empty($exams))
            {{-- Mirrors the existing "no exam allocated for you at this time"
                 message the student sees on the quizaccess_lmsguard side, but
                 in all three languages. --}}
            <div class="empty-card">
                <div class="icon">🕒</div>
                <div class="line">Hozirgi vaqtga sizga belgilangan imtihon topilmadi. Iltimos, proktor bilan bog‘laning.</div>
                <div class="line">На текущее время для вас не назначен экзамен. Пожалуйста, свяжитесь с проктором.</div>
                <div class="line">No exam is allocated for you at this time. Please contact a proctor.</div>
            </div>
        @else
            @foreach($exams as $exam)
                @php
                    $time = $exam['exam_time_local'];
                    $ynLabel = strtoupper($exam['yn_type']);
                    $attempt = (int) $exam['attempt'];
                    $available = $exam['available_langs'] ?: ['uz','ru','en'];
                    $rowKey = $exam['schedule_id'] . '-' . $exam['yn_type'] . '-' . $exam['attempt'];
                @endphp
                <div class="exam-card" data-row="{{ $rowKey }}">
                    <h2 class="subject">{{ $exam['subject_name'] ?: '—' }}</h2>
                    <div class="meta">
                        <span class="pill yn">
                            {{ $ynLabel }}@if($attempt > 1) · {{ $attempt }}-urinish @endif
                        </span>
                        @if($time)
                            <span class="pill time">⏰ {{ $time }}</span>
                        @endif
                    </div>

                    <div class="prompt">
                        <div>Qaysi tilda topshirmoqchisiz?</div>
                        <div>На каком языке вы хотите сдавать?</div>
                        <div>Which language would you like to take it in?</div>
                    </div>

                    <div class="lang-buttons">
                        @foreach($available as $lang)
                            @php $cfg = $langButtons[$lang] ?? ['label' => $lang, 'native' => $lang]; @endphp
                            <button
                                type="button"
                                class="lang-btn js-pick"
                                data-schedule="{{ $exam['schedule_id'] }}"
                                data-yn="{{ $exam['yn_type'] }}"
                                data-attempt="{{ $exam['attempt'] }}"
                                data-lang="{{ $lang }}"
                                data-row="{{ $rowKey }}"
                            >
                                {{ $cfg['native'] }}
                                <span class="small">{{ $cfg['label'] }}</span>
                            </button>
                        @endforeach
                    </div>

                    <div class="error" data-error-for="{{ $rowKey }}"></div>
                </div>
            @endforeach
        @endif

        <div class="footer-note">
            Mark · TTATF · Test markazi
        </div>
    </div>

    <script>
        (function () {
            var chooseUrl = @json(route('exam.landing.choose', $token));
            var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

            document.querySelectorAll('.js-pick').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var card = btn.closest('.exam-card');
                    var rowKey = btn.getAttribute('data-row');
                    var errorEl = card.querySelector('[data-error-for="' + rowKey + '"]');
                    if (errorEl) { errorEl.classList.remove('show'); errorEl.textContent = ''; }

                    // Lock all buttons in this card while we work.
                    var rowBtns = card.querySelectorAll('.js-pick');
                    rowBtns.forEach(function (b) { b.disabled = true; });
                    btn.classList.add('loading');

                    var body = {
                        exam_schedule_id: parseInt(btn.getAttribute('data-schedule'), 10),
                        yn_type: btn.getAttribute('data-yn'),
                        attempt: parseInt(btn.getAttribute('data-attempt'), 10),
                        lang: btn.getAttribute('data-lang'),
                    };

                    fetch(chooseUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify(body),
                    }).then(function (resp) {
                        return resp.json().then(function (data) { return { status: resp.status, data: data }; });
                    }).then(function (r) {
                        if (r.status >= 200 && r.status < 300 && r.data && r.data.ok && r.data.redirect_url) {
                            window.location.href = r.data.redirect_url;
                            return;
                        }
                        var msg = (r.data && (r.data.message || r.data.error)) || 'Xatolik / Ошибка / Error';
                        if (errorEl) {
                            errorEl.textContent = msg;
                            errorEl.classList.add('show');
                        }
                        rowBtns.forEach(function (b) { b.disabled = false; });
                        btn.classList.remove('loading');
                    }).catch(function (e) {
                        if (errorEl) {
                            errorEl.textContent = 'Tarmoq xatosi / Сетевая ошибка / Network error: ' + (e && e.message ? e.message : '');
                            errorEl.classList.add('show');
                        }
                        rowBtns.forEach(function (b) { b.disabled = false; });
                        btn.classList.remove('loading');
                    });
                });
            });
        })();
    </script>
</body>
</html>
