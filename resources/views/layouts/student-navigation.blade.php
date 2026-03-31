<nav class="bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('student.dashboard') }}">
                        <x-application-logo class="block fill-current text-gray-800 dark:text-gray-200" style="width:50px;height:50px;" />
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
                    <x-nav-link :href="route('student.exam-schedule')" :active="request()->routeIs('student.exam-schedule')">
                        {{ __('Imtihon jadvali') }}
                    </x-nav-link>
                    <x-nav-link :href="route('student.absence-excuses.index')" :active="request()->routeIs('student.absence-excuses.*')">
                        {{ __('Sababli ariza') }}
                    </x-nav-link>
                    @if(Auth::guard('student')->user()->is_graduate)
                    <x-nav-link :href="route('student.contracts.index')" :active="request()->routeIs('student.contracts.*')">
                        {{ __('Ishga joylashish') }}
                    </x-nav-link>
                    @endif
                    <x-nav-link :href="route('student.visa-info.index')" :active="request()->routeIs('student.visa-info.*')">
                        {{ __('Viza ma\'lumotlarim') }}
                    </x-nav-link>
                </div>
            </div>

            <!-- Language Switcher + Settings Dropdown (Desktop) -->
            <div class="hidden sm:flex sm:items-center sm:ms-6 sm:gap-3">
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" class="inline-flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium rounded-md border border-gray-300 bg-white text-gray-600 hover:bg-gray-50 transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9.004 9.004 0 008.716-6.747M12 21a9.004 9.004 0 01-8.716-6.747M12 21c2.485 0 4.5-4.03 4.5-9S14.485 3 12 3m0 18c-2.485 0-4.5-4.03-4.5-9S9.515 3 12 3m0 0a8.997 8.997 0 017.843 4.582M12 3a8.997 8.997 0 00-7.843 4.582m15.686 0A11.953 11.953 0 0112 10.5c-2.998 0-5.74-1.1-7.843-2.918m15.686 0A8.959 8.959 0 0121 12c0 .778-.099 1.533-.284 2.253m0 0A17.919 17.919 0 0112 16.5c-3.162 0-6.133-.815-8.716-2.247m0 0A9.015 9.015 0 013 12c0-1.605.42-3.113 1.157-4.418" /></svg>
                        {{ strtoupper(app()->getLocale()) }}
                    </button>
                    <div x-show="open" @click.away="open = false" x-transition class="absolute right-0 mt-1 w-32 bg-white border border-gray-200 rounded-md shadow-lg z-50">
                        <a href="{{ route('language.switch', 'uz') }}" class="block px-4 py-2 text-sm {{ app()->getLocale() == 'uz' ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-700 hover:bg-gray-50' }}">O'zbekcha</a>
                        <a href="{{ route('language.switch', 'ru') }}" class="block px-4 py-2 text-sm {{ app()->getLocale() == 'ru' ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-700 hover:bg-gray-50' }}">Русский</a>
                        <a href="{{ route('language.switch', 'en') }}" class="block px-4 py-2 text-sm {{ app()->getLocale() == 'en' ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-700 hover:bg-gray-50' }}">English</a>
                    </div>
                </div>
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

            <!-- Mobile Top Header: icons + avatar (right side) -->
            <div class="flex items-center gap-2 sm:hidden">
                {{-- Language switcher --}}
                <div x-data="{ open: false }" class="relative">
                    <button @click="open = !open" class="p-2 rounded-full hover:bg-gray-100 transition text-sm font-semibold text-gray-600">
                        {{ strtoupper(app()->getLocale()) }}
                    </button>
                    <div x-show="open" @click.away="open = false" x-transition class="absolute right-0 mt-1 w-32 bg-white border border-gray-200 rounded-md shadow-lg z-50">
                        <a href="{{ route('language.switch', 'uz') }}" class="block px-4 py-2 text-sm {{ app()->getLocale() == 'uz' ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-700 hover:bg-gray-50' }}">O'zbekcha</a>
                        <a href="{{ route('language.switch', 'ru') }}" class="block px-4 py-2 text-sm {{ app()->getLocale() == 'ru' ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-700 hover:bg-gray-50' }}">Русский</a>
                        <a href="{{ route('language.switch', 'en') }}" class="block px-4 py-2 text-sm {{ app()->getLocale() == 'en' ? 'bg-indigo-50 text-indigo-700 font-semibold' : 'text-gray-700 hover:bg-gray-50' }}">English</a>
                    </div>
                </div>
                {{-- Notification bell --}}
                <a href="{{ route('student.notifications.index') }}" class="relative p-2 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition"
                   x-data="{ unread: 0 }"
                   x-init="fetch('{{ route('student.notifications.unread-count') }}').then(r=>r.json()).then(d=>unread=d.count).catch(()=>{})"
                >
                    <svg class="w-6 h-6 text-black sm:text-gray-600 dark:text-gray-300" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                    </svg>
                    <span x-show="unread > 0" x-text="unread > 9 ? '9+' : unread"
                          class="absolute flex items-center justify-center text-[10px] font-bold text-white bg-red-500 rounded-full"
                          style="display:none;top:-4px;right:-4px;min-width:20px;height:20px;padding:0 5px;line-height:20px;box-shadow:0 0 0 2px white;"></span>
                </a>

                {{-- Profile avatar --}}
                <a href="{{ route('student.profile') }}">
                    <img src="{{ Auth::guard('student')->user()->image ?? asset('images/default-avatar.png') }}" alt="{{ Auth::guard('student')->user()->full_name }}" class="rounded-full object-cover border-2 border-indigo-200 dark:border-indigo-700" style="width:44px;height:44px;">
                </a>
            </div>
        </div>
    </div>
</nav>
