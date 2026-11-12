const fs = require('node:fs');
const assert = require('node:assert/strict');
const path = require('node:path');
// Fan mas'uli: kartani bosganda — "Vaqt va xonani o'zgartirish" oynasi (kafedra mudiri oynasi emas). Har o'zgarishda check: rejim serverdan
// ("Qo'llash" — darhol, "So'rov yuborish" — so'rov, qizil — band). Xonalar — check javobidan (bo'shlari tanlanadi, bandlari — yo'q).
// Mas'ul fanida bo'lmagan dars, ma'ruza va hafta tanlanmagan holat — faqat ko'rish.
const { chromium } = require('playwright');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');
const cut = (from, to) => {
    const a = source.indexOf(from), b = source.indexOf(to, a);
    assert(a > 0 && b > a, from);
    return source.slice(a, b);
};
const css = cut('<style>', '</style>').slice('<style>'.length);
const code = cut("            // ===== Fan mas'uli: mas'ul fani darsining vaqti va xonasi", "            // ===== Kartochka modali (o'qituvchi/auditoriya) =====");
// Xona qidiruvi (harf, tutuq belgisi, kirill-lotin farqsiz) va sig'im matni — sahifadagi asl funksiyalar.
const search = cut('            const UZ_CYR_LAT = {', '            // Ma\'ruza yorlig\'i:');
const capText = cut('            const cfCapText = ', '            const cfDate = ');
// "Amaldagi" — butun dars (ko'p bo'lakli: kunlar bo'yicha oraliq; bo'lingan — "(bo'lingan)") — sahifadagi asl funksiyalar.
const unitDays = cut('            function unitIsSplit(list, W = curWeek) {', "            // Ko'p bo'lakli amaliy dars shu haftada:");

const page0 = `<!doctype html><html><head><style>${css}</style></head><body><script>
    const BASE = '/admin/timetable';
    const board = { id: 7, days: 6, pairs_per_day: 12 };
    const esc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    let curWeek = 10, weeklyLayer = 'internal';
    const mySubjectCardIds = new Set([1, 2, 5]);
    const boardDayNames = () => ['Dushanba', 'Seshanba', 'Chorshanba', 'Payshanba', 'Juma', 'Shanba'];
    const boardSchedule = () => Array.from({ length: 12 }, (_, i) => ({ type: 'pair', start: String(8 + i).padStart(2, '0') + ':00' }));
    const cardLen = c => Math.max(1, parseInt(c.len_half) || 2);
    const unitLen = list => list.reduce((s, c) => s + cardLen(c), 0);
    window.units = { 1: [{ id: 1, len_half: 2, day: 4, pair: 7 }], 2: [{ id: 2, len_half: 2, day: 3, pair: 1 }, { id: 3, len_half: 2, day: 3, pair: 3 }] };
    const unitOf = c => window.units[c.id] || [c];
    const effPlace = c => c.day ? { day: c.day, pair: c.pair } : null;
    const slotRangeText = (p, len) => String(7 + p).padStart(2, '0') + ':00–' + String(7 + p + len).padStart(2, '0') + ':00';
    window.calls = []; window.toasts = []; window.reloaded = 0; window.reqLoaded = 0; window.escSeen = 0;
    // Sahifaning umumiy Esc ishlovchilari (sikl tanlovini tozalash va h.k.) — ro'yxatdagi Esc ularga yetib bormasligi kerak.
    document.addEventListener('keydown', e => { if (e.key === 'Escape') window.escSeen++; });
    window.next = [];
${search}
${capText}
    const placeInWeek = c => effPlace(c);
${unitDays}
    const ttToast = (h, t) => window.toasts.push([h, t]);
    const loadBoard = async () => { window.reloaded++; };
    const loadChangeRequests = async () => { window.reqLoaded++; };
    async function api(url, method = 'GET', body = null) {
        window.calls.push({ url, body: body ? JSON.parse(JSON.stringify(body)) : null });
        const r = window.next.length ? window.next.shift() : { status: 200, j: { ok: true, mode: 'fix', message: "Darhol qo'llanadi.", new: 'Seshanba 10:00–12:00', rooms: [] } };
        if (r.status >= 400) { const e = new Error(r.j.message || r.j.error); e.status = r.status; e.data = r.j; throw e; }
        return r.j;
    }
${code}
    window.openResponsibleMove = openResponsibleMove;
    window.setWeek = w => { curWeek = w; };
</script></body></html>`;

