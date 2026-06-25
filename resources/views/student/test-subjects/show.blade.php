<x-student-app-layout>
    <x-slot name="header">
        <h2 class="text-sm font-semibold leading-tight text-gray-800">
            {{ __('Test fan') }}: {{ $testSubject->name }}
        </h2>
    </x-slot>

    @php
        $submitted = $attempt->status === 'submitted';
        $showDetailedResult = (bool) $lessonTest->show_result_after_submit;
        $passPercent = (int) ($lessonTest->pass_percent ?? 0);
    @endphp

    <div class="py-6">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-5">
            <style>
                .st-card { background:#fff; border:1px solid #dbe4ef; border-radius:22px; box-shadow:0 10px 28px rgba(15,23,42,.06); overflow:hidden; }
                .st-head { padding:16px 20px; background:linear-gradient(135deg,#eff6ff,#dbeafe); border-bottom:1px solid #dbe4ef; }
                .st-chip { display:inline-flex; align-items:center; padding:6px 12px; border-radius:999px; font-size:12px; font-weight:800; border:1px solid transparent; }
                .st-chip.blue { background:#eff6ff; color:#1d4ed8; border-color:#bfdbfe; }
                .st-chip.green { background:#ecfdf5; color:#15803d; border-color:#bbf7d0; }
                .st-chip.orange { background:#fff7ed; color:#c2410c; border-color:#fdba74; }
                .st-chip.red { background:#fef2f2; color:#dc2626; border-color:#fecaca; }
                .st-stat-wrap { display:flex; gap:12px; flex-wrap:wrap; }
                .st-stat { flex:1 1 180px; border:1px solid #dbe4ef; border-radius:16px; padding:14px 16px; background:#fff; }
                .st-label { font-size:11px; font-weight:800; letter-spacing:.08em; text-transform:uppercase; color:#64748b; }
                .st-value { margin-top:8px; font-size:26px; font-weight:800; color:#0f172a; }
                .st-body { padding:18px 20px; }
                .st-question { border:1px solid #dbe4ef; border-radius:18px; background:#fff; overflow:hidden; }
                .st-question-head { padding:14px 18px; background:#f8fafc; border-bottom:1px solid #e2e8f0; }
                .st-question-body { padding:16px 18px; }
                .st-option { display:flex; gap:10px; align-items:flex-start; padding:12px 14px; border:1px solid #dbe4ef; border-radius:14px; transition:.15s ease; }
                .st-option:hover { border-color:#93c5fd; background:#f8fbff; }
                .st-option + .st-option { margin-top:10px; }
                .st-answer-input { width:100%; border:1px solid #cbd5e1; border-radius:12px; padding:10px 12px; }
                .st-answer-input:focus { outline:none; border-color:#3b82f6; box-shadow:0 0 0 3px rgba(59,130,246,.12); }
                .st-submit { display:inline-flex; align-items:center; justify-content:center; gap:8px; border-radius:12px; padding:12px 18px; font-size:14px; font-weight:800; background:linear-gradient(135deg,#059669,#10b981); color:#fff; box-shadow:0 10px 24px rgba(5,150,105,.18); }
                .st-muted { color:#64748b; font-size:13px; }
                .st-result-box { border:1px solid #dbe4ef; border-radius:18px; background:linear-gradient(135deg,#f8fafc,#ffffff); padding:18px; }
                @media (max-width: 768px) {
                    .st-value { font-size:22px; }
                }
            </style>

            @if(session('success'))
                <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-emerald-700 shadow-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-red-700 shadow-sm">
                    <ul class="list-disc pl-5 space-y-1 text-sm">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="st-card">
                <div class="st-head">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div class="space-y-3">
                            <div class="flex flex-wrap gap-2">
                                <span class="st-chip blue">{{ $testSubject->name }}</span>
                                <span class="st-chip green">{{ $lesson->topic_order }}-mavzu</span>
                                <span class="st-chip orange">{{ optional($lesson->lesson_date)->format('d.m.Y') }}</span>
                                @if($submitted)
                                    <span class="st-chip {{ $attempt->is_passed ? 'green' : 'red' }}">
                                        {{ $attempt->is_passed ? 'O‘tdingiz' : 'Yiqildingiz' }}
                                    </span>
                                @endif
                            </div>
                            <div>
                                <h1 class="text-2xl font-extrabold text-slate-900">{{ $lesson->topic_title ?: ($lesson->topic_order . '-mavzu') }}</h1>
                                <p class="mt-1 text-sm text-slate-600">{{ $lessonTest->title }}</p>
                            </div>
                        </div>
                        <a href="{{ route('student.subjects') }}" class="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700">Orqaga</a>
                    </div>
                </div>

                <div class="st-body">
                    <div class="st-stat-wrap">
                        <div class="st-stat">
                            <div class="st-label">Savollar</div>
                            <div class="st-value">{{ $questions->count() }}</div>
                        </div>
                        <div class="st-stat">
                            <div class="st-label">Davomiylik</div>
                            <div class="st-value">{{ $lessonTest->duration_minutes }} min</div>
                        </div>
                        <div class="st-stat">
                            <div class="st-label">O‘tish foizi</div>
                            <div class="st-value">{{ $passPercent }}%</div>
                        </div>
                        <div class="st-stat">
                            <div class="st-label">{{ $submitted ? 'Natija' : 'Qolgan vaqt' }}</div>
                            <div class="st-value" id="test-timer">
                                {{ $submitted ? ($attempt->percent . '%') : gmdate('H:i:s', $remainingSeconds) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @if($submitted)
                <div class="st-result-box">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <div class="st-label">To‘plagan ball</div>
                            <div class="text-2xl font-extrabold text-slate-900 mt-1">{{ rtrim(rtrim((string) $attempt->score, '0'), '.') }} / {{ $attempt->total_points }}</div>
                        </div>
                        <div>
                            <div class="st-label">Foiz</div>
                            <div class="text-2xl font-extrabold text-slate-900 mt-1">{{ $attempt->percent }}%</div>
                        </div>
                        <div>
                            <div class="st-label">Javoblar</div>
                            <div class="text-2xl font-extrabold text-slate-900 mt-1">{{ $attempt->answers_count }}</div>
                        </div>
                        <div>
                            <div class="st-label">Holat</div>
                            <div class="text-lg font-bold mt-2 {{ $attempt->is_passed ? 'text-emerald-600' : 'text-red-600' }}">
                                {{ $attempt->is_passed ? 'Muvaffaqiyatli' : 'Qayta ishlash kerak' }}
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            @if(!$submitted)
                <form method="POST" action="{{ route('student.test-subjects.tests.submit', [$testSubject, $lesson]) }}" class="space-y-4" onsubmit="return confirm('Testni yakunlab yuborasizmi?')">
                    @csrf
                    @foreach($questions as $index => $question)
                        <div class="st-question">
                            <div class="st-question-head flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                                <div class="font-bold text-slate-900">{{ $index + 1 }}. {{ $question->prompt }}</div>
                                <div class="st-chip blue">{{ $question->points }} ball</div>
                            </div>
                            <div class="st-question-body">
                                @if($question->helper_text)
                                    <div class="st-muted mb-3">{{ $question->helper_text }}</div>
                                @endif

                                @if($question->type === 'single_choice')
                                    @foreach($question->options as $optionIndex => $option)
                                        <label class="st-option">
                                            <input type="radio" name="answers[{{ $question->id }}][selected_option_id]" value="{{ $option->id }}" class="mt-1 text-blue-600 focus:ring-blue-500">
                                            <div class="flex-1">
                                                <div class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">{{ chr(65 + $optionIndex) }})</div>
                                                <div class="text-sm font-medium text-slate-800 mt-1">{{ $option->option_text }}</div>
                                            </div>
                                        </label>
                                    @endforeach
                                @else
                                    <input type="text" name="answers[{{ $question->id }}][answer_text]" class="st-answer-input" placeholder="Javobingizni kiriting">
                                @endif
                            </div>
                        </div>
                    @endforeach

                    <div class="flex justify-end">
                        <button type="submit" class="st-submit">Testni yakunlash</button>
                    </div>
                </form>
            @elseif($showDetailedResult)
                <div class="space-y-4">
                    @foreach($questions as $index => $question)
                        @php
                            $answer = $attempt->answers->firstWhere('question_id', $question->id);
                        @endphp
                        <div class="st-question">
                            <div class="st-question-head flex flex-col gap-2 lg:flex-row lg:items-center lg:justify-between">
                                <div class="font-bold text-slate-900">{{ $index + 1 }}. {{ $question->prompt }}</div>
                                <div class="st-chip {{ $answer?->is_correct ? 'green' : 'red' }}">
                                    {{ $answer?->is_correct ? 'To‘g‘ri' : 'Noto‘g‘ri' }}
                                </div>
                            </div>
                            <div class="st-question-body space-y-3">
                                @if($question->type === 'single_choice')
                                    @foreach($question->options as $optionIndex => $option)
                                        @php
                                            $isSelected = (int) ($answer?->selected_option_id) === (int) $option->id;
                                        @endphp
                                        <div class="st-option" style="{{ $option->is_correct ? 'border-color:#86efac;background:#f0fdf4;' : ($isSelected ? 'border-color:#fca5a5;background:#fef2f2;' : '') }}">
                                            <div class="mt-1 h-4 w-4 rounded-full border {{ $isSelected ? 'bg-blue-500 border-blue-500' : 'border-slate-300' }}"></div>
                                            <div class="flex-1">
                                                <div class="text-xs font-bold uppercase tracking-[0.08em] text-slate-400">{{ chr(65 + $optionIndex) }})</div>
                                                <div class="text-sm font-medium text-slate-800 mt-1">{{ $option->option_text }}</div>
                                            </div>
                                            @if($option->is_correct)
                                                <div class="text-xs font-bold text-emerald-600">To‘g‘ri javob</div>
                                            @elseif($isSelected)
                                                <div class="text-xs font-bold text-red-600">Siz tanlagansiz</div>
                                            @endif
                                        </div>
                                    @endforeach
                                @else
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                        <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                            <div class="st-label">Sizning javobingiz</div>
                                            <div class="mt-2 text-sm font-semibold text-slate-800">{{ $answer?->answer_text ?: 'Javob berilmagan' }}</div>
                                        </div>
                                        <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                                            <div class="st-label">To‘g‘ri javob</div>
                                            <div class="mt-2 text-sm font-semibold text-emerald-700">{{ $question->correct_answer_text }}</div>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @if(!$submitted)
        <script>
            (function () {
                let seconds = {{ max(0, (int) $remainingSeconds) }};
                const el = document.getElementById('test-timer');
                if (!el) return;

                const pad = (n) => String(n).padStart(2, '0');
                const render = () => {
                    const h = Math.floor(seconds / 3600);
                    const m = Math.floor((seconds % 3600) / 60);
                    const s = seconds % 60;
                    el.textContent = `${pad(h)}:${pad(m)}:${pad(s)}`;
                };

                render();
                const timer = setInterval(() => {
                    if (seconds <= 0) {
                        clearInterval(timer);
                        el.textContent = '00:00:00';
                        return;
                    }
                    seconds--;
                    render();
                }, 1000);
            })();
        </script>
    @endif
</x-student-app-layout>
