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
            <div class="flex-1 overflow-x-hidden overflow-y-auto ml-0 md:ml-64 transition-[margin] duration-200">
                <!-- Mobile Top Bar -->
                <div x-data class="sticky top-0 z-30 flex items-center justify-between bg-white dark:bg-gray-800 shadow px-3 py-2.5 md:hidden">
                    <div class="flex items-center">
                        <button @click="$store.sidebar.toggle()"
                                class="p-2 rounded-lg text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 focus:outline-none active:bg-gray-200 transition">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                            </svg>
                        </button>
                        <span class="ml-2 text-base font-semibold text-gray-800 dark:text-gray-200">LMS</span>
                    </div>
                    @isset($header)
                        <div class="text-sm font-medium text-gray-600 dark:text-gray-300 truncate max-w-[50vw]">
                            {{ $header }}
                        </div>
                    @endisset
                </div>

                <!-- Page Heading (desktop only) -->
                @isset($header)
                    <header class="bg-white dark:bg-gray-800 shadow hidden md:block">
                        <div class="max-w-screen-xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                {{-- Impersonatsiya banneri --}}
                @if(session('impersonating'))
                    <div class="bg-red-600 text-white">
                        <div class="max-w-screen-xl mx-auto px-3 sm:px-6 lg:px-8 py-2 flex items-center justify-between flex-wrap gap-2">
                            <span class="text-xs sm:text-sm font-medium">
                                Siz hozir <strong>{{ session('impersonated_name') }}</strong> sifatida kirgansiz
                            </span>
                            <form action="{{ route('impersonate.stop') }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="px-3 py-1 bg-white text-red-600 text-xs font-bold rounded hover:bg-red-50 transition">
                                    Orqaga qaytish
                                </button>
                            </form>
                        </div>
                    </div>
                @endif

                <!-- Page Content -->
                <main class="p-2 sm:p-4 md:p-6">
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
        console.group('%c LMS DEBUG: Admin Layout', 'color: #e74c3c; font-weight: bold; font-size: 14px;');
        console.log('%cLayout:', 'font-weight:bold', 'app.blade.php (Admin)');
        console.log('%cURL:', 'font-weight:bold', window.location.href);
        console.log('%cGuard (server):', 'font-weight:bold', '{{ auth()->guard("web")->check() ? "web (id=" . auth()->guard("web")->id() . " " . (auth()->guard("web")->user()->name ?? "?") . ")" : "web" }}');
        console.log('%cTeacher guard:', 'font-weight:bold', '{{ auth()->guard("teacher")->check() ? "teacher (id=" . auth()->guard("teacher")->id() . ")" : "teacher" }}');
        console.log('%cStudent guard:', 'font-weight:bold', '{{ auth()->guard("student")->check() ? "student (id=" . auth()->guard("student")->id() . ")" : "student" }}');
        console.log('%cauth()->user():', 'font-weight:bold', '{{ auth()->user() ? "id=" . auth()->user()->id . " name=" . auth()->user()->name : "NULL" }}');
        console.log('%csession.impersonating:', 'font-weight:bold', {{ session('impersonating') ? 'true' : 'false' }});
        console.log('%csession.impersonated_name:', 'font-weight:bold', '{{ session("impersonated_name", "NULL") }}');
        console.log('%csession.impersonator_id:', 'font-weight:bold', '{{ session("impersonator_id", "NULL") }}');
        console.log('%csession.active_role:', 'font-weight:bold', '{{ session("active_role", "NULL") }}');
        console.log('%csession_id:', 'font-weight:bold', '{{ session()->getId() }}');
        @if(session('impersonating'))
            console.warn('%c IMPERSONATION BANNER KO\'RINMOQDA!', 'color: red; font-size: 16px; font-weight: bold;');
        @else
            console.log('%c Impersonation banner yo\'q (to\'g\'ri)', 'color: green;');
        @endif
        console.groupEnd();
    </script>
</html>
