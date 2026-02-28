<x-student-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-sm text-gray-800 leading-tight">
            {{ __('Mustaqil ta\'lim topshiriqlari') }}
        </h2>
    </x-slot>

    @php
        $accordionColors = [
            ['bg' => 'bg-blue-50 dark:bg-blue-900/20', 'border' => 'border-blue-200 dark:border-blue-700', 'accent' => 'bg-blue-500', 'text' => 'text-blue-700 dark:text-blue-300', 'light' => 'bg-blue-100 dark:bg-blue-800/40', 'header' => 'bg-blue-500'],
            ['bg' => 'bg-purple-50 dark:bg-purple-900/20', 'border' => 'border-purple-200 dark:border-purple-700', 'accent' => 'bg-purple-500', 'text' => 'text-purple-700 dark:text-purple-300', 'light' => 'bg-purple-100 dark:bg-purple-800/40', 'header' => 'bg-purple-500'],
            ['bg' => 'bg-emerald-50 dark:bg-emerald-900/20', 'border' => 'border-emerald-200 dark:border-emerald-700', 'accent' => 'bg-emerald-500', 'text' => 'text-emerald-700 dark:text-emerald-300', 'light' => 'bg-emerald-100 dark:bg-emerald-800/40', 'header' => 'bg-emerald-500'],
            ['bg' => 'bg-orange-50 dark:bg-orange-900/20', 'border' => 'border-orange-200 dark:border-orange-700', 'accent' => 'bg-orange-500', 'text' => 'text-orange-700 dark:text-orange-300', 'light' => 'bg-orange-100 dark:bg-orange-800/40', 'header' => 'bg-orange-500'],
            ['bg' => 'bg-rose-50 dark:bg-rose-900/20', 'border' => 'border-rose-200 dark:border-rose-700', 'accent' => 'bg-rose-500', 'text' => 'text-rose-700 dark:text-rose-300', 'light' => 'bg-rose-100 dark:bg-rose-800/40', 'header' => 'bg-rose-500'],
            ['bg' => 'bg-teal-50 dark:bg-teal-900/20', 'border' => 'border-teal-200 dark:border-teal-700', 'accent' => 'bg-teal-500', 'text' => 'text-teal-700 dark:text-teal-300', 'light' => 'bg-teal-100 dark:bg-teal-800/40', 'header' => 'bg-teal-500'],
            ['bg' => 'bg-indigo-50 dark:bg-indigo-900/20', 'border' => 'border-indigo-200 dark:border-indigo-700', 'accent' => 'bg-indigo-500', 'text' => 'text-indigo-700 dark:text-indigo-300', 'light' => 'bg-indigo-100 dark:bg-indigo-800/40', 'header' => 'bg-indigo-500'],
            ['bg' => 'bg-amber-50 dark:bg-amber-900/20', 'border' => 'border-amber-200 dark:border-amber-700', 'accent' => 'bg-amber-500', 'text' => 'text-amber-700 dark:text-amber-300', 'light' => 'bg-amber-100 dark:bg-amber-800/40', 'header' => 'bg-amber-500'],
        ];
        // Fan bo'yicha guruhlash
        $grouped = $independents->groupBy('subject_name');
        $colorIndex = 0;
    @endphp

    <div class="pb-6">
        <div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8">

            @if (session('success'))
                <div id="flash-message" class="mb-3 px-4 py-3 bg-green-100 border border-green-400 text-green-700 rounded-lg text-sm font-semibold">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('error'))
                <div id="flash-message" class="mb-3 px-4 py-3 bg-red-100 border border-red-400 text-red-700 rounded-lg text-sm font-semibold">
                    {{ session('error') }}
                </div>
            @endif

            @if ($errors->any())
                <div id="flash-message" class="mb-3 px-4 py-3 bg-red-100 border border-red-400 text-red-700 rounded-lg text-sm font-semibold">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Compression overlay --}}
            <div id="compress-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.5); z-index:9999; align-items:center; justify-content:center;">
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
                <div x-data="{ openSection: '{{ $grouped->keys()->first() }}' }" class="flex flex-col gap-3 mt-3">
                    @foreach($grouped as $subjectName => $items)
                        @php
                            $color = $accordionColors[$colorIndex % count($accordionColors)];
                            $colorIndex++;
                            $itemCount = $items->count();
                            $completedCount = $items->where('grade_locked', true)->count();
                        @endphp
                        <div class="rounded-xl overflow-hidden border {{ $color['border'] }}">
                            {{-- Accordion header --}}
                            <button @click="openSection = openSection === '{{ $subjectName }}' ? null : '{{ $subjectName }}'"
                                    class="w-full {{ $color['header'] }} text-white px-4 py-3 flex items-center justify-between">
                                <div class="flex items-center gap-2 min-w-0">
                                    <span class="text-sm font-bold truncate">{{ $subjectName }}</span>
                                    <span class="flex-shrink-0 text-[10px] font-medium bg-white/20 px-1.5 py-0.5 rounded-full">{{ $completedCount }}/{{ $itemCount }}</span>
                                </div>
                                <svg class="w-4 h-4 flex-shrink-0 transition-transform duration-200"
                                     :class="openSection === '{{ $subjectName }}' ? 'rotate-180' : ''"
                                     fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/>
                                </svg>
                            </button>

                            {{-- Accordion body --}}
                            <div x-show="openSection === '{{ $subjectName }}'" x-collapse x-cloak
                                 class="{{ $color['bg'] }}">
                                <div class="flex flex-col gap-[5px] p-3">
                                    @foreach($items as $item)
                                        <div class="bg-white dark:bg-gray-800 rounded-lg border {{ $color['border'] }} px-3 py-3 relative overflow-hidden">
                                            <div class="absolute left-0 top-0 bottom-0 w-1 {{ $color['accent'] }}"></div>
                                            <div class="pl-2">
                                                {{-- Status badge --}}
                                                <div class="flex items-center justify-between mb-2">
                                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                                        <svg class="w-3 h-3 inline mr-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0"/></svg>
                                                        {{ $item['teacher_name'] }}
                                                    </span>
                                                    @if($item['grade_locked'])
                                                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-green-100 text-green-700">Qabul qilindi</span>
                                                    @elseif($item['submission'] && $item['grade'] !== null && $item['grade'] < ($minimumLimit ?? 60))
                                                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-orange-100 text-orange-700">Qayta topshirish</span>
                                                    @elseif($item['submission'])
                                                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-green-100 text-green-700">Yuklangan</span>
                                                    @elseif($item['is_overdue'])
                                                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-red-100 text-red-700">Muddat tugagan</span>
                                                    @else
                                                        <span class="px-2 py-0.5 text-[10px] font-bold rounded-full bg-yellow-100 text-yellow-700">Kutilmoqda</span>
                                                    @endif
                                                </div>

                                                {{-- Sana va baho --}}
                                                <div class="flex items-center gap-3 text-[11px] text-gray-500 dark:text-gray-400 mb-2">
                                                    <span>
                                                        <svg class="w-3 h-3 inline mr-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5"/></svg>
                                                        {{ $item['start_date'] }}
                                                    </span>
                                                    <span class="{{ $item['is_overdue'] ? 'text-red-500 font-semibold' : '' }}">
                                                        <svg class="w-3 h-3 inline mr-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                        {{ $item['deadline'] }} ({{ $mtDeadlineTime ?? '17:00' }})
                                                    </span>
                                                    @if($item['grade'] !== null)
                                                        <span class="font-bold {{ $item['grade'] >= ($minimumLimit ?? 60) ? 'text-green-600' : 'text-red-500' }}">
                                                            Baho: {{ $item['grade'] }}
                                                        </span>
                                                    @endif
                                                </div>

                                                {{-- Baho tarixi --}}
                                                @if($item['grade_history']->count() > 0)
                                                    <div class="text-[10px] text-gray-400 mb-2">
                                                        Oldingi baholar:
                                                        @foreach($item['grade_history'] as $history)
                                                            <span class="{{ $history->grade >= ($minimumLimit ?? 60) ? 'text-green-500' : 'text-red-400' }} font-medium">{{ $history->grade }}</span>@if(!$loop->last), @endif
                                                        @endforeach
                                                    </div>
                                                @endif

                                                {{-- Topshiriq fayli --}}
                                                @if($item['file_path'])
                                                    <a href="{{ asset('storage/' . $item['file_path']) }}" target="_blank"
                                                       class="inline-flex items-center gap-1 text-[11px] {{ $color['text'] }} hover:underline mb-2">
                                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m.75 12l3 3m0 0l3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                                        Topshiriq faylini ko'rish
                                                    </a>
                                                @endif

                                                {{-- Yuklangan fayl va upload --}}
                                                @if($item['grade_locked'])
                                                    @if($item['submission'])
                                                        <div class="flex items-center gap-2 bg-green-50 dark:bg-green-900/20 rounded-lg px-2.5 py-2">
                                                            <svg class="w-4 h-4 text-green-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                                                            <div>
                                                                <a href="{{ asset('storage/' . $item['submission']->file_path) }}" target="_blank"
                                                                   class="text-xs text-blue-600 hover:underline font-medium">
                                                                    {{ $item['submission']->file_original_name }}
                                                                </a>
                                                                <div class="text-[10px] text-gray-400">{{ $item['submission']->submitted_at->format('d.m.Y H:i') }}</div>
                                                            </div>
                                                        </div>
                                                    @endif
                                                @elseif($item['yn_locked'])
                                                    @if($item['submission'])
                                                        <div class="flex items-center gap-2 bg-orange-50 dark:bg-orange-900/20 rounded-lg px-2.5 py-2">
                                                            <svg class="w-4 h-4 text-orange-500 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/></svg>
                                                            <div>
                                                                <a href="{{ asset('storage/' . $item['submission']->file_path) }}" target="_blank"
                                                                   class="text-xs text-blue-600 hover:underline font-medium">
                                                                    {{ $item['submission']->file_original_name }}
                                                                </a>
                                                                <div class="text-[10px] text-orange-500 font-medium">YN ga yuborilgan — fayl yuklash mumkin emas</div>
                                                            </div>
                                                        </div>
                                                    @endif
                                                @elseif($item['submission'])
                                                    <div class="flex items-center gap-2 {{ $color['bg'] }} rounded-lg px-2.5 py-2 mb-2">
                                                        <svg class="w-4 h-4 {{ $color['text'] }} flex-shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m.75 12l3 3m0 0l3-3m-3 3v-6m-1.5-9H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                                                        <div>
                                                            <a href="{{ asset('storage/' . $item['submission']->file_path) }}" target="_blank"
                                                               class="text-xs text-blue-600 hover:underline font-medium">
                                                                {{ $item['submission']->file_original_name }}
                                                            </a>
                                                            <div class="text-[10px] text-gray-400">{{ $item['submission']->submitted_at->format('d.m.Y H:i') }}</div>
                                                        </div>
                                                    </div>
                                                    @if($item['can_resubmit'])
                                                        <form method="POST" action="{{ route('student.independents.submit', $item['id']) }}"
                                                              enctype="multipart/form-data" class="mt-upload-form">
                                                            @csrf
                                                            <label class="inline-flex items-center gap-1 cursor-pointer text-xs text-orange-500 hover:text-orange-700 font-medium">
                                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182M2.985 19.644l3.181-3.182"/></svg>
                                                                Qayta yuklash ({{ $item['remaining_attempts'] }} marta)
                                                                <input type="file" name="file" class="hidden mt-file-input"
                                                                       accept=".zip,.doc,.docx,.ppt,.pptx,.pdf">
                                                            </label>
                                                        </form>
                                                    @elseif($item['grade'] !== null && $item['grade'] < ($minimumLimit ?? 60) && $item['remaining_attempts'] <= 0)
                                                        <p class="text-[10px] text-red-400 mt-1">Qayta yuklash imkoniyati tugagan</p>
                                                    @elseif(!$item['is_overdue'] && $item['grade'] === null)
                                                        <form method="POST" action="{{ route('student.independents.submit', $item['id']) }}"
                                                              enctype="multipart/form-data" class="mt-upload-form">
                                                            @csrf
                                                            <label class="inline-flex items-center gap-1 cursor-pointer text-xs text-orange-500 hover:text-orange-700 font-medium">
                                                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182M2.985 19.644l3.181-3.182"/></svg>
                                                                Qayta yuklash
                                                                <input type="file" name="file" class="hidden mt-file-input"
                                                                       accept=".zip,.doc,.docx,.ppt,.pptx,.pdf">
                                                            </label>
                                                        </form>
                                                    @endif
                                                @elseif(!$item['is_overdue'])
                                                    <form method="POST" action="{{ route('student.independents.submit', $item['id']) }}"
                                                          enctype="multipart/form-data" class="mt-upload-form">
                                                        @csrf
                                                        <div class="flex items-center gap-2">
                                                            <input type="file" name="file" required
                                                                   accept=".zip,.doc,.docx,.ppt,.pptx,.pdf"
                                                                   class="text-xs w-full file:mr-2 file:py-1 file:px-2 file:rounded file:border-0 file:text-xs file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 mt-file-input">
                                                            <button type="submit"
                                                                    class="flex-shrink-0 px-3 py-1.5 {{ $color['header'] }} text-white text-xs font-medium rounded-lg hover:opacity-90">
                                                                Yuklash
                                                            </button>
                                                        </div>
                                                        <p class="text-[10px] text-gray-400 mt-1">Max 10MB (zip, doc, ppt, pdf)</p>
                                                    </form>
                                                @else
                                                    <span class="text-xs text-red-400">Muddat tugagan</span>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex items-center justify-center py-16">
                    <div class="text-center">
                        <svg class="w-12 h-12 mx-auto text-gray-300 dark:text-gray-600 mb-2" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.26 10.147a60.438 60.438 0 00-.491 6.347A48.627 48.627 0 0112 20.904a48.627 48.627 0 018.232-4.41 60.46 60.46 0 00-.491-6.347m-15.482 0a50.636 50.636 0 00-2.658-.813A59.906 59.906 0 0112 3.493a59.903 59.903 0 0110.399 5.84c-.896.248-1.783.52-2.658.814m-15.482 0A50.717 50.717 0 0112 13.489a50.702 50.702 0 017.74-3.342M6.75 15a.75.75 0 100-1.5.75.75 0 000 1.5zm0 0v-3.675A55.378 55.378 0 0112 8.443m-7.007 11.55A5.981 5.981 0 006.75 15.75v-1.5"/></svg>
                        <p class="text-sm text-gray-400 dark:text-gray-500 font-medium">Hozircha mustaqil ta'lim topshiriqlari mavjud emas.</p>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var flashMessage = document.getElementById('flash-message');
        if (flashMessage) {
            flashMessage.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }

        var COMPRESS_THRESHOLD = 2 * 1024 * 1024;
        var MAX_FILE_SIZE = 10 * 1024 * 1024;

        document.querySelectorAll('.mt-upload-form').forEach(function(form) {
            var fileInput = form.querySelector('.mt-file-input');
            if (!fileInput) return;

            fileInput.addEventListener('change', function(e) {
                var file = e.target.files[0];
                if (!file) return;

                if (file.size <= COMPRESS_THRESHOLD) {
                    if (fileInput.classList.contains('hidden')) {
                        form.submit();
                    }
                    return;
                }

                var ext = file.name.split('.').pop().toLowerCase();
                if (ext === 'zip') {
                    if (file.size > MAX_FILE_SIZE) {
                        alert('Fayl hajmi ' + (file.size / 1024 / 1024).toFixed(1) + 'MB. Maksimal hajm 10MB.');
                        fileInput.value = '';
                        return;
                    }
                    if (fileInput.classList.contains('hidden')) {
                        form.submit();
                    }
                    return;
                }

                if (typeof JSZip === 'undefined') {
                    alert('Siqish kutubxonasi yuklanmadi. Sahifani yangilang yoki faylni ZIP formatida yuklang.');
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
                    type: 'blob',
                    compression: 'DEFLATE',
                    compressionOptions: { level: 6 }
                }, function(metadata) {
                    detailEl.textContent = 'Siqilmoqda... ' + metadata.percent.toFixed(0) + '%';
                }).then(function(blob) {
                    var compressedSizeMB = (blob.size / 1024 / 1024).toFixed(1);

                    if (blob.size > MAX_FILE_SIZE) {
                        overlay.style.display = 'none';
                        alert('Siqilgandan keyin ham fayl hajmi ' + compressedSizeMB + 'MB (' + originalSizeMB + 'MB dan). Maksimal hajm 10MB. Iltimos, faylni kichikroq qiling.');
                        fileInput.value = '';
                        return;
                    }

                    statusEl.textContent = 'Yuklanmoqda...';
                    var savedPercent = ((1 - blob.size / file.size) * 100).toFixed(0);
                    detailEl.textContent = originalSizeMB + 'MB → ' + compressedSizeMB + 'MB (' + savedPercent + '% siqildi)';

                    var zipFileName = file.name.replace(/\.[^.]+$/, '') + '.zip';
                    var zipFile = new File([blob], zipFileName, { type: 'application/zip' });

                    var dataTransfer = new DataTransfer();
                    dataTransfer.items.add(zipFile);
                    fileInput.files = dataTransfer.files;

                    form.submit();
                }).catch(function(err) {
                    overlay.style.display = 'none';
                    alert('Faylni siqishda xatolik: ' + err.message);
                    fileInput.value = '';
                });
            });

            form.addEventListener('submit', function() {
                var overlay = document.getElementById('compress-overlay');
                if (overlay.style.display === 'flex') {
                    var statusEl = document.getElementById('compress-status');
                    statusEl.textContent = 'Yuklanmoqda...';
                }
            });
        });
    });
    </script>
</x-student-app-layout>
