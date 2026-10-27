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
  const ctx = {
    cards, curWeek: 0, selected: null, cardLen: c => c.len_half,
    curGrid: () => ({ weeks: 15 }), activeWeeksOf: () => [], esc: s => String(s),
    subjStyle: () => '', cardTooltip: () => '', cardLabel: c => c.subject_name,
  };
  vm.createContext(ctx);
  vm.runInContext(code + '\nthis.chipHtml = chipHtml;', ctx);
  return ctx.chipHtml(cards.find(c => c.id === ids[0]), ids);
}
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
