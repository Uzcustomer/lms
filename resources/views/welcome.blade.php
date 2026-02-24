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
        body { font-family: 'Inter', sans-serif; }
        .login-card { width: 400px; animation: fadeUp .4s ease-out; }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .input-group {
            position: relative;
            border-left: 3px solid #3b82f6;
            background: #fff;
            border-radius: 0 8px 8px 0;
            overflow: hidden;
        }
        .input-group input {
            width: 100%;
            padding: 10px 40px 10px 14px;
            font-size: 13px;
            border: 1px solid #e5e7eb;
            border-left: none;
            border-radius: 0 8px 8px 0;
            background: transparent;
            outline: none;
            color: #1e293b;
            transition: border-color .2s, box-shadow .2s;
        }
        .input-group input:focus {
            border-color: #3b82f6;
            box-shadow: 2px 0 0 0 #3b82f6 inset;
        }
        .input-group input::placeholder { color: #9ca3af; }
        .input-label {
            font-size: 11px;
            font-weight: 600;
            color: #64748b;
            margin-bottom: 5px;
            display: block;
        }
        .eye-btn {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            cursor: pointer;
            transition: color .15s;
            background: none;
            border: none;
            padding: 2px;
            display: flex;
            align-items: center;
        }
        .eye-btn:hover { color: #475569; }
        .btn-submit {
            transition: all .25s ease;
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 50%, #1e40af 100%);
            padding: 14px 0;
            font-size: 16px;
            font-weight: 700;
            letter-spacing: 0.5px;
            border-radius: 14px;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(30, 64, 175, 0.4);
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 50%, #1d4ed8 100%);
        }
        .btn-submit:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(30, 64, 175, 0.3);
        }
        .role-btn {
            transition: all .2s ease;
            font-weight: 600;
            font-size: 13px;
            padding: 9px 0;
            border-radius: 10px;
            cursor: pointer;
            flex: 1;
            border: none;
        }
        .role-btn:hover {
            transform: translateY(-1px);
            filter: brightness(1.08);
        }
        .role-btn:active { transform: translateY(0); }
        .role-btn-student-active {
            background: #1e40af;
            color: #fff;
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.35);
        }
        .role-btn-student-inactive {
            background: #dbeafe;
            color: #1e40af;
        }
        .role-btn-student-inactive:hover { background: #bfdbfe; }
        .role-btn-teacher-active {
            background: #1e40af;
            color: #fff;
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.35);
        }
        .role-btn-teacher-inactive {
            background: #dbeafe;
            color: #1e40af;
        }
        .role-btn-teacher-inactive:hover { background: #bfdbfe; }
        .role-btn-admin-active {
            background: #8b5cf6;
            color: #fff;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.35);
        }
        .role-btn-admin-inactive {
            background: #f5f3ff;
            color: #7c3aed;
        }
        .role-btn-admin-inactive:hover { background: #ede9fe; }
    </style>
</head>
<body class="bg-slate-50 antialiased">

{{-- Telegram in-app brauzer uchun ogohlantirish --}}
<div id="telegram-warning" style="display:none; position:fixed; top:0; left:0; right:0; z-index:9999; background:#fef3c7; border-bottom:2px solid #f59e0b; padding:12px 16px; text-align:center;">
    <p style="margin:0; font-size:13px; color:#92400e; font-weight:600;">
        Telegram brauzerida muammo bo'lishi mumkin.
    </p>
    <p style="margin:4px 0 8px; font-size:12px; color:#a16207;">
        Iltimos, havolani brauzerda oching: <strong>"..." &rarr; "Open in Browser"</strong>
    </p>
    <button onclick="document.getElementById('telegram-warning').style.display='none'" style="background:#f59e0b; color:white; border:none; padding:5px 16px; border-radius:6px; font-size:12px; cursor:pointer; font-weight:600;">Tushundim</button>
</div>
<script>
    if (/Telegram/i.test(navigator.userAgent || '')) {
        document.getElementById('telegram-warning').style.display = 'block';
    }
</script>

<div class="min-h-screen flex items-center justify-center p-4" x-data="{
    tab: '{{ old('_profile') }}' || localStorage.getItem('lastLoginTab') || 'student',
    c: 0, adm: false,
    tap() { this.c++; if(this.c>=5){this.adm=true;this.tab='admin';} }
}" x-init="$watch('tab', val => localStorage.setItem('lastLoginTab', val))">
    <div class="login-card">

        {{-- Logo --}}
        <div class="flex justify-center mb-4">
            <img src="{{ asset('logo.png') }}" alt="Logo"
                 class="w-16 h-16 object-contain cursor-pointer select-none" @click="tap()">
        </div>

        {{-- Karta --}}
        <div class="bg-white rounded-2xl shadow-[0_2px_20px_rgba(0,0,0,0.07)] px-7 pt-5 pb-6">

            {{-- Sarlavha --}}
            <h1 class="text-center text-[14px] font-bold text-slate-800 leading-snug">
                Toshkent davlat tibbiyot universiteti
            </h1>
            <p class="text-center text-[13px] font-semibold text-slate-700">Termiz filiali</p>
            <p class="text-center text-[11px] text-blue-600 font-medium mt-0.5 mb-5 tracking-wide">elektron jurnali</p>

            {{-- Rol tugmalari --}}
            <div class="flex gap-2.5 mb-5">
                <button @click="tab = 'student'"
                        :class="tab === 'student' ? 'role-btn-student-active' : 'role-btn-student-inactive'"
                        class="role-btn">
                    Talaba
                </button>
                <button @click="tab = 'teacher'"
                        :class="tab === 'teacher' ? 'role-btn-teacher-active' : 'role-btn-teacher-inactive'"
                        class="role-btn">
                    Xodim
                </button>
                <template x-if="adm">
                    <button @click="tab = 'admin'"
                            :class="tab === 'admin' ? 'role-btn-admin-active' : 'role-btn-admin-inactive'"
                            class="role-btn">
                        Admin
                    </button>
                </template>
            </div>

            {{-- Sessiya xabarlari --}}
            @if(session('status'))
                <div class="mb-3 p-2.5 rounded-lg bg-amber-50 border border-amber-200">
                    <p class="text-[12px] text-amber-700 font-medium">{{ session('status') }}</p>
                </div>
            @endif


            @if($errors->any())
                <div class="mb-3 p-2.5 rounded-lg bg-red-50 border border-red-100">
                    @foreach($errors->all() as $error)
                        <p class="text-[12px] text-red-600">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            {{-- Student forma --}}
            <form x-show="tab === 'student'" method="POST" action="{{ route('student.login.post') }}">
                @csrf
                <input type="hidden" name="_profile" value="student">

                <div class="mb-3">
                    <label class="input-label">Login</label>
                    <div class="input-group">
                        <input type="text" name="login" value="{{ old('login') }}" placeholder="HEMIS login"
                               required autocomplete="login">
                    </div>
                </div>

                <div class="mb-4" x-data="{ show: false }">
                    <label class="input-label">Parol</label>
                    <div class="input-group">
                        <input :type="show ? 'text' : 'password'" name="password" placeholder="Parolni kiriting"
                               required autocomplete="current-password">
                        <button type="button" @click="show = !show" class="eye-btn">
                            <svg x-show="!show" class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                            <svg x-show="show" class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-submit w-full text-white">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3-3l3-3m0 0l-3-3m3 3H9" /></svg>
                    Kirish
                </button>

                {{-- Ajratuvchi --}}
                <div style="display:flex; align-items:center; margin:14px 0;">
                    <div style="flex:1; height:1px; background:#e5e7eb;"></div>
                    <span style="padding:0 10px; font-size:11px; color:#9ca3af;">yoki</span>
                    <div style="flex:1; height:1px; background:#e5e7eb;"></div>
                </div>

                {{-- HEMIS OAuth tugmasi --}}
                <a href="{{ route('auth.hemis.redirect') }}"
                   style="display:flex; align-items:center; justify-content:center; gap:8px; width:100%; padding:12px 0; background:linear-gradient(135deg, #065f46 0%, #047857 100%); color:#fff; font-size:14px; font-weight:700; border-radius:14px; text-decoration:none; letter-spacing:0.3px; transition:all .25s ease;"
                   onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 24px rgba(6,95,70,0.4)'"
                   onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                    Hemis orqali kirish
                </a>
            </form>

            {{-- Xodim forma --}}
            <form x-show="tab === 'teacher'" method="POST" action="{{ route('teacher.login.post') }}">
                @csrf
                <input type="hidden" name="_profile" value="teacher">


                <div class="mb-3">
                    <label class="input-label">Login</label>
                    <div class="input-group">
                        <input type="text" name="login" value="{{ old('login') }}" placeholder="Login"
                               required autocomplete="login">
                    </div>
                </div>

                <div class="mb-4" x-data="{ show: false }">
                    <label class="input-label">Parol</label>
                    <div class="input-group">
                        <input :type="show ? 'text' : 'password'" name="password" placeholder="Parolni kiriting"
                               required autocomplete="current-password">
                        <button type="button" @click="show = !show" class="eye-btn">
                            <svg x-show="!show" class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                            <svg x-show="show" class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-submit w-full text-white">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3-3l3-3m0 0l-3-3m3 3H9" /></svg>
                    Kirish
                </button>

                {{-- Ajratuvchi --}}
                <div style="display:flex; align-items:center; margin:14px 0;">
                    <div style="flex:1; height:1px; background:#e5e7eb;"></div>
                    <span style="padding:0 10px; font-size:11px; color:#9ca3af;">yoki</span>
                    <div style="flex:1; height:1px; background:#e5e7eb;"></div>
                </div>

                {{-- HEMIS OAuth tugmasi --}}
                <a href="{{ route('auth.hemis.redirect') }}"
                   style="display:flex; align-items:center; justify-content:center; gap:8px; width:100%; padding:12px 0; background:linear-gradient(135deg, #065f46 0%, #047857 100%); color:#fff; font-size:14px; font-weight:700; border-radius:14px; text-decoration:none; letter-spacing:0.3px; transition:all .25s ease;"
                   onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 24px rgba(6,95,70,0.4)'"
                   onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='none'">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                    Hemis orqali kirish
                </a>
            </form>

            {{-- Admin forma (yashirin) --}}
            <template x-if="adm && tab === 'admin'">
                <form method="POST" action="{{ route('admin.login.post') }}">
                    @csrf
                    <input type="hidden" name="_profile" value="admin">

                    <div class="mb-3">
                        <label class="input-label">Login</label>
                        <div class="input-group">
                            <input type="text" name="email" value="{{ old('email') }}" placeholder="Admin login"
                                   required autocomplete="username">
                        </div>
                    </div>

                    <div class="mb-4" x-data="{ show: false }">
                        <label class="input-label">Parol</label>
                        <div class="input-group">
                            <input :type="show ? 'text' : 'password'" name="password" placeholder="Parolni kiriting"
                                   required autocomplete="current-password">
                            <button type="button" @click="show = !show" class="eye-btn">
                                <svg x-show="!show" class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                <svg x-show="show" class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit w-full text-white">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3-3l3-3m0 0l-3-3m3 3H9" /></svg>
                        Kirish
                    </button>
                </form>
            </template>
        </div>

        {{-- Pastki izoh --}}
        <p x-show="tab === 'student' || tab === 'teacher'" x-transition.opacity class="text-center text-[10px] text-gray-400 mt-3.5 leading-relaxed">
            Lokal parol bilan kiring yoki "Hemis orqali kirish" tugmasini bosing.<br>Parol esdan chiqqan bo'lsa, admin yoki dekanatga murojaat qiling.
        </p>
    </div>
</div>

{{-- Brauzer back/forward cache va CSRF token yangilash --}}
<script>
    // bfcache dan qaytganda sahifani qayta yuklash
    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            window.location.reload();
        }
    });

    // Sahifa qayta ko'rinsa (tab almashtirish, boshqa oynadan qaytish) CSRF tokenni yangilash
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'visible') {
            fetch('{{ url("/refresh-csrf") }}', { credentials: 'same-origin' })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.token) {
                        document.querySelector('meta[name="csrf-token"]').content = data.token;
                        document.querySelectorAll('input[name="_token"]').forEach(function(input) {
                            input.value = data.token;
                        });
                    }
                })
                .catch(function() {});
        }
    });
</script>
</body>
</html>
