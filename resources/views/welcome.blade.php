<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans text-gray-900 antialiased bg-gray-100 dark:bg-gray-900">

<div class="min-h-screen flex items-center justify-center px-4 py-8"
     x-data="{
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

    <div class="w-full max-w-4xl bg-white dark:bg-gray-800 rounded-2xl shadow-lg overflow-hidden">
        <div class="flex flex-col md:flex-row">

            <!-- Left Side: Title + Login Form -->
            <div class="flex-1 p-8 md:p-12">
                <!-- University Title -->
                <div class="mb-8">
                    <h1 class="text-2xl md:text-3xl font-bold text-[#1a3a8a] dark:text-blue-400 leading-tight">
                        Toshkent tibbiyot akademiyasi
                    </h1>
                    <h2 class="text-xl md:text-2xl font-semibold text-[#1a3a8a] dark:text-blue-400 mt-1">
                        Termiz filiali
                    </h2>
                    <p class="text-gray-500 dark:text-gray-400 mt-2 text-sm">Jurnal tizimi</p>
                </div>

                <!-- Profile Type Tabs (Student / Xodim) -->
                <div class="flex mb-6 bg-gray-100 dark:bg-gray-700 rounded-lg p-1">
                    <button @click="selectedProfile = 'student'"
                            :class="selectedProfile === 'student'
                                ? 'bg-white dark:bg-gray-600 text-[#1a3a8a] dark:text-blue-400 shadow-sm'
                                : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
                            class="flex-1 py-2.5 px-4 rounded-md text-sm font-medium transition-all duration-200 cursor-pointer">
                        Student
                    </button>
                    <button @click="selectedProfile = 'teacher'"
                            :class="selectedProfile === 'teacher'
                                ? 'bg-white dark:bg-gray-600 text-[#1a3a8a] dark:text-blue-400 shadow-sm'
                                : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
                            class="flex-1 py-2.5 px-4 rounded-md text-sm font-medium transition-all duration-200 cursor-pointer">
                        Xodim
                    </button>
                    <!-- Hidden Admin Tab -->
                    <button x-show="showAdmin" x-transition
                            @click="selectedProfile = 'admin'"
                            :class="selectedProfile === 'admin'
                                ? 'bg-white dark:bg-gray-600 text-[#1a3a8a] dark:text-blue-400 shadow-sm'
                                : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300'"
                            class="flex-1 py-2.5 px-4 rounded-md text-sm font-medium transition-all duration-200 cursor-pointer">
                        Admin
                    </button>
                </div>

                <!-- Session Status -->
                <x-auth-session-status class="mb-4" :status="session('status')" />

                @if($errors->any())
                    <div class="mb-4 p-3 rounded-lg bg-red-50 border border-red-200">
                        @foreach($errors->all() as $error)
                            <p class="text-sm text-red-600">{{ $error }}</p>
                        @endforeach
                    </div>
                @endif

                <!-- Student Login Form -->
                <form x-show="selectedProfile === 'student'" x-transition method="POST" action="{{ route('student.login.post') }}">
                    @csrf
                    <input type="hidden" name="_profile" value="student">

                    <div class="mb-4">
                        <label for="student_login" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Login</label>
                        <input id="student_login" type="text" name="login" value="{{ old('login') }}"
                               class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#1a3a8a] focus:border-transparent dark:bg-gray-700 dark:text-white transition"
                               required autocomplete="login" placeholder="HEMIS login">
                    </div>

                    <div class="mb-4">
                        <label for="student_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Parol</label>
                        <div class="relative" x-data="{ showPass: false }">
                            <input id="student_password" :type="showPass ? 'text' : 'password'" name="password"
                                   class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#1a3a8a] focus:border-transparent dark:bg-gray-700 dark:text-white transition pr-12"
                                   required autocomplete="current-password" placeholder="Parolni kiriting">
                            <button type="button" @click="showPass = !showPass" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-500 hover:text-gray-700 dark:text-gray-400">
                                <svg x-show="!showPass" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <svg x-show="showPass" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit"
                            class="w-full py-3 px-4 bg-[#1a3a8a] hover:bg-[#152e6e] text-white font-medium rounded-lg transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#1a3a8a]">
                        Kirish
                    </button>

                    <div class="mt-4 p-3 rounded-lg bg-blue-50 dark:bg-gray-700 border border-blue-200 dark:border-gray-600">
                        <p class="text-xs text-blue-800 dark:text-blue-300">
                            HEMIS login va parol bilan kiring. Agar HEMIS parolingiz esdan chiqqan bo'lsa, admin yoki dekanatga murojaat qiling — vaqtinchalik parol beriladi.
                        </p>
                    </div>
                </form>

                <!-- Teacher/Xodim Login Form -->
                <form x-show="selectedProfile === 'teacher'" x-transition method="POST" action="{{ route('teacher.login.post') }}">
                    @csrf
                    <input type="hidden" name="_profile" value="teacher">

                    <div class="mb-4">
                        <label for="teacher_login" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Login</label>
                        <input id="teacher_login" type="text" name="login" value="{{ old('login') }}"
                               class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#1a3a8a] focus:border-transparent dark:bg-gray-700 dark:text-white transition"
                               required autocomplete="login" placeholder="Login kiriting">
                    </div>

                    <div class="mb-4">
                        <label for="teacher_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Parol</label>
                        <div class="relative" x-data="{ showPass: false }">
                            <input id="teacher_password" :type="showPass ? 'text' : 'password'" name="password"
                                   class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#1a3a8a] focus:border-transparent dark:bg-gray-700 dark:text-white transition pr-12"
                                   required autocomplete="current-password" placeholder="Parolni kiriting">
                            <button type="button" @click="showPass = !showPass" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-500 hover:text-gray-700 dark:text-gray-400">
                                <svg x-show="!showPass" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <svg x-show="showPass" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit"
                            class="w-full py-3 px-4 bg-[#1a3a8a] hover:bg-[#152e6e] text-white font-medium rounded-lg transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#1a3a8a]">
                        Kirish
                    </button>
                </form>

                <!-- Admin Login Form (Hidden) -->
                <form x-show="showAdmin && selectedProfile === 'admin'" x-transition method="POST" action="{{ route('admin.login.post') }}">
                    @csrf
                    <input type="hidden" name="_profile" value="admin">

                    <div class="mb-4">
                        <label for="admin_email" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Login</label>
                        <input id="admin_email" type="text" name="email" value="{{ old('email') }}"
                               class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#1a3a8a] focus:border-transparent dark:bg-gray-700 dark:text-white transition"
                               required autocomplete="username" placeholder="Admin login">
                    </div>

                    <div class="mb-4">
                        <label for="admin_password" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Parol</label>
                        <div class="relative" x-data="{ showPass: false }">
                            <input id="admin_password" :type="showPass ? 'text' : 'password'" name="password"
                                   class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-[#1a3a8a] focus:border-transparent dark:bg-gray-700 dark:text-white transition pr-12"
                                   required autocomplete="current-password" placeholder="Parolni kiriting">
                            <button type="button" @click="showPass = !showPass" class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-500 hover:text-gray-700 dark:text-gray-400">
                                <svg x-show="!showPass" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                </svg>
                                <svg x-show="showPass" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 10-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    <button type="submit"
                            class="w-full py-3 px-4 bg-[#1a3a8a] hover:bg-[#152e6e] text-white font-medium rounded-lg transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-[#1a3a8a]">
                        Kirish
                    </button>
                </form>
            </div>

            <!-- Right Side: Logo -->
            <div class="hidden md:flex flex-1 items-center justify-center bg-gradient-to-br from-[#1a3a8a] to-[#152e6e] p-12">
                <img src="{{ asset('logo.png') }}" alt="Toshkent Tibbiyot Akademiyasi Termiz Filiali"
                     class="w-64 h-64 object-contain drop-shadow-2xl cursor-pointer select-none"
                     @click="handleLogoClick()">
            </div>

            <!-- Mobile Logo (shown on small screens) -->
            <div class="md:hidden flex justify-center py-6 bg-gradient-to-br from-[#1a3a8a] to-[#152e6e]">
                <img src="{{ asset('logo.png') }}" alt="Toshkent Tibbiyot Akademiyasi Termiz Filiali"
                     class="w-32 h-32 object-contain drop-shadow-2xl cursor-pointer select-none"
                     @click="handleLogoClick()">
            </div>

        </div>
    </div>
</div>

</body>
</html>
