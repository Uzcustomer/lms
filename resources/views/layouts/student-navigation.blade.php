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
                    <x-nav-link :href="route('student.exam-schedule')" :active="request()->routeIs('student.exam-schedule')">
                        {{ __('Imtihon jadvali') }}
                    </x-nav-link>
                    <x-nav-link :href="route('student.absence-excuses.index')" :active="request()->routeIs('student.absence-excuses.*')">
                        {{ __('Sababli ariza') }}
                    </x-nav-link>
                </div>
            </div>

            <!-- Notification Bell + Settings Dropdown (Desktop) -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <!-- Notification Bell -->
                <div class="relative mr-3" x-data="studentNotificationBell()" x-init="init()">
                    <button @click="toggleDropdown()" class="relative p-2 text-gray-500 hover:text-gray-700 focus:outline-none transition duration-150 ease-in-out">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                        </svg>
                        <span x-show="unreadCount > 0" x-text="unreadCount > 9 ? '9+' : unreadCount"
                              class="absolute -top-1 -right-1 inline-flex items-center justify-center px-1.5 py-0.5 text-xs font-bold leading-none text-white bg-red-500 rounded-full min-w-[18px]"
                              x-cloak></span>
                    </button>

                    <!-- Dropdown -->
                    <div x-show="showDropdown" @click.away="showDropdown = false"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 scale-95"
                         x-transition:enter-end="opacity-100 scale-100"
                         x-transition:leave="transition ease-in duration-75"
                         x-transition:leave-start="opacity-100 scale-100"
                         x-transition:leave-end="opacity-0 scale-95"
                         class="absolute right-0 mt-2 w-96 bg-white rounded-lg shadow-xl border border-gray-200 z-50 overflow-hidden"
                         x-cloak>
                        <!-- Header -->
                        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                            <h3 class="text-sm font-semibold text-gray-700">Xabarnomalar</h3>
                            <button x-show="unreadCount > 0" @click="markAllRead()" class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                                Barchasini o'qilgan qilish
                            </button>
                        </div>
                        <!-- List -->
                        <div class="max-h-80 overflow-y-auto">
                            <template x-if="notifications.length === 0">
                                <div class="px-4 py-8 text-center text-gray-400 text-sm">
                                    Xabarnomalar yo'q
                                </div>
                            </template>
                            <template x-for="n in notifications" :key="n.id">
                                <div @click="openNotification(n)"
                                     :class="n.is_read ? 'bg-white' : 'bg-blue-50'"
                                     class="px-4 py-3 border-b border-gray-100 cursor-pointer hover:bg-gray-50 transition">
                                    <div class="flex items-start gap-3">
                                        <div class="flex-shrink-0 mt-0.5">
                                            <span :class="n.is_read ? 'bg-gray-300' : 'bg-blue-500'" class="inline-block w-2 h-2 rounded-full"></span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 truncate" x-text="n.title"></p>
                                            <p class="text-xs text-gray-600 mt-0.5" style="display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;" x-text="n.message"></p>
                                            <p class="text-xs text-gray-400 mt-1" x-text="n.created_at"></p>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>

                <style>[x-cloak] { display: none !important; }</style>
                <script>
                    function studentNotificationBell() {
                        return {
                            showDropdown: false,
                            unreadCount: 0,
                            notifications: [],
                            pollInterval: null,

                            init() {
                                this.fetchUnreadCount();
                                this.pollInterval = setInterval(() => this.fetchUnreadCount(), 30000);
                            },

                            fetchUnreadCount() {
                                fetch('/student/notifications/unread-count')
                                    .then(r => r.json())
                                    .then(data => { this.unreadCount = data.count; })
                                    .catch(() => {});
                            },

                            toggleDropdown() {
                                this.showDropdown = !this.showDropdown;
                                if (this.showDropdown) {
                                    this.fetchNotifications();
                                }
                            },

                            fetchNotifications() {
                                fetch('/student/notifications/list')
                                    .then(r => r.json())
                                    .then(data => {
                                        this.notifications = data.notifications;
                                        this.unreadCount = data.unread_count;
                                    })
                                    .catch(() => {});
                            },

                            openNotification(n) {
                                if (!n.is_read) {
                                    fetch('/student/notifications/' + n.id + '/read', {
                                        method: 'POST',
                                        headers: {
                                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                            'Content-Type': 'application/json'
                                        }
                                    }).then(() => {
                                        n.is_read = true;
                                        this.unreadCount = Math.max(0, this.unreadCount - 1);
                                    });
                                }
                                if (n.link) {
                                    window.location.href = n.link;
                                }
                            },

                            markAllRead() {
                                fetch('/student/notifications/mark-all-read', {
                                    method: 'POST',
                                    headers: {
                                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                        'Content-Type': 'application/json'
                                    }
                                }).then(() => {
                                    this.notifications.forEach(n => n.is_read = true);
                                    this.unreadCount = 0;
                                });
                            }
                        };
                    }
                </script>

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

            <!-- Mobile Top Header: Notification bell + Student avatar -->
            <div class="flex items-center gap-2 sm:hidden" x-data="{ mobileUnread: 0 }" x-init="fetch('/student/notifications/unread-count').then(r=>r.json()).then(d=>{mobileUnread=d.count}); setInterval(()=>fetch('/student/notifications/unread-count').then(r=>r.json()).then(d=>{mobileUnread=d.count}),30000)">
                <a href="{{ route('student.exam-schedule') }}" class="relative p-1.5">
                    <svg class="w-6 h-6 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                    </svg>
                    <span x-show="mobileUnread > 0" x-text="mobileUnread > 9 ? '9+' : mobileUnread"
                          class="absolute -top-0.5 -right-0.5 inline-flex items-center justify-center px-1 py-0.5 text-[10px] font-bold leading-none text-white bg-red-500 rounded-full min-w-[16px]"
                          x-cloak></span>
                </a>
                <a href="{{ route('student.profile') }}">
                    <img src="{{ Auth::guard('student')->user()->image ?? asset('images/default-avatar.png') }}" alt="{{ Auth::guard('student')->user()->full_name }}" class="rounded-full object-cover border-2 border-indigo-200 dark:border-indigo-700" style="width:50px;height:50px;">
                </a>
            </div>
        </div>
    </div>
</nav>
