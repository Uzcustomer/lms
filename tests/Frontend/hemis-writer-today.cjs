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
    // after: B (#21) A (#20) bo'shatgan joyga ko'chadi — A filtrda yo'q yoki yozilmasa, B ga tegilmaydi.
    ctx.Date = class extends RealDate { constructor(...a) { if (a.length) super(...a); else super('2099-11-02T10:00:00'); } };
    const dep = { mode: 'today', today: '2099-11-02', hemis_pairs: rep.hemis_pairs, lock_check_url: LOCK, groups: [
        { group: 'p/st26-01b', group_hemis_id: 1597, units: [{ subject_id: 1, subject_name: 'Tarix', code_names: {}, actions: [act({ op: 'update', hemis_id: 20, gseq: 1 })] }] },
        { group: 'p/st26-01a', group_hemis_id: 1596, units: [{ subject_id: 2, subject_name: 'Kimyo', code_names: {}, actions: [act({ op: 'update', hemis_id: 21, gseq: 2, after: [20], start: '10:00', end: '11:20' })] }] },
    ] };
    await els.hwFile.onchange.call({ files: [{ text: async () => JSON.stringify(dep) }] });
    assert.match(els.hwSummary.innerHTML, /Bajariladi: <b>2<\/b>/);
    els.hwGroup.value = 'p/st26-01a';
    els.hwGroup.onchange();
    assert.match(els.hwSummary.innerHTML, /Bajariladi: <b>0<\/b>/);
    els.hwGroup.value = '';
    els.hwGroup.onchange();
    // A shu orada belgilandi — B yozilmaydi (tekshiruv ham so'ralmaydi).
    const lockOf = { 20: { 20: 'davomat olingan' } };
    fetched.length = 0;
    ctx.fetch = async (url, opt) => {
        fetched.push([url, opt && opt.credentials]);
        if (url.startsWith(LOCK)) {
            const id = url.split('ids=')[1];
            return { ok: true, status: 200, json: async () => ({ locked: lockOf[id] || {}, failed: [] }) };
        }
        return { status: 404 };
    };
    els.hwApply.onclick();
    while (!/Yozildi:/.test(els.hwList.textContent)) await new Promise(r => setTimeout(r, 20));
    assert.deepEqual(fetched, [[LOCK + '&ids=20', 'omit']]);
    assert.match(els.hwList.textContent, /#21 \(xona: 73 → 74\)  — avval #20 ko'chishi kerak edi — u yozilmadi, tegilmadi/);
    // A muvaffaqiyatli yozilsa (HEMIS formasi — soxta sahifada "forma to'g'ri" o'rniga 404 xato beradi) — B ham kutadi; tekshirish
    // rejimida A ning formasi topilmasa (xato) B yana yozilmaydi.
    delete lockOf[20];
    fetched.length = 0;
    els.hwCheck.onclick();
    while (!/Tekshirildi:/.test(els.hwList.textContent)) await new Promise(r => setTimeout(r, 20));
    assert.deepEqual(fetched, [[LOCK + '&ids=20', 'omit'], ['/curriculum/schedule-edit?id=20', undefined]]);
    assert.match(els.hwList.textContent, /#21 .* avval #20 ko'chishi kerak edi/);
    // A haqiqatan yozilsa — B ham yoziladi (HEMIS formasining kichik soxtasi: tahrirlash sahifasi va saqlash).
    const opt = v => ({ value: String(v), selected: false, removeAttribute() {}, setAttribute() {} });
    const select = () => ({ options: ['13', '12', '74', '1', '2', '141', '1596', '1597'].map(opt), appendChild(o) { this.options.push(o); } });
    const form = { querySelector: q => (q.startsWith('select') ? select() : {}), getAttribute: () => null };
    ctx.DOMParser = class { parseFromString(html) {
        return html === 'FORM' ? { querySelectorAll: q => (q === 'form' ? [form] : []), createElement: () => opt('') }
            : { querySelectorAll: () => [], querySelector: () => null };
    } };
    ctx.FormData = class { append() {} set() {} };
    const posted = [];
    fetched.length = 0;
    ctx.fetch = async (url, o) => {
        fetched.push([url, o && o.credentials]);
        if (url.startsWith(LOCK)) return { ok: true, status: 200, json: async () => ({ locked: {}, failed: [] }) };
        if (o && o.method === 'POST') { posted.push(url); return { ok: true, url: '/curriculum/schedule', text: async () => 'DONE' }; }
        return { status: 200, ok: true, text: async () => 'FORM' };
    };
    els.hwApply.onclick();
    while (!/Yozildi:/.test(els.hwList.textContent)) await new Promise(r => setTimeout(r, 20));
    assert.deepEqual(posted, ['/curriculum/schedule-edit?id=20', '/curriculum/schedule-edit?id=21']);
    assert.match(els.hwList.textContent, /Yozildi: 2 ta muvaffaqiyatli, 0 ta xato/);
    console.log('HEMIS writer: bugungi darslar — faqat o\'zgartirish, davomat/baho qayta tekshiruvi, navbat (after)');
})().catch(e => { console.error(e); process.exit(1); });
