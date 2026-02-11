<nav x-data="{ open: false }" class="bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('student.dashboard') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800 dark:text-gray-200" />
                    </a>
                </div>

                <!-- Navigation Links (Desktop) -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('student.dashboard')" :active="request()->routeIs('student.dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>
                    <x-nav-link :href="route('student.schedule')" :active="request()->routeIs('student.schedule')">
                        {{ __('Dars jadvali') }}
                    </x-nav-link>
                    <x-nav-link :href="route('student.attendance')" :active="request()->routeIs('student.attendance')">
                        {{ __('Davomat') }}
                    </x-nav-link>
                    <x-nav-link :href="route('student.subjects')" :active="request()->routeIs('student.subjects')">
                        {{ __('Joriy fanlar') }}
                    </x-nav-link>
                    <x-nav-link :href="route('student.pending-lessons')" :active="request()->routeIs('student.pending-lessons')">
                        {{ __('Qayta topshirish fanlari') }}
                    </x-nav-link>
                    <x-nav-link :href="route('student.independents')" :active="request()->routeIs('student.independents')">
                        {{ __('Mustaqil ta\'lim') }}
                    </x-nav-link>
                </div>
            </div>

            <!-- Settings Dropdown (Desktop) -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none transition ease-in-out duration-150">
                            <div>{{ Auth::guard('student')->user()->full_name }}</div>
                            <div class="ms-1">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('student.profile')">
                            {{ __('Talaba ma\'lumoti') }}
                        </x-dropdown-link>
                        @if(session('impersonating'))
                            <form method="POST" action="{{ route('impersonate.stop') }}">
                                @csrf
                                <x-dropdown-link :href="route('impersonate.stop')"
                                                 onclick="event.preventDefault();
                                                    this.closest('form').submit();"
                                                 class="text-red-600">
                                    {{ __('Orqaga qaytish (Admin)') }}
                                </x-dropdown-link>
                            </form>
                        @else
                            <form method="POST" action="{{ route('student.logout') }}">
                                @csrf
                                <x-dropdown-link :href="route('student.logout')"
                                                 onclick="event.preventDefault();
                                                    this.closest('form').submit();">
                                    {{ __('Chiqish') }}
                                </x-dropdown-link>
                            </form>
                        @endif
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger (Mobile) -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-900 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-900 focus:text-gray-500 dark:focus:text-gray-400 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu (Mobile) -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('student.dashboard')" :active="request()->routeIs('student.dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('student.schedule')" :active="request()->routeIs('student.schedule')">
                {{ __('Dars jadvali') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('student.attendance')" :active="request()->routeIs('student.attendance')">
                {{ __('Davomat') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('student.subjects')" :active="request()->routeIs('student.subjects')">
                {{ __('Joriy fanlar') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('student.pending-lessons')" :active="request()->routeIs('student.pending-lessons')">
                {{ __('Qayta topshirish fanlari') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('student.independents')" :active="request()->routeIs('student.independents')">
                {{ __('Mustaqil ta\'lim') }}
            </x-responsive-nav-link>
        </div>

        <!-- Responsive Settings Options (Mobile) -->
        <div class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-600">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800 dark:text-gray-200">{{ Auth::guard('student')->user()->full_name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::guard('student')->user()->student_id_number }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('student.profile')">
                    {{ __('Talaba ma\'lumoti') }}
                </x-responsive-nav-link>
                @if(session('impersonating'))
                    <form method="POST" action="{{ route('impersonate.stop') }}">
                        @csrf
                        <x-responsive-nav-link :href="route('impersonate.stop')"
                                               onclick="event.preventDefault();
                                            this.closest('form').submit();"
                                               class="text-red-600">
                            {{ __('Orqaga qaytish (Admin)') }}
                        </x-responsive-nav-link>
                    </form>
                @else
                    <form method="POST" action="{{ route('student.logout') }}">
                        @csrf
                        <x-responsive-nav-link :href="route('student.logout')"
                                               onclick="event.preventDefault();
                                            this.closest('form').submit();">
                            {{ __('Chiqish') }}
                        </x-responsive-nav-link>
                    </form>
                @endif
            </div>
        </div>
    </div>
</nav>
