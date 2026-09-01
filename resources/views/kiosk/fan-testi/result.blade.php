@extends('kiosk.fan-testi.layout')

@section('title', 'Natija · ' . $test->name)

@section('styles')
        .r-banner {
            padding: 34px 30px 30px;
            border-bottom: 1px solid var(--line-soft);
            text-align: center;
        }
        .r-banner.ok { background: linear-gradient(180deg, #f4fbf7, #fbfefc); }
        .r-banner.no { background: linear-gradient(180deg, #fdf6f5, #fefbfb); }

        .r-dial {
            position: relative; display: grid; place-items: center;
            width: 138px; height: 138px; margin: 0 auto 18px; border-radius: 50%;
        }
        .r-dial-in {
            display: grid; place-items: center;
            width: 112px; height: 112px; border-radius: 50%; background: #fff;
            box-shadow: 0 2px 10px rgba(15, 39, 72, .07);
        }
        .r-dial b {
            color: var(--navy); font-family: 'Roboto Slab', serif;
            font-size: 31px; font-weight: 700; line-height: 1;
        }
        .r-dial span {
            margin-top: 3px; color: var(--muted);
            font-size: 9.5px; font-weight: 600; letter-spacing: .15em; text-transform: uppercase;
        }

        .r-verdict {
            font-family: 'Roboto Slab', serif; font-size: 21px; font-weight: 600; letter-spacing: -.01em;
        }
        .r-verdict.ok { color: var(--ok); }
        .r-verdict.no { color: var(--bad); }
        .r-sub { margin-top: 6px; color: var(--ink-soft); font-size: 14px; }
        .r-expired {
            display: inline-block; margin-top: 11px; padding: 4px 12px;
            border: 1px solid #edd0a0; border-radius: 999px;
            background: var(--warn-bg); color: var(--warn);
            font-size: 11px; font-weight: 500; letter-spacing: .04em;
        }

        .r-stats {
            display: grid; grid-template-columns: repeat(3, 1fr);
            border-bottom: 1px solid var(--line-soft);
        }
        .r-stat { padding: 18px 14px; border-right: 1px solid var(--line-soft); text-align: center; }
        .r-stat:last-child { border-right: 0; }
        .r-stat b {
            display: block; color: var(--navy);
            font-family: 'Roboto Slab', serif; font-size: 20px; font-weight: 600;
        }
        .r-stat span {
            display: block; margin-top: 3px; color: var(--muted);
            font-size: 9.5px; font-weight: 600; letter-spacing: .13em; text-transform: uppercase;
        }

        .r-list { padding: 24px 30px 28px; }
        .r-list h2 {
            margin: 0 0 4px; color: var(--navy);
            font-size: 15px; font-weight: 600;
        }
        .r-list-sub { margin: 0 0 16px; color: var(--muted); font-size: 12.5px; }

        .r-row {
            display: flex; gap: 13px; padding: 14px 0;
            border-top: 1px solid var(--line-soft);
        }
        .r-row:last-child { border-bottom: 1px solid var(--line-soft); }
        .r-mark {
            flex: none; display: grid; place-items: center;
            width: 23px; height: 23px; margin-top: 1px; border-radius: 50%;
            color: #fff; font-size: 12px; font-weight: 700;
        }
        .r-mark.ok { background: var(--ok); }
        .r-mark.no { background: var(--bad); }
        .r-q { color: var(--ink); font-size: 14px; line-height: 1.55; }
        .r-q em { font-style: normal; color: var(--muted); font-weight: 500; }
        .r-given { margin-top: 6px; color: var(--ink-soft); font-size: 13px; }
        .r-given i { font-style: normal; color: var(--muted); }
        .r-correct { margin-top: 3px; color: var(--ok); font-size: 13px; }
        .r-correct i { font-style: normal; color: var(--muted); }

        .r-actions { display: flex; gap: 10px; margin-top: 22px; }
        @media (max-width: 600px) {
            .r-banner, .r-list { padding-left: 18px; padding-right: 18px; }
            .r-stats { grid-template-columns: 1fr; }
            .r-stat { border-right: 0; border-bottom: 1px solid var(--line-soft); }
            .r-stat:last-child { border-bottom: 0; }
        }
@endsection

@section('content')
    @php
        $percent = (float) $attempt->percent;
        $passed = (bool) $attempt->is_passed;
        $shown = rtrim(rtrim(number_format($percent, 1, '.', ''), '0'), '.');
        $ring = $passed ? '#0f7a52' : '#b3261e';
        $track = $passed ? '#d7eee3' : '#f6dcda';
    @endphp

    <div class="k-card">
        <div class="r-banner {{ $passed ? 'ok' : 'no' }}">
            <div class="r-dial" style="background:conic-gradient({{ $ring }} {{ max(0, min(100, $percent)) }}%, {{ $track }} 0)">
                <div class="r-dial-in">
                    <b>{{ $shown }}%</b>
                    <span>Natija</span>
                </div>
            </div>

            <div class="r-verdict {{ $passed ? 'ok' : 'no' }}">
                {{ $passed ? 'Test muvaffaqiyatli topshirildi' : 'Test o\'tilmadi' }}
            </div>
            <div class="r-sub">
                {{ $attempt->student_name }}@if($attempt->group_name) · {{ $attempt->group_name }}@endif
            </div>

            @if($attempt->status === 'expired')
                <div class="r-expired">Vaqt tugagani sababli avtomatik topshirildi</div>
            @endif
        </div>

        <div class="r-stats">
            <div class="r-stat">
                <b>{{ $attempt->correct_count }} / {{ $attempt->questions_count }}</b>
                <span>To'g'ri javob</span>
            </div>
            <div class="r-stat">
                <b>{{ (int) $attempt->score }} / {{ $attempt->total_points }}</b>
                <span>To'plangan ball</span>
            </div>
            <div class="r-stat">
                <b>{{ $attempt->duration_seconds ? gmdate('i:s', min(5999, $attempt->duration_seconds)) : '—' }}</b>
                <span>Sarflangan vaqt</span>
            </div>
        </div>

        @if($test->show_result_after_submit && $attempt->answers->isNotEmpty())
            <div class="r-list">
                <h2>Javoblaringiz tahlili</h2>
                <p class="r-list-sub">Har bir savol bo'yicha bergan javobingiz va to'g'ri javob.</p>

                @foreach($attempt->answers as $answer)
                    @php
                        $given = $answer->question_type === 'fill_in_blank'
                            ? $answer->answer_text
                            : $answer->selected_option_text;
                    @endphp
                    <div class="r-row">
                        <span class="r-mark {{ $answer->is_correct ? 'ok' : 'no' }}">{{ $answer->is_correct ? '✓' : '✕' }}</span>
                        <div style="min-width:0;flex:1">
                            <div class="r-q"><em>{{ $answer->question_index + 1 }}.</em> {{ $answer->question_prompt }}</div>
                            <div class="r-given">
                                <i>Sizning javobingiz:</i>
                                {{ $given ?: 'javob berilmagan' }}
                            </div>
                            @unless($answer->is_correct)
                                <div class="r-correct"><i>To'g'ri javob:</i> {{ $answer->correct_answer_text }}</div>
                            @endunless
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <div class="r-actions">
        <a href="{{ route('kiosk.fan-testi.show', $test) }}" class="k-btn">Keyingi talaba</a>
    </div>

    <p class="k-note">
        Natijangiz saqlandi va o'qituvchining test jurnalida aks etadi.<br>
        Kompyuterni keyingi talabaga bo'shatib bering.
    </p>
@endsection
