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

// HEMIS solishtirish oynasi: darslari tugagan o'tkazilgan fanlar (kind: finished) sukutda yashirin va sanoqda ko'rinadi;
// joylanmagan sikl (unplaced) va kartochkasi yo'q (no_card) fanlar — haqiqiy muammo, doim ko'rinadi.
const els = {};
let blob = null;
const ctx = {
    $: id => els[id] || (els[id] = { id, value: '1', checked: true }),
    document: { querySelectorAll: () => [], createElement: () => ({ click() {}, remove() {} }), body: { appendChild() {} }, addEventListener() {} },
    scopeLabelText: () => 'Hammasi', HS_WRITER_URL: '/js/hemis-writer.js',
    Blob, URL: { createObjectURL: b => { blob = b; return 'blob:test'; } },
};
vm.createContext(ctx);
vm.runInContext(section('let hemisSyncFilter', 'async function hemisSyncRun()')
    + '; this.render = hemisSyncRender; this.filter = () => hemisSyncFilter;', ctx);

const unit = name => ({
    subject_name: name, kafedra: 'K', codes: { 13: 'A' }, code_names: { 13: 'Amaliy' }, fixes: [], notes: [], actions_later: 0,
    actions: [{ op: 'create', date: '2099-10-05', start: '12:00', end: '13:20', code: '13', employee_name: 'USTOZ', auditorium_name: '423',
        flags: [], changes: [], blocked: [], blocks: [] }],
    before: { hours_diff: { 13: 2 }, count_diff: { A: 1 } }, after: { hours_diff: { 13: 0 }, count_diff: { A: 0 } }, period: null,
});
const sk = (name, kind) => ({ subject_name: name, kind, reason: 'sabab: ' + kind });
const rep = {
    today: '2099-10-01', weeks: [{ week: 1, from: '2099-09-28', to: '2099-10-04' }], current_week: 1, period_from: 1, period_to: 1,
    fallback_room: ['KM1', 'Kafedra mudiri xonasi'], groups_not_in_hemis: [],
    groups: [
        { group: 'G-A', units: [unit('Anatomiya')], skipped: [sk('Fiziologiya', 'finished'), sk('Pediatriya', 'unplaced')], cards_without_subject: [] },
        // Faqat tugagan fanli guruh — sarlavhasi va jadvali ham chiqmaydi
        { group: 'G-B', units: [], skipped: [sk('Biologiya', 'finished')], cards_without_subject: [] },
        { group: 'G-C', units: [], skipped: [sk('Kimyo', 'no_card')], cards_without_subject: [] },
    ],
};
const html = () => els.hemisSyncModal.innerHTML;
const shown = () => {
    const h = html();
    return [...h.matchAll(/<h4[^>]*>([^<]*)/g)].map(m => 'guruh:' + m[1].trim())
        .concat([...h.matchAll(/<tr class="align-top"><td class="border px-1">([^<]*)/g)].map(m => 'fan:' + m[1]))
        .concat([...h.matchAll(/<tr class="text-gray-500"><td class="border px-1">([^<]*)/g)].map(m => 'o\'tkazilgan:' + m[1]));
};
const json = () => { els.hsJson.onclick(); return blob.text(); };

(async () => {
    // 1) Sukut: "Tugagan fanlar" belgisi olingan — tugaganlar yashirin, sanoqda; joylanmagan va kartochkasi yo'q — ko'rinadi.
    ctx.render(rep);
    assert.equal(ctx.filter().finished, false);
    assert.match(html(), /id="hsFinished">/);
    assert.deepEqual(shown(), ['guruh:G-A', 'guruh:G-C', 'fan:Anatomiya', 'o\'tkazilgan:Pediatriya', 'o\'tkazilgan:Kimyo']);
    assert.match(html(), /· 2 ta tugagan fan \(yashirildi\)/);
    const before = await json();

    // 2) Belgi qo'yilsa (checkbox orqali) — hammasi ko'rinadi, sanoqda yozuv yo'q; JSON o'zgarmaydi.
    els.hsFinished.checked = true;
    els.hsFinished.onchange();
    assert.equal(ctx.filter().finished, true);
    assert.match(html(), /id="hsFinished" checked/);
    assert.deepEqual(shown(), ['guruh:G-A', 'guruh:G-B', 'guruh:G-C', 'fan:Anatomiya', 'o\'tkazilgan:Fiziologiya', 'o\'tkazilgan:Pediatriya',
        'o\'tkazilgan:Biologiya', 'o\'tkazilgan:Kimyo']);
    assert(!html().includes('tugagan fan (yashirildi)'));
    assert.equal(await json(), before);

    // 3) Fan filtri bilan birga: faqat tanlangan tugagan fanlar sanaladi.
    ctx.filter().finished = false;
    ctx.filter().subjects = ['Anatomiya', 'Fiziologiya'];
    ctx.render(rep);
    assert.deepEqual(shown(), ['guruh:G-A', 'fan:Anatomiya']);
    assert.match(html(), /· 1 ta tugagan fan \(yashirildi\)/);

    console.log('3 HEMIS compare "Tugagan fanlar" scenarios passed');
})().catch(e => { console.error(e); process.exit(1); });
