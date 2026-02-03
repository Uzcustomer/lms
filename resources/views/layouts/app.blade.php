<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
        <link href="https://cdn.datatables.net/1.10.21/css/jquery.dataTables.min.css" rel="stylesheet">

        <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>

        <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="font-sans antialiased">
        <div class="flex h-screen bg-gray-100 dark:bg-gray-900">
            <!-- Sidebar -->
            <aside class="w-64 bg-[#1a3a8a] text-white flex flex-col min-h-screen fixed left-0 top-0 overflow-y-auto">
                <!-- Logo Section -->
                <div class="p-4 flex flex-col items-center border-b border-[#2a4a9a]">
                    <img src="{{ asset('logo.png') }}" alt="Logo" class="w-20 h-20 rounded-full mb-2">
                    <h1 class="text-xl font-bold">LMS</h1>
                </div>

                <!-- Navigation Menu -->
                <nav class="flex-1 py-4">
                    <a href="{{ route('admin.dashboard') }}" class="flex items-center px-4 py-3 hover:bg-[#2a4a9a] {{ request()->routeIs('admin.dashboard') ? 'bg-[#2a4a9a]' : '' }}">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                        </svg>
                        Dashboard
                    </a>

                    @if(auth()->user()->hasRole(['admin']))
                    <a href="{{ route('admin.journal.index') }}" class="flex items-center px-4 py-3 hover:bg-[#2a4a9a] {{ request()->routeIs('admin.journal.*') ? 'bg-[#2a4a9a]' : '' }}">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Jurnal
                    </a>
                    @endif

                    <a href="{{ route('admin.students.index') }}" class="flex items-center px-4 py-3 hover:bg-[#2a4a9a] {{ request()->routeIs('admin.students.*') ? 'bg-[#2a4a9a]' : '' }}">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        Talabalar
                    </a>

                    @if(auth()->user()->hasRole(['admin']))
                    <a href="{{ route('admin.users.index') }}" class="flex items-center px-4 py-3 hover:bg-[#2a4a9a] {{ request()->routeIs('admin.users.*') ? 'bg-[#2a4a9a]' : '' }}">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        Foydalanuvchilar
                    </a>

                    <a href="{{ route('admin.teachers.index') }}" class="flex items-center px-4 py-3 hover:bg-[#2a4a9a] {{ request()->routeIs('admin.teachers.*') ? 'bg-[#2a4a9a]' : '' }}">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                        O'qituvchilar
                    </a>

                    <a href="{{ route('admin.student-grades-week') }}" class="flex items-center px-4 py-3 hover:bg-[#2a4a9a] {{ request()->routeIs('admin.student-grades-week') ? 'bg-[#2a4a9a]' : '' }}">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        Baholar
                    </a>

                    <!-- QO'SHIMCHA Section -->
                    <div class="px-4 py-2 mt-4 text-xs font-semibold text-gray-300 uppercase">Qo'shimcha</div>

                    <a href="{{ route('admin.independent.index') }}" class="flex items-center px-4 py-3 hover:bg-[#2a4a9a] {{ request()->routeIs('admin.independent.*') ? 'bg-[#2a4a9a]' : '' }}">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                        Mustaqil ta'lim
                    </a>

                    <a href="{{ route('admin.oraliqnazorat.index') }}" class="flex items-center px-4 py-3 hover:bg-[#2a4a9a] {{ request()->routeIs('admin.oraliqnazorat.*') ? 'bg-[#2a4a9a]' : '' }}">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                        </svg>
                        Oraliq nazorat
                    </a>

                    <a href="{{ route('admin.oski.index') }}" class="flex items-center px-4 py-3 hover:bg-[#2a4a9a] {{ request()->routeIs('admin.oski.*') ? 'bg-[#2a4a9a]' : '' }}">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                        </svg>
                        OSKI
                    </a>

                    <a href="{{ route('admin.examtest.index') }}" class="flex items-center px-4 py-3 hover:bg-[#2a4a9a] {{ request()->routeIs('admin.examtest.*') ? 'bg-[#2a4a9a]' : '' }}">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Test
                    </a>

                    <a href="{{ route('admin.vedomost.index') }}" class="flex items-center px-4 py-3 hover:bg-[#2a4a9a] {{ request()->routeIs('admin.vedomost.*') ? 'bg-[#2a4a9a]' : '' }}">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        Vedomost
                    </a>

                    <!-- DARSLAR Section -->
                    <div class="px-4 py-2 mt-4 text-xs font-semibold text-gray-300 uppercase">Darslar</div>

                    <a href="{{ route('admin.lessons.create') }}" class="flex items-center px-4 py-3 hover:bg-[#2a4a9a] {{ request()->routeIs('admin.lessons.create') ? 'bg-[#2a4a9a]' : '' }}">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        </svg>
                        Dars yaratish
                    </a>

                    <a href="{{ route('admin.lesson.histories-index') }}" class="flex items-center px-4 py-3 hover:bg-[#2a4a9a] {{ request()->routeIs('admin.lesson.histories-index') ? 'bg-[#2a4a9a]' : '' }}">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Dars tarixi
                    </a>
                    @endif

                    <a href="{{ route('admin.qaytnoma.index') }}" class="flex items-center px-4 py-3 hover:bg-[#2a4a9a] {{ request()->routeIs('admin.qaytnoma.*') ? 'bg-[#2a4a9a]' : '' }}">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        YN oldi qaydnoma
                    </a>

                    @if(auth()->user()->hasRole(['examiner']))
                    <a href="{{ route('admin.examtest.index') }}" class="flex items-center px-4 py-3 hover:bg-[#2a4a9a] {{ request()->routeIs('admin.examtest.*') ? 'bg-[#2a4a9a]' : '' }}">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Test
                    </a>
                    @endif
                </nav>

                <!-- User Section -->
                <div class="p-4 border-t border-[#2a4a9a]">
                    <div class="flex items-center mb-3">
                        <span class="font-medium">{{ Auth::user()->name }}</span>
                    </div>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="w-full px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-md hover:bg-red-500">
                            Chiqish
                        </button>
                    </form>
                </div>
            </aside>

            <!-- Main Content -->
            <div class="flex-1 ml-64 overflow-x-hidden overflow-y-auto">
                <!-- Page Heading -->
                @isset($header)
                    <header class="bg-white dark:bg-gray-800 shadow">
                        <div class="max-w-screen-xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <!-- Page Content -->
                <main class="p-6">
                    {{ $slot }}
                </main>
            </div>
        </div>

        @yield('content')
        @livewireScripts
    </body>
    <script src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js"></script>
</html>
