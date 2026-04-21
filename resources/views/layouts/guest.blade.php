<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
        <meta http-equiv="Pragma" content="no-cache">
        <meta http-equiv="Expires" content="0">

        <title>TDTU Termiz filiali mark platformasi</title>

        <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">
        <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }

            body {
                width: 100%;
                min-height: 100vh;
                background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #0d9488 100%);
                overflow-x: hidden;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
            }

            .logo-header {
                text-align: center;
                margin-bottom: 2rem;
            }
            .logo-header a {
                display: inline-flex;
                align-items: center;
                gap: 14px;
                text-decoration: none;
            }
            .logo-header img {
                width: 52px;
                height: 52px;
                object-fit: contain;
                filter: drop-shadow(0 4px 8px rgba(0,0,0,0.3));
            }
            .logo-header .logo-text {
                text-align: left;
            }
            .logo-header .logo-title {
                color: #fff;
                font-size: 18px;
                font-weight: 700;
                letter-spacing: -0.3px;
                line-height: 1.2;
            }
            .logo-header .logo-sub {
                color: rgba(255,255,255,0.55);
                font-size: 12px;
                font-weight: 400;
                margin-top: 2px;
            }

            /* ===== SWAP CONTAINER ===== */
            .swap-container {
                position: relative;
                width: 720px;
                height: 420px;
                transition: all 0.6s cubic-bezier(0.68, -0.15, 0.27, 1.15);
            }

            .swap-box {
                position: absolute;
                left: 0; top: 0;
                width: 100%; height: 100%;
                overflow: hidden;
                border-radius: 24px;
            }
            .swap-box::before,
            .swap-box::after {
                content: '';
                position: absolute;
                background: rgba(255,255,255,0.06);
                border-radius: 4px;
                transition: all 0.6s cubic-bezier(0.68, -0.15, 0.27, 1.15);
            }
            .swap-box::before {
                left: 150px; top: 50px;
                width: 300px; height: 285px;
                transform: rotateX(52deg) rotateY(15deg) rotateZ(-38deg);
            }
            .swap-box::after {
                left: 80px; top: -10px;
                width: 320px; height: 180px;
                transform: rotateX(52deg) rotateY(15deg) rotateZ(-38deg);
                background: rgba(255,255,255,0.04);
            }

            /* Info panels (behind the white card) */
            .swap-info {
                position: relative;
                text-align: left;
                font-size: 0;
                z-index: 1;
            }
            .swap-info-item {
                display: inline-block;
                vertical-align: top;
                width: 360px;
                height: 420px;
                text-align: center;
                color: #fff;
                opacity: 1;
                transition: all 0.4s;
            }
            .swap-info-item .info-inner {
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                height: 100%;
                padding: 2rem;
            }
            .swap-info-item .info-icon {
                width: 56px; height: 56px;
                background: rgba(255,255,255,0.12);
                border-radius: 16px;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-bottom: 1.25rem;
            }
            .swap-info-item .info-icon svg {
                width: 28px; height: 28px;
                stroke: #fff;
            }
            .swap-info-item h3 {
                font-size: 20px;
                font-weight: 700;
                margin-bottom: 8px;
            }
            .swap-info-item p {
                font-size: 13px;
                color: rgba(255,255,255,0.7);
                margin-bottom: 1.5rem;
                line-height: 1.5;
            }
            .swap-info-btn {
                display: inline-block;
                padding: 12px 36px;
                border: 2px solid rgba(255,255,255,0.4);
                background: rgba(255,255,255,0.08);
                color: #fff;
                border-radius: 14px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s;
                text-decoration: none;
                backdrop-filter: blur(10px);
            }
            .swap-info-btn:hover {
                background: rgba(255,255,255,0.2);
                border-color: rgba(255,255,255,0.7);
                transform: translateY(-2px);
            }

            /* White sliding card */
            .swap-card {
                position: absolute;
                left: 30px;
                top: -30px;
                width: 370px;
                height: 480px;
                background: #fff;
                border-radius: 24px;
                box-shadow: 0 25px 60px rgba(0,0,0,0.3);
                overflow: hidden;
                z-index: 10;
                transition: all 0.6s cubic-bezier(0.68, -0.15, 0.27, 1.15);
            }

            .swap-form {
                position: absolute;
                left: 0; top: 0;
                width: 100%; height: 100%;
                opacity: 1;
                transition: all 0.5s;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 2.5rem;
            }
            .swap-form.form-xodim {
                left: -100%;
                opacity: 0;
            }

            .swap-form h2 {
                font-size: 22px;
                font-weight: 700;
                color: #0f172a;
                margin-bottom: 4px;
            }
            .swap-form .form-subtitle {
                font-size: 13px;
                color: #64748b;
                margin-bottom: 1.75rem;
            }

            /* Inputs */
            .s-input-group {
                position: relative;
                width: 100%;
                margin-bottom: 1rem;
            }
            .s-input-group svg.input-icon {
                position: absolute;
                left: 14px; top: 50%;
                transform: translateY(-50%);
                width: 18px; height: 18px;
                color: #94a3b8;
                pointer-events: none;
                z-index: 2;
            }
            .s-input {
                width: 100%;
                padding: 13px 14px 13px 44px;
                border: 2px solid #e2e8f0;
                border-radius: 12px;
                font-size: 14px;
                background: #f8fafc;
                color: #1e293b;
                outline: none;
                transition: all 0.2s;
            }
            .s-input:focus {
                border-color: #0d9488;
                background: #fff;
                box-shadow: 0 0 0 4px rgba(13,148,136,0.1);
            }
            .s-input::placeholder { color: #94a3b8; }

            .s-pwd-toggle {
                position: absolute;
                right: 14px; top: 50%;
                transform: translateY(-50%);
                background: none;
                border: none;
                cursor: pointer;
                padding: 2px;
                color: #94a3b8;
                display: flex;
                z-index: 2;
            }
            .s-pwd-toggle:hover { color: #64748b; }
            .s-pwd-toggle svg { width: 18px; height: 18px; }

            .s-remember {
                display: flex;
                align-items: center;
                gap: 8px;
                width: 100%;
                margin: 0.5rem 0 1.25rem;
            }
            .s-remember input { width: 16px; height: 16px; accent-color: #0d9488; border-radius: 4px; }
            .s-remember label { font-size: 13px; color: #64748b; cursor: pointer; }

            .s-btn {
                width: 100%;
                padding: 13px;
                background: linear-gradient(135deg, #0f766e, #0d9488);
                color: #fff;
                border: none;
                border-radius: 12px;
                font-size: 15px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s;
                box-shadow: 0 4px 15px rgba(13,148,136,0.3);
            }
            .s-btn:hover {
                background: linear-gradient(135deg, #0d6d66, #0b8a7e);
                transform: translateY(-1px);
                box-shadow: 0 8px 25px rgba(13,148,136,0.4);
            }

            .s-divider {
                display: flex;
                align-items: center;
                width: 100%;
                margin: 1.25rem 0;
                gap: 12px;
            }
            .s-divider::before, .s-divider::after {
                content: '';
                flex: 1;
                height: 1px;
                background: #e2e8f0;
            }
            .s-divider span { font-size: 12px; color: #94a3b8; }

            .s-social-row {
                display: flex;
                gap: 10px;
                width: 100%;
            }
            .s-social-btn {
                flex: 1;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                padding: 11px;
                border: 2px solid #e2e8f0;
                background: #fff;
                border-radius: 12px;
                font-size: 13px;
                font-weight: 500;
                color: #334155;
                text-decoration: none;
                cursor: pointer;
                transition: all 0.2s;
            }
            .s-social-btn:hover {
                border-color: #cbd5e1;
                background: #f8fafc;
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            }
            .s-social-btn svg { width: 18px; height: 18px; }

            .s-error {
                color: #ef4444;
                font-size: 12px;
                margin-top: -0.5rem;
                margin-bottom: 0.5rem;
                width: 100%;
                padding-left: 4px;
            }

            /* ===== SWAPPED STATE ===== */
            .swap-container.swapped .swap-box::before {
                left: 180px; top: 62px; height: 265px;
            }
            .swap-container.swapped .swap-box::after {
                top: 22px; left: 192px; width: 324px; height: 220px;
            }
            .swap-container.swapped .swap-card {
                left: 320px;
            }
            .swap-container.swapped .swap-form.form-xodim {
                left: 0;
                opacity: 1;
            }
            .swap-container.swapped .swap-form.form-talaba {
                left: -100%;
                opacity: 0;
            }

            .footer-text {
                text-align: center;
                margin-top: 2rem;
                font-size: 11px;
                color: rgba(255,255,255,0.35);
            }

            /* ===== MOBILE ===== */
            .mobile-login {
                display: none;
                width: 100%;
                max-width: 420px;
                background: #fff;
                border-radius: 20px;
                box-shadow: 0 25px 60px rgba(0,0,0,0.3);
                overflow: hidden;
            }
            .mobile-tab-bar {
                display: flex;
                border-bottom: 2px solid #e2e8f0;
            }
            .mobile-tab-bar button {
                flex: 1;
                padding: 14px 0;
                font-size: 14px;
                font-weight: 500;
                background: none;
                border: none;
                cursor: pointer;
                border-bottom: 3px solid transparent;
                color: #64748b;
                margin-bottom: -2px;
                transition: all 0.2s;
            }
            .mobile-tab-bar button.active {
                border-bottom-color: #0d9488;
                color: #0d9488;
                font-weight: 600;
            }
            .mobile-panel {
                display: none;
                padding: 2rem 1.5rem;
            }
            .mobile-panel.active {
                display: block;
            }

            @media (max-width: 800px) {
                .swap-container { display: none !important; }
                .mobile-login { display: block; }
                body { padding: 1.5rem 1rem; }
            }
        </style>
    </head>
    <body>

        <div class="logo-header">
            <a href="/">
                <img src="{{ asset('logo.png') }}" alt="Logo" />
                <div class="logo-text">
                    <div class="logo-title">TDTU Termiz filiali</div>
                    <div class="logo-sub">Ta'lim boshqaruv tizimi</div>
                </div>
            </a>
        </div>

        {{ $slot }}

        <script>
            // CSRF refresh
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    fetch('/refresh-csrf')
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            document.querySelectorAll('input[name="_token"]').forEach(function(el) {
                                if (data.token) el.value = data.token;
                            });
                        }).catch(function() {});
                }
            });
            document.querySelectorAll('form').forEach(function(form) {
                var submitting = false;
                form.addEventListener('submit', function(e) {
                    if (submitting) return;
                    e.preventDefault();
                    submitting = true;
                    fetch('/refresh-csrf')
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            var t = form.querySelector('input[name="_token"]');
                            if (t && data.token) t.value = data.token;
                        }).catch(function() {})
                        .finally(function() { form.submit(); });
                });
            });

            function togglePwd(id) {
                var inp = document.getElementById(id);
                var on = document.getElementById(id + '-eye');
                var off = document.getElementById(id + '-eye-off');
                if (inp.type === 'password') {
                    inp.type = 'text';
                    on.style.display = 'none';
                    off.style.display = 'block';
                } else {
                    inp.type = 'password';
                    on.style.display = 'block';
                    off.style.display = 'none';
                }
            }

            window.addEventListener('pageshow', function(e) {
                if (e.persisted) window.location.reload();
            });
        </script>
    </body>
</html>
