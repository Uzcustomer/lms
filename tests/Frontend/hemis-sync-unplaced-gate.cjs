const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');
function section(from, to) {
    const a = source.indexOf(from), b = source.indexOf(to, a);
    assert(a >= 0 && b > a);
    return source.slice(a, b);
}

// "HEMIS solishtirish": solishtirishdan oldin joylanmagan kartalar bo'lsa — alohida ogohlantirish oynasi.
// "Bekor qilish" solishtirish so'rovini yubormaydi; joylanmagan bo'lmasa (yoki xulosa olinmasa) oyna chiqmaydi;
// hafta/davr o'zgartirilib qayta ishga tushirilganda ogohlantirish qayta chiqmaydi.
const els = {};
const modals = [];   // hemisSyncModal oynasiga yozilgan har bir HTML
const mk = id => {
    const el = { id, value: '1', checked: true, classList: { add() {}, remove() {}, toggle() {} }, remove() { delete els[id]; } };
    if (id === 'hemisSyncModal') {
        let h = '';
        Object.defineProperty(el, 'innerHTML', { get: () => h, set: v => { h = v; modals.push(v); } });
    }
    return el;
};
const calls = [];
let summary = null;     // unplaced-summary javobi; Error — so'rov xatosi
const warned = [];
const rep = { today: '2099-10-01', weeks: [], current_week: 1, period_from: 1, period_to: 1, until_week: 1,
    fallback_room: ['KM1', 'Kafedra mudiri xonasi'], groups_not_in_hemis: [], groups: [] };
const ctx = {
    $: id => els[id] || (els[id] = mk(id)),
    document: { querySelectorAll: () => [], createElement: () => mk('x'), body: { appendChild() {} }, addEventListener() {} },
    scopeLabelText: () => 'Qamrov', scopeBody: () => ({ faculty_names: ['F1'], specialty_names: ['davolash ishi'], courses: [1] }),
    HS_WRITER_URL: '/js/hemis-writer.js', BASE: '/admin', board: { id: 7 }, location: { search: '' },
    console: { warn: (...a) => warned.push(a.join(' ')) },
    api: async (url, method, body) => {
        calls.push(url.replace('/admin/boards/7', ''));
        if (url.endsWith('/import-status')) return { running: false, job: null, last_import_at: null };
        if (url.endsWith('/unplaced-summary')) {
            if (summary instanceof Error) throw summary;
            return summary;
        }
        return rep;
    },
};
vm.createContext(ctx);
vm.runInContext(section('            let hemisSyncLast = null;', "            // ===== HEMIS to'siqlari bo'yicha so'rovlar")
    + '; this.run = hemisSyncRun; this.resetWarned = () => { hemisSyncWarned = false; };', ctx);
const tick = () => new Promise(r => setImmediate(r));
// Kutilgan oyna yoki so'rov hech qachon tugamasa, Node jim chiqib ketadi — test oxirigacha yetmasa yiqilsin.
process.exitCode = 1;
const html = () => (els.hemisSyncModal ? els.hemisSyncModal.innerHTML : '');
const text = h => h.replace(/<[^>]+>/g, '');

