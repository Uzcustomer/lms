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

            {{-- Solishtirish --}}
            @php
                $namunaviyList = $curricula->where('type', 'namunaviy');
                $ishchiList = $curricula->where('type', 'ishchi');
            @endphp
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

                            const semesterLabel = i => (i.name || i.code) + (i.current ? ' (joriy)' : '');
                            const curriculumLabel = i => i.name + (i.education_year_name ? ' [' + i.education_year_name + ']' : '');

                            selects.educationType.addEventListener('change', function () {
                                if (!this.value) return reset('faculty', "Avval ta'lim turini tanlang");
                                load('faculties', 'faculty', i => i.name, i => i.id);
                            });
                            selects.faculty.addEventListener('change', function () {
                                if (!this.value) return reset('specialty', 'Avval fakultetni tanlang');
                                load('specialties', 'specialty', i => (i.code ? i.code + ' — ' : '') + i.name, i => i.id);
                            });
                            selects.specialty.addEventListener('change', async function () {
                                if (!this.value) return reset('level', "Avval yo'nalishni tanlang");
                                let autoLevel = null;
                                if (currentToggle.checked) {
                                    // Joriy semestrning kursi yagona bo'lsa, avtomatik tanlanadi
                                    const current = await fetchItems('current');
                                    const levels = [...new Set(current.map(i => String(i.level_code)))];
                                    if (levels.length === 1) autoLevel = levels[0];
                                }
                                load('levels', 'level', i => i.level_name || i.level_code, i => i.level_code, autoLevel);
                            });
                            selects.level.addEventListener('change', async function () {
                                if (!this.value) return reset('semester', 'Avval kursni tanlang');
                                let autoSemester = null;
                                if (currentToggle.checked) {
                                    const current = await fetchItems('current', {level_code: this.value});
                                    if (current.length > 0) autoSemester = current[0].code;
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
                        })();
                    </script>
                </div>
            </div>

            {{-- Yuklangan rejalar --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-800 mb-4">Yuklangan o'quv rejalar</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">#</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Turi</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Nomi</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Yo'nalish</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Reja yili</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">Fan qatorlari</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">Jami soat</th>
                                <th class="px-4 py-2 text-right font-medium text-gray-600">Jami kredit</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Yuklangan</th>
                                <th class="px-4 py-2 text-left font-medium text-gray-600">Amallar</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                            @forelse($curricula as $curriculum)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2">{{ $loop->iteration }}</td>
                                    <td class="px-4 py-2">
                                        <span class="px-2 py-1 rounded text-xs font-medium {{ $curriculum->type === 'namunaviy' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800' }}">
                                            {{ $curriculum->typeLabel() }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-2">
                                        <a href="{{ route('admin.oquv-reja.show', $curriculum) }}" class="text-blue-600 hover:underline">
                                            {{ $curriculum->name }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-2">{{ trim($curriculum->specialty_code . ' ' . $curriculum->specialty_name) ?: '—' }}</td>
                                    <td class="px-4 py-2">{{ $curriculum->plan_year ?: '—' }}</td>
                                    <td class="px-4 py-2 text-right">{{ $curriculum->subjects_count }}</td>
                                    <td class="px-4 py-2 text-right">{{ rtrim(rtrim(number_format($curriculum->total_hours ?? 0, 2, '.', ' '), '0'), '.') }}</td>
                                    <td class="px-4 py-2 text-right">{{ rtrim(rtrim(number_format($curriculum->total_credit ?? 0, 2, '.', ' '), '0'), '.') }}</td>
                                    <td class="px-4 py-2">{{ $curriculum->created_at->format('d.m.Y H:i') }}</td>
                                    <td class="px-4 py-2">
                                        <form method="POST" action="{{ route('admin.oquv-reja.destroy', $curriculum) }}"
                                              onsubmit="return confirm('Ushbu reja va uning barcha fan qatorlari o’chirilsinmi?');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:underline text-sm">O'chirish</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="px-4 py-6 text-center text-gray-500">
                                        Hozircha o'quv reja yuklanmagan. Yuqoridagi forma orqali Excel fayl yuklang.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
