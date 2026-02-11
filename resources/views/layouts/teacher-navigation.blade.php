<nav x-data="{ open: false }" class="bg-white border-b border-gray-100 dark:bg-gray-800 dark:border-gray-700">
    <!-- Primary Navigation Menu -->
    <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="flex items-center shrink-0">
                    <a href="{{ route('teacher.dashboard') }}">
                        <x-application-logo class="block w-auto text-gray-800 fill-current h-9 dark:text-gray-200" />
                    </a>
                </div>

                <!-- Navigation Links -->

                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('teacher.dashboard')" :active="request()->routeIs('teacher.dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>
                </div>
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('teacher.students')" :active="request()->routeIs('teacher.students')">
                        {{ __('Talabalar') }}
                    </x-nav-link>
                </div>
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('teacher.student-grades-week-teacher')"
                        :active="request()->routeIs('teacher.student-grades-week-teacher')">
                        {{ __('Baholar') }}
                    </x-nav-link>
                </div>
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('admin.journal.index')" :active="request()->routeIs('admin.journal.*')">
                        {{ __('Jurnal') }}
                    </x-nav-link>
                </div>
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <div class="flex items-center">
                        <x-dropdown align="right" width="48">
                            <x-slot name="trigger">
                                <button
                                    class="inline-flex items-center px-1 pt-1 text-sm font-medium leading-5 text-gray-500 transition duration-150 ease-in-out border-b-2 border-transparent hover:text-gray-700 hover:border-gray-300">
                                    Qo'shimcha
                                    <svg class="w-4 h-4 ms-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </x-slot>
                            <x-slot name="content">
                                <x-dropdown-link :href="route('teacher.independent.index')"
                                    :active="request()->routeIs('teacher.independent.index')">
                                    {{ __('Mustaqil ta\'lim') }}
                                </x-dropdown-link>
                                <x-dropdown-link :href="route('teacher.oraliqnazorat.index')"
                                    :active="request()->routeIs('teacher.oraliqnazorat.index')">
                                    Oraliq nazorat
                                </x-dropdown-link>
                                @if( auth()->user()->hasRole(['dekan']))
                                    <x-dropdown-link :href="route('teacher.oski.index')"
                                        :active="request()->routeIs('teacher.oski.index')">
                                        OSKI
                                    </x-dropdown-link>
                                    <x-dropdown-link :href="route('teacher.examtest.index')"
                                        :active="request()->routeIs('teacher.examtest.index')">
                                        Test
                                    </x-dropdown-link>
                                    <x-dropdown-link :href="route('teacher.qaytnoma.index')"
                                        :active="request()->routeIs('teacher.qaytnoma.index')">
                                        YN oldi qaydnoma
                                    </x-dropdown-link>
                                    <x-dropdown-link :href="route('teacher.vedomost.index')"
                                        :active="request()->routeIs('teacher.vedomost.index')">
                                        Vedomost
                                    </x-dropdown-link>
                                @endif

                            </x-slot>
                        </x-dropdown>
                    </div>
                </div>
                @if(auth()->guard('teacher')->user()->hasRole(['registrator_ofisi']))
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <div class="flex items-center">
                        <x-dropdown align="right" width="48">
                            <x-slot name="trigger">
                                <button
                                    class="inline-flex items-center px-1 pt-1 text-sm font-medium leading-5 text-indigo-600 transition duration-150 ease-in-out border-b-2 border-transparent hover:text-indigo-800 hover:border-indigo-300">
                                    Registrator
                                    <svg class="w-4 h-4 ms-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd"
                                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </x-slot>
                            <x-slot name="content">
                                <x-dropdown-link :href="route('admin.journal.index')">
                                    Jurnal
                                </x-dropdown-link>
                                <x-dropdown-link :href="route('admin.students.index')">
                                    Talabalar
                                </x-dropdown-link>
                                <div class="border-t border-gray-200 my-1"></div>
                                <x-dropdown-link :href="route('admin.reports.jn')">
                                    JN o'zlashtirish
                                </x-dropdown-link>
                                <x-dropdown-link :href="route('admin.reports.lesson-assignment')">
                                    Dars belgilash
                                </x-dropdown-link>
                                <x-dropdown-link :href="route('admin.reports.schedule-report')">
                                    Dars jadval mosligi
                                </x-dropdown-link>
                                <x-dropdown-link :href="route('admin.absence_report.index')">
                                    74 soat dars qoldirish
                                </x-dropdown-link>
                                <x-dropdown-link :href="route('admin.reports.absence')">
                                    25% sababsiz
                                </x-dropdown-link>
                                <x-dropdown-link :href="route('admin.reports.load-vs-pair')">
                                    Yuklama vs Juftlik
                                </x-dropdown-link>
                                <x-dropdown-link :href="route('admin.reports.debtors')">
                                    4≥qarzdorlar
                                </x-dropdown-link>
                                <x-dropdown-link :href="route('admin.reports.sababli-check')">
                                    Sababli check
                                </x-dropdown-link>
                            </x-slot>
                        </x-dropdown>
                    </div>
                </div>
                @endif
                <!-- <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('teacher.student-grades.index')"
                                :active="request()->routeIs('teacher.student-grades.index')">
                        {{ __('Baholar') }} NEW
                    </x-nav-link>
                </div> -->
                {{--                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">--}}
                {{--                    <x-nav-link :href="route('teacher.subjects')" :active="request()->routeIs('teacher.subjects')">--}}
                {{--                        {{ __('Fanlar') }}--}}
                {{--                    </x-nav-link>--}}
                {{--                </div>--}}
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button
                            class="inline-flex items-center px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out bg-white border border-transparent rounded-md dark:text-gray-400 dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none">
                            <div>{{ Auth::guard('teacher')->user()->short_name }}</div>

                            <div class="ms-1">
                                <svg class="w-4 h-4 fill-current" xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20">
                                    <path fill-rule="evenodd"
                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                        clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <!-- Rollar -->
                        @php
                            $teacherRoles = Auth::guard('teacher')->user()->getRoleNames();
                            $roleLabels = [
                                'oqituvchi' => "O'qituvchi",
                                'registrator_ofisi' => 'Registrator ofisi',
                                'dekan' => 'Dekan',
                                'kafedra_mudiri' => 'Kafedra mudiri',
                                'fan_masuli' => "Fan mas'uli",
                                'superadmin' => 'Superadmin',
                                'admin' => 'Admin',
                                'kichik_admin' => 'Kichik admin',
                                'inspeksiya' => 'Inspeksiya',
                                'oquv_prorektori' => "O'quv prorektori",
                                'oquv_bolimi' => "O'quv bo'limi",
                                'buxgalteriya' => 'Buxgalteriya',
                                'manaviyat' => "Ma'naviyat",
                                'tyutor' => 'Tyutor',
                            ];
                        @endphp
                        @if($teacherRoles->count() > 1)
                        <div class="px-4 py-2 border-b border-gray-200">
                            <p class="text-xs text-gray-500 mb-1">Rollar:</p>
                            <div class="flex flex-wrap gap-1">
                                @foreach($teacherRoles as $role)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                        {{ $role === 'registrator_ofisi' ? 'bg-indigo-100 text-indigo-800' : 'bg-gray-100 text-gray-700' }}">
                                        {{ $roleLabels[$role] ?? $role }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        <x-dropdown-link :href="route('teacher.info-me')">
                            {{ __('Profile') }}
                        </x-dropdown-link>
                        <x-dropdown-link :href="route('teacher.edit_credentials')">
                            {{ __('Login va Parol') }}
                        </x-dropdown-link>

                        @if(Auth::guard('teacher')->user()->hasRole('registrator_ofisi'))
                        <div class="border-t border-gray-200"></div>
                        <x-dropdown-link :href="route('admin.dashboard')" class="text-indigo-600 font-medium">
                            <svg class="w-4 h-4 mr-1 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            Boshqaruv paneli
                        </x-dropdown-link>
                        @endif

                        <!-- Authentication -->
                        <div class="border-t border-gray-200"></div>
                        <form method="POST" action="{{ route('teacher.logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('teacher.logout')" onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                {{ __('Log Out') }}
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="flex items-center -me-2 sm:hidden">
                <button @click="open = ! open"
                    class="inline-flex items-center justify-center p-2 text-gray-400 transition duration-150 ease-in-out rounded-md dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-900 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-900 focus:text-gray-500 dark:focus:text-gray-400">
                    <svg class="w-6 h-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex"
                            stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round"
                            stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('teacher.dashboard')" :active="request()->routeIs('teacher.dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
            {{--            <x-responsive-nav-link :href="route('teacher.students')" :active="request()->routeIs('teacher.students')">--}}
            {{--                {{ __('Talabalar') }}--}}
            {{--            </x-responsive-nav-link>--}}
            {{--            <x-responsive-nav-link :href="route('teacher.subjects')" :active="request()->routeIs('teacher.subjects')">--}}
            {{--                {{ __('Fanlar') }}--}}
            {{--            </x-responsive-nav-link>--}}
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-600">
            <div class="px-4">
                <div class="text-base font-medium text-gray-800 dark:text-gray-200">
                    {{ Auth::guard('teacher')->user()->short_name }}</div>
                <div class="text-sm font-medium text-gray-500">{{ Auth::guard('teacher')->user()->login }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('teacher.dashboard')"
                    :active="request()->routeIs('teacher.dashboard')">
                    {{ __('Dashboard') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('teacher.info-me')">
                    {{ __('Profile') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('teacher.edit_credentials')">
                    {{ __('Login va Parol') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('teacher.student-grades-week-teacher')"
                    :active="request()->routeIs('teacher.student-grades-week-teacher')">
                    {{ __('Baholar') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.journal.index')" :active="request()->routeIs('admin.journal.*')">
                    {{ __('Jurnal') }}
                </x-responsive-nav-link>
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('teacher.independent.index')"
                        :active="request()->routeIs('teacher.independent.index')">
                        {{ __('Mustaqil ta\'lim') }}
                    </x-nav-link>
                </div>
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('teacher.oraliqnazorat.index')"
                        :active="request()->routeIs('teacher.oraliqnazorat.index')">
                        Oraliq nazorat
                    </x-nav-link>
                </div>

                @if( auth()->user()->hasRole(['dekan']))
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('teacher.oski.index')" :active="request()->routeIs('teacher.oski.index')">
                        OSKI
                    </x-nav-link>
                </div>
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('teacher.examtest.index')"
                        :active="request()->routeIs('teacher.examtest.index')">
                        Test
                    </x-nav-link>
                </div>
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('teacher.qaytnoma.index')"
                        :active="request()->routeIs('teacher.qaytnoma.index')">
                        YN oldi qaydnoma
                    </x-nav-link>
                </div>
                @endif

                @if(auth()->guard('teacher')->user()->hasRole(['registrator_ofisi']))
                <div class="border-t border-gray-200 pt-2 mt-2">
                    <div class="px-4 text-xs font-semibold text-indigo-600 uppercase">Registrator</div>
                </div>
                <x-responsive-nav-link :href="route('admin.journal.index')">
                    Jurnal
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.students.index')">
                    Talabalar
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.reports.jn')">
                    JN o'zlashtirish
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.reports.lesson-assignment')">
                    Dars belgilash
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.reports.schedule-report')">
                    Dars jadval mosligi
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.absence_report.index')">
                    74 soat dars qoldirish
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.reports.absence')">
                    25% sababsiz
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.reports.load-vs-pair')">
                    Yuklama vs Juftlik
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.reports.debtors')">
                    4≥qarzdorlar
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.reports.sababli-check')">
                    Sababli check
                </x-responsive-nav-link>
                @endif

                <!-- Authentication -->
                <form method="POST" action="{{ route('teacher.logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('teacher.logout')" onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
