const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');
const a = source.indexOf('            function applyTimetableAccess() {');
const b = source.indexOf('            function applyAscRoleAccess() {', a);
assert(a > 0 && b > a);

// Karta yaratish / kartalar ro'yxatini o'zgartirish tugmalari: ro'yxat serverda ($timetableForbiddenIds — rol bo'yicha, feature testda);
// JS ularni o'chiradi va tt-forbidden qiladi (display:none !important — .toolbar-action display:inline-flex .hidden ni bekor qilmaydi).
const IDS = ['newBoardBtn', 'genBtn', 'refreshNamesBtn', 'delBoardBtn', 'snapshotsBtn', 'ktrWeeksBtn', 'managerBtn', 'unplaceBtn', 'cycleHolAddBtn',
    'cycleRefresh', 'assignBtn'];
function run(flags) {
    const els = {};
    IDS.forEach(id => { els[id] = { id, disabled: false, title: '', cls: new Set(), classList: { add(c) { els[id].cls.add(c); } }, closest: () => null }; });
    const ctx = Object.assign({ $: id => els[id] || null, TIMETABLE_FORBIDDEN_IDS: [] }, flags);
    vm.createContext(ctx);
    vm.runInContext(source.slice(a, b) + '; applyTimetableAccess();', ctx);
    return id => els[id].cls.has('hidden') && els[id].cls.has('tt-forbidden') && els[id].disabled;
}
const head = run({ TIMETABLE_ASSIGNMENT_ONLY: true, TIMETABLE_FORBIDDEN_IDS: ['newBoardBtn', 'genBtn', 'refreshNamesBtn', 'unplaceBtn', 'cycleHolAddBtn', 'missingBtn'],
    TIMETABLE_READ_ONLY: false, TIMETABLE_AUDITORIUM_ASSIGNMENT_ONLY: false });
['newBoardBtn', 'genBtn', 'refreshNamesBtn', 'unplaceBtn', 'cycleHolAddBtn'].forEach(id => assert.equal(head(id), true, id + ' yashirin va o\'chiq bo\'lishi kerak'));
['cycleRefresh', 'assignBtn', 'delBoardBtn'].forEach(id => assert.equal(head(id), false, id));
const admin = run({ TIMETABLE_ASSIGNMENT_ONLY: false, TIMETABLE_FORBIDDEN_IDS: ['genBtn'] });
IDS.forEach(id => assert.equal(admin(id), false, 'admin: ' + id));
assert.match(source, /const TIMETABLE_FORBIDDEN_IDS = @json\(\$timetableForbiddenIds\);/);
assert.match(source, /\.toolbar-action\.hidden, \.tt-forbidden \{ display: none !important; \}/);
// Sahifaga ham yashirin chiqadi (JS ishlamasa ham): har bir taqiqlanishi mumkin bo'lgan tugmada server klassi va disabled.
['newBoardBtn', 'genBtn', 'refreshNamesBtn', 'snapshotsBtn', 'delBoardBtn', 'unplaceBtn', 'ktrWeeksBtn', 'cycleHolAddBtn', 'cycleRefresh', 'cmSave'].forEach(id => {
    const tag = new RegExp('<button type="button" id="' + id + '"[^>]*>').exec(source);
    assert(tag, id);
    assert.match(tag[0], new RegExp("\\{\\{ \\$ttForbid\\('" + id + "'\\) \\}\\}\" @disabled\\(in_array\\('" + id + "', \\$timetableForbiddenIds, true\\)\\)"), id);
});
console.log('timetable admin-only buttons: 3 scenarios passed');
