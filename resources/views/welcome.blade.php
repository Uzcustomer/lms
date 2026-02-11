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
<body class="font-sans text-gray-900 antialiased">

<div class="flex min-h-screen" x-data="{
    selectedProfile: '{{ old('_profile', 'student') }}',
    adminClicks: 0,
    showAdmin: false,
    handleLogoClick() {
        this.adminClicks++;
        if (this.adminClicks >= 5) {
            this.showAdmin = true;
            this.selectedProfile = 'admin';
        }
    }
}">

    {{-- Chap tomon: Forma --}}
    <div class="w-full lg:w-1/2 flex items-center justify-center bg-white p-6 sm:p-12">
        <div class="w-full max-w-md">

            {{-- Universitet nomi --}}
            <div class="mb-10">
                <h1 class="text-2xl sm:text-3xl font-bold text-[#1a3a8a] leading-tight">
                    Toshkent tibbiyot akademiyasi
                </h1>
                <h2 class="text-xl sm:text-2xl font-semibold text-[#1a3a8a] mt-1">
                    Termiz filiali
                </h2>
                <p class="text-gray-400 mt-2 text-sm tracking-wide">Jurnal tizimi</p>
            </div>

            {{-- Tablar: Student / Xodim --}}
            <div class="flex mb-8 bg-gray-100 rounded-lg p-1">
                <button @click="selectedProfile = 'student'"
                        :class="selectedProfile === 'student'
                            ? 'bg-white text-[#1a3a8a] shadow'
                            : 'text-gray-400 hover:text-gray-600'"
                        class="flex-1 py-2.5 rounded-md text-sm font-semibold transition-all duration-200 cursor-pointer">
                    Student
                </button>
                <button @click="selectedProfile = 'teacher'"
                        :class="selectedProfile === 'teacher'
                            ? 'bg-white text-[#1a3a8a] shadow'
                            : 'text-gray-400 hover:text-gray-600'"
                        class="flex-1 py-2.5 rounded-md text-sm font-semibold transition-all duration-200 cursor-pointer">
                    Xodim
                </button>
                <template x-if="showAdmin">
                    <button @click="selectedProfile = 'admin'"
                            :class="selectedProfile === 'admin'
                                ? 'bg-white text-[#1a3a8a] shadow'
                                : 'text-gray-400 hover:text-gray-600'"
                            class="flex-1 py-2.5 rounded-md text-sm font-semibold transition-all duration-200 cursor-pointer">
                        Admin
                    </button>
                </template>
            </div>

            {{-- Xatoliklar --}}
            <x-auth-session-status class="mb-4" :status="session('status')" />
            @if($errors->any())
                <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200">
                    @foreach($errors->all() as $error)
                        <p class="text-sm text-red-600">{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            {{-- Student Login --}}
            <form x-show="selectedProfile === 'student'" method="POST" action="{{ route('student.login.post') }}">
                @csrf
                <input type="hidden" name="_profile" value="student">

                <div class="mb-5">
                    <label for="student_login" class="block text-sm font-medium text-gray-600 mb-1.5">Login</label>
                    <input id="student_login" type="text" name="login" value="{{ old('login') }}"
                           class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-800 placeholder-gray-400 focus:bg-white focus:ring-2 focus:ring-[#1a3a8a] focus:border-transparent outline-none transition"
                           required autocomplete="login" placeholder="HEMIS login">
                </div>

                <div class="mb-6" x-data="{ show: false }">
                    <label for="student_password" class="block text-sm font-medium text-gray-600 mb-1.5">Parol</label>
                    <div class="relative">
                        <input id="student_password" :type="show ? 'text' : 'password'" name="password"
                               class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-800 placeholder-gray-400 focus:bg-white focus:ring-2 focus:ring-[#1a3a8a] focus:border-transparent outline-none transition pr-11"
                               required autocomplete="current-password" placeholder="Parolni kiriting">
                        <button type="button" @click="show = !show"
                                class="absolute top-1/2 right-3 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <svg x-show="!show" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <svg x-show="show" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit"
                        class="w-full py-3 bg-[#1a3a8a] hover:bg-[#152e6e] text-white font-semibold rounded-lg transition-colors duration-200">
                    Kirish
                </button>

                <p class="mt-4 text-xs text-gray-400 leading-relaxed">
                    HEMIS login va parol bilan kiring. Parol esdan chiqqan bo'lsa, admin yoki dekanatga murojaat qiling.
                </p>
            </form>

            {{-- Xodim Login --}}
            <form x-show="selectedProfile === 'teacher'" method="POST" action="{{ route('teacher.login.post') }}">
                @csrf
                <input type="hidden" name="_profile" value="teacher">

                <div class="mb-5">
                    <label for="teacher_login" class="block text-sm font-medium text-gray-600 mb-1.5">Login</label>
                    <input id="teacher_login" type="text" name="login" value="{{ old('login') }}"
                           class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-800 placeholder-gray-400 focus:bg-white focus:ring-2 focus:ring-[#1a3a8a] focus:border-transparent outline-none transition"
                           required autocomplete="login" placeholder="Login kiriting">
                </div>

                <div class="mb-6" x-data="{ show: false }">
                    <label for="teacher_password" class="block text-sm font-medium text-gray-600 mb-1.5">Parol</label>
                    <div class="relative">
                        <input id="teacher_password" :type="show ? 'text' : 'password'" name="password"
                               class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-800 placeholder-gray-400 focus:bg-white focus:ring-2 focus:ring-[#1a3a8a] focus:border-transparent outline-none transition pr-11"
                               required autocomplete="current-password" placeholder="Parolni kiriting">
                        <button type="button" @click="show = !show"
                                class="absolute top-1/2 right-3 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                            <svg x-show="!show" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <svg x-show="show" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit"
                        class="w-full py-3 bg-[#1a3a8a] hover:bg-[#152e6e] text-white font-semibold rounded-lg transition-colors duration-200">
                    Kirish
                </button>
            </form>

            {{-- Admin Login (yashirin) --}}
            <template x-if="showAdmin && selectedProfile === 'admin'">
                <form method="POST" action="{{ route('admin.login.post') }}">
                    @csrf
                    <input type="hidden" name="_profile" value="admin">

                    <div class="mb-5">
                        <label for="admin_email" class="block text-sm font-medium text-gray-600 mb-1.5">Login</label>
                        <input id="admin_email" type="text" name="email" value="{{ old('email') }}"
                               class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-800 placeholder-gray-400 focus:bg-white focus:ring-2 focus:ring-[#1a3a8a] focus:border-transparent outline-none transition"
                               required autocomplete="username" placeholder="Admin login">
                    </div>

                    <div class="mb-6" x-data="{ show: false }">
                        <label for="admin_password" class="block text-sm font-medium text-gray-600 mb-1.5">Parol</label>
                        <div class="relative">
                            <input id="admin_password" :type="show ? 'text' : 'password'" name="password"
                                   class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-lg text-gray-800 placeholder-gray-400 focus:bg-white focus:ring-2 focus:ring-[#1a3a8a] focus:border-transparent outline-none transition pr-11"
                                   required autocomplete="current-password" placeholder="Parolni kiriting">
                            <button type="button" @click="show = !show"
                                    class="absolute top-1/2 right-3 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                                <svg x-show="!show" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <svg x-show="show" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit"
                            class="w-full py-3 bg-[#1a3a8a] hover:bg-[#152e6e] text-white font-semibold rounded-lg transition-colors duration-200">
                        Kirish
                    </button>
                </form>
            </template>

        </div>
    </div>

    {{-- O'ng tomon: Logo (faqat katta ekranda) --}}
    <div class="hidden lg:flex w-1/2 bg-[#1a3a8a] items-center justify-center cursor-pointer select-none"
         @click="handleLogoClick()">
        <img src="{{ asset('logo.png') }}" alt="Toshkent Tibbiyot Akademiyasi Termiz Filiali"
             class="w-72 h-72 object-contain drop-shadow-2xl">
    </div>

</div>

</body>
</html>
