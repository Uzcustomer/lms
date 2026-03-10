<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('notifications.notifications') }}
        </h2>
    </x-slot>

    <div class="py-4 sm:py-6">
        <div class="max-w-full mx-auto px-2 sm:px-6 lg:px-8">
            @if(session('success'))
            <div class="mb-3 flex items-center gap-2.5 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-2.5 rounded-xl text-sm shadow-sm animate-fade-in">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                {{ session('success') }}
            </div>
            @endif

            <div class="bg-white rounded-2xl shadow-sm border border-gray-200/60 overflow-hidden flex" style="min-height: 580px;">
                {{-- ===== CHAP PANEL ===== --}}
                <div class="hidden sm:flex flex-col w-56 border-r border-gray-200/60 bg-gray-50/80 flex-shrink-0">
                    <div class="p-3">
                        <a href="{{ route('admin.notifications.create') }}"
                           class="flex items-center justify-center gap-2 w-full px-4 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-xl hover:bg-blue-700 transition-colors shadow-sm">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            {{ __('notifications.compose') }}
                        </a>
                    </div>

                    <nav class="px-2 pb-2 space-y-0.5">
                        @php
                            $tabs = [
                                'inbox'  => ['icon' => 'M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4', 'label' => __('notifications.inbox'), 'count' => $unreadCount, 'showCount' => $unreadCount > 0],
                                'sent'   => ['icon' => 'M12 19l9 2-9-18-9 18 9-2zm0 0v-8', 'label' => __('notifications.sent'), 'count' => $sentCount, 'showCount' => true],
                                'drafts' => ['icon' => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z', 'label' => __('notifications.drafts'), 'count' => $draftsCount, 'showCount' => true],
                            ];
                        @endphp
                        @foreach($tabs as $tabKey => $tabData)
                        <a href="{{ route('admin.notifications.index', ['tab' => $tabKey]) }}"
                           class="flex items-center gap-2.5 px-3 py-2 rounded-lg text-sm transition-colors {{ $tab === $tabKey ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-100 font-medium' }}">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $tabData['icon'] }}"></path></svg>
                            <span class="flex-1">{{ $tabData['label'] }}</span>
                            @if($tabData['showCount'])
                            <span class="text-[11px] font-semibold {{ $tab === $tabKey && $tabKey === 'inbox' && $unreadCount > 0 ? 'bg-blue-600 text-white px-1.5 py-0.5 rounded-full min-w-[20px] text-center' : 'text-gray-400' }}">{{ $tabData['count'] }}</span>
                            @endif
                        </a>
                        @endforeach
                    </nav>

                    {{-- Jo'natuvchilar (faqat inbox) --}}
                    @if($tab === 'inbox' && $senders->count() > 0)
                    <div class="border-t border-gray-200/60 px-2 pt-2 pb-3">
                        <div class="px-3 py-1 text-[10px] font-bold text-gray-400 uppercase tracking-widest">{{ __('notifications.senders') }}</div>
                        <div class="space-y-0.5 max-h-44 overflow-y-auto">
                            <a href="{{ route('admin.notifications.index', ['tab' => 'inbox', 'search' => $search, 'subject' => $subjectFilter, 'status' => $readStatus]) }}"
                               class="flex items-center gap-2 px-3 py-1.5 rounded-md text-xs transition-colors {{ !$senderFilter ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-500 hover:bg-gray-100' }}">
                                <span class="w-5 h-5 rounded-full bg-gray-200 flex items-center justify-center flex-shrink-0">
                                    <svg class="w-3 h-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                </span>
                                <span class="truncate">{{ __('notifications.all') }}</span>
                            </a>
                            @foreach($senders as $sender)
                            @php
                                $sName = $sender->name ?? '-';
                                $initials = mb_strtoupper(mb_substr($sName, 0, 1));
                                $sColors = ['bg-blue-500','bg-green-500','bg-purple-500','bg-orange-500','bg-pink-500','bg-teal-500','bg-indigo-500'];
                                $sColor = $sColors[$sender->id % count($sColors)];
                            @endphp
                            <a href="{{ route('admin.notifications.index', ['tab' => 'inbox', 'sender_id' => $sender->id, 'search' => $search, 'subject' => $subjectFilter, 'status' => $readStatus]) }}"
                               class="flex items-center gap-2 px-3 py-1.5 rounded-md text-xs transition-colors {{ $senderFilter == $sender->id ? 'bg-blue-50 text-blue-700 font-semibold' : 'text-gray-600 hover:bg-gray-100' }}">
                                <span class="w-5 h-5 rounded-full {{ $sColor }} flex items-center justify-center text-[10px] font-bold text-white flex-shrink-0">{{ $initials }}</span>
                                <span class="truncate">{{ $sName }}</span>
                            </a>
                            @endforeach
                        </div>
                    </div>
                    @endif
                </div>

                {{-- ===== ASOSIY QISM ===== --}}
                <div class="flex-1 flex flex-col min-w-0">
                    {{-- Mobil tablar --}}
                    <div class="flex items-center justify-between px-3 py-2 border-b border-gray-100 bg-white sm:hidden">
                        <div class="flex items-center gap-1 overflow-x-auto">
                            @foreach(['inbox','sent','drafts'] as $mTab)
                            <a href="{{ route('admin.notifications.index', ['tab' => $mTab]) }}"
                               class="px-3 py-1.5 text-xs font-medium rounded-lg whitespace-nowrap {{ $tab === $mTab ? 'bg-blue-50 text-blue-700' : 'text-gray-500' }}">{{ __('notifications.'.$mTab) }}@if($mTab==='inbox' && $unreadCount > 0)<span class="ml-1 text-[10px] font-bold bg-blue-600 text-white px-1.5 py-0.5 rounded-full">{{ $unreadCount }}</span>@endif</a>
                            @endforeach
                        </div>
                        <a href="{{ route('admin.notifications.create') }}" class="sm:hidden inline-flex items-center px-3 py-1.5 bg-blue-600 text-white text-xs font-semibold rounded-lg">
                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                            {{ __('notifications.compose') }}
                        </a>
                    </div>

                    {{-- Qidiruv --}}
                    <div class="flex items-center gap-2 px-3 sm:px-4 py-2.5 border-b border-gray-100 bg-white">
                        <form method="GET" action="{{ route('admin.notifications.index') }}" class="flex-1 flex items-center gap-2">
                            <input type="hidden" name="tab" value="{{ $tab }}">
                            <div class="flex-1 relative">
                                <svg class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                                <input type="text" name="search" value="{{ $search }}"
                                       placeholder="{{ __('notifications.search_placeholder') }}"
                                       class="w-full pl-10 pr-3 py-2 text-sm border border-gray-200 rounded-lg bg-gray-50 focus:bg-white focus:border-blue-400 focus:ring-1 focus:ring-blue-400 transition-colors">
                            </div>
                            @if($search)
                            <a href="{{ route('admin.notifications.index', ['tab' => $tab]) }}" class="text-gray-400 hover:text-gray-600">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            </a>
                            @endif
                        </form>
                        @if($tab === 'inbox' && $unreadCount > 0)
                        <form method="POST" action="{{ route('admin.notifications.mark-all-read') }}">
                            @csrf
                            <button type="submit" class="hidden sm:inline-flex items-center gap-1 text-xs text-gray-500 hover:text-blue-600 font-medium px-2.5 py-1.5 rounded-lg hover:bg-blue-50 transition-colors whitespace-nowrap">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                {{ __('notifications.mark_all_read') }}
                            </button>
                        </form>
                        @endif
                        <span class="hidden sm:inline text-xs text-gray-400 whitespace-nowrap tabular-nums">
                            {{ $notifications->firstItem() ?? 0 }}–{{ $notifications->lastItem() ?? 0 }} / {{ $notifications->total() }}
                        </span>
                    </div>

                    {{-- Filtrlar (barcha tablar uchun) --}}
                    <div class="flex items-center gap-1.5 px-3 sm:px-4 py-2 border-b border-gray-100 bg-gray-50/50 overflow-x-auto">
                        @php
                            $baseParams = ['tab' => $tab, 'search' => $search, 'sender_id' => $senderFilter, 'subject' => $subjectFilter];
                        @endphp

                        {{-- Mavzu filtri (TEPADA, barcha tablar uchun) --}}
                        @if($subjects->count() > 0)
                        <select onchange="if(this.value){window.location='{{ route('admin.notifications.index', ['tab' => $tab, 'search' => $search, 'sender_id' => $senderFilter, 'status' => $readStatus]) }}&subject='+encodeURIComponent(this.value)}else{window.location='{{ route('admin.notifications.index', ['tab' => $tab, 'search' => $search, 'sender_id' => $senderFilter, 'status' => $readStatus]) }}'}"
                                class="text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 bg-white text-gray-700 focus:border-blue-400 focus:ring-1 focus:ring-blue-400 max-w-[220px] cursor-pointer {{ $subjectFilter ? 'border-blue-400 bg-blue-50 text-blue-700 font-semibold' : '' }}">
                            <option value="">{{ __('notifications.subjects') }}: {{ __('notifications.all') }}</option>
                            @foreach($subjects as $subj)
                            <option value="{{ $subj->subject }}" {{ $subjectFilter === $subj->subject ? 'selected' : '' }}>{{ Str::limit($subj->subject, 35) }} ({{ $subj->subject_count }})</option>
                            @endforeach
                        </select>
                        <span class="w-px h-4 bg-gray-200 mx-0.5 flex-shrink-0"></span>
                        @endif

                        {{-- O'qilganlik filtri (faqat inbox) --}}
                        @if($tab === 'inbox')
                        <a href="{{ route('admin.notifications.index', array_merge($baseParams, ['status' => null])) }}"
                           class="px-2.5 py-1 text-xs rounded-full whitespace-nowrap transition-colors {{ !$readStatus ? 'bg-blue-600 text-white font-semibold' : 'bg-white text-gray-500 border border-gray-200 hover:bg-gray-100' }}">
                            {{ __('notifications.all') }}
                        </a>
                        <a href="{{ route('admin.notifications.index', array_merge($baseParams, ['status' => 'unread'])) }}"
                           class="px-2.5 py-1 text-xs rounded-full whitespace-nowrap transition-colors {{ $readStatus === 'unread' ? 'bg-blue-600 text-white font-semibold' : 'bg-white text-gray-500 border border-gray-200 hover:bg-gray-100' }}">
                            <span class="inline-flex items-center gap-1">
                                @if($readStatus !== 'unread')<span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>@endif
                                {{ __('notifications.filter_unread') }}
                            </span>
                        </a>
                        @endif

                        @if($readStatus || $subjectFilter)
                        <a href="{{ route('admin.notifications.index', ['tab' => $tab, 'search' => $search, 'sender_id' => $senderFilter]) }}"
                           class="ml-auto px-2 py-1 text-[10px] text-red-500 hover:text-red-700 hover:bg-red-50 rounded-lg font-semibold transition-colors whitespace-nowrap flex-shrink-0 inline-flex items-center gap-0.5">
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                            {{ __('notifications.clear_filters') }}
                        </a>
                        @endif
                    </div>

                    {{-- Aktiv filtr banner --}}
                    @if($subjectFilter)
                    <div class="flex items-center gap-2 px-3 sm:px-4 py-1.5 border-b border-gray-100 bg-blue-50/50">
                        <svg class="w-3.5 h-3.5 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                        <span class="text-xs text-blue-700 font-medium truncate">{{ $subjectFilter }}</span>
                        <a href="{{ route('admin.notifications.index', ['tab' => $tab, 'search' => $search, 'sender_id' => $senderFilter, 'status' => $readStatus]) }}"
                           class="ml-auto p-0.5 text-blue-400 hover:text-blue-600 rounded flex-shrink-0">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                        </a>
                    </div>
                    @endif

                    {{-- ===== XABARLAR RO'YXATI ===== --}}
                    <div class="flex-1 overflow-y-auto divide-y divide-gray-100/80">
                        @forelse($notifications as $notification)
                        @php
                            $senderName = $notification->sender?->name ?? $notification->sender?->short_name ?? $notification->sender?->full_name ?? null;
                            $isUnread = !$notification->is_read && $tab === 'inbox';
                            $senderInitial = $senderName ? mb_strtoupper(mb_substr($senderName, 0, 1)) : '?';
                            $avatarColors = ['bg-blue-500','bg-green-500','bg-purple-500','bg-orange-500','bg-pink-500','bg-teal-500','bg-indigo-500'];
                            $avatarColor = $avatarColors[($notification->sender_id ?? 0) % count($avatarColors)];
                            $date = $notification->sent_at ?? $notification->updated_at;
                            $isToday = $date && $date->isToday();
                        @endphp
                        <a href="{{ route('admin.notifications.show', $notification) }}"
                           class="flex items-center gap-3 px-3 sm:px-4 py-3 transition-colors cursor-pointer {{ $isUnread ? 'bg-white hover:bg-blue-50/40' : 'hover:bg-gray-50/80' }}">
                            <div class="w-10 h-10 rounded-full {{ $avatarColor }} flex items-center justify-center text-white text-sm font-semibold flex-shrink-0">
                                {{ $senderInitial }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-0.5">
                                    <span class="text-sm {{ $isUnread ? 'font-bold text-gray-900' : 'font-medium text-gray-600' }} truncate">
                                        {{ $senderName ?? __('notifications.system') }}
                                    </span>
                                    @if($isUnread)
                                    <span class="w-2 h-2 rounded-full bg-blue-500 flex-shrink-0"></span>
                                    @endif
                                    <span class="ml-auto text-xs tabular-nums flex-shrink-0 {{ $isUnread ? 'text-blue-600 font-semibold' : 'text-gray-400' }}">
                                        {{ $date ? ($isToday ? $date->format('H:i') : $date->format('d.m.Y')) : '' }}
                                    </span>
                                </div>
                                <div class="flex items-baseline gap-1.5">
                                    <span class="text-sm {{ $isUnread ? 'font-semibold text-gray-800' : 'text-gray-600' }} truncate" style="max-width: 55%;">
                                        {{ $notification->subject }}
                                    </span>
                                    @if($notification->body)
                                    <span class="text-sm text-gray-400 truncate hidden sm:inline">
                                        — {{ Str::limit(strip_tags($notification->body), 60) }}
                                    </span>
                                    @endif
                                </div>
                            </div>
                        </a>
                        @empty
                        <div class="flex flex-col items-center justify-center py-24 px-4">
                            <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mb-4">
                                <svg class="w-8 h-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <p class="text-sm text-gray-400">{{ __('notifications.no_messages') }}</p>
                        </div>
                        @endforelse
                    </div>

                    {{-- Sahifalash --}}
                    @if($notifications->hasPages())
                    <div class="px-4 py-2 border-t border-gray-100 bg-white flex items-center justify-between">
                        <span class="text-xs text-gray-500 tabular-nums">{{ $notifications->firstItem() }}–{{ $notifications->lastItem() }} / {{ $notifications->total() }}</span>
                        <div class="flex items-center gap-0.5">
                            @if($notifications->onFirstPage())
                            <span class="p-1.5 text-gray-300"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg></span>
                            @else
                            <a href="{{ $notifications->withQueryString()->previousPageUrl() }}" class="p-1.5 text-gray-500 hover:bg-gray-100 rounded-lg"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg></a>
                            @endif
                            @if($notifications->hasMorePages())
                            <a href="{{ $notifications->withQueryString()->nextPageUrl() }}" class="p-1.5 text-gray-500 hover:bg-gray-100 rounded-lg"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg></a>
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