(async () => {
    // 1) Joylanmagan kartalar bor — oyna chiqadi; "Bekor qilish" solishtirish so'rovini yubormaydi.
    summary = {
        weekly: { count: 3, items: [{ group: 'G-B', subject: 'Biologiya', type: 'lecture', weeks: [2, 3, 4] }] },
        cycle: { count: 1, items: [{ group: 'G-A', subject: 'Pediatriya', days: 5 }] },
    };
    let run = ctx.run();
    await tick();
    assert.match(html(), /⚠ Hali joylanmagan kartalar bor/);
    assert.equal(text(/<div id="hsUnpText"[^>]*>(.*?)<\/div>/.exec(html())[1]),
        'Haftalik: 3 ta karta-hafta · Sikl: 1 ta karta. Ular HEMIS\'ga yozilmaydi, HEMIS\'da dars kam qoladi.');
    assert.deepEqual([...html().matchAll(/<div><b>([^<]*)<\/b> · ([^<]*)<\/div>/g)].map(m => m[1] + ' · ' + m[2]),
        ['G-A · Pediatriya (sikl): 5 kun', 'G-B · Biologiya (ma\'ruza): 2–4-hafta']);
    assert.match(html(), /id="hsUnpGo"[^>]*>Mayli, solishtirish</);
    assert.match(html(), /id="hsUnpExcel"[^>]*>Excel \(sabablar\)</);
    els.hsUnpCancel.onclick();
    await run;
    assert.deepEqual(calls, ['/unplaced-summary'], '"Bekor qilish"dan keyin solishtirish so\'rovi yuborildi');
    assert.equal(els.hemisSyncModal, undefined, 'oyna yopilmadi');

    // 2) "HEMIS solishtirish" qayta bosilsa — ogohlantirish yana chiqadi; "Mayli, solishtirish" — solishtirish so'rovi
    // va odatdagi natija oynasi.
    calls.length = 0;
    await els.hemisSyncBtn.onclick();
    els.hsUseLast.onclick();
    await tick();
    assert.match(html(), /⚠ Hali joylanmagan kartalar bor/);
    els.hsUnpGo.onclick();
    for (let i = 0; i < 5; i++) await tick();
    assert.deepEqual(calls, ['/hemis-sync/import-status', '/unplaced-summary', '/hemis-sync']);
    assert.match(html(), /HEMIS solishtirish · Qamrov/);

    // 3) Hafta/davr o'zgartirilib qayta ishga tushirilganda ogohlantirish qayta chiqmaydi.
    calls.length = 0;
    modals.length = 0;
    await ctx.run();
    assert.deepEqual(calls, ['/hemis-sync']);
    assert(!modals.some(h => h.includes('Hali joylanmagan')));

    // 4) Joylanmagan karta yo'q — oyna chiqmaydi, darhol solishtiriladi.
    ctx.resetWarned();
    calls.length = 0;
    modals.length = 0;
    summary = { weekly: { count: 0, items: [] }, cycle: { count: 0, items: [] } };
    await ctx.run();
    assert.deepEqual(calls, ['/unplaced-summary', '/hemis-sync']);
    assert(!modals.some(h => h.includes('Hali joylanmagan')), 'joylanmagan karta yo\'q, lekin oyna chiqdi');

    // 5) Xulosa olinmadi — ogohlantirishsiz solishtiriladi, konsolga yoziladi.
    ctx.resetWarned();
    calls.length = 0;
    summary = new Error('HTTP 500');
    await ctx.run();
    assert.deepEqual(calls, ['/unplaced-summary', '/hemis-sync']);
    assert.equal(warned.length, 1);
    assert.match(warned[0], /HTTP 500/);

    // Faqat sikl kartalari — "Excel (sabablar)" (haftalik kartalar diagnostikasi) bo'sh fayl berardi, ko'rsatilmaydi.
    ctx.resetWarned();
    summary = { weekly: { count: 0, items: [] }, cycle: { count: 1, items: [{ group: 'G-A', subject: 'Pediatriya', days: 5 }] } };
    run = ctx.run();
    await tick();
    assert.match(html(), /Haftalik: 0 ta karta-hafta · Sikl: 1 ta karta/);
    assert(!html().includes('hsUnpExcel'), 'faqat sikl kartalari bo\'lsa ham "Excel (sabablar)" chiqdi');
    els.hsUnpCancel.onclick();
    await run;

    // 6) Uzun ro'yxat — birinchi 30 qator, keyin "yana K ta".
    ctx.resetWarned();
    summary = { weekly: { count: 35, items: Array.from({ length: 35 }, (_, i) => ({ group: 'G' + String(i).padStart(2, '0'), subject: 'Fan', type: 'practice', weeks: [2] })) },
        cycle: { count: 0, items: [] } };
    run = ctx.run();
    await tick();
    assert.equal([...html().matchAll(/<div><b>/g)].length, 30);
    assert.match(html(), /… yana 5 ta/);
    els.hsUnpCancel.onclick();
    await run;

    process.exitCode = 0;
    console.log('6 HEMIS compare unplaced-cards gate scenarios passed');
})().catch(e => { console.error(e); process.exit(1); });
