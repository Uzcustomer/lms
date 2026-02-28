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

    <!-- Alpine.js + Collapse plugin -->
    <script src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js" defer></script>
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
        <header class="bg-white dark:bg-gray-800 shadow" style="margin-bottom:15px;">
            <div class="max-w-7xl mx-auto py-2 px-4 sm:px-6 lg:px-8">
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

    <!-- Mobile Bottom Navigation Bar -->
    @php
        $activeTab = 'none';
        if (request()->routeIs('student.subjects') || request()->routeIs('student.subject.*')) $activeTab = 'fanlar';
        elseif (request()->routeIs('student.schedule')) $activeTab = 'jadval';
        elseif (request()->routeIs('student.dashboard')) $activeTab = 'asosiy';
        elseif (request()->routeIs('student.independents')) $activeTab = 'mt';
        elseif (request()->routeIs('student.absence-excuses.*') || request()->routeIs('student.attendance') || request()->routeIs('student.pending-lessons')) $activeTab = 'foydali';
    @endphp
    <div x-data="{ boshqalarOpen: false }" class="sm:hidden" style="position:fixed !important;bottom:0 !important;left:0 !important;right:0 !important;z-index:9999 !important;">
        <!-- Boshqalar popup overlay -->
        <div x-show="boshqalarOpen" @click="boshqalarOpen = false" style="position:fixed;inset:0;z-index:9998;background:rgba(0,0,0,0.3);display:none;"></div>

        <!-- Boshqalar popup menu -->
        <div x-show="boshqalarOpen" @click.away="boshqalarOpen = false" style="position:absolute;bottom:100%;margin-bottom:0.5rem;left:1rem;right:1rem;z-index:9999;display:none;" class="mx-auto max-w-sm bg-white dark:bg-gray-800 rounded-2xl shadow-xl border border-gray-200 dark:border-gray-700 p-4">
            <div class="grid grid-cols-3 gap-3">
                <a href="{{ route('student.absence-excuses.index') }}" class="flex flex-col items-center gap-2 px-2 py-3 rounded-xl transition {{ request()->routeIs('student.absence-excuses.*') ? 'bg-indigo-50 dark:bg-indigo-900/30' : 'hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                    <div class="w-14 h-14 rounded-2xl bg-orange-100 dark:bg-orange-900/40 flex items-center justify-center">
                        <svg class="w-7 h-7 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                        </svg>
                    </div>
                    <span class="text-xs font-medium text-gray-700 dark:text-gray-300 text-center leading-tight">Sababli ariza</span>
                </a>
                <a href="{{ route('student.attendance') }}" class="flex flex-col items-center gap-2 px-2 py-3 rounded-xl transition {{ request()->routeIs('student.attendance') ? 'bg-indigo-50 dark:bg-indigo-900/30' : 'hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                    <div class="w-14 h-14 rounded-2xl bg-green-100 dark:bg-green-900/40 flex items-center justify-center">
                        <svg class="w-7 h-7 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <span class="text-xs font-medium text-gray-700 dark:text-gray-300 text-center leading-tight">Davomat</span>
                </a>
                <a href="{{ route('student.pending-lessons') }}" class="flex flex-col items-center gap-2 px-2 py-3 rounded-xl transition {{ request()->routeIs('student.pending-lessons') ? 'bg-indigo-50 dark:bg-indigo-900/30' : 'hover:bg-gray-100 dark:hover:bg-gray-700' }}">
                    <div class="w-14 h-14 rounded-2xl bg-red-100 dark:bg-red-900/40 flex items-center justify-center">
                        <svg class="w-7 h-7 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182M2.985 19.644l3.181-3.182" />
                        </svg>
                    </div>
                    <span class="text-xs font-medium text-gray-700 dark:text-gray-300 text-center leading-tight">Qayta topshirish</span>
                </a>
            </div>
        </div>

        <!-- Bottom Navigation Tabs -->
        <div class="flex items-center justify-between" style="background-color:#23417b;height:60px;padding:0 15px;padding-bottom:max(5px, env(safe-area-inset-bottom));">
            <!-- 1. Fanlar -->
            <a href="{{ route('student.subjects') }}" class="flex flex-col items-center justify-center" style="width:50px;gap:3px;">
                @if($activeTab === 'fanlar')
                    <div class="rounded-full flex items-center justify-center bg-white" style="width:40px;height:40px;">
                        <svg class="w-5 h-5" fill="none" stroke="#23417b" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                        </svg>
                    </div>
                @else
                    <svg class="w-5 h-5" fill="none" stroke="white" stroke-width="1.8" viewBox="0 0 24 24" style="opacity:0.7;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25" />
                    </svg>
                    <span class="text-[10px] font-medium leading-tight" style="color:rgba(255,255,255,0.7);">Fanlar</span>
                @endif
            </a>

            <!-- 2. Dars jadvali -->
            <a href="{{ route('student.schedule') }}" class="flex flex-col items-center justify-center" style="width:50px;gap:3px;">
                @if($activeTab === 'jadval')
                    <div class="rounded-full flex items-center justify-center bg-white" style="width:40px;height:40px;">
                        <svg class="w-5 h-5" fill="none" stroke="#23417b" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                        </svg>
                    </div>
                @else
                    <svg class="w-5 h-5" fill="none" stroke="white" stroke-width="1.8" viewBox="0 0 24 24" style="opacity:0.7;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                    </svg>
                    <span class="text-[10px] font-medium leading-tight" style="color:rgba(255,255,255,0.7);">Jadval</span>
                @endif
            </a>

            <!-- 3. Asosiy -->
            <a href="{{ route('student.dashboard') }}" class="flex flex-col items-center justify-center" style="width:50px;gap:3px;">
                @if($activeTab === 'asosiy')
                    <div class="rounded-full flex items-center justify-center bg-white" style="width:40px;height:40px;">
                        <svg class="w-5 h-5" fill="none" stroke="#23417b" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                        </svg>
                    </div>
                @else
                    <svg class="w-5 h-5" fill="none" stroke="white" stroke-width="1.8" viewBox="0 0 24 24" style="opacity:0.7;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z" />
                    </svg>
                    <span class="text-[10px] font-medium leading-tight" style="color:rgba(255,255,255,0.7);">Asosiy</span>
                @endif
            </a>

            <!-- 4. MT (Mustaqil ta'lim) -->
            <a href="{{ route('student.independents') }}" class="flex flex-col items-center justify-center" style="width:50px;gap:3px;">
                @if($activeTab === 'mt')
                    <div class="rounded-full flex items-center justify-center bg-white" style="width:40px;height:40px;">
                        <svg class="w-5 h-5" fill="none" stroke="#23417b" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.636 50.636 0 00-2.658-.813A59.906 59.906 0 0112 3.493a59.903 59.903 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5" />
                        </svg>
                    </div>
                @else
                    <svg class="w-5 h-5" fill="none" stroke="white" stroke-width="1.8" viewBox="0 0 24 24" style="opacity:0.7;">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.636 50.636 0 00-2.658-.813A59.906 59.906 0 0112 3.493a59.903 59.903 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5" />
                    </svg>
                    <span class="text-[10px] font-medium leading-tight" style="color:rgba(255,255,255,0.7);">MT</span>
                @endif
            </a>

            <!-- 5. Boshqalar (radial fan trigger) -->
            <button @click="boshqalarOpen = !boshqalarOpen" class="flex flex-col items-center justify-center" style="width:50px;gap:3px;background:none;border:none;">
                @if($activeTab === 'foydali')
                    <div class="rounded-full flex items-center justify-center bg-white" style="width:40px;height:40px;">
                        <svg class="w-5 h-5" fill="none" stroke="#23417b" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12" />
                        </svg>
                    </div>
                @else
                    <svg class="w-5 h-5 transition-transform duration-300" fill="none" stroke="white" stroke-width="1.8" viewBox="0 0 24 24"
                         style="opacity:0.7;" :style="boshqalarOpen ? 'transform:rotate(45deg);opacity:1' : ''">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12" />
                    </svg>
                    <span class="text-[10px] font-medium leading-tight" style="color:rgba(255,255,255,0.7);">Boshqa</span>
                @endif
            </button>
        </div>
    </div>

    @stack('scripts')

    @if(config('app.debug'))
    {{-- DEBUG: Console log - faqat debug rejimda --}}
    <script>
        console.group('%c LMS DEBUG: Student Layout', 'color: #3498db; font-weight: bold;');
        console.log('URL:', window.location.href);
        console.groupEnd();
    </script>
    @endif
</body>
</html>
