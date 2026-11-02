const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');
const a = source.indexOf('            function applyTimetableAccess() {');
const b = source.indexOf('            function applyAscRoleAccess() {', a);
assert(a > 0 && b > a);

// Karta yaratish / kartalar ro'yxatini o'zgartirish tugmalari: o'qituvchi, fan mas'uli, kafedra mudiri — yashirin; o'quv bo'limi — bo'shatish bor.
const IDS = ['newBoardBtn', 'genBtn', 'refreshNamesBtn', 'delBoardBtn', 'snapshotsBtn', 'ktrWeeksBtn', 'managerBtn', 'unplaceBtn', 'cycleHolAddBtn',
    'cycleRefresh', 'assignBtn'];
function run(flags) {
    const els = {};
    IDS.forEach(id => { els[id] = { id, disabled: false, title: '', cls: new Set(), classList: { add(c) { els[id].cls.add(c); } }, closest: () => null }; });
    const ctx = Object.assign({ $: id => els[id] || null }, flags);
    vm.createContext(ctx);
    vm.runInContext(source.slice(a, b) + '; applyTimetableAccess();', ctx);
    return id => els[id].cls.has('hidden');
}
const head = run({ TIMETABLE_ASSIGNMENT_ONLY: true, TIMETABLE_CAN_PLACE: true, TIMETABLE_CAN_UNPLACE: false, TIMETABLE_CAN_USE_MANAGER: false,
    TIMETABLE_READ_ONLY: false, TIMETABLE_AUDITORIUM_ASSIGNMENT_ONLY: false });
['newBoardBtn', 'genBtn', 'refreshNamesBtn', 'delBoardBtn', 'snapshotsBtn', 'ktrWeeksBtn', 'managerBtn', 'unplaceBtn', 'cycleHolAddBtn']
    .forEach(id => assert.equal(head(id), true, 'kafedra mudiri: ' + id + ' yashirin bo\'lishi kerak'));
assert.equal(head('cycleRefresh'), false);
assert.equal(head('assignBtn'), false);
const teacher = run({ TIMETABLE_ASSIGNMENT_ONLY: true, TIMETABLE_CAN_PLACE: false, TIMETABLE_CAN_UNPLACE: false, TIMETABLE_CAN_USE_MANAGER: false,
    TIMETABLE_READ_ONLY: true, TIMETABLE_AUDITORIUM_ASSIGNMENT_ONLY: false });
['genBtn', 'snapshotsBtn', 'ktrWeeksBtn', 'unplaceBtn', 'cycleHolAddBtn', 'cycleRefresh', 'refreshNamesBtn', 'newBoardBtn']
    .forEach(id => assert.equal(teacher(id), true, "o'qituvchi: " + id));
const oquv = run({ TIMETABLE_ASSIGNMENT_ONLY: true, TIMETABLE_CAN_PLACE: true, TIMETABLE_CAN_UNPLACE: true, TIMETABLE_CAN_USE_MANAGER: true,
    TIMETABLE_READ_ONLY: false, TIMETABLE_AUDITORIUM_ASSIGNMENT_ONLY: true });
assert.equal(oquv('unplaceBtn'), false);
assert.equal(oquv('cycleHolAddBtn'), false);
assert.equal(oquv('managerBtn'), false);
assert.equal(oquv('genBtn'), true);
const admin = run({ TIMETABLE_ASSIGNMENT_ONLY: false });
IDS.forEach(id => assert.equal(admin(id), false, 'admin: ' + id));
console.log('timetable admin-only buttons: 4 scenarios passed');
