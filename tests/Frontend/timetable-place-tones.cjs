const fs = require('node:fs');
const assert = require('node:assert/strict');
const path = require('node:path');
// "Joylash uchun tanlash" va sudrash: bo'sh katak hukmi bitta funksiyadan (placeVerdict ← conflictsAtDetail) — yashil / to'q qizil
// (o'qituvchi band) / och qizil (guruh band) / sariq (faqat xona band); title — har sabab alohida qatorda (takrorsiz, 10 tadan ortig'i
// "… yana N ta"), sariqda — xonani almashtirish ko'rsatmasi. Sichqoncha band katakda — sabab bo'lgan dars chipi ramka bilan. Sariq katak
// bosilsa — bo'sh va sig'adigan xonalar (reyting bo'yicha) oynasi: hafta — week-override xona bilan, shablon — assign-room, so'ng joylash.
const { chromium } = require('playwright');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');
const cut = (from, to) => {
    const a = source.indexOf(from), b = source.indexOf(to, a);
    assert(a > 0 && b > a, from);
    return source.slice(a, b);
};
const css = cut('<style>', '</style>').slice('<style>'.length);
const verdict = cut('            // Karta uzunligi yarim-slot birligida', '            // ===== Joylash (bosish yoki drag-and-drop uchun umumiy) =====');
const picker = cut('            // ===== Sariq katak (faqat xona band)', "            // ===== Parallel bo'laklar");
const drag = cut('            // Sudrash nishoni rangi', "            // Sudrash paytida tushadigan vaqt oralig'i");
const cause = cut('            // "Joylash uchun tanlash": sichqoncha band katakda', "            // Bittasi ko'chirilgach");

const page0 = `<!doctype html><html><head><style>${css}</style></head><body><table id="grid"><tbody><tr>
    <td data-day="1" data-pair="1" id="cell"></td>
    <td><div class="tt-chip" data-chip="11">O1</div><div class="tt-chip" data-chip="12">O2</div>
        <div class="tt-chip" data-chip="20" data-merge-ids="20,13">O3+</div><div class="tt-chip" data-chip="99">boshqa</div></td>
</tr></tbody></table><script>
    const esc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    const BASE = '/admin/timetable', AUDS_URL = '/auds', board = { id: 7, weeks: 15 };
    let curWeek = 10, weeklyLayer = 'internal', selected = null;
    window.overrides = {};
    const overrides = window.overrides;
    window.cards = [];
    let cards = window.cards;
    const isNonBlocking = () => false, phOf = () => null, curGrid = () => ({ weeks: 15 });
    const cardGroups = c => (c.group_names && c.group_names.length ? c.group_names : [c.group_name]);
    const cardGKeys = c => cardGroups(c).map(g => 'F|S|2¦' + g);
    const effectiveCard = c => { const ov = curWeek && weeklyLayer !== 'external' ? overrides[c.id + '|' + curWeek] : null;
        return ov && ov.auditorium_code ? { ...c, auditorium_code: ov.auditorium_code, auditorium_name: ov.auditorium_name } : c; };
    const effPlace = c => { const ov = curWeek ? overrides[c.id + '|' + curWeek] : null;
        return ov && ov.day ? { day: ov.day, pair: ov.pair } : (c.day && c.pair ? { day: c.day, pair: c.pair } : null); };
    const boardDayNames = () => ['Dushanba', 'Seshanba', 'Chorshanba', 'Payshanba', 'Juma', 'Shanba'];
    const slotRangeText = (p, len) => String(7 + p).padStart(2, '0') + ':00–' + String(7 + p + len).padStart(2, '0') + ':00';
    const roomMatches = (r, q) => !q || String(r.name || r.code).toLowerCase().includes(q.toLowerCase());
    const asgUnitParams = u => ({ faculty_name: u.faculty_name || '', specialty_name: u.specialty_name, course: u.course,
        subject_name: u.subject_name, training_type: u.training_type, oqim_label: u.oqim_label || '', group_name: u.group_name || '' });
    window.calls = []; window.toasts = []; window.renders = 0; window.next = {};
    const ttToast = (h, t) => window.toasts.push([h, t]);
    const renderAll = () => { window.renders++; };
    const weeklyEditGuard = () => {};
    async function placeOneCard(card, d, p) {
        window.calls.push({ place: [card.id, d, p] });
        if (window.next.place) { const e = new Error(window.next.place); throw e; }
        card.day = d; card.pair = p;
    }
    async function api(url, method = 'GET', body = null) {
        window.calls.push({ url, method, body: body ? JSON.parse(JSON.stringify(body)) : null });
        const k = url.split('?')[0];
        const r = (window.next[k] || []).shift();
        if (r && r.status >= 400) { const e = new Error(r.j.error); e.status = r.status; e.data = r.j; throw e; }
        return r ? r.j : { ok: true };
    }
${verdict}
${drag}
${cause}
${picker}
    Object.assign(window, { conflictsAt, conflictsAtDetail, placeVerdict, placeCauseAttr, placeBadCls, dragPaint, placeCauseMark, openPlaceRoomPick,
        setWeek: w => { curWeek = w; }, setSelected: c => { selected = c; }, getSelected: () => selected });
</script></body></html>`;

