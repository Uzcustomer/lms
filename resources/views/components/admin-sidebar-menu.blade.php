<aside class="w-64 flex flex-col fixed left-0 top-0 shadow-xl z-50" style="background: linear-gradient(180deg, #1e3a8a 0%, #1e40af 100%); height: 100vh;">
    <!-- Logo Section (Fixed at top) -->
    <div class="p-4 flex flex-col items-center flex-shrink-0" style="background-color: #172554; border-bottom: 1px solid rgba(255,255,255,0.1);">
        <img src="{{ asset('logo.png') }}" alt="Logo" class="w-16 h-16 rounded-full mb-2" style="border: 3px solid rgba(255,255,255,0.3); box-shadow: 0 4px 6px rgba(0,0,0,0.3);">
        <h1 style="color: #ffffff; font-size: 1.25rem; font-weight: 700; letter-spacing: 0.05em;">LMS</h1>
    </div>

    <!-- Navigation Menu (Scrollable) -->
    <nav class="flex-1 py-3 px-3 overflow-y-auto" style="scrollbar-width: thin; scrollbar-color: rgba(255,255,255,0.3) transparent;">
        <a href="{{ route('admin.dashboard') }}"
           class="flex items-center px-4 py-3 mb-1 rounded-lg"
           style="color: #ffffff; {{ request()->routeIs('admin.dashboard') ? 'background-color: rgba(255,255,255,0.2); font-weight: 600;' : '' }}"
           onmouseover="this.style.backgroundColor='rgba(255,255,255,0.15)'"
           onmouseout="this.style.backgroundColor='{{ request()->routeIs('admin.dashboard') ? 'rgba(255,255,255,0.2)' : 'transparent' }}'">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
            </svg>
            <span style="color: #ffffff;">Dashboard</span>
        </a>

        @if(auth()->user()->hasRole(['admin']))
        <a href="{{ route('admin.journal.index') }}"
           class="flex items-center px-4 py-3 mb-1 rounded-lg"
           style="color: #ffffff; {{ request()->routeIs('admin.journal.*') ? 'background-color: rgba(255,255,255,0.2); font-weight: 600;' : '' }}"
           onmouseover="this.style.backgroundColor='rgba(255,255,255,0.15)'"
           onmouseout="this.style.backgroundColor='{{ request()->routeIs('admin.journal.*') ? 'rgba(255,255,255,0.2)' : 'transparent' }}'">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <span style="color: #ffffff;">Jurnal</span>
        </a>
        @endif

        <a href="{{ route('admin.students.index') }}"
           class="flex items-center px-4 py-3 mb-1 rounded-lg"
           style="color: #ffffff; {{ request()->routeIs('admin.students.*') ? 'background-color: rgba(255,255,255,0.2); font-weight: 600;' : '' }}"
           onmouseover="this.style.backgroundColor='rgba(255,255,255,0.15)'"
           onmouseout="this.style.backgroundColor='{{ request()->routeIs('admin.students.*') ? 'rgba(255,255,255,0.2)' : 'transparent' }}'">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
            </svg>
            <span style="color: #ffffff;">Talabalar</span>
        </a>

        @if(auth()->user()->hasRole(['admin']))
        <a href="{{ route('admin.users.index') }}"
           class="flex items-center px-4 py-3 mb-1 rounded-lg"
           style="color: #ffffff; {{ request()->routeIs('admin.users.*') ? 'background-color: rgba(255,255,255,0.2); font-weight: 600;' : '' }}"
           onmouseover="this.style.backgroundColor='rgba(255,255,255,0.15)'"
           onmouseout="this.style.backgroundColor='{{ request()->routeIs('admin.users.*') ? 'rgba(255,255,255,0.2)' : 'transparent' }}'">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
            </svg>
            <span style="color: #ffffff;">Foydalanuvchilar</span>
        </a>

        <a href="{{ route('admin.teachers.index') }}"
           class="flex items-center px-4 py-3 mb-1 rounded-lg"
           style="color: #ffffff; {{ request()->routeIs('admin.teachers.*') ? 'background-color: rgba(255,255,255,0.2); font-weight: 600;' : '' }}"
           onmouseover="this.style.backgroundColor='rgba(255,255,255,0.15)'"
           onmouseout="this.style.backgroundColor='{{ request()->routeIs('admin.teachers.*') ? 'rgba(255,255,255,0.2)' : 'transparent' }}'">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
            </svg>
            <span style="color: #ffffff;">O'qituvchilar</span>
        </a>

        <a href="{{ route('admin.student-grades-week') }}"
           class="flex items-center px-4 py-3 mb-1 rounded-lg"
           style="color: #ffffff; {{ request()->routeIs('admin.student-grades-week') ? 'background-color: rgba(255,255,255,0.2); font-weight: 600;' : '' }}"
           onmouseover="this.style.backgroundColor='rgba(255,255,255,0.15)'"
           onmouseout="this.style.backgroundColor='{{ request()->routeIs('admin.student-grades-week') ? 'rgba(255,255,255,0.2)' : 'transparent' }}'">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
            </svg>
            <span style="color: #ffffff;">Baholar</span>
        </a>

        <!-- QO'SHIMCHA Section -->
        <div class="px-4 py-3 mt-4" style="color: rgba(255,255,255,0.6); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em;">Qo'shimcha</div>

        <a href="{{ route('admin.independent.index') }}"
           class="flex items-center px-4 py-3 mb-1 rounded-lg"
           style="color: #ffffff; {{ request()->routeIs('admin.independent.*') ? 'background-color: rgba(255,255,255,0.2); font-weight: 600;' : '' }}"
           onmouseover="this.style.backgroundColor='rgba(255,255,255,0.15)'"
           onmouseout="this.style.backgroundColor='{{ request()->routeIs('admin.independent.*') ? 'rgba(255,255,255,0.2)' : 'transparent' }}'">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
            </svg>
            <span style="color: #ffffff;">Mustaqil ta'lim</span>
        </a>

        <a href="{{ route('admin.oraliqnazorat.index') }}"
           class="flex items-center px-4 py-3 mb-1 rounded-lg"
           style="color: #ffffff; {{ request()->routeIs('admin.oraliqnazorat.*') ? 'background-color: rgba(255,255,255,0.2); font-weight: 600;' : '' }}"
           onmouseover="this.style.backgroundColor='rgba(255,255,255,0.15)'"
           onmouseout="this.style.backgroundColor='{{ request()->routeIs('admin.oraliqnazorat.*') ? 'rgba(255,255,255,0.2)' : 'transparent' }}'">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
            </svg>
            <span style="color: #ffffff;">Oraliq nazorat</span>
        </a>

        <a href="{{ route('admin.oski.index') }}"
           class="flex items-center px-4 py-3 mb-1 rounded-lg"
           style="color: #ffffff; {{ request()->routeIs('admin.oski.*') ? 'background-color: rgba(255,255,255,0.2); font-weight: 600;' : '' }}"
           onmouseover="this.style.backgroundColor='rgba(255,255,255,0.15)'"
           onmouseout="this.style.backgroundColor='{{ request()->routeIs('admin.oski.*') ? 'rgba(255,255,255,0.2)' : 'transparent' }}'">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
            </svg>
            <span style="color: #ffffff;">OSKI</span>
        </a>

        <a href="{{ route('admin.examtest.index') }}"
           class="flex items-center px-4 py-3 mb-1 rounded-lg"
           style="color: #ffffff; {{ request()->routeIs('admin.examtest.*') ? 'background-color: rgba(255,255,255,0.2); font-weight: 600;' : '' }}"
           onmouseover="this.style.backgroundColor='rgba(255,255,255,0.15)'"
           onmouseout="this.style.backgroundColor='{{ request()->routeIs('admin.examtest.*') ? 'rgba(255,255,255,0.2)' : 'transparent' }}'">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span style="color: #ffffff;">Test</span>
        </a>

        <a href="{{ route('admin.vedomost.index') }}"
           class="flex items-center px-4 py-3 mb-1 rounded-lg"
           style="color: #ffffff; {{ request()->routeIs('admin.vedomost.*') ? 'background-color: rgba(255,255,255,0.2); font-weight: 600;' : '' }}"
           onmouseover="this.style.backgroundColor='rgba(255,255,255,0.15)'"
           onmouseout="this.style.backgroundColor='{{ request()->routeIs('admin.vedomost.*') ? 'rgba(255,255,255,0.2)' : 'transparent' }}'">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <span style="color: #ffffff;">Vedomost</span>
        </a>

        <!-- DARSLAR Section -->
        <div class="px-4 py-3 mt-4" style="color: rgba(255,255,255,0.6); font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em;">Darslar</div>

        <a href="{{ route('admin.lessons.create') }}"
           class="flex items-center px-4 py-3 mb-1 rounded-lg"
           style="color: #ffffff; {{ request()->routeIs('admin.lessons.create') ? 'background-color: rgba(255,255,255,0.2); font-weight: 600;' : '' }}"
           onmouseover="this.style.backgroundColor='rgba(255,255,255,0.15)'"
           onmouseout="this.style.backgroundColor='{{ request()->routeIs('admin.lessons.create') ? 'rgba(255,255,255,0.2)' : 'transparent' }}'">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            <span style="color: #ffffff;">Dars yaratish</span>
        </a>

        <a href="{{ route('admin.lesson.histories-index') }}"
           class="flex items-center px-4 py-3 mb-1 rounded-lg"
           style="color: #ffffff; {{ request()->routeIs('admin.lesson.histories-index') ? 'background-color: rgba(255,255,255,0.2); font-weight: 600;' : '' }}"
           onmouseover="this.style.backgroundColor='rgba(255,255,255,0.15)'"
           onmouseout="this.style.backgroundColor='{{ request()->routeIs('admin.lesson.histories-index') ? 'rgba(255,255,255,0.2)' : 'transparent' }}'">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span style="color: #ffffff;">Dars tarixi</span>
        </a>
        @endif

        <a href="{{ route('admin.qaytnoma.index') }}"
           class="flex items-center px-4 py-3 mb-1 rounded-lg"
           style="color: #ffffff; {{ request()->routeIs('admin.qaytnoma.*') ? 'background-color: rgba(255,255,255,0.2); font-weight: 600;' : '' }}"
           onmouseover="this.style.backgroundColor='rgba(255,255,255,0.15)'"
           onmouseout="this.style.backgroundColor='{{ request()->routeIs('admin.qaytnoma.*') ? 'rgba(255,255,255,0.2)' : 'transparent' }}'">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <span style="color: #ffffff;">YN oldi qaydnoma</span>
        </a>

        @if(auth()->user()->hasRole(['examiner']))
        <a href="{{ route('admin.examtest.index') }}"
           class="flex items-center px-4 py-3 mb-1 rounded-lg"
           style="color: #ffffff; {{ request()->routeIs('admin.examtest.*') ? 'background-color: rgba(255,255,255,0.2); font-weight: 600;' : '' }}"
           onmouseover="this.style.backgroundColor='rgba(255,255,255,0.15)'"
           onmouseout="this.style.backgroundColor='{{ request()->routeIs('admin.examtest.*') ? 'rgba(255,255,255,0.2)' : 'transparent' }}'">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span style="color: #ffffff;">Test</span>
        </a>
        @endif
    </nav>

    <!-- User Section (Fixed at bottom) -->
    <div class="p-3 flex-shrink-0" style="background-color: #172554; border-top: 1px solid rgba(255,255,255,0.1);">
        <div class="flex items-center mb-2 px-2">
            <div class="w-8 h-8 rounded-full flex items-center justify-center mr-2" style="background-color: rgba(255,255,255,0.2);">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: #ffffff;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
            </div>
            <span style="color: #ffffff; font-weight: 500; font-size: 0.875rem;">{{ Auth::user()->name }}</span>
        </div>
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit" class="w-full px-3 py-2 rounded-lg flex items-center justify-center" style="background-color: #dc2626; color: #ffffff; font-size: 0.8rem; font-weight: 600;">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="color: #ffffff;">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                </svg>
                Chiqish
            </button>
        </form>
    </div>
</aside>
