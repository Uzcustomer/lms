<x-app-layout>
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=roboto:300,400,500,700|roboto-slab:400,600,700&display=swap" rel="stylesheet">
<style>
    .jr {
        --navy: #0f2748; --navy-soft: #1b3a63; --gold: #c9a227;
        --ink: #17233a; --ink-soft: #4d6180; --muted: #8798b1;
        --line: #dde5ef; --line-soft: #eef2f8;
        --ok: #0f7a52; --ok-bg: #e9f7f0; --bad: #b3261e; --bad-bg: #fdeceb;
        --warn: #a35a06; --warn-bg: #fdf3e4;
        font-family: 'Roboto', system-ui, sans-serif; color: var(--ink);
    }
    .jr h1, .jr h2, .jr .slab { font-family: 'Roboto Slab', Georgia, serif; }

    /* ---- Sarlavha ---- */
    .jr-head {
        padding: 24px 28px;
        border: 1px solid var(--line); border-left: 3px solid var(--gold); border-radius: 6px;
        background: linear-gradient(180deg, #fbfcfe, #f5f8fc);
    }
    .jr-eyebrow {
        margin-bottom: 7px; color: var(--gold);
        font-size: 10px; font-weight: 700; letter-spacing: .2em; text-transform: uppercase;
    }
    .jr-head h1 { margin: 0; color: var(--navy); font-size: 24px; font-weight: 700; letter-spacing: -.01em; }
    .jr-head p { margin: 6px 0 0; color: var(--ink-soft); font-size: 14px; }

    /* ---- Filtr ---- */
    .jr-panel {
        padding: 16px 20px;
        border: 1px solid var(--line); border-radius: 6px; background: #fff;
    }
    .jr-filters { display: grid; grid-template-columns: 2fr 1fr auto; gap: 12px; align-items: end; }
    .jr-filters label {
        display: block; margin-bottom: 6px;
        color: var(--ink-soft); font-size: 11px; font-weight: 500; letter-spacing: .05em;
    }
    .jr-filters select {
        width: 100%; height: 40px; padding: 0 11px;
        border: 1px solid #c4d0e0; border-radius: 5px; background: #fcfdff;
        color: var(--ink); font-family: 'Roboto', sans-serif; font-size: 13.5px; outline: none;
    }
    .jr-filters select:focus { border-color: var(--navy-soft); box-shadow: 0 0 0 3px rgba(27,58,99,.1); }
    .jr-btn {
        height: 40px; padding: 0 22px; border: 0; border-radius: 5px;
        background: var(--navy); color: #fff;
        font-family: 'Roboto', sans-serif; font-size: 13.5px; font-weight: 500;
        letter-spacing: .04em; cursor: pointer; transition: background .16s;
    }
    .jr-btn:hover { background: var(--navy-soft); }

    /* ---- Statistika ---- */
    .jr-stats {
        display: grid; grid-template-columns: repeat(5, 1fr);
        border: 1px solid var(--line); border-radius: 6px; background: #fff; overflow: hidden;
    }
    .jr-stat { padding: 16px 18px; border-right: 1px solid var(--line-soft); }
    .jr-stat:last-child { border-right: 0; }
    .jr-stat b {
        display: block; color: var(--navy);
        font-family: 'Roboto Slab', serif; font-size: 24px; font-weight: 600; line-height: 1.1;
    }
    .jr-stat span {
        display: block; margin-top: 4px; color: var(--muted);
        font-size: 9.5px; font-weight: 600; letter-spacing: .13em; text-transform: uppercase;
    }
    .jr-stat.is-ok b { color: var(--ok); }

    /* ---- Havola ---- */
    .jr-link {
        display: flex; flex-wrap: wrap; align-items: center; gap: 10px;
        padding: 13px 20px;
        border: 1px solid var(--line); border-left: 3px solid var(--navy); border-radius: 6px;
        background: #f8fafd; font-size: 13px; color: var(--ink-soft);
    }
    .jr-link code {
        padding: 4px 10px; border: 1px solid var(--line); border-radius: 4px;
        background: #fff; color: var(--navy); font-size: 12.5px;
    }
    .jr-copy {
        padding: 6px 13px; border: 1px solid #c4d0e0; border-radius: 4px;
        background: #fff; color: var(--navy);
        font-family: 'Roboto', sans-serif; font-size: 11.5px; font-weight: 500; cursor: pointer;
    }
    .jr-copy:hover { background: #f1f5fa; }

    /* ---- Guruh bloki ---- */
    .jr-group {
        overflow: hidden; border: 1px solid var(--line); border-radius: 6px; background: #fff;
    }
    .jr-group-head {
        display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px;
        padding: 13px 20px; border-bottom: 1px solid var(--line-soft);
        background: linear-gradient(180deg, #fbfcfe, #f5f8fc);
    }
    .jr-group-head b {
        color: var(--navy); font-family: 'Roboto Slab', serif; font-size: 16px; font-weight: 600;
    }
    .jr-tags { display: flex; flex-wrap: wrap; gap: 16px; }
    .jr-tag { color: var(--muted); font-size: 11px; font-weight: 500; letter-spacing: .04em; }
    .jr-tag b { color: var(--navy); font-family: 'Roboto', sans-serif; font-size: 13px; font-weight: 700; }
    .jr-tag.is-ok b { color: var(--ok); }

    /* ---- Jadval ---- */
    .jr-table { width: 100%; border-collapse: collapse; }
    .jr-table th {
        padding: 10px 18px; border-bottom: 1px solid var(--line);
        background: #fafcfe; color: var(--muted);
        font-size: 9.5px; font-weight: 700; letter-spacing: .12em; text-align: left; text-transform: uppercase;
    }
    .jr-table td {
        padding: 13px 18px; border-bottom: 1px solid var(--line-soft);
        font-size: 13.5px; vertical-align: top;
    }
    .jr-table tbody tr:last-child td { border-bottom: 0; }
    .jr-table tbody tr:hover { background: #fafcfe; }

    .jr-student { color: var(--ink); font-weight: 500; }
    .jr-sid { display: block; margin-top: 2px; color: var(--muted); font-size: 11.5px; }

    .jr-pill {
        display: inline-flex; padding: 3px 11px; border-radius: 3px;
        font-size: 11px; font-weight: 500; letter-spacing: .03em;
    }
    .jr-pill.ok { background: var(--ok-bg); color: var(--ok); }
    .jr-pill.no { background: var(--bad-bg); color: var(--bad); }
    .jr-pill.wait { background: var(--warn-bg); color: var(--warn); }
    .jr-mini { display: block; margin-top: 3px; color: var(--muted); font-size: 10.5px; }

    .jr-num { color: var(--navy); font-family: 'Roboto Slab', serif; font-size: 14px; font-weight: 600; }
    .jr-num.is-bad { color: var(--bad); }
    .jr-when { color: var(--ink-soft); font-size: 12.5px; }

    /* ---- Javoblar ---- */
    .jr-answers > summary {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 4px 10px; border: 1px solid #c9d5e4; border-radius: 4px;
        color: var(--navy); font-size: 11.5px; font-weight: 500;
        cursor: pointer; list-style: none;
    }
    .jr-answers > summary::-webkit-details-marker { display: none; }
    .jr-answers > summary::before { content: '▸'; color: var(--muted); font-size: 9px; }
    .jr-answers[open] > summary::before { content: '▾'; }
    .jr-answers > summary:hover { background: #f1f5fa; }
    .jr-answer-list { margin-top: 10px; padding-left: 2px; }
    .jr-answer {
        display: flex; gap: 9px; padding: 8px 0; border-top: 1px solid var(--line-soft);
    }
    .jr-mark {
        flex: none; display: grid; place-items: center;
        width: 17px; height: 17px; margin-top: 2px; border-radius: 50%;
        color: #fff; font-size: 10px; font-weight: 700;
    }
    .jr-mark.ok { background: var(--ok); }
    .jr-mark.no { background: var(--bad); }
    .jr-atext { font-size: 12.5px; line-height: 1.6; }
    .jr-atext i { font-style: normal; color: var(--muted); }
    .jr-atext em { font-style: normal; color: var(--ok); font-weight: 500; }

    /* ---- Bo'sh holat ---- */
    .jr-empty {
        padding: 56px 24px; border: 1px dashed #c9d5e4; border-radius: 6px;
        background: #fff; text-align: center;
    }
    .jr-empty b { display: block; color: var(--navy); font-family: 'Roboto Slab', serif; font-size: 16px; font-weight: 600; }
    .jr-empty span { display: block; margin-top: 6px; color: var(--muted); font-size: 13px; }

    .jr-alert {
        padding: 14px 18px; border: 1px solid #edd0a0; border-left: 3px solid var(--warn);
        border-radius: 5px; background: var(--warn-bg); color: #8a4c05; font-size: 13.5px;
    }
    .jr-alert code { padding: 2px 7px; border-radius: 3px; background: rgba(0,0,0,.06); }

    @media (max-width: 900px) {
        .jr-stats { grid-template-columns: repeat(2, 1fr); }
        .jr-stat { border-bottom: 1px solid var(--line-soft); }
        .jr-filters { grid-template-columns: 1fr; }
        .jr-table thead { display: none; }
        .jr-table td { display: block; padding: 6px 16px; border-bottom: 0; }
        .jr-table tbody tr { display: block; padding: 10px 0; border-bottom: 1px solid var(--line-soft); }
    }
</style>

    <div class="jr py-6">
        <div class="w-full px-4 sm:px-6 lg:px-8" style="display:flex;flex-direction:column;gap:14px">

            <div class="jr-head">
                <div class="jr-eyebrow">Test moduli</div>
                <h1>Test jurnali</h1>
                <p>Har bir guruh qaysi test to'plamidan qanday natija ko'rsatganini ko'ring.</p>
            </div>

            @if($migrationPending)
                <div class="jr-alert">
                    Test natijalari jadvali hali yaratilmagan. Serverda <code>php artisan migrate</code> ni ishga tushiring.
                </div>
            @elseif($collections->isEmpty())
                <div class="jr-empty">
                    <b>Hali test to'plami yo'q</b>
                    <span>Avval "Test yaratish" bo'limida to'plam tuzing.</span>
                </div>
            @else
                <div class="jr-panel">
                    <form method="GET" class="jr-filters">
                        <div>
                            <label for="test_id">Test to'plami</label>
                            <select name="test_id" id="test_id">
                                @foreach($collections as $item)
                                    <option value="{{ $item->id }}" @selected($selected && $item->id === $selected->id)>
                                        {{ $item->name }} — {{ $item->subject?->subject_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="group">Guruh</label>
                            <select name="group" id="group">
                                <option value="">Barcha guruhlar</option>
                                @foreach($allGroups ?? [] as $groupName)
                                    <option value="{{ $groupName }}" @selected(request('group') === $groupName)>{{ $groupName }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button class="jr-btn" type="submit">Ko'rsatish</button>
                    </form>
                </div>

                @if($selected)
                    <div class="jr-stats">
                        <div class="jr-stat"><b>{{ $summary['total'] }}</b><span>Jami talaba</span></div>
                        <div class="jr-stat"><b>{{ $summary['submitted'] }}</b><span>Topshirgan</span></div>
                        <div class="jr-stat"><b>{{ $summary['in_progress'] }}</b><span>Ishlamoqda</span></div>
                        <div class="jr-stat is-ok"><b>{{ $summary['passed'] }}</b><span>O'tgan</span></div>
                        <div class="jr-stat"><b>{{ $summary['average_percent'] }}%</b><span>O'rtacha natija</span></div>
                    </div>

                    @php $kioskUrl = route('kiosk.fan-testi.show', $selected); @endphp
                    <div class="jr-link">
                        <span>Talaba havolasi:</span>
                        <code id="kioskUrl">{{ $kioskUrl }}</code>
                        <button type="button" class="jr-copy" id="copyUrl" data-url="{{ $kioskUrl }}">Nusxalash</button>
                        <span style="color:var(--muted)">— shu havolani sinf kompyuterlarida oching.</span>
                    </div>

                    @forelse($groups as $group)
                        <div class="jr-group">
                            <div class="jr-group-head">
                                <b>{{ $group['name'] }}</b>
                                <div class="jr-tags">
                                    <span class="jr-tag"><b>{{ $group['submitted_count'] }}</b> topshirgan</span>
                                    <span class="jr-tag is-ok"><b>{{ $group['passed_count'] }}</b> o'tgan</span>
                                    <span class="jr-tag">o'rtacha <b>{{ $group['average_percent'] }}%</b></span>
                                </div>
                            </div>

                            <table class="jr-table">
                                <thead>
                                <tr>
                                    <th style="width:26%">Talaba</th>
                                    <th style="width:11%">Holat</th>
                                    <th style="width:9%">Ball</th>
                                    <th style="width:8%">Foiz</th>
                                    <th style="width:15%">Topshirgan vaqti</th>
                                    <th>Javoblari</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($group['attempts'] as $attempt)
                                    <tr>
                                        <td>
                                            <span class="jr-student">{{ $attempt->student_name }}</span>
                                            <span class="jr-sid">{{ $attempt->student_id_number }}</span>
                                        </td>
                                        <td>
                                            @if($attempt->status === 'in_progress')
                                                <span class="jr-pill wait">Ishlamoqda</span>
                                            @else
                                                <span class="jr-pill {{ $attempt->is_passed ? 'ok' : 'no' }}">
                                                    {{ $attempt->is_passed ? 'O\'tdi' : 'O\'tmadi' }}
                                                </span>
                                                @if($attempt->status === 'expired')
                                                    <span class="jr-mini">vaqt tugagan</span>
                                                @endif
                                            @endif
                                        </td>
                                        <td><span class="jr-num">{{ (int) $attempt->score }} / {{ $attempt->total_points }}</span></td>
                                        <td>
                                            <span class="jr-num {{ !$attempt->is_passed && $attempt->status !== 'in_progress' ? 'is-bad' : '' }}">
                                                {{ $attempt->percent !== null ? rtrim(rtrim(number_format((float) $attempt->percent, 1, '.', ''), '0'), '.') . '%' : '—' }}
                                            </span>
                                        </td>
                                        <td><span class="jr-when">{{ $attempt->submitted_at?->format('d.m.Y H:i') ?? '—' }}</span></td>
                                        <td>
                                            @if($attempt->answers->isEmpty())
                                                <span class="jr-mini">javob yo'q</span>
                                            @else
                                                <details class="jr-answers">
                                                    <summary>{{ $attempt->correct_count }} / {{ $attempt->questions_count }} to'g'ri</summary>
                                                    <div class="jr-answer-list">
                                                        @foreach($attempt->answers as $answer)
                                                            @php
                                                                $given = $answer->question_type === 'fill_in_blank'
                                                                    ? $answer->answer_text
                                                                    : $answer->selected_option_text;
                                                            @endphp
                                                            <div class="jr-answer">
                                                                <span class="jr-mark {{ $answer->is_correct ? 'ok' : 'no' }}">{{ $answer->is_correct ? '✓' : '✕' }}</span>
                                                                <div class="jr-atext">
                                                                    {{ $answer->question_index + 1 }}. {{ \Illuminate\Support\Str::limit($answer->question_prompt, 90) }}<br>
                                                                    <i>javobi:</i> {{ $given ?: '—' }}
                                                                    @unless($answer->is_correct)
                                                                        &nbsp;·&nbsp; <i>to'g'risi:</i> <em>{{ $answer->correct_answer_text }}</em>
                                                                    @endunless
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                </details>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    @empty
                        <div class="jr-empty">
                            <b>Hali hech kim topshirmagan</b>
                            <span>Talabalar havola orqali testni topshirgach, natijalar shu yerda guruhlar bo'yicha chiqadi.</span>
                        </div>
                    @endforelse
                @endif
            @endif
        </div>
    </div>

    <script>
    (() => {
        const button = document.getElementById('copyUrl');
        if (!button) return;
        button.addEventListener('click', async () => {
            const url = button.dataset.url;
            try {
                await navigator.clipboard.writeText(url);
            } catch (error) {
                const field = document.createElement('textarea');
                field.value = url;
                document.body.appendChild(field);
                field.select();
                document.execCommand('copy');
                field.remove();
            }
            button.textContent = 'Nusxalandi';
            setTimeout(() => { button.textContent = 'Nusxalash'; }, 1600);
        });
    })();
    </script>
</x-app-layout>
