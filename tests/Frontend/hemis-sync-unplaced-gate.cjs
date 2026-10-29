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

// "HEMIS solishtirish" bosilganda eng birinchi — joylanmagan kartalar: bor bo'lsa qizil oyna, odatdagi hemisSyncAsk
// oynasi faqat "Baribir davom etish"dan keyin; "Yopish" — hech qanday so'rov ketmaydi; joylanmagan bo'lmasa (yoki xulosa
// olinmasa) qizil oyna chiqmaydi, darhol hemisSyncAsk.
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
            assert.deepEqual(body, ctx.scopeBody(), 'xulosaga qamrov (scopeBody) yuborilmadi');
            if (summary instanceof Error) throw summary;
            return summary;
        }
        throw new Error('kutilmagan so\'rov: ' + url);
    },
};
vm.createContext(ctx);
vm.runInContext(section('            let hemisSyncLast = null;', "            // ===== HEMIS to'siqlari bo'yicha so'rovlar"), ctx);
const tick = () => new Promise(r => setImmediate(r));
// Kutilgan oyna yoki so'rov hech qachon tugamasa, Node jim chiqib ketadi — test oxirigacha yetmasa yiqilsin.
process.exitCode = 1;
const html = () => (els.hemisSyncModal ? els.hemisSyncModal.innerHTML : '');
const text = h => h.replace(/<[^>]+>/g, '');
const click = async () => { await els.hemisSyncBtn.onclick(); await tick(); };
const ASK = /Shu ma'lumot bilan solishtirish/;

(async () => {
    // 1) Joylanmagan kartalar bor — eng birinchi qizil oyna; hemisSyncAsk (import holati) hali so'ralmaydi.
    summary = {
        weekly: { count: 4, items: [
            { group: 'G-B', card_id: 7, subject: 'Biologiya', type: 'lecture', weeks: [2, 3, 4] },
            { group: 'G-A', card_id: 7, subject: 'Biologiya', type: 'lecture', weeks: [2, 3, 4] },
            { group: 'G-A', card_id: 8, subject: 'Anatomiya', type: 'practice', weeks: [6] },
        ] },
        cycle: { count: 1, items: [{ group: 'G-A', key: 'r1|ped|practice', subject: 'Pediatriya', type: 'practice', days: 5 }] },
    };
    await click();
    assert.deepEqual(calls, ['/unplaced-summary']);
    assert.match(html(), /<div id="hsUnpBox" class="[^"]*border-red-400 bg-red-50 text-red-800/);
    assert.match(html(), /⛔ Hali joylanmagan kartalar bor/);
    assert.equal(text(/<div id="hsUnpText"[^>]*>(.*?)<\/div>/.exec(html())[1]),
        'Haftalik — 4 ta karta-hafta, sikl — 1 ta karta. Ular HEMIS\'ga yozilmaydi, HEMIS\'da dars kam qoladi.');
    // Ro'yxat — natija oynasidagi banner bilan bir xil format (guruh bo'yicha), yig'iladigan.
    assert.match(html(), /<details open[^>]*>.*<div id="hsUnpList"[^>]*><div><b>G-A<\/b>: Anatomiya \(amaliy\): 6-hafta; Biologiya \(ma'ruza\): 2–4-hafta; Pediatriya \(amaliy, sikl\): 5 kun<\/div><div><b>G-B<\/b>: Biologiya \(ma'ruza\): 2–4-hafta<\/div><\/div><\/details>/);
    assert.deepEqual([...html().matchAll(/<button type="button" id="(hsUnp\w+)"[^>]*>([^<]*)</g)].map(m => m[1] + ':' + m[2]),
        ['hsUnpGo:Baribir davom etish', 'hsUnpExcel:Excel (sabablar)', 'hsUnpClose:Yopish']);
    assert(!ASK.test(html()));

    // "Baribir davom etish" — shundan keyingina odatdagi hemisSyncAsk oynasi.
    els.hsUnpGo.onclick();
    await tick();
    assert.deepEqual(calls, ['/unplaced-summary', '/hemis-sync/import-status']);
    assert.match(html(), ASK);

    // 2) "Yopish" — hech qanday so'rov ketmaydi, oyna yopiladi.
    calls.length = 0;
    await click();
    els.hsUnpClose.onclick();
    await tick();
    assert.deepEqual(calls, ['/unplaced-summary']);
    assert.equal(els.hemisSyncModal, undefined, 'oyna yopilmadi');

    // 3) Faqat sikl kartalari — "Excel (sabablar)" (haftalik kartalar diagnostikasi) bo'sh fayl berardi, ko'rsatilmaydi.
    summary = { weekly: { count: 0, items: [] }, cycle: { count: 1, items: [{ group: 'G-A', key: 'k', subject: 'Pediatriya', type: 'practice', days: 5 }] } };
    await click();
    assert.match(html(), /Haftalik — 0 ta karta-hafta, sikl — 1 ta karta/);
    assert(!html().includes('hsUnpExcel'));
    els.hsUnpClose.onclick();

    // 4) Joylanmagan karta yo'q — qizil oyna chiqmaydi, darhol hemisSyncAsk.
    calls.length = 0;
    modals.length = 0;
    summary = { weekly: { count: 0, items: [] }, cycle: { count: 0, items: [] } };
    await click();
    assert.deepEqual(calls, ['/unplaced-summary', '/hemis-sync/import-status']);
    assert(!modals.some(h => h.includes('Hali joylanmagan')), 'joylanmagan karta yo\'q, lekin qizil oyna chiqdi');
    assert.match(html(), ASK);

    // 5) Xulosa olinmadi — konsolga yoziladi va to'g'ridan-to'g'ri hemisSyncAsk.
    calls.length = 0;
    summary = new Error('HTTP 500');
    await click();
    assert.deepEqual(calls, ['/unplaced-summary', '/hemis-sync/import-status']);
    assert.equal(warned.length, 1);
    assert.match(warned[0], /HTTP 500/);
    assert.match(html(), ASK);

    process.exitCode = 0;
    console.log('5 HEMIS compare unplaced-cards red gate scenarios passed');
})().catch(e => { console.error(e); process.exit(1); });
