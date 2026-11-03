const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');

// Birlashgan (ketma-ket) blok belgisi: kartalar soni emas, umumiy soat (yarim-slot = 1 soat).
const a = source.indexOf('const chipHtml = (c, ids, shade) => {');
const b = source.indexOf('\n                };\n', a);
assert(a > 0 && b > a);
const code = source.slice(a, b) + '\n                };';

function chip(cards, ids) {
  const ctx = { phOf: () => null, parallelBadgeHtml: () => "",
    cards, curWeek: 0, selected: null, cardLen: c => c.len_half,
    curGrid: () => ({ weeks: 15 }), activeWeeksOf: () => [], esc: s => String(s),
    subjStyle: () => '', cardTooltip: () => '', cardLabel: c => c.subject_name, weeklyCapLine: () => '',
    TIMETABLE_ROLE: 'kafedra_mudiri', TIMETABLE_TEACHER_ID: 0, scopeMode: 'all', weeklyLayer: 'internal',
  };
  vm.createContext(ctx);
  vm.runInContext(code + '\nthis.chipHtml = chipHtml;', ctx);
  return ctx.chipHtml(cards.find(c => c.id === ids[0]), ids);
}
// O'qituvchi «Faqat meniki» ko'rinishida, aniq haftada — o'z darsida "Vaqtini o'zgartirishni so'rash" tugmasi.
function teacherChip(card, opts) {
  const ctx = Object.assign({
    phOf: () => null, parallelBadgeHtml: () => "", cards: [card], curWeek: 3, selected: null, cardLen: c => c.len_half, curGrid: () => ({ weeks: 15 }), activeWeeksOf: () => [],
    esc: s => String(s), subjStyle: () => '', cardTooltip: (c, extra) => extra, cardLabel: c => c.subject_name, weeklyCapLine: () => '',
    TIMETABLE_ROLE: 'oqituvchi', TIMETABLE_TEACHER_ID: 5, scopeMode: 'mine', weeklyLayer: 'internal',
  }, opts);
  vm.createContext(ctx);
  vm.runInContext(code + '\nthis.chipHtml = chipHtml;', ctx);
  return ctx.chipHtml(card, [card.id]);
}
const own = { id: 9, len_half: 2, training_type: 'practice', subject_name: 'Gigiyena', weeks: 15, teacher_id: 5 };
assert.match(teacherChip(own, {}), /<button type="button" class="tt-ask-time" data-ask-time="9">Vaqtini o'zgartirishni so'rash<\/button>/);
for (const o of [{ scopeMode: 'all' }, { TIMETABLE_ROLE: 'kafedra_mudiri' }, { TIMETABLE_TEACHER_ID: 6 }, { weeklyLayer: 'external' }, { curWeek: 0 }]) {
  assert.doesNotMatch(teacherChip(own, o), /data-ask-time/, JSON.stringify(o));
}
// Xona sig'imi (1.4) — ogohlantirish: xona yonida "⚠ sig'maydi", to'liq server matni sichqoncha ustida va kartaning tooltipida.
const capLine = "Xona sig'maydi: 102-xona — 10 o'rin, 12 talaba (+2)";
const capHtml = teacherChip(Object.assign({}, own, { auditorium_name: '102-xona' }), { weeklyCapLine: () => capLine });
assert.match(capHtml, /<div class="tt-room">№ 102-xona <span class="tt-cap-warn" title="Xona sig'maydi: 102-xona — 10 o'rin, 12 talaba \(\+2\)">⚠ sig'maydi<\/span><\/div>/);
assert.match(capHtml, /title="\nXona sig'maydi: 102-xona — 10 o'rin, 12 talaba \(\+2\)"/);
assert.doesNotMatch(teacherChip(Object.assign({}, own, { auditorium_name: '102-xona' }), {}), /tt-cap-warn/);
const badge = html => (/<span class="tt-merge-badge"[^>]*>([^<]*)<\/span>/.exec(html) || [])[1];
const card = (id, len) => ({ id, len_half: len, training_type: 'practice', subject_name: 'Gigiyena', weeks: 15 });

// 2 soat + 1 soat — "3 soat" ("2 para" emas); kartalar soni sichqoncha ustida.
let html = chip([card(1, 2), card(2, 1)], [1, 2]);
assert.equal(badge(html), '3 soat');
assert.match(html, /title="2 ta karta"/);
// 2 + 2 — "4 soat".
assert.equal(badge(chip([card(1, 2), card(2, 2)], [1, 2])), '4 soat');
// Bitta karta — belgi yo'q.
assert.equal(badge(chip([card(1, 2)], [1])), undefined);

console.log('timetable merge badge: 3 scenarios passed');
