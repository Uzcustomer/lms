const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const writer = fs.readFileSync(path.join(__dirname, '../../public/js/hemis-writer.js'), 'utf8');

// Xatcho'p: LMS'da joylanmagani uchun o'chirishlar (reason_code lms_unplaced) — "HEMIS'ga yozish"da birinchi amaldan oldin BIR MARTA
// oyna (uch tugma); tanlovdan keyin jarayon savolsiz. Boshqa o'chirishlar oynaga kirmaydi; bunday o'chirish yo'q — avvalgi tasdiq.
const unesc = s => s.replace(/&quot;/g, '"').replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
const els = {};
const node = id => els[id] || (els[id] = new El(id));
function parse(html) {
    for (const m of html.matchAll(/<select id="([^"]+)"[^>]*>([\s\S]*?)<\/select>/g)) {
        node(m[1]).options = [...m[2].matchAll(/<option(?: value="([^"]*)")?>([^<]*)<\/option>/g)].map(o => o[1] !== undefined ? unesc(o[1]) : unesc(o[2]).trim());
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
}
const confirms = [], fetched = [];
const ctx = {
    window: {}, location: { hostname: 'hemis.ttatf.uz', pathname: '/curriculum/schedule-info' },
    document: {
        getElementById: id => els[id] || null, createElement: () => new El(null), body: { appendChild() {} },
        querySelector: s => s.includes('csrf') ? { content: '_csrf-backend' } : null,
    },
    alert: m => { throw new Error('alert: ' + m); }, confirm: m => { confirms.push(m); return true; }, setTimeout, console,
    // Har amal bitta so'rov: 404 — o'chirishda "allaqachon yo'q" (ok), tahrirda xato; yozilmaydi, faqat qaysi amal bajarilgani ko'rinadi.
    fetch: url => { fetched.push(url); return Promise.resolve({ status: 404 }); },
};
vm.createContext(ctx);
vm.runInContext(writer, ctx);

const act = o => Object.assign({ start: '08:30', end: '09:50', code: '13', employee_id: 141, auditorium_code: '438', blocked: [], changes: [] }, o);
const mkRep = actions => ({ today: '2099-10-27', hemis_pairs: { '08:30-09:50': 11 }, weeks: [],
    groups: [{ group: 'p/st25-05b', group_hemis_id: 1596, units: [{ subject_id: 16, subject_name: 'Gigiyena', code_names: { 13: 'Amaliy' }, actions }] }] });
const withUnplaced = mkRep([
    act({ op: 'update', hemis_id: 100, date: '2099-10-28' }),
    act({ op: 'delete', hemis_id: 201, date: '2099-11-04', reason_code: 'lms_unplaced', reason: 'LMSda joylanmagan (10-hafta)', week: 10 }),
    act({ op: 'delete', hemis_id: 202, date: '2099-11-11', reason_code: 'lms_unplaced', reason: 'LMSda joylanmagan (11-hafta)', week: 11 }),
    act({ op: 'delete', hemis_id: 203, date: '2099-10-29', reason_code: 'week_surplus', reason: '9-hafta: KTR 1 dars, HEMIS 2 — ortiqcha' }),
]);
const tick = () => new Promise(r => setTimeout(r, 20));
// Oyna javobsiz kutib qolsa (va'da hal bo'lmasa) Node jim chiqib ketadi — test oxirigacha yetmagani xato hisoblanadi.
let completed = false;
process.on('exit', () => { if (!completed) { console.error('test oxirigacha yetmadi (kutib qolgan oyna?)'); process.exitCode = 1; } });
const finished = async () => { while (!/Yozildi:|Tekshirildi:/.test(els.hwList.textContent)) await tick(); };
const ids = () => fetched.map(u => (u.match(/id=(\d+)/) || [])[1] || 'create');
const load = async rep => { await els.hwFile.onchange.call({ files: [{ text: async () => JSON.stringify(rep) }] }); confirms.length = 0; fetched.length = 0; };

(async () => {
    // 1) count > 0: oddiy tasdiq o'rniga bitta oyna — birinchi amaldan oldin (hali hech narsa so'ralmagan, bajarilmagan).
    await load(withUnplaced);
    const p1 = els.hwApply.onclick();
    await tick();
    assert.equal(els.hwAsk.style.display, 'block');
    assert.match(els.hwAsk.innerHTML, /Diqqat: 2 ta dars LMSda joylanmagani uchun HEMIS'dan o'chiriladi/);
    assert.match(els.hwAsk.innerHTML, /p\/st25-05b · Gigiyena · Amaliy: 10, 11-hafta — 2 ta/);
    assert.match(els.hwAsk.innerHTML, /Ha, hammasini bajar.*O'chirishlarsiz bajar.*Bekor/s);
    assert.deepEqual([confirms.length, fetched.length], [0, 0]);
    assert.equal(els.hwApply.disabled, true);
    // "O'chirishlarsiz bajar": lms_unplaced o'chirishlari bajarilmaydi, qolganlari (boshqa o'chirish ham) — savolsiz.
    els.hwAskSkip.onclick();
    await p1;
    await finished();
    assert.deepEqual(ids().sort(), ['100', '203']);
    assert.deepEqual(confirms, []);
    assert.equal(els.hwAsk.style.display, 'none');
    assert.match(els.hwList.textContent, /Tashlab o'tildi: 2 ta \(LMSda joylanmagan — o'chirishsiz tanlandi — 2\)/);

    // 2) "Ha, hammasini bajar" — barcha amallar, o'rtada savol yo'q.
    await load(withUnplaced);
    const p2 = els.hwApply.onclick();
    await tick();
    els.hwAskAll.onclick();
    await p2;
    await finished();
    assert.deepEqual(ids().sort(), ['100', '201', '202', '203']);
    assert.deepEqual(confirms, []);
    assert.doesNotMatch(els.hwList.textContent, /Tashlab o'tildi/);

    // 3) "Bekor" — hech narsa bajarilmaydi.
    await load(withUnplaced);
    els.hwList.textContent = '';
    const p3 = els.hwApply.onclick();
    await tick();
    els.hwAskCancel.onclick();
    await p3;
    await tick();
    assert.deepEqual([fetched.length, confirms.length], [0, 0]);
    assert.match(els.hwList.textContent, /Bekor qilindi — hech narsa bajarilmadi/);
    assert.equal(els.hwApply.disabled, false);

    // 4) Tekshirish (yozmaydi) — oyna chiqmaydi.
    const p4 = els.hwCheck.onclick();
    await tick();
    assert.notEqual(els.hwAsk.style.display, 'block');
    await p4;
    await finished();
    assert.equal(els.hwAsk.style.display, 'none');
    assert.equal(fetched.length, 4);

    // 5) count = 0 (boshqa o'chirishlar — oynaga kirmaydi): avvalgidek bitta oddiy tasdiq, oyna yo'q.
    await load(mkRep([act({ op: 'update', hemis_id: 100, date: '2099-10-28' }),
        act({ op: 'delete', hemis_id: 203, date: '2099-10-29', reason_code: 'not_in_plan', reason: "LMS rejasida yo'q" })]));
    const p5 = els.hwApply.onclick();
    await p5;
    await finished();
    assert.equal(confirms.length, 1);
    assert.match(confirms[0], /^2 ta amal \(1 ta guruh\) HEMIS'ga yoziladi/);
    assert.equal(els.hwAsk.style.display, 'none');
    assert.deepEqual(ids().sort(), ['100', '203']);

    completed = true;
    console.log('HEMIS writer: LMSda joylanmagan o\'chirishlar — 5 scenarios passed');
})().catch(e => { console.error(e); process.exit(1); });
