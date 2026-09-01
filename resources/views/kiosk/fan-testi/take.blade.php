@extends('kiosk.fan-testi.layout')

@section('title', $test->name)
@section('wrap-class', 'k-wide')

@section('styles')
        .t-bar {
            position: sticky; top: 0; z-index: 20;
            display: flex; align-items: center; justify-content: space-between; gap: 14px;
            margin-bottom: 18px; padding: 13px 18px;
            border: 1px solid #d9e4f2; border-radius: 14px;
            background: #fff; box-shadow: 0 6px 20px rgba(23,52,94,.08);
        }
        .t-who b { display: block; color: #16263f; font-size: 14px; font-weight: 900; }
        .t-who span { color: #7e91ad; font-size: 11px; font-weight: 700; }
        .t-right { display: flex; align-items: center; gap: 14px; }
        .t-count { color: #5b7091; font-size: 12px; font-weight: 800; white-space: nowrap; }
        .t-count b { color: #2563eb; }
        .t-clock {
            min-width: 104px; padding: 9px 14px; border-radius: 11px;
            background: #eff6ff; color: #1d4ed8; font-size: 21px; font-weight: 900;
            font-variant-numeric: tabular-nums; text-align: center;
        }
        .t-clock.is-warn { background: #fff7ed; color: #c2410c; animation: tpulse 1s ease-in-out infinite; }
        .t-clock.is-out { background: #fef2f2; color: #b91c1c; }
        @keyframes tpulse { 50% { opacity: .55; } }

        .t-warn {
            display: none; margin-bottom: 16px; padding: 13px 16px;
            border: 1px solid #fed7aa; border-radius: 12px;
            background: #fff7ed; color: #9a3412; font-size: 13px; font-weight: 800;
        }
        .t-warn.is-on { display: block; }

        .q {
            margin-bottom: 14px; padding: 20px 22px;
            border: 1px solid #dde7f5; border-radius: 15px; background: #fff;
        }
        .q-head { display: flex; gap: 12px; margin-bottom: 14px; }
        .q-num {
            flex: none; display: grid; place-items: center;
            width: 32px; height: 32px; border-radius: 9px;
            background: #2563eb; color: #fff; font-size: 13px; font-weight: 900;
        }
        .q-num.is-done { background: #059669; }
        .q-text { color: #16263f; font-size: 15px; font-weight: 700; line-height: 1.55; }
        .q-help { margin-top: 5px; color: #7e91ad; font-size: 12px; font-weight: 600; }
        .q-pts { flex: none; color: #93a5bf; font-size: 11px; font-weight: 800; white-space: nowrap; }
        .q-img { max-width: 100%; max-height: 280px; margin: 0 0 14px; border-radius: 10px; }

        .o { display: block; margin-bottom: 8px; cursor: pointer; }
        .o:last-child { margin-bottom: 0; }
        .o input { position: absolute; opacity: 0; width: 0; height: 0; }
        .o-box {
            display: flex; align-items: center; gap: 11px;
            padding: 12px 14px; border: 2px solid #dfe8f5; border-radius: 11px;
            background: #fbfdff; transition: border-color .13s, background .13s;
        }
        .o:hover .o-box { border-color: #a9c6f0; background: #f4f9ff; }
        .o input:checked + .o-box { border-color: #2563eb; background: #eff6ff; }
        .o-key {
            flex: none; display: grid; place-items: center;
            width: 27px; height: 27px; border-radius: 7px;
            background: #e6edf8; color: #47597a; font-size: 12px; font-weight: 900;
        }
        .o input:checked + .o-box .o-key { background: #2563eb; color: #fff; }
        .o-text { color: #21375c; font-size: 14px; font-weight: 600; line-height: 1.45; }

        .q-blank {
            width: 100%; height: 48px; padding: 0 14px;
            border: 2px solid #dfe8f5; border-radius: 11px;
            background: #fbfdff; color: #16263f; font-size: 15px; font-weight: 600; outline: none;
        }
        .q-blank:focus { border-color: #2563eb; background: #fff; box-shadow: 0 0 0 3px rgba(37,99,235,.12); }

        .t-foot {
            position: sticky; bottom: 0;
            margin-top: 20px; padding: 16px 20px;
            border: 1px solid #d9e4f2; border-radius: 14px;
            background: #fff; box-shadow: 0 -6px 20px rgba(23,52,94,.08);
        }
        @media (max-width: 560px) {
            .t-bar { flex-direction: column; align-items: stretch; }
            .t-right { justify-content: space-between; }
        }
@endsection

@section('content')
    <form method="POST" action="{{ route('kiosk.fan-testi.submit', [$test, $attempt]) }}" id="testForm">
        @csrf

        <div class="t-bar">
            <div class="t-who">
                <b>{{ $attempt->student_name }}</b>
                <span>{{ $attempt->student_id_number }} @if($attempt->group_name) · {{ $attempt->group_name }} @endif</span>
            </div>
            <div class="t-right">
                <span class="t-count"><b id="doneCount">0</b> / {{ $questions->count() }} javob berildi</span>
                <div class="t-clock" id="clock">--:--</div>
            </div>
        </div>

        <div class="t-warn" id="timeWarn">Diqqat! 1 daqiqa vaqt qoldi — test avtomatik topshiriladi.</div>

        @foreach($questions as $index => $question)
            <div class="q" data-q="{{ $index }}">
                <div class="q-head">
                    <span class="q-num" id="num-{{ $index }}">{{ $index + 1 }}</span>
                    <div style="flex:1;min-width:0">
                        <div class="q-text">{{ $question['prompt'] ?? '' }}</div>
                        @if(!empty($question['helper_text']))
                            <div class="q-help">{{ $question['helper_text'] }}</div>
                        @endif
                    </div>
                    <span class="q-pts">{{ max(1, (int) ($question['points'] ?? 1)) }} ball</span>
                </div>

                @if(!empty($question['image_path']))
                    <img class="q-img" src="{{ asset('storage/' . ltrim($question['image_path'], '/')) }}" alt="Savol rasmi">
                @endif

                @if(($question['type'] ?? 'single_choice') === 'fill_in_blank')
                    <input class="q-blank" type="text" name="answers[{{ $index }}]"
                           autocomplete="off" placeholder="Javobingizni yozing...">
                @else
                    @foreach($question['options'] ?? [] as $optionIndex => $option)
                        <label class="o">
                            <input type="radio" name="answers[{{ $index }}]" value="{{ $optionIndex }}">
                            <span class="o-box">
                                <span class="o-key">{{ chr(65 + $optionIndex) }}</span>
                                <span class="o-text">{{ $option['text'] ?? '' }}</span>
                            </span>
                        </label>
                    @endforeach
                @endif
            </div>
        @endforeach

        <div class="t-foot">
            <button class="k-btn k-btn-lg" type="submit" id="submitBtn">Testni topshirish</button>
        </div>
    </form>
@endsection

@section('scripts')
<script>
(() => {
    const form = document.getElementById('testForm');
    const clock = document.getElementById('clock');
    const warn = document.getElementById('timeWarn');
    const doneCount = document.getElementById('doneCount');

    // Vaqt serverdan olinadi; sahifa yangilansa ham qolgan vaqt to'g'ri qoladi.
    let left = {{ (int) $secondsLeft }};
    let submitted = false;

    function two(value) { return String(value).padStart(2, '0'); }

    function renderClock() {
        clock.textContent = two(Math.floor(left / 60)) + ':' + two(left % 60);
        clock.classList.toggle('is-warn', left <= 60 && left > 0);
        clock.classList.toggle('is-out', left <= 0);
        if (left <= 60 && left > 0) warn.classList.add('is-on');
    }

    function submitOnce() {
        if (submitted) return;
        submitted = true;
        document.getElementById('submitBtn').disabled = true;
        form.submit();
    }

    renderClock();
    const ticker = setInterval(() => {
        left -= 1;
        if (left <= 0) {
            left = 0;
            renderClock();
            clearInterval(ticker);
            submitOnce();
            return;
        }
        renderClock();
    }, 1000);

    function refreshProgress() {
        let done = 0;
        form.querySelectorAll('.q').forEach(block => {
            const index = block.dataset.q;
            const radio = block.querySelector('input[type=radio]:checked');
            const text = block.querySelector('.q-blank');
            const answered = Boolean(radio) || Boolean(text && text.value.trim());
            if (answered) done += 1;
            document.getElementById('num-' + index).classList.toggle('is-done', answered);
        });
        doneCount.textContent = done;
    }

    form.addEventListener('change', refreshProgress);
    form.addEventListener('input', refreshProgress);
    refreshProgress();

    form.addEventListener('submit', event => {
        if (submitted) return;
        if (left > 0 && !confirm('Testni topshirasizmi? Keyin javoblarni o\'zgartira olmaysiz.')) {
            event.preventDefault();
            return;
        }
        submitted = true;
        document.getElementById('submitBtn').disabled = true;
    });

    window.addEventListener('beforeunload', event => {
        if (!submitted) { event.preventDefault(); event.returnValue = ''; }
    });
})();
</script>
@endsection
