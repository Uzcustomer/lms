const fs = require('node:fs');
const assert = require('node:assert/strict');
const path = require('node:path');
// Sikl amallari (to'qnashuv oynasi va panel kartasi) — haqiqiy brauzer DOM'ida (Playwright, Chromium).
const { chromium } = require('playwright');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');
const cut = (from, to) => {
    const a = source.indexOf(from), b = source.indexOf(to, a);
    assert(a > 0 && b > a, from);
    return source.slice(a, b);
};
const css = cut('<style>', '</style>').slice('<style>'.length);
const code = cut("            // ===== To'qnashuvlar: qisqa belgi, rang", '            // Ichki jadval: kun ustunini tanlash, kun oynasi va soatni surish.')
    + cut('            function openCfPop(el) {', "            // Panel (sikl va haftalik kartalar) to'qnashuv belgisi")
    + cut("            // \"Vaqtincha kafedra mudirida\" bo'limi", '            function renderCycleCards(j) {')
    // To'qnashuv bloklari va [Ko'rsatish] (haftalik oyna bilan umumiy).
    + cut("            // ===== To'qnashuv sababi: turlarga ajratilgan bloklar", '            function panelPopOutside(ev) {');

const KEY = 'davolash ishi|5|d2/d22-09b|Epidemiologiya|practice';
const stubs = (role, advice) => `
    window.calls = [];
    const esc = s => String(s == null ? '' : s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const BASE = '/admin/timetable', board = { id: 7 };
    const TIMETABLE_READ_ONLY = false, TIMETABLE_ROLE = ${JSON.stringify(role)};
    const WEEKLY_ADMIN = ['admin', 'superadmin', 'registrator_ofisi', 'kichik_admin'].includes(TIMETABLE_ROLE);
    let cycleDragKey = null, cycDaySel = null, cycleHolidays = [], cfBlocks = {}, cfOwners = {};
    const $ = id => document.getElementById(id);
    const cycleEffView = () => 'group', scopeBody = () => ({}), highlightCycleRow = () => {};
    const cycleCardStateText = c => c.placed ? 'joylangan' : 'joylanmagan';
    const ttToast = (h) => calls.push(['toast', h]);
    const ttAgendaOpen = i => calls.push(['agenda', i]);
    const curWeek = 0, boardDayNames = () => ['Dushanba', 'Seshanba', 'Chorshanba', 'Payshanba', 'Juma', 'Shanba'];
    const openCycleAssign = k => calls.push(['assign', k]);
    const placeCycleCardAt = (c, i, p) => calls.push(['place', c.key, i, p]);
    const loadCyclePlan = async () => calls.push(['reload']);
    function closeCycDayPop() { const p = document.getElementById('cycDayPop'); if (p) p.remove(); cycPopUnwatch(); cycDaySel = null; }
    const ADVICE = ${JSON.stringify(advice)};
    async function api(url, method, body) {
        calls.push([url.replace(BASE + '/boards/7/', ''), body]);
        if (url.endsWith('cycle-shift-advice')) return { advice: ADVICE };
        // O'qituvchining 03.11 kuni: ertalab 09a (to'qnashgan) va 09b (shu birlik); tushdan keyin — 09b boshqa fani va 09a boshqa darsi
        // (to'qnashuv vaqtidan tashqarida: "shu dars" ham, qizil ham emas).
        // Ma'ruzachi (32): 08:30 da o'z ma'ruzasi (dars 555) va boshqa ma'ruza (556) — bir vaqtda.
        if (url.includes('/teacher-agenda?teacher_id=32')) return { entries: [
            { from: 1, to: 3, start: '08:30', end: '09:50', groups: ['d2/d22-12a'], subject: 'Farmakologiya', type: 'M', room: 'Zal', card_ids: [90], key: 'l|555|2026-11-03|1' },
            { from: 1, to: 3, start: '08:30', end: '09:50', groups: ['d2/d22-13a'], subject: 'Gistologiya', type: 'M', room: 'Zal', card_ids: [91], key: 'l|556|2026-11-03|1' }] };
        if (url.includes('/teacher-agenda?')) return { entries: [
            { from: 1, to: 7, start: '08:30', end: '13:20', groups: ['d2/d22-09a'], subject: 'Epidemiologiya', type: 'A', room: '305', card_ids: [77], key: 'c|u9|2026-11-03' },
            { from: 1, to: 7, start: '08:30', end: '13:20', groups: ['d2/d22-09b'], subject: 'Epidemiologiya', type: 'A', room: '305', card_ids: [78],
              key: 'c|' + ${JSON.stringify(KEY)} + '|2026-11-03' },
            { from: 8, to: 10, start: '14:00', end: '15:20', groups: ['d2/d22-09b'], subject: 'Gigiyena', type: 'A', room: '306', card_ids: [79], key: 'c|u5|2026-11-03' },
            { from: 10, to: 12, start: '15:30', end: '16:50', groups: ['d2/d22-09a'], subject: 'Anatomiya', type: 'A', room: '307', card_ids: [80], key: 'c|u6|2026-11-03' }] };
        if (url.endsWith('cycle-assign-options')) return { current: { teacher_id: 31, auditorium_code: 'R305' } };
        if (url.endsWith('cycle-plan')) return { rows: [{ group: 'F1 / 1-oqim', subgroups: ['d2/d22-09b'], blocks: [{ subject: 'Epidemiologiya', from: 50 }] }] };
        return { ok: true };
    }
    let cyclePlanData = { layer: 'internal', dates: Object.assign([], { 50: { iso: '2026-11-03' } }), cycle_cards: [
        { key: ${JSON.stringify(KEY)}, group: 'd2/d22-09b', placement_group: 'd2/d22-09b', specialty: 'davolash ishi', course: 5, subject: 'Epidemiologiya',
          type: 'practice', placed: true, start_index: 50, conflict: "O'qituvchi band", teacher: { id: 31, name: "TOSHPO'LATOV B. A." },
          room: { code: 'R305', name: '305' }, mudir_ph: null,
          conflicts: [{ kind: 'teacher_busy', type: 'teacher', who: "TOSHPO'LATOV B. A.", teacher_id: 31, groups: ['d2/d22-09a'], date_from: '2026-11-03',
            date_to: '2026-11-07', past: false, lecture: false, todo: "boshqa o'qituvchi biriktiring yoki smenani almashtiring" }] },
        { key: 'u2', group: 'd2/d22-10a', placement_group: 'F1 / 1-oqim', specialty: 'davolash ishi', course: 5, subject: 'Epidemiologiya',
          type: 'practice', placed: false, start_index: null, conflict: null, conflicts: [], teacher: null, room: null },
        { key: 'u3', group: 'd2/d22-11a', placement_group: 'x', specialty: 'davolash ishi', course: 5, subject: 'Epidemiologiya', type: 'practice',
          placed: true, start_index: 50, conflict: null, conflicts: [], teacher: { id: 31, name: 'T' }, room: { code: 'R305', name: '305' },
          mudir_ph: { dates: ['2026-11-03', '2026-11-04'], mudir_name: 'XOLMATOV AZIZ', room_name: 'Kafedra mudiri xonasi', orig_teacher_name: "TOSHPO'LATOV B. A.",
            reason: '428 band', by_name: 'ADMIN', at: '2026-11-02 10:00:00' } },
    ] };
`;

