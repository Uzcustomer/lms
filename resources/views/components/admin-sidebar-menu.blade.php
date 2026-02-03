<aside class="w-64 bg-gradient-to-b from-[#1e3a8a] to-[#1e40af] text-white flex flex-col min-h-screen fixed left-0 top-0 overflow-y-auto shadow-xl z-50">
    <!-- Logo Section -->
    <div class="p-6 flex flex-col items-center bg-[#172554]">
        <img src="{{ asset('logo.png') }}" alt="Logo" class="w-20 h-20 rounded-full border-4 border-white/20 shadow-lg mb-3">
        <h1 class="text-2xl font-bold tracking-wide">LMS</h1>
    </div>

    <!-- Navigation Menu -->
    <nav class="flex-1 py-4 px-3">
        <a href="{{ route('admin.dashboard') }}"
           class="flex items-center px-4 py-3 mb-1 rounded-lg transition-all duration-200 {{ request()->routeIs('admin.dashboard') ? 'bg-white/20 text-white font-semibold' : 'text-white/90 hover:bg-white/10 hover:text-white' }}">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
            </svg>
            <span>Dashboard</span>
        </a>

        @if(auth()->user()->hasRole(['admin']))
        <a href="{{ route('admin.journal.index') }}"
           class="flex items-center px-4 py-3 mb-1 rounded-lg transition-all duration-200 {{ request()->routeIs('admin.journal.*') ? 'bg-white/20 text-white font-semibold' : 'text-white/90 hover:bg-white/10 hover:text-white' }}">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <span>Jurnal</span>
        </a>
        @endif

        <a href="{{ route('admin.students.index') }}"
           class="flex items-center px-4 py-3 mb-1 rounded-lg transition-all duration-200 {{ request()->routeIs('admin.students.*') ? 'bg-white/20 text-white font-semibold' : 'text-white/90 hover:bg-white/10 hover:text-white' }}">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
            </svg>
            <span>Talabalar</span>
        </a>

        @if(auth()->user()->hasRole(['admin']))
        <a href="{{ route('admin.users.index') }}"
           class="flex items-center px-4 py-3 mb-1 rounded-lg transition-all duration-200 {{ request()->routeIs('admin.users.*') ? 'bg-white/20 text-white font-semibold' : 'text-white/90 hover:bg-white/10 hover:text-white' }}">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
            </svg>
            <span>Foydalanuvchilar</span>
        </a>

        <a href="{{ route('admin.teachers.index') }}"
           class="flex items-center px-4 py-3 mb-1 rounded-lg transition-all duration-200 {{ request()->routeIs('admin.teachers.*') ? 'bg-white/20 text-white font-semibold' : 'text-white/90 hover:bg-white/10 hover:text-white' }}">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
            </svg>
            <span>O'qituvchilar</span>
        </a>

        <a href="{{ route('admin.student-grades-week') }}"
           class="flex items-center px-4 py-3 mb-1 rounded-lg transition-all duration-200 {{ request()->routeIs('admin.student-grades-week') ? 'bg-white/20 text-white font-semibold' : 'text-white/90 hover:bg-white/10 hover:text-white' }}">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
            </svg>
            <span>Baholar</span>
        </a>

        <!-- QO'SHIMCHA Section -->
        <div class="px-4 py-3 mt-4 text-xs font-bold text-white/60 uppercase tracking-wider">Qo'shimcha</div>

        <a href="{{ route('admin.independent.index') }}"
           class="flex items-center px-4 py-3 mb-1 rounded-lg transition-all duration-200 {{ request()->routeIs('admin.independent.*') ? 'bg-white/20 text-white font-semibold' : 'text-white/90 hover:bg-white/10 hover:text-white' }}">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
            </svg>
            <span>Mustaqil ta'lim</span>
        </a>

        <a href="{{ route('admin.oraliqnazorat.index') }}"
           class="flex items-center px-4 py-3 mb-1 rounded-lg transition-all duration-200 {{ request()->routeIs('admin.oraliqnazorat.*') ? 'bg-white/20 text-white font-semibold' : 'text-white/90 hover:bg-white/10 hover:text-white' }}">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
            </svg>
            <span>Oraliq nazorat</span>
        </a>

        <a href="{{ route('admin.oski.index') }}"
           class="flex items-center px-4 py-3 mb-1 rounded-lg transition-all duration-200 {{ request()->routeIs('admin.oski.*') ? 'bg-white/20 text-white font-semibold' : 'text-white/90 hover:bg-white/10 hover:text-white' }}">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
            </svg>
            <span>OSKI</span>
        </a>

        <a href="{{ route('admin.examtest.index') }}"
           class="flex items-center px-4 py-3 mb-1 rounded-lg transition-all duration-200 {{ request()->routeIs('admin.examtest.*') ? 'bg-white/20 text-white font-semibold' : 'text-white/90 hover:bg-white/10 hover:text-white' }}">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span>Test</span>
        </a>

        <a href="{{ route('admin.vedomost.index') }}"
           class="flex items-center px-4 py-3 mb-1 rounded-lg transition-all duration-200 {{ request()->routeIs('admin.vedomost.*') ? 'bg-white/20 text-white font-semibold' : 'text-white/90 hover:bg-white/10 hover:text-white' }}">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <span>Vedomost</span>
        </a>

        <!-- DARSLAR Section -->
        <div class="px-4 py-3 mt-4 text-xs font-bold text-white/60 uppercase tracking-wider">Darslar</div>

        <a href="{{ route('admin.lessons.create') }}"
           class="flex items-center px-4 py-3 mb-1 rounded-lg transition-all duration-200 {{ request()->routeIs('admin.lessons.create') ? 'bg-white/20 text-white font-semibold' : 'text-white/90 hover:bg-white/10 hover:text-white' }}">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            <span>Dars yaratish</span>
        </a>

        <a href="{{ route('admin.lesson.histories-index') }}"
           class="flex items-center px-4 py-3 mb-1 rounded-lg transition-all duration-200 {{ request()->routeIs('admin.lesson.histories-index') ? 'bg-white/20 text-white font-semibold' : 'text-white/90 hover:bg-white/10 hover:text-white' }}">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span>Dars tarixi</span>
        </a>
        @endif

        <a href="{{ route('admin.qaytnoma.index') }}"
           class="flex items-center px-4 py-3 mb-1 rounded-lg transition-all duration-200 {{ request()->routeIs('admin.qaytnoma.*') ? 'bg-white/20 text-white font-semibold' : 'text-white/90 hover:bg-white/10 hover:text-white' }}">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <span>YN oldi qaydnoma</span>
        </a>

        @if(auth()->user()->hasRole(['examiner']))
        <a href="{{ route('admin.examtest.index') }}"
           class="flex items-center px-4 py-3 mb-1 rounded-lg transition-all duration-200 {{ request()->routeIs('admin.examtest.*') ? 'bg-white/20 text-white font-semibold' : 'text-white/90 hover:bg-white/10 hover:text-white' }}">
            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <span>Test</span>
        </a>
        @endif
    </nav>

    <!-- User Section -->
    <div class="p-4 bg-[#172554] border-t border-white/10">
        <div class="flex items-center mb-3 px-2">
            <div class="w-10 h-10 rounded-full bg-white/20 flex items-center justify-center mr-3">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
            </div>
            <span class="font-medium text-white">{{ Auth::user()->name }}</span>
        </div>
        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit" class="w-full px-4 py-2.5 text-sm font-semibold text-white bg-red-500 rounded-lg hover:bg-red-600 transition-colors duration-200 flex items-center justify-center">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                </svg>
                Chiqish
            </button>
        </form>
    </div>
</aside>
