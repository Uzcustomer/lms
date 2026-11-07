const fs = require('node:fs');
const assert = require('node:assert/strict');
const path = require('node:path');
// Fan mas'uli "Joylash uchun tanlash": mas'ul fani kartasini (yoki "Joylash"ni) bosish / paneldan sudrash → kataklar rangi serverdan BITTA
// so'rov bilan (responsible-move/grid; "karta|hafta" keshi): yashil — joylash mumkin (eng yaqin bo'sh xona title'da), to'q qizil — o'qituvchi,
// och qizil — guruh, sariq — mos bo'sh xona yo'q (bosiladi, xona majburiy), kulrang — bugun / o'tgan kun. Yashil katak bosilsa / unga
// tashlansa — katak yonida tasdiq oynasi (openResponsibleMove pick rejimi): vaqt, xona (best_room, "o'zgartirish ▾"), "Joylash" →
// responsible-move; joylangach — rejim tugaydi, doska qayta yuklanadi, "Joylandi (faqat shu hafta)". "Eng yaxshi joylar", Esc, ma'ruza.
const { chromium } = require('playwright');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');
const cut = (from, to) => {
    const a = source.indexOf(from), b = source.indexOf(to, a);
    assert(a > 0 && b > a, from);
    return source.slice(a, b);
};
const css = cut('<style>', '</style>').slice('<style>'.length);
const fm = cut("            // ===== Fan mas'uli: \"Joylash uchun tanlash\" — mas'ul fani kartasini", "            // ===== Fan mas'uli: mas'ul fani darsining vaqti va xonasi");
const move = cut("            // ===== Fan mas'uli: mas'ul fani darsining vaqti va xonasi", "            // ===== Kartochka modali (o'qituvchi/auditoriya) =====");
const search = cut('            const UZ_CYR_LAT = {', "            // Ma'ruza yorlig'i:");
const capText = cut('            const cfCapText = ', '            const cfDate = ');

