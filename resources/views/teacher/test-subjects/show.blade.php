<x-app-layout>
    @php
        $lessonCount = $testSubject->lessons->count();
        $builtTestCount = $testSubject->lessons->filter(fn ($lesson) => $lesson->lessonTest)->count();
        $openTestCount = $testSubject->lessons->filter(fn ($lesson) => $lesson->lessonTest?->is_open)->count();
    @endphp

    <div class="py-6">
        <div class="w-full px-4 sm:px-6 lg:px-8 space-y-6">
            <div class="rounded-[24px] border border-slate-200 bg-white p-6 shadow-sm">
                <div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:justify-between">
                    <div class="space-y-4 flex-1">
                        <div class="flex flex-wrap gap-2">
                            <span class="inline-flex items-center rounded-full border border-blue-200 bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">{{ $testSubject->faculty_name ?: 'Fakultet tanlanmagan' }}</span>
                            <span class="inline-flex items-center rounded-full border border-emerald-200 bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-700">{{ $testSubject->specialty_name ?: 'Yo‘nalish tanlanmagan' }}</span>
                            <span class="inline-flex items-center rounded-full border border-orange-200 bg-orange-50 px-3 py-1 text-xs font-bold text-orange-700">{{ $testSubject->level_name ?: 'Kurs tanlanmagan' }}</span>
                        </div>

                        <div>
                            <h1 class="text-3xl font-extrabold text-slate-900">{{ $testSubject->name }}</h1>
                            <p class="mt-2 max-w-4xl text-sm text-slate-600">
                                Bu oynada sizga biriktirilgan mavzular uchun testlarni yaratish, savollarni kiritish,
                                testni nashr qilish va kerak paytda ochib-yopish mumkin.
                            </p>
                        </div>

                        <div class="flex flex-col lg:flex-row" style="gap: 10px;">
                            <div class="flex-1 rounded-2xl border border-blue-100 bg-blue-50 px-5 py-4">
                                <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Jami mavzu</div>
                                <div class="mt-2 text-3xl font-extrabold text-slate-900">{{ $lessonCount }}</div>
                            </div>
                            <div class="flex-1 rounded-2xl border border-emerald-100 bg-emerald-50 px-5 py-4">
                                <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Test yaratilgan</div>
                                <div class="mt-2 text-3xl font-extrabold text-slate-900">{{ $builtTestCount }}</div>
                            </div>
                            <div class="flex-1 rounded-2xl border border-orange-100 bg-orange-50 px-5 py-4">
                                <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Hozir ochiq</div>
                                <div class="mt-2 text-3xl font-extrabold text-slate-900">{{ $openTestCount }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ route('teacher.test-subjects.index') }}"
                           class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                            Orqaga
                        </a>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <div class="rounded-[22px] border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-bold text-slate-900">Fan ma’lumotlari</h2>
                    <div class="mt-5 space-y-4">
                        <div>
                            <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">O‘qituvchi</div>
                            <div class="mt-1 text-sm font-semibold text-slate-900">{{ $testSubject->teacher_name ?: '-' }}</div>
                        </div>
                        <div>
                            <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Muddat</div>
                            <div class="mt-1 text-sm font-semibold text-slate-900">
                                {{ optional($testSubject->starts_on)->format('d.m.Y') ?: '-' }} - {{ optional($testSubject->ends_on)->format('d.m.Y') ?: '-' }}
                            </div>
                        </div>
                        <div>
                            <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Guruhlar</div>
                            <div class="mt-1 text-sm font-semibold text-slate-900">{{ $testSubject->groups->pluck('group_name')->implode(', ') ?: '-' }}</div>
                        </div>
                    </div>
                </div>

                <div class="xl:col-span-2 rounded-[22px] border border-slate-200 bg-white shadow-sm overflow-hidden">
                    <div class="px-6 py-5 border-b border-slate-100 bg-slate-50/80">
                        <h2 class="text-lg font-bold text-slate-900">Mavzular va test builder</h2>
                        <p class="mt-1 text-sm text-slate-500">Har bir dars/mavzu uchun test yaratish yoki mavjud testni tahrirlash mumkin.</p>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-slate-100 text-slate-600 uppercase text-xs tracking-wide">
                            <tr>
                                <th class="px-4 py-3 text-left">#</th>
                                <th class="px-4 py-3 text-left">Sana</th>
                                <th class="px-4 py-3 text-left">Vaqt</th>
                                <th class="px-4 py-3 text-left">Mavzu</th>
                                <th class="px-4 py-3 text-left">Holat</th>
                                <th class="px-4 py-3 text-left">Savollar</th>
                                <th class="px-4 py-3 text-right">Amal</th>
                            </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                            @forelse($testSubject->lessons as $lesson)
                                @php
                                    $lessonTest = $lesson->lessonTest;
                                    $questionCount = $lessonTest?->questions?->count() ?? 0;
                                @endphp
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-4 font-bold text-slate-900">{{ $lesson->topic_order }}</td>
                                    <td class="px-4 py-4 text-slate-700">{{ optional($lesson->lesson_date)->format('d.m.Y') ?: '-' }}</td>
                                    <td class="px-4 py-4 text-slate-700">
                                        {{ $lesson->starts_at ? substr($lesson->starts_at, 0, 5) : '--:--' }}
                                        -
                                        {{ $lesson->ends_at ? substr($lesson->ends_at, 0, 5) : '--:--' }}
                                    </td>
                                    <td class="px-4 py-4">
                                        <div class="font-semibold text-slate-900">{{ $lesson->topic_title ?: ($lesson->topic_order . '-mavzu') }}</div>
                                    </td>
                                    <td class="px-4 py-4">
                                        @if($lessonTest)
                                            <div class="flex flex-wrap gap-2">
                                                <span class="inline-flex items-center rounded-full border px-3 py-1 text-[11px] font-bold {{ $lessonTest->is_published ? 'border-emerald-200 bg-emerald-50 text-emerald-700' : 'border-slate-200 bg-slate-50 text-slate-600' }}">
                                                    {{ $lessonTest->is_published ? 'Nashr qilingan' : 'Draft' }}
                                                </span>
                                                <span class="inline-flex items-center rounded-full border px-3 py-1 text-[11px] font-bold {{ $lessonTest->is_open ? 'border-orange-200 bg-orange-50 text-orange-700' : 'border-slate-200 bg-slate-50 text-slate-600' }}">
                                                    {{ $lessonTest->is_open ? 'Hozir ochiq' : 'Yopiq' }}
                                                </span>
                                            </div>
                                        @else
                                            <span class="inline-flex items-center rounded-full border border-red-200 bg-red-50 px-3 py-1 text-[11px] font-bold text-red-700">
                                                Test yaratilmagan
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-4 font-semibold text-slate-900">{{ $questionCount }}</td>
                                    <td class="px-4 py-4 text-right">
                                        <a href="{{ route('teacher.test-subjects.tests.edit', [$testSubject, $lesson]) }}"
                                           class="inline-flex items-center rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700">
                                            {{ $lessonTest ? 'Testni boshqarish' : 'Test yaratish' }}
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-10 text-center text-slate-500">Mavzular topilmadi.</td>
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
