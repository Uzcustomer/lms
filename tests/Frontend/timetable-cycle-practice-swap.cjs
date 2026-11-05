const fs = require('node:fs');
const assert = require('node:assert/strict');
const path = require('node:path');
// Sikl jadvali: "Faqat ma'ruza" surilganda to'sib turgan amaliyot — kun oynasida "Amaliyot bilan o'rin almashish" (sukut yoqilgan) va usul
// (o'rin almashish / ma'ruzadan keyin / oraliq bilan N para / aniq para); sudrashda kesishuv xato emas; oldindan tekshiruv oynasi
// ("Amaliyot o'rin almashadi: …", "Qo'llash", "Faqat ma'ruzani (…)", "Bekor"); teskari holat — "Ma'ruza bilan o'rin almashsinmi?";
// o'chirilsa — avvalgidek xato; o'qituvchi ma'ruza so'rovida ham usul.
const { chromium } = require('playwright');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');
const cut = (from, to) => {
    const a = source.indexOf(from), b = source.indexOf(to, a);
    assert(a > 0 && b > a, from);
    return source.slice(a, b);
};
const css = cut('<style>', '</style>').slice('<style>'.length);
const helpers = cut('            // Ichki jadval: kun ustunini tanlash, kun oynasi va soatni surish.', '            function openCycDayPop(key, offset, anchor, keepMulti = false) {');
const dayPop = cut('            function openCycDayPop(key, offset, anchor, keepMulti = false) {', '            // Fan nomi bosilganda:');
const drag = cut('            let cycCellDragged = false;', '            function setupCycleGridEvents() {');

