<aside class="flex h-screen">
    <div class="flex flex-col w-72 bg-gradient-to-b from-blue-900 via-blue-800 to-blue-700 text-blue-100">
        <div class="flex items-center gap-3 px-6 py-6 border-b border-blue-700">
            <div class="flex items-center justify-center w-10 h-10 text-xl font-bold text-white bg-blue-600 rounded-lg">
                LMS
            </div>
            <div>
                <p class="text-lg font-semibold text-white">Admin Panel</p>
                <p class="text-xs text-blue-200">Boshqaruv paneli</p>
            </div>
        </div>

        <nav class="flex-1 px-4 py-6 space-y-1 overflow-y-auto">
            <a href="{{ route('admin.dashboard') }}"
                class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.dashboard') ? 'bg-blue-950 text-white' : 'text-blue-100 hover:bg-blue-800' }}">
                <span class="text-base">🏠</span>
                Dashboard
            </a>
            <a href="{{ route('admin.students.index') }}"
                class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.students.index') ? 'bg-blue-950 text-white' : 'text-blue-100 hover:bg-blue-800' }}">
                <span class="text-base">🎓</span>
                Talabalar
            </a>

            @if(auth()->user()->hasRole(['admin']))
                <a href="{{ route('admin.users.index') }}"
                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.users.index') ? 'bg-blue-950 text-white' : 'text-blue-100 hover:bg-blue-800' }}">
                    <span class="text-base">👥</span>
                    Foydalanuvchilar
                </a>
                <a href="{{ route('admin.teachers.index') }}"
                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.teachers.index') ? 'bg-blue-950 text-white' : 'text-blue-100 hover:bg-blue-800' }}">
                    <span class="text-base">🧑‍🏫</span>
                    O'qituvchilar
                </a>
                <a href="{{ route('admin.student-grades-week') }}"
                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.student-grades-week') ? 'bg-blue-950 text-white' : 'text-blue-100 hover:bg-blue-800' }}">
                    <span class="text-base">📊</span>
                    Baholar
                </a>
                <a href="{{ route('admin.journal.index') }}"
                    class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.journal.*') ? 'bg-blue-950 text-white' : 'text-blue-100 hover:bg-blue-800' }}">
                    <span class="text-base">📒</span>
                    Jurnal
                </a>
            @endif

            <div class="pt-4">
                <p class="px-3 text-xs font-semibold tracking-wide text-blue-200 uppercase">Qo'shimcha</p>
                <div class="mt-2 space-y-1">
                    <a href="{{ route('admin.independent.index') }}"
                        class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.independent.index') ? 'bg-blue-950 text-white' : 'text-blue-100 hover:bg-blue-800' }}">
                        <span class="text-base">📝</span>
                        Mustaqil ta'lim
                    </a>
                    <a href="{{ route('admin.oraliqnazorat.index') }}"
                        class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.oraliqnazorat.index') ? 'bg-blue-950 text-white' : 'text-blue-100 hover:bg-blue-800' }}">
                        <span class="text-base">⏱️</span>
                        Oraliq nazorat
                    </a>
                    <a href="{{ route('admin.oski.index') }}"
                        class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.oski.index') ? 'bg-blue-950 text-white' : 'text-blue-100 hover:bg-blue-800' }}">
                        <span class="text-base">✅</span>
                        OSKI
                    </a>
                    <a href="{{ route('admin.examtest.index') }}"
                        class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.examtest.index') ? 'bg-blue-950 text-white' : 'text-blue-100 hover:bg-blue-800' }}">
                        <span class="text-base">🧪</span>
                        Test
                    </a>
                    <a href="{{ route('admin.vedomost.index') }}"
                        class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.vedomost.index') ? 'bg-blue-950 text-white' : 'text-blue-100 hover:bg-blue-800' }}">
                        <span class="text-base">📄</span>
                        Vedomost
                    </a>
                </div>
            </div>

            <div class="pt-4">
                <p class="px-3 text-xs font-semibold tracking-wide text-blue-200 uppercase">Darslar</p>
                <div class="mt-2 space-y-1">
                    <a href="{{ route('admin.lessons.create') }}"
                        class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.lessons.create') ? 'bg-blue-950 text-white' : 'text-blue-100 hover:bg-blue-800' }}">
                        <span class="text-base">➕</span>
                        Dars yaratish
                    </a>
                    <a href="{{ route('admin.lesson.histories-index') }}"
                        class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.lesson.histories-index') ? 'bg-blue-950 text-white' : 'text-blue-100 hover:bg-blue-800' }}">
                        <span class="text-base">🗂️</span>
                        Dars yaratishlar tarixi
                    </a>
                </div>
            </div>

            <a href="{{ route('admin.qaytnoma.index') }}"
                class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.qaytnoma.index') ? 'bg-blue-950 text-white' : 'text-blue-100 hover:bg-blue-800' }}">
                <span class="text-base">📌</span>
                YN oldi qaydnoma
            </a>

            @if(auth()->user()->hasRole(['admin']))
                <div class="pt-4">
                    <p class="px-3 text-xs font-semibold tracking-wide text-blue-200 uppercase">Admin</p>
                    <div class="mt-2 space-y-1">
                        <a href="{{ route('admin.deadlines') }}"
                            class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.deadlines') ? 'bg-blue-950 text-white' : 'text-blue-100 hover:bg-blue-800' }}">
                            <span class="text-base">⏳</span>
                            Deadline
                        </a>
                        <a href="{{ route('admin.synchronizes') }}"
                            class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs('admin.synchronizes') ? 'bg-blue-950 text-white' : 'text-blue-100 hover:bg-blue-800' }}">
                            <span class="text-base">🔄</span>
                            Sinxronizatsiya
                        </a>
                    </div>
                </div>
            @endif
        </nav>

        <div class="px-6 py-4 border-t border-blue-700">
            <div class="flex items-center gap-3 mb-3">
                <div class="flex items-center justify-center w-10 h-10 text-sm font-semibold text-white bg-blue-600 rounded-full">
                    {{ strtoupper(mb_substr(Auth::user()->name ?? 'A', 0, 1)) }}
                </div>
                <div>
                    <p class="text-sm font-semibold text-white">{{ Auth::user()->name ?? 'Admin' }}</p>
                    <p class="text-xs text-blue-200">Tizimga kirgan</p>
                </div>
            </div>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit"
                    class="flex items-center justify-center w-full gap-2 px-4 py-2 text-sm font-semibold text-white transition bg-red-500 rounded-lg hover:bg-red-400">
                    <span>Logout</span>
                </button>
            </form>
        </div>
    </div>
</aside>
