<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans text-gray-900 antialiased">
<div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100 dark:bg-gray-900"
     x-data="{ selectedProfile: null }">
    <div>
        <a href="/">
            <x-application-logo class="w-20 h-20 fill-current text-gray-500" />
        </a>
    </div>

    <!-- Profile Type Selection -->
    <div class="flex flex-row gap-5 w-full sm:max-w-lg mt-6 px-6 py-4 overflow-hidden">
        <button @click="selectedProfile = selectedProfile === 'student' ? null : 'student'"
                :class="selectedProfile === 'student' ? 'ring-2 ring-blue-500 bg-blue-50 dark:bg-gray-700' : 'bg-white dark:bg-gray-800'"
                class="py-4 w-full flex flex-col items-center gap-2 shadow-sm rounded-lg transition-all duration-200 hover:shadow-md cursor-pointer">
            <img src="{{ asset('/image/student.webp') }}" alt="Student" class="size-20">
            <div class="text-lg font-medium" :class="selectedProfile === 'student' ? 'text-blue-600 dark:text-blue-400' : 'text-gray-800 dark:text-gray-200'">Student</div>
        </button>
        <button @click="selectedProfile = selectedProfile === 'teacher' ? null : 'teacher'"
                :class="selectedProfile === 'teacher' ? 'ring-2 ring-green-500 bg-green-50 dark:bg-gray-700' : 'bg-white dark:bg-gray-800'"
                class="py-4 w-full flex flex-col items-center gap-2 shadow-sm rounded-lg transition-all duration-200 hover:shadow-md cursor-pointer">
            <img src="{{ asset('/image/teacher.webp') }}" alt="Teacher" class="size-20">
            <div class="text-lg font-medium" :class="selectedProfile === 'teacher' ? 'text-green-600 dark:text-green-400' : 'text-gray-800 dark:text-gray-200'">Teacher</div>
        </button>
        <button @click="selectedProfile = selectedProfile === 'admin' ? null : 'admin'"
                :class="selectedProfile === 'admin' ? 'ring-2 ring-purple-500 bg-purple-50 dark:bg-gray-700' : 'bg-white dark:bg-gray-800'"
                class="py-4 w-full flex flex-col items-center gap-2 shadow-sm rounded-lg transition-all duration-200 hover:shadow-md cursor-pointer">
            <img src="{{ asset('/image/admin.webp') }}" alt="Admin" class="size-20">
            <div class="text-lg font-medium" :class="selectedProfile === 'admin' ? 'text-purple-600 dark:text-purple-400' : 'text-gray-800 dark:text-gray-200'">Admin</div>
        </button>
    </div>

    <!-- Login Forms -->
    <div x-show="selectedProfile" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 transform -translate-y-4" x-transition:enter-end="opacity-100 transform translate-y-0" class="w-full sm:max-w-md mt-4 px-6 py-6 bg-white dark:bg-gray-800 shadow-md overflow-visible sm:rounded-lg">

        <!-- Student Login Form -->
        <div x-show="selectedProfile === 'student'" x-transition>
            <x-auth-session-status class="mb-4" :status="session('status')" />

            <form method="POST" action="{{ route('student.login.post') }}">
                @csrf
                <div>
                    <x-input-label for="student_login" :value="__('Login')" />
                    <x-text-input id="student_login" class="block mt-1 w-full" type="text" name="login" :value="old('login')" required autofocus autocomplete="login" />
                    <x-input-error :messages="$errors->get('login')" class="mt-2" />
                </div>

                <div class="mt-4">
                    <x-input-label for="student_password" :value="__('Parol')" />
                    <x-text-input id="student_password" class="block mt-1 w-full" type="password" name="password" required autocomplete="current-password" />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <div class="block mt-4">
                    <label for="student_remember" class="inline-flex items-center">
                        <input id="student_remember" type="checkbox" class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800" name="remember">
                        <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">{{ __('Eslab qolish') }}</span>
                    </label>
                </div>

                <div class="flex items-center justify-end mt-4">
                    <x-primary-button class="ms-3">
                        {{ __('Kirish') }}
                    </x-primary-button>
                </div>
            </form>

            <div class="mt-4 p-3 rounded-lg" style="background-color: #eff6ff; border: 1px solid #bfdbfe;">
                <p class="text-xs" style="color: #1e40af;">
                    HEMIS login va parol bilan kiring. Agar HEMIS parolingiz esdan chiqqan bo'lsa, admin yoki dekanatga murojaat qiling — vaqtinchalik parol beriladi.
                </p>
            </div>
        </div>

        <!-- Teacher Login Form -->
        <div x-show="selectedProfile === 'teacher'" x-transition>
            <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                {{ __("O'qituvchilar uchun Login") }}
            </div>

            <x-auth-session-status class="mb-4" :status="session('status')" />

            <form method="POST" action="{{ route('teacher.login.post') }}">
                @csrf
                <div>
                    <x-input-label for="teacher_login" :value="__('Login')" />
                    <x-text-input id="teacher_login" class="block mt-1 w-full" type="text" name="login" :value="old('login')" required autofocus autocomplete="login" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div class="mt-4">
                    <x-input-label for="teacher_password" :value="__('Parol')" />
                    <x-text-input id="teacher_password" class="block mt-1 w-full" type="password" name="password" required autocomplete="current-password" />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <div class="block mt-4">
                    <label for="teacher_remember" class="inline-flex items-center">
                        <input id="teacher_remember" type="checkbox" class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800" name="remember">
                        <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">{{ __('Eslab qolish') }}</span>
                    </label>
                </div>

                <div class="flex items-center justify-end mt-4">
                    <x-primary-button class="ms-3">
                        {{ __('Kirish') }}
                    </x-primary-button>
                </div>
            </form>
        </div>

        <!-- Admin Login Form -->
        <div x-show="selectedProfile === 'admin'" x-transition>
            <div class="mb-4 text-sm text-gray-600 dark:text-gray-400">
                {{ __("Examinerlar uchun Login") }}
            </div>

            <x-auth-session-status class="mb-4" :status="session('status')" />

            <form method="POST" action="{{ route('admin.login.post') }}">
                @csrf
                <div>
                    <x-input-label for="admin_email" :value="__('Login')" />
                    <x-text-input id="admin_email" class="block mt-1 w-full" type="text" name="email" :value="old('email')" required autofocus autocomplete="username" />
                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                </div>

                <div class="mt-4">
                    <x-input-label for="admin_password" :value="__('Parol')" />
                    <x-text-input id="admin_password" class="block mt-1 w-full" type="password" name="password" required autocomplete="current-password" />
                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                </div>

                <div class="block mt-4">
                    <label for="admin_remember" class="inline-flex items-center">
                        <input id="admin_remember" type="checkbox" class="rounded dark:bg-gray-900 border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600 dark:focus:ring-offset-gray-800" name="remember">
                        <span class="ms-2 text-sm text-gray-600 dark:text-gray-400">{{ __('Eslab qolish') }}</span>
                    </label>
                </div>

                <div class="flex items-center justify-end mt-4">
                    <x-primary-button class="ms-3">
                        {{ __('Kirish') }}
                    </x-primary-button>
                </div>
            </form>
        </div>
    </div>
</div>
</body>
</html>
