<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-semibold leading-tight text-gray-800">
            Kunlik monitoring — Moodle ↔ LMS sync ↔ Mark
        </h2>
    </x-slot>

    <style>
        .km-wrap { padding: 16px; }
        .km-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
        .km-filters { padding: 14px 18px; display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; border-bottom: 1px solid #e2e8f0; background: linear-gradient(135deg, #f0f8f4, #e8f5ee); border-radius: 12px 12px 0 0; }
        .km-filter-item { display: flex; flex-direction: column; gap: 4px; }
        .km-filter-label { font-size: 11px; font-weight: 700; color: #475569; text-transform: uppercase; letter-spacing: 0.04em; }
        .km-date { height: 36px; padding: 0 10px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 13px; background: #fff; }
        .km-date:focus { outline: none; border-color: #16a34a; box-shadow: 0 0 0 3px rgba(22,163,74,0.15); }
        .km-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: linear-gradient(135deg, #16a34a, #22c55e); color: #fff; border: none; border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; height: 36px; box-shadow: 0 2px 6px rgba(22,163,74,0.3); }
        .km-btn:hover:not(:disabled) { background: linear-gradient(135deg, #15803d, #16a34a); }
        .km-btn:disabled { opacity: 0.5; cursor: not-allowed; }
        .km-btn-quick { padding: 6px 12px; height: 32px; background: #fff; color: #1e293b; border: 1px solid #cbd5e1; font-weight: 600; box-shadow: none; }
        .km-btn-quick:hover:not(:disabled) { background: #f0fdf4; color: #166534; border-color: #86efac; }

        .km-totals { display: flex; gap: 14px; padding: 14px 18px; border-bottom: 1px solid #e2e8f0; flex-wrap: wrap; background: #fafafa; }
        .km-stat { flex: 1; min-width: 160px; padding: 12px 14px; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; }
        .km-stat-label { font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.04em; margin-bottom: 4px; }
        .km-stat-value { font-size: 22px; font-weight: 800; color: #0f172a; }
        .km-stat.km-stat-gap { background: linear-gradient(135deg, #fef2f2, #fee2e2); border-color: #fecaca; }
        .km-stat.km-stat-gap .km-stat-value { color: #b91c1c; }
        .km-stat.km-stat-ok { background: linear-gradient(135deg, #f0fdf4, #dcfce7); border-color: #bbf7d0; }
        .km-stat.km-stat-ok .km-stat-value { color: #15803d; }

        .km-table-wrap { overflow-x: auto; }
        table.km-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 13px; }
        .km-table thead th { padding: 10px 12px; text-align: left; background: linear-gradient(135deg, #e8f5ee, #d1fae5); font-weight: 700; font-size: 11px; color: #334155; text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 2px solid #86efac; white-space: nowrap; }
        .km-table tbody tr { border-bottom: 1px solid #f1f5f9; transition: background 0.15s; cursor: pointer; }
        .km-table tbody tr:hover { background: #f0fdf4; }
        .km-table tbody tr.km-row-gap { background: #fff7ed; }
        .km-table tbody tr.km-row-gap:hover { background: #fef3c7; }
        .km-table tbody tr.km-row-sync-gap { background: #fef2f2; }
        .km-table tbody tr.km-row-sync-gap:hover { background: #fee2e2; }
        .km-table td { padding: 10px 12px; vertical-align: middle; }
        .km-num { font-weight: 700; font-variant-numeric: tabular-nums; }
        .km-num-zero { color: #94a3b8; }
        .km-badge { display: inline-block; padding: 3px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.03em; }
        .km-badge-ok { background: #dcfce7; color: #166534; border: 1px solid #86efac; }
        .km-badge-mark { background: #fef3c7; color: #92400e; border: 1px solid #fcd34d; }
        .km-badge-sync { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }
        .km-empty { padding: 60px 20px; text-align: center; color: #64748b; }
        .km-loading { padding: 40px 20px; text-align: center; color: #64748b; }
        .km-loading .km-spinner { display: inline-block; width: 22px; height: 22px; border: 3px solid #e2e8f0; border-top-color: #16a34a; border-radius: 50%; animation: km-spin 0.8s linear infinite; vertical-align: middle; margin-right: 8px; }
        @keyframes km-spin { to { transform: rotate(360deg); } }

        /* Modal */
        .km-modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.55); z-index: 1000; align-items: center; justify-content: center; padding: 16px; }
        .km-modal-overlay.km-show { display: flex; }
        .km-modal { background: #fff; border-radius: 14px; max-width: 900px; width: 100%; max-height: 88vh; overflow: hidden; display: flex; flex-direction: column; box-shadow: 0 20px 60px rgba(0,0,0,0.35); }
        .km-modal-header { padding: 14px 18px; border-bottom: 1px solid #e2e8f0; display: flex; align-items: center; justify-content: space-between; background: linear-gradient(135deg, #f0f8f4, #e8f5ee); }
        .km-modal-title { font-size: 16px; font-weight: 800; color: #0f172a; }
        .km-modal-close { background: transparent; border: none; font-size: 22px; cursor: pointer; color: #64748b; }
        .km-modal-close:hover { color: #dc2626; }
        .km-modal-body { padding: 16px 18px; overflow-y: auto; flex: 1; }
        .km-section-title { font-size: 13px; font-weight: 800; color: #1e293b; margin: 0 0 8px; padding-bottom: 6px; border-bottom: 1px solid #e2e8f0; }
        .km-section + .km-section { margin-top: 18px; }
        .km-ids-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 6px; }
        .km-id-chip { padding: 5px 8px; background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; border-radius: 6px; font-size: 12px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; text-align: center; font-weight: 600; }
        .km-mini-table { width: 100%; border-collapse: separate; border-spacing: 0; font-size: 12px; }
        .km-mini-table thead th { padding: 6px 8px; text-align: left; background: #f8fafc; font-weight: 700; font-size: 10px; color: #475569; text-transform: uppercase; letter-spacing: 0.04em; border-bottom: 1px solid #cbd5e1; }
        .km-mini-table td { padding: 6px 8px; border-bottom: 1px solid #f1f5f9; }
        .km-pill { display: inline-block; padding: 2px 6px; background: #f1f5f9; border-radius: 4px; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11px; }
    </style>

    <div class="km-wrap">
        <div class="km-card">
            <div class="km-filters">
                <div class="km-filter-item">
                    <label class="km-filter-label">Boshlanish sanasi</label>
                    <input type="date" id="kmDateFrom" class="km-date">
                </div>
                <div class="km-filter-item">
                    <label class="km-filter-label">Tugash sanasi</label>
                    <input type="date" id="kmDateTo" class="km-date">
                </div>
                <div class="km-filter-item">
                    <label class="km-filter-label">&nbsp;</label>
                    <button id="kmLoad" class="km-btn">Yuklash</button>
                </div>
                <div class="km-filter-item">
                    <label class="km-filter-label">&nbsp;</label>
                    <button id="kmExport" class="km-btn" type="button" style="background: linear-gradient(135deg, #d97706, #f59e0b); box-shadow: 0 2px 6px rgba(217,119,6,0.3);">📊 Excel</button>
                </div>
                <div class="km-filter-item">
                    <label class="km-filter-label">&nbsp;</label>
                    <button id="kmDiagnose" class="km-btn km-btn-quick" type="button">🔍 Diagnostika</button>
                </div>
                <div class="km-filter-item" style="margin-left: auto;">
                    <label class="km-filter-label">Tezkor</label>
                    <div style="display:flex; gap:6px;">
                        <button class="km-btn km-btn-quick" data-quick="today">Bugun</button>
                        <button class="km-btn km-btn-quick" data-quick="yesterday">Kecha</button>
                        <button class="km-btn km-btn-quick" data-quick="7">So'nggi 7 kun</button>
                        <button class="km-btn km-btn-quick" data-quick="30">So'nggi 30 kun</button>
                    </div>
                </div>
            </div>

            <div class="km-totals" id="kmTotals" style="display:none;">
                <div class="km-stat">
                    <div class="km-stat-label">Moodle (jami)</div>
                    <div class="km-stat-value" id="kmTotMoodle">0</div>
                </div>
                <div class="km-stat">
                    <div class="km-stat-label">LMS sync</div>
                    <div class="km-stat-value" id="kmTotSynced">0</div>
                </div>
                <div class="km-stat">
                    <div class="km-stat-label">Markda</div>
                    <div class="km-stat-value" id="kmTotGraded">0</div>
                </div>
                <div class="km-stat" id="kmStatSyncGap">
                    <div class="km-stat-label">Sync farq</div>
                    <div class="km-stat-value" id="kmTotSyncGap">0</div>
                </div>
                <div class="km-stat" id="kmStatMarkGap">
                    <div class="km-stat-label">Mark farq</div>
                    <div class="km-stat-value" id="kmTotMarkGap">0</div>
                </div>
            </div>

            <div class="km-table-wrap">
                <table class="km-table">
                    <thead>
                        <tr>
                            <th>Sana</th>
                            <th>Moodle</th>
                            <th>LMS sync</th>
                            <th>Markda</th>
                            <th>Sync farq</th>
                            <th>Mark farq</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="kmTbody">
                        <tr>
                            <td colspan="7" class="km-empty">Sana oralig'ini tanlang va "Yuklash" tugmasini bosing.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="km-modal-overlay" id="kmModal">
        <div class="km-modal">
            <div class="km-modal-header">
                <div class="km-modal-title" id="kmModalTitle">Tafsilot</div>
                <button class="km-modal-close" id="kmModalClose">&times;</button>
            </div>
            <div class="km-modal-body" id="kmModalBody"></div>
        </div>
    </div>

    <script>
    (function(){
        const dataUrl = '{{ route($routePrefix . ".kunlik-monitoring.data") }}';
        const missingUrl = '{{ route($routePrefix . ".kunlik-monitoring.missing") }}';
        const diagnoseUrl = '{{ route($routePrefix . ".kunlik-monitoring.diagnose") }}';
        const exportUrl = '{{ route($routePrefix . ".kunlik-monitoring.export") }}';
        const $ = id => document.getElementById(id);

        function fmtDate(d) {
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${day}`;
        }

        function setQuickRange(kind) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            let from, to;
            if (kind === 'today') { from = today; to = today; }
            else if (kind === 'yesterday') {
                const y = new Date(today); y.setDate(y.getDate() - 1);
                from = y; to = y;
            } else {
                const days = parseInt(kind, 10);
                to = today;
                from = new Date(today); from.setDate(from.getDate() - (days - 1));
            }
            $('kmDateFrom').value = fmtDate(from);
            $('kmDateTo').value = fmtDate(to);
        }

        document.querySelectorAll('[data-quick]').forEach(btn => {
            btn.addEventListener('click', () => {
                setQuickRange(btn.dataset.quick);
                loadData();
            });
        });

        // Default oraliq: so'nggi 7 kun.
        setQuickRange('7');

        function numCell(n, klass) {
            const c = n === 0 ? 'km-num km-num-zero' : 'km-num';
            return `<td class="${c} ${klass || ''}">${n}</td>`;
        }

        function statusBadge(s) {
            if (s === 'sync_gap') return '<span class="km-badge km-badge-sync">Sync gap</span>';
            if (s === 'mark_gap') return '<span class="km-badge km-badge-mark">Mark gap</span>';
            return '<span class="km-badge km-badge-ok">OK</span>';
        }

        function rowClass(s) {
            if (s === 'sync_gap') return 'km-row-sync-gap';
            if (s === 'mark_gap') return 'km-row-gap';
            return '';
        }

        async function loadData() {
            const dateFrom = $('kmDateFrom').value;
            const dateTo = $('kmDateTo').value;
            if (!dateFrom || !dateTo) {
                alert('Sana oralig\'ini tanlang');
                return;
            }
            $('kmLoad').disabled = true;
            $('kmTbody').innerHTML = `<tr><td colspan="7" class="km-loading"><span class="km-spinner"></span>Yuklanmoqda...</td></tr>`;
            $('kmTotals').style.display = 'none';

            try {
                const url = new URL(dataUrl, window.location.origin);
                url.searchParams.set('date_from', dateFrom);
                url.searchParams.set('date_to', dateTo);
                const r = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
                const body = await r.json();

                if (!r.ok || !body.success) {
                    const msg = body.error || ('HTTP ' + r.status);
                    $('kmTbody').innerHTML = `<tr><td colspan="7" class="km-empty" style="color:#b91c1c">Xato: ${msg}</td></tr>`;
                    return;
                }

                const days = body.days || [];
                const totals = body.totals || {};

                $('kmTotMoodle').textContent = totals.moodle_count ?? 0;
                $('kmTotSynced').textContent = totals.synced_count ?? 0;
                $('kmTotGraded').textContent = totals.graded_count ?? 0;
                $('kmTotSyncGap').textContent = totals.sync_gap ?? 0;
                $('kmTotMarkGap').textContent = totals.mark_gap ?? 0;

                $('kmStatSyncGap').className = 'km-stat ' + ((totals.sync_gap ?? 0) > 0 ? 'km-stat-gap' : 'km-stat-ok');
                $('kmStatMarkGap').className = 'km-stat ' + ((totals.mark_gap ?? 0) > 0 ? 'km-stat-gap' : 'km-stat-ok');
                $('kmTotals').style.display = 'flex';

                if (days.length === 0) {
                    $('kmTbody').innerHTML = `<tr><td colspan="7" class="km-empty">Ma'lumot topilmadi.</td></tr>`;
                    return;
                }

                const rows = days.map(d => {
                    const cls = rowClass(d.status);
                    return `<tr class="${cls}" data-date="${d.date}">
                        <td><strong>${d.date}</strong></td>
                        ${numCell(d.moodle_count)}
                        ${numCell(d.synced_count)}
                        ${numCell(d.graded_count)}
                        ${numCell(d.sync_gap)}
                        ${numCell(d.mark_gap)}
                        <td>${statusBadge(d.status)}</td>
                    </tr>`;
                }).join('');

                $('kmTbody').innerHTML = rows;

                $('kmTbody').querySelectorAll('tr[data-date]').forEach(tr => {
                    tr.addEventListener('click', () => openMissing(tr.dataset.date));
                });
            } catch (e) {
                $('kmTbody').innerHTML = `<tr><td colspan="7" class="km-empty" style="color:#b91c1c">Xato: ${e.message}</td></tr>`;
            } finally {
                $('kmLoad').disabled = false;
            }
        }

        async function openMissing(date) {
            $('kmModalTitle').textContent = `Tafsilot — ${date}`;
            $('kmModalBody').innerHTML = `<div class="km-loading"><span class="km-spinner"></span>Yuklanmoqda...</div>`;
            $('kmModal').classList.add('km-show');

            try {
                const url = new URL(missingUrl, window.location.origin);
                url.searchParams.set('date', date);
                const r = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
                const body = await r.json();

                if (!r.ok || !body.success) {
                    const msg = body.error || ('HTTP ' + r.status);
                    $('kmModalBody').innerHTML = `<div class="km-empty" style="color:#b91c1c">Xato: ${msg}</div>`;
                    return;
                }

                const missingSync = body.missing_sync || [];
                const missingMark = body.missing_mark || [];

                let html = `<div style="font-size:12px;color:#475569;margin-bottom:14px;">
                    Moodle'da bu sanada jami <strong>${body.moodle_count}</strong> ta tugatilgan urinish bor.
                </div>`;

                html += `<div class="km-section">
                    <h3 class="km-section-title">
                        Moodle → LMS sync bosqichida tushib qolgan (${missingSync.length})
                        <span style="font-weight:500;color:#64748b;font-size:11px;margin-left:6px;">
                            attempt_id Moodle'da bor, ammo hemis_quiz_results jadvalida yo'q
                        </span>
                    </h3>`;
                if (missingSync.length === 0) {
                    html += `<div style="color:#15803d;font-size:12px;font-weight:600;">Yo'qotish yo'q ✓</div>`;
                } else {
                    html += `<div class="km-ids-grid">` +
                        missingSync.map(id => `<div class="km-id-chip">${id}</div>`).join('') +
                        `</div>`;
                    html += `<div style="margin-top:10px;font-size:11px;color:#64748b;">
                        Tuzatish uchun: Moodle Import sahifasidagi SMART Import tugmasini bosing yoki kunlik cron'ni tekshiring.
                    </div>`;
                }
                html += `</div>`;

                html += `<div class="km-section">
                    <h3 class="km-section-title">
                        LMS sync → Mark bosqichida tushib qolgan (${missingMark.length})
                        <span style="font-weight:500;color:#64748b;font-size:11px;margin-left:6px;">
                            hemis_quiz_results'da bor, ammo student_grades'ga yuklanmagan
                        </span>
                    </h3>`;
                if (missingMark.length === 0) {
                    html += `<div style="color:#15803d;font-size:12px;font-weight:600;">Yo'qotish yo'q ✓</div>`;
                } else {
                    html += `<table class="km-mini-table"><thead><tr>
                        <th>attempt_id</th>
                        <th>HEMIS ID</th>
                        <th>F.I.Sh.</th>
                        <th>Fan</th>
                        <th>Quiz turi</th>
                        <th>Tugatildi</th>
                        <th>Baho</th>
                    </tr></thead><tbody>` +
                    missingMark.map(r => `<tr>
                        <td><span class="km-pill">${r.attempt_id}</span></td>
                        <td>${r.student_id ?? ''}</td>
                        <td>${(r.student_name ?? '').toString().replace(/[<>&]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[c]))}</td>
                        <td>${(r.fan_name ?? '').toString().replace(/[<>&]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[c]))}</td>
                        <td>${(r.quiz_type ?? '').toString().replace(/[<>&]/g, c => ({'<':'&lt;','>':'&gt;','&':'&amp;'}[c]))}</td>
                        <td>${r.date_finish ?? ''}</td>
                        <td>${r.grade ?? ''}</td>
                    </tr>`).join('') +
                    `</tbody></table>`;
                    html += `<div style="margin-top:10px;font-size:11px;color:#64748b;">
                        Tuzatish uchun: "Yuklanmagan natijalar" sahifasiga o'tib, ushbu yozuvlarni qo'lda yuklang yoki diagnostika orqali tartibga soling.
                    </div>`;
                }
                html += `</div>`;

                $('kmModalBody').innerHTML = html;
            } catch (e) {
                $('kmModalBody').innerHTML = `<div class="km-empty" style="color:#b91c1c">Xato: ${e.message}</div>`;
            }
        }

        $('kmLoad').addEventListener('click', loadData);

        $('kmExport').addEventListener('click', () => {
            const dateFrom = $('kmDateFrom').value;
            const dateTo = $('kmDateTo').value;
            if (!dateFrom || !dateTo) {
                alert('Sana oralig\'ini tanlang');
                return;
            }
            const url = new URL(exportUrl, window.location.origin);
            url.searchParams.set('date_from', dateFrom);
            url.searchParams.set('date_to', dateTo);
            window.location.href = url.toString();
        });

        $('kmDiagnose').addEventListener('click', async () => {
            $('kmModalTitle').textContent = 'Moodle WS diagnostika';
            $('kmModalBody').innerHTML = `<div class="km-loading"><span class="km-spinner"></span>Tekshirilmoqda...</div>`;
            $('kmModal').classList.add('km-show');

            try {
                const r = await fetch(diagnoseUrl, { headers: { 'Accept': 'application/json' } });
                const body = await r.json();

                const renderResult = (label, info, raw) => {
                    const ok = raw.ok && !(raw.body && raw.body.exception);
                    const color = ok ? '#15803d' : '#b91c1c';
                    let detail = '';
                    if (raw.body && raw.body.exception) {
                        detail = `<div style="margin-top:6px;font-size:11px;">
                            <div><strong>errorcode:</strong> ${raw.body.errorcode ?? '-'}</div>
                            <div><strong>message:</strong> ${raw.body.message ?? '-'}</div>
                            ${raw.body.debuginfo ? `<div><strong>debuginfo:</strong> <code style="word-break:break-all">${raw.body.debuginfo}</code></div>` : ''}
                        </div>`;
                    } else if (raw.error) {
                        detail = `<div style="margin-top:6px;font-size:11px;color:#b91c1c;">${raw.error}</div>`;
                    } else if (ok) {
                        detail = `<div style="margin-top:6px;font-size:11px;color:#15803d;">✓ Javob OK (HTTP ${raw.http_status})</div>`;
                    }
                    return `<div class="km-section">
                        <h3 class="km-section-title" style="color:${color};">${label} — ${ok ? 'OK ✓' : 'XATO ✗'}</h3>
                        <div style="font-size:12px;font-family:ui-monospace,monospace;background:#f8fafc;padding:8px;border-radius:6px;border:1px solid #e2e8f0;">
                            <strong>${info}</strong>
                        </div>
                        ${detail}
                    </div>`;
                };

                let html = `<div style="font-size:12px;color:#475569;margin-bottom:14px;">
                    <div><strong>WS URL:</strong> ${body.ws_url || '<em>not configured</em>'}</div>
                    <div><strong>Token:</strong> ${body.has_token ? body.token_preview : '<em style="color:#b91c1c">not configured</em>'}</div>
                </div>`;

                html += renderResult('1) ESKI funksiya: local_quizexport_get_results', 'page=1, limit=1', body.old || {});
                html += renderResult('2) YANGI funksiya: local_quizexport_get_daily_summary', 'kechagi va bugungi sana', body.new || {});

                html += `<div class="km-section" style="margin-top:18px;padding:12px;background:#fffbeb;border:1px solid #fcd34d;border-radius:8px;">
                    <strong style="color:#92400e;">Talqin:</strong>
                    <ul style="margin:6px 0 0 18px;font-size:12px;color:#78350f;line-height:1.6;">
                        <li>Ikkalasi <strong>OK</strong> bo'lsa — hammasi joyida.</li>
                        <li>Ikkalasi <strong>XATO</strong> bo'lsa — token noto'g'ri yoki WS o'chirilgan.</li>
                        <li>Faqat YANGI funksiya XATO bo'lsa (Access control) — funksiya hali ham service'ga to'liq bog'lanmagan: Moodle <em>web server</em>'ini restart qiling (php-fpm yoki apache), keyin qayta sinab ko'ring.</li>
                    </ul>
                </div>`;

                $('kmModalBody').innerHTML = html;
            } catch (e) {
                $('kmModalBody').innerHTML = `<div class="km-empty" style="color:#b91c1c">Xato: ${e.message}</div>`;
            }
        });

        $('kmModalClose').addEventListener('click', () => $('kmModal').classList.remove('km-show'));
        $('kmModal').addEventListener('click', e => {
            if (e.target === $('kmModal')) $('kmModal').classList.remove('km-show');
        });

        // Birinchi yuklash.
        loadData();
    })();
    </script>
</x-app-layout>
