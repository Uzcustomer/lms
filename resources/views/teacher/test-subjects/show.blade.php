<x-app-layout>
    @php
        $lessonCount = $testSubject->lessons->count();
        $builtTestCount = $builderReady
            ? $testSubject->lessons->filter(fn ($lesson) => $lesson->lessonTest)->count()
            : 0;
        $openTestCount = $builderReady
            ? $testSubject->lessons->filter(fn ($lesson) => $lesson->lessonTest?->is_open)->count()
            : 0;
    @endphp

    <div class="py-6">
        <div class="w-full px-4 sm:px-6 lg:px-8 space-y-6">
            <style>
                .tts-card {
                    background: #fff;
                    border: 1px solid #dbe4ef;
                    border-radius: 24px;
                    box-shadow: 0 10px 28px rgba(15, 23, 42, 0.06);
                }
                .tts-soft {
                    background: linear-gradient(135deg, #f8fbff 0%, #eef4ff 45%, #f7fbff 100%);
                }
                .tts-head {
                    padding: 18px 22px;
                    border-bottom: 1px solid #e8edf5;
                    background: linear-gradient(135deg, #f4f8fc, #e7eef8);
                    border-top-left-radius: 24px;
                    border-top-right-radius: 24px;
                }
                .tts-chip {
                    display: inline-flex;
                    align-items: center;
                    padding: 6px 12px;
                    border-radius: 999px;
                    font-size: 12px;
                    font-weight: 800;
                    border: 1px solid transparent;
                }
                .tts-chip.blue { background:#eff6ff; color:#1d4ed8; border-color:#bfdbfe; }
                .tts-chip.green { background:#ecfdf5; color:#15803d; border-color:#bbf7d0; }
                .tts-chip.orange { background:#fff7ed; color:#c2410c; border-color:#fdba74; }
                .tts-chip.gray { background:#f8fafc; color:#475569; border-color:#cbd5e1; }
                .tts-stat {
                    flex: 1 1 0;
                    min-width: 180px;
                    border-radius: 18px;
                    border: 1px solid #dbe4ef;
                    padding: 18px 20px;
                }
                .tts-stat.blue { background: linear-gradient(135deg, #eff6ff, #dbeafe); }
                .tts-stat.green { background: linear-gradient(135deg, #ecfdf5, #d1fae5); }
                .tts-stat.orange { background: linear-gradient(135deg, #fff7ed, #ffedd5); }
                .tts-info-box {
                    flex: 1 1 0;
                    min-width: 320px;
                    border: 1px solid #dbe4ef;
                    border-radius: 20px;
                    padding: 20px;
                    background: #fff;
                }
                .tts-btn {
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    gap: 8px;
                    border-radius: 12px;
                    padding: 10px 16px;
                    font-size: 13px;
                    font-weight: 800;
                    transition: all .18s ease;
                }
                .tts-btn:hover { transform: translateY(-1px); }
                .tts-btn-primary { background: linear-gradient(135deg, #2b5ea7, #3b7ddb); color:#fff; }
                .tts-btn-light { background:#fff; color:#334155; border:1px solid #cbd5e1; }
                .tts-btn-disabled { background:#f8fafc; color:#94a3b8; border:1px solid #e2e8f0; cursor:not-allowed; }
                .tts-table-wrap { overflow-x: auto; }
                .tts-table { min-width: 100%; width: 100%; border-collapse: separate; border-spacing: 0; }
                .tts-table thead tr { background: linear-gradient(135deg, #e8edf5, #dbe4ef, #d1d9e6); }
                .tts-table th {
                    font-size: 11px;
                    letter-spacing: .05em;
                    text-transform: uppercase;
                    color: #475569;
                    font-weight: 800;
                    white-space: nowrap;
                }
                .tts-table td, .tts-table th { padding: 14px 16px; }
                .tts-table tbody tr:hover { background:#f8fbff; }
                .tts-status {
                    display: inline-flex;
                    align-items: center;
                    padding: 6px 12px;
                    border-radius: 999px;
                    font-size: 11px;
                    font-weight: 800;
                    border: 1px solid transparent;
                }
                .tts-status.red { background:#fef2f2; color:#dc2626; border-color:#fecaca; }
                .tts-status.green { background:#ecfdf5; color:#059669; border-color:#a7f3d0; }
                .tts-status.orange { background:#fff7ed; color:#ea580c; border-color:#fdba74; }
                .tts-status.gray { background:#f8fafc; color:#475569; border-color:#cbd5e1; }
            </style>

            @if(!$builderReady)
                <div class="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-amber-700 shadow-sm">
                    Test builder jadvallari hali serverda yaratilmagan. `php artisan migrate` ishlatilmaguncha bu bo‘lim to‘liq ishlamaydi.
                </div>
            @endif

            <div class="tts-card tts-soft p-6">
                <div class="space-y-4">
                    <div class="flex flex-wrap gap-2">
                        <span class="tts-chip blue">{{ $testSubject->faculty_name ?: 'Fakultet tanlanmagan' }}</span>
                        <span class="tts-chip green">{{ $testSubject->specialty_name ?: 'Yo‘nalish tanlanmagan' }}</span>
                        <span class="tts-chip orange">{{ $testSubject->level_name ?: 'Kurs tanlanmagan' }}</span>
                        <span class="tts-chip gray">{{ $testSubject->teacher_name ?: 'O‘qituvchi biriktirilmagan' }}</span>
                    </div>

                    <div>
                        <h1 class="text-3xl font-extrabold text-slate-900">{{ $testSubject->name }}</h1>
                        <p class="mt-2 max-w-4xl text-sm text-slate-600">
                            Bu oynada sizga biriktirilgan mavzular uchun testlar yaratiladi, savollar boshqariladi va dars paytida testni ochib-yopish mumkin.
                        </p>
                    </div>

                    <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:15px;">
                        <div class="tts-stat blue">
                            <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Jami mavzu</div>
                            <div class="mt-2 text-3xl font-extrabold text-slate-900">{{ $lessonCount }}</div>
                        </div>
                        <div class="tts-stat green">
                            <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Test yaratilgan</div>
                            <div class="mt-2 text-3xl font-extrabold text-slate-900">{{ $builtTestCount }}</div>
                        </div>
                        <div class="tts-stat orange">
                            <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Hozir ochiq</div>
                            <div class="mt-2 text-3xl font-extrabold text-slate-900">{{ $openTestCount }}</div>
                        </div>
                    </div>

                    <div style="margin-top:10px;">
                        <a href="{{ route('teacher.test-subjects.index') }}" class="tts-btn tts-btn-light">Orqaga</a>
                    </div>
                </div>
            </div>

            <div class="tts-card overflow-hidden">
                <div class="tts-head">
                    <h2 class="text-lg font-bold text-slate-900">Fan ma’lumotlari va mavzular</h2>
                    <p class="mt-1 text-sm text-slate-500">Chap tomonda umumiy ma’lumot, o‘ng tomonda esa har bir dars bo‘yicha test builder mavjud.</p>
                </div>

                <div class="p-5" style="display:flex; gap:10px; flex-wrap:wrap;">
                    <div class="tts-info-box">
                        <div class="space-y-4">
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
                            <div>
                                <div class="text-[11px] font-bold uppercase tracking-[0.12em] text-slate-500">Builder holati</div>
                                <div class="mt-2">
                                    <span class="tts-status {{ $builderReady ? 'green' : 'orange' }}">
                                        {{ $builderReady ? 'Tayyor' : 'Migration kutilmoqda' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tts-info-box" style="flex: 2 1 680px;">
                        <div class="flex items-start justify-between gap-3 mb-4">
                            <div>
                                <div class="text-lg font-bold text-slate-900">Mavzular va test builder</div>
                                <div class="text-sm text-slate-500">Har bir dars/mavzu uchun test yaratish yoki mavjud testni boshqarish mumkin.</div>
                            </div>
                        </div>

                        <div class="tts-table-wrap">
                            <table class="tts-table text-sm">
                                <thead>
                                <tr>
                                    <th class="text-left">#</th>
                                    <th class="text-left">Sana</th>
                                    <th class="text-left">Vaqt</th>
                                    <th class="text-left">Mavzu</th>
                                    <th class="text-left">Holat</th>
                                    <th class="text-left">Savollar</th>
                                    <th class="text-right">Amal</th>
                                </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100">
                                @forelse($testSubject->lessons as $lesson)
                                    @php
                                        $lessonTest = $builderReady ? $lesson->lessonTest : null;
                                        $questionCount = ($builderReady && $lessonTest) ? ($lessonTest->questions?->count() ?? 0) : 0;
                                    @endphp
                                    <tr>
                                        <td class="font-bold text-slate-900">{{ $lesson->topic_order }}</td>
                                        <td class="text-slate-700">{{ optional($lesson->lesson_date)->format('d.m.Y') ?: '-' }}</td>
                                        <td class="text-slate-700">
                                            {{ $lesson->starts_at ? substr($lesson->starts_at, 0, 5) : '--:--' }}
                                            -
                                            {{ $lesson->ends_at ? substr($lesson->ends_at, 0, 5) : '--:--' }}
                                        </td>
                                        <td>
                                            <div class="font-semibold text-slate-900">{{ $lesson->topic_title ?: ($lesson->topic_order . '-mavzu') }}</div>
                                        </td>
                                        <td>
                                            @if(!$builderReady)
                                                <span class="tts-status orange">Migration kutilmoqda</span>
                                            @elseif($lessonTest)
                                                <div class="flex flex-wrap gap-2">
                                                    <span class="tts-status {{ $lessonTest->is_published ? 'green' : 'gray' }}">
                                                        {{ $lessonTest->is_published ? 'Nashr qilingan' : 'Draft' }}
                                                    </span>
                                                    <span class="tts-status {{ $lessonTest->is_open ? 'orange' : 'gray' }}">
                                                        {{ $lessonTest->is_open ? 'Hozir ochiq' : 'Yopiq' }}
                                                    </span>
                                                </div>
                                            @else
                                                <span class="tts-status red">Test yaratilmagan</span>
                                            @endif
                                        </td>
                                        <td class="font-semibold text-slate-900">{{ $questionCount }}</td>
                                        <td class="text-right">
                                            @if($builderReady)
                                                <div class="flex items-center justify-end gap-2">
                                                    @if($lessonTest)
                                                        <a href="{{ route('teacher.test-subjects.tests.results', [$testSubject, $lesson]) }}" class="tts-btn tts-btn-light">
                                                            Natijalar
                                                        </a>
                                                    @endif
                                                    <a href="{{ route('teacher.test-subjects.tests.edit', [$testSubject, $lesson]) }}" class="tts-btn tts-btn-primary">
                                                        {{ $lessonTest ? 'Testni boshqarish' : 'Test yaratish' }}
                                                    </a>
                                                </div>
                                            @else
                                                <span class="tts-btn tts-btn-disabled">Builder yopiq</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="py-10 text-center text-slate-500">Mavzular topilmadi.</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
