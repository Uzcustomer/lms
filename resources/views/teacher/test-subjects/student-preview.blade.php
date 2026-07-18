<x-app-layout>
    <div class="py-6">
        <div class="w-full px-4 sm:px-6 lg:px-8 space-y-6">
            <style>
                .tspv-card { background:#fff; border:1px solid #dbe4ef; border-radius:24px; box-shadow:0 12px 30px rgba(15,23,42,.06); overflow:hidden; }
                .tspv-head { padding:18px 22px; background:linear-gradient(135deg,#eff6ff,#dbeafe); border-bottom:1px solid #dbe4ef; }
                .tspv-body { padding:18px 22px; }
                .tspv-chip { display:inline-flex; align-items:center; padding:6px 12px; border-radius:999px; font-size:12px; font-weight:800; border:1px solid transparent; }
                .tspv-chip.blue { background:#eff6ff; color:#1d4ed8; border-color:#bfdbfe; }
                .tspv-chip.green { background:#ecfdf5; color:#15803d; border-color:#bbf7d0; }
                .tspv-chip.orange { background:#fff7ed; color:#c2410c; border-color:#fdba74; }
                .tspv-chip.gray { background:#f8fafc; color:#475569; border-color:#cbd5e1; }
                .tspv-grid { display:grid; gap:14px; }
                .tspv-lesson { border:1px solid #dbe4ef; border-radius:20px; background:#fff; overflow:hidden; }
                .tspv-top { display:flex; align-items:flex-start; justify-content:space-between; gap:14px; padding:16px 18px; border-bottom:1px solid #eef2f7; flex-wrap:wrap; }
                .tspv-title { font-size:18px; font-weight:800; color:#0f172a; }
                .tspv-bottom { padding:16px 18px; display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; }
                .tspv-meta { display:flex; flex-wrap:wrap; gap:10px; }
                .tspv-note { max-width:760px; font-size:14px; line-height:1.6; color:#475569; }
                .tspv-back { display:inline-flex; align-items:center; justify-content:center; border-radius:12px; border:1px solid #cbd5e1; background:#fff; padding:9px 14px; font-size:13px; font-weight:700; color:#334155; }
            </style>

            <div>
                <a href="{{ route('teacher.test-subjects.show', $testSubject) }}" class="tspv-back">Orqaga</a>
            </div>

            <div class="tspv-card">
                <div class="tspv-head">
                    <div class="flex flex-wrap gap-2">
                        @if($testSubject->faculty_name)
                            <span class="tspv-chip blue">{{ $testSubject->faculty_name }}</span>
                        @endif
                        @if($testSubject->specialty_name)
                            <span class="tspv-chip green">{{ $testSubject->specialty_name }}</span>
                        @endif
                        @if($testSubject->level_name)
                            <span class="tspv-chip orange">{{ $testSubject->level_name }}</span>
                        @endif
                        <span class="tspv-chip gray">{{ $testSubject->teacher_name ?: '-' }}</span>
                    </div>
                    <h1 class="mt-3 text-3xl font-extrabold text-slate-900">{{ $testSubject->name }}</h1>
                    <p class="mt-2 text-sm text-slate-600">
                        Bu teacher preview sahifa. Talaba tomonda mavzu testlari qaysi holatda ko'rinishini shu yerda ko'rasiz.
                    </p>
                </div>

                <div class="tspv-body">
                    <div class="mb-5 text-sm text-slate-600">
                        <b>Hozirgi vaqt:</b> {{ $now->format('d.m.Y H:i') }}
                    </div>

                    <div class="tspv-grid">
                        @forelse($lessons as $lesson)
                            <div class="tspv-lesson">
                                <div class="tspv-top">
                                    <div>
                                        <div class="tspv-title">{{ $lesson['topic_title'] }}</div>
                                        <div class="tspv-meta mt-2">
                                            @if($lesson['lesson_date'])
                                                <span class="tspv-chip blue">{{ $lesson['lesson_date'] }}</span>
                                            @endif
                                            @if($lesson['lesson_time'])
                                                <span class="tspv-chip gray">{{ $lesson['lesson_time'] }}</span>
                                            @endif
                                            <span class="tspv-chip orange">{{ $lesson['question_count'] }} ta savol</span>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap gap-2">
                                        @if(!$lesson['is_published'])
                                            <span class="tspv-chip gray">Nashr qilinmagan</span>
                                        @elseif($lesson['is_scheduled_now'] && $lesson['is_open'])
                                            <span class="tspv-chip green">Test ochiq</span>
                                        @elseif($lesson['is_scheduled_now'])
                                            <span class="tspv-chip orange">Dars vaqti, test yopiq</span>
                                        @elseif($lesson['is_future'])
                                            <span class="tspv-chip blue">Vaqti kelmagan</span>
                                        @elseif($lesson['is_past'])
                                            <span class="tspv-chip gray">Vaqti o'tgan</span>
                                        @else
                                            <span class="tspv-chip gray">Kutilmoqda</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="tspv-bottom">
                                    <div class="tspv-note">{{ $lesson['status_text'] }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-10 text-center text-slate-500">
                                Bu test fan uchun hali mavzular topilmadi.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
