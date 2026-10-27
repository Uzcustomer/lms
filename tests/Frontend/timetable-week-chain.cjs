const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');

// Shablon ko'rinishida bir fanning ketma-ket kartalari faqat bir xil haftalarda o'tilsa bitta katakka birlashadi.
const cut = (from, to) => {
  const a = source.indexOf(from), b = source.indexOf(to, a);
  assert(a > 0 && b > a, from);
  return source.slice(a, b + to.length);
};
const activeWeeks = cut('function activeWeeksOf(c, totalWeeks) {', '\n            }\n');
const chain = cut('const weeksKey = ', 'return { span: span || cardLen(c), ids: ids.length ? ids : [c.id] };\n                };');

function vChainFor(cards, cancelled, curWeek = 0) {
  const placedIdx = {}, overrides = {};
  for (const c of cards) for (let q = c.pair; q < c.pair + c.len_half; q++) placedIdx['g|1|' + q] = c;
  for (const [id, weeks] of Object.entries(cancelled)) for (const w of weeks) overrides[id + '|' + w] = { cancelled: true };
  const ctx = { placedIdx, overrides, curWeek, curGrid: () => ({ weeks: 15 }), cardLen: x => x.len_half };
  vm.createContext(ctx);
  vm.runInContext(activeWeeks + '\n' + chain + '\nthis.vChain = vChain;', ctx);
  // vm kontekstidagi obyekt — taqqoslash uchun oddiy obyektga
  return (...a) => JSON.parse(JSON.stringify(ctx.vChain(...a)));
}
const range = (a, b) => Array.from({ length: b - a + 1 }, (_, i) => a + i);
const card = (id, pair) => ({ id, pair, len_half: 2, training_type: 'practice', subject_name: 'Gigiyena' });
const full = card(1, 1), part = card(2, 3);

// Har hafta (15) va almashib keladigan (9 hafta) qism — alohida
let v = vChainFor([full, part], { 2: range(10, 15) });
assert.deepEqual(v('g', 1, 1, full), { span: 2, ids: [1] });
assert.deepEqual(v('g', 1, 3, part), { span: 2, ids: [2] });

// Haftalari bir xil — bitta katak
v = vChainFor([full, part], {});
assert.deepEqual(v('g', 1, 1, full), { span: 4, ids: [1, 2] });

// 12 va 11 haftalik qism ham alohida (10 tadan uzun ro'yxat ko'rinishda qisqartiriladi — kalit to'liq bo'lishi kerak)
v = vChainFor([full, part], { 1: range(13, 15), 2: range(12, 15) });
assert.deepEqual(v('g', 1, 1, full), { span: 2, ids: [1] });

// Aniq hafta tanlanganda — avvalgidek birlashadi
v = vChainFor([full, part], { 2: range(10, 15) }, 3);
assert.deepEqual(v('g', 1, 1, full), { span: 4, ids: [1, 2] });

console.log('timetable week chain: 4 scenarios passed');