const BUSY = { teacher: 'UMAROV J. A.', teacher_full: 'UMAROV JASUR AKMALOVICH', groups: ['d1/d25-07a'], subject: 'Normal fiziologiya',
    time: '08:30–11:20', date: '2026-11-12', dates: ['2026-11-12', '2026-11-12'] };
const ROOMS = [{ code: 'R1', name: '101-xona', volume: 30, free: true, current: true, why: null, kind: null, busy_items: [] },
    { code: 'R2', name: '3-bino, 2-qavat, Bo‘lim xonasi', volume: 30, free: true, why: 'shu qavat · kafedra xonasi', kind: null, busy_items: [] },
    { code: 'R3', name: '103-xona', volume: 30, free: false, why: null, kind: 'busy', busy: 'Band: d1/d25-07a, Normal fiziologiya',
        busy_items: [BUSY, { ...BUSY, teacher: 'ALIYEV B.', teacher_full: 'ALIYEV BOBUR', subject: 'Anatomiya', time: '13:30–14:50' }] },
    { code: 'R4', name: '104-xona', volume: 20, free: false, why: null, kind: 'capacity', busy_items: [], cap: { volume: 20, students: 35, over: 15 } }];
const rows = page => page.$$eval('#respMove [data-rmv-list] .rm-row', xs => xs.map(x => [x.dataset.code, x.getAttribute('aria-disabled') === 'true', x.textContent]));

