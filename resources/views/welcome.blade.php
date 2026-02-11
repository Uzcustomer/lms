<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Laravel') }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-white">

<div class="min-h-screen flex items-center justify-center px-4" x-data="{
    profile: '{{ old('_profile', 'student') }}',
    clicks: 0,
    admin: false,
    logo() { this.clicks++; if (this.clicks >= 5) { this.admin = true; this.profile = 'admin'; } }
}">

    <div class="flex items-center gap-24 max-w-4xl w-full">

        {{-- Chap: Forma --}}
        <div class="w-80 shrink-0">
            <div class="bg-white rounded-xl shadow-lg p-8">

                <h1 class="text-center text-lg font-bold text-[#1a3a8a] leading-snug mb-1">
                    Toshkent tibbiyot akademiyasi<br>Termiz filiali
                </h1>
                <p class="text-center text-[#1a3a8a] text-xs mb-6">elektron jurnali</p>

                {{-- Tablar --}}
                <div class="flex mb-5 border-b border-gray-200">
                    <button @click="profile = 'student'"
                            :class="profile === 'student' ? 'border-[#1a3a8a] text-[#1a3a8a]' : 'border-transparent text-gray-400 hover:text-gray-500'"
                            class="flex-1 pb-2 text-sm font-semibold border-b-2 transition-colors cursor-pointer">
                        Student
                    </button>
                    <button @click="profile = 'teacher'"
                            :class="profile === 'teacher' ? 'border-[#1a3a8a] text-[#1a3a8a]' : 'border-transparent text-gray-400 hover:text-gray-500'"
                            class="flex-1 pb-2 text-sm font-semibold border-b-2 transition-colors cursor-pointer">
                        Xodim
                    </button>
                    <template x-if="admin">
                        <button @click="profile = 'admin'"
                                :class="profile === 'admin' ? 'border-[#1a3a8a] text-[#1a3a8a]' : 'border-transparent text-gray-400 hover:text-gray-500'"
                                class="flex-1 pb-2 text-sm font-semibold border-b-2 transition-colors cursor-pointer">
                            Admin
                        </button>
                    </template>
                </div>

                {{-- Xatoliklar --}}
                <x-auth-session-status class="mb-3" :status="session('status')" />
                @if($errors->any())
                    <div class="mb-3 p-2 rounded bg-red-50 border border-red-200">
                        @foreach($errors->all() as $error)
                            <p class="text-xs text-red-600">{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                {{-- Student --}}
                <form x-show="profile === 'student'" method="POST" action="{{ route('student.login.post') }}">
                    @csrf
                    <input type="hidden" name="_profile" value="student">
                    <div class="mb-3">
                        <label class="block text-xs font-semibold text-[#1a3a8a] mb-1">Login</label>
                        <input type="text" name="login" value="{{ old('login') }}" placeholder="Login"
                               class="w-full px-3 py-2 text-sm border border-gray-200 rounded-md bg-white placeholder-gray-400 focus:ring-1 focus:ring-[#1a3a8a] focus:border-[#1a3a8a] outline-none transition"
                               required autocomplete="login">
                    </div>
                    <div class="mb-4" x-data="{ show: false }">
                        <label class="block text-xs font-semibold text-[#1a3a8a] mb-1">Parol</label>
                        <div class="relative">
                            <input :type="show ? 'text' : 'password'" name="password" placeholder="Parol"
                                   class="w-full px-3 py-2 text-sm border border-gray-200 rounded-md bg-white placeholder-gray-400 focus:ring-1 focus:ring-[#1a3a8a] focus:border-[#1a3a8a] outline-none transition pr-9"
                                   required autocomplete="current-password">
                            <button type="button" @click="show = !show" class="absolute top-1/2 right-2.5 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <svg x-show="!show" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                <svg x-show="show" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="w-full py-2 bg-[#2563eb] hover:bg-[#1d4ed8] text-white text-sm font-semibold rounded-md transition-colors">
                        Kirish
                    </button>
                </form>

                {{-- Xodim --}}
                <form x-show="profile === 'teacher'" method="POST" action="{{ route('teacher.login.post') }}">
                    @csrf
                    <input type="hidden" name="_profile" value="teacher">
                    <div class="mb-3">
                        <label class="block text-xs font-semibold text-[#1a3a8a] mb-1">Login</label>
                        <input type="text" name="login" value="{{ old('login') }}" placeholder="Login"
                               class="w-full px-3 py-2 text-sm border border-gray-200 rounded-md bg-white placeholder-gray-400 focus:ring-1 focus:ring-[#1a3a8a] focus:border-[#1a3a8a] outline-none transition"
                               required autocomplete="login">
                    </div>
                    <div class="mb-4" x-data="{ show: false }">
                        <label class="block text-xs font-semibold text-[#1a3a8a] mb-1">Parol</label>
                        <div class="relative">
                            <input :type="show ? 'text' : 'password'" name="password" placeholder="Parol"
                                   class="w-full px-3 py-2 text-sm border border-gray-200 rounded-md bg-white placeholder-gray-400 focus:ring-1 focus:ring-[#1a3a8a] focus:border-[#1a3a8a] outline-none transition pr-9"
                                   required autocomplete="current-password">
                            <button type="button" @click="show = !show" class="absolute top-1/2 right-2.5 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <svg x-show="!show" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                <svg x-show="show" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="w-full py-2 bg-[#2563eb] hover:bg-[#1d4ed8] text-white text-sm font-semibold rounded-md transition-colors">
                        Kirish
                    </button>
                </form>

                {{-- Admin (yashirin) --}}
                <template x-if="admin && profile === 'admin'">
                    <form method="POST" action="{{ route('admin.login.post') }}">
                        @csrf
                        <input type="hidden" name="_profile" value="admin">
                        <div class="mb-3">
                            <label class="block text-xs font-semibold text-[#1a3a8a] mb-1">Login</label>
                            <input type="text" name="email" value="{{ old('email') }}" placeholder="Login"
                                   class="w-full px-3 py-2 text-sm border border-gray-200 rounded-md bg-white placeholder-gray-400 focus:ring-1 focus:ring-[#1a3a8a] focus:border-[#1a3a8a] outline-none transition"
                                   required autocomplete="username">
                        </div>
                        <div class="mb-4" x-data="{ show: false }">
                            <label class="block text-xs font-semibold text-[#1a3a8a] mb-1">Parol</label>
                            <div class="relative">
                                <input :type="show ? 'text' : 'password'" name="password" placeholder="Parol"
                                       class="w-full px-3 py-2 text-sm border border-gray-200 rounded-md bg-white placeholder-gray-400 focus:ring-1 focus:ring-[#1a3a8a] focus:border-[#1a3a8a] outline-none transition pr-9"
                                       required autocomplete="current-password">
                                <button type="button" @click="show = !show" class="absolute top-1/2 right-2.5 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                    <svg x-show="!show" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                                    <svg x-show="show" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" /></svg>
                                </button>
                            </div>
                        </div>
                        <button type="submit" class="w-full py-2 bg-[#2563eb] hover:bg-[#1d4ed8] text-white text-sm font-semibold rounded-md transition-colors">
                            Kirish
                        </button>
                    </form>
                </template>

            </div>

            <p x-show="profile === 'student'" class="mt-4 text-[11px] text-gray-400 leading-relaxed px-2">
                HEMIS login va parol bilan kiring. Parol esdan chiqqan bo'lsa, admin yoki dekanatga murojaat qiling.
            </p>
        </div>

        {{-- O'ng: Logo --}}
        <div class="hidden lg:flex items-center justify-center flex-1 cursor-pointer select-none" @click="logo()">
            <img src="{{ asset('logo.png') }}" alt="Logo" class="w-56 h-56 object-contain">
        </div>

    </div>

</div>

</body>
</html>
