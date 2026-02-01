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
            .sidebar {
                background: linear-gradient(180deg, #1a4a7c 0%, #2c5aa0 100%);
                min-height: 100vh;
                width: 220px;
                position: fixed;
                left: 0;
                top: 0;
                z-index: 1000;
            }
            .sidebar-logo {
                padding: 20px;
                text-align: center;
                border-bottom: 1px solid rgba(255,255,255,0.1);
            }
            .sidebar-logo img {
                width: 80px;
                height: 80px;
                border-radius: 50%;
                object-fit: cover;
            }
            .sidebar-logo h2 {
                color: white;
                font-size: 16px;
                margin-top: 10px;
                font-weight: 600;
            }
            .sidebar-menu {
                padding: 10px 0;
                overflow-y: auto;
                max-height: calc(100vh - 200px);
                padding-bottom: 80px;
            }
            .sidebar-menu::-webkit-scrollbar {
                width: 6px;
            }
            .sidebar-menu::-webkit-scrollbar-track {
                background: rgba(255,255,255,0.1);
            }
            .sidebar-menu::-webkit-scrollbar-thumb {
                background: rgba(255,255,255,0.3);
                border-radius: 3px;
            }
            .sidebar-menu::-webkit-scrollbar-thumb:hover {
                background: rgba(255,255,255,0.5);
            }
            .sidebar-menu a {
                display: flex;
                align-items: center;
                padding: 12px 20px;
                color: rgba(255,255,255,0.8);
                text-decoration: none;
                font-size: 14px;
                transition: all 0.3s ease;
            }
            .sidebar-menu a:hover,
            .sidebar-menu a.active {
                background: rgba(255,255,255,0.2);
                color: white;
            }
            .sidebar-menu a i {
                width: 24px;
                margin-right: 10px;
                text-align: center;
            }
            .sidebar-menu a.active {
                background: rgba(255,255,255,0.25);
                border-left: 3px solid white;
            }
            .sidebar-divider {
                padding: 15px 20px 8px;
                font-size: 11px;
                text-transform: uppercase;
                color: rgba(255,255,255,0.5);
                letter-spacing: 1px;
                font-weight: 600;
            }
            .sidebar-menu a.yordam-btn {
                background: #10b981 !important;
                margin: 15px 10px;
                border-radius: 8px;
                justify-content: center;
            }
            .sidebar-menu a.yordam-btn:hover {
                background: #059669 !important;
            }
            .sidebar-user {
                position: absolute;
                bottom: 0;
                left: 0;
                right: 0;
                padding: 15px;
                border-top: 1px solid rgba(255,255,255,0.1);
                display: flex;
                align-items: center;
                background: rgba(0,0,0,0.1);
            }
            .sidebar-user img {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                margin-right: 10px;
            }
            .sidebar-user-info {
                color: white;
                font-size: 13px;
                flex: 1;
            }
            .sidebar-user-info small {
                opacity: 0.7;
                display: block;
            }
            .sidebar-user-logout {
                color: white;
                opacity: 0.7;
                cursor: pointer;
                padding: 5px;
            }
            .sidebar-user-logout:hover {
                opacity: 1;
            }
            .main-content {
                margin-left: 220px;
                min-height: 100vh;
                background: #f3f4f6;
            }
            .main-header {
                background: white;
                padding: 15px 25px;
                border-bottom: 1px solid #e5e7eb;
                display: flex;
                align-items: center;
                justify-content: space-between;
            }
            .main-header h1 {
                font-size: 18px;
                font-weight: 600;
                color: #1f2937;
                margin: 0;
            }
            .main-body {
                padding: 20px 25px;
                overflow-x: auto;
            }
            @media (max-width: 768px) {
                .sidebar {
                    transform: translateX(-100%);
                    transition: transform 0.3s ease;
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
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-logo">
                <img src="{{ asset('logo.png') }}" alt="Logo">
                <h2>LMS</h2>
            </div>

            <nav class="sidebar-menu">
                <!-- Asosiy -->
                <a href="{{ route('admin.dashboard') }}" class="{{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                    <i class="fas fa-tachometer-alt"></i>
                    Dashboard
                </a>
                <a href="{{ route('admin.jurnal.index') }}" class="{{ request()->routeIs('admin.jurnal.*') ? 'active' : '' }}">
                    <i class="fas fa-book"></i>
                    Jurnal
                </a>
                <a href="{{ route('admin.students.index') }}" class="{{ request()->routeIs('admin.students.*') ? 'active' : '' }}">
                    <i class="fas fa-user-graduate"></i>
                    Talabalar
                </a>

                @if(auth()->user()->hasRole(['admin']))
                <a href="{{ route('admin.users.index') }}" class="{{ request()->routeIs('admin.users.*') ? 'active' : '' }}">
                    <i class="fas fa-users-cog"></i>
                    Foydalanuvchilar
                </a>
                <a href="{{ route('admin.teachers.index') }}" class="{{ request()->routeIs('admin.teachers.*') ? 'active' : '' }}">
                    <i class="fas fa-chalkboard-teacher"></i>
                    O'qituvchilar
                </a>
                <a href="{{ route('admin.student-grades-week') }}" class="{{ request()->routeIs('admin.student-grades-week') ? 'active' : '' }}">
                    <i class="fas fa-chart-line"></i>
                    Baholar
                </a>

                <!-- Qo'shimcha bo'limi -->
                <div class="sidebar-divider">Qo'shimcha</div>
                <a href="{{ route('admin.independent.index') }}" class="{{ request()->routeIs('admin.independent.*') ? 'active' : '' }}">
                    <i class="fas fa-tasks"></i>
                    Mustaqil ta'lim
                </a>
                <a href="{{ route('admin.oraliqnazorat.index') }}" class="{{ request()->routeIs('admin.oraliqnazorat.*') ? 'active' : '' }}">
                    <i class="fas fa-clipboard-check"></i>
                    Oraliq nazorat
                </a>
                <a href="{{ route('admin.oski.index') }}" class="{{ request()->routeIs('admin.oski.*') ? 'active' : '' }}">
                    <i class="fas fa-file-alt"></i>
                    OSKI
                </a>
                <a href="{{ route('admin.examtest.index') }}" class="{{ request()->routeIs('admin.examtest.*') ? 'active' : '' }}">
                    <i class="fas fa-question-circle"></i>
                    Test
                </a>
                <a href="{{ route('admin.vedomost.index') }}" class="{{ request()->routeIs('admin.vedomost.*') ? 'active' : '' }}">
                    <i class="fas fa-table"></i>
                    Vedomost
                </a>

                <!-- Darslar bo'limi -->
                <div class="sidebar-divider">Darslar</div>
                <a href="{{ route('admin.lessons.create') }}" class="{{ request()->routeIs('admin.lessons.*') ? 'active' : '' }}">
                    <i class="fas fa-plus-circle"></i>
                    Dars yaratish
                </a>
                <a href="{{ route('admin.lesson.histories-index') }}" class="{{ request()->routeIs('admin.lesson.histories-index') ? 'active' : '' }}">
                    <i class="fas fa-history"></i>
                    Dars tarixi
                </a>
                @endif

                <a href="{{ route('admin.qaytnoma.index') }}" class="{{ request()->routeIs('admin.qaytnoma.*') ? 'active' : '' }}">
                    <i class="fas fa-file-signature"></i>
                    YN oldi qaydnoma
                </a>

                @if(auth()->user()->hasRole(['admin']))
                <!-- Sozlamalar -->
                <div class="sidebar-divider">Sozlamalar</div>
                <a href="{{ route('admin.deadlines') }}" class="{{ request()->routeIs('admin.deadlines') ? 'active' : '' }}">
                    <i class="fas fa-clock"></i>
                    Deadline
                </a>
                <a href="{{ route('admin.synchronizes') }}" class="{{ request()->routeIs('admin.synchronizes') ? 'active' : '' }}">
                    <i class="fas fa-sync"></i>
                    Sinxronizatsiya
                </a>
                @endif

                <!-- Yordam -->
                <a href="https://t.me/your_support_bot" target="_blank" class="yordam-btn">
                    <i class="fab fa-telegram-plane"></i>
                    Yordam
                </a>
            </nav>

            <div class="sidebar-user">
                <img src="https://ui-avatars.com/api/?name={{ urlencode(Auth::user()->name ?? 'User') }}&background=random" alt="User">
                <div class="sidebar-user-info">
                    <div>{{ Auth::user()->name ?? 'User' }}</div>
                    <small>{{ Auth::user()->roles->first()->name ?? 'user' }}</small>
                </div>
                <form method="POST" action="{{ route('admin.logout') }}" style="margin: 0;">
                    @csrf
                    <button type="submit" class="sidebar-user-logout" title="Chiqish">
                        <i class="fas fa-sign-out-alt"></i>
                    </button>
                </form>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Page Heading -->
            @isset($header)
                <div class="main-header">
                    {{ $header }}
                </div>
            @endisset

            <!-- Page Content -->
            <div class="main-body">
                {{ $slot }}
            </div>
        </div>

        @yield('content')
        @livewireScripts
        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <script src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js"></script>
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

        <script>
            // Sidebar scroll pozitsiyasini saqlash va tiklash
            document.addEventListener('DOMContentLoaded', function() {
                var sidebarMenu = document.querySelector('.sidebar-menu');

                // Saqlangan pozitsiyani tiklash
                var savedScrollPos = localStorage.getItem('sidebarScrollPos');
                if (savedScrollPos && sidebarMenu) {
                    sidebarMenu.scrollTop = parseInt(savedScrollPos);
                }

                // Scroll pozitsiyasini saqlash
                if (sidebarMenu) {
                    sidebarMenu.addEventListener('scroll', function() {
                        localStorage.setItem('sidebarScrollPos', sidebarMenu.scrollTop);
                    });
                }

                // Link bosilganda pozitsiyani saqlash
                var sidebarLinks = document.querySelectorAll('.sidebar-menu a');
                sidebarLinks.forEach(function(link) {
                    link.addEventListener('click', function() {
                        localStorage.setItem('sidebarScrollPos', sidebarMenu.scrollTop);
                    });
                });
            });
        </script>
    </body>
</html>
