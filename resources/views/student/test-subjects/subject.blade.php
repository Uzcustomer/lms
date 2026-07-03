<x-student-app-layout>
    <x-slot name="header">
        <h2 class="text-sm font-semibold leading-tight text-gray-800">
            {{ __('Test fan') }}: {{ $testSubject->name }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">
            <style>
                .tsp-card { background:#fff; border:1px solid #dbe4ef; border-radius:24px; box-shadow:0 12px 30px rgba(15,23,42,.06); overflow:hidden; }
                .tsp-head { padding:18px 20px; background:linear-gradient(135deg,#eff6ff,#dbeafe); border-bottom:1px solid #dbe4ef; }
                .tsp-body { padding:18px 20px; }
                .tsp-chip { display:inline-flex; align-items:center; padding:6px 12px; border-radius:999px; font-size:12px; font-weight:800; border:1px solid transparent; }
                .tsp-chip.blue { background:#eff6ff; color:#1d4ed8; border-color:#bfdbfe; }
                .tsp-chip.green { background:#ecfdf5; color:#15803d; border-color:#bbf7d0; }
                .tsp-chip.orange { background:#fff7ed; color:#c2410c; border-color:#fdba74; }
                .tsp-chip.gray { background:#f8fafc; color:#475569; border-color:#cbd5e1; }
                .tsp-hero { display:flex; align-items:flex-start; justify-content:space-between; gap:16px; flex-wrap:wrap; }
                .tsp-stat-grid { display:flex; gap:12px; flex-wrap:wrap; }
                .tsp-stat { flex:1 1 180px; min-width:180px; border:1px solid #dbe4ef; border-radius:18px; background:#fff; padding:14px 16px; }
                .tsp-stat-label { font-size:11px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; color:#64748b; }
                .tsp-stat-value { margin-top:8px; font-size:26px; font-weight:800; color:#0f172a; }
                .tsp-list { display:grid; gap:14px; }
                .tsp-lesson { border:1px solid #dbe4ef; border-radius:20px; background:#fff; overflow:hidden; }
                .tsp-lesson-top { display:flex; align-items:flex-start; justify-content:space-between; gap:12px; padding:16px 18px; border-bottom:1px solid #eef2f7; flex-wrap:wrap; }
                .tsp-lesson-title { font-size:18px; font-weight:800; color:#0f172a; }
                .tsp-lesson-body { padding:16px 18px; display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; }
                .tsp-meta { display:flex; gap:10px; flex-wrap:wrap; }
                .tsp-btn { display:inline-flex; align-items:center; justify-content:center; border-radius:14px; padding:11px 16px; font-size:14px; font-weight:800; }
                .tsp-btn.primary { background:linear-gradient(135deg,#059669,#10b981); color:#fff; box-shadow:0 10px 24px rgba(5,150,105,.18); }
                .tsp-btn.secondary { background:#fff; color:#334155; border:1px solid #cbd5e1; }
                .tsp-note { font-size:13px; color:#64748b; }
                @media (max-width: 768px) {
                    .tsp-head, .tsp-body, .tsp-lesson-top, .tsp-lesson-body { padding:14px; }
                    .tsp-stat { min-width:calc(50% - 6px); }
                    .tsp-btn { width:100%; }
                }
            </style>

            <div>
                <a href="{{ route('student.test-subjects.index') }}" class="tsp-btn secondary" style="width:auto;">Orqaga</a>
            </div>

            <div class="tsp-card">
                <div class="tsp-head">
                    <div class="tsp-hero">
                        <div>
                            <div class="flex flex-wrap gap-2">
                                @if($testSubject->faculty_name)
                                    <span class="tsp-chip blue">{{ $testSubject->faculty_name }}</span>
                                @endif
                                @if($testSubject->specialty_name)
                                    <span class="tsp-chip green">{{ $testSubject->specialty_name }}</span>
                                @endif
                                @if($testSubject->level_name)
                                    <span class="tsp-chip orange">{{ $testSubject->level_name }}</span>
                                @endif
                            </div>
                            <h1 class="mt-3 text-3xl font-bold text-slate-900">{{ $testSubject->name }}</h1>
                            <p class="mt-2 text-sm text-slate-600">Barcha mavzu testlari shu sahifada yuritiladi. Faqat sana-soat va ochiq holat mos bo'lgan testni ishlay olasiz.</p>
                        </div>
                        <div class="tsp-note">
                            <div><b>O'qituvchi:</b> {{ $testSubject->teacher_name ?: '-' }}</div>
                            <div class="mt-1"><b>Muddat:</b> {{ optional($testSubject->starts_on)->format('d.m.Y') ?: '--.--.----' }} - {{ optional($testSubject->ends_on)->format('d.m.Y') ?: '--.--.----' }}</div>
                        </div>
                    </div>
                </div>
                <div class="tsp-body">
                    <div class="tsp-stat-grid">
                        <div class="tsp-stat">
                            <div class="tsp-stat-label">Jami mavzu</div>
                            <div class="tsp-stat-value">{{ $lessons->count() }}</div>
                        </div>
                        <div class="tsp-stat">
                            <div class="tsp-stat-label">Hozir ochiq</div>
                            <div class="tsp-stat-value">{{ $lessons->where('is_scheduled_now', true)->where('is_open', true)->where('is_published', true)->count() }}</div>
                        </div>
                        <div class="tsp-stat">
                            <div class="tsp-stat-label">Yakunlangan</div>
                            <div class="tsp-stat-value">{{ $lessons->where('is_submitted', true)->count() }}</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tsp-list">
                @foreach($lessons as $lesson)
                    <div class="tsp-lesson">
                        <div class="tsp-lesson-top">
                            <div>
                                <div class="tsp-lesson-title">{{ $lesson['topic_title'] }}</div>
                                <div class="mt-2 tsp-meta">
                                    @if($lesson['lesson_date'])
                                        <span class="tsp-chip blue">{{ $lesson['lesson_date'] }}</span>
                                    @endif
                                    @if($lesson['lesson_time'])
                                        <span class="tsp-chip gray">{{ $lesson['lesson_time'] }}</span>
                                    @endif
                                    <span class="tsp-chip orange">{{ $lesson['question_count'] }} ta savol</span>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                @if($lesson['is_submitted'])
                                    <span class="tsp-chip {{ $lesson['attempt_is_passed'] ? 'green' : 'orange' }}">
                                        {{ $lesson['attempt_is_passed'] ? 'Yakunlangan' : 'Topshirilgan' }}
                                    </span>
                                @elseif($lesson['is_scheduled_now'] && $lesson['is_open'] && $lesson['is_published'])
                                    <span class="tsp-chip green">Hozir ochiq</span>
                                @elseif($lesson['is_future'])
                                    <span class="tsp-chip blue">Vaqti kelmagan</span>
                                @elseif($lesson['is_past'])
                                    <span class="tsp-chip gray">Vaqti o'tgan</span>
                                @elseif(!$lesson['is_published'])
                                    <span class="tsp-chip gray">Nashr qilinmagan</span>
                                @else
                                    <span class="tsp-chip gray">Yopiq</span>
                                @endif
                            </div>
                        </div>

                        <div class="tsp-lesson-body">
                            <div class="tsp-note">
                                @if($lesson['is_submitted'])
                                    Natija: <b>{{ rtrim(rtrim((string) $lesson['attempt_percent'], '0'), '.') }}%</b>
                                @elseif($lesson['is_scheduled_now'])
                                    Test faqat belgilangan vaqt oralig'ida ishlanadi.
                                @else
                                    Mavzu testi sana va dars soatiga qarab ochiladi.
                                @endif
                            </div>

                            <div>
                                @if($lesson['test_route'] && $lesson['is_submitted'])
                                    <a href="{{ $lesson['test_route'] }}" class="tsp-btn secondary">Natijani ko'rish</a>
                                @elseif($lesson['test_route'] && $lesson['is_open'] && $lesson['is_published'] && $lesson['is_scheduled_now'] && !$lesson['is_submitted'])
                                    <a href="{{ $lesson['test_route'] }}" class="tsp-btn primary">Testni boshlash</a>
                                @else
                                    <span class="tsp-btn secondary" style="opacity:.7; cursor:default;">Kutilmoqda</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</x-student-app-layout>
