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
// Faqat ko'rish rejimida ham: panel belgisi bosilsa — tafsilot oynasi (har to'qnashuv alohida qatorda, guruhlar 3 ta + "+N ta").
const appended = [];
const ctx = {
    esc: s => String(s), window: { innerWidth: 1200, innerHeight: 800 }, curWeek: 9, closeCycDayPop() {},
    allCards: [{ id: 5, subject_name: 'Odam anatomiyasi', group_name: 'd1/d26-06b' }],
    cardGroups: c => c.group_names && c.group_names.length ? c.group_names : [c.group_name], cardConflict: () => 'eski matn',
    weeklyConflictItems: { 5: { 9: [
        { kind: 'teacher_busy', type: 'teacher', teacher_name: 'USTOZ', groups: ['a', 'b', 'c', 'd', 'e'], date_from: '2026-11-04', date_to: '2026-11-04', past: false, detail: 'x' },
        { kind: 'room_capacity', type: 'room', room: '215', groups: ['p/p25-02b'], date_from: '2026-11-04', date_to: '2026-11-04', past: false, detail: 'y' },
    ] } },
    document: { createElement: () => ({ style: {}, querySelector: () => ({}), offsetWidth: 100, offsetHeight: 50 }), body: { appendChild: n => appended.push(n) } },
};
vm.createContext(ctx);
vm.runInContext(section("            // ===== To'qnashuvlar: qisqa belgi, rang", '            // Ichki jadval: kun ustunini tanlash, kun oynasi va soatni surish.')
    + section('            function openWeekCfPop(el) {', '            function setupCycleMarking() {') + '; this.openWeekCfPop = openWeekCfPop;', ctx);
ctx.openWeekCfPop({ dataset: { wcf: '5' }, getBoundingClientRect: () => ({ left: 10, bottom: 10 }) });
const pop = appended[0];
assert.match(pop.innerHTML, /<div class="dp-title">Odam anatomiyasi<\/div><div class="dp-sub">d1\/d26-06b · 9-hafta · to‘qnashuvlar<\/div>/);
const lines = pop.innerHTML.match(/<li class="cf-line[^"]*">/g) || [];
assert.equal(lines.length, 2);
assert.match(pop.innerHTML, /a, b, c \+2 ta/);
assert.doesNotMatch(pop.innerHTML, /eski matn/);
console.log('timetable conflict popups: 1 scenario passed');
