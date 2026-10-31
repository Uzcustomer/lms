<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ !empty($multi) ? "Yig'ma tekshiruv — " . count($multi) . ' ta vedomost' : 'Tekshiruv — ' . ($merged->group_name ?? $v->group_name) . ' · ' . ($merged->subject_name ?? $v->subject_name) }}</title>
    <style>
        :root {
            --xl: #217346; --xl-dark: #185c37; --xl-soft: #e7f2ec;
            --grid: #d4d4d4; --hdr: #f3f3f3; --hdr-text: #444; --head: #d9e1f2;
            --yellow: #ffff00; --orange: #f6c98a; --light: #ffffcc; --edit: #2563eb; --edit-soft: #dbeafe;
            --text: #1f2328; --muted: #6b7280; --surface: #fff; --bg: #f5f6f7;
            --font: Calibri, "Segoe UI", Inter, ui-sans-serif, system-ui, -apple-system, Roboto, Arial, sans-serif;
        }
        * { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; }
        body { font-family: var(--font); font-size: 14px; color: var(--text); background: var(--bg); display: flex; flex-direction: column; height: 100dvh; overflow: hidden; }
        button, input { font-family: inherit; font-size: inherit; }
        .icon { width: 16px; height: 16px; flex-shrink: 0; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
        :focus-visible { outline: 2px solid var(--xl); outline-offset: 1px; }

        /* Title bar */
        .titlebar { display: flex; align-items: center; gap: 12px; padding: 8px 16px; background: var(--xl); color: #fff; }
        .titlebar a.back { display: inline-flex; width: 32px; height: 32px; align-items: center; justify-content: center; border-radius: 6px; color: #fff; text-decoration: none; }
        .titlebar a.back:hover { background: rgba(255,255,255,.15); }
        .titlebar .t { flex: 1; min-width: 0; }
        .titlebar h1 { font-size: 15px; font-weight: 600; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .titlebar .sub { font-size: 12px; opacity: .85; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .verdict { display: inline-flex; align-items: center; gap: 6px; padding: 4px 12px; border-radius: 999px; font-weight: 700; font-size: 13px; background: #fff; white-space: nowrap; }
        .verdict.ok { color: #166534; } .verdict.issues { color: #92400e; } .verdict.review { color: #9a3412; } .verdict.wrong_document { color: #b91c1c; }
        .tbtn { display: inline-flex; align-items: center; gap: 6px; height: 32px; padding: 0 12px; border-radius: 6px; border: 1px solid rgba(255,255,255,.5); color: #fff; background: transparent; text-decoration: none; font-weight: 600; font-size: 13px; cursor: pointer; white-space: nowrap; }
        .tbtn:hover { background: rgba(255,255,255,.15); }
        .jdrop { position: relative; }
        .jdrop summary { list-style: none; }
        .jdrop summary::-webkit-details-marker { display: none; }
        .jdrop-menu { position: absolute; right: 0; top: calc(100% + 6px); z-index: 70; min-width: 220px; max-height: 360px; overflow: auto; background: #fff; border: 1px solid #d1d5db; border-radius: 8px; box-shadow: 0 10px 30px rgba(0,0,0,.18); padding: 4px; }
        .jdrop-menu a { display: block; padding: 6px 10px; border-radius: 6px; color: #1f2937; text-decoration: none; font-size: 13px; white-space: nowrap; }
        .jdrop-menu a:hover { background: #eef2ff; }
        .tbtn.save { background: #fff; color: var(--xl); border-color: #fff; }
        .tbtn.save:hover { background: #e8f3ec; }
        .tbtn.save:disabled { opacity: .55; cursor: default; background: #fff; }
        .tbtn.save .n { background: var(--xl); color: #fff; border-radius: 999px; padding: 0 7px; font-size: 11px; line-height: 18px; }

        /* Ribbon */
        .ribbon { display: flex; align-items: center; gap: 10px; padding: 8px 16px; background: var(--surface); border-bottom: 1px solid var(--grid); flex-wrap: wrap; }
        .summary { flex-basis: 100%; font-size: 13px; color: #374151; }
        .chips { display: flex; gap: 6px; flex-wrap: wrap; }
        .chip { display: inline-flex; align-items: center; gap: 6px; height: 30px; padding: 0 12px; border: 1px solid #d1d5db; border-radius: 4px; background: #fff; color: #374151; font-weight: 600; font-size: 13px; cursor: pointer; }
        .chip .n { font-variant-numeric: tabular-nums; color: var(--muted); }
        .chip .sw { width: 12px; height: 12px; border: 1px solid rgba(0,0,0,.2); }
        .chip[aria-pressed="true"] { border-color: var(--xl); background: var(--xl-soft); color: var(--xl-dark); }
        .spacer { flex: 1; }
        .toggle { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: #374151; cursor: pointer; user-select: none; }
        .toggle input { accent-color: var(--xl); }
        .search { position: relative; }
        .search input { height: 30px; width: 240px; padding: 0 10px 0 30px; border: 1px solid #d1d5db; border-radius: 4px; }
        .search .icon { position: absolute; left: 9px; top: 7px; color: var(--muted); }

        /* Formula bar */
        .fbar { display: flex; align-items: stretch; background: var(--surface); border-bottom: 1px solid var(--grid); font-size: 13px; min-height: 30px; }
        .fbar .ref { width: 90px; padding: 6px 10px; border-right: 1px solid var(--grid); color: #374151; font-weight: 600; font-variant-numeric: tabular-nums; }
        .fbar .fx { padding: 6px 10px; border-right: 1px solid var(--grid); color: var(--muted); font-style: italic; }
        .fbar .val { flex: 1; padding: 6px 10px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

        .banner { display: flex; gap: 10px; padding: 10px 16px; background: #fee2e2; color: #991b1b; border-bottom: 1px solid #fecaca; font-size: 14px; }

        /* Sheets */
        .sheet-area { flex: 1; min-height: 0; position: relative; background: #fff; }
        .sheet { position: absolute; inset: 0; overflow: auto; display: none; }
        .sheet.active { display: block; }

        table.xl { border-collapse: separate; border-spacing: 0; font-size: 13px; font-variant-numeric: tabular-nums; }
        .xl th, .xl td { border-right: 1px solid var(--grid); border-bottom: 1px solid var(--grid); padding: 0 6px; height: 24px; white-space: nowrap; background: #fff; }
        .xl td { text-align: right; }
        .xl td.txt { text-align: left; }
        .xl thead tr.letters th { position: sticky; top: 0; z-index: 4; background: var(--hdr); color: var(--hdr-text); font-weight: 400; font-size: 12px; height: 22px; text-align: center; }
        .xl thead tr.names th { position: sticky; top: 22px; z-index: 4; background: #fff; font-weight: 700; text-align: center; white-space: normal; line-height: 1.2; height: 34px; min-width: 48px; }
        .xl thead tr.names th.h { background: var(--head); }
        .xl .rn { position: sticky; left: 0; z-index: 3; background: var(--hdr) !important; color: var(--hdr-text); text-align: center; width: 40px; min-width: 40px; font-size: 12px; }
        .xl thead tr th.rn { z-index: 7; }
        .xl .c-a { position: sticky; left: 40px; z-index: 2; width: 36px; min-width: 36px; text-align: center; }
        .xl .c-b { position: sticky; left: 76px; z-index: 2; width: 300px; min-width: 300px; max-width: 300px; overflow: hidden; text-overflow: ellipsis; text-align: left; }
        .xl .c-c { position: sticky; left: 376px; z-index: 2; width: 116px; min-width: 116px; text-align: left; border-right: 2px solid #9ca3af; }
        .xl thead tr th.c-a, .xl thead tr th.c-b, .xl thead tr th.c-c { z-index: 6; }
        .xl td.y { background: var(--yellow); }
        .xl td.o { background: var(--orange); }
        .xl td.l { background: var(--light); }
        .xl td.grey { color: #8a8a8a; font-style: italic; text-align: left; }
        .xl td.sep { background: #fafafa; min-width: 14px; }
        .xl td.bq { min-width: 72px; position: relative; }
        .xl td.bq.ed { cursor: cell; }
        .xl td.sys { cursor: cell; box-shadow: inset 0 0 0 1px #c4b5fd; }
        .xl td.sys-edited { background: #ede9fe !important; color: #5b21b6; font-weight: 700; }
        .xl td.sys-dirty { box-shadow: inset 0 0 0 2px #7c3aed; color: #5b21b6; font-weight: 700; }
        .chip.sysmode[aria-pressed="true"] { border-color: #7c3aed; background: #ede9fe; color: #5b21b6; }
        .why-dlg { border: none; border-radius: 10px; padding: 0; width: min(440px, 92vw); box-shadow: 0 20px 50px rgba(0,0,0,.25); }
        .why-dlg::backdrop { background: rgba(15,23,42,.45); }
        .why-dlg form { padding: 18px; display: flex; flex-direction: column; gap: 10px; }
        .why-dlg h3 { margin: 0; font-size: 15px; }
        .why-dlg p { margin: 0; font-size: 13px; color: var(--muted); }
        .why-dlg textarea { width: 100%; min-height: 80px; border: 1px solid #d1d5db; border-radius: 6px; padding: 8px; font: inherit; }
        .why-dlg .acts { display: flex; justify-content: flex-end; gap: 8px; }
        .why-dlg button { height: 34px; padding: 0 14px; border-radius: 6px; border: 1px solid #d1d5db; background: #fff; font-weight: 600; cursor: pointer; }
        .why-dlg button.primary { background: #7c3aed; border-color: #7c3aed; color: #fff; }
        .xl td.edited::before { content: ""; position: absolute; top: 0; right: 0; border-style: solid; border-width: 0 8px 8px 0; border-color: transparent var(--edit) transparent transparent; }
        .xl td.dirty { box-shadow: inset 0 0 0 2px var(--edit); font-weight: 700; color: var(--edit); }
        .xl td.sel { box-shadow: inset 0 0 0 2px var(--xl); }
        .xl td input.ci { width: 100%; min-width: 56px; height: 22px; border: none; outline: none; text-align: right; background: #fff; padding: 0; }
        .xl td.note { text-align: left; min-width: 320px; max-width: 560px; overflow: hidden; text-overflow: ellipsis; font-size: 12px; }
        .xl.wrap td.note { white-space: normal; line-height: 1.35; padding: 3px 6px; }
        .xl tr.flash td { animation: flash 1.4s ease-out; }
        @keyframes flash { 0% { box-shadow: inset 0 0 0 9999px rgba(33,115,70,.25); } 100% { box-shadow: none; } }
        .st { display: inline-flex; align-items: center; gap: 4px; font-weight: 700; }
        .st .icon { width: 13px; height: 13px; }
        .st.ok { color: #166534; } .st.bad { color: #92400e; } .st.ocr { color: #9a3412; } .st.missing, .st.nopdf { color: #b91c1c; }
        .empty { padding: 40px; text-align: center; color: var(--muted); }
        .pad { padding: 16px; }
        .card { border: 1px solid var(--grid); border-radius: 6px; margin-bottom: 16px; overflow: hidden; background: #fff; }
        .card h2 { margin: 0; font-size: 14px; padding: 10px 14px; background: #fafafa; border-bottom: 1px solid var(--grid); display: flex; align-items: center; gap: 8px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; padding: 14px; }
        .form-grid label { display: flex; flex-direction: column; gap: 4px; font-size: 12px; color: var(--muted); font-weight: 600; }
        .form-grid input[type=text], .form-grid input[type=number] { height: 32px; border: 1px solid #d1d5db; border-radius: 4px; padding: 0 8px; font-size: 14px; color: var(--text); }
        .form-grid input.dirty { box-shadow: 0 0 0 2px var(--edit); }
        .sig { display: flex; align-items: center; gap: 8px; padding: 8px 10px; border: 1px solid #e5e7eb; border-radius: 6px; font-size: 14px; color: var(--text); cursor: pointer; }
        .sig input { width: 18px; height: 18px; accent-color: var(--xl); }
        .sig.dirty { box-shadow: 0 0 0 2px var(--edit); }
        .meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1px; background: var(--grid); }
        .meta-grid div { background: #fff; padding: 9px 14px; font-size: 13px; }
        .meta-grid span { display: block; font-size: 11px; color: var(--muted); font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }
        .log { padding: 10px 14px; border-bottom: 1px solid #eee; font-size: 13px; }
        .log .who { color: var(--muted); font-size: 12px; margin-bottom: 3px; }
        .log pre { margin: 0; white-space: pre-wrap; font-family: inherit; }
        .link { color: var(--xl); background: none; border: none; padding: 0; cursor: pointer; text-decoration: underline; font-weight: 600; font-size: 12px; }

        /* Save bar + tabs */
        .savebar { display: none; align-items: center; gap: 12px; padding: 8px 16px; background: #1f2937; color: #fff; font-size: 13px; }
        .savebar.show { display: flex; }
        .sbtn { height: 30px; padding: 0 14px; border-radius: 4px; border: 1px solid #4b5563; background: transparent; color: #e5e7eb; font-weight: 600; cursor: pointer; }
        .sbtn.primary { background: var(--xl); border-color: var(--xl); color: #fff; }
        .sbtn.primary:hover { background: var(--xl-dark); }
        .sbtn:disabled { opacity: .6; cursor: wait; }
        .tabs { display: flex; align-items: stretch; background: var(--hdr); border-top: 1px solid var(--grid); min-height: 34px; padding-left: 8px; }
        .tab { padding: 0 16px; border: none; background: transparent; color: #374151; font-size: 13px; cursor: pointer; border-bottom: 3px solid transparent; white-space: nowrap; }
        .tab:hover { background: #e9e9e9; }
        .tab[aria-selected="true"] { background: #fff; color: var(--xl-dark); font-weight: 700; border-bottom-color: var(--xl); }
        .tab .n { display: inline-block; margin-left: 6px; min-width: 18px; padding: 0 5px; border-radius: 9px; background: var(--yellow); color: #333; font-size: 11px; font-weight: 700; }
        .legend { display: flex; align-items: center; gap: 12px; margin-left: auto; padding: 0 12px; font-size: 12px; color: #374151; }
        .legend i { display: inline-block; width: 12px; height: 12px; border: 1px solid rgba(0,0,0,.2); margin-right: 4px; vertical-align: -2px; }
        .toast { position: fixed; right: 16px; bottom: 60px; padding: 10px 14px; border-radius: 6px; background: #111827; color: #fff; font-size: 13px; opacity: 0; transform: translateY(6px); transition: opacity .2s, transform .2s; pointer-events: none; z-index: 50; }
        .toast.show { opacity: 1; transform: none; } .toast.err { background: #991b1b; }
        .xl th.cf-th { position: sticky; }
        .xl th.cf-th span { display: block; padding-right: 14px; }
        .cf-btn { position: absolute; right: 2px; bottom: 2px; width: 16px; height: 16px; padding: 0; border: 1px solid #b6bcc6; border-radius: 3px; background: #fff; color: #374151; font-size: 10px; line-height: 14px; cursor: pointer; }
        .cf-btn:hover { background: #eef2ff; }
        .cf-btn.on { background: var(--xl); border-color: var(--xl); color: #fff; }
        .cf-pop { position: fixed; z-index: 60; width: 280px; background: #fff; border: 1px solid #c7ccd4; border-radius: 6px; box-shadow: 0 10px 30px rgba(0,0,0,.18); padding: 8px; font-size: 13px; }
        .cf-sort { display: flex; flex-direction: column; gap: 2px; border-bottom: 1px solid #eee; padding-bottom: 6px; margin-bottom: 6px; }
        .cf-sort button { text-align: left; border: none; background: none; padding: 4px 6px; border-radius: 4px; cursor: pointer; font: inherit; }
        .cf-sort button:hover { background: #f3f4f6; }
        .cf-q { width: 100%; height: 28px; border: 1px solid #d1d5db; border-radius: 4px; padding: 0 8px; font: inherit; }
        .cf-all { display: flex; gap: 6px; align-items: center; padding: 6px 2px 4px; font-weight: 700; }
        .cf-list { max-height: 240px; overflow: auto; border: 1px solid #eee; border-radius: 4px; }
        .cf-list label { display: flex; gap: 6px; align-items: center; padding: 3px 6px; cursor: pointer; }
        .cf-list label:hover { background: #f8fafc; }
        .cf-list label span { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .cf-list label i { font-style: normal; color: var(--muted); font-size: 11px; }
        .cf-acts { display: flex; gap: 6px; margin-top: 8px; }
        .cf-acts button { height: 28px; padding: 0 10px; border: 1px solid #d1d5db; border-radius: 4px; background: #fff; cursor: pointer; font: inherit; }
        .cf-acts .cf-ok { background: var(--xl); border-color: var(--xl); color: #fff; }
        .cf-info { font-size: 12px; color: var(--xl-dark); display: inline-flex; gap: 6px; align-items: center; }
        .cf-info[hidden], .cf-list label[hidden] { display: none; }
        .xl td.grp { color: #374151; font-size: 12px; background: #fafafa; }
        .xl td.src { max-width: 220px; min-width: 160px; overflow: hidden; text-overflow: ellipsis; color: #374151; font-size: 12px; background: #fafafa; }
        .xl tr.src-first td { border-top: 2px solid #9ca3af; }
        @media (max-width: 900px) {
            .titlebar { flex-wrap: wrap; } .search, .search input { width: 100%; } .legend { display: none; }
            .xl .c-b { width: 170px; min-width: 170px; max-width: 170px; } .xl .c-c { left: auto; border-right: 1px solid var(--grid); } .xl td.c-c { position: static; }
            .tabs { overflow-x: auto; }
        }
        @media (prefers-reduced-motion: reduce) { * { animation: none !important; transition: none !important; } }
    </style>
</head>
<body>
@php
    $multi = $multi ?? null;
    $r = $report;
    $c = $r['counts'];
    $verdictTxt = ['ok' => 'Mos keladi', 'issues' => 'Nomuvofiqlik bor', 'review' => "Ko'z bilan tekshirish kerak", 'wrong_document' => "Noto'g'ri fayl"][$r['verdict']] ?? $r['verdict'];
    $problems = $c['bad'] + $c['ocr'] + $c['missing'] + $c['nopdf'];
    $hdrBad = collect($r['header'])->where('st', 'bad')->count();
@endphp
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
    <symbol id="i-back" viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></symbol>
    <symbol id="i-check" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></symbol>
    <symbol id="i-alert" viewBox="0 0 24 24"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0zM12 9v4M12 17h.01"/></symbol>
    <symbol id="i-eye" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></symbol>
    <symbol id="i-minus" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M8 12h8"/></symbol>
    <symbol id="i-file" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/></symbol>
    <symbol id="i-download" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></symbol>
    <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></symbol>
    <symbol id="i-pencil" viewBox="0 0 24 24"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></symbol>
    <symbol id="i-info" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></symbol>
</svg>

<header class="titlebar">
    @if($multi)
    <a class="back" href="{{ route('admin.vedomost-submission.index') }}" aria-label="Vedomostlar ro'yxatiga qaytish"><svg class="icon"><use href="#i-back"/></svg></a>
    <div class="t">
        <h1>Yig'ma tekshiruv — {{ count($multi) }} ta vedomost</h1>
        <div class="sub" title="{{ collect($multi)->pluck('label')->implode("\n") }}">{{ collect($multi)->pluck('label')->implode(' · ') }}</div>
    </div>
    @else
    <a class="back" href="{{ route('admin.vedomost-submission.show', $v->id) }}" aria-label="Vedomost sahifasiga qaytish"><svg class="icon"><use href="#i-back"/></svg></a>
    <div class="t">
        <h1>{{ $merged->group_name ?? $v->group_name }} — {{ $merged->subject_name ?? $v->subject_name }}</h1>
        <div class="sub">
            {{ \App\Models\VedomostSubmission::formLabel($v->form_type) }} · AI tekshiruv {{ $v->ai_checked_at ? \Carbon\Carbon::parse($v->ai_checked_at)->format('d.m.Y H:i') : '' }}
            @if($model) · {{ $model }}@endif
            @unless($canEdit) · faqat ko'rish @endunless
        </div>
    </div>
    @endif
    <span class="verdict {{ $r['verdict'] }}"><svg class="icon"><use href="#{{ $r['verdict'] === 'ok' ? 'i-check' : 'i-alert' }}"/></svg>{{ $verdictTxt }}</span>
    @if($multi)
        <button type="button" class="tbtn" data-open-tab="approve"><svg class="icon"><use href="#i-check"/></svg>Tasdiqlash</button>
    @else
    @if($v->pdf_path)
        <a class="tbtn" href="{{ route('admin.vedomost-submission.file', [$v->id, 'pdf']) }}" target="_blank" rel="noopener"><svg class="icon"><use href="#i-file"/></svg>PDF</a>
    @endif
    <a class="tbtn" href="{{ route('admin.vedomost-submission.ai-report.excel', $v->id) }}"><svg class="icon"><use href="#i-download"/></svg>Excel</a>
    @php $journalLinks = rescue(fn () => app(\App\Services\VedomostMergeService::class)->journalLinks($v), [], false); @endphp
    @if(count($journalLinks) === 1)
        <a class="tbtn" href="{{ $journalLinks[0]['url'] }}" target="_blank" rel="noopener" title="{{ $journalLinks[0]['label'] }} — jurnal (yangi oynada)">📖 Jurnal</a>
    @elseif(count($journalLinks) > 1)
        <details class="jdrop">
            <summary class="tbtn">📖 Jurnal ({{ count($journalLinks) }})</summary>
            <div class="jdrop-menu">
                @foreach($journalLinks as $jl)<a href="{{ $jl['url'] }}" target="_blank" rel="noopener">{{ $jl['label'] }} ↗</a>@endforeach
            </div>
        </details>
    @endif
    @endif
    @if($canEdit)
        <button type="button" class="tbtn save" id="btn-save-top" disabled title="Tuzatishlarni saqlash (Ctrl+S)"><svg class="icon"><use href="#i-pencil"/></svg><span class="lbl">Saqlash</span><span class="n" id="save-top-n" hidden>0</span></button>
    @endif
</header>

<section class="ribbon" aria-label="Filtrlar">
    <div class="summary">{{ $r['summary'] }}</div>
    <div class="chips" role="group" aria-label="Qatorlar filtri">
        <button class="chip" data-filter="problems">Muammolilar <span class="n">{{ $problems }}</span></button>
        <button class="chip" data-filter="all">Hammasi <span class="n">{{ $c['total'] }}</span></button>
        <button class="chip" data-filter="bad"><span class="sw" style="background:var(--yellow)"></span>XATO <span class="n">{{ $c['bad'] }}</span></button>
        <button class="chip" data-filter="ocr"><span class="sw" style="background:var(--orange)"></span>Vedomost PDFdan tekshiring <span class="n">{{ $c['ocr'] }}</span></button>
        <button class="chip" data-filter="nopdf"><span class="sw" style="background:#fecaca"></span>Vedomost PDFda yo'q <span class="n">{{ $c['nopdf'] }}</span></button>
        <button class="chip" data-filter="missing"><span class="sw" style="background:#fecaca"></span>Kontingentda yo'q <span class="n">{{ $c['missing'] }}</span></button>
        <button class="chip" data-filter="edited"><span class="sw" style="background:var(--edit)"></span>Tuzatilgan <span class="n">{{ $c['edited'] }}</span></button>
    </div>
    <div class="spacer"></div>
    @if($canEdit)
        <button class="chip sysmode" id="sys-mode" aria-pressed="false" title="Mark qiymatlarini faqat shu hisobotda o'zgartirish (sabab majburiy). Jurnal, Mark va HEMIS'ga yozilmaydi.">
            <span class="sw" style="background:#7c3aed"></span>Mark qiymatlarini tahrirlash @if(!empty($c['sys_edited']))<span class="n">{{ $c['sys_edited'] }}</span>@endif
        </button>
    @endif
    <span class="cf-info" id="cf-info" hidden><b></b> <button type="button" class="link" id="cf-reset">Ustun filtrlarini tozalash</button></span>
    <label class="toggle"><input type="checkbox" id="all-cols"> Barcha Mark ustunlari</label>
    <div class="search"><svg class="icon"><use href="#i-search"/></svg><input type="search" id="search" placeholder="F.I.Sh yoki ID" aria-label="Qidirish"></div>
</section>

<div class="fbar" aria-live="polite"><div class="ref" id="f-ref">—</div><div class="fx">fx</div><div class="val" id="f-val">Katakni tanlang — PDF (BQ) kataklarini Enter yoki ikki marta bosib tahrirlang.</div></div>

@if($r['verdict'] === 'wrong_document')
    <div class="banner" role="alert"><svg class="icon" style="width:20px;height:20px"><use href="#i-alert"/></svg><div><b>Yuklangan PDF bu vedomostga tegishli emas ko'rinadi.</b> {{ $r['summary'] }}</div></div>
@endif

<main class="sheet-area">
    <section class="sheet" id="sheet-main" role="tabpanel" aria-label="Tekshirish"></section>
    <section class="sheet" id="sheet-header" role="tabpanel" aria-label="Sarlavha"></section>
    <section class="sheet" id="sheet-errors" role="tabpanel" aria-label="Xatolar"></section>
    <section class="sheet" id="sheet-pdf" role="tabpanel" aria-label="pdf (BQ)"></section>
    <section class="sheet" id="sheet-history" role="tabpanel" aria-label="Tarix">
        <div class="pad">
            @php $m = $r['meta'] ?? []; @endphp
            @unless($multi)
            <div class="card">
                <h2><svg class="icon"><use href="#i-info"/></svg>AI o'qish ma'lumotlari</h2>
                <div class="meta-grid">
                    <div><span>Rejim</span>{{ ($m['mode'] ?? '') === 'images' ? 'Rasm, ' . ($m['bands'] ?? 1) . " bo'lak" : "Butun PDF (bo'laklashsiz)" }}</div>
                    <div><span>Model</span>{{ $m['model'] ?? $model ?? '—' }}</div>
                    <div><span>O'qilgan qatorlar</span>{{ $m['rows_read'] ?? '—' }}@if(!empty($m['rows_expected'])) / {{ $m['rows_expected'] }}@endif</div>
                    <div><span>Qayta o'qib tuzatildi</span>{{ $m['repaired'] ?? 0 }} qator</div>
                    <div><span>O'qilmagan qatorlar</span>{{ empty($m['missing_nrs']) ? "yo'q" : implode(', ', $m['missing_nrs']) }}</div>
                    <div><span>Noaniq kataklar</span>{{ $m['unclear_cells'] ?? 0 }}</div>
                    <div><span>Keshdan</span>{{ !empty($m['cached']) ? 'ha' : "yo'q" }}</div>
                    <div><span>O'qilgan vaqt</span>{{ $m['read_at'] ?? '—' }}</div>
                </div>
            </div>
            @endunless
            <div class="card">
                <h2><svg class="icon"><use href="#i-pencil"/></svg>Xodim tuzatishlari tarixi</h2>
                @forelse($history as $log)
                    <div class="log"><div class="who">{{ $log->created_at->format('d.m.Y H:i') }} · {{ $log->user_name }}</div><pre>{{ $log->note }}</pre></div>
                @empty
                    <div class="empty">Hali tuzatish kiritilmagan.</div>
                @endforelse
            </div>
        </div>
    </section>
    @if($multi)
    <section class="sheet" id="sheet-approve" role="tabpanel" aria-label="Tasdiqlash">
        <div class="pad">
            @if(session('success'))<div class="card" style="padding:10px 14px;background:#dcfce7;color:#166534;white-space:pre-line">{{ session('success') }}</div>@endif
            @if(session('error'))<div class="card" style="padding:10px 14px;background:#fee2e2;color:#991b1b;white-space:pre-line">{{ session('error') }}</div>@endif
            <form method="POST" action="{{ route('admin.vedomost-submission.ai-review.approve') }}" id="approve-form">
                @csrf
                <div class="card">
                    <h2><svg class="icon"><use href="#i-check"/></svg>Vedomostlar — belgilanganlar tasdiqlanadi
                        <span style="flex:1"></span>
                        @if($canApprove)<button type="submit" class="sbtn primary" id="btn-approve" disabled>Belgilanganlarni tasdiqlash (<span id="ap-n">0</span>)</button>@endif
                    </h2>
                    <table class="xl" style="width:100%">
                        <thead><tr class="names"><th class="rn"></th><th class="h">Fan · guruh</th><th class="h">Shakl</th><th class="h">AI natijasi</th><th class="h" style="width:340px">Tasdiqlash</th><th class="h">Havolalar</th></tr></thead>
                        <tbody>
                        @foreach($multi as $i => $mv)
                            @php $can = $canApprove && $mv['approvable'] && !$mv['gate']['blocked']; @endphp
                            <tr>
                                <td class="rn">{{ $i + 1 }}</td>
                                <td class="txt"><b>{{ $mv['label'] }}</b></td>
                                <td class="txt">{{ $mv['form'] }}</td>
                                <td class="note {{ $mv['verdict'] === 'ok' ? '' : ($mv['checked'] ? 'y' : 'o') }}" style="max-width:420px;white-space:normal">{{ $mv['summary'] }}</td>
                                <td class="txt" style="white-space:normal">
                                    @if($can)
                                        <label style="display:flex;align-items:center;gap:8px;font-weight:700;color:#166534;cursor:pointer">
                                            <input type="checkbox" name="approve[]" value="{{ $mv['id'] }}" class="ap-cb" data-vid="{{ $mv['id'] }}" {{ $mv['verdict'] === 'ok' && !$mv['gate']['needs_reason'] ? 'checked' : '' }}> Tasdiqlash
                                        </label>
                                        @if($mv['gate']['needs_reason'])
                                            <textarea name="reasons[{{ $mv['id'] }}]" class="ap-why" data-vid="{{ $mv['id'] }}" placeholder="Sabab (majburiy, kamida 5 belgi)" style="width:100%;margin-top:4px;min-height:34px;border:1px solid #d1d5db;border-radius:4px;padding:5px 7px;font:inherit;font-size:13px"></textarea>
                                            <div style="font-size:11px;color:#92400e">{{ $mv['gate']['message'] }}</div>
                                        @endif
                                    @else
                                        <span style="color:var(--muted)">{{ !$mv['approvable'] ? (\App\Models\VedomostSubmission::statusLabels()[$mv['status']] ?? $mv['status']) : ($mv['gate']['message'] ?? "Tasdiqlash huquqi yo'q") }}</span>
                                    @endif
                                </td>
                                <td class="txt">
                                    @if($mv['checked'])<a class="link" href="{{ $mv['report'] }}" target="_blank" rel="noopener">Alohida hisobot</a> · @endif
                                    @if($mv['pdf'])<a class="link" href="{{ $mv['pdf'] }}" target="_blank" rel="noopener">PDF</a> · @endif
                                    <a class="link" href="{{ $mv['show'] }}" target="_blank" rel="noopener">Vedomost</a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    <div style="padding:8px 14px;font-size:12px;color:var(--muted)">Har vedomost odatdagi shart bilan tasdiqlanadi: AI muammo topgan yoki Mark qiymati o'zgartirilgan bo'lsa — shu vedomost uchun sabab majburiy. Saqlanmagan tuzatish bo'lsa, avval saqlang.</div>
                </div>
            </form>
        </div>
    </section>
    @endif
</main>

<div class="savebar" id="savebar" role="region" aria-live="polite">
    <svg class="icon"><use href="#i-pencil"/></svg>
    <span><b id="dirty-count">0</b> ta saqlanmagan o'zgarish — saqlanganda hisobot qayta hisoblanadi</span>
    <div class="spacer"></div>
    <button class="sbtn" id="btn-discard">Bekor qilish</button>
    <button class="sbtn primary" id="btn-save">Saqlash</button>
</div>

<nav class="tabs" role="tablist" aria-label="Varaqlar">
    <button class="tab" role="tab" data-tab="main">Tekshirish</button>
    <button class="tab" role="tab" data-tab="header">Sarlavha @if($hdrBad)<span class="n">{{ $hdrBad }}</span>@endif</button>
    <button class="tab" role="tab" data-tab="errors">Xatolar @if($problems + $c['extra'])<span class="n">{{ $problems + $c['extra'] }}</span>@endif</button>
    <button class="tab" role="tab" data-tab="pdf">pdf (BQ)</button>
    <button class="tab" role="tab" data-tab="history">Tarix</button>
    @if($multi)<button class="tab" role="tab" data-tab="approve">Tasdiqlash <span class="n" id="ap-tab-n">{{ count($multi) }}</span></button>@endif
    <div class="legend">
        <span><i style="background:var(--yellow)"></i>Farq (XATO)</span>
        <span><i style="background:var(--orange)"></i>Vedomost PDFdan tekshiring</span>
        <span><i style="background:var(--light)"></i>YN = 0</span>
        <span><i style="background:var(--edit-soft);border-color:var(--edit)"></i>Tuzatilgan</span>
    </div>
</nav>
<div class="toast" id="toast" role="status" aria-live="polite"></div>
<dialog class="why-dlg" id="why-dlg">
    <form method="dialog">
        <h3 id="why-title">Mark qiymatini o'zgartirish</h3>
        <p>Qiymat faqat shu hisobotda o'zgaradi — jurnal, Mark va HEMIS'ga yozilmaydi. Sabab jarayon tarixiga yoziladi.</p>
        <label for="why-text" style="font-size:13px;font-weight:600;">Sabab (majburiy)</label>
        <textarea id="why-text" required minlength="3" placeholder="Masalan: dekanat farmoyishi №…, jurnalga hali kiritilmagan"></textarea>
        <div class="acts"><button value="cancel" formnovalidate>Bekor qilish</button><button value="ok" class="primary">Qo'llash</button></div>
    </form>
</dialog>

<script>
(function () {
    const R = @json($report);
    const VT_HEADERS = @json(\App\Services\VedomostCheckEngine::VT_HEADERS);
    const COMPARE = @json(\App\Services\VedomostCheckEngine::COMPARE);
    const LABELS = @json(\App\Services\VedomostCheckEngine::fieldLabels());
    const STATUS_TXT = @json(\App\Services\VedomostCheckEngine::STATUS_TXT);
    const HEADER_EDITABLE = @json(\App\Services\VedomostCheckEngine::HEADER_EDITABLE);
    const CAN_EDIT = @json($canEdit);
    const SAVE_URL = @json(route('admin.vedomost-submission.ai-report.edits', $v->id));
    // Yig'ma rejim: bir nechta vedomost bitta jadvalda; qator kalitlari «vedomost id|kalit».
    const MULTI = @json($multi ? array_column($multi, 'save', 'id') : null);
    const MK = row => row.mk || row.id;
    const USE = row => row.use || R.use;
    const TOL = 0.05;
    const AB = [['JN % (BQ)', 'jn', 'D'], ['MT ball (BQ)', 'mtb', 'H'], ['OSKE % (BQ)', 'oski', 'P'], ['TEST % (BQ)', 'test', 'S'], ['YN (BQ)', 'yn', 'V']];
    const VT_KEYS = Object.keys(VT_HEADERS);
    const COMPACT = ['A', 'B', 'C', 'D', 'E', 'G', 'H', 'J', 'K', 'M', 'N', 'P', 'Q', 'S', 'T', 'V', 'W', 'X', 'Y'];
    const ST_ICON = { ok: 'i-check', bad: 'i-alert', ocr: 'i-eye', missing: 'i-minus', nopdf: 'i-minus' };

    const pending = { rows: {}, header: {}, weights: {}, signatures: {}, system: {} };
    const SYS = @json(\App\Services\VedomostCheckEngine::SYSTEM_EDITABLE);
    let sysMode = false;
    const sysPending = (id, col) => (pending.system[id] || {})[col];
    let filter = (R.counts.bad + R.counts.ocr + R.counts.missing + R.counts.nopdf) > 0 ? 'problems' : 'all';
    let query = '';
    let allCols = false;

    const esc = s => String(s ?? '').replace(/[&<>"']/g, ch => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[ch]));
    const num = v => { if (v === null || v === undefined || v === '') return null; const s = String(v).replace(',', '.').replace('%', '').trim(); return s === '' || isNaN(+s) ? null : +s; };
    const fmt = v => { if (v === null || v === undefined || v === '') return ''; if (typeof v === 'number') return Number.isInteger(v) ? String(v) : String(+v.toFixed(2)); return String(v); };
    const icon = id => `<svg class="icon"><use href="#${id}"/></svg>`;
    const colL = n => { let s = ''; n++; while (n > 0) { const m = (n - 1) % 26; s = String.fromCharCode(65 + m) + s; n = Math.floor((n - 1) / 26); } return s; };
    const usesON = R.weights.on > 0 || R.rows.some(r => num(r.vt.J));

    function vtCols() {
        const keys = allCols ? VT_KEYS : COMPACT;
        return keys.filter(k => VT_HEADERS[k] !== '' && (usesON || !['J', 'K'].includes(k)));
    }
    function isDirty(key, f) { return !!(key && pending.rows[key] && f in pending.rows[key]); }
    function pdfVal(row, f) {
        if (isDirty(row.key, f)) { const p = pending.rows[row.key][f]; return p === null ? ((row.orig || {})[f] ?? '') : p; }
        if (f === 'fio') return row.scan_fio ?? row.fio ?? '';
        if (f === 'id') return row.scan_id ?? row.id ?? '';
        return (row.pdf || {})[f] ?? '';
    }
    function visible(row) {
        if (query) {
            const q = query.toLowerCase();
            if (!(String(row.fio).toLowerCase().includes(q) || String(row.id).includes(q) || String(row.scan_fio || '').toLowerCase().includes(q))) return false;
        }
        if (filter === 'all') return true;
        if (filter === 'problems') return row.status !== 'ok' || (row.edited || []).length > 0;
        if (filter === 'edited') return (row.edited || []).length > 0;
        return row.status === filter;
    }

    // ───────── Tekshirish ─────────
    function renderMain() {
        const cols = vtCols();
        const vtIndex = k => VT_KEYS.indexOf(k);
        const base = VT_KEYS.length;
        let letters = '<tr class="letters"><th class="rn"></th>';
        let names = '<tr class="names"><th class="rn">1</th>';
        cols.forEach(k => {
            const cls = k === 'A' ? 'c-a' : k === 'B' ? 'c-b' : k === 'C' ? 'c-c' : '';
            letters += `<th class="${cls}">${colL(vtIndex(k))}</th>`;
            names += `<th class="h ${cls}">${esc(VT_HEADERS[k])}</th>`;
            // Fan — faqat yig'ma rejimda (C dan keyin); talabaning aniq guruhi — oxirida, «FIO (kontingent)» oldida.
            if (MULTI && k === 'C') { letters += '<th></th>'; names += '<th class="h">Fan</th>'; }
        });
        AB.forEach(([t], i) => {
            letters += `<th>${colL(base + i * 2)}</th><th>${colL(base + i * 2 + 1)}</th>`;
            names += `<th class="h">${esc(t)}</th><th class="h">Δ</th>`;
        });
        letters += `<th>${colL(base + 10)}</th><th>${colL(base + 11)}</th><th>${colL(base + 12)}</th><th>${colL(base + 13)}</th><th>${colL(base + 14)}</th><th>${colL(base + 15)}</th><th>${colL(base + 16)}</th><th>${colL(base + 17)}</th><th>${colL(base + 18)}</th></tr>`;
        names += '<th></th><th class="h">OSKE test markazi</th><th class="h">Δ</th><th class="h">TEST test markazi</th><th class="h">Δ</th><th class="h">Guruh</th><th class="h">FIO (kontingent)</th><th class="h">Holat</th><th class="h">Izoh</th></tr>';

        let body = '';
        let shown = 0;
        R.rows.forEach((row, ri) => {
            if (!visible(row)) return;
            shown++;
            const vt = row.vt;
            const ynZero = num(vt.V) === 0;
            const statusFill = ['bad', 'missing', 'nopdf'].includes(row.status) ? 'y' : '';
            const first = MULTI && (ri === 0 || R.rows[ri - 1].vid !== row.vid);
            body += `<tr data-ri="${ri}"${first ? ' class="src-first"' : ''}><td class="rn">${ri + 2}</td>`;
            cols.forEach(k => {
                let cls = k === 'A' ? 'c-a' : k === 'B' ? 'c-b txt' : k === 'C' ? 'c-c txt' : (['X', 'Y', 'W', 'AB', 'AC', 'AD', 'AE', 'AG', 'AJ', 'AS', 'AU', 'AW', 'AT', 'AV'].includes(k) ? 'txt' : '');
                if (ynZero) cls += ' l';
                if (['A', 'B', 'C'].includes(k) && statusFill) cls += ' y';
                if (k === 'B' && row.status === 'ocr') cls += ' o';
                let v = vt[k];
                let sysAttrs = '';
                if (SYS[k] && row.id) {
                    const p = sysPending(MK(row), k);
                    if (p !== undefined) { v = p === null ? (row.sys_orig || {})[k] ?? v : p.v; cls += ' sys-dirty'; }
                    else if ((row.sys_edited || {})[k] !== undefined) cls += ' sys-edited';
                    if (sysMode && CAN_EDIT && !row.ro) { cls += ' sys'; sysAttrs = ` tabindex="0" data-mkey="${esc(MK(row))}" data-mcol="${k}" data-fio="${esc(row.fio)}" data-label="${esc(VT_HEADERS[k])} (Mark)"`; }
                    if ((row.sys_edited || {})[k] !== undefined) sysAttrs += ` title="Asl Mark qiymati: ${esc(fmt((row.sys_orig || {})[k]))} · sabab: ${esc(row.sys_edited[k])}"`;
                }
                if (k === 'N' && v !== null && v !== '') v = Math.round(v * 100) + '%';
                else if (k === 'AM' && v !== null && v !== '') v = (+(v * 100).toFixed(2)) + '%';
                else if (['E', 'H'].includes(k) && typeof v === 'number') v = v.toFixed(1);
                if (MULTI && k === 'D') body += `<td class="txt src" title="${esc(row.fan)}">${esc(row.fan)}</td>`;
                body += `<td class="${cls}" data-ref="${colL(vtIndex(k))}${ri + 2}"${sysAttrs}${k === 'B' ? ` title="${esc((row.flags || []).map(f => f.m).join('\n'))}"` : ''}>${esc(fmt(v))}</td>`;
            });
            AB.forEach(([t, key, hc], i) => {
                const f = COMPARE[key][1];
                const skip = (key === 'oski' && !USE(row).oski) || (key === 'test' && !USE(row).test);
                const cc = (row.cells || {})[key];
                const ref = colL(base + i * 2) + (ri + 2);
                if (skip) { body += `<td class="grey" data-ref="${ref}">kerak emas</td><td></td>`; return; }
                if (!row.pdf) { body += `<td class="txt" data-ref="${ref}">Vedomost PDFda yo'q</td><td></td>`; return; }
                const dirty = isDirty(row.key, f);
                const raw = pdfVal(row, f);
                let st = cc ? cc.status : '';
                const p = num(raw), h = num(vt[hc]);
                if (dirty) st = String(raw).trim() === '' ? (h ? 'bad' : 'empty') : (p !== null && h !== null && Math.abs(p - h) <= TOL + 1e-9 ? 'ok' : 'bad');
                const fill = st === 'bad' ? 'y' : st === 'ocr' ? 'o' : '';
                const cls = ['bq', fill, (row.edited || []).includes(f) ? 'edited' : '', dirty ? 'dirty' : '', CAN_EDIT && row.key ? 'ed' : ''].join(' ');
                const d = p !== null && h !== null ? +(p - h).toFixed(2) : null;
                const attrs = CAN_EDIT && row.key ? `tabindex="0" data-key="${row.key}" data-field="${f}"` : '';
                body += `<td class="${cls}" ${attrs} data-ref="${ref}" data-hemis="${esc(fmt(h))}" data-label="${esc(t)}" data-fio="${esc(row.fio)}">${esc(raw)}</td>`;
                body += `<td class="${fill}">${d === null ? '' : esc(fmt(d))}</td>`;
            });
            body += '<td class="sep"></td>';
            [['oski', 'P', USE(row).oski, USE(row).tm_oski], ['test', 'S', USE(row).test, USE(row).tm_test]].forEach(([k, hc, need, on]) => {
                const tm = (row.tm || {})[k];
                if (!need) { body += '<td class="grey">kerak emas</td><td></td>'; return; }
                if (!on) { body += `<td class="o txt">Test markazida natija yo'q</td><td></td>`; return; }
                if (tm && tm.status === 'skip') {
                    body += `<td class="grey" title="${esc(tm.note)}">hisobga olinmaydi${tm.tm !== null ? ' (TM ' + esc(fmt(tm.tm)) + ')' : ''}</td><td></td>`;
                    return;
                }
                if (tm && tm.sinov) {
                    const fill = tm.status === 'bad' ? 'y' : '';
                    body += `<td class="${fill} txt">sinov: JN ${esc(fmt(tm.jn))}</td><td class="${fill}">${esc(fmt(num(vt.S) !== null && num(vt.D) !== null ? num(vt.S) - num(vt.D) : ''))}</td>`;
                    return;
                }
                if (!tm) { body += '<td></td><td></td>'; return; }
                const fill = tm.status === 'bad' ? 'y' : '';
                const v = tm.tm !== null ? (tm.dal ? tm.tm + ' (Dalolatnoma)' : fmt(tm.tm)) : (tm.nd ? 'nd' : "yo'q");
                body += `<td class="${fill}">${esc(v)}</td><td class="${fill}">${tm.nd ? 'nd' : esc(fmt(tm.delta))}</td>`;
            });
            body += `<td class="txt grp">${esc(row.grp || row.group || '')}</td>`;
            body += `<td class="txt ${row.kont_fio === null ? 'y' : ''}">${esc(row.kont_fio ?? "KONTINGENTDA YO'Q")}</td>`;
            body += `<td class="txt"><span class="st ${row.status}">${icon(ST_ICON[row.status] || 'i-info')}${esc(STATUS_TXT[row.status] || '')}</span></td>`;
            body += `<td class="note" title="${esc((row.flags || []).map(f => f.m).join('\n'))}">${(row.flags || []).map(f => esc(f.m)).join(' · ')}</td></tr>`;
        });
        document.getElementById('sheet-main').innerHTML = shown === 0
            ? `<div class="empty">${filter === 'problems' ? "Muammoli qator yo'q — barcha talabalar mos keladi." : "Filtr bo'yicha qator yo'q."}</div>`
            : `<table class="xl" aria-label="Tekshirish varag'i"><thead>${letters}${names}</thead><tbody>${body}</tbody></table>`;
        setupColFilters();
    }

    // ───────── ustun filtrlari (Excel'dagidek: ▾ — qiymatlar ro'yxati, qidiruv, saralash) ─────────
    const colFilters = {};   // ustun id => Set(ruxsat etilgan matnlar)
    let colSort = null;      // {cid, dir: 1|-1}
    const EMPTY = "(bo'sh)";
    const cellText = td => { const t = (td ? td.textContent : '').trim(); return t === '' ? EMPTY : t; };
    const cmpVal = (a, b) => { const x = num(a), y = num(b); return x !== null && y !== null ? x - y : String(a).localeCompare(String(b), 'uz', { numeric: true }); };
    function colMap(table) {
        // Ustun id: harf (A, B, … — barqaror) yoki sarlavha matni; Δ ustunlari oldingi ustun nomi bilan.
        const letters = [...table.querySelectorAll('thead tr.letters th')];
        const names = [...table.querySelectorAll('thead tr.names th')];
        const map = {};
        names.forEach((th, i) => {
            if (th.classList.contains('rn') || !th.classList.contains('h')) return;
            const label = th.dataset.label || th.textContent.trim();
            th.dataset.label = label;
            const cid = (letters[i] && letters[i].textContent.trim()) || label;
            th.dataset.cid = cid;
            map[cid] = i;
        });
        return map;
    }
    function rowPasses(tr, map, except) {
        return Object.entries(colFilters).every(([cid, set]) => cid === except || !(cid in map) || set.has(cellText(tr.children[map[cid]])));
    }
    function setupColFilters() {
        const table = document.querySelector('#sheet-main table.xl');
        if (!table) return;
        const map = colMap(table);
        table.querySelectorAll('thead tr.names th[data-cid]').forEach(th => {
            const on = th.dataset.cid in colFilters || (colSort && colSort.cid === th.dataset.cid);
            th.classList.add('cf-th');
            th.innerHTML = `<span>${esc(th.dataset.label)}</span><button type="button" class="cf-btn${on ? ' on' : ''}" data-cid="${esc(th.dataset.cid)}" aria-label="${esc(th.dataset.label)} — filtr">${colSort && colSort.cid === th.dataset.cid ? (colSort.dir > 0 ? '↑' : '↓') : (th.dataset.cid in colFilters ? '⧩' : '▾')}</button>`;
        });
        const tbody = table.tBodies[0];
        if (colSort && colSort.cid in map) {
            const ci = map[colSort.cid];
            [...tbody.rows].sort((a, b) => colSort.dir * cmpVal(cellText(a.children[ci]), cellText(b.children[ci]))).forEach(tr => tbody.appendChild(tr));
        }
        let shown = 0;
        [...tbody.rows].forEach(tr => { tr.hidden = !rowPasses(tr, map, null); if (!tr.hidden) shown++; });
        const total = tbody.rows.length;
        const active = Object.keys(colFilters).length;
        const info = document.getElementById('cf-info');
        if (info) {
            info.hidden = !active && !colSort;
            info.querySelector('b').textContent = active ? `${shown} / ${total} qator · ${active} ta ustun filtri` : 'Saralangan';
        }
    }
    function closeCf() { document.getElementById('cf-pop')?.remove(); }
    function openCf(btn) {
        closeCf();
        const table = btn.closest('table');
        const map = colMap(table);
        const cid = btn.dataset.cid, ci = map[cid];
        // Qiymatlar — boshqa ustun filtrlaridan o'tgan qatorlardan (Excel'dagidek).
        const counts = new Map();
        [...table.tBodies[0].rows].forEach(tr => { if (rowPasses(tr, map, cid)) { const v = cellText(tr.children[ci]); counts.set(v, (counts.get(v) || 0) + 1); } });
        const values = [...counts.keys()].sort(cmpVal);
        const sel = colFilters[cid] ? new Set(colFilters[cid]) : new Set(values);
        const pop = document.createElement('div');
        pop.id = 'cf-pop';
        pop.className = 'cf-pop';
        pop.innerHTML = `<div class="cf-sort"><button type="button" data-sort="1">↑ A→Z / kichikdan</button><button type="button" data-sort="-1">↓ Z→A / kattadan</button></div>
            <input type="search" class="cf-q" placeholder="Qidirish…" aria-label="Qiymatni qidirish">
            <label class="cf-all"><input type="checkbox" class="cf-allcb"> (Hammasi)</label>
            <div class="cf-list">${values.map(v => `<label><input type="checkbox" value="${esc(v)}" ${sel.has(v) ? 'checked' : ''}><span>${esc(v)}</span><i>${counts.get(v)}</i></label>`).join('')}</div>
            <div class="cf-acts"><button type="button" class="cf-clear">Tozalash</button><span style="flex:1"></span><button type="button" class="cf-cancel">Bekor</button><button type="button" class="cf-ok">OK</button></div>`;
        document.body.appendChild(pop);
        const r = btn.getBoundingClientRect();
        pop.style.top = Math.min(r.bottom + 4, innerHeight - pop.offsetHeight - 8) + 'px';
        pop.style.left = Math.max(8, Math.min(r.left - 180, innerWidth - pop.offsetWidth - 8)) + 'px';
        const boxes = () => [...pop.querySelectorAll('.cf-list input')];
        const syncAll = () => { const vis = boxes().filter(b => !b.closest('label').hidden); pop.querySelector('.cf-allcb').checked = vis.length > 0 && vis.every(b => b.checked); };
        syncAll();
        pop.querySelector('.cf-allcb').addEventListener('change', e => { boxes().filter(b => !b.closest('label').hidden).forEach(b => b.checked = e.target.checked); });
        pop.querySelector('.cf-list').addEventListener('change', syncAll);
        pop.querySelector('.cf-q').addEventListener('input', e => {
            const q = e.target.value.trim().toLowerCase();
            boxes().forEach(b => { const l = b.closest('label'); l.hidden = q !== '' && !b.value.toLowerCase().includes(q); if (q) b.checked = !l.hidden; });
            syncAll();
        });
        const apply = () => {
            const chosen = new Set(boxes().filter(b => b.checked).map(b => b.value));
            if (chosen.size === values.length) delete colFilters[cid]; else colFilters[cid] = chosen;
            closeCf(); setupColFilters();
        };
        pop.querySelector('.cf-ok').addEventListener('click', apply);
        pop.querySelector('.cf-q').addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); apply(); } });
        pop.querySelector('.cf-cancel').addEventListener('click', closeCf);
        pop.querySelector('.cf-clear').addEventListener('click', () => { delete colFilters[cid]; if (colSort && colSort.cid === cid) colSort = null; closeCf(); renderMain(); });
        pop.querySelectorAll('[data-sort]').forEach(b => b.addEventListener('click', () => { colSort = { cid, dir: +b.dataset.sort }; apply(); }));
        pop.addEventListener('keydown', e => { if (e.key === 'Escape') { e.stopPropagation(); closeCf(); btn.focus(); } });
        pop.querySelector('.cf-q').focus();
    }
    document.addEventListener('click', e => {
        const b = e.target.closest('.cf-btn');
        if (b) { e.stopPropagation(); if (document.getElementById('cf-pop') && document.getElementById('cf-pop').dataset.for === b.dataset.cid) closeCf(); else { openCf(b); document.getElementById('cf-pop').dataset.for = b.dataset.cid; } return; }
        if (!e.target.closest('#cf-pop')) closeCf();
    }, true);
    document.getElementById('cf-reset')?.addEventListener('click', () => { Object.keys(colFilters).forEach(k => delete colFilters[k]); colSort = null; renderMain(); });

    // ───────── pdf (BQ) ─────────
    function renderPdf() {
        const F = ['fio', 'id', 'jb_pct', 'jb_ball', 'mt_pct', 'mt_ball', 'on_pct', 'on_ball', 'jbmton_ball', 'jbmton_pct', 'oski_pct', 'oski_ball', 'test_pct', 'test_ball', 'yn', 'ects'];
        const entries = [];
        R.rows.forEach(r => { if (r.pdf) entries.push(Object.assign({ matched: true }, r)); });
        (R.extra || []).forEach(e => entries.push({ matched: false, key: e.key, page: e.page, nr: e.nr, fio: '', id: '', scan_fio: e.fio, scan_id: e.id, pdf: e.pdf, unclear: e.unclear, edited: e.edited, orig: e.orig, note: e.note, expelled: e.expelled, status: 'extra' }));
        entries.sort((a, b) => ((a.page || 0) - (b.page || 0)) || ((a.nr || 0) - (b.nr || 0)));
        let head = '<tr class="letters"><th class="rn"></th>' + ['Fayl / bet', '№', ...F.map(f => f === 'fio' ? 'FIO (PDF)' : f === 'id' ? 'ID (PDF)' : LABELS[f]), 'FIO (moslashtirilgan)', 'ID (moslashtirilgan)', "Aniq o'qilmagan"].map((_, i) => `<th>${colL(i)}</th>`).join('') + '</tr>';
        head += '<tr class="names"><th class="rn">1</th>' + ['Fayl / bet', '№', ...F.map(f => f === 'fio' ? 'FIO (PDF)' : f === 'id' ? 'ID (PDF)' : LABELS[f]), 'FIO (moslashtirilgan)', 'ID (moslashtirilgan)', "Aniq o'qilmagan"].map(h => `<th class="h">${esc(h)}</th>`).join('') + '</tr>';
        let body = '';
        entries.forEach((e, i) => {
            if (e.matched && !visible(e)) return;
            body += `<tr><td class="rn">${i + 2}</td><td class="txt">vedomost.pdf / ${esc(e.page || 1)}</td><td>${esc(e.nr ?? '')}</td>`;
            F.forEach(f => {
                const dirty = isDirty(e.key, f);
                const cls = ['bq', (e.unclear || []).includes(f) ? 'o' : '', (e.edited || []).includes(f) ? 'edited' : '', dirty ? 'dirty' : '', CAN_EDIT ? 'ed' : '', ['fio', 'id'].includes(f) ? 'txt' : ''].join(' ');
                const st = f === 'fio' ? ' style="min-width:260px"' : f === 'id' ? ' style="min-width:120px"' : '';
                body += `<td class="${cls}"${st} ${CAN_EDIT ? `tabindex="0" data-key="${e.key}" data-field="${f}"` : ''} data-label="${esc(LABELS[f] || f)}" data-fio="${esc(e.scan_fio)}">${esc(pdfVal(e, f))}</td>`;
            });
            body += `<td class="txt ${e.matched ? '' : (e.expelled ? 'grey' : 'y')}">${e.matched ? esc(e.fio) : esc(e.expelled ? (e.note || '') : "Markda yo'q — " + (e.note || ''))}</td><td class="txt">${esc(e.id)}</td><td class="txt ${(e.unclear || []).length ? 'o' : ''}">${esc((e.unclear || []).join(', '))}</td></tr>`;
        });
        document.getElementById('sheet-pdf').innerHTML = body ? `<table class="xl" aria-label="PDFdan o'qilgan jadval"><thead>${head}</thead><tbody>${body}</tbody></table>` : '<div class="empty">Qator yo\'q.</div>';
    }

    // ───────── Xatolar ─────────
    function renderErrors() {
        let body = '';
        let n = 1;
        R.rows.forEach((row, ri) => {
            if (row.status === 'ok') return;
            n++;
            body += `<tr><td class="rn">${n}</td><td>${esc(row.vt.A)}</td><td class="txt">${esc(row.vt.B)}</td><td class="txt">${esc(row.id)}</td><td class="txt">${esc(row.group)}</td><td class="txt"><span class="st ${row.status}">${icon(ST_ICON[row.status] || 'i-info')}${esc(STATUS_TXT[row.status])}</span></td><td class="note">${(row.flags || []).map(f => esc(f.m)).join('<br>')} <button class="link" data-goto="${ri}">Jadvalda</button></td></tr>`;
        });
        (R.extra || []).forEach(e => { n++; body += `<tr><td class="rn">${n}</td><td></td><td class="txt">${esc(e.fio)}</td><td class="txt">${esc(e.id)}</td><td></td><td class="txt ${e.expelled ? 'grey' : 'y'}">${e.expelled ? "Chetlashtirilgan (ma'lumot)" : "Vedomost PDFda bor, Markda yo'q"}</td><td class="note">vedomost.pdf / ${esc(e.page || 1)} · ${esc(e.note)}</td></tr>`; });
        (R.issues || []).forEach(i => { n++; body += `<tr><td class="rn">${n}</td><td></td><td></td><td></td><td></td><td class="txt">Ogohlantirish</td><td class="note">${esc(i.m)}</td></tr>`; });
        R.header.filter(h => h.st !== 'ok').forEach(h => { n++; body += `<tr><td class="rn">${n}</td><td></td><td></td><td></td><td></td><td class="txt ${h.st === 'bad' ? 'y' : 'o'}">Sarlavha: ${esc(h.k)}</td><td class="note">${esc(h.v)}</td></tr>`; });
        const head = '<tr class="names"><th class="rn">1</th>' + ['#', 'FIO', 'ID', 'Guruh', 'Holat', 'Izoh'].map(h => `<th class="h">${h}</th>`).join('') + '</tr>';
        document.getElementById('sheet-errors').innerHTML = body ? `<table class="xl wrap" aria-label="Xatolar"><thead>${head}</thead><tbody>${body}</tbody></table>` : '<div class="empty">Xato yo\'q.</div>';
    }

    // ───────── Sarlavha ─────────
    function renderHeader() {
        let rows = '';
        R.header.forEach((h, i) => {
            const fill = h.st === 'bad' ? 'y' : h.st === 'warn' ? 'o' : '';
            rows += `<tr><td class="rn">${i + 2}</td><td class="txt"><b>${esc(h.k)}</b></td><td class="note" style="max-width:none">${esc(h.v)}</td><td class="txt ${fill}">${h.st === 'ok' ? 'OK' : h.st === 'bad' ? 'XATO' : 'Tekshiring'}</td></tr>`;
        });
        const w = R.weights;
        if (!MULTI) rows += `<tr><td class="rn">${R.header.length + 2}</td><td class="txt"><b>Vaznlar (JN/MT/ON/OSKE/Test)</b></td><td class="note">${[w.jb, w.mt, w.on, w.oski, w.test].join(' / ')}</td><td class="txt">OK</td></tr>`;
        let html = `<div class="pad"><div class="card"><h2>${icon('i-file')}Sarlavha tekshiruvi</h2><table class="xl" style="width:100%"><thead><tr class="names"><th class="rn">1</th><th class="h" style="width:240px">Tekshiruv</th><th class="h">Natija</th><th class="h" style="width:110px">Holat</th></tr></thead><tbody>${rows}</tbody></table></div>`;

        if (MULTI && CAN_EDIT) html += `<div class="card" style="padding:10px 14px;font-size:13px;color:var(--muted)">Sarlavha, vaznlar va imzolarni tuzatish — har vedomostning alohida hisobotida («Tasdiqlash» varag'idagi havola).</div>`;
        if (CAN_EDIT && !MULTI) {
            const sh = R.scan_header || {};
            const labels = { fan: 'Fan nomi', guruh: 'Guruh', soat: 'Umumiy soat', kredit: 'Kredit', maruzachi: "Ma'ruzachi", amaliyot: "Amaliyot o'qituvchilari (vergul bilan)", yn_sana: 'YaN sanasi', dekan: 'Fakultet dekani', kafedra_mudiri: 'Kafedra mudiri', shakl: "Vedomost shakli (o'ng yuqori burchak)" };
            html += `<div class="card"><h2>${icon('i-pencil')}AI o'qigan sarlavha — noto'g'ri o'qilgan bo'lsa tuzating</h2><div class="form-grid">`;
            Object.keys(labels).forEach(k => {
                const orig = Array.isArray(sh[k]) ? sh[k].join(', ') : (sh[k] ?? '');
                const val = k in pending.header ? pending.header[k] : orig;
                html += `<label>${labels[k]}<input type="text" data-hkey="${k}" value="${esc(val)}" data-orig="${esc(orig)}" class="${k in pending.header ? 'dirty' : ''}"></label>`;
            });
            html += '</div></div>';
            const map = [['jb', 'jb', 'JN'], ['mt', 'mt', 'MT'], ['on', 'on', 'ON'], ['oski', 'oski', 'OSKE'], ['test', 'test', 'Test']];
            html += `<div class="card"><h2>${icon('i-info')}Vaznlar (sarlavhadagi maksimal ball)</h2><div class="form-grid">`;
            map.forEach(([k, rk, l]) => {
                const val = k in pending.weights ? pending.weights[k] : w[rk];
                html += `<label>${l}<input type="number" min="0" max="100" data-wkey="${k}" value="${esc(val)}" data-orig="${esc(w[rk])}" class="${k in pending.weights ? 'dirty' : ''}"></label>`;
            });
            html += `</div></div><div class="card"><h2>${icon('i-check')}Imzolar va muhr</h2><div class="form-grid">`;
            [['oqituvchi', "O'qituvchi imzosi"], ['dekan', 'Dekan imzosi'], ['kafedra_mudiri', 'Kafedra mudiri imzosi'], ['muhr', "Muhr (M.O')"]].forEach(([k, l]) => {
                const on = k in pending.signatures ? pending.signatures[k] : !!R.signatures[k];
                html += `<label class="sig ${k in pending.signatures ? 'dirty' : ''}"><input type="checkbox" data-skey="${k}" ${on ? 'checked' : ''}>${l}</label>`;
            });
            html += `</div><div style="padding:0 14px 12px;font-size:12px;color:var(--muted)">AI imzo va muhr borligini aniqlaydi, haqiqiyligini emas.</div></div>`;
        }
        document.getElementById('sheet-header').innerHTML = html + '</div>';
    }

    function renderAll() { renderMain(); renderPdf(); renderErrors(); renderHeader(); updateSavebar(); }

    // ───────── formula satri ─────────
    function showCell(td) {
        document.querySelectorAll('.xl td.sel').forEach(x => x.classList.remove('sel'));
        if (!td) return;
        td.classList.add('sel');
        // Katak manzili: ustun harfi (sarlavha) + qator raqami — har qanday katak uchun.
        const tr = td.parentElement;
        const lt = td.closest('table').querySelector('thead tr.letters');
        const col = lt ? (lt.children[td.cellIndex] || {}).textContent || '' : '';
        const rn = tr.querySelector('.rn');
        const ref = td.dataset.ref || (col && rn ? col + rn.textContent : '');
        document.getElementById('f-ref').textContent = ref || '—';
        let txt = td.textContent;
        if (td.dataset.field) txt = `${td.dataset.fio || ''} — ${td.dataset.label || ''}: ${td.textContent || '∅'}` + (td.dataset.hemis !== undefined ? `   |   Mark: ${td.dataset.hemis || '∅'}` : '');
        if ((td.classList.contains('c-b') || td.classList.contains('note')) && td.title) txt = td.textContent + '  —  ' + td.title.replace(/\n/g, ' · ');
        document.getElementById('f-val').textContent = txt;
    }
    document.addEventListener('click', e => {
        const td = e.target.closest('.xl tbody td');
        if (td && !td.querySelector('input') && !e.target.closest('button')) selectCell(td.classList.contains('rn') ? td.nextElementSibling : td);
        const go = e.target.closest('[data-goto]');
        if (go) {
            filter = 'all'; setChips(); renderMain(); showTab('main');
            const tr = document.querySelector(`#sheet-main tr[data-ri="${go.dataset.goto}"]`);
            if (tr) { tr.scrollIntoView({ block: 'center' }); tr.classList.add('flash'); setTimeout(() => tr.classList.remove('flash'), 1500); }
        }
    });

    // ───────── tahrirlash ─────────
    function dirtyCount() {
        let n = Object.keys(pending.header).length + Object.keys(pending.weights).length + Object.keys(pending.signatures).length;
        Object.values(pending.rows).forEach(o => { n += Object.keys(o).length; });
        Object.values(pending.system).forEach(o => { n += Object.keys(o).length; });
        return n;
    }
    function updateSavebar() {
        const n = dirtyCount();
        document.getElementById('dirty-count').textContent = n;
        document.getElementById('savebar').classList.toggle('show', n > 0);
        const top = document.getElementById('btn-save-top');
        if (top) {
            top.disabled = n === 0;
            document.getElementById('save-top-n').textContent = n;
            document.getElementById('save-top-n').hidden = n === 0;
        }
    }
    function findEntry(key) { return R.rows.find(r => r.key === key) || (R.extra || []).find(e => e.key === key); }
    function savedVal(e, f) {
        if (f === 'fio') return e.scan_fio ?? e.fio ?? '';
        if (f === 'id') return e.scan_id ?? e.id ?? '';
        return (e.pdf || {})[f] ?? '';
    }
    function startEdit(td, mode = 'edit') {
        if (td && td.dataset.mkey) return startSysEdit(td, mode);
        if (!td || !td.dataset.key || td.querySelector('input')) return;
        const key = td.dataset.key, f = td.dataset.field;
        const e = findEntry(key);
        const saved = String(savedVal(e, f)).trim();
        const orig = (e.edited || []).includes(f) ? String((e.orig || {})[f] ?? '').trim() : saved;
        const input = document.createElement('input');
        input.className = 'ci';
        input.dataset.mode = mode;
        input.value = pdfVal(Object.assign({}, e, { key }), f);
        input.setAttribute('aria-label', (td.dataset.label || f) + ' — ' + (td.dataset.fio || ''));
        if (['fio', 'id'].includes(f)) input.style.textAlign = 'left';
        td.textContent = '';
        td.appendChild(input);
        input.focus(); input.select();
        let done = false;
        const sheet = td.closest('.sheet').id;
        const commit = move => {
            if (done) return; done = true;
            const v = input.value.trim();
            pending.rows[key] = pending.rows[key] || {};
            if (v === saved) delete pending.rows[key][f];
            else if (v === orig) pending.rows[key][f] = null;
            else pending.rows[key][f] = v;
            if (!Object.keys(pending.rows[key]).length) delete pending.rows[key];
            renderAll(); refocus(sheet, key, f, move);
        };
        input.addEventListener('keydown', ev => {
            ev.stopPropagation();
            const arrows = { ArrowUp: 'up', ArrowDown: 'down', ArrowLeft: 'left', ArrowRight: 'right' };
            if (ev.key === 'Enter') { ev.preventDefault(); commit(ev.shiftKey ? 'up' : 'down'); }
            else if (ev.key === 'Tab') { ev.preventDefault(); commit(ev.shiftKey ? 'left' : 'right'); }
            else if (ev.key === 'Escape') { done = true; renderAll(); refocus(sheet, key, f); }
            // Excel: yozishni boshlagan bo'lsangiz strelka katakni saqlab, keyingisiga o'tadi;
            // F2 / Enter / ikki marta bosish bilan ochilganda chap-o'ng strelka matn ichida yuradi.
            else if (arrows[ev.key] && (input.dataset.mode === 'type' || ev.key === 'ArrowUp' || ev.key === 'ArrowDown')) { ev.preventDefault(); commit(arrows[ev.key]); }
        });
        input.addEventListener('blur', () => commit(null));
    }
    // Mark qiymati: kiritiladi → sabab so'raladi → pending.system ga yoziladi (saqlanganda qayta hisob).
    function startSysEdit(td, mode) {
        if (td.querySelector('input')) return;
        const id = td.dataset.mkey, col = td.dataset.mcol;
        const row = R.rows.find(r => MK(r) === id);
        const orig = (row.sys_orig || {})[col] ?? row.vt[col];
        const cur = sysPending(id, col);
        const shown = cur === undefined ? row.vt[col] : (cur === null ? orig : cur.v);
        const input = document.createElement('input');
        input.className = 'ci';
        input.dataset.mode = mode;
        input.value = fmt(shown);
        input.setAttribute('aria-label', (td.dataset.label || col) + ' — ' + (td.dataset.fio || ''));
        td.textContent = '';
        td.appendChild(input);
        input.focus(); input.select();
        let done = false;
        const finish = move => { renderAll(); const t = document.querySelector(`#sheet-main td[data-mkey="${CSS.escape(id)}"][data-mcol="${col}"]`); if (t) selectCell(move ? (neighbour(t, move) || t) : t); };
        const commit = move => {
            if (done) return; done = true;
            const raw = input.value.trim().replace(',', '.');
            if (raw === '' || isNaN(+raw) || +raw === +shown) { finish(move); return; }
            if (+raw === +orig) {
                pending.system[id] = pending.system[id] || {};
                if ((row.sys_edited || {})[col] !== undefined) pending.system[id][col] = null; else delete pending.system[id][col];
                if (!Object.keys(pending.system[id]).length) delete pending.system[id];
                finish(move); return;
            }
            const dlg = document.getElementById('why-dlg');
            document.getElementById('why-title').textContent = `${row.fio} — ${VT_HEADERS[col]}: ${fmt(orig)} → ${fmt(+raw)}`;
            const txt = document.getElementById('why-text');
            txt.value = (cur && cur.why) || (row.sys_edited || {})[col] || '';
            dlg.returnValue = '';
            dlg.onclose = () => {
                const why = txt.value.trim();
                if (dlg.returnValue === 'ok' && why.length >= 3) {
                    pending.system[id] = pending.system[id] || {};
                    pending.system[id][col] = { v: +raw, why };
                } else if (dlg.returnValue === 'ok') {
                    toast("Sabab kamida 3 ta belgi bo'lishi kerak — o'zgarish qo'llanmadi", true);
                }
                finish(move);
            };
            dlg.showModal();
            txt.focus();
        };
        input.addEventListener('keydown', ev => {
            ev.stopPropagation();
            const arrows = { ArrowUp: 'up', ArrowDown: 'down', ArrowLeft: 'left', ArrowRight: 'right' };
            if (ev.key === 'Enter') { ev.preventDefault(); commit(ev.shiftKey ? 'up' : 'down'); }
            else if (ev.key === 'Tab') { ev.preventDefault(); commit(ev.shiftKey ? 'left' : 'right'); }
            else if (ev.key === 'Escape') { done = true; finish(); }
            else if (arrows[ev.key] && (input.dataset.mode === 'type' || ev.key === 'ArrowUp' || ev.key === 'ArrowDown')) { ev.preventDefault(); commit(arrows[ev.key]); }
        });
        input.addEventListener('blur', () => { if (!document.getElementById('why-dlg').open) commit(null); });
    }
    function revertCell(td) {
        if (td.dataset.mkey) {
            const id = td.dataset.mkey, col = td.dataset.mcol;
            const row = R.rows.find(r => MK(r) === id);
            pending.system[id] = pending.system[id] || {};
            if (sysPending(id, col) !== undefined) delete pending.system[id][col];
            else if ((row.sys_edited || {})[col] !== undefined) pending.system[id][col] = null;
            if (!Object.keys(pending.system[id]).length) delete pending.system[id];
            renderAll();
            const t = document.querySelector(`#sheet-main td[data-mkey="${CSS.escape(id)}"][data-mcol="${col}"]`);
            if (t) selectCell(t);
            return;
        }
        const key = td.dataset.key, f = td.dataset.field;
        const e = findEntry(key);
        if (isDirty(key, f)) delete pending.rows[key][f];
        else if ((e.edited || []).includes(f)) { pending.rows[key] = pending.rows[key] || {}; pending.rows[key][f] = null; }
        if (pending.rows[key] && !Object.keys(pending.rows[key]).length) delete pending.rows[key];
        const sheet = td.closest('.sheet').id;
        renderAll(); refocus(sheet, key, f);
    }
    // ───────── Excel'dagidek katak bo'ylab yurish ─────────
    // Har qanday katak tanlanadi; strelka, Tab, Enter, Home/End, PageUp/PageDown bilan yuriladi.
    // Faqat PDF (BQ) kataklari tahrirlanadi.
    const navCells = tr => Array.from(tr.children).filter(c => c.tagName === 'TD' && !c.classList.contains('rn'));
    function neighbour(td, dir, step = 1) {
        const tr = td.parentElement;
        const row = navCells(tr);
        const i = row.indexOf(td);
        if (dir === 'left') return row[Math.max(0, i - step)] || null;
        if (dir === 'right') return row[Math.min(row.length - 1, i + step)] || null;
        if (dir === 'home') return row[0];
        if (dir === 'end') return row[row.length - 1];
        const rows = Array.from(tr.parentElement.children).filter(r => !r.hidden);
        let ri = rows.indexOf(tr);
        if (dir === 'top') ri = 0;
        else if (dir === 'bottom') ri = rows.length - 1;
        else ri = Math.max(0, Math.min(rows.length - 1, ri + (dir === 'down' ? step : -step)));
        const target = navCells(rows[ri]);
        return target[Math.min(i, target.length - 1)] || null;
    }
    function ensureVisible(td) {
        const box = td.closest('.sheet');
        if (!box) return;
        const b = box.getBoundingClientRect(), r = td.getBoundingClientRect();
        const head = td.closest('table').querySelector('thead');
        const top = b.top + (head ? head.getBoundingClientRect().height : 0);
        let left = b.left;
        if (!td.matches('.c-a, .c-b, .c-c')) {
            td.parentElement.querySelectorAll('.rn, .c-a, .c-b, .c-c').forEach(c => {
                if (getComputedStyle(c).position === 'sticky') left = Math.max(left, c.getBoundingClientRect().right);
            });
        }
        if (r.top < top) box.scrollTop -= top - r.top;
        else if (r.bottom > b.bottom - 14) box.scrollTop += r.bottom - b.bottom + 14;
        if (r.left < left) box.scrollLeft -= left - r.left;
        else if (r.right > b.right - 14) box.scrollLeft += r.right - b.right + 14;
    }
    function selectCell(td) {
        if (!td) return;
        if (!td.hasAttribute('tabindex')) td.setAttribute('tabindex', '-1');
        td.focus({ preventScroll: true });
        ensureVisible(td);
        showCell(td);
    }
    function refocus(sheet, key, f, move) {
        let t = document.querySelector(`#${sheet} td[data-key="${key}"][data-field="${f}"]`);
        if (!t) return;
        if (move) t = neighbour(t, move) || t;
        selectCell(t);
    }
    document.addEventListener('keydown', e => {
        const td = document.activeElement;
        if (!td || td.tagName !== 'TD' || !td.closest('.xl tbody')) return;
        const page = Math.max(1, Math.floor((td.closest('.sheet').clientHeight - 60) / 25));
        const ctrl = e.ctrlKey || e.metaKey;
        const moves = {
            ArrowLeft: ctrl ? 'home' : 'left', ArrowRight: ctrl ? 'end' : 'right',
            ArrowUp: ctrl ? 'top' : 'up', ArrowDown: ctrl ? 'bottom' : 'down',
            Home: ctrl ? 'top' : 'home', End: ctrl ? 'bottom' : 'end', PageUp: 'up', PageDown: 'down',
        };
        if (moves[e.key]) {
            e.preventDefault();
            selectCell(neighbour(td, moves[e.key], e.key.startsWith('Page') ? page : 1));
            return;
        }
        if (e.key === 'Tab') { e.preventDefault(); selectCell(neighbour(td, e.shiftKey ? 'left' : 'right')); return; }
        const editable = !!(td.dataset.key || td.dataset.mkey);
        if (e.key === 'Enter') {
            e.preventDefault();
            if (editable && !e.shiftKey) startEdit(td, 'edit');
            else selectCell(neighbour(td, e.shiftKey ? 'up' : 'down'));
            return;
        }
        if (!editable) return;
        if (e.key === 'F2') { e.preventDefault(); startEdit(td, 'edit'); return; }
        if (e.key === 'Delete' || e.key === 'Backspace') { e.preventDefault(); revertCell(td); return; }
        if (e.key.length === 1 && !ctrl && !e.altKey) {
            e.preventDefault();
            startEdit(td, 'type');
            const inp = td.querySelector('input');
            if (inp) inp.value = e.key;
        }
    });
    document.addEventListener('dblclick', e => { const td = e.target.closest('td[data-key], td[data-mkey]'); if (td) startEdit(td, 'edit'); });
    document.addEventListener('focusin', e => { const td = e.target.closest && e.target.closest('.xl td'); if (td) showCell(td); });
    document.addEventListener('change', e => {
        const h = e.target.closest('[data-hkey]');
        if (h) { const k = h.dataset.hkey; if (h.value.trim() === String(h.dataset.orig).trim()) delete pending.header[k]; else pending.header[k] = h.value.trim(); h.classList.toggle('dirty', k in pending.header); updateSavebar(); return; }
        const w = e.target.closest('[data-wkey]');
        if (w) { const k = w.dataset.wkey; if (String(w.value) === String(w.dataset.orig)) delete pending.weights[k]; else pending.weights[k] = w.value; w.classList.toggle('dirty', k in pending.weights); updateSavebar(); return; }
        const s = e.target.closest('[data-skey]');
        if (s) { const k = s.dataset.skey; if (s.checked === !!R.signatures[k]) delete pending.signatures[k]; else pending.signatures[k] = s.checked; s.closest('.sig').classList.toggle('dirty', k in pending.signatures); updateSavebar(); }
    });

    // ───────── saqlash ─────────
    const toast = (m, err) => { const t = document.getElementById('toast'); t.textContent = m; t.className = 'toast show' + (err ? ' err' : ''); setTimeout(() => t.className = 'toast', 3500); };
    document.getElementById('btn-discard').addEventListener('click', () => {
        if (!confirm("Saqlanmagan o'zgarishlar bekor qilinsinmi?")) return;
        pending.rows = {}; pending.header = {}; pending.weights = {}; pending.signatures = {}; pending.system = {};
        renderAll();
    });
    async function saveAll() {
        const b = document.getElementById('btn-save');
        const top = document.getElementById('btn-save-top');
        if (b.disabled || dirtyCount() === 0) return;
        b.disabled = true; b.textContent = 'Saqlanmoqda...';
        if (top) { top.disabled = true; top.querySelector('.lbl').textContent = 'Saqlanmoqda...'; }
        try {
            // Yig'ma rejimda tuzatishlar vedomostlar bo'yicha ajratilib, har biri o'z hisobotiga saqlanadi.
            const batches = {};
            if (MULTI) {
                const add = (vid) => (batches[vid] = batches[vid] || { rows: {}, header: {}, weights: {}, signatures: {}, system: {} });
                Object.entries(pending.rows).forEach(([k, o]) => { const [vid, key] = k.split('|'); add(vid).rows[key] = o; });
                Object.entries(pending.system).forEach(([k, o]) => { const i = k.indexOf('|'); add(k.slice(0, i)).system[k.slice(i + 1)] = o; });
            } else {
                batches[''] = pending;
            }
            let saved = 0;
            for (const [vid, body] of Object.entries(batches)) {
                const res = await fetch(MULTI ? MULTI[vid] : SAVE_URL, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify(body) });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error((saved ? saved + " ta vedomost saqlandi, keyingisida: " : '') + (data.message || ('Xatolik: ' + res.status)));
                saved++;
                if (MULTI) { Object.keys(pending.rows).filter(k => k.startsWith(vid + '|')).forEach(k => delete pending.rows[k]); Object.keys(pending.system).filter(k => k.startsWith(vid + '|')).forEach(k => delete pending.system[k]); }
                if (!MULTI) sessionStorage.setItem('vedomost-report-toast', data.message || 'Saqlandi');
            }
            if (MULTI) sessionStorage.setItem('vedomost-report-toast', saved + ' ta vedomostdagi tuzatishlar saqlandi');
            pending.rows = {}; pending.header = {}; pending.weights = {}; pending.signatures = {}; pending.system = {};
            location.reload();
        } catch (err) {
            toast(err.message, true); b.disabled = false; b.textContent = 'Saqlash';
            if (top) { top.querySelector('.lbl').textContent = 'Saqlash'; updateSavebar(); }
        }
    }
    document.getElementById('btn-save').addEventListener('click', saveAll);
    document.getElementById('btn-save-top')?.addEventListener('click', saveAll);
    document.addEventListener('keydown', e => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); saveAll(); }
    });
    window.addEventListener('beforeunload', e => { if (dirtyCount() > 0 && !window.__approving) { e.preventDefault(); e.returnValue = ''; } });

    // ───────── varaqlar, filtrlar ─────────
    function showTab(name) {
        document.querySelectorAll('.tab').forEach(t => t.setAttribute('aria-selected', t.dataset.tab === name ? 'true' : 'false'));
        document.querySelectorAll('.sheet').forEach(s => s.classList.toggle('active', s.id === 'sheet-' + name));
        history.replaceState(null, '', '#' + name);
    }
    document.querySelectorAll('.tab').forEach(t => t.addEventListener('click', () => showTab(t.dataset.tab)));
    function setChips() { document.querySelectorAll('.chip[data-filter]').forEach(c => c.setAttribute('aria-pressed', c.dataset.filter === filter ? 'true' : 'false')); }
    document.querySelectorAll('.chip[data-filter]').forEach(c => c.addEventListener('click', () => { filter = c.dataset.filter; setChips(); renderMain(); renderPdf(); }));
    let qt;
    document.getElementById('search').addEventListener('input', e => { clearTimeout(qt); qt = setTimeout(() => { query = e.target.value.trim(); renderMain(); renderPdf(); }, 150); });
    document.getElementById('all-cols').addEventListener('change', e => { allCols = e.target.checked; renderMain(); });
    const sysBtn = document.getElementById('sys-mode');
    if (sysBtn) sysBtn.addEventListener('click', () => {
        sysMode = !sysMode;
        sysBtn.setAttribute('aria-pressed', sysMode ? 'true' : 'false');
        if (sysMode) { filter = 'all'; setChips(); showTab('main'); toast("Mark ustunlari (JN, MT, ON, OSKI, Test, YN) tahrirlanadi — faqat shu hisobotda, sabab bilan"); }
        renderMain();
    });

    setChips();
    renderAll();
    showTab(MULTI && @json((bool) (session('success') || session('error'))) ? 'approve'
        : (['main', 'header', 'errors', 'pdf', 'history', ...(MULTI ? ['approve'] : [])].includes(location.hash.slice(1)) ? location.hash.slice(1) : 'main'));
    document.querySelectorAll('[data-open-tab]').forEach(b => b.addEventListener('click', () => showTab(b.dataset.openTab)));
    if (MULTI) {
        const form = document.getElementById('approve-form');
        const cbs = () => [...document.querySelectorAll('.ap-cb')];
        const syncAp = () => {
            const n = cbs().filter(c => c.checked).length;
            const btn = document.getElementById('btn-approve');
            if (!btn) return;
            document.getElementById('ap-n').textContent = n;
            btn.disabled = n === 0 || dirtyCount() > 0;
            btn.title = dirtyCount() > 0 ? 'Avval tuzatishlarni saqlang' : '';
        };
        document.addEventListener('change', e => { if (e.target.classList?.contains('ap-cb')) syncAp(); });
        const _upd = updateSavebar;
        updateSavebar = function () { _upd(); syncAp(); };
        form?.addEventListener('submit', e => {
            const missing = cbs().filter(c => c.checked).filter(c => { const w = document.querySelector(`.ap-why[data-vid="${c.value}"]`); return w && w.value.trim().length < 5; });
            if (missing.length) { e.preventDefault(); toast(missing.length + ' ta vedomost uchun sabab yozilmagan (kamida 5 belgi)', true); document.querySelector(`.ap-why[data-vid="${missing[0].value}"]`).focus(); return; }
            if (!confirm(cbs().filter(c => c.checked).length + ' ta vedomost tasdiqlansinmi?')) { e.preventDefault(); return; }
            window.__approving = true;
        });
        syncAp();
    }
    const msg = sessionStorage.getItem('vedomost-report-toast');
    if (msg) { sessionStorage.removeItem('vedomost-report-toast'); toast(msg); }
})();
</script>
</body>
</html>
