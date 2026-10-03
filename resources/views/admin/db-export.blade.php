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
                        <span class="text-xs text-gray-500 mt-1 text-center">o'qituvchilar kesimida · sana, guruh, semestr, o'qituvchi bo'yicha · faqat bakalavr</span>
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
        </style>
        <div x-show="timingOpen" class="tm-overlay" style="display:none;">
            <div style="width:100%; max-width:520px; background:#fff; border-radius:14px; box-shadow:0 24px 60px rgba(15,23,42,.3); overflow:hidden;" @click.outside="timingOpen = false">
                <div style="display:flex; align-items:center; gap:10px; padding:12px 16px; background:linear-gradient(135deg,#4338ca,#6366f1); color:#fff;">
                    <svg style="width:20px;height:20px;flex:0 0 20px;" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <div style="min-width:0;">
                        <div style="font-size:14px; font-weight:700; line-height:1.2;">Baho qo'yish vaqti</div>
                        <div style="font-size:11px; opacity:.85;">Faqat bakalavr · bo'sh filtr = barchasi</div>
                    </div>
                    <button type="button" @click="timingOpen = false" style="margin-left:auto; background:none; border:none; color:#fff; font-size:22px; line-height:1; cursor:pointer; opacity:.85;">&times;</button>
                </div>

                <form method="GET" action="{{ route('admin.export.teacher-grade-timing') }}" id="timingForm" style="padding:14px 16px;">
                    <style>
                        .tm-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px; }
                        .tm-label { display:block; font-size:11px; font-weight:700; color:#475569; margin-bottom:4px; }
                        .tm-input { width:100%; box-sizing:border-box; height:34px; padding:0 10px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px; color:#1e293b; background:#fff; }
                        .tm-input:focus { outline:none; border-color:#6366f1; box-shadow:0 0 0 3px rgba(99,102,241,.18); }
                        .tm-input:disabled { background:#f1f5f9; color:#94a3b8; }
                        .tm-full { grid-column:1 / -1; }
                        .tm-btn { display:inline-flex; align-items:center; gap:6px; height:36px; padding:0 16px; border-radius:9px; font-size:13px; font-weight:700; cursor:pointer; border:none; }
                    </style>

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

                        <div>
                            <label class="tm-label">Fakultet</label>
                            <select name="faculty_id" id="tm-faculty" class="tm-input">
                                <option value="">Barchasi</option>
                                @foreach(($timingFaculties ?? []) as $fac)
                                    <option value="{{ $fac->id }}" {{ old('faculty_id') == $fac->id ? 'selected' : '' }}>{{ $fac->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="tm-label">Yo'nalish</label>
                            <select name="specialty_id" id="tm-specialty" class="tm-input"><option value="">Barchasi</option></select>
                        </div>

                        <div>
                            <label class="tm-label">Kurs</label>
                            <select name="level_code" id="tm-level" class="tm-input"><option value="">Barchasi</option></select>
                        </div>
                        <div>
                            <label class="tm-label">Semestr</label>
                            <select name="semester_code" id="tm-semester" class="tm-input"><option value="">Barchasi</option></select>
                        </div>

                        <div class="tm-full">
                            <label class="tm-label">Guruh</label>
                            <select name="group_id" id="tm-group" class="tm-input"><option value="">Barchasi</option></select>
                        </div>

                        <div class="tm-full">
                            <label class="tm-label">O'qituvchi</label>
                            <select name="employee_id" class="tm-input">
                                <option value="">Barchasi</option>
                                @foreach(($timingTeachers ?? []) as $tch)
                                    <option value="{{ $tch->hemis_id }}" {{ old('employee_id') == $tch->hemis_id ? 'selected' : '' }}>{{ $tch->full_name }}</option>
                                @endforeach
                            </select>
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

        <script>
        (function () {
            // Kaskad: fakultet → yo'nalish → kurs → semestr → guruh. Faqat bakalavr —
            // jurnal filtr endpointlariga education_type bilan beriladi.
            var BAKALAVR = @json($bakalavrCode ?? '');
            var urls = {
                specialties: '{{ route("admin.journal.get-specialties") }}',
                levels: '{{ route("admin.journal.get-level-codes") }}',
                semesters: '{{ route("admin.journal.get-semesters") }}',
                groups: '{{ route("admin.journal.get-groups") }}'
            };
            var el = function (id) { return document.getElementById(id); };
            var faculty = el('tm-faculty'), specialty = el('tm-specialty'), level = el('tm-level'),
                semester = el('tm-semester'), group = el('tm-group');

            function params(extra) {
                var p = new URLSearchParams();
                if (BAKALAVR) p.set('education_type', BAKALAVR);
                if (faculty.value) p.set('faculty_id', faculty.value);
                if (extra.specialty && specialty.value) p.set('specialty_id', specialty.value);
                if (extra.level && level.value) p.set('level_code', level.value);
                if (extra.semester && semester.value) p.set('semester_code', semester.value);
                return p.toString();
            }

            // {kalit: nom} javobini select'ga to'kish, avvalgi tanlov saqlansa saqlanadi
            function fill(select, data) {
                var prev = select.value;
                select.innerHTML = '<option value="">Barchasi</option>';
                Object.keys(data).forEach(function (key) {
                    var o = document.createElement('option');
                    o.value = key;
                    o.textContent = data[key];
                    select.appendChild(o);
                });
                if (prev && select.querySelector('option[value="' + prev + '"]')) select.value = prev;
            }

            function load(url, query, select) {
                select.disabled = true;
                return fetch(url + '?' + query, { headers: { 'Accept': 'application/json' } })
                    .then(function (r) { return r.json(); })
                    .then(function (data) { fill(select, data || {}); })
                    .catch(function () { fill(select, {}); })
                    .finally(function () { select.disabled = false; });
            }

            function reload(from) {
                // Yuqoridagi tanlov o'zgarsa, pastdagilar qayta yuklanadi
                var chain = [];
                if (from === 'faculty') chain.push(load(urls.specialties, params({}), specialty));
                if (from === 'faculty' || from === 'specialty') chain.push(load(urls.levels, params({ specialty: true }), level));
                if (from !== 'semester') chain.push(load(urls.semesters, params({ specialty: true, level: true }), semester));
                Promise.all(chain).then(function () {
                    load(urls.groups, params({ specialty: true, level: true, semester: true }), group);
                });
            }

            faculty.addEventListener('change', function () { reload('faculty'); });
            specialty.addEventListener('change', function () { reload('specialty'); });
            level.addEventListener('change', function () { reload('level'); });
            semester.addEventListener('change', function () { reload('semester'); });

            reload('faculty');
        })();
        </script>
    </div>
</x-app-layout>
