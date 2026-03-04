<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            Xabarnomalar
        </h2>
    </x-slot>

    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 px-3" x-data="notificationManager()">
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
            {{-- Select all bar — faqat selectionMode da ko'rinadi --}}
            <div x-show="selectionMode" x-transition class="flex items-center justify-between mb-3 px-3 py-2.5 bg-indigo-50 border border-indigo-200 rounded-xl">
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <input type="checkbox" x-model="selectAll" @change="toggleAll()"
                           class="rounded border-indigo-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4">
                    <span class="text-xs font-medium text-indigo-700">Barchasini belgilash</span>
                </label>
                <div class="flex items-center gap-2">
                    <span x-show="selectedIds.length > 0" class="text-xs text-indigo-600 font-semibold" x-text="selectedIds.length + ' ta'"></span>
                    <button @click="cancelSelection()" class="text-xs text-gray-500 hover:text-gray-700 font-medium">Bekor</button>
                </div>
            </div>

            <div class="space-y-3" :class="selectionMode ? 'pb-24' : ''">
                @foreach($notifications as $notification)
                    @php
                        $headerColor = 'text-gray-700 bg-gray-50 border-gray-200';
                        $headerDot = 'bg-gray-400';
                        $statusBadge = null;

                        if ($notification->type === 'exam_reminder') {
                            $headerColor = 'text-yellow-700 bg-yellow-50 border-yellow-300';
                            $headerDot = 'bg-yellow-400';
                            if (str_contains($notification->title, 'Bugun')) {
                                $headerColor = 'text-red-700 bg-red-50 border-red-300';
                                $headerDot = 'bg-red-500';
                            } elseif (str_contains($notification->title, 'Ertaga')) {
                                $headerColor = 'text-orange-700 bg-orange-50 border-orange-300';
                                $headerDot = 'bg-orange-400';
                            }
                        } elseif ($notification->type === 'absence_excuse') {
                            $excuseData = $notification->data ?? [];
                            $isApproved = ($excuseData['status'] ?? '') === 'approved';
                            if ($isApproved) {
                                $headerColor = 'text-emerald-700 bg-emerald-50 border-emerald-300';
                                $headerDot = 'bg-emerald-400';
                                $statusBadge = ['text' => 'Tasdiqlandi', 'class' => 'bg-emerald-100 text-emerald-700'];
                            } else {
                                $headerColor = 'text-red-700 bg-red-50 border-red-300';
                                $headerDot = 'bg-red-400';
                                $statusBadge = ['text' => 'Rad etildi', 'class' => 'bg-red-100 text-red-700'];
                            }
                        } elseif ($notification->type === 'appeal') {
                            $appealData = $notification->data ?? [];
                            $isAppealApproved = ($appealData['status'] ?? '') === 'approved';
                            if ($isAppealApproved) {
                                $headerColor = 'text-emerald-700 bg-emerald-50 border-emerald-300';
                                $headerDot = 'bg-emerald-400';
                                $statusBadge = ['text' => 'Qabul qilindi', 'class' => 'bg-emerald-100 text-emerald-700'];
                            } else {
                                $headerColor = 'text-red-700 bg-red-50 border-red-300';
                                $headerDot = 'bg-red-400';
                                $statusBadge = ['text' => 'Rad etildi', 'class' => 'bg-red-100 text-red-700'];
                            }
                        } elseif ($notification->type === 'sms') {
                            $headerColor = 'text-blue-700 bg-blue-50 border-blue-200';
                            $headerDot = 'bg-blue-400';
                        }

                        $linkText = 'Batafsil';
                        $linkUrl = $notification->link;
                        if ($notification->type === 'exam_reminder') {
                            $linkText = 'Imtihon jadvalini ko\'rish';
                            $linkUrl = $notification->link ?? route('student.exam-schedule');
                        }
                    @endphp

                    <div class="rounded-xl border {{ $notification->isRead() ? 'border-gray-200 bg-white' : 'border-indigo-200 bg-indigo-50/20' }} overflow-hidden transition"
                         :class="selectedIds.includes({{ $notification->id }}) ? 'ring-2 ring-indigo-400' : ''">
                        {{-- Sarlavha --}}
                        <div class="flex items-center justify-between px-4 py-3 border-b {{ $headerColor }}">
                            <div class="flex items-center gap-2 min-w-0">
                                {{-- Checkbox — faqat selectionMode da --}}
                                <input x-show="selectionMode" type="checkbox" value="{{ $notification->id }}"
                                       x-model.number="selectedIds"
                                       @change="updateSelectAll()"
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 w-4 h-4 flex-shrink-0">
                                <span class="w-2.5 h-2.5 rounded-full {{ $headerDot }} flex-shrink-0"></span>
                                <h4 class="text-sm font-bold truncate">{{ $notification->title }}</h4>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <span class="text-[11px] opacity-70">{{ $notification->created_at->diffUz() }}</span>
                                @if(!$notification->isRead())
                                    <span class="w-2 h-2 rounded-full bg-indigo-500 flex-shrink-0"></span>
                                @endif
                                {{-- Delete button — har bir xabarda --}}
                                <button @click.stop="enterSelectionMode({{ $notification->id }})"
                                        x-show="!selectionMode"
                                        class="p-1 rounded-md hover:bg-red-100 text-gray-400 hover:text-red-500 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        {{-- Kontent --}}
                        @if($notification->type === 'exam_reminder')
                            @php
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
                                        if ($currentGroup !== null) $groups[] = $currentGroup;
                                        $groupColor = 'yellow';
                                        if (str_starts_with($line, 'Bugun')) $groupColor = 'red';
                                        elseif (str_starts_with($line, 'Ertaga')) $groupColor = 'orange';
                                        $currentGroup = ['label' => $line, 'color' => $groupColor, 'subjects' => []];
                                    }
                                }
                                if ($currentGroup !== null) $groups[] = $currentGroup;
                            @endphp
                            <div class="flex flex-col divide-y divide-gray-100">
                                @foreach($groups as $group)
                                    <div class="px-4 py-3">
                                        <div class="flex items-center gap-2 mb-2">
                                            <span class="w-2 h-2 rounded-full {{ $group['color'] === 'red' ? 'bg-red-500' : ($group['color'] === 'orange' ? 'bg-orange-400' : 'bg-yellow-400') }}"></span>
                                            <span class="text-xs font-semibold {{ $group['color'] === 'red' ? 'text-red-700' : ($group['color'] === 'orange' ? 'text-orange-700' : 'text-yellow-700') }}">{{ $group['label'] }}</span>
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
                        @else
                            <div class="px-4 py-3">
                                <p class="text-xs text-gray-600 whitespace-pre-line">{{ $notification->message }}</p>
                                @if($notification->type === 'absence_excuse' && !($isApproved ?? true) && !empty($excuseData['rejection_reason'] ?? null))
                                    <p class="text-xs text-red-600 mt-1 font-medium">Sabab: {{ $excuseData['rejection_reason'] }}</p>
                                @endif
                            </div>
                        @endif

                        {{-- Footer --}}
                        <div class="px-4 py-2 bg-gray-50 flex items-center justify-between border-t border-gray-100">
                            <div class="flex items-center gap-3">
                                @if($linkUrl)
                                    <a href="{{ $linkUrl }}" class="text-[11px] text-indigo-600 hover:text-indigo-800 font-medium">{{ $linkText }}</a>
                                @endif
                                @if(!$notification->isRead())
                                    <form method="POST" action="{{ route('student.notifications.mark-read', $notification->id) }}">
                                        @csrf
                                        <button type="submit" class="text-[11px] text-indigo-500 hover:text-indigo-700">O'qilgan</button>
                                    </form>
                                @endif
                            </div>
                            @if($statusBadge)
                                <span class="text-[10px] px-1.5 py-0.5 rounded-full font-semibold {{ $statusBadge['class'] }}">{{ $statusBadge['text'] }}</span>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>

            <div class="mt-4">
                {{ $notifications->links() }}
            </div>

            {{-- Bottom action bar — tanlanganda chiqadi --}}
            <div x-show="selectedIds.length > 0" x-transition
                 style="position:fixed;bottom:70px;left:0;right:0;z-index:9990;padding:10px 15px;padding-bottom:max(10px, env(safe-area-inset-bottom));">
                <div class="max-w-7xl mx-auto" style="display:flex;flex-wrap:wrap;gap:5px;">
                    {{-- O'chirish --}}
                    <button @click="deleteSelected()"
                            style="flex:1 1 calc(50% - 3px);min-width:0;"
                            class="flex items-center justify-center gap-2 py-3 rounded-xl text-sm font-semibold text-white bg-red-500 hover:bg-red-600 active:bg-red-700 transition shadow-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/>
                        </svg>
                        <span>O'chirish (<span x-text="selectedIds.length"></span>)</span>
                    </button>

                    {{-- O'qilgan deb belgilash --}}
                    <button @click="markSelectedRead()"
                            style="flex:1 1 calc(50% - 3px);min-width:0;"
                            class="flex items-center justify-center gap-2 py-3 rounded-xl text-sm font-semibold text-white bg-indigo-500 hover:bg-indigo-600 active:bg-indigo-700 transition shadow-lg">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/>
                        </svg>
                        <span>O'qilgan</span>
                    </button>
                </div>
            </div>
        @endif
    </div>

    @push('scripts')
    <script>
        function notificationManager() {
            return {
                selectedIds: [],
                selectAll: false,
                selectionMode: false,
                allIds: @json($notifications->pluck('id')->toArray()),

                enterSelectionMode(id) {
                    this.selectionMode = true;
                    if (!this.selectedIds.includes(id)) {
                        this.selectedIds.push(id);
                    }
                    this.updateSelectAll();
                },

                cancelSelection() {
                    this.selectionMode = false;
                    this.selectedIds = [];
                    this.selectAll = false;
                },

                toggleAll() {
                    this.selectedIds = this.selectAll ? [...this.allIds] : [];
                },

                updateSelectAll() {
                    this.selectAll = this.selectedIds.length === this.allIds.length && this.allIds.length > 0;
                    if (this.selectedIds.length === 0) {
                        this.selectionMode = false;
                    }
                },

                async markSelectedRead() {
                    if (this.selectedIds.length === 0) return;

                    const token = document.querySelector('meta[name="csrf-token"]').content;
                    try {
                        const response = await fetch('{{ route("student.notifications.bulk-mark-read") }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                            body: JSON.stringify({ ids: this.selectedIds })
                        });
                        if (response.ok) location.reload();
                    } catch (e) {
                        alert('Xatolik yuz berdi');
                    }
                },

                async deleteSelected() {
                    if (this.selectedIds.length === 0) return;
                    if (!confirm(this.selectedIds.length + ' ta xabarnomani o\'chirishni xohlaysizmi?')) return;

                    const token = document.querySelector('meta[name="csrf-token"]').content;
                    try {
                        const response = await fetch('{{ route("student.notifications.bulk-delete") }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': token },
                            body: JSON.stringify({ ids: this.selectedIds })
                        });
                        if (response.ok) location.reload();
                    } catch (e) {
                        alert('Xatolik yuz berdi');
                    }
                }
            }
        }
    </script>
    @endpush
</x-student-app-layout>
