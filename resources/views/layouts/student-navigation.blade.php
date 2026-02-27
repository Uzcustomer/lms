<nav class="bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700">
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
                    <x-nav-link :href="route('student.absence-excuses.index')" :active="request()->routeIs('student.absence-excuses.*')">
                        {{ __('Sababli ariza') }}
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

            <!-- Mobile Top Header: Profile icon (right side) -->
            <div class="flex items-center sm:hidden">
                <a href="{{ route('student.profile') }}" class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-indigo-100 dark:bg-indigo-900 text-indigo-600 dark:text-indigo-300">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                    </svg>
                </a>
            </div>
        </div>
    </div>
</nav>
