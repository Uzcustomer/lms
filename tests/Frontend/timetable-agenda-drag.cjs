const fs = require('node:fs');
const assert = require('node:assert/strict');
const path = require('node:path');
// O'qituvchi kun/hafta jadvali oynasida sudrab ko'chirish (pointer): ko'chirsa bo'ladigan dars — data-ag-drag (server movable), bo'lmasa —
// sababi title da; 5px dan ortiq siljisa — teacher-agenda?move= (oyna qayta chizilmaydi, kutishda "tekshirilmoqda…"); bo'sh paralar —
// yashil (ok) / sariq (ogohlantirish, boshqa xona) / qizil (sababi yorliqda); bo'sh paraga tashlansa — tasdiq paneli (keyingi haftalar
// tekshiruvi bilan); qizil / band katak — toast; Esc — bekor. Kun va hafta ko'rinishi. O'qituvchining to'qnashuvdagi darsi (apply_fix)
// toza joyga — "Joylash (darhol)", sababsiz; bugungi dars (xodim) — HEMIS eslatmasi.
// Javob kelmasdan tashlash — katak eslab qolinadi ("tekshirilmoqda…"), javob kelgach davom etadi (bittasi; yangi sudrash / Esc — bekor;
// xato — toast). Oldindan yuklash: dars ustida 150 ms — variantlar keshga (bir vaqtda bittasi, eskisi to'xtatiladi); oyna ochilganda —
// to'qnashuvdagi ikkinchi dars. Darsni bosish — uni ko'chiriladigan dars qiladi; to'qnashuvdagi ko'chirsa bo'ladigan darslarda — "↕".
const { chromium } = require('playwright');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');
const cut = (from, to) => {
    const a = source.indexOf(from), b = source.indexOf(to, a);
    assert(a > 0 && b > a, from);
    return source.slice(a, b);
};
const css = cut('<style>', '</style>').slice('<style>'.length);
const agenda = cut("            // ===== O'qituvchi kun/hafta jadvali (teacher-agenda) =====", "            // \"Ko'rsatish\" havolasi bosilganda (capture");

const PAIRS = [1, 2, 3, 4, 5, 6].map(k => ({ no: k, from: 2 * k - 1, to: 2 * k + 1, start: ['08:30', '10:00', '12:00', '13:30', '15:00', '16:30'][k - 1],
    end: ['09:50', '11:20', '13:20', '14:50', '16:20', '17:50'][k - 1], label: ['I', 'II', 'III', 'IV', 'V', 'VI'][k - 1] + ' para' }));
const W = { key: 'w|5|10', kind: 'weekly', type: 'A', subject: 'Farmakologiya', groups: ['pp/2024-01b'], room: '102-xona', conflict: true, hidden: false,
    movable: true, movable_why: null, date: '2026-11-19', from: 7, to: 9, start: '13:30', end: '14:50' };
const M = { key: 'l|9|2026-11-19|1', kind: 'cycle', type: 'M', subject: 'Klinik farmakologiya', groups: ['d1/d22-01a'], room: 'Zal', conflict: false,
    hidden: false, movable: false, movable_why: "Sikl ma'ruzasi bu oynadan ko'chirilmaydi (ma'ruza soati — sikl jadvalida).", date: '2026-11-19', from: 1, to: 3,
    start: '08:30', end: '09:50' };
const MOVE_W = { key: 'w|5|10', kind: 'weekly', label: 'Farmakologiya (A) — pp/2024-01b', date: '2026-11-19', week: 10, card_id: 5, via: 'apply', why: null,
    rest_weeks: [10, 11], options: { '2026-11-19': {
        2: { ok: true, why: null, room: null, day: 4, pair: 3, fix: false },
        5: { ok: false, why: 'Guruh band: pp/2024-01b — Anatomiya', room: null, day: 4, pair: 9, fix: false },
        6: { ok: true, why: "Xona sig'maydi: 10 o'rin, 12 talaba (+2)", room: { code: 'R1', name: '101-xona', volume: 30 }, day: 4, pair: 11, fix: false } },
        '2026-11-20': { 1: { ok: true, why: null, room: null, day: 5, pair: 1, fix: false } } }, block: {} };
const dayData = (over = {}) => Object.assign({ title: 'MUXAMMADIYEV S. B. — 19.11.2026 (payshanba)', range: 'day', today: '2026-11-05',
    hemis_hint: 'Bugungi dars — ko‘chirgandan keyin HEMIS dagi jadval va davomatni «Bugungi darslar (HEMIS)» orqali solishtiring.',
    dates: [{ date: '2026-11-19', dow: 4, label: '19.11 (payshanba)' }], pairs: PAIRS, entries: [M, W],
    free: { '2026-11-19': [2, 3, 5, 6] }, move: null }, over);
