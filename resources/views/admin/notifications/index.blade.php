<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('notifications.notifications') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            @if(session('success'))
            <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg text-sm">
                {{ session('success') }}
            </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="flex flex-col md:flex-row">
                    <!-- Left Sidebar - Email-like folders -->
                    <div class="w-full md:w-64 border-b md:border-b-0 md:border-r border-gray-200 flex-shrink-0">
                        <div class="p-4">
                            <a href="{{ route('admin.notifications.create') }}"
                               class="w-full inline-flex items-center justify-center px-4 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition-colors">
                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                </svg>
                                {{ __('notifications.compose') }}
                            </a>
                        </div>
                        <nav class="px-2 pb-4">
                            <a href="{{ route('admin.notifications.index', ['tab' => 'inbox']) }}"
                               class="flex items-center justify-between px-3 py-2.5 text-sm rounded-lg transition-colors {{ $tab === 'inbox' ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-700 hover:bg-gray-50' }}">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                                    </svg>
                                    {{ __('notifications.inbox') }}
                                </div>
                                @if($unreadCount > 0)
                                <span class="inline-flex items-center justify-center px-2 py-0.5 text-xs font-bold text-white bg-blue-600 rounded-full">{{ $unreadCount }}</span>
                                @endif
                            </a>
                            <a href="{{ route('admin.notifications.index', ['tab' => 'sent']) }}"
                               class="flex items-center justify-between px-3 py-2.5 text-sm rounded-lg transition-colors {{ $tab === 'sent' ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-700 hover:bg-gray-50' }}">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                    </svg>
                                    {{ __('notifications.sent') }}
                                </div>
                                <span class="text-xs text-gray-400">{{ $sentCount }}</span>
                            </a>
                            <a href="{{ route('admin.notifications.index', ['tab' => 'drafts']) }}"
                               class="flex items-center justify-between px-3 py-2.5 text-sm rounded-lg transition-colors {{ $tab === 'drafts' ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-700 hover:bg-gray-50' }}">
                                <div class="flex items-center">
                                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                    </svg>
                                    {{ __('notifications.drafts') }}
                                </div>
                                <span class="text-xs text-gray-400">{{ $draftsCount }}</span>
                            </a>
                        </nav>
                    </div>

                    <!-- Right Content - Message list -->
                    <div class="flex-1 min-w-0">
                        <!-- Toolbar -->
                        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200">
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-semibold text-gray-800">
                                    @if($tab === 'inbox')
                                        {{ __('notifications.inbox') }}
                                    @elseif($tab === 'sent')
                                        {{ __('notifications.sent') }}
                                    @else
                                        {{ __('notifications.drafts') }}
                                    @endif
                                </h3>
                                <span class="text-xs text-gray-400">({{ $notifications->total() }})</span>
                            </div>
                            @if($tab === 'inbox' && $unreadCount > 0)
                            <form method="POST" action="{{ route('admin.notifications.mark-all-read') }}">
                                @csrf
                                <button type="submit" class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                                    {{ __('notifications.mark_all_read') }}
                                </button>
                            </form>
                            @endif
                        </div>

                        <!-- Messages List -->
                        <div class="divide-y divide-gray-100">
                            @forelse($notifications as $notification)
                            <a href="{{ route('admin.notifications.show', $notification) }}"
                               class="flex items-center px-4 py-3 hover:bg-gray-50 transition-colors {{ !$notification->is_read && $tab === 'inbox' ? 'bg-blue-50/50' : '' }}">
                                <!-- Unread indicator -->
                                <div class="w-2 mr-3 flex-shrink-0">
                                    @if(!$notification->is_read && $tab === 'inbox')
                                    <div class="w-2 h-2 bg-blue-500 rounded-full"></div>
                                    @endif
                                </div>
                                <!-- Avatar -->
                                <div class="w-8 h-8 rounded-full flex items-center justify-center mr-3 flex-shrink-0
                                    {{ $notification->type === 'system' ? 'bg-purple-100 text-purple-600' : ($notification->type === 'alert' ? 'bg-red-100 text-red-600' : 'bg-blue-100 text-blue-600') }}">
                                    @if($notification->type === 'system')
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                    </svg>
                                    @else
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                    </svg>
                                    @endif
                                </div>
                                <!-- Content -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm {{ !$notification->is_read && $tab === 'inbox' ? 'font-semibold text-gray-900' : 'font-medium text-gray-700' }} truncate">
                                            {{ $notification->subject }}
                                        </span>
                                        <span class="text-xs text-gray-400 ml-2 flex-shrink-0">
                                            {{ ($notification->sent_at ?? $notification->updated_at)->format('d.m.Y H:i') }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-gray-500 truncate mt-0.5">
                                        {{ Str::limit(strip_tags($notification->body), 80) }}
                                    </p>
                                </div>
                            </a>
                            @empty
                            <div class="px-4 py-12 text-center">
                                <svg class="w-12 h-12 mx-auto text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                                </svg>
                                <p class="text-sm text-gray-500">{{ __('notifications.no_messages') }}</p>
                            </div>
                            @endforelse
                        </div>

                        <!-- Pagination -->
                        @if($notifications->hasPages())
                        <div class="px-4 py-3 border-t border-gray-200">
                            {{ $notifications->withQueryString()->links() }}
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
