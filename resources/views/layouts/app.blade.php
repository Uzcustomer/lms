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
                <!-- Mobile Top Bar -->
                <div class="mobile-top-bar" x-data>
                    <button @click="$store.sidebar.toggle()" class="hamburger-btn">
                        <svg style="width: 24px; height: 24px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                    <span style="margin-left: 12px; font-size: 1.125rem; font-weight: 600; color: #1f2937;">LMS</span>
                    <!-- Mobile notification bell -->
                    <div x-data="adminNotifBell()" x-init="init()" style="margin-left: auto;">
                        <button @click="togglePanel()" style="position: relative; padding: 8px; border-radius: 8px; background: none; border: none; cursor: pointer; color: #4b5563;">
                            <svg style="width: 22px; height: 22px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                            </svg>
                            <span x-show="unreadCount > 0" x-text="unreadCount > 9 ? '9+' : unreadCount"
                                  style="position: absolute; top: 2px; right: 2px; min-width: 18px; height: 18px; background: #ef4444; color: #fff; font-size: 10px; font-weight: 700; border-radius: 9px; display: flex; align-items: center; justify-content: center; padding: 0 4px;"></span>
                        </button>
                    </div>
                </div>

                <!-- Desktop notification bell (fixed top-right) -->
                <div x-data="adminNotifBell()" x-init="init()" class="admin-notif-desktop-bell" style="display: none;">
                    <button @click="togglePanel()" class="admin-notif-bell-btn" style="position: relative;">
                        <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                        </svg>
                        <span x-show="unreadCount > 0" x-text="unreadCount > 9 ? '9+' : unreadCount"
                              style="position: absolute; top: -2px; right: -2px; min-width: 16px; height: 16px; background: #ef4444; color: #fff; font-size: 9px; font-weight: 700; border-radius: 8px; display: flex; align-items: center; justify-content: center; padding: 0 3px;"></span>
                    </button>
                    <!-- Dropdown panel -->
                    <div x-show="showPanel" @click.outside="showPanel = false"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 transform -translate-y-1"
                         x-transition:enter-end="opacity-100 transform translate-y-0"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         style="position: absolute; right: 0; top: 44px; width: 360px; max-height: 420px; background: #fff; border-radius: 12px; box-shadow: 0 8px 30px rgba(0,0,0,0.15); border: 1px solid #e5e7eb; z-index: 9999; overflow: hidden;">
                        <div style="padding: 12px 16px; border-bottom: 1px solid #f3f4f6; display: flex; justify-content: space-between; align-items: center;">
                            <h3 style="font-size: 14px; font-weight: 600; color: #1f2937; margin: 0;">Xabarnomalar</h3>
                            <button x-show="unreadCount > 0" @click="markAllRead()" style="font-size: 12px; color: #3b82f6; background: none; border: none; cursor: pointer;">Hammasini o'qilgan</button>
                        </div>
                        <div style="max-height: 350px; overflow-y: auto;">
                            <template x-if="notifications.length === 0">
                                <div style="padding: 32px 16px; text-align: center; color: #9ca3af; font-size: 13px;">
                                    Xabarnomalar yo'q
                                </div>
                            </template>
                            <template x-for="n in notifications" :key="n.id">
                                <div @click="openNotification(n)"
                                     :style="'padding: 12px 16px; border-bottom: 1px solid #f9fafb; cursor: pointer; transition: background 0.15s;' + (n.is_read ? '' : 'background: #eff6ff;')"
                                     onmouseover="this.style.background='#f3f4f6'" onmouseout="this.style.background=this.getAttribute('data-bg')"
                                     :data-bg="n.is_read ? '' : '#eff6ff'">
                                    <div style="display: flex; align-items: flex-start; gap: 10px;">
                                        <div :style="'width: 8px; height: 8px; border-radius: 50%; margin-top: 5px; flex-shrink: 0;' + (n.is_read ? 'background: transparent;' : 'background: #3b82f6;')"></div>
                                        <div style="flex: 1; min-width: 0;">
                                            <div style="font-size: 13px; font-weight: 600; color: #1f2937;" x-text="n.title"></div>
                                            <div style="font-size: 12px; color: #6b7280; margin-top: 2px; line-height: 1.4;" x-text="n.message"></div>
                                            <div style="font-size: 11px; color: #9ca3af; margin-top: 4px;" x-text="n.created_at"></div>
                                        </div>
                                    </div>
                                </div>
                            </template>
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

    {{-- Admin notification bell script --}}
    <script>
        function adminNotifBell() {
            return {
                unreadCount: 0,
                notifications: [],
                showPanel: false,
                init() {
                    this.fetchUnreadCount();
                    setInterval(() => this.fetchUnreadCount(), 30000);
                },
                fetchUnreadCount() {
                    fetch('/admin/notifications/unread-count')
                        .then(r => r.json())
                        .then(data => {
                            this.unreadCount = data.count || 0;
                            if (this.unreadCount > 0 && this.showPanel) {
                                this.fetchNotifications();
                            }
                        })
                        .catch(() => {});
                },
                fetchNotifications() {
                    fetch('/admin/notifications/list')
                        .then(r => r.json())
                        .then(data => {
                            this.notifications = data.notifications || [];
                            this.unreadCount = data.unread_count || 0;
                        })
                        .catch(() => {});
                },
                togglePanel() {
                    this.showPanel = !this.showPanel;
                    if (this.showPanel) {
                        this.fetchNotifications();
                    }
                },
                openNotification(n) {
                    if (!n.is_read) {
                        fetch('/admin/notifications/' + n.id + '/read', {
                            method: 'POST',
                            headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}
                        }).then(() => {
                            n.is_read = true;
                            this.unreadCount = Math.max(0, this.unreadCount - 1);
                        });
                    }
                    if (n.link) {
                        window.location.href = n.link;
                    }
                },
                markAllRead() {
                    fetch('/admin/notifications/mark-all-read', {
                        method: 'POST',
                        headers: {'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content}
                    }).then(() => {
                        this.notifications.forEach(n => n.is_read = true);
                        this.unreadCount = 0;
                    });
                }
            }
        }
    </script>
    <style>
        .admin-notif-desktop-bell {
            position: fixed;
            top: 12px;
            right: 20px;
            z-index: 9998;
        }
        .admin-notif-bell-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #fff;
            border: 1px solid #e5e7eb;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #4b5563;
            transition: all 0.15s;
        }
        .admin-notif-bell-btn:hover {
            background: #f3f4f6;
            box-shadow: 0 4px 12px rgba(0,0,0,0.12);
        }
        @media (min-width: 768px) {
            .admin-notif-desktop-bell {
                display: block !important;
            }
        }
        @media (max-width: 767px) {
            .admin-notif-desktop-bell {
                display: none !important;
            }
        }
    </style>

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
