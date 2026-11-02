const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/admin/timetable/index.blade.php'), 'utf8');
function section(from, to) {
    const a = source.indexOf(from), b = source.indexOf(to, a);
    assert(a >= 0 && b > a, 'bo\'lim topilmadi: ' + from);
    return source.slice(a, b);
}

// api(): so'rov tanasi PHP uslubida — Takliflar actions (obyektlar massivi) actions[0][op]=... bo'lib boradi ("[object Object]" emas).
const ctx = { Object, Array };
vm.createContext(ctx);
vm.runInContext(section('            function appendFormValue(fd, key, v) {', '            async function api(url, method') + '; this.appendFormValue = appendFormValue;', ctx);
const entries = [];
const fd = { append: (k, v) => entries.push([k, String(v)]) };
const body = { week: 9, card_id: 5, type: 'room', title: 'Xonani almashtirish', empty: null, none: undefined, flag: false,
    faculty_names: ['F1', 'F2'],
    actions: [{ op: 'move', card_id: 5, week: 9, day: 2, pair: 3, auditorium_code: '301' }, { op: 'teacher', card_id: 6, teacher_id: 7 }],
    nested: { a: 1, b: [1, 2] } };
Object.entries(body).forEach(([k, v]) => ctx.appendFormValue(fd, k, v));
assert.deepEqual(entries, [
    ['week', '9'], ['card_id', '5'], ['type', 'room'], ['title', 'Xonani almashtirish'], ['flag', 'false'],
    ['faculty_names[]', 'F1'], ['faculty_names[]', 'F2'],
    ['actions[0][op]', 'move'], ['actions[0][card_id]', '5'], ['actions[0][week]', '9'], ['actions[0][day]', '2'], ['actions[0][pair]', '3'],
    ['actions[0][auditorium_code]', '301'],
    ['actions[1][op]', 'teacher'], ['actions[1][card_id]', '6'], ['actions[1][teacher_id]', '7'],
    ['nested[a]', '1'], ['nested[b][]', '1'], ['nested[b][]', '2'],
]);
assert(!entries.some(([, v]) => v === '[object Object]'));
assert.match(source, /Object\.entries\(body\)\.forEach\(\(\[k, v\]\) => appendFormValue\(fd, k, v\)\);/);

// Takliflar: xato — oyna ichida (alert emas), qayta hisoblangan ro'yxat ustida.
const sug = section("            // ===== Takliflar: joylanmagan kartalarni sig'dirish yo'llari", "            if ($('suggestWeekBtn')) $('suggestWeekBtn').onclick");
assert.doesNotMatch(sug, /alert\(/);
assert.match(sug, /sugNote\(body, 'Bajarilmadi \(jadval o\\'zgarmadi\): ' \+ esc\(e\.message\), 'err'\)/);
assert.match(sug, /await finish\(msgs, errs\);/);
const c2 = { document: { createElement: () => { const n = { style: {}, attrs: {}, setAttribute(k, v) { n.attrs[k] = v; } }; return n; } } };
vm.createContext(c2);
vm.runInContext(section('            function sugNote(body, html, kind) {', '            async function openWeekSuggestions(notice) {') + '; this.sugNote = sugNote;', c2);
let note = null;
const box = { querySelector: () => note, prepend: n => { note = n; } };
c2.sugNote(box, 'Bajarilmadi: 422', 'err');
assert.equal(note.innerHTML, 'Bajarilmadi: 422');
assert.equal(note.attrs.role, 'alert');
assert.match(note.style.cssText, /#fef2f2/);
c2.sugNote(box, 'Yuborildi', 'ok');   // o'sha joy yangilanadi
assert.equal(note.innerHTML, 'Yuborildi');
assert.equal(note.attrs.role, 'status');
// /data: istisnolar ixcham massiv (ov) — kartochka|hafta xaritasiga; o'qituvchi / fan mas'uli "Faqat meniki" / "Mas'ul fanlarim" —
// server faqat shu kartalarni beradi, qamrov almashsa doska qayta yuklanadi.
{
    const ld = section("                // Hafta bo'yicha istisnolar: ixcham massiv", '                // Fan rejimi (hafta almashinuvi / sikl)');
    const c3 = { j: { room_volumes: { R1: 24 }, ov: [[7, 3, 2, 5, 0, 'R1', '101'], [7, 5, null, null, 1, null, null]] }, overrides: null };
    vm.createContext(c3);
    vm.runInContext('let overrides; const j = this.j;' + ld + '; this.overrides = overrides;', c3);
    assert.deepEqual(JSON.parse(JSON.stringify(c3.overrides)), {
        '7|3': { day: 2, pair: 5, cancelled: false, auditorium_code: 'R1', auditorium_name: '101', auditorium_volume: 24 },
        '7|5': { day: null, pair: null, cancelled: true, auditorium_code: null, auditorium_name: null, auditorium_volume: null },
    });
    assert.match(source, /const dataScope = TIMETABLE_READ_ONLY && \(scopeMode === 'mine' \|\| scopeMode === 'subjects'\) \? '&scope=' \+ scopeMode : '';/);
    assert.match(source, /if \(TIMETABLE_READ_ONLY && board\) \{\n\s+loadBoard\(board\.id\)/);
}
console.log('timetable api form + takliflar note + /data: 4 scenarios passed');
