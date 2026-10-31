<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">Vedomost — AI sozlamalari</h2>
    </x-slot>

    @php
        $s = $settings;
        $p = $stats['periods'];
        $o = $stats['outcomes'];
        $errorLabels = [
            'rate_limit' => "So'rovlar ko'p (429)",
            'quota' => 'Kvota tugagan (429)',
            'overloaded' => 'Gemini band (503)',
            'server' => 'Gemini server xatosi',
            'timeout' => 'Timeout / ulanish',
            'auth' => 'Kalit yaroqsiz',
            'model_not_found' => 'Model topilmadi',
            'bad_request' => "Noto'g'ri so'rov",
            'truncated' => 'Javob kesilgan',
            'invalid_json' => 'Yaroqsiz javob',
            'blocked' => 'Bloklangan',
        ];
        $fmtMs = fn ($ms) => $ms === null ? '—' : ($ms >= 1000 ? number_format($ms / 1000, 1) . ' s' : $ms . ' ms');
        $fmtNum = fn ($n) => number_format((int) $n, 0, '.', ' ');
        $fmtTok = fn ($n) => $n >= 1e6 ? round($n / 1e6, 1) . 'M' : ($n >= 1e3 ? round($n / 1e3) . 'K' : (string) (int) $n);
        $rate = fn ($ok, $total) => $total > 0 ? (int) round($ok / $total * 100) : null;
        $maxDay = max(1, collect($stats['days'])->max('total'));
        $hasFreeKeys = $keys->contains(fn ($k) => !$k->is_paid && $k->enabled);
        $outTotal = max(1, array_sum($o));
        $tabs = ['overview' => 'Umumiy holat', 'live' => 'Jonli kuzatuv', 'settings' => 'Sozlamalar', 'rules' => 'Tekshiruv qoidalari'];
        if ($canManageKeys) {
            $tabs['keys'] = 'API kalitlar';
        }
        $formTabs = ['settings', 'rules'];
        $errTab = $errors->any() ? (collect(['api_key', 'label', 'rpm'])->contains(fn ($f) => $errors->has($f)) ? 'keys' : 'settings') : null;
    @endphp

    <style>
        .ais { --navy:#1a3268; --ai:#6d28d9; --ai-soft:#f5f3ff; --ok:#166534; --ok-soft:#dcfce7; --warn:#92400e; --warn-soft:#fef3c7; --bad:#b91c1c; --bad-soft:#fee2e2;
               --text:#0f172a; --muted:#64748b; --line:#e2e8f0; --soft:#f8fafc; color:var(--text); font-size:14px; }
        .ais *, .ais *::before, .ais *::after { box-sizing:border-box; }
        .ais .icon { width:16px; height:16px; stroke:currentColor; fill:none; stroke-width:2; stroke-linecap:round; stroke-linejoin:round; flex-shrink:0; }
        .ais a.back { display:inline-flex; align-items:center; gap:6px; color:var(--muted); text-decoration:none; font-size:13px; }
        .ais a.back:hover { color:var(--navy); }
        .ais .flash { display:flex; gap:10px; align-items:flex-start; padding:10px 14px; border-radius:10px; margin:12px 0 0; border:1px solid; font-size:14px; }
        .ais .flash.ok { background:var(--ok-soft); color:var(--ok); border-color:#bbf7d0; }
        .ais .flash.err { background:var(--bad-soft); color:var(--bad); border-color:#fecaca; }

        /* Holat paneli */
        .ais .status { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:10px; margin-top:14px; }
        .ais .st { display:flex; gap:12px; align-items:center; background:#fff; border:1px solid var(--line); border-radius:12px; padding:12px 14px; }
        .ais .st .dot { width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0; }
        .ais .st .dot .icon { width:18px; height:18px; }
        .ais .st.ok .dot { background:var(--ok-soft); color:var(--ok); }
        .ais .st.bad .dot { background:var(--bad-soft); color:var(--bad); }
        .ais .st.warn .dot { background:var(--warn-soft); color:var(--warn); }
        .ais .st.neutral .dot { background:var(--ai-soft); color:var(--ai); }
        .ais .st .k { font-size:12px; color:var(--muted); font-weight:600; }
        .ais .st .v { font-size:14px; font-weight:700; line-height:1.3; }

        /* Varaqlar */
        .ais .tabs { display:flex; gap:4px; margin-top:18px; border-bottom:1px solid var(--line); overflow-x:auto; }
        .ais .tab { appearance:none; border:none; background:none; padding:10px 14px; font-size:14px; font-weight:600; color:var(--muted); border-bottom:2px solid transparent; cursor:pointer; white-space:nowrap; font-family:inherit; }
        .ais .tab:hover { color:var(--text); }
        .ais .tab[aria-selected="true"] { color:var(--navy); border-bottom-color:var(--navy); }
        .ais .panel { display:none; padding-top:16px; }
        .ais .panel.active { display:block; }

        .ais .card { background:#fff; border:1px solid var(--line); border-radius:12px; margin-bottom:14px; }
        .ais .card-h { display:flex; align-items:center; justify-content:space-between; gap:12px; padding:14px 18px; border-bottom:1px solid var(--line); flex-wrap:wrap; }
        .ais .card-h h3 { margin:0; font-size:15px; font-weight:700; display:flex; align-items:center; gap:8px; }
        .ais .card-h p { margin:2px 0 0; font-size:13px; color:var(--muted); }
        .ais .card-b { padding:16px 18px; }
        .ais .lv-kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:10px; margin:16px 0 14px; }
        .ais .lv-kpi { background:#fff; border:1px solid var(--line); border-radius:10px; padding:10px 14px; }
        .ais .lv-kpi .t { font-size:12px; color:var(--muted); font-weight:600; }
        .ais .lv-kpi .v { font-size:22px; font-weight:800; font-variant-numeric:tabular-nums; }
        .ais .lv-alert { margin-top:16px; padding:10px 14px; border-radius:10px; background:#fef2f2; color:#991b1b; border:1px solid #fecaca; font-size:13px; }
        .ais .lv-upd { font-size:12px; color:var(--muted); }
        .ais .lv-wrap { overflow-x:auto; }
        .ais .lv-tbl { width:100%; border-collapse:collapse; font-size:13px; }
        .ais .lv-tbl th { text-align:left; font-size:11px; text-transform:uppercase; letter-spacing:.03em; color:var(--muted); padding:8px 12px; background:var(--soft); border-bottom:1px solid var(--line); white-space:nowrap; }
        .ais .lv-tbl td { padding:8px 12px; border-bottom:1px solid var(--line); vertical-align:top; }
        .ais .lv-tbl td.empty { text-align:center; color:var(--muted); padding:22px; }
        .ais .lv-tbl a { color:#1d4ed8; text-decoration:none; font-weight:600; }
        .ais .lv-b { display:inline-block; padding:2px 9px; border-radius:999px; font-size:12px; font-weight:700; white-space:nowrap; }
        .ais .lv-b.run { background:#dbeafe; color:#1d4ed8; } .ais .lv-b.q { background:#f1f5f9; color:#475569; }
        .ais .lv-b.ok { background:#dcfce7; color:#166534; } .ais .lv-b.err { background:#fee2e2; color:#b91c1c; } .ais .lv-b.stuck { background:#fef3c7; color:#92400e; }
        .ais .lv-step { font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; font-size:12px; color:#334155; }
        .ais .lv-step.warn { color:#92400e; } .ais .lv-step.error { color:#b91c1c; } .ais .lv-step.ok { color:#166534; }
        .ais .lv-muted { color:var(--muted); font-size:12px; }

        /* KPI */
        .ais .kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:10px; }
        .ais .kpi { border:1px solid var(--line); border-radius:10px; padding:12px 14px; }
        .ais .kpi .t { font-size:12px; color:var(--muted); font-weight:600; }
        .ais .kpi .n { font-size:26px; font-weight:800; font-variant-numeric:tabular-nums; line-height:1.2; }
        .ais .kpi .bar { height:6px; border-radius:3px; background:var(--bad-soft); overflow:hidden; margin:8px 0 6px; }
        .ais .kpi .bar i { display:block; height:100%; background:var(--ok); }
        .ais .kpi .row { display:flex; justify-content:space-between; font-size:12px; color:#475569; gap:8px; font-variant-numeric:tabular-nums; padding:2px 0; }
        .ais .kpi .row > :last-child { white-space:nowrap; }

        .ais .chart { display:flex; align-items:flex-end; gap:6px; height:120px; border-bottom:1px solid var(--line); }
        .ais .chart .c { flex:1; display:flex; flex-direction:column; justify-content:flex-end; height:100%; min-width:8px; }
        .ais .chart .c i { display:block; }
        .ais .chart .c .e { background:#fca5a5; border-radius:3px 3px 0 0; }
        .ais .chart .c .s { background:var(--ai); }
        .ais .chart .c:hover .s { background:#5b21b6; }
        .ais .axis { display:flex; gap:6px; }
        .ais .axis span { flex:1; text-align:center; font-size:11px; color:#94a3b8; font-variant-numeric:tabular-nums; }
        .ais .legend { display:flex; gap:14px; font-size:12px; color:var(--muted); }
        .ais .legend i { display:inline-block; width:10px; height:10px; border-radius:2px; margin-right:5px; vertical-align:-1px; }

        .ais .grid3 { display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:14px; }
        .ais .sr-only { position:absolute; width:1px; height:1px; overflow:hidden; clip:rect(0 0 0 0); white-space:nowrap; }
        .ais .split i { display:block; height:100%; }
        .ais .split { height:10px; display:flex; border-radius:5px; overflow:hidden; background:var(--soft); margin-bottom:10px; }
        .ais .list { list-style:none; margin:0; padding:0; }
        .ais .list li { display:flex; justify-content:space-between; align-items:center; gap:10px; padding:7px 0; border-bottom:1px solid #f1f5f9; font-size:13px; }
        .ais .list li:last-child { border-bottom:none; }
        .ais .list b { font-variant-numeric:tabular-nums; }
        .ais .sw { display:inline-block; width:10px; height:10px; border-radius:2px; margin-right:8px; }
        .ais .empty { color:#94a3b8; font-size:13px; padding:6px 0; }

        /* Forma */
        .ais .fields { display:grid; grid-template-columns:repeat(auto-fill,minmax(240px,1fr)); gap:16px; }
        .ais .f label.l { display:block; font-size:13px; font-weight:600; color:#334155; margin-bottom:6px; }
        .ais .f .h { font-size:12px; color:var(--muted); margin-top:5px; line-height:1.4; }
        .ais .f .w { font-size:12px; color:var(--warn); margin-top:5px; display:flex; gap:6px; }
        .ais input.in { width:100%; height:38px; border:1px solid #cbd5e1; border-radius:8px; padding:0 10px; font-size:14px; color:var(--text); background:#fff; font-family:inherit; }
        .ais input.in:focus { outline:none; border-color:var(--navy); box-shadow:0 0 0 3px rgba(26,50,104,.12); }
        .ais .unit { position:relative; }
        .ais .unit input.in { padding-right:44px; }
        .ais .unit span { position:absolute; right:10px; top:10px; font-size:13px; color:var(--muted); pointer-events:none; }
        .ais .seg { display:inline-flex; border:1px solid #cbd5e1; border-radius:8px; overflow:hidden; }
        .ais .seg label { position:relative; }
        .ais .seg input { position:absolute; opacity:0; inset:0; cursor:pointer; }
        .ais .seg span { display:block; padding:8px 16px; font-weight:700; font-size:14px; border-right:1px solid #cbd5e1; cursor:pointer; font-variant-numeric:tabular-nums; }
        .ais .seg label:last-child span { border-right:none; }
        .ais .seg input:checked + span { background:var(--navy); color:#fff; }
        .ais .seg input:focus-visible + span { box-shadow:inset 0 0 0 2px #93c5fd; }

        .ais .toggle { display:flex; gap:14px; align-items:flex-start; padding:14px 0; border-bottom:1px solid #f1f5f9; cursor:pointer; }
        .ais .toggle:last-of-type { border-bottom:none; }
        .ais .toggle input { position:absolute; opacity:0; width:1px; height:1px; }
        .ais .toggle .knob { width:40px; height:22px; border-radius:11px; background:#cbd5e1; position:relative; flex-shrink:0; transition:background .15s; margin-top:1px; }
        .ais .toggle .knob::after { content:""; position:absolute; top:3px; left:3px; width:16px; height:16px; border-radius:50%; background:#fff; transition:transform .15s; box-shadow:0 1px 2px rgba(0,0,0,.2); }
        .ais .toggle input:checked + .knob { background:var(--navy); }
        .ais .toggle input:checked + .knob::after { transform:translateX(18px); }
        .ais .toggle input:focus-visible + .knob { box-shadow:0 0 0 3px rgba(26,50,104,.25); }
        .ais .toggle .tt { font-weight:600; font-size:14px; }
        .ais .toggle .td { font-size:13px; color:var(--muted); margin-top:2px; line-height:1.45; }
        .ais .note { display:flex; gap:10px; padding:10px 12px; border-radius:8px; font-size:13px; line-height:1.45; }
        .ais .note.warn { background:var(--warn-soft); color:var(--warn); border:1px solid #fde68a; }
        .ais .note.info { background:#eff6ff; color:#1e40af; border:1px solid #bfdbfe; }

        .ais .btn { display:inline-flex; align-items:center; justify-content:center; gap:6px; height:38px; padding:0 16px; border-radius:8px; font-weight:600; font-size:14px; cursor:pointer; border:1px solid transparent; font-family:inherit; text-decoration:none; white-space:nowrap; }
        .ais .btn.primary { background:var(--navy); color:#fff; }
        .ais .btn.primary:hover { background:#13254f; }
        .ais .btn.ai { background:var(--ai); color:#fff; }
        .ais .btn.ai:hover { background:#5b21b6; }
        .ais .btn.ghost { background:#fff; color:#334155; border-color:#cbd5e1; }
        .ais .btn.ghost:hover { background:var(--soft); }
        .ais .btn.danger { background:#fff; color:var(--bad); border-color:#fecaca; }
        .ais .btn.danger:hover { background:var(--bad-soft); }
        .ais .btn.sm { height:32px; padding:0 12px; font-size:13px; }
        .ais .btn:disabled { opacity:.5; cursor:not-allowed; }
        .ais .savebar { position:sticky; bottom:0; display:flex; align-items:center; justify-content:space-between; gap:12px; padding:12px 18px; background:#fff; border:1px solid var(--line); border-radius:12px; box-shadow:0 -4px 12px rgba(15,23,42,.06); margin-top:4px; z-index:5; }
        .ais .savebar .msg { font-size:13px; color:var(--muted); }
        .ais .savebar.dirty .msg { color:var(--warn); font-weight:600; }

        /* Kalitlar */
        .ais .key { border:1px solid var(--line); border-radius:10px; padding:14px; margin-bottom:10px; }
        .ais .key.off { background:var(--soft); }
        .ais .key-top { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
        .ais .key-top code { font-size:13px; background:var(--soft); padding:3px 8px; border-radius:6px; border:1px solid var(--line); }
        .ais .badge { display:inline-flex; align-items:center; gap:4px; font-size:12px; font-weight:700; padding:2px 9px; border-radius:999px; }
        .ais .badge.paid { background:#ede9fe; color:var(--ai); }
        .ais .badge.free { background:#f1f5f9; color:#475569; }
        .ais .badge.off { background:#f1f5f9; color:#94a3b8; }
        .ais .badge.ok { background:var(--ok-soft); color:var(--ok); }
        .ais .usage { flex:1; min-width:180px; }
        .ais .usage .ub { height:6px; border-radius:3px; background:#f1f5f9; overflow:hidden; margin-top:4px; }
        .ais .usage .ub i { display:block; height:100%; background:var(--ai); }
        .ais .usage .ut { font-size:12px; color:var(--muted); font-variant-numeric:tabular-nums; }
        .ais .key-err { margin-top:10px; font-size:12px; color:var(--bad); background:var(--bad-soft); border-radius:6px; padding:6px 10px; display:flex; gap:6px; }
        .ais .key-edit { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:10px; align-items:end; margin-top:12px; padding-top:12px; border-top:1px dashed var(--line); }
        .ais .chk { display:flex; align-items:center; gap:8px; font-size:13px; font-weight:600; height:38px; cursor:pointer; }
        .ais .chk input { width:16px; height:16px; accent-color:var(--navy); }
        .ais details.key-more summary { cursor:pointer; font-size:13px; color:var(--navy); font-weight:600; margin-top:10px; list-style:none; display:inline-flex; gap:4px; align-items:center; }
        .ais details.key-more summary::-webkit-details-marker { display:none; }
        .ais details.key-more[open] summary .icon { transform:rotate(180deg); }
        @media (max-width:640px) {
            .ais .card-h, .ais .card-b { padding-left:14px; padding-right:14px; }
            .ais .savebar { flex-direction:column; align-items:stretch; }
            .ais .chart { gap:3px; } .ais .axis { gap:3px; } .ais .axis span { font-size:10px; }
        }
        @media (prefers-reduced-motion: reduce) { .ais * { transition:none !important; } }
    </style>

    <svg width="0" height="0" style="position:absolute" aria-hidden="true">
        <symbol id="ai-i-check" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></symbol>
        <symbol id="ai-i-x" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></symbol>
        <symbol id="ai-i-alert" viewBox="0 0 24 24"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0zM12 9v4M12 17h.01"/></symbol>
        <symbol id="ai-i-image" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="m21 15-5-5L5 21"/></symbol>
        <symbol id="ai-i-zap" viewBox="0 0 24 24"><path d="M13 2 3 14h9l-1 8 10-12h-9z"/></symbol>
        <symbol id="ai-i-clock" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></symbol>
        <symbol id="ai-i-back" viewBox="0 0 24 24"><path d="M19 12H5M12 19l-7-7 7-7"/></symbol>
        <symbol id="ai-i-refresh" viewBox="0 0 24 24"><path d="M21 12a9 9 0 1 1-3-6.7L21 8M21 3v5h-5"/></symbol>
        <symbol id="ai-i-key" viewBox="0 0 24 24"><circle cx="7.5" cy="15.5" r="5.5"/><path d="m21 2-9.6 9.6M15.5 7.5l3 3L22 7l-3-3"/></symbol>
        <symbol id="ai-i-plus" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></symbol>
        <symbol id="ai-i-chev" viewBox="0 0 24 24"><path d="m6 9 6 6 6-6"/></symbol>
        <symbol id="ai-i-shield" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></symbol>
        <symbol id="ai-i-info" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></symbol>
        <symbol id="ai-i-send" viewBox="0 0 24 24"><path d="m22 2-7 20-4-9-9-4zM22 2 11 13"/></symbol>
    </svg>

    <div class="py-4 ais">
        <div class="max-w-6xl mx-auto px-4 lg:px-6">
            <a class="back" href="{{ route('admin.vedomost-submission.index') }}"><svg class="icon"><use href="#ai-i-back"/></svg>Vedomost topshirish</a>

            @if(session('success'))
                <div class="flash ok" role="status"><svg class="icon"><use href="#ai-i-check"/></svg>{{ session('success') }}</div>
            @endif
            @if(session('error'))
                <div class="flash err" role="alert"><svg class="icon"><use href="#ai-i-alert"/></svg>{{ session('error') }}</div>
            @endif
            @if($errors->any())
                <div class="flash err" role="alert"><svg class="icon"><use href="#ai-i-alert"/></svg><div>@foreach($errors->all() as $e)<div>{{ $e }}</div>@endforeach</div></div>
            @endif

            {{-- Holat --}}
            <div class="status">
                <div class="st {{ $configured ? 'ok' : 'bad' }}">
                    <div class="dot"><svg class="icon"><use href="#{{ $configured ? 'ai-i-check' : 'ai-i-x' }}"/></svg></div>
                    <div><div class="k">AI tekshiruv</div><div class="v">{{ $configured ? 'Ishlaydi' : "Kalit yo'q" }}</div></div>
                </div>
                <div class="st {{ $renderer ? 'ok' : 'warn' }}">
                    <div class="dot"><svg class="icon"><use href="#ai-i-image"/></svg></div>
                    <div><div class="k">PDF o'qish rejimi</div><div class="v">{{ $renderer ? 'Rasm, ' . $s['bands'] . " bo'lak" : "Butun PDF (pdftoppm yo'q)" }}</div></div>
                </div>
                <div class="st neutral">
                    <div class="dot"><svg class="icon"><use href="#ai-i-zap"/></svg></div>
                    <div><div class="k">Yuklanganda tekshirish</div><div class="v">{{ $s['auto_check'] ? 'Avtomatik' : "Qo'lda" }}</div></div>
                </div>
                <div class="st {{ $o['in_progress'] ? 'warn' : 'neutral' }}">
                    <div class="dot"><svg class="icon"><use href="#ai-i-clock"/></svg></div>
                    <div><div class="k">Navbat</div><div class="v">{{ $o['in_progress'] }} ishlamoqda · {{ $o['pending'] }} tekshirilmagan</div></div>
                </div>
            </div>

            <div class="tabs" role="tablist" aria-label="AI sozlamalari bo'limlari">
                @foreach($tabs as $id => $title)
                    <button type="button" class="tab" role="tab" id="tab-{{ $id }}" aria-controls="panel-{{ $id }}" data-tab="{{ $id }}">{{ $title }}</button>
                @endforeach
            </div>

            {{-- ───────── Jonli kuzatuv (5 s da yangilanadi) ───────── --}}
            <section class="panel" id="panel-live" role="tabpanel" aria-labelledby="tab-live" data-url="{{ route('admin.vedomost-submission.ai-settings.live') }}">
                <div class="lv-alert" id="lv-alert" hidden></div>
                <div class="lv-kpis" id="lv-kpis"></div>
                <div class="card">
                    <div class="card-h"><div><h3>Hozir ishlayotgan va navbatdagi vedomostlar</h3><p>Har ishchi o'z kalitida bitta vedomostni tekshiradi; bo'sh kalit bo'lmasa vedomost navbatda kutadi.</p></div><span class="lv-upd" id="lv-upd">yuklanmoqda…</span></div>
                    <div class="lv-wrap"><table class="lv-tbl"><thead><tr><th>Vedomost</th><th>Holat</th><th>Vaqt</th><th>Kalit</th><th>Oxirgi qadam</th></tr></thead><tbody id="lv-active"></tbody></table></div>
                </div>
                <div class="card">
                    <div class="card-h"><div><h3>Oxirgi 50 ta Gemini so'rovi</h3><p>So'rov tugagach yoziladi — hozir yuborilayotgani yuqoridagi «Oxirgi qadam»da ko'rinadi.</p></div></div>
                    <div class="lv-wrap"><table class="lv-tbl"><thead><tr><th>Vaqt</th><th>Vedomost</th><th>Kalit</th><th>Model</th><th>Holat</th><th>Davomiyligi</th><th>Xato</th></tr></thead><tbody id="lv-req"></tbody></table></div>
                </div>
            </section>

            {{-- ───────── Umumiy holat ───────── --}}
            <section class="panel" id="panel-overview" role="tabpanel" aria-labelledby="tab-overview">
                <div class="card">
                    <div class="card-h">
                        <div><h3>Tekshirilmagan vedomostlar</h3><p>PDF yuklangan, hali tasdiqlanmagan va AI tekshirmagan vedomostlarni navbatga qo'yish.</p></div>
                        <form method="POST" action="{{ route('admin.vedomost-submission.ai-settings.queue-pending') }}" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;"
                              onsubmit="return confirm('Tekshirilmagan vedomostlar AI navbatiga qo\'yilsinmi? Har biri Gemini so\'rovlarini sarflaydi.');">
                            @csrf
                            <label class="sr-only" for="q-max">Eng ko'pi bilan nechta</label>
                            <input id="q-max" class="in" type="number" name="max" min="1" placeholder="hammasi" style="width:110px;" title="Eng ko'pi bilan nechta vedomost">
                            <button type="submit" class="btn ai" @disabled(!$configured || $o['pending'] === 0)>
                                <svg class="icon"><use href="#ai-i-send"/></svg>Navbatga qo'shish ({{ $o['pending'] }})
                            </button>
                        </form>
                    </div>
                </div>

                <div class="card">
                    <div class="card-h"><div><h3>So'rovlar statistikasi</h3><p>Gemini'ga yuborilgan har bir so'rov (sahifa yoki bo'lak) alohida sanaladi.</p></div></div>
                    <div class="card-b">
                        <div class="kpis">
                            @foreach(['today' => 'Bugun', 'week' => 'Oxirgi 7 kun', 'month' => 'Oxirgi 30 kun', 'all' => 'Jami'] as $k => $t)
                                @php $r = $rate($p[$k]['ok'], $p[$k]['total']); @endphp
                                <div class="kpi">
                                    <div class="t">{{ $t }}</div>
                                    <div class="n">{{ $fmtNum($p[$k]['total']) }}</div>
                                    <div class="bar" title="Muvaffaqiyatli: {{ $r ?? 0 }}%"><i style="width:{{ $p[$k]['total'] ? $r : 0 }}%"></i></div>
                                    <div class="row"><span>Muvaffaqiyatli</span><b>{{ $r === null ? '—' : $r . '%' }}</b></div>
                                    <div class="row"><span>Xato</span><span>{{ $fmtNum($p[$k]['errors']) }}</span></div>
                                    <div class="row"><span>Javob vaqti</span><span title="o'rtacha / eng uzun">{{ $fmtMs($p[$k]['avg_ms']) }} / {{ $fmtMs($p[$k]['max_ms']) }}</span></div>
                                    <div class="row"><span>Token</span><span title="kirish / chiqish">{{ $fmtTok($p[$k]['input_tokens']) }} / {{ $fmtTok($p[$k]['output_tokens']) }}</span></div>
                                    @if($p[$k]['cost'] !== null)
                                        <div class="row"><span>Taxminiy narx</span><b>${{ number_format($p[$k]['cost'], 2) }}</b></div>
                                    @endif
                                </div>
                            @endforeach
                        </div>

                        <div style="display:flex;justify-content:space-between;align-items:center;margin:22px 0 8px;flex-wrap:wrap;gap:8px;">
                            <div style="font-size:13px;font-weight:700;color:#334155;">Oxirgi 14 kun</div>
                            <div class="legend"><span><i style="background:var(--ai)"></i>Muvaffaqiyatli</span><span><i style="background:#fca5a5"></i>Xato</span></div>
                        </div>
                        <div class="chart" role="img" aria-label="Oxirgi 14 kundagi so'rovlar">
                            @foreach($stats['days'] as $d)
                                <div class="c" title="{{ \Carbon\Carbon::parse($d['date'])->format('d.m') }}: {{ $d['total'] }} so'rov, {{ $d['ok'] }} muvaffaqiyatli">
                                    <i class="e" style="height:{{ round(($d['total'] - $d['ok']) / $maxDay * 100) }}%"></i>
                                    <i class="s" style="height:{{ round($d['ok'] / $maxDay * 100) }}%"></i>
                                </div>
                            @endforeach
                        </div>
                        <div class="axis">
                            @foreach($stats['days'] as $d)<span>{{ \Carbon\Carbon::parse($d['date'])->format('d') }}</span>@endforeach
                        </div>
                    </div>
                </div>

                <div class="grid3">
                    <div class="card">
                        <div class="card-h"><h3>Vedomostlar natijasi</h3></div>
                        <div class="card-b">
                            @php
                                $parts = [['ok', 'Mos keladi', '#16a34a'], ['issues', 'Nomuvofiqlik topilgan', '#eab308'], ['error', 'Tekshiruv xatosi', '#dc2626'], ['in_progress', 'Navbatda / ishlamoqda', '#8b5cf6'], ['pending', 'Tekshirilmagan', '#cbd5e1']];
                            @endphp
                            <div class="split" role="img" aria-label="Vedomostlar natijasi taqsimoti">
                                @foreach($parts as [$k, , $c])
                                    @if($o[$k])<i style="width:{{ $o[$k] / $outTotal * 100 }}%;background:{{ $c }}"></i>@endif
                                @endforeach
                            </div>
                            <ul class="list">
                                @foreach($parts as [$k, $l, $c])
                                    <li><span><span class="sw" style="background:{{ $c }}"></span>{{ $l }}</span><b>{{ $o[$k] }}</b></li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-h"><h3>Xato turlari <span style="font-weight:400;color:var(--muted);font-size:13px;">30 kun</span></h3></div>
                        <div class="card-b">
                            <ul class="list">
                                @forelse($stats['errorTypes'] as $type => $cnt)
                                    <li><span>{{ $errorLabels[$type] ?? ($type ?: "noma'lum") }}</span><b>{{ $cnt }}</b></li>
                                @empty
                                    <li class="empty">Xato bo'lmagan.</li>
                                @endforelse
                            </ul>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-h"><h3>Modellar <span style="font-weight:400;color:var(--muted);font-size:13px;">30 kun</span></h3></div>
                        <div class="card-b">
                            <ul class="list">
                                @forelse($stats['models'] as $m)
                                    <li><span style="font-family:ui-monospace,monospace;font-size:12px;">{{ $m->model }}</span>
                                        <span style="font-size:12px;color:var(--muted);white-space:nowrap;"><b style="color:var(--text)">{{ (int) $m->ok }}</b>/{{ (int) $m->total }} · {{ $fmtMs($m->avg_ms !== null ? (int) round($m->avg_ms) : null) }}</span></li>
                                @empty
                                    <li class="empty">Hali so'rov yo'q.</li>
                                @endforelse
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            {{-- ───────── Sozlamalar + qoidalar (bitta forma) ───────── --}}
            <form method="POST" action="{{ route('admin.vedomost-submission.ai-settings.save') }}" id="settings-form">
                @csrf
                <section class="panel" id="panel-settings" role="tabpanel" aria-labelledby="tab-settings">
                    <div class="card">
                        <div class="card-h">
                            <div><h3>Model</h3><p>Qaysi Gemini modeli vedomostni o'qiydi.</p></div>
                            <button type="submit" form="models-form" class="btn ghost sm"><svg class="icon"><use href="#ai-i-refresh"/></svg>Modellar ro'yxatini yangilash ({{ count($models) }})</button>
                        </div>
                        <div class="card-b fields">
                            <div class="f">
                                <label class="l" for="f-model">Asosiy model</label>
                                <input id="f-model" class="in" name="model" list="ai-models" value="{{ old('model', $s['model']) }}" required>
                                <div class="h">Masalan: gemini-2.5-flash yoki gemini-2.5-pro (aniqroq, sekinroq).</div>
                            </div>
                            <div class="f">
                                <label class="l" for="f-fallback">Zaxira model</label>
                                <input id="f-fallback" class="in" name="fallback_model" list="ai-models" value="{{ old('fallback_model', $s['fallback_model']) }}" placeholder="yo'q">
                                <div class="h">Asosiy model topilmasa yoki uzoq vaqt band bo'lsa ishlatiladi.</div>
                            </div>
                            <div class="f">
                                <label class="l" for="f-think">Thinking budget</label>
                                <div class="unit"><input id="f-think" class="in" type="number" name="thinking_budget" min="0" max="32768" value="{{ old('thinking_budget', $s['thinking_budget']) }}" placeholder="standart"><span>token</span></div>
                                <div class="h">Bo'sh — model standarti; 0 — o'ylamasdan (tez, arzon, lekin xatoroq).</div>
                            </div>
                        </div>
                        <datalist id="ai-models">
                            @foreach($models as $m)
                                <option value="{{ $m['name'] }}">{{ $m['label'] }}</option>
                            @endforeach
                        </datalist>
                    </div>

                    <div class="card">
                        <div class="card-h"><div><h3>O'qish sifati</h3><p>Sahifa nechta bo'lakka bo'linib AI'ga yuboriladi.</p></div></div>
                        <div class="card-b">
                            <div class="f">
                                <span class="l" style="display:block;font-size:13px;font-weight:600;color:#334155;margin-bottom:6px;" id="bands-l">Bo'laklar soni</span>
                                <div class="seg" role="radiogroup" aria-labelledby="bands-l">
                                    @foreach([1, 2, 3, 4] as $b)
                                        <label><input type="radio" name="bands" value="{{ $b }}" @checked((int) old('bands', $s['bands']) === $b)><span>{{ $b }}</span></label>
                                    @endforeach
                                </div>
                                <div class="h" id="bands-help" style="max-width:640px;"></div>
                            </div>
                            @unless($renderer)
                                <div class="note warn" style="margin-top:12px;"><svg class="icon"><use href="#ai-i-alert"/></svg>
                                    <div>Serverda <b>pdftoppm</b> yo'q — PDF butunligicha yuboriladi, bo'laklash va tushib qolgan qatorni qayta o'qish ishlamaydi.</div></div>
                            @endunless
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-h"><div><h3>Ishonchlilik va limitlar</h3><p>Gemini javob bermasa yoki limit tugasa nima bo'ladi.</p></div></div>
                        <div class="card-b fields">
                            <div class="f">
                                <label class="l" for="f-timeout">Javob kutish vaqti</label>
                                <div class="unit"><input id="f-timeout" class="in" type="number" name="timeout" min="10" max="600" value="{{ old('timeout', $s['timeout']) }}" required><span>soniya</span></div>
                            </div>
                            <div class="f">
                                <label class="l" for="f-retries">Qayta urinishlar</label>
                                <input id="f-retries" class="in" type="number" name="max_retries" min="0" max="5" value="{{ old('max_retries', $s['max_retries']) }}" required>
                                <div class="h">429/503 bo'lsa barcha kalitlar bo'yicha yana necha marta.</div>
                            </div>
                            <div class="f">
                                <label class="l" for="f-daily">Kunlik umumiy limit</label>
                                <div class="unit"><input id="f-daily" class="in" type="number" name="daily_limit" min="1" value="{{ old('daily_limit', $s['daily_limit']) }}" placeholder="cheksiz"><span>so'rov</span></div>
                                <div class="h">Barcha kalitlar bo'yicha jami; kalitlarning o'z limiti alohida.</div>
                            </div>
                            <div class="f">
                                <label class="l" for="f-pin">Narx — 1M kirish tokeni</label>
                                <div class="unit"><input id="f-pin" class="in" type="number" step="0.0001" min="0" name="price_input_per_m" value="{{ old('price_input_per_m', $s['price_input_per_m']) }}" placeholder="—"><span>$</span></div>
                            </div>
                            <div class="f">
                                <label class="l" for="f-pout">Narx — 1M chiqish tokeni</label>
                                <div class="unit"><input id="f-pout" class="in" type="number" step="0.0001" min="0" name="price_output_per_m" value="{{ old('price_output_per_m', $s['price_output_per_m']) }}" placeholder="—"><span>$</span></div>
                                <div class="h">Faqat statistikadagi taxminiy narx uchun.</div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-h"><div><h3>Ish jarayoni</h3><p>AI tekshiruvi vedomost topshirish jarayoniga qanday ulanadi.</p></div></div>
                        <div class="card-b" style="padding-top:4px;padding-bottom:4px;">
                            <label class="toggle">
                                <input type="checkbox" name="auto_check" value="1" @checked(old('auto_check', $s['auto_check']))><span class="knob"></span>
                                <span><span class="tt">PDF yuklanishi bilan avtomatik tekshirish</span>
                                <span class="td" style="display:block">O'chiq bo'lsa, xodim vedomost sahifasida «AI tekshirish» tugmasini bosadi.</span></span>
                            </label>
                            <label class="toggle">
                                <input type="checkbox" name="require_ai_before_approve" value="1" @checked(old('require_ai_before_approve', $s['require_ai_before_approve']))><span class="knob"></span>
                                <span><span class="tt">Tasdiqlashdan oldin AI tekshiruvi talab qilinsin</span>
                                <span class="td" style="display:block">AI xato topsa yoki tekshirmagan bo'lsa, xodim sabab yozib tasdiqlaydi. AI ishlayotgan paytda tasdiqlab bo'lmaydi.</span></span>
                            </label>
                            <label class="toggle">
                                <input type="checkbox" name="allow_free_keys" value="1" @checked(old('allow_free_keys', $s['allow_free_keys']))><span class="knob"></span>
                                <span><span class="tt">Bepul kalitlardan ham foydalanish</span>
                                <span class="td" style="display:block">Pullik kalitlar har doim birinchi ishlatiladi; bepullari — ular ishlamasa yoki limiti tugasa.</span></span>
                            </label>
                        </div>
                        <div style="padding:0 18px 16px;">
                            <div class="note warn"><svg class="icon"><use href="#ai-i-shield"/></svg>
                                <div>Vedomostda talabalarning F.I.Sh, ID raqami va baholari bor. Google bepul Gemini tarifida yuborilgan ma'lumotdan o'z mahsulotlarini yaxshilash uchun foydalanishi mumkin; pullik (billing yoqilgan) kalitlarda bunday emas.</div></div>
                        </div>
                    </div>
                </section>

                <section class="panel" id="panel-rules" role="tabpanel" aria-labelledby="tab-rules">
                    <div class="note info" style="margin-bottom:14px;"><svg class="icon"><use href="#ai-i-info"/></svg>
                        <div>Bu qoidalar <b>faqat AI tekshiruviga</b> ta'sir qiladi. «Vedomost tekshirish» sahifasi va jurnal o'z qoidalari bilan ishlaydi — ular bu yerdan o'zgarmaydi.</div></div>

                    <div class="card">
                        <div class="card-h"><div><h3>Chegaralar</h3><p>Python dasturidagi qoidalar: shu chegaradan past bo'lsa ball 0 bo'lishi kerak.</p></div></div>
                        <div class="card-b fields">
                            <div class="f">
                                <label class="l" for="f-pass">JN / MT o'tish chegarasi</label>
                                <div class="unit"><input id="f-pass" class="in" type="number" name="jn_mt_pass" min="0" max="100" value="{{ old('jn_mt_pass', $s['jn_mt_pass']) }}" required><span>%</span></div>
                                @if((int) $s['jn_mt_pass'] !== (int) $systemPass)
                                    <div class="w"><svg class="icon" style="width:14px;height:14px"><use href="#ai-i-alert"/></svg>Jurnalda {{ $systemPass }} ishlatiladi — farq soxta nomuvofiqlik chiqarishi mumkin.</div>
                                @endif
                            </div>
                            <div class="f">
                                <label class="l" for="f-pass-i">JN / MT chegarasi — xalqaro fakultet</label>
                                <div class="unit"><input id="f-pass-i" class="in" type="number" name="jn_mt_pass_international" min="0" max="100" value="{{ old('jn_mt_pass_international', $s['jn_mt_pass_international']) }}" required><span>%</span></div>
                                @if((int) $s['jn_mt_pass_international'] !== (int) $systemPassIntl)
                                    <div class="w"><svg class="icon" style="width:14px;height:14px"><use href="#ai-i-alert"/></svg>Jurnalda {{ $systemPassIntl }} ishlatiladi.</div>
                                @endif
                            </div>
                            <div class="f">
                                <label class="l" for="f-oski">OSKE minimal — Testga kirish</label>
                                <div class="unit"><input id="f-oski" class="in" type="number" name="oski_min" min="0" max="100" value="{{ old('oski_min', $s['oski_min']) }}" required><span>%</span></div>
                                <div class="h">OSKE + Test fanlarida OSKE shundan past bo'lsa, Test bahosi bo'lmasligi kerak.</div>
                            </div>
                            <div class="f">
                                <label class="l" for="f-test">Test minimal</label>
                                <div class="unit"><input id="f-test" class="in" type="number" name="test_min" min="0" max="100" value="{{ old('test_min', $s['test_min']) }}" required><span>%</span></div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-h"><div><h3>Solishtirish</h3><p>PDF va tizim qiymati qachon «mos» hisoblanadi.</p></div></div>
                        <div class="card-b fields">
                            <div class="f">
                                <label class="l" for="f-tol">Ruxsat etilgan farq</label>
                                <input id="f-tol" class="in" type="number" step="0.01" name="tolerance" min="0" max="5" value="{{ old('tolerance', $s['tolerance']) }}" required>
                                <div class="h">Masalan 0,05: PDFda 14,9, tizimda 14,85 — mos.</div>
                            </div>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-h"><div><h3>Doimiy tekshiruvlar</h3><p>Sozlanmaydi — har bir vedomostda avtomatik.</p></div></div>
                        <div class="card-b">
                            <ul class="list">
                                <li><span>Fan nomi (PDF ↔ HEMIS) va boshqa fan PDFi yuklanganini aniqlash</span><span class="badge ok">yoqilgan</span></li>
                                <li><span>Yopilish shakli (HEMIS ↔ vedomostdagi OSKE / Test vaznlari)</span><span class="badge ok">yoqilgan</span></li>
                                <li><span>6-kurs: ballar rasmiy shablon formulasi bo'yicha</span><span class="badge ok">yoqilgan</span></li>
                                <li><span>Sinov fanlar: Test % = Joriy %</span><span class="badge ok">yoqilgan</span></li>
                                <li><span>Soat, kredit, guruh, YN sanasi, o'qituvchilar, dekan, kafedra mudiri</span><span class="badge ok">yoqilgan</span></li>
                                <li><span>Imzolar va muhr borligi (haqiqiyligi emas)</span><span class="badge ok">yoqilgan</span></li>
                                <li><span>Test markazi natijalari, kontingent (chetlashtirilgan talabalar)</span><span class="badge ok">yoqilgan</span></li>
                            </ul>
                        </div>
                    </div>
                </section>

                <div class="savebar" id="savebar" style="display:none;">
                    <span class="msg" id="save-msg">O'zgarishlar yo'q</span>
                    <button type="submit" class="btn primary"><svg class="icon"><use href="#ai-i-check"/></svg>Saqlash</button>
                </div>
            </form>
            <form method="POST" action="{{ route('admin.vedomost-submission.ai-settings.models') }}" id="models-form">@csrf</form>

            {{-- ───────── API kalitlar (faqat admin) ───────── --}}
            @if($canManageKeys)
                <section class="panel" id="panel-keys" role="tabpanel" aria-labelledby="tab-keys">
                    @if($hasFreeKeys && $s['allow_free_keys'])
                        <div class="note warn" style="margin-bottom:14px;"><svg class="icon"><use href="#ai-i-alert"/></svg>
                            <div>Bepul kalitlar yoqilgan — pullik kalitlar ishlamasa yoki limiti tugasa, so'rovlar bepul kalitlarga ketadi.</div></div>
                    @endif

                    <div class="card">
                        <div class="card-h"><div><h3><svg class="icon"><use href="#ai-i-key"/></svg>Gemini API kalitlari</h3><p>Navbat: pullik → bepul; har guruh ichida qo'shilish tartibida.</p></div></div>
                        <div class="card-b">
                            @forelse($keys as $k)
                                @php
                                    $u = $keyUsage[$k->id] ?? [];
                                    $fid = 'key-form-' . $k->id;
                                    $today = $u['today'] ?? 0;
                                    $pct = $k->daily_limit ? min(100, round($today / $k->daily_limit * 100)) : null;
                                @endphp
                                <div class="key {{ $k->enabled ? '' : 'off' }}">
                                    <div class="key-top">
                                        <code>{{ $k->key_hint }}</code>
                                        <b>{{ $k->label ?: 'Nomsiz' }}</b>
                                        <span class="badge {{ $k->is_paid ? 'paid' : 'free' }}">{{ $k->is_paid ? 'Pullik' : 'Bepul' }}</span>
                                        @unless($k->enabled)<span class="badge off">O'chirilgan</span>@endunless
                                        <div class="usage">
                                            <div class="ut">Bugun <b style="color:var(--text)">{{ $today }}</b>{{ $k->daily_limit ? ' / ' . $k->daily_limit : '' }}
                                                @if(!empty($u['today_errors'])) · <span style="color:var(--bad)">{{ $u['today_errors'] }} xato</span>@endif
                                                · 7 kun {{ $u['week'] ?? 0 }}{{ $k->rpm ? ' · ' . $k->rpm . ' RPM' : '' }}</div>
                                            @if($pct !== null)<div class="ub"><i style="width:{{ $pct }}%;{{ $pct >= 90 ? 'background:var(--bad)' : '' }}"></i></div>@endif
                                        </div>
                                    </div>
                                    @if($k->last_error)
                                        <div class="key-err"><svg class="icon" style="width:14px;height:14px"><use href="#ai-i-alert"/></svg>
                                            <span title="{{ $k->last_error }}">{{ \Illuminate\Support\Str::limit($k->last_error, 140) }} <span style="opacity:.7">· {{ $k->last_error_at?->format('d.m.Y H:i') }}</span></span></div>
                                    @endif
                                    <details class="key-more">
                                        <summary>Tahrirlash <svg class="icon" style="width:14px;height:14px"><use href="#ai-i-chev"/></svg></summary>
                                        <form id="{{ $fid }}" method="POST" action="{{ route('admin.vedomost-submission.ai-settings.keys.update', $k->id) }}" class="key-edit">
                                            @csrf
                                            <div class="f"><label class="l" for="{{ $fid }}-label">Nomi</label><input id="{{ $fid }}-label" class="in" name="label" value="{{ $k->label }}"></div>
                                            <div class="f"><label class="l" for="{{ $fid }}-rpm">RPM</label><input id="{{ $fid }}-rpm" class="in" type="number" name="rpm" min="1" value="{{ $k->rpm }}" placeholder="∞"></div>
                                            <div class="f"><label class="l" for="{{ $fid }}-dl">Kunlik limit</label><input id="{{ $fid }}-dl" class="in" type="number" name="daily_limit" min="1" value="{{ $k->daily_limit }}" placeholder="∞"></div>
                                            <label class="chk"><input type="checkbox" name="is_paid" value="1" @checked($k->is_paid)>Pullik</label>
                                            <label class="chk"><input type="checkbox" name="enabled" value="1" @checked($k->enabled)>Yoqilgan</label>
                                            <div style="display:flex;gap:8px;">
                                                <button type="submit" class="btn primary sm">Saqlash</button>
                                                <button type="submit" form="{{ $fid }}-del" class="btn danger sm">O'chirish</button>
                                            </div>
                                        </form>
                                        <form id="{{ $fid }}-del" method="POST" action="{{ route('admin.vedomost-submission.ai-settings.keys.destroy', $k->id) }}"
                                              onsubmit="return confirm('{{ $k->key_hint }} kaliti o\'chirilsinmi? Bu qaytarib bo\'lmaydi.');">@csrf</form>
                                    </details>
                                </div>
                            @empty
                                <div class="empty" style="padding:14px 0;">Hali kalit qo'shilmagan — AI tekshiruv ishlashi uchun kamida bitta kalit kerak.</div>
                            @endforelse
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-h"><div><h3><svg class="icon"><use href="#ai-i-plus"/></svg>Yangi kalit qo'shish</h3><p>Kalit bazada shifrlangan holda saqlanadi va qayta ko'rsatilmaydi — faqat oxirgi 4 belgisi.</p></div></div>
                        <form method="POST" action="{{ route('admin.vedomost-submission.ai-settings.keys.store') }}" class="card-b fields" style="align-items:end;">
                            @csrf
                            <div class="f" style="grid-column:1 / -1;max-width:560px;">
                                <label class="l" for="k-new">API kalit</label>
                                <input id="k-new" class="in" type="password" name="api_key" autocomplete="off" required placeholder="AIza…">
                            </div>
                            <div class="f"><label class="l" for="k-label">Nomi</label><input id="k-label" class="in" name="label" placeholder="masalan: Asosiy" value="{{ old('label') }}"></div>
                            <div class="f"><label class="l" for="k-rpm">RPM</label><input id="k-rpm" class="in" type="number" name="rpm" min="1" placeholder="∞" value="{{ old('rpm') }}"></div>
                            <div class="f"><label class="l" for="k-dl">Kunlik limit</label><input id="k-dl" class="in" type="number" name="daily_limit" min="1" placeholder="∞" value="{{ old('daily_limit') }}"></div>
                            <label class="chk"><input type="checkbox" name="is_paid" value="1" @checked(old('is_paid'))>Pullik kalit</label>
                            <div><button type="submit" class="btn ai" style="width:100%;"><svg class="icon"><use href="#ai-i-plus"/></svg>Qo'shish</button></div>
                        </form>
                    </div>
                </section>
            @endif
        </div>
    </div>

    <script>
        (function () {
            const root = document.querySelector('.ais');
            const tabs = Array.from(root.querySelectorAll('.tab'));
            const formTabs = @json($formTabs);
            const savebar = document.getElementById('savebar');
            const form = document.getElementById('settings-form');

            function show(id) {
                if (!tabs.some(t => t.dataset.tab === id)) id = 'overview';
                tabs.forEach(t => t.setAttribute('aria-selected', t.dataset.tab === id ? 'true' : 'false'));
                root.querySelectorAll('.panel').forEach(p => p.classList.toggle('active', p.id === 'panel-' + id));
                savebar.style.display = formTabs.includes(id) ? 'flex' : 'none';
                history.replaceState(null, '', '#' + id);
            }
            tabs.forEach(t => t.addEventListener('click', () => show(t.dataset.tab)));
            root.querySelector('.tabs').addEventListener('keydown', e => {
                const i = tabs.findIndex(t => t.getAttribute('aria-selected') === 'true');
                if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
                    const n = tabs[(i + (e.key === 'ArrowRight' ? 1 : tabs.length - 1)) % tabs.length];
                    n.focus(); show(n.dataset.tab);
                }
            });
            show(@json($errTab) || location.hash.slice(1) || 'overview');

            // Jonli kuzatuv — faqat tab ochiq bo'lganda, 5 s da bir.
            const live = document.getElementById('panel-live');
            const esc = v => String(v ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
            const dur = s => s === null || s === undefined ? '—' : (s >= 3600 ? Math.floor(s / 3600) + ' soat ' : '') + (s >= 60 ? Math.floor(s % 3600 / 60) + ' daq ' : '') + (s % 60) + ' s';
            async function poll() {
                if (!live.classList.contains('active') || document.hidden) return;
                try {
                    const r = await fetch(live.dataset.url, { headers: { Accept: 'application/json' } });
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    const d = await r.json();
                    const running = d.active.filter(a => a.status === 'running').length;
                    document.getElementById('lv-kpis').innerHTML = [
                        ['Tekshirilmoqda', running + ' / ' + d.workers + ' ishchi'],
                        ['Navbatda', d.active.length - running],
                        ['Band kalitlar', d.leases.length + ' / ' + d.keys_total],
                        ['Navbat vazifalari', d.jobs.pending + ' tayyor · ' + d.jobs.delayed + ' kalit kutmoqda'],
                    ].map(([t, v]) => `<div class="lv-kpi"><div class="t">${t}</div><div class="v">${esc(v)}</div></div>`).join('');
                    const al = document.getElementById('lv-alert');
                    al.hidden = !d.worker_warning;
                    al.innerHTML = d.worker_warning ? `Navbatda ${d.jobs.pending} ta vazifa ${dur(d.jobs.oldest_wait)} dan beri turibdi, lekin hech biri boshlanmadi — AI ishchisi ishlamayotgan bo'lishi mumkin. Serverda: <code>docker exec lmsttatf_app php artisan queue:restart</code>` : '';
                    document.getElementById('lv-active').innerHTML = d.active.length ? d.active.map(a => `<tr>
                        <td><a href="${esc(a.url)}" target="_blank" rel="noopener">${esc(a.title)}</a><div class="lv-muted">${esc(a.form)} · #${a.id}</div></td>
                        <td>${a.stuck ? '<span class="lv-b stuck">Qotib qolgan?</span>' : a.status === 'running' ? '<span class="lv-b run">Tekshirilmoqda</span>' : '<span class="lv-b q">Navbatda</span>'}</td>
                        <td style="white-space:nowrap">${dur(a.seconds)}</td>
                        <td style="white-space:nowrap">${esc(a.key || "—")}</td>
                        <td>${a.step ? `<span class="lv-step ${esc(a.step.l)}">${esc(a.step.t)} · ${esc(a.step.m)}</span><div class="lv-muted">${a.steps} qadam</div>` : '<span class="lv-muted">—</span>'}</td>
                    </tr>`).join('') : '<tr><td colspan="5" class="empty">Hozir hech qanday vedomost tekshirilmayapti.</td></tr>';
                    document.getElementById('lv-req').innerHTML = d.requests.length ? d.requests.map(q => `<tr>
                        <td style="white-space:nowrap">${esc(q.at)}</td>
                        <td>${q.url ? `<a href="${esc(q.url)}" target="_blank" rel="noopener">${esc(q.title || '#' + q.submission)}</a>` : '<span class="lv-muted">—</span>'}</td>
                        <td>${esc(q.key)}</td>
                        <td class="lv-muted">${esc(q.model)}</td>
                        <td>${q.status === 'ok' ? '<span class="lv-b ok">OK</span>' : `<span class="lv-b err">${esc(q.error_type || 'xato')}${q.http ? ' ' + q.http : ''}</span>`}</td>
                        <td style="white-space:nowrap">${q.ms !== null ? (q.ms / 1000).toFixed(1) + ' s' : '—'}</td>
                        <td class="lv-muted" style="max-width:380px">${esc(q.error || '')}</td>
                    </tr>`).join('') : '<tr><td colspan="7" class="empty">So\'rovlar yo\'q.</td></tr>';
                    document.getElementById('lv-upd').textContent = 'Yangilandi: ' + d.now;
                } catch (e) {
                    document.getElementById('lv-upd').textContent = 'Yangilab bo\'lmadi: ' + e.message;
                }
            }
            setInterval(poll, 5000);
            tabs.forEach(t => t.addEventListener('click', () => { if (t.dataset.tab === 'live') poll(); }));
            poll();

            // Saqlanmagan o'zgarishlar
            const initial = new FormData(form);
            const snapshot = fd => JSON.stringify(Array.from(fd.entries()).filter(([k]) => k !== '_token'));
            const base = snapshot(initial);
            let dirty = false;
            form.addEventListener('input', check);
            form.addEventListener('change', check);
            function check() {
                dirty = snapshot(new FormData(form)) !== base;
                savebar.classList.toggle('dirty', dirty);
                document.getElementById('save-msg').textContent = dirty ? "Saqlanmagan o'zgarishlar bor" : "O'zgarishlar yo'q";
            }
            form.addEventListener('submit', () => { dirty = false; });
            window.addEventListener('beforeunload', e => { if (dirty) { e.preventDefault(); e.returnValue = ''; } });

            // Bo'laklar tavsifi
            const help = {
                1: "Butun sahifa bitta so'rovda. Eng arzon. Qator tushib qolsa yoki ichki hisob mos kelmasa, o'sha sahifa avtomatik 2 bo'lak bilan qayta o'qiladi.",
                2: "Har sahifa 2 bo'lakda — raqamlar kattaroq ko'rinadi, tushib qolish va xato o'qish kamayadi. So'rovlar ~2 baravar. Tavsiya etiladi.",
                3: "Har sahifa 3 bo'lakda. Mayda yoki sifatsiz skanerlar uchun. So'rovlar ~3 baravar.",
                4: "Har sahifa 4 bo'lakda. Juda zich jadvallar uchun. So'rovlar ~4 baravar, tekshiruv sekinroq.",
            };
            const bh = document.getElementById('bands-help');
            const upd = () => { const c = form.querySelector('input[name=bands]:checked'); bh.textContent = c ? help[c.value] : ''; };
            form.querySelectorAll('input[name=bands]').forEach(r => r.addEventListener('change', upd));
            upd();
        })();
    </script>
</x-app-layout>
