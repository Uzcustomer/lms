const fs = require('node:fs');
const assert = require('node:assert/strict');
const path = require('node:path');
// Sikl paneli: "Vaqtincha kafedra mudirida — N ta" bo'limi hafta tanlanganda ham ko'rinadi (joylangan, to'qnashuvsiz kartalar
// panel ro'yxatiga kirmaydi, bo'lim esa alohida — belgi sanalari bo'yicha); sarlavhada "· vaqtincha kafedra mudirida: N ta",
// bosilsa panel ichida bo'limga suriladi. Haqiqiy brauzer DOM'ida (Playwright, Chromium).
const { chromium } = require('playwright');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');
const cut = (from, to) => {
    const a = source.indexOf(from), b = source.indexOf(to, a);
    assert(a > 0 && b > a, from);
    return source.slice(a, b);
};
const css = cut('<style>', '</style>').slice('<style>'.length);
const code = cut('            function isHemisExcluded(card)', '            function applyWeeklyLayer(list)')
    + cut("            // ===== To'qnashuvlar: qisqa belgi, rang", '            // Ichki jadval: kun ustunini tanlash, kun oynasi va soatni surish.')
    + cut('            // ── Sikl paneli va hafta tanlovi (weekSel) ──', '            // ── Fan ranglari modali ──');

// Sanalar: 02.11–21.11 (indeks 0–19). 8-hafta — 02–08 (0–6), 9-hafta — 09–15 (7–13), 10-hafta — 16–22 (14–19).
const dates = Array.from({ length: 20 }, (_, i) => { const iso = '2026-11-' + String(2 + i).padStart(2, '0'); return { iso, d: iso.slice(8, 10) + '.11' }; });
const ph = (dates, extra = {}) => Object.assign({ dates, mudir_name: 'XOLMATOV A. B.', orig_teacher_name: "TOSHPO'LATOV B. A.", reason: 'o‘qituvchi band' }, extra);
const card = (group, subject, from, to, extra) => Object.assign({ key: 'davolash ishi|5|' + group + '|' + subject + '|practice', row_key: 'r-' + group, group,
    specialty: 'davolash ishi', course: 5, subject, type: 'practice', days: 5, placed: true, conflict: null, conflicts: [], start_index: from, end_index: to,
    mudir_ph: null, mudir_ph_lec: null }, extra);
// 1-son davolash, 5-kurs: 9-haftada 4 ta mudir belgili blok — barchasi joylangan va to'qnashuvsiz.
const cards = [
    card('d1/d22-03c', 'Akusherlik', 7, 11, { mudir_ph: ph(['2026-11-10', '2026-11-11']) }),
    card('d1/d22-04b', 'Akusherlik', 5, 9, { mudir_ph: ph(null) }),                       // butun blok (06.11–10.11) — 8- va 9-hafta
    card('d1/d22-04c', 'Akusherlik', 9, 13, { mudir_ph: ph(['2026-11-13']) }),
    card('d1/d22-05a', 'Klinik farmakologiya', 8, 12, { mudir_ph_lec: ph(['2026-11-12'], { mudir_name: 'RAHIMOV K.' }) }),   // faqat oqim ma'ruzasi
    card('d1/d22-06a', 'Klinik farmakologiya', 1, 5, { mudir_ph: ph(['2026-11-03', '2026-11-04']) }),   // faqat 8-hafta
    card('d1/d22-07a', 'Akusherlik', 14, 18, { mudir_ph: ph(null) }),                     // butun blok — faqat 10-hafta
    // Belgisiz, joylangan; to'qnashuv 10-haftada — 9-hafta panelida yo'q.
    card('d1/d22-08a', 'Akusherlik', 14, 18, { conflict: "O'qituvchi band", conflicts: [{ kind: 'teacher_busy', type: 'teacher', who: 'MUX', groups: ['x'],
        past: false, date_from: '2026-11-17', date_to: '2026-11-18', from: '2026-11-17', to: '2026-11-18' }] }),
];
const stubs = `
    const esc = s => String(s == null ? '' : s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
    const $ = id => document.getElementById(id);
    const board = null, TIMETABLE_READ_ONLY = true, TIMETABLE_ROLE = 'admin', WEEKLY_ADMIN = true;
    let curWeek = 9, viewMode = 'cycle', cycleGroupFilter = '', cycleCfFilter = '', cycleRowFilters = [], cfOwners = {};
    let cyclePlanData = { layer: 'internal', dates: ${JSON.stringify(dates)}, cycle_cards: ${JSON.stringify(cards)}, rows: [] };
    const weekMonday = n => new Date(2026, 10, 2 + (n - 8) * 7);
    const weekDates = n => ({ 8: '02.11–07.11', 9: '09.11–14.11', 10: '16.11–21.11' })[n];
    const cycleVisibleRows = rows => rows.filter(r => cycleRowFilters.includes(r.row_key));
    const subjColor = () => ({ bg: '#fff', border: '#000' }), cycleFlowShort = g => g, highlightCycleRow = () => {};
    let ACT = false;
    function cycActAllowed() { return ACT; }
    function cycPhRevert(c) { window.reverted = c.key; }
`;
const filler = Array.from({ length: 12 }, (_, i) => card('d1/d22-09' + String.fromCharCode(97 + i), 'Ichki kasalliklar', null, null, { placed: false, start_index: null, end_index: null }));

