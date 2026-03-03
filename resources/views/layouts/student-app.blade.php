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

    @stack('styles')
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
    <main class="pb-20 sm:pb-0">
        {{ $slot }}
    </main>
</div>

<!-- Mobile Bottom Navigation -->
<nav class="fixed bottom-0 left-0 right-0 z-50 sm:hidden" style="background-color: #0f3487;">
    <div class="flex justify-around items-center h-16">
        <a href="{{ route('student.dashboard') }}" class="flex flex-col items-center justify-center w-full h-full {{ request()->routeIs('student.dashboard') ? 'opacity-100' : 'opacity-70' }}">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-4 0a1 1 0 01-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 01-1 1h-2z"/>
            </svg>
            <span class="text-[10px] mt-1 text-white">Bosh sahifa</span>
        </a>
        <a href="{{ route('student.schedule') }}" class="flex flex-col items-center justify-center w-full h-full {{ request()->routeIs('student.schedule') ? 'opacity-100' : 'opacity-70' }}">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <span class="text-[10px] mt-1 text-white">Jadval</span>
        </a>
        <a href="{{ route('student.attendance') }}" class="flex flex-col items-center justify-center w-full h-full {{ request()->routeIs('student.attendance') ? 'opacity-100' : 'opacity-70' }}">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
            </svg>
            <span class="text-[10px] mt-1 text-white">Davomat</span>
        </a>
        <a href="{{ route('student.subjects') }}" class="flex flex-col items-center justify-center w-full h-full {{ request()->routeIs('student.subjects') ? 'opacity-100' : 'opacity-70' }}">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
            </svg>
            <span class="text-[10px] mt-1 text-white">Fanlar</span>
        </a>
        <a href="{{ route('student.independents') }}" class="flex flex-col items-center justify-center w-full h-full {{ request()->routeIs('student.independents') ? 'opacity-100' : 'opacity-70' }}">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
            <span class="text-[10px] mt-1 text-white">MT</span>
        </a>
        <a href="{{ route('student.profile') }}" class="flex flex-col items-center justify-center w-full h-full {{ request()->routeIs('student.profile') ? 'opacity-100' : 'opacity-70' }}">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
            <span class="text-[10px] mt-1 text-white">Profil</span>
        </a>
    </div>
</nav>

    @stack('scripts')

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
