<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Qo'lda yarim to'ldirilgan otrabotka — ogohlantirish
        </h2>
    </x-slot>

    <style>
        .mrg-wrap { padding: 16px; }
        .mrg-card { background:#fff; border:1px solid #e2e8f0; border-radius:12px; box-shadow:0 1px 3px rgba(0,0,0,0.05); }
        .mrg-note { margin:14px 16px; padding:10px 14px; background:#fffbeb; border:1px solid #fcd34d; border-radius:8px; font-size:12.5px; color:#78350f; line-height:1.6; }
        .mrg-filters { padding:14px 18px; display:flex; gap:12px; align-items:flex-end; flex-wrap:wrap; border-bottom:1px solid #e2e8f0; background:linear-gradient(135deg,#fef3f2,#fff5f5); border-radius:12px 12px 0 0; }
        .mrg-label { font-size:11px; font-weight:700; color:#475569; text-transform:uppercase; letter-spacing:.04em; display:block; margin-bottom:4px; }
        .mrg-date { height:36px; padding:0 10px; border:1px solid #cbd5e1; border-radius:8px; font-size:13px; }
        .mrg-btn { height:36px; padding:8px 16px; background:linear-gradient(135deg,#d97706,#f59e0b); color:#fff; border:none; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; }
        .mrg-btn:disabled { opacity:.5; cursor:not-allowed; }
        .mrg-badge { display:inline-block; padding:6px 14px; font-size:13px; font-weight:700; border-radius:8px; background:linear-gradient(135deg,#b45309,#d97706); color:#fff; }
        .mrg-table-wrap { overflow-x:auto; }
        table.mrg-table { width:100%; border-collapse:separate; border-spacing:0; font-size:13px; }
        .mrg-table thead th { padding:10px 12px; text-align:left; background:linear-gradient(135deg,#fef3c7,#fde68a); font-weight:700; font-size:11px; color:#334155; text-transform:uppercase; letter-spacing:.04em; border-bottom:2px solid #fcd34d; white-space:nowrap; }
        .mrg-table tbody tr { border-bottom:1px solid #f1f5f9; }
        .mrg-table tbody tr:hover { background:#fffbeb; }
        .mrg-table td { padding:9px 12px; vertical-align:middle; }
        .mrg-pill { display:inline-block; padding:2px 8px; border-radius:6px; font-size:11px; font-weight:700; }
        .mrg-pill-q { background:#fee2e2; color:#991b1b; border:1px solid #fca5a5; }
        .mrg-mono { font-family:ui-monospace,SFMono-Regular,Menlo,monospace; font-size:12px; color:#475569; }
        .mrg-empty { padding:50px 20px; text-align:center; color:#64748b; }
        .mrg-spin { display:inline-block; width:20px; height:20px; border:3px solid #e2e8f0; border-top-color:#d97706; border-radius:50%; animation:mrg-rot .8s linear infinite; vertical-align:middle; margin-right:8px; }
        @keyframes mrg-rot { to { transform:rotate(360deg); } }
    </style>

    <div class="mrg-wrap">
        <div class="mrg-card">
            <div class="mrg-filters">
                <div>
                    <label class="mrg-label">Sanadan</label>
                    <input type="date" id="mrgFrom" class="mrg-date">
                </div>
                <div>
                    <label class="mrg-label">Sanagacha</label>
                    <input type="date" id="mrgTo" class="mrg-date">
                </div>
                <div>
                    <label class="mrg-label">&nbsp;</label>
                    <button id="mrgLoad" class="mrg-btn">Yuklash</button>
                </div>
                <div style="margin-left:auto;">
                    <label class="mrg-label">&nbsp;</label>
                    <span class="mrg-badge" id="mrgTotal">—</span>
                </div>
            </div>

            <div class="mrg-note">
                <strong>Bu nima?</strong> Bir kunda bir nechta juftlik (pair) bo'lib, retake (otrabotka)
                ba'zi juftliklarga qo'yilgan, lekin qoidaviy juftlik (NB yoki &lt;60) retakesiz qolgan holatlar.
                Ko'pincha o'qituvchi qo'lda to'ldirishda bitta juftlikni unutgan bo'ladi.
                <strong>Bu yerda baholar o'zgartirilmaydi</strong> — faqat ko'rsatiladi; kerak bo'lsa jurnalда qo'lda to'ldiring.
                <span class="mrg-mono">(q)</span> belgisi — retake quiz orqali kelgan.
            </div>

            <div class="mrg-table-wrap">
                <table class="mrg-table">
                    <thead>
                        <tr>
                            <th>F.I.Sh.</th>
                            <th>Guruh</th>
                            <th>Fan</th>
                            <th>Sana</th>
                            <th>Juftlik</th>
                            <th>Qoldirilgan</th>
                            <th>Tafsilot (juftlik:baho/retake)</th>
                        </tr>
                    </thead>
                    <tbody id="mrgBody">
                        <tr><td colspan="7" class="mrg-empty">Sana oralig'ini tanlab "Yuklash" tugmasini bosing.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
    (function(){
        const dataUrl = '{{ route('admin.reports.manual-retake-gaps.data') }}';
        const $ = id => document.getElementById(id);
        const esc = s => (s ?? '').toString().replace(/[<>&]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[c]));

        // Default: oxirgi 6 oy
        const today = new Date();
        const from = new Date(); from.setMonth(from.getMonth() - 6);
        const fmt = d => d.toISOString().slice(0,10);
        $('mrgTo').value = fmt(today);
        $('mrgFrom').value = fmt(from);

        async function load() {
            const f = $('mrgFrom').value, t = $('mrgTo').value;
            if (!f || !t) { alert("Sana oralig'ini tanlang"); return; }
            $('mrgLoad').disabled = true;
            $('mrgBody').innerHTML = `<tr><td colspan="7" class="mrg-empty"><span class="mrg-spin"></span>Yuklanmoqda... (biroz vaqt olishi mumkin)</td></tr>`;
            $('mrgTotal').textContent = '—';
            try {
                const url = new URL(dataUrl, window.location.origin);
                url.searchParams.set('date_from', f);
                url.searchParams.set('date_to', t);
                const r = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
                const body = await r.json();
                if (!r.ok || !body.success) {
                    $('mrgBody').innerHTML = `<tr><td colspan="7" class="mrg-empty" style="color:#b91c1c">Xato: ${esc(body.error || ('HTTP '+r.status))}</td></tr>`;
                    return;
                }
                const rows = body.rows || [];
                $('mrgTotal').textContent = 'Jami: ' + body.total + ' ta holat';
                if (rows.length === 0) {
                    $('mrgBody').innerHTML = `<tr><td colspan="7" class="mrg-empty" style="color:#15803d;font-weight:600;">Bunday holat yo'q ✓</td></tr>`;
                    return;
                }
                $('mrgBody').innerHTML = rows.map(x => `<tr>
                    <td style="font-weight:700;color:#0f172a;">${esc(x.full_name)}</td>
                    <td>${esc(x.group_name)}</td>
                    <td>${esc(x.subject_name)}</td>
                    <td>${esc(x.kun)}</td>
                    <td style="text-align:center;">${esc(x.juftliklar)}</td>
                    <td style="text-align:center;"><span class="mrg-pill mrg-pill-q">${esc(x.qoldirilgan)}</span></td>
                    <td class="mrg-mono">${esc(x.tafsilot)}</td>
                </tr>`).join('');
            } catch (e) {
                $('mrgBody').innerHTML = `<tr><td colspan="7" class="mrg-empty" style="color:#b91c1c">Xato: ${esc(e.message)}</td></tr>`;
            } finally {
                $('mrgLoad').disabled = false;
            }
        }

        $('mrgLoad').addEventListener('click', load);
        load();
    })();
    </script>
</x-app-layout>