(async () => {
    const browser = await chromium.launch();
    try {
        const p = await (await browser.newContext({ viewport: { width: 1200, height: 800 } })).newPage();
        await p.setContent('<!doctype html><html><head><style>' + css + '</style></head><body>' +
            ['cycleGroupFilter', 'skipToggle', 'unplacedExportBtn', 'subjectColorsBtn', 'cardPanelTitle', 'cycleMsg', 'cardPanelHint', 'unplacedCount']
                .map(id => (id === 'cycleGroupFilter' ? '<select id="' + id + '"></select>' : '<div id="' + id + '"></div>')).join('') +
            '<div id="cycleGridWrap" style="overflow:auto;width:600px"><table id="cycleGrid"><thead><tr></tr></thead></table></div>' +
            '<div id="cardPanel" class="p-2 flex flex-wrap gap-1.5 overflow-y-auto bg-white" style="max-height:120px;display:flex;flex-wrap:wrap;overflow-y:auto"></div>' +
            '<div style="height:2000px"></div></body></html>');
        await p.addScriptTag({ content: stubs + code + `
            window.T = {
                render: () => renderCycleCards(cyclePlanData),
                set: o => { if ('week' in o) curWeek = o.week; if ('group' in o) cycleGroupFilter = o.group; if ('rows' in o) { cycleRowFilters = o.rows; cyclePlanData.rows = o.rows.map(k => ({ row_key: k })); }
                    if ('act' in o) ACT = o.act; if ('cards' in o) cyclePlanData.cycle_cards = o.cards; },
                scoped: () => cycleScopedCards(cyclePlanData),
                inWeek: (c, wk) => cyclePhInWeek(c, wk, cyclePlanData.dates),
            };` });
        const text = sel => p.evaluate(s => { const e = document.querySelector(s); return e ? e.textContent.replace(/\s+/g, ' ').trim() : null; }, sel);
        const phGroups = () => p.$$eval('[data-cyc-ph-sec] .cyc-ph-item > div:first-child b', bs => bs.map(b => b.textContent));
        const panelKeys = () => p.$$eval('#cardPanel .cycle-pn-card', cs => cs.map(c => c.dataset.cycleKey));

        // 1) 9-hafta: panel ro'yxati bo'sh (hammasi joylangan, 9-haftada to'qnashuv yo'q), bo'limda 4 ta — 03c/04b/04c Akusherlik va
        //    05a Klinik farmakologiya (faqat oqim ma'ruzasi belgisi); sarlavhada ikkalasi.
        await p.evaluate(() => T.render());
        assert.deepEqual(await panelKeys(), []);
        assert.equal(await text('.cyc-wk-head > span'), '9-hafta (09.11–14.11): 0 ta · vaqtincha kafedra mudirida: 4 ta');
        assert.match(await text('[data-cyc-ph-sec] > b'), /^Vaqtincha kafedra mudirida — 4 ta$/);
        assert.deepEqual(await phGroups(), ['d1/d22-03c', 'd1/d22-04b', 'd1/d22-04c', 'd1/d22-05a']);
        const items = await p.$$eval('[data-cyc-ph-sec] .cyc-ph-item', es => es.map(e => e.textContent.replace(/\s+/g, ' ').trim()));
        assert.equal(items[0], "d1/d22-03c · AkusherlikXOLMATOV A. B. (asl: TOSHPO'LATOV B. A.); 10.11, 11.11; sabab: o‘qituvchi band");
        assert.match(items[1], /; butun blok; /);
        assert.match(items[3], /^d1\/d22-05a · Klinik farmakologiyaRAHIMOV K\. .*; 12\.11 \(ma‘ruza\); /);
        assert.equal(await p.locator('[data-cyc-ph-revert-key]').count(), 0);   // faqat ko'rish huquqi — qaytarish tugmasi yo'q
        // "Sikl bloklari" hisoblagichi (scoped) o'zgarmaydi — bo'lim alohida ro'yxat.
        const sc = await p.evaluate(() => { const r = T.scoped(); return { scoped: r.scoped.map(c => c.group), ph: r.ph.map(c => c.group) }; });
        assert.deepEqual(sc.scoped, []);
        assert.deepEqual(sc.ph, ['d1/d22-03c', 'd1/d22-04b', 'd1/d22-04c', 'd1/d22-05a']);

        // 2) Hafta oralig'i: sanali belgi — sanalardan biri haftada; butun blok — blok sanalari hafta bilan kesishsa.
        const inW = (i, from, to) => p.evaluate(([i, from, to]) => T.inWeek(cyclePlanData.cycle_cards[i], { from, to }), [i, from, to]);
        assert.equal(await inW(1, '2026-11-02', '2026-11-08'), true);    // 04b: 06.11–10.11 — 8-haftaga ham
        assert.equal(await inW(1, '2026-11-16', '2026-11-22'), false);
        assert.equal(await inW(0, '2026-11-02', '2026-11-08'), false);   // 03c: 10.11, 11.11
        assert.equal(await inW(5, '2026-11-16', '2026-11-22'), true);    // 07a: 16.11–20.11

        // 3) Boshqa haftalar: 8-hafta — 04b (butun blok) va 06a; 10-hafta — 07a, panelda to'qnashuvli 08a.
        await p.evaluate(() => { T.set({ week: 8 }); T.render(); });
        assert.equal(await text('.cyc-wk-head > span'), '8-hafta (02.11–07.11): 0 ta · vaqtincha kafedra mudirida: 2 ta');
        assert.deepEqual(await phGroups(), ['d1/d22-04b', 'd1/d22-06a']);
        await p.evaluate(() => { T.set({ week: 10 }); T.render(); });
        assert.equal(await text('.cyc-wk-head > span'), '10-hafta (16.11–21.11): 1 ta · vaqtincha kafedra mudirida: 1 ta');
        assert.deepEqual(await phGroups(), ['d1/d22-07a']);
        assert.deepEqual(await panelKeys(), ['davolash ishi|5|d1/d22-08a|Akusherlik|practice']);

        // 4) "Butun semestr" — barcha belgilar (6 ta).
        await p.evaluate(() => { T.set({ week: 9 }); T.render(); });
        await p.locator('[data-cyc-all]').check();
        assert.equal(await text('.cyc-wk-head > span'), 'Butun semestr: 1 ta · vaqtincha kafedra mudirida: 6 ta');
        assert.equal(await p.locator('[data-cyc-ph-sec] .cyc-ph-item').count(), 6);
        await p.locator('[data-cyc-all]').uncheck();
        assert.equal(await text('.cyc-wk-head > span'), '9-hafta (09.11–14.11): 0 ta · vaqtincha kafedra mudirida: 4 ta');

        // 5) Guruh filtri: oqim tanlovi va jadvaldagi ko'p tanlov (cycleRowFilters) — bo'limga ham; belgisi bo'lmasa — bo'lim ham, havola ham yo'q.
        await p.evaluate(() => { T.set({ group: 'd1/d22-04b' }); T.render(); });
        assert.equal(await text('.cyc-wk-head > span'), '9-hafta (09.11–14.11): 0 ta · vaqtincha kafedra mudirida: 1 ta');
        await p.evaluate(() => { T.set({ group: 'd1/d22-06a' }); T.render(); });
        assert.equal(await text('.cyc-wk-head > span'), '9-hafta (09.11–14.11): 0 ta');
        assert.equal(await p.locator('[data-cyc-ph-sec], [data-cyc-ph-go]').count(), 0);
        await p.evaluate(() => { T.set({ group: '', rows: ['r-d1/d22-03c', 'r-d1/d22-05a'] }); T.render(); });
        assert.deepEqual(await phGroups(), ['d1/d22-03c', 'd1/d22-05a']);
        await p.evaluate(() => { T.set({ rows: [] }); });

        // 6) Qaytarish huquqi bor — har bandda "Asl holatga qaytarish" (hafta tanlanganda ham).
        await p.evaluate(() => { T.set({ act: true }); T.render(); });
        assert.equal(await p.locator('[data-cyc-ph-revert-key]').count(), 4);
        await p.locator('[data-cyc-ph-revert-key]').nth(2).click();
        assert.equal(await p.evaluate(() => window.reverted), 'davolash ishi|5|d1/d22-04c|Akusherlik|practice');

        // 7) Sarlavhadagi havola: panel ko'p kartali (scroll) — bosilsa panel ichida bo'lim tepaga keladi, sahifa siljimaydi, bo'lim ajraladi.
        await p.evaluate(f => { T.set({ act: false, cards: cyclePlanData.cycle_cards.concat(f) }); T.render(); $('cardPanel').scrollTop = 0; }, filler);
        assert.equal(await text('.cyc-wk-head > span'), '9-hafta (09.11–14.11): 12 ta · vaqtincha kafedra mudirida: 4 ta');
        const before = await p.evaluate(() => ({ panel: $('cardPanel').scrollTop, page: window.scrollY }));
        assert.equal(before.panel, 0);
        await p.locator('[data-cyc-ph-go]').click();
        const after = await p.evaluate(() => {
            const pn = $('cardPanel'), sec = pn.querySelector('[data-cyc-ph-sec]');
            return { panel: pn.scrollTop, page: window.scrollY, gap: Math.round(sec.getBoundingClientRect().top - pn.getBoundingClientRect().top),
                max: pn.scrollHeight - pn.clientHeight, flash: sec.classList.contains('cyc-ph-flash') };
        });
        assert.ok(after.panel > 0, 'panel bo\'limga surilmadi');
        assert.equal(after.page, 0);
        assert.ok(after.gap === 0 || after.panel === after.max, 'bo\'lim panel tepasida emas: ' + JSON.stringify(after));
        assert.ok(after.flash);
    } finally {
        await browser.close();
    }
    console.log('timetable cycle ph week: 7 scenarios passed');
})().catch(e => { console.error(e); process.exit(1); });
