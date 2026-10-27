/*
 * HEMIS'ga yozish (xatcho'p / bookmarklet). HEMIS sahifasida, registratorning o'z sessiyasida ishlaydi.
 * mark (LMS) "HEMIS solishtirish" oynasidan yuklangan JSON'dagi amallarni HEMIS'ning o'z
 * qo'shish / tahrirlash / o'chirish formalari orqali bajaradi — tools/hemis-sync/hemis-write.mjs bilan bir xil mantiq:
 * bugun va o'tgan kunlarga yozilmaydi, to'siqli amallar o'tkaziladi, boshida bir marta tasdiq so'raladi.
 */
(function () {
    'use strict';
    if (window.__hemisWriterOpen) { document.getElementById('hwPanel')?.scrollIntoView(); return; }
    if (!/hemis\./i.test(location.hostname)) { alert('Bu tugma HEMIS sahifasida bosilishi kerak (hemis.ttatf.uz).'); return; }
    if (!document.querySelector('meta[name="csrf-param"]') || /login/i.test(location.pathname)) {
        alert('Avval HEMIS\'ga kiring (login va parol bilan), so\'ng tugmani qayta bosing.'); return;
    }
    window.__hemisWriterOpen = true;

    const OP_NAME = { create: 'Qo\'shish', update: 'O\'zgartirish', delete: 'O\'chirish' };
    const OP_ORDER = { delete: 0, update: 1, create: 2 };
    const esc = v => String(v == null ? '' : v).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const sleep = ms => new Promise(r => setTimeout(r, ms));
    const localToday = () => { const d = new Date(); return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0'); };

    const runInPage = async ({ job, apply }) => {
        const q = String.fromCharCode(63);
        const csrfParam = document.querySelector('meta[name="csrf-param"]').content;
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        const parse = html => new DOMParser().parseFromString(html, 'text/html');
        const errorsOf = doc => [...doc.querySelectorAll('.has-error .help-block, .help-block-error, .alert-danger, .error-summary li')]
            .map(e => e.textContent.trim()).filter(Boolean);
    
        if (job.op === 'delete') {
            const editUrl = '/curriculum/schedule-edit' + q + 'id=' + job.hemis_id;
            const er = await fetch(editUrl);
            if (er.status === 404) return { ok: true, note: 'HEMIS\'da allaqachon yo\'q' };
            if (!apply) return { ok: true, note: 'tekshirildi' };
            const fd = new FormData();
            fd.append(csrfParam, csrfToken);
            const r = await fetch(editUrl + '&delete=1', { method: 'POST', body: fd });
            const doc = parse(await r.text());
            const errs = errorsOf(doc);
            return errs.length ? { ok: false, error: errs.join('; ') } : { ok: r.ok, error: r.ok ? null : 'HTTP ' + r.status };
        }
    
        const url = job.op === 'create'
            ? '/curriculum/schedule-create' + q + 'date=' + job.date + '&curriculum=' + job.curriculum + '&education_year=' + job.education_year
                + '&semester=' + job.semester + '&group=' + job.group_id
            : '/curriculum/schedule-edit' + q + 'id=' + job.hemis_id;
        const gr = await fetch(url);
        if (gr.status === 404) return { ok: false, error: 'HEMIS yozuvi topilmadi (#' + job.hemis_id + ')' };
        const doc = parse(await gr.text());
        const form = [...doc.querySelectorAll('form')].find(f => f.querySelector('[name^="ESubjectSchedule"]'));
        if (!form) return { ok: false, error: 'HEMIS formasi topilmadi (sessiya tugaganmi?)' };
    
        const problems = [];
        const setSelect = (name, value, label, mustExist = true) => {
            // Tahrirlash formasida o'qituvchi/guruh bitta qiymatli ("[_employees]"), yaratishda ko'p qiymatli ("[_employees][]").
            const el = form.querySelector('select[name="' + name + '"]') || form.querySelector('select[name="' + name.replace(/\[\]$/, '') + '"]');
            if (!el) { problems.push(label + ' maydoni yo\'q'); return; }
            const values = Array.isArray(value) ? value.map(String) : [String(value)];
            [...el.options].forEach(o => { o.selected = false; o.removeAttribute('selected'); });
            for (const v of values) {
                let o = [...el.options].find(x => x.value === v);
                if (!o) {
                    if (mustExist) { problems.push(label + ' ro\'yxatida yo\'q: ' + v); continue; }
                    o = doc.createElement('option');
                    o.value = v;
                    el.appendChild(o);
                }
                o.selected = true;
                o.setAttribute('selected', 'selected');
            }
        };
        setSelect('ESubjectSchedule[_lesson_pair]', job.pair, 'Para');
        setSelect('ESubjectSchedule[_auditorium]', job.auditorium_code, 'Xona');
        setSelect('ESubjectSchedule[_subject]', job.subject_id, 'Fan');
        // Dars turi ro'yxati fan tanlangach (AJAX) to'ladi — qiymat qo'lda qo'shiladi.
        setSelect('ESubjectSchedule[_training_type]', job.code, 'Dars turi', false);
        setSelect('ESubjectSchedule[_employees][]', [job.employee_id], 'O\'qituvchi');
        setSelect('ESubjectSchedule[groups][]', [job.group_id], 'Guruh');
        if (problems.length) return { ok: false, error: problems.join('; '), problems };
        if (!apply) return { ok: true, note: 'forma to\'g\'ri' };
    
        const fd = new FormData(form);
        fd.set(csrfParam, csrfToken);
        const action = form.getAttribute('action') || url;
        const r = await fetch(action, { method: 'POST', body: fd });
        const html = await r.text();
        const after = parse(html);
        const errs = errorsOf(after);
        if (errs.length) return { ok: false, error: errs.join('; ') };
        // Muvaffaqiyatda HEMIS jadval sahifasiga qaytaradi; forma qayta chiqsa — saqlanmagan.
        const stillForm = /schedule-(create|edit)/.test(r.url) && after.querySelector('form [name^="ESubjectSchedule"]');
        return stillForm ? { ok: false, error: 'saqlanmadi (forma qayta qaytdi)' } : { ok: true };
    };

    // --- Oyna
    const box = document.createElement('div');
    box.id = 'hwPanel';
    box.style.cssText = 'position:fixed;top:12px;right:12px;width:720px;max-width:calc(100vw - 24px);max-height:calc(100vh - 24px);overflow:auto;z-index:2147483647;'
        + 'background:#fff;border:1px solid #999;border-radius:10px;box-shadow:0 10px 30px rgba(0,0,0,.3);font:13px/1.4 Arial,sans-serif;color:#222;padding:12px';
    box.innerHTML = '<div style="display:flex;align-items:center;gap:8px;margin-bottom:8px"><b style="flex:1;font-size:15px">HEMIS\'ga yozish (mark)</b>'
        + '<button id="hwClose" style="padding:2px 10px">Yopish</button></div>'
        + '<div style="margin-bottom:6px">1) mark\'dagi «HEMIS solishtirish» oynasidan yuklangan JSON faylni tanlang: <input type="file" id="hwFile" accept=".json,application/json"></div>'
        + '<div id="hwFilters" style="display:none;margin-bottom:6px;padding:6px;background:#f5f5f5;border-radius:6px"></div>'
        + '<div id="hwSummary" style="margin-bottom:6px"></div>'
        + '<div id="hwButtons" style="display:none;gap:6px;margin-bottom:6px">'
        + '<button id="hwCheck" style="padding:4px 10px">Tekshirish (yozmaydi)</button> '
        + '<button id="hwApply" style="padding:4px 10px;background:#059669;color:#fff;border:0;border-radius:4px">HEMIS\'ga yozish</button> '
        + '<button id="hwStop" style="padding:4px 10px" disabled>To\'xtatish</button> '
        + '<button id="hwLog" style="padding:4px 10px" disabled>Jurnalni yuklab olish</button></div>'
        + '<div id="hwList" style="font-family:Consolas,monospace;font-size:12px;white-space:pre-wrap;border-top:1px solid #ddd;padding-top:6px"></div>';
    document.body.appendChild(box);
    const $ = id => document.getElementById(id);
    $('hwClose').onclick = () => { if (running && !confirm('Yozish davom etmoqda. To\'xtatib yopamizmi?')) return; stop = true; box.remove(); window.__hemisWriterOpen = false; };

    let rep = null, jobs = [], skipped = [], results = [], running = false, stop = false, lastMode = null;
    const FILTERS = ['hwUntil', 'hwGroup', 'hwSubject', 'hwOp_create', 'hwOp_update', 'hwOp_delete'];
    // Bugun — JSON'dagi sana va shu kompyuterdagi sanadan kattasi. Har safar qayta hisoblanadi: oyna kechadan ochiq qolishi mumkin.
    const cutoff = () => [String(rep.today || ''), localToday()].sort().pop();

    const line = j => OP_NAME[j.op] + ' · ' + j.group + ' · ' + j.date + ' ' + j.start + '–' + j.end + ' · ' + j.subject + ' · ' + j.type
        + ' · ' + (j.employee_name || '—') + ' · ' + (j.auditorium_name || '—') + (j.hemis_id ? ' · #' + j.hemis_id : '')
        + (j.changes && j.changes.length ? ' (' + j.changes.join('; ') + ')' : '');

    function build() {
        const f = {
            until: $('hwUntil') ? $('hwUntil').value : '',
            group: $('hwGroup') ? $('hwGroup').value : '',
            subject: $('hwSubject') ? $('hwSubject').value : '',
            ops: ['create', 'update', 'delete'].filter(o => !$('hwOp_' + o) || $('hwOp_' + o).checked),
        };
        const today = cutoff();
        jobs = []; skipped = [];
        for (const g of rep.groups || []) {
            if (f.group && String(g.group) !== f.group) continue;
            for (const u of g.units || []) {
                if (f.subject && u.subject_name !== f.subject) continue;
                for (const a of u.actions || []) {
                    const base = Object.assign({ group: g.group, subject: u.subject_name, type: (u.code_names || {})[a.code] || a.code }, a);
                    if (a.date <= today) { skipped.push(Object.assign(base, { skip: 'bugun yoki o\'tgan kun' })); continue; }
                    if (f.until && a.date > f.until) { skipped.push(Object.assign(base, { skip: 'chegaradan keyin' })); continue; }
                    if (!f.ops.includes(a.op)) { skipped.push(Object.assign(base, { skip: 'amal turi tanlanmagan' })); continue; }
                    if (a.blocked && a.blocked.length) { skipped.push(Object.assign(base, { skip: 'to\'siq: ' + a.blocked.join('; ') })); continue; }
                    const pair = (rep.hemis_pairs || {})[a.start + '-' + a.end];
                    if (a.op !== 'delete' && !pair) { skipped.push(Object.assign(base, { skip: 'HEMIS para topilmadi' })); continue; }
                    jobs.push(Object.assign(base, { pair, curriculum: g.curriculum_hemis_id, education_year: g.education_year,
                        semester: g.semester_code, group_id: g.group_hemis_id, subject_id: u.subject_id }));
                }
            }
        }
        jobs.sort((x, y) => String(x.group).localeCompare(String(y.group)) || x.group_id - y.group_id
            || OP_ORDER[x.op] - OP_ORDER[y.op] || x.date.localeCompare(y.date) || (x.seq || 0) - (y.seq || 0) || x.start.localeCompare(y.start));
        const c = { create: 0, update: 0, delete: 0 };
        jobs.forEach(j => c[j.op]++);
        const blk = skipped.filter(s => s.skip.startsWith('to\'siq')).length;
        $('hwSummary').innerHTML = 'Bugun: <b>' + esc(today) + '</b> (bugun va undan oldingi kunlarga yozilmaydi). Bajariladi: <b>' + jobs.length + '</b> ta — '
            + '<span style="color:#047857">+' + c.create + ' qo\'shish</span>, <span style="color:#b45309">~' + c.update + ' o\'zgartirish</span>, '
            + '<span style="color:#b91c1c">−' + c.delete + ' o\'chirish</span>. O\'tkaziladi: ' + skipped.length + ' ta' + (blk ? ' (shundan to\'siqli ' + blk + ')' : '') + '.';
        $('hwList').textContent = jobs.map(j => '  ' + line(j)).join('\n');
        $('hwButtons').style.display = jobs.length ? 'block' : 'none';
    }

    $('hwFile').onchange = async function () {
        const file = this.files && this.files[0];
        if (!file) return;
        try { rep = JSON.parse(await file.text()); } catch (e) { alert('JSON o\'qilmadi: ' + e.message); return; }
        if (!rep || !Array.isArray(rep.groups)) { alert('Bu fayl mark «HEMIS solishtirish» JSON\'i emas.'); return; }
        const groups = [...new Set(rep.groups.map(g => g.group))];
        const subjects = [...new Set(rep.groups.flatMap(g => (g.units || []).filter(u => (u.actions || []).length).map(u => u.subject_name)))].sort();
        $('hwFilters').style.display = 'block';
        // value aniq beriladi: usiz brauzer nomdagi ortiqcha bo'shliqlarni qisqartiradi va filtr hech narsa topmaydi.
        $('hwFilters').innerHTML = '2) Qaysi haftagacha: <select id="hwUntil"><option value="">JSON\'dagi hammasi</option>'
            + (rep.weeks || []).map(w => '<option value="' + esc(w.to) + '">' + w.week + '-hafta oxirigacha (' + esc(w.to) + ')' + (w.week === rep.current_week ? ' • joriy' : '') + '</option>').join('') + '</select> '
            + ' Guruh: <select id="hwGroup"><option value="">hammasi</option>' + groups.map(g => '<option value="' + esc(g) + '">' + esc(g) + '</option>').join('') + '</select> '
            + ' Fan: <select id="hwSubject" style="max-width:220px"><option value="">hammasi</option>' + subjects.map(s => '<option value="' + esc(s) + '">' + esc(s) + '</option>').join('') + '</select><br>'
            + ['create', 'update', 'delete'].map(o => '<label style="margin-right:8px"><input type="checkbox" id="hwOp_' + o + '" checked> ' + OP_NAME[o] + '</label>').join('');
        FILTERS.forEach(id => { $(id).onchange = build; });
        build();
    };

    async function run(apply) {
        if (running) return;
        // Bajariladigan ro'yxat boshlanishdagi holatdan olinadi; yozish davomida filtrlar o'chiq — tasdiqdagi son
        // va yoziladigan amallar bir xil bo'lib qoladi. Oldin qayta tuziladi — sana chegarasi hozirgi kun bo'yicha.
        build();
        const batch = jobs.slice();
        if (!batch.length) return;
        // Bitta tasdiq — butun ro'yxat uchun (guruhlar soni bilan); guruh almashganda qayta so'ralmaydi. To'xtatish — "To'xtatish" tugmasi.
        const nGroups = new Set(batch.map(x => x.group_id)).size;
        if (apply && !confirm(batch.length + ' ta amal (' + nGroups + ' ta guruh) HEMIS\'ga yoziladi. Guruhlar orasida qayta so\'ralmaydi — kerak bo\'lsa "To\'xtatish" tugmasini bosing.\n\nBoshlaymizmi?')) return;
        running = true; stop = false; results = []; lastMode = apply ? 'apply' : 'check';
        ['hwCheck', 'hwApply', 'hwFile'].concat(FILTERS).forEach(id => { $(id).disabled = true; });
        $('hwStop').disabled = false; $('hwLog').disabled = true;
        const list = $('hwList');
        list.textContent = '';
        let lastGroup = null;
        for (const j of batch) {
            if (stop) { results.push(Object.assign({}, j, { status: 'skipped', reason: 'to\'xtatildi' })); continue; }
            // Yozish yarim tundan o'tib ketsa — endi bugun bo'lib qolgan darslar yozilmaydi.
            if (j.date <= cutoff()) {
                results.push(Object.assign({}, j, { status: 'skipped', reason: 'bugun yoki o\'tgan kun' }));
                list.textContent += '  – ' + line(j) + '  — bugun yoki o\'tgan kun, yozilmadi\n';
                continue;
            }
            if (j.group_id !== lastGroup) {
                list.textContent += '— ' + j.group + ' (' + batch.filter(x => x.group_id === j.group_id).length + ' ta amal)\n';
            }
            lastGroup = j.group_id;
            let res;
            try { res = await runInPage({ job: j, apply }); } catch (e) { res = { ok: false, error: String(e && e.message || e) }; }
            results.push(Object.assign({}, j, { status: res.ok ? 'ok' : 'error', note: res.note || null, error: res.error || null }));
            list.textContent += (res.ok ? '  ✓ ' : '  ✗ ') + line(j) + (res.ok ? (res.note ? '  — ' + res.note : '') : '  — ' + res.error) + '\n';
            box.scrollTop = box.scrollHeight;
            await sleep(apply ? 400 : 100);
        }
        const ok = results.filter(r => r.status === 'ok').length, err = results.filter(r => r.status === 'error').length;
        list.textContent += '\n' + (apply ? 'Yozildi' : 'Tekshirildi') + ': ' + ok + ' ta muvaffaqiyatli, ' + err + ' ta xato.'
            + (apply ? '\nEndi mark\'da «HEMIS solishtirish» → «HEMIS\'dan yangilab, keyin solishtirish»ni bosing.' : '');
        running = false;
        ['hwCheck', 'hwApply', 'hwFile'].concat(FILTERS).forEach(id => { $(id).disabled = false; });
        $('hwStop').disabled = true; $('hwLog').disabled = false;
    }
    $('hwCheck').onclick = () => run(false);
    $('hwApply').onclick = () => run(true);
    $('hwStop').onclick = () => { stop = true; };
    $('hwLog').onclick = () => {
        const blob = new Blob([JSON.stringify({ mode: lastMode, at: new Date().toISOString(), results, skipped }, null, 1)], { type: 'application/json' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'hemis-write-log-' + new Date().toISOString().replace(/[:.]/g, '-') + '.json';
        document.body.appendChild(a); a.click(); a.remove();
    };
})();
