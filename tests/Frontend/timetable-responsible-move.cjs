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
    window.calls = []; window.toasts = []; window.reloaded = 0; window.reqLoaded = 0;
    window.next = [];
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

const ROOMS = [{ code: 'R2', name: '102-xona', volume: 30, free: true, why: 'kafedra xonasi', busy: null },
    { code: 'R3', name: '103-xona', volume: 30, free: false, why: null, busy: 'Band: pp/2024-01c, Farmakologiya' }];

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
    const opts = await page.$$eval('#respMove select[name="room"] option', xs => xs.map(o => [o.value, o.disabled, o.textContent]));
    assert.deepEqual(opts.map(o => o.slice(0, 2)), [['', false], ['R2', false], ['R3', true]]);
    assert.match(opts[2][2], /103-xona \(30 o‘rin\) — Band: pp\/2024-01c/);

    // 3) Xona tanlansa — qayta check (auditorium_code bilan); band joy (422) — qizil, "Band", tugma o'chiq, xonalar baribir yangilanadi.
    await page.evaluate(rooms => { window.next = [{ status: 422, j: { ok: false, mode: null, message: "O'qituvchi band: SOTTOROV S.", rooms } }]; }, ROOMS);
    await page.selectOption('#respMove select[name="room"]', 'R2');
    await page.waitForFunction(() => document.querySelector('#respMove [type="submit"]').textContent === 'Band');
    c0 = (await calls()).at(-1);
    assert.deepEqual(c0.body, { week: 10, day: 4, pair: 7, auditorium_code: 'R2' });
    assert.equal(await page.isDisabled('#respMove [type="submit"]'), true);
    assert.match(await page.locator('#respMove [role="alert"]').getAttribute('class'), /text-red-600/);
    assert.equal(await page.inputValue('#respMove select[name="room"]'), 'R2');

    // 4) Kun o'zgarsa — "request": "So'rov yuborish"; sababsiz — yuborilmaydi; sabab bilan — so'rov, so'ng so'rovlar ro'yxati.
    await page.evaluate(rooms => { window.next = [{ status: 200, j: { ok: true, mode: 'request', message: "So'rov kafedra mudiri va registrator ofisiga yuboriladi.",
        new: 'Chorshanba 12:00–14:00', rooms } }]; }, ROOMS);
    await page.selectOption('#respMove select[name="day"]', '3');
    await page.waitForFunction(() => document.querySelector('#respMove [type="submit"]').textContent === 'So‘rov yuborish');
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
    await page.click('#respMove [type="submit"]');
    await page.waitForFunction(() => !document.getElementById('respMove'));
    assert.deepEqual(await page.evaluate(() => [window.reloaded, window.toasts.at(-1)[1]]), [1, 'ok']);

    // 6) openModal: fan mas'uli — shu oyna (kafedra mudiri oynasi emas).
    assert.match(source, /if \(TIMETABLE_ROLE === 'fan_masuli'\) \{ openResponsibleMove\(c\); return; \}/);

    await browser.close();
    console.log('timetable-responsible-move: ok');
})().catch(e => { console.error(e); process.exit(1); });