(async () => {
    const browser = await chromium.launch();
    const page = await browser.newPage();
    page.on('pageerror', e => { throw e; });
    await page.setContent(page0);
    const calls = () => page.evaluate(() => window.calls);
    const card = { id: 1, subject_name: 'Farmakologiya', group_name: 'pp/2024-01b', training_type: 'practice', len_half: 2, day: 4, pair: 7,
        auditorium_name: '101-xona', auditorium_code: 'R1' };

    // 1) Faqat ko'rish: mas'ul fanida emas, ma'ruza, hafta tanlanmagan — so'rov yuborilmaydi.
    for (const [c, re, week] of [[{ ...card, id: 9 }, /mas'ul fanlaringizda emas/, 10], [{ ...card, id: 5, training_type: 'lecture', oqim_label: '1-oqim' }, /Ma'ruzani surish/, 10],
        [card, /haftani tanlang/, 0]]) {
        await page.evaluate(([c, w]) => { window.setWeek(w); window.openResponsibleMove(c); }, [c, week]);
        assert.match(await page.locator('#respMove [data-rm-why]').innerText(), re);
        assert.equal(await page.locator('#respMove form').count(), 0);
        await page.click('#respMove [data-close]');
    }
    assert.deepEqual(await calls(), []);
    await page.evaluate(() => window.setWeek(10));

    // 2) Mas'ul fani: ochilganda check (amaldagi kun/para) — "fix": "Qo'llash"; xonalar: bo'shi tanlanadi, bandi — yo'q (sababi bilan).
    await page.evaluate(rooms => { window.next = [{ status: 200, j: { ok: true, mode: 'fix', message: "Darhol qo'llanadi (to'qnashuvni tuzatish, faqat shu hafta).",
        new: 'Payshanba 14:00–16:00', rooms } }]; }, ROOMS);
    await page.evaluate(c => window.openResponsibleMove(c), card);
    await page.waitForFunction(() => document.querySelector('#respMove [type="submit"]').textContent === 'Qo‘llash');
    let c0 = (await calls()).at(-1);
    assert.match(c0.url, /\/cards\/1\/responsible-move\/check$/);
    assert.deepEqual(c0.body, { week: 10, day: 4, pair: 7 });
    assert.match(await page.locator('#respMove').innerText(), /Amaldagi: Payshanba 14:00–16:00, xona 101-xona/);
    assert.match(await page.locator('#respMove [role="alert"]').innerText(), /Darhol qo'llanadi/);
    // Qidiruvli ro'yxat (oddiy select emas): yopiq — tanlov "hozirgi xona"; ochilganda birinchi qator — hozirgi xona (takrorlanmaydi),
    // bo'shi — yaqinlik sababi bilan, band — kim / guruh / fan / vaqt (+1), sig'maydigan — raqamlar bilan; bandlari tanlab bo'lmaydi.
    assert.equal(await page.locator('#respMove select[name="room"]').count(), 0);
    assert.equal(await page.innerText('#respMove [data-rmv-pick]'), '— hozirgi xona (101-xona) —');
    assert.equal(await page.isVisible('#respMove [data-rmv-panel]'), false);
    await page.click('#respMove [data-rmv-pick]');
    assert.equal(await page.evaluate(() => document.activeElement.matches('#respMove [data-rmv-q]')), true);
    let r0 = await rows(page);
    assert.deepEqual(r0.map(r => r.slice(0, 2)), [['', false], ['R2', false], ['R3', true], ['R4', true]]);
    assert.match(r0[1][2], /shu qavat · kafedra xonasi/);
    assert.match(r0[2][2], /Band: UMAROV J\. A\. · d1\/d25-07a · Normal fiziologiya · 08:30–11:20 \+1/);
    assert.match(r0[3][2], /Xona sig‘maydi: 20 o'rin, 35 talaba \(\+15\)/);
    const title = await page.getAttribute('#respMove .rm-row[data-code="R3"]', 'title');
    assert.match(title, /UMAROV JASUR AKMALOVICH · d1\/d25-07a · Normal fiziologiya · 12\.11 · 08:30–11:20/);
    assert.match(title, /ALIYEV BOBUR · d1\/d25-07a · Anatomiya/);
    // Band xona — bosilsa ham tanlanmaydi (check yuborilmaydi).
    let n0 = (await calls()).length;
    await page.click('#respMove .rm-row[data-code="R3"]', { force: true });
    assert.equal((await calls()).length, n0);
    assert.equal(await page.inputValue('#respMove input[name="room"]'), '');
    // Qidiruv: harf va tutuq belgisi farqsiz (o' ↔ o‘), faqat xona nomi bo'yicha (kod bo'yicha emas); topilmasa — xabar.
    await page.fill('#respMove [data-rmv-q]', "BO'LIM");
    assert.deepEqual((await rows(page)).map(r => r[0]), ['', 'R2']);
    await page.fill('#respMove [data-rmv-q]', 'r4');
    assert.match(await page.innerText('#respMove [data-rmv-list]'), /Xona topilmadi/);
    await page.fill('#respMove [data-rmv-q]', '104');
    assert.deepEqual((await rows(page)).map(r => r[0]), ['', 'R4']);
    await page.fill('#respMove [data-rmv-q]', 'yo‘q-xona');
    assert.match(await page.innerText('#respMove [data-rmv-list]'), /Xona topilmadi/);
    // "Faqat bo'shlar" — sukut o'chiq; yoqilsa bandlar va sig'maydiganlar yashirinadi.
    await page.fill('#respMove [data-rmv-q]', '');
    assert.equal(await page.isChecked('#respMove [data-rmv-free]'), false);
    await page.check('#respMove [data-rmv-free]');
    assert.deepEqual((await rows(page)).map(r => r[0]), ['', 'R2']);
    await page.uncheck('#respMove [data-rmv-free]');
    // Klaviatura: ↓ — faqat tanlanadiganlar bo'ylab (band / sig'maydigan o'tkaziladi), Esc — ro'yxat yopiladi, oyna ochiq qoladi.
    await page.press('#respMove [data-rmv-q]', 'ArrowDown');
    await page.press('#respMove [data-rmv-q]', 'ArrowDown');
    await page.press('#respMove [data-rmv-q]', 'ArrowDown');
    assert.equal(await page.getAttribute('#respMove .rm-row.is-active', 'data-code'), 'R2');
    await page.press('#respMove [data-rmv-q]', 'Escape');
    assert.equal(await page.isVisible('#respMove [data-rmv-panel]'), false);
    assert.equal(await page.locator('#respMove').count(), 1);
    assert.equal(await page.evaluate(() => window.escSeen), 0);

    // 3) Xona tanlansa (Enter) — qayta check (auditorium_code bilan); band joy (422) — qizil, tugma o'chiq va kulrang: "Qo'llab bo'lmaydi".
    await page.evaluate(rooms => { window.next = [{ status: 422, j: { ok: false, mode: null, message: "O'qituvchi band: SOTTOROV S.", rooms } }]; }, ROOMS);
    await page.click('#respMove [data-rmv-pick]');
    await page.press('#respMove [data-rmv-q]', 'ArrowDown');
    await page.press('#respMove [data-rmv-q]', 'ArrowDown');
    await page.press('#respMove [data-rmv-q]', 'Enter');
    await page.waitForFunction(() => document.querySelector('#respMove [type="submit"]').textContent === 'Qo‘llab bo‘lmaydi');
    c0 = (await calls()).at(-1);
    assert.deepEqual(c0.body, { week: 10, day: 4, pair: 7, auditorium_code: 'R2' });
    assert.equal(await page.isDisabled('#respMove [type="submit"]'), true);
    assert.match(await page.getAttribute('#respMove [type="submit"]', 'class'), /rmv-off/);
    assert.match(await page.locator('#respMove [role="alert"]').getAttribute('class'), /text-red-600/);
    assert.equal(await page.inputValue('#respMove input[name="room"]'), 'R2');
    assert.match(await page.innerText('#respMove [data-rmv-pick]'), /3-bino, 2-qavat, Bo‘lim xonasi \(30 o‘rin\) — shu qavat/);
    assert.equal(await page.isVisible('#respMove [data-rmv-panel]'), false);

    // 4) Kun o'zgarsa — "request": "So'rov yuborish"; sababsiz — yuborilmaydi; sabab bilan — so'rov, so'ng so'rovlar ro'yxati.
    await page.evaluate(rooms => { window.next = [{ status: 200, j: { ok: true, mode: 'request', message: "So'rov kafedra mudiri va registrator ofisiga yuboriladi.",
        new: 'Chorshanba 12:00–14:00', rooms } }]; }, ROOMS);
    await page.selectOption('#respMove select[name="day"]', '3');
    await page.waitForFunction(() => document.querySelector('#respMove [type="submit"]').textContent === 'So‘rov yuborish');
    assert.doesNotMatch(await page.getAttribute('#respMove [type="submit"]', 'class'), /rmv-off/);
    assert.match(await page.locator('#respMove [role="alert"]').getAttribute('class'), /text-amber-700/);
    const before = (await calls()).length;
    await page.click('#respMove [type="submit"]');
    assert.match(await page.locator('#respMove [role="alert"]').innerText(), /So'rov sababini kiriting/);
    assert.equal((await calls()).length, before);
    await page.fill('#respMove textarea[name="reason"]', 'Kafedra yig‘ilishi');
    await page.evaluate(() => { window.next = [{ status: 200, j: { ok: true, mode: 'request', pending_approval: true } }]; });
    await page.click('#respMove [type="submit"]');
    await page.waitForFunction(() => !document.getElementById('respMove'));
    c0 = (await calls()).at(-1);
    assert.match(c0.url, /\/cards\/1\/responsible-move$/);
    assert.deepEqual(c0.body, { week: 10, day: 3, pair: 7, auditorium_code: 'R2', reason: 'Kafedra yig‘ilishi' });
    assert.deepEqual(await page.evaluate(() => [window.reqLoaded, window.reloaded]), [1, 0]);

    // 5) Ko'p bo'lakli dars (2 + 2): boshlanish variantlari butun dars uzunligi bo'yicha; "fix" — qo'llangach panjara qayta yuklanadi.
    await page.evaluate(() => { window.next = [{ status: 200, j: { ok: true, mode: 'fix', message: 'Darhol.', rooms: [] } }, { status: 200, j: { ok: true, mode: 'fix', fixed: true, message: 'Joylandi.' } }]; });
    await page.evaluate(c => window.openResponsibleMove(c), { ...card, id: 2, day: 3, pair: 1 });
    await page.waitForFunction(() => document.querySelector('#respMove [type="submit"]').textContent === 'Qo‘llash');
    const startOpts = await page.$$eval('#respMove select[name="pair"] option', xs => xs.map(o => o.value));
    assert.equal(startOpts.length, 12 - 4 + 1);   // 4 soatlik dars — 1..9
    assert.match(await page.locator('#respMove').innerText(), /2 \+ 2 soatlik bo‘laklar/);
    // Amaldagi — butun dars: birinchi bo'lak boshidan oxirgisi oxirigacha (avval faqat birinchi bo'lak oralig'i).
    assert.match(await page.locator('#respMove [data-rmv-now]').innerText(), /^Amaldagi: Chorshanba 08:00–12:00, xona 101-xona/);
    await page.click('#respMove [type="submit"]');
    await page.waitForFunction(() => !document.getElementById('respMove'));
    assert.deepEqual(await page.evaluate(() => [window.reloaded, window.toasts.at(-1)[1]]), [1, 'ok']);

    // 5a) Bo'laklar turli kunlarda (bo'lingan) — amaldagi har kun alohida.
    await page.evaluate(() => { window.units[2][1] = { id: 3, len_half: 2, day: 5, pair: 3 }; window.next = [{ status: 200, j: { ok: true, mode: 'fix', message: 'Darhol.', rooms: [] } }]; });
    await page.evaluate(c => window.openResponsibleMove(c), { ...card, id: 2, day: 3, pair: 1 });
    assert.match(await page.locator('#respMove [data-rmv-now]').innerText(), /^Amaldagi: Chorshanba 08:00–10:00; Juma 10:00–12:00 \(bo‘lingan\)/);
    await page.click('#respMove [data-close]');
    await page.evaluate(() => { window.units[2][1] = { id: 3, len_half: 2, day: 3, pair: 3 }; });

    // 5b) Ko'p xona (~650): avval 50 ta, "Ko'proq ko'rsatish" — yana 50; qidiruv — hammasidan (ro'yxatdan tashqaridagi ham topiladi).
    // Tanlangan xona yangi vaqtda band bo'lib qolsa — "hozirgi xona"ga qaytadi. Mobil kenglikda ro'yxat sahifadan chiqib ketmaydi.
    const many = Array.from({ length: 120 }, (_, i) => ({ code: 'M' + i, name: (200 + i) + '-xona', volume: 30, free: true, why: null, kind: null, busy_items: [] }));
    await page.evaluate(rooms => { window.next = [{ status: 200, j: { ok: true, mode: 'fix', message: 'Darhol.', rooms } }]; }, many);
    await page.setViewportSize({ width: 375, height: 740 });
    await page.evaluate(c => window.openResponsibleMove(c), card);
    await page.waitForFunction(() => document.querySelector('#respMove [type="submit"]').textContent === 'Qo‘llash');
    await page.click('#respMove [data-rmv-pick]');
    assert.equal((await rows(page)).length, 1 + 50);
    assert.match(await page.innerText('#respMove [data-rmv-more]'), /Ko‘proq ko‘rsatish \(yana 70 ta\)/);
    await page.click('#respMove [data-rmv-more]');
    assert.equal((await rows(page)).length, 1 + 100);
    await page.fill('#respMove [data-rmv-q]', '319-xona');
    assert.deepEqual((await rows(page)).map(r => r[0]), ['', 'M119']);
    const box = await page.locator('#respMove [data-rmv-panel]').boundingBox();
    assert.ok(box.x >= 0 && box.x + box.width <= 375, JSON.stringify(box));
    await page.evaluate(rooms => { window.next = [{ status: 200, j: { ok: true, mode: 'fix', message: 'Darhol.', rooms } }]; }, many);
    await page.click('#respMove .rm-row[data-code="M119"]');
    await page.waitForFunction(() => window.next.length === 0 && document.querySelector('#respMove [type="submit"]').textContent === 'Qo‘llash');
    assert.equal(await page.inputValue('#respMove input[name="room"]'), 'M119');
    assert.deepEqual((await calls()).at(-1).body, { week: 10, day: 4, pair: 7, auditorium_code: 'M119' });
    const busyNow = many.map(r => r.code === 'M119' ? { ...r, free: false, kind: 'busy', busy_items: [BUSY] } : r);
    await page.evaluate(rooms => { window.next = [{ status: 200, j: { ok: true, mode: 'fix', message: 'Darhol.', rooms } }]; }, busyNow);
    await page.selectOption('#respMove select[name="day"]', '5');
    await page.waitForFunction(() => document.querySelector('#respMove input[name="room"]').value === '');
    assert.equal(await page.innerText('#respMove [data-rmv-pick]'), '— hozirgi xona (101-xona) —');
    await page.click('#respMove [data-close]');
    await page.setViewportSize({ width: 1280, height: 800 });

    // 5c) Ko'p bo'lakli dars, bo'laklar har xil xonada (101 va nofaol 105): birinchi qator "har bo'lak o'z xonasida (…)" qizil izoh bilan,
    // bu tanlovda tugma o'chiq (server "fix" desa ham); 101 — oddiy qator, tanlansa yuboriladi; biriktirilgani — kulrang, kimga
    // biriktirilgani bilan (qisqa nomi — qatorda, to'liq F.I.Sh. — title'da).
    const mixedRooms = [{ code: 'R1', name: '101-xona', volume: 30, free: true, current: false, why: 'shu qavat', kind: null, busy_items: [] },
        { code: 'R7', name: '107-xona', volume: 30, free: false, current: false, why: null, kind: 'assigned', note: 'Biriktirilgan: OSTANAQULOV SH. F.',
            note_full: 'Biriktirilgan: OSTANAQULOV SHAXBOZ FARXODOVICH', busy_items: [] }];
    const mixedParts = [{ code: 'R1', name: '101-xona', problem: null }, { code: 'RX', name: '105-xona', problem: 'nofaol' }];
    await page.evaluate(([rooms, parts]) => { window.next = [{ status: 200, j: { ok: true, mode: 'fix', message: 'Darhol.', rooms, part_rooms: parts } }]; },
        [mixedRooms, mixedParts]);
    await page.evaluate(c => window.openResponsibleMove(c), { ...card, id: 2, day: 3, pair: 1 });
    await page.waitForFunction(() => document.querySelector('#respMove [type="submit"]').textContent === 'Qo‘llab bo‘lmaydi');
    assert.equal(await page.isDisabled('#respMove [type="submit"]'), true);
    assert.match(await page.locator('#respMove [role="alert"]').innerText(), /105-xona — nofaol, xona tanlang/);
    assert.equal(await page.innerText('#respMove [data-rmv-pick]'), '— har bo‘lak o‘z xonasida (101-xona, 105-xona) —');
    await page.click('#respMove [data-rmv-pick]');
    let mr = await rows(page);
    assert.deepEqual(mr.map(r => r.slice(0, 2)), [['', false], ['R1', false], ['R7', true]]);
    assert.match(mr[0][2], /har bo‘lak o‘z xonasida \(101-xona, 105-xona\).*105-xona — nofaol, xona tanlang/);
    assert.match(mr[2][2], /Biriktirilgan: OSTANAQULOV SH\. F\./);
    assert.equal(await page.getAttribute('#respMove .rm-row[data-code="R7"]', 'title'), 'Biriktirilgan: OSTANAQULOV SHAXBOZ FARXODOVICH');
    // Biriktirilgan xona tanlanmaydi (bosilsa ham check yuborilmaydi), "Faqat bo'shlar"da yashirin.
    const nAssigned = (await calls()).length;
    await page.click('#respMove .rm-row[data-code="R7"]', { force: true });
    assert.equal((await calls()).length, nAssigned);
    await page.check('#respMove [data-rmv-free]');
    assert.deepEqual((await rows(page)).map(r => r[0]), ['', 'R1']);
    await page.uncheck('#respMove [data-rmv-free]');
    await page.evaluate(([rooms, parts]) => { window.next = [{ status: 200, j: { ok: true, mode: 'fix', message: 'Darhol.', rooms, part_rooms: parts } }]; },
        [mixedRooms, mixedParts]);
    await page.click('#respMove .rm-row[data-code="R1"]');
    await page.waitForFunction(() => document.querySelector('#respMove [type="submit"]').textContent === 'Qo‘llash');
    assert.deepEqual((await calls()).at(-1).body, { week: 10, day: 3, pair: 1, auditorium_code: 'R1' });
    assert.equal(await page.isDisabled('#respMove [type="submit"]'), false);
    // Bo'laklar xonasi yaroqli (izohsiz) — "har bo'lak o'z xonasida" tanlovi ham qo'llanadi.
    await page.evaluate(([rooms]) => { window.next = [{ status: 200, j: { ok: true, mode: 'fix', message: 'Darhol.', rooms,
        part_rooms: [{ code: 'R1', name: '101-xona', problem: null }, { code: 'R2', name: '102-xona', problem: null }] } }]; }, [mixedRooms]);
    await page.click('#respMove [data-rmv-pick]');
    await page.click('#respMove .rm-row[data-code=""]');
    await page.waitForFunction(() => document.querySelector('#respMove [type="submit"]').textContent === 'Qo‘llash');
    assert.equal(await page.innerText('#respMove [data-rmv-pick]'), '— har bo‘lak o‘z xonasida (101-xona, 102-xona) —');
    await page.click('#respMove [data-close]');

    // 5d) "510" qidiruvi — faqat nomida 510 bo'lgan xona; kodi 510 bo'lgan boshqa xona (205-xona) chiqmaydi.
    // Ma'ruza (ko'p guruh) bilan band xona: guruhlar va fan alohida (server: subject — faqat fan nomi); biriktirilgani — oxirida.
    const LEC = { teacher: 'UMAROV J. A.', teacher_full: 'UMAROV JASUR AKMALOVICH', groups: ["p/p25-01b (o'z)", 'p/p25-01c', 'p/p25-02a'],
        subject: 'Normal fiziologiya', time: '10:00–11:20', date: '2026-11-17', dates: ['2026-11-17'] };
    const r510 = [{ code: 'R1', name: '101-xona', volume: 30, free: true, current: true, why: null, kind: null, busy_items: [] },
        { code: '510', name: '205-xona', volume: 30, free: true, why: null, kind: null, busy_items: [] },
        { code: 'R9', name: '3-bino, 510-xona', volume: 30, free: true, why: null, kind: null, busy_items: [] },
        { code: 'L1', name: 'Ma‘ruza zali 1', volume: 120, free: false, why: null, kind: 'busy', busy_items: [LEC] },
        { code: '505', name: '3-bino, 505-xona', volume: 30, free: false, why: null, kind: 'assigned', note: 'Biriktirilgan: OSTANAQULOV SH. F.',
            note_full: 'Biriktirilgan: OSTANAQULOV SHAXBOZ FARXODOVICH', busy_items: [] }];
    await page.evaluate(rooms => { window.next = [{ status: 200, j: { ok: true, mode: 'fix', message: 'Darhol.', rooms } }]; }, r510);
    await page.evaluate(c => window.openResponsibleMove(c), card);
    await page.waitForFunction(() => document.querySelector('#respMove [type="submit"]').textContent === 'Qo‘llash');
    await page.click('#respMove [data-rmv-pick]');
    await page.fill('#respMove [data-rmv-q]', '510');
    assert.deepEqual((await rows(page)).map(r => r[0]), ['', 'R9']);
    await page.fill('#respMove [data-rmv-q]', '205');
    assert.deepEqual((await rows(page)).map(r => r[0]), ['', '510']);
    await page.fill('#respMove [data-rmv-q]', '');
    const all = await rows(page);
    assert.deepEqual(all.map(r => r.slice(0, 2)), [['', false], ['510', false], ['R9', false], ['L1', true], ['505', true]]);
    assert.match(all[3][2], /Band: UMAROV J\. A\. · p\/p25-01b \(o'z\), p\/p25-01c, p\/p25-02a · Normal fiziologiya · 10:00–11:20/);
    assert.equal((all[3][2].match(/p\/p25-01c/g) || []).length, 1);
    assert.match(all[4][2], /Biriktirilgan: OSTANAQULOV SH\. F\./);
    await page.fill('#respMove [data-rmv-q]', '505');
    assert.deepEqual((await rows(page)).map(r => r.slice(0, 2)), [['', false], ['505', true]]);
    await page.click('#respMove [data-close]');
    // Boshqa oynalar (umumiy roomMatches) — kod bo'yicha ham izlaydi; faqat fan mas'uli oynasi nameOnly bilan.
    const matches = new Function(search + '\nreturn roomMatches;')();
    assert.equal(matches({ code: '510', name: '205-xona' }, '510'), true);
    assert.equal(matches({ code: '510', name: '205-xona' }, '510', true), false);
    assert.equal(matches({ code: 'R9', name: '3-bino, 510-xona' }, '510', true), true);
    assert.equal((source.match(/roomMatches\([^)]*, true\)/g) || []).length, 1);

    // 6) openModal: fan mas'uli — shu oyna (kafedra mudiri oynasi emas).
    assert.match(source, /if \(TIMETABLE_ROLE === 'fan_masuli'\) \{ openResponsibleMove\(c\); return; \}/);

    await browser.close();
    console.log('timetable-responsible-move: ok');
})().catch(e => { console.error(e); process.exit(1); });
