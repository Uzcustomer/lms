<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $testSubject->name }} - test kiosk</title>
    <style>
        body{margin:0;font-family:Arial,sans-serif;background:#f8fafc;color:#0f172a}
        .wrap{max-width:1100px;margin:0 auto;padding:24px}
        .card{background:#fff;border:1px solid #dbe4ef;border-radius:18px;box-shadow:0 8px 24px rgba(15,23,42,.05);overflow:hidden}
        .head{padding:18px 20px;background:#f8fbff;border-bottom:1px solid #dbe4ef}
        .body{padding:18px 20px}
        .chip{display:inline-flex;align-items:center;padding:6px 12px;border-radius:999px;font-size:12px;font-weight:600;border:1px solid transparent}
        .blue{background:#eff6ff;color:#1d4ed8;border-color:#bfdbfe}.green{background:#ecfdf5;color:#15803d;border-color:#bbf7d0}
        .orange{background:#fff7ed;color:#c2410c;border-color:#fdba74}.red{background:#fef2f2;color:#dc2626;border-color:#fecaca}
        .stat-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}
        .stat{border:1px solid #dbe4ef;border-radius:18px;padding:14px 16px;background:#fff}
        .q{border:1px solid #dbe4ef;border-radius:18px;background:#fff;overflow:hidden}
        .qh{padding:12px 14px;background:#f8fafc;border-bottom:1px solid #e2e8f0}
        .qb{padding:14px}
        .img{width:100%;max-height:320px;object-fit:contain;border-radius:16px;border:1px solid #dbe4ef;background:#f8fafc;margin-bottom:14px}
        .opt{display:flex;gap:10px;align-items:flex-start;padding:10px 12px;border:1px solid #dbe4ef;border-radius:14px}
        .opt + .opt{margin-top:8px}
        .input{width:100%;border:1px solid #cbd5e1;border-radius:12px;padding:10px 12px;box-sizing:border-box}
        .btn{display:inline-flex;align-items:center;justify-content:center;border-radius:12px;padding:12px 18px;font-size:14px;font-weight:600;text-decoration:none;border:none;cursor:pointer}
        .btn-primary{background:#059669;color:#fff}
        .btn-light{background:#fff;color:#334155;border:1px solid #cbd5e1}
        .alert-ok{border:1px solid #bbf7d0;background:#ecfdf5;color:#15803d;border-radius:16px;padding:14px 16px}
        .alert-err{border:1px solid #fecaca;background:#fef2f2;color:#dc2626;border-radius:16px;padding:14px 16px}
        .modal-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.35);display:flex;align-items:center;justify-content:center;padding:20px;z-index:999}
        .modal-card{width:100%;max-width:520px;background:#fff;border-radius:18px;border:1px solid #dbe4ef;box-shadow:0 18px 50px rgba(15,23,42,.18);overflow:hidden}
        .modal-head{padding:18px 20px;background:#f8fbff;border-bottom:1px solid #dbe4ef}
        .modal-body{padding:20px}
        @media (max-width:768px){.stat-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
    </style>
</head>
<body>
<div class="wrap">
    @php
        $submitted = $attempt->status === 'submitted';
        $showDetailedResult = (bool) $lessonTest->show_result_after_submit;
        $passPercent = (int) ($lessonTest->pass_percent ?? 0);
        $langLabels = ['uz' => "O'zbek", 'ru' => 'Русский', 'en' => 'English'];
    @endphp

    @php
        $submissionResult = session('submission_result');
    @endphp

    @if(session('success'))
        <div class="alert-ok" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert-err" style="margin-bottom:16px;">
            <ul style="margin:0;padding-left:20px;">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div style="margin-bottom:16px;">
        <a href="{{ route('student.test-kiosk.student', $student->student_id_number) }}" class="btn btn-light">Orqaga</a>
    </div>

    <div class="card">
        <div class="head">
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <span class="chip blue">{{ $student->full_name }}</span>
                <span class="chip green">{{ $lesson->topic_order }}-mavzu</span>
                <span class="chip orange">{{ optional($lesson->lesson_date)->format('d.m.Y') }}</span>
                <span class="chip blue">{{ $langLabels[$language] ?? "O'zbek" }}</span>
                @if($submitted)
                    <span class="chip {{ $attempt->is_passed ? 'green' : 'red' }}">{{ $attempt->is_passed ? 'O‘tdingiz' : 'Yiqildingiz' }}</span>
                @endif
            </div>
            <h1 style="margin:14px 0 0;font-size:28px;line-height:1.2;font-weight:600;">{{ $lesson->topic_title ?: ($lesson->topic_order . '-mavzu') }}</h1>
            <p style="margin:8px 0 0;color:#475569;">{{ $lessonTest->title }}</p>
        </div>
        <div class="body">
            <div style="margin-bottom:16px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                <div style="font-size:12px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#64748b;margin-right:6px;">Test tili</div>
                @foreach($langLabels as $code => $label)
                    <a href="{{ route('student.test-kiosk.tests.show', [$student->student_id_number, $testSubject, $lesson, 'lang' => $code]) }}" class="chip {{ $language === $code ? 'green' : 'blue' }}" style="text-decoration:none;">{{ $label }}</a>
                @endforeach
            </div>

            <div class="stat-grid">
                <div class="stat"><div style="font-size:11px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#64748b;">Savollar</div><div style="margin-top:6px;font-size:22px;font-weight:600;">{{ $questions->count() }}</div></div>
                <div class="stat"><div style="font-size:11px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#64748b;">Davomiylik</div><div style="margin-top:6px;font-size:22px;font-weight:600;">{{ $lessonTest->duration_minutes }} min</div></div>
                <div class="stat"><div style="font-size:11px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#64748b;">O'tish foizi</div><div style="margin-top:6px;font-size:22px;font-weight:600;">{{ $passPercent }}%</div></div>
                <div class="stat"><div style="font-size:11px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#64748b;">{{ $submitted ? 'Natija' : 'Qolgan vaqt' }}</div><div style="margin-top:6px;font-size:22px;font-weight:600;" id="test-timer">{{ $submitted ? ($attempt->percent . '%') : gmdate('H:i:s', $remainingSeconds) }}</div></div>
            </div>
        </div>
    </div>

    @if($submitted)
        <div class="card" style="margin-top:16px;">
            <div class="body">
                <div class="stat-grid">
                    <div class="stat"><div style="font-size:11px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#64748b;">To'plagan ball</div><div style="margin-top:6px;font-size:22px;font-weight:600;">{{ rtrim(rtrim((string) $attempt->score, '0'), '.') }} / {{ $attempt->total_points }}</div></div>
                    <div class="stat"><div style="font-size:11px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#64748b;">Foiz</div><div style="margin-top:6px;font-size:22px;font-weight:600;">{{ $attempt->percent }}%</div></div>
                    <div class="stat"><div style="font-size:11px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#64748b;">Javoblar</div><div style="margin-top:6px;font-size:22px;font-weight:600;">{{ $attempt->answers_count }}</div></div>
                    <div class="stat"><div style="font-size:11px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#64748b;">Holat</div><div style="margin-top:10px;font-size:18px;font-weight:600;color:{{ $attempt->is_passed ? '#059669' : '#dc2626' }};">{{ $attempt->is_passed ? 'Muvaffaqiyatli' : 'Qayta ishlash kerak' }}</div></div>
                </div>
            </div>
        </div>
    @endif

    <div style="height:16px;"></div>

    @if(!$submitted)
        <form id="kiosk-test-form" method="POST" action="{{ route('student.test-kiosk.tests.submit', [$student->student_id_number, $testSubject, $lesson]) }}" onsubmit="return confirm('Testni yakunlab yuborasizmi?')" style="display:grid;gap:14px;">
            @csrf
            <input type="hidden" name="lang" value="{{ $language }}">
            @foreach($questions as $index => $question)
                <div class="q">
                    <div class="qh" style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                        <div style="font-weight:600;">{{ $index + 1 }}. {{ $question->promptFor($language) }}</div>
                        <div class="chip blue">{{ $question->points }} ball</div>
                    </div>
                    <div class="qb">
                        @if($question->imageUrl())
                            <img src="{{ $question->imageUrl() }}" alt="Savol rasmi" class="img">
                        @endif
                        @if($question->helperTextFor($language))
                            <div style="color:#64748b;font-size:13px;margin-bottom:12px;">{{ $question->helperTextFor($language) }}</div>
                        @endif
                        @if($question->type === 'single_choice')
                            @foreach($question->options as $optionIndex => $option)
                                <label class="opt">
                                    <input type="radio" name="answers[{{ $question->id }}][selected_option_id]" value="{{ $option->id }}" style="margin-top:2px;">
                                    <div style="flex:1;">
                                        <div style="font-size:11px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#94a3b8;">{{ chr(65 + $optionIndex) }})</div>
                                        <div style="margin-top:6px;font-size:15px;font-weight:400;">{{ $option->textFor($language) }}</div>
                                    </div>
                                </label>
                            @endforeach
                        @else
                            <input type="text" name="answers[{{ $question->id }}][answer_text]" class="input" placeholder="Javobingizni kiriting">
                        @endif
                    </div>
                </div>
            @endforeach

            <div style="display:flex;justify-content:flex-end;">
                <button type="submit" class="btn btn-primary">Testni yakunlash</button>
            </div>
        </form>
    @elseif($showDetailedResult)
        <div style="display:grid;gap:14px;">
            @foreach($questions as $index => $question)
                @php $answer = $attempt->answers->firstWhere('question_id', $question->id); @endphp
                <div class="q">
                    <div class="qh" style="display:flex;justify-content:space-between;gap:10px;flex-wrap:wrap;">
                        <div style="font-weight:600;">{{ $index + 1 }}. {{ $question->promptFor($language) }}</div>
                        <div class="chip {{ $answer?->is_correct ? 'green' : 'red' }}">{{ $answer?->is_correct ? 'To‘g‘ri' : 'Noto‘g‘ri' }}</div>
                    </div>
                    <div class="qb" style="display:grid;gap:10px;">
                        @if($question->imageUrl())
                            <img src="{{ $question->imageUrl() }}" alt="Savol rasmi" class="img">
                        @endif
                        @if($question->type === 'single_choice')
                            @foreach($question->options as $optionIndex => $option)
                                @php $isSelected = (int) ($answer?->selected_option_id) === (int) $option->id; @endphp
                                <div class="opt" style="{{ $option->is_correct ? 'border-color:#86efac;background:#f0fdf4;' : ($isSelected ? 'border-color:#fca5a5;background:#fef2f2;' : '') }}">
                                    <div style="margin-top:2px;height:14px;width:14px;border-radius:999px;border:1px solid {{ $isSelected ? '#3b82f6' : '#cbd5e1' }};background:{{ $isSelected ? '#3b82f6' : 'transparent' }};"></div>
                                    <div style="flex:1;">
                                        <div style="font-size:11px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#94a3b8;">{{ chr(65 + $optionIndex) }})</div>
                                        <div style="margin-top:6px;font-size:15px;font-weight:400;">{{ $option->textFor($language) }}</div>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div><b>Yozgan javob:</b> {{ $answer?->answer_text ?: '-' }}</div>
                            <div><b>To‘g‘ri javob:</b> {{ $question->correctAnswerFor($language) ?: '-' }}</div>
                        @endif
                        @if($question->correctExplanationFor($language))
                            <div style="padding:12px 14px;border-radius:14px;background:#f8fafc;border:1px solid #dbe4ef;">
                                <b>Izoh:</b> {{ $question->correctExplanationFor($language) }}
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

@if($submissionResult)
<div id="submit-result-modal" class="modal-backdrop">
    <div class="modal-card">
        <div class="modal-head">
            <div style="font-size:22px;font-weight:600;">Test yakunlandi</div>
        </div>
        <div class="modal-body">
            <div style="font-size:16px;line-height:1.6;">{{ $submissionResult['message'] }}</div>
            <div class="stat-grid" style="margin-top:16px;">
                <div class="stat">
                    <div style="font-size:11px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#64748b;">Ball</div>
                    <div style="margin-top:6px;font-size:22px;font-weight:600;">{{ rtrim(rtrim((string) $submissionResult['score'], '0'), '.') }} / {{ $submissionResult['total_points'] }}</div>
                </div>
                <div class="stat">
                    <div style="font-size:11px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:#64748b;">Foiz</div>
                    <div style="margin-top:6px;font-size:22px;font-weight:600;">{{ $submissionResult['percent'] }}%</div>
                </div>
            </div>
            <div style="margin-top:16px;padding:12px 14px;border-radius:12px;background:{{ $submissionResult['is_passed'] ? '#ecfdf5' : '#fef2f2' }};border:1px solid {{ $submissionResult['is_passed'] ? '#bbf7d0' : '#fecaca' }};color:{{ $submissionResult['is_passed'] ? '#15803d' : '#dc2626' }};font-weight:600;">
                {{ $submissionResult['is_passed'] ? "Siz testdan o'tdingiz." : "Siz testdan o'tmadingiz." }}
            </div>
            <div style="margin-top:18px;">
                <button type="button" id="submit-result-close" class="btn btn-primary" style="width:100%;">Yopish</button>
            </div>
        </div>
    </div>
</div>
@endif

@if(!$submitted)
<script>
    (function () {
        var remaining = {{ (int) $remainingSeconds }};
        var el = document.getElementById('test-timer');
        function render() {
            var hrs = String(Math.floor(remaining / 3600)).padStart(2, '0');
            var mins = String(Math.floor((remaining % 3600) / 60)).padStart(2, '0');
            var secs = String(remaining % 60).padStart(2, '0');
            if (el) el.textContent = hrs + ':' + mins + ':' + secs;
            if (remaining <= 0) {
                var form = document.getElementById('kiosk-test-form');
                if (form) {
                    form.submit();
                    return;
                }
                window.location.reload();
                return;
            }
            remaining--;
        }
        render();
        setInterval(render, 1000);
    })();
</script>
@endif

@if($submissionResult)
<script>
    (function () {
        var modal = document.getElementById('submit-result-modal');
        var closeBtn = document.getElementById('submit-result-close');
        var targetUrl = @json(route('student.test-kiosk.index'));

        function closeModal() {
            window.location.href = targetUrl;
        }

        if (closeBtn) {
            closeBtn.addEventListener('click', closeModal);
        }

        if (modal) {
            modal.addEventListener('click', closeModal);
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    })();
</script>
@endif
</body>
</html>
