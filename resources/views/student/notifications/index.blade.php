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
            <div class="space-y-3">
                @foreach($notifications as $notification)
                    @if($notification->type === 'exam_reminder')
                        {{-- Imtihon eslatmasi — Telegram stilida --}}
                        @php
                            $titleColor = 'text-yellow-700 bg-yellow-50 border-yellow-300';
                            $titleDot = 'bg-yellow-400';
                            if (str_contains($notification->title, 'Bugun')) {
                                $titleColor = 'text-red-700 bg-red-50 border-red-300';
                                $titleDot = 'bg-red-500';
                            } elseif (str_contains($notification->title, 'Ertaga')) {
                                $titleColor = 'text-orange-700 bg-orange-50 border-orange-300';
                                $titleDot = 'bg-orange-400';
                            }

                            $lines = explode("\n", $notification->message);
                            $groups = [];
                            $currentGroup = null;
                            foreach ($lines as $line) {
                                $line = trim($line);
                                if ($line === '') continue;
                                if (str_starts_with($line, '- ') || str_starts_with($line, '- ')) {
                                    if ($currentGroup !== null) {
                                        $currentGroup['subjects'][] = ltrim($line, '- ');
                                    }
                                } else {
                                    if ($currentGroup !== null) {
                                        $groups[] = $currentGroup;
                                    }
                                    $groupColor = 'yellow';
                                    if (str_starts_with($line, 'Bugun')) {
                                        $groupColor = 'red';
                                    } elseif (str_starts_with($line, 'Ertaga')) {
                                        $groupColor = 'orange';
                                    }
                                    $currentGroup = ['label' => $line, 'color' => $groupColor, 'subjects' => []];
                                }
                            }
                            if ($currentGroup !== null) {
                                $groups[] = $currentGroup;
                            }
                        @endphp
                        <div class="rounded-xl border {{ $notification->isRead() ? 'border-gray-200 bg-white' : 'border-indigo-200 bg-indigo-50/20' }} overflow-hidden transition">
                            {{-- Sarlavha --}}
                            <div class="flex items-center justify-between px-4 py-3 border-b {{ $titleColor }}">
                                <div class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-full {{ $titleDot }} flex-shrink-0"></span>
                                    <h4 class="text-sm font-bold">{{ $notification->title }}</h4>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-[11px] opacity-70">{{ $notification->created_at->diffUz() }}</span>
                                    @if(!$notification->isRead())
                                        <span class="w-2 h-2 rounded-full bg-indigo-500 flex-shrink-0"></span>
                                    @endif
                                </div>
                            </div>
                            {{-- Fanlar ro'yxati — column layout --}}
                            <div class="flex flex-col divide-y divide-gray-100">
                                @foreach($groups as $group)
                                    <div class="px-4 py-3">
                                        <div class="flex items-center gap-2 mb-2">
                                            @if($group['color'] === 'red')
                                                <span class="w-2 h-2 rounded-full bg-red-500"></span>
                                                <span class="text-xs font-semibold text-red-700">{{ $group['label'] }}</span>
                                            @elseif($group['color'] === 'orange')
                                                <span class="w-2 h-2 rounded-full bg-orange-400"></span>
                                                <span class="text-xs font-semibold text-orange-700">{{ $group['label'] }}</span>
                                            @else
                                                <span class="w-2 h-2 rounded-full bg-yellow-400"></span>
                                                <span class="text-xs font-semibold text-yellow-700">{{ $group['label'] }}</span>
                                            @endif
                                        </div>
                                        <div class="flex flex-col gap-1.5 pl-4">
                                            @foreach($group['subjects'] as $subject)
                                                <div class="flex items-center gap-2 text-sm text-gray-700">
                                                    <svg class="w-3.5 h-3.5 text-gray-400 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"/>
                                                    </svg>
                                                    <span>{{ $subject }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            {{-- Link va o'qilgan --}}
                            <div class="px-4 py-2 bg-gray-50 flex items-center justify-between border-t border-gray-100">
                                <a href="{{ $notification->link ?? route('student.exam-schedule') }}" class="text-[11px] text-indigo-600 hover:text-indigo-800 font-medium">
                                    Imtihon jadvalini ko'rish
                                </a>
                                @if(!$notification->isRead())
                                    <form method="POST" action="{{ route('student.notifications.mark-read', $notification->id) }}">
                                        @csrf
                                        <button type="submit" class="text-[11px] text-indigo-500 hover:text-indigo-700">O'qilgan</button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @else
                        {{-- Boshqa turdagi xabarnomalar --}}
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
                                    <p class="text-xs text-gray-600 mt-1 whitespace-pre-line">{{ $notification->message }}</p>
                                    <span class="text-[11px] text-gray-400 mt-1 block">{{ $notification->created_at->diffUz() }}</span>
                                </div>
                            </div>
                            @if(!$notification->isRead())
                                <form method="POST" action="{{ route('student.notifications.mark-read', $notification->id) }}" class="mt-2 flex justify-end">
                                    @csrf
                                    <button type="submit" class="text-[11px] text-indigo-500 hover:text-indigo-700">O'qilgan</button>
                                </form>
                            @endif
                        </div>
                    @endif
                @endforeach
            </div>

            <div class="mt-4">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
</x-student-app-layout>
