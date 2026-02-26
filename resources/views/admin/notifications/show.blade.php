<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('notifications.notifications') }}
        </h2>
    </x-slot>

    <div class="py-4 sm:py-6">
        <div class="max-w-4xl mx-auto px-2 sm:px-6 lg:px-8">
            <!-- Back link -->
            <div class="mb-4">
                <a href="{{ route('admin.notifications.index') }}"
                   class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    {{ __('notifications.back_to_list') }}
                </a>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-gray-200/60 overflow-hidden">
                <!-- Message Header -->
                <div class="px-5 sm:px-6 py-5 border-b border-gray-100">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2.5 flex-wrap mb-2">
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-[11px] font-semibold uppercase tracking-wide
                                    {{ $notification->type === 'system' ? 'bg-purple-50 text-purple-700' : ($notification->type === 'alert' ? 'bg-red-50 text-red-700' : ($notification->type === 'info' ? 'bg-green-50 text-green-700' : 'bg-blue-50 text-blue-700')) }}">
                                    @if($notification->type === 'system')
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                    @elseif($notification->type === 'alert')
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                    @else
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                                    @endif
                                    {{ __('notifications.type_' . $notification->type) }}
                                </span>
                            </div>
                            <h1 class="text-lg sm:text-xl font-bold text-gray-900 leading-tight">{{ $notification->subject }}</h1>
                        </div>
                        <form method="POST" action="{{ route('admin.notifications.destroy', $notification) }}" onsubmit="return confirm('{{ __('notifications.confirm_delete') }}')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all" title="{{ __('notifications.delete') }}">
                                <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </form>
                    </div>

                    <!-- Sender & Date info -->
                    <div class="mt-4 flex items-center gap-3">
                        @php
                            $senderName = $notification->sender->name ?? $notification->sender->short_name ?? $notification->sender->full_name ?? null;
                            $initial = $senderName ? mb_strtoupper(mb_substr($senderName, 0, 1)) : '?';
                        @endphp
                        <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0
                            {{ $notification->type === 'system' ? 'bg-purple-100 text-purple-600' : 'bg-blue-100 text-blue-600' }}">
                            {{ $initial }}
                        </div>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-800">{{ $senderName ?? __('notifications.system') }}</p>
                            <p class="text-xs text-gray-400">{{ ($notification->sent_at ?? $notification->created_at)->format('d.m.Y, H:i') }}</p>
                        </div>
                    </div>
                </div>

                <!-- Message Body -->
                <div class="px-5 sm:px-6 py-6">
                    <div class="text-sm text-gray-700 leading-7 whitespace-pre-line">{{ $notification->body }}</div>
                </div>

                <!-- Footer actions -->
                <div class="px-5 sm:px-6 py-3 border-t border-gray-100 bg-gray-50/30 flex items-center justify-between">
                    <a href="{{ route('admin.notifications.index') }}"
                       class="inline-flex items-center gap-1.5 text-xs text-gray-500 hover:text-gray-700 transition-colors">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                        </svg>
                        {{ __('notifications.back_to_list') }}
                    </a>
                    <a href="{{ route('admin.notifications.create') }}"
                       class="inline-flex items-center gap-1.5 px-3.5 py-1.5 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 transition-all shadow-sm">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                        </svg>
                        {{ __('notifications.compose') }}
                    </a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
