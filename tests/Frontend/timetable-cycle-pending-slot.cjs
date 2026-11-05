const fs = require('node:fs');
const assert = require('node:assert/strict');
const path = require('node:path');
const vm = require('node:vm');
// Sikl kun oynasi: tasdiq kutilayotgan o'z so'rovi (pendingDaySlot) — bitta kunlik ↑ ↓ endi cycDaysApply orqali (day_indexes: [k],
// start_hour) yuboriladi; keyingi bosish so'ralgan soatdan davom etishi uchun shu so'rov ham topilishi kerak (eski day_index ham).
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');
const a = source.indexOf('            function pendingDaySlot(card, dayIndex, mode) {');
const b = source.indexOf('            function openChangeReqModal() {', a);
assert(a > 0 && b > a);
const ctx = { reqs: [] };
vm.createContext(ctx);
vm.runInContext('const pendingReqs = () => reqs;\n' + source.slice(a, b) + '\nthis.pendingDaySlot = pendingDaySlot;', ctx);
const card = { subject: 'Ichki kasalliklar', group: 'F1 / 1-oqim' };
const req = payload => ({ route: 'admin.timetable.cycle-lecture-slots', payload: Object.assign({ subject_name: card.subject, group_name: card.group }, payload) });
const find = (reqs, k, mode) => { ctx.reqs = reqs; return ctx.pendingDaySlot(card, k, mode); };

// Eski yo'l: day_index.
assert.equal(find([req({ day_index: 2, start_hour: 5, mode: 'lecture' })], 2, 'lecture').payload.start_hour, 5);
// ↑ ↓ (cycDaysApply): bitta elementli day_indexes + start_hour.
assert.equal(find([req({ day_indexes: [2], start_hour: 4, mode: 'lecture' })], 2, 'lecture').payload.start_hour, 4);
assert.equal(find([req({ day_indexes: ['2'], start_hour: 4, mode: 'practice' })], 2, 'practice').payload.start_hour, 4);
// Boshqa kun, boshqa rejim, ko'p kun yoki start_hour yo'q (delta) — topilmaydi.
assert.equal(find([req({ day_indexes: [3], start_hour: 4, mode: 'lecture' })], 2, 'lecture'), null);
assert.equal(find([req({ day_indexes: [2], start_hour: 4, mode: 'lecture' })], 2, 'practice'), null);
assert.equal(find([req({ day_indexes: [2, 3], start_hour: 4, mode: 'lecture' })], 2, 'lecture'), null);
assert.equal(find([req({ day_indexes: [2], delta: 1, mode: 'lecture' })], 2, 'lecture'), null);
// Rejimsiz qidiruv — "both" sukut.
assert.equal(find([req({ day_indexes: [2], start_hour: 6 })], 2).payload.start_hour, 6);
console.log('timetable cycle pending slot: 8 checks passed');
