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
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet"/>
    <link href="https://cdn.datatables.net/1.10.21/css/jquery.dataTables.min.css" rel="stylesheet">

    {{-- Alpine.js Livewire v3 orqali yuklanadi (@livewireScripts), CDN kerak emas --}}

    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet"/>

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
            <!-- Mobile Top Bar -->
            <div class="mobile-top-bar" x-data>
                <button @click="$store.sidebar.toggle()" class="hamburger-btn">
                    <svg style="width: 24px; height: 24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
                <span style="margin-left: 12px; font-size: 1.125rem; font-weight: 600; color: #1f2937;">LMS</span>
                <span style="margin-left: auto; font-size: 0.875rem; color: #6b7280;">
                    {{ Auth::guard('teacher')->user()->short_name ?? '' }}
                </span>
            </div>

            <!-- Xabarnomalar ikonchasi — o'ng yuqori burchak -->
                @php
                    $notifUser = auth()->guard('teacher')->user() ?? auth()->user();
                    $notifUserId = $notifUser->id ?? 0;
                    $notifUserType = $notifUser ? get_class($notifUser) : 'App\\Models\\Teacher';
                    $notifUnreadCount = \App\Models\Notification::where('recipient_id', $notifUserId)
                        ->where('recipient_type', $notifUserType)
                        ->where('is_draft', false)
                        ->where('is_read', false)
                        ->count();
                @endphp
                <div x-data="{ notifOpen: false }" style="position:fixed;top:10px;right:16px;z-index:9999;">
                    <button @click.stop="notifOpen = !notifOpen"
                            style="position:relative;width:36px;height:36px;display:inline-flex;align-items:center;justify-content:center;border-radius:50%;background:#fff;border:1px solid #e5e7eb;box-shadow:0 1px 4px rgba(0,0,0,0.08);cursor:pointer;transition:all 0.15s;"
                            onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='#fff'">
                        <svg style="width:18px;height:18px;color:#4b5563;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                        @if($notifUnreadCount > 0)
                        <span style="position:absolute;top:-4px;right:-4px;min-width:18px;height:18px;padding:0 5px;background:#ef4444;border-radius:9px;border:2px solid #fff;display:inline-flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;color:#fff;line-height:1;">{{ $notifUnreadCount > 99 ? '99+' : $notifUnreadCount }}</span>
                        @endif
                    </button>

                    <div x-show="notifOpen" x-cloak
                         x-transition:enter="transition ease-out duration-100"
                         x-transition:enter-start="opacity-0 transform scale-95"
                         x-transition:enter-end="opacity-100 transform scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="opacity-100 transform scale-100"
                         x-transition:leave-end="opacity-0 transform scale-95"
                         @click.outside="notifOpen = false"
                         style="position:absolute;top:42px;right:0;width:300px;background:#fff;border:1px solid #d1d5db;border-radius:10px;box-shadow:0 10px 40px rgba(0,0,0,0.15);overflow:hidden;z-index:99999;">
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;border-bottom:1px solid #e5e7eb;background:#f9fafb;">
                            <span style="font-weight:600;font-size:0.8rem;color:#111827;">{{ __('notifications.notifications') }}</span>
                            @if($notifUnreadCount > 0)
                            <form method="POST" action="{{ route('admin.notifications.mark-all-read') }}" style="display:inline;">
                                @csrf
                                <button type="submit" style="font-size:0.65rem;color:#2563eb;background:none;border:none;cursor:pointer;font-weight:500;">{{ __('notifications.mark_all_read') }}</button>
                            </form>
                            @endif
                        </div>
                        <div style="max-height:280px;overflow-y:auto;">
                            @php
                                $recentNotifs = \App\Models\Notification::with('sender')
                                    ->where('recipient_id', $notifUserId)
                                    ->where('recipient_type', $notifUserType)
                                    ->where('is_draft', false)
                                    ->orderByDesc('sent_at')
                                    ->take(6)
                                    ->get();
                            @endphp
                            @forelse($recentNotifs as $notif)
                            <a href="{{ route('admin.notifications.show', $notif) }}"
                               style="display:block;padding:8px 14px;border-bottom:1px solid #f3f4f6;text-decoration:none;transition:background 0.1s;{{ !$notif->is_read ? 'background:#eff6ff;' : '' }}"
                               onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='{{ !$notif->is_read ? '#eff6ff' : '#fff' }}'">
                                <div style="display:flex;align-items:flex-start;gap:8px;">
                                    @if(!$notif->is_read)
                                    <span style="width:6px;height:6px;background:#3b82f6;border-radius:50%;flex-shrink:0;margin-top:5px;"></span>
                                    @else
                                    <span style="width:6px;height:6px;flex-shrink:0;margin-top:5px;"></span>
                                    @endif
                                    <div style="flex:1;min-width:0;">
                                        <p style="font-size:0.7rem;color:#6b7280;margin:0 0 1px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">{{ $notif->sender?->name ?? $notif->sender?->short_name ?? $notif->sender?->full_name ?? __('notifications.system') }}</p>
                                        <p style="font-size:0.8rem;font-weight:{{ !$notif->is_read ? '600' : '400' }};color:#111827;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin:0;">{{ $notif->subject }}</p>
                                        <p style="font-size:0.65rem;color:#6b7280;margin:2px 0 0;">{{ $notif->sent_at ? $notif->sent_at->diffForHumans() : '' }}</p>
                                    </div>
                                </div>
                            </a>
                            @empty
                            <div style="padding:24px 14px;text-align:center;font-size:0.8rem;color:#9ca3af;">
                                {{ __('notifications.no_notifications') }}
                            </div>
                            @endforelse
                        </div>
                        <a href="{{ route('admin.notifications.index') }}"
                           style="display:block;text-align:center;padding:8px;font-size:0.75rem;font-weight:600;color:#3b82f6;border-top:1px solid #e5e7eb;text-decoration:none;transition:background 0.1s;"
                           onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background='#fff'">
                            {{ __('notifications.view_all') }}
                        </a>
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

            {{-- Telegram tasdiqlash ogohlantirishi --}}
            @auth('teacher')
                @php
                    $authTeacher = auth()->guard('teacher')->user();
                @endphp
                @if($authTeacher && $authTeacher->phone && !$authTeacher->isTelegramVerified())
                    @php
                        $daysLeft = $authTeacher->telegramDaysLeft();
                    @endphp
                    <div class="max-w-screen-xl mx-auto px-3 sm:px-6 lg:px-8 mt-3">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between px-4 py-3 rounded-lg border gap-3
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
                            <a href="{{ route('teacher.complete-profile') }}"
                               class="inline-flex items-center px-4 py-2 text-sm font-semibold rounded-lg transition flex-shrink-0
                               {{ $daysLeft <= 2 ? 'bg-red-600 text-white hover:bg-red-700' : ($daysLeft <= 4 ? 'bg-yellow-600 text-white hover:bg-yellow-700' : 'bg-blue-600 text-white hover:bg-blue-700') }}">
                                <svg class="w-5 h-5 mr-1.5" fill="currentColor" viewBox="0 0 24 24">
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
        console.group('%c🔍 LMS DEBUG: Teacher Layout', 'color: #2ecc71; font-weight: bold; font-size: 14px;');
        console.log('%cLayout:', 'font-weight:bold', 'teacher-app.blade.php (Teacher)');
        console.log('%cURL:', 'font-weight:bold', window.location.href);
        console.log('%cGuard (server):', 'font-weight:bold', '{{ auth()->guard("web")->check() ? "web ✅ (id=" . auth()->guard("web")->id() . ")" : "web ❌" }}');
        console.log('%cTeacher guard:', 'font-weight:bold', '{{ auth()->guard("teacher")->check() ? "teacher ✅ (id=" . auth()->guard("teacher")->id() . " " . (auth()->guard("teacher")->user()->full_name ?? "?") . ")" : "teacher ❌" }}');
        console.log('%cStudent guard:', 'font-weight:bold', '{{ auth()->guard("student")->check() ? "student ✅ (id=" . auth()->guard("student")->id() . ")" : "student ❌" }}');
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
</html>
