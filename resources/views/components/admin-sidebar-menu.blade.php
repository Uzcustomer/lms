<aside x-data="sidebarTheme()" :data-theme="theme"
       class="sidebar-themed w-64 flex flex-col fixed left-0 top-0 z-50"
       style="height: 100vh;">
    <!-- Logo Section -->
    <div class="p-4 flex flex-col items-center flex-shrink-0 sidebar-logo-section">
        <img src="{{ asset('logo.png') }}" alt="Logo" class="w-16 h-16 rounded-full mb-2 sidebar-logo-img">
        <h1 class="sidebar-logo-text">LMS</h1>
    </div>

    <!-- Navigation Menu -->
    <nav class="flex-1 py-3 px-3 overflow-y-auto sidebar-nav">
        <a href="{{ route('admin.dashboard') }}"
           class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
            </svg>
            Dashboard
        </a>

        @if(auth()->user()->hasRole(['superadmin', 'admin', 'kichik_admin']))
        <a href="{{ route('admin.journal.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.journal.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            Jurnal
        </a>
        @endif

        <a href="{{ route('admin.students.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.students.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
            </svg>
            Talabalar
        </a>

        @if(auth()->user()->hasRole(['superadmin', 'admin', 'kichik_admin']))
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

        <div class="sidebar-section">Qo'shimcha</div>

        <a href="{{ route('admin.independent.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.independent.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
            </svg>
            Mustaqil ta'lim
        </a>

        <a href="{{ route('admin.oraliqnazorat.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.oraliqnazorat.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
            </svg>
            Oraliq nazorat
        </a>

        <a href="{{ route('admin.oski.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.oski.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
            </svg>
            OSKI
        </a>

        <a href="{{ route('admin.examtest.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.examtest.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Test
        </a>

        <a href="{{ route('admin.vedomost.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.vedomost.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            Vedomost
        </a>

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

        <a href="{{ route('admin.qaytnoma.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.qaytnoma.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            YN oldi qaydnoma
        </a>

        <div class="sidebar-section">Hisobotlar</div>

        <a href="{{ route('admin.reports.jn') }}"
           class="sidebar-link {{ request()->routeIs('admin.reports.jn') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
            </svg>
            JN o'zlashtirish
        </a>

        <a href="{{ route('admin.reports.lesson-assignment') }}"
           class="sidebar-link {{ request()->routeIs('admin.reports.lesson-assignment*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
            </svg>
            Dars belgilash
        </a>

        <a href="{{ route('admin.reports.schedule-report') }}"
           class="sidebar-link {{ request()->routeIs('admin.reports.schedule-report*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
            </svg>
            Dars jadval mosligi
        </a>

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

        <a href="{{ route('admin.reports.debtors') }}"
           class="sidebar-link {{ request()->routeIs('admin.reports.debtors') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
            </svg>
            4≥qarzdorlar
        </a>

        @if(auth()->user()->hasRole(['superadmin', 'admin', 'kichik_admin', 'inspeksiya']))
        <a href="{{ route('admin.examtest.index') }}"
           class="sidebar-link {{ request()->routeIs('admin.examtest.*') ? 'sidebar-active' : '' }}">
            <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            Test
        </a>
        @endif
    </nav>

    <!-- User Section with Profile Dropdown -->
    <div class="p-3 flex-shrink-0 sidebar-user-section" x-data="{ profileOpen: false }" @click.outside="profileOpen = false">
        <!-- Dropdown Menu (fixed, opens upward) -->
        <div x-show="profileOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 transform translate-y-2"
             x-transition:enter-end="opacity-100 transform translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 transform translate-y-0"
             x-transition:leave-end="opacity-0 transform translate-y-2"
             class="fixed rounded-xl sidebar-dropdown"
             style="bottom: 70px; left: 12px; width: 232px; z-index: 9999; overflow: visible;">

            <!-- User email/info -->
            <div class="px-4 py-3 sidebar-dropdown-header" style="border-radius: 12px 12px 0 0;">
                <p class="sidebar-dropdown-email">{{ Auth::user()->email ?? Auth::user()->name }}</p>
            </div>

            <!-- Theme switcher with cascade submenu -->
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
                     x-init="$nextTick(() => {
                         const el = $el;
                         const parent = $el.previousElementSibling;
                         if (parent) {
                             const rect = parent.getBoundingClientRect();
                             el.style.top = (rect.top - 4) + 'px';
                         }
                     })"
                     x-effect="if (themeOpen) {
                         const parent = $el.previousElementSibling;
                         if (parent) {
                             const rect = parent.getBoundingClientRect();
                             $el.style.top = (rect.top - 4) + 'px';
                         }
                     }">
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

            @if(auth()->user()->hasRole(['superadmin', 'admin', 'kichik_admin']))
            <!-- Settings links -->
            <div class="py-1">
                <a href="{{ route('admin.deadlines') }}" class="profile-dropdown-link">
                    <svg class="w-4 h-4 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    Deadline
                </a>
                <a href="{{ route('admin.password-settings.index') }}" class="profile-dropdown-link">
                    <svg class="w-4 h-4 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                    </svg>
                    Parol sozlamalari
                </a>
                <a href="{{ route('admin.synchronizes') }}" class="profile-dropdown-link">
                    <svg class="w-4 h-4 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                    Sinxronizatsiya
                </a>
            </div>

            <div class="sidebar-dropdown-divider"></div>
            @endif

            <!-- Logout -->
            <div class="py-1" style="border-radius: 0 0 12px 12px;">
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="profile-dropdown-link w-full text-left sidebar-logout-btn">
                        <svg class="w-4 h-4 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        Chiqish
                    </button>
                </form>
            </div>
        </div>

        <!-- Profile Button (clickable) -->
        <button @click="profileOpen = !profileOpen" class="w-full flex items-center px-2 py-2 rounded-lg transition-all duration-200 sidebar-profile-btn cursor-pointer">
            <div class="w-9 h-9 rounded-full flex items-center justify-center mr-3 flex-shrink-0 sidebar-avatar">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
            </div>
            <div class="flex-1 text-left min-w-0">
                <span class="block truncate sidebar-username">{{ Auth::user()->name }}</span>
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
        .sidebar-themed[data-theme="kosmik"] .sidebar-username {
            color: #ffffff;
            font-weight: 500;
            font-size: 0.875rem;
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
        .sidebar-themed[data-theme="yorug"] .sidebar-username {
            color: #1f2937;
            font-weight: 500;
            font-size: 0.875rem;
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
    </style>

    <script>
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
