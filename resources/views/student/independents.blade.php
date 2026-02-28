<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            {{ __('Mustaqil ta\'lim topshiriqlari') }}
        </h2>
    </x-slot>

    @php
        $cardColors = [
            ['bg' => 'bg-blue-50 dark:bg-blue-900/20', 'border' => 'border-blue-200 dark:border-blue-700', 'accent' => 'bg-blue-500', 'text' => 'text-blue-700 dark:text-blue-300', 'light' => 'bg-blue-100 dark:bg-blue-800/40', 'header' => 'bg-blue-500', 'headerLight' => 'bg-blue-50'],
            ['bg' => 'bg-purple-50 dark:bg-purple-900/20', 'border' => 'border-purple-200 dark:border-purple-700', 'accent' => 'bg-purple-500', 'text' => 'text-purple-700 dark:text-purple-300', 'light' => 'bg-purple-100 dark:bg-purple-800/40', 'header' => 'bg-purple-500', 'headerLight' => 'bg-purple-50'],
            ['bg' => 'bg-emerald-50 dark:bg-emerald-900/20', 'border' => 'border-emerald-200 dark:border-emerald-700', 'accent' => 'bg-emerald-500', 'text' => 'text-emerald-700 dark:text-emerald-300', 'light' => 'bg-emerald-100 dark:bg-emerald-800/40', 'header' => 'bg-emerald-500', 'headerLight' => 'bg-emerald-50'],
            ['bg' => 'bg-orange-50 dark:bg-orange-900/20', 'border' => 'border-orange-200 dark:border-orange-700', 'accent' => 'bg-orange-500', 'text' => 'text-orange-700 dark:text-orange-300', 'light' => 'bg-orange-100 dark:bg-orange-800/40', 'header' => 'bg-orange-500', 'headerLight' => 'bg-orange-50'],
            ['bg' => 'bg-rose-50 dark:bg-rose-900/20', 'border' => 'border-rose-200 dark:border-rose-700', 'accent' => 'bg-rose-500', 'text' => 'text-rose-700 dark:text-rose-300', 'light' => 'bg-rose-100 dark:bg-rose-800/40', 'header' => 'bg-rose-500', 'headerLight' => 'bg-rose-50'],
            ['bg' => 'bg-teal-50 dark:bg-teal-900/20', 'border' => 'border-teal-200 dark:border-teal-700', 'accent' => 'bg-teal-500', 'text' => 'text-teal-700 dark:text-teal-300', 'light' => 'bg-teal-100 dark:bg-teal-800/40', 'header' => 'bg-teal-500', 'headerLight' => 'bg-teal-50'],
            ['bg' => 'bg-indigo-50 dark:bg-indigo-900/20', 'border' => 'border-indigo-200 dark:border-indigo-700', 'accent' => 'bg-indigo-500', 'text' => 'text-indigo-700 dark:text-indigo-300', 'light' => 'bg-indigo-100 dark:bg-indigo-800/40', 'header' => 'bg-indigo-500', 'headerLight' => 'bg-indigo-50'],
            ['bg' => 'bg-amber-50 dark:bg-amber-900/20', 'border' => 'border-amber-200 dark:border-amber-700', 'accent' => 'bg-amber-500', 'text' => 'text-amber-700 dark:text-amber-300', 'light' => 'bg-amber-100 dark:bg-amber-800/40', 'header' => 'bg-amber-500', 'headerLight' => 'bg-amber-50'],
        ];
        $grouped = $independents->groupBy('subject_name');
        $colorIndex = 0;
        // JSON data for modal
        $groupedJson = [];
        $colorMap = [];
        foreach ($grouped as $subjectName => $items) {
            $c = $cardColors[$colorIndex % count($cardColors)];
            $colorMap[$subjectName] = $c;
            $groupedJson[$subjectName] = $items->values()->map(function($item) use ($minimumLimit, $mtDeadlineTime) {
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
                    'file_path' => $item['file_path'],
                    'submission_file' => $item['submission'] ? asset('storage/' . $item['submission']->file_path) : null,
                    'submission_name' => $item['submission']?->file_original_name,
                    'submission_date' => $item['submission'] ? $item['submission']->submitted_at->format('d.m.Y H:i') : null,
                    'grade_history' => $item['grade_history']->map(fn($h) => ['grade' => $h->grade, 'pass' => $h->grade >= ($minimumLimit ?? 60)])->values()->toArray(),
                    'status_label' => $item['grade_locked'] ? 'Qabul qilindi' : ($item['submission'] && $item['grade'] !== null && $item['grade'] < ($minimumLimit ?? 60) ? 'Qayta topshirish' : ($item['submission'] ? 'Yuklangan' : ($item['is_overdue'] ? 'Muddat tugagan' : 'Kutilmoqda'))),
                    'status_type' => $item['grade_locked'] ? 'success' : ($item['submission'] && $item['grade'] !== null && $item['grade'] < ($minimumLimit ?? 60) ? 'warning' : ($item['submission'] ? 'success' : ($item['is_overdue'] ? 'danger' : 'pending'))),
                    'minimum_limit' => $minimumLimit ?? 60,
                ];
            })->toArray();
            $colorIndex++;
        }
    @endphp

    <div x-data="{
        modalOpen: false,
        modalSubject: '',
        modalItems: [],
        modalColor: {},
        openModal(subject, items, color) {
            this.modalSubject = subject;
            this.modalItems = items;
            this.modalColor = color;
            this.modalOpen = true;
            document.body.style.overflow = 'hidden';
        },
        closeModal() {
            this.modalOpen = false;
            document.body.style.overflow = '';
        }
    }" class="pb-6">
        <div class="max-w-7xl mx-auto px-0 sm:px-6 lg:px-8">

            @if (session('success'))
                <div id="flash-message" class="mx-3 mb-3 px-4 py-3 bg-green-100 border border-green-400 text-green-700 rounded-lg text-sm font-semibold">
                    {{ session('success') }}
                </div>
            @endif
            @if (session('error'))
                <div id="flash-message" class="mx-3 mb-3 px-4 py-3 bg-red-100 border border-red-400 text-red-700 rounded-lg text-sm font-semibold">
                    {{ session('error') }}
                </div>
            @endif
            @if ($errors->any())
                <div id="flash-message" class="mx-3 mb-3 px-4 py-3 bg-red-100 border border-red-400 text-red-700 rounded-lg text-sm font-semibold">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
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
                {{-- Card list --}}
                <div class="flex flex-col">
                    @php $colorIndex = 0; @endphp
                    @foreach($grouped as $subjectName => $items)
                        @php
                            $color = $cardColors[$colorIndex % count($cardColors)];
                            $itemCount = $items->count();
                            $completedCount = $items->where('grade_locked', true)->count();
                            $colorIndex++;
                        @endphp
                        <button @click="openModal(
                                '{{ addslashes($subjectName) }}',
                                {{ json_encode($groupedJson[$subjectName]) }},
                                {{ json_encode($color) }}
                            )"
                            class="w-full flex items-center bg-white dark:bg-gray-800 border-b {{ $color['border'] }} relative overflow-hidden active:bg-gray-50 dark:active:bg-gray-700 transition-colors"
                            style="border-radius:0;">
                            {{-- Left accent bar --}}
                            <div class="absolute left-0 top-0 bottom-0 w-1.5 {{ $color['accent'] }}"></div>
                            {{-- Content --}}
                            <div class="flex-1 flex items-center pl-5 pr-3 py-3.5">
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-sm font-semibold text-gray-800 dark:text-gray-100 truncate text-left">{{ $subjectName }}</h3>
                                    <div class="flex items-center gap-2 mt-0.5">
                                        <span class="text-[11px] text-gray-400">{{ $itemCount }} topshiriq</span>
                                        @if($completedCount > 0)
                                            <span class="text-[10px] font-medium text-green-600 bg-green-50 px-1.5 py-0.5 rounded-full">{{ $completedCount }} bajarildi</span>
                                        @endif
                                    </div>
                                </div>
                                {{-- Chevron right --}}
                                <svg class="w-5 h-5 text-gray-300 dark:text-gray-600 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"/></svg>
                            </div>
                        </button>
                    @endforeach
                </div>
            @else
                <div class="flex items-center justify-center py-16">
                    <div class="text-center">
                        <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.636 50.636 0 00-2.658-.813A59.906 59.906 0 0112 3.493a59.903 59.903 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5"/></svg>
                        <p class="text-sm text-gray-400 dark:text-gray-500 font-medium">Mustaqil ta'lim topshiriqlari mavjud emas.</p>
                    </div>
                </div>
            @endif

            {{-- FULL HEIGHT RIGHT SLIDE MODAL --}}
            <div x-show="modalOpen" x-cloak class="fixed inset-0 z-[9998]">
                {{-- Backdrop --}}
                <div x-show="modalOpen"
                     x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                     x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
                     @click="closeModal()" class="absolute inset-0 bg-black/40"></div>

                {{-- Slide panel --}}
                <div x-show="modalOpen"
                     x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                     x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
                     class="absolute inset-y-0 right-0 w-full sm:max-w-md bg-white dark:bg-gray-800 shadow-2xl flex flex-col">

                    {{-- Modal header --}}
                    <div class="flex-shrink-0 flex items-center gap-3 px-4 py-3 border-b border-gray-200 dark:border-gray-700" :class="modalColor.header" >
                        <button @click="closeModal()" class="w-8 h-8 flex items-center justify-center rounded-full bg-white/20 hover:bg-white/30 text-white">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5L8.25 12l7.5-7.5"/></svg>
                        </button>
                        <h2 class="text-sm font-bold text-white truncate flex-1" x-text="modalSubject"></h2>
                    </div>

                    {{-- Modal body - scrollable --}}
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
                                          }" x-text="item.status_label">
                                    </span>
                                    <template x-if="item.grade !== null">
                                        <span class="text-lg font-black"
                                              :class="item.grade >= item.minimum_limit ? 'text-green-600' : 'text-red-500'"
                                              x-text="item.grade"></span>
                                    </template>
                                </div>

                                {{-- Info rows --}}
                                <div class="space-y-2">
                                    {{-- O'qituvchi --}}
                                    <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                                        <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0"/></svg>
                                        <span x-text="item.teacher_name"></span>
                                    </div>
                                    {{-- Berilgan sana --}}
                                    <div class="flex items-center gap-2 text-xs text-gray-600 dark:text-gray-300">
                                        <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                                        <span>Berilgan: <span class="font-medium" x-text="item.start_date"></span></span>
                                    </div>
                                    {{-- Muddat --}}
                                    <div class="flex items-center gap-2 text-xs" :class="item.is_overdue ? 'text-red-500 font-semibold' : 'text-gray-600 dark:text-gray-300'">
                                        <svg class="w-4 h-4 flex-shrink-0" :class="item.is_overdue ? 'text-red-400' : 'text-gray-400'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                        <span>Muddat: <span class="font-medium" x-text="item.deadline"></span> (<span x-text="item.deadline_time"></span>)</span>
                                    </div>
                                </div>

                                {{-- Baho tarixi --}}
                                <template x-if="item.grade_history && item.grade_history.length > 0">
                                    <div class="mt-3 pt-2 border-t border-gray-200 dark:border-gray-600">
                                        <span class="text-[10px] text-gray-400 font-medium">Oldingi baholar:</span>
                                        <template x-for="(h, hi) in item.grade_history" :key="hi">
                                            <span class="text-xs font-semibold ml-1" :class="h.pass ? 'text-green-500' : 'text-red-400'" x-text="h.grade"></span>
                                        </template>
                                    </div>
                                </template>

                                {{-- Topshiriq fayli --}}
                                <template x-if="item.file_path">
                                    <a :href="'/storage/' + item.file_path" target="_blank"
                                       class="inline-flex items-center gap-1 text-xs text-blue-500 hover:underline mt-2">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m.75 12l3 3m0 0l3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                        Topshiriq fayli
                                    </a>
                                </template>

                                {{-- Yuklangan fayl --}}
                                <template x-if="item.submission_file">
                                    <div class="mt-2 flex items-center gap-2 bg-white dark:bg-gray-800 rounded-lg px-3 py-2 border border-gray-200 dark:border-gray-600">
                                        <svg class="w-5 h-5 text-blue-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                        <div class="min-w-0 flex-1">
                                            <a :href="item.submission_file" target="_blank" class="text-xs font-medium text-blue-600 hover:underline truncate block" x-text="item.submission_name"></a>
                                            <div class="text-[10px] text-gray-400" x-text="item.submission_date"></div>
                                        </div>
                                    </div>
                                </template>

                                {{-- Upload form (server-side rendered per item) --}}
                                <div class="mt-3" :id="'upload-slot-' + item.id"></div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Hidden upload forms (rendered server-side, moved into modal via JS) --}}
    <div id="mt-upload-forms" style="display:none;">
        @foreach($independents as $item)
            <div data-mt-id="{{ $item['id'] }}">
                @if($item['grade_locked'])
                    {{-- No upload needed --}}
                @elseif($item['yn_locked'])
                    <p class="text-[11px] text-orange-500 font-medium">YN ga yuborilgan — fayl yuklash mumkin emas</p>
                @elseif($item['submission'] && $item['can_resubmit'])
                    <form method="POST" action="{{ route('student.independents.submit', $item['id']) }}"
                          enctype="multipart/form-data" class="mt-upload-form">
                        @csrf
                        <label class="inline-flex items-center gap-1.5 cursor-pointer text-xs font-medium text-white bg-orange-500 hover:bg-orange-600 px-3 py-2 rounded-lg transition">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182M2.985 19.644l3.181-3.182"/></svg>
                            Qayta yuklash ({{ $item['remaining_attempts'] }} marta)
                            <input type="file" name="file" class="hidden mt-file-input" accept=".zip,.doc,.docx,.ppt,.pptx,.pdf">
                        </label>
                    </form>
                @elseif($item['submission'] && !$item['is_overdue'] && $item['grade'] === null)
                    <form method="POST" action="{{ route('student.independents.submit', $item['id']) }}"
                          enctype="multipart/form-data" class="mt-upload-form">
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
                    <form method="POST" action="{{ route('student.independents.submit', $item['id']) }}"
                          enctype="multipart/form-data" class="mt-upload-form">
                        @csrf
                        <div class="flex items-center gap-2">
                            <input type="file" name="file" required accept=".zip,.doc,.docx,.ppt,.pptx,.pdf"
                                   class="text-xs w-full file:mr-2 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-blue-500 file:text-white hover:file:bg-blue-600 mt-file-input">
                            <button type="submit" class="flex-shrink-0 px-4 py-1.5 bg-blue-500 text-white text-xs font-medium rounded-lg hover:bg-blue-600 transition">
                                Yuklash
                            </button>
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
    document.addEventListener('DOMContentLoaded', function() {
        var flashMessage = document.getElementById('flash-message');
        if (flashMessage) {
            flashMessage.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        // Move server-rendered upload forms into modal slots when modal items render
        var observer = new MutationObserver(function() {
            document.querySelectorAll('[id^="upload-slot-"]').forEach(function(slot) {
                if (slot.children.length > 0) return;
                var itemId = slot.id.replace('upload-slot-', '');
                var source = document.querySelector('#mt-upload-forms [data-mt-id="' + itemId + '"]');
                if (source && source.innerHTML.trim()) {
                    slot.innerHTML = source.innerHTML;
                    // Re-bind file compression
                    bindCompression(slot);
                }
            });
        });
        observer.observe(document.body, { childList: true, subtree: true });

        function bindCompression(container) {
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
                        if (file.size > MAX_FILE_SIZE) {
                            alert('Fayl hajmi ' + (file.size / 1024 / 1024).toFixed(1) + 'MB. Maksimal hajm 10MB.');
                            fileInput.value = '';
                            return;
                        }
                        if (fileInput.classList.contains('hidden')) { form.submit(); }
                        return;
                    }

                    if (typeof JSZip === 'undefined') {
                        alert('Siqish kutubxonasi yuklanmadi.');
                        fileInput.value = '';
                        return;
                    }

                    var overlay = document.getElementById('compress-overlay');
                    var statusEl = document.getElementById('compress-status');
                    var detailEl = document.getElementById('compress-detail');
                    overlay.style.display = 'flex';
                    statusEl.textContent = 'Fayl siqilmoqda...';
                    var originalSizeMB = (file.size / 1024 / 1024).toFixed(1);
                    detailEl.textContent = 'Asl hajm: ' + originalSizeMB + 'MB';

                    var zip = new JSZip();
                    zip.file(file.name, file);
                    zip.generateAsync({
                        type: 'blob', compression: 'DEFLATE', compressionOptions: { level: 6 }
                    }, function(metadata) {
                        detailEl.textContent = 'Siqilmoqda... ' + metadata.percent.toFixed(0) + '%';
                    }).then(function(blob) {
                        var compressedSizeMB = (blob.size / 1024 / 1024).toFixed(1);
                        if (blob.size > MAX_FILE_SIZE) {
                            overlay.style.display = 'none';
                            alert('Fayl juda katta: ' + compressedSizeMB + 'MB');
                            fileInput.value = '';
                            return;
                        }
                        statusEl.textContent = 'Yuklanmoqda...';
                        detailEl.textContent = originalSizeMB + 'MB → ' + compressedSizeMB + 'MB';
                        var zipFileName = file.name.replace(/\.[^.]+$/, '') + '.zip';
                        var zipFile = new File([blob], zipFileName, { type: 'application/zip' });
                        var dataTransfer = new DataTransfer();
                        dataTransfer.items.add(zipFile);
                        fileInput.files = dataTransfer.files;
                        form.submit();
                    }).catch(function(err) {
                        overlay.style.display = 'none';
                        alert('Xatolik: ' + err.message);
                        fileInput.value = '';
                    });
                });
            });
        }

        // Also bind for any already-visible forms
        bindCompression(document);
    });
    </script>
</x-student-app-layout>
