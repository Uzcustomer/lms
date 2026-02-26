<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('notifications.notifications') }}
        </h2>
    </x-slot>

    <div class="py-4 sm:py-6">
        <div class="max-w-4xl mx-auto px-2 sm:px-6 lg:px-8">
            <!-- Toolbar -->
            <div class="mb-3 flex items-center justify-between">
                <a href="{{ route('admin.notifications.index') }}"
                   class="inline-flex items-center gap-1.5 text-sm text-gray-500 hover:text-gray-700 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                    {{ __('notifications.back_to_list') }}
                </a>
                <div class="flex items-center gap-1">
                    <form method="POST" action="{{ route('admin.notifications.destroy', $notification) }}" onsubmit="return confirm('{{ __('notifications.confirm_delete') }}')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-all" title="{{ __('notifications.delete') }}">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </button>
                    </form>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <!-- Subject -->
                <div class="px-5 sm:px-6 pt-5 pb-3">
                    <div class="flex items-center gap-2.5 flex-wrap">
                        <h1 class="text-xl font-normal text-gray-900 leading-tight">{{ $notification->subject }}</h1>
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium
                            {{ $notification->type === 'system' ? 'bg-purple-50 text-purple-600' : ($notification->type === 'alert' ? 'bg-red-50 text-red-600' : 'bg-gray-100 text-gray-500') }}">
                            {{ __('notifications.type_' . $notification->type) }}
                        </span>
                    </div>
                </div>

                <!-- Sender info -->
                <div class="px-5 sm:px-6 py-3 flex items-start gap-3">
                    @php
                        $senderName = $notification->sender->name ?? $notification->sender->short_name ?? $notification->sender->full_name ?? null;
                        $initial = $senderName ? mb_strtoupper(mb_substr($senderName, 0, 1)) : '?';
                        $colors = ['bg-blue-500', 'bg-green-500', 'bg-purple-500', 'bg-orange-500', 'bg-pink-500', 'bg-teal-500', 'bg-indigo-500'];
                        $colorIndex = $notification->sender_id ? ($notification->sender_id % count($colors)) : 0;
                    @endphp
                    <div class="w-10 h-10 rounded-full flex items-center justify-center text-white text-sm font-semibold flex-shrink-0 {{ $colors[$colorIndex] }}">
                        {{ $initial }}
                    </div>
                    <div class="flex-1 min-w-0" x-data="{ showDetails: false }">
                        <div class="flex items-baseline gap-2 flex-wrap">
                            <span class="text-sm font-semibold text-gray-900">{{ $senderName ?? __('notifications.system') }}</span>
                            <span class="text-xs text-gray-400">{{ ($notification->sent_at ?? $notification->created_at)->format('d M Y, H:i') }}</span>
                        </div>
                        <button @click="showDetails = !showDetails" class="text-xs text-gray-400 hover:text-gray-600 mt-0.5 flex items-center gap-0.5">
                            <span>{{ __('notifications.recipient') }}: {{ $notification->recipient->name ?? $notification->recipient->full_name ?? '—' }}</span>
                            <svg class="w-3 h-3 transition-transform" :class="showDetails ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div x-show="showDetails" x-collapse class="mt-2 text-xs text-gray-500 space-y-0.5 bg-gray-50 rounded-lg p-3 border border-gray-100">
                            <p><span class="font-medium text-gray-600">{{ __('notifications.from') }}:</span> {{ $senderName ?? __('notifications.system') }}</p>
                            <p><span class="font-medium text-gray-600">{{ __('notifications.to') }}:</span> {{ $notification->recipient->name ?? $notification->recipient->full_name ?? '—' }}</p>
                            <p><span class="font-medium text-gray-600">{{ __('notifications.date') }}:</span> {{ ($notification->sent_at ?? $notification->created_at)->format('d.m.Y H:i:s') }}</p>
                            <p><span class="font-medium text-gray-600">{{ __('notifications.type') }}:</span> {{ __('notifications.type_' . $notification->type) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Divider -->
                <div class="mx-5 sm:mx-6 border-t border-gray-100"></div>

                <!-- Message Body -->
                <div class="px-5 sm:px-6 py-6 pl-[4.5rem]">
                    <div class="text-sm text-gray-800 leading-7 whitespace-pre-line">{{ $notification->body }}</div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
