<aside class="w-64 flex flex-col fixed left-0 top-0 z-50" style="background: linear-gradient(180deg, #0c1929 0%, #142850 50%, #1a3268 100%); height: 100vh; box-shadow: 4px 0 24px rgba(0,0,0,0.3);">
    <!-- Logo Section -->
    <div class="p-4 flex flex-col items-center flex-shrink-0" style="background-color: rgba(0,0,0,0.2); border-bottom: 1px solid rgba(255,255,255,0.08);">
        <img src="{{ asset('logo.png') }}" alt="Logo" class="w-16 h-16 rounded-full mb-2" style="border: 3px solid rgba(255,255,255,0.25); box-shadow: 0 4px 16px rgba(0,0,0,0.5);">
        <h1 style="color: #ffffff; font-size: 1.25rem; font-weight: 700; letter-spacing: 0.05em;">LMS</h1>
    </div>

    <!-- Navigation Menu -->
    <nav class="flex-1 py-3 px-3 overflow-y-auto" style="scrollbar-width: thin; scrollbar-color: rgba(255,255,255,0.15) transparent;">
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
    <div class="p-3 flex-shrink-0 relative" x-data="{ profileOpen: false }" @click.outside="profileOpen = false" style="background-color: rgba(0,0,0,0.25); border-top: 1px solid rgba(255,255,255,0.08);">
        <!-- Dropdown Menu (opens upward) -->
        <div x-show="profileOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 translate-y-2"
             class="absolute bottom-full left-3 right-3 mb-2 rounded-xl overflow-hidden"
             style="background: #1e293b; border: 1px solid rgba(255,255,255,0.12); box-shadow: 0 -8px 32px rgba(0,0,0,0.4);">

            <!-- User email/info -->
            <div class="px-4 py-3" style="border-bottom: 1px solid rgba(255,255,255,0.08);">
                <p style="color: rgba(255,255,255,0.5); font-size: 0.75rem;">{{ Auth::user()->email ?? Auth::user()->name }}</p>
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

            <div style="border-top: 1px solid rgba(255,255,255,0.08);"></div>
            @endif

            <!-- Logout -->
            <div class="py-1">
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="profile-dropdown-link w-full text-left" style="color: #fca5a5;">
                        <svg class="w-4 h-4 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                        </svg>
                        Chiqish
                    </button>
                </form>
            </div>
        </div>

        <!-- Profile Button (clickable) -->
        <button @click="profileOpen = !profileOpen" class="w-full flex items-center px-2 py-2 rounded-lg transition-all duration-200 hover:bg-white/10 cursor-pointer">
            <div class="w-9 h-9 rounded-full flex items-center justify-center mr-3 flex-shrink-0" style="background: linear-gradient(135deg, #2b5ea7, #3b7ddb);">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: #ffffff;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
            </div>
            <div class="flex-1 text-left min-w-0">
                <span class="block truncate" style="color: #ffffff; font-weight: 500; font-size: 0.875rem;">{{ Auth::user()->name }}</span>
            </div>
            <svg class="w-4 h-4 flex-shrink-0 transition-transform duration-200" :class="{'rotate-180': profileOpen}" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: rgba(255,255,255,0.5);">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
            </svg>
        </button>
    </div>

    <style>
        .sidebar-link {
            display: flex;
            align-items: center;
            padding: 10px 16px;
            margin-bottom: 2px;
            border-radius: 8px;
            color: rgba(255,255,255,0.75);
            font-size: 0.875rem;
            font-weight: 400;
            text-decoration: none;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }
        .sidebar-link:hover {
            background-color: rgba(255,255,255,0.08);
            color: #ffffff;
            border-left-color: rgba(255,255,255,0.3);
        }
        .sidebar-link.sidebar-active {
            background: linear-gradient(135deg, rgba(43,94,167,0.5), rgba(43,94,167,0.3));
            color: #ffffff;
            font-weight: 600;
            border-left-color: #60a5fa;
            box-shadow: 0 2px 8px rgba(0,0,0,0.15);
        }
        .sidebar-link.sidebar-active .sidebar-icon {
            color: #60a5fa;
        }
        .sidebar-icon {
            color: rgba(255,255,255,0.5);
            transition: color 0.2s;
            flex-shrink: 0;
        }
        .sidebar-link:hover .sidebar-icon {
            color: rgba(255,255,255,0.85);
        }
        .sidebar-section {
            padding: 12px 16px 8px;
            margin-top: 12px;
            color: rgba(96,165,250,0.6);
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.12em;
            border-top: 1px solid rgba(255,255,255,0.05);
        }
        .profile-dropdown-link {
            display: flex;
            align-items: center;
            padding: 10px 16px;
            color: rgba(255,255,255,0.8);
            font-size: 0.8rem;
            font-weight: 400;
            text-decoration: none;
            transition: all 0.15s ease;
            cursor: pointer;
            background: none;
            border: none;
        }
        .profile-dropdown-link:hover {
            background-color: rgba(255,255,255,0.08);
            color: #ffffff;
        }
    </style>
</aside>
