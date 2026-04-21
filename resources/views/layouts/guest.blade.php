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
        <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

        <style>
            *, *::before, *::after { box-sizing: border-box; font-family: 'Inter', sans-serif; }

            .login-page {
                min-height: 100vh;
                background: #0f172a;
                background-image:
                    radial-gradient(at 20% 80%, rgba(13, 148, 136, 0.2) 0%, transparent 50%),
                    radial-gradient(at 80% 20%, rgba(59, 130, 246, 0.15) 0%, transparent 50%),
                    radial-gradient(at 50% 50%, rgba(99, 102, 241, 0.08) 0%, transparent 70%);
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                padding: 2rem 1rem;
                position: relative;
                overflow: hidden;
            }

            .login-page .bg-grid {
                position: absolute;
                inset: 0;
                background-image:
                    linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px),
                    linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
                background-size: 60px 60px;
                pointer-events: none;
            }

            .logo-header {
                text-align: center;
                margin-bottom: 2.5rem;
                position: relative;
                z-index: 2;
            }
            .logo-header a {
                display: inline-flex;
                align-items: center;
                gap: 16px;
                text-decoration: none;
            }
            .logo-header img {
                width: 56px; height: 56px;
                object-fit: contain;
                filter: drop-shadow(0 0 20px rgba(13,148,136,0.4));
            }
            .logo-title {
                color: #fff;
                font-size: 20px;
                font-weight: 800;
                letter-spacing: -0.5px;
                line-height: 1.2;
            }
            .logo-sub {
                color: rgba(255,255,255,0.45);
                font-size: 12px;
                font-weight: 400;
                margin-top: 3px;
                letter-spacing: 0.5px;
                text-transform: uppercase;
            }

            /* ===== CARD ===== */
            .login-card {
                position: relative;
                z-index: 2;
                background: #fff;
                border-radius: 28px;
                box-shadow:
                    0 0 0 1px rgba(255,255,255,0.08),
                    0 30px 80px -20px rgba(0,0,0,0.5),
                    0 0 60px rgba(13,148,136,0.08);
                overflow: hidden;
            }

            /* ===== SPLIT LAYOUT ===== */
            .split-left {
                padding: 3rem 2.5rem;
            }
            .split-right {
                background: linear-gradient(160deg, #0f766e 0%, #0d9488 40%, #14b8a6 100%);
                padding: 3rem 2.5rem;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                position: relative;
                overflow: hidden;
            }
            .split-right::before {
                content: '';
                position: absolute;
                top: -40%; right: -40%;
                width: 80%; height: 80%;
                background: radial-gradient(ellipse, rgba(255,255,255,0.12) 0%, transparent 70%);
                pointer-events: none;
            }

            .form-heading {
                font-size: 24px;
                font-weight: 800;
                color: #0f172a;
                margin: 0 0 4px;
                letter-spacing: -0.5px;
            }
            .form-subtitle {
                font-size: 13px;
                color: #94a3b8;
                margin: 0 0 2rem;
            }

            /* ===== INPUTS ===== */
            .input-wrap {
                position: relative;
                margin-bottom: 1rem;
            }
            .input-wrap .icon {
                position: absolute;
                left: 16px; top: 50%;
                transform: translateY(-50%);
                width: 18px; height: 18px;
                color: #cbd5e1;
                pointer-events: none;
                z-index: 2;
                transition: color 0.2s;
            }
            .input-wrap:focus-within .icon {
                color: #0d9488;
            }
            .login-input {
                width: 100%;
                padding: 15px 16px 15px 48px;
                border: 2px solid #e2e8f0;
                border-radius: 14px;
                font-size: 14px;
                font-weight: 500;
                background: #fff;
                color: #1e293b;
                outline: none;
                transition: all 0.25s;
            }
            .login-input:focus {
                border-color: #0d9488;
                box-shadow: 0 0 0 4px rgba(13,148,136,0.08), 0 2px 8px rgba(13,148,136,0.06);
            }
            .login-input::placeholder { color: #94a3b8; font-weight: 400; }

            .pwd-toggle {
                position: absolute;
                right: 16px; top: 50%;
                transform: translateY(-50%);
                background: none; border: none;
                cursor: pointer; padding: 2px;
                color: #cbd5e1;
                display: flex; z-index: 2;
                transition: color 0.2s;
            }
            .pwd-toggle:hover { color: #64748b; }
            .pwd-toggle svg { width: 18px; height: 18px; }

            .remember-row {
                display: flex;
                align-items: center;
                gap: 8px;
                margin: 0.75rem 0 1.5rem;
            }
            .remember-row input[type="checkbox"] {
                width: 16px; height: 16px;
                accent-color: #0d9488;
                border-radius: 4px;
                cursor: pointer;
            }
            .remember-row label {
                font-size: 13px; color: #64748b; cursor: pointer;
            }

            /* ===== BUTTONS ===== */
            .btn-login {
                width: 100%;
                padding: 15px;
                background: linear-gradient(135deg, #0f766e, #0d9488);
                color: #fff;
                border: none;
                border-radius: 14px;
                font-size: 15px;
                font-weight: 700;
                cursor: pointer;
                transition: all 0.3s;
                letter-spacing: 0.3px;
            }
            .btn-login:hover {
                background: linear-gradient(135deg, #0d6d66, #0b8a7e);
                transform: translateY(-2px);
                box-shadow: 0 12px 28px rgba(13,148,136,0.35);
            }
            .btn-login:active {
                transform: translateY(0);
                box-shadow: 0 4px 12px rgba(13,148,136,0.25);
            }

            .btn-login-outline {
                width: 100%;
                padding: 15px;
                background: rgba(255,255,255,0.1);
                border: 2px solid rgba(255,255,255,0.35);
                color: #fff;
                border-radius: 14px;
                font-size: 15px;
                font-weight: 700;
                cursor: pointer;
                transition: all 0.3s;
                backdrop-filter: blur(8px);
                letter-spacing: 0.3px;
            }
            .btn-login-outline:hover {
                background: rgba(255,255,255,0.2);
                border-color: rgba(255,255,255,0.6);
                transform: translateY(-2px);
                box-shadow: 0 12px 28px rgba(0,0,0,0.15);
            }

            .btn-social {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                flex: 1;
                padding: 12px;
                border: 2px solid #e2e8f0;
                background: #fff;
                color: #334155;
                border-radius: 12px;
                font-size: 13px;
                font-weight: 600;
                cursor: pointer;
                text-decoration: none;
                transition: all 0.2s;
            }
            .btn-social:hover {
                border-color: #cbd5e1;
                background: #f8fafc;
                transform: translateY(-2px);
                box-shadow: 0 6px 16px rgba(0,0,0,0.06);
            }
            .btn-social svg { width: 18px; height: 18px; }

            .divider {
                display: flex;
                align-items: center;
                margin: 1.5rem 0;
                gap: 14px;
            }
            .divider::before, .divider::after {
                content: '';
                flex: 1;
                height: 1px;
                background: linear-gradient(90deg, transparent, #e2e8f0, transparent);
            }
            .divider span { font-size: 11px; color: #94a3b8; text-transform: uppercase; letter-spacing: 1px; font-weight: 500; }

            .form-error {
                color: #ef4444;
                font-size: 12px;
                margin-top: -0.5rem;
                margin-bottom: 0.75rem;
                padding-left: 4px;
            }

            /* ===== RIGHT PANEL FORM CARD ===== */
            .right-form-card {
                width: 100%;
                max-width: 320px;
                background: rgba(255,255,255,0.1);
                border-radius: 20px;
                padding: 2rem;
                backdrop-filter: blur(16px);
                border: 1px solid rgba(255,255,255,0.15);
                position: relative;
                z-index: 2;
            }
            .right-form-card .login-input {
                background: rgba(255,255,255,0.95);
                border-color: rgba(255,255,255,0.3);
            }
            .right-form-card .login-input:focus {
                background: #fff;
                border-color: #fff;
                box-shadow: 0 0 0 4px rgba(255,255,255,0.15);
            }
            .right-form-card .remember-row label {
                color: rgba(255,255,255,0.7);
            }
            .right-form-card .remember-row input[type="checkbox"] {
                accent-color: #fff;
            }

            /* ===== MOBILE TABS ===== */
            .mobile-card {
                display: none;
                width: 100%;
                max-width: 420px;
                background: #fff;
                border-radius: 24px;
                box-shadow: 0 30px 80px -20px rgba(0,0,0,0.5);
                overflow: hidden;
                position: relative;
                z-index: 2;
            }
            .m-tab-bar {
                display: flex;
                background: #f8fafc;
                border-bottom: 1px solid #e2e8f0;
            }
            .m-tab-bar button {
                flex: 1;
                padding: 16px 0;
                font-size: 14px;
                font-weight: 500;
                background: none;
                border: none;
                cursor: pointer;
                color: #94a3b8;
                position: relative;
                transition: all 0.3s;
            }
            .m-tab-bar button.active {
                color: #0d9488;
                font-weight: 700;
            }
            .m-tab-bar button.active::after {
                content: '';
                position: absolute;
                bottom: 0; left: 20%; right: 20%;
                height: 3px;
                background: linear-gradient(90deg, #0f766e, #0d9488);
                border-radius: 3px 3px 0 0;
            }
            .m-panel {
                display: none;
                padding: 2rem 1.5rem 2.5rem;
            }
            .m-panel.active { display: block; }

            @media (max-width: 768px) {
                .desktop-split { display: none !important; }
                .mobile-card { display: block; }
                .login-page { padding: 1.5rem 1rem; }
                .logo-header { margin-bottom: 1.5rem; }
                .logo-title { font-size: 17px; }
            }

            .copyright {
                text-align: center;
                margin-top: 2rem;
                font-size: 11px;
                color: rgba(255,255,255,0.25);
                position: relative;
                z-index: 2;
            }
        </style>
    </head>
    <body>
        <div class="login-page">
            <div class="bg-grid"></div>

            <div class="logo-header">
                <a href="/">
                    <img src="{{ asset('logo.png') }}" alt="Logo" />
                    <div>
                        <div class="logo-title">TDTU Termiz filiali</div>
                        <div class="logo-sub">Ta'lim boshqaruv tizimi</div>
                    </div>
                </a>
            </div>

            {{ $slot }}
        </div>

        <script>
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    fetch('/refresh-csrf')
                        .then(function(r) { return r.json(); })
                        .then(function(d) {
                            document.querySelectorAll('input[name="_token"]').forEach(function(el) {
                                if (d.token) el.value = d.token;
                            });
                        }).catch(function() {});
                }
            });
            document.querySelectorAll('form').forEach(function(f) {
                var s = false;
                f.addEventListener('submit', function(e) {
                    if (s) return;
                    e.preventDefault(); s = true;
                    fetch('/refresh-csrf')
                        .then(function(r) { return r.json(); })
                        .then(function(d) {
                            var t = f.querySelector('input[name="_token"]');
                            if (t && d.token) t.value = d.token;
                        }).catch(function() {})
                        .finally(function() { f.submit(); });
                });
            });

            function togglePwd(id) {
                var i = document.getElementById(id);
                var on = document.getElementById(id + '-eye');
                var off = document.getElementById(id + '-eyeoff');
                if (i.type === 'password') { i.type = 'text'; on.style.display = 'none'; off.style.display = 'block'; }
                else { i.type = 'password'; on.style.display = 'block'; off.style.display = 'none'; }
            }

            window.addEventListener('pageshow', function(e) { if (e.persisted) window.location.reload(); });
        </script>
    </body>
</html>