const weekData = (over = {}) => dayData(Object.assign({ range: 'week', title: 'MUXAMMADIYEV S. B. — 16.11–21.11.2026',
    dates: [{ date: '2026-11-19', dow: 4, label: '19.11 (payshanba)' }, { date: '2026-11-20', dow: 5, label: '20.11 (juma)' }],
    free: { '2026-11-19': [2, 3, 5, 6], '2026-11-20': [1, 2, 3, 4, 5, 6] } }, over));

const stubs = role => `
    const TIMETABLE_ROLE = ${JSON.stringify(role)};
    const esc = s => String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/"/g, '&quot;');
    const BASE = '/admin/timetable', board = { id: 3 };
    let viewMode = 'week', weeklyLayer = 'internal', cycleLayer = null;
    const cfDate = d => String(d || '').split('-').reverse().slice(0, 2).join('.');
    const cfGroups = g => (g || []).join(', ');
    const crDow = () => 'payshanba';
    const infoOnlyText = () => '', isHemisExcluded = () => false, infoSubjectMatch = () => '';
    window.calls = []; window.toasts = []; window.reasons = 0;
    window.resp = null;      // (query) => javob (teacher-agenda)
    window.delay = 0;        // teacher-agenda javobi kechikishi (ms)
    window.post = null;      // (path, body) => javob (POST)
    async function askSugReason() { window.reasons++; return 'sabab'; }
    function ttToast(html, tone) { window.toasts.push({ html, tone }); }
    async function loadChangeRequests() {}
    async function loadBoard() { window.calls.push({ reload: 'board' }); }
    async function loadCyclePlan() { window.calls.push({ reload: 'cycle' }); }
    window.signals = [];      // [move, AbortSignal] — oldindan yuklash to'xtatildimi
    window.fail = null;       // (query) => xato matni (GET)
    async function api(url, method, body, opts = {}) {
        if (!method || method === 'GET') {
            const q = Object.fromEntries(new URL('http://x' + url).searchParams);
            window.calls.push({ get: q });
            if (opts.signal) window.signals.push([q.move, opts.signal]);
            if (window.delay) await new Promise(r => setTimeout(r, window.delay));
            const why = window.fail && window.fail(q);
            if (why) throw new Error(why);
            return JSON.parse(JSON.stringify(window.resp(q)));
        }
        window.calls.push({ post: url.replace(BASE + '/boards/3', ''), body: JSON.parse(JSON.stringify(body)) });
        return window.post ? window.post(url, body) : { ok: true, message: 'Ko‘chirildi.' };
    }
`;

