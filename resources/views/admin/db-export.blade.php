<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">DB ma'lumotlar</h2>
    </x-slot>

    <div class="py-6" x-data="{ timingOpen: {{ $errors->any() ? 'true' : 'false' }} }" @keydown.escape.window="timingOpen = false">
        <div class="w-full px-4 sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4">Ma'lumotlar bazasidan Excel eksport</h3>
                <p class="text-sm text-gray-500 mb-6">Kerakli jadvalni tanlang va Excel formatida yuklab oling.</p>

                @if(session('error') || $errors->any())
                    <div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-sm text-red-700">
                        {{ session('error') ?: $errors->first() }}
                    </div>
                @endif

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <a href="{{ route('admin.export.curriculum-subjects') }}"
                       class="flex flex-col items-center p-5 bg-blue-50 border border-blue-200 rounded-xl hover:bg-blue-100 transition">
                        <svg class="w-10 h-10 text-blue-600 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                        </svg>
                        <span class="text-sm font-bold text-gray-800">Curriculum Subjects</span>
                        <span class="text-xs text-gray-500 mt-1">curriculum_subjects jadvali</span>
                    </a>

                    <a href="{{ route('admin.export.semesters') }}"
                       class="flex flex-col items-center p-5 bg-green-50 border border-green-200 rounded-xl hover:bg-green-100 transition">
                        <svg class="w-10 h-10 text-green-600 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                        </svg>
                        <span class="text-sm font-bold text-gray-800">Semesters</span>
                        <span class="text-xs text-gray-500 mt-1">semesters jadvali</span>
                    </a>

                    <a href="{{ route('admin.export.curricula') }}"
                       class="flex flex-col items-center p-5 bg-purple-50 border border-purple-200 rounded-xl hover:bg-purple-100 transition">
                        <svg class="w-10 h-10 text-purple-600 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                        </svg>
                        <span class="text-sm font-bold text-gray-800">Curricula</span>
                        <span class="text-xs text-gray-500 mt-1">curricula jadvali</span>
                    </a>

                    <a href="{{ route('admin.export.groups') }}"
                       class="flex flex-col items-center p-5 bg-rose-50 border border-rose-200 rounded-xl hover:bg-rose-100 transition">
                        <svg class="w-10 h-10 text-rose-600 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                        </svg>
                        <span class="text-sm font-bold text-gray-800">Guruhlar ro'yxati</span>
                        <span class="text-xs text-gray-500 mt-1">groups jadvali</span>
                    </a>

                    <a href="{{ route('admin.export.lesson-pairs') }}"
                       class="flex flex-col items-center p-5 bg-cyan-50 border border-cyan-200 rounded-xl hover:bg-cyan-100 transition">
                        <svg class="w-10 h-10 text-cyan-600 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6l4 2m5-2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="text-sm font-bold text-gray-800">Juftliklar (dars soatlari)</span>
                        <span class="text-xs text-gray-500 mt-1">kodi, nomi va vaqti — schedules jadvalidan</span>
                    </a>

                    {{-- Baho qo'yish vaqti: tugma modal ochadi, filtrlar o'sha yerda --}}
                    <button type="button" @click="timingOpen = true"
                            class="flex flex-col items-center p-5 bg-indigo-50 border border-indigo-200 rounded-xl hover:bg-indigo-100 transition text-left">
                        <svg class="w-10 h-10 text-indigo-600 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <span class="text-sm font-bold text-gray-800">Baho qo'yish vaqti</span>
                        <span class="text-xs text-gray-500 mt-1 text-center">o'qituvchilar kesimida · sana, guruh va semestr bo'yicha · faqat bakalavr</span>
                        <span class="mt-3 inline-flex items-center gap-1.5 px-4 py-2 text-xs font-bold text-white bg-indigo-600 rounded-lg">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                            </svg>
                            Hisoblash
                        </span>
                    </button>

                    <a href="{{ route('admin.export.teachers') }}"
                       class="flex flex-col items-center p-5 bg-blue-50 border border-blue-200 rounded-xl hover:bg-blue-100 transition">
                        <svg class="w-10 h-10 text-blue-600 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                        </svg>
                        <span class="text-sm font-bold text-gray-800">Xodimlar</span>
                        <span class="text-xs text-gray-500 mt-1">teachers jadvali — HEMIS id bilan</span>
                    </a>

                    <a href="{{ route('admin.tutors.index') }}"
                       class="flex flex-col items-center p-5 bg-amber-50 border border-amber-200 rounded-xl hover:bg-amber-100 transition">
                        <svg class="w-10 h-10 text-amber-600 mb-3" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/>
                        </svg>
                        <span class="text-sm font-bold text-gray-800">Tyutorlar ro'yxati</span>
                        <span class="text-xs text-gray-500 mt-1">Har bir tyutor guruhlarini Excelga yuklab olish</span>
                    </a>
                </div>
            </div>
        </div>

        {{-- Baho qo'yish vaqti — filtrlar modali (Tailwind build yo'q, shuning uchun inline stil) --}}
        <style>
            .tm-overlay { position:fixed; inset:0; z-index:9999; display:flex; align-items:center; justify-content:center; padding:16px; background:rgba(15,23,42,.55); }
            .tm-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
            .tm-label { display:block; font-size:11px; font-weight:700; color:#475569; margin-bottom:4px; }
            .tm-input { width:100%; box-sizing:border-box; height:34px; padding:0 10px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px; color:#1e293b; background:#fff; }
            .tm-input:focus { outline:none; border-color:#6366f1; box-shadow:0 0 0 3px rgba(99,102,241,.18); }
            .tm-input:disabled { background:#f1f5f9; color:#94a3b8; }
            .tm-full { grid-column:1 / -1; }
            .tm-btn { display:inline-flex; align-items:center; gap:6px; height:36px; padding:0 16px; border-radius:9px; font-size:13px; font-weight:700; cursor:pointer; border:none; }
            /* select2 — jurnal sahifasidagi ko'rinish */
            #timingModalBox .select2-container--classic .select2-selection--single { height:34px; border:1px solid #cbd5e1; border-radius:8px; background:#fff; }
            #timingModalBox .select2-container--classic .select2-selection--single .select2-selection__rendered { line-height:32px; font-size:13px; color:#1e293b; padding-left:10px; }
            #timingModalBox .select2-container--classic .select2-selection--single .select2-selection__arrow { height:32px; }
            #timingModalBox .select2-dropdown { border-color:#cbd5e1; border-radius:8px; font-size:13px; }
        </style>
        <div x-show="timingOpen" class="tm-overlay" style="display:none;">
            <div id="timingModalBox" style="width:100%; max-width:480px; background:#fff; border-radius:14px; box-shadow:0 24px 60px rgba(15,23,42,.3); overflow:visible;" @click.outside="timingOpen = false">
                <div style="display:flex; align-items:center; gap:10px; padding:12px 16px; background:linear-gradient(135deg,#4338ca,#6366f1); color:#fff; border-radius:14px 14px 0 0;">
                    <svg style="width:20px;height:20px;flex:0 0 20px;" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div style="min-width:0;">
                        <div style="font-size:14px; font-weight:700; line-height:1.2;">Baho qo'yish vaqti</div>
                        <div style="font-size:11px; opacity:.85;">Faqat bakalavr · guruh tanlanmasa — hammasi</div>
                    </div>
                    <button type="button" @click="timingOpen = false" style="margin-left:auto; background:none; border:none; color:#fff; font-size:22px; line-height:1; cursor:pointer; opacity:.85;">&times;</button>
                </div>

                <form method="GET" action="{{ route('admin.export.teacher-grade-timing') }}" id="timingForm" style="padding:14px 16px;">
                    <div class="tm-grid">
                        <div>
                            <label class="tm-label">Sanadan <span style="color:#dc2626;">*</span></label>
                            <input type="date" name="date_from" required class="tm-input"
                                   value="{{ old('date_from', now('Asia/Tashkent')->startOfMonth()->toDateString()) }}">
                        </div>
                        <div>
                            <label class="tm-label">Sanagacha <span style="color:#dc2626;">*</span></label>
                            <input type="date" name="date_to" required class="tm-input"
                                   value="{{ old('date_to', now('Asia/Tashkent')->subDay()->toDateString()) }}">
                        </div>

                        <div class="tm-full">
                            <label class="tm-label">Guruh</label>
                            {{-- Jurnal sahifasidagi kabi: yozib qidiriladi, AJAX orqali topiladi --}}
                            <select name="group_id" id="tm-group" style="width:100%;"><option value="">Barchasi</option></select>
                        </div>

                        <div class="tm-full">
                            <label class="tm-label">Semestr <span id="tm-sem-hint" style="font-weight:400; color:#94a3b8;">— avval guruhni tanlang</span></label>
                            <select name="semester_code" id="tm-semester" class="tm-input" disabled><option value="">Barchasi</option></select>
                        </div>
                    </div>

                    <div style="display:flex; justify-content:flex-end; gap:8px; margin-top:14px;">
                        <button type="button" @click="timingOpen = false" class="tm-btn" style="background:#f1f5f9; color:#475569;">Bekor qilish</button>
                        <button type="submit" class="tm-btn" style="background:#4f46e5; color:#fff;">
                            <svg style="width:15px;height:15px;" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3"/>
                            </svg>
                            Excel yuklab olish
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
        <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
        <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
        <script>
        $(function () {
            var BAKALAVR = @json($bakalavrCode ?? '');
            var $group = $('#tm-group');
            var $semester = $('#tm-semester');
            var $hint = $('#tm-sem-hint');

            // Guruh: jurnaldagi filtr bilan bir xil — har bir yozilgan harfda serverdan qidiradi
            $group.select2({
                theme: 'classic',
                width: '100%',
                allowClear: true,
                placeholder: 'Barchasi',
                minimumInputLength: 0,
                dropdownParent: $('#timingModalBox'),
                ajax: {
                    url: '{{ route("admin.journal.get-groups") }}',
                    dataType: 'json',
                    delay: 200,
                    data: function (params) {
                        return { education_type: BAKALAVR, search: params.term || '' };
                    },
                    processResults: function (data) {
                        var results = [];
                        $.each(data, function (id, name) { results.push({ id: id, text: name }); });
                        return { results: results };
                    },
                    cache: true
                }
            }).on('select2:open', function () {
                setTimeout(function () {
                    var sf = document.querySelector('.select2-container--open .select2-search__field');
                    if (sf) sf.focus();
                }, 10);
            });

            // Guruh tanlangach — shu guruh o'qigan semestrlar
            function loadSemesters(groupId) {
                $semester.html('<option value="">Barchasi</option>');
                if (!groupId) {
                    $semester.prop('disabled', true);
                    $hint.text('— avval guruhni tanlang');
                    return;
                }
                $semester.prop('disabled', true);
                $hint.text('yuklanmoqda...');
                $.getJSON('{{ route("admin.export.teacher-grade-timing.semesters") }}', { group_id: groupId })
                    .done(function (data) {
                        var n = 0;
                        $.each(data, function (code, name) {
                            $semester.append($('<option>', { value: code, text: name }));
                            n++;
                        });
                        $hint.text(n ? '(' + n + ' ta)' : '— bu guruhda semestr topilmadi');
                    })
                    .fail(function () { $hint.text("— yuklab bo'lmadi"); })
                    .always(function () { $semester.prop('disabled', false); });
            }

            $group.on('change', function () { loadSemesters($(this).val()); });
        });
        </script>
    </div>
</x-app-layout>
