<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Yig'ma tekshiruv — {{ count($items) }} ta vedomost</title>
    @php
        $compare = \App\Services\VedomostCheckEngine::COMPARE;
        $statusTxt = \App\Services\VedomostCheckEngine::STATUS_TXT;
        $verdictTxt = ['ok' => 'Mos', 'issues' => 'Nomuvofiqlik', 'review' => 'PDFdan tekshiring', 'wrong_document' => "Noto'g'ri fayl"];
        $problems = array_values(array_filter($items, fn ($i) => $i['checked'] && $i['verdict'] !== 'ok'));
        $clean = array_values(array_filter($items, fn ($i) => $i['checked'] && $i['verdict'] === 'ok'));
        $unchecked = array_values(array_filter($items, fn ($i) => !$i['checked']));
        $fmt = fn ($x) => $x === null || $x === '' ? '—' : (is_numeric($x) ? rtrim(rtrim(number_format((float) $x, 1, ',', ''), '0'), ',') : $x);
        $approveBox = function ($it) use ($canApprove) {
            return $canApprove && $it['approvable'] && !$it['gate']['blocked'];
        };
    @endphp
    <style>
        :root {
            --xl: #217346; --xl-dark: #185c37; --xl-soft: #e7f2ec;
            --grid: #e2e5e9; --text: #1f2328; --muted: #6b7280; --bg: #f5f6f7;
            --bad: #fde68a; --ocr: #fed7aa; --red: #fecaca; --edit: #2563eb;
            --font: Calibri, "Segoe UI", Inter, ui-sans-serif, system-ui, -apple-system, Roboto, Arial, sans-serif;
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: var(--font); font-size: 14px; color: var(--text); background: var(--bg); padding-bottom: 72px; }
        button, input, textarea { font-family: inherit; font-size: inherit; }
        a { color: var(--xl-dark); }
        .titlebar { position: sticky; top: 0; z-index: 10; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; padding: 10px 16px; background: var(--xl); color: #fff; }
        .titlebar a.back { color: #fff; text-decoration: none; font-size: 20px; line-height: 1; padding: 4px 8px; border-radius: 6px; }
        .titlebar a.back:hover { background: rgba(255,255,255,.15); }
        .titlebar h1 { margin: 0; font-size: 16px; font-weight: 700; flex: 1; min-width: 200px; }
        .kpi { display: inline-flex; align-items: center; gap: 6px; padding: 4px 11px; border-radius: 999px; background: #fff; font-weight: 700; font-size: 13px; text-decoration: none; }
        .kpi.ok { color: #166534; } .kpi.bad { color: #92400e; } .kpi.none { color: #475569; }
        main { max-width: 1400px; margin: 0 auto; padding: 16px; }
        .flash { padding: 10px 14px; border-radius: 8px; margin-bottom: 12px; white-space: pre-line; }
        .flash.ok { background: #dcfce7; color: #166534; } .flash.err { background: #fee2e2; color: #991b1b; }
        h2.sec { font-size: 13px; text-transform: uppercase; letter-spacing: .05em; color: var(--muted); margin: 22px 0 10px; display: flex; align-items: center; gap: 8px; }
        h2.sec .n { background: #e5e7eb; color: #374151; border-radius: 999px; padding: 0 8px; font-size: 12px; }
        .card { background: #fff; border: 1px solid var(--grid); border-radius: 10px; margin-bottom: 14px; overflow: hidden; }
        .card-h { display: flex; align-items: flex-start; gap: 12px; flex-wrap: wrap; padding: 12px 14px; border-bottom: 1px solid var(--grid); background: #fafbfc; }
        .card-h .t { flex: 1; min-width: 240px; }
        .card-h .t b { font-size: 15px; }
        .card-h .sub { font-size: 12px; color: var(--muted); margin-top: 2px; }
        .verdict { font-size: 12px; font-weight: 700; padding: 2px 9px; border-radius: 999px; white-space: nowrap; }
        .verdict.ok { background: #dcfce7; color: #166534; } .verdict.issues { background: #fef3c7; color: #92400e; }
        .verdict.review { background: #ffedd5; color: #9a3412; } .verdict.wrong_document { background: #fee2e2; color: #b91c1c; }
        .links { display: flex; gap: 8px; align-items: center; }
        .lbtn { display: inline-flex; align-items: center; height: 30px; padding: 0 11px; border: 1px solid #cbd5e1; border-radius: 6px; background: #fff; color: #1f2937; text-decoration: none; font-size: 13px; font-weight: 600; white-space: nowrap; }
        .lbtn:hover { background: #f1f5f9; }
        .approve { display: flex; flex-direction: column; gap: 6px; min-width: 260px; }
        .approve label.chk { display: flex; align-items: center; gap: 8px; font-weight: 700; color: #166534; cursor: pointer; }
        .approve input[type=checkbox] { width: 18px; height: 18px; accent-color: var(--xl); }
        .approve textarea { width: 100%; min-height: 34px; height: 34px; resize: vertical; border: 1px solid #d1d5db; border-radius: 6px; padding: 6px 8px; font-size: 13px; }
        .approve .why { font-size: 11px; color: #92400e; }
        .approve .na { font-size: 12px; color: var(--muted); }
        .hdr-issues { padding: 8px 14px; border-bottom: 1px solid var(--grid); font-size: 13px; background: #fffbeb; }
        .hdr-issues div { padding: 2px 0; }
        .tbl-wrap { overflow-x: auto; }
        table { border-collapse: collapse; width: 100%; font-size: 13px; }
        th, td { border: 1px solid var(--grid); padding: 4px 7px; text-align: center; white-space: nowrap; }
        th { background: #f3f4f6; font-size: 11px; color: #4b5563; font-weight: 700; }
        th.grp { background: #e8eef9; }
        td.l { text-align: left; }
        td.fio { text-align: left; font-weight: 600; max-width: 280px; overflow: hidden; text-overflow: ellipsis; }
        td.note { text-align: left; white-space: normal; min-width: 280px; max-width: 520px; font-size: 12px; color: #374151; }
        td.c-bad { background: var(--bad); } td.c-ocr { background: var(--ocr); }
        td.mark { color: #475569; }
        .st { font-weight: 700; font-size: 12px; }
        .st.bad { color: #92400e; } .st.ocr { color: #9a3412; } .st.nopdf, .st.missing { color: #b91c1c; }
        input.cell { width: 64px; height: 26px; border: 1px solid transparent; background: rgba(255,255,255,.65); border-radius: 4px; text-align: center; font-weight: 700; }
        input.cell:hover { border-color: #94a3b8; }
        input.cell:focus { outline: none; border-color: var(--edit); background: #fff; }
        input.cell.dirty { border-color: var(--edit); box-shadow: 0 0 0 2px #dbeafe; background: #fff; }
        .empty-ok { padding: 10px 14px; font-size: 13px; color: var(--muted); }
        .clean-tbl td, .clean-tbl th { text-align: left; }
        .bar { position: fixed; left: 0; right: 0; bottom: 0; z-index: 20; display: flex; align-items: center; gap: 12px; flex-wrap: wrap; padding: 10px 16px; background: #1f2937; color: #e5e7eb; font-size: 13px; }
        .bar .sp { flex: 1; }
        .bbtn { height: 36px; padding: 0 16px; border-radius: 6px; border: 1px solid #4b5563; background: transparent; color: #e5e7eb; font-weight: 700; cursor: pointer; }
        .bbtn.primary { background: var(--xl); border-color: var(--xl); color: #fff; }
        .bbtn.save { background: var(--edit); border-color: var(--edit); color: #fff; }
        .bbtn:disabled { opacity: .45; cursor: not-allowed; }
        .toast { position: fixed; right: 16px; bottom: 70px; padding: 10px 14px; border-radius: 6px; background: #111827; color: #fff; opacity: 0; transition: opacity .2s; pointer-events: none; z-index: 30; }
        .toast.show { opacity: 1; } .toast.err { background: #991b1b; }
        @media (max-width: 640px) { .approve { min-width: 0; width: 100%; } main { padding: 10px; } }
    </style>
</head>
<body>
<header class="titlebar">
    <a class="back" href="{{ route('admin.vedomost-submission.index') }}" aria-label="Vedomostlar ro'yxatiga qaytish">←</a>
    <h1>Yig'ma tekshiruv — {{ count($items) }} ta vedomost</h1>
    <a class="kpi bad" href="#problems">⚠ {{ count($problems) }} muammoli</a>
    <a class="kpi ok" href="#clean">✓ {{ count($clean) }} mos</a>
    @if(count($unchecked))<a class="kpi none" href="#unchecked">{{ count($unchecked) }} AI tekshirmagan</a>@endif
</header>

<main>
    @if(session('success'))<div class="flash ok">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="flash err">{{ session('error') }}</div>@endif

    <form id="approve-form" method="POST" action="{{ route('admin.vedomost-submission.ai-review.approve') }}">
        @csrf
    </form>

    {{-- ───── Muammolilar ───── --}}
    <h2 class="sec" id="problems">Muammolilar <span class="n">{{ count($problems) }}</span></h2>
    @forelse($problems as $it)
        @php $v = $it['v']; $m = $it['merged']; $r = $it['report']; @endphp
        <section class="card" data-vid="{{ $v->id }}" data-save="{{ route('admin.vedomost-submission.ai-report.edits', $v->id) }}">
            <div class="card-h">
                <div class="t">
                    <b>{{ $m->subject_name ?? $v->subject_name }}</b> · {{ $m->group_name ?? $v->group_name }}
                    <span class="verdict {{ $it['verdict'] }}">{{ $verdictTxt[$it['verdict']] ?? $it['verdict'] }}</span>
                    <div class="sub">{{ \App\Models\VedomostSubmission::formLabel($v->form_type) }} · {{ $r['summary'] }}</div>
                </div>
                <div class="links">
                    <a class="lbtn" href="{{ route('admin.vedomost-submission.ai-report', $v->id) }}" target="_blank" rel="noopener">To'liq hisobot</a>
                    @if($v->pdf_path)<a class="lbtn" href="{{ route('admin.vedomost-submission.file', [$v->id, 'pdf']) }}" target="_blank" rel="noopener">PDF</a>@endif
                </div>
                <div class="approve">
                    @if($approveBox($it))
                        <label class="chk"><input type="checkbox" name="approve[]" value="{{ $v->id }}" form="approve-form" class="ap-cb"> Tasdiqlash</label>
                        @if($it['gate']['needs_reason'])
                            <textarea name="reasons[{{ $v->id }}]" form="approve-form" placeholder="Sabab (majburiy, kamida 5 belgi)" class="ap-why" data-vid="{{ $v->id }}"></textarea>
                            <span class="why">{{ $it['gate']['message'] }}</span>
                        @endif
                    @else
                        <span class="na">{{ !$it['approvable'] ? \App\Models\VedomostSubmission::statusLabels()[$v->status] ?? $v->status : ($it['gate']['message'] ?? 'Tasdiqlash huquqi yo\'q') }}</span>
                    @endif
                </div>
            </div>

            @if(count($it['header']))
                <div class="hdr-issues">
                    @foreach($it['header'] as $h)
                        <div><b>{{ $h['st'] === 'bad' ? '✗' : '⚠' }} {{ $h['k'] }}:</b> {{ $h['v'] }}</div>
                    @endforeach
                </div>
            @endif

            @if(count($it['rows']) || count($it['extra']))
                <div class="tbl-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th rowspan="2">#</th><th rowspan="2">F.I.Sh</th><th rowspan="2">ID</th><th rowspan="2">Holat</th>
                                @foreach($compare as $key => [$label])
                                    <th colspan="2" class="grp">{{ $label }}</th>
                                @endforeach
                                <th rowspan="2">Izoh</th>
                            </tr>
                            <tr>
                                @foreach($compare as $key => $c)<th>Mark</th><th>PDF</th>@endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($it['rows'] as $row)
                                <tr>
                                    <td>{{ $row['n'] }}</td>
                                    <td class="fio" title="{{ $row['fio'] }}">{{ $row['fio'] }}</td>
                                    <td>{{ $row['id'] }}</td>
                                    <td><span class="st {{ $row['status'] }}">{{ $statusTxt[$row['status']] ?? $row['status'] }}</span></td>
                                    @foreach($compare as $key => [$label, $pf, $col])
                                        @php $cell = $row['cells'][$key] ?? null; $cst = $cell['status'] ?? 'empty'; @endphp
                                        @if($cst === 'skip')
                                            <td class="mark" colspan="2" title="Bu yopilish shaklida solishtirilmaydi">—</td>
                                        @else
                                            <td class="mark">{{ $fmt($row['vt'][$col] ?? null) }}</td>
                                            <td class="{{ in_array($cst, ['bad', 'ocr'], true) ? 'c-' . $cst : '' }}">
                                                @if($it['canEdit'] && !empty($row['key']))
                                                    <input class="cell" data-key="{{ $row['key'] }}" data-f="{{ $pf }}" value="{{ $row['pdf'][$pf] ?? '' }}" data-orig="{{ $row['pdf'][$pf] ?? '' }}" aria-label="{{ $row['fio'] }} — {{ $label }} (PDF)">
                                                @else
                                                    {{ $row['pdf'][$pf] ?? '—' }}
                                                @endif
                                            </td>
                                        @endif
                                    @endforeach
                                    <td class="note">{{ implode('; ', array_map(fn ($f) => $f['m'], $row['flags'] ?? [])) }}</td>
                                </tr>
                            @endforeach
                            @foreach($it['extra'] as $e)
                                <tr>
                                    <td>{{ $e['nr'] ?? '' }}</td>
                                    <td class="fio">{{ $e['fio'] }}</td>
                                    <td>{{ $e['id'] }}</td>
                                    <td><span class="st missing">Markda yo'q</span></td>
                                    <td colspan="{{ count($compare) * 2 }}" class="mark">—</td>
                                    <td class="note">{{ $e['note'] ?? '' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="empty-ok">Talabalar qatorlari mos — muammo faqat sarlavhada.</div>
            @endif
        </section>
    @empty
        <div class="card"><div class="empty-ok">Muammoli vedomost yo'q.</div></div>
    @endforelse

    {{-- ───── Mos kelganlar ───── --}}
    <h2 class="sec" id="clean">Mos kelganlar <span class="n">{{ count($clean) }}</span></h2>
    @if(count($clean))
        <div class="card tbl-wrap">
            <table class="clean-tbl">
                <thead><tr><th style="width:34px;"><input type="checkbox" id="clean-all" aria-label="Hammasini belgilash"></th><th>Fan</th><th>Guruh</th><th>Shakl</th><th>Natija</th><th></th></tr></thead>
                <tbody>
                    @foreach($clean as $it)
                        @php $v = $it['v']; $m = $it['merged']; @endphp
                        <tr>
                            <td>
                                @if($approveBox($it))
                                    <input type="checkbox" name="approve[]" value="{{ $v->id }}" form="approve-form" class="ap-cb clean-cb" {{ $it['gate']['needs_reason'] ? '' : 'checked' }}>
                                @endif
                            </td>
                            <td>{{ $m->subject_name ?? $v->subject_name }}</td>
                            <td>{{ $m->group_name ?? $v->group_name }}</td>
                            <td>{{ \App\Models\VedomostSubmission::formLabel($v->form_type) }}</td>
                            <td style="white-space:normal;">
                                {{ $it['report']['summary'] }}
                                @if(!$approveBox($it))<span class="na" style="color:var(--muted);"> · {{ !$it['approvable'] ? (\App\Models\VedomostSubmission::statusLabels()[$v->status] ?? $v->status) : ($it['gate']['message'] ?? '') }}</span>@endif
                                @if($it['gate']['needs_reason'] && $approveBox($it))
                                    <textarea name="reasons[{{ $v->id }}]" form="approve-form" placeholder="Sabab (majburiy)" class="ap-why" data-vid="{{ $v->id }}" style="display:block;width:100%;margin-top:4px;"></textarea>
                                    <span class="why" style="font-size:11px;color:#92400e;">{{ $it['gate']['message'] }}</span>
                                @endif
                            </td>
                            <td><a class="lbtn" href="{{ route('admin.vedomost-submission.ai-report', $v->id) }}" target="_blank" rel="noopener">Hisobot</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="card"><div class="empty-ok">Tanlanganlar orasida to'liq mos vedomost yo'q.</div></div>
    @endif

    {{-- ───── AI tekshirmagan ───── --}}
    @if(count($unchecked))
        <h2 class="sec" id="unchecked">AI tekshirmagan <span class="n">{{ count($unchecked) }}</span></h2>
        <div class="card tbl-wrap">
            <table class="clean-tbl">
                <thead><tr><th>Fan</th><th>Guruh</th><th>Holat</th><th></th></tr></thead>
                <tbody>
                    @foreach($unchecked as $it)
                        @php $v = $it['v']; $m = $it['merged']; @endphp
                        <tr>
                            <td>{{ $m->subject_name ?? $v->subject_name }}</td>
                            <td>{{ $m->group_name ?? $v->group_name }}</td>
                            <td>{{ match ($v->ai_check_status) { 'queued' => 'Navbatda', 'running' => 'Tekshirilmoqda', 'error' => 'AI xatosi', 'done' => 'Eski formatdagi natija — qayta tekshiring', default => 'Tekshirilmagan' } }}</td>
                            <td><a class="lbtn" href="{{ route('admin.vedomost-submission.show', $v->id) }}" target="_blank" rel="noopener">Vedomostni ochish</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</main>

<div class="bar" role="region" aria-label="Amallar">
    <span id="bar-info">Belgilangan: <b id="ap-count">0</b> ta vedomost</span>
    <span class="sp"></span>
    <button type="button" class="bbtn save" id="btn-save" disabled>Tuzatishlarni saqlash (<span id="dirty-n">0</span>)</button>
    <button type="submit" form="approve-form" class="bbtn primary" id="btn-approve" disabled>Belgilanganlarni tasdiqlash</button>
</div>
<div class="toast" id="toast" role="status" aria-live="polite"></div>

<script>
(function () {
    const pending = {}; // vid => {rows: {key: {field: value|null}}}
    const toast = (m, err) => { const t = document.getElementById('toast'); t.textContent = m; t.className = 'toast show' + (err ? ' err' : ''); setTimeout(() => t.className = 'toast', 3500); };
    const dirtyCount = () => Object.values(pending).reduce((n, p) => n + Object.values(p.rows).reduce((k, o) => k + Object.keys(o).length, 0), 0);
    const cbs = () => [...document.querySelectorAll('.ap-cb')];

    function sync() {
        const d = dirtyCount();
        document.getElementById('dirty-n').textContent = d;
        document.getElementById('btn-save').disabled = d === 0;
        const n = cbs().filter(c => c.checked).length;
        document.getElementById('ap-count').textContent = n;
        // Saqlanmagan tuzatish bo'lsa — avval saqlash (tasdiqlash eski natija bo'yicha ketmasin).
        document.getElementById('btn-approve').disabled = n === 0 || d > 0;
        document.getElementById('btn-approve').title = d > 0 ? 'Avval tuzatishlarni saqlang' : '';
        const all = document.getElementById('clean-all');
        const clean = [...document.querySelectorAll('.clean-cb')];
        if (all) all.checked = clean.length > 0 && clean.every(c => c.checked);
    }

    document.querySelectorAll('input.cell').forEach(inp => {
        inp.addEventListener('input', () => {
            const vid = inp.closest('[data-vid]').dataset.vid, key = inp.dataset.key, f = inp.dataset.f;
            pending[vid] = pending[vid] || { rows: {} };
            pending[vid].rows[key] = pending[vid].rows[key] || {};
            if (inp.value.trim() === inp.dataset.orig.trim()) delete pending[vid].rows[key][f];
            else pending[vid].rows[key][f] = inp.value.trim();
            if (!Object.keys(pending[vid].rows[key]).length) delete pending[vid].rows[key];
            inp.classList.toggle('dirty', f in (pending[vid].rows[key] || {}));
            sync();
        });
        inp.addEventListener('keydown', e => {
            if (e.key !== 'Enter' && e.key !== 'ArrowDown' && e.key !== 'ArrowUp') return;
            e.preventDefault();
            const list = [...document.querySelectorAll('input.cell')];
            const i = list.indexOf(inp) + (e.key === 'ArrowUp' ? -1 : 1);
            if (list[i]) { list[i].focus(); list[i].select(); }
        });
    });
    document.addEventListener('change', e => { if (e.target.classList?.contains('ap-cb')) sync(); });
    document.getElementById('clean-all')?.addEventListener('change', e => {
        document.querySelectorAll('.clean-cb').forEach(c => c.checked = e.target.checked); sync();
    });

    async function save() {
        const btn = document.getElementById('btn-save');
        if (btn.disabled) return;
        btn.disabled = true; btn.textContent = 'Saqlanmoqda...';
        const token = document.querySelector('meta[name=csrf-token]').content;
        const errors = [];
        for (const [vid, p] of Object.entries(pending)) {
            if (!Object.keys(p.rows).length) continue;
            const url = document.querySelector(`[data-vid="${vid}"]`).dataset.save;
            try {
                const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify({ rows: p.rows, header: {}, weights: {}, signatures: {}, system: {} }) });
                const data = await res.json().catch(() => ({}));
                if (!res.ok) throw new Error(data.message || ('Xatolik: ' + res.status));
                delete pending[vid];
            } catch (err) { errors.push(err.message); }
        }
        if (errors.length) { toast(errors.join('; '), true); btn.textContent = 'Tuzatishlarni saqlash (' + dirtyCount() + ')'; sync(); return; }
        location.reload();
    }
    document.getElementById('btn-save').addEventListener('click', save);
    document.addEventListener('keydown', e => { if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); save(); } });

    document.getElementById('approve-form').addEventListener('submit', e => {
        const missing = cbs().filter(c => c.checked).filter(c => {
            const why = document.querySelector(`.ap-why[data-vid="${c.value}"]`);
            return why && why.value.trim().length < 5;
        });
        if (missing.length) {
            e.preventDefault();
            toast(missing.length + ' ta vedomost uchun sabab yozilmagan (kamida 5 belgi)', true);
            document.querySelector(`.ap-why[data-vid="${missing[0].value}"]`).focus();
            return;
        }
        if (!confirm(cbs().filter(c => c.checked).length + ' ta vedomost tasdiqlansinmi?')) e.preventDefault();
    });
    window.addEventListener('beforeunload', e => { if (dirtyCount() > 0) { e.preventDefault(); e.returnValue = ''; } });
    sync();
})();
</script>
</body>
</html>
