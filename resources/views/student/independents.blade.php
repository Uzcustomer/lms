<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            {{ __('Mustaqil ta\'lim topshiriqlari') }}
        </h2>
    </x-slot>

    @php
        $cardColors = [
            ['accent' => '#3b82f6', 'bg' => '#eff6ff', 'text' => '#1d4ed8', 'class' => 'bg-blue-500', 'lightClass' => 'bg-blue-50 dark:bg-blue-900/20', 'borderClass' => 'border-blue-100 dark:border-blue-800'],
            ['accent' => '#a855f7', 'bg' => '#faf5ff', 'text' => '#7e22ce', 'class' => 'bg-purple-500', 'lightClass' => 'bg-purple-50 dark:bg-purple-900/20', 'borderClass' => 'border-purple-100 dark:border-purple-800'],
            ['accent' => '#10b981', 'bg' => '#ecfdf5', 'text' => '#047857', 'class' => 'bg-emerald-500', 'lightClass' => 'bg-emerald-50 dark:bg-emerald-900/20', 'borderClass' => 'border-emerald-100 dark:border-emerald-800'],
            ['accent' => '#f97316', 'bg' => '#fff7ed', 'text' => '#c2410c', 'class' => 'bg-orange-500', 'lightClass' => 'bg-orange-50 dark:bg-orange-900/20', 'borderClass' => 'border-orange-100 dark:border-orange-800'],
            ['accent' => '#f43f5e', 'bg' => '#fff1f2', 'text' => '#be123c', 'class' => 'bg-rose-500', 'lightClass' => 'bg-rose-50 dark:bg-rose-900/20', 'borderClass' => 'border-rose-100 dark:border-rose-800'],
            ['accent' => '#14b8a6', 'bg' => '#f0fdfa', 'text' => '#0f766e', 'class' => 'bg-teal-500', 'lightClass' => 'bg-teal-50 dark:bg-teal-900/20', 'borderClass' => 'border-teal-100 dark:border-teal-800'],
            ['accent' => '#6366f1', 'bg' => '#eef2ff', 'text' => '#4338ca', 'class' => 'bg-indigo-500', 'lightClass' => 'bg-indigo-50 dark:bg-indigo-900/20', 'borderClass' => 'border-indigo-100 dark:border-indigo-800'],
            ['accent' => '#f59e0b', 'bg' => '#fffbeb', 'text' => '#b45309', 'class' => 'bg-amber-500', 'lightClass' => 'bg-amber-50 dark:bg-amber-900/20', 'borderClass' => 'border-amber-100 dark:border-amber-800'],
        ];
        $grouped = $independents->groupBy('subject_name');
        $colorIndex = 0;

        $groupedJson = [];
        foreach ($grouped as $subjectName => $items) {
            $c = $cardColors[$colorIndex % count($cardColors)];
            $groupedJson[$subjectName] = [
                'color' => $c,
                'items' => $items->values()->map(function($item) use ($minimumLimit, $mtDeadlineTime) {
                    return [
                        'id' => $item['id'],
                        'teacher_name' => $item['teacher_name'],
                        'start_date' => $item['start_date'],
                        'deadline' => $item['deadline'],
                        'deadline_time' => $mtDeadlineTime ?? '17:00',
                        'is_overdue' => $item['is_overdue'],
                        'grade' => $item['grade'],
                        'grade_locked' => $item['grade_locked'],
                        'yn_locked' => $item['yn_locked'],
                        'can_resubmit' => $item['can_resubmit'],
                        'remaining_attempts' => $item['remaining_attempts'],
                        'task_file_url' => $item['file_path'] ? asset('storage/' . $item['file_path']) : null,
                        'task_file_name' => $item['file_original_name'] ?? ($item['file_path'] ? basename($item['file_path']) : null),
                        'submission_file_url' => $item['submission'] ? asset('storage/' . $item['submission']->file_path) : null,
                        'submission_name' => $item['submission']?->file_original_name,
                        'submission_date' => $item['submission'] ? $item['submission']->submitted_at->format('d.m.Y H:i') : null,
                        'grade_history' => $item['grade_history']->map(fn($h) => ['grade' => $h->grade, 'pass' => $h->grade >= ($minimumLimit ?? 60)])->values()->toArray(),
                        'status_label' => $item['grade_locked'] ? 'Qabul qilindi' : ($item['submission'] && $item['grade'] !== null && $item['grade'] < ($minimumLimit ?? 60) ? 'Qayta topshirish' : ($item['submission'] ? 'Yuklangan' : ($item['is_overdue'] ? 'Muddat tugagan' : 'Kutilmoqda'))),
                        'status_type' => $item['grade_locked'] ? 'success' : ($item['submission'] && $item['grade'] !== null && $item['grade'] < ($minimumLimit ?? 60) ? 'warning' : ($item['submission'] ? 'success' : ($item['is_overdue'] ? 'danger' : 'pending'))),
                        'minimum_limit' => $minimumLimit ?? 60,
                    ];
                })->toArray(),
            ];
            $colorIndex++;
        }
    @endphp

    <div x-data="{
        modalOpen: false,
        modalSubject: '',
        modalItems: [],
        modalColor: {},
        openModal(subject, data) {
            this.modalSubject = subject;
            this.modalItems = data.items;
            this.modalColor = data.color;
            this.modalOpen = true;
            document.body.style.overflow = 'hidden';
            this.$nextTick(() => {
                document.querySelectorAll('[id^=&quot;upload-slot-&quot;]').forEach(slot => {
                    if (slot.children.length > 0) return;
                    var id = slot.id.replace('upload-slot-', '');
                    var src = document.querySelector('#mt-upload-forms [data-mt-id=&quot;' + id + '&quot;]');
                    if (src && src.innerHTML.trim()) {
                        slot.innerHTML = src.innerHTML;
                        window.bindMtCompression(slot);
                    }
                });
            });
        },
        closeModal() {
            this.modalOpen = false;
            document.body.style.overflow = '';
        }
    }" class="pb-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if (session('success'))
                <div id="flash-message" class="mx-3 mb-3 px-4 py-3 bg-green-100 border border-green-400 text-green-700 rounded-lg text-sm font-semibold">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div id="flash-message" class="mx-3 mb-3 px-4 py-3 bg-red-100 border border-red-400 text-red-700 rounded-lg text-sm font-semibold">{{ session('error') }}</div>
            @endif
            @if ($errors->any())
                <div id="flash-message" class="mx-3 mb-3 px-4 py-3 bg-red-100 border border-red-400 text-red-700 rounded-lg text-sm font-semibold">
                    <ul class="list-disc list-inside">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
                </div>
            @endif

            {{-- Compression overlay --}}
            <div id="compress-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:99999; align-items:center; justify-content:center;">
                <div style="background:white; border-radius:12px; padding:24px 32px; text-align:center; max-width:360px;">
                    <div style="margin-bottom:12px;">
                        <svg class="animate-spin inline h-8 w-8 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                    </div>
                    <p id="compress-status" style="font-size:14px; color:#1e40af; font-weight:600;">Fayl siqilmoqda...</p>
                    <p id="compress-detail" style="font-size:12px; color:#6b7280; margin-top:4px;"></p>
                </div>
            </div>

            @if($grouped->count() > 0)
                <div class="flex flex-col px-3 mt-1" style="gap:5px;">
                    @php $colorIndex = 0; @endphp
                    @foreach($grouped as $subjectName => $items)
                        @php
                            $color = $cardColors[$colorIndex % count($cardColors)];
                            $itemCount = $items->count();
                            $completedCount = $items->where('grade_locked', true)->count();
                            $colorIndex++;
                        @endphp
                        <button @click="openModal('{{ addslashes($subjectName) }}', {{ json_encode($groupedJson[$subjectName]) }})"
                            class="w-full flex items-center shadow-sm border overflow-hidden active:scale-[0.98] transition-all duration-150 {{ $color['borderClass'] }}"
                            style="background:{{ $color['bg'] }};border-radius:0;">
                            {{-- Left accent bar --}}
                            <div class="w-1.5 self-stretch" style="background:{{ $color['accent'] }};"></div>
                            {{-- Icon --}}
                            <div class="w-10 h-10 rounded-lg flex items-center justify-center ml-3 flex-shrink-0" style="background:{{ $color['accent'] }};">
                                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                            </div>
                            {{-- Content --}}
                            <div class="flex-1 min-w-0 py-3 px-3 text-left">
                                <h3 class="text-sm font-bold truncate" style="color:{{ $color['text'] }};">{{ $subjectName }}</h3>
                                <div class="flex items-center gap-2 mt-0.5">
                                    <span class="text-[11px] text-gray-400">{{ $itemCount }} topshiriq</span>
                                    @if($completedCount > 0)
                                        <span class="text-[10px] font-semibold text-green-600 bg-green-100 px-1.5 py-0.5 rounded-full">{{ $completedCount }}/{{ $itemCount }}</span>
                                    @endif
                                </div>
                            </div>
                            {{-- Chevron --}}
                            <div class="pr-3 flex-shrink-0">
                                <svg class="w-5 h-5" style="color:{{ $color['accent'] }};" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                            </div>
                        </button>
                    @endforeach
                </div>
            @else
                <div class="flex items-center justify-center py-16">
                    <div class="text-center">
                        <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.636 50.636 0 00-2.658-.813A59.906 59.906 0 0112 3.493a59.903 59.903 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5"/></svg>
                        <p class="text-sm text-gray-400 font-medium">Mustaqil ta'lim topshiriqlari mavjud emas.</p>
                    </div>
                </div>
            @endif

            {{-- RIGHT SLIDE MODAL --}}
            <style>
                @keyframes slideInRight { from { transform: translateX(100%); } to { transform: translateX(0); } }
                @keyframes slideOutRight { from { transform: translateX(0); } to { transform: translateX(100%); } }
                @keyframes fadeIn { from { opacity:0; } to { opacity:1; } }
                @keyframes fadeOut { from { opacity:1; } to { opacity:0; } }
                .mt-modal-backdrop-enter { animation: fadeIn 0.3s ease forwards; }
                .mt-modal-backdrop-leave { animation: fadeOut 0.2s ease forwards; }
                .mt-modal-panel-enter { animation: slideInRight 0.3s ease forwards; }
                .mt-modal-panel-leave { animation: slideOutRight 0.2s ease forwards; }
            </style>
            <div x-show="modalOpen" x-cloak class="fixed inset-0 z-[9998]"
                 style="position:fixed;top:0;left:0;right:0;bottom:0;">
                {{-- Backdrop --}}
                <div @click="closeModal()"
                     :class="modalOpen ? 'mt-modal-backdrop-enter' : 'mt-modal-backdrop-leave'"
                     style="position:absolute;inset:0;background:rgba(0,0,0,0.4);"></div>

                {{-- Slide panel from RIGHT --}}
                <div :class="modalOpen ? 'mt-modal-panel-enter' : 'mt-modal-panel-leave'"
                     style="position:absolute;top:0;bottom:0;right:0;width:70%;background:white;box-shadow:-4px 0 25px rgba(0,0,0,0.15);display:flex;flex-direction:column;">

                    {{-- Modal header --}}
                    <div class="flex-shrink-0 flex items-center justify-between px-4 py-3" :style="'background:' + modalColor.accent">
                        <div class="flex items-center gap-3 min-w-0 flex-1">
                            <svg class="w-5 h-5 text-white/80 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                            <h2 class="text-sm font-bold text-white truncate" x-text="modalSubject"></h2>
                        </div>
                        {{-- X close button --}}
                        <button @click="closeModal()" class="w-8 h-8 flex items-center justify-center rounded-full bg-white/20 hover:bg-white/30 text-white flex-shrink-0 ml-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                        </button>
                    </div>

                    {{-- Modal body --}}
                    <div class="flex-1 overflow-y-auto p-4">
                        <template x-for="(item, idx) in modalItems" :key="idx">
                            <div class="mb-3 bg-gray-50 dark:bg-gray-700/50 rounded-xl p-4 border border-gray-100 dark:border-gray-600">
                                {{-- Status + Baho --}}
                                <div class="flex items-center justify-between mb-3">
                                    <span class="px-2.5 py-1 text-[11px] font-bold rounded-full"
                                          :class="{
                                              'bg-green-100 text-green-700': item.status_type === 'success',
                                              'bg-orange-100 text-orange-700': item.status_type === 'warning',
                                              'bg-red-100 text-red-700': item.status_type === 'danger',
                                              'bg-yellow-100 text-yellow-700': item.status_type === 'pending'
                                          }" x-text="item.status_label"></span>
                                    <template x-if="item.grade !== null">
                                        <span class="text-xl font-black" :class="item.grade >= item.minimum_limit ? 'text-green-600' : 'text-red-500'" x-text="item.grade"></span>
                                    </template>
                                </div>

                                {{-- Info --}}
                                <div class="space-y-2 mb-3">
                                    <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                                        <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0"/></svg>
                                        <span x-text="item.teacher_name"></span>
                                    </div>
                                    <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                                        <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                                        <span>Berilgan: <span class="font-medium" x-text="item.start_date"></span></span>
                                    </div>
                                    <div class="flex items-center gap-2 text-xs" :class="item.is_overdue ? 'text-red-500 font-semibold' : 'text-gray-600 dark:text-gray-300'">
                                        <svg class="w-4 h-4 flex-shrink-0" :class="item.is_overdue ? 'text-red-400' : 'text-gray-400'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <span>Muddat: <span class="font-medium" x-text="item.deadline"></span> (<span x-text="item.deadline_time"></span>)</span>
                                    </div>
                                </div>

                                {{-- Baho tarixi --}}
                                <template x-if="item.grade_history && item.grade_history.length > 0">
                                    <div class="mb-3 pt-2 border-t border-gray-200 dark:border-gray-600">
                                        <span class="text-[11px] text-gray-400 font-medium">Oldingi baholar:</span>
                                        <template x-for="(h, hi) in item.grade_history" :key="hi">
                                            <span class="text-xs font-semibold ml-1" :class="h.pass ? 'text-green-500' : 'text-red-400'" x-text="h.grade"></span>
                                        </template>
                                    </div>
                                </template>

                                {{-- Topshiriq fayli (o'qituvchi yuklagan) --}}
                                <template x-if="item.task_file_url">
                                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-lg px-3 py-3 mb-2 border border-blue-100 dark:border-blue-800">
                                        <div class="flex items-center gap-2 mb-2.5">
                                            <svg class="w-5 h-5 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-[11px] text-gray-400">Topshiriq fayli</p>
                                                <p class="text-xs font-medium text-gray-700 dark:text-gray-200 truncate" x-text="item.task_file_name"></p>
                                            </div>
                                        </div>
                                        <a :href="item.task_file_url" download class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-blue-500 hover:bg-blue-600 text-white text-xs font-medium rounded-lg transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                                            Yuklab olish
                                        </a>
                                    </div>
                                </template>

                                {{-- Yuklangan fayl (talaba topshirgani) --}}
                                <template x-if="item.submission_file_url">
                                    <div class="bg-green-50 dark:bg-green-900/20 rounded-lg px-3 py-3 mb-2 border border-green-100 dark:border-green-800">
                                        <div class="flex items-center gap-2 mb-2.5">
                                            <svg class="w-5 h-5 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-[11px] text-gray-400">Topshirilgan fayl</p>
                                                <p class="text-xs font-medium text-gray-700 dark:text-gray-200 truncate" x-text="item.submission_name"></p>
                                                <p class="text-[10px] text-gray-400" x-text="item.submission_date"></p>
                                            </div>
                                        </div>
                                        <a :href="item.submission_file_url" download class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-green-500 hover:bg-green-600 text-white text-xs font-medium rounded-lg transition">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
                                            Yuklab olish
                                        </a>
                                    </div>
                                </template>

                                {{-- Upload form slot --}}
                                <div class="mt-2" :id="'upload-slot-' + item.id"></div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Hidden upload forms --}}
    <div id="mt-upload-forms" style="display:none;">
        @foreach($independents as $item)
            <div data-mt-id="{{ $item['id'] }}">
                @if($item['grade_locked'])
                @elseif($item['yn_locked'])
                    <p class="text-[11px] text-orange-500 font-medium">YN ga yuborilgan — fayl yuklash mumkin emas</p>
                @elseif($item['submission'] && $item['can_resubmit'])
                    <form method="POST" action="{{ route('student.independents.submit', $item['id']) }}" enctype="multipart/form-data" class="mt-upload-form">
                        @csrf
                        <label class="inline-flex items-center gap-1.5 cursor-pointer text-xs font-medium text-white bg-orange-500 hover:bg-orange-600 px-3 py-2 rounded-lg transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182M2.985 19.644l3.181-3.182"/></svg>
                            Qayta yuklash ({{ $item['remaining_attempts'] }} marta)
                            <input type="file" name="file" class="hidden mt-file-input" accept=".zip,.doc,.docx,.ppt,.pptx,.pdf">
                        </label>
                    </form>
                @elseif($item['submission'] && !$item['is_overdue'] && $item['grade'] === null)
                    <form method="POST" action="{{ route('student.independents.submit', $item['id']) }}" enctype="multipart/form-data" class="mt-upload-form">
                        @csrf
                        <label class="inline-flex items-center gap-1.5 cursor-pointer text-xs font-medium text-white bg-orange-500 hover:bg-orange-600 px-3 py-2 rounded-lg transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182M2.985 19.644l3.181-3.182"/></svg>
                            Qayta yuklash
                            <input type="file" name="file" class="hidden mt-file-input" accept=".zip,.doc,.docx,.ppt,.pptx,.pdf">
                        </label>
                    </form>
                @elseif($item['submission'] && $item['grade'] !== null && $item['grade'] < ($minimumLimit ?? 60) && $item['remaining_attempts'] <= 0)
                    <p class="text-[11px] text-red-400 font-medium">Qayta yuklash imkoniyati tugagan</p>
                @elseif(!$item['submission'] && !$item['is_overdue'])
                    <form method="POST" action="{{ route('student.independents.submit', $item['id']) }}" enctype="multipart/form-data" class="mt-upload-form">
                        @csrf
                        <div class="flex items-center gap-2">
                            <input type="file" name="file" required accept=".zip,.doc,.docx,.ppt,.pptx,.pdf"
                                   class="text-xs w-full file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-blue-500 file:text-white hover:file:bg-blue-600 mt-file-input">
                            <button type="submit" class="flex-shrink-0 px-4 py-1.5 bg-blue-500 text-white text-xs font-medium rounded-lg hover:bg-blue-600 transition">Yuklash</button>
                        </div>
                        <p class="text-[10px] text-gray-400 mt-1.5">Max 10MB (zip, doc, ppt, pdf)</p>
                    </form>
                @elseif($item['is_overdue'] && !$item['submission'])
                    <p class="text-[11px] text-red-400 font-medium">Muddat tugagan</p>
                @endif
            </div>
        @endforeach
    </div>

    <script>
    window.bindMtCompression = function(container) {
        var COMPRESS_THRESHOLD = 2 * 1024 * 1024;
        var MAX_FILE_SIZE = 10 * 1024 * 1024;
        container.querySelectorAll('.mt-upload-form').forEach(function(form) {
            var fileInput = form.querySelector('.mt-file-input');
            if (!fileInput) return;
            fileInput.addEventListener('change', function(e) {
                var file = e.target.files[0];
                if (!file) return;
                if (file.size <= COMPRESS_THRESHOLD) {
                    if (fileInput.classList.contains('hidden')) { form.submit(); }
                    return;
                }
                var ext = file.name.split('.').pop().toLowerCase();
                if (ext === 'zip') {
                    if (file.size > MAX_FILE_SIZE) { alert('Fayl hajmi ' + (file.size/1024/1024).toFixed(1) + 'MB. Max 10MB.'); fileInput.value = ''; return; }
                    if (fileInput.classList.contains('hidden')) { form.submit(); }
                    return;
                }
                if (typeof JSZip === 'undefined') { alert('Siqish kutubxonasi yuklanmadi.'); fileInput.value = ''; return; }
                var overlay = document.getElementById('compress-overlay');
                var statusEl = document.getElementById('compress-status');
                var detailEl = document.getElementById('compress-detail');
                overlay.style.display = 'flex';
                statusEl.textContent = 'Fayl siqilmoqda...';
                var origMB = (file.size/1024/1024).toFixed(1);
                detailEl.textContent = 'Asl hajm: ' + origMB + 'MB';
                var zip = new JSZip();
                zip.file(file.name, file);
                zip.generateAsync({ type:'blob', compression:'DEFLATE', compressionOptions:{level:6} }, function(m) {
                    detailEl.textContent = 'Siqilmoqda... ' + m.percent.toFixed(0) + '%';
                }).then(function(blob) {
                    if (blob.size > MAX_FILE_SIZE) { overlay.style.display='none'; alert('Fayl juda katta'); fileInput.value=''; return; }
                    statusEl.textContent = 'Yuklanmoqda...';
                    detailEl.textContent = origMB + 'MB → ' + (blob.size/1024/1024).toFixed(1) + 'MB';
                    var dt = new DataTransfer();
                    dt.items.add(new File([blob], file.name.replace(/\.[^.]+$/, '') + '.zip', {type:'application/zip'}));
                    fileInput.files = dt.files;
                    form.submit();
                }).catch(function(err) { overlay.style.display='none'; alert('Xatolik: '+err.message); fileInput.value=''; });
            });
        });
    };
    document.addEventListener('DOMContentLoaded', function() {
        var fm = document.getElementById('flash-message');
        if (fm) fm.scrollIntoView({ behavior:'smooth', block:'center' });
        window.bindMtCompression(document);
    });
    </script>
</x-student-app-layout>
