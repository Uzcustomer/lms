const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');

// Sikl kun oynasi: "Vaqtini o'zgartirishni so'rash" tugmasi va kutilayotgan so'rov bosqichi.
const a = source.indexOf('            function crStageText(r)');
const b = source.indexOf('            function openChangeReqModal()', a);
assert(a > 0 && b > a);
const end = source.indexOf('            function cycCanAsk', a);
const code = source.slice(a, source.indexOf('\n            }\n', end) + 14);

function ctx(role, reqs) {
  const c = { TIMETABLE_ROLE: role, pendingReqs: () => reqs || [] };
  vm.createContext(c);
  vm.runInContext(code + '\nthis.crStageText = crStageText; this.pendingPracticeAsk = pendingPracticeAsk; this.cycCanAsk = cycCanAsk;', c);
  return c;
}
const card = { subject: 'Gigiyena', group: 'g5-01a', type: 'practice', ask: true };
const dp = { prc: 6 };
const tomorrow = { iso: '2026-09-02' };
// Tugma: o'qituvchi va fan mas'uli, server ruxsat bergan (ask) amaliy dars, ertadan.
assert.equal(ctx('oqituvchi').cycCanAsk(card, dp, tomorrow, '2026-09-01'), true);
assert.equal(ctx('fan_masuli').cycCanAsk(card, dp, tomorrow, '2026-09-01'), true);
assert.equal(ctx('fan_masuli').cycCanAsk(Object.assign({}, card, { ask: false }), dp, tomorrow, '2026-09-01'), false);
assert.equal(ctx('kafedra_mudiri').cycCanAsk(card, dp, tomorrow, '2026-09-01'), false);
assert.equal(ctx('fan_masuli').cycCanAsk(card, dp, { iso: '2026-09-01' }, '2026-09-01'), false);
assert.equal(ctx('fan_masuli').cycCanAsk(card, { prc: 0 }, tomorrow, '2026-09-01'), false);
// Bosqich yozuvi.
const c = ctx('fan_masuli');
assert.equal(c.crStageText({ approval_stage: 'department' }), 'Kafedra mudiri tasdig‘ida');
assert.equal(c.crStageText({ approval_stage: 'registrar' }), 'Registrator ofisi tasdig‘ida');
assert.equal(c.crStageText({ approval_stage: null }), 'Registrator ofisi tasdig‘ida');
// Kutilayotgan amaliy so'rovi: shu kun; "blok oxirigacha" — keyingi kunlar ham; boshqa guruh/fan — yo'q.
const r = { id: 1, status: 'pending', route: 'admin.timetable.cycle-practice-starts', approval_stage: 'department',
  payload: { subject_name: 'Gigiyena', group_name: 'g5-01a', units: ['g5-01a'], date: '2026-09-02', scope: 'rest', start_hour: 7 } };
const p = ctx('fan_masuli', [r]);
assert.equal(p.pendingPracticeAsk(card, '2026-09-02'), r);
assert.equal(p.pendingPracticeAsk(card, '2026-09-03'), r);
assert.equal(p.pendingPracticeAsk(card, '2026-09-01'), null);
assert.equal(p.pendingPracticeAsk(Object.assign({}, card, { group: 'g5-01b' }), '2026-09-02'), null);
assert.equal(ctx('fan_masuli', [Object.assign({}, r, { payload: Object.assign({}, r.payload, { scope: 'day' }) })]).pendingPracticeAsk(card, '2026-09-03'), null);
console.log('timetable cycle ask: 5 scenarios passed');
