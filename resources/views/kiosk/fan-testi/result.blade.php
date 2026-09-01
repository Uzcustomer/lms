@extends('kiosk.fan-testi.layout')

@section('title', 'Natija · ' . $test->name)

@section('styles')
        .r-score { padding: 30px 28px; text-align: center; }
        .r-ring {
            display: grid; place-items: center;
            width: 128px; height: 128px; margin: 0 auto 16px; border-radius: 50%;
            font-size: 32px; font-weight: 900;
        }
        .r-ring.ok { background: #ecfdf5; color: #047857; box-shadow: inset 0 0 0 6px #a7f3d0; }
        .r-ring.no { background: #fef2f2; color: #b91c1c; box-shadow: inset 0 0 0 6px #fecaca; }
        .r-verdict { font-size: 19px; font-weight: 900; }
        .r-verdict.ok { color: #047857; }
        .r-verdict.no { color: #b91c1c; }
        .r-sub { margin-top: 6px; color: #7e91ad; font-size: 13px; font-weight: 700; }
        .r-stats {
            display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;
            margin-top: 22px; padding-top: 20px; border-top: 1px solid #eaf0f9;
        }
        .r-stat b { display: block; color: #16263f; font-size: 20px; font-weight: 900; }
        .r-stat span { color: #93a5bf; font-size: 11px; font-weight: 800; }
        .r-list { padding: 0 28px 28px; }
        .r-list h2 { margin: 0 0 12px; color: #21375c; font-size: 14px; font-weight: 900; }
        .r-row {
            display: flex; gap: 11px; margin-bottom: 8px; padding: 12px 14px;
            border: 1px solid #e6eefb; border-radius: 11px; background: #fbfdff;
        }
        .r-mark {
            flex: none; display: grid; place-items: center;
            width: 26px; height: 26px; border-radius: 7px;
            font-size: 14px; font-weight: 900; color: #fff;
        }
        .r-mark.ok { background: #059669; }
        .r-mark.no { background: #dc2626; }
        .r-q { color: #21375c; font-size: 13px; font-weight: 700; line-height: 1.5; }
        .r-a { margin-top: 4px; color: #7e91ad; font-size: 12px; font-weight: 600; }
        .r-a i { font-style: normal; color: #047857; font-weight: 800; }
        @media (max-width: 560px) { .r-stats { grid-template-columns: 1fr; } }
@endsection

@section('content')
    @php
        $percent = (float) $attempt->percent;
        $passed = $attempt->is_passed;
    @endphp

    <div class="k-card">
        <div class="k-head">
            <h1>{{ $test->name }}</h1>
            <p>{{ $attempt->student_name }} @if($attempt->group_name) · {{ $attempt->group_name }} @endif</p>
        </div>

        <div class="r-score">
            <div class="r-ring {{ $passed ? 'ok' : 'no' }}">{{ rtrim(rtrim(number_format($percent, 1, '.', ''), '0'), '.') }}%</div>
            <div class="r-verdict {{ $passed ? 'ok' : 'no' }}">{{ $passed ? 'Test muvaffaqiyatli topshirildi' : 'Test o\'tilmadi' }}</div>
            <div class="r-sub">
                {{ $attempt->correct_count }} / {{ $attempt->questions_count }} ta to'g'ri javob
                @if($attempt->status === 'expired') · vaqt tugadi @endif
            </div>

            <div class="r-stats">
                <div class="r-stat"><b>{{ (int) $attempt->score }} / {{ $attempt->total_points }}</b><span>BALL</span></div>
                <div class="r-stat"><b>{{ $attempt->answers_count }}</b><span>JAVOB BERILDI</span></div>
                <div class="r-stat"><b>{{ $attempt->duration_seconds ? gmdate('i:s', min(5999, $attempt->duration_seconds)) : '—' }}</b><span>SARFLANGAN VAQT</span></div>
            </div>
        </div>

        @if($test->show_result_after_submit && $attempt->answers->isNotEmpty())
            <div class="r-list">
                <h2>Javoblaringiz</h2>
                @foreach($attempt->answers as $answer)
                    <div class="r-row">
                        <span class="r-mark {{ $answer->is_correct ? 'ok' : 'no' }}">{{ $answer->is_correct ? '✓' : '✕' }}</span>
                        <div style="min-width:0">
                            <div class="r-q">{{ $answer->question_index + 1 }}. {{ $answer->question_prompt }}</div>
                            <div class="r-a">
                                Sizning javobingiz:
                                {{ $answer->question_type === 'fill_in_blank'
                                    ? ($answer->answer_text ?: 'javob berilmagan')
                                    : ($answer->selected_option_text ?: 'javob berilmagan') }}
                                @unless($answer->is_correct)
                                    · To'g'ri javob: <i>{{ $answer->correct_answer_text }}</i>
                                @endunless
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <p class="k-note">Natijangiz saqlandi. Kompyuterni keyingi talabaga bo'shatib bering.</p>
    <div style="max-width:320px;margin:14px auto 0">
        <a href="{{ route('kiosk.fan-testi.show', $test) }}" class="k-btn" style="text-decoration:none">Keyingi talaba</a>
    </div>
@endsection
