<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>TDTU Termiz filiali mark platformasi</title>
    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body { font-family: 'Inter', sans-serif; }
        .login-card {
            width: 340px;
            animation: fadeUp .5s ease-out;
        }
        @keyframes fadeUp {
            from { opacity: 0; transform: translateY(16px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .tab-btn { position: relative; }
        .tab-btn::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 50%;
            width: 0;
            height: 2px;
            background: #1e40af;
            transition: all .25s ease;
            transform: translateX(-50%);
        }
        .tab-btn.active::after { width: 100%; }
        .input-field {
            transition: border-color .2s ease, box-shadow .2s ease;
        }
        .input-field:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .btn-submit {
            transition: all .2s ease;
        }
        .btn-submit:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(30, 64, 175, 0.3);
        }
        .btn-submit:active {
            transform: translateY(0);
        }
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
        <div class="flex justify-center mb-5">
            <img src="{{ asset('logo.png') }}" alt="Logo"
                 class="w-16 h-16 object-contain cursor-pointer select-none"
                 @click="tap()">
        </div>

        {{-- Karta --}}
        <div class="bg-white rounded-2xl shadow-[0_2px_16px_rgba(0,0,0,0.08)] px-7 py-6">

            {{-- Sarlavha --}}
            <h1 class="text-center text-[15px] font-bold text-slate-800 leading-snug">
                Toshkent tibbiyot akademiyasi
            </h1>
            <p class="text-center text-[13px] font-semibold text-slate-800">Termiz filiali</p>
            <p class="text-center text-[11px] text-blue-600 font-medium mt-0.5 mb-5">elektron jurnali</p>

            {{-- Tablar --}}
            <div class="flex border-b border-gray-100 mb-5">
                <button @click="tab = 'student'"
                        class="tab-btn flex-1 pb-2.5 text-[13px] font-semibold cursor-pointer"
                        :class="tab === 'student' ? 'active text-blue-800' : 'text-gray-400 hover:text-gray-500'">
                    Student
                </button>
                <button @click="tab = 'teacher'"
                        class="tab-btn flex-1 pb-2.5 text-[13px] font-semibold cursor-pointer"
                        :class="tab === 'teacher' ? 'active text-blue-800' : 'text-gray-400 hover:text-gray-500'">
                    Xodim
                </button>
                <template x-if="adm">
                    <button @click="tab = 'admin'"
                            class="tab-btn flex-1 pb-2.5 text-[13px] font-semibold cursor-pointer"
                            :class="tab === 'admin' ? 'active text-blue-800' : 'text-gray-400 hover:text-gray-500'">
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

                <div class="mb-3.5">
                    <label class="block text-[11px] font-semibold text-slate-500 mb-1 uppercase tracking-wider">Login</label>
                    <input type="text" name="login" value="{{ old('login') }}" placeholder="HEMIS login"
                           class="input-field w-full px-3.5 py-2.5 text-[13px] border border-gray-200 rounded-lg bg-slate-50 text-slate-800 placeholder-gray-400 outline-none"
                           required autocomplete="login">
                </div>

                <div class="mb-5" x-data="{ show: false }">
                    <label class="block text-[11px] font-semibold text-slate-500 mb-1 uppercase tracking-wider">Parol</label>
                    <div class="relative">
                        <input :type="show ? 'text' : 'password'" name="password" placeholder="Parolni kiriting"
                               class="input-field w-full px-3.5 py-2.5 text-[13px] border border-gray-200 rounded-lg bg-slate-50 text-slate-800 placeholder-gray-400 outline-none pr-10"
                               required autocomplete="current-password">
                        <button type="button" @click="show = !show" class="absolute top-1/2 right-3 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                            <svg x-show="!show" class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                            <svg x-show="show" class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-submit w-full py-2.5 bg-blue-700 hover:bg-blue-800 text-white text-[13px] font-semibold rounded-lg">
                    Kirish
                </button>
            </form>

            {{-- Xodim forma --}}
            <form x-show="tab === 'teacher'" x-transition.opacity.duration.200ms method="POST" action="{{ route('teacher.login.post') }}">
                @csrf
                <input type="hidden" name="_profile" value="teacher">

                <div class="mb-3.5">
                    <label class="block text-[11px] font-semibold text-slate-500 mb-1 uppercase tracking-wider">Login</label>
                    <input type="text" name="login" value="{{ old('login') }}" placeholder="Login"
                           class="input-field w-full px-3.5 py-2.5 text-[13px] border border-gray-200 rounded-lg bg-slate-50 text-slate-800 placeholder-gray-400 outline-none"
                           required autocomplete="login">
                </div>

                <div class="mb-5" x-data="{ show: false }">
                    <label class="block text-[11px] font-semibold text-slate-500 mb-1 uppercase tracking-wider">Parol</label>
                    <div class="relative">
                        <input :type="show ? 'text' : 'password'" name="password" placeholder="Parolni kiriting"
                               class="input-field w-full px-3.5 py-2.5 text-[13px] border border-gray-200 rounded-lg bg-slate-50 text-slate-800 placeholder-gray-400 outline-none pr-10"
                               required autocomplete="current-password">
                        <button type="button" @click="show = !show" class="absolute top-1/2 right-3 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                            <svg x-show="!show" class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                            <svg x-show="show" class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-submit w-full py-2.5 bg-blue-700 hover:bg-blue-800 text-white text-[13px] font-semibold rounded-lg">
                    Kirish
                </button>
            </form>

            {{-- Admin forma (yashirin) --}}
            <template x-if="adm && tab === 'admin'">
                <form method="POST" action="{{ route('admin.login.post') }}">
                    @csrf
                    <input type="hidden" name="_profile" value="admin">

                    <div class="mb-3.5">
                        <label class="block text-[11px] font-semibold text-slate-500 mb-1 uppercase tracking-wider">Login</label>
                        <input type="text" name="email" value="{{ old('email') }}" placeholder="Admin login"
                               class="input-field w-full px-3.5 py-2.5 text-[13px] border border-gray-200 rounded-lg bg-slate-50 text-slate-800 placeholder-gray-400 outline-none"
                               required autocomplete="username">
                    </div>

                    <div class="mb-5" x-data="{ show: false }">
                        <label class="block text-[11px] font-semibold text-slate-500 mb-1 uppercase tracking-wider">Parol</label>
                        <div class="relative">
                            <input :type="show ? 'text' : 'password'" name="password" placeholder="Parolni kiriting"
                                   class="input-field w-full px-3.5 py-2.5 text-[13px] border border-gray-200 rounded-lg bg-slate-50 text-slate-800 placeholder-gray-400 outline-none pr-10"
                                   required autocomplete="current-password">
                            <button type="button" @click="show = !show" class="absolute top-1/2 right-3 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                                <svg x-show="!show" class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                <svg x-show="show" class="w-[18px] h-[18px]" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit w-full py-2.5 bg-blue-700 hover:bg-blue-800 text-white text-[13px] font-semibold rounded-lg">
                        Kirish
                    </button>
                </form>
            </template>
        </div>

        {{-- Pastki izoh --}}
        <p x-show="tab === 'student'" x-transition.opacity class="text-center text-[11px] text-gray-400 mt-4 leading-relaxed px-4">
            HEMIS login va parol bilan kiring. Parol esdan chiqqan bo'lsa,<br>admin yoki dekanatga murojaat qiling.
        </p>
    </div>

</div>

</body>
</html>
