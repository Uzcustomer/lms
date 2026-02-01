<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Jurnal' }} - {{ config('app.name', 'LMS') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <style>
        .sidebar {
            background: linear-gradient(180deg, #1e40af 0%, #3b82f6 100%);
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
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: white;
            padding: 5px;
        }
        .sidebar-logo h2 {
            color: white;
            font-size: 16px;
            margin-top: 10px;
            font-weight: 600;
        }
        .sidebar-menu {
            padding: 10px 0;
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
        }
        .sidebar-user-info small {
            opacity: 0.7;
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
        .breadcrumb {
            font-size: 13px;
            color: #6b7280;
        }
        .main-body {
            padding: 20px 25px;
        }
        /* Filter styles */
        .filter-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .filter-row {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            align-items: flex-end;
        }
        .filter-group {
            flex: 1;
            min-width: 150px;
        }
        .filter-group label {
            display: block;
            font-size: 12px;
            font-weight: 500;
            color: #6b7280;
            margin-bottom: 5px;
        }
        .filter-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            background: white;
        }
        .filter-group select:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        .btn-filter {
            background: #10b981;
            color: white;
            padding: 8px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.3s;
        }
        .btn-filter:hover {
            background: #059669;
        }
        /* Table styles */
        .journal-table-wrapper {
            background: white;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        .journal-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .journal-table th {
            background: #f8fafc;
            padding: 10px 8px;
            text-align: center;
            font-weight: 600;
            color: #374151;
            border: 1px solid #e5e7eb;
            white-space: nowrap;
        }
        .journal-table td {
            padding: 8px;
            border: 1px solid #e5e7eb;
            text-align: center;
        }
        .journal-table tbody tr:hover {
            background: #f9fafb;
        }
        .journal-table .student-name {
            text-align: left;
            white-space: nowrap;
            min-width: 200px;
            position: sticky;
            left: 0;
            background: white;
            z-index: 1;
        }
        .journal-table tbody tr:hover .student-name {
            background: #f9fafb;
        }
        .journal-table .grade-cell {
            min-width: 35px;
            font-weight: 500;
        }
        .journal-table .grade-cell.absent {
            background: #fef2f2;
            color: #dc2626;
        }
        .journal-table .grade-cell.low {
            background: #fef3c7;
            color: #d97706;
        }
        .journal-table .grade-cell.good {
            background: #d1fae5;
            color: #059669;
        }
        .journal-table .summary-cell {
            background: #f0f9ff;
            font-weight: 600;
            min-width: 50px;
        }
        .journal-table .date-header {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            transform: rotate(180deg);
            height: 80px;
            font-size: 11px;
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
    @stack('styles')
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-logo">
            <img src="{{ asset('images/logo.png') }}" alt="Logo" onerror="this.src='https://via.placeholder.com/60'">
            <h2>Jurnal</h2>
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
            <img src="https://via.placeholder.com/40" alt="User">
            <div class="sidebar-user-info">
                <div>{{ auth()->user()->name ?? 'admin' }}</div>
                <small>{{ auth()->user()->roles->first()->name ?? 'manager' }}</small>
            </div>
            <button class="ml-auto text-white" title="Menu">
                <i class="fas fa-ellipsis-v"></i>
            </button>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="main-header">
            <div>
                <span class="breadcrumb">/ {{ $breadcrumb ?? 'Jurnal' }}</span>
                <h1><i class="fas fa-chevron-left mr-2"></i> {{ $pageTitle ?? 'Jurnal' }}</h1>
            </div>
            <div>
                @yield('header-actions')
            </div>
        </div>

        <div class="main-body">
            {{ $slot }}
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.select2').select2({
                placeholder: 'Tanlang...',
                allowClear: true
            });
        });
    </script>
    @stack('scripts')
</body>
</html>
