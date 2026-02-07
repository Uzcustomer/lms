<nav x-data="{ open: false }" class="bg-white border-b border-gray-100 dark:bg-gray-800 dark:border-gray-700">
    <!-- Primary Navigation Menu -->
    <div class="px-4 mx-auto max-w-7xl sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="flex items-center shrink-0">
                    <a href="{{ route('admin.dashboard') }}">
                        <x-application-logo class="block w-auto text-gray-800 fill-current h-9 dark:text-gray-200" />
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>
                </div>
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('admin.students.index')"
                        :active="request()->routeIs('admin.students.index')">
                        {{ __('Talabalar') }}
                    </x-nav-link>
                </div>

                @if( auth()->user()->hasRole(['superadmin', 'admin', 'kichik_admin']))
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.index')">
                        {{ __('Foydalanuvchilar') }}
                    </x-nav-link>
                </div>
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('admin.teachers.index')"
                        :active="request()->routeIs('admin.teachers.index')">
                        {{ __("Xodimlar") }}
                    </x-nav-link>
                </div>
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('admin.student-grades-week')"
                        :active="request()->routeIs('admin.student-grades-week')">
                        {{ __('Baholar') }}
                    </x-nav-link>
                </div>
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('admin.journal.index')"
                        :active="request()->routeIs('admin.journal.*')">
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
                                <x-dropdown-link :href="route('admin.independent.index')"
                                    :active="request()->routeIs('admin.independent.index')">
                                    {{ __('Mustaqil ta\'lim') }}
                                </x-dropdown-link>
                                <x-dropdown-link :href="route('admin.oraliqnazorat.index')"
                                    :active="request()->routeIs('admin.oraliqnazorat.index')">
                                    Oraliq nazorat
                                </x-dropdown-link>
                                <x-dropdown-link :href="route('admin.oski.index')"
                                    :active="request()->routeIs('admin.oski.index')">
                                    OSKI
                                </x-dropdown-link>
                                <x-dropdown-link :href="route('admin.examtest.index')"
                                    :active="request()->routeIs('admin.examtest.index')">
                                    Test
                                </x-dropdown-link>
                                <x-dropdown-link :href="route('admin.vedomost.index')"
                                    :active="request()->routeIs('admin.vedomost.index')">
                                    Vedomost
                                </x-dropdown-link>
                            </x-slot>
                        </x-dropdown>
                    </div>
                </div>
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <div class="flex items-center">
                        <x-dropdown align="right" width="48">
                            <x-slot name="trigger">
                                <button
                                    class="inline-flex items-center px-1 pt-1 text-sm font-medium leading-5 text-gray-500 transition duration-150 ease-in-out border-b-2 border-transparent hover:text-gray-700 hover:border-gray-300">
                                    {{ __('Darslar') }}
                                    <svg class="w-4 h-4 ms-1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd"
                                            d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                            clip-rule="evenodd" />
                                    </svg>
                                </button>
                            </x-slot>

                            <x-slot name="content">
                                <x-dropdown-link :href="route('admin.lessons.create')"
                                    :active="request()->routeIs('admin.lessons.create')">
                                    {{ __('Dars yaratish') }}
                                </x-dropdown-link>
                                <x-dropdown-link :href="route('admin.lesson.histories-index')"
                                    :active="request()->routeIs('admin.lesson.histories-index')">
                                    {{ __('Dars yaratishlar tarixi') }}
                                </x-dropdown-link>
                            </x-slot>
                        </x-dropdown>
                    </div>
                </div>

                @endif

                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('admin.qaytnoma.index')"
                        :active="request()->routeIs('admin.qaytnoma.index')">
                        YN oldi qaydnoma
                    </x-nav-link>
                </div>
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('admin.absence_report.index')"
                        :active="request()->routeIs('admin.absence_report.*')">
                        74 soat dars qoldirish
                    </x-nav-link>
                </div>
                @if( auth()->user()->hasRole(['superadmin', 'admin', 'kichik_admin', 'inspeksiya']))

                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('admin.examtest.index')"
                        :active="request()->routeIs('admin.examtest.index')">
                        Test
                    </x-nav-link>
                </div>
                @endif
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button
                            class="inline-flex items-center px-3 py-2 text-sm font-medium leading-4 text-gray-500 transition duration-150 ease-in-out bg-white border border-transparent rounded-md dark:text-gray-400 dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none">
                            <div>{{ Auth::user()->name }}</div>

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
                        @if( auth()->user()->hasRole(['superadmin', 'admin', 'kichik_admin']))
                        <x-dropdown-link :href="route('admin.deadlines')">
                            {{ __('Deadline') }}
                        </x-dropdown-link>
                        @endif

                        @if( auth()->user()->hasRole(['superadmin', 'admin', 'kichik_admin']))
                        <x-dropdown-link :href="route('admin.synchronizes')">
                            {{ __('Sinxronizatsiya') }}
                        </x-dropdown-link>
                        @endif
                        <!-- Authentication -->
                        <form method="POST" action="{{ route('admin.logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('admin.logout')" onclick="event.preventDefault();
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
            <x-responsive-nav-link :href="route('admin.dashboard')" :active="request()->routeIs('admin.dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('admin.students.index')"
                :active="request()->routeIs('admin.students.index')">
                {{ __('Talabalar') }}
            </x-responsive-nav-link>
            @if( auth()->user()->hasRole(['superadmin', 'admin', 'kichik_admin']))
            <x-responsive-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.index')">
                {{ __('Foydalanuvchilar') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('admin.teachers.index')"
                :active="request()->routeIs('admin.teachers.index')">
                {{ __('Xodimlar') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('admin.student-grades-week')"
                :active="request()->routeIs('admin.student-grades-week')">
                {{ __('Baholar') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('admin.journal.index')"
                :active="request()->routeIs('admin.journal.*')">
                {{ __('Jurnal') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('admin.independent.index')"
                :active="request()->routeIs('admin.independent.index')">
                {{ __('Mustaqil ta\'lim') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('admin.oraliqnazorat.index')"
                :active="request()->routeIs('admin.oraliqnazorat.index')">
                Oraliq nazorat
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('admin.oski.index')" :active="request()->routeIs('admin.oski.index')">
                OSKI
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('admin.examtest.index')"
                :active="request()->routeIs('admin.examtest.index')">
                Test
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('admin.vedomost.index')"
                :active="request()->routeIs('admin.vedomost.index')">
                Vedomost
            </x-responsive-nav-link>


            <div class="pt-2 pb-3 space-y-1" x-data="{ open: false }">
                <button @click="open = !open"
                    class="w-full px-3 py-2 text-base font-medium text-left text-gray-600 transition duration-150 ease-in-out border-l-4 border-transparent hover:text-gray-800 hover:bg-gray-50 hover:border-gray-300 focus:outline-none focus:text-gray-800 focus:bg-gray-50 focus:border-gray-300">
                    <div class="flex items-center justify-between">
                        <span>{{ __('Darslar') }}</span>
                        <svg class="w-4 h-4 transform" :class="{'rotate-180': open}" xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd"
                                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                clip-rule="evenodd" />
                        </svg>
                    </div>
                </button>

                <div x-show="open" class="pl-4">
                    <x-responsive-nav-link :href="route('admin.lessons.create')"
                        :active="request()->routeIs('admin.lessons.create')">
                        {{ __('Dars') }}
                    </x-responsive-nav-link>
                    <x-responsive-nav-link :href="route('admin.lesson.histories-index')"
                        :active="request()->routeIs('admin.lesson.histories-index')">
                        {{ __('Tarix') }}
                    </x-responsive-nav-link>
                </div>
            </div>
            @endif
            <x-responsive-nav-link :href="route('admin.qaytnoma.index')"
                :active="request()->routeIs('admin.qaytnoma.index')">
                YN oldi qaytnoma

            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('admin.absence_report.index')"
                :active="request()->routeIs('admin.absence_report.*')">
                74 soat dars qoldirish
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('admin.examtest.index')"
                :active="request()->routeIs('admin.examtest.index')">
                Test
            </x-responsive-nav-link>

        </div>


        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-600">
            <div class="px-4">
                <div class="text-base font-medium text-gray-800 dark:text-gray-200">{{ Auth::user()->name }}</div>
                <div class="text-sm font-medium text-gray-500">{{ Auth::user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                {{--                <x-responsive-nav-link :href="route('profile.edit')">--}}
                {{--                    {{ __('Profile') }}--}}
                {{--                </x-responsive-nav-link>--}}

                <!-- Authentication -->
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('admin.logout')" onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