const base = { faculty_name: 'F', specialty_name: 'S', course: 2, training_type: 'practice', len_half: 2, students: 20, oqim_label: '1-oqim' };
const S = { ...base, id: 1, subject_name: 'Farmakologiya', group_name: 'g1', teacher_id: 500, teacher_name: 'USTOZ', auditorium_code: 'R1', auditorium_name: '101-xona' };
const other = (id, o) => ({ ...base, id, subject_name: 'Fan' + id, group_name: 'g' + (100 + id), teacher_id: 900 + id, teacher_name: 'T' + id,
    auditorium_code: 'X' + id, auditorium_name: 'X' + id, ...o });
const CARDS = [S,
    other(11, { day: 1, pair: 1, teacher_id: 500, teacher_name: 'USTOZ' }),                  // o'qituvchi band
    other(12, { day: 1, pair: 3, group_name: 'g1' }),                                         // guruh band
    other(13, { day: 1, pair: 5, auditorium_code: 'R1', auditorium_name: '101-xona' }),      // faqat xona
    other(14, { day: 1, pair: 7, group_name: 'g1', auditorium_code: 'R1', auditorium_name: '101-xona' }),   // guruh + xona
    other(15, { day: 2, pair: 1, teacher_id: 500, teacher_name: 'USTOZ' }),
    other(16, { day: 2, pair: 1, auditorium_code: 'R1', auditorium_name: '101-xona' }),
    other(17, { day: 1, pair: 5, auditorium_code: 'R4', auditorium_name: '104-xona' }),      // R4 — 1-kun 5-katakda band
    ...Array.from({ length: 12 }, (_, i) => other(30 + i, { day: 3, pair: 1, auditorium_code: 'R1', auditorium_name: '101-xona' })),
];
const ROOMS = [
    { code: 'R1', name: '101-xona', volume: 30, active: true }, { code: 'R2', name: '102-xona', volume: 30, active: true, building_name: 'A bino' },
    { code: 'R3', name: '103-xona', volume: 5, active: true }, { code: 'R4', name: '104-xona', volume: 40, active: true },
    { code: 'R6', name: '106-xona', volume: 0, active: true }, { code: 'R7', name: '107-xona', volume: 25, active: true },
];

