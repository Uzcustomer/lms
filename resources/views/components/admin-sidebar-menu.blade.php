@php
    $isTeacher = auth()->guard('teacher')->check();
    $user = auth()->user() ?? auth()->guard('teacher')->user();

    // Foydalanuvchi rollari va faol rol
    $userRoles = $user ? $user->getRoleNames()->toArray() : [];
    $activeRole = session('active_role', $userRoles[0] ?? '');
    // Session dagi rol foydalanuvchida mavjud ekanligini tekshirish
    if (!in_array($activeRole, $userRoles) && count($userRoles) > 0) {
        $activeRole = $userRoles[0];
    }

    // Admin rollar - har doim admin route ishlatadi
    $adminRoles = ['superadmin', 'admin', 'kichik_admin'];

    // Faol rolga qarab menyu ko'rsatish
    $hasActiveRole = function($roles) use ($activeRole) {
        return in_array($activeRole, (array) $roles);
    };

    // Foydalanuvchida rol mavjudligini tekshirish (faol bo'lmasa ham)
    $hasAnyRole = function($roles) use ($userRoles) {
        return count(array_intersect((array) $roles, $userRoles)) > 0;
    };

    // Route resolver - teacher yoki admin guardga qarab route aniqlash
    $r = function($adminRoute, $teacherRoute = null) use ($isTeacher, $activeRole, $adminRoles) {
        if ($isTeacher && $teacherRoute && !in_array($activeRole, $adminRoles)) {
            return route($teacherRoute);
        }
        return route($adminRoute);
    };

    // Active route check - ikkala guard uchun
    $isActive = function($adminPattern, $teacherPattern = null) use ($isTeacher, $activeRole, $adminRoles) {
        if ($isTeacher && $teacherPattern && !in_array($activeRole, $adminRoles)) {
            return request()->routeIs($teacherPattern);
        }
        return request()->routeIs($adminPattern);
    };

    $useTeacherRoutes = $isTeacher && !in_array($activeRole, $adminRoles);
    $isImpersonating = session('impersonating', false);
    $logoutRoute = $isImpersonating ? route('impersonate.stop') : ($useTeacherRoutes ? route('teacher.logout') : route('admin.logout'));
    $switchRoleRoute = $useTeacherRoutes ? route('teacher.switch-role') : route('admin.switch-role');
    $profileRoute = $useTeacherRoutes ? route('teacher.info-me') : null;

    // Rol labellarini olish
    $roleLabels = [];
    foreach (\App\Enums\ProjectRole::cases() as $role) {
        $roleLabels[$role->value] = $role->label();
    }
    $activeRoleLabel = $roleLabels[$activeRole] ?? $activeRole;

    // Foydalanuvchi rasmi
    $userAvatar = null;
    if ($isTeacher && isset($user->image) && $user->image) {
        $userAvatar = $user->image;
    }

    // Foydalanuvchi to'liq ismi
    $userName = $user ? ($user->name ?? ($user->full_name ?? $user->short_name ?? 'Foydalanuvchi')) : 'Foydalanuvchi';
@endphp
<!-- Mobile backdrop -->
<div x-data x-show="$store.sidebar.open"
     x-transition:enter="transition-opacity ease-linear duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition-opacity ease-linear duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     @click="$store.sidebar.open = false"
     class="sidebar-backdrop"
     style="display: none;"></div>

