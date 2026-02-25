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
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        <link href="https://cdn.datatables.net/1.10.21/css/jquery.dataTables.min.css" rel="stylesheet">

        {{-- Alpine.js Vite bundle orqali app.js da yuklanadi, CDN kerak emas --}}

        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
        @stack('styles')
    </head>
    <body class="font-sans antialiased">
        <div class="flex min-h-screen bg-gray-100 dark:bg-gray-900">
            <!-- Sidebar -->
            <x-admin-sidebar-menu />

            <!-- Main Content -->
            <div class="flex-1 overflow-x-hidden overflow-y-auto sidebar-main-content">
                <!-- HEMIS-style Top Header Bar -->
                @php
                    $currentLocale = app()->getLocale();
                    $locales = ['uz' => "O'zbekcha", 'ru' => 'Русский', 'en' => 'English'];
                    $headerUser = auth()->user();
                    $headerUserName = $headerUser->name ?? ($headerUser->full_name ?? ($headerUser->short_name ?? __('notifications.user')));

                    // Unread notification count
                    $headerUserId = $headerUser->id ?? 0;
                    $headerUserType = get_class($headerUser);
                    $headerUnreadCount = \App\Models\Notification::where('recipient_id', $headerUserId)
                        ->where('recipient_type', $headerUserType)
                        ->where('is_draft', false)
                        ->where('is_read', false)
                        ->count();
                @endphp
                <div class="top-header-bar" x-data="{ notifOpen: false, langOpen: false, profileOpen: false }" @click.outside="notifOpen = false; langOpen = false; profileOpen = false">
                    <!-- Left: Mobile hamburger + breadcrumb -->
                    <div class="top-header-left">
                        <button @click="$store.sidebar.toggle()" class="hamburger-btn-header" x-data>
                            <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                            </svg>
                        </button>
                        <span class="top-header-title">{{ config('app.name', 'LMS') }}</span>
                    </div>

                    <!-- Right: Language, Notifications, Profile -->
                    <div class="top-header-right">
                        <!-- Language Switcher -->
                        <div class="top-header-item" style="position: relative;">
                            <button @click="langOpen = !langOpen; notifOpen = false; profileOpen = false" class="top-header-btn" title="{{ __('notifications.language') }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span class="top-header-lang-label">{{ strtoupper($currentLocale) }}</span>
                            </button>
                            <!-- Language Dropdown -->
                            <div x-show="langOpen" x-transition
                                 class="top-header-dropdown" style="right: 0; min-width: 160px;">
                                @foreach($locales as $code => $label)
                                <a href="{{ route('language.switch', $code) }}"
                                   class="top-header-dropdown-item {{ $currentLocale === $code ? 'active' : '' }}">
                                    <span class="font-medium">{{ strtoupper($code) }}</span>
                                    <span class="ml-2 text-sm opacity-75">{{ $label }}</span>
                                    @if($currentLocale === $code)
                                    <svg class="w-4 h-4 ml-auto text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    @endif
                                </a>
                                @endforeach
                            </div>
                        </div>

                        <!-- Notifications Bell -->
                        <div class="top-header-item" style="position: relative;">
                            <button @click="notifOpen = !notifOpen; langOpen = false; profileOpen = false" class="top-header-btn" title="{{ __('notifications.notifications') }}">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                                </svg>
                                @if($headerUnreadCount > 0)
                                <span class="notif-badge">{{ $headerUnreadCount > 99 ? '99+' : $headerUnreadCount }}</span>
                                @endif
                            </button>
                            <!-- Notifications Dropdown -->
                            <div x-show="notifOpen" x-transition
                                 class="top-header-dropdown notif-dropdown" style="right: 0; width: 340px;">
                                <div class="notif-dropdown-header">
                                    <span class="font-semibold">{{ __('notifications.notifications') }}</span>
                                    @if($headerUnreadCount > 0)
                                    <form method="POST" action="{{ route('admin.notifications.mark-all-read') }}" class="inline">
                                        @csrf
                                        <button type="submit" class="text-xs text-blue-600 hover:text-blue-800">{{ __('notifications.mark_all_read') }}</button>
                                    </form>
                                    @endif
                                </div>
                                <div class="notif-dropdown-body">
                                    @php
                                        $recentNotifs = \App\Models\Notification::where('recipient_id', $headerUserId)
                                            ->where('recipient_type', $headerUserType)
                                            ->where('is_draft', false)
                                            ->orderByDesc('sent_at')
                                            ->take(5)
                                            ->get();
                                    @endphp
                                    @forelse($recentNotifs as $notif)
                                    <a href="{{ route('admin.notifications.show', $notif) }}" class="notif-dropdown-item {{ !$notif->is_read ? 'unread' : '' }}">
                                        <div class="flex items-start">
                                            <div class="notif-dot {{ !$notif->is_read ? 'bg-blue-500' : 'bg-transparent' }}"></div>
                                            <div class="flex-1 min-w-0">
                                                <p class="text-sm font-medium text-gray-900 truncate">{{ $notif->subject }}</p>
                                                <p class="text-xs text-gray-500 mt-1">{{ $notif->sent_at ? $notif->sent_at->diffForHumans() : '' }}</p>
                                            </div>
                                        </div>
                                    </a>
                                    @empty
                                    <div class="px-4 py-6 text-center text-sm text-gray-500">
                                        {{ __('notifications.no_notifications') }}
                                    </div>
                                    @endforelse
                                </div>
                                <a href="{{ route('admin.notifications.index') }}" class="notif-dropdown-footer">
                                    {{ __('notifications.view_all') }}
                                </a>
                            </div>
                        </div>

                        <!-- Profile -->
                        <div class="top-header-item" style="position: relative;">
                            <button @click="profileOpen = !profileOpen; langOpen = false; notifOpen = false" class="top-header-btn top-header-profile-btn">
                                <div class="top-header-avatar">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                </div>
                                <span class="top-header-username">{{ $headerUserName }}</span>
                                <svg class="w-4 h-4 transition-transform" :class="{'rotate-180': profileOpen}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <!-- Profile Dropdown -->
                            <div x-show="profileOpen" x-transition
                                 class="top-header-dropdown" style="right: 0; min-width: 200px;">
                                <div class="px-4 py-3 border-b border-gray-100">
                                    <p class="text-sm font-medium text-gray-900">{{ $headerUserName }}</p>
                                    <p class="text-xs text-gray-500">{{ $headerUser->email ?? '' }}</p>
                                </div>
                                <a href="{{ route('admin.notifications.index') }}" class="top-header-dropdown-item">
                                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                                    </svg>
                                    {{ __('notifications.notifications') }}
                                    @if($headerUnreadCount > 0)
                                    <span class="ml-auto bg-red-500 text-white text-xs rounded-full px-1.5 py-0.5">{{ $headerUnreadCount }}</span>
                                    @endif
                                </a>
                                <form method="POST" action="{{ route('admin.logout') }}">
                                    @csrf
                                    <button type="submit" class="top-header-dropdown-item w-full text-left text-red-600 hover:text-red-700">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                        </svg>
                                        {{ __('notifications.logout') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Page Heading -->
                @isset($header)
                    <header class="bg-white dark:bg-gray-800 shadow desktop-only-header">
                        <div class="max-w-screen-xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                {{-- Impersonatsiya banneri --}}
                @if(session('impersonating'))
                    <div class="bg-red-600 text-white">
                        <div class="max-w-screen-xl mx-auto px-4 sm:px-6 lg:px-8 py-2 flex items-center justify-between">
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
                <main class="p-3 sm:p-6">
                    {{ $slot }}
                </main>
            </div>
        </div>

        @yield('content')
        @livewireScripts
        @stack('scripts')
    </body>
    <script src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js"></script>

    {{-- DEBUG: Console log - account switching debug --}}
    <script>
        console.group('%c🔍 LMS DEBUG: Admin Layout', 'color: #e74c3c; font-weight: bold; font-size: 14px;');
        console.log('%cLayout:', 'font-weight:bold', 'app.blade.php (Admin)');
        console.log('%cURL:', 'font-weight:bold', window.location.href);
        console.log('%cGuard (server):', 'font-weight:bold', '{{ auth()->guard("web")->check() ? "web ✅ (id=" . auth()->guard("web")->id() . " " . (auth()->guard("web")->user()->name ?? "?") . ")" : "web ❌" }}');
        console.log('%cTeacher guard:', 'font-weight:bold', '{{ auth()->guard("teacher")->check() ? "teacher ✅ (id=" . auth()->guard("teacher")->id() . ")" : "teacher ❌" }}');
        console.log('%cStudent guard:', 'font-weight:bold', '{{ auth()->guard("student")->check() ? "student ✅ (id=" . auth()->guard("student")->id() . ")" : "student ❌" }}');
        console.log('%cauth()->user():', 'font-weight:bold', '{{ auth()->user() ? "id=" . auth()->user()->id . " name=" . auth()->user()->name : "NULL" }}');
        console.log('%csession.impersonating:', 'font-weight:bold', {{ session('impersonating') ? 'true' : 'false' }});
        console.log('%csession.impersonated_name:', 'font-weight:bold', '{{ session("impersonated_name", "NULL") }}');
        console.log('%csession.impersonator_id:', 'font-weight:bold', '{{ session("impersonator_id", "NULL") }}');
        console.log('%csession.active_role:', 'font-weight:bold', '{{ session("active_role", "NULL") }}');
        console.log('%csession_id:', 'font-weight:bold', '{{ session()->getId() }}');
        @if(session('impersonating'))
            console.warn('%c⚠️ IMPERSONATION BANNER KO\'RINMOQDA!', 'color: red; font-size: 16px; font-weight: bold;');
        @else
            console.log('%c✅ Impersonation banner yo\'q (to\'g\'ri)', 'color: green;');
        @endif
        console.groupEnd();
    </script>
</html>
