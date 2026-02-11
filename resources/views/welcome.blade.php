<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Laravel') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Inter', sans-serif; }
        .login-card { width: 340px; animation: fadeUp .4s ease-out; }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .input-group {
            position: relative;
            border-left: 3px solid #f97316;
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
            transition: all .2s ease;
        }
        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
        }
        .btn-submit:active { transform: translateY(0); }
        .role-btn {
            transition: all .2s ease;
        }
        .role-btn:hover {
            transform: translateY(-1px);
            filter: brightness(1.05);
        }
        .role-btn:active { transform: translateY(0); }
    </style>
</head>
<body class="bg-slate-50 antialiased">

<div class="min-h-screen flex items-center justify-center p-4" x-data="{
    tab: '{{ old('_profile', 'student') }}',
    c: 0, adm: false,
    tap() { this.c++; if(this.c>=5){this.adm=true;this.tab='admin';} }
}">
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
                        :class="tab === 'student'
                            ? 'bg-emerald-500 text-white shadow-md shadow-emerald-200'
                            : 'bg-emerald-50 text-emerald-600 hover:bg-emerald-100'"
                        class="role-btn flex-1 py-2 rounded-lg text-[13px] font-semibold cursor-pointer">
                    Talaba
                </button>
                <button @click="tab = 'teacher'"
                        :class="tab === 'teacher'
                            ? 'bg-amber-500 text-white shadow-md shadow-amber-200'
                            : 'bg-amber-50 text-amber-600 hover:bg-amber-100'"
                        class="role-btn flex-1 py-2 rounded-lg text-[13px] font-semibold cursor-pointer">
                    Xodim
                </button>
                <template x-if="adm">
                    <button @click="tab = 'admin'"
                            :class="tab === 'admin'
                                ? 'bg-violet-500 text-white shadow-md shadow-violet-200'
                                : 'bg-violet-50 text-violet-600 hover:bg-violet-100'"
                            class="role-btn flex-1 py-2 rounded-lg text-[13px] font-semibold cursor-pointer">
                        Admin
                    </button>
                </template>
            </div>

            {{-- Xatoliklar --}}
            <x-auth-session-status class="mb-3" :status="session('status')" />
            @if($errors->any())
                <div class="mb-3 p-2.5 rounded-lg bg-red-50 border border-red-100">
                    @foreach($errors->all() as $error)
                        <p class="text-[12px] text-red-600">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            {{-- Student forma --}}
            <form x-show="tab === 'student'" x-transition.opacity.duration.200ms method="POST" action="{{ route('student.login.post') }}">
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

                <button type="submit" class="btn-submit w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-[13px] font-semibold rounded-lg">
                    Kirish
                </button>
            </form>

            {{-- Xodim forma --}}
            <form x-show="tab === 'teacher'" x-transition.opacity.duration.200ms method="POST" action="{{ route('teacher.login.post') }}">
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

                <button type="submit" class="btn-submit w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-[13px] font-semibold rounded-lg">
                    Kirish
                </button>
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

                    <button type="submit" class="btn-submit w-full py-2.5 bg-blue-600 hover:bg-blue-700 text-white text-[13px] font-semibold rounded-lg">
                        Kirish
                    </button>
                </form>
            </template>
        </div>

        {{-- Pastki izoh --}}
        <p x-show="tab === 'student'" x-transition.opacity class="text-center text-[10px] text-gray-400 mt-3.5 leading-relaxed">
            HEMIS login va parol bilan kiring. Parol esdan chiqqan bo'lsa,<br>admin yoki dekanatga murojaat qiling.
        </p>
    </div>
</div>

</body>
</html>