(async () => {
    const browser = await chromium.launch();
    const page = await browser.newPage();
    page.on('pageerror', e => { throw e; });
    await page.setContent(page0);
    await page.evaluate(list => { window.cards.push(...list); }, CARDS);
    const V = (d, p) => page.evaluate(([d, p]) => { const v = window.placeVerdict(window.conflictsAtDetail(window.cards[0], d, p)); return { tone: v.tone, title: v.title, causes: v.causes }; }, [d, p]);

    // 1) Turlar: o'qituvchi — to'q qizil, guruh — och qizil, faqat xona — sariq (ko'rsatma bilan), guruh + xona — guruh; bo'sh — yashil.
    let v = await V(1, 1);
    assert.equal(v.tone, 'teacher');
    assert.equal(v.title, "O'qituvchi band: USTOZ — F, 2-kurs · g111 · Fan11 (amaliy)");
    assert.deepEqual(v.causes, { 11: 'teacher' });
    v = await V(1, 3);
    assert.equal(v.tone, 'group');
    assert.match(v.title, /^Guruh band: g1 — F, 2-kurs · g1 · Fan12 \(amaliy\)$/);
    v = await V(1, 5);
    assert.equal(v.tone, 'room');
    assert.equal(v.title, 'Auditoriya band: 101-xona — F, 2-kurs · g113 · Fan13 (amaliy)\nKartaning xonasini almashtirsangiz, shu vaqtga joylash mumkin.');
    v = await V(1, 7);
    assert.equal(v.tone, 'group');
    assert.equal(v.title.split('\n').length, 2);
    assert.deepEqual(v.causes, { 14: 'group' });   // bitta dars ikki sabab — og'irrog'i
    v = await V(2, 1);
    assert.equal(v.tone, 'teacher');
    assert.deepEqual(v.title.split('\n').map(t => t.split(':')[0]), ["O'qituvchi band", 'Auditoriya band']);
    assert.deepEqual(v.causes, { 15: 'teacher', 16: 'room' });
    assert.equal((await V(2, 5)).tone, 'ok');
    // 12 ta sabab — 10 tasi va "… yana 2 ta" (so'ng sariq ko'rsatmasi).
    const many = (await V(3, 1)).title.split('\n');
    assert.equal(many.length, 12);
    assert.equal(many[10], '… yana 2 ta');
    // conflictsAt matnlari — avvalgidek (tartib bilan).
    assert.deepEqual(await page.evaluate(() => window.conflictsAt(window.cards[0], 2, 1).map(t => t.split(':')[0])), ["O'qituvchi band", 'Auditoriya band']);
    // Katak atributlari.
    assert.equal(await page.evaluate(() => window.placeBadCls(window.placeVerdict(window.conflictsAtDetail(window.cards[0], 2, 1)))), ' tt-bad tt-bad-teacher');
    assert.equal(await page.evaluate(() => window.placeCauseAttr(window.placeVerdict(window.conflictsAtDetail(window.cards[0], 2, 1)))),
        ' data-bad-ids="15:teacher,16:room" data-bad-tone="teacher"');

    // 2) Sudrash — o'sha hukm (yorliq o'zgarmaydi): drag-ok / drag-bad + tur; "kun oxiri" — to'q qizil.
    const dragCls = items => page.evaluate(items => { const el = document.getElementById('cell'); el.className = 'drag-ok'; window.dragPaint(el, items); return el.className; }, items);
    assert.equal(await dragCls([]), 'drag-ok');
    assert.equal(await dragCls([{ kind: 'room', cardId: 13, text: 'x' }]), 'drag-bad drag-bad-room');
    assert.equal(await dragCls([{ kind: 'room', cardId: 13, text: 'x' }, { kind: 'group', cardId: 12, text: 'y' }]), 'drag-bad drag-bad-group');
    assert.equal(await dragCls([{ kind: 'other', cardId: null, text: 'kun oxiri' }]), 'drag-bad drag-bad-teacher');
    // Ranglar CSS'da: sariq / och qizil / to'q qizil.
    const bg = cls => page.evaluate(cls => { const td = document.getElementById('cell'); td.className = cls; return getComputedStyle(td).backgroundColor; }, cls);
    assert.deepEqual([await bg('tt-bad tt-bad-teacher'), await bg('tt-bad tt-bad-group'), await bg('tt-bad tt-bad-room')],
        ['rgb(248, 113, 113)', 'rgb(254, 202, 202)', 'rgb(253, 230, 138)']);

    // Legend: hidden — ko'rinmaydi (CSS display:flex hidden atributini bosmasin), ochiq — qator.
    assert.deepEqual(await page.evaluate(() => { const l = document.createElement('div'); l.id = 'ttPlaceLegend'; l.hidden = true; document.body.appendChild(l);
        const a = getComputedStyle(l).display; l.hidden = false; const b = getComputedStyle(l).display; l.remove(); return [a, b]; }), ['none', 'flex']);

    // 3) Sichqoncha band katakda — sabab darslar ramka bilan (birlashtirilgan chip — qismi bo'lsa ham); chiqsa — olib tashlanadi.
    await page.evaluate(() => { const td = document.getElementById('cell'); td.dataset.badIds = '11:teacher,12:group,13:room'; });
    await page.hover('#cell');
    assert.deepEqual(await page.$$eval('#grid [data-chip]', els => els.map(el => [el.dataset.chip, el.className.replace('tt-chip', '').trim()])),
        [['11', 'tt-cause-teacher'], ['12', 'tt-cause-group'], ['20', 'tt-cause-room'], ['99', '']]);
    await page.hover('#grid [data-chip="99"]');
    assert.equal(await page.$$eval('#grid .tt-cause-teacher, #grid .tt-cause-group, #grid .tt-cause-room', els => els.length), 0);

    // 4) Sariq katak (1-kun, 5-katak): bo'sh va sig'adigan xonalar — reyting bo'yicha (R4 band, R3 kichik, R6 sig'imsiz, R1 hozirgisi — yo'q).
    const setNext = obj => page.evaluate(o => { window.next = o; window.calls = []; window.toasts = []; window.renders = 0; }, obj);
    await setNext({ '/auds': [{ j: ROOMS }], '/admin/timetable/boards/7/unit-busy': [{ j: { room_rank: { order: ['R4', 'R7', 'R2'], why: { R7: 'odatdagi xona' } } } }],
        '/admin/timetable/cards/1/week-override': [{ j: { ok: true } }] });
    await page.evaluate(() => { window.setSelected(window.cards[0]); window.openPlaceRoomPick(window.cards[0], 1, 5); });
    await page.waitForSelector('#ttPlaceRoom [data-pr-code]');
    assert.deepEqual(await page.$$eval('#ttPlaceRoom [data-pr-code]', els => els.map(el => el.dataset.prCode)), ['R7', 'R2']);
    assert.match(await page.textContent('#ttPlaceRoom [data-pr-why]'), /^Auditoriya band: 101-xona/);
    assert.match(await page.textContent('#ttPlaceRoom'), /Farmakologiya · g1 → Dushanba 12:00–14:00 · 10-hafta \(faqat shu hafta\)/);
    assert.match(await page.textContent('#ttPlaceRoom [data-pr-code="R7"]'), /107-xona.*odatdagi xona.*25 o‘rin/);
    assert.deepEqual((await page.evaluate(() => window.calls)).map(c => c.url.split('?')[0]), ['/auds', '/admin/timetable/boards/7/unit-busy']);
    // Qidiruv.
    await page.fill('#ttPlaceRoom [data-pr-q]', '102');
    assert.deepEqual(await page.$$eval('#ttPlaceRoom [data-pr-code]', els => els.map(el => el.dataset.prCode)), ['R2']);
    // Hafta tanlangan: shu hafta istisnosi shu xona bilan; oyna yopiladi, tanlov tozalanadi.
    await page.click('#ttPlaceRoom [data-pr-code="R2"]');
    await page.waitForFunction(() => !document.getElementById('ttPlaceRoom'));
    let calls = await page.evaluate(() => window.calls);
    assert.deepEqual(calls.at(-1), { url: '/admin/timetable/cards/1/week-override', method: 'POST', body: { week: 10, action: 'move', day: 1, pair: 5, auditorium_code: 'R2' } });
    assert.deepEqual(await page.evaluate(() => [window.overrides['1|10'].auditorium_code, window.getSelected(), window.renders]), ['R2', null, 1]);
    assert.match((await page.evaluate(() => window.toasts))[0][0], /Joylandi: 102-xona xonasi bilan/);

    // 5) Shablon (hafta tanlanmagan): darsning xonasi (assign-room), so'ng joylash. 409 needs_confirm — "Baribir joylash" (force).
    await page.evaluate(() => { delete window.overrides['1|10']; window.setWeek(0); });
    await setNext({ '/admin/timetable/boards/7/unit-busy': [{ j: { room_rank: null } }],
        '/admin/timetable/boards/7/assign-room': [{ status: 409, j: { error: 'Xona band: 102-xona — Seshanba', needs_confirm: true } }, { j: { ok: true } }] });
    await page.evaluate(() => window.openPlaceRoomPick(window.cards[0], 1, 5));
    await page.waitForSelector('#ttPlaceRoom [data-pr-code]');
    assert.match(await page.textContent('#ttPlaceRoom'), /barcha haftalar \(darsning xonasi almashtiriladi\)/);
    // Reytingsiz — sig'imi yaqini avval.
    assert.deepEqual(await page.$$eval('#ttPlaceRoom [data-pr-code]', els => els.map(el => el.dataset.prCode)), ['R7', 'R2']);
    await page.click('#ttPlaceRoom [data-pr-code="R2"]');
    await page.waitForSelector('#ttPlaceRoom [data-pr-force]');
    assert.match(await page.textContent('#ttPlaceRoom [data-pr-msg]'), /⚠ Xona band: 102-xona — Seshanba/);
    calls = await page.evaluate(() => window.calls.filter(c => c.method === 'POST' || c.place));
    assert.deepEqual(calls.map(c => c.place ? 'place' : c.body.force), [undefined]);
    await page.click('#ttPlaceRoom [data-pr-force]');
    await page.waitForFunction(() => !document.getElementById('ttPlaceRoom'));
    calls = await page.evaluate(() => window.calls.filter(c => c.method === 'POST' || c.place));
    assert.deepEqual(calls.map(c => c.place ? 'place ' + c.place.join(',') : c.url.split('/').pop() + ' ' + c.body.auditorium_code + ' ' + c.body.force),
        ['assign-room R2 undefined', 'assign-room R2 1', 'place 1,1,5']);
    assert.equal(await page.evaluate(() => window.cards[0].auditorium_code), 'R2');

    // 6) Joylanmasa — xona avvalgisiga qaytariladi, xato oynada (oyna ochiq qoladi).
    await page.evaluate(() => { const c = window.cards[0]; c.day = null; c.pair = null; c.auditorium_code = 'R1'; c.auditorium_name = '101-xona'; });
    await setNext({ '/admin/timetable/boards/7/unit-busy': [{ j: { room_rank: null } }], '/admin/timetable/boards/7/assign-room': [{ j: { ok: true } }, { j: { ok: true } }],
        place: 'Guruh band' });
    await page.evaluate(() => window.openPlaceRoomPick(window.cards[0], 1, 5));
    await page.waitForSelector('#ttPlaceRoom [data-pr-code]');
    await page.click('#ttPlaceRoom [data-pr-code="R7"]');
    await page.waitForFunction(() => /Joylanmadi/.test(document.querySelector('#ttPlaceRoom [data-pr-msg]').textContent));
    calls = await page.evaluate(() => window.calls.filter(c => c.method === 'POST' || c.place));
    assert.deepEqual(calls.map(c => c.place ? 'place' : c.body.auditorium_code), ['R7', 'place', 'R1']);
    assert.deepEqual(await page.evaluate(() => [window.cards[0].auditorium_code, window.cards[0].day]), ['R1', null]);
    assert.match(await page.textContent('#ttPlaceRoom [data-pr-msg]'), /Joylanmadi: Guruh band/);

    // 7) Bekor (Esc) — hech narsa.
    await page.evaluate(() => { window.next = {}; window.calls = []; });
    await page.keyboard.press('Escape');
    assert.equal(await page.$('#ttPlaceRoom'), null);
    assert.deepEqual(await page.evaluate(() => window.calls), []);

    // 8) Ulanish (manba): ikkala katakda (ma'ruza oqimi va amaliy guruh) hukm, sariq — data-room-place → xona oynasi; legend; sudrash —
    //    dragPaint (tanlash bilan bir xil); tashqi tooltip (dragTip) o'zgarmagan.
    assert.equal((source.match(/const v = placeVerdict\(conflictsAtDetail\(selected, d, p\)\);/g) || []).length, 2);
    assert.match(source, /document\.querySelectorAll\('\[data-room-place\]'\)\.forEach\(td => td\.onclick = \(\) => \{[^]*?openPlaceRoomPick\(selected, d, p\);/);
    assert.match(source, /<div id="ttPlaceLegend" hidden>/);
    assert.match(source, /legend\.hidden = !\(selected && !mudirPick && !TIMETABLE_READ_ONLY\);/);
    assert.match(source, /dragPaint\(td, plan \? plan\.items : \[\]\);/);
    assert.match(source, /dragPaint\(z, card \? conflictsAtDetail\(card, d, p, dragCardIds\) : \[\]\);/);
    assert.match(source, /tip\.textContent = \(slotRangeText\(plan\.start, plan\.len\) \|\| ''\) \+ \(bad \? ' — band' : ''\);/);

    await browser.close();
    console.log('timetable place tones: 8 scenarios passed');
})().catch(e => { console.error(e); process.exit(1); });
