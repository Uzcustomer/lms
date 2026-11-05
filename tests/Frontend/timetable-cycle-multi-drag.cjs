const fs = require('node:fs');
const assert = require('node:assert/strict');
const path = require('node:path');
// Sikl jadvali: bir nechta kunni belgilab sudrash — Excel kabi tanlov (bo'lak — kunning M yoki A si; Ctrl — bo'lak, Shift — to'rtburchak,
// Ctrl+Shift — qo'shib), ikki marta bosish, yorliq (rejim bilan), Esc, rejim filtri; aralash tanlov — bitta so'rov (day_modes); tanlangan kunlarni sudrash
// (oldindan ko'rinish, server tekshiruvi), muammosiz — darhol + "Qaytarish", to'qnashuv — oyna ("Qo'llash" / "Baribir hammasiga"), Ctrl+Z,
// ↑ ↓; o'qituvchi — so'rov oynasi tanlangan kunlar bilan; bitta kunni sudrash: birga — moveCycDay, faqat ma'ruza / amaliy — tekshiruv
// bilan (cycDaysApply, bitta kun).
const { chromium } = require('playwright');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');
const cut = (from, to) => {
    const a = source.indexOf(from), b = source.indexOf(to, a);
    assert(a > 0 && b > a, from);
    return source.slice(a, b);
};
const css = cut('<style>', '</style>').slice('<style>'.length);
const code = cut('            // Ichki jadval: kun ustunini tanlash, kun oynasi va soatni surish.', '            function openCycDayPop(key, offset, anchor, keepMulti = false) {')
    + cut('            let cycCellDragged = false;', '            function setupCycleGridEvents() {');

