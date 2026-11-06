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

// HEMIS solishtirish oynasi: fan rejimi va balans davri (1–H) yig'indisi, LMS'da joylanmagan haftalar (sariq), balanslash darsi
// belgisi ("N-hafta darsi o'rniga"), chegaradan keyingi balanslash — hozir yozilmaydi, o'chirish sababi; hisobot boshida
// "Diqqat: N ta dars LMSda joylanmagani uchun HEMIS'dan o'chiriladi" va JSON meta unplaced_deletes (filtrdagidek).
const els = {};
const ctx = {
    $: id => els[id] || (els[id] = { id, value: '1', checked: true }),
    document: { querySelectorAll: () => [], createElement: () => ({}), body: { appendChild() {} }, addEventListener() {} }, scopeLabelText: () => 'Hammasi', HS_WRITER_URL: '/js/hemis-writer.js',
};
vm.createContext(ctx);
require('./helpers/timetable-info-only.cjs')(ctx);
vm.runInContext(section('let hemisSyncFilter', 'async function hemisSyncRun()')
    + '; this.render = hemisSyncRender; this.filter = () => hemisSyncFilter; this.jsonOut = hsJsonOut;', ctx);

const act = o => Object.assign({ op: 'create', date: '2099-11-04', start: '08:30', end: '09:50', code: '13', employee_name: 'BOLTAYEV J.J.',
    auditorium_name: '210', flags: [], changes: [], blocked: [], blocks: [] }, o);
const unit = (name, o) => Object.assign({
    subject_name: name, kafedra: 'K', codes: { 13: 'A' }, code_names: { 13: 'Amaliy' }, fixes: [], notes: [], actions: [], actions_later: 0,
    before: { hours_diff: { 13: 0 }, count_diff: { A: 0 } }, after: { hours_diff: { 13: 0 }, count_diff: { A: 0 } }, period: null,
    mode: 'weekly', mode_label: 'Har hafta', balance_period: '1–9-hafta',
    balance_stats: { ktr: { count: 9, hours: 24 }, hemis: { count: 8, hours: 20 }, lms: { count: 1, hours: 4 } },
}, o);
const rep = {
    today: '2099-11-06', weeks: [], current_week: 8, period_from: 1, period_to: 8, fallback_room: null, groups_not_in_hemis: [],
    unplaced_units: [{ group: 'p/st25-05b', subject: 'Gigiyena', part: 'Amaliy qism', weeks: '10–15', deletes: 2 }],
    groups: [{ group: 'p/st25-05b', until: '2099-11-15', skipped: [], cards_without_subject: [], units: [
        unit('Gigiyena', {
            unplaced_label: 'LMSda joylanmagan: 10–15-hafta — balanslanmadi (joylanishi kutilmoqda)',
            actions: [
                act({ op: 'delete', hemis_id: 501, date: '2099-11-11', reason_code: 'lms_unplaced', reason: 'LMSda joylanmagan (10-hafta)', week: 10 }),
                act({ op: 'delete', hemis_id: 502, date: '2099-11-12', reason_code: 'lms_unplaced', reason: 'LMSda joylanmagan (11-hafta)', week: 11 }),
                act({ op: 'delete', hemis_id: 503, date: '2099-11-07', reason_code: 'week_surplus', reason: '8-hafta: KTR 1 dars, HEMIS 2 — ortiqcha' }),
            ],
        }),
        unit('Lotin tili', {
            actions: [act({ date: '2099-11-07', balance: true, balance_week: 6, reason: "Balanslash — 6-hafta darsi o'rniga (…)" })],
            later_balance: [act({ date: '2099-11-17', balance: true, balance_week: 7, balance_later: true, later: true })],
        }),
    ] }],
};
const html = () => els.hemisSyncModal.innerHTML;
ctx.filter().onlyDiff = false;
ctx.render(rep);

// 1) Fan rejimi va balans davri — KTR / HEMIS / LMS (dars / soat); joylanmagan haftalar — sariq belgi.
assert.match(html(), /<div class="text-gray-500" data-hs-mode>Har hafta · balans 1–9-hafta: KTR 9 \/ 24 · HEMIS 8 \/ 20 · LMS 1 \/ 4/);
assert.match(html(), /data-hs-unplaced>LMSda joylanmagan: 10–15-hafta — balanslanmadi \(joylanishi kutilmoqda\)<\/div>/);
// 2) Hisobot boshi: "Diqqat" va ro'yxat; joylanmagan haftalar ro'yxati (o'chirishlar soni bilan).
assert.match(html(), /data-hs-unplaced-deletes>⚠ Diqqat: 2 ta dars LMSda joylanmagani uchun HEMIS'dan o'chiriladi<\/div>/);
assert.match(html(), /<li>p\/st25-05b · Gigiyena · Amaliy qism: 10–11-hafta — 2 ta<\/li>/);
assert.match(html(), /<li>p\/st25-05b · Gigiyena · Amaliy qism: 10–15-hafta <span class="text-red-700">\(HEMIS'dan o'chiriladi: 2 ta\)<\/span><\/li>/);
// 3) O'chirish sababi; balanslash belgisi; chegaradan keyingi balanslash — kulrang, "hozir yozilmaydi".
assert.match(html(), /Sabab: LMSda joylanmagan \(10-hafta\)/);
assert.match(html(), /Sabab: 8-hafta: KTR 1 dars, HEMIS 2 — ortiqcha/);
assert.match(html(), /data-hs-balance title="[^"]*">Balanslash — 6-hafta darsi o‘rniga<\/span>/);
assert.match(html(), /<div class="text-gray-400"><span class=" font-semibold">Qo'shish<\/span> · 2099-11-17 [^]*Balanslash — 7-hafta darsi o‘rniga[^]*\(hozir yozilmaydi\)/);

// 4) JSON meta: filtrdagi amallar bo'yicha (o'chirishlar o'chiq — meta 0, ogohlantirish yo'q).
let out = ctx.jsonOut(rep);
assert.deepEqual(JSON.parse(JSON.stringify(out.unplaced_deletes)), { count: 2, by_unit: [
    { group: 'p/st25-05b', subject: 'Gigiyena', part: 'Amaliy qism', weeks: '10–11', count: 2 }] });
// Chegaradan keyingi balanslash JSON amallariga kirmaydi.
assert.deepEqual([...out.groups[0].units[1].actions.map(a => a.date)], ['2099-11-07']);
ctx.filter().ops = { create: true, update: true, delete: false, blocked: true };
out = ctx.jsonOut(rep);
assert.deepEqual(JSON.parse(JSON.stringify(out.unplaced_deletes)), { count: 0, by_unit: [] });
ctx.render(rep);
assert.doesNotMatch(html(), /data-hs-unplaced-deletes/);
console.log('HEMIS compare: balans davri, balanslash belgisi, LMSda joylanmagan ogohlantirishi — 4 scenarios passed');
