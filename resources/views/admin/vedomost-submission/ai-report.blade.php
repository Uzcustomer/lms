<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Tekshiruv — {{ $merged->group_name ?? $v->group_name }} · {{ $merged->subject_name ?? $v->subject_name }}</title>
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
    <a class="back" href="{{ route('admin.vedomost-submission.show', $v->id) }}" aria-label="Vedomost sahifasiga qaytish"><svg class="icon"><use href="#i-back"/></svg></a>
    <div class="t">
        <h1>{{ $merged->group_name ?? $v->group_name }} — {{ $merged->subject_name ?? $v->subject_name }}</h1>
        <div class="sub">
            {{ \App\Models\VedomostSubmission::formLabel($v->form_type) }} · AI tekshiruv {{ $v->ai_checked_at ? \Carbon\Carbon::parse($v->ai_checked_at)->format('d.m.Y H:i') : '' }}
            @if($model) · {{ $model }}@endif
            @unless($canEdit) · faqat ko'rish @endunless
        </div>
    </div>
    <span class="verdict {{ $r['verdict'] }}"><svg class="icon"><use href="#{{ $r['verdict'] === 'ok' ? 'i-check' : 'i-alert' }}"/></svg>{{ $verdictTxt }}</span>
    @if($v->pdf_path)
        <a class="tbtn" href="{{ route('admin.vedomost-submission.file', [$v->id, 'pdf']) }}" target="_blank" rel="noopener"><svg class="icon"><use href="#i-file"/></svg>PDF</a>
    @endif
    <a class="tbtn" href="{{ route('admin.vedomost-submission.ai-report.excel', $v->id) }}"><svg class="icon"><use href="#i-download"/></svg>Excel</a>
</header>

<section class="ribbon" aria-label="Filtrlar">
    <div class="summary">{{ $r['summary'] }}</div>
    <div class="chips" role="group" aria-label="Qatorlar filtri">
        <button class="chip" data-filter="problems">Muammolilar <span class="n">{{ $problems }}</span></button>
        <button class="chip" data-filter="all">Hammasi <span class="n">{{ $c['total'] }}</span></button>
        <button class="chip" data-filter="bad"><span class="sw" style="background:var(--yellow)"></span>XATO <span class="n">{{ $c['bad'] }}</span></button>
        <button class="chip" data-filter="ocr"><span class="sw" style="background:var(--orange)"></span>PDFdan tekshiring <span class="n">{{ $c['ocr'] }}</span></button>
        <button class="chip" data-filter="nopdf"><span class="sw" style="background:#fecaca"></span>PDFda yo'q <span class="n">{{ $c['nopdf'] }}</span></button>
        <button class="chip" data-filter="missing"><span class="sw" style="background:#fecaca"></span>Kontingentda yo'q <span class="n">{{ $c['missing'] }}</span></button>
        <button class="chip" data-filter="edited"><span class="sw" style="background:var(--edit)"></span>Tuzatilgan <span class="n">{{ $c['edited'] }}</span></button>
    </div>
    <div class="spacer"></div>
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
    <div class="legend">
        <span><i style="background:var(--yellow)"></i>Farq (XATO)</span>
        <span><i style="background:var(--orange)"></i>PDFdan tekshiring</span>
        <span><i style="background:var(--light)"></i>YN = 0</span>
        <span><i style="background:var(--edit-soft);border-color:var(--edit)"></i>Tuzatilgan</span>
    </div>