(async () => {
    const browser = await chromium.launch();
    try {
        const page = async role => {
            const p = await (await browser.newContext({ viewport: { width: 1200, height: 900 } })).newPage();
            p.on('pageerror', e => { console.error('PAGEERROR', e.message); process.exitCode = 1; });
            const html = '<!doctype html><html><head><style>' + css + '</style></head><body></body></html>';
            await p.route('http://lms.test/**', r => r.fulfill({ contentType: 'text/html', body: html }));
            await p.goto('http://lms.test/');
            await p.addScriptTag({ content: stubs(role) + agenda + '\nwindow.T = { ttAgendaOpen, st: () => ttAgenda };' });
            return p;
        };
        const center = (p, sel) => p.$eval(sel, el => { const r = el.getBoundingClientRect(); return { x: r.left + r.width / 2, y: r.top + r.height / 2 }; });
        const free = (date, no) => '#ttAgendaModal [data-ag-free="' + date + '|' + no + '"]';
        const cls = (p, sel) => p.$eval(sel, el => el.className);
        // Sudrash: dars katakchasidan nishonga; drop=false — qo'yib yubormasdan (holatni tekshirish uchun).
        const dragTo = async (p, fromSel, toSel, { drop = true, wait = 60 } = {}) => {
            const a = await center(p, fromSel), b = await center(p, toSel);
            await p.mouse.move(a.x, a.y);
            await p.mouse.down();
            await p.mouse.move(a.x + 3, a.y + 2);     // 5px gacha — sudrash emas
            await p.mouse.move(b.x, b.y, { steps: 5 });
            await p.waitForTimeout(wait);
            if (drop) { await p.mouse.up(); await p.waitForTimeout(60); }
        };
        const tip = p => p.evaluate(() => { const t = document.getElementById('agDragTip'); return t ? { text: t.textContent, cls: t.className } : null; });
        const reset = p => p.evaluate(() => { window.calls = []; window.toasts = []; window.reasons = 0; });

        // 1) Kun: ko'chirsa bo'ladigan dars — data-ag-drag; sikl ma'ruzasi — sababi title da.
        const a = await page('admin');
        await a.evaluate(d => { window.resp = q => (q.move ? Object.assign({}, d, { move: window.MOVE }) : d); }, dayData());
        await a.evaluate(m => { window.MOVE = m; }, MOVE_W);
        await a.evaluate(() => T.ttAgendaOpen({ teacher_id: 7, teacher_name: 'MUX', date: '2026-11-19' }));
        assert.equal(await a.$eval('#ttAgendaModal .ag-e[data-ag-drag="w|5|10"]', el => getComputedStyle(el).cursor), 'grab');
        // Tooltip: to'liq ma'lumot (vaqt, fan, guruh, xona) va oxirida ko'chirib bo'lmaslik sababi.
        assert.ok((await a.$eval('#ttAgendaModal .ag-e:not([data-ag-drag])', el => el.title)).endsWith('\n' + M.movable_why));
        assert.equal(await a.$$eval('#ttAgendaModal [data-ag-free]', els => els.length), 4);

        // 2) Oddiy bosish (siljimasdan) — darsni ko'chiriladigan dars qiladi (variantlar so'raladi); 5px gacha siljish — sudrash emas,
        //    qayta so'rov yo'q (keshda).
        assert.match(await a.textContent('#ttAgendaModal [data-ag-head]'), /^Ko‘chirish uchun darsni bosing yoki sudrang\.$/);
        await reset(a);
        await a.click('#ttAgendaModal .ag-e[data-ag-drag]');
        await a.waitForTimeout(60);
        assert.deepEqual((await a.evaluate(() => window.calls)).map(c => c.get.move), ['w|5|10']);
        assert.equal(await a.evaluate(() => T.st().move), 'w|5|10');
        assert.match(await a.textContent('#ttAgendaModal [data-ag-head]'), /^Ko‘chiriladigan dars: Farmakologiya \(A\) — pp\/2024-01b, 19\.11 — bo‘sh paralardan birini tanlang \(shu hafta ichida\)\.$/);
        assert.equal(await a.$$eval('#ttAgendaModal [data-ag-pick]', els => els.length), 2);
        const c0 = await center(a, '#ttAgendaModal .ag-e[data-ag-drag]');
        await a.mouse.move(c0.x, c0.y);
        await a.mouse.down();
        await a.mouse.move(c0.x + 3, c0.y + 1, { steps: 2 });
        await a.mouse.up();
        await a.waitForTimeout(60);
        assert.equal((await a.evaluate(() => window.calls)).length, 1);
        assert.equal(await a.evaluate(() => document.getElementById('agDragTip')), null);
        // Qayta ochish — tanlov va kesh yo'q (keyingi stsenariylar uchun).
        await a.evaluate(() => T.ttAgendaOpen({ teacher_id: 7, teacher_name: 'MUX', date: '2026-11-19' }));
        await a.mouse.move(5, 5);

        // 3) Sudrash: move so'rovi (oyna qayta chizilmaydi), kutishda "tekshirilmoqda…"; keyin ranglar: II — yashil, V — qizil, VI — sariq.
        await a.evaluate(() => { window.delay = 300; });
        await dragTo(a, '#ttAgendaModal .ag-e[data-ag-drag="w|5|10"]', free('2026-11-19', 5), { drop: false, wait: 50 });
        assert.match((await tip(a)).text, /tekshirilmoqda…/);
        assert.ok(await a.$('#agDragTip .ag-spin'));   // aylanuvchi belgi
        assert.equal((await a.evaluate(() => window.calls))[0].get.move, 'w|5|10');
        await a.waitForTimeout(400);
        assert.match(await cls(a, free('2026-11-19', 2)), /\bis-ok\b/);
        assert.match(await cls(a, free('2026-11-19', 5)), /\bis-bad\b/);
        assert.match(await cls(a, free('2026-11-19', 6)), /\bis-warn\b/);
        let t = await tip(a);
        assert.match(t.text, /^Farmakologiya \(A\) → 19\.11 V para: Guruh band: pp\/2024-01b — Anatomiya$/);
        assert.match(t.cls, /is-bad/);
        // Oyna qayta chizilmagan (kataklar o'sha elementlar), mavjud tanlov yo'q.
        assert.equal(await a.evaluate(() => T.st().pick), null);
        // Qizilga tashlash — toast, hech narsa o'zgarmaydi.
        await a.mouse.up();
        await a.waitForTimeout(60);
        assert.match((await a.evaluate(() => window.toasts))[0].html, /^Ko‘chirib bo‘lmaydi: Guruh band/);
        assert.equal(await a.evaluate(() => T.st().pick), null);
        assert.equal(await a.$$eval('#ttAgendaModal .is-ok, #ttAgendaModal .is-bad, #ttAgendaModal .is-warn', els => els.length), 0);
        assert.equal(await a.evaluate(() => document.getElementById('agDragTip')), null);

        // 4) Sariq (VI — boshqa xona) ga tashlash — tasdiq paneli; keyingi haftalar tekshiruvi (rest_for) bilan qayta yuklash.
        await a.evaluate(() => { window.delay = 0; });
        await reset(a);
        await dragTo(a, '#ttAgendaModal .ag-e[data-ag-drag="w|5|10"]', free('2026-11-19', 6), { drop: false });
        t = await tip(a);
        assert.match(t.text, /→ 19\.11 VI para: ko‘chiriladi \(Xona sig'maydi: 10 o'rin, 12 talaba \(\+2\) · bo‘sh xona: 101-xona\)/);
        assert.match(t.cls, /is-warn/);
        await a.mouse.up();
        await a.waitForTimeout(80);
        assert.deepEqual(await a.evaluate(() => T.st().pick), { date: '2026-11-19', pair: 6, choice: 'one' });
        const gets = (await a.evaluate(() => window.calls)).filter(c => c.get);
        assert.equal(gets[gets.length - 1].get.rest_for, '2026-11-19|6');
        assert.match(await a.textContent('#ttAgendaModal .ag-pick'), /Farmakologiya \(A\) — pp\/2024-01b → 19\.11, VI para \(16:30–17:50\), xona: 101-xona/);
        assert.match(await a.textContent('#ttAgendaModal .ag-pick-btns'), /Qo‘llash/);
        // Sudrashdan keyingi "click" bosish sifatida ishlamaydi (panel yopilmaydi).
        assert.ok(await a.$('#ttAgendaModal .ag-pick'));

        // 5) Esc — sudrash bekor (oyna yopilmaydi, tanlov o'zgarmaydi).
        await a.evaluate(() => { T.st().pick = null; });
        await dragTo(a, '#ttAgendaModal .ag-e[data-ag-drag="w|5|10"]', free('2026-11-19', 2), { drop: false });
        assert.match(await cls(a, free('2026-11-19', 2)), /\bis-over\b/);
        await a.keyboard.press('Escape');
        assert.equal(await a.$$eval('#ttAgendaModal .is-ok, #ttAgendaModal .is-bad, #ttAgendaModal .is-warn, #ttAgendaModal .is-over', els => els.length), 0);
        await a.mouse.up();
        await a.waitForTimeout(60);
        assert.ok(await a.$('#ttAgendaModal'));
        assert.equal(await a.evaluate(() => T.st().pick), null);
        assert.equal(await a.evaluate(() => document.getElementById('agDragTip')), null);
        // Oynadan tashqariga (fon) tashlash — oyna yopilmaydi (sudrashdan keyingi "click" bosish emas).
        const box = await a.$eval('#ttAgendaModal .ag-box', el => { const r = el.getBoundingClientRect(); return { x: r.right + 60, y: r.top + 40 }; });
        const c1 = await center(a, '#ttAgendaModal .ag-e[data-ag-drag="w|5|10"]');
        await a.mouse.move(c1.x, c1.y);
        await a.mouse.down();
        await a.mouse.move(box.x, box.y, { steps: 6 });
        await a.mouse.up();
        await a.waitForTimeout(60);
        assert.ok(await a.$('#ttAgendaModal'));
        assert.equal(await a.evaluate(() => T.st().pick), null);

        // 6) Band katakka (darsli para) tashlash — toast.
        await reset(a);
        await dragTo(a, '#ttAgendaModal .ag-e[data-ag-drag="w|5|10"]', '#ttAgendaModal .ag-e:not([data-ag-drag])');
        assert.match((await a.evaluate(() => window.toasts))[0].html, /Bu para band/);
        assert.equal(await a.evaluate(() => T.st().pick), null);

        // 7) Hafta: boshqa kunga (server variant bergan) — yashil; variantsiz kun — qizil.
        await a.evaluate(d => { window.resp = q => (q.move ? Object.assign({}, d, { move: window.MOVE }) : d); }, weekData());
        await a.evaluate(() => { T.st().move = null; T.st().pick = null; });
        await a.click('#ttAgendaModal [data-ag="week"]');
        await a.waitForTimeout(60);
        await reset(a);
        await dragTo(a, '#ttAgendaModal .ag-e[data-ag-drag="w|5|10"]', free('2026-11-20', 1), { drop: false });
        assert.match(await cls(a, free('2026-11-20', 1)), /\bis-ok\b/);
        assert.match(await cls(a, free('2026-11-20', 2)), /\bis-bad\b/);
        assert.match((await tip(a)).text, /→ 20\.11 I para: ko‘chiriladi$/);
        await a.mouse.up();
        await a.waitForTimeout(80);
        assert.deepEqual(await a.evaluate(() => T.st().pick), { date: '2026-11-20', pair: 1, choice: 'one' });

        // 8) O'qituvchi: to'qnashuvdagi dars (apply_fix) — toza joy (fix) → "Joylash (darhol)", sababsiz week-suggestions/apply;
        //    toza emas (boshqa xona) → "So'rov yuborish", sabab bilan propose.
        const o = await page('oqituvchi');
        const fixMove = Object.assign({}, MOVE_W, { via: 'apply_fix', rest_weeks: [10],
            options: { '2026-11-19': Object.assign({}, MOVE_W.options['2026-11-19'], { 2: { ok: true, why: null, room: null, day: 4, pair: 3, fix: true } }) } });
        await o.evaluate(([d, m]) => { window.MOVE = m; window.resp = q => (q.move ? Object.assign({}, d, { move: window.MOVE }) : d); }, [dayData(), fixMove]);
        await o.evaluate(() => T.ttAgendaOpen({ teacher_id: 7, teacher_name: 'MUX', date: '2026-11-19' }));
        await dragTo(o, '#ttAgendaModal .ag-e[data-ag-drag="w|5|10"]', free('2026-11-19', 2), { drop: false });
        assert.match((await tip(o)).text, /→ 19\.11 II para: so‘rovsiz joylanadi$/);
        await o.mouse.up();
        await o.waitForTimeout(80);
        assert.match(await o.textContent('#ttAgendaModal .ag-pick'), /To‘qnashuvdagi dars — so‘rovsiz joylanadi/);
        assert.equal(await o.textContent('#ttAgendaModal [data-ag="submit"]'), 'Joylash (darhol)');
        await reset(o);
        await o.evaluate(() => { window.post = () => ({ ok: true, fixed: true, message: "To'qnashuvdagi dars so'rovsiz joylandi." }); });
        await o.click('#ttAgendaModal [data-ag="submit"]');
        await o.waitForTimeout(100);
        let posts = (await o.evaluate(() => window.calls)).filter(c => c.post);
        assert.equal(posts[0].post, '/week-suggestions/apply');
        assert.equal(posts[0].body.reason, undefined);
        assert.deepEqual(posts[0].body.actions, [{ op: 'move', card_id: 5, week: 10, day: 4, pair: 3 }]);
        assert.equal(await o.evaluate(() => window.reasons), 0);
        assert.match((await o.evaluate(() => window.toasts))[0].html, /so'rovsiz joylandi/);
        // VI para (boshqa xona) — fix emas: so'rov.
        await o.evaluate(() => { T.st().move = null; });
        await dragTo(o, '#ttAgendaModal .ag-e[data-ag-drag="w|5|10"]', free('2026-11-19', 6));
        assert.equal(await o.textContent('#ttAgendaModal [data-ag="submit"]'), 'So‘rov yuborish');
        assert.equal(await o.$('#ttAgendaModal [data-ag-fix]'), null);
        await reset(o);
        await o.evaluate(() => { window.post = () => ({ ok: true, pending_approval: true, message: "So'rov yuborildi." }); });
        await o.click('#ttAgendaModal [data-ag="submit"]');
        await o.waitForTimeout(100);
        posts = (await o.evaluate(() => window.calls)).filter(c => c.post);
        assert.equal(posts[0].post, '/week-suggestions/propose');
        assert.equal(posts[0].body.reason, 'sabab');
        assert.equal(await o.evaluate(() => window.reasons), 1);
        // Server "propose" desa (variantda fix bo'lsa ham) — so'rov (oyna qayta ochiladi: variantlar keshi — ko'chirish / sana almashguncha).
        await o.evaluate(() => { window.MOVE = Object.assign({}, window.MOVE, { via: 'propose' }); });
        await o.evaluate(() => T.ttAgendaOpen({ teacher_id: 7, teacher_name: 'MUX', date: '2026-11-19' }));
        await dragTo(o, '#ttAgendaModal .ag-e[data-ag-drag="w|5|10"]', free('2026-11-19', 2));
        assert.equal(await o.textContent('#ttAgendaModal [data-ag="submit"]'), 'So‘rov yuborish');
        assert.equal(await o.$('#ttAgendaModal [data-ag-fix]'), null);

        // 9) Sikl (apply_fix, shu kun) — cycle-time-proposal sababsiz (server har kunni o'zi ajratadi).
        const C = { key: 'c|K|2026-11-19', kind: 'cycle', type: 'A', subject: 'Klinik farmakologiya', groups: ['d1/d22-01b'], room: null, conflict: true,
            hidden: false, movable: true, movable_why: null, date: '2026-11-19', from: 1, to: 3, start: '08:30', end: '09:50' };
        const cycMove = { key: C.key, kind: 'cycle', label: 'Klinik farmakologiya (A) — d1/d22-01b', date: '2026-11-19', via: 'apply_fix', why: null,
            unit: { key: 'K', unit: 'd1/d22-01b', specialty_name: 'davolash ishi', course: 5, subject_name: 'Klinik farmakologiya', group_name: 'd1/d22-01b' },
            options: { '2026-11-19': { 5: { ok: true, why: null, room: null, start_hour: 9, fix: true } } }, block: {}, block_date: null, block_days: [] };
        await o.evaluate(([d, m]) => { window.MOVE = m; window.resp = q => (q.move ? Object.assign({}, d, { move: window.MOVE }) : d); },
            [dayData({ entries: [C], free: { '2026-11-19': [5] } }), cycMove]);
        await o.evaluate(() => T.ttAgendaOpen({ teacher_id: 7, teacher_name: 'MUX', date: '2026-11-19' }));
        await dragTo(o, '#ttAgendaModal .ag-e[data-ag-drag="c|K|2026-11-19"]', free('2026-11-19', 5));
        assert.equal(await o.textContent('#ttAgendaModal [data-ag="submit"]'), 'Joylash (darhol)');
        await reset(o);
        await o.evaluate(() => { window.post = () => ({ ok: true, applied_days: ['2026-11-19'], proposed_request_id: null,
            message: "1 kun darhol joylandi (to'qnashuvdagi dars — so'rovsiz)." }); });
        await o.click('#ttAgendaModal [data-ag="submit"]');
        await o.waitForTimeout(100);
        posts = (await o.evaluate(() => window.calls)).filter(c => c.post);
        assert.equal(posts[0].post, '/cycle-time-proposal');
        assert.equal(posts[0].body.reason, undefined);
        assert.equal(posts[0].body.start_hour, 9);
        assert.match((await o.evaluate(() => window.toasts))[0].html, /1 kun darhol joylandi/);

        // 10) Xodim: bugungi dars ko'chirilsa — tasdiq panelida HEMIS eslatmasi.
        const h = await page('registrator_ofisi');
        const todayMove = Object.assign({}, MOVE_W, { rest_weeks: [10] });
        await h.evaluate(([d, m]) => { window.MOVE = m; window.resp = q => (q.move ? Object.assign({}, d, { move: window.MOVE }) : d); },
            [dayData({ today: '2026-11-19' }), todayMove]);
        await h.evaluate(() => T.ttAgendaOpen({ teacher_id: 7, teacher_name: 'MUX', date: '2026-11-19' }));
        await dragTo(h, '#ttAgendaModal .ag-e[data-ag-drag="w|5|10"]', free('2026-11-19', 2));
        assert.match(await h.textContent('#ttAgendaModal [data-ag-hemis]'), /«Bugungi darslar \(HEMIS\)»/);
        await reset(h);
        await h.evaluate(() => { window.post = () => ({ ok: true, today_moved: true, hemis_hint: 'HEMIS eslatmasi' }); });
        await h.click('#ttAgendaModal [data-ag="submit"]');
        await h.waitForTimeout(100);
        assert.match((await h.evaluate(() => window.toasts))[0].html, /data-hemis-hint>HEMIS eslatmasi/);
        // Kelgusi kunga — eslatma yo'q.
        await h.evaluate(d => { window.resp = q => (q.move ? Object.assign({}, d, { move: window.MOVE }) : d); }, dayData({ today: '2026-11-05' }));
        await h.evaluate(() => T.ttAgendaOpen({ teacher_id: 7, teacher_name: 'MUX', date: '2026-11-19' }));
        await dragTo(h, '#ttAgendaModal .ag-e[data-ag-drag="w|5|10"]', free('2026-11-19', 2));
        assert.equal(await h.$('#ttAgendaModal [data-ag-hemis]'), null);

        // 11) Javob kelmasdan tashlash: katak "tekshirilmoqda…", javob kelgach — tasdiq paneli (II — yashil); V (qizil) — toast.
        const w2 = Object.assign({}, W, { key: 'w|6|10', subject: 'Gistologiya', groups: ['pp/2024-01b'] });
        const pg = await page('admin');
        const conflictDay = dayData({ entries: [M, W, w2] });
        await pg.evaluate(([d, m]) => { window.MOVE = m; window.resp = q => (q.move ? Object.assign({}, d, { move: Object.assign({}, window.MOVE, { key: q.move,
            label: q.move === 'w|6|10' ? 'Gistologiya (A) — pp/2024-01b' : window.MOVE.label, rest_weeks: [10] }) }) : d); }, [conflictDay, MOVE_W]);
        await pg.evaluate(() => T.ttAgendaOpen({ teacher_id: 7, teacher_name: 'MUX', date: '2026-11-19' }));
        // ↕ — to'qnashuvdagi ko'chirsa bo'ladigan ikkala darsda; ko'chirib bo'lmaydiganida yo'q.
        assert.deepEqual(await pg.$$eval('#ttAgendaModal .ag-e', els => els.map(el => [el.dataset.agDrag || null, !!el.querySelector('.ag-mvicon')])),
            [[null, false], ['w|5|10', true], ['w|6|10', true]]);
        await pg.evaluate(() => { window.delay = 300; window.calls = []; });
        await dragTo(pg, '#ttAgendaModal .ag-e[data-ag-drag="w|5|10"]', free('2026-11-19', 2), { wait: 20 });
        assert.match(await cls(pg, free('2026-11-19', 2)), /\bis-pending\b/);
        assert.match(await pg.textContent(free('2026-11-19', 2)), /tekshirilmoqda…/);
        assert.deepEqual(await pg.evaluate(() => window.toasts), []);
        await pg.waitForTimeout(400);
        assert.deepEqual(await pg.evaluate(() => T.st().pick), { date: '2026-11-19', pair: 2, choice: 'one' });
        assert.equal(await pg.$('#ttAgendaModal .is-pending'), null);
        assert.match(await pg.textContent('#ttAgendaModal .ag-pick'), /Farmakologiya \(A\) — pp\/2024-01b → 19\.11, II para/);
        // Qizil katakka (javobdan oldin) — javob kelgach toast, tanlov yo'q.
        await pg.evaluate(() => { T.st().pick = null; T.st().move = null; T.st().data.move = null; window.toasts = []; });
        await pg.evaluate(() => T.ttAgendaOpen({ teacher_id: 7, teacher_name: 'MUX', date: '2026-11-19' }));
        await dragTo(pg, '#ttAgendaModal .ag-e[data-ag-drag="w|5|10"]', free('2026-11-19', 5), { wait: 20 });
        assert.match(await cls(pg, free('2026-11-19', 5)), /\bis-pending\b/);
        await pg.waitForTimeout(400);
        assert.match((await pg.evaluate(() => window.toasts))[0].html, /^Ko‘chirib bo‘lmaydi: Guruh band/);
        assert.equal(await pg.evaluate(() => T.st().pick), null);
        assert.equal(await pg.$('#ttAgendaModal .is-pending'), null);

        // 12) Kutilayotgan tashlash — Esc bekor qiladi (oyna yopilmaydi); javob kelsa ham davom etmaydi. Xato — sababi bilan toast.
        await pg.evaluate(() => T.ttAgendaOpen({ teacher_id: 7, teacher_name: 'MUX', date: '2026-11-19' }));
        await pg.evaluate(() => { window.toasts = []; });
        await dragTo(pg, '#ttAgendaModal .ag-e[data-ag-drag="w|5|10"]', free('2026-11-19', 2), { wait: 20 });
        assert.ok(await pg.$('#ttAgendaModal .is-pending'));
        await pg.keyboard.press('Escape');
        assert.equal(await pg.$('#ttAgendaModal .is-pending'), null);
        assert.ok(await pg.$('#ttAgendaModal'));
        await pg.waitForTimeout(400);
        assert.equal(await pg.evaluate(() => T.st().pick), null);
        assert.deepEqual(await pg.evaluate(() => window.toasts), []);
        await pg.evaluate(() => T.ttAgendaOpen({ teacher_id: 7, teacher_name: 'MUX', date: '2026-11-19' }));
        await pg.evaluate(() => { window.fail = q => (q.move ? 'Server javob bermadi (vaqt tugadi).' : null); });
        await dragTo(pg, '#ttAgendaModal .ag-e[data-ag-drag="w|5|10"]', free('2026-11-19', 2), { wait: 20 });
        await pg.waitForTimeout(400);
        assert.match((await pg.evaluate(() => window.toasts))[0].html, /^Tekshirib bo‘lmadi: Server javob bermadi \(vaqt tugadi\)\.$/);
        assert.equal(await pg.$('#ttAgendaModal .is-pending'), null);
        assert.equal(await pg.evaluate(() => T.st().pick), null);
        await pg.evaluate(() => { window.fail = null; });

        // 13) Yangi sudrash kutilayotganining o'rnini egallaydi: birinchisi (II) davom etmaydi, ikkinchisi (VI) — tasdiq paneli.
        await pg.evaluate(() => T.ttAgendaOpen({ teacher_id: 7, teacher_name: 'MUX', date: '2026-11-19' }));
        await pg.evaluate(() => { window.delay = 1500; });
        await dragTo(pg, '#ttAgendaModal .ag-e[data-ag-drag="w|5|10"]', free('2026-11-19', 2), { wait: 20 });
        await dragTo(pg, '#ttAgendaModal .ag-e[data-ag-drag="w|5|10"]', free('2026-11-19', 6), { wait: 20 });
        assert.deepEqual(await pg.$$eval('#ttAgendaModal .is-pending', els => els.map(el => el.dataset.agFree)), ['2026-11-19|6']);
        await pg.waitForTimeout(1600);
        assert.deepEqual(await pg.evaluate(() => T.st().pick), { date: '2026-11-19', pair: 6, choice: 'one' });

        // 14) Oldindan yuklash: dars ustida 150 ms — variantlar keshga; keyin sudrash — kutishsiz (ranglar darhol), yangi so'rov yo'q.
        //     Ikkinchi dars ustiga o'tilsa — birinchisining so'rovi to'xtatiladi (bir vaqtda bittasi).
        await pg.evaluate(() => T.ttAgendaOpen({ teacher_id: 7, teacher_name: 'MUX', date: '2026-11-19' }));
        await pg.evaluate(() => { window.delay = 0; window.calls = []; window.signals = []; });
        const h1 = await center(pg, '#ttAgendaModal .ag-e[data-ag-drag="w|5|10"]');
        await pg.mouse.move(h1.x, h1.y);
        await pg.waitForTimeout(250);
        assert.deepEqual((await pg.evaluate(() => window.calls)).map(c => c.get.move), ['w|5|10']);
        await dragTo(pg, '#ttAgendaModal .ag-e[data-ag-drag="w|5|10"]', free('2026-11-19', 2), { drop: false, wait: 10 });
        assert.match(await cls(pg, free('2026-11-19', 2)), /\bis-ok\b/);
        assert.doesNotMatch((await tip(pg)).text, /tekshirilmoqda/);
        await pg.keyboard.press('Escape');
        await pg.mouse.up();
        assert.equal((await pg.evaluate(() => window.calls)).length, 1);
        await pg.evaluate(() => T.ttAgendaOpen({ teacher_id: 7, teacher_name: 'MUX', date: '2026-11-19' }));
        await pg.evaluate(() => { window.delay = 500; window.calls = []; window.signals = []; });
        const h2 = await center(pg, '#ttAgendaModal .ag-e[data-ag-drag="w|6|10"]');
        await pg.mouse.move(h1.x, h1.y);
        await pg.waitForTimeout(200);
        await pg.mouse.move(h2.x, h2.y);
        await pg.waitForTimeout(200);
        assert.deepEqual(await pg.evaluate(() => window.signals.map(([m, sig]) => [m, sig.aborted])), [['w|5|10', true], ['w|6|10', false]]);
        await pg.waitForTimeout(600);

        // 15) To'qnashuvdagi ikkinchi darsni bosish — u tanlanadi: kutishda "tekshirilmoqda…", keyin uning "→ ko'chirish" tugmalari;
        //     "ko'chiriladi" belgisi shu darsga o'tadi. Oyna move bilan ochilsa — to'qnashuvdagi ikkinchi dars oldindan yuklanadi.
        await pg.evaluate(() => { window.delay = 200; window.calls = []; });
        await pg.evaluate(() => T.ttAgendaOpen({ teacher_id: 7, teacher_name: 'MUX', date: '2026-11-19', move: 'w|5|10' }));
        await pg.mouse.move(5, 5);
        await pg.waitForTimeout(300);
        assert.deepEqual((await pg.evaluate(() => window.calls)).map(c => c.get.move), ['w|5|10', 'w|6|10']);
        assert.match(await pg.textContent('#ttAgendaModal [data-ag-head]'), /Boshqa darsni tanlash uchun uni bosing yoki sudrang\.$/);
        await pg.evaluate(() => { window.calls = []; });
        await pg.click('#ttAgendaModal .ag-e[data-ag-drag="w|6|10"]');
        await pg.waitForTimeout(30);
        assert.equal(await pg.evaluate(() => T.st().move), 'w|6|10');
        assert.match(await pg.textContent('#ttAgendaModal [data-ag-head]'), /^Ko‘chiriladigan dars: Gistologiya \(A\) — pp\/2024-01b, 19\.11 — bo‘sh paralardan birini tanlang/);
        assert.deepEqual(await pg.evaluate(() => window.calls), []);   // oldindan yuklangan — so'rovsiz
        assert.deepEqual(await pg.$$eval('#ttAgendaModal .ag-moving', els => els.map(el => el.dataset.agDrag)), ['w|6|10']);
        // Keshda yo'q dars (qayta ochilgan oyna, oldindan yuklashsiz): kutishda "tekshirilmoqda…", tugmalarsiz; keyin — variantlar.
        await pg.evaluate(() => T.ttAgendaOpen({ teacher_id: 7, teacher_name: 'MUX', date: '2026-11-19' }));
        await pg.evaluate(() => { window.calls = []; });
        await pg.click('#ttAgendaModal .ag-e[data-ag-drag="w|6|10"]');
        await pg.waitForTimeout(30);
        assert.match(await pg.textContent('#ttAgendaModal [data-ag-head]'), /^Ko‘chiriladigan dars: Gistologiya \(A\) — pp\/2024-01b, 19\.11 — tekshirilmoqda…/);
        assert.ok(await pg.$('#ttAgendaModal [data-ag-head] .ag-spin'));
        assert.equal(await pg.$$eval('#ttAgendaModal [data-ag-pick]', els => els.length), 0);
        await pg.waitForTimeout(300);
        assert.match(await pg.textContent('#ttAgendaModal [data-ag-head]'), /Gistologiya \(A\) — pp\/2024-01b, 19\.11 — bo‘sh paralardan birini tanlang/);
        assert.equal(await pg.$$eval('#ttAgendaModal [data-ag-pick]', els => els.length), 2);
        assert.deepEqual((await pg.evaluate(() => window.calls)).map(c => c.get.move), ['w|6|10']);

        console.log('timetable agenda drag: 15 scenarios passed');
    } finally {
        await browser.close();
    }
})().catch(e => { console.error(e); process.exit(1); });
