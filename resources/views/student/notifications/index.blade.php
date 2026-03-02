<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            Xabarnomalar
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 px-3">
        @if($unreadCount > 0)
            <div class="flex justify-end mb-3">
                <form method="POST" action="{{ route('student.notifications.mark-all-read') }}">
                    @csrf
                    <button type="submit" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                        Barchasini o'qilgan deb belgilash
                    </button>
                </form>
            </div>
        @endif

        @if($notifications->isEmpty())
            <div class="bg-white rounded-xl border border-gray-200 p-8 text-center">
                <div class="w-16 h-16 rounded-full bg-gray-100 flex items-center justify-center mx-auto mb-4">
                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                    </svg>
                </div>
                <p class="text-gray-500 text-sm">Hozircha xabarnomalar yo'q</p>
            </div>
        @else
            <div class="space-y-2">
                @foreach($notifications as $notification)
                    <div class="bg-white rounded-xl border {{ $notification->isRead() ? 'border-gray-200' : 'border-indigo-200 bg-indigo-50/30' }} p-4 transition">
                        <div class="flex items-start gap-3">
                            <div class="w-9 h-9 rounded-full flex items-center justify-center flex-shrink-0
                                {{ $notification->type === 'sms' ? 'bg-blue-100' : 'bg-gray-100' }}">
                                @if($notification->type === 'sms')
                                    <svg class="w-4.5 h-4.5 text-blue-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" />
                                    </svg>
                                @else
                                    <svg class="w-4.5 h-4.5 text-gray-600" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 005.454-1.31A8.967 8.967 0 0118 9.75v-.7V9A6 6 0 006 9v.75a8.967 8.967 0 01-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 01-5.714 0m5.714 0a3 3 0 11-5.714 0" />
                                    </svg>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-sm font-semibold text-gray-800 truncate">{{ $notification->title }}</h4>
                                    @if(!$notification->isRead())
                                        <span class="w-2 h-2 rounded-full bg-indigo-500 flex-shrink-0 ml-2"></span>
                                    @endif
                                </div>
                                <p class="text-xs text-gray-600 mt-1 line-clamp-2">{{ $notification->message }}</p>
                                <span class="text-[11px] text-gray-400 mt-1 block">{{ $notification->created_at->diffForHumans() }}</span>
                            </div>
                        </div>
                        @if(!$notification->isRead())
                            <form method="POST" action="{{ route('student.notifications.mark-read', $notification->id) }}" class="mt-2 flex justify-end">
                                @csrf
                                <button type="submit" class="text-[11px] text-indigo-500 hover:text-indigo-700">O'qilgan</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="mt-4">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
</x-student-app-layout>