</nav>
<div class="toast" id="toast" role="status" aria-live="polite"></div>

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
    const TOL = 0.05;
    const AB = [['JN % (BQ)', 'jn', 'D'], ['MT ball (BQ)', 'mtb', 'H'], ['OSKE % (BQ)', 'oski', 'P'], ['TEST % (BQ)', 'test', 'S'], ['YN (BQ)', 'yn', 'V']];
    const VT_KEYS = Object.keys(VT_HEADERS);
    const COMPACT = ['A', 'B', 'C', 'D', 'E', 'G', 'H', 'J', 'K', 'M', 'N', 'P', 'Q', 'S', 'T', 'V', 'W', 'X', 'Y'];
    const ST_ICON = { ok: 'i-check', bad: 'i-alert', ocr: 'i-eye', missing: 'i-minus', nopdf: 'i-minus' };

    const pending = { rows: {}, header: {}, weights: {}, signatures: {} };
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
        });
        AB.forEach(([t], i) => {
            letters += `<th>${colL(base + i * 2)}</th><th>${colL(base + i * 2 + 1)}</th>`;
            names += `<th class="h">${esc(t)}</th><th class="h">Δ</th>`;
        });
        letters += `<th>${colL(base + 10)}</th><th>${colL(base + 11)}</th><th>${colL(base + 12)}</th><th>${colL(base + 13)}</th><th>${colL(base + 14)}</th><th>${colL(base + 15)}</th><th>${colL(base + 16)}</th><th>${colL(base + 17)}</th></tr>`;
        names += '<th></th><th class="h">OSKE test markazi</th><th class="h">Δ</th><th class="h">TEST test markazi</th><th class="h">Δ</th><th class="h">FIO (kontingent)</th><th class="h">Holat</th><th class="h">Izoh</th></tr>';

        let body = '';
        let shown = 0;
        R.rows.forEach((row, ri) => {
            if (!visible(row)) return;
            shown++;
            const vt = row.vt;
            const ynZero = num(vt.V) === 0;
            const statusFill = ['bad', 'missing', 'nopdf'].includes(row.status) ? 'y' : '';
            body += `<tr data-ri="${ri}"><td class="rn">${ri + 2}</td>`;
            cols.forEach(k => {
                let cls = k === 'A' ? 'c-a' : k === 'B' ? 'c-b txt' : k === 'C' ? 'c-c txt' : (['X', 'Y', 'W', 'AB', 'AC', 'AD', 'AE', 'AG', 'AJ', 'AS', 'AU', 'AW', 'AT', 'AV'].includes(k) ? 'txt' : '');
                if (ynZero) cls += ' l';
                if (['A', 'B', 'C'].includes(k) && statusFill) cls += ' y';
                if (k === 'B' && row.status === 'ocr') cls += ' o';
                let v = vt[k];
                if (k === 'N' && v !== null && v !== '') v = Math.round(v * 100) + '%';
                else if (k === 'AM' && v !== null && v !== '') v = (+(v * 100).toFixed(2)) + '%';
                else if (['E', 'H'].includes(k) && typeof v === 'number') v = v.toFixed(1);
                body += `<td class="${cls}" data-ref="${colL(vtIndex(k))}${ri + 2}" title="${k === 'B' ? esc((row.flags || []).map(f => f.m).join('\n')) : ''}">${esc(fmt(v))}</td>`;
            });
            AB.forEach(([t, key, hc], i) => {
                const f = COMPARE[key][1];
                const skip = (key === 'oski' && !R.use.oski) || (key === 'test' && !R.use.test);
                const cc = (row.cells || {})[key];
                const ref = colL(base + i * 2) + (ri + 2);
                if (skip) { body += `<td class="grey" data-ref="${ref}">kerak emas</td><td></td>`; return; }
                if (!row.pdf) { body += `<td class="txt" data-ref="${ref}">PDFda yo'q</td><td></td>`; return; }
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
            [['oski', 'P', R.use.oski, R.use.tm_oski], ['test', 'S', R.use.test, R.use.tm_test]].forEach(([k, hc, need, on]) => {
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
            body += `<td class="txt ${row.kont_fio === null ? 'y' : ''}">${esc(row.kont_fio ?? "KONTINGENTDA YO'Q")}</td>`;
            body += `<td class="txt"><span class="st ${row.status}">${icon(ST_ICON[row.status] || 'i-info')}${esc(STATUS_TXT[row.status] || '')}</span></td>`;
            body += `<td class="note" title="${esc((row.flags || []).map(f => f.m).join('\n'))}">${(row.flags || []).map(f => esc(f.m)).join(' · ')}</td></tr>`;
        });
        document.getElementById('sheet-main').innerHTML = shown === 0
            ? `<div class="empty">${filter === 'problems' ? "Muammoli qator yo'q — barcha talabalar mos keladi." : "Filtr bo'yicha qator yo'q."}</div>`
            : `<table class="xl" aria-label="Tekshirish varag'i"><thead>${letters}${names}</thead><tbody>${body}</tbody></table>`;
    }

    // ───────── pdf (BQ) ─────────
    function renderPdf() {
        const F = ['fio', 'id', 'jb_pct', 'jb_ball', 'mt_pct', 'mt_ball', 'on_pct', 'on_ball', 'jbmton_ball', 'jbmton_pct', 'oski_pct', 'oski_ball', 'test_pct', 'test_ball', 'yn', 'ects'];
        const entries = [];
        R.rows.forEach(r => { if (r.pdf) entries.push(Object.assign({ matched: true }, r)); });
        (R.extra || []).forEach(e => entries.push({ matched: false, key: e.key, page: e.page, nr: e.nr, fio: '', id: '', scan_fio: e.fio, scan_id: e.id, pdf: e.pdf, unclear: e.unclear, edited: e.edited, orig: e.orig, note: e.note, status: 'extra' }));
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
            body += `<td class="txt ${e.matched ? '' : 'y'}">${e.matched ? esc(e.fio) : esc("Markda yo'q — " + (e.note || ''))}</td><td class="txt">${esc(e.id)}</td><td class="txt ${(e.unclear || []).length ? 'o' : ''}">${esc((e.unclear || []).join(', '))}</td></tr>`;
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
        (R.extra || []).forEach(e => { n++; body += `<tr><td class="rn">${n}</td><td></td><td class="txt">${esc(e.fio)}</td><td class="txt">${esc(e.id)}</td><td></td><td class="txt y">PDFda bor, Markda yo'q</td><td class="note">vedomost.pdf / ${esc(e.page || 1)} · ${esc(e.note)}</td></tr>`; });
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
        rows += `<tr><td class="rn">${R.header.length + 2}</td><td class="txt"><b>Vaznlar (JN/MT/ON/OSKE/Test)</b></td><td class="note">${[w.jb, w.mt, w.on, w.oski, w.test].join(' / ')}</td><td class="txt">OK</td></tr>`;
        let html = `<div class="pad"><div class="card"><h2>${icon('i-file')}Sarlavha tekshiruvi</h2><table class="xl" style="width:100%"><thead><tr class="names"><th class="rn">1</th><th class="h" style="width:240px">Tekshiruv</th><th class="h">Natija</th><th class="h" style="width:110px">Holat</th></tr></thead><tbody>${rows}</tbody></table></div>`;

        if (CAN_EDIT) {
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
        return n;
    }
    function updateSavebar() {
        const n = dirtyCount();
        document.getElementById('dirty-count').textContent = n;
        document.getElementById('savebar').classList.toggle('show', n > 0);
    }
    function findEntry(key) { return R.rows.find(r => r.key === key) || (R.extra || []).find(e => e.key === key); }
    function savedVal(e, f) {
        if (f === 'fio') return e.scan_fio ?? e.fio ?? '';
        if (f === 'id') return e.scan_id ?? e.id ?? '';
        return (e.pdf || {})[f] ?? '';
    }
    function startEdit(td, mode = 'edit') {
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
    function revertCell(td) {
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
        const rows = Array.from(tr.parentElement.children);
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
        const editable = !!td.dataset.key;
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
    document.addEventListener('dblclick', e => { const td = e.target.closest('td[data-key]'); if (td) startEdit(td, 'edit'); });
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
        pending.rows = {}; pending.header = {}; pending.weights = {}; pending.signatures = {};
        renderAll();
    });
    document.getElementById('btn-save').addEventListener('click', async () => {
        const b = document.getElementById('btn-save');
        b.disabled = true; b.textContent = 'Saqlanmoqda...';
        try {
            const res = await fetch(SAVE_URL, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify(pending) });
            const data = await res.json().catch(() => ({}));
            if (!res.ok) throw new Error(data.message || ('Xatolik: ' + res.status));
            pending.rows = {}; pending.header = {}; pending.weights = {}; pending.signatures = {};
            sessionStorage.setItem('vedomost-report-toast', data.message || 'Saqlandi');
            location.reload();
        } catch (err) { toast(err.message, true); b.disabled = false; b.textContent = 'Saqlash'; }
    });
    window.addEventListener('beforeunload', e => { if (dirtyCount() > 0) { e.preventDefault(); e.returnValue = ''; } });

    // ───────── varaqlar, filtrlar ─────────
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
    document.getElementById('all-cols').addEventListener('change', e => { allCols = e.target.checked; renderMain(); });

    setChips();
    renderAll();
    showTab(['main', 'header', 'errors', 'pdf', 'history'].includes(location.hash.slice(1)) ? location.hash.slice(1) : 'main');
    const msg = sessionStorage.getItem('vedomost-report-toast');
    if (msg) { sessionStorage.removeItem('vedomost-report-toast'); toast(msg); }
})();
</script>
</body>
</html>