// Blok: 16.11 (kalendar 10) dan 6 kun; har kuni ma'ruza 1–2-soat, amaliy 7–10-soat. Bugun — 05.11 (hammasi kelgusi).
const stubs = (role, dayEdit) => `
    const TIMETABLE_ROLE = ${JSON.stringify(role)};
    const TIMETABLE_READ_ONLY = ${JSON.stringify(['oqituvchi', 'fan_masuli'].includes(role))};
    const esc = s => String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;');
    const $ = id => document.getElementById(id);
    const BASE = '/admin/timetable', board = { id: 7 };
    let viewMode = 'cycle', cycTitleDragged = false, cycleRowFilters = [];
    const DAY = k => ({ k: k, lec: 2, prc: 4, start: 1, ps: 7, ktr: true });
    const BLOCK = { key: 'K', subject: 'Ichki kasalliklar', from: 10, day_plan: { 0: DAY(1), 1: DAY(2), 2: DAY(3), 3: DAY(4), 4: DAY(5), 5: DAY(6) } };
    const cyclePlanData = { day_edit: ${JSON.stringify(dayEdit)}, day_hours: 12, rows: [{ blocks: [BLOCK] }],
        cycle_cards: [{ key: 'K', specialty: 'davolash ishi', course: 4, group: 'F1 / 1-oqim', subject: 'Ichki kasalliklar', type: 'practice',
            placement_group: 'F1 / 1-oqim' }],
        dates: Object.assign([], { 10: { iso: '2026-11-16', d: '16.11' }, 11: { iso: '2026-11-17', d: '17.11' }, 12: { iso: '2026-11-18', d: '18.11' },
            13: { iso: '2026-11-19', d: '19.11' }, 14: { iso: '2026-11-20', d: '20.11' }, 15: { iso: '2026-11-21', d: '21.11' } }) };
    const cycleEffView = () => 'group';
    window.TODAY = '2026-11-05';
    const cycLocalToday = () => window.TODAY;
    window.T12 = ['08:30', '09:10', '10:00', '10:40', '12:00', '12:40', '13:30', '14:10', '15:00', '15:40', '16:30', '17:10', '17:50'];
    const cycHourTimes = () => [null].concat(window.T12.slice(0, 12).map((t, i) => [t, window.T12[i + 1]]));
    const crDow = () => 'du';
    const cycPopUnwatch = () => {}, openCfPop = () => {}, cycleRowFiltersApply = () => {}, openCycSubjPop = () => {};
    window.calls = [];
    window.toasts = [];
    window.reloads = 0;
    window.dryDays = null;   // dry_run javobi (test o'rnatadi)
    function ttToast(html, tone) { window.toasts.push({ html, tone }); }
    function onPendingApproval(j) { window.toasts.push({ html: 'pending', tone: 'warn' }); }
    async function loadCyclePlan() { window.reloads++; }
    function openCycDayPop(key, offset) { window.calls.push({ pop: [key, +offset] }); cycDaySel = { key: key, offset: +offset }; }
    function moveCycDay(...args) { window.calls.push({ move: args }); }
    function openCycleTimeProposal(card, dp, date, opts) { window.calls.push({ proposal: { kind: opts.kind, preselect: opts.preselect, start: opts.start, date: date.iso } }); }
    const cycCanAsk = (card, dp, date, today, kind) => date.iso > today;
    async function api(url, method, body) {
        body = JSON.parse(JSON.stringify(body));
        window.calls.push({ api: body });
        if (body.undo) return { ok: true, undone: true };
        const days = (body.day_indexes || []).map(k => {
            const preset = window.dryDays && window.dryDays[k];
            return Object.assign({ day_index: k, date: '2026-11-' + (16 + k), status: 'ok', why: null, from: 1, to: 1 + body.delta,
                applied: !body.dry_run }, preset || {}, preset && preset.status === 'conflict' ? { applied: !body.dry_run && Boolean(body.force) } : {},
                preset && ['nofit', 'past'].includes(preset.status) ? { applied: false } : {});
        });
        return { ok: true, dry_run: Boolean(body.dry_run), days: days, applied: days.filter(d => d.applied).length, undo: body.dry_run ? null : 'TOKEN' };
    }
`;
// Panjara: 6 kun × 12 soat; M — 1–2-soat, A — 7–10-soat; sarlavha (fan nomi).
const gridHtml = () => {
    // Haqiqiydagidek: jadval tepasida sana sarlavhasi, so'ng blok sarlavhasi (fan nomi) va kataklar.
    let h = '<div id="cycleGridWrap"><div class="dates" style="height:28px">16.11 … 21.11</div>'
        + '<div id="cycleGrid" style="display:grid;grid-template-columns:repeat(6,40px);grid-auto-rows:24px">'
        + '<div class="cyc-mk-title" data-mk-subj="K" role="button" tabindex="0" style="grid-column:1 / -1">Ichki kasalliklar</div>';
    for (let hr = 1; hr <= 12; hr++) {
        for (let d = 0; d < 6; d++) {
            const kind = hr <= 2 ? ' is-lecture' : (hr >= 7 && hr <= 10 ? ' is-practice' : '');
            h += '<div class="cyc-mk-cell' + kind + '" data-mk-key="K" data-mk-day="' + d + '" data-mk-hour="' + hr + '" style="position:relative;border:1px solid #ddd">'
                + (kind ? (kind.includes('lecture') ? 'M' : 'A') : '') + '</div>';
        }
    }
    return h + '</div></div>';
};
const at = (p, day, hour) => p.evaluate(([d, h]) => {
    const r = document.querySelector('.cyc-mk-cell[data-mk-day="' + d + '"][data-mk-hour="' + h + '"]').getBoundingClientRect();
    return { x: r.left + r.width / 2, y: r.top + r.height / 2 };
}, [day, hour]);
const selected = p => p.evaluate(() => [...new Set([...document.querySelectorAll('.cyc-mk-cell.is-multi-sel')].map(c => +c.dataset.mkDay))].sort());
const cls = (p, day, hour) => p.evaluate(([d, h]) => document.querySelector('.cyc-mk-cell[data-mk-day="' + d + '"][data-mk-hour="' + h + '"]').className, [day, hour]);
const click = async (p, day, hour, mods = []) => {
    const c = await at(p, day, hour);
    for (const m of mods) await p.keyboard.down(m);
    await p.mouse.click(c.x, c.y);
    for (const m of mods) await p.keyboard.up(m);
};
const drag = async (p, from, to, { hold = 400, mods = [] } = {}) => {
    const a = await at(p, ...from), b = await at(p, ...to);
    for (const m of mods) await p.keyboard.down(m);
    await p.mouse.move(a.x, a.y);
    await p.mouse.down();
    await p.mouse.move(a.x, a.y + 8, { steps: 2 });
    await p.mouse.move(b.x, b.y, { steps: 4 });
    await p.waitForTimeout(hold);   // server tekshiruvi (250 ms kechikish)
    const preview = await p.evaluate(() => ({ tip: (document.getElementById('cycDragTip') || {}).textContent || '' }));
    await p.mouse.up();
    for (const m of mods) await p.keyboard.up(m);
    await p.waitForTimeout(80);
    return preview;
};
const apiCalls = p => p.evaluate(() => window.calls.filter(c => c.api).map(c => c.api));
const reset = p => p.evaluate(() => { window.calls = []; window.toasts = []; window.dryDays = null; });