// 1) Sahifaga ulanish (renderGrid / panel / sudrash) — asl koddagi bog'lanishlar.
const grid = cut('            function renderGrid() {', '            // Teachers propose a time;');
assert.match(grid, /^\s+function renderGrid\(\) \{\n\s+fmSync\(\);\n\s+fmRenderBar\(\);/);
assert.match(grid, /\} else if \(fmPick && cardGKeys\(fmPick\.card\)\.includes\(grp\)\) \{[\s\S]*?const fc = fmCell\(d, p\);[\s\S]*?fmCellAttrs\(fc, d, p\)/);
assert.match(grid, /querySelectorAll\('#grid \[data-fm-place\]'\)\.forEach\(td => td\.onclick = \(\) => \{[\s\S]*?fmOpenConfirm\(d, p, td\);/);
assert.match(grid, /if \(fmDragCard\) \{ ev\.preventDefault\(\); ev\.dataTransfer\.dropEffect = 'move'; fmDragPaint\(td, ev\); return; \}/);
assert.match(grid, /if \(fmDragCard\) \{ fmDrop\(td, ev\); return; \}/);
const panel = cut('            function renderPanel() {', '            // Ko\'rinadigan sikl darslari');
assert.match(panel, /const fmMine = c => TIMETABLE_ROLE === 'fan_masuli' && mySubjectCardIds\.has\(\+c\.id\) \? 1 : 0;/);
assert.match(panel, /const bySubject = \(a, b\) => \(fmMine\(b\) - fmMine\(a\)\) \|\|/);   // mas'ul fan kartalari tepada
assert.match(panel, /data-fm-place-btn="' \+ c\.id \+ '"[^>]*>Joylash<\/button>/);
// "Joylash" tugmasi — faqat amaliy (ma'ruzada yo'q).
assert.match(panel, /\(!skip && fmMine\(c\) && c\.training_type !== 'lecture' \? '<button type="button" class="pn-fm-place" data-fm-place-btn=/);
// "Faqat shu guruh": rejimda ustunlar — kartaning fakulteti / yo'nalishi / kursi (buildGroupRows).
const rowsFn = cut('            function buildGroupRows() {', '            // Karta uzunligi yarim-slot birligida');
assert.match(rowsFn, /if \(fmPick && fmOwnOnly\) \{\n\s+const fc = fmPick\.card;\n\s+groupRows = groupRows\.filter\(r => r\.faculty === \(fc\.faculty_name \|\| ''\) && r\.specialty === \(fc\.specialty_name \|\| ''\) && \+r\.course === \+fc\.course\);/);
assert.match(source, /let fmOwnOnly = true;/);
assert.match(panel, /if \(fmMine\(c\)\) \{ fmPickStart\(c\); return; \}/);
assert.match(panel, /if \(TIMETABLE_ROLE === 'fan_masuli'\) \{ fmDragStart\(c, ev\); return; \}/);
// "Mas'ul fanlarim" panel filtri — fan mas'ulida sukut bo'yicha yoqilgan.
assert.match(source, /@if\(\$timetableActiveRole === 'fan_masuli'\)\n\s+<label[^>]*><input type="checkbox" id="pnMine" class="rounded border-gray-300" checked> Mas'ul fanlarim<\/label>/);
assert.match(source, /const pnFilter = \{ subject: '', oqim: '', groups: new Set\(\), type: '', mine: TIMETABLE_ROLE === 'fan_masuli' \};/);
assert.match(source, /\(!pnFilter\.mine \|\| mySubjectCardIds\.has\(\+c\.id\)\) &&/);

const page0 = `<!doctype html><html><head><style>${css}</style></head><body>
<select id="weekSel"><option value="10">10</option><option value="11">11</option><option value="12">12</option></select>
<div id="ttFmPick" hidden></div><table id="grid"><tbody></tbody></table><script>
    const BASE = '/admin/timetable';
    const board = { id: 7, days: 6, pairs_per_day: 12 };
    const TIMETABLE_ROLE = 'fan_masuli';
    const esc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    const $ = id => document.getElementById(id);
    let curWeek = 10, weeklyLayer = 'internal', viewMode = 'teacher';
    let fmPick = null, fmDragCard = null, fmOwnOnly = true;
    // Avtomatik surish: qaysi katakka (kun-qator) va qanday.
    window.scrolls = [];
    Element.prototype.scrollIntoView = function (o) { window.scrolls.push([this.dataset.day + '-' + this.dataset.pair, o && o.block, o && o.inline]); };
    document.getElementById('weekSel').onchange = function () { curWeek = +this.value; window.log.push('week:' + curWeek); renderAll(); };
    const fmGridCache = new Map();
    const mySubjectCardIds = new Set([1, 2, 5]);
    const selectedFaculties = new Set(), selectedDirs = new Set(), selectedCourses = new Set();
    window.log = []; window.calls = []; window.toasts = []; window.next = [];
    const reconcileSelection = () => window.log.push('reconcile'), fillSelectors = () => window.log.push('selectors'),
        fillGridInputs = () => {}, buildGroupRows = () => window.log.push('rows');
    const renderAll = () => { window.log.push('all'); renderGrid(); }, renderPanel = () => window.log.push('panel');
    const boardDayNames = () => ['Dushanba', 'Seshanba', 'Chorshanba', 'Payshanba', 'Juma', 'Shanba'];
    const boardSchedule = () => Array.from({ length: 12 }, (_, i) => ({ type: 'pair', start: String(8 + i).padStart(2, '0') + ':00' }));
    const cardLen = c => Math.max(1, parseInt(c.len_half) || 2);
    const unitLen = list => list.reduce((s, c) => s + cardLen(c), 0);
    window.units = { 1: [{ id: 1, len_half: 2 }, { id: 3, len_half: 2 }] };
    const unitOf = c => (window.units[c.id] || [c]).map(x => x.id === c.id ? c : Object.assign({}, c, x));
    const effPlace = c => c.day ? { day: c.day, pair: c.pair } : null;
    const cardGKeys = c => ['F|S|2¦' + c.group_name];
    const slotRangeText = (p, len) => String(7 + p).padStart(2, '0') + ':00–' + String(7 + p + len).padStart(2, '0') + ':00';
    const dropSlotAt = td => +td.dataset.pair;
    const DRAG_TONES = ['drag-ok', 'drag-bad', 'drag-bad-teacher', 'drag-bad-group', 'drag-bad-room', 'drag-bad-closed'];
    const dragTip = (ev, plan) => { window.tip = plan ? plan.errs.length + ':' + plan.start + '+' + plan.len : null; };
    const ttToast = (h, t) => window.toasts.push([h, t]);
    const loadBoard = async () => { window.log.push('loadBoard'); };
    const loadChangeRequests = async () => {};
    async function api(url, method = 'GET', body = null) {
        window.calls.push({ url, method, body: body ? JSON.parse(JSON.stringify(body)) : null });
        const r = window.next.length ? window.next.shift() : { status: 200, j: { ok: true, mode: 'fix', message: 'Darhol.', rooms: [] } };
        if (r.status >= 400) { const e = new Error(r.j.message || r.j.error); e.status = r.status; e.data = r.j; throw e; }
        return r.j;
    }
${search}
${capText}
    // Jadvalning soddalashtirilgan nusxasi (renderGrid'dagi fan mas'uli tarmog'i va bog'lanishlari bilan bir xil): 2 kun × 4 qator.
    function renderGrid() {
        fmSync();
        fmRenderBar();
        window.log.push('grid');
        let h = '';
        for (let p = 1; p <= 4; p++) {
            h += '<tr>';
            for (let d = 1; d <= 2; d++) {
                let cls = 'tt-cell', clickable = '';
                const grp = 'F|S|2¦pp/2024-01g';
                if (fmPick && cardGKeys(fmPick.card).includes(grp)) {
                    const fc = fmCell(d, p);
                    if (fc) { const t = fmCellAttrs(fc, d, p); cls += t.cls; clickable = t.attrs; }
                }
                h += '<td class="' + cls + '" data-day="' + d + '" data-pair="' + p + '" data-g="' + esc(grp) + '"' + clickable + '></td>';
            }
            h += '</tr>';
        }
        $('grid').innerHTML = '<tbody>' + h + '</tbody>';
        document.querySelectorAll('#grid [data-fm-place]').forEach(td => td.onclick = () => {
            if (!fmPick) return;
            const [d, p] = td.dataset.fmPlace.split('-').map(Number);
            fmOpenConfirm(d, p, td);
        });
        document.querySelectorAll('#grid td[data-day]').forEach(td => {
            td.addEventListener('dragover', ev => { if (fmDragCard) { ev.preventDefault(); ev.dataTransfer.dropEffect = 'move'; fmDragPaint(td, ev); return; } });
            td.addEventListener('drop', ev => { ev.preventDefault(); td.classList.remove(...DRAG_TONES); if (fmDragCard) { fmDrop(td, ev); return; } });
        });
    }
${fm}
${move}
    Object.assign(window, { fmPickStart, fmPickStop, fmDragStart, renderGrid, openResponsibleMove,
        state: () => ({ pick: fmPick ? { id: fmPick.card.id, week: fmPick.week, loaded: !!fmPick.data } : null, view: viewMode, cache: [...fmGridCache.keys()],
            dirs: [...selectedDirs], drag: fmDragCard ? fmDragCard.id : null, own: fmOwnOnly }),
        setWeek: w => { curWeek = w; }, clearCache: () => fmGridCache.clear() });
</script></body></html>`;

const ROOM = { code: 'R400', name: '400-amaliyot', why: 'shu bino' };
const cells = {
    '1-1': { state: 'closed', reason: "Bugungi va o'tgan kunlar — yopiq (16.11)." }, '1-2': { state: 'closed', reason: "Bugungi va o'tgan kunlar — yopiq (16.11)." },
    '1-3': { state: 'closed', reason: 'x' }, '1-4': { state: 'closed', reason: 'x' },
    '2-1': { state: 'ok', reason: null, best_room: ROOM },
    '2-2': { state: 'teacher', reason: "O'qituvchi band: USTOZ — Tarix", causes: { 41: 'teacher' } },
    '2-3': { state: 'group', reason: 'Guruh band: pp/2024-01g — Anatomiya', causes: { 42: 'group' } },
    '2-4': { state: 'room', reason: "Vaqt bo'sh, lekin mos bo'sh xona yo'q — xona tanlang." },
};
const GRID = { ok: true, week: 10, days: 2, pairs: 4, len: 4, closed: null, cells,
    parts: [{ card_id: 1, len_half: 2, offset: 0 }, { card_id: 3, len_half: 2, offset: 2 }],
    best: [{ day: 2, pair: 1, date: '2026-11-17', label: 'Seshanba, 17.11 08:30–11:20', room: ROOM, why: "guruhning shu kungi darsiga ulashgan · o'qituvchi shu kuni bo'sh" }] };
const CHECK_ROOMS = [{ code: 'R400', name: '400-amaliyot', volume: 30, free: true, current: false, why: 'shu bino', kind: null, busy_items: [] },
    { code: 'R401', name: '401-amaliyot', volume: 30, free: true, current: false, why: 'shu qavat', kind: null, busy_items: [] },
    { code: 'R402', name: '402-amaliyot', volume: 30, free: false, current: false, why: null, kind: 'busy', busy_items: [] }];
const card = { id: 1, subject_name: 'Farmakologiya', group_name: 'pp/2024-01g', training_type: 'practice', len_half: 2, day: null, pair: null,
    faculty_name: 'F2', specialty_name: 'pediatriya', course: 2, auditorium_code: 'R400', auditorium_name: '400-amaliyot' };

(async () => {
    const browser = await chromium.launch();
    const page = await browser.newPage();
    page.on('pageerror', e => { throw e; });
    await page.setContent(page0);
    const calls = () => page.evaluate(() => window.calls);
    const state = () => page.evaluate(() => window.state());
    const cellCls = (d, p) => page.getAttribute('#grid td[data-day="' + d + '"][data-pair="' + p + '"]', 'class');
    const cellAttr = (d, p, a) => page.getAttribute('#grid td[data-day="' + d + '"][data-pair="' + p + '"]', a);

    // 2) "Joylash": guruh kesimiga o'tiladi (kartaning yo'nalishi tanlanadi), kataklar — bitta GET (grid) bilan.
    await page.evaluate(([g, c]) => { window.next = [{ status: 200, j: g }]; window.fmPickStart(c); }, [GRID, card]);
    await page.waitForFunction(() => window.state().pick && window.state().pick.loaded);
    let st = await state();
    assert.deepEqual(st.pick, { id: 1, week: 10, loaded: true });
    assert.equal(st.view, 'group');
    assert.deepEqual(st.dirs, ['pediatriya']);
    assert.deepEqual((await calls()).map(x => [x.method, x.url]), [['GET', '/admin/timetable/cards/1/responsible-move/grid?week=10']]);
    // Grid kelgach — birinchi yashil katakka (kartaning guruh ustunida) avtomatik surildi, markazga.
    assert.deepEqual(await page.evaluate(() => window.scrolls), [['2-1', 'center', 'center']]);
    // "Faqat shu guruh" — sukut bo'yicha yoqiq; o'chirilsa — ustunlar qayta quriladi.
    assert.equal(await page.isChecked('#ttFmPick [data-fm-own]'), true);
    await page.uncheck('#ttFmPick [data-fm-own]');
    assert.equal((await state()).own, false);
    assert.equal(await page.evaluate(() => window.log.at(-2)), 'rows');
    await page.check('#ttFmPick [data-fm-own]');
    assert.equal((await state()).own, true);
    assert.match(await cellCls(2, 1), /tt-ok/);
    assert.equal(await cellAttr(2, 1, 'title'), 'Joylash mumkin · xona: 400-amaliyot (shu bino)');
    assert.match(await cellCls(2, 2), /tt-bad tt-bad-teacher/);
    assert.equal(await cellAttr(2, 2, 'data-bad-ids'), '41:teacher');
    assert.match(await cellAttr(2, 2, 'title'), /O'qituvchi band: USTOZ/);
    assert.match(await cellCls(2, 3), /tt-bad-group/);
    assert.match(await cellCls(2, 4), /tt-bad-room/);
    assert.equal(await cellAttr(2, 4, 'data-fm-place'), '2-4');   // sariq — bosiladi (xona tanlab)
    assert.match(await cellCls(1, 1), /tt-closed/);
    assert.equal(await cellAttr(1, 1, 'data-fm-place'), null);
    assert.equal(await cellAttr(2, 2, 'data-fm-place'), null);
    // Holat qatori: nima joylanmoqda, yashil joylar soni, ranglar izohi, "Eng yaxshi joylar".
    const barText = () => page.evaluate(() => document.getElementById('ttFmPick').textContent.replace(/\s+/g, ' '));
    assert.match(await barText(), /Joylash: Farmakologiya · pp\/2024-01g · 4 soat \(2 \+ 2\) · 10-hafta — yashil kataklardan birini bosing yoki kartani unga sudrang — 1 ta joy/);
    assert.match(await barText(), /kulrang — bugun \/ o‘tgan kun yoki sig‘maydi/);

    // 3) Ikkinchi marta bosish — rejim o'chadi; qayta yoqilsa — keshdan (yangi so'rov yo'q).
    await page.evaluate(c => window.fmPickStart(c), card);
    assert.equal((await state()).pick, null);
    assert.equal(await page.isHidden('#ttFmPick'), true);
    assert.doesNotMatch(await cellCls(2, 1), /tt-ok/);
    await page.evaluate(c => window.fmPickStart(c), card);
    assert.deepEqual((await state()).pick, { id: 1, week: 10, loaded: true });
    assert.equal((await calls()).length, 1);

    // 4) Yashil katak bosilsa — katak yonida tasdiq oynasi: vaqt, xona (best_room) "o'zgartirish ▾", "Joylash" → responsible-move.
    await page.evaluate(rooms => { window.next = [{ status: 200, j: { ok: true, mode: 'fix', message: 'Darhol.', new: 'Seshanba, 17.11.2026 08:30–11:20', rooms } }]; }, CHECK_ROOMS);
    await page.click('#grid td[data-day="2"][data-pair="1"]');
    await page.waitForFunction(() => document.querySelector('#respMove [type="submit"]').textContent === 'Joylash');
    assert.match(await page.getAttribute('#respMove', 'class'), /rm-pop/);
    assert.equal(await page.innerText('#respMove [data-rmv-when]'), 'Seshanba, 17.11.2026 08:30–11:20');
    assert.equal(await page.innerText('#respMove [data-rmv-pick]'), '400-amaliyot (30 o‘rin) — shu bino · o‘zgartirish ▾');
    assert.equal(await page.isVisible('#respMove select[name="day"]'), false);
    assert.equal(await page.isVisible('#respMove textarea[name="reason"]'), false);
    let c0 = (await calls()).at(-1);
    assert.deepEqual([c0.method, c0.url, c0.body], ['POST', '/admin/timetable/cards/1/responsible-move/check', { week: 10, day: 2, pair: 1, auditorium_code: 'R400' }]);
    const box = await page.locator('#respMove .cr-proposal-form').boundingBox(), cell = await page.locator('#grid td[data-day="2"][data-pair="1"]').boundingBox();
    assert.ok(box.x >= cell.x + cell.width || box.x + box.width <= cell.x, 'oyna katak yonida: ' + JSON.stringify([box, cell]));
    // "o'zgartirish ▾" — qidiruvli xona ro'yxati (band — kulrang).
    await page.click('#respMove [data-rmv-pick]');
    const rows = await page.$$eval('#respMove [data-rmv-list] .rm-row', xs => xs.map(x => [x.dataset.code, x.getAttribute('aria-disabled') === 'true']));
    assert.deepEqual(rows, [['', false], ['R400', false], ['R401', false], ['R402', true]]);
    await page.press('#respMove [data-rmv-q]', 'Escape');
    await page.evaluate(() => { window.next = [{ status: 200, j: { ok: true, mode: 'fix', fixed: true, message: "To'qnashuvdagi dars darhol joylandi (faqat shu hafta)." } }]; });
    await page.click('#respMove [type="submit"]');
    await page.waitForFunction(() => !document.getElementById('respMove'));
    c0 = (await calls()).at(-1);
    assert.deepEqual([c0.method, c0.url, c0.body], ['POST', '/admin/timetable/cards/1/responsible-move', { week: 10, day: 2, pair: 1, auditorium_code: 'R400', reason: '' }]);
    assert.deepEqual(await page.evaluate(() => window.toasts.at(-1)), ['<b>Joylandi (faqat shu hafta)</b>', 'ok']);
    st = await state();
    assert.equal(st.pick, null);
    assert.deepEqual(st.cache, []);
    assert.equal(await page.evaluate(() => window.log.filter(x => x === 'loadBoard').length), 1);

    // 5) Sariq katak: tasdiq oynasi, lekin xona tanlanmaguncha "Joylab bo'lmaydi"; xona tanlansa — "Joylash".
    await page.evaluate(([g, c]) => { window.next = [{ status: 200, j: g }]; window.fmPickStart(c); }, [GRID, card]);
    await page.waitForFunction(() => window.state().pick && window.state().pick.loaded);
    await page.evaluate(rooms => { window.next = [{ status: 422, j: { ok: false, mode: null, message: 'Xona band: 400-amaliyot — x', rooms } }]; }, CHECK_ROOMS);
    await page.click('#grid td[data-day="2"][data-pair="4"]');
    await page.waitForFunction(() => document.querySelector('#respMove [type="submit"]').textContent === 'Joylab bo‘lmaydi');
    assert.equal(await page.isDisabled('#respMove [type="submit"]'), true);
    assert.match(await page.innerText('#respMove [role="alert"]'), /mos bo‘sh xona yo‘q — «o‘zgartirish ▾» orqali xona tanlang/);
    assert.equal((await calls()).at(-1).body.auditorium_code, undefined);
    await page.evaluate(rooms => { window.next = [{ status: 200, j: { ok: true, mode: 'fix', message: 'Darhol.', rooms } }]; }, CHECK_ROOMS);
    await page.click('#respMove [data-rmv-pick]');
    await page.click('#respMove .rm-row[data-code="R401"]');
    await page.waitForFunction(() => document.querySelector('#respMove [type="submit"]').textContent === 'Joylash');
    assert.equal((await calls()).at(-1).body.auditorium_code, 'R401');
    await page.click('#respMove [data-close]');

    // 6) Sudrab tashlash: yashil katak — ramka yashil, tashlansa — tasdiq oynasi (darhol yozilmaydi); band katakka — sabab (toast).
    const n0 = (await calls()).length;
    await page.evaluate(c => window.fmDragStart(c, null), card);
    const drag = (sel, type) => page.evaluate(([s, t]) => document.querySelector(s).dispatchEvent(new DragEvent(t, { bubbles: true, cancelable: true, dataTransfer: new DataTransfer() })), [sel, type]);
    await drag('#grid td[data-day="2"][data-pair="2"]', 'dragover');
    assert.match(await cellCls(2, 2), /drag-bad drag-bad-teacher/);
    assert.equal(await page.evaluate(() => window.tip), '1:2+4');
    await drag('#grid td[data-day="2"][data-pair="2"]', 'drop');
    assert.match(await page.evaluate(() => window.toasts.at(-1)[0]), /O'qituvchi band: USTOZ/);
    assert.equal(await page.locator('#respMove').count(), 0);
    await page.evaluate(c => window.fmDragStart(c, null), card);
    await drag('#grid td[data-day="2"][data-pair="1"]', 'dragover');
    assert.match(await cellCls(2, 1), /drag-ok/);
    await page.evaluate(rooms => { window.next = [{ status: 200, j: { ok: true, mode: 'fix', message: 'Darhol.', rooms } }]; }, CHECK_ROOMS);
    await drag('#grid td[data-day="2"][data-pair="1"]', 'drop');
    await page.waitForFunction(() => document.querySelector('#respMove [type="submit"]') && document.querySelector('#respMove [type="submit"]').textContent === 'Joylash');
    assert.deepEqual((await calls()).slice(n0).map(x => x.url), ['/admin/timetable/cards/1/responsible-move/check']);
    assert.equal((await state()).drag, null);
    await page.click('#respMove [data-close]');

    // 7) "Eng yaxshi joylar": ro'yxat (kun, vaqt, xona, sabab) — bosilsa shu katak uchun tasdiq oynasi.
    await page.click('#ttFmPick [data-fm-best-toggle]');
    assert.match(await page.evaluate(() => document.querySelector('#ttFmPick [data-fm-best]').textContent), /Seshanba, 17\.11 08:30–11:20 · xona: 400-amaliyot\s*guruhning shu kungi darsiga ulashgan · o'qituvchi shu kuni bo'sh/);
    await page.evaluate(rooms => { window.next = [{ status: 200, j: { ok: true, mode: 'fix', message: 'Darhol.', rooms } }]; }, CHECK_ROOMS);
    await page.click('#ttFmPick [data-fm-best-go="0"]');
    await page.waitForFunction(() => document.querySelector('#respMove [type="submit"]') && document.querySelector('#respMove [type="submit"]').textContent === 'Joylash');
    assert.deepEqual((await calls()).at(-1).body, { week: 10, day: 2, pair: 1, auditorium_code: 'R400' });
    await page.click('#respMove [data-close]');

    // 8) Hafta almashdi — shu hafta uchun qayta so'raladi; Esc — rejim tugaydi.
    await page.evaluate(g => { window.next = [{ status: 200, j: Object.assign({}, g, { week: 11 }) }]; window.setWeek(11); window.renderGrid(); }, GRID);
    await page.waitForFunction(() => window.state().pick && window.state().pick.week === 11 && window.state().pick.loaded);
    assert.equal((await calls()).at(-1).url, '/admin/timetable/cards/1/responsible-move/grid?week=11');
    await page.keyboard.press('Escape');
    assert.equal((await state()).pick, null);

    // 9) Ma'ruza (mas'ul fanida ham) — rejim yoqilmaydi, ko'rish oynasi sababi bilan; mas'ul bo'lmagan fan — ham.
    const before = (await calls()).length;
    for (const [c, re] of [[{ ...card, id: 5, training_type: 'lecture', oqim_label: '1-oqim' }, /Ma'ruzani surish/], [{ ...card, id: 9 }, /mas'ul fanlaringizda emas/]]) {
        await page.evaluate(x => window.fmPickStart(x), c);
        assert.equal((await state()).pick, null);
        assert.match(await page.locator('#respMove [data-rm-why]').innerText(), re);
        await page.click('#respMove [data-close]');
    }
    assert.equal((await calls()).length, before);

    // 10) Bo'sh joy yo'q: "Bu haftada bo'sh joy yo'q — keyingi haftalar: 11-hafta (5 joy), 12-hafta (3 joy)"; jadval guruh ustunining
    // birinchi qatoriga suriladi; havola bosilsa — shu haftaga o'tiladi, rejim davom etadi (shu hafta grid'i so'raladi).
    await page.evaluate(() => { window.setWeek(10); document.getElementById('weekSel').value = '10'; window.scrolls = []; window.clearCache(); });
    const none = Object.assign({}, GRID, { cells: Object.fromEntries(Object.keys(cells).map(k => [k, { state: 'teacher', reason: "O'qituvchi band", causes: {} }])),
        best: [], ahead: [{ week: 11, ok: 5 }, { week: 12, ok: 3 }, { week: 13, ok: 0 }] });
    await page.evaluate(([g, c]) => { window.next = [{ status: 200, j: g }]; window.fmPickStart(c); }, [none, card]);
    await page.waitForFunction(() => window.state().pick && window.state().pick.loaded);
    assert.match(await barText(), /Bu haftada bo‘sh joy yo‘q — keyingi haftalar: 11-hafta \(5 joy\), 12-hafta \(3 joy\)/);
    assert.doesNotMatch(await barText(), /13-hafta/);
    assert.deepEqual(await page.$$eval('#ttFmPick [data-fm-week]', xs => xs.map(x => x.dataset.fmWeek)), ['11', '12']);
    assert.deepEqual(await page.evaluate(() => window.scrolls), [['1-1', 'center', 'center']]);
    await page.evaluate(g => { window.next = [{ status: 200, j: Object.assign({}, g, { week: 11 }) }]; }, GRID);
    await page.click('#ttFmPick [data-fm-week="11"]');
    await page.waitForFunction(() => window.state().pick && window.state().pick.week === 11 && window.state().pick.loaded);
    assert.equal(await page.evaluate(() => window.log.includes('week:11')), true);
    assert.equal((await calls()).at(-1).url, '/admin/timetable/cards/1/responsible-move/grid?week=11');
    assert.deepEqual(await page.evaluate(() => window.scrolls.at(-1)), ['2-1', 'center', 'center']);
    // Keyingi haftalarda ham yo'q — shunchaki xabar.
    await page.evaluate(([g, c]) => { window.fmPickStop(); window.setWeek(10); window.clearCache(); window.next = [{ status: 200, j: g }]; window.fmPickStart(c); },
        [Object.assign({}, none, { ahead: [{ week: 11, ok: 0 }, { week: 12, ok: 0 }, { week: 13, ok: 0 }] }), card]);
    await page.waitForFunction(() => window.state().pick && window.state().pick.loaded);
    assert.match(await barText(), /Bu haftada bo‘sh joy yo‘q — keyingi 3 haftada ham bo‘sh joy yo‘q/);
    await page.evaluate(() => window.fmPickStop());

    await browser.close();
    console.log('timetable fm pick: rejim, grid ranglari (bitta so\'rov, kesh), avtomatik surish, tasdiq oynasi, sariq — xona majburiy, sudrab tashlash, eng yaxshi joylar, keyingi haftalar');
})().catch(e => { console.error(e); process.exit(1); });
