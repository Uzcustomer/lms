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

// HEMIS solishtirish oynasi: amal/to'siq turi filtri tanlanganda shu turdagi amali yo'q fanlar, o'tkazilgan fanlar
// va HEMIS rejasida topilmagan kartochkalar ko'rsatilmaydi; filtrsiz hammasi chiqadi.
const els = {};
const ctx = {
    $: id => els[id] || (els[id] = { id, value: '1', checked: true }),
    document: { querySelectorAll: () => [], createElement: () => ({}), body: { appendChild() {} } }, scopeLabelText: () => 'Hammasi', HS_WRITER_URL: '/js/hemis-writer.js',
};
vm.createContext(ctx);
vm.runInContext(section('let hemisSyncFilter', 'async function hemisSyncRun()')
    + '; this.render = hemisSyncRender; this.filter = () => hemisSyncFilter;', ctx);

const unit = (name, actions) => ({
    subject_name: name, kafedra: 'K', codes: { 13: 'A' }, code_names: { 13: 'Amaliy' }, fixes: [], notes: [], actions, actions_later: 0,
    before: { hours_diff: { 13: 2 }, count_diff: { A: 1 } }, after: { hours_diff: { 13: 0 }, count_diff: { A: 0 } }, period: null,
});
const act = o => Object.assign({ op: 'create', date: '2099-10-05', start: '12:00', end: '13:20', code: '13', employee_name: 'USTOZ',
    auditorium_name: '423', flags: [], changes: [], blocked: [], blocks: [] }, o);
const rep = {
    today: '2099-10-01', weeks: [{ week: 1, from: '2099-09-28', to: '2099-10-04' }], current_week: 1, period_from: 1, period_to: 1,
    fallback_room: ['KM1', 'Kafedra mudiri xonasi'], groups_not_in_hemis: [],
    groups: [
        { group: 'G-A', units: [unit('Anatomiya', [act({ blocked: ['xona topilmadi'] })]), unit('Fiziologiya', [act({ op: 'update' })])],
            skipped: [{ subject_name: 'Lotin tili', reason: 'LMS doskasida kartochka yo\'q' }], cards_without_subject: ['Eski fan'] },
        { group: 'G-B', units: [unit('Biologiya', [act({})])],
            skipped: [{ subject_name: 'Kimyo', reason: 'sikl fani — LMS ichki sikl jadvalida joylanmagan' }], cards_without_subject: [] },
    ],
};
const html = () => els.hemisSyncModal.innerHTML;
// Fanlar filtri ro'yxati barcha nomlarni sanaydi — faqat jadval qatorlari, guruh sarlavhalari va ogohlantirishlar tekshiriladi.
const shown = () => {
    const h = html();
    return [...h.matchAll(/<h4[^>]*>([^<]*)/g)].map(m => 'guruh:' + m[1].trim())
        .concat([...h.matchAll(/<tr class="align-top"><td class="border px-1">([^<]*)/g)].map(m => 'fan:' + m[1]))
        .concat([...h.matchAll(/<tr class="text-gray-500"><td class="border px-1">([^<]*)/g)].map(m => 'o\'tkazilgan:' + m[1]))
        .concat([...h.matchAll(/rejasida topilmagan kartochkalar: ([^<]*)/g)].map(m => 'kartochka:' + m[1]));
};

// 1) Filtrsiz: hamma fanlar, o'tkazilganlar va topilmagan kartochkalar ko'rinadi.
ctx.render(rep);
assert.deepEqual(shown(), ['guruh:G-A', 'guruh:G-B', 'fan:Anatomiya', 'fan:Fiziologiya', 'fan:Biologiya',
    'o\'tkazilgan:Lotin tili', 'o\'tkazilgan:Kimyo', 'kartochka:Eski fan']);
assert(!html().includes('tanlangan turdagi amal yo'));

// 2) Faqat to'siqlilar: faqat to'siqli amali bor fan qoladi; o'tkazilgan fanlar, topilmagan kartochkalar va
// to'siqsiz guruh yashiriladi; yig'indida yashirilgan fanlar soni.
ctx.filter().ops = { create: false, update: false, delete: false, blocked: true };
ctx.render(rep);
assert.deepEqual(shown(), ['guruh:G-A', 'fan:Anatomiya']);
assert.match(html(), /2 ta fanda tanlangan turdagi amal yo'q \(yashirildi\)/);

// 3) To'siq turi bo'yicha: "Boshqa" o'chirilsa, xona topilmagan (boshqa) to'siqli fan ham yashiriladi.
ctx.filter().blk = { room: true, teacher: true, other: false };
ctx.render(rep);
assert.deepEqual(shown(), []);
console.log('3 HEMIS compare filter render scenarios passed');
