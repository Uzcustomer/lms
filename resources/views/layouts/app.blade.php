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

        <style>
            /* Sidebar styles */
            .sidebar {
                width: 220px;
                min-height: 100vh;
                background: linear-gradient(180deg, #1e40af 0%, #1e3a8a 100%);
                position: fixed;
                left: 0;
                top: 0;
                z-index: 40;
                display: flex;
                flex-direction: column;
            }
            .sidebar-logo {
                padding: 20px;
                text-align: center;
                border-bottom: 1px solid rgba(255,255,255,0.1);
            }
            .sidebar-logo img {
                width: 60px;
                height: 60px;
                border-radius: 50%;
                margin-bottom: 8px;
            }
            .sidebar-logo span {
                color: white;
                font-size: 18px;
                font-weight: 600;
                display: block;
            }
            .sidebar-menu {
                flex: 1;
                padding: 16px 0;
                overflow-y: auto;
            }
            .sidebar-section {
                padding: 8px 16px;
                color: rgba(255,255,255,0.5);
                font-size: 11px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
                margin-top: 8px;
            }
            .sidebar-link {
                display: flex;
                align-items: center;
                padding: 12px 20px;
                color: rgba(255,255,255,0.8);
                text-decoration: none;
                font-size: 14px;
                transition: all 0.2s;
            }
            .sidebar-link:hover {
                background: rgba(255,255,255,0.1);
                color: white;
                text-decoration: none;
            }
            .sidebar-link.active {
                background: rgba(255,255,255,0.15);
                color: white;
                border-left: 3px solid white;
            }
            .sidebar-link i {
                width: 20px;
                margin-right: 12px;
                font-size: 16px;
            }
            .sidebar-user {
                padding: 16px 20px;
                border-top: 1px solid rgba(255,255,255,0.1);
                display: flex;
                align-items: center;
                gap: 12px;
            }
            .sidebar-user-avatar {
                width: 36px;
                height: 36px;
                border-radius: 50%;
                background: rgba(255,255,255,0.2);
                display: flex;
                align-items: center;
                justify-content: center;
                color: white;
                font-weight: 600;
            }
            .sidebar-user-info {
                flex: 1;
            }
            .sidebar-user-name {
                color: white;
                font-size: 14px;
                font-weight: 500;
            }
            .sidebar-user-role {
                color: rgba(255,255,255,0.6);
                font-size: 12px;
            }
            .main-content {
                margin-left: 220px;
                min-height: 100vh;
                background: #f3f4f6;
            }
            /* Mobile sidebar toggle */
            @media (max-width: 768px) {
                .sidebar {
                    transform: translateX(-100%);
                    transition: transform 0.3s;
                }
                .sidebar.open {
                    transform: translateX(0);
                }
                .main-content {
                    margin-left: 0;
                }
            }
        </style>
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen">
            <!-- Sidebar -->
            <aside class="sidebar" id="sidebar">
                <!-- Logo -->
                <div class="sidebar-logo">
                    <img src="{{ asset('images/logo.png') }}" alt="Logo" onerror="this.style.display='none'">
                    <span>LMS</span>
                </div>

                <!-- Menu -->
                <nav class="sidebar-menu">
                    <a href="{{ route('admin.dashboard') }}" class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                        <i class="fas fa-th-large"></i>
                        Dashboard
                    </a>
                    <a href="{{ route('admin.journal.index') }}" class="sidebar-link {{ request()->routeIs('admin.journal.*') ? 'active' : '' }}">
                        <i class="fas fa-book"></i>
                        Jurnal
                    </a>
                    <a href="{{ route('admin.students.index') }}" class="sidebar-link {{ request()->routeIs('admin.students.*') ? 'active' : '' }}">
                        <i class="fas fa-user-graduate"></i>
                        Talabalar
                    </a>
                    @if(auth()->user()->hasRole(['admin']))
                    <a href="{{ route('admin.users.index') }}" class="sidebar-link {{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                        <i class="fas fa-users"></i>
                        Foydalanuvchilar
                    </a>
                    <a href="{{ route('admin.teachers.index') }}" class="sidebar-link {{ request()->routeIs('admin.teachers.*') ? 'active' : '' }}">
                        <i class="fas fa-chalkboard-teacher"></i>
                        O'qituvchilar
                    </a>
                    <a href="{{ route('admin.student-grades-week') }}" class="sidebar-link {{ request()->routeIs('admin.student-grades-week') ? 'active' : '' }}">
                        <i class="fas fa-star"></i>
                        Baholar
                    </a>

                    <div class="sidebar-section">Qo'shimcha</div>
                    <a href="{{ route('admin.independent.index') }}" class="sidebar-link {{ request()->routeIs('admin.independent.*') ? 'active' : '' }}">
                        <i class="fas fa-tasks"></i>
                        Mustaqil ta'lim
                    </a>
                    <a href="{{ route('admin.oraliqnazorat.index') }}" class="sidebar-link {{ request()->routeIs('admin.oraliqnazorat.*') ? 'active' : '' }}">
                        <i class="fas fa-clipboard-check"></i>
                        Oraliq nazorat
                    </a>
                    <a href="{{ route('admin.oski.index') }}" class="sidebar-link {{ request()->routeIs('admin.oski.*') ? 'active' : '' }}">
                        <i class="fas fa-stethoscope"></i>
                        OSKI
                    </a>
                    <a href="{{ route('admin.examtest.index') }}" class="sidebar-link {{ request()->routeIs('admin.examtest.*') ? 'active' : '' }}">
                        <i class="fas fa-question-circle"></i>
                        Test
                    </a>
                    <a href="{{ route('admin.vedomost.index') }}" class="sidebar-link {{ request()->routeIs('admin.vedomost.*') ? 'active' : '' }}">
                        <i class="fas fa-table"></i>
                        Vedomost
                    </a>

                    <div class="sidebar-section">Darslar</div>
                    <a href="{{ route('admin.lessons.create') }}" class="sidebar-link {{ request()->routeIs('admin.lessons.create') ? 'active' : '' }}">
                        <i class="fas fa-plus-circle"></i>
                        Dars yaratish
                    </a>
                    <a href="{{ route('admin.lesson.histories-index') }}" class="sidebar-link {{ request()->routeIs('admin.lesson.histories-index') ? 'active' : '' }}">
                        <i class="fas fa-history"></i>
                        Dars tarixi
                    </a>
                    @endif

                    <a href="{{ route('admin.qaytnoma.index') }}" class="sidebar-link {{ request()->routeIs('admin.qaytnoma.*') ? 'active' : '' }}">
                        <i class="fas fa-file-alt"></i>
                        YN oldi qaydnoma
                    </a>
                </nav>

                <!-- User -->
                <div class="sidebar-user">
                    <div class="sidebar-user-avatar">
                        {{ strtoupper(substr(Auth::user()->name, 0, 2)) }}
                    </div>
                    <div class="sidebar-user-info">
                        <div class="sidebar-user-name">{{ Auth::user()->name }}</div>
                        <div class="sidebar-user-role">{{ Auth::user()->roles->first()?->name ?? 'user' }}</div>
                    </div>
                    <form method="POST" action="{{ route('admin.logout') }}">
                        @csrf
                        <button type="submit" class="text-white opacity-60 hover:opacity-100" title="Chiqish">
                            <i class="fas fa-sign-out-alt"></i>
                        </button>
                    </form>
                </div>
            </aside>

            <!-- Main Content -->
            <div class="main-content">
                <!-- Page Heading -->
                @isset($header)
                    <header class="bg-white shadow-sm">
                        <div class="py-4 px-6">
                            {{ $header }}
                        </div>
                    </header>
                @endisset

                <!-- Page Content -->
                <main>
                    {{ $slot }}
                </main>
            </div>
        </div>

        @yield('content')
        @livewireScripts
        <script src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js"></script>
    </body>
</html>