<aside x-data="sidebarTheme()" :data-theme="theme"
       :class="$store.sidebar.open ? 'sidebar-open' : ''"
       class="sidebar-themed w-64 flex flex-col fixed left-0 top-0 z-50"
       style="height: 100vh;">
    <!-- Logo Section -->
    <div class="p-4 flex flex-col items-center flex-shrink-0 sidebar-logo-section" style="position: relative;">
        <!-- Mobile close button -->
        <button x-data @click="$store.sidebar.close()"
                class="sidebar-close-btn">
            <svg style="width: 20px; height: 20px;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
        <img src="{{ asset('logo.png') }}" alt="Logo" class="w-16 h-16 rounded-full mb-2 sidebar-logo-img">
        <h1 class="sidebar-logo-text">LMS</h1>
    </div>

    <!-- Navigation Menu -->
    <nav class="flex-1 py-2 px-2 overflow-y-auto sidebar-nav"
         x-data @click="if($event.target.closest('a')) { if(window.innerWidth < 768) $store.sidebar.close() }">
        <!-- Xabarnomalar (Notifications) -->
        @php
            $sidebarUserType = get_class($user);
            $sidebarUnreadCount = \App\Models\Notification::where('recipient_id', $user->id)
                ->where('recipient_type', $sidebarUserType)
                ->where('is_draft', false)
                ->where('is_read', false)
                ->count();
        @endphp
        <a href="{{ route('admin.notifications.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.notifications.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
            </svg>
            {{ __('notifications.notifications') }}
            @if($sidebarUnreadCount > 0)
            <span class="ml-auto inline-flex items-center justify-center px-2 py-0.5 text-xs font-bold leading-none text-white bg-red-500 rounded-full">{{ $sidebarUnreadCount }}</span>
            @endif
        </a>

        <a href="{{ $r('admin.dashboard', 'teacher.dashboard') }}"
           class="sidebar-link {{ $isActive('admin.dashboard', 'teacher.dashboard') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
            </svg>
            Dashboard
        </a>

        @if($hasActiveRole(['admin', 'superadmin', 'registrator_ofisi']))
        <a href="{{ route('admin.student-ratings.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.student-ratings.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
            </svg>
            Talabalar reytingi
        </a>
        @endif

        @if($hasActiveRole('javobgar_firma'))
        <a href="{{ route('admin.firm-students.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.firm-students.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"></path>
            </svg>
            Viza va registratsiya
        </a>
        @endif

        {{-- Javobgar firma roli uchun faqat 'Firma talabalari' ko'rinadi, qolganlar yashiriladi --}}
        @if(!$hasActiveRole('javobgar_firma'))

        @if(($isTeacher || $hasActiveRole(['superadmin', 'admin', 'kichik_admin', 'registrator_ofisi', 'dekan', 'oqituvchi', 'kafedra_mudiri', 'fan_masuli'])) && !$hasActiveRole(['oquv_bolimi', 'oquv_bolimi_boshligi']))
        <a href="{{ route('admin.journal.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.journal.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            Jurnal
        </a>
        @endif

        @if(!$hasActiveRole(['test_markazi', 'oquv_bolimi', 'oquv_bolimi_boshligi', 'oqituvchi']))
        <a href="{{ $r('admin.students.index', $hasActiveRole('registrator_ofisi') ? null : 'teacher.students') }}"
           class="sidebar-link {{ $isActive('admin.students.*', 'teacher.students') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
            </svg>
            Talabalar
        </a>
        @endif


        @if($hasActiveRole(['superadmin', 'admin', 'kichik_admin']))
        <a href="{{ route('admin.users.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.users.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
            </svg>
            Foydalanuvchilar
        </a>

        <a href="{{ route('admin.teachers.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.teachers.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
            </svg>
            Xodimlar
        </a>

        <a href="{{ route('admin.student-grades-week') }}"
           class="sidebar-link {{ request()->routeIs('admin.student-grades-week') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
            </svg>
            Baholar
        </a>

        @endif

        @if($hasActiveRole(['superadmin', 'admin', 'kichik_admin', 'fan_masuli', 'kafedra_mudiri', 'dekan', 'registrator_ofisi']))
        <a href="{{ route('admin.ktr.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.ktr.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
            KTR
        </a>
        @endif

        @if($hasActiveRole(['superadmin', 'admin', 'kichik_admin', 'registrator_ofisi', 'dekan']))
        <a href="{{ route('admin.international-students.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.international-students.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418"></path>
            </svg>
            Xalqaro talabalar
        </a>
        @endif

        @if($hasActiveRole(['superadmin', 'admin', 'kichik_admin', 'kafedra_mudiri']))
        <a href="{{ route('admin.club-applications.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.club-applications.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m.94 3.198l.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0112 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 016 18.719m12 0a5.971 5.971 0 00-.941-3.197m0 0A5.995 5.995 0 0012 12.75a5.995 5.995 0 00-5.058 2.772m0 0a3 3 0 00-4.681 2.72 8.986 8.986 0 003.74.477m.94-3.197a5.971 5.971 0 00-.94 3.197M15 6.75a3 3 0 11-6 0 3 3 0 016 0zm6 3a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0zm-13.5 0a2.25 2.25 0 11-4.5 0 2.25 2.25 0 014.5 0z"/>
            </svg>
            To'garak arizalari
        </a>
        @endif

        @if($hasActiveRole(['superadmin', 'admin', 'kichik_admin']))
        <a href="{{ $r('admin.academic-schedule.index', 'teacher.academic-schedule.index') }}"
           class="sidebar-link {{ $isActive('admin.academic-schedule.index', 'teacher.academic-schedule.index') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
            YN kunini belgilash
        </a>
        @endif

        @if($hasActiveRole('test_markazi'))
        {{-- Test markazi roli uchun faqat Diagnostika --}}
        <div class="sidebar-section">Test markazi</div>

        <a href="{{ $r('admin.diagnostika.index', 'teacher.diagnostika.index') }}"
           class="sidebar-link {{ $isActive('admin.diagnostika.*', 'teacher.diagnostika.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"></path>
            </svg>
            Diagnostika
        </a>

        <a href="{{ $r('admin.saqlangan-hisobot.index', 'teacher.saqlangan-hisobot.index') }}"
           class="sidebar-link {{ $isActive('admin.saqlangan-hisobot.*', 'teacher.saqlangan-hisobot.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Yuklangan natijalar
        </a>

        <a href="{{ $r('admin.yuklanmagan-natijalar.index', 'teacher.yuklanmagan-natijalar.index') }}"
           class="sidebar-link {{ $isActive('admin.yuklanmagan-natijalar.*', 'teacher.yuklanmagan-natijalar.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Yuklanmagan natijalar
        </a>

        <a href="{{ $r('admin.yn-qaytnoma.index', 'teacher.yn-qaytnoma.index') }}"
           class="sidebar-link {{ $isActive('admin.yn-qaytnoma.*', 'teacher.yn-qaytnoma.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
            </svg>
            YN qaytnomasi
        </a>

        <a href="{{ $r('admin.academic-schedule.test-center', 'teacher.academic-schedule.test-center') }}"
           class="sidebar-link {{ $isActive('admin.academic-schedule.test-center', 'teacher.academic-schedule.test-center') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
            YN jadvali
        </a>

        <a href="{{ $r('admin.academic-schedule.bandlik-kursatkichi', 'teacher.academic-schedule.bandlik-kursatkichi') }}"
           class="sidebar-link {{ $isActive('admin.academic-schedule.bandlik-kursatkichi', 'teacher.academic-schedule.bandlik-kursatkichi') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
            </svg>
            Bandlik ko'rsatkichi
        </a>

        <a href="{{ route('admin.exam-appeals.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.exam-appeals.*') ? 'sidebar-active' : '' }}" style="position: relative;">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
            </svg>
            Apellyatsiyalar
        </a>
        @elseif($hasActiveRole(['oquv_bolimi', 'oquv_bolimi_boshligi']))
        {{-- O'quv bo'limi roli uchun --}}
        <div class="sidebar-section">O'quv bo'limi</div>

        <a href="{{ $r('admin.academic-schedule.index', 'teacher.academic-schedule.index') }}"
           class="sidebar-link {{ $isActive('admin.academic-schedule.index', 'teacher.academic-schedule.index') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
            YN kunini belgilash
        </a>
        @elseif(!$hasActiveRole(['oquv_bolimi', 'oquv_bolimi_boshligi', 'oqituvchi', 'fan_masuli']))
        {{-- Boshqa rollar uchun Qo'shimcha --}}
        @php
            $isTutorWithGroups = $hasActiveRole('tyutor') && auth()->guard('teacher')->check() && auth()->guard('teacher')->user()->groups()->where('active', true)->count() > 0;
        @endphp

        @if($isTutorWithGroups)
        <div class="sidebar-section">Hisobotlar</div>

        <a href="{{ route('teacher.reports.jn') }}"
           class="sidebar-link {{ request()->routeIs('teacher.reports.jn') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
            </svg>
            JN o'zlashtirish
        </a>

        <a href="{{ route('teacher.reports.absence-74') }}"
           class="sidebar-link {{ request()->routeIs('teacher.reports.absence-74') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            74 soat dars qoldirish
        </a>

        <a href="{{ route('teacher.reports.absence-25') }}"
           class="sidebar-link {{ request()->routeIs('teacher.reports.absence-25') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
            </svg>
            25% sababsiz
        </a>

        <a href="{{ route('teacher.reports.debtors') }}"
           class="sidebar-link {{ request()->routeIs('teacher.reports.debtors') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            4&#8805;qarzdorlar
        </a>

        <a href="{{ route('teacher.reports.top-students') }}"
           class="sidebar-link {{ request()->routeIs('teacher.reports.top-students') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
            </svg>
            5 ga da'vogar
        </a>

        <a href="{{ route('teacher.reports.unrated') }}"
           class="sidebar-link {{ request()->routeIs('teacher.reports.unrated') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
            </svg>
            Baho qo'ymaganlar
        </a>

        <a href="{{ route('teacher.reports.contracts') }}"
           class="sidebar-link {{ request()->routeIs('teacher.reports.contracts') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            Kontraktlar
        </a>

        @else
        <div class="sidebar-section">Qo'shimcha</div>

        <a href="{{ $r('admin.independent.index', 'teacher.independent.index') }}"
           class="sidebar-link {{ $isActive('admin.independent.*', 'teacher.independent.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
            </svg>
            Mustaqil ta'lim
        </a>

        <a href="{{ $r('admin.oraliqnazorat.index', 'teacher.oraliqnazorat.index') }}"
           class="sidebar-link {{ $isActive('admin.oraliqnazorat.*', 'teacher.oraliqnazorat.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
            </svg>
            Oraliq nazorat
        </a>

        <a href="{{ $r('admin.oski.index', 'teacher.oski.index') }}"
           class="sidebar-link {{ $isActive('admin.oski.*', 'teacher.oski.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
            </svg>
            OSKI
        </a>

        <a href="{{ $r('admin.examtest.index', 'teacher.examtest.index') }}"
           class="sidebar-link {{ $isActive('admin.examtest.*', 'teacher.examtest.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Test
        </a>

        <a href="{{ $r('admin.vedomost.index', 'teacher.vedomost.index') }}"
           class="sidebar-link {{ $isActive('admin.vedomost.*', 'teacher.vedomost.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            Vedomost
        </a>
        @endif

        @if($hasActiveRole(['superadmin', 'admin', 'kichik_admin', 'registrator_ofisi', 'oquv_bolimi', 'oquv_bolimi_boshligi', 'oquv_prorektori']))
        <a href="{{ route('admin.vedomost-tekshirish.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.vedomost-tekshirish.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
            </svg>
            Vedomost tekshirish
        </a>
        @endif

        @if($hasActiveRole(['superadmin', 'admin', 'kichik_admin']))
        <a href="{{ $r('admin.diagnostika.index', 'teacher.diagnostika.index') }}"
           class="sidebar-link {{ $isActive('admin.diagnostika.*', 'teacher.diagnostika.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17V7m0 10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2h2a2 2 0 012 2m0 10a2 2 0 002 2h2a2 2 0 002-2M9 7a2 2 0 012-2h2a2 2 0 012 2m0 10V7m0 10a2 2 0 002 2h2a2 2 0 002-2V7a2 2 0 00-2-2h-2a2 2 0 00-2 2"></path>
            </svg>
            Diagnostika
        </a>

        <a href="{{ $r('admin.saqlangan-hisobot.index', 'teacher.saqlangan-hisobot.index') }}"
           class="sidebar-link {{ $isActive('admin.saqlangan-hisobot.*', 'teacher.saqlangan-hisobot.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Yuklangan natijalar
        </a>

        <a href="{{ $r('admin.yuklanmagan-natijalar.index', 'teacher.yuklanmagan-natijalar.index') }}"
           class="sidebar-link {{ $isActive('admin.yuklanmagan-natijalar.*', 'teacher.yuklanmagan-natijalar.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Yuklanmagan natijalar
        </a>
        @endif
        @endif

        @if($hasActiveRole(['superadmin', 'admin', 'kichik_admin']))
        <div class="sidebar-section">Darslar</div>

        <a href="{{ route('admin.lessons.create') }}"
           class="sidebar-link {{ request()->routeIs('admin.lessons.create') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            Dars yaratish
        </a>

        <a href="{{ route('admin.lesson.histories-index') }}"
           class="sidebar-link {{ request()->routeIs('admin.lesson.histories-index') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Dars tarixi
        </a>
        @endif

        @if($hasActiveRole(['superadmin', 'admin', 'kichik_admin', 'oquv_bolimi', 'oquv_bolimi_boshligi']))
        <a href="{{ $r('admin.lecture-schedule.index', 'teacher.lecture-schedule.index') }}"
           class="sidebar-link {{ $isActive('admin.lecture-schedule.*', 'teacher.lecture-schedule.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
            Ma'ruza jadvali
        </a>
        @endif

        @if($hasActiveRole(['superadmin', 'admin', 'kichik_admin', 'oquv_bolimi', 'oquv_bolimi_boshligi', 'registrator_ofisi']))
        <a href="{{ $r('admin.timetable-view.index', 'teacher.timetable-view.index') }}"
           class="sidebar-link {{ $isActive('admin.timetable-view.*', 'teacher.timetable-view.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
            </svg>
            Dars jadvali
        </a>
        @endif

        @if($hasActiveRole(['superadmin', 'admin', 'kichik_admin']))
        <div class="sidebar-section">Monitoring</div>

        <a href="{{ route('admin.activity-log.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.activity-log.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
            </svg>
            Faoliyat jurnali
        </a>

        <a href="{{ route('admin.grade-history.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.grade-history.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Baholar tarixi
        </a>

        <a href="{{ route('admin.face-id.test') }}"
           class="sidebar-link {{ request()->routeIs('admin.face-id.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.069A1 1 0 0121 8.87v6.26a1 1 0 01-1.447.894L15 14M3 8a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"></path>
            </svg>
            Face ID Test
        </a>
        @endif

        @if(!$hasActiveRole(['test_markazi', 'oquv_bolimi', 'oquv_bolimi_boshligi', 'oqituvchi']))
        <a href="{{ $r('admin.qaytnoma.index', 'teacher.qaytnoma.index') }}"
           class="sidebar-link {{ $isActive('admin.qaytnoma.*', 'teacher.qaytnoma.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            YN oldi qaydnoma
        </a>
        @endif

        @if($hasActiveRole(['superadmin', 'admin', 'kichik_admin', 'registrator_ofisi', 'dekan', 'oquv_bolimi', 'oquv_bolimi_boshligi']))
        <div class="sidebar-section">Hisobotlar</div>

        @if(!$hasActiveRole(['oquv_bolimi', 'oquv_bolimi_boshligi']))
        <a href="{{ route('admin.reports.jn') }}"
           class="sidebar-link {{ request()->routeIs('admin.reports.jn') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
            </svg>
            JN o'zlashtirish
        </a>
        @endif

        <a href="{{ route('admin.reports.lesson-assignment') }}"
           class="sidebar-link {{ request()->routeIs('admin.reports.lesson-assignment*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
            </svg>
            Dars belgilash
        </a>

        <a href="{{ route('admin.reports.lesson-hours') }}"
           class="sidebar-link {{ request()->routeIs('admin.reports.lesson-hours*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Dars soati belgilash
        </a>

        @if(!$hasActiveRole(['dekan', 'oquv_bolimi', 'oquv_bolimi_boshligi']))
        <a href="{{ route('admin.reports.schedule-report') }}"
           class="sidebar-link {{ request()->routeIs('admin.reports.schedule-report*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
            Jadval KTR mosligi
        </a>
        @endif

        <a href="{{ route('admin.reports.auditorium-list') }}"
           class="sidebar-link {{ request()->routeIs('admin.reports.auditorium-list*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
            </svg>
            Auditoriyalar ro'yxati
        </a>

        @if(!$hasActiveRole(['oquv_bolimi', 'oquv_bolimi_boshligi']))
        <a href="{{ route('admin.absence_report.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.absence_report.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            74 soat dars qoldirish
        </a>

        <a href="{{ route('admin.reports.absence') }}"
           class="sidebar-link {{ request()->routeIs('admin.reports.absence') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
            </svg>
            25% sababsiz
        </a>

        @if(!$hasActiveRole(['dekan']))
        <a href="{{ route('admin.reports.load-vs-pair') }}"
           class="sidebar-link {{ request()->routeIs('admin.reports.load-vs-pair*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"></path>
            </svg>
            Yuklama vs Juftlik
        </a>
        @endif

        <a href="{{ route('admin.reports.debtors') }}"
           class="sidebar-link {{ request()->routeIs('admin.reports.debtors') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
            </svg>
            4≥qarzdorlar
        </a>

        <a href="{{ route('admin.reports.top-students') }}"
           class="sidebar-link {{ request()->routeIs('admin.reports.top-students') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path>
            </svg>
            5 ga da'vogar
        </a>

        @if(!$hasActiveRole(['dekan']))
        <a href="{{ route('admin.reports.sababli-check') }}"
           class="sidebar-link {{ request()->routeIs('admin.reports.sababli-check') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Sababli check
        </a>
        @endif

        <a href="{{ route('admin.reports.users-without-ratings') }}"
           class="sidebar-link {{ request()->routeIs('admin.reports.users-without-ratings') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
            </svg>
            Baho qo'ymaganlar
        </a>

        <a href="{{ route('admin.reports.grading-time-stats') }}"
           class="sidebar-link {{ request()->routeIs('admin.reports.grading-time-stats*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Vaqtlar statistikasi
        </a>

        <a href="{{ route('admin.reports.test-markazi-times') }}"
           class="sidebar-link {{ request()->routeIs('admin.reports.test-markazi-times*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
            </svg>
            Test markazi vaqtlari
        </a>

        @if($hasActiveRole(['superadmin', 'admin', 'kichik_admin', 'registrator_ofisi', 'buxgalteriya']))
        <a href="{{ route('admin.contracts.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.contracts.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            Kontraktlar
        </a>
        @endif

        @if($hasActiveRole(['superadmin', 'admin']))
        <a href="{{ route('admin.document-templates.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.document-templates.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z"></path>
            </svg>
            Shablonlar
        </a>
        @endif
        @endif

        @if($hasActiveRole(['superadmin', 'admin', 'kichik_admin', 'inspeksiya', 'registrator_ofisi']))
        <a href="{{ route('admin.examtest.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.examtest.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Test
        </a>
        @endif
        @endif

        {{-- ============ TALABA ARIZALARI SECTION ============ --}}
        @if($hasActiveRole(['superadmin', 'admin', 'kichik_admin', 'registrator_ofisi', 'dekan']))
        <div class="sidebar-section">Talaba arizalari</div>

        <a href="{{ route('admin.absence-excuses.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.absence-excuses.*') ? 'sidebar-active' : '' }}" style="position: relative;">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            Sababli arizalar
            @if(($pendingExcusesCount ?? 0) > 0)
                <span class="sidebar-badge">{{ $pendingExcusesCount }}</span>
            @endif
        </a>

        <a href="{{ route('admin.graduate-passports.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.graduate-passports.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path>
            </svg>
            Bitiruvchilar ma'lumotlari
        </a>

        <a href="{{ route('admin.exam-appeals.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.exam-appeals.*') ? 'sidebar-active' : '' }}" style="position: relative;">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m0-10.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285zm0 13.036h.008v.008H12v-.008z"></path>
            </svg>
            Apellyatsiyalar
            @if(($pendingAppealsCount ?? 0) > 0)
                <span class="sidebar-badge">{{ $pendingAppealsCount }}</span>
            @endif
        </a>

        <a href="{{ route('admin.student-contracts.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.student-contracts.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            Ishga joylashish shartnomasi
        </a>
        @endif

        {{-- ============ ISH E'LONLARI ============ --}}
        @if($hasActiveRole(['superadmin', 'admin', 'kichik_admin', 'registrator_ofisi']))
        <div class="sidebar-section">Ish e'lonlari</div>

        <a href="{{ route('admin.job-listings.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.job-listings.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 00.75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 00-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0112 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 01-.673-.38m0 0A2.18 2.18 0 013 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 013.413-.387m7.5 0V5.25A2.25 2.25 0 0013.5 3h-3a2.25 2.25 0 00-2.25 2.25v.894m7.5 0a48.667 48.667 0 00-7.5 0"></path>
            </svg>
            Ish e'lonlari
        </a>
        @endif

        {{-- ============ DB MA'LUMOTLAR ============ --}}
        @if($hasActiveRole(['superadmin', 'admin']))
        <a href="{{ route('admin.db-export.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.db-export.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125"/>
            </svg>
            DB ma'lumotlar
        </a>
        @endif

        {{-- ============ XODIMLARNI BAHOLASH ============ --}}
        @if($hasActiveRole('superadmin'))
        <a href="{{ route('admin.staff-evaluation.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.staff-evaluation.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
            </svg>
            Xodimlarni baholash
        </a>
        @endif

        @endif {{-- end if !javobgar_firma --}}

    </nav>

    <!-- User Section with Profile Dropdown -->
    <div class="p-3 flex-shrink-0 sidebar-user-section" x-data="{ profileOpen: false, rolesOpen: false }" @click.outside="profileOpen = false; rolesOpen = false">
        <!-- Dropdown Menu (fixed, opens upward) -->
        <div x-show="profileOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 transform translate-y-2"
             x-transition:enter-end="opacity-100 transform translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 transform translate-y-0"
             x-transition:leave-end="opacity-0 transform translate-y-2"
             class="fixed rounded-xl sidebar-dropdown"
             style="bottom: 80px; left: 12px; width: 232px; z-index: 9999; overflow: visible;">

            <!-- Profil -->
            @if($profileRoute)
            <div class="py-1 sidebar-dropdown-divider-bottom">
                <a href="{{ $profileRoute }}" class="profile-dropdown-link">
                    <svg class="w-4 h-4 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                    </svg>
                    Profil
                </a>
            </div>
            @endif

            <!-- Mavzu (Theme switcher) with cascade submenu -->
            <div class="py-1 sidebar-dropdown-divider-bottom" x-data="{ themeOpen: false }" style="position: relative;">
                <button @mouseenter="themeOpen = true" @mouseleave="themeOpen = false"
                        @click="themeOpen = !themeOpen"
                        class="profile-dropdown-link w-full text-left" style="justify-content: space-between;">
                    <span class="flex items-center">
                        <svg class="w-4 h-4 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
                        </svg>
                        Mavzu
                    </span>
                    <svg style="width: 14px; height: 14px; flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>

                <!-- Cascade submenu (opens to the right) -->
                <div x-show="themeOpen"
                     @mouseenter="themeOpen = true"
                     @mouseleave="themeOpen = false"
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 transform -translate-x-1"
                     x-transition:enter-end="opacity-100 transform translate-x-0"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     class="sidebar-submenu"
                     style="position: fixed; left: 248px; width: 170px; z-index: 10000;"
                     x-init="$nextTick(() => { positionSubmenu($el) })"
                     x-effect="if (themeOpen) { $nextTick(() => positionSubmenu($el)) }">
                    <button @click="setTheme('kosmik'); themeOpen = false;"
                            class="profile-dropdown-link w-full text-left" style="justify-content: space-between;">
                        <span class="flex items-center">
                            <svg class="w-4 h-4 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                            </svg>
                            Kosmik
                        </span>
                        <svg x-show="theme === 'kosmik'" class="w-4 h-4 flex-shrink-0 sidebar-check-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </button>
                    <button @click="setTheme('yorug'); themeOpen = false;"
                            class="profile-dropdown-link w-full text-left" style="justify-content: space-between;">
                        <span class="flex items-center">
                            <svg class="w-4 h-4 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                            </svg>
                            Yorug'
                        </span>
                        <svg x-show="theme === 'yorug'" class="w-4 h-4 flex-shrink-0 sidebar-check-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Rolni almashtirish (Switch role) with cascade submenu -->
            @if(count($userRoles) > 1)
            <div class="py-1 sidebar-dropdown-divider-bottom" x-data="{ rolesSubOpen: false }" style="position: relative;">
                <button @mouseenter="rolesSubOpen = true" @mouseleave="rolesSubOpen = false"
                        @click="rolesSubOpen = !rolesSubOpen"
                        class="profile-dropdown-link w-full text-left" style="justify-content: space-between;">
                    <span class="flex items-center">
                        <svg class="w-4 h-4 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                        </svg>
                        Rolni almashtirish...
                    </span>
                    <svg style="width: 14px; height: 14px; flex-shrink: 0;" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>

                <!-- Roles cascade submenu -->
                <div x-show="rolesSubOpen"
                     @mouseenter="rolesSubOpen = true"
                     @mouseleave="rolesSubOpen = false"
                     x-transition:enter="transition ease-out duration-150"
                     x-transition:enter-start="opacity-0 transform -translate-x-1"
                     x-transition:enter-end="opacity-100 transform translate-x-0"
                     x-transition:leave="transition ease-in duration-100"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     class="sidebar-submenu"
                     style="position: fixed; left: 248px; width: 200px; z-index: 10000;"
                     x-init="$nextTick(() => { positionSubmenu($el) })"
                     x-effect="if (rolesSubOpen) { $nextTick(() => positionSubmenu($el)) }">
                    @foreach($userRoles as $role)
                    <form method="POST" action="{{ $switchRoleRoute }}">
                        @csrf
                        <input type="hidden" name="role" value="{{ $role }}">
                        <button type="submit" class="profile-dropdown-link w-full text-left" style="justify-content: space-between;">
                            <span class="flex items-center">
                                {{ $roleLabels[$role] ?? $role }}
                            </span>
                            @if($role === $activeRole)
                            <svg class="w-4 h-4 flex-shrink-0 sidebar-check-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"></path>
                            </svg>
                            @endif
                        </button>
                    </form>
                    @endforeach
                </div>
            </div>
            @endif

            @if($hasActiveRole(['superadmin', 'admin', 'kichik_admin']))
            <!-- Sozlamalar -->
            <div class="py-1 sidebar-dropdown-divider-bottom">
                <a href="{{ route('admin.settings') }}" class="profile-dropdown-link">
                    <svg class="w-4 h-4 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    </svg>
                    Sozlamalar
                </a>
                <a href="{{ route('admin.staff-registration.index') }}" class="profile-dropdown-link">
                    <svg class="w-4 h-4 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                    </svg>
                    Registrator bo'linmalari
                </a>
            </div>
            @endif

            <!-- Chiqish -->
            <div class="py-1" style="border-radius: 0 0 12px 12px;">
                <form method="POST" action="{{ $logoutRoute }}">
                    @csrf
                    <button type="submit" class="profile-dropdown-link w-full text-left sidebar-logout-btn">
                        <svg class="w-4 h-4 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            @if($isImpersonating)
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 15l-3-3m0 0l3-3m-3 3h8M3 12a9 9 0 1118 0 9 9 0 01-18 0z"></path>
                            @else
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                            @endif
                        </svg>
                        {{ $isImpersonating ? 'Superadminga qaytish' : 'Chiqish' }}
                    </button>
                </form>
            </div>
        </div>

        <!-- Profile Button (clickable) - Rasm, ism va faol rol -->
        <button @click="profileOpen = !profileOpen" class="w-full flex items-center px-2 py-2 rounded-lg transition-all duration-200 sidebar-profile-btn cursor-pointer">
            @if($userAvatar)
            <img src="{{ $userAvatar }}" alt="{{ $userName }}" class="rounded-full object-cover mr-3 flex-shrink-0 sidebar-avatar-img" style="width: 36px; height: 36px; min-width: 36px;">
            @else
            <div class="rounded-full flex items-center justify-center mr-3 flex-shrink-0 sidebar-avatar" style="width: 36px; height: 36px; min-width: 36px;">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
            </div>
            @endif
            <div class="flex-1 text-left min-w-0">
                <span class="block truncate sidebar-username text-sm font-medium">{{ $userName }}</span>
                <span class="block truncate sidebar-role-label text-xs">{{ $activeRoleLabel }}</span>
            </div>
            <svg class="w-4 h-4 flex-shrink-0 transition-transform duration-200 sidebar-chevron" :class="{'rotate-180': profileOpen}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
            </svg>
        </button>
    </div>

    <style>
        /* ===== KOSMIK THEME (Dark - Default) ===== */
        .sidebar-themed[data-theme="kosmik"] {
            background: linear-gradient(180deg, #0c1929 0%, #142850 50%, #1a3268 100%);
            box-shadow: 4px 0 24px rgba(0,0,0,0.3);
        }
        .sidebar-themed[data-theme="kosmik"] .sidebar-logo-section {
            background-color: rgba(0,0,0,0.2);
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .sidebar-themed[data-theme="kosmik"] .sidebar-logo-img {
            border: 3px solid rgba(255,255,255,0.25);
            box-shadow: 0 4px 16px rgba(0,0,0,0.5);
        }
        .sidebar-themed[data-theme="kosmik"] .sidebar-logo-text {
            color: #ffffff;
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: 0.05em;
        }
        .sidebar-themed[data-theme="kosmik"] .sidebar-nav {
            scrollbar-width: thin;
            scrollbar-color: rgba(255,255,255,0.15) transparent;
        }
        .sidebar-themed[data-theme="kosmik"] .sidebar-link {
            color: rgba(255,255,255,0.75);
        }
        .sidebar-themed[data-theme="kosmik"] .sidebar-link:hover {
            background-color: rgba(255,255,255,0.08);
            color: #ffffff;
            border-left-color: rgba(255,255,255,0.3);
        }
        .sidebar-themed[data-theme="kosmik"] .sidebar-link.sidebar-active {
            background: linear-gradient(135deg, rgba(43,94,167,0.5), rgba(43,94,167,0.3));
            color: #ffffff;
            border-left-color: #60a5fa;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .sidebar-themed[data-theme="kosmik"] .sidebar-link.sidebar-active .sidebar-icon {
            color: #60a5fa;
        }
        .sidebar-themed[data-theme="kosmik"] .sidebar-icon {
            color: rgba(255,255,255,0.5);
        }
        .sidebar-themed[data-theme="kosmik"] .sidebar-link:hover .sidebar-icon {
            color: rgba(255,255,255,0.85);
        }
        .sidebar-themed[data-theme="kosmik"] .sidebar-section {
            color: rgba(96,165,250,0.6);
            border-top-color: rgba(255,255,255,0.05);
        }
        .sidebar-themed[data-theme="kosmik"] .sidebar-user-section {
            background-color: rgba(0,0,0,0.25);
            border-top: 1px solid rgba(255,255,255,0.08);
        }
        .sidebar-themed[data-theme="kosmik"] .sidebar-profile-btn:hover {
            background-color: rgba(255,255,255,0.1);
        }
        .sidebar-themed[data-theme="kosmik"] .sidebar-avatar {
            background: linear-gradient(135deg, #2b5ea7, #3b7ddb);
            color: #ffffff;
        }
        .sidebar-themed[data-theme="kosmik"] .sidebar-avatar-img {
            border: 2px solid rgba(255,255,255,0.25);
            box-shadow: 0 2px 8px rgba(0,0,0,0.3);
        }
        .sidebar-themed[data-theme="kosmik"] .sidebar-username {
            color: #ffffff;
            font-weight: 500;
            font-size: 0.875rem;
        }
        .sidebar-themed[data-theme="kosmik"] .sidebar-role-label {
            color: rgba(96,165,250,0.8);
            font-weight: 400;
        }
        .sidebar-themed[data-theme="kosmik"] .sidebar-chevron {
            color: rgba(255,255,255,0.5);
        }
        .sidebar-themed[data-theme="kosmik"] .sidebar-dropdown {
            background: #1e293b;
            border: 1px solid rgba(255,255,255,0.12);
            box-shadow: 0 -8px 32px rgba(0,0,0,0.5);
        }
        .sidebar-themed[data-theme="kosmik"] .sidebar-dropdown-header {
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .sidebar-themed[data-theme="kosmik"] .sidebar-dropdown-email {
            color: rgba(255,255,255,0.5);
            font-size: 0.75rem;
        }
        .sidebar-themed[data-theme="kosmik"] .sidebar-dropdown-divider {
            border-top: 1px solid rgba(255,255,255,0.08);
        }
        .sidebar-themed[data-theme="kosmik"] .sidebar-dropdown-divider-bottom {
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .sidebar-themed[data-theme="kosmik"] .profile-dropdown-link {
            color: rgba(255,255,255,0.8);
        }
        .sidebar-themed[data-theme="kosmik"] .profile-dropdown-link:hover {
            background-color: rgba(255,255,255,0.08);
            color: #ffffff;
        }
        .sidebar-themed[data-theme="kosmik"] .sidebar-logout-btn {
            color: #fca5a5 !important;
        }
        .sidebar-themed[data-theme="kosmik"] .sidebar-submenu {
            background: #1e293b;
            border: 1px solid rgba(255,255,255,0.12);
            box-shadow: 4px 0 24px rgba(0,0,0,0.4);
            border-radius: 10px;
            padding: 4px 0;
        }
        .sidebar-themed[data-theme="kosmik"] .sidebar-check-icon {
            color: #60a5fa;
        }

        /* ===== YORUG' THEME (Light) ===== */
        .sidebar-themed[data-theme="yorug"] {
            background: #ffffff;
            box-shadow: 2px 0 12px rgba(0,0,0,0.08);
        }
        .sidebar-themed[data-theme="yorug"] .sidebar-logo-section {
            background-color: #ffffff;
            border-bottom: 1px solid #e5e7eb;
        }
        .sidebar-themed[data-theme="yorug"] .sidebar-logo-img {
            border: 3px solid #e5e7eb;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .sidebar-themed[data-theme="yorug"] .sidebar-logo-text {
            color: #1f2937;
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: 0.05em;
        }
        .sidebar-themed[data-theme="yorug"] .sidebar-nav {
            scrollbar-width: thin;
            scrollbar-color: rgba(0,0,0,0.12) transparent;
        }
        .sidebar-themed[data-theme="yorug"] .sidebar-link {
            color: #4b5563;
        }
        .sidebar-themed[data-theme="yorug"] .sidebar-link:hover {
            background-color: #f3f4f6;
            color: #111827;
            border-left-color: #d1d5db;
        }
        .sidebar-themed[data-theme="yorug"] .sidebar-link.sidebar-active {
            background: #eff6ff;
            color: #1d4ed8;
            border-left-color: #3b82f6;
            box-shadow: none;
        }
        .sidebar-themed[data-theme="yorug"] .sidebar-link.sidebar-active .sidebar-icon {
            color: #3b82f6;
        }
        .sidebar-themed[data-theme="yorug"] .sidebar-icon {
            color: #9ca3af;
        }
        .sidebar-themed[data-theme="yorug"] .sidebar-link:hover .sidebar-icon {
            color: #6b7280;
        }
        .sidebar-themed[data-theme="yorug"] .sidebar-section {
            color: #3b82f6;
            border-top-color: #e5e7eb;
        }
        .sidebar-themed[data-theme="yorug"] .sidebar-user-section {
            background-color: #f9fafb;
            border-top: 1px solid #e5e7eb;
        }
        .sidebar-themed[data-theme="yorug"] .sidebar-profile-btn:hover {
            background-color: #f3f4f6;
        }
        .sidebar-themed[data-theme="yorug"] .sidebar-avatar {
            background: linear-gradient(135deg, #3b82f6, #60a5fa);
            color: #ffffff;
        }
        .sidebar-themed[data-theme="yorug"] .sidebar-avatar-img {
            border: 2px solid #e5e7eb;
            box-shadow: 0 1px 4px rgba(0,0,0,0.08);
        }
        .sidebar-themed[data-theme="yorug"] .sidebar-username {
            color: #1f2937;
            font-weight: 500;
            font-size: 0.875rem;
        }
        .sidebar-themed[data-theme="yorug"] .sidebar-role-label {
            color: #3b82f6;
            font-weight: 400;
        }
        .sidebar-themed[data-theme="yorug"] .sidebar-chevron {
            color: #9ca3af;
        }
        .sidebar-themed[data-theme="yorug"] .sidebar-dropdown {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            box-shadow: 0 -8px 32px rgba(0,0,0,0.12);
        }
        .sidebar-themed[data-theme="yorug"] .sidebar-dropdown-header {
            border-bottom: 1px solid #e5e7eb;
        }
        .sidebar-themed[data-theme="yorug"] .sidebar-dropdown-email {
            color: #6b7280;
            font-size: 0.75rem;
        }
        .sidebar-themed[data-theme="yorug"] .sidebar-dropdown-divider {
            border-top: 1px solid #e5e7eb;
        }
        .sidebar-themed[data-theme="yorug"] .sidebar-dropdown-divider-bottom {
            border-bottom: 1px solid #e5e7eb;
        }
        .sidebar-themed[data-theme="yorug"] .profile-dropdown-link {
            color: #4b5563;
        }
        .sidebar-themed[data-theme="yorug"] .profile-dropdown-link:hover {
            background-color: #f3f4f6;
            color: #111827;
        }
        .sidebar-themed[data-theme="yorug"] .sidebar-logout-btn {
            color: #ef4444 !important;
        }
        .sidebar-themed[data-theme="yorug"] .sidebar-submenu {
            background: #ffffff;
            border: 1px solid #e5e7eb;
            box-shadow: 4px 0 16px rgba(0,0,0,0.1);
            border-radius: 10px;
            padding: 4px 0;
        }
        .sidebar-themed[data-theme="yorug"] .sidebar-check-icon {
            color: #3b82f6;
        }

        /* ===== BADGE (notification count) ===== */
        .sidebar-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 20px;
            height: 20px;
            padding: 0 6px;
            margin-left: auto;
            font-size: 0.7rem;
            font-weight: 700;
            line-height: 1;
            color: #ffffff;
            background-color: #ef4444;
            border-radius: 10px;
            animation: badge-pulse 2s ease-in-out infinite;
        }
        @keyframes badge-pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.1); }
        }

        /* ===== BASE STYLES (shared) ===== */
        .sidebar-link {
            display: flex;
            align-items: center;
            padding: 10px 16px;
            margin-bottom: 2px;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 400;
            text-decoration: none;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }
        .sidebar-link.sidebar-active {
            font-weight: 600;
        }
        .sidebar-icon {
            transition: color 0.2s;
            flex-shrink: 0;
        }
        .sidebar-section {
            padding: 12px 16px 8px;
            margin-top: 12px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            border-top: 1px solid transparent;
        }
        .profile-dropdown-link {
            display: flex;
            align-items: center;
            padding: 10px 16px;
            font-size: 0.8rem;
            font-weight: 400;
            text-decoration: none;
            transition: all 0.15s ease;
            cursor: pointer;
            background: none;
            border: none;
        }

        /* ===== RESPONSIVE SIDEBAR ===== */
        .sidebar-themed {
            transform: translateX(-100%);
            transition: transform 0.2s ease-in-out;
        }
        .sidebar-themed.sidebar-open {
            transform: translateX(0);
        }
        .sidebar-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            z-index: 40;
        }
        .sidebar-close-btn {
            position: absolute;
            top: 12px;
            right: 12px;
            padding: 6px;
            border-radius: 8px;
            color: rgba(255,255,255,0.6);
            background: none;
            border: none;
            cursor: pointer;
            transition: all 0.15s;
        }
        .sidebar-close-btn:hover {
            color: #fff;
            background: rgba(255,255,255,0.1);
        }
        .sidebar-themed[data-theme="yorug"] .sidebar-close-btn {
            color: #9ca3af;
        }
        .sidebar-themed[data-theme="yorug"] .sidebar-close-btn:hover {
            color: #111827;
            background: #f3f4f6;
        }
        .mobile-top-bar {
            position: sticky;
            top: 0;
            z-index: 30;
            display: flex;
            align-items: center;
            background: #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            padding: 12px 16px;
        }
        .mobile-top-bar .hamburger-btn {
            padding: 8px;
            border-radius: 8px;
            color: #4b5563;
            background: none;
            border: none;
            cursor: pointer;
        }
        .mobile-top-bar .hamburger-btn:hover {
            background: #f3f4f6;
        }

        /* Desktop (>=768px): sidebar always visible */
        @media (min-width: 768px) {
            .sidebar-themed {
                transform: translateX(0) !important;
            }
            .sidebar-main-content {
                margin-left: 256px;
            }
            .mobile-top-bar {
                display: none !important;
            }
            .sidebar-backdrop {
                display: none !important;
            }
            .sidebar-close-btn {
                display: none !important;
            }
        }

        /* Mobile (<768px): no margin, full width */
        @media (max-width: 767px) {
            .sidebar-main-content {
                margin-left: 0 !important;
            }
            .desktop-only-header {
                display: none !important;
            }
        }
    </style>

    <script>
        // Alpine.js global store for sidebar toggle
        document.addEventListener('alpine:init', () => {
            Alpine.store('sidebar', {
                open: false,
                toggle() {
                    this.open = !this.open;
                },
                close() {
                    this.open = false;
                }
            });
        });

        // Cascade submenu pozitsiyasini aniqlash - viewport ga sig'masa tepaga ochadi
        function positionSubmenu(el) {
            const btn = el.previousElementSibling;
            if (!btn) return;
            const btnRect = btn.getBoundingClientRect();
            const elHeight = el.offsetHeight || el.scrollHeight;
            const viewportH = window.innerHeight;
            // Pastga sig'adimi?
            if (btnRect.top + elHeight > viewportH - 10) {
                // Sig'masa — pastki chegaraga yopishtirish
                el.style.top = 'auto';
                el.style.bottom = '10px';
            } else {
                el.style.bottom = 'auto';
                el.style.top = (btnRect.top - 4) + 'px';
            }
        }

        function sidebarTheme() {
            return {
                theme: localStorage.getItem('sidebar-theme') || 'kosmik',
                setTheme(name) {
                    this.theme = name;
                    localStorage.setItem('sidebar-theme', name);
                }
            }
        }

        // Sidebar scroll position saqlab qolish
        document.addEventListener('DOMContentLoaded', function () {
            const nav = document.querySelector('.sidebar-nav');
            if (!nav) return;

            // Sahifa yuklanganda oldingi scroll pozitsiyasini tiklash
            const saved = sessionStorage.getItem('sidebar-scroll');
            if (saved !== null) {
                nav.scrollTop = parseInt(saved, 10);
            }

            // Sahifa tark etilishidan oldin scroll pozitsiyasini saqlash
            window.addEventListener('beforeunload', function () {
                sessionStorage.setItem('sidebar-scroll', nav.scrollTop);
            });
        });
    </script>
</aside>
