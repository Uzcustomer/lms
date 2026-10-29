<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Tekshiruv — {{ $merged->group_name ?? $v->group_name }} · {{ $merged->subject_name ?? $v->subject_name }}</title>
    <style>
        :root {
            --bg: #f8fafc; --surface: #ffffff; --surface-2: #f1f5f9; --border: #e2e8f0; --border-strong: #cbd5e1;
            --text: #0f172a; --text-2: #334155; --muted: #64748b;
            --primary: #1e40af; --primary-600: #1d4ed8; --primary-50: #eff6ff;
            --bad-bg: #fef08a; --bad-text: #713f12; --bad-soft: #fefce8;
            --ocr-bg: #fed7aa; --ocr-text: #7c2d12; --ocr-soft: #fff7ed;
            --miss-bg: #fee2e2; --miss-text: #991b1b;
            --extra-bg: #f3e8ff; --extra-text: #6b21a8;
            --ok-bg: #dcfce7; --ok-text: #166534;
            --edit: #2563eb; --edit-soft: #dbeafe;
            --sys: #f8fafc;
            --radius: 8px;
            --font: Inter, ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif;
        }
        * { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; }
        body { font-family: var(--font); font-size: 14px; color: var(--text); background: var(--bg); display: flex; flex-direction: column; height: 100dvh; overflow: hidden; }
        button, input { font-family: inherit; font-size: inherit; }
        .icon { width: 16px; height: 16px; flex-shrink: 0; stroke: currentColor; fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; }
        :focus-visible { outline: 2px solid var(--primary-600); outline-offset: 2px; }

        /* Topbar */
        .topbar { display: flex; align-items: center; gap: 16px; padding: 10px 20px; background: var(--surface); border-bottom: 1px solid var(--border); }
        .back { display: inline-flex; align-items: center; justify-content: center; width: 36px; height: 36px; border-radius: var(--radius); color: var(--muted); text-decoration: none; border: 1px solid var(--border); }
        .back:hover { background: var(--surface-2); color: var(--text); }
        .title { min-width: 0; flex: 1; }
        .title h1 { font-size: 16px; font-weight: 700; margin: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .title .sub { font-size: 12px; color: var(--muted); margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .verdict { display: inline-flex; align-items: center; gap: 6px; padding: 6px 12px; border-radius: 999px; font-weight: 700; font-size: 13px; white-space: nowrap; }
        .verdict.ok { background: var(--ok-bg); color: var(--ok-text); }
        .verdict.issues { background: var(--bad-bg); color: var(--bad-text); }
        .verdict.review { background: var(--ocr-bg); color: var(--ocr-text); }
        .verdict.wrong_document { background: var(--miss-bg); color: var(--miss-text); }
        .btn { display: inline-flex; align-items: center; gap: 6px; height: 36px; padding: 0 14px; border-radius: var(--radius); border: 1px solid var(--border-strong); background: var(--surface); color: var(--text-2); font-weight: 600; font-size: 13px; cursor: pointer; text-decoration: none; white-space: nowrap; transition: background .15s, border-color .15s; }
        .btn:hover { background: var(--surface-2); }
        .btn.primary { background: var(--primary); border-color: var(--primary); color: #fff; }
        .btn.primary:hover { background: var(--primary-600); }
        .btn:disabled { opacity: .5; cursor: not-allowed; }

        /* Summary row */
        .toolbar { display: flex; align-items: center; gap: 10px; padding: 10px 20px; background: var(--surface); border-bottom: 1px solid var(--border); flex-wrap: wrap; }
        .summary-text { font-size: 13px; color: var(--text-2); flex-basis: 100%; }
        .chips { display: flex; gap: 6px; flex-wrap: wrap; }
        .chip { display: inline-flex; align-items: center; gap: 6px; height: 32px; padding: 0 12px; border-radius: 999px; border: 1px solid var(--border); background: var(--surface); color: var(--text-2); font-size: 13px; font-weight: 600; cursor: pointer; transition: background .15s, border-color .15s; }
        .chip .n { font-variant-numeric: tabular-nums; color: var(--muted); font-weight: 700; }
        .chip:hover { border-color: var(--border-strong); }
        .chip[aria-pressed="true"] { background: var(--primary-50); border-color: var(--primary); color: var(--primary); }
        .chip[aria-pressed="true"] .n { color: var(--primary); }
        .chip .dot { width: 10px; height: 10px; border-radius: 3px; }
        .spacer { flex: 1; }
        .search { position: relative; }
        .search input { height: 32px; width: 240px; padding: 0 10px 0 32px; border: 1px solid var(--border-strong); border-radius: var(--radius); background: var(--surface); }
        .search .icon { position: absolute; left: 10px; top: 8px; color: var(--muted); }
        .toggle { display: inline-flex; align-items: center; gap: 6px; font-size: 13px; color: var(--text-2); cursor: pointer; user-select: none; }

        .banner { display: flex; gap: 12px; align-items: flex-start; margin: 12px 20px 0; padding: 12px 16px; border-radius: var(--radius); background: var(--miss-bg); color: var(--miss-text); border: 1px solid #fecaca; font-size: 14px; }
        .banner b { display: block; margin-bottom: 2px; }

        /* Sheet area */
        .sheet-area { flex: 1; min-height: 0; position: relative; }
        .sheet { position: absolute; inset: 0; overflow: auto; display: none; }
        .sheet.active { display: block; }
        .pad { padding: 16px 20px 24px; }

        /* Grid */
        table.grid { border-collapse: separate; border-spacing: 0; font-size: 13px; font-variant-numeric: tabular-nums; background: var(--surface); }
        .grid th, .grid td { border-right: 1px solid var(--border); border-bottom: 1px solid var(--border); padding: 0 8px; height: 30px; white-space: nowrap; text-align: right; }
        .grid thead th { position: sticky; background: #eef2f7; color: var(--text-2); font-weight: 700; font-size: 12px; text-align: center; z-index: 3; }
        .grid thead tr:first-child th { top: 0; height: 28px; }
        .grid thead tr:nth-child(2) th { top: 28px; height: 24px; font-weight: 600; color: var(--muted); }
        .grid .stick { position: sticky; z-index: 2; background: var(--surface); text-align: left; }
        .grid thead .stick { z-index: 4; background: #eef2f7; }
        .grid .c-n { left: 0; width: 44px; min-width: 44px; text-align: center; color: var(--muted); }
        .grid .c-fio { left: 44px; width: 280px; min-width: 280px; max-width: 280px; overflow: hidden; text-overflow: ellipsis; font-weight: 600; }
        .grid .c-id { left: 324px; width: 124px; min-width: 124px; color: var(--text-2); border-right: 2px solid var(--border-strong); }
        .grid td.sys { background: var(--sys); color: var(--muted); }
        .grid td.pdf { cursor: default; position: relative; min-width: 64px; }
        .grid td.pdf.editable { cursor: cell; }
        .grid td.pdf.editable:hover { box-shadow: inset 0 0 0 1px var(--border-strong); }
        .grid td.delta { color: var(--muted); font-size: 12px; min-width: 44px; }
        .grid td.grp-end, .grid th.grp-end { border-right: 2px solid var(--border-strong); }
        .grid td.s-bad { background: var(--bad-bg); color: var(--bad-text); font-weight: 700; }
        .grid td.s-ocr { background: var(--ocr-bg); color: var(--ocr-text); font-weight: 700; }
        .grid td.d-bad { background: var(--bad-soft); color: var(--bad-text); font-weight: 700; }
        .grid td.d-ocr { background: var(--ocr-soft); color: var(--ocr-text); font-weight: 700; }
        .grid td.edited::before { content: ""; position: absolute; top: 0; right: 0; border-style: solid; border-width: 0 8px 8px 0; border-color: transparent var(--edit) transparent transparent; }
        .grid td.dirty { box-shadow: inset 0 0 0 2px var(--edit); background: var(--edit-soft); }
        .grid td:focus { outline: none; box-shadow: inset 0 0 0 2px var(--primary-600); z-index: 1; }
        .grid td input.cell-input { width: 100%; min-width: 60px; height: 26px; border: none; outline: none; background: #fff; text-align: right; font-variant-numeric: tabular-nums; padding: 0; }
        .grid tbody tr:hover td:not(.s-bad):not(.s-ocr):not(.dirty) { background-image: linear-gradient(rgba(30,64,175,.04), rgba(30,64,175,.04)); }
        .grid tr.r-missing td.stick, .grid tr.r-missing td { background-color: #fff5f5; }
        .grid tr.r-extra td.stick, .grid tr.r-extra td { background-color: #faf5ff; }
        .grid tr.flash td { animation: flash 1.4s ease-out; }
        @keyframes flash { 0% { background-color: #bfdbfe; } 100% { } }
        .grid td.c-status { text-align: left; }
        .grid td.c-note { text-align: left; white-space: normal; min-width: 300px; max-width: 360px; font-size: 12px; color: var(--text-2); line-height: 1.4; padding: 4px 8px; }
        .muted { color: var(--muted); }
        .na { color: #cbd5e1; }

        .badge { display: inline-flex; align-items: center; gap: 4px; height: 22px; padding: 0 8px; border-radius: 999px; font-size: 12px; font-weight: 700; white-space: nowrap; }
        .badge .icon { width: 13px; height: 13px; }
        .b-ok { background: var(--ok-bg); color: var(--ok-text); }
        .b-bad { background: var(--bad-bg); color: var(--bad-text); }
        .b-ocr { background: var(--ocr-bg); color: var(--ocr-text); }
        .b-missing { background: var(--miss-bg); color: var(--miss-text); }
        .b-extra { background: var(--extra-bg); color: var(--extra-text); }
        .b-warn { background: var(--ocr-bg); color: var(--ocr-text); }
        .b-info { background: var(--surface-2); color: var(--text-2); }

        .empty { padding: 48px; text-align: center; color: var(--muted); }

        /* Cards (Sarlavha / Tarix) */
        .card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; margin-bottom: 16px; overflow: hidden; }
        .card h2 { font-size: 14px; margin: 0; padding: 12px 16px; border-bottom: 1px solid var(--border); background: #fbfcfe; display: flex; align-items: center; gap: 8px; }
        .kv { width: 100%; border-collapse: collapse; font-size: 13px; }
        .kv th, .kv td { padding: 9px 16px; border-bottom: 1px solid var(--border); text-align: left; vertical-align: top; }
        .kv th { color: var(--muted); font-weight: 600; font-size: 12px; background: #fbfcfe; }
        .kv td.edit-text { cursor: text; }
        .kv td.edit-text:hover { background: var(--primary-50); }
        .kv input.text-input { width: 100%; height: 30px; border: 1px solid var(--primary); border-radius: 6px; padding: 0 8px; }
        .weights { display: flex; gap: 10px; flex-wrap: wrap; padding: 12px 16px; }
        .weights label { display: flex; flex-direction: column; gap: 4px; font-size: 12px; color: var(--muted); font-weight: 600; }
        .weights input { width: 72px; height: 32px; border: 1px solid var(--border-strong); border-radius: 6px; padding: 0 8px; text-align: right; font-variant-numeric: tabular-nums; }
        .weights input.dirty, .sig input.dirty + span { box-shadow: 0 0 0 2px var(--edit); }
        .sigs { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 10px; padding: 12px 16px; }
        .sig { display: flex; align-items: center; gap: 10px; padding: 10px 12px; border: 1px solid var(--border); border-radius: var(--radius); cursor: pointer; }
        .sig input { width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer; }
        .issues { list-style: none; margin: 0; padding: 4px 0; }
        .issues li { display: flex; gap: 10px; align-items: flex-start; padding: 8px 16px; font-size: 13px; border-bottom: 1px solid var(--border); }
        .issues li:last-child { border-bottom: none; }
        .meta-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1px; background: var(--border); }
        .meta-grid div { background: var(--surface); padding: 10px 16px; font-size: 13px; }
        .meta-grid span { display: block; font-size: 11px; color: var(--muted); font-weight: 600; text-transform: uppercase; letter-spacing: .02em; margin-bottom: 2px; }
        .log { padding: 10px 16px; border-bottom: 1px solid var(--border); font-size: 13px; }
        .log .who { color: var(--muted); font-size: 12px; margin-bottom: 4px; }
        .log pre { margin: 0; white-space: pre-wrap; font-family: inherit; color: var(--text-2); }
        .link { color: var(--primary); cursor: pointer; text-decoration: underline; background: none; border: none; padding: 0; font-weight: 600; font-size: 12px; }

        /* Bottom: save bar + tabs */
        .savebar { display: none; align-items: center; gap: 12px; padding: 10px 20px; background: #1e293b; color: #fff; font-size: 13px; }
        .savebar.show { display: flex; }
        .savebar .btn { height: 32px; }
        .savebar .btn.ghost { background: transparent; color: #e2e8f0; border-color: #475569; }
        .savebar .btn.ghost:hover { background: #334155; }
        .tabs { display: flex; align-items: stretch; gap: 2px; padding: 0 12px; background: #e2e8f0; border-top: 1px solid var(--border-strong); min-height: 40px; }
        .tab { display: inline-flex; align-items: center; gap: 6px; padding: 0 16px; border: none; background: transparent; color: var(--text-2); font-weight: 600; font-size: 13px; cursor: pointer; border-top: 3px solid transparent; }
        .tab:hover { background: #f1f5f9; }
        .tab[aria-selected="true"] { background: var(--surface); color: var(--primary); border-top-color: var(--primary); }
        .tab .n { background: var(--bad-bg); color: var(--bad-text); border-radius: 999px; padding: 0 6px; font-size: 11px; }
        .legend { display: flex; align-items: center; gap: 12px; margin-left: auto; font-size: 12px; color: var(--text-2); padding: 0 8px; flex-wrap: wrap; }
        .legend i { display: inline-block; width: 12px; height: 12px; border-radius: 3px; margin-right: 4px; vertical-align: -2px; border: 1px solid rgba(0,0,0,.08); }
        .toast { position: fixed; right: 20px; bottom: 64px; padding: 10px 16px; border-radius: var(--radius); background: #0f172a; color: #fff; font-size: 13px; box-shadow: 0 8px 24px rgba(15,23,42,.25); opacity: 0; transform: translateY(8px); transition: opacity .2s, transform .2s; pointer-events: none; z-index: 50; }
        .toast.show { opacity: 1; transform: none; }
        .toast.err { background: #991b1b; }
        @media (max-width: 900px) {
            .topbar { flex-wrap: wrap; padding: 10px 16px; }
            .toolbar { padding: 10px 16px; }
            .search input { width: 100%; }
            .search { flex: 1; }
            .legend { display: none; }
            .grid .c-fio { width: 160px; min-width: 160px; max-width: 160px; }
            .grid .c-id { position: static; border-right: 1px solid var(--border); }
            .tabs { overflow-x: auto; }
            .tab { white-space: nowrap; padding: 0 12px; }
            .savebar .hint { display: none; }
            .title .sub { white-space: normal; }
        }
        @media (prefers-reduced-motion: reduce) { * { animation: none !important; transition: none !important; } }
    </style>
</head>
<body>
@php
    $r = $report;
    $c = $r['counts'];
    $verdictTxt = [
        'ok' => 'Mos keladi', 'issues' => 'Nomuvofiqlik bor', 'review' => "Ko'z bilan tekshirish kerak", 'wrong_document' => "Noto'g'ri fayl",
    ][$r['verdict']] ?? $r['verdict'];
    $problems = $c['bad'] + $c['ocr'] + $c['missing'] + $c['extra'];
@endphp

<svg width="0" height="0" style="position:absolute" aria-hidden="true">
    <symbol id="i-back" viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></symbol>
    <symbol id="i-check" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></symbol>
    <symbol id="i-alert" viewBox="0 0 24 24"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0zM12 9v4M12 17h.01"/></symbol>
    <symbol id="i-eye" viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></symbol>
    <symbol id="i-minus" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M8 12h8"/></symbol>
    <symbol id="i-plus" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v8M8 12h8"/></symbol>
    <symbol id="i-x" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/></symbol>
    <symbol id="i-file" viewBox="0 0 24 24"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 13h6M9 17h6"/></symbol>
    <symbol id="i-download" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></symbol>
    <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></symbol>
    <symbol id="i-pencil" viewBox="0 0 24 24"><path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/></symbol>
    <symbol id="i-info" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></symbol>
</svg>

<header class="topbar">
    <a class="back" href="{{ route('admin.vedomost-submission.show', $v->id) }}" aria-label="Vedomost sahifasiga qaytish">
        <svg class="icon"><use href="#i-back"/></svg>
    </a>
    <div class="title">
        <h1>{{ $merged->group_name ?? $v->group_name }} — {{ $merged->subject_name ?? $v->subject_name }}</h1>
        <div class="sub">
            {{ \App\Models\VedomostSubmission::formLabel($v->form_type) }}
            · AI tekshiruv {{ $v->ai_checked_at ? \Carbon\Carbon::parse($v->ai_checked_at)->format('d.m.Y H:i') : '' }}
            @if($model) · {{ $model }} @endif
            @if(!$canEdit) · <b>faqat ko'rish</b>@endif
        </div>
    </div>
    <span class="verdict {{ $r['verdict'] }}">
        <svg class="icon"><use href="#{{ $r['verdict'] === 'ok' ? 'i-check' : 'i-alert' }}"/></svg>{{ $verdictTxt }}
    </span>
    @if($v->pdf_path)
        <a class="btn" href="{{ route('admin.vedomost-submission.file', [$v->id, 'pdf']) }}" target="_blank" rel="noopener">
            <svg class="icon"><use href="#i-file"/></svg>PDF
        </a>
    @endif
    <a class="btn" href="{{ route('admin.vedomost-submission.ai-report.excel', $v->id) }}">
        <svg class="icon"><use href="#i-download"/></svg>Excel
    </a>
</header>

<section class="toolbar" aria-label="Filtrlar">
    <div class="summary-text">{{ $r['summary'] }}</div>
    <div class="chips" role="group" aria-label="Qatorlar filtri">
        <button class="chip" data-filter="problems" aria-pressed="false">Muammolilar <span class="n">{{ $problems }}</span></button>
        <button class="chip" data-filter="all" aria-pressed="false">Hammasi <span class="n">{{ $c['total'] }}</span></button>
        <button class="chip" data-filter="bad" aria-pressed="false"><span class="dot" style="background:var(--bad-bg)"></span>Xato <span class="n">{{ $c['bad'] }}</span></button>
        <button class="chip" data-filter="ocr" aria-pressed="false"><span class="dot" style="background:var(--ocr-bg)"></span>Tekshiring <span class="n">{{ $c['ocr'] }}</span></button>
        <button class="chip" data-filter="missing" aria-pressed="false"><span class="dot" style="background:var(--miss-bg)"></span>PDFda yo'q <span class="n">{{ $c['missing'] }}</span></button>
        <button class="chip" data-filter="extra" aria-pressed="false"><span class="dot" style="background:var(--extra-bg)"></span>Ortiqcha <span class="n">{{ $c['extra'] }}</span></button>
        <button class="chip" data-filter="edited" aria-pressed="false"><span class="dot" style="background:var(--edit)"></span>Tuzatilgan <span class="n">{{ $c['edited'] }}</span></button>
    </div>
    <div class="spacer"></div>
    <label class="toggle"><input type="checkbox" id="show-balls" checked> Ballar ustunlari</label>
    <div class="search">
        <svg class="icon"><use href="#i-search"/></svg>
        <input type="search" id="search" placeholder="F.I.Sh yoki ID bo'yicha qidirish" aria-label="Qidirish">
    </div>
</section>

@if($r['verdict'] === 'wrong_document')
    <div class="banner" role="alert">
        <svg class="icon" style="width:20px;height:20px;margin-top:2px"><use href="#i-alert"/></svg>
        <div><b>Yuklangan PDF bu vedomostga tegishli emas ko'rinadi</b>{{ $r['summary'] }}</div>
    </div>
@endif

<main class="sheet-area">
    <section class="sheet" id="sheet-main" role="tabpanel" aria-label="Tekshirish"></section>
    <section class="sheet" id="sheet-header" role="tabpanel" aria-label="Sarlavha va imzolar"><div class="pad" id="header-pad"></div></section>
    <section class="sheet" id="sheet-errors" role="tabpanel" aria-label="Xatolar"></section>
    <section class="sheet" id="sheet-pdf" role="tabpanel" aria-label="PDF o'qilgani"></section>
    <section class="sheet" id="sheet-history" role="tabpanel" aria-label="Tarix">
        <div class="pad">
            <div class="card">
                <h2><svg class="icon"><use href="#i-info"/></svg>AI o'qish ma'lumotlari</h2>
                @php $m = $r['meta'] ?? []; @endphp
                <div class="meta-grid">
                    <div><span>Rejim</span>{{ ($m['mode'] ?? '') === 'images' ? "Rasm, {$m['bands']} bo'lak" : 'Butun PDF (bo\'laklashsiz)' }}</div>
                    <div><span>Model</span>{{ $m['model'] ?? $model ?? '—' }}</div>
                    <div><span>Sahifalar</span>{{ $m['pages'] ?? '—' }}</div>
                    <div><span>Qayta o'qib tuzatildi</span>{{ $m['repaired'] ?? 0 }} qator</div>
                    <div><span>Noaniq kataklar</span>{{ $m['unclear_cells'] ?? 0 }}</div>
                    <div><span>Keshdan</span>{{ !empty($m['cached']) ? 'ha (limit sarflanmadi)' : "yo'q" }}</div>
                    <div><span>O'qilgan vaqt</span>{{ $m['read_at'] ?? '—' }}</div>
                </div>
            </div>
            <div class="card">
                <h2><svg class="icon"><use href="#i-pencil"/></svg>Xodim tuzatishlari tarixi</h2>
                @forelse($history as $log)
                    <div class="log">
                        <div class="who">{{ $log->created_at->format('d.m.Y H:i') }} · {{ $log->user_name }}</div>
                        <pre>{{ $log->note }}</pre>
                    </div>
                @empty
                    <div class="empty">Hali tuzatish kiritilmagan.</div>
                @endforelse
            </div>
        </div>
    </section>
</main>

<div class="savebar" id="savebar" role="region" aria-live="polite">
    <svg class="icon"><use href="#i-pencil"/></svg>
    <span><b id="dirty-count">0</b> ta saqlanmagan o'zgarish<span class="hint"> · saqlanganda hisobot qayta hisoblanadi</span></span>
    <div class="spacer"></div>
    <button class="btn ghost" id="btn-discard">Bekor qilish</button>
    <button class="btn primary" id="btn-save">Saqlash</button>
</div>

<nav class="tabs" role="tablist" aria-label="Varaqlar">
    <button class="tab" role="tab" data-tab="main">Tekshirish</button>
    <button class="tab" role="tab" data-tab="header">Sarlavha va imzolar
        @php $hb = collect($r['header'])->whereIn('status', ['bad'])->count() + collect($r['doc_issues'])->where('t', 'bad')->count(); @endphp
        @if($hb)<span class="n">{{ $hb }}</span>@endif
    </button>
    <button class="tab" role="tab" data-tab="errors">Xatolar @if($problems)<span class="n">{{ $problems }}</span>@endif</button>
    <button class="tab" role="tab" data-tab="pdf">PDF o'qilgani</button>
    <button class="tab" role="tab" data-tab="history">Tarix</button>
    <div class="legend" aria-label="Ranglar">
        <span><i style="background:var(--bad-bg)"></i>Farq bor</span>
        <span><i style="background:var(--ocr-bg)"></i>AI aniq o'qimagan</span>
        <span><i style="background:var(--miss-bg)"></i>PDFda yo'q</span>
        <span><i style="background:var(--extra-bg)"></i>Tizimda yo'q</span>
        <span><i style="background:var(--edit-soft);border-color:var(--edit)"></i>Tuzatilgan</span>
    </div>
</nav>

<div class="toast" id="toast" role="status" aria-live="polite"></div>

<script>
(function () {
    const R = @json($report);
    const LABELS = @json($labels);
    const CAN_EDIT = @json($canEdit);
    const SAVE_URL = @json(route('admin.vedomost-submission.ai-report.edits', $v->id));
    const HEADER_EDITABLE = @json(\App\Services\VedomostCheckEngine::HEADER_EDITABLE);
    const STATUS = {
        ok: ['b-ok', 'i-check', 'OK'], bad: ['b-bad', 'i-alert', 'Xato'], ocr: ['b-ocr', 'i-eye', 'Tekshiring'],
        missing: ['b-missing', 'i-minus', "PDFda yo'q"], extra: ['b-extra', 'i-plus', "Tizimda yo'q"],
        warn: ['b-warn', 'i-eye', 'Tekshiring'], info: ['b-info', 'i-info', "Ma'lumot"],
    };
    const NUMERIC = f => !['ects', 'baho', 'fio', 'id'].includes(f);
    const TOL = 0.05;

    const pending = { rows: {}, header: {}, weights: {}, signatures: {} };
    let filter = R.counts.bad + R.counts.ocr + R.counts.missing + R.counts.extra > 0 ? 'problems' : 'all';
    let query = '';
    let showBalls = true;

    const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const num = v => { if (v === null || v === undefined) return null; const s = String(v).replace(',', '.').replace('%', '').trim(); return s === '' || isNaN(+s) ? null : +s; };
    const fmt = v => { const n = num(v); if (n === null) return v === null || v === undefined || v === '' ? '' : String(v); return Number.isInteger(n) ? String(n) : String(+n.toFixed(2)); };
    const icon = id => `<svg class="icon"><use href="#${id}"/></svg>`;
    const badge = st => { const s = STATUS[st] || STATUS.info; return `<span class="badge ${s[0]}">${icon(s[1])}${s[2]}</span>`; };

    function fields() {
        const c = R.controls || [];
        const f = ['jb_pct', 'jb_ball', 'mt_pct', 'mt_ball'];
        if (c.includes('on')) f.push('on_pct', 'on_ball');
        f.push('jbmton_ball');
        if (c.includes('oski')) f.push('oski_pct', 'oski_ball');
        if (c.includes('test')) f.push('test_pct', 'test_ball');
        f.push('yn', 'ects', 'baho');
        return showBalls ? f : f.filter(x => !x.endsWith('_ball') || x === 'jbmton_ball');
    }

    // Joriy (saqlangan) PDF qiymati — tuzatishlar bilan.
    function savedValue(row, f) {
        if (f === 'fio') return row.scan_fio ?? '';
        if (f === 'id') return row.scan_id ?? '';
        return row.scan ? (row.scan[f] ?? '') : '';
    }
    // Ko'rsatiladigan qiymat: saqlanmagan o'zgarish (null — AI o'qigan asl qiymatga qaytish).
    function pdfValue(row, f) {
        if (isDirty(row, f)) {
            const p = pending.rows[row.key][f];
            return p === null ? ((row.orig || {})[f] ?? '') : p;
        }
        return savedValue(row, f);
    }
    function isDirty(row, f) { return !!(row.key && pending.rows[row.key] && f in pending.rows[row.key]); }

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

    // ───────── Tekshirish varag'i ─────────
    function renderMain() {
        const F = fields();
        const tm = (R.controls || []).filter(k => k === 'oski' || k === 'test').filter(k => R.rows.some(r => r.tm && r.tm[k]));
        let h1 = '<tr><th class="stick c-n" rowspan="2">№</th><th class="stick c-fio" rowspan="2">F.I.Sh</th><th class="stick c-id" rowspan="2">ID raqami</th>'
            + '<th rowspan="2" style="text-align:left">Holat</th><th rowspan="2" class="grp-end" style="text-align:left">Izoh</th>';
        let h2 = '<tr>';
        F.forEach(f => {
            const span = NUMERIC(f) ? 3 : 2;
            h1 += `<th colspan="${span}" class="grp-end">${esc(LABELS[f] || f)}</th>`;
            h2 += '<th>Tizim</th><th' + (NUMERIC(f) ? '' : ' class="grp-end"') + '>PDF</th>' + (NUMERIC(f) ? '<th class="grp-end">Δ</th>' : '');
        });
        tm.forEach(k => { h1 += `<th rowspan="2" class="grp-end">${k === 'oski' ? 'OSKE' : 'Test'}<br>test markazi</th>`; });
        h1 += '</tr>';
        h2 += '</tr>';

        let body = '';
        let shown = 0;
        R.rows.forEach((row, ri) => {
            if (!visible(row)) return;
            shown++;
            const cls = row.status === 'missing' ? 'r-missing' : row.status === 'extra' ? 'r-extra' : '';
            body += `<tr class="${cls}" data-ri="${ri}">`;
            body += `<td class="stick c-n">${esc(row.n ?? '')}</td>`;
            body += `<td class="stick c-fio" title="${esc(row.fio)}${row.scan_fio && row.scan_fio !== row.fio ? ' · PDF: ' + esc(row.scan_fio) : ''}">${esc(row.fio)}</td>`;
            body += `<td class="stick c-id">${esc(row.id)}</td>`;
            const notes = (row.flags || []).map(fl => esc(fl.m));
            body += `<td class="c-status">${badge(row.status)}</td>`;
            body += `<td class="c-note grp-end">${notes.join('<br>') || '<span class="muted">—</span>'}</td>`;
            F.forEach(f => {
                const sys = row.system ? row.system[f] : null;
                body += `<td class="sys">${row.status === 'extra' ? '<span class="na">—</span>' : esc(fmt(sys))}</td>`;
                const val = pdfValue(row, f);
                let st = (row.cells || {})[f];
                const dirty = isDirty(row, f);
                if (dirty && NUMERIC(f)) {
                    const a = num(val), b = num(sys);
                    st = a !== null && b !== null && Math.abs(a - b) <= (f === 'yn' ? 0.5 : TOL) ? 'ok' : (a === null && b === null ? 'empty' : 'bad');
                }
                const cl = ['pdf'];
                if (!NUMERIC(f)) cl.push('grp-end');
                if (st === 'bad') cl.push('s-bad');
                if (st === 'ocr') cl.push('s-ocr');
                if ((row.edited || []).includes(f)) cl.push('edited');
                if (dirty) cl.push('dirty');
                const canEdit = CAN_EDIT && row.key;
                if (canEdit) cl.push('editable');
                const tip = [];
                if (st === 'bad') tip.push('Tizimda: ' + fmt(sys));
                if (st === 'ocr') tip.push("AI aniq o'qiy olmagan — PDFdan tekshiring");
                if ((row.edited || []).includes(f)) tip.push("AI o'qigan: " + ((row.orig || {})[f] ?? ''));
                if (canEdit) tip.push('Tahrirlash: Enter yoki ikki marta bosing');
                body += `<td class="${cl.join(' ')}" ${canEdit ? `tabindex="0" data-key="${row.key}" data-field="${f}"` : ''} title="${esc(tip.join(' · '))}">${row.status === 'missing' ? '<span class="na">—</span>' : esc(fmt(val))}</td>`;
                if (NUMERIC(f)) {
                    const a = num(val), b = num(sys);
                    const d = a !== null && b !== null ? +(a - b).toFixed(2) : null;
                    const dc = st === 'bad' ? 'd-bad' : st === 'ocr' ? 'd-ocr' : '';
                    body += `<td class="delta grp-end ${dc}">${d === null || d === 0 ? '' : (d > 0 ? '+' : '') + d}</td>`;
                }
            });
            tm.forEach(k => {
                const t = (row.tm || {})[k];
                const c = t && t.status === 'bad' ? 's-bad' : '';
                body += `<td class="grp-end ${c}">${t ? (t.value === null ? "yo'q" : esc(fmt(t.value)) + (t.dalolat ? ' (D)' : '')) : ''}</td>`;
            });
            body += '</tr>';
        });

        const el = document.getElementById('sheet-main');
        el.innerHTML = shown === 0
            ? `<div class="empty">${filter === 'problems' ? "Muammoli qator yo'q — barcha talabalar mos keladi." : "Tanlangan filtr bo'yicha qator yo'q."}</div>`
            : `<table class="grid" aria-label="Talabalar bo'yicha tekshiruv"><thead>${h1}${h2}</thead><tbody>${body}</tbody></table>`;
    }

    // ───────── PDF o'qilgani ─────────
    function renderPdf() {
        const F = ['fio', 'id', 'jb_pct', 'jb_ball', 'mt_pct', 'mt_ball', 'on_pct', 'on_ball', 'jbmton_ball', 'oski_pct', 'oski_ball', 'test_pct', 'test_ball', 'yn', 'ects', 'baho'];
        let head = '<tr><th class="stick c-n">Bet</th>' + F.map(f => `<th>${esc(LABELS[f] || f)}</th>`).join('') + '<th style="text-align:left">Noaniq</th></tr>';
        let body = '';
        R.rows.forEach(row => {
            if (!row.key || !visible(row)) return;
            body += '<tr>';
            body += `<td class="stick c-n">${esc(row.page ?? '')}</td>`;
            F.forEach(f => {
                const val = pdfValue(row, f);
                const cl = ['pdf'];
                if ((row.unclear || []).includes(f)) cl.push('s-ocr');
                if ((row.edited || []).includes(f)) cl.push('edited');
                if (isDirty(row, f)) cl.push('dirty');
                if (CAN_EDIT) cl.push('editable');
                const style = f === 'fio' ? ' style="text-align:left;min-width:240px"' : f === 'id' ? ' style="min-width:120px"' : '';
                body += `<td class="${cl.join(' ')}"${style} ${CAN_EDIT ? `tabindex="0" data-key="${row.key}" data-field="${f}"` : ''}>${esc(fmt(val) || val)}</td>`;
            });
            body += `<td class="c-note">${esc((row.unclear || []).map(f => LABELS[f] || f).join(', '))}</td></tr>`;
        });
        document.getElementById('sheet-pdf').innerHTML = body
            ? `<table class="grid" aria-label="PDFdan o'qilgan jadval"><thead>${head}</thead><tbody>${body}</tbody></table>`
            : '<div class="empty">Qator yo\'q.</div>';
    }

    // ───────── Xatolar ─────────
    function renderErrors() {
        let html = '<div class="pad"><div class="card"><h2>' + icon('i-alert') + 'Talabalar bo\'yicha</h2><ul class="issues">';
        let any = false;
        R.rows.forEach((row, ri) => {
            if (row.status === 'ok') return;
            any = true;
            html += `<li>${badge(row.status)}<div style="flex:1"><b>${esc(row.n ?? '')}. ${esc(row.fio)}</b> <span class="muted">${esc(row.id)}</span><br>`
                + (row.flags || []).filter(f => f.t !== 'info').map(f => esc(f.m)).join('<br>')
                + `</div><button class="link" data-goto="${ri}">Jadvalda ko'rish</button></li>`;
        });
        if (!any) html += '<li class="muted">Talabalar bo\'yicha xato yo\'q.</li>';
        html += '</ul></div>';
        const hdr = R.header.filter(h => h.status === 'bad' || h.status === 'warn');
        const doc = R.doc_issues.filter(d => d.t !== 'info');
        if (hdr.length || doc.length) {
            html += '<div class="card"><h2>' + icon('i-file') + 'Sarlavha, vazn va imzolar</h2><ul class="issues">';
            hdr.forEach(h => { html += `<li>${badge(h.status)}<div><b>${esc(h.label)}</b>: tizimda «${esc(h.system)}», skanerda «${esc(h.scan)}». ${esc(h.note)}</div></li>`; });
            doc.forEach(d => { html += `<li>${badge(d.t)}<div>${esc(d.m)}</div></li>`; });
            html += '</ul></div>';
        }
        html += '</div>';
        document.getElementById('sheet-errors').innerHTML = html;
    }

    // ───────── Sarlavha va imzolar ─────────
    function renderHeader() {
        const scanH = R.scan_header || {};
        let html = '<div class="card"><h2>' + icon('i-file') + 'Sarlavha</h2><table class="kv"><thead><tr><th style="width:22%">Maydon</th><th style="width:28%">Tizimda</th><th style="width:28%">Skanerda</th><th style="width:10%">Holat</th><th>Izoh</th></tr></thead><tbody>';
        R.header.forEach(h => {
            const key = h.key === 'yn_sana' ? 'yn_sana' : h.key;
            const editable = CAN_EDIT && HEADER_EDITABLE.includes(key);
            const val = key in pending.header ? pending.header[key] : h.scan;
            html += `<tr><td><b>${esc(h.label)}</b></td><td>${esc(h.system) || '<span class="muted">—</span>'}</td>`
                + `<td class="${editable ? 'edit-text' : ''}" ${editable ? `tabindex="0" data-hkey="${key}" title="Tahrirlash uchun bosing"` : ''} style="${key in pending.header ? 'box-shadow:inset 0 0 0 2px var(--edit)' : ''}">${esc(val) || '<span class="muted">—</span>'}</td>`
                + `<td>${badge(h.status)}</td><td class="muted">${esc(h.note)}</td></tr>`;
        });
        html += '</tbody></table></div>';

        const w = R.weights;
        const wk = [['jb', 'jn', 'JB'], ['mt', 'mt', 'MT'], ['on', 'on', 'ON'], ['oski', 'oski', 'OSKE'], ['test', 'test', 'Test']];
        html += '<div class="card"><h2>' + icon('i-info') + 'Vaznlar (vedomost sarlavhasidagi maksimal ball)</h2><div class="weights">';
        wk.forEach(([k, rk, label]) => {
            const val = k in pending.weights ? pending.weights[k] : w[rk];
            html += `<label>${label}<input type="number" min="0" max="100" data-wkey="${k}" value="${esc(val)}" ${CAN_EDIT ? '' : 'disabled'} class="${k in pending.weights ? 'dirty' : ''}"></label>`;
        });
        html += `<div class="muted" style="align-self:flex-end;font-size:12px">Yig'indi 100 bo'lishi kerak. Vazn o'zgarsa ballar qayta hisoblanadi.</div></div></div>`;

        const sigs = [['oqituvchi', "O'qituvchi imzosi"], ['dekan', 'Dekan imzosi'], ['kafedra_mudiri', 'Kafedra mudiri imzosi'], ['muhr', "Muhr (M.O')"]];
        html += '<div class="card"><h2>' + icon('i-check') + 'Imzolar va muhr</h2><div class="sigs">';
        sigs.forEach(([k, label]) => {
            const on = k in pending.signatures ? pending.signatures[k] : !!R.signatures[k];
            html += `<label class="sig"><input type="checkbox" data-skey="${k}" ${on ? 'checked' : ''} ${CAN_EDIT ? '' : 'disabled'} class="${k in pending.signatures ? 'dirty' : ''}"><span>${label}</span><span style="margin-left:auto">${on ? badge('ok') : badge('bad')}</span></label>`;
        });
        html += '</div><div class="muted" style="padding:0 16px 12px;font-size:12px">AI imzo va muhr borligini aniqlaydi, haqiqiyligini emas. Noto\'g\'ri aniqlagan bo\'lsa, belgini o\'zgartiring.</div></div>';

        if (R.doc_issues.length) {
            html += '<div class="card"><h2>' + icon('i-alert') + 'Ogohlantirishlar</h2><ul class="issues">';
            R.doc_issues.forEach(d => { html += `<li>${badge(d.t)}<div>${esc(d.m)}</div></li>`; });
            html += '</ul></div>';
        }
        document.getElementById('header-pad').innerHTML = html;
    }

    function renderAll() { renderMain(); renderPdf(); renderErrors(); renderHeader(); updateSavebar(); }

    // ───────── tahrirlash ─────────
    function dirtyCount() {
        let n = Object.keys(pending.header).length + Object.keys(pending.weights).length + Object.keys(pending.signatures).length;
        Object.values(pending.rows).forEach(o => { n += Object.keys(o).length; });
        return n;
    }
    function updateSavebar() {
        const n = dirtyCount();
        document.getElementById('dirty-count').textContent = n;
        document.getElementById('savebar').classList.toggle('show', n > 0);
    }

    function startEdit(td) {
        if (!td || !td.dataset.key || td.querySelector('input')) return;
        const key = td.dataset.key, f = td.dataset.field;
        const row = R.rows.find(r => r.key === key);
        const saved = String(savedValue(row, f)).trim();
        const orig = (row.edited || []).includes(f) ? String((row.orig || {})[f] ?? '').trim() : saved;
        const input = document.createElement('input');
        input.className = 'cell-input';
        input.value = pdfValue(row, f) ?? '';
        input.setAttribute('aria-label', (LABELS[f] || f) + ' — ' + row.fio);
        if (f === 'fio') input.style.textAlign = 'left';
        td.textContent = '';
        td.appendChild(input);
        input.focus();
        input.select();
        let done = false;
        const commit = (move) => {
            if (done) return; done = true;
            const v = input.value.trim();
            pending.rows[key] = pending.rows[key] || {};
            if (v === saved) delete pending.rows[key][f];            // o'zgarmadi
            else if (v === orig) pending.rows[key][f] = null;        // AI o'qigan asl qiymatga qaytish
            else pending.rows[key][f] = v;
            if (!Object.keys(pending.rows[key]).length) delete pending.rows[key];
            const sheet = td.closest('.sheet').id;
            renderAll();
            refocus(sheet, key, f, move);
        };
        input.addEventListener('keydown', e => {
            e.stopPropagation();
            if (e.key === 'Enter') { e.preventDefault(); commit('down'); }
            else if (e.key === 'Tab') { e.preventDefault(); commit(e.shiftKey ? 'left' : 'right'); }
            else if (e.key === 'Escape') { done = true; const s = td.closest('.sheet').id; renderAll(); refocus(s, key, f); }
        });
        input.addEventListener('blur', () => commit(null));
    }

    function revertCell(td) {
        const key = td.dataset.key, f = td.dataset.field;
        const row = R.rows.find(r => r.key === key);
        if (isDirty(row, f)) delete pending.rows[key][f];
        else if ((row.edited || []).includes(f)) { pending.rows[key] = pending.rows[key] || {}; pending.rows[key][f] = null; }
        if (pending.rows[key] && !Object.keys(pending.rows[key]).length) delete pending.rows[key];
        const sheet = td.closest('.sheet').id;
        renderAll();
        refocus(sheet, key, f);
    }

    function cellsIn(sheetId) { return Array.from(document.querySelectorAll('#' + sheetId + ' td[data-key]')); }
    function refocus(sheetId, key, f, move) {
        const cells = cellsIn(sheetId);
        let i = cells.findIndex(c => c.dataset.key === key && c.dataset.field === f);
        if (i < 0) return;
        let target = cells[i];
        if (move) target = neighbour(cells, target, move) || target;
        target.focus();
    }
    function neighbour(cells, td, dir) {
        const tr = td.parentElement;
        const rowCells = Array.from(tr.querySelectorAll('td[data-key]'));
        const idx = rowCells.indexOf(td);
        if (dir === 'left') return rowCells[idx - 1];
        if (dir === 'right') return rowCells[idx + 1];
        let sib = dir === 'down' ? tr.nextElementSibling : tr.previousElementSibling;
        while (sib) {
            const c = sib.querySelector(`td[data-field="${td.dataset.field}"][data-key]`);
            if (c) return c;
            sib = dir === 'down' ? sib.nextElementSibling : sib.previousElementSibling;
        }
        return null;
    }

    document.addEventListener('keydown', e => {
        const td = document.activeElement;
        if (!td || td.tagName !== 'TD' || !td.dataset.key) return;
        const map = { ArrowLeft: 'left', ArrowRight: 'right', ArrowUp: 'up', ArrowDown: 'down' };
        if (map[e.key]) { e.preventDefault(); const n = neighbour(null, td, map[e.key]); if (n) n.focus(); return; }
        if (e.key === 'Enter' || e.key === 'F2') { e.preventDefault(); startEdit(td); return; }
        if (e.key === 'Delete' || e.key === 'Backspace') { e.preventDefault(); revertCell(td); return; }
        if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
            startEdit(td);
            const inp = td.querySelector('input'); if (inp) { inp.value = ''; }
        }
    });
    document.addEventListener('dblclick', e => { const td = e.target.closest('td[data-key]'); if (td) startEdit(td); });
    document.addEventListener('click', e => {
        const go = e.target.closest('[data-goto]');
        if (go) {
            const ri = go.dataset.goto;
            filter = 'all'; setChips(); renderMain(); showTab('main');
            const tr = document.querySelector(`#sheet-main tr[data-ri="${ri}"]`);
            if (tr) { tr.scrollIntoView({ block: 'center' }); tr.classList.add('flash'); setTimeout(() => tr.classList.remove('flash'), 1500); }
            return;
        }
        const h = e.target.closest('[data-hkey]');
        if (h && !h.querySelector('input')) editHeader(h);
    });
    document.addEventListener('keydown', e => {
        const h = document.activeElement;
        if (h && h.dataset && h.dataset.hkey && (e.key === 'Enter' || e.key === 'F2')) { e.preventDefault(); editHeader(h); }
    });

    function editHeader(td) {
        const key = td.dataset.hkey;
        const h = R.header.find(x => x.key === key);
        const input = document.createElement('input');
        input.className = 'text-input';
        input.value = key in pending.header ? pending.header[key] : (h ? h.scan : '');
        td.textContent = ''; td.appendChild(input); input.focus(); input.select();
        let done = false;
        const commit = () => {
            if (done) return; done = true;
            const v = input.value.trim();
            if (v === String(h ? h.scan : '').trim()) delete pending.header[key]; else pending.header[key] = v;
            renderHeader(); updateSavebar();
        };
        input.addEventListener('keydown', e => { e.stopPropagation(); if (e.key === 'Enter') commit(); if (e.key === 'Escape') { done = true; renderHeader(); } });
        input.addEventListener('blur', commit);
    }
    document.addEventListener('change', e => {
        const w = e.target.closest('[data-wkey]');
        if (w) {
            const k = w.dataset.wkey; const map = { jb: 'jn', mt: 'mt', on: 'on', oski: 'oski', test: 'test' };
            if (String(w.value) === String(R.weights[map[k]])) delete pending.weights[k]; else pending.weights[k] = w.value;
            renderHeader(); updateSavebar(); return;
        }
        const s = e.target.closest('[data-skey]');
        if (s) {
            const k = s.dataset.skey;
            if (s.checked === !!R.signatures[k]) delete pending.signatures[k]; else pending.signatures[k] = s.checked;
            renderHeader(); updateSavebar();
        }
    });

    // ───────── saqlash ─────────
    const toast = (msg, err) => { const t = document.getElementById('toast'); t.textContent = msg; t.className = 'toast show' + (err ? ' err' : ''); setTimeout(() => t.className = 'toast', 3500); };
    document.getElementById('btn-discard').addEventListener('click', () => {
        if (!confirm("Saqlanmagan o'zgarishlar bekor qilinsinmi?")) return;
        pending.rows = {}; pending.header = {}; pending.weights = {}; pending.signatures = {};
        renderAll();
    });
    document.getElementById('btn-save').addEventListener('click', async () => {
        const btn = document.getElementById('btn-save');
        btn.disabled = true; btn.textContent = 'Saqlanmoqda...';
        try {
            const res = await fetch(SAVE_URL, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content },
                body: JSON.stringify(pending),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || ('Xatolik: ' + res.status));
            pending.rows = {}; pending.header = {}; pending.weights = {}; pending.signatures = {};
            sessionStorage.setItem('vedomost-report-toast', data.message || 'Saqlandi');
            location.reload();
        } catch (err) {
            toast(err.message, true);
            btn.disabled = false; btn.textContent = 'Saqlash';
        }
    });
    window.addEventListener('beforeunload', e => { if (dirtyCount() > 0) { e.preventDefault(); e.returnValue = ''; } });

    // ───────── tablar, filtrlar ─────────
    function showTab(name) {
        document.querySelectorAll('.tab').forEach(t => t.setAttribute('aria-selected', t.dataset.tab === name ? 'true' : 'false'));
        document.querySelectorAll('.sheet').forEach(s => s.classList.toggle('active', s.id === 'sheet-' + name));
        history.replaceState(null, '', '#' + name);
    }
    document.querySelectorAll('.tab').forEach(t => t.addEventListener('click', () => showTab(t.dataset.tab)));
    function setChips() { document.querySelectorAll('.chip').forEach(c => c.setAttribute('aria-pressed', c.dataset.filter === filter ? 'true' : 'false')); }
    document.querySelectorAll('.chip').forEach(c => c.addEventListener('click', () => { filter = c.dataset.filter; setChips(); renderMain(); renderPdf(); }));
    let qt;
    document.getElementById('search').addEventListener('input', e => { clearTimeout(qt); qt = setTimeout(() => { query = e.target.value.trim(); renderMain(); renderPdf(); }, 150); });
    document.getElementById('show-balls').addEventListener('change', e => { showBalls = e.target.checked; renderMain(); });

    setChips();
    renderAll();
    showTab(['main', 'header', 'errors', 'pdf', 'history'].includes(location.hash.slice(1)) ? location.hash.slice(1) : 'main');
    const msg = sessionStorage.getItem('vedomost-report-toast');
    if (msg) { sessionStorage.removeItem('vedomost-report-toast'); toast(msg); }
})();
</script>
</body>
</html>
