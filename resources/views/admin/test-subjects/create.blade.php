<x-app-layout>
    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <h1 class="text-2xl font-bold text-slate-900">Yangi test fan</h1>
                <p class="text-sm text-slate-500 mt-1">Admin fan yaratadi, o‘qituvchi biriktiradi, guruhlar va dars kunlarini belgilaydi.</p>
            </div>

            @if($errors->any())
                <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-rose-700">
                    <div class="font-semibold mb-1">Saqlashda xatolik bor.</div>
                    <ul class="list-disc pl-5 space-y-1 text-sm">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('admin.test-subjects.store') }}" x-data="testSubjectCreatePage()" class="space-y-6">
                @csrf

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
                        <div class="lg:col-span-3">
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Fan nomi</label>
                            <input type="text" name="name" value="{{ old('name') }}"
                                   class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500"
                                   placeholder="Masalan: Klinik test fan">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Kafedra</label>
                            <select name="department_hemis_id" x-model="selectedDepartment"
                                    class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Barchasi</option>
                                @foreach($departments as $department)
                                    <option value="{{ $department->department_hemis_id }}" @selected(old('department_hemis_id') == $department->department_hemis_id)>
                                        {{ $department->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Yo‘nalish</label>
                            <select name="specialty_hemis_id" x-model="selectedSpecialty"
                                    class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Barchasi</option>
                                <template x-for="specialty in filteredSpecialties" :key="specialty.specialty_hemis_id">
                                    <option :value="specialty.specialty_hemis_id" x-text="specialty.name"></option>
                                </template>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Kurs</label>
                            <select name="level_code" x-model="selectedLevel"
                                    class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Barchasi</option>
                                @foreach($levels as $level)
                                    <option value="{{ $level['code'] }}" @selected(old('level_code') == $level['code'])>
                                        {{ $level['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">O‘qituvchi</label>
                            <select name="teacher_id" class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Tanlanmagan</option>
                                @foreach($teachers as $teacher)
                                    <option value="{{ $teacher->id }}" @selected(old('teacher_id') == $teacher->id)>
                                        {{ $teacher->full_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Boshlanish sanasi</label>
                            <input type="date" name="starts_on" value="{{ old('starts_on') }}"
                                   class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">Tugash sanasi</label>
                            <input type="date" name="ends_on" value="{{ old('ends_on') }}"
                                   class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <div class="flex items-center justify-between gap-4 mb-4">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900">Guruhlar</h2>
                            <p class="text-sm text-slate-500">Tanlangan kafedra, yo‘nalish va kursga mos guruhlarni belgilang.</p>
                        </div>
                        <div class="text-sm text-slate-500">
                            Topildi: <span class="font-semibold text-slate-800" x-text="filteredGroups.length"></span>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-200 p-4 max-h-96 overflow-auto">
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                            <template x-for="group in filteredGroups" :key="group.id">
                                <label class="flex items-start gap-3 rounded-xl border border-slate-200 px-4 py-3 hover:border-blue-300 hover:bg-blue-50/40 transition cursor-pointer">
                                    <input type="checkbox" name="group_ids[]" :value="group.id"
                                           :checked="isInitiallySelected(group.id)"
                                           class="mt-1 rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                    <div>
                                        <div class="font-semibold text-slate-900" x-text="group.name"></div>
                                        <div class="text-xs text-slate-500" x-text="group.level_name || 'Kurs noma’lum'"></div>
                                    </div>
                                </label>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                    <div class="flex items-center justify-between gap-4 mb-4">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900">Dars jadvali</h2>
                            <p class="text-sm text-slate-500">Har bir dars keyin alohida mavzuga aylantiriladi.</p>
                        </div>
                        <button type="button" @click="addLesson()"
                                class="inline-flex items-center px-4 py-2 rounded-lg bg-emerald-600 text-white font-semibold hover:bg-emerald-700 transition">
                            + Dars qo‘shish
                        </button>
                    </div>

                    <div class="space-y-3">
                        <template x-for="(lesson, index) in lessons" :key="index">
                            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 rounded-2xl border border-slate-200 p-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-2">Sana</label>
                                    <input type="date" :name="`lessons[${index}][lesson_date]`" x-model="lesson.lesson_date"
                                           class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-2">Boshlanish</label>
                                    <input type="time" :name="`lessons[${index}][starts_at]`" x-model="lesson.starts_at"
                                           class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-2">Tugash</label>
                                    <input type="time" :name="`lessons[${index}][ends_at]`" x-model="lesson.ends_at"
                                           class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-600 mb-2">Mavzu nomi</label>
                                    <div class="flex gap-2">
                                        <input type="text" :name="`lessons[${index}][topic_title]`" x-model="lesson.topic_title"
                                               class="w-full rounded-xl border-slate-300 focus:border-blue-500 focus:ring-blue-500"
                                               placeholder="Ixtiyoriy">
                                        <button type="button" @click="removeLesson(index)"
                                                class="px-3 rounded-xl border border-rose-300 text-rose-600 hover:bg-rose-50">
                                            ×
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <a href="{{ route('admin.test-subjects.index') }}"
                       class="inline-flex items-center px-4 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-100 transition">
                        Bekor qilish
                    </a>
                    <button type="submit"
                            class="inline-flex items-center px-5 py-2.5 rounded-lg bg-blue-600 text-white font-semibold hover:bg-blue-700 transition">
                        Saqlash
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function testSubjectCreatePage() {
            return {
                selectedDepartment: @js((string) old('department_hemis_id', '')),
                selectedSpecialty: @js((string) old('specialty_hemis_id', '')),
                selectedLevel: @js((string) old('level_code', '')),
                specialties: @js($specialties),
                groups: @js($groups),
                initialSelectedGroups: @js(array_map('intval', old('group_ids', []))),
                lessons: @js(old('lessons', [['lesson_date' => '', 'starts_at' => '', 'ends_at' => '', 'topic_title' => '']])),
                get filteredSpecialties() {
                    if (!this.selectedDepartment) {
                        return this.specialties;
                    }
                    return this.specialties.filter(item => String(item.department_hemis_id) === String(this.selectedDepartment));
                },
                get filteredGroups() {
                    return this.groups.filter(group => {
                        const departmentOk = !this.selectedDepartment || String(group.department_hemis_id) === String(this.selectedDepartment);
                        const specialtyOk = !this.selectedSpecialty || String(group.specialty_hemis_id) === String(this.selectedSpecialty);
                        const levelOk = !this.selectedLevel || String(group.level_code) === String(this.selectedLevel);
                        return departmentOk && specialtyOk && levelOk;
                    });
                },
                isInitiallySelected(groupId) {
                    return this.initialSelectedGroups.includes(Number(groupId));
                },
                addLesson() {
                    this.lessons.push({ lesson_date: '', starts_at: '', ends_at: '', topic_title: '' });
                },
                removeLesson(index) {
                    if (this.lessons.length === 1) {
                        this.lessons[0] = { lesson_date: '', starts_at: '', ends_at: '', topic_title: '' };
                        return;
                    }
                    this.lessons.splice(index, 1);
                }
            }
        }
    </script>
</x-app-layout>
