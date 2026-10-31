const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const writer = fs.readFileSync(path.join(__dirname, '../../public/js/hemis-writer.js'), 'utf8');

// «Bugungi darslar (HEMIS)» JSON'i xatcho'pda: faqat bugungi kun va faqat o'zgartirish; har biridan oldin davomat/baho qayta
// tekshiriladi (mark havolasi) — belgilangan yoki tekshirib bo'lmasa, yozilmaydi.
const els = {};
const node = id => els[id] || (els[id] = new El(id));
function parse(html) {
    for (const m of html.matchAll(/<select id="([^"]+)"[^>]*>([\s\S]*?)<\/select>/g)) {
        node(m[1]).options = [...m[2].matchAll(/<option(?: value="([^"]*)")?>([^<]*)<\/option>/g)].map(o => o[1] !== undefined ? o[1] : o[2].trim());
        els[m[1]].value = els[m[1]].options[0];
    }
    for (const m of html.matchAll(/<input([^>]*)>/g)) {
        const id = (m[1].match(/id="([^"]+)"/) || [])[1];
        if (id) node(id).checked = /\bchecked\b/.test(m[1]);
    }
    for (const m of html.matchAll(/id="([^"]+)"/g)) node(m[1]);
}
class El {
    constructor(id) { Object.assign(this, { id, style: {}, value: '', checked: false, disabled: false, textContent: '', options: [], html: '' }); }
    get innerHTML() { return this.html; }
    set innerHTML(h) { this.html = h; parse(h); }
    scrollIntoView() {}
    remove() {}
    click() {}
}
const RealDate = Date;
const ctx = {
    window: {}, location: { hostname: 'hemis.ttatf.uz', pathname: '/curriculum/schedule-info' },
    document: {
        getElementById: id => els[id] || null, createElement: () => new El(null), body: { appendChild() {} },
        querySelector: s => s.includes('csrf') ? { content: '_csrf-backend' } : null,
    },
    alert: m => { throw new Error('alert: ' + m); }, confirm: () => true, setTimeout, console, encodeURIComponent,
    Date: class extends RealDate { constructor(...a) { if (a.length) super(...a); else super('2099-11-02T10:00:00'); } },
};
vm.createContext(ctx);
vm.runInContext(writer, ctx);

const LOCK = 'https://mark.example/hemis-today-locks/7?date=2099-11-02&expires=1&signature=s';
const act = o => Object.assign({ today: true, date: '2099-11-02', start: '12:00', end: '13:20', code: '13', employee_id: 141, auditorium_code: '74', blocked: [], changes: ['xona: 73 → 74'] }, o);
const rep = {
    mode: 'today', today: '2099-11-02', hemis_pairs: { '12:00-13:20': 13, '10:00-11:20': 12 }, lock_check_url: LOCK,
    groups: [{ group: 'p/st26-01a', group_hemis_id: 1596, units: [{ subject_id: 476, subject_name: 'Kimyo', code_names: { 13: 'Amaliy' }, actions: [
        act({ op: 'update', hemis_id: 3 }),
        act({ op: 'update', hemis_id: 4, start: '10:00', end: '11:20' }),
        act({ op: 'update', hemis_id: 8, start: '10:00', end: '11:20' }),
        act({ op: 'update', hemis_id: 9, start: '10:00', end: '11:20' }),
        act({ op: 'create' }),
        act({ op: 'delete', hemis_id: 5 }),
        act({ op: 'update', hemis_id: 6, date: '2099-11-01' }),
        // Oddiy (to'liq solishtirish) amali bugunga — avvalgidek yozilmaydi.
        { op: 'update', hemis_id: 7, date: '2099-11-02', start: '12:00', end: '13:20', code: '13', employee_id: 141, auditorium_code: '74', blocked: [] },
    ] }] }],
};

