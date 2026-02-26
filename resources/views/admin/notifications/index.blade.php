<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('notifications.notifications') }}
        </h2>
    </x-slot>

    <div class="py-4 sm:py-6">
        <div class="max-w-full mx-auto px-2 sm:px-6 lg:px-8">
            @if(session('success'))
            <div class="mb-3 flex items-center gap-2 bg-green-50 border border-green-200 text-green-700 px-4 py-2.5 rounded-lg text-sm">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                {{ session('success') }}
            </div>
            @endif

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden flex" style="min-height: 520px;">
                <!-- Chap panel -->
                <div class="hidden sm:flex flex-col w-52 border-r border-gray-200 bg-gray-50/80 flex-shrink-0">
                    <div class="p-3">
                        <a href="{{ route('admin.notifications.create') }}"
                           class="flex items-center justify-center gap-2 w-full px-4 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition-colors shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                            </svg>
                            {{ __('notifications.compose') }}
                        </a>
                    </div>
                    <nav class="flex-1 px-2 pb-3 space-y-0.5">
                        <a href="{{ route('admin.notifications.index', ['tab' => 'inbox']) }}"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm transition-colors {{ $tab === 'inbox' ? 'bg-blue-100/80 text-blue-800 font-semibold' : 'text-gray-600 hover:bg-gray-100 font-medium' }}">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                            </svg>
                            <span class="flex-1">{{ __('notifications.inbox') }}</span>
                            @if($unreadCount > 0)
                            <span class="text-xs font-bold {{ $tab === 'inbox' ? 'text-blue-700' : 'text-gray-500' }}">{{ $unreadCount }}</span>
                            @endif
                        </a>
                        <a href="{{ route('admin.notifications.index', ['tab' => 'sent']) }}"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm transition-colors {{ $tab === 'sent' ? 'bg-blue-100/80 text-blue-800 font-semibold' : 'text-gray-600 hover:bg-gray-100 font-medium' }}">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                            </svg>
                            <span class="flex-1">{{ __('notifications.sent') }}</span>
                            <span class="text-xs text-gray-400">{{ $sentCount }}</span>
                        </a>
                        <a href="{{ route('admin.notifications.index', ['tab' => 'drafts']) }}"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm transition-colors {{ $tab === 'drafts' ? 'bg-blue-100/80 text-blue-800 font-semibold' : 'text-gray-600 hover:bg-gray-100 font-medium' }}">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            <span class="flex-1">{{ __('notifications.drafts') }}</span>
                            <span class="text-xs text-gray-400">{{ $draftsCount }}</span>
                        </a>
                    </nav>

                    @if($tab === 'inbox' && $senders->count() > 0)
                    <div class="border-t border-gray-200 px-2 pt-2 pb-3">
                        <div class="px-3 py-1.5 text-xs font-semibold text-gray-400 uppercase tracking-wider">{{ __('notifications.senders') }}</div>
                        <div class="space-y-0.5 max-h-48 overflow-y-auto">
                            <a href="{{ route('admin.notifications.index', ['tab' => 'inbox', 'search' => $search]) }}"
                               class="flex items-center gap-2 px-3 py-1.5 rounded-md text-xs transition-colors {{ !$senderFilter ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-500 hover:bg-gray-100' }}">
                                <span class="w-5 h-5 rounded-full bg-gray-200 flex items-center justify-center text-[10px] font-bold text-gray-500 flex-shrink-0">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                </span>
                                <span class="truncate">Barchasi</span>
                            </a>
                            @foreach($senders as $sender)
                            @php
                                $sName = $sender->name ?? '-';
                                $initials = mb_strtoupper(mb_substr($sName, 0, 1));
                                $colors = ['bg-blue-500', 'bg-green-500', 'bg-purple-500', 'bg-orange-500', 'bg-pink-500', 'bg-teal-500', 'bg-indigo-500', 'bg-red-400'];
                                $color = $colors[$sender->id % count($colors)];
                            @endphp
                            <a href="{{ route('admin.notifications.index', ['tab' => 'inbox', 'sender_id' => $sender->id, 'search' => $search]) }}"
                               class="flex items-center gap-2 px-3 py-1.5 rounded-md text-xs transition-colors {{ $senderFilter == $sender->id ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-100' }}">
                                <span class="w-5 h-5 rounded-full {{ $color }} flex items-center justify-center text-[10px] font-bold text-white flex-shrink-0">{{ $initials }}</span>
                                <span class="truncate">{{ $sName }}</span>
                            </a>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>

                <!-- Asosiy qism -->
                <div class="flex-1 flex flex-col min-w-0">
                    <!-- Mobil tablar -->
                    <div class="flex items-center justify-between px-3 py-2 border-b border-gray-200 bg-white sm:hidden">
                        <div class="flex items-center gap-1 overflow-x-auto">
                            <a href="{{ route('admin.notifications.index', ['tab' => 'inbox']) }}"
                               class="px-2.5 py-1.5 text-xs font-medium rounded-md whitespace-nowrap {{ $tab === 'inbox' ? 'bg-blue-100 text-blue-700' : 'text-gray-500' }}">
                                {{ __('notifications.inbox') }}@if($unreadCount > 0)<span class="ml-0.5 text-[10px] font-bold">{{ $unreadCount }}</span>@endif
                            </a>
                            <a href="{{ route('admin.notifications.index', ['tab' => 'sent']) }}"
                               class="px-2.5 py-1.5 text-xs font-medium rounded-md whitespace-nowrap {{ $tab === 'sent' ? 'bg-blue-100 text-blue-700' : 'text-gray-500' }}">{{ __('notifications.sent') }}</a>
                            <a href="{{ route('admin.notifications.index', ['tab' => 'drafts']) }}"
                               class="px-2.5 py-1.5 text-xs font-medium rounded-md whitespace-nowrap {{ $tab === 'drafts' ? 'bg-blue-100 text-blue-700' : 'text-gray-500' }}">{{ __('notifications.drafts') }}</a>
                        </div>
                        <a href="{{ route('admin.notifications.create') }}" class="sm:hidden inline-flex items-center px-2.5 py-1.5 bg-blue-600 text-white text-xs font-semibold rounded-md hover:bg-blue-700">
                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            {{ __('notifications.compose') }}
                        </a>
                    </div>

                    <!-- Qidiruv va amallar -->
                    <div class="flex items-center gap-2 px-3 sm:px-4 py-2 border-b border-gray-200 bg-white">
                        <form method="GET" action="{{ route('admin.notifications.index') }}" class="flex-1 flex items-center gap-2">
                            <input type="hidden" name="tab" value="{{ $tab }}">
                            <div class="flex-1 relative">
                                <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                                <input type="text" name="search" value="{{ $search }}"
                                       placeholder="{{ __('notifications.search_placeholder') }}"
                                       class="w-full pl-9 pr-3 py-1.5 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:bg-white focus:border-blue-400 focus:ring-1 focus:ring-blue-400 transition-colors">
                            </div>
                            @if($search)
                            <a href="{{ route('admin.notifications.index', ['tab' => $tab]) }}" class="text-xs text-gray-400 hover:text-gray-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </a>
                            @endif
                        </form>
                        @if($tab === 'inbox' && $unreadCount > 0)
                        <form method="POST" action="{{ route('admin.notifications.mark-all-read') }}">
                            @csrf
                            <button type="submit" class="hidden sm:inline text-xs text-gray-500 hover:text-blue-600 font-medium px-2 py-1.5 rounded hover:bg-gray-100 transition-colors whitespace-nowrap">
                                {{ __('notifications.mark_all_read') }}
                            </button>
                        </form>
                        @endif
                        <span class="hidden sm:inline text-xs text-gray-400 whitespace-nowrap">
                            {{ $notifications->firstItem() ?? 0 }}–{{ $notifications->lastItem() ?? 0 }} / {{ $notifications->total() }}
                        </span>
                    </div>

                    <!-- Xabarlar ro'yxati -->
                    <div class="flex-1 overflow-y-auto">
                        @forelse($notifications as $notification)
                        @php
                            $senderName = $notification->sender->name ?? $notification->sender->short_name ?? $notification->sender->full_name ?? null;
                            $isUnread = !$notification->is_read && $tab === 'inbox';
                        @endphp
                        <a href="{{ route('admin.notifications.show', $notification) }}"
                           class="group flex items-center px-3 sm:px-4 py-2.5 border-b border-gray-100 transition-colors cursor-pointer {{ $isUnread ? 'bg-white hover:bg-gray-50' : 'bg-gray-50/30 hover:bg-gray-100/50' }}">
                            <div class="w-5 flex-shrink-0 flex justify-center">
                                @if($isUnread)
                                <div class="w-2.5 h-2.5 bg-blue-500 rounded-full"></div>
                                @endif
                            </div>
                            <div class="w-32 sm:w-44 flex-shrink-0 pr-3 truncate">
                                <span class="text-sm {{ $isUnread ? 'font-bold text-gray-900' : 'font-normal text-gray-600' }}">
                                    {{ $senderName ?? __('notifications.system') }}
                                </span>
                            </div>
                            <div class="flex-1 min-w-0 flex items-baseline gap-1.5 truncate">
                                <span class="text-sm {{ $isUnread ? 'font-bold text-gray-900' : 'font-normal text-gray-700' }} truncate flex-shrink-0" style="max-width: 45%;">
                                    {{ $notification->subject }}
                                </span>
                                @if($notification->body)
                                <span class="hidden sm:inline text-sm text-gray-400 font-normal truncate">
                                    — {{ Str::limit(strip_tags($notification->body), 80) }}
                                </span>
                                @endif
                            </div>
                            <div class="ml-3 flex-shrink-0 text-right">
                                <span class="text-xs tabular-nums {{ $isUnread ? 'font-semibold text-gray-800' : 'text-gray-400' }}">
                                    @php
                                        $date = $notification->sent_at ?? $notification->updated_at;
                                        $isToday = $date->isToday();
                                    @endphp
                                    {{ $isToday ? $date->format('H:i') : $date->format('d.m.Y') }}
                                </span>
                            </div>
                        </a>
                        @empty
                        <div class="flex flex-col items-center justify-center py-20 px-4">
                            <svg class="w-16 h-16 text-gray-200 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            <p class="text-sm text-gray-400 font-medium">{{ __('notifications.no_messages') }}</p>
                        </div>
                        @endforelse
                    </div>

                    @if($notifications->hasPages())
                    <div class="px-4 py-2.5 border-t border-gray-200 bg-gray-50/50 flex items-center justify-between">
                        <span class="text-xs text-gray-500">{{ $notifications->firstItem() }}–{{ $notifications->lastItem() }} / {{ $notifications->total() }}</span>
                        <div class="flex items-center gap-1">
                            @if($notifications->onFirstPage())
                            <span class="p-1.5 text-gray-300"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg></span>
                            @else
                            <a href="{{ $notifications->withQueryString()->previousPageUrl() }}" class="p-1.5 text-gray-500 hover:bg-gray-200 rounded"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg></a>
                            @endif
                            @if($notifications->hasMorePages())
                            <a href="{{ $notifications->withQueryString()->nextPageUrl() }}" class="p-1.5 text-gray-500 hover:bg-gray-200 rounded"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></a>
                            @else
                            <span class="p-1.5 text-gray-300"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></span>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
