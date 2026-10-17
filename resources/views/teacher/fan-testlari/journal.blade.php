<x-app-layout>
<style>
    /* Shrift LMS sahifasidan olinadi; forma elementlari ham shunga ergashsin */
    .jr input, .jr select, .jr textarea, .jr button { font-family: inherit; }
    .jr {
        --navy: #0f2748; --navy-soft: #1b3a63; --gold: #c9a227;
        --ink: #17233a; --ink-soft: #4d6180; --muted: #8798b1;
        --line: #dde5ef; --line-soft: #eef2f8;
        --ok: #0f7a52; --ok-bg: #e9f7f0; --bad: #b3261e; --bad-bg: #fdeceb;
        --warn: #a35a06; --warn-bg: #fdf3e4;
        font-family: inherit; color: var(--ink);
    }
    .jr h1, .jr h2, .jr .slab { font-family: inherit; }

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
    .jr-filters { display: grid; grid-template-columns: 1.3fr 1.6fr 1fr 1.2fr auto auto auto; gap: 12px; align-items: end; }
    .jr-excel {
        display: inline-flex; align-items: center; gap: 7px; height: 42px; padding: 0 18px;
        border: 1px solid #9fd3b9; border-radius: 9px; background: var(--ok-bg);
        color: var(--ok); font-size: 13.5px; font-weight: 600; text-decoration: none; white-space: nowrap;
        transition: background .16s, color .16s;
    }
    .jr-excel:hover { background: var(--ok); color: #fff; }
    .jr-excel:focus-visible { outline: 2px solid var(--ok); outline-offset: 2px; }
    .jr-filters input[type='search'] {
        width: 100%; height: 40px; padding: 0 11px;
        border: 1px solid #c4d0e0; border-radius: 5px; background: #fcfdff;
        color: var(--ink); font-family: inherit; font-size: 13.5px; outline: none;
    }
    .jr-filters input[type='search']:focus { border-color: var(--navy-soft); box-shadow: 0 0 0 3px rgba(27,58,99,.1); }
    .jr-reset {
        display: inline-flex; align-items: center; height: 40px; padding: 0 16px;
        border: 1px solid #c4d0e0; border-radius: 5px; background: #fff;
        color: var(--ink-soft); font-size: 12.5px; text-decoration: none; white-space: nowrap;
    }
    .jr-reset:hover { background: #f1f5fa; color: var(--navy); }

    .jr-flash {
        padding: 13px 18px; border: 1px solid #a5d6bf; border-left: 3px solid var(--ok);
        border-radius: 5px; background: var(--ok-bg); color: #0a6043; font-size: 13.5px;
    }

    .jr-group-clear { margin: 0; }
    .jr-group-clear button {
        padding: 5px 12px; border: 1px solid #e8c3c0; border-radius: 4px; background: #fff;
        color: var(--bad); font-family: inherit; font-size: 11px; cursor: pointer; transition: background .16s;
    }
    .jr-group-clear button:hover { background: var(--bad-bg); }

    .jr-del-form { margin: 0; }
    .jr-del {
        width: 28px; height: 28px; border: 0; border-radius: 4px; background: transparent;
        color: #c3ccd9; font-size: 19px; line-height: 1; cursor: pointer; transition: background .16s, color .16s;
    }
    .jr-del:hover { background: var(--bad-bg); color: var(--bad); }
    .jr-filters label {
        display: block; margin-bottom: 6px;
        color: var(--ink-soft); font-size: 11px; font-weight: 500; letter-spacing: .05em;
    }
    .jr-filters select {
        width: 100%; height: 40px; padding: 0 11px;
        border: 1px solid #c4d0e0; border-radius: 5px; background: #fcfdff;
        color: var(--ink); font-family: inherit; font-size: 13.5px; outline: none;
    }
    .jr-filters select:focus { border-color: var(--navy-soft); box-shadow: 0 0 0 3px rgba(27,58,99,.1); }
    .jr-btn {
        height: 40px; padding: 0 22px; border: 0; border-radius: 5px;
        background: var(--navy); color: #fff;
        font-family: inherit; font-size: 13.5px; font-weight: 500;
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
        font-family: inherit; font-size: 24px; font-weight: 600; line-height: 1.1;
    }
    .jr-stat span {
        display: block; margin-top: 4px; color: var(--muted);
        font-size: 9.5px; font-weight: 600; letter-spacing: .13em; text-transform: uppercase;
    }
    .jr-stat.is-ok b { color: var(--ok); }

    /* ---- Guruhlar sharhi (kartochkalar) ---- */
    .jr-section-title {
        display: flex; align-items: baseline; justify-content: space-between; gap: 12px;
        margin: 4px 2px -4px; color: var(--navy); font-size: 13px; font-weight: 700;
        letter-spacing: .06em; text-transform: uppercase;
    }
    .jr-section-title span { color: var(--muted); font-size: 11.5px; font-weight: 500; letter-spacing: 0; text-transform: none; }
    .jr-groups { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 10px; }
    .jr-gcard {
        display: flex; flex-direction: column; gap: 10px;
        padding: 14px 16px; border: 1px solid var(--line); border-radius: 10px;
        background: #fff; color: inherit; text-decoration: none;
        transition: border-color .16s, box-shadow .16s, transform .16s;
    }
    .jr-gcard:hover { border-color: #b7c7dc; box-shadow: 0 6px 18px rgba(14, 35, 66, .08); transform: translateY(-1px); }
    .jr-gcard:focus-visible { outline: 2px solid var(--navy-soft); outline-offset: 2px; }
    .jr-gcard-top { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; }
    .jr-gcard-name { color: var(--navy); font-size: 15px; font-weight: 700; line-height: 1.25; word-break: break-word; }
    .jr-gcard-avg { flex: none; color: var(--navy); font-size: 20px; font-weight: 700; line-height: 1; }
    .jr-gcard-avg small { display: block; margin-top: 3px; color: var(--muted); font-size: 9.5px; font-weight: 600; letter-spacing: .1em; text-align: right; text-transform: uppercase; }
    .jr-gcard-meta { display: flex; flex-wrap: wrap; gap: 4px 12px; color: var(--ink-soft); font-size: 12px; }
    .jr-gcard-meta b { color: var(--navy); font-weight: 700; }
    .jr-gcard-meta .ok b { color: var(--ok); }
    .jr-gcard-meta .wait b { color: var(--warn); }

    /* O'tish ulushi chizig'i */
    .jr-bar { position: relative; height: 6px; border-radius: 999px; background: #edf1f7; overflow: hidden; }
    .jr-bar > i { position: absolute; inset: 0 auto 0 0; border-radius: inherit; background: linear-gradient(90deg, #12925f, var(--ok)); }
    .jr-bar.is-low > i { background: linear-gradient(90deg, #d9534a, var(--bad)); }
    .jr-bar.is-mid > i { background: linear-gradient(90deg, #e3a336, var(--warn)); }
    .jr-bar-label { display: flex; justify-content: space-between; margin-top: -4px; color: var(--muted); font-size: 10.5px; }

    /* Jadvaldagi foiz chizig'i */
    .jr-pct { display: flex; flex-direction: column; gap: 5px; min-width: 70px; }
    .jr-pct .jr-bar { height: 4px; }
    .jr-idx { color: var(--muted); font-size: 12px; font-variant-numeric: tabular-nums; }

    /* ---- Guruh bloki ---- */
    .jr-group {
        overflow: hidden; border: 1px solid var(--line); border-radius: 6px; background: #fff;
    }
    .jr-group-head {
        display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 12px;
        padding: 13px 20px; border-bottom: 1px solid var(--line-soft);
        background: linear-gradient(180deg, #fbfcfe, #f5f8fc);
        cursor: pointer; list-style: none;
    }
    .jr-group-head::-webkit-details-marker { display: none; }
    .jr-group:not([open]) > .jr-group-head { border-bottom: 0; }
    .jr-group-title { display: flex; align-items: center; gap: 10px; }
    .jr-group-title::before { content: '▾'; color: var(--muted); font-size: 11px; transition: transform .16s; }
    .jr-group:not([open]) .jr-group-title::before { transform: rotate(-90deg); }
    .jr-group-count { padding: 2px 9px; border-radius: 999px; background: #e8eef7; color: var(--navy-soft); font-size: 11px; font-weight: 700; }
    .jr-group-rate { display: flex; align-items: center; gap: 8px; min-width: 150px; color: var(--muted); font-size: 11px; }
    .jr-group-rate .jr-bar { flex: 1; }
    .jr-group { scroll-margin-top: 16px; }
    .jr-group-head b {
        color: var(--navy); font-family: inherit; font-size: 16px; font-weight: 600;
    }
    .jr-tags { display: flex; flex-wrap: wrap; gap: 16px; }
    .jr-tag { color: var(--muted); font-size: 11px; font-weight: 500; letter-spacing: .04em; }
    .jr-tag b { color: var(--navy); font-family: inherit; font-size: 13px; font-weight: 700; }
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
    .jr-flag {
        display: inline-block; margin-top: 4px; padding: 2px 8px; border-radius: 999px;
        font-size: 11px; font-weight: 700; white-space: nowrap;
    }
    .jr-flag.is-bad { background: #fee2e2; color: #b91c1c; }
    .jr-flag.is-warn { background: #fef3c7; color: #a35a06; }
    .jr-sid { display: block; margin-top: 2px; color: var(--muted); font-size: 11.5px; }

    .jr-pill {
        display: inline-flex; padding: 3px 11px; border-radius: 3px;
        font-size: 11px; font-weight: 500; letter-spacing: .03em;
    }
    .jr-pill.ok { background: var(--ok-bg); color: var(--ok); }
    .jr-pill.no { background: var(--bad-bg); color: var(--bad); }
    .jr-pill.wait { background: var(--warn-bg); color: var(--warn); }
    .jr-mini { display: block; margin-top: 3px; color: var(--muted); font-size: 10.5px; }

    .jr-num { color: var(--navy); font-family: inherit; font-size: 14px; font-weight: 600; }
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
    .jr-empty b { display: block; color: var(--navy); font-family: inherit; font-size: 16px; font-weight: 600; }
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
        .jr-reset, .jr-excel { justify-content: center; }
        .jr-table thead { display: none; }
        .jr-table td { display: block; padding: 6px 16px; border-bottom: 0; }
        .jr-table tbody tr { display: block; padding: 10px 0; border-bottom: 1px solid var(--line-soft); }
    }

    /* ============================================================
       Rasmiy ko'rinish qatlami — test yaratish sahifasi bilan bir xil
       (builder.blade.php). HTML va JS tegilmaydi.
       ============================================================ */

    .jr {
        --navy: #0e2342;
        --gold-soft: #f2e4bd;
        --gold-deep: #a8871d;
        --shadow-sm: 0 1px 2px rgba(14, 35, 66, .05);
        --shadow-md: 0 2px 6px rgba(14, 35, 66, .06), 0 14px 34px rgba(14, 35, 66, .07);
    }

    /* ---- Sarlavha ---- */
    .jr-head {
        position: relative; overflow: hidden;
        padding: 26px 30px;
        border-left-width: 5px; border-radius: 10px;
        background:
            radial-gradient(circle at 92% 130%, rgba(201, 162, 39, .13), transparent 58%),
            linear-gradient(180deg, #ffffff, #f2f7fc);
        box-shadow: var(--shadow-md);
    }
    /* Gerb konturi — builder sarlavhasidagidek */
    .jr-head::after {
        content: ''; position: absolute; right: -30px; bottom: -46px; width: 210px; height: 168px;
        background-repeat: no-repeat; background-position: center; background-size: contain; opacity: .06;
        background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%230e2342' stroke-width='1.2'><path d='M12 3 2 8v2h20V8L12 3Z'/><path d='M4 10v9M8 10v9M12 10v9M16 10v9M20 10v9'/><path d='M2 21h20'/></svg>");
        pointer-events: none;
    }
    .jr-eyebrow {
        display: inline-flex; align-items: center; gap: 7px;
        color: var(--gold-deep); letter-spacing: .22em; font-weight: 800;
    }
    .jr-eyebrow::before {
        content: ''; width: 18px; height: 2px; border-radius: 2px;
        background: linear-gradient(90deg, var(--gold), var(--gold-soft));
    }
    .jr-head h1 { font-size: 25px; letter-spacing: -.02em; }
    .jr-head p { max-width: 56ch; font-size: 13.5px; line-height: 1.6; }

    /* ---- Panellar va maydonlar ---- */
    .jr-panel {
        padding: 18px 22px; border-radius: 10px;
        background: linear-gradient(180deg, #ffffff, #fcfdff);
        box-shadow: var(--shadow-sm);
    }
    .jr-filters label {
        margin-bottom: 7px; color: var(--navy);
        font-size: 11px; font-weight: 700; letter-spacing: .07em; text-transform: uppercase;
    }
    .jr-filters input[type='search'], .jr-filters select {
        height: 42px; border-color: #d8e2ee; border-radius: 9px; background: #fcfdff;
    }
    .jr-filters input[type='search']:focus, .jr-filters select:focus {
        box-shadow: 0 0 0 3px rgba(27, 58, 99, .12);
    }
    .jr-btn {
        height: 42px; border-radius: 9px; font-weight: 600;
        background: linear-gradient(165deg, #1e4272, var(--navy));
        box-shadow: 0 5px 14px rgba(14, 35, 66, .24), inset 0 1px 0 rgba(255, 255, 255, .12);
    }
    .jr-btn:hover { box-shadow: 0 8px 20px rgba(14, 35, 66, .3); }
    .jr-reset { height: 42px; border-color: #d8e2ee; border-radius: 9px; }
    .jr-flash { border-radius: 9px; border-left-width: 4px; }

    /* ---- Jadval ---- */
    .jr-table th {
        background: #f4f8fc; color: #7b8ca6;
        font-size: 10px; letter-spacing: .14em;
    }
    .jr-table tbody tr:hover { background: #f7fafd; }

    /* ---- Bo'sh holat ---- */
    .jr-empty {
        border-radius: 11px;
        background: linear-gradient(180deg, #ffffff, #fafcfe);
    }

    /* ---- Tipografiya ---- */
    .jr h1, .jr h2, .jr .slab { letter-spacing: -.015em; }
    .jr-head h1 { font-size: 26px; font-weight: 600; }
    .jr-num, .jr-table td, .jr-stat { font-variant-numeric: tabular-nums; }
</style>

    <div class="jr py-6">
        <div class="w-full px-4 sm:px-6 lg:px-8" style="display:flex;flex-direction:column;gap:14px">

            @if(session('success'))
                <div class="jr-flash">{{ session('success') }}</div>
            @endif

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
                            <label for="subject_id">Mavzu (fan)</label>
                            <select name="subject_id" id="subject_id" onchange="this.form.querySelector('[name=test_id]').value=''; this.form.submit();">
                                <option value="">Barcha fanlar</option>
                                @foreach($subjectOptions ?? [] as $subjectOption)
                                    @php
                                        $optionLabel = collect([
                                            $subjectOption->subject_name,
                                            $subjectOption->semester_name,
                                            $subjectOption->curriculum_label ?? null,
                                        ])->filter()->implode(' · ');
                                    @endphp
                                    <option value="{{ $subjectOption->id }}" @selected((int) request('subject_id') === (int) $subjectOption->id)>
                                        {{ $optionLabel }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
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
                        <div>
                            <label for="student">Talaba (ism yoki ID)</label>
                            <input type="search" name="student" id="student" value="{{ request('student') }}" placeholder="Familiya, ism yoki ID raqami">
                        </div>
                        <button class="jr-btn" type="submit">Ko'rsatish</button>
                        @if($selected)
                            <a class="jr-excel" href="{{ route('teacher.fan-testlari.journal.excel', array_filter(['test_id' => $selected->id, 'group' => request('group'), 'student' => request('student')])) }}"
                               title="Tanlangan test natijalari va javoblari (guruh/talaba filtri bilan)">
                                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 10v6m0 0-3-3m3 3 3-3M6 20h12a2 2 0 0 0 2-2V8l-6-6H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2Z"/></svg>
                                Excel
                            </a>
                        @endif
                        @if(request()->hasAny(['group', 'student', 'subject_id']))
                            <a class="jr-reset" href="{{ route('teacher.fan-testlari.journal', ['test_id' => $selected?->id]) }}">Tozalash</a>
                        @endif
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

                    @if($groups->isNotEmpty())
                        <div class="jr-section-title">Guruhlar <span>{{ $groups->count() }} ta guruh · bosib, guruh natijalariga o'ting</span></div>
                        <div class="jr-groups">
                            @foreach($groups as $group)
                                @php $rateClass = $group['pass_rate'] < 50 ? 'is-low' : ($group['pass_rate'] < 75 ? 'is-mid' : ''); @endphp
                                <a class="jr-gcard" href="#grp-{{ $loop->index }}">
                                    <div class="jr-gcard-top">
                                        <span class="jr-gcard-name">{{ $group['name'] }}</span>
                                        <span class="jr-gcard-avg">{{ $group['average_percent'] }}%<small>o'rtacha</small></span>
                                    </div>
                                    <div class="jr-gcard-meta">
                                        <span><b>{{ $group['submitted_count'] }}</b> topshirgan</span>
                                        <span class="ok"><b>{{ $group['passed_count'] }}</b> o'tgan</span>
                                        @if($group['in_progress_count'] > 0)
                                            <span class="wait"><b>{{ $group['in_progress_count'] }}</b> ishlamoqda</span>
                                        @endif
                                    </div>
                                    <div class="jr-bar {{ $rateClass }}" role="img" aria-label="O'tish ulushi {{ $group['pass_rate'] }}%"><i style="width: {{ $group['pass_rate'] }}%"></i></div>
                                    <div class="jr-bar-label"><span>O'tish ulushi</span><span>{{ $group['pass_rate'] }}%</span></div>
                                </a>
                            @endforeach
                        </div>
                        <div class="jr-section-title">Talabalar natijalari <span>guruhlar bo'yicha</span></div>
                    @endif

                    @forelse($groups as $group)
                        @php $rateClass = $group['pass_rate'] < 50 ? 'is-low' : ($group['pass_rate'] < 75 ? 'is-mid' : ''); @endphp
                        <details class="jr-group" id="grp-{{ $loop->index }}" open>
                            <summary class="jr-group-head">
                                <span class="jr-group-title">
                                    <b>{{ $group['name'] }}</b>
                                    <span class="jr-group-count">{{ $group['attempts']->count() }} talaba</span>
                                </span>
                                <div class="jr-tags">
                                    <span class="jr-group-rate">
                                        <span class="jr-bar {{ $rateClass }}"><i style="width: {{ $group['pass_rate'] }}%"></i></span>
                                        {{ $group['pass_rate'] }}% o'tdi
                                    </span>
                                    <span class="jr-tag"><b>{{ $group['submitted_count'] }}</b> topshirgan</span>
                                    <span class="jr-tag is-ok"><b>{{ $group['passed_count'] }}</b> o'tgan</span>
                                    <span class="jr-tag">o'rtacha <b>{{ $group['average_percent'] }}%</b></span>
                                </div>
                            </summary>

                            <table class="jr-table">
                                <thead>
                                <tr>
                                    <th style="width:44px">№</th>
                                    <th style="width:26%">Talaba</th>
                                    <th style="width:11%">Holat</th>
                                    <th style="width:9%">Ball</th>
                                    <th style="width:8%">Foiz</th>
                                    <th style="width:14%">Topshirgan vaqti</th>
                                    <th>Javoblari</th>
                                </tr>
                                </thead>
                                <tbody>
                                @foreach($group['attempts'] as $attempt)
                                    <tr>
                                        <td><span class="jr-idx">{{ $loop->iteration }}</span></td>
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
                                            {{-- Test davomidagi yuz kuzatuvi --}}
                                            @if((int) $attempt->face_mismatches > 0)
                                                <span class="jr-flag is-bad" title="Test davomida jim olingan suratlar talabaning rasmiga mos kelmadi">Yuz mos emas: {{ $attempt->face_mismatches }}/{{ $attempt->face_checks }}</span>
                                            @endif
                                            @if((int) $attempt->away_count > 0)
                                                <span class="jr-flag is-warn" title="Monitordan chalg'igani uchun test to'xtatilgan holatlar">Chalg'idi: {{ $attempt->away_count }} marta</span>
                                            @endif
                                            @if(!empty($attempt->face_bypass_by))
                                                <span class="jr-flag is-warn" title="O'qituvchi bir martalik ruxsati bilan yuz tekshiruvisiz kirgan">Yuz tekshiruvisiz: {{ $attempt->face_bypass_by }}</span>
                                            @endif
                                            @if(($attempt->ended_reason ?? null) === 'away')
                                                <span class="jr-flag is-bad" title="Ochib berilgandan keyin yana chalg'igani uchun test avtomatik yakunlangan">Chalg'igani uchun yakunlandi</span>
                                            @elseif($attempt->locked_at && $attempt->status === 'in_progress')
                                                <span class="jr-flag is-bad" title="O'qituvchi ochib berishini kutmoqda">Qulflangan</span>
                                            @elseif((int) ($attempt->lock_count ?? 0) === 1)
                                                <span class="jr-flag is-warn" title="Chalg'igani uchun bir marta qulflangan va o'qituvchi ochib bergan">1 marta qulflangan</span>
                                            @endif
                                        </td>
                                        <td><span class="jr-num">{{ (int) $attempt->score }} / {{ $attempt->total_points }}</span></td>
                                        <td>
                                            <div class="jr-pct">
                                                <span class="jr-num {{ !$attempt->is_passed && $attempt->status !== 'in_progress' ? 'is-bad' : '' }}">
                                                    {{ $attempt->percent !== null ? rtrim(rtrim(number_format((float) $attempt->percent, 1, '.', ''), '0'), '.') . '%' : '—' }}
                                                </span>
                                                @if($attempt->percent !== null && $attempt->status !== 'in_progress')
                                                    <span class="jr-bar {{ $attempt->is_passed ? '' : 'is-low' }}"><i style="width: {{ min(100, max(0, (float) $attempt->percent)) }}%"></i></span>
                                                @endif
                                            </div>
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
                        </details>
                    @empty
                        <div class="jr-empty">
                            <b>Hali hech kim topshirmagan</b>
                            <span>Test boshlangach, topshirgan talabalar shu yerda o'z guruhlari bo'yicha chiqadi.</span>
                        </div>
                    @endforelse
                @endif
            @endif
        </div>
    </div>

</x-app-layout>
