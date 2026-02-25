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

                    // User role info
                    $headerUserRoles = $headerUser->getRoleNames()->toArray();
                    $headerActiveRole = session('active_role', $headerUserRoles[0] ?? '');
                    $headerRoleLabels = [];
                    foreach (\App\Enums\ProjectRole::cases() as $role) {
                        $headerRoleLabels[$role->value] = $role->label();
                    }
                    $headerActiveRoleLabel = $headerRoleLabels[$headerActiveRole] ?? $headerActiveRole;

                    // Avatar
                    $headerIsTeacher = auth()->guard('teacher')->check();
                    $headerUserAvatar = ($headerIsTeacher && isset($headerUser->image) && $headerUser->image) ? $headerUser->image : null;

                    // Logout route
                    $headerIsImpersonating = session('impersonating', false);
                    $headerAdminRoles = ['superadmin', 'admin', 'kichik_admin'];
                    $headerUseTeacherRoutes = $headerIsTeacher && !in_array($headerActiveRole, $headerAdminRoles);
                    $headerLogoutRoute = $headerIsImpersonating ? route('impersonate.stop') : ($headerUseTeacherRoutes ? route('teacher.logout') : route('admin.logout'));
                    $headerProfileRoute = $headerUseTeacherRoutes ? route('teacher.info-me') : null;
                    $headerSwitchRoleRoute = $headerUseTeacherRoutes ? route('teacher.switch-role') : route('admin.switch-role');

                    // Unread notification count
                    $headerUserId = $headerUser->id ?? 0;
                    $headerUserType = get_class($headerUser);
                    $headerUnreadCount = \App\Models\Notification::where('recipient_id', $headerUserId)
                        ->where('recipient_type', $headerUserType)
                        ->where('is_draft', false)
                        ->where('is_read', false)
                        ->count();
                @endphp
                <div class="top-header-bar" style="display:flex;flex-direction:row;align-items:center;justify-content:space-between;flex-wrap:nowrap;"
                     x-data="{ notifOpen: false, langOpen: false, profileOpen: false }">
                    <!-- Left: Mobile hamburger + title -->
                    <div class="top-header-left" style="display:flex;flex-direction:row;align-items:center;">
                        <button @click="$store.sidebar.toggle()" class="hamburger-btn-header" x-data>
                            <svg style="width:22px;height:22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                            </svg>
                        </button>
                        <span class="top-header-title">{{ config('app.name', 'LMS') }}</span>
                    </div>

                    <!-- Right: Til, Xabarnoma, Profil — gorizontal -->
                    <div class="top-header-right" style="display:flex;flex-direction:row;align-items:center;flex-wrap:nowrap;gap:4px;margin-left:auto;">

                        <!-- 1. Til (Language Switcher) -->
                        <div class="top-header-item" style="display:inline-block;position:relative;">
                            <button @click="langOpen = !langOpen; notifOpen = false; profileOpen = false"
                                    class="top-header-btn" style="display:inline-flex;flex-direction:row;align-items:center;"
                                    title="{{ __('notifications.language') }}">
                                <svg style="width:20px;height:20px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span class="top-header-lang-label">{{ strtoupper($currentLocale) }}</span>
                            </button>
                            <div x-show="langOpen" x-transition @click.outside="langOpen = false"
                                 class="top-header-dropdown" style="right:0;min-width:160px;">
                                @foreach($locales as $code => $label)
                                <a href="{{ route('language.switch', $code) }}"
                                   class="top-header-dropdown-item {{ $currentLocale === $code ? 'active' : '' }}">
                                    <span style="font-weight:600;">{{ strtoupper($code) }}</span>
                                    <span style="margin-left:8px;font-size:0.85rem;opacity:0.75;">{{ $label }}</span>
                                    @if($currentLocale === $code)
                                    <svg style="width:16px;height:16px;margin-left:auto;color:#22c55e;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                                    </svg>
                                    @endif
                                </a>
                                @endforeach
                            </div>
                        </div>

                        <!-- 2. Xabarnomalar (Notifications Bell) -->
                        <div class="top-header-item" style="display:inline-block;position:relative;">
                            <button @click="notifOpen = !notifOpen; langOpen = false; profileOpen = false"
                                    class="top-header-btn" style="display:inline-flex;flex-direction:row;align-items:center;"
                                    title="{{ __('notifications.notifications') }}">
                                <svg style="width:20px;height:20px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                                </svg>
                                @if($headerUnreadCount > 0)
                                <span class="notif-badge">{{ $headerUnreadCount > 99 ? '99+' : $headerUnreadCount }}</span>
                                @endif
                            </button>
                            <div x-show="notifOpen" x-transition @click.outside="notifOpen = false"
                                 class="top-header-dropdown notif-dropdown" style="right:0;width:340px;">
                                <div class="notif-dropdown-header">
                                    <span style="font-weight:600;">{{ __('notifications.notifications') }}</span>
                                    @if($headerUnreadCount > 0)
                                    <form method="POST" action="{{ route('admin.notifications.mark-all-read') }}" style="display:inline;">
                                        @csrf
                                        <button type="submit" style="font-size:0.75rem;color:#2563eb;background:none;border:none;cursor:pointer;">{{ __('notifications.mark_all_read') }}</button>
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
                                        <div style="display:flex;align-items:flex-start;">
                                            <div class="notif-dot" style="background:{{ !$notif->is_read ? '#3b82f6' : 'transparent' }};"></div>
                                            <div style="flex:1;min-width:0;">
                                                <p style="font-size:0.875rem;font-weight:500;color:#111827;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $notif->subject }}</p>
                                                <p style="font-size:0.75rem;color:#6b7280;margin-top:2px;">{{ $notif->sent_at ? $notif->sent_at->diffForHumans() : '' }}</p>
                                            </div>
                                        </div>
                                    </a>
                                    @empty
                                    <div style="padding:24px 16px;text-align:center;font-size:0.875rem;color:#6b7280;">
                                        {{ __('notifications.no_notifications') }}
                                    </div>
                                    @endforelse
                                </div>
                                <a href="{{ route('admin.notifications.index') }}" class="notif-dropdown-footer">
                                    {{ __('notifications.view_all') }}
                                </a>
                            </div>
                        </div>

                        <!-- 3. Profil (Profile - sidebardagini tepaga ko'chirdik) -->
                        <div class="top-header-item" style="display:inline-block;position:relative;">
                            <button @click="profileOpen = !profileOpen; langOpen = false; notifOpen = false"
                                    class="top-header-btn top-header-profile-btn"
                                    style="display:inline-flex;flex-direction:row;align-items:center;gap:8px;padding-left:10px;border-left:1px solid #e5e7eb;margin-left:4px;">
                                @if($headerUserAvatar)
                                <img src="{{ $headerUserAvatar }}" alt="{{ $headerUserName }}"
                                     style="width:32px;height:32px;min-width:32px;border-radius:50%;object-fit:cover;border:2px solid #e5e7eb;">
                                @else
                                <div class="top-header-avatar">
                                    <svg style="width:16px;height:16px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                </div>
                                @endif
                                <span class="top-header-username">{{ $headerUserName }}</span>
                                <svg class="top-header-chevron" style="width:16px;height:16px;" :style="profileOpen ? 'transform:rotate(180deg)' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div x-show="profileOpen" x-transition @click.outside="profileOpen = false"
                                 class="top-header-dropdown" style="right:0;min-width:240px;">
                                <!-- User info -->
                                <div style="padding:12px 16px;border-bottom:1px solid #f3f4f6;">
                                    <p style="font-size:0.875rem;font-weight:600;color:#111827;">{{ $headerUserName }}</p>
                                    <p style="font-size:0.75rem;color:#6b7280;margin-top:2px;">{{ $headerActiveRoleLabel }}</p>
                                </div>

                                <!-- Profil link -->
                                @if($headerProfileRoute)
                                <a href="{{ $headerProfileRoute }}" class="top-header-dropdown-item">
                                    <svg style="width:16px;height:16px;margin-right:8px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                    Profil
                                </a>
                                @endif

                                <!-- Rol almashtirish -->
                                @if(count($headerUserRoles) > 1)
                                <div style="border-bottom:1px solid #f3f4f6;padding:4px 0;">
                                    <p style="padding:6px 16px;font-size:0.7rem;font-weight:600;color:#9ca3af;text-transform:uppercase;letter-spacing:0.05em;">Rolni almashtirish</p>
                                    @foreach($headerUserRoles as $hRole)
                                    <form method="POST" action="{{ $headerSwitchRoleRoute }}">
                                        @csrf
                                        <input type="hidden" name="role" value="{{ $hRole }}">
                                        <button type="submit" class="top-header-dropdown-item" style="width:100;justify-content:space-between;">
                                            <span>{{ $headerRoleLabels[$hRole] ?? $hRole }}</span>
                                            @if($hRole === $headerActiveRole)
                                            <svg style="width:16px;height:16px;color:#22c55e;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                                            </svg>
                                            @endif
                                        </button>
                                    </form>
                                    @endforeach
                                </div>
                                @endif

                                <!-- Sozlamalar -->
                                @if(in_array($headerActiveRole, ['superadmin', 'admin', 'kichik_admin']))
                                <a href="{{ route('admin.settings') }}" class="top-header-dropdown-item">
                                    <svg style="width:16px;height:16px;margin-right:8px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    </svg>
                                    Sozlamalar
                                </a>
                                @endif

                                <!-- Chiqish -->
                                <div style="border-top:1px solid #f3f4f6;">
                                    <form method="POST" action="{{ $headerLogoutRoute }}">
                                        @csrf
                                        <button type="submit" class="top-header-dropdown-item" style="width:100%;color:#ef4444;">
                                            <svg style="width:16px;height:16px;margin-right:8px;flex-shrink:0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                @if($headerIsImpersonating)
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 15l-3-3m0 0l3-3m-3 3h8M3 12a9 9 0 1118 0 9 9 0 01-18 0z"></path>
                                                @else
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                                @endif
                                            </svg>
                                            {{ $headerIsImpersonating ? 'Superadminga qaytish' : __('notifications.logout') }}
                                        </button>
                                    </form>
                                </div>
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
