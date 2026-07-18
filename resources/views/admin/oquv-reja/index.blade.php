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
                    const validHashes = ['solishtirish', 'yonalish'];
                    activateMainTab(validHashes.includes(hash) ? hash : 'rejalar');
                    const firstSub = document.querySelector('.sub-tab');
                    if (firstSub) firstSub.click();

                    // ===== Yo'nalish bo'yicha tab =====
                    (function () {
                        const optionsUrl = @json(route('admin.oquv-reja.options'));
                        const batchUrl   = @json(route('admin.oquv-reja.batch-view'));
                        const showUrl    = @json(route('admin.oquv-reja.show', '__ID__'));

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
                                const rows = await (await fetch(batchUrl + '?specialty_id=' + this.value, {headers: {'Accept': 'application/json'}})).json();
                                loading.classList.add('hidden');
                                if (!rows.length) { empty.classList.remove('hidden'); return; }

                                tbody.innerHTML = rows.map(r => {
                                    // Barcha yuklangan rejalarni ko'rsatamiz (semestrdan qat'i nazar)
                                    const nam = r.uploaded.filter(u => u.type === 'namunaviy');
                                    const ish = r.uploaded.filter(u => u.type === 'ishchi');

                                    function uploadedCell(list) {
                                        if (!list.length) return '<span class="text-gray-400">—</span>';
                                        return list.map(u => {
                                            const isCurrent = !u.semester_code || u.semester_code === r.semester_code;
                                            const semSuffix = !isCurrent && u.semester_code ? ' [' + u.semester_code + '-sem]' : '';
                                            const label = (u.name.length > 30 ? u.name.slice(0,30)+'…' : u.name) + semSuffix;
                                            const cls = isCurrent
                                                ? 'bg-green-100 text-green-800 hover:bg-green-200'
                                                : 'bg-yellow-100 text-yellow-800 hover:bg-yellow-200';
                                            return '<a href="' + showUrl.replace('__ID__', u.id) + '" class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xs font-medium ' + cls + ' mr-1">✓ ' + escHtml(label) + '</a>';
                                        }).join('');
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

                        function escHtml(s) {
                            return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
                        }
                    })();
                })();
            </script>

        </div>
    </div>
</x-app-layout>
