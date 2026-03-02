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
                    @elseif($notification->type === 'absence_excuse')
                        {{-- Sababli ariza natijasi — stillantirilgan card --}}
                        @php
                            $excuseData = $notification->data ?? [];
                            $excuseStatus = $excuseData['status'] ?? '';
                            $isApproved = $excuseStatus === 'approved';

                            $headerBg = $isApproved ? 'bg-emerald-500' : 'bg-red-500';
                            $headerIcon = $isApproved ? 'M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z' : 'M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z';
                            $statusLabel = $isApproved ? 'Tasdiqlandi' : 'Rad etildi';
                            $borderColor = $notification->isRead() ? 'border-gray-200' : ($isApproved ? 'border-emerald-300' : 'border-red-300');
                        @endphp
                        <div class="rounded-xl border {{ $borderColor }} overflow-hidden transition shadow-sm {{ !$notification->isRead() ? 'ring-1 ' . ($isApproved ? 'ring-emerald-200' : 'ring-red-200') : '' }}">
                            {{-- Rangli sarlavha --}}
                            <div class="flex items-center justify-between px-4 py-3 {{ $headerBg }}">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center">
                                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="{{ $headerIcon }}" />
                                        </svg>
                                    </div>
                                    <div>
                                        <h4 class="text-sm font-bold text-white">{{ $notification->title }}</h4>
                                        <span class="text-[11px] text-white/80">Sababli ariza</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] px-2 py-0.5 rounded-full font-semibold {{ $isApproved ? 'bg-white/20 text-white' : 'bg-white/20 text-white' }}">
                                        {{ $statusLabel }}
                                    </span>
                                    @if(!$notification->isRead())
                                        <span class="w-2.5 h-2.5 rounded-full bg-white animate-pulse flex-shrink-0"></span>
                                    @endif
                                </div>
                            </div>

                            {{-- Ma'lumotlar qismi --}}
                            <div class="bg-white px-4 py-3">
                                <div class="grid grid-cols-2 gap-3">
                                    @if(!empty($excuseData['reason_label']))
                                        <div class="col-span-2">
                                            <span class="text-[10px] uppercase tracking-wider text-gray-400 font-medium">Sabab</span>
                                            <p class="text-sm text-gray-800 font-medium mt-0.5">{{ $excuseData['reason_label'] }}</p>
                                        </div>
                                    @endif
                                    @if(!empty($excuseData['start_date']) && !empty($excuseData['end_date']))
                                        <div>
                                            <span class="text-[10px] uppercase tracking-wider text-gray-400 font-medium">Muddat</span>
                                            <p class="text-sm text-gray-700 mt-0.5 flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                                                </svg>
                                                {{ $excuseData['start_date'] }} — {{ $excuseData['end_date'] }}
                                            </p>
                                        </div>
                                    @endif
                                    @if(!empty($excuseData['doc_number']))
                                        <div>
                                            <span class="text-[10px] uppercase tracking-wider text-gray-400 font-medium">Hujjat raqami</span>
                                            <p class="text-sm text-gray-700 mt-0.5">{{ $excuseData['doc_number'] }}</p>
                                        </div>
                                    @endif
                                    @if(!empty($excuseData['reviewer_name']))
                                        <div class="{{ empty($excuseData['doc_number']) ? '' : 'col-span-2' }}">
                                            <span class="text-[10px] uppercase tracking-wider text-gray-400 font-medium">Ko'rib chiqdi</span>
                                            <p class="text-sm text-gray-700 mt-0.5 flex items-center gap-1">
                                                <svg class="w-3.5 h-3.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                                                </svg>
                                                {{ $excuseData['reviewer_name'] }}
                                            </p>
                                        </div>
                                    @endif
                                </div>

                                {{-- Rad etish sababi (faqat rejected uchun) --}}
                                @if(!$isApproved && !empty($excuseData['rejection_reason']))
                                    <div class="mt-3 p-2.5 rounded-lg bg-red-50 border border-red-100">
                                        <span class="text-[10px] uppercase tracking-wider text-red-400 font-medium">Rad etish sababi</span>
                                        <p class="text-xs text-red-700 mt-1">{{ $excuseData['rejection_reason'] }}</p>
                                    </div>
                                @endif
                            </div>

                            {{-- Footer --}}
                            <div class="px-4 py-2.5 bg-gray-50 flex items-center justify-between border-t border-gray-100">
                                <div class="flex items-center gap-3">
                                    <a href="{{ $notification->link }}" class="text-xs text-indigo-600 hover:text-indigo-800 font-medium flex items-center gap-1">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                        Arizani ko'rish
                                    </a>
                                    @if($isApproved && !empty($excuseData['excuse_id']))
                                        <a href="{{ url('/student/absence-excuses/' . $excuseData['excuse_id'] . '/download-pdf') }}" class="text-xs text-emerald-600 hover:text-emerald-800 font-medium flex items-center gap-1">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                                            </svg>
                                            PDF yuklab olish
                                        </a>
                                    @endif
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-[11px] text-gray-400">{{ $notification->created_at->diffUz() }}</span>
                                    @if(!$notification->isRead())
                                        <form method="POST" action="{{ route('student.notifications.mark-read', $notification->id) }}">
                                            @csrf
                                            <button type="submit" class="text-[11px] text-indigo-500 hover:text-indigo-700 font-medium">O'qilgan</button>
                                        </form>
                                    @endif
                                </div>
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
                                    <div class="flex items-center justify-between mt-1">
                                        <span class="text-[11px] text-gray-400">{{ $notification->created_at->diffUz() }}</span>
                                        @if($notification->link)
                                            <a href="{{ $notification->link }}" class="text-[11px] text-indigo-600 hover:text-indigo-800 font-medium">Batafsil</a>
                                        @endif
                                    </div>
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