(async () => {
    await els.hwFile.onchange.call({ files: [{ text: async () => JSON.stringify(rep) }] });
    assert.match(els.hwSummary.innerHTML, /Bajariladi: <b>4<\/b> ta — <span[^>]*>\+0 qo'shish<\/span>, <span[^>]*>~4 o'zgartirish<\/span>, <span[^>]*>−0 o'chirish<\/span>\. O'tkaziladi: 4 ta/);
    assert.match(els.hwSummary.innerHTML, /bugungi darslar: faqat o'zgartirish; davomat olingan yoki baho qo'yilgan darsga tegilmaydi/);
    assert.doesNotMatch(els.hwList.textContent, /Qo'shish|O'chirish|#6|#7/);

    // Yozish: 3 — tekshiruvdan o'tdi (HEMIS formasi so'raldi); 4 — shu orada davomat olindi; 8 — tekshirib bo'lmadi.
    const fetched = [];
    ctx.fetch = async (url, opt) => {
        fetched.push([url, opt && opt.credentials]);
        if (url.startsWith(LOCK)) {
            const id = url.split('ids=')[1];
            if (id === '8') throw new Error('Failed to fetch');
            return { ok: true, status: 200, json: async () => ({ locked: id === '4' ? { 4: 'davomat olingan' } : {}, failed: id === '9' ? [9] : [] }) };
        }
        return { status: 404 };
    };
    els.hwApply.onclick();
    while (!/Yozildi:/.test(els.hwList.textContent)) await new Promise(r => setTimeout(r, 20));
    // Tartib — vaqt bo'yicha (10:00 lar oldin).
    assert.deepEqual(fetched, [
        [LOCK + '&ids=4', 'omit'],
        [LOCK + '&ids=8', 'omit'],
        [LOCK + '&ids=9', 'omit'],
        [LOCK + '&ids=3', 'omit'], ['/curriculum/schedule-edit?id=3', undefined],
    ]);
    assert.match(els.hwList.textContent, /#9 \(xona: 73 → 74\)  — davomat\/baho tekshirib bo'lmadi \(HEMIS javob bermadi\) — tegilmadi/);
    assert.match(els.hwList.textContent, /#4 \(xona: 73 → 74\)  — belgilandi — tegilmadi \(davomat olingan\)/);
    assert.match(els.hwList.textContent, /#8 \(xona: 73 → 74\)  — davomat\/baho tekshirib bo'lmadi \(Failed to fetch\) — tegilmadi/);

    // Log: rad etilganlar va tegilmaganlar sababi bilan.
    let blob;
    ctx.Blob = class { constructor(parts) { blob = JSON.parse(parts[0]); } };
    ctx.URL = { createObjectURL: () => 'blob:x' };
    els.hwLog.onclick();
    const reason = id => (blob.results.find(r => r.hemis_id === id) || {}).reason;
    assert.equal(reason(4), 'belgilandi — tegilmadi (davomat olingan)');
    assert.equal(blob.results.find(r => r.hemis_id === 3).status, 'error');   // forma 404 — yozishga urinildi
    assert.deepEqual(blob.skipped.map(s => s.op + ' ' + (s.hemis_id || '') + ' ' + s.skip), [
        "create  bugungi darsga faqat o'zgartirish — qo'shish/o'chirish rad etildi",
        "delete 5 bugungi darsga faqat o'zgartirish — qo'shish/o'chirish rad etildi",
        'update 6 bugungi kun emas',
        "update 7 bugun yoki o'tgan kun",
    ]);

    // Yozish davomida yarim tun o'tdi — qolgan bugungi amallar yozilmaydi (tekshiruv ham so'ralmaydi).
    fetched.length = 0;
    const fetchBefore = ctx.fetch;
    ctx.fetch = async (url, opt) => {
        const r = await fetchBefore(url, opt);
        ctx.Date = class extends RealDate { constructor(...a) { if (a.length) super(...a); else super('2099-11-03T00:01:00'); } };
        return r;
    };
    els.hwApply.onclick();
    while (!/Yozildi:/.test(els.hwList.textContent)) await new Promise(r => setTimeout(r, 20));
    assert.deepEqual(fetched, [[LOCK + '&ids=4', 'omit']]);
    assert.match(els.hwList.textContent, /#8 \(xona: 73 → 74\)  — bugungi kun emas, yozilmadi/);
    ctx.fetch = fetchBefore;

    // Ertasi kun (oyna ochiq qolgan) — bugungi amallar endi yozilmaydi.
    ctx.Date = class extends RealDate { constructor(...a) { if (a.length) super(...a); else super('2099-11-03T08:00:00'); } };
    fetched.length = 0;
    els.hwApply.onclick();
    await new Promise(r => setTimeout(r, 50));
    assert.deepEqual(fetched, []);
    assert.match(els.hwSummary.innerHTML, /Bajariladi: <b>0<\/b>/);
    console.log('HEMIS writer: bugungi darslar — faqat o\'zgartirish, davomat/baho qayta tekshiruvi');
})().catch(e => { console.error(e); process.exit(1); });
