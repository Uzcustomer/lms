const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');

// Parallel bo'laklar ("Fan (a)", "(b)", "(c)"): to'plam sharti (tur, qamrov, oqim, talaba kesishuvi), "parallel: a · b · c" belgisi.
const a0 = source.indexOf('            const BOLAK_RX = ');
const fnA = source.indexOf('            // Parallel to\'plam (serverdagi bolakSiblings)', a0);
const fnB = source.indexOf('            // Ikki karta kamida bitta umumiy faol haftaga egami', fnA);
const pA = source.indexOf('            // ===== Parallel bo\'laklar ("Fan (a)"');
const pB = source.indexOf('            // Sichqoncha belgida — to\'plamning qolgan kartalari', pA);
assert(a0 > 0 && fnA > a0 && fnB > fnA && pA > 0 && pB > pA);
const base = { faculty_name: 'F', specialty_name: 'S', course: 1, oqim_label: '1-oqim', training_type: 'practice', students: 5 };
const S = 'Preklinik restavratsion stomatologiya';
const cards = [
    Object.assign({ id: 1, subject_name: S + ' (a)', group_name: '01a' }, base),
    Object.assign({ id: 2, subject_name: S + ' (b)', group_name: '01b' }, base),
    Object.assign({ id: 3, subject_name: S + ' (c)', group_names: ['01a', '01b'] }, base, { students: 10 }),
    Object.assign({ id: 4, subject_name: S + ' (d)', group_name: '01a' }, base, { training_type: 'lecture' }),
    Object.assign({ id: 5, subject_name: S + ' (e)', group_name: '01a' }, base, { oqim_label: '2-oqim' }),
];
const ctx = { cards, groupStudents: { 'F|01a': 10, 'F|01b': 10 }, esc: s => String(s), isCycleCardAny: () => false, document: { addEventListener() {} } };
vm.createContext(ctx);
vm.runInContext(source.slice(a0, source.indexOf('\n', a0)) + '\n' + source.slice(fnA, fnB) + source.slice(pA, pB)
    + '; Object.assign(this, { bolakSiblings, parallelSet, parallelBadgeHtml });', ctx);

assert.equal(ctx.bolakSiblings(cards[0], cards[1]), true);
assert.equal(ctx.bolakSiblings(cards[0], cards[2]), true);   // 01a: 5 + 10/2 = 10 ≤ 10 talaba — kesishmaydi
assert.equal(ctx.bolakSiblings(cards[0], cards[3]), false);  // boshqa tur
assert.equal(ctx.bolakSiblings(cards[0], cards[4]), false);  // boshqa oqim
// Guruhda 8 talaba: (a) 5 + (c) 5 > 8 — haqiqiy kesishuv, parallel emas.
ctx.groupStudents['F|01a'] = 8;
assert.equal(ctx.bolakSiblings(cards[0], cards[2]), false);
ctx.groupStudents['F|01a'] = 10;
assert.deepEqual(ctx.parallelSet(cards[0]).map(c => c.id), [2, 3]);
assert.match(ctx.parallelBadgeHtml(cards[1]), /data-par-ids="2,1,3"[^>]*>parallel: a · <b>b<\/b> · c<\/div>/);
assert.equal(ctx.parallelBadgeHtml(Object.assign({}, cards[0], { id: 9, subject_name: 'Anatomiya' })), '');
console.log('timetable parallel bolak: 3 scenarios passed');