async function page(browser, role, advice, anchorTop) {
    const p = await (await browser.newContext({ viewport: { width: 1200, height: 800 } })).newPage();
    await p.setContent('<!doctype html><html><head><style>' + css + '</style></head><body><div id="cardPanel"></div><input id="cycleStart" value="2026-09-14"><div id="cycleMsg"></div>' +
        '<button id="anchor" style="position:fixed;left:40px;top:' + anchorTop + 'px;height:20px">x</button></body></html>');
    await p.addScriptTag({ content: stubs(role, advice) + code + '\nwindow.T = { openCfPop, openCycCardPop, cycPhSectionHtml, cycPopPlace, get calls() { return calls; } };' });
    return p;
}
const settle = p => p.evaluate(() => new Promise(r => setTimeout(r, 30)));

(async () => {
    const browser = await chromium.launch();
    try {
        const partial = { kind: 'partial', start: 7, label: '13:30', text: "13:30 ga o'tkazilsa faqat 03.11, 04.11 qoladi: pp/2024-01a, Farmakologiya",
            remaining: [{ date: '2026-11-03', with: ['pp/2024-01a, Farmakologiya'] }, { date: '2026-11-04', with: ['pp/2024-01b, Farmakologiya'] }] };

        // 1) To'qnashuv oynasi (admin): "Bo'sh smenaga o'tkazish" — qisman: tasdiqda faqat 03.11, 04.11; o'tkazishda joriy o'qituvchi
        //    va xona saqlanadi (cycle-assign-options → cycle-assign: shift_start 7, force); natijada qolgan sanalar.
        let p = await page(browser, 'admin', partial, 100);
        await p.evaluate(k => { const a = document.getElementById('anchor'); a.dataset.cfKey = k; T.openCfPop(a); }, KEY);
        await settle(p);
        const btn = p.locator('#cycDayPop [data-cyc-shift]');
        assert.equal(await btn.isDisabled(), false);
        assert.equal(await btn.textContent(), 'Bo‘sh smenaga o‘tkazish — 13:30');
        assert.equal(await p.locator('#cycDayPop [data-cyc-teacher]').count(), 1);
        assert.equal(await p.locator('#cycDayPop [data-cyc-mudir]').count(), 1);
        await btn.click();
        assert.deepEqual(await p.locator('#cycDayPop [data-cyc-remaining] li').allTextContents(),
            ['03.11 — pp/2024-01a, Farmakologiya', '04.11 — pp/2024-01b, Farmakologiya']);
        await p.locator('#cycDayPop [data-cyc-confirm="shift"]').click();
        await settle(p);
        let calls = await p.evaluate(() => T.calls);
        const assign = calls.find(c => c[0] === 'cycle-assign');
        assert.equal(assign[1].shift_start, 7);
        assert.equal(assign[1].force, 1);
        assert.equal(assign[1].teacher_id, 31);
        assert.equal(assign[1].auditorium_code, 'R305');
        assert.equal(assign[1].group_name, 'd2/d22-09b');
        assert.equal(assign[1].view, 'group');
        assert.ok(calls.some(c => c[0] === 'reload'));
        assert.equal(await p.locator('#cycDayPop [data-cyc-msg]').textContent(), '✓ 13:30 ga o‘tkazildi; to‘qnashuv faqat 03.11, 04.11 da qoldi');

        // 2) "Vaqtincha kafedra mudiriga yozish": tanlov (faqat to'qnashuv sanalari / butun blok), sabab → server; oyna yopiladi, reja qayta.
        await p.evaluate(k => { document.getElementById('cycDayPop').remove(); const a = document.getElementById('anchor'); T.openCfPop(a); }, KEY);
        await settle(p);
        await p.locator('#cycDayPop [data-cyc-mudir]').click();
        assert.match(await p.locator('#cycDayPop [data-cyc-form]').textContent(), /Faqat to‘qnashuv sanalari \(03\.11–07\.11\)/);
        await p.locator('#cycDayPop input[value="all"]').check();
        await p.locator('#cycDayPop [data-cyc-reason]').fill("TOSHPO'LATOV band");
        await p.locator('#cycDayPop [data-cyc-mudir-save]').click();
        await settle(p);
        calls = await p.evaluate(() => T.calls);
        const ph = calls.find(c => c[0] === 'cycle-mudir-placeholder');
        assert.deepEqual([ph[1].key, ph[1].scope, ph[1].reason, ph[1].subject_name], [KEY, 'all', "TOSHPO'LATOV band", 'Epidemiologiya']);
        assert.equal(await p.locator('#cycDayPop').count(), 0);
        assert.ok(calls.filter(c => c[0] === 'reload').length >= 2);

        // 3) Panel: "Vaqtincha kafedra mudirida" bo'limi va "Asl holatga qaytarish".
        await p.evaluate(() => { document.getElementById('cardPanel').innerHTML = T.cycPhSectionHtml(cyclePlanData.cycle_cards); });
        assert.match(await p.locator('[data-cyc-ph-sec]').textContent(), /Vaqtincha kafedra mudirida — 1 ta.*d2\/d22-11a.*XOLMATOV AZIZ \(asl: TOSHPO'LATOV B\. A\.\); 03\.11, 04\.11; sabab: 428 band/);
        await p.evaluate(() => T.openCycCardPop('u3', document.getElementById('anchor')));
        assert.match(await p.locator('#cycDayPop [data-cyc-ph]').textContent(), /Vaqtincha kafedra mudirida:.*sanalar: 03\.11, 04\.11/);
        await p.locator('#cycDayPop [data-cyc-ph-revert]').click();
        await settle(p);
        calls = await p.evaluate(() => T.calls);
        assert.deepEqual(calls.find(c => c[0] === 'cycle-mudir-placeholder/revert')[1].key, 'u3');

        // 4) Joylanmagan karta: "Joylash uchun tanlash", tashqi jadvaldagi joyi (03.11 dan); o'qituvchi/xona havolasi — jadval.
        await p.evaluate(() => T.openCycCardPop('u2', document.getElementById('anchor')));
        await settle(p);
        assert.equal(await p.locator('#cycDayPop [data-cyc-ext]').textContent(), 'Tashqi jadvaldagi joyiga joylash (03.11 dan)');
        await p.locator('#cycDayPop [data-cyc-ext]').click();
        calls = await p.evaluate(() => T.calls);
        assert.deepEqual(calls.find(c => c[0] === 'place'), ['place', 'u2', 50, 1]);
        await p.evaluate(k => T.openCycCardPop(k, document.getElementById('anchor')), KEY);
        await p.locator('#cycDayPop [data-cyc-agenda="r"]').click();
        calls = await p.evaluate(() => T.calls);
        assert.deepEqual(calls.filter(c => c[0] === 'agenda').pop()[1], { room_code: 'R305', room_name: '305', date: '2026-11-03', range: 'week' });
        await p.close();

        // 5) O'qituvchi — amallar yo'q (faqat havolalar); fan mas'uli ham.
        for (const role of ['oqituvchi', 'fan_masuli']) {
            p = await page(browser, role, partial, 100);
            await p.evaluate(k => T.openCycCardPop(k, document.getElementById('anchor')), KEY);
            await settle(p);
            assert.equal(await p.locator('#cycDayPop [data-cyc-shift], #cycDayPop [data-cyc-mudir], #cycDayPop [data-cyc-teacher]').count(), 0, role);
            assert.equal(await p.locator('#cycDayPop [data-cyc-agenda="t"]').count(), 1);
            await p.close();
        }

        // 6) Joylashuv: pastda ochilsa — yuqoriga ochiladi va ekrandan chiqmaydi; juda baland oyna — ichida scroll.
        p = await page(browser, 'admin', partial, 760);
        await p.evaluate(k => { const a = document.getElementById('anchor'); a.dataset.cfKey = k; T.openCfPop(a); }, KEY);
        await settle(p);
        let box = await p.evaluate(() => { const r = document.getElementById('cycDayPop').getBoundingClientRect(); return { top: r.top, bottom: r.bottom }; });
        assert.ok(box.bottom <= 792 && box.bottom <= 760, 'oyna pastdan chiqdi yoki yuqoriga ochilmadi: ' + JSON.stringify(box));
        await p.locator('#cycDayPop [data-cyc-mudir]').click();   // kontent o'sdi — qayta joylanadi
        await settle(p);
        box = await p.evaluate(() => { const r = document.getElementById('cycDayPop').getBoundingClientRect(); return { top: r.top, bottom: r.bottom }; });
        assert.ok(box.top >= 8 && box.bottom <= 792, JSON.stringify(box));
        await p.evaluate(() => { const f = document.querySelector('#cycDayPop [data-cyc-form]'); f.insertAdjacentHTML('beforeend', '<div style="height:2000px">x</div>'); });
        await settle(p);
        box = await p.evaluate(() => { const e = document.getElementById('cycDayPop'); const r = e.getBoundingClientRect(); return { top: r.top, bottom: r.bottom, sh: e.scrollHeight, ch: e.clientHeight }; });
        assert.ok(box.top >= 8 && box.bottom <= 792 && box.sh > box.ch, 'max-height / scroll: ' + JSON.stringify(box));
        await p.close();
        // 7) Sikl to'qnashuv oynasi (panel belgisi / jadval bloki): turlarga ajratilgan blok va [Ko'rsatish] — o'qituvchining to'qnashuv
        //    sanasidagi (03.11) kunlik ro'yxati: to'qnashgan guruhcha (09a) qizil, shu dars (09b) belgilangan; haftalik jadval havolasi.
        p = await page(browser, 'admin', partial, 40);
        await p.evaluate(k => { const a = document.getElementById('anchor'); a.dataset.cfKey = k; T.openCfPop(a); }, KEY);
        await settle(p);
        assert.equal(await p.locator('#cycDayPop [data-cb-title]').textContent(), "To'qnashuv — sabab: o'qituvchi band");
        assert.equal(await p.locator('#cycDayPop [data-cb-head]').textContent(), "O'qituvchi band: TOSHPO'LATOV B. A.");
        await p.locator('#cycDayPop [data-cyc-cb-show]').click();
        await p.waitForSelector('#cycDayPop [data-cyc-cb-detail] [data-cb-row]');
        const agendaCall = (await p.evaluate(() => T.calls)).find(c => /teacher-agenda/.test(c[0]));
        assert.match(agendaCall[0], /teacher-agenda\?teacher_id=31&date=2026-11-03&range=day&layer=internal/);
        assert.equal(await p.locator('#cycDayPop [data-cyc-cb-detail] [data-cb-detail-head]').textContent(), "TOSHPO'LATOV B. A. · Seshanba, 03.11");
        assert.deepEqual(await p.$$eval('#cycDayPop [data-cyc-cb-detail] [data-cb-hit]', xs => xs.map(x => x.textContent)), ['08:30–13:20 · d2/d22-09a · Epidemiologiya (amaliy) · 305']);
        assert.deepEqual(await p.$$eval('#cycDayPop [data-cyc-cb-detail] [data-cb-mine]', xs => xs.map(x => x.textContent)),
            ['08:30–13:20 · d2/d22-09b · Epidemiologiya (amaliy) · 305 — shu dars']);
        assert.equal(await p.locator('#cycDayPop [data-cyc-cb-detail] [data-cb-row]').count(), 4);
        assert.equal(await p.locator('#cycDayPop [data-cyc-cb-detail] [data-cb-agenda]').textContent(), "O'qituvchining haftalik jadvali");
        // Smena maslahati — o'z qatorida (bloklarda ham).
        assert.match(await p.locator('#cycDayPop .cf-list > li[data-cf-i="0"] .cf-todo').textContent(), /13:30 ga o'tkazilsa/);
        await p.close();

        // 7b) Codex: sikl ma'ruzasi to'qnashuvi — kun ro'yxatida o'z ma'ruzasi (kaliti "l|555|…", yozuvning lecture_id si) "shu dars",
        //     qizil emas (karta raqami ikkala tomon show.card_ids ichida bo'lsa ham); qizil — faqat boshqa ma'ruza.
        p = await page(browser, 'admin', partial, 40);
        await p.evaluate(() => cyclePlanData.cycle_cards.push({ key: 'u4', group: 'd2/d22-12a', placement_group: 'd2/d22-12a', specialty: 'davolash ishi', course: 5,
            subject: 'Farmakologiya', type: 'lecture', placed: true, start_index: 50, conflict: "O'qituvchi band", teacher: { id: 32, name: "MA'RUZACHI" },
            room: { code: 'Z1', name: 'Zal' }, mudir_ph: null,
            conflicts: [{ kind: 'teacher_busy', type: 'teacher', who: "MA'RUZACHI", teacher_id: 32, groups: ['d2/d22-13a'], date_from: '2026-11-03',
                date_to: '2026-11-03', past: false, lecture: true, lecture_id: 555, todo: "ma'ruza o'qituvchisini yoki vaqtini o'zgartiring",
                show: { kind: 'teacher_busy', card_ids: [90, 91], hour_from: 1, hour_to: 3, hour_unit: 'hour', dates: ['2026-11-03', '2026-11-03'] } }] }));
        await p.evaluate(() => { cfOwners[555] = 'u4'; const a = document.getElementById('anchor'); a.dataset.cfKey = 'u4'; T.openCfPop(a); });
        await p.locator('#cycDayPop [data-cyc-cb-show]').click();
        await p.waitForSelector('#cycDayPop [data-cyc-cb-detail] [data-cb-row]');
        assert.deepEqual(await p.$$eval('#cycDayPop [data-cyc-cb-detail] [data-cb-mine]', xs => xs.map(x => x.textContent)),
            ["08:30–09:50 · d2/d22-12a · Farmakologiya (ma'ruza) · Zal — shu dars"]);
        assert.deepEqual(await p.$$eval('#cycDayPop [data-cyc-cb-detail] [data-cb-hit]', xs => xs.map(x => x.textContent)),
            ["08:30–09:50 · d2/d22-13a · Gistologiya (ma'ruza) · Zal"]);
        await p.close();
        // 7c) Sikl "Xona band" — blokda "Boshqa xona tanlash": sikl biriktirish oynasi (xona tanlovi) ochiladi.
        p = await page(browser, 'admin', partial, 40);
        await p.evaluate(() => cyclePlanData.cycle_cards.push({ key: 'u5', group: 'd2/d22-14a', placement_group: 'd2/d22-14a', specialty: 'davolash ishi', course: 5,
            subject: 'Gigiyena', type: 'practice', placed: true, start_index: 50, conflict: 'Xona band', teacher: { id: 33, name: 'T' }, room: { code: 'R305', name: '305' },
            mudir_ph: null, conflicts: [{ kind: 'room_busy', type: 'room', who: '305', room: 'R305', groups: ['d2/d22-15a'], date_from: '2026-11-03', date_to: '2026-11-03',
                past: false, lecture: false, todo: 'boshqa xona tanlang yoki smenani almashtiring' }] }));
        await p.evaluate(() => { const a = document.getElementById('anchor'); a.dataset.cfKey = 'u5'; T.openCfPop(a); });
        assert.equal(await p.locator('#cycDayPop [data-cb-block="room"] [data-cyc-room-pick]').textContent(), 'Boshqa xona tanlash');
        await p.locator('#cycDayPop [data-cyc-room-pick]').click();
        assert.ok((await p.evaluate(() => T.calls)).some(c => c[0] === 'assign' && c[1] === 'u5'));
        await p.close();
        console.log('timetable cycle actions: 9 scenarios passed');
    } finally {
        await browser.close();
    }
})().catch(e => { console.error(e); process.exit(1); });
