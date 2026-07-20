<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            O'quv reja to'g'riligi
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">

            @if(session('success'))
                <div class="mb-4 p-4 bg-green-100 border border-green-300 text-green-800 rounded-lg">{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="mb-4 p-4 bg-red-100 border border-red-300 text-red-800 rounded-lg">{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="mb-4 p-4 bg-red-100 border border-red-300 text-red-800 rounded-lg">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            {{-- Ro'yxatlar --}}
            @php
                $namunaviyList = $curricula->where('type', 'namunaviy');
                $ishchiList = $curricula->where('type', 'ishchi');
            @endphp

            {{-- Asosiy vkladkalar --}}
            <div class="mb-6 border-b border-gray-200">
                <nav class="flex flex-wrap gap-1" aria-label="Tabs">
                    <button type="button" data-tab="rejalar"
                            class="main-tab px-5 py-2.5 text-sm font-semibold border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                        O'quv rejalar
                    </button>
                    <button type="button" data-tab="solishtirish"
                            class="main-tab px-5 py-2.5 text-sm font-semibold border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                        Solishtirish
                        @if($savedComparisons->isNotEmpty())
                            <span class="ml-1 inline-flex items-center justify-center px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-800">{{ $savedComparisons->count() }}</span>
                        @endif
                    </button>
                    <button type="button" data-tab="yonalish"
                            class="main-tab px-5 py-2.5 text-sm font-semibold border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                        Yo'nalish bo'yicha
                    </button>
                    <button type="button" data-tab="fanlar"
                            class="main-tab px-5 py-2.5 text-sm font-semibold border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                        O'tiladigan fanlar
                    </button>
                </nav>
            </div>

            {{-- ===== PANEL: O'quv rejalar ===== --}}
            <div data-panel="rejalar">
            {{-- Yangi reja yuklash --}}
            <div class="bg-white shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Yangi o'quv reja yuklash (Excel)</h3>
                    <form method="POST" action="{{ route('admin.oquv-reja.store') }}" enctype="multipart/form-data"
                          id="upload-form" class="space-y-4">
                        @csrf

                        {{-- 1-qator: reja turi + rejim toggle --}}
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Reja turi</label>
                                <select name="type" required class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="namunaviy">Namunaviy o'quv reja</option>
                                    <option value="ishchi">Ishchi o'quv reja</option>
                                </select>
                            </div>
                            <div class="md:col-span-2 flex items-center">
                                <label class="flex items-start gap-2 cursor-pointer select-none px-3 py-2 rounded-lg border border-amber-200 bg-amber-50 hover:bg-amber-100">
                                    <input type="checkbox" id="kelajak-toggle"
                                           class="mt-0.5 rounded border-gray-300 text-amber-500 shadow-sm focus:ring-amber-400">
                                    <span>
                                        <span class="text-sm font-semibold text-amber-800">Kelajak rejalari rejimi</span>
                                        <span class="block text-xs text-amber-600">
                                            Talabalar hali keyingi kursga o'tmagan — cascade o'rniga to'g'ridan-to'g'ri HEMIS bazasidan qidiruv
                                        </span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        {{-- Cascade blok (odatiy rejim) --}}
                        <div id="cascade-block" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Ta'lim turi</label>
                                <select id="cascade-education-type" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="">Tanlang</option>
                                    @foreach($educationTypes as $et)
                                        <option value="{{ $et->education_type_code }}">{{ $et->education_type_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Fakultet</label>
                                <select id="cascade-faculty" disabled class="w-full rounded-md border-gray-300 shadow-sm text-sm disabled:bg-gray-100">
                                    <option value="">Avval ta'lim turini tanlang</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Yo'nalish</label>
                                <select id="cascade-specialty" disabled class="w-full rounded-md border-gray-300 shadow-sm text-sm disabled:bg-gray-100">
                                    <option value="">Avval fakultetni tanlang</option>
                                </select>
                            </div>
                            <div class="md:col-span-3 flex items-center gap-2">
                                <input type="checkbox" id="cascade-current-toggle" checked
                                       class="rounded border-gray-300 text-blue-600 shadow-sm">
                                <label for="cascade-current-toggle" class="text-sm font-medium text-gray-700">
                                    Joriy kurs va semestr avtomatik tanlansin
                                </label>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Kurs</label>
                                <select id="cascade-level" name="level_code" disabled class="w-full rounded-md border-gray-300 shadow-sm text-sm disabled:bg-gray-100">
                                    <option value="">Avval yo'nalishni tanlang</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Semestr</label>
                                <select id="cascade-semester" name="semester_code" disabled class="w-full rounded-md border-gray-300 shadow-sm text-sm disabled:bg-gray-100">
                                    <option value="">Avval kursni tanlang</option>
                                </select>
                            </div>
                        </div>

                        {{-- Kelajak rejim bloki --}}
                        <div id="kelajak-block" class="hidden grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">O'quv yil</label>
                                <select id="kelajak-year" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="">Barcha yillar</option>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nom yoki yo'nalish kodi bo'yicha qidiruv</label>
                                <input type="text" id="kelajak-search"
                                       placeholder="Masalan: 5510100 yoki Tibbiyot..."
                                       class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:ring-amber-500 focus:border-amber-500">
                            </div>
                            <div class="md:col-span-3">
                                <p class="text-xs text-amber-700 bg-amber-50 rounded-md px-3 py-2 border border-amber-200">
                                    HEMIS'da mavjud barcha o'quv rejalar qidiriladi — talabalar o'tishini kutmasdan keyingi o'quv yili rejalarini yuklash mumkin.
                                    Qidiruv maydonini bo'sh qoldirsangiz, tanlangan yildagi barcha rejalar chiqadi.
                                </p>
                            </div>
                        </div>

                        {{-- O'quv reja + Excel fayl (ikkala rejimda ham) --}}
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium text-gray-700 mb-1">O'quv reja (HEMIS)</label>
                                <select id="cascade-curriculum" name="curricula_hemis_id" required disabled
                                        class="w-full rounded-md border-gray-300 shadow-sm text-sm disabled:bg-gray-100">
                                    <option value="">Avval semestrni tanlang</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Excel fayl (.xlsx)</label>
                                <input type="file" name="file" required accept=".xlsx,.xls"
                                       class="w-full text-sm text-gray-700 border border-gray-300 rounded-md">
                            </div>
                        </div>

                        <div>
                            <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-md hover:bg-green-700">
                                Yuklash
                            </button>
                            <span class="ml-3 text-sm text-gray-500">
                                Ustunlar: Fan kodi, Fan nomi, Blok, Kurs (ishchi uchun), Semestr, Umumiy yuklama (soat),
                                Ma'ruza, Amaliy, Laboratoriya, Seminar, Mustaqil ta'lim, Kredit.
                                Bir fan bir nechta semestrda o'tilsa — har semestri alohida qator.
                            </span>
                        </div>
                    </form>

                    <script>
                        (function () {
                            const optionsUrl = @json(route('admin.oquv-reja.options'));
                            const selects = {
                                educationType: document.getElementById('cascade-education-type'),
                                faculty: document.getElementById('cascade-faculty'),
                                specialty: document.getElementById('cascade-specialty'),
                                level: document.getElementById('cascade-level'),
                                semester: document.getElementById('cascade-semester'),
                                curriculum: document.getElementById('cascade-curriculum'),
                            };
                            const chain = ['faculty', 'specialty', 'level', 'semester', 'curriculum'];

                            // ===== Cascade rejim =====
                            function reset(fromKey, placeholder) {
                                let clearing = false;
                                for (const key of chain) {
                                    if (key === fromKey) clearing = true;
                                    if (!clearing) continue;
                                    const sel = selects[key];
                                    sel.innerHTML = '<option value="">' + (key === fromKey ? placeholder : 'Avval oldingi maydonni tanlang') + '</option>';
                                    sel.disabled = true;
                                }
                            }

                            function cascadeParams() {
                                const p = new URLSearchParams();
                                if (selects.educationType.value) p.set('education_type_code', selects.educationType.value);
                                if (selects.faculty.value) p.set('department_id', selects.faculty.value);
                                if (selects.specialty.value) p.set('specialty_id', selects.specialty.value);
                                if (selects.level.value) p.set('level_code', selects.level.value);
                                if (selects.semester.value) p.set('semester_code', selects.semester.value);
                                if (document.getElementById('cascade-current-toggle').checked) p.set('current_only', '1');
                                return p;
                            }

                            async function fetchItems(list, extra = {}) {
                                const p = cascadeParams();
                                p.set('list', list);
                                for (const [k, v] of Object.entries(extra)) p.set(k, v);
                                const r = await fetch(optionsUrl + '?' + p.toString(), {headers: {'Accept': 'application/json'}});
                                return r.json();
                            }

                            async function load(list, targetKey, labelFn, valueFn, autoSelect = null) {
                                const target = selects[targetKey];
                                reset(targetKey, 'Yuklanmoqda...');
                                try {
                                    const items = await fetchItems(list);
                                    target.innerHTML = '<option value="">Tanlang</option>';
                                    for (const item of items) {
                                        const opt = document.createElement('option');
                                        opt.value = valueFn(item);
                                        opt.textContent = labelFn(item);
                                        target.appendChild(opt);
                                    }
                                    target.disabled = false;
                                    if (!items.length) {
                                        target.innerHTML = "<option value=''>Ma'lumot topilmadi</option>";
                                    } else if (autoSelect !== null && items.some(i => String(valueFn(i)) === String(autoSelect))) {
                                        target.value = String(autoSelect);
                                        target.dispatchEvent(new Event('change'));
                                    }
                                    return items;
                                } catch (e) {
                                    target.innerHTML = "<option value=''>Xatolik, qayta urinib ko'ring</option>";
                                    return [];
                                }
                            }

                            const cnt = i => i.student_count ? ' (' + i.student_count + ' ta talaba)' : '';
                            const semesterLabel = i => (i.name || i.code) + cnt(i);
                            const curriculumLabel = i => (i.name || ('Reja #' + i.id)) + (i.exists ? '' : " ❌ (curricula jadvalida yo'q)") + cnt(i);

                            selects.educationType.addEventListener('change', function () {
                                if (!this.value) return reset('faculty', "Avval ta'lim turini tanlang");
                                load('faculties', 'faculty', i => i.name, i => i.id);
                            });
                            selects.faculty.addEventListener('change', function () {
                                if (!this.value) return reset('specialty', 'Avval fakultetni tanlang');
                                load('specialties', 'specialty', i => (i.code ? i.code + ' — ' : '') + i.name + cnt(i), i => i.id);
                            });
                            selects.specialty.addEventListener('change', async function () {
                                if (!this.value) return reset('level', "Avval yo'nalishni tanlang");
                                const currentToggle = document.getElementById('cascade-current-toggle');
                                let autoLevel = null;
                                if (currentToggle.checked) {
                                    const levels = await fetchItems('levels');
                                    if (levels.length === 1) autoLevel = levels[0].level_code;
                                }
                                load('levels', 'level', i => (i.level_name || i.level_code) + cnt(i), i => i.level_code, autoLevel);
                            });
                            selects.level.addEventListener('change', async function () {
                                if (!this.value) return reset('semester', 'Avval kursni tanlang');
                                const currentToggle = document.getElementById('cascade-current-toggle');
                                let autoSemester = null;
                                if (currentToggle.checked) {
                                    const semesters = await fetchItems('semesters', {level_code: this.value});
                                    if (semesters.length > 0) {
                                        autoSemester = semesters.reduce((a, b) => b.student_count > a.student_count ? b : a).code;
                                    }
                                }
                                load('semesters', 'semester', semesterLabel, i => i.code, autoSemester);
                            });
                            selects.semester.addEventListener('change', function () {
                                if (!this.value) return reset('curriculum', 'Avval semestrni tanlang');
                                load('curricula', 'curriculum', curriculumLabel, i => i.id);
                            });
                            document.getElementById('cascade-current-toggle').addEventListener('change', function () {
                                if (selects.specialty.value) selects.specialty.dispatchEvent(new Event('change'));
                            });

                            // ===== Kelajak rejimi =====
                            const kelajakToggle = document.getElementById('kelajak-toggle');
                            const kelajakBlock = document.getElementById('kelajak-block');
                            const cascadeBlock = document.getElementById('cascade-block');
                            const kelajakYear = document.getElementById('kelajak-year');
                            const kelajakSearch = document.getElementById('kelajak-search');
                            let kelajakTimer = null;

                            async function loadKelajakYears() {
                                const r = await fetch(optionsUrl + '?list=education_years', {headers: {'Accept': 'application/json'}});
                                const years = await r.json();
                                kelajakYear.innerHTML = '<option value="">Barcha yillar</option>';
                                for (const y of years) {
                                    const opt = document.createElement('option');
                                    opt.value = y.code;
                                    opt.textContent = y.name || y.code;
                                    kelajakYear.appendChild(opt);
                                }
                                // Birinchi (eng so'nggi) yilni avtomatik tanlash
                                if (years.length > 0) {
                                    kelajakYear.value = years[0].code;
                                    await searchKelajakCurricula();
                                }
                            }

                            async function searchKelajakCurricula() {
                                const cur = selects.curriculum;
                                cur.innerHTML = "<option value=''>Qidirilmoqda...</option>";
                                cur.disabled = true;
                                const p = new URLSearchParams({list: 'all_curricula'});
                                if (kelajakYear.value) p.set('education_year_code', kelajakYear.value);
                                if (kelajakSearch.value.trim()) p.set('q', kelajakSearch.value.trim());
                                try {
                                    const r = await fetch(optionsUrl + '?' + p.toString(), {headers: {'Accept': 'application/json'}});
                                    const items = await r.json();
                                    cur.innerHTML = '<option value="">Tanlang (' + items.length + ' ta reja)</option>';
                                    for (const item of items) {
                                        const opt = document.createElement('option');
                                        opt.value = item.id;
                                        opt.textContent = (item.education_year_name ? '[' + item.education_year_name + '] ' : '') + item.name;
                                        cur.appendChild(opt);
                                    }
                                    cur.disabled = items.length === 0;
                                    if (!items.length) cur.innerHTML = "<option value=''>Reja topilmadi — qidiruvni o'zgartiring</option>";
                                } catch (e) {
                                    cur.innerHTML = "<option value=''>Xatolik yuz berdi</option>";
                                }
                            }

                            kelajakToggle.addEventListener('change', async function () {
                                const on = this.checked;
                                kelajakBlock.classList.toggle('hidden', !on);
                                cascadeBlock.classList.toggle('hidden', on);
                                if (on) {
                                    // Cascade natijalarini tozalash
                                    reset('faculty', "Avval ta'lim turini tanlang");
                                    selects.educationType.value = '';
                                    selects.curriculum.innerHTML = "<option value=''>Yuklanmoqda...</option>";
                                    selects.curriculum.disabled = true;
                                    await loadKelajakYears();
                                } else {
                                    // Cascade rejimga qaytish
                                    reset('curriculum', 'Avval semestrni tanlang');
                                    selects.curriculum.innerHTML = "<option value=''>Avval semestrni tanlang</option>";
                                    selects.curriculum.disabled = true;
                                }
                            });

                            kelajakYear.addEventListener('change', () => searchKelajakCurricula());
                            kelajakSearch.addEventListener('input', function () {
                                clearTimeout(kelajakTimer);
                                kelajakTimer = setTimeout(searchKelajakCurricula, 400);
                            });

                            // ===== Diagnostika =====
                            document.addEventListener('DOMContentLoaded', function () {
                                const diagBtn = document.getElementById('diagnose-btn');
                                const diagBox = document.getElementById('diagnose-result');
                                if (!diagBtn || !diagBox) return;
                                diagBtn.addEventListener('click', async function () {
                                    if (!selects.faculty.value) {
                                        diagBox.innerHTML = "<div class='text-sm text-red-600 p-3'>Avval ta'lim turi va fakultetni tanlang.</div>";
                                        return;
                                    }
                                    diagBox.innerHTML = "<div class='text-sm text-gray-500 p-3'>Yuklanmoqda...</div>";
                                    let rows;
                                    try {
                                        rows = await fetchItems('diagnose');
                                    } catch (e) {
                                        diagBox.innerHTML = "<div class='text-sm text-red-600 p-3'>Xatolik: " + e + "</div>";
                                        return;
                                    }
                                    if (!rows.length) {
                                        diagBox.innerHTML = "<div class='text-sm text-gray-500 p-3'>Bu fakultetda talaba topilmadi.</div>";
                                        return;
                                    }
                                    let html = '<div class="overflow-x-auto"><table class="min-w-full text-xs border"><thead class="bg-gray-100"><tr>' +
                                        ["Yo'nalish", 'spec_id', 'Kurs', 'Semestr', 'Talaba', 'Status', 'Reja ID', 'Reja nomi', 'Semestr "current"?']
                                            .map(h => '<th class="px-2 py-1 text-left border">' + h + '</th>').join('') +
                                        '</tr></thead><tbody>';
                                    for (const r of rows) {
                                        const bad = !r.curriculum_exists;
                                        html += '<tr class="' + (bad ? 'bg-red-50' : '') + '">' +
                                            '<td class="px-2 py-1 border">' + r.specialty + '</td>' +
                                            '<td class="px-2 py-1 border">' + (r.specialty_id ?? '') + '</td>' +
                                            '<td class="px-2 py-1 border">' + (r.level_name ?? '') + '</td>' +
                                            '<td class="px-2 py-1 border">' + (r.semester ?? '') + '</td>' +
                                            '<td class="px-2 py-1 border text-right">' + r.student_count + '</td>' +
                                            '<td class="px-2 py-1 border">' + (r.status_code ?? '') + '</td>' +
                                            '<td class="px-2 py-1 border">' + (r.curriculum_id ?? '') + '</td>' +
                                            '<td class="px-2 py-1 border">' + r.curriculum_name + '</td>' +
                                            '<td class="px-2 py-1 border">' + (r.semester_is_current ? '✅' : '—') + '</td>' +
                                            '</tr>';
                                    }
                                    html += '</tbody></table></div>';
                                    diagBox.innerHTML = html;
                                });
                            });
                        })();
                    </script>
                </div>
            </div>

            {{-- Rejalashtirilgan reja (HEMIS'siz) --}}
            <div class="bg-white shadow-sm sm:rounded-lg mb-6 border-l-4 border-amber-400">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-1">⏳ Rejalashtirilgan ishchi reja (HEMIS'siz)</h3>
                    <p class="text-sm text-gray-500 mb-4">
                        HEMIS'da reja hali yaratilmagan kohortlar uchun: sentyabrdagi 1-kurschilar yoki yangi
                        joriy etilayotgan yo'nalishlar (masalan Oliy hamshiralik ishi). Reja avvaldan tuziladi,
                        keyin HEMIS reja paydo bo'lganda bog'lanadi.
                    </p>

                    <div class="flex gap-4 mb-4">
                        <label class="flex items-center gap-1.5 text-sm cursor-pointer">
                            <input type="radio" name="planned_mode" value="copy" class="text-amber-600" checked> O'tgan yildan nusxa
                        </label>
                        <label class="flex items-center gap-1.5 text-sm cursor-pointer">
                            <input type="radio" name="planned_mode" value="new" class="text-amber-600"> Yangi yo'nalish (noldan)
                        </label>
                    </div>

                    {{-- NUSXA rejimi --}}
                    <div id="planned-copy" class="space-y-3">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Yangi reja yili</label>
                                <input type="text" id="pcYear" value="2026-2027" placeholder="2026-2027"
                                       class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-medium text-gray-600 mb-1">Manba rejalarni qidirish (nom/kod)</label>
                                <input type="text" id="pcFilter" placeholder="Filtrlash uchun yozing..."
                                       class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                            </div>
                        </div>
                        <div id="pcList" class="max-h-64 overflow-y-auto rounded-md border border-gray-200 divide-y divide-gray-100 text-sm">
                            <p class="px-3 py-2 text-gray-400 italic">Yuklanmoqda...</p>
                        </div>
                        <button type="button" id="pcSubmit"
                                class="px-4 py-2 text-sm bg-amber-600 text-white rounded-md hover:bg-amber-700 disabled:opacity-50">
                            Tanlangan rejalardan nusxa yaratish
                        </button>
                        <div id="pcResult" class="hidden text-sm rounded-md px-4 py-2"></div>
                    </div>

                    {{-- YANGI YO'NALISH rejimi --}}
                    <div id="planned-new" class="hidden space-y-3">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Yo'nalish nomi <span class="text-red-500">*</span></label>
                                <input type="text" id="pnName" placeholder="Masalan: Oliy hamshiralik ishi"
                                       class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Yo'nalish kodi</label>
                                <input type="text" id="pnCode" placeholder="Masalan: 60910500"
                                       class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Reja yili</label>
                                <input type="text" id="pnYear" value="2026-2027" placeholder="2026-2027"
                                       class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Kurs</label>
                                <select id="pnKurs" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    @for($k = 1; $k <= 6; $k++)
                                        <option value="{{ $k }}">{{ $k }}-kurs</option>
                                    @endfor
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Ta'lim turi</label>
                                <select id="pnEduType" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="">—</option>
                                    @foreach($educationTypes as $et)
                                        <option value="{{ $et->education_type_name }}">{{ $et->education_type_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Excel fayl (ixtiyoriy)</label>
                                <input type="file" id="pnFile" accept=".xlsx,.xls"
                                       class="w-full text-sm text-gray-700 border border-gray-300 rounded-md">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1.5">Semestrlar</label>
                            <div id="pnSems" class="flex items-center gap-4 text-sm"></div>
                        </div>
                        <p class="text-xs text-gray-500">
                            Excel yuklamasangiz, bo'sh reja yaratiladi — fanlarni keyin reja sahifasida qo'lda qo'shasiz.
                        </p>
                        <button type="button" id="pnSubmit"
                                class="px-4 py-2 text-sm bg-amber-600 text-white rounded-md hover:bg-amber-700 disabled:opacity-50">
                            Rejalashtirilgan reja yaratish
                        </button>
                        <div id="pnResult" class="hidden text-sm rounded-md px-4 py-2"></div>
                    </div>
                </div>
            </div>

            <script>
                (function () {
                    const srcUrl     = @json(route('admin.oquv-reja.planned-sources'));
                    const plannedUrl = @json(route('admin.oquv-reja.store-planned'));
                    const token      = document.querySelector('meta[name="csrf-token"]')?.content
                                       || document.querySelector('input[name="_token"]')?.value;

                    function esc(s) {
                        return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
                    }
                    function showMsg(el, cls, text) {
                        el.className = 'text-sm rounded-md px-4 py-2 ' + cls;
                        el.textContent = text;
                        el.classList.remove('hidden');
                    }

                    // Rejim almashtirish
                    document.querySelectorAll('input[name="planned_mode"]').forEach(r =>
                        r.addEventListener('change', function () {
                            document.getElementById('planned-copy').classList.toggle('hidden', this.value !== 'copy');
                            document.getElementById('planned-new').classList.toggle('hidden', this.value !== 'new');
                        }));

                    // ===== NUSXA rejimi =====
                    let allSources = [];
                    const pcList   = document.getElementById('pcList');
                    const pcFilter = document.getElementById('pcFilter');

                    function renderSources() {
                        const q = pcFilter.value.trim().toLowerCase();
                        const list = allSources.filter(s =>
                            !q || (s.name || '').toLowerCase().includes(q) || (s.specialty_code || '').toLowerCase().includes(q));
                        if (!list.length) { pcList.innerHTML = '<p class="px-3 py-2 text-gray-400 italic">Reja topilmadi</p>'; return; }
                        pcList.innerHTML = list.map(s =>
                            '<label class="flex items-center gap-2 px-3 py-2 hover:bg-amber-50 cursor-pointer">' +
                            '<input type="checkbox" class="pc-src rounded border-gray-300 text-amber-600" value="' + s.id + '">' +
                            '<span class="flex-1"><span class="text-gray-800">' + esc(s.name) + '</span>' +
                            '<span class="text-xs text-gray-400 ml-1">· ' + esc(s.plan_year || '') + ' · ' + s.subjects_count + ' fan</span></span>' +
                            '</label>').join('');
                    }

                    async function loadSources() {
                        try {
                            allSources = await (await fetch(srcUrl, {headers: {'Accept': 'application/json'}})).json();
                            renderSources();
                        } catch (e) {
                            pcList.innerHTML = '<p class="px-3 py-2 text-red-500 italic">Yuklashda xatolik</p>';
                        }
                    }
                    pcFilter.addEventListener('input', renderSources);
                    loadSources();

                    document.getElementById('pcSubmit').addEventListener('click', async function () {
                        const ids = [...document.querySelectorAll('.pc-src:checked')].map(c => c.value);
                        const year = document.getElementById('pcYear').value.trim();
                        const res = document.getElementById('pcResult');
                        if (!ids.length) { showMsg(res, 'bg-yellow-50 text-yellow-800', 'Kamida bitta manba reja tanlang.'); return; }
                        if (!year)       { showMsg(res, 'bg-yellow-50 text-yellow-800', 'Reja yilini kiriting.'); return; }
                        this.disabled = true;
                        showMsg(res, 'bg-gray-50 text-gray-600', 'Yaratilmoqda...');
                        const fd = new FormData();
                        fd.append('_token', token); fd.append('mode', 'copy'); fd.append('plan_year', year);
                        ids.forEach(id => fd.append('source_ids[]', id));
                        try {
                            const r = await fetch(plannedUrl, {method: 'POST', body: fd, headers: {'Accept': 'application/json'}});
                            const j = await r.json();
                            if (!r.ok) throw new Error(j.error || j.message || 'Xatolik');
                            showMsg(res, 'bg-green-50 text-green-800', j.created + ' ta rejalashtirilgan reja yaratildi. Sahifa yangilanmoqda...');
                            setTimeout(() => location.reload(), 1200);
                        } catch (e) {
                            showMsg(res, 'bg-red-50 text-red-700', 'Xatolik: ' + e.message);
                            this.disabled = false;
                        }
                    });

                    // ===== YANGI YO'NALISH rejimi =====
                    const pnKurs = document.getElementById('pnKurs');
                    const pnSems = document.getElementById('pnSems');
                    function rebuildSems() {
                        const k = parseInt(pnKurs.value);
                        const codes = [10 + 2 * k - 1, 10 + 2 * k];
                        pnSems.innerHTML = codes.map(c =>
                            '<label class="flex items-center gap-1 cursor-pointer">' +
                            '<input type="checkbox" class="pn-sem rounded border-gray-300 text-amber-600" value="' + c + '" checked>' +
                            (c - 10) + '-semestr</label>').join('');
                    }
                    pnKurs.addEventListener('change', rebuildSems);
                    rebuildSems();

                    document.getElementById('pnSubmit').addEventListener('click', async function () {
                        const res  = document.getElementById('pnResult');
                        const name = document.getElementById('pnName').value.trim();
                        const sems = [...document.querySelectorAll('.pn-sem:checked')].map(c => c.value);
                        if (!name)       { showMsg(res, 'bg-yellow-50 text-yellow-800', 'Yo\'nalish nomini kiriting.'); return; }
                        if (!sems.length){ showMsg(res, 'bg-yellow-50 text-yellow-800', 'Kamida bitta semestr tanlang.'); return; }
                        this.disabled = true;
                        showMsg(res, 'bg-gray-50 text-gray-600', 'Yaratilmoqda...');
                        const fd = new FormData();
                        fd.append('_token', token); fd.append('mode', 'new');
                        fd.append('plan_year', document.getElementById('pnYear').value.trim());
                        fd.append('specialty_name', name);
                        fd.append('specialty_code', document.getElementById('pnCode').value.trim());
                        fd.append('education_type_name', document.getElementById('pnEduType').value);
                        fd.append('level_code', '1' + parseInt(pnKurs.value)); // kurs k → level_code "1k"
                        sems.forEach(s => fd.append('semester_codes[]', s));
                        const file = document.getElementById('pnFile').files[0];
                        if (file) fd.append('file', file);
                        try {
                            const r = await fetch(plannedUrl, {method: 'POST', body: fd, headers: {'Accept': 'application/json'}});
                            const j = await r.json();
                            if (!r.ok) throw new Error(j.error || j.message || 'Xatolik');
                            showMsg(res, 'bg-green-50 text-green-800', j.created + ' ta rejalashtirilgan reja yaratildi. Sahifa yangilanmoqda...');
                            setTimeout(() => location.reload(), 1200);
                        } catch (e) {
                            showMsg(res, 'bg-red-50 text-red-700', 'Xatolik: ' + e.message);
                            this.disabled = false;
                        }
                    });
                })();
            </script>

            {{-- Diagnostika --}}
            <div class="bg-white shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex items-center justify-between flex-wrap gap-3">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800">Diagnostika</h3>
                            <p class="text-sm text-gray-500">
                                Yuqorida ta'lim turi va fakultetni (kerak bo'lsa yo'nalishni) tanlab,
                                tugmani bosing — talabalar qaysi kurs/semestr/rejaga biriktirilgani,
                                reja HEMIS bazasida bor-yo'qligi xom holda ko'rinadi. Qaysidir kurs yoki
                                reja cascade'da chiqmasa, sababi shu jadvalda aniqlanadi.
                            </p>
                        </div>
                        <button type="button" id="diagnose-btn"
                                class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-md hover:bg-indigo-700 whitespace-nowrap">
                            Diagnostikani ko'rsatish
                        </button>
                    </div>
                    <div id="diagnose-result" class="mt-4"></div>
                </div>
            </div>

            {{-- Yuklangan o'quv rejalar (Namunaviy / Ishchi vkladkalari) --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Yuklangan o'quv rejalar</h3>

                    <div class="mb-4 flex flex-wrap gap-1 border-b border-gray-200">
                        <button type="button" data-subtab="namunaviy"
                                class="sub-tab px-4 py-2 text-sm font-semibold border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                            Namunaviy o'quv rejalar
                            <span class="ml-1 inline-flex items-center justify-center px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-800">{{ $namunaviyList->count() }}</span>
                        </button>
                        <button type="button" data-subtab="ishchi"
                                class="sub-tab px-4 py-2 text-sm font-semibold border-b-2 border-transparent text-gray-500 hover:text-gray-700">
                            Ishchi o'quv rejalar
                            <span class="ml-1 inline-flex items-center justify-center px-2 py-0.5 text-xs rounded-full bg-purple-100 text-purple-800">{{ $ishchiList->count() }}</span>
                        </button>
                    </div>

                    <div data-subpanel="namunaviy">
                        @include('admin.oquv-reja._curricula-table', [
                            'list' => $namunaviyList,
                            'emptyText' => "Hozircha namunaviy o'quv reja yuklanmagan. Yuqoridagi forma orqali Excel fayl yuklang.",
                        ])
                    </div>
                    <div data-subpanel="ishchi" class="hidden">
                        @include('admin.oquv-reja._curricula-table', [
                            'list' => $ishchiList,
                            'emptyText' => "Hozircha ishchi o'quv reja yuklanmagan. Yuqoridagi forma orqali Excel fayl yuklang.",
                        ])
                    </div>
                </div>
            </div>

            </div> {{-- /panel: rejalar --}}

            {{-- ===== PANEL: O'tiladigan fanlar (2-bosqich) ===== --}}
            @php
                $ishSpecs = $ishchiList->filter(fn($c) => $c->specialty_name)
                    ->unique(fn($c) => $c->specialty_code . '|' . $c->specialty_name)
                    ->sortBy('specialty_name')
                    ->values();
                // O'qitiladigan o'quv yili = plan_year boshi + (kurs - 1)
                $acadYears = $ishchiList->map(function ($c) {
                    $start = (int) substr($c->plan_year ?? '', 0, 4);
                    if (!$start || !$c->level_code) return null;
                    $course = (int) $c->level_code >= 11 ? (int) $c->level_code - 10 : (int) $c->level_code;
                    $as = $start + $course - 1;
                    return $as . '-' . ($as + 1);
                })->filter()->unique()->sortDesc()->values();
            @endphp
            <div data-panel="fanlar" class="hidden">
                <div class="bg-white shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-1">O'tiladigan fanlar (barcha ishchi rejalardan)</h3>
                        <p class="text-sm text-gray-500 mb-4">
                            Barcha ishchi rejalardagi fanlar yo'nalish + kurs + semestr kesimida bir joyga to'planadi.
                            Soatlar turlar bo'yicha (ma'ruza / amaliy / laboratoriya / seminar) alohida ko'rsatiladi —
                            keyingi bosqichda ma'ruza oqimga, amaliy/lab guruhlarga bo'linib o'qituvchi yuklamasi hisoblanadi.
                        </p>

                        <div class="grid grid-cols-1 md:grid-cols-5 gap-3 mb-4">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Yo'nalish</label>
                                <select id="fsSpecialty" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="">Barchasi</option>
                                    @foreach($ishSpecs as $s)
                                        <option value="{{ $s->specialty_code }}">{{ trim($s->specialty_code . ' — ' . $s->specialty_name) }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">O'quv yili (o'qitiladigan)</label>
                                <select id="fsYear" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="">Barchasi</option>
                                    @foreach($acadYears as $y)
                                        <option value="{{ $y }}">{{ $y }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Kurs</label>
                                <select id="fsKurs" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="">Barchasi</option>
                                    @for($k = 1; $k <= 6; $k++)
                                        <option value="1{{ $k }}">{{ $k }}-kurs</option>
                                    @endfor
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Semestr</label>
                                <select id="fsSem" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="">Barchasi</option>
                                    @for($sm = 1; $sm <= 12; $sm++)
                                        <option value="{{ $sm }}">{{ $sm }}-semestr</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="flex items-end gap-2">
                                <label class="flex items-center gap-1.5 text-sm cursor-pointer">
                                    <input type="checkbox" id="fsPlanned" checked class="rounded border-gray-300 text-amber-600">
                                    Rejalashtirilganlar ham
                                </label>
                            </div>
                        </div>

                        {{-- Semestrlararo yuklama (balans) --}}
                        <div id="fsBySem" class="hidden mb-4">
                            <div class="text-xs font-semibold text-gray-600 mb-1.5">Semestrlararo yuklama (fanni semestrdan semestrga ko'chirib tenglashtirish uchun)</div>
                            <div class="overflow-x-auto">
                                <table class="text-xs border border-gray-200 rounded">
                                    <thead class="bg-gray-50 text-gray-600">
                                    <tr>
                                        <th class="px-2 py-1 text-left">Semestr</th>
                                        <th class="px-2 py-1 text-right">Fanlar</th>
                                        <th class="px-2 py-1 text-right text-blue-700">Ma'ruza</th>
                                        <th class="px-2 py-1 text-right text-purple-700">Amaliy</th>
                                        <th class="px-2 py-1 text-right text-teal-700">Lab</th>
                                        <th class="px-2 py-1 text-right text-orange-700">Seminar</th>
                                        <th class="px-2 py-1 text-right font-semibold">Jami soat</th>
                                        <th class="px-2 py-1 text-right">Kredit</th>
                                    </tr>
                                    </thead>
                                    <tbody id="fsBySemBody" class="divide-y divide-gray-100"></tbody>
                                </table>
                            </div>
                        </div>

                        <div class="flex items-center justify-between gap-2 mb-3 flex-wrap">
                            <div id="fsTiles" class="flex flex-wrap gap-2 text-xs"></div>
                            <a id="fsExport" href="#" class="px-3 py-1.5 text-sm bg-emerald-600 text-white rounded-md hover:bg-emerald-700">⬇ CSV yuklab olish</a>
                        </div>

                        <datalist id="fsKafList"></datalist>
                        <div id="fsLoading" class="hidden text-center text-sm text-gray-500 py-6">Yuklanmoqda...</div>
                        <div id="fsEmpty" class="hidden text-center text-sm text-gray-500 py-6">Fan topilmadi.</div>
                        <div id="fsTableWrap" class="overflow-x-auto hidden">
                            <table class="min-w-full divide-y divide-gray-200 text-xs">
                                <thead class="bg-gray-50">
                                <tr>
                                    <th data-sort="specialty_name" class="fs-sort cursor-pointer select-none px-2 py-2 text-left font-medium text-gray-600 hover:bg-gray-100">Yo'nalish<span class="fs-ind"></span></th>
                                    <th data-sort="kurs" class="fs-sort cursor-pointer select-none px-2 py-2 text-center font-medium text-gray-600 hover:bg-gray-100">Kurs<span class="fs-ind"></span></th>
                                    <th data-sort="semester" class="fs-sort cursor-pointer select-none px-2 py-2 text-center font-medium text-gray-600 hover:bg-gray-100">Sem<span class="fs-ind"></span></th>
                                    <th data-sort="block" class="fs-sort cursor-pointer select-none px-2 py-2 text-left font-medium text-gray-600 hover:bg-gray-100">Blok<span class="fs-ind"></span></th>
                                    <th data-sort="subject_name" class="fs-sort cursor-pointer select-none px-2 py-2 text-left font-medium text-gray-600 hover:bg-gray-100">Fan<span class="fs-ind"></span></th>
                                    <th class="px-2 py-2 text-left font-medium text-gray-600">O'quv reja</th>
                                    <th data-sort="kafedra" class="fs-sort cursor-pointer select-none px-2 py-2 text-left font-medium text-gray-600 hover:bg-gray-100">Kafedra<span class="fs-ind"></span></th>
                                    <th data-sort="practice_size" class="fs-sort cursor-pointer select-none px-2 py-2 text-center font-medium text-gray-600 hover:bg-gray-100" title="Amaliy mashg'ulot guruhi nechta kishilik">Amaliy guruh<span class="fs-ind"></span></th>
                                    <th data-sort="lecture" class="fs-sort cursor-pointer select-none px-2 py-2 text-right font-medium text-blue-700 hover:bg-gray-100">Ma'ruza<span class="fs-ind"></span></th>
                                    <th data-sort="practice" class="fs-sort cursor-pointer select-none px-2 py-2 text-right font-medium text-purple-700 hover:bg-gray-100">Amaliy<span class="fs-ind"></span></th>
                                    <th data-sort="laboratory" class="fs-sort cursor-pointer select-none px-2 py-2 text-right font-medium text-teal-700 hover:bg-gray-100">Lab<span class="fs-ind"></span></th>
                                    <th data-sort="seminar" class="fs-sort cursor-pointer select-none px-2 py-2 text-right font-medium text-orange-700 hover:bg-gray-100">Seminar<span class="fs-ind"></span></th>
                                    <th data-sort="independent" class="fs-sort cursor-pointer select-none px-2 py-2 text-right font-medium text-gray-500 hover:bg-gray-100">Mustaqil<span class="fs-ind"></span></th>
                                    <th data-sort="total_hours" class="fs-sort cursor-pointer select-none px-2 py-2 text-right font-medium text-gray-700 hover:bg-gray-100">Jami<span class="fs-ind"></span></th>
                                    <th data-sort="credit" class="fs-sort cursor-pointer select-none px-2 py-2 text-right font-medium text-gray-700 hover:bg-gray-100">Kredit<span class="fs-ind"></span></th>
                                </tr>
                                </thead>
                                <tbody id="fsTbody" class="divide-y divide-gray-100"></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===== PANEL: Yo'nalish bo'yicha ===== --}}
            <div data-panel="yonalish" class="hidden">
                <div class="bg-white shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-1">Yo'nalish bo'yicha rejalar holati</h3>
                        <p class="text-sm text-gray-500 mb-4">
                            Yo'nalishni tanlang — har bir kohort (kurs)dagi talabalar, joriy semestri va yuklangan
                            namunaviy/ishchi rejalar bir jadvalda ko'rinadi.
                        </p>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Ta'lim turi</label>
                                <select id="batch-education-type" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="">Tanlang</option>
                                    @foreach($educationTypes as $et)
                                        <option value="{{ $et->education_type_code }}">{{ $et->education_type_name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Fakultet</label>
                                <select id="batch-faculty" disabled class="w-full rounded-md border-gray-300 shadow-sm text-sm disabled:bg-gray-100">
                                    <option value="">Avval ta'lim turini tanlang</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Yo'nalish</label>
                                <select id="batch-specialty" disabled class="w-full rounded-md border-gray-300 shadow-sm text-sm disabled:bg-gray-100">
                                    <option value="">Avval fakultetni tanlang</option>
                                </select>
                            </div>
                        </div>

                        <div id="batch-table-wrap" class="overflow-x-auto hidden">
                            <div class="flex justify-end mb-3">
                                <button type="button" id="bulkOpenBtn"
                                        class="px-3 py-1.5 text-sm font-medium bg-indigo-600 text-white rounded-md hover:bg-indigo-700">
                                    📦 Barcha kurslarga bittada yuklash
                                </button>
                            </div>
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600">Kohort (reja)</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600">Joriy kurs</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600">Joriy semestr</th>
                                    <th class="px-3 py-2 text-right font-medium text-gray-600">Faol talabalar</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600">Namunaviy reja</th>
                                    <th class="px-3 py-2 text-left font-medium text-gray-600">Ishchi reja</th>
                                    <th class="px-3 py-2 text-center font-medium text-gray-600">Yuklash</th>
                                </tr>
                                </thead>
                                <tbody id="batch-tbody" class="divide-y divide-gray-100">
                                </tbody>
                            </table>
                        </div>
                        <div id="batch-empty" class="hidden text-center text-sm text-gray-500 py-6">
                            Bu yo'nalishda faol talabalar topilmadi.
                        </div>
                        <div id="batch-loading" class="hidden text-center text-sm text-gray-500 py-6">
                            Yuklanmoqda...
                        </div>
                    </div>
                </div>
            </div>

            {{-- Yuklash modali (Yo'nalish bo'yicha tab uchun) --}}
            <div id="batchUploadModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/40">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="bg-white rounded-lg shadow-xl w-full max-w-lg">
                        <form id="batchUploadForm" method="POST"
                              action="{{ route('admin.oquv-reja.store') }}"
                              enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" name="curricula_hemis_id" id="batchCurriculumId">
                            <input type="hidden" name="level_code" id="batchLevelCode">

                            <div class="flex items-center justify-between px-6 py-4 border-b">
                                <h3 class="text-base font-semibold text-gray-800" id="batchModalTitle">O'quv reja yuklash</h3>
                                <button type="button" class="js-batch-close text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                            </div>

                            <div class="px-6 py-4 space-y-4">

                                {{-- Kohort kartochkasi --}}
                                <div class="rounded-lg border border-blue-100 bg-blue-50 px-4 py-3 space-y-1.5">
                                    <div class="text-xs font-semibold text-blue-500 uppercase tracking-wide">Kohort (o'quv reja)</div>
                                    <div class="font-semibold text-gray-800 text-sm" id="batchInfoName"></div>
                                    <div class="flex flex-wrap gap-3 text-xs text-gray-500">
                                        <span>📅 <span id="batchInfoYear" class="font-medium text-gray-700"></span></span>
                                        <span>🎓 <span id="batchInfoType" class="font-medium text-gray-700"></span></span>
                                    </div>
                                    <div class="pt-1 border-t border-blue-100 flex flex-wrap gap-3 text-xs text-gray-500">
                                        <span>Joriy holat:</span>
                                        <span class="font-semibold text-blue-700" id="batchInfoLevel"></span>
                                        <span class="text-gray-400">·</span>
                                        <span class="font-semibold text-blue-700" id="batchInfoSem"></span>
                                        <span class="text-gray-400">·</span>
                                        <span id="batchInfoStudents" class="text-gray-600"></span>
                                    </div>
                                </div>

                                {{-- Reja turi --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Reja turi</label>
                                    <div class="flex gap-4">
                                        <label class="flex items-center gap-1.5 text-sm cursor-pointer">
                                            <input type="radio" name="type" value="namunaviy" class="text-blue-600">
                                            Namunaviy
                                        </label>
                                        <label class="flex items-center gap-1.5 text-sm cursor-pointer">
                                            <input type="radio" name="type" value="ishchi" class="text-purple-600" checked>
                                            Ishchi
                                        </label>
                                    </div>
                                </div>

                                {{-- Kurs va o'quv yili --}}
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">Kurs</label>
                                        <select id="batchKursSel"
                                                class="w-full rounded-md border-gray-300 shadow-sm text-sm focus:ring-blue-500 focus:border-blue-500 disabled:bg-gray-100">
                                            <option value="">Yuklanmoqda...</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-gray-600 mb-1">O'quv yili</label>
                                        <p id="batchKursYear"
                                           class="text-sm font-semibold text-gray-700 px-3 py-[9px] rounded-md bg-gray-50 border border-gray-200">—</p>
                                    </div>
                                </div>

                                {{-- Semestrlar (kurs tanlanganda avtomatik belgilanadi) --}}
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1.5">Semestrlar</label>
                                    <div id="batchSemesterCheckboxes"
                                         class="rounded-md border border-gray-200 px-3 py-2.5 bg-gray-50 space-y-2 min-h-[44px]">
                                        <p class="text-sm text-gray-400 italic">Yuklanmoqda...</p>
                                    </div>
                                </div>

                                {{-- Excel fayl --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Excel fayl (.xlsx)</label>
                                    <input type="file" name="file" required accept=".xlsx,.xls"
                                           class="w-full text-sm text-gray-700 border border-gray-300 rounded-md">
                                </div>
                            </div>

                            <div class="flex justify-end gap-2 px-6 py-4 border-t bg-gray-50 rounded-b-lg">
                                <button type="button" class="js-batch-close px-4 py-2 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50 text-gray-700">
                                    Bekor qilish
                                </button>
                                <button type="submit" class="px-4 py-2 text-sm bg-green-600 text-white rounded-md hover:bg-green-700">
                                    Yuklash
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Bulk yuklash modali (yo'nalishning barcha kurslari uchun bittada) --}}
            <div id="bulkUploadModal" class="hidden fixed inset-0 z-50 overflow-y-auto bg-black/40">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl">
                        <div class="flex items-center justify-between px-6 py-4 border-b">
                            <h3 class="text-base font-semibold text-gray-800">Barcha kurslarga bittada yuklash</h3>
                            <button type="button" class="js-bulk-close text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                        </div>
                        <div class="px-6 py-4 space-y-4 max-h-[70vh] overflow-y-auto">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Reja turi</label>
                                <div class="flex gap-4">
                                    <label class="flex items-center gap-1.5 text-sm cursor-pointer">
                                        <input type="radio" name="bulk_type" value="namunaviy" class="text-blue-600">
                                        Namunaviy
                                    </label>
                                    <label class="flex items-center gap-1.5 text-sm cursor-pointer">
                                        <input type="radio" name="bulk_type" value="ishchi" class="text-purple-600" checked>
                                        Ishchi
                                    </label>
                                </div>
                            </div>
                            <p class="text-xs text-gray-500">
                                Har bir kurs (kohort) uchun Excel fayl tanlang — fayl shu kursdagi <b>barcha o'quv rejalarga</b>
                                va tanlangan semestrlarga yuklanadi. Fayl tanlanmagan kurslar o'tkazib yuboriladi.
                                Oldin yuklangan (reja + semestr) juftliklar takror yuklanmaydi.
                            </p>
                            <div id="bulkGroups" class="space-y-3"></div>
                            <div id="bulkPreview" class="space-y-2"></div>
                            <div id="bulkResult" class="hidden text-sm rounded-md px-4 py-3"></div>
                        </div>
                        <div class="flex justify-end gap-2 px-6 py-4 border-t bg-gray-50 rounded-b-lg">
                            <button type="button" class="js-bulk-close px-4 py-2 text-sm bg-white border border-gray-300 rounded-md hover:bg-gray-50 text-gray-700">
                                Yopish
                            </button>
                            <button type="button" id="bulkSubmitBtn"
                                    class="px-4 py-2 text-sm bg-indigo-600 text-white rounded-md hover:bg-indigo-700 disabled:opacity-50">
                                Tekshirish
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ===== PANEL: Solishtirish ===== --}}
            <div data-panel="solishtirish" class="hidden">

                {{-- Qo'lda solishtirish --}}
                <div class="bg-white shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Namunaviy va ishchi rejani solishtirish</h3>
                        <form method="GET" action="{{ route('admin.oquv-reja.compare') }}" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Namunaviy o'quv reja</label>
                                <select name="reference_id" required class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="">Tanlang</option>
                                    @foreach($namunaviyList as $curriculum)
                                        <option value="{{ $curriculum->id }}">{{ $curriculum->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Ishchi o'quv reja</label>
                                <select name="working_id" required class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                    <option value="">Tanlang</option>
                                    @foreach($ishchiList as $curriculum)
                                        <option value="{{ $curriculum->id }}">{{ $curriculum->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-md hover:bg-blue-700">
                                    Solishtirish
                                </button>
                            </div>
                        </form>
                        @if($namunaviyList->isEmpty() || $ishchiList->isEmpty())
                            <p class="mt-3 text-sm text-gray-500">
                                Solishtirish uchun kamida bitta namunaviy va bitta ishchi o'quv reja yuklangan bo'lishi kerak.
                            </p>
                        @endif
                    </div>
                </div>

                {{-- Solishtirilgan rejalar ro'yxati (saqlangan tarix) --}}
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-1">Solishtirilgan rejalar</h3>
                        <p class="text-sm text-gray-500 mb-4">
                            Yuqorida tanlab "Solishtirish" tugmasi bosilganda juftlik shu ro'yxatga qo'shiladi va saqlanadi. Ustiga bosib solishtirish jadvalini qayta oching.
                        </p>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left font-medium text-gray-600">#</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-600">Namunaviy reja</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-600">Ishchi reja</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-600">Yo'nalish</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-600">Reja yili</th>
                                    <th class="px-4 py-2 text-left font-medium text-gray-600">Amal</th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100">
                                @forelse($savedComparisons as $i => $comp)
                                    @php $compareUrl = route('admin.oquv-reja.compare', ['reference_id' => $comp->reference->id, 'working_id' => $comp->working->id]); @endphp
                                    <tr class="hover:bg-blue-50 cursor-pointer" onclick="window.location='{{ $compareUrl }}'">
                                        <td class="px-4 py-2">{{ $i + 1 }}</td>
                                        <td class="px-4 py-2">
                                            <span class="px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800 mr-1">Namunaviy</span>
                                            {{ $comp->reference->name }}
                                        </td>
                                        <td class="px-4 py-2">
                                            <span class="px-2 py-0.5 rounded text-xs font-medium bg-purple-100 text-purple-800 mr-1">Ishchi</span>
                                            {{ $comp->working->name }}
                                        </td>
                                        <td class="px-4 py-2">{{ trim($comp->working->specialty_code . ' ' . $comp->working->specialty_name) ?: '—' }}</td>
                                        <td class="px-4 py-2">{{ $comp->working->plan_year ?: '—' }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap" onclick="event.stopPropagation();">
                                            <a href="{{ $compareUrl }}" class="text-blue-600 hover:underline font-medium mr-3">
                                                Solishtirishni ko'rish →
                                            </a>
                                            <form method="POST" action="{{ route('admin.oquv-reja.comparisons.destroy', $comp) }}" class="inline"
                                                  onsubmit="return confirm('Ushbu solishtirish ro\'yxatdan o\'chirilsinmi?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:underline text-sm">O'chirish</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="px-4 py-6 text-center text-gray-500">
                                            Hali solishtirish bajarilmagan. Yuqorida namunaviy va ishchi rejani tanlab "Solishtirish" tugmasini bosing — juftlik shu yerga qo'shiladi.
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div> {{-- /panel: solishtirish --}}

            <style>
                .main-tab.tab-active { color:#2563eb; border-bottom-color:#2563eb; }
                .sub-tab.subtab-active { color:#2563eb; border-bottom-color:#2563eb; }
            </style>

            <script>
                (function () {
                    // ===== Saralash: barcha .js-sortable-table jadvallari uchun =====
                    function initSortable(table) {
                        const tbody = table.tBodies[0];
                        if (!tbody) return;
                        const headers = table.querySelectorAll('th.js-sortable');

                        function dataRows() {
                            return Array.from(tbody.querySelectorAll('tr')).filter(tr => !tr.querySelector('td[colspan]'));
                        }
                        function cellValue(row, index, type) {
                            const cell = row.children[index];
                            if (!cell) return type === 'number' ? 0 : '';
                            const raw = cell.hasAttribute('data-sort-value')
                                ? cell.getAttribute('data-sort-value')
                                : cell.textContent.trim();
                            if (type === 'number') {
                                const num = parseFloat(String(raw).replace(/\s/g, '').replace(',', '.'));
                                return isNaN(num) ? 0 : num;
                            }
                            return String(raw).toLowerCase();
                        }

                        headers.forEach((header) => {
                            const colIndex = Array.from(header.parentNode.children).indexOf(header);
                            const type = header.getAttribute('data-sort-type') || 'text';
                            header.addEventListener('click', function () {
                                const rows = dataRows();
                                if (rows.length < 2) return;
                                const dir = header.getAttribute('data-sort-dir') === 'asc' ? 'desc' : 'asc';
                                headers.forEach(h => {
                                    h.removeAttribute('data-sort-dir');
                                    const ind = h.querySelector('.js-sort-indicator');
                                    if (ind) ind.textContent = '';
                                });
                                header.setAttribute('data-sort-dir', dir);
                                const ind = header.querySelector('.js-sort-indicator');
                                if (ind) ind.textContent = dir === 'asc' ? '▲' : '▼';
                                rows.sort((a, b) => {
                                    const va = cellValue(a, colIndex, type);
                                    const vb = cellValue(b, colIndex, type);
                                    const cmp = type === 'number' ? (va - vb) : va.localeCompare(vb, 'uz');
                                    return dir === 'asc' ? cmp : -cmp;
                                });
                                const frag = document.createDocumentFragment();
                                rows.forEach(r => frag.appendChild(r));
                                tbody.appendChild(frag);
                            });
                        });
                    }
                    document.querySelectorAll('.js-sortable-table').forEach(initSortable);

                    // ===== Asosiy vkladkalar =====
                    function activateMainTab(name) {
                        document.querySelectorAll('.main-tab').forEach(b =>
                            b.classList.toggle('tab-active', b.dataset.tab === name));
                        document.querySelectorAll('[data-panel]').forEach(p =>
                            p.classList.toggle('hidden', p.dataset.panel !== name));
                        if (history.replaceState) history.replaceState(null, '', '#' + name);
                    }
                    document.querySelectorAll('.main-tab').forEach(btn =>
                        btn.addEventListener('click', () => activateMainTab(btn.dataset.tab)));

                    // ===== Sub-vkladkalar (Namunaviy / Ishchi) =====
                    document.querySelectorAll('.sub-tab').forEach(btn =>
                        btn.addEventListener('click', () => {
                            const name = btn.dataset.subtab;
                            document.querySelectorAll('.sub-tab').forEach(b =>
                                b.classList.toggle('subtab-active', b.dataset.subtab === name));
                            document.querySelectorAll('[data-subpanel]').forEach(p =>
                                p.classList.toggle('hidden', p.dataset.subpanel !== name));
                        }));

                    // Boshlang'ich holat (URL hash bo'yicha)
                    const hash = (location.hash || '').replace('#', '');
                    const validHashes = ['solishtirish', 'yonalish', 'fanlar'];
                    activateMainTab(validHashes.includes(hash) ? hash : 'rejalar');
                    const firstSub = document.querySelector('.sub-tab');
                    if (firstSub) firstSub.click();

                    // ===== Yo'nalish bo'yicha tab =====
                    (function () {
                        const optionsUrl = @json(route('admin.oquv-reja.options'));
                        const batchUrl   = @json(route('admin.oquv-reja.batch-view'));
                        const bulkUrl    = @json(route('admin.oquv-reja.store-bulk'));
                        const showUrl    = @json(route('admin.oquv-reja.show', '__ID__'));
                        let lastBatchRows = [];

                        const etSel  = document.getElementById('batch-education-type');
                        const facSel = document.getElementById('batch-faculty');
                        const spSel  = document.getElementById('batch-specialty');
                        const tbody  = document.getElementById('batch-tbody');
                        const wrap   = document.getElementById('batch-table-wrap');
                        const empty  = document.getElementById('batch-empty');
                        const loading = document.getElementById('batch-loading');

                        async function fetchOpts(list, params = {}) {
                            const p = new URLSearchParams({list, ...params});
                            const r = await fetch(optionsUrl + '?' + p, {headers: {'Accept': 'application/json'}});
                            return r.json();
                        }

                        etSel.addEventListener('change', async function () {
                            facSel.innerHTML = '<option value="">Yuklanmoqda...</option>';
                            facSel.disabled = true;
                            spSel.innerHTML = '<option value="">Avval fakultetni tanlang</option>';
                            spSel.disabled = true;
                            wrap.classList.add('hidden'); empty.classList.add('hidden'); tbody.innerHTML = '';
                            if (!this.value) { facSel.innerHTML = '<option value="">Tanlang</option>'; return; }
                            const items = await fetchOpts('faculties', {education_type_code: this.value});
                            facSel.innerHTML = '<option value="">Tanlang</option>';
                            items.forEach(i => {
                                const o = document.createElement('option');
                                o.value = i.id; o.textContent = i.name;
                                facSel.appendChild(o);
                            });
                            facSel.disabled = false;
                        });

                        facSel.addEventListener('change', async function () {
                            spSel.innerHTML = '<option value="">Yuklanmoqda...</option>';
                            spSel.disabled = true;
                            wrap.classList.add('hidden'); empty.classList.add('hidden'); tbody.innerHTML = '';
                            if (!this.value) return;
                            const params = {department_id: this.value};
                            if (etSel.value) params.education_type_code = etSel.value;
                            const items = await fetchOpts('specialties', params);
                            spSel.innerHTML = '<option value="">Tanlang</option>';
                            items.forEach(i => {
                                const o = document.createElement('option');
                                o.value = i.id;
                                o.textContent = (i.code ? i.code + ' — ' : '') + i.name + (i.student_count ? ' (' + i.student_count + ')' : '');
                                spSel.appendChild(o);
                            });
                            spSel.disabled = false;
                        });

                        spSel.addEventListener('change', async function () {
                            wrap.classList.add('hidden'); empty.classList.add('hidden'); tbody.innerHTML = '';
                            if (!this.value) return;
                            loading.classList.remove('hidden');
                            try {
                                // Yo'nalish kod bo'yicha tanlanadi; fakultet/ta'lim turi bo'yicha cheklanadi
                                const p = new URLSearchParams({specialty_code: this.value});
                                if (facSel.value) p.set('department_id', facSel.value);
                                if (etSel.value)  p.set('education_type_code', etSel.value);
                                const rows = await (await fetch(batchUrl + '?' + p, {headers: {'Accept': 'application/json'}})).json();
                                loading.classList.add('hidden');
                                lastBatchRows = rows;
                                if (!rows.length) { empty.classList.remove('hidden'); return; }

                                tbody.innerHTML = rows.map(r => {
                                    // Barcha yuklangan rejalarni ko'rsatamiz (semestrdan qat'i nazar)
                                    const nam = r.uploaded.filter(u => u.type === 'namunaviy');
                                    const ish = r.uploaded.filter(u => u.type === 'ishchi');

                                    // Semestr kodini raqamga (13 → 3) va nomga (3-semestr) aylantirish
                                    const semNum  = c => { const n = parseInt(c); return n >= 11 ? n - 10 : n; };
                                    const semName = c => c ? (semNum(c) + '-semestr') : 'Umumiy';

                                    function uploadedCell(list) {
                                        if (!list.length) return '<span class="text-gray-400">—</span>';
                                        // Semestr bo'yicha tartiblash (kodsizlar oxirida)
                                        const sorted = [...list].sort((a, b) =>
                                            (a.semester_code ? semNum(a.semester_code) : 99) -
                                            (b.semester_code ? semNum(b.semester_code) : 99));
                                        return '<div class="flex flex-col gap-1">' + sorted.map(u => {
                                            const isCurrent = !u.semester_code || u.semester_code === r.semester_code;
                                            const badgeCls = isCurrent
                                                ? 'bg-green-600 text-white'
                                                : 'bg-amber-500 text-white';
                                            const rowCls = isCurrent
                                                ? 'bg-green-50 hover:bg-green-100 border-green-200'
                                                : 'bg-amber-50 hover:bg-amber-100 border-amber-200';
                                            const shortName = u.name.length > 32 ? u.name.slice(0, 32) + '…' : u.name;
                                            return '<a href="' + showUrl.replace('__ID__', u.id) + '" title="' + escHtml(u.name) + '" ' +
                                                'class="inline-flex items-center gap-2 rounded-md border px-2 py-1 ' + rowCls + '">' +
                                                '<span class="inline-flex shrink-0 items-center justify-center rounded px-1.5 py-0.5 text-[10px] font-bold ' + badgeCls + '">' +
                                                    escHtml(semName(u.semester_code)) + '</span>' +
                                                '<span class="text-xs text-gray-700 truncate">' + escHtml(shortName) + '</span>' +
                                            '</a>';
                                        }).join('') + '</div>';
                                    }

                                    const sharedAttrs = ' data-id="' + r.curriculum_id + '" data-level="' + (r.level_code||'') + '" data-sem="' + (r.semester_code||'') + '" data-name="' + escHtml(r.curriculum_name) + '" data-year="' + escHtml(r.education_year||'') + '" data-eductype="' + escHtml(r.education_type||'') + '" data-levelname="' + escHtml(r.level_name||'') + '" data-semname="' + escHtml(r.semester_name||'') + '" data-students="' + (r.student_count||'') + '"';
                                    const btnNam = '<button type="button" class="js-batch-upload px-2 py-1 text-xs bg-blue-600 text-white rounded hover:bg-blue-700 mr-1"' + sharedAttrs + ' data-type="namunaviy">+ Namunaviy</button>';
                                    const btnIsh = '<button type="button" class="js-batch-upload px-2 py-1 text-xs bg-purple-600 text-white rounded hover:bg-purple-700"' + sharedAttrs + ' data-type="ishchi">+ Ishchi</button>';

                                    return '<tr class="hover:bg-gray-50">' +
                                        '<td class="px-3 py-2 text-gray-800 text-xs max-w-xs"><div class="font-medium">' + escHtml(r.curriculum_name) + '</div>' +
                                            (r.education_year ? '<div class="text-gray-400">' + escHtml(r.education_year) + (r.education_type ? ' · ' + escHtml(r.education_type) : '') + '</div>' : '') +
                                        '</td>' +
                                        '<td class="px-3 py-2 font-semibold">' + escHtml(r.level_name || r.level_code || '—') + '</td>' +
                                        '<td class="px-3 py-2">' + escHtml(r.semester_name || r.semester_code || '—') + '</td>' +
                                        '<td class="px-3 py-2 text-right">' + r.student_count + '</td>' +
                                        '<td class="px-3 py-2">' + uploadedCell(nam) + '</td>' +
                                        '<td class="px-3 py-2">' + uploadedCell(ish) + '</td>' +
                                        '<td class="px-3 py-2 text-center whitespace-nowrap">' + btnNam + btnIsh + '</td>' +
                                        '</tr>';
                                }).join('');

                                wrap.classList.remove('hidden');

                                // Yuklash tugmalari
                                tbody.querySelectorAll('.js-batch-upload').forEach(btn => {
                                    btn.addEventListener('click', async function () {
                                        const id = this.dataset.id;
                                        const type = this.dataset.type;
                                        const currentSem = this.dataset.sem;

                                        document.getElementById('batchCurriculumId').value = id;
                                        document.getElementById('batchLevelCode').value = this.dataset.level;
                                        document.getElementById('batchModalTitle').textContent =
                                            (type === 'namunaviy' ? 'Namunaviy' : 'Ishchi') + " o'quv reja yuklash";
                                        document.getElementById('batchInfoName').textContent = this.dataset.name;
                                        document.getElementById('batchInfoYear').textContent = this.dataset.year || '';
                                        document.getElementById('batchInfoType').textContent = this.dataset.eductype || '';
                                        document.getElementById('batchInfoLevel').textContent = this.dataset.levelname || this.dataset.level || '';
                                        document.getElementById('batchInfoSem').textContent = this.dataset.semname || currentSem || '';
                                        document.getElementById('batchInfoStudents').textContent = this.dataset.students ? this.dataset.students + ' talaba' : '';
                                        document.querySelector('input[name="type"][value="' + type + '"]').checked = true;
                                        document.getElementById('batchUploadModal').classList.remove('hidden');

                                        const semBox  = document.getElementById('batchSemesterCheckboxes');
                                        const kursSel = document.getElementById('batchKursSel');
                                        const kursYearEl = document.getElementById('batchKursYear');

                                        // Kohortning joriy kursi va o'quv yilini data-atributlardan olamiz
                                        const curLevelName = this.dataset.levelname || '';
                                        const curKurs = parseInt(curLevelName.match(/(\d+)/)?.[1]) || 1;
                                        const baseYear = parseInt((this.dataset.year || '').match(/(\d{4})/)?.[1]) || null;

                                        semBox.innerHTML  = '<p class="text-sm text-gray-400 italic">Yuklanmoqda...</p>';
                                        kursSel.innerHTML = '<option value="">Yuklanmoqda...</option>';
                                        kursSel.disabled  = true;
                                        try {
                                            const sems = await fetchOpts('semesters_for_curriculum', {curriculum_id: id});
                                            if (sems.length) {
                                                // Checkboxlar — dastlab hech biri belgilanmagan
                                                semBox.innerHTML = sems.map(s =>
                                                    '<label class="flex items-center gap-2 text-sm cursor-pointer select-none">' +
                                                    '<input type="checkbox" name="semester_codes[]" value="' + escHtml(s.code) + '"' +
                                                    ' class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">' +
                                                    '<span>' + escHtml(s.name) + '</span>' +
                                                    (s.current ? ' <span class="text-xs text-green-600 font-medium">★ joriy</span>' : '') +
                                                    '</label>'
                                                ).join('');

                                                // Kurs tanlash (1 dan N kursga)
                                                const numKurs = Math.ceil(sems.length / 2);
                                                kursSel.innerHTML = Array.from({length: numKurs}, (_, i) => {
                                                    const k = i + 1;
                                                    return '<option value="' + k + '">' + k + '-kurs</option>';
                                                }).join('');
                                                kursSel.disabled = false;

                                                // Kurs o'zgarganda: tegishli 2 semestrni avtomatik belgilash
                                                kursSel.onchange = function () {
                                                    const k = parseInt(this.value);
                                                    if (!k) return;
                                                    const s1 = k * 2 - 1, s2 = k * 2;
                                                    semBox.querySelectorAll('input[type="checkbox"]').forEach(cb => {
                                                        const v = parseInt(cb.value);
                                                        cb.checked = (v === s1 || v === s2);
                                                    });
                                                    if (kursYearEl && baseYear !== null) {
                                                        const offset = k - curKurs;
                                                        const y = baseYear + offset;
                                                        kursYearEl.textContent = y + '-' + (y + 1);
                                                    }
                                                };

                                                // Joriy kursni default qilib tanlaymiz va semestrlarni belgilaymiz
                                                kursSel.value = String(curKurs);
                                                if (!kursSel.options[kursSel.selectedIndex]?.value) kursSel.value = '1';
                                                kursSel.dispatchEvent(new Event('change'));

                                            } else {
                                                semBox.innerHTML  = '<p class="text-sm text-gray-400 italic">Semestrlar topilmadi</p>';
                                                kursSel.innerHTML = '<option value="">Topilmadi</option>';
                                                kursSel.disabled  = false;
                                            }
                                        } catch (e) {
                                            semBox.innerHTML  = '<p class="text-sm text-red-500 italic">Xatolik yuz berdi</p>';
                                            kursSel.innerHTML = '<option value="">Xatolik</option>';
                                            kursSel.disabled  = false;
                                        }
                                    });
                                });
                            } catch (e) {
                                loading.classList.add('hidden');
                                empty.textContent = 'Xatolik yuz berdi.';
                                empty.classList.remove('hidden');
                            }
                        });

                        // Yuborishdan oldin kamida bitta semestr tanlanganini tekshirish
                        document.getElementById('batchUploadForm').addEventListener('submit', function (e) {
                            const checked = this.querySelectorAll('input[name="semester_codes[]"]:checked');
                            if (!checked.length) {
                                e.preventDefault();
                                document.getElementById('batchSemesterCheckboxes').classList.add('ring-1', 'ring-red-400');
                                setTimeout(() => document.getElementById('batchSemesterCheckboxes').classList.remove('ring-1', 'ring-red-400'), 2000);
                            }
                        });

                        // Modal yopish
                        document.querySelectorAll('.js-batch-close').forEach(b =>
                            b.addEventListener('click', () => document.getElementById('batchUploadModal').classList.add('hidden')));
                        document.getElementById('batchUploadModal').addEventListener('click', function (e) {
                            if (e.target === this) this.classList.add('hidden');
                        });

                        // ===== Bulk yuklash (barcha kurslarga bittada) =====
                        const bulkModal  = document.getElementById('bulkUploadModal');
                        const bulkGroups = document.getElementById('bulkGroups');
                        const bulkResult = document.getElementById('bulkResult');

                        document.getElementById('bulkOpenBtn').addEventListener('click', () => {
                            if (!lastBatchRows.length) return;
                            bulkResult.classList.add('hidden');
                            resetBulkStage();

                            // Kurs (level_code) bo'yicha guruhlash — bir kursda bir nechta reja bo'lishi mumkin
                            const groups = {};
                            lastBatchRows.forEach(r => {
                                const lv = r.level_code || '00';
                                if (!groups[lv]) groups[lv] = {level_name: r.level_name || lv, curricula: {}, students: 0, semCount: 12};
                                const g = groups[lv];
                                if (!g.curricula[r.curriculum_id]) g.curricula[r.curriculum_id] = {name: r.curriculum_name, students: 0};
                                g.curricula[r.curriculum_id].students += r.student_count;
                                g.students += r.student_count;
                                if (r.semester_count) g.semCount = r.semester_count;
                            });

                            bulkGroups.innerHTML = Object.keys(groups).sort().map(lv => {
                                const g = groups[lv];
                                const curKurs = (parseInt(lv) - 10) || parseInt((g.level_name.match(/\d+/) || [1])[0]);
                                const maxKurs = Math.max(1, Math.floor(g.semCount / 2));
                                const defKurs = Math.min(curKurs + 1, maxKurs);
                                const ids = Object.keys(g.curricula);
                                const kursOpts = Array.from({length: maxKurs}, (_, i) => i + 1)
                                    .map(k => '<option value="' + k + '"' + (k === defKurs ? ' selected' : '') + '>' + k + '-kurs</option>').join('');
                                return '<div class="bulk-group rounded-lg border border-gray-200 p-3" data-curricula="' + ids.join(',') + '">' +
                                    '<div class="flex flex-wrap items-center justify-between gap-2 mb-1.5">' +
                                        '<div class="text-sm font-semibold text-gray-800">Joriy ' + escHtml(g.level_name) + ' · ' + g.students + ' talaba</div>' +
                                        '<label class="flex items-center gap-2 text-xs text-gray-600">Qaysi kurs uchun:' +
                                            '<select class="bulk-kurs rounded border-gray-300 text-xs py-1">' + kursOpts + '</select>' +
                                        '</label>' +
                                    '</div>' +
                                    '<div class="text-xs text-gray-500 mb-2">' +
                                        ids.map(id => escHtml(g.curricula[id].name) + ' (' + g.curricula[id].students + ')').join(' · ') +
                                    '</div>' +
                                    '<div class="flex flex-wrap items-center gap-4">' +
                                        '<div class="bulk-sems flex items-center gap-3 text-sm"></div>' +
                                        '<input type="file" accept=".xlsx,.xls" class="bulk-file text-xs text-gray-700 border border-gray-300 rounded-md flex-1 min-w-[220px]">' +
                                    '</div>' +
                                    '<div class="bulk-warn text-xs font-medium text-red-600 mt-1"></div>' +
                                '</div>';
                            }).join('');

                            // Kurs tanlanganda semestr checkboxlarini qayta qurish (HEMIS: kod = 10 + semestr raqami)
                            bulkGroups.querySelectorAll('.bulk-group').forEach(g => {
                                const sel  = g.querySelector('.bulk-kurs');
                                const file = g.querySelector('.bulk-file');
                                const warn = g.querySelector('.bulk-warn');
                                const rebuild = () => {
                                    const k = parseInt(sel.value);
                                    const codes = [10 + 2 * k - 1, 10 + 2 * k];
                                    g.querySelector('.bulk-sems').innerHTML = codes.map(c =>
                                        '<label class="flex items-center gap-1 cursor-pointer">' +
                                        '<input type="checkbox" class="bulk-sem rounded border-gray-300 text-indigo-600" value="' + c + '" checked>' +
                                        (c - 10) + '-semestr</label>').join('');
                                };
                                // Fayl nomidagi kurs raqami tanlangan kursga mos kelmasa — darhol ogohlantirish
                                const checkName = () => {
                                    const f = file.files[0];
                                    const m = f && f.name.match(/(\d+)\s*[-_ ]?\s*kurs/i);
                                    warn.textContent = (m && parseInt(m[1]) !== parseInt(sel.value))
                                        ? "⚠ Fayl nomi " + m[1] + "-kursga o'xshaydi, siz " + sel.value + "-kurs uchun tanladingiz!"
                                        : '';
                                };
                                sel.addEventListener('change', () => { rebuild(); checkName(); });
                                file.addEventListener('change', checkName);
                                rebuild();
                            });

                            bulkModal.classList.remove('hidden');
                        });

                        bulkModal.querySelectorAll('.js-bulk-close').forEach(b =>
                            b.addEventListener('click', () => bulkModal.classList.add('hidden')));
                        bulkModal.addEventListener('click', function (e) {
                            if (e.target === this) this.classList.add('hidden');
                        });

                        // Ikki bosqich: 1) Tekshirish (preview, saqlanmaydi) → 2) Tasdiqlash (haqiqiy yuklash)
                        const bulkPreview   = document.getElementById('bulkPreview');
                        const bulkSubmitBtn = document.getElementById('bulkSubmitBtn');
                        let bulkStage = 'form';

                        function resetBulkStage() {
                            bulkStage = 'form';
                            bulkSubmitBtn.textContent = 'Tekshirish';
                            bulkPreview.innerHTML = '';
                        }
                        // Har qanday o'zgarish (fayl/kurs/semestr/tur) tasdiqni bekor qiladi — qayta tekshirish kerak
                        bulkGroups.addEventListener('change', resetBulkStage);
                        document.querySelectorAll('input[name="bulk_type"]').forEach(r => r.addEventListener('change', resetBulkStage));

                        function buildBulkFd() {
                            const fd = new FormData();
                            fd.append('_token', document.querySelector('#batchUploadForm input[name="_token"]').value);
                            fd.append('type', document.querySelector('input[name="bulk_type"]:checked').value);
                            let i = 0;
                            bulkGroups.querySelectorAll('.bulk-group').forEach(g => {
                                const f = g.querySelector('.bulk-file').files[0];
                                if (!f) return;
                                const sems = [...g.querySelectorAll('.bulk-sem:checked')].map(c => c.value);
                                if (!sems.length) return;
                                fd.append('items[' + i + '][file]', f);
                                g.dataset.curricula.split(',').forEach(id => fd.append('items[' + i + '][curricula][]', id));
                                sems.forEach(s => fd.append('items[' + i + '][semester_codes][]', s));
                                i++;
                            });
                            return {fd, count: i};
                        }

                        function renderBulkPreview(items) {
                            bulkPreview.innerHTML = items.map(it => {
                                if (it.error) {
                                    return '<div class="rounded-md border border-red-200 bg-red-50 p-3 text-xs text-red-700">' +
                                        '<b>' + escHtml(it.file) + '</b>: ' + escHtml(it.error) + ' — bu fayl yuklanmaydi.</div>';
                                }
                                const ok = it.sem_ok;
                                return '<div class="rounded-md border ' + (ok ? 'border-green-200 bg-green-50' : 'border-red-200 bg-red-50') + ' p-3 text-xs space-y-1">' +
                                    '<div class="font-semibold text-gray-800">' + escHtml(it.file) + ' — ' + it.imported +
                                        ' ta fan qatori · ' + it.credit_sum + ' kredit · ' + it.hours_sum + ' soat</div>' +
                                    '<div class="text-gray-600">Fayl semestrlari: <b>' + (it.file_sems.join(', ') || '—') + '</b>' +
                                        ' · Tanlangan: <b>' + it.target_sems.join(', ') + '</b> ' +
                                        (ok ? '<span class="text-green-700 font-semibold">✓ mos</span>'
                                            : '<span class="text-red-700 font-semibold">✗ mos emas — yuklanmaydi</span>') + '</div>' +
                                    (it.sample.length ? '<div class="text-gray-500">Namuna: ' + it.sample.map(escHtml).join(' · ') + '</div>' : '') +
                                    '<div class="text-gray-700">' + it.targets.map(t =>
                                        '→ ' + escHtml(t.name) +
                                        (t.sems.length ? ': <b>' + t.sems.map(s => s + '-sem').join(', ') + '</b>' : ': —') +
                                        (t.skipped_sems.length ? ' <span class="text-gray-400">(mavjud, o\'tkaziladi: ' + t.skipped_sems.map(s => s + '-sem').join(', ') + ')</span>' : '')
                                    ).join('<br>') + '</div>' +
                                '</div>';
                            }).join('');
                        }

                        bulkSubmitBtn.addEventListener('click', async function () {
                            const {fd, count} = buildBulkFd();
                            bulkResult.classList.remove('hidden');
                            if (!count) {
                                bulkResult.className = 'text-sm rounded-md px-4 py-3 bg-yellow-50 text-yellow-800';
                                bulkResult.textContent = 'Hech qaysi kursga fayl tanlanmadi.';
                                return;
                            }
                            this.disabled = true;

                            if (bulkStage === 'form') {
                                // 1-bosqich: faqat tekshirish, hech narsa saqlanmaydi
                                fd.append('preview', '1');
                                bulkResult.className = 'text-sm rounded-md px-4 py-3 bg-gray-50 text-gray-600';
                                bulkResult.textContent = 'Fayllar tekshirilmoqda... (' + count + ' ta)';
                                try {
                                    const res = await fetch(bulkUrl, {method: 'POST', body: fd, headers: {'Accept': 'application/json'}});
                                    const j = await res.json();
                                    if (!res.ok) throw new Error(j.message || 'Server xatosi (' + res.status + ')');
                                    renderBulkPreview(j.items);
                                    const okCount = j.items.filter(it => !it.error && it.sem_ok).length;
                                    if (okCount) {
                                        bulkStage = 'confirm';
                                        bulkSubmitBtn.textContent = '✅ Tasdiqlayman — yuklash';
                                        bulkResult.className = 'text-sm rounded-md px-4 py-3 bg-blue-50 text-blue-800';
                                        bulkResult.textContent = "Hali hech narsa saqlanmadi. Yuqoridagi natijalarni ko'rib chiqing va tasdiqlang.";
                                    } else {
                                        bulkResult.className = 'text-sm rounded-md px-4 py-3 bg-red-50 text-red-700';
                                        bulkResult.textContent = "Birorta fayl ham yuklashga yaroqli emas — fayllarni yoki kurs/semestr tanlovini to'g'rilang.";
                                    }
                                } catch (e) {
                                    bulkResult.className = 'text-sm rounded-md px-4 py-3 bg-red-50 text-red-700';
                                    bulkResult.textContent = 'Xatolik: ' + e.message;
                                } finally {
                                    this.disabled = false;
                                }
                                return;
                            }

                            // 2-bosqich: tasdiqlangan — haqiqiy yuklash
                            bulkResult.className = 'text-sm rounded-md px-4 py-3 bg-gray-50 text-gray-600';
                            bulkResult.textContent = 'Yuklanmoqda... (' + count + ' ta fayl)';
                            try {
                                const res = await fetch(bulkUrl, {method: 'POST', body: fd, headers: {'Accept': 'application/json'}});
                                const j = await res.json();
                                if (!res.ok) throw new Error(j.message || 'Server xatosi (' + res.status + ')');
                                const parts = [j.created + ' ta reja yuklandi'];
                                if (j.skipped) parts.push(j.skipped + " ta oldin mavjud (o'tkazildi)");
                                if (j.errors && j.errors.length) parts.push('Xatolar: ' + j.errors.join(' | '));
                                bulkResult.className = 'text-sm rounded-md px-4 py-3 ' +
                                    (j.errors && j.errors.length ? 'bg-yellow-50 text-yellow-800' : 'bg-green-50 text-green-800');
                                bulkResult.textContent = parts.join('. ');
                                resetBulkStage();
                                spSel.dispatchEvent(new Event('change')); // orqadagi jadvalni yangilash
                            } catch (e) {
                                bulkResult.className = 'text-sm rounded-md px-4 py-3 bg-red-50 text-red-700';
                                bulkResult.textContent = 'Xatolik: ' + e.message;
                            } finally {
                                this.disabled = false;
                            }
                        });

                        function escHtml(s) {
                            return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
                        }
                    })();

                    // ===== O'tiladigan fanlar tab (2-bosqich) =====
                    (function () {
                        const sumUrl = @json(route('admin.oquv-reja.subjects-summary'));
                        const expUrl = @json(route('admin.oquv-reja.subjects-summary.export'));
                        const kafListUrl = @json(route('admin.oquv-reja.kafedra-list'));
                        const setKafUrl  = @json(route('admin.oquv-reja.set-kafedra'));
                        const setPsUrl   = @json(route('admin.oquv-reja.set-practice-size'));
                        const showUrlFs  = @json(route('admin.oquv-reja.show', '__ID__'));
                        const csrf = document.querySelector('input[name="_token"]')?.value;
                        const kafDatalist = document.getElementById('fsKafList');
                        let rowsData = [];
                        let sortKey = null, sortDir = 1;
                        const spec = document.getElementById('fsSpecialty');
                        const year = document.getElementById('fsYear');
                        const kurs = document.getElementById('fsKurs');
                        const sem  = document.getElementById('fsSem');
                        const planned = document.getElementById('fsPlanned');
                        const bySem = document.getElementById('fsBySem');
                        const bySemBody = document.getElementById('fsBySemBody');
                        const tbody = document.getElementById('fsTbody');
                        const wrap  = document.getElementById('fsTableWrap');
                        const empty = document.getElementById('fsEmpty');
                        const load  = document.getElementById('fsLoading');
                        const tiles = document.getElementById('fsTiles');
                        const exp   = document.getElementById('fsExport');
                        let loadedOnce = false;

                        function esc(s){return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}
                        function n(v){return (v === null || v === undefined || v === 0) ? '<span class="text-gray-300">·</span>' : (Math.round(v*10)/10);}

                        function params() {
                            const p = new URLSearchParams();
                            if (spec.value) p.set('specialty_code', spec.value);
                            if (year.value) p.set('academic_year', year.value);
                            if (kurs.value) p.set('level_code', kurs.value);
                            if (sem.value)  p.set('semester', sem.value);
                            p.set('include_planned', planned.checked ? '1' : '0');
                            return p;
                        }

                        function tile(label, val, cls) {
                            return '<span class="inline-flex items-center gap-1 rounded-md px-2 py-1 ' + cls + '">' +
                                label + ': <b>' + val + '</b></span>';
                        }

                        async function reload() {
                            load.classList.remove('hidden'); wrap.classList.add('hidden'); empty.classList.add('hidden');
                            exp.href = expUrl + '?' + params().toString();
                            try {
                                const j = await (await fetch(sumUrl + '?' + params(), {headers:{'Accept':'application/json'}})).json();
                                load.classList.add('hidden');
                                const t = j.totals;
                                tiles.innerHTML =
                                    tile('Fanlar', t.subjects, 'bg-gray-100 text-gray-700') +
                                    tile("Ma'ruza", t.lecture, 'bg-blue-50 text-blue-700') +
                                    tile('Amaliy', t.practice, 'bg-purple-50 text-purple-700') +
                                    tile('Lab', t.laboratory, 'bg-teal-50 text-teal-700') +
                                    tile('Seminar', t.seminar, 'bg-orange-50 text-orange-700') +
                                    tile('Mustaqil', t.independent, 'bg-gray-50 text-gray-600') +
                                    tile('Jami soat', t.total_hours, 'bg-gray-800 text-white') +
                                    tile('Kredit', t.credit, 'bg-emerald-50 text-emerald-700');
                                // Semestrlararo yuklama jadvali
                                const bs = j.by_semester || [];
                                if (bs.length > 1) {
                                    bySemBody.innerHTML = bs.map(s =>
                                        '<tr class="hover:bg-gray-50">' +
                                        '<td class="px-2 py-1 font-semibold text-gray-800">' + (s.semester ? s.semester + '-semestr' : '—') + '</td>' +
                                        '<td class="px-2 py-1 text-right">' + s.subjects + '</td>' +
                                        '<td class="px-2 py-1 text-right text-blue-700">' + n(s.lecture) + '</td>' +
                                        '<td class="px-2 py-1 text-right text-purple-700">' + n(s.practice) + '</td>' +
                                        '<td class="px-2 py-1 text-right text-teal-700">' + n(s.laboratory) + '</td>' +
                                        '<td class="px-2 py-1 text-right text-orange-700">' + n(s.seminar) + '</td>' +
                                        '<td class="px-2 py-1 text-right font-semibold">' + n(s.total_hours) + '</td>' +
                                        '<td class="px-2 py-1 text-right">' + n(s.credit) + '</td>' +
                                        '</tr>').join('');
                                    bySem.classList.remove('hidden');
                                } else {
                                    bySem.classList.add('hidden');
                                }

                                rowsData = j.rows || [];
                                if (!rowsData.length) { empty.classList.remove('hidden'); tbody.innerHTML=''; return; }
                                renderRows();
                                wrap.classList.remove('hidden');
                            } catch (e) {
                                load.classList.add('hidden');
                                empty.textContent = 'Xatolik yuz berdi.'; empty.classList.remove('hidden');
                            }
                        }

                        function renderRows() {
                            let rows = rowsData.slice();
                            if (sortKey) {
                                rows.sort((a, b) => {
                                    let x = a[sortKey], y = b[sortKey];
                                    if (typeof x === 'string' || typeof y === 'string') {
                                        x = (x || '').toString().toLowerCase(); y = (y || '').toString().toLowerCase();
                                        return x < y ? -sortDir : x > y ? sortDir : 0;
                                    }
                                    return ((x || 0) - (y || 0)) * sortDir;
                                });
                            }
                            tbody.innerHTML = rows.map(r => {
                                const kafHtml = r.kafedra
                                    ? '<span class="' + (r.kafedra_manual ? 'text-emerald-700 font-medium' : 'text-gray-600') + '">' + esc(r.kafedra) + '</span>'
                                    : '<span class="text-gray-300">— belgilash</span>';
                                const rejaList = r.reja || [];
                                const rejaHtml = rejaList.length
                                    ? rejaList.map(rj => '<a href="' + showUrlFs.replace('__ID__', rj.id) + '" target="_blank" class="text-blue-600 hover:underline block truncate max-w-[220px]" title="' + esc(rj.name) + '">' + esc(rj.name) + '</a>').join('')
                                    : '<span class="text-gray-300">—</span>';
                                return '<tr class="hover:bg-gray-50">' +
                                    '<td class="px-2 py-1 text-gray-700">' + esc(r.specialty_name || r.specialty_code || '') +
                                        (r.specialty_code ? ' <span class="text-[10px] text-gray-400">' + esc(r.specialty_code) + '</span>' : '') + '</td>' +
                                    '<td class="px-2 py-1 text-center">' + (r.kurs ?? '') + '</td>' +
                                    '<td class="px-2 py-1 text-center">' + (r.semester ?? '') + '</td>' +
                                    '<td class="px-2 py-1 text-gray-500">' + esc(r.block||'') + '</td>' +
                                    '<td class="px-2 py-1 font-medium text-gray-800">' + esc(r.subject_name) +
                                        (r.reja_count > 1 ? ' <span class="text-[10px] text-gray-400">×' + r.reja_count + '</span>' : '') + '</td>' +
                                    '<td class="px-2 py-1">' + rejaHtml + '</td>' +
                                    '<td class="px-2 py-1 kaf-cell cursor-pointer hover:bg-amber-50" title="Kafedrani tahrirlash uchun bosing" data-subject="' + esc(r.subject_name) + '">' + kafHtml + '</td>' +
                                    '<td class="px-2 py-1 text-center ps-cell cursor-pointer hover:bg-amber-50" title="Amaliy guruh o\'lchamini tahrirlash uchun bosing" data-subject="' + esc(r.subject_name) + '"><span class="' + (r.practice_manual ? 'text-emerald-700 font-semibold' : 'text-gray-500') + '">' + (r.practice_size || '') + '</span></td>' +
                                    '<td class="px-2 py-1 text-right">' + n(r.lecture) + '</td>' +
                                    '<td class="px-2 py-1 text-right">' + n(r.practice) + '</td>' +
                                    '<td class="px-2 py-1 text-right">' + n(r.laboratory) + '</td>' +
                                    '<td class="px-2 py-1 text-right">' + n(r.seminar) + '</td>' +
                                    '<td class="px-2 py-1 text-right text-gray-500">' + n(r.independent) + '</td>' +
                                    '<td class="px-2 py-1 text-right font-semibold">' + n(r.total_hours) + '</td>' +
                                    '<td class="px-2 py-1 text-right">' + n(r.credit) + '</td>' +
                                    '</tr>';
                            }).join('');
                        }

                        // Ustun sarlavhasini bosib saralash
                        document.querySelectorAll('.fs-sort').forEach(th => th.addEventListener('click', () => {
                            const k = th.dataset.sort;
                            if (sortKey === k) sortDir = -sortDir; else { sortKey = k; sortDir = 1; }
                            document.querySelectorAll('.fs-ind').forEach(s => s.textContent = '');
                            th.querySelector('.fs-ind').textContent = sortDir > 0 ? ' ▲' : ' ▼';
                            renderRows();
                        }));

                        // Kafedrani qatorда qo'lda tahrirlash (inline)
                        tbody.addEventListener('click', function (e) {
                            const cell = e.target.closest('.kaf-cell');
                            if (!cell || cell.querySelector('input')) return;
                            const subject = cell.dataset.subject;
                            const cur = cell.textContent.replace('— belgilash', '').trim();
                            cell.innerHTML = '<input list="fsKafList" class="w-full text-xs rounded border-amber-300 px-1 py-0.5" value="' + cur.replace(/"/g,'&quot;') + '">';
                            const inp = cell.querySelector('input');
                            inp.focus();
                            let done = false;
                            const save = async () => {
                                if (done) return; done = true;
                                const val = inp.value.trim();
                                const fd = new FormData();
                                fd.append('_token', csrf);
                                fd.append('subject_name', subject);
                                fd.append('kafedra_name', val);
                                try {
                                    const r = await fetch(setKafUrl, {method:'POST', body:fd, headers:{'Accept':'application/json'}});
                                    if (!r.ok) throw new Error();
                                    // Shu nomli barcha qatorlarni yangilaymiz
                                    rowsData.forEach(rd => { if (rd.subject_name === subject) { rd.kafedra = val || null; rd.kafedra_manual = !!val; } });
                                    renderRows();
                                } catch (_) {
                                    cell.innerHTML = '<span class="text-red-500">xato</span>';
                                }
                            };
                            inp.addEventListener('keydown', ev => { if (ev.key === 'Enter') { ev.preventDefault(); save(); } if (ev.key === 'Escape') { done = true; renderRows(); } });
                            inp.addEventListener('blur', save);
                        });

                        // Amaliy guruh o'lchamini qatorda tahrirlash
                        tbody.addEventListener('click', function (e) {
                            const cell = e.target.closest('.ps-cell');
                            if (!cell || cell.querySelector('input')) return;
                            const subject = cell.dataset.subject;
                            const cur = cell.textContent.trim();
                            cell.innerHTML = '<input type="number" min="1" class="w-14 text-xs text-center rounded border-amber-300 px-1 py-0.5" value="' + cur + '">';
                            const inp = cell.querySelector('input');
                            inp.focus();
                            let done = false;
                            const save = async () => {
                                if (done) return; done = true;
                                const val = inp.value.trim();
                                const fd = new FormData();
                                fd.append('_token', csrf);
                                fd.append('subject_name', subject);
                                fd.append('practice_group_size', val);
                                try {
                                    const r = await fetch(setPsUrl, {method:'POST', body:fd, headers:{'Accept':'application/json'}});
                                    if (!r.ok) throw new Error();
                                    const num = parseInt(val) || null;
                                    rowsData.forEach(rd => { if (rd.subject_name === subject) { rd.practice_size = num; rd.practice_manual = !!num; } });
                                    renderRows();
                                } catch (_) {
                                    cell.innerHTML = '<span class="text-red-500">xato</span>';
                                }
                            };
                            inp.addEventListener('keydown', ev => { if (ev.key === 'Enter') { ev.preventDefault(); save(); } if (ev.key === 'Escape') { done = true; renderRows(); } });
                            inp.addEventListener('blur', save);
                        });

                        // Kafedralar ro'yxatini (datalist) yuklaymiz
                        (async () => {
                            try {
                                const list = await (await fetch(kafListUrl, {headers:{'Accept':'application/json'}})).json();
                                kafDatalist.innerHTML = list.map(k => '<option value="' + String(k).replace(/"/g,'&quot;') + '">').join('');
                            } catch (_) {}
                        })();

                        [spec, year, kurs, sem, planned].forEach(el => el.addEventListener('change', reload));
                        // Boshlanishida eng so'nggi o'quv yilini tanlab qo'yamiz
                        if (year.options.length > 1) year.selectedIndex = 1;
                        // Tab ochilganda birinchi marta yuklaymiz
                        document.querySelector('.main-tab[data-tab="fanlar"]').addEventListener('click', () => {
                            if (!loadedOnce) { loadedOnce = true; reload(); }
                        });
                        if ((location.hash || '').replace('#','') === 'fanlar') { loadedOnce = true; reload(); }
                    })();
                })();
            </script>

        </div>
    </div>
</x-app-layout>
