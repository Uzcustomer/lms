<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>TDTU Termiz filiali mark platformasi</title>

    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">
    <link rel="shortcut icon" href="{{ asset('favicon.ico') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Alpine.js (student layoutda Livewire yuklanmaydi, shuning uchun alohida kerak) -->
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

    <!-- JSZip for client-side file compression -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
</head>
<body class="font-sans antialiased">
<div class="min-h-screen bg-gray-100 dark:bg-gray-900">
    @include('layouts.student-navigation')

    <!-- Page Heading -->
    @if (isset($header))
        <header class="bg-white dark:bg-gray-800 shadow">
            <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                {{ $header }}
            </div>
        </header>
    @endif

    {{-- Telegram tasdiqlash ogohlantirishi --}}
    @auth('student')
        @php
            $authStudent = auth()->guard('student')->user();
        @endphp
        @if($authStudent && $authStudent->phone && !$authStudent->isTelegramVerified())
            @php
                $daysLeft = $authStudent->telegramDaysLeft();
            @endphp
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-3">
                <div class="flex items-center justify-between px-4 py-3 rounded-lg border
                    {{ $daysLeft <= 2 ? 'bg-red-50 border-red-200' : ($daysLeft <= 4 ? 'bg-yellow-50 border-yellow-200' : 'bg-blue-50 border-blue-200') }}">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-2 flex-shrink-0 {{ $daysLeft <= 2 ? 'text-red-500' : ($daysLeft <= 4 ? 'text-yellow-500' : 'text-blue-500') }}" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                        </svg>
                        <div>
                            <p class="text-sm font-medium {{ $daysLeft <= 2 ? 'text-red-800' : ($daysLeft <= 4 ? 'text-yellow-800' : 'text-blue-800') }}">
                                Telegram hisobingizni tasdiqlang!
                                @if($daysLeft > 0)
                                    <span class="font-bold">{{ $daysLeft }} kun</span> qoldi.
                                @else
                                    <span class="font-bold">Muhlat tugadi!</span>
                                @endif
                            </p>
                            <p class="text-xs {{ $daysLeft <= 2 ? 'text-red-600' : ($daysLeft <= 4 ? 'text-yellow-600' : 'text-blue-600') }}">
                                @if($daysLeft <= 0)
                                    Telegram tasdiqlanmaguncha tizimdan foydalanish cheklanadi.
                                @else
                                    Muhlat tugagandan so'ng tizimga kirish cheklanadi.
                                @endif
                            </p>
                        </div>
                    </div>
                    <a href="{{ route('student.complete-profile') }}"
                       class="ml-3 inline-flex items-center px-3 py-1.5 text-xs font-medium rounded-md transition flex-shrink-0
                       {{ $daysLeft <= 2 ? 'bg-red-600 text-white hover:bg-red-700' : ($daysLeft <= 4 ? 'bg-yellow-600 text-white hover:bg-yellow-700' : 'bg-blue-600 text-white hover:bg-blue-700') }}">
                        <svg class="w-3.5 h-3.5 mr-1" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                        </svg>
                        Tasdiqlash
                    </a>
                </div>
            </div>
        @endif
    @endauth

    {{-- Impersonatsiya banneri --}}
    @if(session('impersonating'))
        <div class="bg-red-600 text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-2 flex items-center justify-between">
                <span class="text-sm font-medium">
                    Siz hozir <strong>{{ session('impersonated_name') }}</strong> sifatida kirgansiz (Superadmin rejimi)
                </span>
                <form action="{{ route('impersonate.stop') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit" class="ml-4 px-3 py-1 bg-white text-red-600 text-xs font-bold rounded hover:bg-red-50 transition">
                        Orqaga qaytish
                    </button>
                </form>
            </div>
        </div>
    @endif

    <!-- Page Content -->
    <main>
        {{ $slot }}
    </main>
</div>

    {{-- DEBUG: Console log - account switching debug --}}
    <script>
        console.group('%c🔍 LMS DEBUG: Student Layout', 'color: #3498db; font-weight: bold; font-size: 14px;');
        console.log('%cLayout:', 'font-weight:bold', 'student-app.blade.php (Student)');
        console.log('%cURL:', 'font-weight:bold', window.location.href);
        console.log('%cGuard (server):', 'font-weight:bold', '{{ auth()->guard("web")->check() ? "web ✅ (id=" . auth()->guard("web")->id() . ")" : "web ❌" }}');
        console.log('%cTeacher guard:', 'font-weight:bold', '{{ auth()->guard("teacher")->check() ? "teacher ✅ (id=" . auth()->guard("teacher")->id() . ")" : "teacher ❌" }}');
        console.log('%cStudent guard:', 'font-weight:bold', '{{ auth()->guard("student")->check() ? "student ✅ (id=" . auth()->guard("student")->id() . " " . (auth()->guard("student")->user()->full_name ?? "?") . ")" : "student ❌" }}');
        console.log('%cauth()->user():', 'font-weight:bold', '{{ auth()->user() ? "id=" . auth()->user()->id : "NULL" }}');
        console.log('%csession.impersonating:', 'font-weight:bold', {{ session('impersonating') ? 'true' : 'false' }});
        console.log('%csession.impersonated_name:', 'font-weight:bold', '{{ session("impersonated_name", "NULL") }}');
        console.log('%csession.impersonator_id:', 'font-weight:bold', '{{ session("impersonator_id", "NULL") }}');
        console.log('%csession.active_role:', 'font-weight:bold', '{{ session("active_role", "NULL") }}');
        console.log('%csession_id:', 'font-weight:bold', '{{ session()->getId() }}');
        @if(session('impersonating'))
            console.log('%c📍 Impersonation mode AKTIV — banner ko\'rinmoqda', 'color: orange; font-weight: bold;');
        @endif
        console.groupEnd();
    </script>
</body>
</html>