// Travmatologiya va ortopediya, oqim "2-son davolash / 3-oqim": har kuni ma'ruza 1–2-soat (08:30), amaliy 3–4-soat (10:00). Bugun — 05.11.
const stubs = (role, dayEdit) => `
    const TIMETABLE_ROLE = ${JSON.stringify(role)};
    const TIMETABLE_READ_ONLY = ${JSON.stringify(['oqituvchi', 'fan_masuli'].includes(role))};
    const esc = s => String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;');
    const $ = id => document.getElementById(id);
    const BASE = '/admin/timetable', board = { id: 7 };
    let viewMode = 'cycle', cycTitleDragged = false, cycleRowFilters = [];
    const DAY = k => ({ k: k, lec: 2, prc: 2, start: 1, ps: 3, ktr: true });
    const BLOCK = { key: 'K', subject: 'Travmatologiya va ortopediya', from: 10, day_plan: { 0: DAY(1), 1: DAY(2), 2: DAY(3), 3: DAY(4), 4: DAY(5), 5: DAY(6) } };
    const cyclePlanData = { day_edit: ${JSON.stringify(dayEdit)}, day_hours: 12, rows: [{ blocks: [BLOCK] }],
        cycle_cards: [{ key: 'K', specialty: 'davolash ishi', course: 4, group: '2-son davolash / 3-oqim', subject: 'Travmatologiya va ortopediya',
            type: 'practice', placement_group: '2-son davolash / 3-oqim' }],
        dates: Object.assign([], { 10: { iso: '2026-11-16', d: '16.11' }, 11: { iso: '2026-11-17', d: '17.11' }, 12: { iso: '2026-11-18', d: '18.11' },
            13: { iso: '2026-11-19', d: '19.11' }, 14: { iso: '2026-11-20', d: '20.11' }, 15: { iso: '2026-11-21', d: '21.11' } }) };
    const cycleEffView = () => 'group';
    const cycLocalToday = () => '2026-11-05';
    const T12 = ['08:30', '09:10', '10:00', '10:40', '12:00', '12:40', '13:30', '14:10', '15:00', '15:40', '16:30', '17:10', '17:50'];
    const cycHourTimes = () => [null].concat(T12.slice(0, 12).map((t, i) => [t, T12[i + 1]]));
    const boardSchedule = () => [['I', '08:30', '09:50'], ['II', '10:00', '11:20'], ['III', '12:00', '13:20'], ['IV', '13:30', '14:50'], ['V', '15:00', '16:20'],
        ['VI', '16:30', '17:50']].map(([abbr, start, end]) => ({ type: 'pair', abbr, start, end }));
    const crDow = () => 'du';
    const cycPopUnwatch = () => {}, openCfPop = () => {}, cycleRowFiltersApply = () => {}, openCycSubjPop = () => {};
    const pendingDaySlot = () => null, pendingPracticeAsk = () => null, crStageText = () => '';
    const cycCanAsk = (card, dp, date, today, kind) => ${JSON.stringify(!dayEdit)} && kind === 'lecture' && date.iso > today;
    window.calls = [];
    window.toasts = [];
    window.reloads = 0;
    window.srv = null;   // (body) => days — test o'rnatadi
    function ttToast(html, tone) { window.toasts.push({ html, tone }); }
    function onPendingApproval(j) { window.toasts.push({ html: 'pending', tone: 'warn' }); }
    async function loadCyclePlan() { window.reloads++; }
    function moveCycDay(...args) { window.calls.push({ move: args }); }
    function openCycleTimeProposal(card, dp, date, opts) { window.calls.push({ proposal: opts.kind }); }
    async function api(url, method, body) {
        body = JSON.parse(JSON.stringify(body));
        window.calls.push({ api: body });
        if (body.undo) return { ok: true, undone: true };
        const days = (body.day_indexes || []).map(k => Object.assign({ day_index: k, date: '2026-11-' + (16 + k), status: 'ok', why: null, from: 1,
            to: body.start_hour || 1 + body.delta, swaps: [], lecture: null }, window.srv ? window.srv(body, k) : {}));
        days.forEach(d => { d.applied = !body.dry_run && (d.status === 'ok' || (d.status === 'conflict' && Boolean(body.force))); });
        return { ok: true, dry_run: Boolean(body.dry_run), days: days, applied: days.filter(d => d.applied).length, undo: body.dry_run ? null : 'TOKEN' };
    }
`;
// Panjara: 6 kun × 12 soat; M — 1–2-soat, A — 3–4-soat.
const gridHtml = () => {
    let h = '<div id="cycleGridWrap"><div id="cycleGrid" style="display:grid;grid-template-columns:repeat(6,40px);grid-auto-rows:24px">'
        + '<div class="cyc-mk-title" data-mk-subj="K" style="grid-column:1 / -1">Travmatologiya va ortopediya</div>';
    for (let hr = 1; hr <= 12; hr++) {
        for (let d = 0; d < 6; d++) {
            const kind = hr <= 2 ? ' is-lecture' : (hr <= 4 ? ' is-practice' : '');
            h += '<div class="cyc-mk-cell' + kind + '" data-mk-key="K" data-mk-day="' + d + '" data-mk-hour="' + hr + '" style="border:1px solid #ddd">'
                + (kind ? (kind.includes('lecture') ? 'M' : 'A') : '') + '</div>';
        }
    }
    return h + '</div></div>';
};
const at = (p, day, hour) => p.evaluate(([d, h]) => {
    const r = document.querySelector('.cyc-mk-cell[data-mk-day="' + d + '"][data-mk-hour="' + h + '"]').getBoundingClientRect();
    return { x: r.left + r.width / 2, y: r.top + r.height / 2 };
}, [day, hour]);
const dragCell = async (p, from, to, { mods = [], hold = 60 } = {}) => {
    const a = await at(p, ...from), b = await at(p, ...to);
    for (const m of mods) await p.keyboard.down(m);
    await p.mouse.move(a.x, a.y);
    await p.mouse.down();
    await p.mouse.move(a.x, a.y + 8, { steps: 2 });
    await p.mouse.move(b.x, b.y, { steps: 4 });
    await p.waitForTimeout(hold);
    const tip = await p.evaluate(() => ({ text: (document.getElementById('cycDragTip') || {}).textContent || '',
        bad: Boolean(document.querySelector('#cycDragTip.is-bad')) }));
    await p.mouse.up();
    for (const m of mods) await p.keyboard.up(m);
    await p.waitForTimeout(80);
    return tip;
};
const apiCalls = p => p.evaluate(() => window.calls.filter(c => c.api).map(c => c.api));
const reset = p => p.evaluate(() => { window.calls = []; window.toasts = []; window.srv = null; });
// Server javobi: ma'ruza 08:30 → 10:00, amaliyot 08a, 09a — o'rin almashadi, 09b — sig'madi, keyinga (12:00).
const SWAPS = [{ unit: 'd2/d23-08a', from: 3, to: 1, len: 2, how: 'swap', busy: false, why: null },
    { unit: 'd2/d23-09a', from: 3, to: 1, len: 2, how: 'swap', busy: false, why: null },
    { unit: 'd2/d23-09b', from: 3, to: 5, len: 4, how: 'after', busy: false, why: 'nofit' }];

