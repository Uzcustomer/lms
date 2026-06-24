<x-app-layout>
    @php
        $lessonCount = $testSubject->lessons->count();
        $groupCount = $testSubject->groups->count();
        $activeLessonCount = $testSubject->lessons->where('is_active', true)->count();
        $durationText = (optional($testSubject->starts_on)->format('d.m.Y') ?: '-') . ' - ' . (optional($testSubject->ends_on)->format('d.m.Y') ?: '-');
    @endphp

    <div class="py-6" x-data="{ openGroupModal: null }">
        <div class="w-full px-4 sm:px-6 lg:px-8 space-y-6">
            <style>
                .test-subject-hero {
                    background: linear-gradient(135deg, #f8fbff 0%, #eef4ff 45%, #f7fbff 100%);
                    border: 1px solid #d9e4f4;
                    box-shadow: 0 12px 30px rgba(43, 94, 167, 0.08);
                }
                .test-subject-chip {
                    display: inline-flex;
                    align-items: center;
                    gap: 6px;
                    padding: 6px 12px;
                    border-radius: 9999px;
                    font-size: 12px;
                    font-weight: 700;
                    border: 1px solid transparent;
                }
                .test-subject-chip.blue {
                    background: linear-gradient(135deg, #dbeafe, #bfdbfe);
                    color: #1d4ed8;
                    border-color: #bfdbfe;
                }
                .test-subject-chip.green {
                    background: linear-gradient(135deg, #dcfce7, #bbf7d0);
                    color: #15803d;
                    border-color: #bbf7d0;
                }
                .test-subject-chip.orange {
                    background: linear-gradient(135deg, #ffedd5, #fed7aa);
                    color: #c2410c;
                    border-color: #fdba74;
                }
                .test-subject-chip.slate {
                    background: linear-gradient(135deg, #f1f5f9, #e2e8f0);
                    color: #334155;
                    border-color: #cbd5e1;
                }
                .test-subject-stat {
                    border-radius: 18px;
                    border: 1px solid #dbe4ef;
                    padding: 18px 20px;
                    box-shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
                }
                .test-subject-stat.blue {
                    background: linear-gradient(135deg, #eff6ff, #dbeafe);
                }
                .test-subject-stat.green {
                    background: linear-gradient(135deg, #ecfdf5, #d1fae5);
                }
                .test-subject-stat.orange {
                    background: linear-gradient(135deg, #fff7ed, #ffedd5);
                }
                .test-subject-card {
                    background: #fff;
                    border: 1px solid #dbe4ef;
                    border-radius: 20px;
                    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
                }
                .test-subject-card-head {
                    padding: 18px 22px;
                    border-bottom: 1px solid #e8edf5;
                    background: linear-gradient(135deg, #f0f5fb, #e5edf8);
                    border-top-left-radius: 20px;
                    border-top-right-radius: 20px;
                }
                .test-subject-input {
                    width: 100%;
                    border: 1px solid #cbd5e1;
                    border-radius: 10px;
                    padding: 10px 12px;
                    font-size: 14px;
                    color: #0f172a;
                    background: #fff;
                    transition: all .18s ease;
                }
                .test-subject-input:focus {
                    outline: none;
                    border-color: #3b82f6;
                    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.14);
                }
                .test-subject-label {
                    display: block;
                    margin-bottom: 6px;
                    font-size: 12px;
                    font-weight: 700;
                    color: #475569;
                    text-transform: uppercase;
                    letter-spacing: .04em;
                }
                .test-btn {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    gap: 8px;
                    border-radius: 10px;
                    padding: 10px 16px;
                    font-size: 13px;
                    font-weight: 700;
                    transition: all .18s ease;
                    border: none;
                    cursor: pointer;
                }
                .test-btn:hover {
                    transform: translateY(-1px);
                }
                .test-btn-primary {
                    background: linear-gradient(135deg, #2b5ea7, #3b7ddb);
                    color: #fff;
                    box-shadow: 0 8px 20px rgba(43, 94, 167, 0.22);
                }
                .test-btn-primary:hover {
                    background: linear-gradient(135deg, #234f92, #2b5ea7);
                }
                .test-btn-green {
                    background: linear-gradient(135deg, #059669, #10b981);
                    color: #fff;
                    box-shadow: 0 8px 20px rgba(5, 150, 105, 0.22);
                }
                .test-btn-green:hover {
                    background: linear-gradient(135deg, #047857, #059669);
                }
                .test-btn-red {
                    background: linear-gradient(135deg, #dc2626, #ef4444);
                    color: #fff;
                    box-shadow: 0 8px 20px rgba(220, 38, 38, 0.18);
                }
                .test-btn-red:hover {
                    background: linear-gradient(135deg, #b91c1c, #dc2626);
                }
                .test-btn-light {
                    background: #fff;
                    color: #334155;
                    border: 1px solid #cbd5e1;
                }
                .test-btn-light:hover {
                    background: #f8fafc;
                }
                .test-subject-table thead tr {
                    background: linear-gradient(135deg, #e8edf5, #dbe4ef, #d1d9e6);
                }
                .test-subject-table th {
                    font-size: 11px;
                    letter-spacing: .05em;
                    text-transform: uppercase;
                    color: #475569;
                    font-weight: 800;
                }
                .test-subject-table td {
                    vertical-align: top;
                }
                .lesson-row:hover {
                    background: #f8fbff;
                }
                .group-journal-modal {
                    position: fixed;
                    inset: 0;
                    z-index: 70;
                    background: rgba(15, 23, 42, 0.45);
                    backdrop-filter: blur(4px);
                    padding: 24px;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .group-journal-panel {
                    width: min(1400px, 100%);
                    max-height: calc(100vh - 48px);
                    background: #fff;
                    border-radius: 24px;
                    overflow: hidden;
                    box-shadow: 0 30px 80px rgba(15, 23, 42, 0.28);
                    border: 1px solid #dbe4ef;
                    display: flex;
                    flex-direction: column;
                }
                .group-journal-head {
                    padding: 20px 24px;
                    background: linear-gradient(135deg, #eef4ff, #dbeafe);
                    border-bottom: 1px solid #dbe4ef;
                }
                .group-journal-table thead tr {
                    background: linear-gradient(135deg, #e8edf5, #dbe4ef, #d1d9e6);
                }
                .group-journal-table th {
                    position: sticky;
                    top: 0;
                    z-index: 2;
                    font-size: 11px;
                    letter-spacing: .05em;
                    text-transform: uppercase;
                    color: #475569;
                    font-weight: 800;
                    white-space: nowrap;
                }
                .group-journal-cell-input {
                    width: 62px;
                    border: 1px solid #cbd5e1;
                    border-radius: 9px;
                    padding: 8px 6px;
                    text-align: center;
                    font-size: 13px;
                    font-weight: 700;
                    color: #0f172a;
                    background: #fff;
                }
                .group-journal-cell-input:focus {
                    outline: none;
                    border-color: #3b82f6;
                    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.14);
                }
            </style>

            @if(session('success'))
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-emerald-700 shadow-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-red-700 shadow-sm">
                    <div class="font-semibold mb-2">Formada xatolik bor.</div>
                    <ul class="list-disc pl-5 space-y-1 text-sm">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="test-subject-hero rounded-[24px] p-6 lg:p-7">
                <div class="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                    <div class="space-y-4">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="test-subject-chip blue">{{ $testSubject->faculty_name ?: 'Fakultet tanlanmagan' }}</span>
                            <span class="test-subject-chip green">{{ $testSubject->specialty_name ?: 'Yo‘nalish tanlanmagan' }}</span>
                            <span class="test-subject-chip orange">{{ $testSubject->level_name ?: 'Kurs tanlanmagan' }}</span>
                            <span class="test-subject-chip slate">{{ $testSubject->is_active ? 'Faol modul' : 'Nofaol modul' }}</span>
                        </div>

                        <div>
                            <h1 class="text-3xl font-extrabold text-slate-900">{{ $testSubject->name }}</h1>
                            <p class="text-sm text-slate-600 mt-2 max-w-3xl">
                                Shu oynada test fan uchun biriktirilgan guruhlar, dars jadvali va mavzularni to‘liq boshqarishingiz mumkin.
                                Yangi dars qo‘shing, mavjud mavzuni tahrirlang yoki kerak bo‘lsa o‘chirib tashlang.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 mb-[15px]">
                            <div class="test-subject-stat blue">
                                <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">O‘qituvchi</div>
                                <div class="mt-2 text-sm font-semibold text-slate-900">{{ $testSubject->teacher_name ?: 'Biriktirilmagan' }}</div>
                            </div>
                            <div class="test-subject-stat green">
                                <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Guruhlar</div>
                                <div class="mt-2 text-3xl font-extrabold text-slate-900">{{ $groupCount }}</div>
                            </div>
                            <div class="test-subject-stat orange">
                                <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Darslar</div>
                                <div class="mt-2 text-3xl font-extrabold text-slate-900">{{ $lessonCount }}</div>
                            </div>
                            <div class="test-subject-stat blue">
                                <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Faol darslar</div>
                                <div class="mt-2 text-3xl font-extrabold text-slate-900">{{ $activeLessonCount }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 lg:justify-end">
                        <a href="{{ route('admin.test-subjects.index') }}" class="test-btn test-btn-light">
                            Orqaga
                        </a>
                        <form method="POST" action="{{ route('admin.test-subjects.destroy', $testSubject) }}"
                              onsubmit="return confirm('Test fanni o\\'chirishni tasdiqlaysizmi?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="test-btn test-btn-red">
                                Test fanni o‘chirish
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="test-subject-card overflow-hidden">
                <div class="test-subject-card-head">
                    <h2 class="text-lg font-bold text-slate-900">Modul ma’lumotlari va guruhlar</h2>
                    <p class="text-sm text-slate-500 mt-1">Chap tomonda test fan ma’lumotlari, o‘ng tomonda biriktirilgan guruhlar.</p>
                </div>
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-0">
                    <div class="p-5 xl:border-r xl:border-slate-200">
                        <div class="space-y-4">
                            <div>
                                <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Fakultet</div>
                                <div class="mt-1 text-sm font-semibold text-slate-900">{{ $testSubject->faculty_name ?: '-' }}</div>
                            </div>
                            <div>
                                <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Yo‘nalish</div>
                                <div class="mt-1 text-sm font-semibold text-slate-900">{{ $testSubject->specialty_name ?: '-' }}</div>
                            </div>
                            <div>
                                <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Kurs</div>
                                <div class="mt-1 text-sm font-semibold text-slate-900">{{ $testSubject->level_name ?: '-' }}</div>
                            </div>
                            <div>
                                <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">O‘qituvchi</div>
                                <div class="mt-1 text-sm font-semibold text-slate-900">{{ $testSubject->teacher_name ?: '-' }}</div>
                            </div>
                            <div>
                                <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Muddat</div>
                                <div class="mt-1 text-sm font-semibold text-slate-900">{{ $durationText }}</div>
                            </div>
                            <div>
                                <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Holat</div>
                                <div class="mt-1">
                                    <span class="test-subject-chip {{ $testSubject->is_active ? 'green' : 'slate' }}">
                                        {{ $testSubject->is_active ? 'Faol' : 'Nofaol' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="p-5">
                        <div class="flex items-center justify-between gap-3 mb-4">
                            <div>
                                <div class="text-lg font-bold text-slate-900">Biriktirilgan guruhlar</div>
                                <div class="text-sm text-slate-500">Guruh ustiga bossangiz jurnal ko‘rinishidagi modal ochiladi.</div>
                            </div>
                            <span class="test-subject-chip blue">{{ $groupCount }} ta guruh</span>
                        </div>

                        <div class="space-y-3">
                            @forelse($testSubject->groups as $group)
                                <button type="button"
                                        @click="openGroupModal = 'group-{{ $group->id }}'"
                                        class="w-full text-left rounded-2xl border border-slate-200 bg-slate-50/70 px-4 py-4 hover:border-blue-300 hover:bg-blue-50 transition">
                                    <div class="flex items-center justify-between gap-4">
                                        <div class="flex items-center gap-4">
                                            <div class="w-10 h-10 rounded-full flex items-center justify-center text-sm font-extrabold text-white"
                                                 style="background: linear-gradient(135deg, #2b5ea7, #3b82f6);">
                                                {{ $loop->iteration }}
                                            </div>
                                            <div>
                                                <div class="text-base font-bold text-slate-900">{{ $group->group_name }}</div>
                                                <div class="text-xs text-slate-500 mt-1">HEMIS ID: {{ $group->group_hemis_id ?: '-' }}</div>
                                            </div>
                                        </div>
                                        <span class="test-subject-chip green">Jurnalni ochish</span>
                                    </div>
                                </button>
                            @empty
                                <div class="text-sm text-slate-500">Hozircha guruh biriktirilmagan.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 2xl:grid-cols-3 gap-6">
                <div class="test-subject-card 2xl:col-span-1 overflow-hidden">
                    <div class="test-subject-card-head">
                        <h2 class="text-lg font-bold text-slate-900">Yangi dars qo‘shish</h2>
                        <p class="text-sm text-slate-500 mt-1">Sana, vaqt va mavzuni kiriting.</p>
                    </div>
                    <div class="p-5">
                        <form method="POST" action="{{ route('admin.test-subjects.lessons.store', $testSubject) }}" class="space-y-4">
                            @csrf

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="test-subject-label">Sana</label>
                                    <input type="date" name="lesson_date" value="{{ old('lesson_date') }}" class="test-subject-input" required>
                                </div>
                                <div>
                                    <label class="test-subject-label">Tartib raqami</label>
                                    <div class="test-subject-input bg-slate-50 text-slate-500 flex items-center">
                                        Avtomatik: {{ $lessonCount + 1 }}
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="test-subject-label">Boshlanish vaqti</label>
                                    <input type="time" name="starts_at" value="{{ old('starts_at') }}" class="test-subject-input">
                                </div>
                                <div>
                                    <label class="test-subject-label">Tugash vaqti</label>
                                    <input type="time" name="ends_at" value="{{ old('ends_at') }}" class="test-subject-input">
                                </div>
                            </div>

                            <div>
                                <label class="test-subject-label">Mavzu nomi</label>
                                <input type="text" name="topic_title" value="{{ old('topic_title') }}" class="test-subject-input" placeholder="Masalan: 2-mavzu. Nafas tizimi bo‘yicha test" required>
                            </div>

                            <label class="inline-flex items-center gap-3 text-sm text-slate-700">
                                <input type="checkbox" name="is_active" value="1" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500" {{ old('is_active', '1') ? 'checked' : '' }}>
                                Dars faol holatda yaratiladi
                            </label>

                            <button type="submit" class="test-btn test-btn-primary w-full">
                                Dars jadvalini qo‘shish
                            </button>
                        </form>
                    </div>
                </div>

                <div class="test-subject-card 2xl:col-span-2 overflow-hidden">
                    <div class="test-subject-card-head flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 class="text-lg font-bold text-slate-900">Dars jadvali va mavzular</h2>
                            <p class="text-sm text-slate-500 mt-1">Mavjud darslarni shu yerning o‘zida tahrirlashingiz mumkin.</p>
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="test-subject-chip blue">Jami: {{ $lessonCount }}</span>
                            <span class="test-subject-chip green">Faol: {{ $activeLessonCount }}</span>
                        </div>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm test-subject-table">
                            <thead>
                            <tr>
                                <th class="px-4 py-3 text-left">#</th>
                                <th class="px-4 py-3 text-left">Sana</th>
                                <th class="px-4 py-3 text-left">Boshlanish</th>
                                <th class="px-4 py-3 text-left">Tugash</th>
                                <th class="px-4 py-3 text-left">Mavzu</th>
                                <th class="px-4 py-3 text-left">Holat</th>
                                <th class="px-4 py-3 text-right">Amallar</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 bg-white">
                            @forelse($testSubject->lessons as $lesson)
                                <tr class="lesson-row">
                                    <td class="px-4 py-4">
                                        <span class="inline-flex min-w-[24px] text-sm font-bold text-slate-900">{{ $lesson->topic_order }}</span>
                                    </td>
                                    <td class="px-4 py-4">
                                        <input type="date"
                                               form="lesson-update-{{ $lesson->id }}"
                                               name="lesson_date"
                                               value="{{ old('lesson_date', optional($lesson->lesson_date)->format('Y-m-d')) }}"
                                               class="test-subject-input min-w-[150px]"
                                               required>
                                    </td>
                                    <td class="px-4 py-4">
                                        <input type="time"
                                               form="lesson-update-{{ $lesson->id }}"
                                               name="starts_at"
                                               value="{{ old('starts_at', $lesson->starts_at ? substr($lesson->starts_at, 0, 5) : '') }}"
                                               class="test-subject-input min-w-[120px]">
                                    </td>
                                    <td class="px-4 py-4">
                                        <input type="time"
                                               form="lesson-update-{{ $lesson->id }}"
                                               name="ends_at"
                                               value="{{ old('ends_at', $lesson->ends_at ? substr($lesson->ends_at, 0, 5) : '') }}"
                                               class="test-subject-input min-w-[120px]">
                                    </td>
                                    <td class="px-4 py-4 min-w-[280px]">
                                        <input type="text"
                                               form="lesson-update-{{ $lesson->id }}"
                                               name="topic_title"
                                               value="{{ old('topic_title', $lesson->topic_title ?: ($lesson->topic_order . '-mavzu')) }}"
                                               class="test-subject-input"
                                               required>
                                    </td>
                                    <td class="px-4 py-4">
                                        <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                                            <input type="checkbox"
                                                   form="lesson-update-{{ $lesson->id }}"
                                                   name="is_active"
                                                   value="1"
                                                   class="rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                                   {{ $lesson->is_active ? 'checked' : '' }}>
                                            <span class="test-subject-chip {{ $lesson->is_active ? 'green' : 'slate' }}">
                                                {{ $lesson->is_active ? 'Faol' : 'Nofaol' }}
                                            </span>
                                        </label>
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="flex items-center justify-end gap-2">
                                            <form id="lesson-update-{{ $lesson->id }}"
                                                  method="POST"
                                                  action="{{ route('admin.test-subjects.lessons.update', [$testSubject, $lesson]) }}">
                                                @csrf
                                                @method('PUT')
                                            </form>

                                            <button type="submit"
                                                    form="lesson-update-{{ $lesson->id }}"
                                                    class="test-btn test-btn-green !px-4 !py-2">
                                                Saqlash
                                            </button>

                                            <form method="POST"
                                                  action="{{ route('admin.test-subjects.lessons.destroy', [$testSubject, $lesson]) }}"
                                                  onsubmit="return confirm('Ushbu dars jadvalini o\\'chirasizmi?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="test-btn test-btn-red !px-4 !py-2">
                                                    O‘chirish
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-10 text-center text-slate-500">
                                        Hozircha dars jadvali kiritilmagan.
                                    </td>
                                </tr>
                            @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        @foreach($testSubject->groups as $group)
            @php
                $groupStudents = $studentsByGroup->get((string) $group->group_hemis_id, collect());
            @endphp
            <div x-show="openGroupModal === 'group-{{ $group->id }}'"
                 x-cloak
                 class="group-journal-modal"
                 style="display: none;"
                 @keydown.escape.window="openGroupModal = null">
                <div class="group-journal-panel" @click.outside="openGroupModal = null">
                    <div class="group-journal-head">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <div class="text-2xl font-extrabold text-slate-900">{{ $group->group_name }}</div>
                                <div class="text-sm text-slate-600 mt-2">
                                    Talabalar soni: {{ $groupStudents->count() }} ta.
                                    Darslar soni: {{ $lessonCount }} ta.
                                </div>
                            </div>
                            <button type="button"
                                    @click="openGroupModal = null"
                                    class="w-10 h-10 rounded-full bg-white/90 text-slate-700 text-xl font-bold border border-slate-200 hover:bg-white transition">
                                ×
                            </button>
                        </div>
                    </div>

                    <div class="p-5 overflow-auto">
                        <div class="rounded-2xl border border-slate-200 overflow-hidden">
                            <table class="min-w-full text-sm group-journal-table">
                                <thead>
                                <tr>
                                    <th class="px-4 py-3 text-left">#</th>
                                    <th class="px-4 py-3 text-left min-w-[280px]">Talaba</th>
                                    @foreach($testSubject->lessons as $lesson)
                                        <th class="px-3 py-3 text-center min-w-[120px]">
                                            <div>{{ $lesson->topic_order }}-dars</div>
                                            <div class="text-[10px] font-semibold normal-case tracking-normal mt-1 text-slate-500">
                                                {{ optional($lesson->lesson_date)->format('d.m.Y') ?: '-' }}
                                            </div>
                                        </th>
                                    @endforeach
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                @forelse($groupStudents as $student)
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-4 py-4 font-semibold text-slate-900">{{ $loop->iteration }}</td>
                                        <td class="px-4 py-4">
                                            <div class="font-semibold text-slate-900">{{ $student->full_name }}</div>
                                            <div class="text-xs text-slate-500 mt-1">
                                                HEMIS ID: {{ $student->hemis_id }} | ID: {{ $student->student_id_number ?: '-' }}
                                            </div>
                                        </td>
                                        @foreach($testSubject->lessons as $lesson)
                                            <td class="px-3 py-4 text-center">
                                                <input type="number"
                                                       min="0"
                                                       max="100"
                                                       step="1"
                                                       class="group-journal-cell-input"
                                                       placeholder="-">
                                            </td>
                                        @endforeach
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="{{ 2 + max($lessonCount, 1) }}" class="px-4 py-10 text-center text-slate-500">
                                            Bu guruh uchun talabalar topilmadi.
                                        </td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</x-app-layout>