(async () => {
    const browser = await chromium.launch();
    try {
        const page = async (role, dayEdit) => {
            const p = await (await browser.newContext({ viewport: { width: 1000, height: 700 } })).newPage();
            await p.setContent('<!doctype html><html><head><style>' + css + '</style></head><body style="margin:40px">' + gridHtml() + '</body></html>');
            await p.addScriptTag({ content: stubs(role, dayEdit) + code + '\nsetupCycleMarking();\nwindow.T = { get sel() { return cycMultiSel; } };' });
            await p.evaluate(() => { document.getElementById('cycleGrid').dataset.mkReady = ''; });
            return p;
        };

        // 1) Tanlov: Ctrl+bosish (M katagi) — 4 kun ma'ruzasi; yorliqda rejim; Shift — to'rtburchak; sarlavhada ikki marta bosish — hammasi;
        //    Esc — bekor.
        const p = await page('admin', true);
        for (const d of [0, 1, 2, 3]) await click(p, d, 1, ['Control']);
        assert.deepEqual(await selected(p), [0, 1, 2, 3]);
        assert.match(await p.textContent('#cycSelBadge'), /Tanlangan: 4 kun · Faqat ma'ruza · Esc — bekor/);
        assert.match(await cls(p, 2, 2), /is-multi-sel/);          // ma'ruzaning har ikki soati (bo'lak butunligicha)
        assert.doesNotMatch(await cls(p, 2, 5), /is-multi-sel/);   // bo'sh soat — yo'q
        assert.doesNotMatch(await cls(p, 2, 7), /is-multi-sel/);   // amaliy — tanlanmagan
        await click(p, 1, 1, ['Control']);                   // qayta Ctrl — olinadi
        assert.deepEqual(await selected(p), [0, 2, 3]);
        await p.keyboard.press('Escape');
        assert.deepEqual(await selected(p), []);
        assert.equal(await p.$('#cycSelBadge'), null);
        await click(p, 1, 1, ['Control']);
        await click(p, 4, 1, ['Shift']);
        assert.deepEqual(await selected(p), [1, 2, 3, 4]);
        await p.dblclick('.cyc-mk-title[data-mk-subj="K"]');
        assert.deepEqual(await selected(p), [0, 1, 2, 3, 4, 5]);
        await p.click('#cycSelBadge [data-sel-act="clear"]');
        assert.deepEqual(await selected(p), []);
        // Oddiy bosish — kun oynasi (tanlovsiz).
        await reset(p);
        await click(p, 2, 1);
        assert.deepEqual((await p.evaluate(() => window.calls)).map(c => c.pop), [['K', 2]]);
        await p.keyboard.press('Escape');

        // 2) Ctrl bilan 4 kun, M katakni 2 soat pastga: oldindan ko'rinish — 4 kunda yangi o'rin; muammo yo'q — darhol, xabar + "Qaytarish".
        for (const d of [0, 1, 2, 3]) await click(p, d, 1, ['Control']);
        await reset(p);
        const pre = await drag(p, [1, 1], [1, 3]);
        assert.match(pre.tip, /^\+2 soat · 4 kun \(ma'ruza\)/);
        let calls = await apiCalls(p);
        assert.ok(calls.some(c => c.dry_run === 1), 'sudrash paytida server tekshiruvi');
        const commit = calls.filter(c => !c.dry_run);
        assert.equal(commit.length, 1);
        assert.deepEqual(commit[0], { specialty_name: 'davolash ishi', course: 4, group_name: 'F1 / 1-oqim', subject_name: 'Ichki kasalliklar',
            training_type: 'practice', view: 'group', placement_group: 'F1 / 1-oqim', day_indexes: [0, 1, 2, 3], mode: 'lecture', delta: 2,
            practice_place: 'swap' });
        let toasts = await p.evaluate(() => window.toasts);
        assert.match(toasts[0].html, /^4 kun: ma'ruza 08:30 → 10:00 <button type="button" class="tt-toast-btn" data-cyc-undo>Qaytarish \(Ctrl\+Z\)<\/button>/);
        assert.equal(await p.evaluate(() => window.reloads), 1);
        assert.deepEqual(await selected(p), [0, 1, 2, 3]);   // tanlov qoladi

        // 3) Ctrl+Z — oxirgi surish: bitta so'rov (undo kaliti), qayta — yo'q.
        await reset(p);
        await p.keyboard.press('Control+z');
        await p.waitForTimeout(50);
        calls = await apiCalls(p);
        assert.equal(calls.length, 1);
        assert.equal(calls[0].undo, 'TOKEN');
        assert.equal(calls[0].day_indexes, undefined);
        assert.match((await p.evaluate(() => window.toasts))[0].html, /qaytarildi/);

        // 4) Sudrash paytida: to'qnashuvli kun — qizil, ko'rsatkichda "1 ta to'qnashuv"; qo'yib yuborilganda — oyna; "Qo'llash" — qolganlariga.
        await reset(p);
        await p.evaluate(() => { window.dryDays = { 2: { status: 'conflict', why: 'Guruh band: d2/d23-04a, Fizika (18.11, 10:00–11:20)' } }; });
        const a = await at(p, 1, 1), b = await at(p, 1, 3);
        await p.mouse.move(a.x, a.y); await p.mouse.down(); await p.mouse.move(a.x, a.y + 8, { steps: 2 }); await p.mouse.move(b.x, b.y, { steps: 4 });
        await p.waitForTimeout(400);
        assert.match(await p.textContent('#cycDragTip'), /\+2 soat · 4 kun · 1 ta to'qnashuv/);
        assert.match(await cls(p, 2, 3), /is-drag-bad/);
        assert.match(await cls(p, 0, 3), /is-drag-ok/);
        assert.doesNotMatch(await cls(p, 4, 3), /is-drag/);   // tanlanmagan kun o'zgarmaydi
        await p.mouse.up();
        await p.waitForSelector('#cycMultiDlg');
        const dlg = await p.textContent('#cycMultiDlg');
        assert.match(dlg, /18\.11 \(3-kun\) 08:30 → 10:00 — to'qnashuv: Guruh band: d2\/d23-04a, Fizika/);
        assert.match(dlg, /Qo'llash \(3\)/);
        assert.match(dlg, /Baribir hammasiga \(4\)/);
        await p.click('#cycMultiDlg [data-cmd="apply"]');
        await p.waitForTimeout(50);
        calls = (await apiCalls(p)).filter(c => !c.dry_run);
        assert.deepEqual(calls.map(c => [c.day_indexes, c.force]), [[[0, 1, 3], undefined]]);
        assert.equal(await p.$('#cycMultiDlg'), null);
        // "Baribir hammasiga" — force bilan to'qnashuvlisi ham.
        await reset(p);
        await p.evaluate(() => { window.dryDays = { 2: { status: 'conflict', why: 'Guruh band' } }; });
        await drag(p, [1, 1], [1, 3]);
        await p.click('#cycMultiDlg [data-cmd="force"]');
        await p.waitForTimeout(50);
        calls = (await apiCalls(p)).filter(c => !c.dry_run);
        assert.deepEqual(calls.map(c => [c.day_indexes, c.force]), [[[0, 1, 2, 3], 1]]);

        // 5) Kunga sig'maydi: oldindan ko'rinishda kulrang + "sig'maydi"; server ham — oynada, "Qo'llash" o'chiq.
        await reset(p);
        await p.evaluate(() => { window.dryDays = { 0: { status: 'nofit', why: 'Kunga sig‘maydi' }, 1: { status: 'nofit', why: 'Kunga sig‘maydi' },
            2: { status: 'nofit', why: 'Kunga sig‘maydi' }, 3: { status: 'nofit', why: 'Kunga sig‘maydi' } }; });
        const c1 = await at(p, 1, 1), c2 = await at(p, 1, 12);
        await p.mouse.move(c1.x, c1.y); await p.mouse.down(); await p.mouse.move(c1.x, c1.y + 8, { steps: 2 }); await p.mouse.move(c2.x, c2.y, { steps: 6 });
        await p.waitForTimeout(100);
        assert.match(await p.textContent('#cycDragTip'), /4 ta sig'maydi/);
        assert.match(await cls(p, 0, 1), /is-drag-nofit/);
        assert.equal(await p.getAttribute('.cyc-mk-cell[data-mk-day="0"][data-mk-hour="1"]', 'data-drag-note'), "sig'maydi");
        await p.mouse.up();
        await p.waitForSelector('#cycMultiDlg');
        assert.equal(await p.getAttribute('#cycMultiDlg [data-cmd="apply"]', 'disabled'), '');
        await p.click('#cycMultiDlg [data-cmd="cancel"]');
        assert.equal((await apiCalls(p)).filter(c => !c.dry_run).length, 0);

        // 5a) Tanlangan bo'lakni sudrash — tanlovning o'z rejimi: Shift (birga tugmasi) hisobga olinmaydi, faqat ma'ruza.
        await reset(p);
        const pre5 = await drag(p, [1, 1], [1, 2], { hold: 50, mods: ['Shift'] });
        assert.doesNotMatch(pre5.tip, /Shift — birga/);
        assert.deepEqual((await apiCalls(p)).filter(c => !c.dry_run).map(c => [c.day_indexes, c.delta, c.mode]), [[[0, 1, 2, 3], 1, 'lecture']]);
        // 5b) "Birga" +3 (Ctrl bilan amaliyotlari ham qo'shildi — tanlov M+A): mijoz taxmini — amaliy (7–10) kun oxiridan chiqadi, server — ok
        //     (smenasi qotirilgan amaliy surilmaydi): server natijasi ustun — "sig'maydi" emas.
        for (const d of [0, 1, 2, 3]) await click(p, d, 7, ['Control']);
        assert.match(await p.textContent('#cycSelBadge'), /Tanlangan: 4 kun · Birga/);
        await reset(p);
        const g1 = await at(p, 1, 1), g2 = await at(p, 1, 4);
        await p.mouse.move(g1.x, g1.y); await p.mouse.down(); await p.mouse.move(g1.x, g1.y + 8, { steps: 2 }); await p.mouse.move(g2.x, g2.y, { steps: 4 });
        await p.waitForTimeout(400);
        assert.doesNotMatch(await p.textContent('#cycDragTip'), /sig'maydi/);
        assert.match(await cls(p, 0, 4), /is-drag-ok/);
        assert.doesNotMatch(await cls(p, 0, 1), /is-drag-nofit/);
        await p.mouse.up();
        await p.waitForTimeout(80);
        assert.deepEqual((await apiCalls(p)).filter(c => !c.dry_run).map(c => [c.day_indexes, c.delta, c.mode]), [[[0, 1, 2, 3], 3, 'both']]);

        // 6) Klaviatura ↓ / ↑ — tanlangan kunlar bir qadam (step=jump), har kun o'z bo'laklari bilan (bu yerda M+A — "birga").
        await reset(p);
        await p.keyboard.press('ArrowDown');
        await p.waitForTimeout(60);
        calls = await apiCalls(p);
        assert.deepEqual(calls.map(c => [c.dry_run, c.day_indexes, c.delta, c.mode, c.step]),
            [[1, [0, 1, 2, 3], 1, 'both', 'jump'], [undefined, [0, 1, 2, 3], 1, 'both', 'jump']]);
        await reset(p);
        await p.keyboard.press('ArrowUp');
        await p.waitForTimeout(60);
        assert.deepEqual((await apiCalls(p)).map(c => c.delta), [-1, -1]);
        // Sichqoncha boshqa tanlangan kun ustunida — siljish soat qatori bo'yicha (17.11 dan 19.11 ning 3-soatiga).
        await reset(p);
        await drag(p, [1, 1], [3, 3]);
        assert.deepEqual((await apiCalls(p)).filter(c => !c.dry_run).map(c => [c.day_indexes, c.delta]), [[[0, 1, 2, 3], 2]]);

        // 7) Bitta kunni sudrash (tanlovsiz): A katak — faqat amaliy, tekshiruv bilan (bitta kun, muammo yo'q — darhol); Shift bilan — birga
        // (avvalgidek moveCycDay).
        await p.keyboard.press('Escape');
        await reset(p);
        await drag(p, [4, 7], [4, 9], { hold: 50 });
        let all = await p.evaluate(() => window.calls);
        assert.deepEqual(all.filter(c => c.api).map(c => [c.api.dry_run, c.api.day_indexes, c.api.delta, c.api.mode, c.api.practice_place]),
            [[1, [4], 2, 'practice', undefined], [undefined, [4], 2, 'practice', undefined]]);
        assert.equal(all.filter(c => c.move).length, 0);
        await reset(p);
        await drag(p, [4, 7], [4, 9], { hold: 50, mods: ['Shift'] });
        all = await p.evaluate(() => window.calls);
        assert.equal(all.filter(c => c.api).length, 0);
        assert.deepEqual(all.filter(c => c.move).map(c => c.move.slice(0, 3).concat(c.move[4])), [['K', 4, 3, 'both']]);

        // 7b) O'tgan va bugungi kun: o'tgan — tanlanmaydi; bugungi — admin (dars boshlangan, hatto tugagan bo'lsa ham).
        await p.keyboard.press('Escape');
        await reset(p);
        await p.evaluate(() => { window.TODAY = '2026-11-17'; window.T12 = ['24:00'].concat(window.T12.slice(1)); });
        await click(p, 0, 1, ['Control']);
        assert.deepEqual(await selected(p), []);
        assert.match((await p.evaluate(() => window.toasts))[0].html, /tanlab bo'lmaydi: o'tgan kun/);
        await click(p, 1, 1, ['Control']);
        assert.deepEqual(await selected(p), [1]);   // bugun, 1-soat "24:00" — hali boshlanmagan
        await p.keyboard.press('Escape');
        await p.evaluate(() => { window.T12 = ['00:00'].concat(window.T12.slice(1)); });
        await click(p, 1, 1, ['Control']);
        assert.deepEqual(await selected(p), [1]);   // boshlangan — admin uchun baribir mumkin
        await p.keyboard.press('Escape');
        await p.evaluate(() => { window.TODAY = '2026-11-05'; window.T12 = ['08:30'].concat(window.T12.slice(1)); });

        // 7d) Excel kabi: oddiy bosish — boshlanish nuqtasi; Shift — to'rtburchak (kunlar × soatlar) tanlovni almashtiradi, tegib o'tgan bo'lak
        //     butunligicha (A 7–10 ning 8–9-soati — butun amaliy); Ctrl+Shift — qo'shadi (boshlanish nuqtasi joyida).
        await reset(p);
        await click(p, 0, 8);
        await click(p, 2, 9, ['Shift']);
        assert.deepEqual(await selected(p), [0, 1, 2]);
        assert.match(await cls(p, 1, 7), /is-multi-sel/);
        assert.match(await cls(p, 1, 10), /is-multi-sel/);
        assert.doesNotMatch(await cls(p, 1, 1), /is-multi-sel/);
        assert.match(await p.textContent('#cycSelBadge'), /Tanlangan: 3 kun · Faqat amaliy/);
        await click(p, 4, 1, ['Control', 'Shift']);   // 0..4 kun × 1..8 soat — M va A
        assert.deepEqual(await selected(p), [0, 1, 2, 3, 4]);
        assert.match(await cls(p, 4, 2), /is-multi-sel/);
        assert.match(await cls(p, 3, 9), /is-multi-sel/);
        assert.match(await p.textContent('#cycSelBadge'), /Tanlangan: 5 kun · Birga/);
        await click(p, 5, 5, ['Shift']);   // bo'sh soatlar (5-qator) — faqat to'rtburchakka tegib o'tgan bo'laklar: 0..5 kun 5..8 soat — A
        assert.deepEqual(await selected(p), [0, 1, 2, 3, 4, 5]);
        assert.doesNotMatch(await cls(p, 0, 1), /is-multi-sel/);
        assert.match(await p.textContent('#cycSelBadge'), /Tanlangan: 6 kun · Faqat amaliy/);
        // Bo'sh soatni Ctrl bilan — tanlanmaydi (xabar).
        await reset(p);
        await click(p, 3, 5, ['Control']);
        assert.match((await p.evaluate(() => window.toasts))[0].html, /Bo'sh soat tanlanmaydi/);
        // Rejim filtri (kun oynasidagi radio): "Faqat ma'ruza" — tanlangan kunlarda faqat M; ma'ruzasi yo'q kun tanlovda qoladi (uzuq ramka,
        // surilmaydi), "Birga" — yana tanlanadi.
        await p.keyboard.press('Escape');
        await p.evaluate(() => { BLOCK.day_plan[5] = { k: 6, lec: 0, prc: 4, start: 1, ps: 7, ktr: true }; });
        await click(p, 4, 7, ['Control']);
        await click(p, 5, 7, ['Control']);
        await p.evaluate(() => { cycMoveMode = 'lecture'; cycSelApplyMode('K', 'lecture'); });
        assert.deepEqual(await selected(p), [4]);
        assert.match(await cls(p, 5, 8), /is-multi-idle/);
        assert.match(await p.textContent('#cycSelBadge'), /Tanlangan: 2 kun · Faqat ma'ruza<\/b>|Tanlangan: 2 kun · Faqat ma'ruza · 1 kunda ma'ruza yo'q/);
        await reset(p);
        await p.keyboard.press('ArrowDown');
        await p.waitForTimeout(60);
        assert.deepEqual((await apiCalls(p)).map(c => [c.day_indexes, c.mode]), [[[4], 'lecture'], [[4], 'lecture']]);
        await p.evaluate(() => { cycMoveMode = 'both'; cycSelApplyMode('K', 'both'); });
        assert.deepEqual(await selected(p), [4, 5]);
        assert.doesNotMatch(await cls(p, 5, 8), /is-multi-idle/);
        await p.keyboard.press('Escape');
        await p.evaluate(() => { BLOCK.day_plan[5] = DAY(6); });
        // Aralash (Ctrl): 0-kun M, 1-kun A — yorliq "Aralash", ↓ — bitta so'rov, har kun o'z rejimi (day_modes).
        await click(p, 0, 1, ['Control']);
        await click(p, 1, 8, ['Control']);
        assert.match(await p.textContent('#cycSelBadge'), /Tanlangan: 2 kun · Aralash \(M 1, A 1\)/);
        await reset(p);
        await p.keyboard.press('ArrowDown');
        await p.waitForTimeout(60);
        assert.deepEqual((await apiCalls(p)).map(c => [c.dry_run, c.day_indexes, c.mode, c.day_modes, c.step, c.practice_place]),
            [[1, [0, 1], 'both', { 0: 'lecture', 1: 'practice' }, 'jump', 'swap'], [undefined, [0, 1], 'both', { 0: 'lecture', 1: 'practice' }, 'jump', 'swap']]);
        assert.match((await p.evaluate(() => window.toasts))[0].html, /^2 kun: darslar \(aralash\) 08:30 → 09:10/);
        await p.keyboard.press('Escape');

        // 7c) Kafedra mudiri (kun soatini sura oladi) — bugungi kun tanlanmaydi (faqat admin / registrator ofisi).
        const km = await page('kafedra_mudiri', true);
        await km.evaluate(() => { window.TODAY = '2026-11-17'; window.T12 = ['24:00'].concat(window.T12.slice(1)); });
        await click(km, 1, 1, ['Control']);
        assert.deepEqual(await selected(km), []);
        await click(km, 2, 1, ['Control']);
        assert.deepEqual(await selected(km), [2]);

        // 8) O'qituvchi: tanlangan kunlarni sudrash — so'rov oynasi (kunlar va yangi vaqt oldindan), to'g'ridan-to'g'ri o'zgartirish yo'q.
        const t = await page('oqituvchi', false);
        // Bugungi kun o'qituvchiga tanlanmaydi (dars boshlanmagan bo'lsa ham) — faqat admin / registrator.
        await t.evaluate(() => { window.TODAY = '2026-11-17'; window.T12 = ['24:00'].concat(window.T12.slice(1)); });
        await click(t, 1, 7, ['Control']);
        assert.deepEqual(await selected(t), []);
        await t.evaluate(() => { window.TODAY = '2026-11-05'; window.T12 = ['08:30'].concat(window.T12.slice(1)); window.toasts = []; });
        await click(t, 1, 7, ['Control']);
        await click(t, 2, 7, ['Control']);
        assert.deepEqual(await selected(t), [1, 2]);
        await drag(t, [1, 7], [1, 5], { hold: 50 });
        const tc = await t.evaluate(() => window.calls);
        assert.equal(tc.filter(c => c.api).length, 0);
        assert.deepEqual(tc.filter(c => c.proposal).map(c => c.proposal),
            [{ kind: 'practice', preselect: ['2026-11-17', '2026-11-18'], start: 5, date: '2026-11-17' }]);
        // Aralash tanlov (M va A) — so'rov bitta tur uchun: sudrab bo'lmaydi, xabar (so'rov oynasi ochilmaydi).
        await t.keyboard.press('Escape');
        await t.evaluate(() => { window.calls = []; window.toasts = []; });
        await click(t, 1, 1, ['Control']);
        await click(t, 2, 7, ['Control']);
        assert.match(await t.textContent('#cycSelBadge'), /Aralash/);
        await drag(t, [1, 1], [1, 3], { hold: 50 });
        assert.equal((await t.evaluate(() => window.calls)).filter(c => c.proposal || c.api).length, 0);
        assert.match((await t.evaluate(() => window.toasts)).map(x => x.html).join(' '), /ma'ruza va amaliy aralash/);
        // 9) So'rov oynasi (haqiqiy openCycleTimeProposal): sudrashdagi yangi vaqt (opts.start) va tanlangan kunlar oldindan belgilangan.
        const q = await (await browser.newContext({ viewport: { width: 1000, height: 700 } })).newPage();
        await q.setContent('<!doctype html><html><head><style>' + css + '</style></head><body></body></html>');
        await q.addScriptTag({ content: stubs('oqituvchi', false).replace('function openCycleTimeProposal', 'function _unusedProposal')
            + 'const lectureLabel = (a, u) => ({ text: String(u), title: "" }); async function loadChangeRequests() {}\n'
            + cut('            function openCycleTimeProposal(card, dp, date, opts = {}) {', '            async function moveCycDay(key, offset, newStart, hint, mode) {')
            + '\nwindow.open1 = (o) => openCycleTimeProposal(cyclePlanData.cycle_cards[0], { k: 2, lec: 0, prc: 4, start: 1, ps: 7 }, { iso: "2026-11-17", d: "17.11" }, o);' });
        const days = [{ iso: '2026-11-17', d: '17.11', k: 2, dow: 'se', time: '13:30–16:20', offset: 1 }, { iso: '2026-11-18', d: '18.11', k: 3, dow: 'ch', time: '13:30–16:20', offset: 2 }];
        await q.evaluate(d => window.open1({ kind: 'practice', days: d, preselect: ['2026-11-17', '2026-11-18'], start: 5 }), days);
        assert.equal(await q.$eval('#teacherTimeProposal select[name="start_hour"]', el => el.value), '5');
        assert.equal(await q.$eval('#teacherTimeProposal select[name="scope"]', el => el.value), 'multi');
        assert.deepEqual(await q.$$eval('#teacherTimeProposal [data-days] input:checked', els => els.map(e => e.value)), ['2026-11-17', '2026-11-18']);
        // Sudrashsiz (start yo'q) — hozirgi vaqt.
        await q.evaluate(d => window.open1({ kind: 'practice', days: d, preselect: ['2026-11-17'] }), days);
        assert.equal(await q.$eval('#teacherTimeProposal select[name="start_hour"]', el => el.value), '7');
        console.log('timetable cycle multi drag: 10 scenarios passed');
    } finally {
        await browser.close();
    }
})().catch(e => { console.error(e); process.exit(1); });