(async () => {
    const browser = await chromium.launch();
    try {
        const page = async (role, dayEdit, withPop = false) => {
            const p = await (await browser.newContext({ viewport: { width: 1100, height: 760 } })).newPage();
            p.on('pageerror', e => console.error('PAGEERROR', e.message));
            // Haqiqiy manzil (about:blank da localStorage yo'q — sozlama saqlanishi tekshiriladi).
            const html = '<!doctype html><html><head><style>' + css + '</style></head><body style="margin:40px">' + gridHtml() + '</body></html>';
            await p.route('http://lms.test/**', r => r.fulfill({ contentType: 'text/html', body: html }));
            await p.goto('http://lms.test/');
            await p.addScriptTag({ content: stubs(role, dayEdit) + helpers
                + (withPop ? dayPop : 'function openCycDayPop(key, offset) { window.calls.push({ pop: [key, +offset] }); cycDaySel = { key: key, offset: +offset }; }\n')
                + drag + '\nsetupCycleMarking();\nwindow.T = { cycPracPlace, cycPracPlaceParams };' });
            await p.evaluate(() => { try { localStorage.clear(); } catch (e) { } });
            return p;
        };

        // 1) Kun oynasi: sozlama — sukut yoqilgan, "O'rin almashish"; usul tanlansa saqlanadi; "Oraliq bilan" — para soni, "Aniq para" — para.
        const k = await page('admin', true, true);
        await k.evaluate(() => openCycDayPop('K', 0, document.querySelector('.cyc-mk-cell[data-mk-day="0"][data-mk-hour="1"]')));
        assert.equal(await k.$eval('#cycDayPop [data-dp-swap]', el => el.checked), true);
        assert.match(await k.textContent('#cycDayPop .dp-swap'), /Amaliyot bilan o'rin almashish/);
        assert.equal(await k.$eval('#cycDayPop [data-dp-swap-how]', el => el.value), 'swap');
        assert.deepEqual(await k.$$eval('#cycDayPop [data-dp-swap-how] option', els => els.map(e => e.textContent)),
            ["O'rin almashish", "Ma'ruzadan keyin", 'Oraliq bilan', 'Aniq para']);
        await k.selectOption('#cycDayPop [data-dp-swap-how]', 'gap');
        assert.equal(await k.$eval('#cycDayPop [data-dp-swap-gap]', el => el.value), '1');
        await k.fill('#cycDayPop [data-dp-swap-gap]', '2');
        await k.dispatchEvent('#cycDayPop [data-dp-swap-gap]', 'change');
        assert.deepEqual(await k.evaluate(() => T.cycPracPlaceParams()), { practice_place: 'gap', practice_gap: 2 });
        await k.selectOption('#cycDayPop [data-dp-swap-how]', 'pair');
        assert.deepEqual(await k.$$eval('#cycDayPop [data-dp-swap-pair] option', els => els.map(e => e.textContent)).then(x => x.slice(0, 3)),
            ['I para (08:30)', 'II para (10:00)', 'III para (12:00)']);
        await k.selectOption('#cycDayPop [data-dp-swap-pair]', '4');
        assert.deepEqual(await k.evaluate(() => T.cycPracPlaceParams()), { practice_place: 'pair', practice_pair: 4 });
        // O'chirilsa — "keep" va izoh.
        await k.uncheck('#cycDayPop [data-dp-swap]');
        assert.deepEqual(await k.evaluate(() => T.cycPracPlaceParams()), { practice_place: 'keep' });
        assert.match(await k.textContent('#cycDayPop .dp-swap'), /O'chiq: ma'ruza amaliyot bilan kesishsa — xato/);
        assert.equal(await k.$('#cycDayPop [data-dp-swap-how]'), null);
        // Ma'ruza rejimida ↓: ostida amaliy yonma-yon — qadam o'rin almashish (amaliy butunligicha tepaga, ma'ruza uning ortidan), sozlama
        // o'chiq bo'lsa ham (sozlama — faqat sudrashda va boshqa guruhcha amaliyoti uchun); ↑ — kun boshida, o'chiq.
        await k.check('#cycDayPop input[name="dpMode"][value="lecture"]');
        assert.equal(await k.$eval('#cycDayPop [data-dp-move="1"]', el => el.disabled), false);
        assert.equal(await k.$eval('#cycDayPop [data-dp-move="-1"]', el => el.disabled), true);
        // Kun ustuni: "Faqat ma'ruza" — faqat M kataklari (1–2-soat) ramkada, A va bo'sh soatlar emas.
        assert.deepEqual(await k.$$eval('.cyc-mk-cell.is-day-sel', els => els.map(e => +e.dataset.mkDay + ':' + e.dataset.mkHour + (e.classList.contains('is-day-top') ? 't' : '')
            + (e.classList.contains('is-day-bottom') ? 'b' : ''))), ['0:1t', '0:2b']);
        await k.check('#cycDayPop [data-dp-swap]');
        assert.equal(await k.$eval('#cycDayPop [data-dp-move="1"]', el => el.disabled), false);
        await k.selectOption('#cycDayPop [data-dp-swap-how]', 'swap');
        // ↓ (ma'ruza, bitta kun) — qadam (step=jump, delta ±1; sakrash serverda), avval tekshiruv; boshqa guruhcha amaliyoti ko'chadi — oyna.
        await reset(k);
        await k.evaluate(s => { window.srv = () => ({ swaps: s, to: 3 }); }, SWAPS);
        await k.click('#cycDayPop [data-dp-move="1"]');
        await k.waitForSelector('#cycMultiDlg');
        assert.deepEqual((await apiCalls(k)).map(c => [c.dry_run, c.day_indexes, c.mode, c.start_hour, c.delta, c.practice_place, c.step]),
            [[1, [0], 'lecture', undefined, 1, 'swap', 'jump']]);
        assert.match(await k.textContent('#cycMultiDlg p.text-sm'), /ma'ruza 1 qadam pastga · 1 kun/);
        await k.click('#cycMultiDlg [data-cmd="cancel"]');
        // 1b) Bir nechta kun tanlangan bo'lsa, kun oynasidagi ↓ — hammasiga (har biri o'z boshidan, delta), faqat ochiq kunga emas.
        await reset(k);
        await k.evaluate(() => { closeCycDayPop(); window.srv = null; });
        for (const d of [2, 3]) {
            const c = await at(k, d, 1);
            await k.keyboard.down('Control'); await k.mouse.click(c.x, c.y); await k.keyboard.up('Control');
        }
        await k.evaluate(() => openCycDayPop('K', 2, document.querySelector('.cyc-mk-cell[data-mk-day="2"][data-mk-hour="1"]'), true));
        await reset(k);
        await k.click('#cycDayPop [data-dp-move="1"]');
        await k.waitForTimeout(80);
        assert.deepEqual((await apiCalls(k)).map(c => [c.dry_run, c.day_indexes, c.mode, c.delta, c.start_hour, c.step, c.day_modes]),
            [[1, [2, 3], 'lecture', 1, undefined, 'jump', undefined], [undefined, [2, 3], 'lecture', 1, undefined, 'jump', undefined]]);
        // Oyna qayta ochiladi, tanlov qoladi — keyingi ↓ ham ikkala kunga.
        assert.match(await k.textContent('#cycSelBadge'), /Tanlangan: 2 kun/);
        await reset(k);
        await k.click('#cycDayPop [data-dp-move="1"]');
        await k.waitForTimeout(80);
        assert.deepEqual((await apiCalls(k)).map(c => c.day_indexes), [[2, 3], [2, 3]]);
        // Tanlovga kirmagan kun oynasi — faqat shu kun.
        await k.evaluate(() => openCycDayPop('K', 4, document.querySelector('.cyc-mk-cell[data-mk-day="4"][data-mk-hour="1"]'), true));
        await reset(k);
        await k.click('#cycDayPop [data-dp-move="1"]');
        await k.waitForTimeout(80);
        assert.deepEqual((await apiCalls(k)).map(c => [c.dry_run, c.day_indexes, c.start_hour, c.delta, c.step]), [[1, [4], undefined, 1, 'jump'], [undefined, [4], undefined, 1, 'jump']]);
        await k.keyboard.press('Escape');

        // 2) Ma'ruzani (M) amaliy ustiga sudrash: kesishuv xato emas, ko'rsatkichda usul; qo'yib yuborilganda — oyna: "Ma'ruza 08:30 → 10:00 (oqim,
        //    1 kun). Amaliyot o'rin almashadi: d2/d23-08a 10:00 → 08:30, 09a 10:00 → 08:30, 09b 10:00 → 12:00 (sig'madi — keyinga)".
        const p = await page('admin', true);
        await p.evaluate(s => { window.srv = (b) => ({ swaps: b.practice_place === 'keep' ? [] : s,
            status: b.practice_place === 'keep' ? 'blocked' : 'ok', why: b.practice_place === 'keep' ? "Ma'ruza 10:00–11:20 amaliy bilan ustma-ust (d2/d23-08a)" : null }); }, SWAPS);
        const tip = await dragCell(p, [0, 1], [0, 3]);
        assert.equal(tip.bad, false);
        assert.match(tip.text, /^Ma'ruza: 10:00–12:00 · amaliyot: o'rin almashish/);
        await p.waitForSelector('#cycMultiDlg');
        assert.equal(await p.textContent('#cycMultiDlg h3'), 'Kunni surish');
        assert.equal(await p.textContent('#cycMultiDlg [data-cmd-head]'), "Ma'ruza 08:30 → 10:00 (oqim, 1 kun).");
        assert.equal(await p.textContent('#cycMultiDlg [data-cmd-swaps]'),
            "Amaliyot o'rin almashadi: d2/d23-08a 10:00 → 08:30, 09a 10:00 → 08:30, 09b 10:00 → 12:00 (sig'madi — keyinga).");
        assert.deepEqual(await p.$$eval('#cycMultiDlg button', els => els.map(e => e.textContent)),
            ['Bekor', "Faqat ma'ruzani (amaliyotga tegmasdan — xato beradi)", "Qo'llash (1)"]);
        assert.deepEqual((await apiCalls(p)).map(c => [c.dry_run, c.day_indexes, c.delta, c.mode, c.practice_place]), [[1, [0], 2, 'lecture', 'swap']]);
        await p.click('#cycMultiDlg [data-cmd="apply"]');
        await p.waitForTimeout(60);
        let commit = (await apiCalls(p)).filter(c => !c.dry_run);
        assert.deepEqual(commit.map(c => [c.day_indexes, c.delta, c.mode, c.practice_place]), [[[0], 2, 'lecture', 'swap']]);
        let toasts = await p.evaluate(() => window.toasts);
        assert.match(toasts[0].html, /^1 kun: ma'ruza 08:30 → 10:00 · amaliyot ko'chdi: 3 guruhcha <button[^>]*data-cyc-undo>Qaytarish \(Ctrl\+Z\)/);
        // Ctrl+Z — bitta so'rov (server ma'ruza va amaliyotni birga tiklaydi).
        await reset(p);
        await p.keyboard.press('Control+z');
        await p.waitForTimeout(50);
        assert.deepEqual((await apiCalls(p)).map(c => c.undo), ['TOKEN']);

        // 3) "Faqat ma'ruzani" — amaliyotga tegmasdan (keep): server xato beradi — o'zgarmaydi, xabarda sababi.
        await reset(p);
        await p.evaluate(s => { window.srv = (b) => ({ swaps: b.practice_place === 'keep' ? [] : s,
            status: b.practice_place === 'keep' ? 'blocked' : 'ok', why: b.practice_place === 'keep' ? 'ustma-ust' : null }); }, SWAPS);
        await dragCell(p, [0, 1], [0, 3]);
        await p.waitForSelector('#cycMultiDlg');
        await p.click('#cycMultiDlg [data-cmd="keep"]');
        await p.waitForTimeout(60);
        commit = (await apiCalls(p)).filter(c => !c.dry_run);
        assert.deepEqual(commit.map(c => [c.day_indexes, c.practice_place]), [[[0], 'keep']]);
        toasts = await p.evaluate(() => window.toasts);
        assert.match(toasts[0].html, /Hech bir kun o'zgarmadi/);
        assert.match(toasts[0].html, /16\.11 \(1-kun\) — o'zgarmaydi/);

        // 4) O'rin almashish o'chiq: ma'ruza amaliy ustiga — avvalgidek xato (serverga so'rov yo'q).
        await reset(p);
        await p.evaluate(() => localStorage.setItem('tt.cycPracPlace', JSON.stringify({ on: false })));
        const off = await dragCell(p, [0, 1], [0, 3]);
        assert.equal(off.bad, true);
        assert.match(off.text, /Ma'ruza va amaliy ustma-ust/);
        assert.equal((await apiCalls(p)).length, 0);
        assert.match((await p.evaluate(() => window.toasts))[0].html, /Ko'chirib bo'lmadi: Ma'ruza va amaliy ustma-ust/);

        // 5) Usul "Oraliq bilan: 1 para" — so'rovda practice_gap; amaliyot ko'chmasa (kesishmaydi) — darhol, oynasiz.
        await reset(p);
        await p.evaluate(() => localStorage.setItem('tt.cycPracPlace', JSON.stringify({ on: true, how: 'gap', gap: 1 })));
        await dragCell(p, [1, 1], [1, 7]);
        assert.equal(await p.$('#cycMultiDlg'), null);
        assert.deepEqual((await apiCalls(p)).map(c => [c.dry_run, c.mode, c.practice_place, c.practice_gap]),
            [[1, 'lecture', 'gap', 1], [undefined, 'lecture', 'gap', 1]]);
        await p.evaluate(() => localStorage.setItem('tt.cycPracPlace', JSON.stringify({ on: true, how: 'swap' })));

        // 6) Ko'p kun (Ctrl): har kun o'z natijasi; amaliyot ko'chadigan bo'lsa — oyna, kun qatorida amaliyot ro'yxati; "Baribir" — to'qnashuvlisi ham.
        await reset(p);
        for (const d of [0, 1, 2]) {
            const c = await at(p, d, 1);
            await p.keyboard.down('Control'); await p.mouse.click(c.x, c.y); await p.keyboard.up('Control');
        }
        await p.evaluate(s => { window.srv = (b, k) => k === 1 ? { status: 'conflict', why: "Amaliy (d2/d23-09b): O'qituvchi band: d2/d24-01a, Anatomiya",
            swaps: [Object.assign({}, s[2], { to: 1, how: 'swap', why: null, busy: true })] } : { swaps: s.slice(0, 2) }; }, SWAPS);
        await dragCell(p, [0, 1], [0, 3], { hold: 400 });
        await p.waitForSelector('#cycMultiDlg');
        assert.equal(await p.textContent('#cycMultiDlg h3'), 'Bir nechta kunni surish');
        assert.equal(await p.textContent('#cycMultiDlg [data-cmd-head]'), "Ma'ruza 08:30 → 10:00 (oqim, 3 kun).");
        assert.equal(await p.textContent('#cycMultiDlg [data-cmd-swaps]'),
            "Amaliyot o'rin almashadi: d2/d23-08a 10:00 → 08:30, 09a 10:00 → 08:30, 09b 10:00 → 08:30 — band!.");
        const items = await p.$$eval('#cycMultiDlg .cmd-list li', els => els.map(e => e.textContent));
        assert.match(items[1], /17\.11 \(2-kun\) 08:30 → 10:00 — to'qnashuv: Amaliy \(d2\/d23-09b\): O'qituvchi band.*amaliyot: d2\/d23-09b 10:00 → 08:30 — band!/);
        assert.match(items[0], /amaliyot: d2\/d23-08a 10:00 → 08:30, 09a 10:00 → 08:30$/);
        await p.click('#cycMultiDlg [data-cmd="force"]');
        await p.waitForTimeout(60);
        commit = (await apiCalls(p)).filter(c => !c.dry_run);
        assert.deepEqual(commit.map(c => [c.day_indexes, c.force, c.practice_place]), [[[0, 1, 2], 1, 'swap']]);
        await p.keyboard.press('Escape');

        // 7) Teskari holat: amaliyni (A) ma'ruza ustiga sudrash — kesishuv xato emas; server "Ma'ruza vaqti bilan ustma-ust" — o'rin almashish
        //    taklifi (lecture_swap bilan oldindan ko'rinish): "Ma'ruza bilan o'rin almashsinmi?", boshqa guruhchalar amaliyoti ham.
        await reset(p);
        await p.evaluate(() => { window.srv = (b) => b.lecture_swap
            ? { from: 3, to: 1, lecture: { from: 1, to: 3, len: 2 }, swaps: [{ unit: '2-son davolash / 3-oqim', from: 3, to: 1, how: 'pin', busy: false, why: null }] }
            : { from: 3, to: 1, status: 'blocked', why: 'Ma’ruza vaqti bilan ustma-ust (16.11, ma’ruza 08:30–09:50).' }; });
        const rt = await dragCell(p, [0, 3], [0, 1]);
        assert.equal(rt.bad, false);
        assert.match(rt.text, /ma'ruza bilan o'rin almashish taklif qilinadi/);
        await p.waitForSelector('#cycMultiDlg');
        assert.equal(await p.textContent('#cycMultiDlg h3'), "Ma'ruza bilan o'rin almashsinmi?");
        assert.equal(await p.textContent('#cycMultiDlg [data-cmd-lec]'), "Ma'ruza (butun oqim) 08:30 → 10:00.");
        assert.match(await p.textContent('#cycMultiDlg [data-cmd-swaps]'), /2-son davolash \/ 3-oqim 10:00 → 08:30 \(siz surgan\)/);
        assert.deepEqual(await p.$$eval('#cycMultiDlg button', els => els.map(e => e.textContent)), ['Bekor', "Ha, o'rin almashsin (1)"]);
        assert.deepEqual((await apiCalls(p)).map(c => [c.dry_run, c.mode, c.lecture_swap, c.practice_place]),
            [[1, 'practice', undefined, undefined], [1, 'practice', 1, 'swap']]);
        await p.click('#cycMultiDlg [data-cmd="apply"]');
        await p.waitForTimeout(60);
        commit = (await apiCalls(p)).filter(c => !c.dry_run);
        assert.deepEqual(commit.map(c => [c.day_indexes, c.mode, c.lecture_swap, c.practice_place]), [[[0], 'practice', 1, 'swap']]);
        assert.match((await p.evaluate(() => window.toasts))[0].html, /ma'ruza bilan o'rin almashdi/);

        // 7b) Ko'p kun: amaliyni ma'ruza ustiga — server "ustma-ust" (taklif): oldindan ko'rinishda qizil (sig'maydigan kulrang emas), so'ng taklif.
        await reset(p);
        for (const d of [3, 4]) {
            const c = await at(p, d, 3);
            await p.keyboard.down('Control'); await p.mouse.click(c.x, c.y); await p.keyboard.up('Control');
        }
        await p.evaluate(() => { window.srv = (b) => b.lecture_swap ? { from: 3, to: 1, lecture: { from: 1, to: 3, len: 2 }, swaps: [] }
            : { from: 3, to: 1, status: 'blocked', why: 'Ma’ruza vaqti bilan ustma-ust (19.11, ma’ruza 08:30–09:50).' }; });
        const a7 = await at(p, 3, 3), b7 = await at(p, 3, 1);
        await p.mouse.move(a7.x, a7.y); await p.mouse.down(); await p.mouse.move(a7.x, a7.y + 8, { steps: 2 }); await p.mouse.move(b7.x, b7.y, { steps: 4 });
        await p.waitForTimeout(400);
        const c31 = await p.evaluate(() => document.querySelector('.cyc-mk-cell[data-mk-day="3"][data-mk-hour="1"]').className);
        assert.match(c31, /is-drag-bad/);
        assert.doesNotMatch(c31, /is-drag-nofit/);
        await p.mouse.up();
        await p.waitForSelector('#cycMultiDlg');
        assert.equal(await p.textContent('#cycMultiDlg h3'), "Ma'ruza bilan o'rin almashsinmi?");
        await p.click('#cycMultiDlg [data-cmd="cancel"]');
        await p.keyboard.press('Escape');

        // 8) O'qituvchi ma'ruza so'rovi: "To'sib turgan amaliyot" (sukut — kun oynasidagi sozlama), tekshiruvda o'rin almashish rejasi.
        const q = await (await browser.newContext({ viewport: { width: 1000, height: 760 } })).newPage();
        await q.route('http://lms.test/**', r => r.fulfill({ contentType: 'text/html', body: '<!doctype html><html><head><style>' + css + '</style></head><body></body></html>' }));
        await q.goto('http://lms.test/');
        await q.addScriptTag({ content: stubs('oqituvchi', false).replace('function openCycleTimeProposal', 'function _unusedProposal')
            .replace('async function api(url, method, body) {', 'async function api(url, method, body) { if (/check$/.test(url)) { window.calls.push({ check: JSON.parse(JSON.stringify(body)) }); '
                + 'if (body.practice_place === "after") throw new Error("Amaliy (d2/d23-08a) joylashmadi"); '
                + 'return { message: "Vaqt bo‘sh.", old: "a", new: "b", units: [], swaps: body.practice_place === "keep" ? "" : "Amaliyot o‘rin almashadi: d2/d23-08a 10:00 → 08:30" }; }')
            + helpers + 'const lectureLabel = (a, u) => ({ text: String(u), title: "" }); async function loadChangeRequests() {}\n'
            + cut('            function openCycleTimeProposal(card, dp, date, opts = {}) {', '            async function moveCycDay(key, offset, newStart, hint, mode) {')
            + '\nwindow.open1 = (o) => openCycleTimeProposal(Object.assign({}, cyclePlanData.cycle_cards[0], { type: "lecture" }), { k: 1, lec: 2, prc: 2, start: 1, ps: 3 }, { iso: "2026-11-16", d: "16.11" }, o);' });
        await q.evaluate(() => { localStorage.clear(); window.open1({ kind: 'lecture', start: 3 }); });
        await q.waitForTimeout(50);
        assert.equal(await q.$eval('#teacherTimeProposal select[name="practice_place"]', el => el.value), 'swap');
        assert.equal(await q.textContent('#teacherTimeProposal [data-swaps]'), 'Amaliyot o‘rin almashadi: d2/d23-08a 10:00 → 08:30');
        assert.deepEqual(await q.evaluate(() => window.calls.filter(c => c.check).map(c => [c.check.training_type, c.check.start_hour, c.check.practice_place])),
            [['lecture', '3', 'swap']]);
        // Tekshiruv xatosi — reja matni o'chadi.
        await q.selectOption('#teacherTimeProposal select[name="practice_place"]', 'after');
        await q.waitForTimeout(50);
        assert.equal(await q.textContent('#teacherTimeProposal [data-swaps]'), '');
        assert.match(await q.textContent('#teacherTimeProposal [role="alert"]'), /joylashmadi/);
        await q.selectOption('#teacherTimeProposal select[name="practice_place"]', 'swap');
        await q.waitForTimeout(50);
        assert.equal(await q.textContent('#teacherTimeProposal [data-swaps]'), 'Amaliyot o‘rin almashadi: d2/d23-08a 10:00 → 08:30');
        await q.selectOption('#teacherTimeProposal select[name="practice_place"]', 'pair');
        assert.equal(await q.$eval('#teacherTimeProposal [data-pp-pair]', el => el.hidden), false);
        await q.selectOption('#teacherTimeProposal select[name="practice_pair"]', '3');
        await q.waitForTimeout(50);
        const last = await q.evaluate(() => window.calls.filter(c => c.check).pop().check);
        assert.equal(last.practice_place, 'pair');
        assert.equal(last.practice_pair, '3');
        await q.selectOption('#teacherTimeProposal select[name="practice_place"]', 'keep');
        await q.waitForTimeout(50);
        assert.equal(await q.textContent('#teacherTimeProposal [data-swaps]'), '');
        assert.equal(await q.evaluate(() => window.calls.filter(c => c.check).pop().check.practice_place), 'keep');
        console.log('timetable cycle practice swap: 10 scenarios passed');
    } finally {
        await browser.close();
    }
})().catch(e => { console.error(e); process.exit(1); });
