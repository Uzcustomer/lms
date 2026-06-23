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
                </nav>
            </div>

            {{-- ===== PANEL: O'quv rejalar ===== --}}
            <div data-panel="rejalar">
            {{-- Yangi reja yuklash --}}
            <div class="bg-white shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Yangi o'quv reja yuklash (Excel)</h3>
                    <form method="POST" action="{{ route('admin.oquv-reja.store') }}" enctype="multipart/form-data"
                          class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @csrf
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Reja turi</label>
                            <select name="type" required class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="namunaviy">Namunaviy o'quv reja</option>
                                <option value="ishchi">Ishchi o'quv reja</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Ta'lim turi</label>
                            <select id="cascade-education-type" required class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="">Tanlang</option>
                                @foreach($educationTypes as $et)
                                    <option value="{{ $et->education_type_code }}">{{ $et->education_type_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fakultet</label>
                            <select id="cascade-faculty" required disabled class="w-full rounded-md border-gray-300 shadow-sm text-sm disabled:bg-gray-100">
                                <option value="">Avval ta'lim turini tanlang</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Yo'nalish</label>
                            <select id="cascade-specialty" required disabled class="w-full rounded-md border-gray-300 shadow-sm text-sm disabled:bg-gray-100">
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
                            <select id="cascade-level" name="level_code" required disabled class="w-full rounded-md border-gray-300 shadow-sm text-sm disabled:bg-gray-100">
                                <option value="">Avval yo'nalishni tanlang</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Semestr</label>
                            <select id="cascade-semester" name="semester_code" required disabled class="w-full rounded-md border-gray-300 shadow-sm text-sm disabled:bg-gray-100">
                                <option value="">Avval kursni tanlang</option>
                            </select>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700 mb-1">O'quv reja (HEMIS)</label>
                            <select id="cascade-curriculum" name="curricula_hemis_id" required disabled class="w-full rounded-md border-gray-300 shadow-sm text-sm disabled:bg-gray-100">
                                <option value="">Avval semestrni tanlang</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Excel fayl (.xlsx)</label>
                            <input type="file" name="file" required accept=".xlsx,.xls"
                                   class="w-full text-sm text-gray-700 border border-gray-300 rounded-md">
                        </div>
                        <div class="md:col-span-3">
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
                            // Tanlov o'zgarganda o'zidan keyingilarini tozalash tartibi
                            const chain = ['faculty', 'specialty', 'level', 'semester', 'curriculum'];

                            function reset(fromKey, placeholder) {
                                let clearing = false;
                                for (const key of chain) {
                                    if (key === fromKey) clearing = true;
                                    if (!clearing) continue;
                                    const select = selects[key];
                                    select.innerHTML = '<option value="">' + (key === fromKey ? placeholder : 'Avval oldingi maydonni tanlang') + '</option>';
                                    select.disabled = true;
                                }
                            }

                            function params() {
                                const p = new URLSearchParams();
                                if (selects.educationType.value) p.set('education_type_code', selects.educationType.value);
                                if (selects.faculty.value) p.set('department_id', selects.faculty.value);
                                if (selects.specialty.value) p.set('specialty_id', selects.specialty.value);
                                if (selects.level.value) p.set('level_code', selects.level.value);
                                if (selects.semester.value) p.set('semester_code', selects.semester.value);
                                if (document.getElementById('cascade-current-toggle').checked) p.set('current_only', '1');
                                return p;
                            }

                            const currentToggle = document.getElementById('cascade-current-toggle');

                            async function fetchItems(list, extra = {}) {
                                const p = params();
                                p.set('list', list);
                                for (const [k, v] of Object.entries(extra)) p.set(k, v);
                                const response = await fetch(optionsUrl + '?' + p.toString(), {headers: {'Accept': 'application/json'}});
                                return response.json();
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
                                    if (items.length === 0) {
                                        target.innerHTML = '<option value="">Ma\'lumot topilmadi</option>';
                                    } else if (autoSelect !== null && items.some(i => String(valueFn(i)) === String(autoSelect))) {
                                        target.value = String(autoSelect);
                                        target.dispatchEvent(new Event('change'));
                                    }
                                    return items;
                                } catch (e) {
                                    target.innerHTML = '<option value="">Xatolik, qayta urinib ko\'ring</option>';
                                    return [];
                                }
                            }

                            const cnt = i => i.student_count ? ' (' + i.student_count + ' ta talaba)' : '';
                            const semesterLabel = i => (i.name || i.code) + cnt(i);
                            const curriculumLabel = i => (i.name || ('Reja #' + i.id)) + (i.exists ? '' : ' ❌ (curricula jadvalida yo\'q)') + cnt(i);

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
                                let autoLevel = null;
                                if (currentToggle.checked) {
                                    // Yo'nalishda yagona kurs bo'lsa, avtomatik tanlanadi
                                    const levels = await fetchItems('levels');
                                    if (levels.length === 1) autoLevel = levels[0].level_code;
                                }
                                load('levels', 'level', i => (i.level_name || i.level_code) + cnt(i), i => i.level_code, autoLevel);
                            });
                            selects.level.addEventListener('change', async function () {
                                if (!this.value) return reset('semester', 'Avval kursni tanlang');
                                let autoSemester = null;
                                if (currentToggle.checked) {
                                    // Active talabalar joriy semestrida — eng ko'p talabali semestr tanlanadi
                                    const semesters = await fetchItems('semesters', {level_code: this.value});
                                    if (semesters.length > 0) {
                                        autoSemester = semesters.reduce((a, b) => (b.student_count > a.student_count ? b : a)).code;
                                    }
                                }
                                load('semesters', 'semester', semesterLabel, i => i.code, autoSemester);
                            });
                            selects.semester.addEventListener('change', function () {
                                if (!this.value) return reset('curriculum', 'Avval semestrni tanlang');
                                load('curricula', 'curriculum', curriculumLabel, i => i.id);
                            });
                            currentToggle.addEventListener('change', function () {
                                // Toggle holati o'zgarsa, yo'nalishdan boshlab qayta hisoblanadi
                                if (selects.specialty.value) {
                                    selects.specialty.dispatchEvent(new Event('change'));
                                }
                            });
                            // ===== Diagnostika =====
                            // Tugma script'dan keyin DOM'ga qo'shiladi, shuning uchun
                            // handler DOM to'liq yuklangach ulanadi
                            document.addEventListener('DOMContentLoaded', function () {
                                const diagBtn = document.getElementById('diagnose-btn');
                                const diagBox = document.getElementById('diagnose-result');
                                if (!diagBtn || !diagBox) return;
                                diagBtn.addEventListener('click', async function () {
                                    if (!selects.faculty.value) {
                                        diagBox.innerHTML = '<div class="text-sm text-red-600 p-3">Avval ta\'lim turi va fakultetni tanlang.</div>';
                                        return;
                                    }
                                    diagBox.innerHTML = '<div class="text-sm text-gray-500 p-3">Yuklanmoqda...</div>';
                                    let rows;
                                    try {
                                        rows = await fetchItems('diagnose');
                                    } catch (e) {
                                        diagBox.innerHTML = '<div class="text-sm text-red-600 p-3">Xatolik: ' + e + '</div>';
                                        return;
                                    }
                                    if (!rows.length) {
                                        diagBox.innerHTML = '<div class="text-sm text-gray-500 p-3">Bu fakultetda talaba topilmadi.</div>';
                                        return;
                                    }
                                    let html = '<div class="overflow-x-auto"><table class="min-w-full text-xs border"><thead class="bg-gray-100"><tr>' +
                                        ['Yo\'nalish', 'spec_id', 'Kurs', 'Semestr', 'Talaba', 'Status', 'Reja ID', 'Reja nomi', 'Semestr "current"?']
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
                    activateMainTab(hash === 'solishtirish' ? 'solishtirish' : 'rejalar');
                    const firstSub = document.querySelector('.sub-tab');
                    if (firstSub) firstSub.click();
                })();
            </script>

        </div>
    </div>
</x-app-layout>
