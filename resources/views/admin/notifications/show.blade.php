<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('notifications.notifications') }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="mb-4">
                <a href="{{ route('admin.notifications.index') }}"
                   class="inline-flex items-center text-sm text-blue-600 hover:text-blue-800">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    {{ __('notifications.back_to_list') }}
                </a>
            </div>

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <!-- Header -->
                <div class="px-6 py-4 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                        <h1 class="text-lg font-semibold text-gray-900">{{ $notification->subject }}</h1>
                        <div class="flex items-center gap-2">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                {{ $notification->type === 'system' ? 'bg-purple-100 text-purple-800' : ($notification->type === 'alert' ? 'bg-red-100 text-red-800' : ($notification->type === 'info' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800')) }}">
                                {{ __('notifications.type_' . $notification->type) }}
                            </span>
                            <form method="POST" action="{{ route('admin.notifications.destroy', $notification) }}" onsubmit="return confirm('{{ __('notifications.confirm_delete') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-red-400 hover:text-red-600 p-1" title="{{ __('notifications.delete') }}">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                    </svg>
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="mt-2 flex items-center gap-4 text-sm text-gray-500">
                        @if($notification->sender)
                        <span>
                            <strong>{{ __('notifications.from') }}:</strong>
                            {{ $notification->sender->name ?? $notification->sender->full_name ?? __('notifications.system') }}
                        </span>
                        @endif
                        <span>
                            <strong>{{ __('notifications.date') }}:</strong>
                            {{ ($notification->sent_at ?? $notification->created_at)->format('d.m.Y H:i') }}
                        </span>
                    </div>
                </div>

                <!-- Body -->
                <div class="px-6 py-6">
                    <div class="prose max-w-none text-gray-700 text-sm leading-relaxed">
                        {!! nl2br(e($notification->body)) !!}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
