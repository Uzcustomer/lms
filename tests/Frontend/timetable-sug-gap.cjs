const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');

// Takliflar: yorliqlar ("Darsni surish" — faqat kun o'zgarmaydigan; boshqa kunga — "To'siqni boshqa kunga") va bo'shliq belgisi.
const a = source.indexOf('            const SUG_LABEL = ');
const b = source.indexOf('            // Takliflar oynasi ichidagi xabar', a);
assert(a > 0 && b > a);
const ctx = {};
vm.createContext(ctx);
vm.runInContext(source.slice(a, b) + '; Object.assign(this, { SUG_LABEL, sugGapBadge });', ctx);
assert.equal(ctx.SUG_LABEL.blocker, 'Darsni surish');
assert.equal(ctx.SUG_LABEL.blocker_day, "To'siqni boshqa kunga");
assert.equal(ctx.SUG_LABEL.slot, 'Shu kun, boshqa vaqt');
assert.equal(ctx.SUG_LABEL.pair, 'Ikkalasini ketma-ket');
assert.match(ctx.sugGapBadge(0), /background:#dcfce7;color:#166534">bo'shliqsiz</);
assert.match(ctx.sugGapBadge(-2), /bo'shliqsiz/);
assert.match(ctx.sugGapBadge(1), /background:#fef9c3;color:#854d0e">\+½ para bo'shliq</);
assert.match(ctx.sugGapBadge(2), />\+1 para bo'shliq</);
assert.match(ctx.sugGapBadge(3), />\+1½ para bo'shliq</);
assert.equal(ctx.sugGapBadge(undefined), '');
// Taklif qatorida belgi sarlavhadan keyin.
assert.match(source, /esc\(s\.title\) \+ sugGapBadge\(s\.gap\)/);
console.log('timetable sug gap: 3 scenarios passed');
