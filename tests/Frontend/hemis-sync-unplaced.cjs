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

// HEMIS solishtirish oynasi: joylanmagan kartalar (HEMIS'ga yozilmaydi) — tepada sariq ogohlantirish va "Ro'yxat";
// guruh filtri bo'yicha sanaladi; JSON (HEMIS'ga yozish) bosilganda tasdiq so'raladi.
const els = {};
let blob = null;
const asked = [];
let answer = false;
let listHidden = true;
const ctx = {
    $: id => els[id] || (els[id] = { id, value: '1', checked: true }),
    document: { querySelectorAll: () => [], createElement: () => ({ click() {}, remove() {} }), body: { appendChild() {} }, addEventListener() {} },
    scopeLabelText: () => 'Hammasi', HS_WRITER_URL: '/js/hemis-writer.js',
    Blob, URL: { createObjectURL: b => { blob = b; return 'blob:test'; } },
    confirm: msg => { asked.push(msg); return answer; },
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
const group = name => ({ group: name, units: [unit('Anatomiya')], skipped: [], cards_without_subject: [] });
const rep = {
    today: '2099-10-01', weeks: [{ week: 1, from: '2099-09-28', to: '2099-10-04' }], current_week: 1, period_from: 1, period_to: 1,
    fallback_room: ['KM1', 'Kafedra mudiri xonasi'], groups_not_in_hemis: [],
    groups: [group('G-A'), group('G-B')],
    unplaced: {
        weekly: { count: 4, items: [
            // Oqim ma'ruzasi ikki guruhda — bir marta sanaladi
            { group: 'G-A', card_id: 7, subject: 'Biologiya', type: 'lecture', weeks: [2, 3, 4] },
            { group: 'G-B', card_id: 7, subject: 'Biologiya', type: 'lecture', weeks: [2, 3, 4] },
            { group: 'G-A', card_id: 8, subject: 'Anatomiya', type: 'practice', weeks: [6] },
        ] },
        cycle: { count: 1, items: [{ group: 'G-B', key: 'r1|ped|practice', subject: 'Pediatriya', type: 'practice', days: 5 }] },
    },
};
const html = () => els.hemisSyncModal.innerHTML;
const banner = () => (/<div id="hsUnplacedBanner"[^>]*>([\s\S]*?)<button/.exec(html()) || [])[1];

// 1) Ogohlantirish tepada, filtrlardan oldin: haftalik karta-haftalar va sikl kartalari.
ctx.render(rep);
assert.equal(banner(), '⚠ Hali joylanmagan kartalar bor: haftalik — 4 ta karta-hafta, sikl — 1 ta karta. Ular HEMIS\'ga yozilmaydi. ');
assert(html().indexOf('hsUnplacedBanner') < html().indexOf('id="hsOnlyDiff"'), 'ogohlantirish filtrlardan keyin chiqdi');
// "Ro'yxat" — guruh va fan bo'yicha; sukutda yopiq, bosilganda ochiladi.
assert.match(html(), /<div id="hsUnplacedList" class="mt-1 hidden"><div><b>G-A<\/b>: Anatomiya \(amaliy\): 6-hafta; Biologiya \(ma'ruza\): 2–4-hafta<\/div><div><b>G-B<\/b>: Biologiya \(ma'ruza\): 2–4-hafta; Pediatriya \(amaliy, sikl\): 5 kun<\/div><\/div>/);
ctx.$('hsUnplacedList').classList = { toggle: (cls, on) => { listHidden = on; } };
els.hsUnplacedToggle.onclick();
assert.equal(listHidden, false);

// 2) JSON: joylanmagan kartalar bor — tasdiq so'raladi; rad etilsa fayl yaratilmaydi, rozi bo'linsa yaratiladi.
els.hsJson.onclick();
assert.deepEqual(asked, ['3 ta joylanmagan karta bor — ular HEMIS\'ga yozilmaydi. Baribir davom etamizmi?']);
assert.equal(blob, null);
answer = true;
els.hsJson.onclick();
assert.equal(asked.length, 2);
assert.notEqual(blob, null);

// 3) Guruh filtri: faqat G-B — ogohlantirish va tasdiq shu guruh bo'yicha.
ctx.filter().groups = ['G-B'];
ctx.render(rep);
assert.match(html(), /id="hsUnplacedList" class="mt-1">/, 'qayta chizishda ro\'yxat yopilib qoldi');
assert.equal(banner(), '⚠ Hali joylanmagan kartalar bor: haftalik — 3 ta karta-hafta, sikl — 1 ta karta. Ular HEMIS\'ga yozilmaydi. ');
els.hsJson.onclick();
assert.equal(asked[2], '2 ta joylanmagan karta bor — ular HEMIS\'ga yozilmaydi. Baribir davom etamizmi?');

// 4) Joylanmagan karta yo'q (yoki filtrdagi guruhlarda yo'q) — ogohlantirish yo'q, tasdiq so'ralmaydi.
ctx.filter().groups = ['G-A'];
ctx.render(Object.assign({}, rep, { unplaced: { weekly: { count: 0, items: [] }, cycle: { count: 0, items: [] } } }));
assert(!html().includes('hsUnplacedBanner'));
els.hsJson.onclick();
assert.equal(asked.length, 3);
ctx.filter().groups = ['G-X'];
ctx.render(rep);
assert(!html().includes('hsUnplacedBanner'));

console.log('4 HEMIS compare unplaced-cards warning scenarios passed');
