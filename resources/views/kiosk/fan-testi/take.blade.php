@extends('kiosk.fan-testi.layout')

@section('title', $test->name)
@section('wrap-class', 'k-wide')

@section('styles')
        /* ---------- Ustki panel ---------- */
        .t-bar {
            position: sticky; top: 0; z-index: 30;
            display: flex; align-items: center; justify-content: space-between; gap: 18px;
            margin-bottom: 16px; padding: 13px 20px;
            border: 1px solid var(--line); border-radius: 6px;
            background: rgba(255, 255, 255, .97);
            backdrop-filter: blur(6px);
            box-shadow: 0 2px 16px rgba(15, 39, 72, .09);
        }
        .t-who b {
            display: block; color: var(--navy);
            font-family: 'Roboto Slab', serif; font-size: 15px; font-weight: 600;
        }
        .t-who span { display: block; margin-top: 1px; color: var(--muted); font-size: 11.5px; letter-spacing: .02em; }
        .t-right { display: flex; align-items: center; gap: 18px; }

        .t-progress { min-width: 132px; }
        .t-progress-top {
            display: flex; justify-content: space-between; gap: 10px; margin-bottom: 5px;
            color: var(--ink-soft); font-size: 11px; font-weight: 500; letter-spacing: .04em;
        }
        .t-progress-top b { color: var(--navy); font-weight: 700; }
        .t-track { height: 4px; border-radius: 2px; background: #e3e9f2; overflow: hidden; }
        .t-fill { height: 100%; width: 0; border-radius: 2px; background: var(--navy); transition: width .25s ease; }

        .t-clock {
            min-width: 116px; padding: 9px 15px;
            border: 1px solid #cdd8e6; border-radius: 5px;
            background: #f7f9fc; color: var(--navy);
            font-family: 'Roboto Slab', serif; font-size: 23px; font-weight: 600;
            font-variant-numeric: tabular-nums; text-align: center; line-height: 1.15;
        }
        .t-clock small {
            display: block; margin-top: 1px;
            color: var(--muted); font-family: 'Roboto', sans-serif;
            font-size: 9px; font-weight: 500; letter-spacing: .13em; text-transform: uppercase;
        }
        .t-clock.is-warn { border-color: #edd0a0; background: var(--warn-bg); color: var(--warn); }
        .t-clock.is-warn small { color: #c08339; }
        .t-clock.is-out { border-color: #edc4c1; background: var(--bad-bg); color: var(--bad); }

        .t-warn {
            display: none; align-items: center; gap: 11px;
            margin-bottom: 16px; padding: 13px 17px;
            border: 1px solid #edd0a0; border-left: 3px solid var(--warn); border-radius: 4px;
            background: var(--warn-bg); color: #8a4c05; font-size: 13.5px; font-weight: 500;
        }
        .t-warn.is-on { display: flex; }

        /* ---------- Savol ---------- */
        .q {
            margin-bottom: 14px;
            border: 1px solid var(--line); border-radius: 6px; background: var(--paper);
            box-shadow: 0 1px 2px rgba(15, 39, 72, .04);
            transition: border-color .16s, box-shadow .16s;
        }
        .q.is-done { border-color: #bcd9cc; }
        .q-top {
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
            padding: 11px 20px; border-bottom: 1px solid var(--line-soft);
            background: #fafcfe; border-radius: 6px 6px 0 0;
        }
        .q-label {
            display: flex; align-items: center; gap: 9px;
            color: var(--navy); font-size: 11px; font-weight: 700;
            letter-spacing: .13em; text-transform: uppercase;
        }
        .q-tick {
            display: grid; place-items: center;
            width: 17px; height: 17px; border-radius: 50%;
            background: #dde5ef; color: transparent;
            font-size: 10px; font-weight: 700; transition: background .16s, color .16s;
        }
        .q.is-done .q-tick { background: var(--ok); color: #fff; }
        .q-pts { color: var(--muted); font-size: 11px; font-weight: 500; letter-spacing: .04em; }

        .q-body { padding: 19px 20px 20px; }
        .q-text { color: var(--ink); font-size: 16px; font-weight: 400; line-height: 1.6; }
        .q-help {
            margin-top: 7px; padding-left: 11px; border-left: 2px solid var(--line);
            color: var(--muted); font-size: 13px; font-style: italic;
        }
        .q-img {
            display: block; max-width: 100%; max-height: 300px;
            margin: 15px 0 0; border: 1px solid var(--line); border-radius: 4px;
        }
        .q-options { margin-top: 17px; }
        .q-hint-multi {
            margin-bottom: 11px; padding: 8px 12px;
            border-left: 3px solid var(--gold); border-radius: 0 3px 3px 0;
            background: #fdfaf0; color: var(--ink-soft); font-size: 12.5px;
        }
        .o.is-multi input:checked + .o-box .o-key { border-radius: 4px; }

        .q-match { display: grid; gap: 9px; }
        .q-match-row {
            display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 12px; align-items: center;
            padding: 10px 13px; border: 1px solid var(--line); border-radius: 5px; background: #fafcfe;
        }
        .q-match-left { color: var(--ink); font-size: 14px; line-height: 1.5; }
        .q-match-pick, .q-order-pick {
            height: 40px; padding: 0 10px; border: 1px solid #c4d0e0; border-radius: 4px;
            background: #fff; color: var(--ink); font-family: inherit; font-size: 13.5px; outline: none;
        }
        .q-match-pick:focus, .q-order-pick:focus { border-color: var(--navy); box-shadow: 0 0 0 3px rgba(27,58,99,.14); }

        .q-order { display: grid; gap: 9px; }
        .q-order-row {
            display: grid; grid-template-columns: 78px minmax(0, 1fr); gap: 12px; align-items: center;
            padding: 10px 13px; border: 1px solid var(--line); border-radius: 5px; background: #fafcfe;
        }
        .q-order-row.is-set { border-color: #a9c6ec; background: #f4f8fd; }
        .q-order-text { color: var(--ink); font-size: 14px; line-height: 1.5; }
        .q-order-dup .q-order-pick { border-color: var(--bad); background: var(--bad-bg); }

        @media (max-width: 640px) {
            .q-match-row { grid-template-columns: 1fr; gap: 8px; }
            .q-order-row { grid-template-columns: 64px minmax(0, 1fr); }
        }

        /* ---------- Variantlar ---------- */
        .o { display: block; margin-bottom: 8px; cursor: pointer; }
        .o:last-child { margin-bottom: 0; }
        .o input { position: absolute; width: 1px; height: 1px; opacity: 0; }
        .o-box {
            display: flex; align-items: flex-start; gap: 13px;
            padding: 13px 16px;
            border: 1px solid #d5dfec; border-radius: 5px; background: #fcfdff;
            transition: border-color .14s, background .14s, box-shadow .14s;
        }
        .o:hover .o-box { border-color: #a9bdd8; background: #f6f9fd; }
        .o input:focus-visible + .o-box { box-shadow: 0 0 0 3px rgba(27, 58, 99, .16); }
        .o input:checked + .o-box {
            border-color: var(--navy); background: #f3f7fc;
            box-shadow: inset 0 0 0 1px var(--navy);
        }
        .o-key {
            flex: none; display: grid; place-items: center;
            width: 27px; height: 27px; border-radius: 50%;
            border: 1px solid #c9d5e4; background: #fff;
            color: var(--ink-soft); font-size: 12.5px; font-weight: 700;
            transition: background .14s, color .14s, border-color .14s;
        }
        .o input:checked + .o-box .o-key {
            border-color: var(--navy); background: var(--navy); color: #fff;
        }
        .o-text { padding-top: 3px; color: var(--ink); font-size: 15px; line-height: 1.5; }

        .q-blank {
            width: 100%; height: 50px; padding: 0 16px;
            border: 1px solid #d5dfec; border-radius: 5px;
            background: #fcfdff; color: var(--ink);
            font-family: 'Roboto', sans-serif; font-size: 16px; outline: none;
            transition: border-color .14s, box-shadow .14s, background .14s;
        }
        .q-blank::placeholder { color: #b4c1d3; }
        .q-blank:focus {
            border-color: var(--navy); background: #fff;
            box-shadow: 0 0 0 3px rgba(27, 58, 99, .1);
        }

        /* ---------- Pastki panel ---------- */
        .t-foot {
            position: sticky; bottom: 0; z-index: 20;
            display: flex; align-items: center; justify-content: space-between; gap: 16px;
            margin-top: 18px; padding: 15px 20px;
            border: 1px solid var(--line); border-radius: 6px;
            background: rgba(255, 255, 255, .97);
            backdrop-filter: blur(6px);
            box-shadow: 0 -2px 16px rgba(15, 39, 72, .09);
        }
        .t-foot-note { color: var(--muted); font-size: 12.5px; }
        .t-foot-note b { color: var(--navy); font-weight: 700; }
        .t-foot .k-btn { width: auto; min-width: 210px; }

        @media (max-width: 720px) {
            .t-bar { flex-direction: column; align-items: stretch; gap: 13px; }
            .t-right { justify-content: space-between; }
            .t-foot { flex-direction: column; align-items: stretch; }
            .t-foot .k-btn { width: 100%; }
            .t-foot-note { text-align: center; }
            .q-body, .q-top { padding-left: 15px; padding-right: 15px; }
        }
@endsection

@section('content')
    <form method="POST" action="{{ route('kiosk.fan-testi.submit', [$test, $attempt]) }}" id="testForm">
        @csrf

        <div class="t-bar">
            <div class="t-who">
                <b>{{ $attempt->student_name }}</b>
                <span>{{ $attempt->student_id_number }}@if($attempt->group_name) &nbsp;·&nbsp; {{ $attempt->group_name }}@endif</span>
            </div>
            <div class="t-right">
                <div class="t-progress">
                    <div class="t-progress-top">
                        <span>Javob berildi</span>
                        <span><b id="doneCount">0</b> / {{ $questions->count() }}</span>
                    </div>
                    <div class="t-track"><div class="t-fill" id="progressFill"></div></div>
                </div>
                <div class="t-clock" id="clock"><span id="clockTime">--:--</span><small>Qolgan vaqt</small></div>
            </div>
        </div>

        <div class="t-warn" id="timeWarn">
            <span style="font-size:16px">&#9888;</span>
            <span>Vaqt tugashiga bir daqiqa qoldi. Test avtomatik topshiriladi.</span>
        </div>

        @foreach($questions as $index => $question)
            <div class="q" data-q="{{ $index }}" id="q-{{ $index }}">
                <div class="q-top">
                    <span class="q-label">
                        <span class="q-tick">&#10003;</span>
                        {{ $index + 1 }}-savol
                    </span>
                    <span class="q-pts">{{ max(1, (int) ($question['points'] ?? 1)) }} ball</span>
                </div>

                <div class="q-body">
                    <div class="q-text">{{ $question['prompt'] ?? '' }}</div>

                    @if(!empty($question['helper_text']))
                        <div class="q-help">{{ $question['helper_text'] }}</div>
                    @endif

                    @if(!empty($question['image_path']))
                        <img class="q-img" src="{{ route('fan-testi.attempt-image', [$attempt, $index]) }}" alt="Savol rasmi" loading="lazy">
                    @endif

                    @php $qType = $question['type'] ?? 'single_choice'; @endphp

                    <div class="q-options">
                        @if($qType === 'fill_in_blank')
                            <input class="q-blank" type="text" name="answers[{{ $index }}]"
                                   autocomplete="off" placeholder="Javobingizni yozing">

                        @elseif($qType === 'multiple_choice')
                            <div class="q-hint-multi">Bir nechta to'g'ri javob bor — barchasini belgilang.</div>
                            @foreach($question['options'] ?? [] as $optionIndex => $option)
                                <label class="o is-multi">
                                    <input type="checkbox" name="answers[{{ $index }}][]" value="{{ $optionIndex }}">
                                    <span class="o-box">
                                        <span class="o-key">{{ chr(65 + $optionIndex) }}</span>
                                        <span class="o-text">{{ $option['text'] ?? '' }}</span>
                                    </span>
                                </label>
                            @endforeach

                        @elseif($qType === 'matching')
                            @php
                                $pairs = $question['pairs'] ?? [];
                                $rightOrder = collect($pairs)->keys()->all();
                                // O'ng ustun aralashtiriladi — tanlov mazmunli bo'lsin.
                                $seed = $index + 7;
                                usort($rightOrder, fn ($a, $b) => (($a * 31 + $seed) % 97) <=> (($b * 31 + $seed) % 97));
                            @endphp
                            <div class="q-hint-multi">Har bir chap ustun bandiga mos javobni tanlang.</div>
                            <div class="q-match">
                                @foreach($pairs as $pairIndex => $pair)
                                    <div class="q-match-row">
                                        <span class="q-match-left">{{ $pair['left'] ?? '' }}</span>
                                        <select class="q-match-pick" name="answers[{{ $index }}][{{ $pairIndex }}]">
                                            <option value="">— tanlang —</option>
                                            @foreach($rightOrder as $rightIndex)
                                                <option value="{{ $rightIndex }}">{{ $pairs[$rightIndex]['right'] ?? '' }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @endforeach
                            </div>

                        @elseif($qType === 'ordering')
                            @php
                                $steps = $question['steps'] ?? [];
                                $stepOrder = collect($steps)->keys()->all();
                                $seed = $index + 3;
                                usort($stepOrder, fn ($a, $b) => (($a * 17 + $seed) % 89) <=> (($b * 17 + $seed) % 89));
                                $stepCount = count($steps);
                            @endphp
                            <div class="q-hint-multi">Bosqichlarni to'g'ri ketma-ketlikda raqamlang (1 dan {{ $stepCount }} gacha).</div>
                            <div class="q-order" data-order="{{ $index }}" data-count="{{ $stepCount }}">
                                @foreach($stepOrder as $stepIndex)
                                    <div class="q-order-row">
                                        <select class="q-order-pick" data-step="{{ $stepIndex }}">
                                            <option value="">—</option>
                                            @for($position = 1; $position <= $stepCount; $position++)
                                                <option value="{{ $position }}">{{ $position }}</option>
                                            @endfor
                                        </select>
                                        <span class="q-order-text">{{ $steps[$stepIndex]['text'] ?? '' }}</span>
                                    </div>
                                @endforeach
                            </div>
                            {{-- Tartib JS orqali yig'iladi: pozitsiya bo'yicha bosqich indekslari --}}
                            @for($slot = 0; $slot < $stepCount; $slot++)
                                <input type="hidden" name="answers[{{ $index }}][]" data-order-slot="{{ $index }}-{{ $slot }}" value="">
                            @endfor

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
                </div>
            </div>
        @endforeach

        <div class="t-foot">
            <div class="t-foot-note">
                Topshirgandan so'ng javoblarni o'zgartirib bo'lmaydi.
                <b id="footLeft"></b>
            </div>
            <button class="k-btn" type="submit" id="submitBtn">Testni topshirish</button>
        </div>
    </form>
@endsection

@section('scripts')
<script>
(() => {
    const form = document.getElementById('testForm');
    const clock = document.getElementById('clock');
    const clockTime = document.getElementById('clockTime');
    const warn = document.getElementById('timeWarn');
    const doneCount = document.getElementById('doneCount');
    const fill = document.getElementById('progressFill');
    const footLeft = document.getElementById('footLeft');
    const total = {{ $questions->count() }};

    // Qolgan vaqt serverdan olinadi — sahifa yangilansa ham to'g'ri qoladi.
    let left = {{ (int) $secondsLeft }};
    let submitted = false;

    const two = value => String(value).padStart(2, '0');

    function renderClock() {
        clockTime.textContent = two(Math.floor(left / 60)) + ':' + two(left % 60);
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

    // Ketma-ketlik savoli: tanlangan pozitsiyalardan yashirin maydonlarga
    // bosqich indekslarini joylaymiz (1-pozitsiya -> birinchi maydon).
    function syncOrdering() {
        form.querySelectorAll('.q-order').forEach(box => {
            const qIndex = box.dataset.order;
            const count = Number(box.dataset.count || 0);
            const slots = form.querySelectorAll('[data-order-slot^="' + qIndex + '-"]');
            slots.forEach(slot => { slot.value = ''; });

            const used = new Map();
            box.querySelectorAll('.q-order-pick').forEach(pick => {
                const position = Number(pick.value || 0);
                const row = pick.closest('.q-order-row');
                row.classList.toggle('is-set', position > 0);
                if (!position) return;
                if (used.has(position)) {
                    used.get(position).classList.add('q-order-dup');
                    row.classList.add('q-order-dup');
                } else {
                    used.set(position, row);
                    row.classList.remove('q-order-dup');
                    const slot = form.querySelector('[data-order-slot="' + qIndex + '-' + (position - 1) + '"]');
                    if (slot) slot.value = pick.dataset.step;
                }
            });

            // Takrorlanmagan qatorlardan xato belgisini olib tashlaymiz
            box.querySelectorAll('.q-order-row').forEach(row => {
                const pick = row.querySelector('.q-order-pick');
                const position = Number(pick.value || 0);
                if (!position || used.get(position) === row) row.classList.remove('q-order-dup');
            });
        });
    }

    function refreshProgress() {
        syncOrdering();
        let done = 0;
        form.querySelectorAll('.q').forEach(block => {
            const radio = block.querySelector('input[type=radio]:checked');
            const checks = block.querySelectorAll('input[type=checkbox]:checked');
            const text = block.querySelector('.q-blank');
            const matchPicks = block.querySelectorAll('.q-match-pick');
            const orderPicks = block.querySelectorAll('.q-order-pick');

            let answered = Boolean(radio) || checks.length > 0 || Boolean(text && text.value.trim());
            if (!answered && matchPicks.length) {
                answered = Array.from(matchPicks).every(pick => pick.value !== '');
            }
            if (!answered && orderPicks.length) {
                answered = Array.from(orderPicks).every(pick => pick.value !== '');
            }
            if (answered) done += 1;
            block.classList.toggle('is-done', answered);
        });
        doneCount.textContent = done;
        fill.style.width = total ? (done / total * 100) + '%' : '0%';
        const rest = total - done;
        footLeft.textContent = rest > 0 ? rest + ' ta savol javobsiz.' : 'Barcha savollarga javob berildi.';
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
