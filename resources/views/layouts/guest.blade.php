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
            * { font-family: 'Inter', sans-serif; }

            .login-bg {
                background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 50%, #0d9488 100%);
                min-height: 100vh;
                position: relative;
                overflow: hidden;
            }
            .login-bg::before {
                content: '';
                position: absolute;
                top: -50%;
                right: -30%;
                width: 80%;
                height: 150%;
                background: radial-gradient(ellipse, rgba(13, 148, 136, 0.15) 0%, transparent 70%);
                pointer-events: none;
            }
            .login-bg::after {
                content: '';
                position: absolute;
                bottom: -20%;
                left: -20%;
                width: 60%;
                height: 80%;
                background: radial-gradient(ellipse, rgba(59, 130, 246, 0.1) 0%, transparent 70%);
                pointer-events: none;
            }

            .geo-shape {
                position: absolute;
                border-radius: 50%;
                opacity: 0.06;
                background: #fff;
                pointer-events: none;
            }

            .login-card {
                background: rgba(255, 255, 255, 0.97);
                backdrop-filter: blur(20px);
                border-radius: 24px;
                box-shadow:
                    0 25px 60px rgba(0, 0, 0, 0.3),
                    0 0 0 1px rgba(255, 255, 255, 0.1);
                overflow: hidden;
            }

            .split-left {
                padding: 3rem 2.5rem;
                position: relative;
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
                top: -30%;
                right: -30%;
                width: 70%;
                height: 70%;
                background: radial-gradient(ellipse, rgba(255,255,255,0.1) 0%, transparent 70%);
                pointer-events: none;
            }
            .split-right::after {
                content: '';
                position: absolute;
                bottom: -20%;
                left: -20%;
                width: 50%;
                height: 50%;
                background: radial-gradient(ellipse, rgba(255,255,255,0.08) 0%, transparent 70%);
                pointer-events: none;
            }

            .login-input-group {
                position: relative;
                margin-bottom: 1.25rem;
            }
            .login-input-group svg {
                position: absolute;
                left: 14px;
                top: 50%;
                transform: translateY(-50%);
                width: 18px;
                height: 18px;
                color: #94a3b8;
                z-index: 2;
                pointer-events: none;
            }
            .login-input {
                width: 100%;
                padding: 14px 14px 14px 44px;
                border: 2px solid #e2e8f0;
                border-radius: 14px;
                font-size: 14px;
                transition: all 0.2s;
                background: #f8fafc;
                color: #1e293b;
                outline: none;
            }
            .login-input:focus {
                border-color: #0d9488;
                background: #fff;
                box-shadow: 0 0 0 4px rgba(13, 148, 136, 0.1);
            }
            .login-input::placeholder {
                color: #94a3b8;
            }

            .btn-primary-login {
                width: 100%;
                padding: 14px;
                background: linear-gradient(135deg, #0f766e, #0d9488);
                color: #fff;
                border: none;
                border-radius: 14px;
                font-size: 15px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s;
                position: relative;
                overflow: hidden;
            }
            .btn-primary-login:hover {
                background: linear-gradient(135deg, #0d6d66, #0b8a7e);
                transform: translateY(-1px);
                box-shadow: 0 8px 25px rgba(13, 148, 136, 0.35);
            }
            .btn-primary-login:active {
                transform: translateY(0);
            }

            .btn-outline-login {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                width: 100%;
                padding: 12px;
                border: 2px solid rgba(255,255,255,0.4);
                background: rgba(255,255,255,0.1);
                color: #fff;
                border-radius: 14px;
                font-size: 14px;
                font-weight: 500;
                cursor: pointer;
                text-decoration: none;
                transition: all 0.2s;
                backdrop-filter: blur(10px);
            }
            .btn-outline-login:hover {
                background: rgba(255,255,255,0.2);
                border-color: rgba(255,255,255,0.6);
                transform: translateY(-1px);
            }

            .btn-social {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                width: 100%;
                padding: 12px;
                border: 2px solid #e2e8f0;
                background: #fff;
                color: #334155;
                border-radius: 14px;
                font-size: 13px;
                font-weight: 500;
                cursor: pointer;
                text-decoration: none;
                transition: all 0.2s;
            }
            .btn-social:hover {
                border-color: #cbd5e1;
                background: #f8fafc;
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            }

            .divider {
                display: flex;
                align-items: center;
                margin: 1.25rem 0;
                gap: 12px;
            }
            .divider::before, .divider::after {
                content: '';
                flex: 1;
                height: 1px;
                background: #e2e8f0;
            }
            .divider span {
                font-size: 12px;
                color: #94a3b8;
                white-space: nowrap;
            }

            .divider-white {
                display: flex;
                align-items: center;
                margin: 1.25rem 0;
                gap: 12px;
            }
            .divider-white::before, .divider-white::after {
                content: '';
                flex: 1;
                height: 1px;
                background: rgba(255,255,255,0.25);
            }
            .divider-white span {
                font-size: 12px;
                color: rgba(255,255,255,0.6);
                white-space: nowrap;
            }

            .password-toggle {
                position: absolute;
                right: 14px;
                top: 50%;
                transform: translateY(-50%);
                background: none;
                border: none;
                cursor: pointer;
                padding: 4px;
                color: #94a3b8;
                z-index: 2;
                display: flex;
                align-items: center;
            }
            .password-toggle:hover { color: #64748b; }
            .password-toggle svg { width: 18px; height: 18px; }

            .remember-check {
                display: flex;
                align-items: center;
                gap: 8px;
                margin: 1rem 0 1.5rem;
            }
            .remember-check input[type="checkbox"] {
                width: 16px;
                height: 16px;
                border-radius: 5px;
                accent-color: #0d9488;
            }
            .remember-check label {
                font-size: 13px;
                color: #64748b;
                cursor: pointer;
            }

            .form-error {
                color: #ef4444;
                font-size: 12px;
                margin-top: 4px;
                padding-left: 4px;
            }

            .mobile-tabs {
                display: none;
            }

            @media (max-width: 768px) {
                .login-card {
                    border-radius: 20px;
                    margin: 1rem;
                }
                .desktop-split {
                    display: none !important;
                }
                .mobile-tabs {
                    display: block;
                }
                .split-left, .split-right {
                    padding: 2rem 1.5rem;
                }
                .mobile-tab-content {
                    padding: 2rem 1.5rem;
                }
            }
        </style>
    </head>
    <body>
        <div class="login-bg">
            <div class="geo-shape" style="width:400px;height:400px;top:-100px;right:-100px;"></div>
            <div class="geo-shape" style="width:300px;height:300px;bottom:-80px;left:-80px;"></div>
            <div class="geo-shape" style="width:150px;height:150px;top:40%;left:5%;"></div>
            <div class="geo-shape" style="width:80px;height:80px;top:20%;right:15%;"></div>

            <div style="position:relative; z-index:10; min-height:100vh; display:flex; flex-direction:column; align-items:center; justify-content:center; padding:2rem 1rem;">

                <div style="text-align:center; margin-bottom:2rem;">
                    <a href="/" style="display:inline-flex; align-items:center; gap:14px; text-decoration:none;">
                        <img src="{{ asset('logo.png') }}" alt="Logo" style="width:56px; height:56px; object-fit:contain; filter:drop-shadow(0 4px 8px rgba(0,0,0,0.3));" />
                        <div style="text-align:left;">
                            <div style="color:#fff; font-size:18px; font-weight:700; letter-spacing:-0.3px; line-height:1.2;">TDTU Termiz filiali</div>
                            <div style="color:rgba(255,255,255,0.6); font-size:12px; font-weight:400; margin-top:2px;">Ta'lim boshqaruv tizimi</div>
                        </div>
                    </a>
                </div>

                {{ $slot }}

            </div>
        </div>

        <script>
            // CSRF tokenni avtomatik yangilash
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    fetch('/refresh-csrf')
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            document.querySelectorAll('input[name="_token"]').forEach(function(el) {
                                if (data.token) el.value = data.token;
                            });
                        })
                        .catch(function() {});
                }
            });

            // Formani yuborishdan oldin CSRF tokenni yangilash
            document.querySelectorAll('form').forEach(function(form) {
                var submitting = false;
                form.addEventListener('submit', function(e) {
                    if (submitting) return;
                    e.preventDefault();
                    submitting = true;
                    fetch('/refresh-csrf')
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            var tokenInput = form.querySelector('input[name="_token"]');
                            if (tokenInput && data.token) tokenInput.value = data.token;
                        })
                        .catch(function() {})
                        .finally(function() {
                            form.submit();
                        });
                });
            });

            // Password toggle
            function togglePassword(inputId) {
                var input = document.getElementById(inputId);
                var eyeOn = document.getElementById(inputId + '-eye');
                var eyeOff = document.getElementById(inputId + '-eye-off');
                if (input.type === 'password') {
                    input.type = 'text';
                    eyeOn.style.display = 'none';
                    eyeOff.style.display = 'block';
                } else {
                    input.type = 'password';
                    eyeOn.style.display = 'block';
                    eyeOff.style.display = 'none';
                }
            }
        </script>

        <script>
            window.addEventListener('pageshow', function(event) {
                if (event.persisted) {
                    window.location.reload();
                }
            });
        </script>
    </body>
</html>
