<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('notifications.notifications') }}
        </h2>
    </x-slot>

    <div class="py-4 sm:py-6">
        <div class="max-w-full mx-auto px-2 sm:px-6 lg:px-8">
            @if(session('success'))
            <div class="mb-3 flex items-center gap-2 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-2.5 rounded-xl text-sm shadow-sm">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                {{ session('success') }}
            </div>
            @endif

            <div class="bg-white rounded-2xl shadow-sm border border-gray-200/80 overflow-hidden flex" style="min-height: 560px;">
                <!-- Chap panel -->
                <div class="hidden sm:flex flex-col w-56 border-r border-gray-200/80 bg-gradient-to-b from-slate-50 to-gray-50 flex-shrink-0">
                    <div class="p-3">
                        <a href="{{ route('admin.notifications.create') }}"
                           class="flex items-center justify-center gap-2 w-full px-4 py-2.5 bg-gradient-to-r from-blue-600 to-blue-700 text-white text-sm font-semibold rounded-xl hover:from-blue-700 hover:to-blue-800 transition-all shadow-md shadow-blue-500/20 hover:shadow-lg hover:shadow-blue-500/30">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                            </svg>
                            {{ __('notifications.compose') }}
                        </a>
                    </div>
                    <nav class="flex-1 px-2 pb-3 space-y-0.5">
                        <a href="{{ route('admin.notifications.index', ['tab' => 'inbox']) }}"
                           class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm transition-all {{ $tab === 'inbox' ? 'bg-blue-100/70 text-blue-800 font-semibold shadow-sm' : 'text-gray-600 hover:bg-white/80 hover:shadow-sm font-medium' }}">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"></path>
                            </svg>
                            <span class="flex-1">{{ __('notifications.inbox') }}</span>
                            @if($unreadCount > 0)
                            <span class="min-w-[20px] h-5 flex items-center justify-center px-1.5 text-[11px] font-bold rounded-full {{ $tab === 'inbox' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-600' }}">{{ $unreadCount }}</span>
                            @endif
                        </a>
                        <a href="{{ route('admin.notifications.index', ['tab' => 'sent']) }}"
                           class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm transition-all {{ $tab === 'sent' ? 'bg-blue-100/70 text-blue-800 font-semibold shadow-sm' : 'text-gray-600 hover:bg-white/80 hover:shadow-sm font-medium' }}">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                            </svg>
                            <span class="flex-1">{{ __('notifications.sent') }}</span>
                            <span class="text-xs text-gray-400">{{ $sentCount }}</span>
                        </a>
                        <a href="{{ route('admin.notifications.index', ['tab' => 'drafts']) }}"
                           class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-sm transition-all {{ $tab === 'drafts' ? 'bg-blue-100/70 text-blue-800 font-semibold shadow-sm' : 'text-gray-600 hover:bg-white/80 hover:shadow-sm font-medium' }}">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            <span class="flex-1">{{ __('notifications.drafts') }}</span>
                            <span class="text-xs text-gray-400">{{ $draftsCount }}</span>
                        </a>
                    </nav>

                    @if($tab === 'inbox' && $subjects->count() > 0)
                    <div class="border-t border-gray-200/80 px-2 pt-2 pb-3">
                        <div class="px-3 py-1.5 text-[10px] font-bold text-gray-400 uppercase tracking-widest">{{ __('notifications.subjects') }}</div>
                        <div class="space-y-0.5 max-h-48 overflow-y-auto">
                            <a href="{{ route('admin.notifications.index', ['tab' => 'inbox', 'search' => $search, 'sender_id' => $senderFilter, 'status' => $readStatus]) }}"
                               class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs transition-all {{ !$subjectFilter ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-500 hover:bg-white/80' }}">
                                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path></svg>
                                <span class="truncate flex-1">{{ __('notifications.all') }}</span>
                                <span class="text-[10px] text-gray-400 bg-gray-100 px-1.5 py-0.5 rounded-full">{{ $inboxCount }}</span>
                            </a>
                            @foreach($subjects as $subj)
                            <a href="{{ route('admin.notifications.index', ['tab' => 'inbox', 'subject' => $subj->subject, 'search' => $search, 'sender_id' => $senderFilter, 'status' => $readStatus]) }}"
                               class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs transition-all {{ $subjectFilter === $subj->subject ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-white/80' }}"
                               title="{{ $subj->subject }}">
                                <svg class="w-3.5 h-3.5 flex-shrink-0 {{ $subjectFilter === $subj->subject ? 'text-blue-500' : 'text-gray-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                                <span class="truncate flex-1">{{ Str::limit($subj->subject, 20) }}</span>
                                <span class="text-[10px] {{ $subjectFilter === $subj->subject ? 'bg-blue-100 text-blue-600' : 'bg-gray-100 text-gray-400' }} px-1.5 py-0.5 rounded-full">{{ $subj->subject_count }}</span>
                            </a>
                            @endforeach
                        </div>
                    </div>
                    @endif

                    @if($tab === 'inbox' && $senders->count() > 0)
                    <div class="border-t border-gray-200/80 px-2 pt-2 pb-3">
                        <div class="px-3 py-1.5 text-[10px] font-bold text-gray-400 uppercase tracking-widest">{{ __('notifications.senders') }}</div>
                        <div class="space-y-0.5 max-h-48 overflow-y-auto">
                            <a href="{{ route('admin.notifications.index', ['tab' => 'inbox', 'search' => $search, 'subject' => $subjectFilter, 'status' => $readStatus]) }}"
                               class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs transition-all {{ !$senderFilter ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-500 hover:bg-white/80' }}">
                                <span class="w-5 h-5 rounded-full bg-gray-200 flex items-center justify-center text-[10px] font-bold text-gray-500 flex-shrink-0">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                </span>
                                <span class="truncate">{{ __('notifications.all') }}</span>
                            </a>
                            @foreach($senders as $sender)
                            @php
                                $sName = $sender->name ?? '-';
                                $initials = mb_strtoupper(mb_substr($sName, 0, 1));
                                $colors = ['bg-blue-500', 'bg-emerald-500', 'bg-purple-500', 'bg-amber-500', 'bg-pink-500', 'bg-teal-500', 'bg-indigo-500', 'bg-rose-400'];
                                $color = $colors[$sender->id % count($colors)];
                            @endphp
                            <a href="{{ route('admin.notifications.index', ['tab' => 'inbox', 'sender_id' => $sender->id, 'search' => $search, 'subject' => $subjectFilter, 'status' => $readStatus]) }}"
                               class="flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs transition-all {{ $senderFilter == $sender->id ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-white/80' }}">
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
                               class="px-3 py-1.5 text-xs font-medium rounded-lg whitespace-nowrap transition-all {{ $tab === 'inbox' ? 'bg-blue-100 text-blue-700 shadow-sm' : 'text-gray-500' }}">
                                {{ __('notifications.inbox') }}@if($unreadCount > 0)<span class="ml-1 text-[10px] font-bold bg-blue-600 text-white px-1.5 py-0.5 rounded-full">{{ $unreadCount }}</span>@endif
                            </a>
                            <a href="{{ route('admin.notifications.index', ['tab' => 'sent']) }}"
                               class="px-3 py-1.5 text-xs font-medium rounded-lg whitespace-nowrap transition-all {{ $tab === 'sent' ? 'bg-blue-100 text-blue-700 shadow-sm' : 'text-gray-500' }}">{{ __('notifications.sent') }}</a>
                            <a href="{{ route('admin.notifications.index', ['tab' => 'drafts']) }}"
                               class="px-3 py-1.5 text-xs font-medium rounded-lg whitespace-nowrap transition-all {{ $tab === 'drafts' ? 'bg-blue-100 text-blue-700 shadow-sm' : 'text-gray-500' }}">{{ __('notifications.drafts') }}</a>
                        </div>
                        <a href="{{ route('admin.notifications.create') }}" class="sm:hidden inline-flex items-center px-3 py-1.5 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 shadow-sm">
                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            {{ __('notifications.compose') }}
                        </a>
                    </div>

                    <!-- Qidiruv va amallar -->
                    <div class="flex items-center gap-2 px-3 sm:px-4 py-2.5 border-b border-gray-200/80 bg-white">
                        <form method="GET" action="{{ route('admin.notifications.index') }}" class="flex-1 flex items-center gap-2">
                            <input type="hidden" name="tab" value="{{ $tab }}">
                            <div class="flex-1 relative">
                                <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                                <input type="text" name="search" value="{{ $search }}"
                                       placeholder="{{ __('notifications.search_placeholder') }}"
                                       class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-xl bg-gray-50/80 focus:bg-white focus:border-blue-400 focus:ring-2 focus:ring-blue-100 transition-all">
                            </div>
                            @if($search)
                            <a href="{{ route('admin.notifications.index', ['tab' => $tab]) }}" class="text-gray-400 hover:text-gray-600 transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </a>
                            @endif
                        </form>
                        @if($tab === 'inbox' && $unreadCount > 0)
                        <form method="POST" action="{{ route('admin.notifications.mark-all-read') }}">
                            @csrf
                            <button type="submit" class="hidden sm:inline-flex items-center gap-1 text-xs text-gray-500 hover:text-blue-600 font-medium px-3 py-2 rounded-lg hover:bg-blue-50 transition-all whitespace-nowrap">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                {{ __('notifications.mark_all_read') }}
                            </button>
                        </form>
                        @endif
                        <span class="hidden sm:inline text-xs text-gray-400 whitespace-nowrap tabular-nums">
                            {{ $notifications->firstItem() ?? 0 }}–{{ $notifications->lastItem() ?? 0 }} / {{ $notifications->total() }}
                        </span>
                    </div>

                    @if($subjectFilter)
                    <div class="flex items-center gap-2 px-3 sm:px-4 py-2 border-b border-gray-100 bg-amber-50/60">
                        <svg class="w-4 h-4 text-amber-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                        <span class="text-xs text-amber-800 font-medium truncate">{{ __('notifications.subject') }}: <strong>{{ $subjectFilter }}</strong></span>
                        <a href="{{ route('admin.notifications.index', ['tab' => $tab, 'search' => $search, 'sender_id' => $senderFilter, 'status' => $readStatus]) }}"
                           class="ml-auto p-1 text-amber-400 hover:text-amber-600 hover:bg-amber-100 rounded-lg transition-all flex-shrink-0">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </a>
                    </div>
                    @endif

                    <!-- Filtrlar -->
                    @if($tab === 'inbox')
                    <div class="flex items-center gap-1.5 px-3 sm:px-4 py-2 border-b border-gray-100 bg-gray-50/30 overflow-x-auto">
                        @php
                            $baseParams = ['tab' => $tab, 'search' => $search, 'sender_id' => $senderFilter, 'subject' => $subjectFilter];
                        @endphp
                        <a href="{{ route('admin.notifications.index', array_merge($baseParams, ['status' => null])) }}"
                           class="px-3 py-1 text-xs rounded-full whitespace-nowrap transition-all {{ !$readStatus ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'bg-white text-gray-500 border border-gray-200 hover:border-gray-300 hover:bg-gray-50' }}">
                            {{ __('notifications.all') }}
                        </a>
                        <a href="{{ route('admin.notifications.index', array_merge($baseParams, ['status' => 'unread'])) }}"
                           class="px-3 py-1 text-xs rounded-full whitespace-nowrap transition-all {{ $readStatus === 'unread' ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'bg-white text-gray-500 border border-gray-200 hover:border-gray-300 hover:bg-gray-50' }}">
                            <span class="inline-flex items-center gap-1">
                                @if($readStatus !== 'unread')<span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>@endif
                                {{ __('notifications.filter_unread') }}
                            </span>
                        </a>

                        {{-- Mavzu filtri --}}
                        @if($subjects->count() > 0)
                        <span class="w-px h-4 bg-gray-200 mx-1 flex-shrink-0"></span>
                        <span class="text-[10px] text-gray-400 font-bold uppercase tracking-widest mr-1 flex-shrink-0">{{ __('notifications.subject') }}:</span>
                        <select onchange="if(this.value){window.location='{{ route('admin.notifications.index', array_merge($baseParams, ['status' => $readStatus])) }}&subject='+encodeURIComponent(this.value)}else{window.location='{{ route('admin.notifications.index', ['tab' => $tab, 'search' => $search, 'sender_id' => $senderFilter, 'status' => $readStatus]) }}'}"
                                class="text-xs border border-gray-200 rounded-lg px-2 py-1 bg-white text-gray-600 focus:border-blue-400 focus:ring-2 focus:ring-blue-100 max-w-[200px] cursor-pointer">
                            <option value="">{{ __('notifications.all') }}</option>
                            @foreach($subjects as $subj)
                            <option value="{{ $subj->subject }}" {{ $subjectFilter === $subj->subject ? 'selected' : '' }}>{{ Str::limit($subj->subject, 30) }} ({{ $subj->subject_count }})</option>
                            @endforeach
                        </select>
                        @endif

                        @if($readStatus || $subjectFilter)
                        <a href="{{ route('admin.notifications.index', ['tab' => $tab, 'search' => $search, 'sender_id' => $senderFilter]) }}"
                           class="ml-auto px-2 py-1 text-[10px] text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg font-semibold transition-all whitespace-nowrap flex-shrink-0 inline-flex items-center gap-0.5">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            {{ __('notifications.clear_filters') }}
                        </a>
                        @endif
                    </div>
                    @endif

                    <!-- Xabarlar ro'yxati -->
                    <div class="flex-1 overflow-y-auto">
                        @forelse($notifications as $notification)
                        @php
                            $senderName = $notification->sender?->name ?? $notification->sender?->short_name ?? $notification->sender?->full_name ?? null;
                            $isUnread = !$notification->is_read && $tab === 'inbox';
                            $senderInitial = $senderName ? mb_strtoupper(mb_substr($senderName, 0, 1)) : '?';
                            $avatarColors = ['bg-blue-500', 'bg-emerald-500', 'bg-violet-500', 'bg-amber-500', 'bg-pink-500', 'bg-teal-500', 'bg-indigo-500', 'bg-rose-500'];
                            $avatarColor = $avatarColors[($notification->sender_id ?? 0) % count($avatarColors)];
                            $date = $notification->sent_at ?? $notification->updated_at;
                            $isToday = $date->isToday();
                        @endphp
                        <a href="{{ route('admin.notifications.show', $notification) }}"
                           class="group flex items-center gap-3 px-3 sm:px-4 py-3 border-b border-gray-100/80 transition-all cursor-pointer {{ $isUnread ? 'bg-white hover:bg-blue-50/30' : 'bg-gray-50/20 hover:bg-gray-50/60' }}">
                            {{-- Avatar --}}
                            <div class="w-9 h-9 rounded-full {{ $avatarColor }} flex items-center justify-center text-white text-xs font-bold flex-shrink-0 shadow-sm">
                                {{ $senderInitial }}
                            </div>
                            {{-- Kontent --}}
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-0.5">
                                    <span class="text-sm {{ $isUnread ? 'font-bold text-gray-900' : 'font-medium text-gray-600' }} truncate">
                                        {{ $senderName ?? __('notifications.system') }}
                                    </span>
                                    @if($isUnread)
                                    <span class="w-2 h-2 rounded-full bg-blue-500 flex-shrink-0"></span>
                                    @endif
                                    <span class="ml-auto text-xs tabular-nums flex-shrink-0 {{ $isUnread ? 'text-blue-600 font-semibold' : 'text-gray-400' }}">
                                        {{ $isToday ? $date->format('H:i') : $date->format('d.m.Y') }}
                                    </span>
                                </div>
                                <div class="flex items-baseline gap-1.5">
                                    <span class="text-sm {{ $isUnread ? 'font-semibold text-gray-800' : 'text-gray-600' }} truncate flex-shrink-0" style="max-width: 50%;">
                                        {{ $notification->subject }}
                                    </span>
                                    @if($notification->body)
                                    <span class="text-sm text-gray-400 truncate hidden sm:inline">
                                        — {{ Str::limit(strip_tags($notification->body), 70) }}
                                    </span>
                                    @endif
                                </div>
                            </div>
                        </a>
                        @empty
                        <div class="flex flex-col items-center justify-center py-24 px-4">
                            <div class="w-20 h-20 rounded-full bg-gray-100 flex items-center justify-center mb-5">
                                <svg class="w-10 h-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <p class="text-sm text-gray-400 font-medium">{{ __('notifications.no_messages') }}</p>
                        </div>
                        @endforelse
                    </div>

                    @if($notifications->hasPages())
                    <div class="px-4 py-2.5 border-t border-gray-200/80 bg-gray-50/50 flex items-center justify-between">
                        <span class="text-xs text-gray-500 tabular-nums">{{ $notifications->firstItem() }}–{{ $notifications->lastItem() }} / {{ $notifications->total() }}</span>
                        <div class="flex items-center gap-0.5">
                            @if($notifications->onFirstPage())
                            <span class="p-2 text-gray-300"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg></span>
                            @else
                            <a href="{{ $notifications->withQueryString()->previousPageUrl() }}" class="p-2 text-gray-500 hover:bg-gray-200 rounded-lg transition-all"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg></a>
                            @endif
                            @if($notifications->hasMorePages())
                            <a href="{{ $notifications->withQueryString()->nextPageUrl() }}" class="p-2 text-gray-500 hover:bg-gray-200 rounded-lg transition-all"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></a>
                            @else
                            <span class="p-2 text-gray-300"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></span>
                            @endif
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
