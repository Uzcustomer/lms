<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('notifications.notifications') }}
        </h2>
    </x-slot>

    <div class="py-4 sm:py-6">
        <div class="max-w-full mx-auto px-2 sm:px-6 lg:px-8">
            @if(session('success'))
            <div class="mb-4 flex items-center gap-2 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-xl text-sm">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                {{ session('success') }}
            </div>
            @endif

            <div class="bg-white overflow-hidden rounded-xl shadow-sm border border-gray-200/60">
                <!-- Toolbar -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between px-4 py-3 border-b border-gray-100 bg-gray-50/50 gap-3">
                    <div class="flex items-center gap-1.5 overflow-x-auto">
                        <a href="{{ route('admin.notifications.index', ['tab' => 'inbox']) }}"
                           class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg transition-all whitespace-nowrap {{ $tab === 'inbox' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-600 hover:bg-white hover:shadow-sm' }}">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                            </svg>
                            {{ __('notifications.inbox') }}
                            @if($unreadCount > 0)
                            <span class="inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 text-[10px] font-bold rounded-full {{ $tab === 'inbox' ? 'bg-white/20 text-white' : 'bg-blue-600 text-white' }}">{{ $unreadCount }}</span>
                            @endif
                        </a>
                        <a href="{{ route('admin.notifications.index', ['tab' => 'sent']) }}"
                           class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg transition-all whitespace-nowrap {{ $tab === 'sent' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-600 hover:bg-white hover:shadow-sm' }}">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                            </svg>
                            {{ __('notifications.sent') }}
                            <span class="text-[10px] opacity-50">{{ $sentCount }}</span>
                        </a>
                        <a href="{{ route('admin.notifications.index', ['tab' => 'drafts']) }}"
                           class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg transition-all whitespace-nowrap {{ $tab === 'drafts' ? 'bg-blue-600 text-white shadow-sm' : 'text-gray-600 hover:bg-white hover:shadow-sm' }}">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            {{ __('notifications.drafts') }}
                            <span class="text-[10px] opacity-50">{{ $draftsCount }}</span>
                        </a>

                        @if($tab === 'inbox' && $unreadCount > 0)
                        <span class="text-gray-300 mx-1 hidden sm:inline">|</span>
                        <form method="POST" action="{{ route('admin.notifications.mark-all-read') }}">
                            @csrf
                            <button type="submit" class="text-[11px] text-blue-600 hover:text-blue-800 font-medium whitespace-nowrap px-2 py-1.5 rounded-md hover:bg-blue-50 transition-colors">
                                <svg class="w-3 h-3 inline mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                {{ __('notifications.mark_all_read') }}
                            </button>
                        </form>
                        @endif
                    </div>

                    <a href="{{ route('admin.notifications.create') }}"
                       class="inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 transition-all shadow-sm hover:shadow whitespace-nowrap">
                        <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                        </svg>
                        {{ __('notifications.compose') }}
                    </a>
                </div>

                <!-- Messages List -->
                <div>
                    <div class="divide-y divide-gray-100">
                        @forelse($notifications as $notification)
                        <a href="{{ route('admin.notifications.show', $notification) }}"
                           class="group flex items-center px-4 py-3.5 transition-all duration-150 {{ !$notification->is_read && $tab === 'inbox' ? 'bg-blue-50/40 hover:bg-blue-50/70' : 'hover:bg-gray-50' }}">
                            <!-- Unread indicator -->
                            <div class="w-2.5 mr-3 flex-shrink-0">
                                @if(!$notification->is_read && $tab === 'inbox')
                                <div class="w-2 h-2 bg-blue-500 rounded-full ring-4 ring-blue-500/10"></div>
                                @endif
                            </div>
                            <!-- Avatar -->
                            <div class="w-9 h-9 rounded-full flex items-center justify-center mr-3.5 flex-shrink-0 text-xs font-bold
                                {{ $notification->type === 'system' ? 'bg-purple-100 text-purple-600' : ($notification->type === 'alert' ? 'bg-red-100 text-red-600' : 'bg-blue-100 text-blue-600') }}">
                                @php
                                    $senderName = $notification->sender->name ?? $notification->sender->short_name ?? $notification->sender->full_name ?? null;
                                    $initials = $senderName ? mb_strtoupper(mb_substr($senderName, 0, 1)) : '?';
                                @endphp
                                {{ $initials }}
                            </div>
                            <!-- Content -->
                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <span class="text-xs {{ !$notification->is_read && $tab === 'inbox' ? 'font-semibold text-gray-700' : 'text-gray-500' }}">{{ $senderName ?? __('notifications.system') }}</span>
                                        <p class="text-sm {{ !$notification->is_read && $tab === 'inbox' ? 'font-semibold text-gray-900' : 'font-medium text-gray-700' }} truncate leading-snug">
                                            {{ $notification->subject }}
                                        </p>
                                    </div>
                                    <span class="text-[11px] text-gray-400 flex-shrink-0 mt-0.5 tabular-nums">
                                        {{ ($notification->sent_at ?? $notification->updated_at)->format('d.m.Y H:i') }}
                                    </span>
                                </div>
                                @if($notification->body)
                                <p class="text-xs text-gray-400 truncate mt-0.5 leading-relaxed group-hover:text-gray-500">
                                    {{ Str::limit(strip_tags($notification->body), 90) }}
                                </p>
                                @endif
                            </div>
                            <!-- Arrow -->
                            <svg class="w-4 h-4 text-gray-300 ml-2 flex-shrink-0 opacity-0 group-hover:opacity-100 transition-opacity" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                            </svg>
                        </a>
                        @empty
                        <div class="px-4 py-16 text-center">
                            <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-100 flex items-center justify-center">
                                <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                                </svg>
                            </div>
                            <p class="text-sm font-medium text-gray-400">{{ __('notifications.no_messages') }}</p>
                        </div>
                        @endforelse
                    </div>

                    <!-- Pagination -->
                    @if($notifications->hasPages())
                    <div class="px-4 py-3 border-t border-gray-100 bg-gray-50/30">
                        {{ $notifications->withQueryString()->links() }}
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
