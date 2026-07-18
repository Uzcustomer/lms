<x-app-layout>
    <div class="py-6">
        <div class="w-full px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <h1 class="text-2xl font-bold text-slate-900">Test fan yaratish</h1>
                <p class="text-sm text-slate-500 mt-1">Birinchi bosqich: test fan, o'qituvchi, guruhlar va dars jadvalini yarating.</p>
            </div>

            @if ($errors->any())
                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-red-700">
                    <div class="font-semibold mb-2">Xatoliklar:</div>
                    <ul class="list-disc ml-5 space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.test-subjects.store') }}" class="space-y-6">
                @csrf

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Fan nomi</label>
                            <input type="text" name="name" value="{{ old('name') }}"
                                   class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500"
                                   placeholder="Masalan: Klinik test fan" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Fakultet</label>
                            <select id="faculty-filter" name="faculty_hemis_id"
                                    class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Barchasi</option>
                                @foreach($faculties as $hemisId => $name)
                                    <option value="{{ $hemisId }}" @selected(old('faculty_hemis_id') == $hemisId)>{{ $name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Yo'nalish</label>
                            <select id="specialty-filter" name="specialty_hemis_id"
                                    class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Barchasi</option>
                                @foreach($specialties as $specialty)
                                    <option value="{{ $specialty['specialty_hemis_id'] }}"
                                            data-faculty="{{ $specialty['department_hemis_id'] }}"
                                            @selected(old('specialty_hemis_id') == $specialty['specialty_hemis_id'])>
                                        {{ $specialty['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Kurs</label>
                            <select id="level-filter" name="level_code"
                                    class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Barchasi</option>
                                @foreach($levels as $level)
                                    <option value="{{ $level['code'] }}" @selected(old('level_code') == $level['code'])>{{ $level['name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">O'qituvchi</label>
                            <select name="teacher_id"
                                    class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Tanlanmagan</option>
                                @foreach($teachers as $teacher)
                                    <option value="{{ $teacher['id'] }}" @selected(old('teacher_id') == $teacher['id'])>{{ $teacher['full_name'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Boshlanish sanasi</label>
                            <input type="date" name="starts_on" value="{{ old('starts_on') }}"
                                   class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Tugash sanasi</label>
                            <input type="date" name="ends_on" value="{{ old('ends_on') }}"
                                   class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500" required>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">Guruhlar</h2>
                            <p class="text-sm text-slate-500">Faqat 2025-2026 o'quv yilidagi guruhlar ko'rsatiladi.</p>
                        </div>
                        <button type="button" id="toggle-all-groups"
                                class="inline-flex items-center px-3 py-2 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 transition">
                            Hammasini belgilash
                        </button>
                    </div>

                    <div id="groups-grid" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                        @foreach($groups as $group)
                            <label class="group-card rounded-xl border border-slate-200 p-4 flex items-start gap-3"
                                   data-faculty="{{ $group['department_hemis_id'] }}"
                                   data-specialty="{{ $group['specialty_hemis_id'] }}"
                                   data-level="{{ $group['level_code'] }}">
                                <input type="checkbox" name="group_ids[]" value="{{ $group['id'] }}"
                                       class="mt-1 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                       @checked(in_array($group['id'], old('group_ids', [])))>
                                <div>
                                    <div class="font-semibold text-slate-900">{{ $group['name'] }}</div>
                                    <div class="text-xs text-slate-500 mt-1">
                                        {{ $group['level_name'] ?: (($group['level_code'] ?: '-') . '-kurs') }}
                                    </div>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-lg font-semibold text-slate-900">Dars jadvali</h2>
                            <p class="text-sm text-slate-500">Har bir qator keyinchalik bitta mavzu sifatida ishlatiladi.</p>
                        </div>
                        <button type="button" id="add-lesson"
                                class="inline-flex items-center px-3 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition">
                            + Dars qo'shish
                        </button>
                    </div>

                    <div id="lessons-wrap" class="space-y-3">
                        @php
                            $oldLessons = old('lessons', [['lesson_date' => '', 'starts_at' => '', 'ends_at' => '', 'topic_title' => '']]);
                        @endphp
                        @foreach($oldLessons as $index => $lesson)
                            <div class="lesson-row grid grid-cols-1 md:grid-cols-4 gap-3 rounded-xl border border-slate-200 p-4">
                                <div>
                                    <label class="block text-xs font-medium text-slate-600 mb-2">Sana</label>
                                    <input type="date" name="lessons[{{ $index }}][lesson_date]" value="{{ $lesson['lesson_date'] ?? '' }}"
                                           class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500" required>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-600 mb-2">Boshlanish</label>
                                    <input type="time" name="lessons[{{ $index }}][starts_at]" value="{{ $lesson['starts_at'] ?? '' }}"
                                           class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-slate-600 mb-2">Tugash</label>
                                    <input type="time" name="lessons[{{ $index }}][ends_at]" value="{{ $lesson['ends_at'] ?? '' }}"
                                           class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                                </div>
                                <div class="flex gap-3 items-end">
                                    <div class="flex-1">
                                        <label class="block text-xs font-medium text-slate-600 mb-2">Mavzu nomi</label>
                                        <input type="text" name="lessons[{{ $index }}][topic_title]" value="{{ $lesson['topic_title'] ?? '' }}"
                                               class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500"
                                               placeholder="Masalan: 1-mavzu">
                                    </div>
                                    <button type="button" class="remove-lesson inline-flex items-center px-3 py-2 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition">
                                        O'chirish
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('admin.test-subjects.index') }}"
                       class="inline-flex items-center px-4 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100 transition">
                        Bekor qilish
                    </a>
                    <button type="submit"
                            class="inline-flex items-center px-5 py-2 rounded-lg bg-blue-600 text-white font-semibold hover:bg-blue-700 transition">
                        Saqlash
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const faculty = document.getElementById('faculty-filter');
            const specialty = document.getElementById('specialty-filter');
            const level = document.getElementById('level-filter');
            const groupCards = Array.from(document.querySelectorAll('.group-card'));
            const toggleAllBtn = document.getElementById('toggle-all-groups');
            const lessonsWrap = document.getElementById('lessons-wrap');
            const addLessonBtn = document.getElementById('add-lesson');

            function syncSpecialtyOptions() {
                const facultyVal = faculty.value;
                Array.from(specialty.options).forEach((option) => {
                    if (!option.value) {
                        option.hidden = false;
                        return;
                    }
                    option.hidden = facultyVal !== '' && option.dataset.faculty !== facultyVal;
                });

                const selected = specialty.options[specialty.selectedIndex];
                if (selected && selected.hidden) {
                    specialty.value = '';
                }
            }

            function filterGroups() {
                const facultyVal = faculty.value;
                const specialtyVal = specialty.value;
                const levelVal = level.value;

                let visibleCount = 0;
                groupCards.forEach((card) => {
                    const matchFaculty = !facultyVal || card.dataset.faculty === facultyVal;
                    const matchSpecialty = !specialtyVal || card.dataset.specialty === specialtyVal;
                    const matchLevel = !levelVal || card.dataset.level === levelVal;
                    const visible = matchFaculty && matchSpecialty && matchLevel;
                    card.style.display = visible ? '' : 'none';
                    if (visible) visibleCount++;
                });

                toggleAllBtn.disabled = visibleCount === 0;
            }

            function toggleVisibleGroups() {
                const visibleCards = groupCards.filter((card) => card.style.display !== 'none');
                const inputs = visibleCards.map((card) => card.querySelector('input[type="checkbox"]'));
                const shouldCheck = inputs.some((input) => !input.checked);
                inputs.forEach((input) => {
                    input.checked = shouldCheck;
                });
            }

            function refreshLessonIndexes() {
                Array.from(lessonsWrap.querySelectorAll('.lesson-row')).forEach((row, index) => {
                    row.querySelectorAll('input').forEach((input) => {
                        input.name = input.name.replace(/lessons\[\d+\]/, 'lessons[' + index + ']');
                    });
                });
            }

            function bindRemoveButtons() {
                lessonsWrap.querySelectorAll('.remove-lesson').forEach((btn) => {
                    btn.onclick = function () {
                        const rows = lessonsWrap.querySelectorAll('.lesson-row');
                        if (rows.length === 1) {
                            rows[0].querySelectorAll('input').forEach((input) => input.value = '');
                            return;
                        }
                        btn.closest('.lesson-row').remove();
                        refreshLessonIndexes();
                    };
                });
            }

            addLessonBtn.addEventListener('click', function () {
                const index = lessonsWrap.querySelectorAll('.lesson-row').length;
                const wrapper = document.createElement('div');
                wrapper.className = 'lesson-row grid grid-cols-1 md:grid-cols-4 gap-3 rounded-xl border border-slate-200 p-4';
                wrapper.innerHTML = `
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-2">Sana</label>
                        <input type="date" name="lessons[${index}][lesson_date]" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500" required>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-2">Boshlanish</label>
                        <input type="time" name="lessons[${index}][starts_at]" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-slate-600 mb-2">Tugash</label>
                        <input type="time" name="lessons[${index}][ends_at]" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div class="flex gap-3 items-end">
                        <div class="flex-1">
                            <label class="block text-xs font-medium text-slate-600 mb-2">Mavzu nomi</label>
                            <input type="text" name="lessons[${index}][topic_title]" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500" placeholder="Masalan: 1-mavzu">
                        </div>
                        <button type="button" class="remove-lesson inline-flex items-center px-3 py-2 rounded-lg bg-red-50 text-red-600 hover:bg-red-100 transition">O'chirish</button>
                    </div>
                `;
                lessonsWrap.appendChild(wrapper);
                bindRemoveButtons();
            });

            faculty.addEventListener('change', function () {
                syncSpecialtyOptions();
                filterGroups();
            });
            specialty.addEventListener('change', filterGroups);
            level.addEventListener('change', filterGroups);
            toggleAllBtn.addEventListener('click', toggleVisibleGroups);

            syncSpecialtyOptions();
            filterGroups();
            bindRemoveButtons();
        });
    </script>
</x-app-layout>
